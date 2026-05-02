<?php
/**
 * UniLink — test_login.php
 * Diagnóstico de sesión y JWT
 * Coloca este archivo en: C:/xampp/htdocs/RedSocial_BUAP/test_login.php
 * Accede en: http://localhost:8012/RedSocial_BUAP/test_login.php
 * ⚠️ ELIMINAR después de resolver el problema
 */

// Cargar helpers para tener las constantes
require_once __DIR__ . '/backend/shared/helpers.php';
require_once __DIR__ . '/backend/shared/jwt.php';

if (session_status() === PHP_SESSION_NONE) session_start();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<title>Diagnóstico UniLink</title>
<style>
  body{font-family:monospace;padding:24px;background:#f0f4ff}
  h2{color:#1A3A6B} .ok{color:green;font-weight:bold} .fail{color:red;font-weight:bold}
  .warn{color:orange;font-weight:bold} pre{background:#fff;padding:14px;border-radius:8px;border:1px solid #ddd;overflow-x:auto}
  .section{background:#fff;border-radius:10px;padding:16px;margin-bottom:16px;border:1px solid #dde}
  button{padding:10px 20px;background:#2557A7;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;margin-top:8px}
</style></head><body>";

echo "<h2>🔍 Diagnóstico UniLink — Problemas de Login</h2>";

// ── 1. Constantes definidas ───────────────────────────────────
echo "<div class='section'><b>1. Constantes cargadas desde helpers.php</b><pre>";
echo "JWT_SECRET  = " . JWT_SECRET . "\n";
echo "DB_HOST     = " . DB_HOST . "\n";
echo "DB_PORT     = " . DB_PORT . "\n";
echo "DB_USER     = " . DB_USER . "\n";
echo "DB_NAME     = " . DB_NAME . "\n";
echo "BASE_URL    = " . BASE_URL . "\n";
echo "</pre></div>";

// ── 2. Conexión BD ────────────────────────────────────────────
echo "<div class='section'><b>2. Conexión a base de datos</b><br>";
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
    $cnt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<span class='ok'>✅ Conectado a MySQL $ver</span><br>";
    echo "Usuarios en BD: <b>$cnt</b><br>";
} catch (PDOException $e) {
    echo "<span class='fail'>❌ Error BD: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// ── 3. Probar generación y verificación JWT ──────────────────
echo "<div class='section'><b>3. Prueba JWT (generar + verificar)</b><br>";
$testPayload = ['user_id' => 1, 'email' => 'test@alumno.buap.mx', 'role' => 'student', 'faculty_id' => 1];
$token = JWT::generate($testPayload);
$verified = JWT::verify($token);

if ($verified && $verified['user_id'] === 1) {
    echo "<span class='ok'>✅ JWT funciona correctamente</span><br>";
    echo "<pre>Token (primeros 60 chars): " . substr($token, 0, 60) . "...\n";
    echo "Payload verificado: " . json_encode($verified, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<span class='fail'>❌ JWT FALLA — El token no se puede verificar con el secret actual</span><br>";
    echo "<pre>Secret usado: " . JWT_SECRET . "</pre>";
}
echo "</div>";

// ── 4. Estado de sesión ───────────────────────────────────────
echo "<div class='section'><b>4. Estado de sesión PHP actual</b><pre>";
if (!empty($_SESSION)) {
    echo "SESSION activa:\n";
    $safe = $_SESSION;
    if (isset($safe['jwt_token'])) $safe['jwt_token'] = substr($safe['jwt_token'], 0, 30) . '...';
    print_r($safe);
} else {
    echo "<span class='warn'>⚠️ No hay sesión activa</span>\n";
}
echo "</pre></div>";

// ── 5. Simular login completo ─────────────────────────────────
echo "<div class='section'><b>5. Simular login con admin@tec.mx</b><br>";

// Probar con los emails de prueba del setup
$testEmails = [
    'admin@tec.mx',
    'ana@tec.mx',
];

foreach ($testEmails as $email) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, email, role, status, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $passOk = password_verify('password', $user['password_hash']);
            echo "<b>$email</b>: ";
            echo "status=" . $user['status'] . " | role=" . $user['role'] . " | password_verify=" . ($passOk ? "<span class='ok'>OK</span>" : "<span class='fail'>FALLA</span>");
            echo "<br>";
        } else {
            echo "<b>$email</b>: <span class='warn'>No encontrado en BD</span><br>";
        }
    } catch (Exception $e) {
        echo "<span class='fail'>Error: " . $e->getMessage() . "</span><br>";
    }
}

// También buscar usuarios con dominio BUAP
try {
    $stmt = $pdo->query("SELECT email, role, status FROM users WHERE email LIKE '%buap.mx' LIMIT 5");
    $buapUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($buapUsers) {
        echo "<br><b>Usuarios con @buap.mx en BD:</b><br>";
        foreach ($buapUsers as $u) {
            echo "  - {$u['email']} | rol={$u['role']} | status={$u['status']}<br>";
        }
    } else {
        echo "<br><span class='warn'>⚠️ No hay usuarios con @buap.mx en la BD</span><br>";
        echo "<small>El sistema solo acepta @alumno.buap.mx y @correo.buap.mx — asegúrate de registrarte con esos dominios</small><br>";
    }
} catch(Exception $e) {}

echo "</div>";

// ── 6. Verificar token de sessionStorage via GET ──────────────
echo "<div class='section'><b>6. Verificar un token JWT manualmente</b><br>";
if (isset($_GET['token'])) {
    $tok = trim($_GET['token']);
    $result = JWT::verify($tok);
    if ($result) {
        echo "<span class='ok'>✅ Token válido</span><pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<span class='fail'>❌ Token inválido o expirado</span><br>";
    }
} else {
    echo "<small>Pega un token en la URL: ?token=TU_TOKEN_AQUI</small><br>";
}
echo "</div>";

// ── 7. Verificar que auth.php set_session funciona ───────────
echo "<div class='section'><b>7. Rutas importantes</b><pre>";
$files = [
    'helpers.php'    => __DIR__ . '/backend/shared/helpers.php',
    'jwt.php'        => __DIR__ . '/backend/shared/jwt.php',
    'auth.php'       => __DIR__ . '/backend/api-gateway/auth.php',
    'auth_check.php' => __DIR__ . '/backend/shared/auth_check.php',
    'index.php'      => __DIR__ . '/index.php',
];
foreach ($files as $name => $path) {
    $exists = file_exists($path);
    echo ($exists ? "✅" : "❌") . " $name => $path\n";
}
echo "</pre></div>";

// ── 8. Solución sugerida si hay problemas ────────────────────
echo "<div class='section' style='background:#fffbe6;border-color:#f0c040'>";
echo "<b>💡 Pasos para arreglar el login:</b><br><br>";
echo "<b>Opción A — Registrarte con correo BUAP:</b><br>";
echo "El sistema ahora <b>solo acepta</b> @alumno.buap.mx o @correo.buap.mx<br>";
echo "Ve a la página de inicio y regístrate con un correo válido.<br><br>";
echo "<b>Opción B — Agregar usuario de prueba compatible:</b><br>";
echo "Ejecuta este SQL en phpMyAdmin:<br>";
echo "<pre>INSERT INTO users (email, password_hash, first_name, last_name, student_id, faculty_id, semester, role, status, email_verified)
VALUES (
  'admin@alumno.buap.mx',
  '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Admin',
  'BUAP',
  'S202312345',
  1, 1, 'admin', 'active', 1
);

-- Contraseña: password</pre>";
echo "</div>";

echo "<p style='color:#999;font-size:12px'>⚠️ Elimina este archivo (test_login.php) después de resolver el problema</p>";
echo "</body></html>";
