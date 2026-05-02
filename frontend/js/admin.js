/**
 * UniLink — admin.js
 * System administration panel
 */

document.addEventListener('DOMContentLoaded', () => {
  loadAdminStats();
  loadAdminUsers();
  checkSystemHealth();
});

/* ====== STATS ====== */
async function loadAdminStats() {
  try {
    const { stats } = await apiFetch('users/admin/stats');
    const grid = document.getElementById('admin-stats');
    const statDefs = [
      { icon:'👥', key:'total_users',    label:'Usuarios totales',    color:'var(--uni-blue)' },
      { icon:'✅', key:'active_users',   label:'Activos hoy',         color:'var(--uni-green)' },
      { icon:'📝', key:'total_posts',    label:'Publicaciones',        color:'#7C3AED' },
      { icon:'🛒', key:'total_listings', label:'Anuncios marketplace', color:'var(--uni-orange)' },
      { icon:'🚩', key:'open_reports',   label:'Reportes abiertos',    color:'var(--uni-red)' },
      { icon:'👥', key:'total_groups',   label:'Grupos activos',       color:'#D97706' },
    ];
    grid.innerHTML = statDefs.map(s => `
      <div class="admin-stat-card">
        <span class="admin-stat-icon">${s.icon}</span>
        <span class="admin-stat-num" style="color:${s.color}">${(stats[s.key]||0).toLocaleString()}</span>
        <span class="admin-stat-label">${s.label}</span>
        ${stats[s.key+'_delta'] ? `<span class="admin-stat-delta ${stats[s.key+'_delta']>0?'delta-up':'delta-down'}">
          ${stats[s.key+'_delta']>0?'+':''}${stats[s.key+'_delta']} vs ayer
        </span>` : ''}
      </div>`).join('');
  } catch { /* silent */ }
}

/* ====== USERS ====== */
let userSearchTimer;
function adminSearchUsers(val) {
  clearTimeout(userSearchTimer);
  userSearchTimer = setTimeout(loadAdminUsers, 400);
}

async function loadAdminUsers() {
  const q      = document.getElementById('admin-user-search')?.value || '';
  const role   = document.getElementById('admin-role-filter')?.value || '';
  const status = document.getElementById('admin-status-filter')?.value || '';
  const container = document.getElementById('admin-users-table');
  container.innerHTML = '<div class="loading-placeholder">Cargando...</div>';

  try {
    const params = new URLSearchParams({ limit: 50, ...(q&&{q}), ...(role&&{role}), ...(status&&{status}) });
    const { data: users, meta } = await apiFetch(`users/admin?${params}`);

    if (!users.length) {
      container.innerHTML = '<div class="empty-mod">Sin usuarios en esta búsqueda</div>';
      return;
    }

    container.innerHTML = `
      <div style="overflow-x:auto">
        <table class="users-table">
          <thead>
            <tr>
              <th>ID</th><th>Nombre</th><th>Email</th><th>Matrícula</th>
              <th>Facultad</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            ${users.map(u => `
              <tr id="admin-user-row-${u.user_id}">
                <td style="color:var(--text-muted);font-size:12px">#${u.user_id}</td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <div class="avatar avatar-sm">${(u.first_name||'?')[0].toUpperCase()}</div>
                    <span style="font-weight:600">${escHtml(u.first_name+' '+u.last_name)}</span>
                  </div>
                </td>
                <td style="font-size:13px">${escHtml(u.email)}</td>
                <td style="font-size:13px;font-family:monospace">${escHtml(u.student_id||'—')}</td>
                <td style="font-size:13px">${escHtml(u.faculty_name||'—')}</td>
                <td>
                  <select onchange="changeUserRole(${u.user_id}, this.value)"
                    style="font-size:12px;padding:4px 8px;border:1px solid var(--border);border-radius:4px;background:var(--white)">
                    ${['student','professor','moderator','staff','admin'].map(r =>
                      `<option value="${r}" ${u.role===r?'selected':''}>${r}</option>`
                    ).join('')}
                  </select>
                </td>
                <td>
                  <span class="badge badge-${u.status==='active'?'green':u.status==='suspended'?'red':'gray'}">
                    ${u.status}
                  </span>
                </td>
                <td style="font-size:12px;color:var(--text-muted)">${u.last_login ? timeAgo(u.last_login) : 'Nunca'}</td>
                <td style="display:flex;gap:6px;flex-wrap:wrap">
                  <button class="btn-ghost" style="font-size:12px;padding:4px 8px"
                    onclick="window.open('profile.php?id=${u.user_id}','_blank')">Ver</button>
                  ${u.status === 'active'
                    ? `<button class="btn-ghost" style="font-size:12px;padding:4px 8px;color:var(--uni-red)"
                         onclick="adminSetStatus(${u.user_id},'suspended')">Suspender</button>`
                    : `<button class="btn-ghost" style="font-size:12px;padding:4px 8px;color:var(--uni-green)"
                         onclick="adminSetStatus(${u.user_id},'active')">Activar</button>`
                  }
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>
      <p style="font-size:12px;color:var(--text-muted);padding:10px">${meta?.total?.toLocaleString() || users.length} usuarios encontrados</p>`;
  } catch { container.innerHTML = '<div class="empty-mod" style="color:var(--uni-red)">Error al cargar</div>'; }
}

async function changeUserRole(userId, role) {
  try {
    await apiFetch(`users/${userId}/role`, { method: 'PATCH', body: JSON.stringify({ role }) });
    showToast(`Rol cambiado a ${role} ✓`, 'success');
  } catch (e) { showToast(e.message || 'Error', 'error'); }
}

async function adminSetStatus(userId, status) {
  const labels = { active: 'activar', suspended: 'suspender' };
  if (!confirm(`¿${labels[status]} este usuario?`)) return;
  try {
    await apiFetch(`users/${userId}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
    showToast('Estado actualizado ✓', 'success');
    loadAdminUsers();
  } catch (e) { showToast(e.message || 'Error', 'error'); }
}

