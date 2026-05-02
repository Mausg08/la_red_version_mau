/**
 * UniLink — websocket.js
 * Real-time updates via WebSockets (Socket.io)
 * Handles: post deletions, new notifications, moderation actions
 */

class UniLinkSocket {
  constructor() {
    this.socket = null;
    this.reconnectAttempts = 0;
    this.maxReconnect = 5;
    this.reconnectDelay = 3000;
    this.handlers = {};
    this.init();
  }

  init() {
    // Load Socket.io if not already loaded
    if (typeof io === 'undefined') {
      const script = document.createElement('script');
      script.src = 'https://cdn.socket.io/4.7.2/socket.io.min.js';
      script.onload = () => this.connect();
      document.head.appendChild(script);
    } else {
      this.connect();
    }
  }

  connect() {
    try {
      this.socket = io(window.location.origin, {
        auth: { token: typeof UL_TOKEN !== 'undefined' ? UL_TOKEN : '' },
        transports: ['websocket', 'polling'],
        reconnectionAttempts: this.maxReconnect,
        reconnectionDelay: this.reconnectDelay
      });

      this.socket.on('connect', () => {
        console.log('[UniLink WS] Connected:', this.socket.id);
        this.reconnectAttempts = 0;

        // Join user-specific room and faculty room
        if (typeof UL_USER !== 'undefined') {
          this.socket.emit('join_room', { 
            user_id: UL_USER.id, 
            faculty: UL_USER.faculty,
            role: UL_USER.role
          });
        }
      });

      this.socket.on('disconnect', (reason) => {
        console.log('[UniLink WS] Disconnected:', reason);
      });

      // ---- Post moderation: remove post from feed instantly ----
      this.socket.on('post_removed', ({ post_id, reason }) => {
        const postEl = document.querySelector(`[data-post-id="${post_id}"]`);
        if (postEl) {
          postEl.style.transition = 'opacity 400ms, transform 400ms';
          postEl.style.opacity = '0';
          postEl.style.transform = 'translateX(-10px)';
          setTimeout(() => postEl.remove(), 400);
          if (reason === 'moderated') {
            showToast('Una publicación fue moderada y eliminada', 'info');
          }
        }
      });

      // ---- New notification ----
      this.socket.on('notification', (notif) => {
        this.handleNotification(notif);
      });

      // ---- New post in feed (from followed users/groups) ----
      this.socket.on('new_post', (post) => {
        const container = document.getElementById('feed-container');
        if (!container) return;
        const newEl = renderPost(post);
        newEl.style.border = '2px solid var(--uni-blue-light)';
        container.prepend(newEl);
        setTimeout(() => { newEl.style.border = ''; }, 3000);
      });

      // ---- Report count update (for moderators) ----
      this.socket.on('reports_update', ({ count, faculty }) => {
        if (UL_USER.role !== 'moderator' && UL_USER.role !== 'admin') return;
        const badge = document.getElementById('badge-reports');
        if (badge) {
          badge.textContent = count > 0 ? (count > 99 ? '99+' : count) : '';
          badge.style.display = count > 0 ? 'flex' : 'none';
        }
      });

      // ---- Like update (someone else liked a post you're viewing) ----
      this.socket.on('like_update', ({ post_id, likes_count }) => {
        const btn = document.getElementById(`like-btn-${post_id}`);
        if (!btn) return;
        const countEl = btn.querySelector('.like-count');
        if (countEl && !btn.classList.contains('liked')) {
          countEl.textContent = likes_count;
        }
      });

      // ---- System announcements ----
      this.socket.on('system_alert', ({ message, level }) => {
        showToast(message, level === 'critical' ? 'error' : 'info', 8000);
      });

    } catch (err) {
      console.warn('[UniLink WS] Could not connect:', err.message);
      // Graceful degradation: app still works without WebSockets
    }
  }

  handleNotification(notif) {
    // Show dot indicator
    document.getElementById('notifDot')?.classList.add('show');

    // Prepend to notification panel if open
    const list = document.getElementById('notif-list');
    if (list) {
      const item = document.createElement('div');
      item.className = 'notif-item unread';
      item.innerHTML = `
        <div class="avatar avatar-sm">${notif.sender_name?.[0]?.toUpperCase() || '🔔'}</div>
        <div>
          <div class="notif-text">${escHtml(notif.message)}</div>
          <div class="notif-time">ahora</div>
        </div>`;
      list.prepend(item);
    }

    // Browser notification (if permission granted)
    if (Notification.permission === 'granted') {
      new Notification('UniLink', {
        body: notif.message,
        icon: '/frontend/assets/logo-192.png',
        tag: 'unilink-notif-' + notif.id
      });
    }
  }

  emit(event, data) {
    if (this.socket?.connected) {
      this.socket.emit(event, data);
    }
  }

  disconnect() {
    this.socket?.disconnect();
  }
}

// Initialize and expose globally
const ULSocket = new UniLinkSocket();

// Request notification permission on load
if ('Notification' in window && Notification.permission === 'default') {
  setTimeout(() => {
    Notification.requestPermission();
  }, 5000);
}
