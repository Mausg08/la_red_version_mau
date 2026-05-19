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
  <title>Encuestas - UniLink</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/main.css?v=<?= filemtime(__DIR__ . '/../css/main.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../css/dashboard.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/polls.css?v=<?= filemtime(__DIR__ . '/../css/polls.css') ?>">
</head>
<body class="dashboard-page">
<?php include '../components/sidebar.php'; ?>
<?php include '../components/topbar.php'; ?>

<main class="main-content main-content-wide">
  <div class="page-header">
    <div>
      <h1 class="page-title">Encuestas</h1>
      <p class="page-subtitle">Crea encuestas públicas, para tu facultad o para tus grupos.</p>
    </div>
    <div class="page-header-actions">
      <button class="btn-primary" onclick="openCreatePollModal()">+ Crear encuesta</button>
    </div>
  </div>

  <div class="category-tabs">
    <button class="cat-tab active" onclick="filterPolls('')">Todas</button>
    <button class="cat-tab" onclick="filterPolls('activa')">Activas</button>
    <button class="cat-tab" onclick="filterPolls('cafeteria')">Cafetería</button>
    <button class="cat-tab" onclick="filterPolls('laboratorio')">Laboratorio</button>
    <button class="cat-tab" onclick="filterPolls('transporte')">Transporte</button>
    <button class="cat-tab" onclick="filterPolls('biblioteca')">Biblioteca</button>
    <button class="cat-tab" onclick="filterPolls('academico')">Académico</button>
  </div>

  <div id="polls-banner" class="polls-summary-banner" style="display:none">
    <div id="polls-summary-grid" class="polls-summary-grid"></div>
  </div>

  <div id="polls-grid" class="polls-grid">
    <?php for($i=0;$i<6;$i++): ?><div class="skeleton-card" style="height:220px"></div><?php endfor; ?>
  </div>
  <div id="polls-empty" class="empty-state hidden">
    <h3>Sin encuestas</h3>
    <p>Crea la primera encuesta para tu comunidad.</p>
    <button class="btn-primary" onclick="openCreatePollModal()">Crear encuesta</button>
  </div>
</main>

<div class="modal-backdrop hidden" id="createPollModal">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <h2 class="modal-title">Crear encuesta</h2>
      <button class="modal-close" onclick="closeModal('createPollModal')">×</button>
    </div>
    <form id="create-poll-form">
      <div class="post-modal-body">
        <div class="form-group">
          <label>Pregunta *</label>
          <input type="text" name="title" maxlength="300" required placeholder="Ej: ¿Qué servicio debería mejorar primero?">
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="description" rows="2" maxlength="500" placeholder="Contexto opcional para la encuesta"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Categoría</label>
            <select name="category">
              <option value="general">General</option>
              <option value="cafeteria">Cafetería</option>
              <option value="laboratorio">Laboratorio</option>
              <option value="transporte">Transporte</option>
              <option value="biblioteca">Biblioteca</option>
              <option value="academico">Académico</option>
            </select>
          </div>
          <div class="form-group">
            <label>Dirigida a</label>
            <select name="audience" id="poll-audience" onchange="togglePollAudienceTarget(this.value)">
              <option value="public">Público general</option>
              <option value="faculty">Mi facultad</option>
              <option value="group">Un grupo</option>
            </select>
          </div>
        </div>
        <div class="form-group hidden" id="poll-group-target">
          <label>Grupo</label>
          <select name="group_id" id="poll-group-select">
            <option value="">Selecciona un grupo</option>
          </select>
        </div>
        <div class="form-group">
          <label>Tipo</label>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
            <label class="checkbox-label"><input type="radio" name="poll_type" value="options" checked onchange="setPollType('options')"> Opciones</label>
            <label class="checkbox-label"><input type="radio" name="poll_type" value="yesno" onchange="setPollType('yesno')"> Sí / No</label>
            <label class="checkbox-label"><input type="radio" name="poll_type" value="rating" onchange="setPollType('rating')"> Calificación</label>
          </div>
        </div>
        <div id="poll-options-section">
          <div id="poll-options-list">
            <div class="form-group"><input type="text" name="option[]" placeholder="Opción 1" required></div>
            <div class="form-group"><input type="text" name="option[]" placeholder="Opción 2" required></div>
          </div>
          <button type="button" class="btn-secondary" onclick="addPollOptionField()">+ Agregar opción</button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal('createPollModal')">Cancelar</button>
        <button type="submit" class="btn-primary">Publicar encuesta</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-container"></div>
<script>
const UL_USER = <?= json_encode(['id'=>$user['user_id'],'role'=>$user['role'],'faculty_id'=>$user['faculty_id'] ?? null]) ?>;
const UL_TOKEN = '<?= $_SESSION['jwt_token'] ?? '' ?>';
</script>
<script src="<?= $base ?>/frontend/js/utils.js?v=<?= filemtime(__DIR__ . '/../js/utils.js') ?>"></script>
<script src="<?= $base ?>/frontend/js/polls.js?v=<?= filemtime(__DIR__ . '/../js/polls.js') ?>"></script>
</body>
</html>
