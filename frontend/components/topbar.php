<?php
// frontend/components/topbar.php
if (!isset($user)) $user = $_SESSION['user'] ?? [];
$initial = strtoupper(substr($user['first_name']??'U',0,1));
$base    = '/RedSocial_BUAP';
?>
<div class="topbar" id="topbar">
  <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>

  <div class="topbar-search">
    <span class="search-icon">🔍</span>
    <input type="search" placeholder="Buscar personas, grupos, publicaciones..."
           id="globalSearch" autocomplete="off">
    <div class="search-results" id="searchResults"></div>
  </div>

  <div class="topbar-actions">
    <div style="position:relative">
      <button class="topbar-btn notif-btn" id="notifBtn" title="Notificaciones">
        🔔<span class="notif-dot" id="notifDot"></span>
      </button>
      <div class="notif-panel" id="notifPanel">
        <div class="notif-header">
          <h4>Notificaciones</h4>
          <button onclick="markAllRead()" class="link-small">Marcar leídas</button>
        </div>
        <div id="notif-list" class="notif-list">
          <p style="padding:16px;text-align:center;font-size:14px;color:var(--text-muted)">Cargando...</p>
        </div>
      </div>
    </div>

    <div class="avatar avatar-sm topbar-avatar"
         onclick="window.location.href='<?= $base ?>/frontend/pages/profile.php'">
      <?= $initial ?>
    </div>

    <button class="btn-ghost"
            onclick="if(confirm('¿Cerrar sesión?')) window.location.href='<?= $base ?>/backend/api-gateway/auth.php?action=logout'">
      Salir
    </button>
  </div>
</div>

<script>
  document.getElementById('mobileMenuBtn')?.addEventListener('click', () =>
    document.getElementById('sidebar').classList.add('open'));
  document.getElementById('sidebarClose')?.addEventListener('click', () =>
    document.getElementById('sidebar').classList.remove('open'));
</script>
