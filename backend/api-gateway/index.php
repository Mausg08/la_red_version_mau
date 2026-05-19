<?php
/**
 * API Gateway
 * Punto de entrada unico para los microservicios.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../shared/helpers.php';
require_once __DIR__ . '/../shared/jwt.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/rate_limiter.php';

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!RateLimiter::check($clientIp, 200, 60)) {
    Response::error('Demasiadas peticiones. Espera un momento.', 429);
}

$service = $_GET['service'] ?? null;
$path = trim($_GET['path'] ?? '', '/');

/**
 * Obtiene Bearer token desde varios lugares compatibles con XAMPP/Apache.
 */
function resolveBearerToken(): ?string {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!$authHeader && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }

    // Fallback para peticiones same-origin de la app.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionToken = $_SESSION['jwt_token'] ?? $_COOKIE['ul_token'] ?? null;
    if (is_string($sessionToken) && $sessionToken !== '') {
        return trim($sessionToken);
    }

    return null;
}

$services = [
    'auth'        => '../microservices/users/auth_controller.php',
    'users'       => '../microservices/users/users_controller.php',
    'feed'        => '../microservices/feed/feed_controller.php',
    'groups'      => '../microservices/groups/groups_controller.php',
    'calendar'    => '../microservices/calendar/calendar_controller.php',
    'marketplace' => '../microservices/marketplace/marketplace_controller.php',
    'lost-found'  => '../microservices/lost-found/lost_found_controller.php',
    'directory'   => '../microservices/directory/directory_controller.php',
    'polls'       => '../microservices/polls/polls_controller.php',
    'academic'    => '../microservices/academic/academic_controller.php',
    'moderation'  => '../microservices/moderation/moderation_controller.php',
];

if (!$service || !isset($services[$service])) {
    Response::error('Servicio no encontrado.', 404);
}

$publicPaths = [
    'auth/login',
    'auth/register',
    'auth/forgot-password',
    'auth/verify-email',
    'auth/reset-password',
];

$requiresAuth = !in_array($path, $publicPaths, true);

if ($requiresAuth) {
    $token = resolveBearerToken();
    if (!$token) {
        Response::error('Token de autenticacion requerido.', 401);
    }

    $payload = JWT::verify($token);
    if (!$payload) {
        Response::error('Token invalido o expirado.', 401);
    }

    if (!defined('CURRENT_USER')) {
        define('CURRENT_USER', $payload);
    }
} else {
    if (!defined('CURRENT_USER')) {
        define('CURRENT_USER', null);
    }
}

$rawBody = file_get_contents('php://input');
$jsonBody = json_decode($rawBody, true) ?? [];

if (!defined('REQUEST_BODY')) {
    define('REQUEST_BODY', $jsonBody);
}
if (!defined('REQUEST_PATH')) {
    define('REQUEST_PATH', $path);
}
if (!defined('REQUEST_METHOD')) {
    define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
}

function serviceEnvName(string $service): string {
    return strtoupper(str_replace('-', '_', $service)) . '_SERVICE_URL';
}

function proxyToService(string $service, string $path, string $rawBody): void {
    $baseUrl = rtrim((string) getenv(serviceEnvName($service)), '/');
    if ($baseUrl === '') {
        return;
    }

    $query = $_GET;
    $query['service'] = $service;
    $query['path'] = $path;
    $url = $baseUrl . '/index.php?' . http_build_query($query);

    $isMultipart = str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
    $headers = [
        'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
    ];
    if (!$isMultipart) {
        $headers[] = 'Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'application/json');
    }

    $token = resolveBearerToken();
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $_SERVER['REQUEST_METHOD'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            ? ($isMultipart ? buildMultipartPayload() : $rawBody)
            : null,
        CURLOPT_CONNECTTIMEOUT_MS => 800,
        CURLOPT_TIMEOUT_MS        => 2500,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        Response::error('El microservicio ' . $service . ' no esta disponible.', 503);
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 502;
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo $body;
    exit;
}

function buildMultipartPayload(): array {
    $payload = $_POST;
    foreach ($_FILES as $field => $fileInfo) {
        if (is_array($fileInfo['name'])) {
            foreach ($fileInfo['name'] as $index => $name) {
                if (($fileInfo['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $payload[$field . '[' . $index . ']'] = new CURLFile(
                    $fileInfo['tmp_name'][$index],
                    $fileInfo['type'][$index] ?? 'application/octet-stream',
                    $name
                );
            }
            continue;
        }
        if (($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $payload[$field] = new CURLFile(
                $fileInfo['tmp_name'],
                $fileInfo['type'] ?? 'application/octet-stream',
                $fileInfo['name']
            );
        }
    }
    return $payload;
}

if (function_exists('curl_init')) {
    proxyToService($service, $path, $rawBody);
}

$controllerFile = realpath(__DIR__ . '/' . $services[$service]);
if (!$controllerFile || !file_exists($controllerFile)) {
    Response::error('Controlador no disponible. Ruta: ' . $services[$service], 503);
}

require $controllerFile;

