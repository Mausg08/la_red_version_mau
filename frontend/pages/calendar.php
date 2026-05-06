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
  <title>Calendario — UniLink BUAP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/main.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/dashboard.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/calendar.css">
</head>
<body class="dashboard-page">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo-icon">U</div>
      <span class="logo-text">UniLink</span>
      <button class="sidebar-close-btn" onclick="toggleSidebar()">✕</button>
    </div>
    <div class="sidebar-profile" onclick="window.location.href='<?= $base ?>/frontend/pages/profile.php'">
      <div class="profile-avatar"><?= strtoupper(substr($user['first_name']??'U',0,1)) ?></div>
      <div>
        <div class="profile-name"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
        <div class="profile-role"><?= ucfirst($user['role']??'estudiante') ?></div>
      </div>
    </div>
    <nav class="sidebar-nav" id="sidebarNav"></nav>
    <div class="sidebar-panic">
      <button class="panic-btn" onclick="triggerPanic()">🚨 Botón de Pánico</button>
    </div>
  </aside>

  <!-- Topbar -->
  <div class="topbar">
    <button class="hamburger" onclick="toggleSidebar()">☰</button>
    <div class="topbar-search">
      <input type="text" placeholder="Buscar en UniLink...">
    </div>
    <div class="topbar-actions">
      <button class="icon-btn" onclick="window.location.href='<?= $base ?>/frontend/pages/notifications.php'">🔔</button>
      <button class="icon-btn" onclick="logout()">🚪</button>
    </div>
  </div>

  <!-- Main Content: Calendar -->
  <main class="main-content cal-layout" id="mainContent">

    <!-- Calendar section -->
    <div class="cal-container">
      <!-- Toolbar -->
      <div class="cal-toolbar">
        <div class="cal-nav">
          <button class="cal-nav-btn btn-ghost" id="cal-prev">‹</button>
          <span class="cal-month-label" id="cal-month-label">Cargando...</span>
          <button class="cal-nav-btn btn-ghost" id="cal-next">›</button>
        </div>
        <button class="cal-today-btn btn-secondary" id="cal-today">Hoy</button>
        <div class="cal-view-toggle">
          <button class="view-btn active" data-view="month" onclick="switchView('month')">Mes</button>
          <button class="view-btn" data-view="week" onclick="switchView('week')">Semana</button>
          <button class="view-btn" data-view="list" onclick="switchView('list')">Lista</button>
        </div>
        <div class="cal-filters">
          <select id="cal-type-filter" onchange="renderCalendar()">
            <option value="">Todos los tipos</option>
            <option value="clase">Clase</option>
            <option value="examen">Examen</option>
            <option value="taller">Taller</option>
            <option value="conferencia">Conferencia</option>
            <option value="deportivo">Deportivo</option>
            <option value="cultural">Cultural</option>
            <option value="institucional">Institucional</option>
            <option value="otro">Otro</option>
          </select>
        </div>
      </div>

      <!-- Calendar grid -->
      <div id="cal-view" class="cal-loading">Cargando calendario...</div>
    </div>

    <!-- Upcoming events sidebar -->
    <aside class="cal-upcoming">
      <h3 style="font-family:var(--font-display);font-size:18px;font-weight:800;padding:16px;border-bottom:1px solid var(--border)">
        📅 Próximos eventos
      </h3>
      <div id="upcoming-list"></div>
    </aside>
  </main>

  <!-- Event detail modal -->
  <div class="modal-backdrop hidden" id="eventDetailModal">
    <div class="modal-content" style="max-width:520px;padding:0;overflow:hidden">
      <div id="event-detail-content"></div>
      <button class="modal-close" onclick="closeModal('eventDetailModal')" style="position:absolute;top:16px;right:16px;background:rgba(0,0,0,.5);color:#fff;border:none;width:32px;height:32px;border-radius:50%;font-size:18px;cursor:pointer">✕</button>
    </div>
  </div>

  <!-- Create event modal (only for professors/staff/admin) -->
  <?php if (in_array($user['role']??'', ['professor','staff','admin'])): ?>
  <div class="modal-backdrop hidden" id="createEventModal">
    <div class="modal-content" style="max-width:520px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800">Crear evento</h3>
        <button class="btn-ghost" onclick="closeModal('createEventModal')">✕</button>
      </div>
      <form id="create-event-form">
        <div class="form-group">
          <label>Título *</label>
          <input type="text" name="title" required class="form-input">
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="description" rows="3" class="form-input"></textarea>
        </div>
        <div class="form-group">
          <label>Fecha y hora *</label>
          <input type="datetime-local" name="event_date" required class="form-input">
        </div>
        <div class="form-group">
          <label>Fecha de fin</label>
          <input type="datetime-local" name="end_date" class="form-input">
        </div>
        <div class="form-group">
          <label>Lugar</label>
          <input type="text" name="location" class="form-input" placeholder="Salón, edificio, etc.">
        </div>
        <div class="form-group">
          <label>Tipo</label>
          <select name="type" class="form-input">
            <option value="clase">Clase</option>
            <option value="examen">Examen</option>
            <option value="taller">Taller</option>
            <option value="conferencia">Conferencia</option>
            <option value="deportivo">Deportivo</option>
            <option value="cultural">Cultural</option>
            <option value="institucional">Institucional</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" name="is_public" checked>
            Evento público (visible para todos)
          </label>
        </div>
        <button type="submit" class="btn-primary" style="width:100%">Crear evento</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script>
    const UL_TOKEN = '<?= $_SESSION['jwt_token'] ?? '' ?>';
    const CAN_CREATE_EVENTS = <?= in_array($user['role']??'', ['professor','staff','admin']) ? 'true' : 'false' ?>;
  </script>
  <script src="<?= $base ?>/frontend/js/utils.js"></script>
  <script src="<?= $base ?>/frontend/js/calendar.js"></script>
  <script>
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
    }
  </script>
</body>
</html>