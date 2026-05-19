<?php
/**
 * UniLink — helpers.php
 * Proyecto: RedSocial_BUAP
 * URL base: http://localhost:8012/RedSocial_BUAP/
 */

if (!defined('UL_ENV')) {
    define('UL_ENV', getenv('APP_ENV') ?: 'development');

    define('JWT_SECRET', getenv('JWT_SECRET') ?: 'unilink_redsocial_buap_secret_key_');
    define('JWT_EXPIRY',  60 * 60 * 24 * 7);

    // ── Base de datos (confirmado por diagnóstico) ────────────
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'red_social');

    // ── Rutas del proyecto ────────────────────────────────────
    define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/RedSocial_BUAP');
    define('BASE_PATH', getenv('BASE_PATH') ?: '/RedSocial_BUAP');

    // ── Almacenamiento ────────────────────────────────────────
    define('STORAGE_PATH', getenv('STORAGE_PATH') ?: 'C:/xampp/htdocs/RedSocial_BUAP/storage');
    define('STORAGE_URL',  BASE_PATH . '/storage');

    // ── Dominios BUAP ─────────────────────────────────────────
    define('DOMAIN_STUDENT',   'alumno.buap.mx');
    define('DOMAIN_PROFESSOR', 'correo.buap.mx');
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function isInstitutionalEmail(string $email): bool {
    return (bool) preg_match('/^[a-zA-Z0-9._%+\-]+@(alumno|correo)\.buap\.mx$/i', $email);
}

function detectAccountType(string $email): string {
    if (preg_match('/^[a-zA-Z0-9._%+\-]+@alumno\.buap\.mx$/i', $email)) return 'student';
    if (preg_match('/^[a-zA-Z0-9._%+\-]+@correo\.buap\.mx$/i', $email)) return 'professor';
    return 'unknown';
}

function getPagination(): array {
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    return compact('page', 'limit', 'offset');
}

function uploadFile(array $file, string $subfolder = 'uploads'): ?string {
    $allowed = ['image/jpeg','image/png','image/gif','image/webp','video/mp4'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 10 * 1024 * 1024)  return null;
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('ul_', true) . '.' . strtolower($ext);
    $dir      = STORAGE_PATH . '/' . $subfolder;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], "$dir/$filename")) {
        return STORAGE_URL . "/$subfolder/$filename";
    }
    return null;
}

function logEvent(string $level, string $message, array $context = []): void {
    $logDir = 'C:/xampp/htdocs/RedSocial_BUAP/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    file_put_contents(
        "$logDir/app.log",
        json_encode(['ts'=>date('c'),'level'=>$level,'message'=>$message,'context'=>$context]) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function getDBConnection(string $dbName = null): PDO {
    static $connections = [];
    $db = $dbName ?? DB_NAME;
    if (isset($connections[$db])) return $connections[$db];

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname={$db};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
        exit;
    }

    $connections[$db] = $pdo;
    return $pdo;
}
