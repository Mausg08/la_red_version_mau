<?php
/**
 * UniLink — api-gateway/auth.php  (VERSIÓN CORREGIDA)
 * Coloca este archivo en: backend/api-gateway/auth.php
 */
require_once __DIR__ . '/../shared/helpers.php';
require_once __DIR__ . '/../shared/jwt.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Leer body — soporta tanto JSON como GET
$input  = [];
$raw    = file_get_contents('php://input');
if ($raw) {
    $input = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $input['action'] ?? '';

// ── SET SESSION ───────────────────────────────────────────────
if ($action === 'set_session') {
    header('Content-Type: application/json; charset=UTF-8');

    $token = $input['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token vacío']);
        exit;
    }

    // Verificar el JWT con el mismo secret de helpers.php
    $payload = JWT::verify($token);

    if (!$payload) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token inválido — posible mismatch de JWT_SECRET',
            'debug'   => defined('JWT_SECRET') ? 'secret_len=' . strlen(JWT_SECRET) : 'NO_SECRET'
        ]);
        exit;
    }

    // Traer datos completos del usuario desde la BD
    require_once __DIR__ . '/../microservices/users/user_model.php';

    $dbUser = null;
    try {
        $dbUser = UserModel::findById((int)$payload['user_id']);
    } catch (Throwable $e) {
        // Si falla la BD, usar solo el payload del JWT
        error_log('[UniLink auth.php] BD error: ' . $e->getMessage());
    }

    // Construir datos de sesión — nunca dejar campos vacíos
    $sessionUser = [
        'user_id'      => (int)($dbUser['user_id']    ?? $payload['user_id']    ?? 0),
        'email'        => $dbUser['email']             ?? $payload['email']      ?? '',
        'first_name'   => $dbUser['first_name']        ?? $payload['first_name'] ?? 'Usuario',
        'last_name'    => $dbUser['last_name']         ?? $payload['last_name']  ?? '',
        'role'         => $dbUser['role']               ?? $payload['role']       ?? 'student',
        'faculty_id'   => $dbUser['faculty_id']         ?? $payload['faculty_id'] ?? null,
        'faculty_name' => $dbUser['faculty_name']       ?? '',
        'avatar'       => $dbUser['avatar']             ?? null,
        'status'       => $dbUser['status']             ?? 'active',
    ];

    $_SESSION['jwt_token']         = $token;
    $_SESSION['user']              = $sessionUser;
    $_SESSION['last_status_check'] = time();

    // Cookie "recordarme"
    if (!empty($input['remember'])) {
        setcookie('ul_token', $token, time() + (60 * 60 * 24 * 30), '/', '', false, true);
    }

    echo json_encode(['success' => true, 'user' => $sessionUser]);
    exit;
}

// ── LOGOUT ────────────────────────────────────────────────────
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    setcookie('ul_token', '', time() - 3600, '/');

    header('Location: http://localhost:8012/RedSocial_BUAP/index.php');
    exit;
}

// ── REFRESH TOKEN ─────────────────────────────────────────────
if ($action === 'refresh') {
    header('Content-Type: application/json; charset=UTF-8');
    $old     = $_SESSION['jwt_token'] ?? '';
    $payload = JWT::verify($old);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
        exit;
    }
    unset($payload['iat'], $payload['exp']);
    $new = JWT::generate($payload);
    $_SESSION['jwt_token'] = $new;
    echo json_encode(['success' => true, 'token' => $new]);
    exit;
}

// ── DEBUG (solo en development) ───────────────────────────────
if ($action === 'debug' && defined('UL_ENV') && UL_ENV === 'development') {
    header('Content-Type: application/json; charset=UTF-8');
    $token   = $_SESSION['jwt_token'] ?? '';
    $payload = $token ? JWT::verify($token) : null;
    echo json_encode([
        'session_exists'  => isset($_SESSION['user']),
        'session_user_id' => $_SESSION['user']['user_id'] ?? null,
        'jwt_valid'       => $payload !== null,
        'jwt_payload'     => $payload,
        'jwt_secret_len'  => strlen(JWT_SECRET),
        'db_host'         => DB_HOST,
        'db_port'         => DB_PORT,
    ]);
    exit;
}

http_response_code(400);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['success' => false, 'message' => 'Acción no reconocida: ' . htmlspecialchars($action)]);