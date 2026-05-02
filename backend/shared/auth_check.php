<?php
/**
 * UniLink — auth_check.php (VERSIÓN FINAL CORREGIDA)
 * Ruta: backend/shared/auth_check.php
 */

$sharedDir = __DIR__;
require_once $sharedDir . '/helpers.php';
require_once $sharedDir . '/jwt.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_SESSION['jwt_token'] ?? $_COOKIE['ul_token'] ?? null;

if (!$token) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: http://localhost/RedSocial_BUAP/index.php?redirect=' . $redirect);
    exit;
}

$payload = JWT::verify($token);

if (!$payload) {
    session_destroy();
    setcookie('ul_token', '', time() - 1, '/');
    header('Location: http://localhost/RedSocial_BUAP/index.php?expired=1');
    exit;
}

// ── NUNCA destruir sesión por fallo de BD ─────────────────────
// Solo verificar cada 5 minutos Y solo si podemos conectar a BD
$last_check = $_SESSION['last_status_check'] ?? 0;
$needs_check = (time() - $last_check) > 300;

if ($needs_check) {
    try {
        require_once $sharedDir . '/../microservices/users/user_model.php';
        $dbUser = UserModel::findById((int)$payload['user_id']);

        if ($dbUser && $dbUser['status'] === 'active') {
            // Actualizar sesión con datos frescos
            $_SESSION['user'] = [
                'user_id'      => (int)$dbUser['user_id'],
                'email'        => $dbUser['email']        ?? '',
                'first_name'   => $dbUser['first_name']   ?? 'Usuario',
                'last_name'    => $dbUser['last_name']    ?? '',
                'faculty_name' => $dbUser['faculty_name'] ?? '',
                'avatar'       => $dbUser['avatar']       ?? null,
                'faculty_id'   => $dbUser['faculty_id']   ?? $payload['faculty_id'] ?? null,
                'role'         => $dbUser['role']          ?? $payload['role'] ?? 'student',
                'status'       => 'active',
            ];
            $_SESSION['jwt_token']         = $token;
            $_SESSION['last_status_check'] = time();

        } elseif ($dbUser && $dbUser['status'] !== 'active') {
            // Usuario suspendido — SÍ cerrar sesión
            session_destroy();
            header('Location: http://localhost/RedSocial_BUAP/index.php?deactivated=1');
            exit;

        } else {
            // Usuario no encontrado en BD — NO cerrar sesión, usar payload del JWT
            // Puede pasar si hay problema de BD temporal
            if (!isset($_SESSION['user'])) {
                $_SESSION['user'] = _buildUserFromPayload($payload);
            }
            $_SESSION['last_status_check'] = time();
        }

    } catch (Throwable $e) {
        // Error de BD — NO cerrar sesión, continuar con lo que tenemos
        error_log('[UniLink auth_check] BD error: ' . $e->getMessage());
        if (!isset($_SESSION['user'])) {
            $_SESSION['user'] = _buildUserFromPayload($payload);
        }
        $_SESSION['last_status_check'] = time();
    }
} else {
    // No es momento de verificar — asegurar que 'user' exista
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        try {
            require_once $sharedDir . '/../microservices/users/user_model.php';
            $dbUser = UserModel::findById((int)$payload['user_id']);
            if ($dbUser) {
                $_SESSION['user'] = [
                    'user_id'      => (int)$dbUser['user_id'],
                    'email'        => $dbUser['email']        ?? '',
                    'first_name'   => $dbUser['first_name']   ?? 'Usuario',
                    'last_name'    => $dbUser['last_name']    ?? '',
                    'faculty_name' => $dbUser['faculty_name'] ?? '',
                    'avatar'       => $dbUser['avatar']       ?? null,
                    'faculty_id'   => $dbUser['faculty_id']   ?? $payload['faculty_id'] ?? null,
                    'role'         => $dbUser['role']          ?? $payload['role'] ?? 'student',
                    'status'       => 'active',
                ];
            } else {
                $_SESSION['user'] = _buildUserFromPayload($payload);
            }
        } catch (Throwable $e) {
            $_SESSION['user'] = _buildUserFromPayload($payload);
        }
        $_SESSION['jwt_token']         = $token;
        $_SESSION['last_status_check'] = time();
    }
}

// Alias corto
$user = $_SESSION['user'];

// ── Helper interno ────────────────────────────────────────────
function _buildUserFromPayload(array $payload): array {
    return [
        'user_id'      => (int)($payload['user_id']    ?? 0),
        'email'        => $payload['email']             ?? '',
        'first_name'   => $payload['first_name']        ?? 'Usuario',
        'last_name'    => $payload['last_name']         ?? '',
        'role'         => $payload['role']              ?? 'student',
        'faculty_id'   => $payload['faculty_id']        ?? null,
        'status'       => 'active',
        'faculty_name' => '',
        'avatar'       => null,
    ];
}
