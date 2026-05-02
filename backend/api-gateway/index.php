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
    'marketplace' => '../microservices/marketplace/marketplace_controller.php',
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

$controllerFile = realpath(__DIR__ . '/' . $services[$service]);
if (!$controllerFile || !file_exists($controllerFile)) {
    Response::error('Controlador no disponible. Ruta: ' . $services[$service], 503);
}

require $controllerFile;

