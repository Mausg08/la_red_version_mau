<?php
/**
 * UniLink — debug_login.php
 * Diagnóstico COMPLETO del problema de login
 * Accede en: http://localhost:8012/RedSocial_BUAP/debug_login.php
 * ⚠️ ELIMINAR después de resolver
 */

// Cargar todo el sistema
require_once __DIR__ . '/backend/shared/helpers.php';
require_once __DIR__ . '/backend/shared/jwt.php';
require_once __DIR__ . '/backend/microservices/users/user_model.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<title>Debug Login — UniLink</title>
<style>
body{font-family:monospace;padding:24px;background:#f0f4ff;max-width:960px;margin:0 auto}
h2{color:#1A3A6B} .ok{color:green;font-weight:bold} .fail{color:red;font-weight:bold}
.warn{color:#B45309;font-weight:bold}
pre{background:#fff;padding:14px;border-radius:8px;border:1px solid #ddd;font-size:12px;overflow-x:auto;white-space:pre-wrap}
.box{background:#fff;border-radius:10px;padding:16px;margin-bottom:16px;border:1px solid #dde}
.fix{background:#fffbe6;border-color:#f0c040;border-radius:10px;padding:16px;margin-bottom:16px}
h3{margin:0 0 10px;font-size:15px}
</style></head><body>
<h2>🔍 UniLink — Debug de Login</h2>

<?php

// ══════════════════════════════════════════════════
// 1. CONSTANTES
// ══════════════════════════════════════════════════
echo "<div class='box'><h3>1. Constantes del sistema</h3><pre>";
echo "JWT_SECRET   = '" . JWT_SECRET . "' (longitud: " . strlen(JWT_SECRET) . ")\n";
echo "JWT_EXPIRY   = " . JWT_EXPIRY . " segundos (" . (JWT_EXPIRY/86400) . " días)\n";
echo "DB_HOST      = " . DB_HOST . "\n";
echo "DB_PORT      = " . DB_PORT . "\n";
echo "DB_USER      = " . DB_USER . "\n";
echo "DB_NAME      = " . DB_NAME . "\n";
echo "BASE_URL     = " . BASE_URL . "\n";
echo "PHP version  = " . PHP_VERSION . "\n";
echo "</pre></div>";

// ══════════════════════════════════════════════════
// 2. CONEXIÓN BD
// ══════════════════════════════════════════════════
echo "<div class='box'><h3>2. Conexión a Base de Datos</h3>";
$pdo = null;
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
    $cnt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<span class='ok'>✅ Conectado — MySQL $ver — $cnt usuarios</span><br>";
} catch (PDOException $e) {
    echo "<span class='fail'>❌ ERROR BD: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<div class='fix'><b>Solución:</b> Verifica que MySQL corre en puerto " . DB_PORT . " y que la BD <b>" . DB_NAME . "</b> existe.</div>";
}
echo "</div>";

// ══════════════════════════════════════════════════
// 3. JWT GENERATION + VERIFY
// ══════════════════════════════════════════════════
echo "<div class='box'><h3>3. Prueba JWT (generar → verificar)</h3>";
$testPayload = [
    'user_id'    => 999,
    'email'      => 'test@alumno.buap.mx',
    'role'       => 'student',
    'faculty_id' => 1,
];
$token    = JWT::generate($testPayload);
$verified = JWT::verify($token);

if ($verified && $verified['user_id'] === 999) {
    echo "<span class='ok'>✅ JWT funciona correctamente con el secret actual</span><br>";
    echo "<pre>Token (50 chars): " . substr($token, 0, 50) . "...\nExpira en: " . date('Y-m-d H:i:s', $verified['exp']) . "</pre>";
} else {
    echo "<span class='fail'>❌ JWT FALLA — el token generado no se puede verificar</span><br>";
    echo "<pre>Secret: '" . JWT_SECRET . "'\nToken generado: " . substr($token, 0, 80) . "...\nVerificación: " . var_export($verified, true) . "</pre>";
}
echo "</div>";

// ══════════════════════════════════════════════════
// 4. USUARIOS EN BD
// ══════════════════════════════════════════════════
if ($pdo) {
    echo "<div class='box'><h3>4. Usuarios en la BD</h3>";
    
    // Todos los usuarios
    $stmt = $pdo->query("SELECT user_id, email, first_name, last_name, role, status FROM users ORDER BY user_id LIMIT 20");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($users) {
        echo "<table style='width:100%;border-collapse:collapse;font-size:12px'>";
        echo "<tr style='background:#f0f4ff'><th>ID</th><th>Email</th><th>Nombre</th><th>Rol</th><th>Status</th><th>Password OK?</th></tr>";
        foreach ($users as $u) {
            // Verificar password hash
            $passOk = password_verify('password', $u['user_id'] ? 
                ($pdo->prepare("SELECT password_hash FROM users WHERE user_id=?") && 
                 ($ps = $pdo->prepare("SELECT password_hash FROM users WHERE user_id=?")) && 
                 $ps->execute([$u['user_id']]) && 
                 ($hash = $ps->fetchColumn()) && 
                 password_verify('password', $hash) ? true : false) : false);
            
            // Re-fetch hash properly
            $ps2 = $pdo->prepare("SELECT password_hash FROM users WHERE user_id=?");
            $ps2->execute([$u['user_id']]);
            $hash2 = $ps2->fetchColumn();
            $passOk2 = $hash2 ? password_verify('password', $hash2) : false;
            
            $rowStyle = $u['status'] !== 'active' ? 'background:#FFF0F0' : '';
            echo "<tr style='$rowStyle'>";
            echo "<td style='padding:4px 8px'>{$u['user_id']}</td>";
            echo "<td style='padding:4px 8px'>" . htmlspecialchars($u['email']) . "</td>";
            echo "<td style='padding:4px 8px'>" . htmlspecialchars($u['first_name'].' '.$u['last_name']) . "</td>";
            echo "<td style='padding:4px 8px'>{$u['role']}</td>";
            echo "<td style='padding:4px 8px'>" . ($u['status']==='active' ? "<span class='ok'>{$u['status']}</span>" : "<span class='fail'>{$u['status']}</span>") . "</td>";
            echo "<td style='padding:4px 8px'>" . ($passOk2 ? "<span class='ok'>✅ 'password'</span>" : "<span class='warn'>⚠️ no es 'password'</span>") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<span class='fail'>❌ No hay usuarios en la BD</span><br>";
    }
    echo "</div>";
}

// ══════════════════════════════════════════════════
// 5. SIMULAR LOGIN COMPLETO
// ══════════════════════════════════════════════════
echo "<div class='box'><h3>5. Simulación completa de login</h3>";
if ($pdo) {
    // Tomar el primer usuario activo
    $stmt = $pdo->query("SELECT * FROM users WHERE status='active' LIMIT 1");
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($u) {
        echo "Probando con: <b>{$u['email']}</b><br>";
        
        // Simular lo que hace auth_controller.php
        $passOk = password_verify('password', $u['password_hash']);
        echo "password_verify('password', hash): " . ($passOk ? "<span class='ok'>✅ OK</span>" : "<span class='fail'>❌ FALLA</span>") . "<br>";
        
        if ($passOk) {
            $payload = [
                'user_id'    => (int)$u['user_id'],
                'email'      => $u['email'],
                'role'       => $u['role'],
                'faculty_id' => $u['faculty_id'],
            ];
            $jwtToken = JWT::generate($payload);
            $verified = JWT::verify($jwtToken);
            
            echo "JWT generado: " . ($verified ? "<span class='ok'>✅ verificado</span>" : "<span class='fail'>❌ FALLA</span>") . "<br>";
            
            if ($verified) {
                echo "<br><span class='ok'>✅ Todo OK — el login DEBERÍA funcionar</span><br>";
                echo "<br><b>Prueba estas credenciales:</b><br>";
                echo "Email: <code>" . htmlspecialchars($u['email']) . "</code><br>";
                echo "Password: <code>password</code><br>";
                echo "<br><b>Token de prueba (primeros 80 chars):</b><br>";
                echo "<code>" . substr($jwtToken, 0, 80) . "...</code>";
            }
        } else {
            echo "<br><span class='fail'>❌ La contraseña 'password' no coincide con el hash en BD</span><br>";
            echo "<div class='fix'><b>Solución:</b> Ejecuta este SQL en phpMyAdmin:<br>";
            echo "<pre>UPDATE users SET password_hash = '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE email = '" . htmlspecialchars($u['email']) . "';</pre>";
            echo "La contraseña será: <b>password</b></div>";
        }
    }
}
echo "</div>";

// ══════════════════════════════════════════════════
// 6. VERIFICAR auth.php (set_session)
// ══════════════════════════════════════════════════
echo "<div class='box'><h3>6. Verificar auth.php internamente</h3>";
$authFile = __DIR__ . '/backend/api-gateway/auth.php';
$authContent = file_get_contents($authFile);

// Detectar problemas comunes
$problems = [];
if (strpos($authContent, 'Location: http://localhost:8012/RedSocial_BUAP') === false &&
    strpos($authContent, 'Location: ') !== false) {
    // Tiene redirect pero a URL diferente
    preg_match('/Location: (.+)/', $authContent, $m);
    $problems[] = "Redirect apunta a: " . ($m[1] ?? '?') . " — ¿es correcto para tu XAMPP?";
}
if (strpos($authContent, 'JWT::verify') === false) {
    $problems[] = "No llama JWT::verify — ¡puede aceptar cualquier token!";
}

if ($problems) {
    foreach ($problems as $p) echo "<span class='warn'>⚠️ $p</span><br>";
} else {
    echo "<span class='ok'>✅ auth.php parece correcto</span><br>";
}

// Detectar si el set_session retorna JSON correctamente
if (strpos($authContent, "Content-Type: application/json") !== false || 
    strpos($authContent, 'json_encode') !== false) {
    echo "<span class='ok'>✅ Devuelve JSON correctamente</span><br>";
} else {
    echo "<span class='fail'>❌ No detecté Content-Type JSON en auth.php — puede causar errores de parseo</span><br>";
    $problems[] = "missing_json_header";
}

echo "</div>";

// ══════════════════════════════════════════════════
// 7. SOLUCIÓN AUTOMÁTICA
// ══════════════════════════════════════════════════
echo "<div class='fix'><h3>🔧 Pasos para resolver el error 'Tu sesión expiró'</h3>";

echo "<b>CAUSA MÁS PROBABLE:</b> El JWT se genera en <code>index.php</code> y luego se envía a <code>auth.php</code>, 
pero si el JWT_SECRET cambió o hay output antes de los headers, <code>JWT::verify()</code> devuelve null y auth.php lo rechaza.<br><br>";

echo "<b>Paso 1 — Agrega usuario BUAP de prueba si no tienes uno:</b>";
echo "<pre>-- Ejecuta en phpMyAdmin → BD red_social
INSERT INTO users 
  (email, password_hash, first_name, last_name, student_id, faculty_id, semester, role, status, email_verified)
VALUES 
  ('prueba@alumno.buap.mx',
   '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'Prueba', 'BUAP', 'S202300001', 1, 1, 'student', 'active', 1);
   
-- Contraseña: password</pre>";

echo "<b>Paso 2 — Reemplaza <code>backend/api-gateway/auth.php</code></b> con el archivo <code>auth_REEMPLAZAR.php</code> que descargaste.<br><br>";

echo "<b>Paso 3 — Verifica que <code>backend/shared/helpers.php</code> tenga exactamente:</b>";
echo "<pre>define('JWT_SECRET', 'unilink_redsocial_buap_secret_key_');</pre>";
echo "Este valor DEBE ser idéntico en <b>todos los archivos que lo usen</b>.<br><br>";

echo "<b>Paso 4 — Si el problema persiste, abre la consola del navegador (F12) y revisa la respuesta de estas peticiones:</b><br>";
echo "<code>POST /backend/api-gateway/index.php?service=auth&path=auth/login</code><br>";
echo "<code>POST /backend/api-gateway/auth.php?action=set_session</code><br><br>";

echo "<a href='http://localhost:8012/RedSocial_BUAP/debug_login.php?action=test_login&email=prueba@alumno.buap.mx' style='background:#2557A7;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none'>▶ Probar login de prueba</a>";

echo "</div>";

// ══════════════════════════════════════════════════
// 8. TEST RÁPIDO DE LOGIN VÍA GET
// ══════════════════════════════════════════════════
if (isset($_GET['action']) && $_GET['action'] === 'test_login') {
    echo "<div class='box'><h3>8. Resultado test login rápido</h3>";
    $testEmail = $_GET['email'] ?? 'prueba@alumno.buap.mx';
    
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$testEmail]);
        $u = $stmt->fetch();
        
        if (!$u) {
            echo "<span class='fail'>❌ Usuario '$testEmail' no encontrado o inactivo</span>";
        } elseif (!password_verify('password', $u['password_hash'])) {
            echo "<span class='fail'>❌ Contraseña incorrecta para '$testEmail'</span>";
        } else {
            $tok = JWT::generate([
                'user_id'    => (int)$u['user_id'],
                'email'      => $u['email'],
                'role'       => $u['role'],
                'faculty_id' => $u['faculty_id'],
            ]);
            $v = JWT::verify($tok);
            echo $v 
                ? "<span class='ok'>✅ LOGIN OK para {$u['email']} — Token válido</span>" 
                : "<span class='fail'>❌ JWT verify falló después de generar</span>";
        }
    }
    echo "</div>";
}

?>
<p style="color:#999;font-size:12px;margin-top:20px">⚠️ Elimina <code>debug_login.php</code> después de resolver el problema</p>
</body></html>