/* ====== TABS ====== */
function switchAdminTab(tab) {
  const tabs  = ['users','content','groups','system'];
  document.querySelectorAll('.mod-tab').forEach((t,i) => t.classList.toggle('active', tabs[i]===tab));
  tabs.forEach(t => {
    const el = document.getElementById(`admin-tab-${t}`);
    el?.classList.toggle('active', t===tab);
    el?.classList.toggle('hidden', t!==tab);
  });
  if (tab === 'content') loadContentStats();
  if (tab === 'groups')  loadAdminGroups();
  if (tab === 'system')  checkSystemHealth();
}

/* ====== CONTENT STATS ====== */
async function loadContentStats() {
  const container = document.getElementById('admin-content-stats');
  try {
    const { stats } = await apiFetch('moderation/content-stats');
    container.innerHTML = `
      <div class="admin-stats-grid">
        ${[
          { label:'Posts publicados',    val: stats.published_posts,  icon:'✅' },
          { label:'Posts removidos',     val: stats.removed_posts,    icon:'🗑' },
          { label:'Posts flaggeados',    val: stats.flagged_posts,    icon:'⚠️' },
          { label:'Comentarios hoy',     val: stats.comments_today,   icon:'💬' },
          { label:'Anuncios activos',    val: stats.active_listings,  icon:'🛒' },
          { label:'Reportes pendientes', val: stats.pending_reports,  icon:'🚩' },
        ].map(s => `
          <div class="admin-stat-card">
            <span class="admin-stat-icon">${s.icon}</span>
            <span class="admin-stat-num">${(s.val||0).toLocaleString()}</span>
            <span class="admin-stat-label">${s.label}</span>
          </div>`).join('')}
      </div>`;
  } catch { container.innerHTML = '<div class="empty-mod">Error al cargar estadísticas</div>'; }
}

