<?php
session_start();
require_once '../../backend/shared/auth_check.php';
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Objetos Perdidos — UniLink</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/RedSocial_BUAP/frontend/css/main.css?v=<?php echo filemtime(__DIR__ . '/../css/main.css'); ?>">
  <link rel="stylesheet" href="/RedSocial_BUAP/frontend/css/dashboard.css?v=<?php echo filemtime(__DIR__ . '/../css/dashboard.css'); ?>">
  <link rel="stylesheet" href="/RedSocial_BUAP/frontend/css/marketplace.css?v=<?php echo filemtime(__DIR__ . '/../css/marketplace.css'); ?>">
</head>
<body class="dashboard-page">
<?php include '../components/sidebar.php'; ?>
<?php include '../components/topbar.php'; ?>

<main class="main-content main-content-wide">
  <div class="page-header">
    <div>
      <h1 class="page-title">🔍 Objetos Perdidos y Encontrados</h1>
      <p class="page-subtitle">¿Perdiste algo en el campus? ¿Encontraste algo? Publícalo aquí.</p>
    </div>
    <div class="page-header-actions">
      <button class="btn-primary" onclick="openLostModal()">+ Reportar objeto</button>
    </div>
  </div>

  <div class="category-tabs">
    <button class="cat-tab active" onclick="setLostFilter('all', this)">🔍 Todo</button>
    <button class="cat-tab" onclick="setLostFilter('lost', this)">❓ Perdidos</button>
    <button class="cat-tab" onclick="setLostFilter('found', this)">✅ Encontrados</button>
  </div>

  <div class="mkt-grid" id="lost-grid">
    <?php for($i=0;$i<6;$i++): ?><div class="skeleton-card" style="height:200px"></div><?php endfor; ?>
  </div>
  <div id="lost-empty" class="empty-state hidden">
    <p style="font-size:48px">🔍</p>
    <h3>Sin reportes activos</h3>
    <p>¿Perdiste o encontraste algo? Sé el primero en publicar</p>
    <button class="btn-primary" style="margin-top:16px" onclick="openLostModal()">Reportar objeto</button>
  </div>
</main>

<!-- Report Modal -->
<div class="modal-backdrop hidden" id="lostModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h2 class="modal-title">Reportar objeto</h2>
      <button class="modal-close" onclick="closeModal('lostModal')">✕</button>
    </div>
    <form id="lost-form" enctype="multipart/form-data">
      <div class="post-modal-body">
        <div class="form-group">
          <label>¿Perdiste o encontraste?</label>
          <div style="display:flex;gap:10px;margin-top:6px">
            <label class="checkbox-label" style="flex:1;padding:12px;border:2px solid var(--border);border-radius:var(--radius-md);cursor:pointer;justify-content:center">
              <input type="radio" name="lost_type" value="lost" checked> ❓ Perdí algo
            </label>
            <label class="checkbox-label" style="flex:1;padding:12px;border:2px solid var(--border);border-radius:var(--radius-md);cursor:pointer;justify-content:center">
              <input type="radio" name="lost_type" value="found"> ✅ Encontré algo
            </label>
          </div>
        </div>
        <div class="form-group">
          <label>Descripción del objeto *</label>
          <input type="text" name="title" placeholder="Ej: Calculadora Casio negra" required maxlength="200">
        </div>
        <div class="form-group">
          <label>Detalles adicionales</label>
          <textarea name="description" rows="2" placeholder="Color, marca, características especiales..." maxlength="500"></textarea>
        </div>
        <div class="form-group">
          <label>Lugar donde se perdió/encontró *</label>
          <input type="text" name="lost_location" placeholder="Ej: Biblioteca central, piso 2" required maxlength="200">
        </div>
        <div class="form-group">
          <label>Foto (recomendada)</label>
          <label for="lost-img" class="media-upload-label">
            📷 Subir foto
            <input type="file" id="lost-img" name="images" accept="image/*">
          </label>
          <div id="lost-img-preview" class="media-preview"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal('lostModal')">Cancelar</button>
        <button type="submit" class="btn-primary"><span class="btn-text">Publicar</span></button>
      </div>
    </form>
  </div>
</div>

