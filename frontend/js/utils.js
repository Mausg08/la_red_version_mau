/**
 * UniLink — utils.js
 * BASE_PATH apunta a /RedSocial_BUAP
 */

const UL_BASE = '/RedSocial_BUAP';

/* ── API FETCH ───────────────────────────────────────────────── */
async function apiFetch(endpoint, options = {}) {
  const token = typeof UL_TOKEN !== 'undefined' ? UL_TOKEN : (sessionStorage.getItem('ul_token') || '');

  const defaults = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      'X-Requested-With': 'XMLHttpRequest'
    }
  };

  const config = {
    ...defaults,
    ...options,
    headers: { ...defaults.headers, ...(options.headers || {}) }
  };

  if (config.body instanceof FormData) {
    delete config.headers['Content-Type'];
  }

  const [pathOnly, queryString] = endpoint.split('?');
  const service = pathOnly.split('/')[0];
  const querySuffix = queryString ? `&${queryString}` : '';
  const url = `${UL_BASE}/backend/api-gateway/index.php?service=${encodeURIComponent(service)}&path=${encodeURIComponent(pathOnly)}${querySuffix}`;

  const res  = await fetch(url, config);
  const data = await res.json();

  if (!res.ok) {
    if (res.status === 401) {
      window.location.href = `${UL_BASE}/index.php?expired=1`;
      return;
    }
    throw new Error(data.message || `HTTP ${res.status}`);
  }

  if (data && data.data && typeof data.data === 'object' && !Array.isArray(data.data)) {
    return { ...data, ...data.data };
  }

  return data;
}

/* ── TOAST ───────────────────────────────────────────────────── */
function showToast(message, type = 'info', duration = 4000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  const icons = { success:'✓', error:'✕', info:'ℹ' };
  toast.innerHTML = `<span>${icons[type]||'ℹ'}</span><span>${escHtml(message)}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slideInRight 300ms ease reverse forwards';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

/* ── HELPERS ─────────────────────────────────────────────────── */
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}

function timeAgo(dateStr) {
  const date = new Date(dateStr);
  const diff = Math.floor((new Date() - date) / 1000);
  if (diff < 60)    return 'ahora';
  if (diff < 3600)  return `hace ${Math.floor(diff/60)}min`;
  if (diff < 86400) return `hace ${Math.floor(diff/3600)}h`;
  if (diff < 604800)return `hace ${Math.floor(diff/86400)}d`;
  return date.toLocaleDateString('es-MX', { day:'numeric', month:'short' });
}

function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.type      = input.type === 'password' ? 'text' : 'password';
  btn.textContent = input.type === 'password' ? '👁' : '🙈';
}

function formatMXN(amount) {
  return new Intl.NumberFormat('es-MX', { style:'currency', currency:'MXN' }).format(amount);
}

function debounce(fn, delay) {
  let timer;
  return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

function formToJSON(form) {
  const data = {};
  new FormData(form).forEach((value, key) => {
    if (data[key] !== undefined) {
      if (!Array.isArray(data[key])) data[key] = [data[key]];
      data[key].push(value);
    } else {
      data[key] = value;
    }
  });
  return data;
}

function logout() {
  if (confirm('¿Cerrar sesión?')) {
    window.location.href = `${UL_BASE}/backend/api-gateway/auth.php?action=logout`;
  }
}

function triggerPanic() {
  if (!confirm('⚠️ ¿Confirmas que necesitas asistencia de seguridad del campus?')) return;
  apiFetch('moderation/panic', {
    method: 'POST',
    body: JSON.stringify({ timestamp: new Date().toISOString() })
  });
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      apiFetch('moderation/panic', {
        method: 'POST',
        body: JSON.stringify({ lat: pos.coords.latitude, lng: pos.coords.longitude })
      });
    });
  }
  alert('🚨 Alerta enviada a seguridad del campus.');
}

const Storage = {
  get: (key, fallback = null) => {
    try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
  },
  set: (key, value) => {
    try { localStorage.setItem(key, JSON.stringify(value)); } catch {}
  },
  remove: (key) => localStorage.removeItem(key)
};