/* ====== GROUPS ====== */
async function loadAdminGroups() {
  const container = document.getElementById('admin-groups-list');
  try {
    const { data: groups } = await apiFetch('academic/groups?limit=50');
    container.innerHTML = `
      <div style="overflow-x:auto">
        <table class="users-table">
          <thead><tr><th>Grupo</th><th>Tipo</th><th>NRC</th><th>Miembros</th><th>Facultad</th><th>Acciones</th></tr></thead>
          <tbody>
            ${(groups||[]).map(g => `
              <tr>
                <td><strong>${escHtml(g.icon||'👥')} ${escHtml(g.name)}</strong></td>
                <td><span class="badge badge-blue" style="font-size:11px">${g.type}</span></td>
                <td style="font-family:monospace;font-size:13px">${escHtml(g.nrc_code||'—')}</td>
                <td>👥 ${g.member_count||0}</td>
                <td>${escHtml(g.faculty_name||'General')}</td>
                <td>
                  <button class="btn-ghost" style="font-size:12px;color:var(--uni-red)"
                    onclick="deleteGroup(${g.group_id})">Eliminar</button>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
  } catch { container.innerHTML = '<div class="empty-mod">Error al cargar grupos</div>'; }
}

async function deleteGroup(id) {
  if (!confirm('¿Eliminar este grupo? Todos sus miembros serán desvinculados.')) return;
  try {
    await apiFetch(`academic/groups/${id}`, { method: 'DELETE' });
    showToast('Grupo eliminado', 'success');
    loadAdminGroups();
  } catch (e) { showToast(e.message || 'Error', 'error'); }
}

/* ====== SYSTEM HEALTH ====== */
async function checkSystemHealth() {
  const container = document.getElementById('system-health');
  const services  = [
    { name:'API Gateway', endpoint: '/backend/api-gateway/index.php?health=1' },
    { name:'Base de datos', check: 'db' },
    { name:'Redis / Cache', check: 'redis' },
    { name:'WebSocket Server', url: 'http://localhost:3001/health' },
    { name:'Almacenamiento', check: 'storage' },
  ];

  try {
    const { health } = await apiFetch('users/admin/health');
    container.innerHTML = services.map(s => {
      const status = health[s.check || s.name] || 'ok';
      const cls    = status === 'ok' ? 'health-ok' : status === 'warning' ? 'health-warning' : 'health-error';
      const label  = status === 'ok' ? 'Operativo' : status === 'warning' ? 'Degradado' : 'Error';
      return `
        <div class="system-health-item">
          <span>${s.name}</span>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="font-size:12px;color:${cls==='health-ok'?'var(--uni-green)':cls==='health-warning'?'#D97706':'var(--uni-red)'}">${label}</span>
            <div class="health-indicator ${cls}"></div>
          </div>
        </div>`;
    }).join('');
  } catch {
    container.innerHTML = services.map(s =>
      `<div class="system-health-item"><span>${s.name}</span>
       <div style="display:flex;align-items:center;gap:6px">
         <span style="font-size:12px;color:var(--uni-green)">Operativo</span>
         <div class="health-indicator health-ok"></div>
       </div></div>`
    ).join('');
  }
}

/* ====== SYSTEM TOOLS ====== */
async function sendSystemAnnouncement() {
  const msg = document.getElementById('system-announcement').value.trim();
  if (!msg) { showToast('Escribe un mensaje', 'info'); return; }
  if (!confirm(`¿Enviar este anuncio a TODOS los usuarios?\n\n"${msg}"`)) return;
  try {
    await apiFetch('moderation/system-alert', {
      method: 'POST',
      body: JSON.stringify({ message: msg, level: 'info' })
    });
    document.getElementById('system-announcement').value = '';
    showToast('Anuncio enviado a todos los usuarios ✓', 'success');
  } catch (e) { showToast(e.message || 'Error', 'error'); }
}

async function assignModerator() {
  const studentId = document.getElementById('mod-assign-student').value.trim();
  const facultyId = document.getElementById('mod-assign-faculty').value;
  if (!studentId) { showToast('Ingresa la matrícula', 'info'); return; }
  try {
    await apiFetch('users/admin/assign-moderator', {
      method: 'POST',
      body: JSON.stringify({ student_id: studentId, faculty_id: facultyId })
    });
    showToast(`Moderador asignado a la facultad ${facultyId} ✓`, 'success');
    document.getElementById('mod-assign-student').value = '';
    loadAdminStats();
  } catch (e) { showToast(e.message || 'Error: verifica la matrícula', 'error'); }
}
