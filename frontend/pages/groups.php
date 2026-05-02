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
  <title>Grupos — UniLink BUAP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/main.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/dashboard.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/groups.css">
</head>
<body class="dashboard-page">

<?php include '../../frontend/components/sidebar.php'; ?>
<?php include '../../frontend/components/topbar.php'; ?>

<main class="main-content" style="display:block;padding:24px;max-width:1200px;margin:0 auto">

  <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 class="page-title" style="font-family:var(--font-display);font-size:26px;font-weight:800">👥 Grupos</h1>
      <p style="font-size:14px;color:var(--text-muted);margin-top:4px">Grupos académicos, clubs y comunidades de la BUAP</p>
    </div>
    <button class="btn-primary" onclick="openCreateGroupModal()">+ Crear grupo</button>
  </div>

  <!-- Tabs -->
  <div class="mod-tabs" style="margin-bottom:20px">
    <button class="mod-tab active" data-tab="mine" onclick="switchGroupTab('mine')">📚 Mis grupos</button>
    <button class="mod-tab" data-tab="explore" onclick="switchGroupTab('explore')">🔍 Explorar</button>
    <button class="mod-tab" data-tab="nrc" onclick="switchGroupTab('nrc')">🎓 Unirse por NRC</button>
  </div>

  <!-- Filtros (solo en explore) -->
  <div id="tab-groups-explore" class="hidden" style="margin-bottom:16px">
    <div class="groups-filter-bar">
      <input type="text" placeholder="🔍 Buscar grupos..." oninput="debounceGroupSearch(this.value)"
             style="flex:1;padding:9px 14px;border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:14px;outline:none">
      <select id="groupType" onchange="loadGroups()">
        <option value="">Todos los tipos</option>
        <option value="nrc">Materia NRC</option>
        <option value="faculty">Facultad</option>
        <option value="club">Club</option>
        <option value="study">Grupo de estudio</option>
        <option value="general">General</option>
      </select>
    </div>
  </div>

  <!-- Unirse por NRC -->
  <div id="tab-groups-nrc" class="hidden" style="margin-bottom:20px">
    <div class="card card-body nrc-join-box">
      <h3 style="font-family:var(--font-display);font-weight:700;margin-bottom:12px">🎓 Unirse a grupo por código NRC</h3>
      <p style="font-size:14px;color:var(--text-secondary);margin-bottom:14px">
        Ingresa el NRC de tu materia para encontrar y unirte al grupo oficial.
      </p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <input type="text" id="nrc-input" placeholder="Ej: 10234"
               style="flex:1;min-width:200px;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:15px;outline:none"
               onkeydown="if(event.key==='Enter')joinByNRC()">
        <button class="btn-primary" onclick="joinByNRC()">Buscar grupo</button>
      </div>
      <div id="nrc-result" style="margin-top:12px"></div>
    </div>
  </div>

  <!-- Grid de grupos -->
  <div class="groups-grid" id="groups-grid">
    <?php for($i=0;$i<6;$i++): ?>
    <div style="height:160px;background:linear-gradient(90deg,var(--gray-100) 25%,var(--gray-200) 50%,var(--gray-100) 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:var(--radius-lg)"></div>
    <?php endfor; ?>
  </div>

  <div id="groups-empty" class="hidden" style="text-align:center;padding:60px 20px;color:var(--text-muted)">
    <p style="font-size:40px;margin-bottom:12px">👥</p>
    <h3 style="font-family:var(--font-display);font-size:20px;margin-bottom:8px">Sin grupos aún</h3>
    <p style="font-size:14px">Únete a un grupo por NRC o explora los disponibles</p>
    <button class="btn-primary" style="margin-top:16px" onclick="switchGroupTab('explore')">Explorar grupos</button>
  </div>
</main>

<!-- Modal crear grupo -->
<div class="modal-backdrop hidden" id="createGroupModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h2 class="modal-title">Crear grupo</h2>
      <button class="modal-close" onclick="closeModal('createGroupModal')">✕</button>
    </div>
    <form id="create-group-form">
      <div class="post-modal-body">
        <div class="form-group">
          <label>Nombre del grupo *</label>
          <input type="text" name="name" placeholder="Ej: Cálculo Diferencial NRC-12345" required maxlength="200">
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="description" rows="2" placeholder="¿De qué trata este grupo?" maxlength="500"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label>Tipo</label>
            <select name="type">
              <option value="general">General</option>
              <option value="nrc">Materia NRC</option>
              <option value="club">Club</option>
              <option value="study">Grupo de estudio</option>
            </select>
          </div>
          <div class="form-group">
            <label>Código NRC (opcional)</label>
            <input type="text" name="nrc_code" placeholder="Ej: 10234" maxlength="20">
          </div>
        </div>
        <div class="form-group">
          <label>Ícono</label>
          <input type="hidden" name="icon" id="selected-icon" value="👥">
          <div class="icon-picker">
            <?php foreach(['👥','📚','💻','🔬','🎨','⚽','🎵','🏗','🤖','📐','💼','🌱'] as $icon): ?>
            <button type="button" class="icon-opt <?= $icon==='👥'?'selected':'' ?>"
                    data-icon="<?= $icon ?>" onclick="selectIcon('<?= $icon ?>')"><?= $icon ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal('createGroupModal')">Cancelar</button>
        <button type="submit" class="btn-primary">Crear grupo</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-container"></div>

<script>
const UL_USER  = <?= json_encode(['id'=>$user['user_id'],'role'=>$user['role']]) ?>;
const UL_TOKEN = '<?= $_SESSION['jwt_token'] ?? '' ?>';
</script>
<script src="<?= $base ?>/frontend/js/utils.js"></script>
<script src="<?= $base ?>/frontend/js/groups.js"></script>
<script src="<?= $base ?>/frontend/js/notifications.js"></script>
</body>
</html>
