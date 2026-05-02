/**
 * UniLink — moderation.js
 * Moderation panel: reports, flagged, panic, users
 */

let activeTab = 'reports';

document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadReports();
  // Refresh stats every 30s
  setInterval(loadStats, 30000);
});

/* ====== TABS ====== */
function switchTab(tab) {
  activeTab = tab;
  document.querySelectorAll('.mod-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  document.querySelectorAll('.mod-panel').forEach(p => {
    p.classList.toggle('active', p.id === `tab-${tab}`);
    p.classList.toggle('hidden', p.id !== `tab-${tab}`);
  });

  const loaders = {
    reports: loadReports,
    flagged: loadFlagged,
    panic:   loadPanicAlerts,
    users:   loadUsers,
    log:     loadModLog,
  };
  loaders[tab]?.();
}

/* ====== STATS ====== */
async function loadStats() {
  try {
    const { stats } = await apiFetch('moderation/stats');
    document.getElementById('stat-pending').textContent = stats.pending_reports ?? 0;
    document.getElementById('stat-today').textContent   = stats.resolved_today  ?? 0;
    document.getElementById('stat-panic').textContent   = stats.active_panic    ?? 0;
    document.getElementById('tab-badge-reports').textContent = stats.pending_reports ?? 0;

    if (stats.active_panic > 0) {
      document.getElementById('panic-live-banner')?.style.setProperty('display','flex');
    }
  } catch { /* silent */ }
}

/* ====== REPORTS ====== */
async function loadReports() {
  const status = document.getElementById('rep-filter-status')?.value || 'pending';
  const reason = document.getElementById('rep-filter-reason')?.value || '';
  const container = document.getElementById('reports-list');
  container.innerHTML = '<div class="loading-placeholder">Cargando reportes...</div>';

  try {
    const { reports } = await apiFetch(`moderation/reports?status=${status}&reason=${reason}&limit=30`);
    if (!reports.length) {
      container.innerHTML = '<div class="empty-mod">✅ Sin reportes en esta categoría</div>';
      return;
    }
    container.innerHTML = reports.map(r => renderReport(r)).join('');
  } catch {
    container.innerHTML = '<div class="empty-mod" style="color:var(--uni-red)">Error al cargar reportes</div>';
  }
}

function renderReport(r) {
  const reasonLabels = {
    spam:'Spam', harassment:'Acoso', hate_speech:'Discurso de odio',
    false_info:'Info falsa', inappropriate:'Inapropiado', other:'Otro'
  };
  return `
    <div class="report-item" id="report-${r.report_id}">
      <div class="report-header">
        <div class="report-meta">
          <span class="badge badge-${r.status==='pending'?'orange':'gray'}">${r.status}</span>
          <span class="badge badge-red">${reasonLabels[r.reason] || r.reason}</span>
          <span class="text-muted" style="font-size:12px">${timeAgo(r.created_at)}</span>
        </div>
        <span class="text-muted" style="font-size:12px">Reportado por: <strong>${escHtml(r.reporter_name||'')}</strong></span>
      </div>

      ${r.post_content ? `
        <div class="report-content-preview">
          <strong>Publicación reportada:</strong>
          <p>${escHtml(r.post_content.substring(0, 200))}${r.post_content.length>200?'...':''}</p>
          <small>Por: ${escHtml(r.author_name||'?')} · ${timeAgo(r.post_created_at||r.created_at)}</small>
        </div>` : ''}

      ${r.details ? `<div class="report-details"><strong>Detalles:</strong> ${escHtml(r.details)}</div>` : ''}

      ${r.status === 'pending' ? `
        <div class="report-actions">
          <button class="btn-danger" onclick="takeAction('remove_post', ${r.report_id}, ${r.post_id})">
            🗑 Eliminar publicación
          </button>
          <button class="btn-secondary" onclick="takeAction('warn_user', ${r.report_id}, ${r.post_id})">
            ⚠️ Advertir usuario
          </button>
          <button class="btn-secondary" onclick="takeAction('suspend_user', ${r.report_id}, ${r.post_id})">
            🔒 Suspender usuario
          </button>
          <button class="btn-ghost" onclick="takeAction('dismiss', ${r.report_id}, null)">
            Desestimar
          </button>
        </div>` : ''}
    </div>`;
}

