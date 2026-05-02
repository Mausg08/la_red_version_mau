/**
 * UniLink — notifications.js
 * Notification bell, panel, mark-as-read
 */

document.addEventListener('DOMContentLoaded', () => {
  loadNotifications();
  setupNotifPanel();
});

async function loadNotifications() {
  try {
    const { notifications, unread_count } = await apiFetch('users/notifications?limit=15');

    // Dot indicator
    const dot = document.getElementById('notifDot');
    if (dot) dot.classList.toggle('show', unread_count > 0);

    // Render list
    const list = document.getElementById('notif-list');
    if (!list) return;

    if (!notifications.length) {
      list.innerHTML = '<p style="padding:20px;text-align:center;font-size:14px;color:var(--text-muted)">Sin notificaciones</p>';
      return;
    }

    list.innerHTML = notifications.map(n => `
      <div class="notif-item ${n.is_read ? '' : 'unread'}" 
           onclick="handleNotifClick(${n.notif_id}, '${escHtml(n.link || '')}')">
        <div class="avatar avatar-sm" style="flex-shrink:0">
          ${notifIcon(n.type)}
        </div>
        <div>
          <div class="notif-text">${escHtml(n.message)}</div>
          <div class="notif-time">${timeAgo(n.created_at)}</div>
        </div>
      </div>`
    ).join('');
  } catch { /* silent */ }
}

function notifIcon(type) {
  const icons = {
    like:'❤️', comment:'💬', follow:'👤', mention:'@', 
    group_invite:'👥', marketplace:'🛒', moderation:'🛡', 
    system:'🔔', event:'📅'
  };
  return icons[type] || '🔔';
}

async function handleNotifClick(id, link) {
  // Mark as read
  apiFetch(`users/notifications/${id}/read`, { method: 'PATCH' }).catch(() => {});
  document.getElementById('notifPanel')?.classList.remove('show');
  if (link) window.location.href = link;
}

async function markAllRead() {
  await apiFetch('users/notifications/read-all', { method: 'PATCH' });
  document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  document.getElementById('notifDot')?.classList.remove('show');
  showToast('Notificaciones marcadas como leídas', 'success');
}

function setupNotifPanel() {
  const btn   = document.getElementById('notifBtn');
  const panel = document.getElementById('notifPanel');
  if (!btn || !panel) return;

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    panel.classList.toggle('show');
    if (panel.classList.contains('show')) loadNotifications();
  });

  document.addEventListener('click', () => panel.classList.remove('show'));
  panel.addEventListener('click', e => e.stopPropagation());
}
