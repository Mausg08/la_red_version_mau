<?php
/**
 * UniLink — fix_passwords.php
 * Genera el hash correcto para tu PHP y actualiza la BD
 * Accede en: http://localhost:8012/RedSocial_BUAP/fix_passwords.php
 * ⚠️ ELIMINAR después de usar
 */
require_once __DIR__ . '/backend/shared/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<title>Fix Passwords — UniLink</title>
<style>
body{font-family:monospace;padding:24px;background:#f0f4ff;max-width:800px;margin:0 auto}
.ok{color:green;font-weight:bold} .fail{color:red;font-weight:bold}
pre{background:#fff;padding:14px;border-radius:8px;border:1px solid #ddd;font-size:13px}
.box{background:#fff;border-radius:10px;padding:16px;margin-bottom:16px;border:1px solid #dde}
h2{color:#1A3A6B}
.btn{display:inline-block;padding:12px 24px;background:#2557A7;color:#fff;border-radius:8px;text-decoration:none;font-size:15px;border:none;cursor:pointer;margin-top:10px}
.btn-green{background:#0F6E56}
.btn-red{background:#C0392B}
</style></head><body>
<h2>🔧 Fix de Contraseñas — UniLink</h2>

<?php

$dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => true,
]);

// ── ACCIÓN: Actualizar contraseñas ────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'fix') {
    $nuevaPassword = $_POST['nueva_password'] ?? 'password123';
    
    if (strlen($nuevaPassword) < 6) {
        echo "<div class='box'><span class='fail'>❌ La contraseña debe tener al menos 6 caracteres</span></div>";
    } else {
        // Generar hash con PHP actual
        $hash = password_hash($nuevaPassword, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Verificar inmediatamente
        $verOk = password_verify($nuevaPassword, $hash);
        
        if (!$verOk) {
            echo "<div class='box'><span class='fail'>❌ ERROR CRÍTICO: password_hash/verify no funciona en este PHP</span></div>";
        } else {
            // Actualizar TODOS los usuarios
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?");
            $stmt->execute([$hash]);
            $affected = $stmt->rowCount();
            
            echo "<div class='box'>";
            echo "<span class='ok'>✅ $affected usuarios actualizados</span><br><br>";
            echo "<b>Nueva contraseña para TODOS:</b> <code>$nuevaPassword</code><br>";
            echo "<b>Hash generado:</b><br><pre>$hash</pre>";
            echo "<b>Verificación inmediata:</b> <span class='ok'>✅ OK</span><br><br>";
            
            echo "<hr><b>Ahora prueba iniciar sesión con:</b><br>";
            
            // Mostrar todos los usuarios
            $users = $pdo->query("SELECT email, role FROM users ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC);
            echo "<table style='border-collapse:collapse;margin-top:8px'>";
            echo "<tr style='background:#f0f4ff'><th style='padding:6px 12px;text-align:left'>Email</th><th style='padding:6px 12px'>Rol</th></tr>";
            foreach ($users as $u) {
                echo "<tr><td style='padding:6px 12px'><b>{$u['email']}</b></td><td style='padding:6px 12px'>{$u['role']}</td></tr>";
            }
            echo "</table><br>";
            echo "<a href='http://localhost:8012/RedSocial_BUAP/index.php' class='btn btn-green'>▶ Ir al Login</a>";
            echo "</div>";
        }
    }
    exit;
}

// ── DIAGNÓSTICO PREVIO ────────────────────────────────────────
echo "<div class='box'>";
echo "<b>PHP Version:</b> " . PHP_VERSION . "<br>";
echo "<b>password_hash disponible:</b> " . (function_exists('password_hash') ? "<span class='ok'>✅ Sí</span>" : "<span class='fail'>❌ No</span>") . "<br>";

// Generar hash de prueba y verificar
$testHash = password_hash('test123', PASSWORD_BCRYPT, ['cost' => 10]);
$testVerify = password_verify('test123', $testHash);
echo "<b>Prueba password_hash+verify:</b> " . ($testVerify ? "<span class='ok'>✅ Funciona</span>" : "<span class='fail'>❌ Falla</span>") . "<br>";
echo "<b>Hash de prueba generado:</b><br><pre>$testHash</pre>";
echo "</div>";

// ── VER HASHES ACTUALES EN BD ─────────────────────────────────
echo "<div class='box'>";
echo "<b>Hashes actuales en BD:</b><br><br>";
$users = $pdo->query("SELECT user_id, email, role, LEFT(password_hash, 30) as hash_preview FROM users ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table style='border-collapse:collapse;font-size:12px;width:100%'>";
echo "<tr style='background:#f0f4ff'><th style='padding:4px 8px'>ID</th><th style='padding:4px 8px'>Email</th><th style='padding:4px 8px'>Rol</th><th style='padding:4px 8px'>Hash (primeros 30 chars)</th><th style='padding:4px 8px'>¿Es bcrypt?</th></tr>";
foreach ($users as $u) {
    $isBcrypt = str_starts_with($u['hash_preview'], '$2y$') || str_starts_with($u['hash_preview'], '$2a$');
    echo "<tr>";
    echo "<td style='padding:4px 8px'>{$u['user_id']}</td>";
    echo "<td style='padding:4px 8px'>{$u['email']}</td>";
    echo "<td style='padding:4px 8px'>{$u['role']}</td>";
    echo "<td style='padding:4px 8px'><code>{$u['hash_preview']}...</code></td>";
    echo "<td style='padding:4px 8px'>" . ($isBcrypt ? "<span class='ok'>✅ Sí</span>" : "<span class='fail'>❌ No es bcrypt</span>") . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// ── FORMULARIO ────────────────────────────────────────────────
echo "<div class='box' style='background:#fffbe6;border-color:#f0c040'>";
echo "<h3 style='margin:0 0 12px'>🔑 Actualizar contraseña de TODOS los usuarios</h3>";
echo "<p style='margin:0 0 12px;font-size:14px'>Esto generará un hash nuevo con TU versión de PHP y lo aplicará a todos los usuarios.</p>";
echo "<form method='POST'>";
echo "<input type='hidden' name='action' value='fix'>";
echo "<label style='font-size:14px'><b>Nueva contraseña:</b><br>";
echo "<input type='text' name='nueva_password' value='password123' style='padding:8px 12px;font-size:15px;border:1.5px solid #ddd;border-radius:6px;margin:6px 0;width:250px'></label><br>";
echo "<button type='submit' class='btn'>✅ Actualizar contraseñas en BD</button>";
echo "</form>";
echo "</div>";
?>
<p style="color:#999;font-size:12px">⚠️ Elimina este archivo después de resolver el problema</p>
</body></html>
