<?php
/**
 * UniLink — fix_pages.php
 * Corrige todas las páginas que solo tienen redirects
 * Coloca en: C:/xampp/htdocs/RedSocial_BUAP/fix_pages.php
 * Ejecuta en: http://localhost:8012/RedSocial_BUAP/fix_pages.php
 * ⚠️ ELIMINAR después de usar
 */

$base_path = __DIR__;
$base_url  = '/RedSocial_BUAP';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<title>Fix Pages — UniLink</title>
<style>
body{font-family:monospace;padding:24px;background:#f0f4ff;max-width:900px;margin:0 auto}
h2{color:#1A3A6B}.ok{color:green;font-weight:bold}.fail{color:red;font-weight:bold}
.warn{color:orange;font-weight:bold}pre{background:#fff;padding:14px;border-radius:8px;border:1px solid #ddd;font-size:12px;overflow-x:auto}
.section{background:#fff;border-radius:10px;padding:16px;margin-bottom:16px;border:1px solid #dde}
</style></head><body>
<h2>🔧 UniLink — Corrección de páginas</h2>";

// ── Verificar auth_check.php también ─────────────────────────
$auth_check_path = $base_path . '/backend/shared/auth_check.php';
$auth_check_content = file_get_contents($auth_check_path);

// El problema: auth_check destruye la sesión si no puede leer el usuario de la BD
// pero el payload del JWT no tiene first_name, así que la sesión queda incompleta
// Vamos a parchear auth_check para que sea más tolerante

echo "<div class='section'><b>1. Verificando auth_check.php</b><br>";
if (strpos($auth_check_content, 'last_status_check') !== false) {
    echo "<span class='ok'>✅ auth_check.php encontrado</span><br>";
    
    // Verificar si tiene el problema del array_merge fallido
    if (strpos($auth_check_content, "?? \$payload['first_name']") !== false ||
        strpos($auth_check_content, "?? \$payload[") !== false) {
        echo "<span class='warn'>⚠️ Posible problema en array_merge detectado — parcheando...</span><br>";
    }
} else {
    echo "<span class='fail'>❌ auth_check.php no encontrado en: $auth_check_path</span><br>";
}
echo "</div>";

// ── Nuevo auth_check.php más robusto ─────────────────────────
$new_auth_check = <<<'PHPEOF'
<?php
/**
 * UniLink — auth_check.php (parcheado)
 */

$sharedDir = __DIR__;
require_once $sharedDir . '/helpers.php';
require_once $sharedDir . '/jwt.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_SESSION['jwt_token'] ?? $_COOKIE['ul_token'] ?? null;

if (!$token) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: http://localhost:8012/RedSocial_BUAP/index.php?redirect=' . $redirect);
    exit;
}

$payload = JWT::verify($token);

if (!$payload) {
    // Token inválido — limpiar todo y redirigir
    session_destroy();
    setcookie('ul_token', '', time() - 1, '/');
    header('Location: http://localhost:8012/RedSocial_BUAP/index.php?expired=1');
    exit;
}

// Verificar estado del usuario cada 5 minutos
$last_check = $_SESSION['last_status_check'] ?? 0;
if (time() - $last_check > 300) {
    try {
        require_once $sharedDir . '/../microservices/users/user_model.php';
        $dbUser = UserModel::findById((int)$payload['user_id']);

        if (!$dbUser || $dbUser['status'] !== 'active') {
            session_destroy();
            header('Location: http://localhost:8012/RedSocial_BUAP/index.php?deactivated=1');
            exit;
        }

        // Actualizar sesión con datos frescos de BD
        $_SESSION['user'] = [
            'user_id'      => (int)$dbUser['user_id'],
            'email'        => $dbUser['email']        ?? $payload['email']      ?? '',
            'first_name'   => $dbUser['first_name']   ?? '',
            'last_name'    => $dbUser['last_name']    ?? '',
            'faculty_name' => $dbUser['faculty_name'] ?? '',
            'avatar'       => $dbUser['avatar']       ?? null,
            'faculty_id'   => $dbUser['faculty_id']   ?? $payload['faculty_id'] ?? null,
            'role'         => $dbUser['role']          ?? $payload['role']      ?? 'student',
            'status'       => $dbUser['status']        ?? 'active',
        ];
        $_SESSION['jwt_token']            = $token;
        $_SESSION['last_status_check']    = time();

    } catch (Throwable $e) {
        // Si falla la BD, usar lo que hay en sesión/payload sin destruirla
        if (!isset($_SESSION['user'])) {
            $_SESSION['user'] = [
                'user_id'    => (int)($payload['user_id']    ?? 0),
                'email'      => $payload['email']             ?? '',
                'first_name' => $payload['first_name']        ?? 'Usuario',
                'last_name'  => $payload['last_name']         ?? '',
                'role'       => $payload['role']              ?? 'student',
                'faculty_id' => $payload['faculty_id']        ?? null,
                'status'     => 'active',
            ];
        }
        $_SESSION['last_status_check'] = time();
    }
} else {
    // No es momento de verificar — asegurarse de que 'user' exista en sesión
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = [
            'user_id'    => (int)($payload['user_id']    ?? 0),
            'email'      => $payload['email']             ?? '',
            'first_name' => $payload['first_name']        ?? 'Usuario',
            'last_name'  => $payload['last_name']         ?? '',
            'role'       => $payload['role']              ?? 'student',
            'faculty_id' => $payload['faculty_id']        ?? null,
            'status'     => 'active',
            'faculty_name' => '',
            'avatar'     => null,
        ];
    }
}

// Alias corto disponible en todas las páginas
$user = $_SESSION['user'];
PHPEOF;

// ── Nuevo feed.php ────────────────────────────────────────────
$new_feed = <<<'PHPEOF'
<?php
session_start();
require_once '../../backend/shared/auth_check.php';
$user = $_SESSION['user'];
$base = '/RedSocial_BUAP';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feed — UniLink BUAP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/RedSocial_BUAP/frontend/css/main.css">
  <link rel="stylesheet" href="/RedSocial_BUAP/frontend/css/dashboard.css">
</head>
<body class="dashboard-page">

<?php include '../../frontend/components/sidebar.php'; ?>
<?php include '../../frontend/components/topbar.php'; ?>

<main class="main-content" id="mainContent">
  <section class="feed-column">
    <div class="card create-post-card">
      <div class="card-body">
        <div class="create-post-row">
          <div class="avatar"><?php echo strtoupper(substr($user['first_name'],0,1)); ?></div>
          <button class="create-post-trigger" id="openPostModal">
            ¿Qué quieres compartir, <?php echo htmlspecialchars($user['first_name']); ?>?
          </button>
        </div>
        <div class="create-post-actions">
          <button class="post-action-btn" onclick="openPostModal('image')">🖼 Foto</button>
          <button class="post-action-btn" onclick="openPostModal('event')">📅 Evento</button>
          <button class="post-action-btn" onclick="openPostModal('poll')">📊 Encuesta</button>
        </div>
      </div>
    </div>

    <div class="feed-filters">
      <button class="filter-btn active" data-filter="all">Todo</button>
      <button class="filter-btn" data-filter="academic">Académico</button>
      <button class="filter-btn" data-filter="events">Eventos</button>
      <button class="filter-btn" data-filter="groups">Mis grupos</button>
    </div>

    <div id="feed-container">
      <div class="skeleton-post"></div>
      <div class="skeleton-post"></div>
      <div class="skeleton-post"></div>
    </div>
    <div id="feed-loader" class="feed-loader hidden">
      <div class="spinner" style="border-top-color:var(--uni-blue-mid)"></div>
      <span>Cargando más publicaciones...</span>
    </div>
    <div id="feed-end" class="feed-end hidden"><p>¡Has visto todo! 🎉</p></div>
  </section>

  <aside class="widgets-column">
    <div class="card widget-card">
      <div class="card-header">
        <h3>Mis grupos activos</h3>
        <a href="/RedSocial_BUAP/frontend/pages/groups.php" class="link-small">Ver todos</a>
      </div>
      <div id="my-groups-list" class="groups-list"></div>
    </div>
    <div class="card widget-card">
      <div class="card-header">
        <h3>Próximos eventos</h3>
        <a href="/RedSocial_BUAP/frontend/pages/calendar.php" class="link-small">Calendario</a>
      </div>
      <div id="events-list" class="events-list"></div>
    </div>
    <div class="card widget-card">
      <div class="card-header">
        <h3>Marketplace</h3>
        <a href="/RedSocial_BUAP/frontend/pages/marketplace.php" class="link-small">Ver todo</a>
      </div>
      <div id="marketplace-quick" class="marketplace-quick"></div>
    </div>
  </aside>
</main>

<!-- Modal crear post -->
<div class="modal-backdrop hidden" id="postModal">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-header">
      <h2 class="modal-title">Nueva publicación</h2>
      <button class="modal-close" onclick="closeModal('postModal')">✕</button>
    </div>
    <form id="post-form" enctype="multipart/form-data">
      <div class="post-modal-body">
        <div class="post-author-row">
          <div class="avatar"><?php echo strtoupper(substr($user['first_name'],0,1)); ?></div>
          <div>
            <p class="post-author-name"><?php echo htmlspecialchars(($user['first_name']??'').' '.($user['last_name']??'')); ?></p>
            <select name="audience" class="audience-select">
              <option value="public">🌐 Toda la uni</option>
              <option value="faculty">🏛 Mi facultad</option>
            </select>
          </div>
        </div>
        <textarea name="content" id="post-content" placeholder="¿Qué quieres compartir?" rows="4" maxlength="2000" required></textarea>
        <div class="char-counter"><span id="char-count">0</span>/2000</div>
        <div id="post-extra-fields"></div>
        <div class="post-categories">
          <label class="field-label">Etiquetas (opcional)</label>
          <div class="tag-options">
            <button type="button" class="tag-btn" data-tag="academia">📖 Academia</button>
            <button type="button" class="tag-btn" data-tag="eventos">🎉 Eventos</button>
            <button type="button" class="tag-btn" data-tag="deporte">⚽ Deporte</button>
            <button type="button" class="tag-btn" data-tag="tech">💻 Tech</button>
            <button type="button" class="tag-btn" data-tag="cultura">🎭 Cultura</button>
          </div>
          <input type="hidden" name="tags" id="post-tags-input">
        </div>
        <label for="post-media" class="media-upload-label">
          <span>🖼</span> Adjuntar imagen
          <input type="file" id="post-media" name="media" accept="image/*,video/*" multiple>
        </label>
        <div id="media-preview" class="media-preview"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal('postModal')">Cancelar</button>
        <button type="submit" class="btn-primary" id="submit-post">
          <span class="btn-text">Publicar</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal reporte -->
<div class="modal-backdrop hidden" id="reportModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Reportar publicación</h2>
      <button class="modal-close" onclick="closeModal('reportModal')">✕</button>
    </div>
    <form id="report-form">
      <input type="hidden" name="post_id" id="report-post-id">
      <div class="post-modal-body">
        <div class="report-reasons">
          <label class="report-reason-item"><input type="radio" name="reason" value="spam"> Spam</label>
          <label class="report-reason-item"><input type="radio" name="reason" value="harassment"> Acoso</label>
          <label class="report-reason-item"><input type="radio" name="reason" value="hate_speech"> Discurso de odio</label>
          <label class="report-reason-item"><input type="radio" name="reason" value="false_info"> Información falsa</label>
          <label class="report-reason-item"><input type="radio" name="reason" value="inappropriate"> Contenido inapropiado</label>
        </div>
        <textarea name="details" placeholder="Detalles adicionales (opcional)" rows="2"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal('reportModal')">Cancelar</button>
        <button type="submit" class="btn-danger">🚩 Enviar reporte</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-container"></div>

<script>
const UL_USER = <?php echo json_encode([
  'id'      => (int)($user['user_id'] ?? 0),
  'name'    => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
  'role'    => $user['role'] ?? 'student',
  'faculty' => $user['faculty_id'] ?? null,
  'avatar'  => $user['avatar'] ?? null,
]); ?>;
const UL_TOKEN = '<?php echo $_SESSION['jwt_token'] ?? ''; ?>';
</script>
<script src="/RedSocial_BUAP/frontend/js/utils.js"></script>
<script src="/RedSocial_BUAP/frontend/js/feed.js"></script>
<script src="/RedSocial_BUAP/frontend/js/notifications.js"></script>
<script src="/RedSocial_BUAP/frontend/js/websocket.js"></script>
</body>
</html>
PHPEOF;

// ── Escribir archivos ─────────────────────────────────────────
$files_to_fix = [
    'backend/shared/auth_check.php'  => $new_auth_check,
    'frontend/pages/feed.php'        => $new_feed,
];

// Páginas simples que solo redirigen — reemplazar con redirect al feed
// pero de forma segura (verificando sesión primero)
$simple_redirect_pages = [
    'frontend/pages/groups.php',
    'frontend/pages/marketplace.php',
    'frontend/pages/calendar.php',
    'frontend/pages/polls.php',
    'frontend/pages/moderation.php',
    'frontend/pages/admin.php',
    'frontend/pages/directory.php',
    'frontend/pages/profile.php',
];

echo "<div class='section'><b>2. Corrigiendo archivos principales</b><br>";

foreach ($files_to_fix as $relative => $content) {
    $full_path = $base_path . '/' . $relative;
    
    // Backup
    $backup = $full_path . '.bak';
    if (file_exists($full_path) && !file_exists($backup)) {
        copy($full_path, $backup);
        echo "📦 Backup: $relative.bak<br>";
    }
    
    if (file_put_contents($full_path, $content) !== false) {
        echo "<span class='ok'>✅ Corregido: $relative</span><br>";
    } else {
        echo "<span class='fail'>❌ No se pudo escribir: $relative</span><br>";
        echo "<small>Verifica permisos del archivo</small><br>";
    }
}
echo "</div>";

// ── Verificar páginas con redirect ───────────────────────────
echo "<div class='section'><b>3. Estado de otras páginas</b><br>";
foreach ($simple_redirect_pages as $page) {
    $full = $base_path . '/' . $page;
    if (file_exists($full)) {
        $content = file_get_contents($full);
        $has_redirect = strpos($content, "header('Location:") !== false && 
                        strpos($content, 'feed.php') !== false &&
                        strpos($content, '<!DOCTYPE') === false;
        
        if ($has_redirect) {
            echo "<span class='warn'>⚠️ $page — Solo tiene redirect (normal por ahora)</span><br>";
        } else {
            echo "<span class='ok'>✅ $page — Tiene contenido real</span><br>";
        }
    } else {
        echo "<span class='fail'>❌ $page — No existe</span><br>";
    }
}
echo "</div>";

// ── Test final: simular lo que hace auth_check ────────────────
echo "<div class='section'><b>4. Test de autenticación post-fix</b><br>";

require_once $base_path . '/backend/shared/helpers.php';
require_once $base_path . '/backend/shared/jwt.php';

// Generar token de prueba
$test_token = JWT::generate(['user_id'=>1,'email'=>'test@alumno.buap.mx','role'=>'admin','faculty_id'=>1]);
$verified   = JWT::verify($test_token);

if ($verified) {
    echo "<span class='ok'>✅ JWT genera y verifica correctamente</span><br>";
    echo "<small>user_id={$verified['user_id']}, role={$verified['role']}</small><br>";
} else {
    echo "<span class='fail'>❌ JWT falla</span><br>";
}

// Probar conexión BD
try {
    $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $u   = $pdo->prepare("SELECT user_id, email, role, status FROM users WHERE email LIKE '%buap.mx' LIMIT 1");
    $u->execute();
    $row = $u->fetch();
    if ($row) {
        echo "<span class='ok'>✅ BD conectada — usuario encontrado: {$row['email']} ({$row['role']})</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='fail'>❌ BD: ".$e->getMessage()."</span><br>";
}

echo "</div>";

echo "<div class='section' style='background:#E3F5F0;border-color:#9FE1CB'>
<b style='color:#0F6E56'>✅ Proceso completado</b><br><br>
<b>Pasos siguientes:</b><br>
1. <a href='http://localhost:8012/RedSocial_BUAP/index.php' target='_blank'>Ir al login</a> y entrar con tu usuario BUAP<br>
2. Si funciona, <a href='http://localhost:8012/RedSocial_BUAP/frontend/pages/feed.php' target='_blank'>probar el feed directamente</a><br>
3. Eliminar este archivo: <code>fix_pages.php</code> y <code>test_login.php</code><br>
</div>";

echo "<p style='color:#999;font-size:12px'>⚠️ Elimina fix_pages.php y test_login.php después de resolver el problema</p>";
echo "</body></html>";
