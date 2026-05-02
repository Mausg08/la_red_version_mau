<?php
/**
 * test_auth_flow.php — prueba TODO el flujo de login en una sola página
 * Pon en: C:\xampp\htdocs\RedSocial_BUAP\test_auth_flow.php
 * Accede:  http://localhost:8012/RedSocial_BUAP/test_auth_flow.php
 * ELIMINAR después de resolver
 */
require_once __DIR__ . '/backend/shared/helpers.php';
require_once __DIR__ . '/backend/shared/jwt.php';
require_once __DIR__ . '/backend/microservices/users/user_model.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: text/html; charset=UTF-8');

// ── Acción: login directo ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = ['steps' => []];

    // STEP 1: Buscar usuario
    $user = UserModel::findByEmail($email);
    if (!$user) {
        $result['steps'][] = ['step'=>'find_user', 'ok'=>false, 'msg'=>"Usuario '$email' NO encontrado en BD"];
    } else {
        $result['steps'][] = ['step'=>'find_user', 'ok'=>true,
            'msg'=>"Usuario encontrado: id={$user['user_id']} role={$user['role']} status={$user['status']}"];

        // STEP 2: Verificar password
        $passOk = password_verify($password, $user['password_hash']);
        $result['steps'][] = ['step'=>'password', 'ok'=>$passOk,
            'msg'=>$passOk ? "Contraseña correcta" : "Contraseña INCORRECTA. Hash en BD: " . substr($user['password_hash'],0,20)."..."];

        if ($passOk && $user['status'] === 'active') {
            // STEP 3: Generar JWT
            $payload = [
                'user_id'    => (int)$user['user_id'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'faculty_id' => $user['faculty_id'],
            ];
            $token   = JWT::generate($payload);
            $verified = JWT::verify($token);
            $result['steps'][] = ['step'=>'jwt_generate', 'ok'=>(bool)$verified,
                'msg'=> $verified ? "JWT generado y verificado OK. Expira: ".date('Y-m-d H:i:s',$verified['exp']) : "JWT FALLA"];

            if ($verified) {
                // STEP 4: Guardar sesión
                $_SESSION['jwt_token'] = $token;
                $_SESSION['user'] = [
                    'user_id'      => (int)$user['user_id'],
                    'email'        => $user['email'],
                    'first_name'   => $user['first_name'],
                    'last_name'    => $user['last_name'],
                    'role'         => $user['role'],
                    'faculty_id'   => $user['faculty_id'],
                    'faculty_name' => $user['faculty_name'] ?? '',
                    'avatar'       => $user['avatar'] ?? null,
                    'status'       => 'active',
                ];
                $_SESSION['last_status_check'] = time();
                $result['steps'][] = ['step'=>'session_save', 'ok'=>true, 'msg'=>"Sesión PHP guardada OK. Session ID: ".session_id()];

                // STEP 5: Verificar que la sesión se guardó
                $result['token']     = $token;
                $result['session_id'] = session_id();
                $result['all_ok']    = true;
            }
        }
    }

    // Mostrar resultado como JSON formateado
    ?><!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>Test Login Result</title>
    <style>
    body{font-family:monospace;padding:20px;background:#f0f4ff;max-width:800px;margin:0 auto}
    .ok{color:green;font-weight:bold}.fail{color:red;font-weight:bold}
    pre{background:#fff;padding:12px;border-radius:8px;border:1px solid #ddd;font-size:12px}
    .box{background:#fff;border-radius:10px;padding:16px;margin:12px 0;border:1px solid #dde}
    a.btn{display:inline-block;padding:10px 20px;background:#2557A7;color:#fff;border-radius:6px;text-decoration:none;margin-top:10px}
    </style></head><body>
    <h2>🔍 Resultado del flujo de login</h2>
    <?php foreach ($result['steps'] as $s): ?>
    <div class="box">
      <?= $s['ok'] ? "<span class='ok'>✅ [{$s['step']}]</span>" : "<span class='fail'>❌ [{$s['step']}]</span>" ?>
      &nbsp; <?= htmlspecialchars($s['msg']) ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($result['all_ok'])): ?>
    <div class="box" style="background:#E3F5F0;border-color:#9FE1CB">
      <span class="ok">✅ TODO EL FLUJO OK — sesión creada correctamente</span><br><br>
      <b>Session ID:</b> <?= $result['session_id'] ?><br>
      <b>Token (50 chars):</b> <?= substr($result['token'],0,50) ?>...<br><br>
      <a href="http://localhost:8012/RedSocial_BUAP/frontend/pages/feed.php" class="btn">▶ Ir al Feed directamente</a>
      &nbsp;<a href="http://localhost:8012/RedSocial_BUAP/test_auth_flow.php?check_session=1" class="btn" style="background:#0F6E56">🔍 Verificar sesión</a>
    </div>
    <?php else: ?>
    <div class="box" style="background:#FEECEC;border-color:#FBBCBC">
      <span class="fail">❌ El flujo falló — revisa los pasos anteriores</span>
    </div>
    <?php endif; ?>

    <a href="http://localhost:8012/RedSocial_BUAP/test_auth_flow.php" style="color:#2557A7">← Volver al formulario</a>
    </body></html>
    <?php
    exit;
}

// ── Verificar sesión actual ───────────────────────────────────
if (isset($_GET['check_session'])) {
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Check Session</title>
    <style>body{font-family:monospace;padding:20px;background:#f0f4ff;max-width:700px;margin:0 auto}
    .ok{color:green;font-weight:bold}.fail{color:red;font-weight:bold}
    pre{background:#fff;padding:12px;border-radius:8px;border:1px solid #ddd;font-size:12px}
    a.btn{display:inline-block;padding:10px 20px;background:#2557A7;color:#fff;border-radius:6px;text-decoration:none;margin:6px}</style>
    </head><body>
    <h2>🔍 Estado de la sesión actual</h2>
    <?php
    $hasToken   = !empty($_SESSION['jwt_token']);
    $hasUser    = !empty($_SESSION['user']['user_id']);
    $tokenValid = $hasToken ? (bool)JWT::verify($_SESSION['jwt_token']) : false;

    echo "<pre>";
    echo "Session ID    : " . session_id() . "\n";
    echo "jwt_token     : " . ($hasToken ? substr($_SESSION['jwt_token'],0,40)."..." : "NO EXISTE") . "\n";
    echo "token válido  : " . ($tokenValid ? "✅ SÍ" : "❌ NO") . "\n";
    echo "user.user_id  : " . ($_SESSION['user']['user_id'] ?? "NO EXISTE") . "\n";
    echo "user.email    : " . ($_SESSION['user']['email']   ?? "NO EXISTE") . "\n";
    echo "user.role     : " . ($_SESSION['user']['role']    ?? "NO EXISTE") . "\n";
    echo "last_check    : " . ($_SESSION['last_status_check'] ?? "NO EXISTE") . "\n";
    echo "</pre>";

    if ($hasToken && $hasUser && $tokenValid) {
        echo "<p style='color:green;font-weight:bold'>✅ Sesión válida — deberías poder acceder al feed</p>";
        echo "<a href='http://localhost:8012/RedSocial_BUAP/frontend/pages/feed.php' class='btn'>▶ Ir al Feed</a>";
    } else {
        echo "<p style='color:red;font-weight:bold'>❌ Sesión inválida o incompleta</p>";
    }
    ?>
    <br><a href="http://localhost:8012/RedSocial_BUAP/test_auth_flow.php" class="btn" style="background:#555">← Volver</a>
    </body></html>
    <?php
    exit;
}

// ── Listado de usuarios disponibles ──────────────────────────
$dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>true]);
$users = $pdo->query("SELECT user_id, email, first_name, last_name, role, status, LEFT(password_hash,20) as hash_prev FROM users WHERE status='active' ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Test Auth Flow — UniLink</title>
<style>
body{font-family:monospace;padding:20px;background:#f0f4ff;max-width:800px;margin:0 auto}
h2{color:#1A3A6B}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
th{background:#2557A7;color:#fff;padding:8px 12px;text-align:left;font-size:12px}
td{padding:8px 12px;border-bottom:1px solid #eee;font-size:12px}
.btn-row{background:#2557A7;color:#fff;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:12px}
.box{background:#fff;border-radius:10px;padding:16px;margin:16px 0;border:1px solid #dde}
input{padding:8px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:14px;width:100%;box-sizing:border-box;margin-top:4px}
.btn{display:inline-block;padding:12px 24px;background:#2557A7;color:#fff;border-radius:8px;border:none;cursor:pointer;font-size:15px;width:100%;margin-top:12px}
</style>
</head><body>
<h2>🔐 Test del flujo de autenticación — UniLink</h2>

<div class="box">
  <b>Usuarios activos en BD:</b>
  <table style="margin-top:8px">
    <tr><th>ID</th><th>Email</th><th>Nombre</th><th>Rol</th><th>Hash (20 chars)</th><th>Acción</th></tr>
    <?php foreach($users as $u): ?>
    <tr>
      <td><?=$u['user_id']?></td>
      <td><?=htmlspecialchars($u['email'])?></td>
      <td><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></td>
      <td><?=$u['role']?></td>
      <td><code><?=$u['hash_prev']?>...</code></td>
      <td>
        <button class="btn-row" onclick="
          document.getElementById('email').value='<?=htmlspecialchars($u['email'])?>';
          document.getElementById('email').scrollIntoView();
        ">Usar</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="box">
  <b>Probar login completo:</b>
  <form method="POST">
    <label>Email:<input type="email" id="email" name="email" placeholder="ana@alumno.buap.mx"></label>
    <label style="margin-top:8px;display:block">Contraseña:<input type="text" name="password" value="password123" placeholder="password123"></label>
    <button type="submit" name="do_login" class="btn">▶ Ejecutar flujo de login</button>
  </form>
</div>

<div class="box">
  <b>Estado sesión actual:</b><br><br>
  <?php
  $t = $_SESSION['jwt_token'] ?? null;
  $v = $t ? JWT::verify($t) : null;
  if ($t && $v && !empty($_SESSION['user']['user_id'])) {
      echo "<span style='color:green;font-weight:bold'>✅ Sesión activa</span> — user_id=" . ($_SESSION['user']['user_id']??'?') . " email=" . ($_SESSION['user']['email']??'?');
      echo "<br><br><a href='http://localhost:8012/RedSocial_BUAP/frontend/pages/feed.php' style='background:#0F6E56;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>▶ Ir al Feed</a>";
  } else {
      echo "<span style='color:#B45309;font-weight:bold'>⚠️ Sin sesión activa</span>";
  }
  ?>
</div>

<p style="color:#999;font-size:11px">⚠️ Elimina test_auth_flow.php después de resolver el problema</p>
</body></html>