async function takeAction(action, reportId, targetId) {
  const confirmMessages = {
    remove_post:  '¿Eliminar la publicación? Esta acción es visible en el log.',
    warn_user:    '¿Enviar advertencia al usuario?',
    suspend_user: '¿Suspender la cuenta del usuario?',
    dismiss:      '¿Desestimar este reporte?',
  };
  if (!confirm(confirmMessages[action])) return;

  try {
    await apiFetch('moderation/actions', {
      method: 'POST',
      body: JSON.stringify({ action, report_id: reportId, target_id: targetId, target_type: 'post' })
    });

    // Remove from UI
    document.getElementById(`report-${reportId}`)?.remove();
    showToast('Acción registrada en el log de moderación ✓', 'success');
    loadStats();

    if (action === 'remove_post') {
      // Broadcast to all users via WebSocket
      ULSocket?.emit('moderate_post', { post_id: targetId, reason: 'moderated' });
    }
  } catch (e) {
    showToast(e.message || 'Error al ejecutar acción', 'error');
  }
}

/* ====== FLAGGED CONTENT ====== */
async function loadFlagged() {
  const container = document.getElementById('flagged-list');
  container.innerHTML = '<div class="loading-placeholder">Cargando contenido marcado...</div>';
  try {
    const { posts } = await apiFetch('moderation/flagged?limit=30');
    if (!posts.length) {
      container.innerHTML = '<div class="empty-mod">✅ Sin contenido flaggeado pendiente</div>';
      return;
    }
    container.innerHTML = posts.map(p => `
      <div class="report-item" id="flagged-${p.post_id}">
        <div class="report-header">
          <span class="badge badge-orange">Auto-flaggeado</span>
          <span class="text-muted" style="font-size:12px">${timeAgo(p.created_at)}</span>
        </div>
        <div class="report-content-preview">
          <p>${escHtml(p.content.substring(0,300))}</p>
          <small>Por: ${escHtml(p.author_name||'?')}</small>
        </div>
        <div class="report-actions">
          <button class="btn-danger" onclick="removeFlaggedPost(${p.post_id})">Eliminar</button>
          <button class="btn-secondary" onclick="approveFlaggedPost(${p.post_id})">Aprobar</button>
        </div>
      </div>`).join('');
  } catch {
    container.innerHTML = '<div class="empty-mod" style="color:var(--uni-red)">Error al cargar</div>';
  }
}

async function removeFlaggedPost(id) {
  await apiFetch(`feed/posts/${id}`, { method: 'DELETE' });
  document.getElementById(`flagged-${id}`)?.remove();
  showToast('Publicación eliminada', 'success');
}

async function approveFlaggedPost(id) {
  await apiFetch(`moderation/flagged/${id}/approve`, { method: 'PATCH' });
  document.getElementById(`flagged-${id}`)?.remove();
  showToast('Publicación aprobada y publicada', 'success');
}

/* ====== PANIC ALERTS ====== */
async function loadPanicAlerts() {
  const container = document.getElementById('panic-list');
  container.innerHTML = '<div class="loading-placeholder">Cargando alertas...</div>';
  try {
    const { alerts } = await apiFetch('moderation/panic?limit=20');
    if (!alerts.length) {
      container.innerHTML = '<div class="empty-mod">✅ Sin alertas activas</div>';
      return;
    }
    container.innerHTML = alerts.map(a => `
      <div class="report-item panic-item ${a.status==='active'?'panic-active':''}" id="panic-${a.alert_id}">
        <div class="report-header">
          <div class="report-meta">
            <span class="badge badge-${a.status==='active'?'red':'gray'}">${a.status==='active'?'🚨 ACTIVA':'Resuelta'}</span>
            <strong>${escHtml(a.user_name||'Usuario')}</strong>
            <span class="text-muted" style="font-size:12px">${timeAgo(a.created_at)}</span>
          </div>
        </div>
        ${a.latitude ? `<p style="font-size:13px;margin:8px 0">📍 Ubicación: ${a.latitude}, ${a.longitude}</p>` : ''}
        ${a.status === 'active' ? `
          <div class="report-actions">
            <button class="btn-primary" onclick="acknowledgePanic(${a.alert_id})">✓ Atendiendo</button>
            <button class="btn-secondary" onclick="resolvePanic(${a.alert_id})">Marcar como resuelto</button>
          </div>` : ''}
      </div>`).join('');
  } catch {
    container.innerHTML = '<div class="empty-mod" style="color:var(--uni-red)">Error al cargar alertas</div>';
  }
}

async function acknowledgePanic(id) {
  await apiFetch(`moderation/panic/${id}/acknowledge`, { method: 'PATCH' });
  showToast('Alerta atendida. El equipo de seguridad está respondiendo.', 'info');
  loadPanicAlerts();
  loadStats();
}

async function resolvePanic(id) {
  await apiFetch(`moderation/panic/${id}/resolve`, { method: 'PATCH' });
  document.getElementById(`panic-${id}`)?.remove();
  showToast('Alerta marcada como resuelta', 'success');
  loadStats();
}

