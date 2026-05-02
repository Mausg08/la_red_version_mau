<?php
session_start();
require_once '../../backend/shared/auth_check.php';
$user     = $_SESSION['user'];
$base     = '/RedSocial_BUAP';

$profile_id    = isset($_GET['id']) ? (int)$_GET['id'] : (int)$user['user_id'];
$is_own_profile = $profile_id === (int)$user['user_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil — UniLink BUAP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/main.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/dashboard.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/profile.css">
</head>
<body class="dashboard-page">

<?php include '../../frontend/components/sidebar.php'; ?>
<?php include '../../frontend/components/topbar.php'; ?>

<main class="main-content profile-layout" id="mainContent">

  <!-- Tarjeta de perfil -->
  <div class="card profile-card">
    <div class="profile-cover"></div>
    <div class="profile-main">
      <div class="profile-avatar-wrap">
        <div class="avatar avatar-xl" id="profile-avatar-el">
          <?= strtoupper(substr($user['first_name'],0,1).substr($user['last_name']??'',0,1)) ?>
        </div>
        <?php if($is_own_profile): ?>
        <label class="avatar-change-btn" title="Cambiar foto">
          📷
          <input type="file" accept="image/*" style="display:none" onchange="uploadAvatar(this)">
        </label>
        <?php endif; ?>
      </div>
      <div id="profile-info-section">
        <div style="text-align:center;padding:20px;color:var(--text-muted)">
          <div class="spinner" style="border-top-color:var(--uni-blue-mid);margin:0 auto 10px"></div>
          Cargando perfil...
        </div>
      </div>
    </div>
  </div>

  <!-- Columna de posts -->
  <div class="profile-posts-col">
    <h2 class="section-title" id="posts-section-title">Publicaciones</h2>
    <div id="profile-feed"></div>
    <div id="profile-feed-loader" class="feed-loader hidden">
      <div class="spinner" style="border-top-color:var(--uni-blue-mid)"></div>
      <span>Cargando publicaciones...</span>
    </div>
  </div>
</main>

<!-- Modal editar perfil -->
<div class="modal-backdrop hidden" id="editProfileModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Editar perfil</h2>
      <button class="modal-close" onclick="closeModal('editProfileModal')">✕</button>
    </div>
    <form id="edit-profile-form">
      <div class="post-modal-body">
        <div class="form-group">
          <label>Biografía</label>
          <textarea id="edit-bio" name="bio" rows="3" placeholder="Cuéntanos sobre ti..." maxlength="300"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label>Semestre actual</label>
            <select id="edit-semester" name="semester">
              <?php for($i=1;$i<=10;$i++): ?>
              <option value="<?= $i ?>"><?= $i ?>° semestre</option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Teléfono (opcional)</label>
            <input type="tel" id="edit-phone" name="phone" placeholder="222 000 0000">
          </div>
        </div>
        <label class="checkbox-label">
          <input type="checkbox" id="edit-show-phone" name="show_phone">
          Mostrar teléfono en mi perfil
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal('editProfileModal')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-container"></div>

<script>
const UL_USER      = <?= json_encode(['id'=>$user['user_id'],'role'=>$user['role'],'name'=>($user['first_name']??'').' '.($user['last_name']??'')]) ?>;
const UL_TOKEN     = '<?= $_SESSION['jwt_token'] ?? '' ?>';
const PROFILE_ID   = <?= $profile_id ?>;
const IS_OWN_PROFILE = <?= $is_own_profile ? 'true' : 'false' ?>;
</script>
<script src="<?= $base ?>/frontend/js/utils.js"></script>
<script src="<?= $base ?>/frontend/js/feed.js"></script>
<script src="<?= $base ?>/frontend/js/profile.js"></script>
<script src="<?= $base ?>/frontend/js/notifications.js"></script>
</body>
</html>
