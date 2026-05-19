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
  <title>Directorio - UniLink</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/main.css?v=<?= filemtime(__DIR__ . '/../css/main.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../css/dashboard.css') ?>">
  <style>
    .directory-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
    .directory-toolbar input,.directory-toolbar select{min-width:180px}
    .directory-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
    .person-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;box-shadow:var(--shadow-sm)}
    .person-head{display:flex;gap:12px;align-items:center;margin-bottom:12px}
    .person-name{font-weight:800;font-size:16px}
    .person-meta{font-size:12px;color:var(--text-muted);margin-top:2px}
    .person-actions{display:flex;gap:8px;margin-top:14px}
    .directory-tabs{display:flex;gap:8px;margin-bottom:14px}
  </style>
</head>
<body class="dashboard-page">
<?php include '../components/sidebar.php'; ?>
<?php include '../components/topbar.php'; ?>

<main class="main-content main-content-wide">
  <div class="page-header">
    <div>
      <h1 class="page-title">Directorio</h1>
      <p class="page-subtitle">Encuentra usuarios de la comunidad y agrégalos a tus contactos.</p>
    </div>
  </div>

  <div class="directory-tabs">
    <button class="mod-tab active" data-view="all" onclick="switchDirectoryView('all')">Todos</button>
    <button class="mod-tab" data-view="contacts" onclick="switchDirectoryView('contacts')">Mis contactos</button>
  </div>

  <div class="directory-toolbar">
    <input type="search" id="directory-search" placeholder="Buscar por nombre o matrícula" oninput="debounceDirectorySearch(this.value)">
    <input type="number" id="directory-faculty" placeholder="ID facultad" min="1" oninput="debounceDirectorySearch(document.getElementById('directory-search').value)">
    <button class="btn-secondary" onclick="loadDirectory(true)">Actualizar</button>
  </div>

  <div id="directory-grid" class="directory-grid">
    <?php for($i=0;$i<6;$i++): ?><div class="skeleton-card" style="height:180px"></div><?php endfor; ?>
  </div>

  <div id="directory-empty" class="empty-state hidden">
    <h3>Sin resultados</h3>
    <p>No encontramos usuarios con esos filtros.</p>
  </div>
</main>

<div id="toast-container"></div>
<script>
const UL_USER = <?= json_encode(['id'=>$user['user_id'],'role'=>$user['role'],'name'=>$user['first_name'].' '.$user['last_name']]) ?>;
const UL_TOKEN = '<?= $_SESSION['jwt_token'] ?? '' ?>';
</script>
<script src="<?= $base ?>/frontend/js/utils.js?v=<?= filemtime(__DIR__ . '/../js/utils.js') ?>"></script>
<script src="<?= $base ?>/frontend/js/directory.js?v=<?= filemtime(__DIR__ . '/../js/directory.js') ?>"></script>
</body>
</html>
