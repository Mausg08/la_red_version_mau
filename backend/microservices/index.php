<?php
/**
 * Entrada HTTP para contenedores de microservicios.
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

function microserviceBearerToken(): ?string {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

$service = $_GET['service'] ?? getenv('SERVICE_NAME') ?: null;
$path = trim($_GET['path'] ?? '', '/');

$controllers = [
    'feed'        => __DIR__ . '/feed/feed_controller.php',
    'groups'      => __DIR__ . '/groups/groups_controller.php',
    'calendar'    => __DIR__ . '/calendar/calendar_controller.php',
    'marketplace' => __DIR__ . '/marketplace/marketplace_controller.php',
    'lost-found'  => __DIR__ . '/lost-found/lost_found_controller.php',
    'directory'   => __DIR__ . '/directory/directory_controller.php',
    'polls'       => __DIR__ . '/polls/polls_controller.php',
];

if (!$service || !isset($controllers[$service])) {
    Response::error('Microservicio no encontrado.', 404);
}

$token = microserviceBearerToken();
if (!$token) {
    Response::error('Token de autenticacion requerido.', 401);
}

$payload = JWT::verify($token);
if (!$payload) {
    Response::error('Token invalido o expirado.', 401);
}

define('CURRENT_USER', $payload);
define('REQUEST_BODY', json_decode(file_get_contents('php://input'), true) ?? []);
define('REQUEST_PATH', $path);
define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);

require $controllers[$service];