<div id="toast-container"></div>
<script>
const UL_USER  = <?= json_encode(['id'=>$user['user_id'],'role'=>$user['role']]) ?>;
const UL_TOKEN = '<?= $_SESSION['jwt_token'] ?? '' ?>';
let lostFilter = 'all';

async function loadLostItems() {
  const grid = document.getElementById('lost-grid');
  grid.innerHTML = Array(6).fill('<div class="skeleton-card" style="height:200px"></div>').join('');
  document.getElementById('lost-empty').classList.add('hidden');

  try {
    const params = lostFilter !== 'all' ? `lost_type=${lostFilter}&` : '';
    const res = await apiFetch(`marketplace/listings?${params}is_lost_found=1&status=active&limit=24`);
    const listings = res.data || [];
    grid.innerHTML = '';
    if (!listings.length) { document.getElementById('lost-empty').classList.remove('hidden'); return; }
    listings.forEach(l => {
      const el = document.createElement('div');
      el.className = 'listing-card';
      el.innerHTML = `
        <div class="listing-img" style="height:130px">
          ${l.thumbnail ? `<img src="${escHtml(l.thumbnail)}" alt="" style="width:100%;height:100%;object-fit:cover">` : `<span>${l.lost_type==='found'?'✅':'❓'}</span>`}
        </div>
        <div class="listing-body">
          <div style="margin-bottom:4px">
            <span class="badge badge-${l.lost_type==='found'?'green':'orange'}" style="font-size:11px">
              ${l.lost_type==='found'?'✅ Encontrado':'❓ Perdido'}
            </span>
          </div>
          <div class="listing-title">${escHtml(l.title)}</div>
          ${l.lost_location ? `<div style="font-size:12px;color:var(--text-secondary);margin-top:4px">📍 ${escHtml(l.lost_location)}</div>` : ''}
          <div class="listing-seller" style="margin-top:6px">
            <div class="avatar avatar-sm" style="width:18px;height:18px;font-size:9px">${(l.seller_name||'?')[0].toUpperCase()}</div>
            ${escHtml(l.seller_name||'')} · ${timeAgo(l.created_at)}
          </div>
          <button class="btn-secondary" style="width:100%;margin-top:10px;font-size:13px"
                  onclick="contactAboutItem(${l.seller_id}, '${escHtml(l.title)}')">
            💬 Contactar
          </button>
        </div>`;
      grid.appendChild(el);
    });
  } catch { showToast('Error al cargar', 'error'); }
}

function setLostFilter(f, btn) {
  lostFilter = f;
  document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  loadLostItems();
}

function openLostModal() {
  document.getElementById('lostModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  document.getElementById(id)?.classList.add('hidden');
  document.body.style.overflow = '';
}

async function contactAboutItem(sellerId, title) {
  try {
    const { contact } = await apiFetch(`users/${sellerId}/contact`);
    alert(`Contacto:\n${contact.email}\n\nMensaje: "Hola, vi tu publicación sobre: ${title}"`);
  } catch { showToast('No disponible', 'error'); }
}

document.getElementById('lost-img')?.addEventListener('change', function() {
  const preview = document.getElementById('lost-img-preview');
  const reader = new FileReader();
  reader.onload = e => {
    preview.innerHTML = `<img src="${e.target.result}" class="media-preview-item">`;
  };
  if (this.files[0]) reader.readAsDataURL(this.files[0]);
});

document.getElementById('lost-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('.btn-text');
  btn.textContent = 'Publicando...';
  const fd = new FormData(e.target);
  fd.append('is_lost_found', '1');
  fd.append('price', '0');
  fd.append('category', 'otros');
  try {
    const res = await fetch('/RedSocial_BUAP/backend/api-gateway/index.php?service=marketplace&path=marketplace/listings', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${UL_TOKEN}` },
      body: fd
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    closeModal('lostModal');
    showToast('Reporte publicado 🔍', 'success');
    loadLostItems();
  } catch (err) { showToast(err.message || 'Error', 'error'); }
  finally { btn.textContent = 'Publicar'; }
});

document.addEventListener('DOMContentLoaded', loadLostItems);
</script>
<script src="/RedSocial_BUAP/frontend/js/utils.js?v=<?php echo filemtime(__DIR__ . '/../js/utils.js'); ?>"></script>
</body>
</html>
