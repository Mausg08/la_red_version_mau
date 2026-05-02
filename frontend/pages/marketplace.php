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
  <title>Marketplace — UniLink BUAP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/main.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/dashboard.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/marketplace.css">
</head>
<body class="dashboard-page">

<?php include '../../frontend/components/sidebar.php'; ?>
<?php include '../../frontend/components/topbar.php'; ?>

<main class="main-content main-content-wide" style="display:block;padding:24px;max-width:1300px;margin:0 auto">

  <div class="page-header">
    <div>
      <h1 class="page-title">🛒 Marketplace Universitario</h1>
      <p class="page-subtitle">Compra y vende con otros estudiantes de la BUAP</p>
    </div>
    <div class="page-header-actions">
      <button class="btn-primary" onclick="openNewListingModal()">+ Publicar anuncio</button>
    </div>
  </div>

  <!-- Filtros -->
  <div class="mkt-filters-bar" style="margin-bottom:12px">
    <div class="mkt-filters-row">
      <div class="mkt-search-wrap">
        <span>🔍</span>
        <input type="text" id="mkt-search" placeholder="Buscar en el marketplace..."
               oninput="filterListings()" style="border:none;background:none;outline:none;font-size:14px;width:100%">
      </div>
      <select id="mkt-sort" onchange="filterListings()">
        <option value="recent">Más recientes</option>
        <option value="price_asc">Menor precio</option>
        <option value="price_desc">Mayor precio</option>
        <option value="popular">Más vistos</option>
      </select>
      <input type="number" id="price-min" placeholder="$ Mín" onchange="filterListings()"
             style="width:90px;padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:13px">
      <input type="number" id="price-max" placeholder="$ Máx" onchange="filterListings()"
             style="width:90px;padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:13px">
    </div>
  </div>

  <!-- Categorías -->
  <div id="category-chips" class="category-tabs">
    <button class="cat-tab chip active" data-cat="">🔍 Todos</button>
    <button class="cat-tab chip" data-cat="libros">📚 Libros</button>
    <button class="cat-tab chip" data-cat="calculadoras">🔢 Calculadoras</button>
    <button class="cat-tab chip" data-cat="tutorias">👨‍🏫 Tutorías</button>
    <button class="cat-tab chip" data-cat="electronica">💻 Electrónica</button>
    <button class="cat-tab chip" data-cat="ropa">👕 Ropa</button>
    <button class="cat-tab chip" data-cat="otros">📦 Otros</button>
  </div>

  <!-- Grid de anuncios -->
  <div class="mkt-grid" id="listings-grid">
    <?php for($i=0;$i<8;$i++): ?><div class="listing-skeleton" style="height:280px;background:var(--gray-100);border-radius:var(--radius-lg);animation:shimmer 1.5s infinite;background:linear-gradient(90deg,var(--gray-100) 25%,var(--gray-200) 50%,var(--gray-100) 75%);background-size:200% 100%"></div><?php endfor; ?>
  </div>

  <div id="listings-loader" class="feed-loader hidden" style="padding:20px;text-align:center;color:var(--text-muted)">
    <div class="spinner" style="border-top-color:var(--uni-blue-mid);margin:0 auto 8px"></div>
    Cargando más anuncios...
  </div>
  <div id="listings-end" class="hidden" style="text-align:center;padding:20px;color:var(--text-muted)">
    ¡Has visto todos los anuncios! 🎉
  </div>
</main>

<!-- Modal detalle de anuncio -->
<div class="modal-backdrop hidden" id="listingDetailModal">
  <div class="modal" style="max-width:720px;padding:0;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <h2 class="modal-title" id="detail-title" style="margin:0;font-size:18px">Detalle del anuncio</h2>
      <button class="modal-close" style="position:static" onclick="closeModal('listingDetailModal')">✕</button>
    </div>
    <div id="listing-detail-body" style="display:grid;grid-template-columns:1fr 1fr;gap:0;max-height:80vh;overflow-y:auto">
      <div style="text-align:center;padding:40px">
        <div class="spinner" style="border-top-color:var(--uni-blue-mid);margin:0 auto"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal nuevo anuncio -->
<div class="modal-backdrop hidden" id="newListingModal">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h2 class="modal-title">Publicar anuncio</h2>
      <button class="modal-close" onclick="closeModal('newListingModal')">✕</button>
    </div>
    <form id="listing-form" enctype="multipart/form-data">
      <div class="post-modal-body">
        <div class="form-group">
          <label>Título *</label>
          <input type="text" name="title" placeholder="¿Qué vendes?" required maxlength="200">
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="description" rows="3" placeholder="Describe el artículo..." maxlength="1000"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label>Precio (MXN) *</label>
            <input type="number" name="price" min="0" step="0.01" placeholder="0.00" required>
          </div>
          <div class="form-group">
            <label>Categoría</label>
            <select name="category">
              <option value="libros">📚 Libros</option>
              <option value="calculadoras">🔢 Calculadoras</option>
              <option value="tutorias">👨‍🏫 Tutorías</option>
              <option value="electronica">💻 Electrónica</option>
              <option value="ropa">👕 Ropa</option>
              <option value="otros">📦 Otros</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Condición</label>
          <select name="condition_val">
            <option value="nuevo">✨ Nuevo</option>
            <option value="como_nuevo">🌟 Como nuevo</option>
            <option value="buen_estado" selected>👍 Buen estado</option>
            <option value="usado">📦 Usado</option>
          </select>
        </div>
        <div class="form-group">
          <label>Fotos (máx. 4)</label>
          <label for="listing-images" class="media-upload-label">
            📷 Subir fotos
            <input type="file" id="listing-images" name="images[]" accept="image/*" multiple>
          </label>
          <div id="listing-img-preview" class="media-preview"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal('newListingModal')">Cancelar</button>
        <button type="submit" class="btn-primary">Publicar anuncio</button>
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
<script src="<?= $base ?>/frontend/js/marketplace.js"></script>
<script src="<?= $base ?>/frontend/js/notifications.js"></script>
</body>
</html>
