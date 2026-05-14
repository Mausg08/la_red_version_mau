<?php
session_start();
require_once '../../backend/shared/auth_check.php';
$user = $_SESSION['user'];
$base = '/RedSocial_BUAP';

$group_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$group_id) {
  header('Location: ' . $base . '/frontend/pages/groups.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Grupo — UniLink BUAP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/main.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/dashboard.css">
  <link rel="stylesheet" href="<?= $base ?>/frontend/css/groups.css">
  <style>
    .group-detail-header {
      display: flex;
      gap: 20px;
      padding: 24px;
      background: var(--white);
      border-radius: var(--radius-lg);
      border: 1px solid var(--border);
      margin-bottom: 20px;
      align-items: center;
    }
    .group-detail-icon {
      width: 72px; height: 72px;
      font-size: 36px;
      background: var(--gray-100);
      border-radius: var(--radius-lg);
      display: flex; align-items: center; justify-content: center;
    }
    .group-detail-name {
      font-family: var(--font-display);
      font-size: 24px;
      font-weight: 800;
    }
    .group-detail-type {
      font-size: 13px;
      color: var(--text-muted);
      margin-top: 4px;
    }
    .group-detail-actions {
      margin-left: auto;
    }
    .group-feed {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .post-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 20px;
    }
    .post-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }
    .post-avatar {
      width: 40px; height: 40px;
      background: var(--uni-blue-mid);
      color: #fff;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700;
      font-size: 16px;
    }
    .post-author {
      font-weight: 600;
      font-size: 14px;
    }
    .post-date {
      font-size: 12px;
      color: var(--text-muted);
    }
    .post-content {
      font-size: 14px;
      line-height: 1.6;
      color: var(--text-primary);
    }
    .member-list {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      padding: 16px;
    }
    .member-chip {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      background: var(--gray-100);
      border-radius: var(--radius-full);
      font-size: 13px;
    }
    .member-chip .avatar {
      width: 28px; height: 28px;
      background: var(--uni-blue-mid);
      color: #fff;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px;
      font-weight: 700;
    }
  </style>
</head>
<body class="dashboard-page">

<?php include '../../frontend/components/sidebar.php'; ?>
<?php include '../../frontend/components/topbar.php'; ?>

<main class="main-content" style="display:block;padding:24px;max-width:900px;margin:0 auto">

  <!-- Group header -->
  <div class="group-detail-header" id="group-header">
    <div class="skeleton-card" style="width:72px;height:72px;border-radius:var(--radius-lg)"></div>
    <div style="flex:1">
      <div class="skeleton-card" style="height:24px;width:60%;margin-bottom:8px"></div>
      <div class="skeleton-card" style="height:14px;width:40%"></div>
    </div>
    <div class="skeleton-card" style="height:36px;width:120px;border-radius:var(--radius-md)"></div>
  </div>

  <!-- Tabs -->
  <div class="mod-tabs" style="margin-bottom:20px">
    <button class="mod-tab active" data-tab="feed" onclick="switchDetailTab('feed')">📢 Publicaciones</button>
    <button class="mod-tab" data-tab="members" onclick="switchDetailTab('members')">👥 Miembros</button>
  </div>

  <!-- Group feed -->
  <div class="group-feed" id="tab-feed">
    <!-- Formulario para publicar -->
    <div id="post-form-container" class="post-form" style="margin-bottom:20px; display:none;">
        <textarea id="post-content" placeholder="Escribe una publicación en este grupo..." rows="3" style="width:100%; padding:12px; border-radius:12px; border:1px solid var(--border); resize:vertical;"></textarea>
        <div style="display:flex; justify-content:flex-end; margin-top:8px;">
            <button id="submit-post-btn" class="btn-primary">Publicar</button>
        </div>
    </div>
    <div id="posts-container">
        <div class="skeleton-card" style="height:120px; border-radius:var(--radius-lg);"></div>
        <div class="skeleton-card" style="height:120px; border-radius:var(--radius-lg);"></div>
    </div>
</div>

  <!-- Members list -->
  <div class="member-list hidden" id="tab-members"></div>

  <div id="group-empty" class="hidden" style="text-align:center;padding:60px;color:var(--text-muted)">
    <p style="font-size:40px">📭</p>
    <h3>No hay publicaciones aún</h3>
    <p>Sé el primero en publicar en este grupo</p>
  </div>
</main>

<div id="toast-container"></div>

<script>
const UL_USER  = <?= json_encode(['id'=>$user['user_id'],'role'=>$user['role']]) ?>;
const UL_TOKEN = '<?= $_SESSION['jwt_token'] ?? '' ?>';
const GROUP_ID = <?= $group_id ?>;
let detailTab = 'feed';
</script>
<script src="<?= $base ?>/frontend/js/utils.js"></script>
<script src="<?= $base ?>/frontend/js/groups.js"></script>

<script>

/* ====== LOAD GROUP DETAIL ====== */
async function loadGroupDetail() {
  try {
    const { data } = await apiFetch(`academic/groups/${GROUP_ID}`);
    if (!data) { window.location.href = 'groups.php'; return; }

    const typeLabels = { nrc:'Materia NRC', faculty:'Facultad', club:'Club', study:'Grupo de estudio', general:'General' };

    document.getElementById('group-header').innerHTML = `
      <div class="group-detail-icon">${data.icon || '👥'}</div>
      <div>
        <div class="group-detail-name">${escHtml(data.name)}</div>
        <div class="group-detail-type">
          ${typeLabels[data.type] || data.type}
          ${data.nrc_code ? ` · NRC ${escHtml(data.nrc_code)}` : ''}
          · ${data.member_count || 0} miembros
        </div>
        ${data.description ? `<p style="font-size:14px;color:var(--text-secondary);margin-top:8px">${escHtml(data.description)}</p>` : ''}
      </div>
      <div class="group-detail-actions">
        ${data.is_member == 1
          ? `<button class="btn-secondary" onclick="leaveGroup(${GROUP_ID})">Salir del grupo</button>`
          : `<button class="btn-primary" onclick="joinGroupFromDetail(${GROUP_ID})">Unirse</button>`
        }
      </div>`;

    // Mostrar/ocultar formulario de publicación según membresía
    const postForm = document.getElementById('post-form-container');
    if (data.is_member == 1) {
      postForm.style.display = 'block';
    } else {
      postForm.style.display = 'none';
    }

    loadGroupFeed();
    loadGroupMembers();
  } catch(e) {
    showToast('Error al cargar grupo', 'error');
  }
}

/* ====== LOAD FEED ====== */
async function loadGroupFeed() {
    const container = document.getElementById('posts-container');
    try {
        const { data } = await apiFetch(`academic/groups/${GROUP_ID}/posts?limit=20`);
        const posts = data || [];
        if (!posts.length) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)">📭 No hay publicaciones aún. ¡Sé el primero!</div>';
            return;
        }
        container.innerHTML = posts.map(p => `
            <div class="post-card">
                <div class="post-header">
                    <div class="post-avatar">${escHtml((p.first_name || 'U').charAt(0).toUpperCase())}</div>
                    <div>
                        <div class="post-author">${escHtml(p.first_name + ' ' + p.last_name)}</div>
                        <div class="post-date">${timeAgo(p.created_at)}</div>
                    </div>
                </div>
                <div class="post-content">${escHtml(p.content)}</div>
            </div>`).join('');
    } catch {
        container.innerHTML = '<p style="text-align:center;padding:40px;color:var(--text-muted)">Error al cargar publicaciones</p>';
    }
}