/* ====== USERS (admin) ====== */
async function loadUsers() {
  const role = document.getElementById('user-role-filter')?.value || '';
  const q    = document.getElementById('user-search')?.value || '';
  const container = document.getElementById('users-table-wrap');
  container.innerHTML = '<div class="loading-placeholder">Cargando usuarios...</div>';

  try {
    const { users, meta } = await apiFetch(`users/admin?role=${role}&q=${encodeURIComponent(q)}&limit=50`);
    container.innerHTML = `
      <table class="users-table">
        <thead>
          <tr>
            <th>Usuario</th><th>Email</th><th>Facultad</th>
            <th>Rol</th><th>Estado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          ${users.map(u => `
            <tr id="user-row-${u.user_id}">
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="avatar avatar-sm">${u.first_name[0].toUpperCase()}</div>
                  ${escHtml(u.first_name+' '+u.last_name)}
                </div>
              </td>
              <td>${escHtml(u.email)}</td>
              <td>${escHtml(u.faculty_name||'—')}</td>
              <td>
                <select onchange="changeRole(${u.user_id}, this.value)" style="font-size:12px;padding:4px 8px;border:1px solid var(--border);border-radius:4px">
                  ${['student','professor','moderator','staff','admin'].map(r =>
                    `<option value="${r}" ${u.role===r?'selected':''}>${r}</option>`
                  ).join('')}
                </select>
              </td>
              <td>
                <span class="badge badge-${u.status==='active'?'green':'red'}">${u.status}</span>
              </td>
              <td>
                ${u.status === 'active'
                  ? `<button class="btn-ghost" style="color:var(--uni-red);font-size:12px" onclick="suspendUser(${u.user_id})">Suspender</button>`
                  : `<button class="btn-ghost" style="font-size:12px" onclick="activateUser(${u.user_id})">Activar</button>`
                }
              </td>
            </tr>`).join('')}
        </tbody>
      </table>
      <p style="font-size:12px;color:var(--text-muted);padding:8px">${meta.total} usuarios totales</p>`;
  } catch {
    container.innerHTML = '<div class="empty-mod" style="color:var(--uni-red)">Error al cargar usuarios</div>';
  }
}

async function changeRole(userId, role) {
  try {
    await apiFetch(`users/${userId}/role`, { method:'PATCH', body: JSON.stringify({ role }) });
    showToast(`Rol actualizado a ${role}`, 'success');
  } catch (e) { showToast(e.message || 'Error', 'error'); }
}

async function suspendUser(userId) {
  if (!confirm('¿Suspender este usuario?')) return;
  await apiFetch(`users/${userId}/status`, { method:'PATCH', body: JSON.stringify({ status:'suspended' }) });
  showToast('Usuario suspendido', 'success');
  loadUsers();
}

async function activateUser(userId) {
  await apiFetch(`users/${userId}/status`, { method:'PATCH', body: JSON.stringify({ status:'active' }) });
  showToast('Usuario reactivado', 'success');
  loadUsers();
}

let userSearchTimer;
function searchUsers(val) {
  clearTimeout(userSearchTimer);
  userSearchTimer = setTimeout(loadUsers, 400);
}

/* ====== MOD LOG ====== */
async function loadModLog() {
  const container = document.getElementById('mod-log-list');
  container.innerHTML = '<div class="loading-placeholder">Cargando historial...</div>';
  try {
    const { log } = await apiFetch('moderation/log?limit=50');
    if (!log.length) {
      container.innerHTML = '<div class="empty-mod">Sin acciones registradas</div>';
      return;
    }
    const actionLabels = {
      remove_post:'Eliminó publicación', remove_comment:'Eliminó comentario',
      suspend_user:'Suspendió usuario', warn_user:'Advirtió usuario',
      dismiss_report:'Desestimó reporte', restore_post:'Restauró publicación'
    };
    container.innerHTML = log.map(l => `
      <div class="report-item">
        <div class="report-header">
          <strong>${escHtml(l.moderator_name||'Sistema')}</strong>
          <span class="badge badge-blue">${actionLabels[l.action] || l.action}</span>
          <span class="text-muted" style="font-size:12px">${timeAgo(l.created_at)}</span>
        </div>
        ${l.reason ? `<p style="font-size:13px;color:var(--text-secondary);margin-top:4px">${escHtml(l.reason)}</p>` : ''}
      </div>`).join('');
  } catch {
    container.innerHTML = '<div class="empty-mod" style="color:var(--uni-red)">Error</div>';
  }
}
