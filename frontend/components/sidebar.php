<?php
// frontend/components/sidebar.php
if (!isset($user)) $user = $_SESSION['user'] ?? [];
$roles    = ['student'=>'Estudiante','professor'=>'Profesor','admin'=>'Admin','moderator'=>'Moderador','staff'=>'Administrativo'];
$initials = strtoupper(substr($user['first_name']??'U',0,1).substr($user['last_name']??'',0,1));
$base     = '/RedSocial_BUAP';
$current  = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="brand-logo">
      <div class="logo-icon">U</div>
      <span class="logo-text">UniLink</span>
    </div>
    <button class="sidebar-close-btn" id="sidebarClose">✕</button>
  </div>

  <div class="sidebar-profile">
    <div class="avatar avatar-lg"><?= $initials ?></div>
    <div class="profile-info">
      <p class="profile-name"><?= htmlspecialchars(($user['first_name']??'').' '.($user['last_name']??'')) ?></p>
      <p class="profile-role badge badge-blue"><?= $roles[$user['role']??'student'] ?? 'Estudiante' ?></p>
      <p class="profile-faculty"><?= htmlspecialchars($user['faculty_name']??'') ?></p>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Principal</div>
    <a href="<?= $base ?>/frontend/pages/feed.php" class="nav-item <?= $current==='feed'?'active':'' ?>">
      <span class="nav-icon">🏠</span> Feed
    </a>
    <a href="<?= $base ?>/frontend/pages/groups.php" class="nav-item <?= $current==='groups'?'active':'' ?>">
      <span class="nav-icon">👥</span> Mis grupos
    </a>
    <a href="<?= $base ?>/frontend/pages/calendar.php" class="nav-item <?= $current==='calendar'?'active':'' ?>">
      <span class="nav-icon">📅</span> Calendario
    </a>

    <div class="nav-section-label">Comunidad</div>
    <a href="<?= $base ?>/frontend/pages/marketplace.php" class="nav-item <?= $current==='marketplace'?'active':'' ?>">
      <span class="nav-icon">🛒</span> Marketplace
    </a>
    <a href="<?= $base ?>/frontend/pages/lost-found.php" class="nav-item <?= $current==='lost-found'?'active':'' ?>">
      <span class="nav-icon">🔍</span> Objetos perdidos
    </a>
    <a href="<?= $base ?>/frontend/pages/directory.php" class="nav-item <?= $current==='directory'?'active':'' ?>">
      <span class="nav-icon">🗂</span> Directorio
    </a>
    <a href="<?= $base ?>/frontend/pages/polls.php" class="nav-item <?= $current==='polls'?'active':'' ?>">
      <span class="nav-icon">📊</span> Encuestas
    </a>

    <?php if (in_array($user['role']??'', ['admin','moderator'])): ?>
    <div class="nav-section-label">Moderación</div>
    <a href="<?= $base ?>/frontend/pages/moderation.php" class="nav-item nav-item-mod <?= $current==='moderation'?'active':'' ?>">
      <span class="nav-icon">🛡</span> Moderación
      <span class="nav-badge danger" id="badge-reports"></span>
    </a>
    <?php endif; ?>

    <?php if (($user['role']??'') === 'admin'): ?>
    <a href="<?= $base ?>/frontend/pages/admin.php" class="nav-item nav-item-mod <?= $current==='admin'?'active':'' ?>">
      <span class="nav-icon">⚙️</span> Administración
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-panic">
    <button class="btn-panic" onclick="triggerPanic()">
      <span>🚨</span> Botón de Pánico
    </button>
    <p class="panic-hint">Contacta seguridad campus</p>
  </div>
</aside>