/* ====== LOAD MEMBERS ====== */
async function loadGroupMembers() {
    const container = document.getElementById('tab-members');
    try {
        const { data } = await apiFetch(`academic/groups/${GROUP_ID}/members`);
        const members = data || [];
        if (!members.length) {
            container.innerHTML = '<p style="padding:20px;color:var(--text-muted)">No hay miembros aún</p>';
            return;
        }
        container.innerHTML = members.map(m => `
            <div class="member-chip">
                <div class="avatar">${escHtml((m.first_name || '?').charAt(0).toUpperCase())}</div>
                <div>
                    <div style="font-weight:600;font-size:13px">${escHtml(m.first_name + ' ' + m.last_name)}</div>
                    <div style="font-size:11px;color:var(--text-muted)">${m.role || 'miembro'}</div>
                </div>
            </div>`).join('');
    } catch {
        container.innerHTML = '<p style="padding:20px;color:var(--text-muted)">Error al cargar miembros</p>';
    }
}

/* ====== JOIN FROM DETAIL PAGE ====== */
async function joinGroupFromDetail(id) {
  try {
    await apiFetch(`academic/groups/${id}/join`, { method: 'POST' });
    showToast('¡Te uniste al grupo! 🎉', 'success');
    location.reload();
  } catch(e) {
    showToast(e.message || 'Error al unirse', 'error');
  }
}

/* ====== TAB SWITCH ====== */
function switchDetailTab(tab) {
  detailTab = tab;
  document.querySelectorAll('.mod-tab').forEach((t,i) => {
    const tabs = ['feed','members'];
    t.classList.toggle('active', tabs[i] === tab);
  });
  document.getElementById('tab-feed').classList.toggle('hidden', tab !== 'feed');
  document.getElementById('tab-members').classList.toggle('hidden', tab !== 'members');
  document.getElementById('group-empty').classList.toggle('hidden', tab !== 'feed');
}

// Publicar en el grupo
document.getElementById('submit-post-btn')?.addEventListener('click', async () => {
    const content = document.getElementById('post-content').value.trim();
    if (!content) {
        showToast('Escribe algo antes de publicar', 'warning');
        return;
    }
    try {
        await apiFetch(`academic/groups/${GROUP_ID}/posts`, {
            method: 'POST',
            body: JSON.stringify({ content: content })
        });
        showToast('Publicación creada', 'success');
        document.getElementById('post-content').value = '';
        loadGroupFeed(); // recargar el feed
    } catch (e) {
        showToast(e.message || 'Error al publicar', 'error');
    }
});

// Init
loadGroupDetail();
</script>
</body>
</html>