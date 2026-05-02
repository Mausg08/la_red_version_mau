/**
 * UniLink — feed.js
 * Feed loading, post creation, interactions
 */

const API = `${UL_BASE}/backend/api-gateway/index.php`;
let currentPage = 1;
let currentFilter = 'all';
let isLoading = false;
let hasMore = true;
const selectedTags = new Set();

/* ============ INIT ============ */
document.addEventListener('DOMContentLoaded', () => {
  loadFeed();
  loadWidgets();
  setupInfiniteScroll();
  setupFilterButtons();
  setupPostForm();
  setupSearch();
  setupTagButtons();
  setupMediaPreview();
  loadNotifications();
});

/* ============ FEED LOADING ============ */
async function loadFeed(reset = false) {
  if (isLoading || (!hasMore && !reset)) return;

  if (reset) {
    currentPage = 1;
    hasMore = true;
    document.getElementById('feed-container').innerHTML = `
      <div class="skeleton-post"></div>
      <div class="skeleton-post"></div>
      <div class="skeleton-post"></div>
    `;
    document.getElementById('feed-end').classList.add('hidden');
  }

  isLoading = true;
  document.getElementById('feed-loader').classList.remove('hidden');

  try {
    const res = await apiFetch(`feed/posts?page=${currentPage}&filter=${currentFilter}&limit=10`);
    const posts = res.posts || res.data || [];
    const meta = res.meta || {};

    if (reset || currentPage === 1) {
      document.getElementById('feed-container').innerHTML = '';
    }

    if (posts.length === 0 && currentPage === 1) {
      document.getElementById('feed-container').innerHTML = `
        <div class="card card-body" style="text-align:center;padding:40px;color:var(--text-muted)">
          <p style="font-size:32px;margin-bottom:12px">👋</p>
          <p style="font-size:16px;font-weight:600">No hay publicaciones aún</p>
          <p style="font-size:14px;margin-top:6px">¡Sé el primero en compartir algo con tu comunidad!</p>
        </div>`;
    } else {
      posts.forEach(post => {
        document.getElementById('feed-container').appendChild(renderPost(post));
      });
    }

    hasMore = Boolean(meta.has_more);
    currentPage++;

    if (!hasMore) {
      document.getElementById('feed-end').classList.remove('hidden');
    }
  } catch (err) {
    showToast('Error al cargar el feed. Intenta de nuevo.', 'error');
  } finally {
    isLoading = false;
    document.getElementById('feed-loader').classList.add('hidden');
  }
}

/* ============ RENDER POST ============ */
function renderPost(post) {
  const el = document.createElement('div');
  el.className = `post-card ${post.type ? 'post-type-' + post.type : ''}`;
  el.dataset.postId = post.post_id;

  const initials = post.author_name.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase();
  const tagsHtml = post.tags?.length
    ? `<div class="post-tags">${post.tags.map(t => `<span class="post-tag">#${escHtml(t)}</span>`).join('')}</div>`
    : '';

  const mediaHtml = buildMediaHtml(post.media);
  const contentTruncated = post.content.length > 300;
  const contentPreview = contentTruncated
    ? `<div class="post-content truncated" id="content-${post.post_id}">${escHtml(post.content)}</div>
       <button class="read-more-btn" onclick="expandPost(${post.post_id})">Ver más</button>`
    : `<div class="post-content">${escHtml(post.content)}</div>`;

  const isMod = UL_USER.role === 'admin' || UL_USER.role === 'moderator';
  const isOwner = post.author_id === UL_USER.id;

  el.innerHTML = `
    <div class="post-header">
      <div class="avatar" onclick="window.location.href='profile.php?id=${post.author_id}'"
           title="${escHtml(post.author_name)}"
           style="cursor:pointer">${initials}</div>
      <div class="post-meta">
        <a class="post-author" href="profile.php?id=${post.author_id}">${escHtml(post.author_name)}</a>
        <div class="post-info">
          <span>${escHtml(post.faculty_name || '')}</span>
          <span class="dot">·</span>
          <span>${timeAgo(post.created_at)}</span>
          ${post.audience !== 'public' ? `<span class="dot">·</span><span>${audienceLabel(post.audience)}</span>` : ''}
        </div>
      </div>
      <div class="post-options-btn" onclick="togglePostMenu(event, ${post.post_id}, ${isOwner}, ${isMod})">⋮</div>
    </div>

    ${contentPreview}
    ${tagsHtml}
    ${mediaHtml}

    <div class="post-actions">
      <button class="post-action ${post.user_liked ? 'liked' : ''}"
              id="like-btn-${post.post_id}"
              onclick="toggleLike(${post.post_id})">
        ${post.user_liked ? '❤️' : '🤍'} <span class="like-count">${post.likes_count || 0}</span>
      </button>
      <button class="post-action" onclick="toggleComments(${post.post_id})">
        💬 <span>${post.comments_count || 0}</span>
      </button>
      <button class="post-action" onclick="sharePost(${post.post_id})">
        ↗️ Compartir
      </button>
      <button class="post-action" onclick="openReportModal(${post.post_id})">
        🚩
      </button>
    </div>

    <div class="post-comments hidden" id="comments-${post.post_id}">
      <div class="comments-loading">Cargando comentarios...</div>
    </div>
  `;

  return el;
}

function buildMediaHtml(media) {
  if (!media?.length) return '';
  if (media.length === 1) {
    return `<img class="post-media" src="${escHtml(media[0].url)}" alt="Imagen de la publicación" loading="lazy">`;
  }
  const imgs = media.slice(0, 4).map(m =>
    `<img src="${escHtml(m.url)}" alt="" loading="lazy">`
  ).join('');
  return `<div class="post-media-grid">${imgs}</div>`;
}

function audienceLabel(a) {
  const map = { faculty: '🏛 Facultad', career: '📚 Carrera', group: '👥 Grupo' };
  return map[a] || a;
}

/* ============ POST INTERACTIONS ============ */
async function toggleLike(postId) {
  const btn = document.getElementById(`like-btn-${postId}`);
  const liked = btn.classList.contains('liked');
  const countEl = btn.querySelector('.like-count');
  const count = parseInt(countEl.textContent);

  // Optimistic update
  btn.classList.toggle('liked');
  btn.innerHTML = `${liked ? '🤍' : '❤️'} <span class="like-count">${liked ? count - 1 : count + 1}</span>`;

  try {
    await apiFetch(`feed/posts/${postId}/like`, { method: liked ? 'DELETE' : 'POST' });
  } catch {
    // Revert on failure
    btn.classList.toggle('liked');
    btn.innerHTML = `${liked ? '❤️' : '🤍'} <span class="like-count">${count}</span>`;
    showToast('Error al procesar tu reacción', 'error');
  }
}

async function toggleComments(postId) {
  const container = document.getElementById(`comments-${postId}`);
  if (container.classList.contains('hidden')) {
    container.classList.remove('hidden');
    await loadComments(postId);
  } else {
    container.classList.add('hidden');
  }
}

async function loadComments(postId) {
  const container = document.getElementById(`comments-${postId}`);
  try {
    const { comments } = await apiFetch(`feed/posts/${postId}/comments`);
    const commentsHtml = comments.length
      ? comments.map(c => `
        <div class="comment-item">
          <div class="avatar avatar-sm">${c.author_name[0].toUpperCase()}</div>
          <div class="comment-main">
            <div class="comment-bubble">
              <div class="comment-author">${escHtml(c.author_name)}</div>
              <div class="comment-text">${escHtml(c.content)}</div>
            </div>
            <div class="comment-time">${timeAgo(c.created_at)}</div>
          </div>
        </div>
      `).join('')
      : '<p class="comments-empty">Todavía no hay comentarios.</p>';

    container.innerHTML = `<div class="comments-list">${commentsHtml}</div>` + `
      <div class="comment-input-row">
        <div class="avatar avatar-sm">${UL_USER.name[0].toUpperCase()}</div>
        <input type="text" class="comment-input" id="comment-input-${postId}"
               placeholder="Escribe un comentario..." maxlength="500"
               onkeydown="if(event.key==='Enter')submitComment(${postId})">
        <button class="btn-primary" style="padding:7px 14px;font-size:13px"
                onclick="submitComment(${postId})">Enviar</button>
      </div>`;
  } catch {
    container.innerHTML = '<p style="padding:12px;font-size:13px;color:var(--text-muted)">Error al cargar comentarios</p>';
  }
}

async function submitComment(postId) {
  const input = document.getElementById(`comment-input-${postId}`);
  const content = input.value.trim();
  if (!content) return;

  input.disabled = true;
  try {
    await apiFetch(`feed/posts/${postId}/comments`, {
      method: 'POST',
      body: JSON.stringify({ content })
    });
    input.value = '';
    await loadComments(postId);
    // Increment comment count
    const btn = document.querySelector(`[onclick="toggleComments(${postId})"]`);
    if (btn) {
      const span = btn.querySelector('span');
      span.textContent = parseInt(span.textContent) + 1;
    }
  } catch {
    showToast('Error al publicar comentario', 'error');
  } finally {
    input.disabled = false;
    input.focus();
  }
}

function sharePost(postId) {
  const url = `${window.location.origin}/frontend/pages/post.php?id=${postId}`;
  if (navigator.share) {
    navigator.share({ title: 'UniLink', url });
  } else {
    navigator.clipboard.writeText(url).then(() => showToast('Enlace copiado 📋', 'info'));
  }
}

function expandPost(postId) {
  const el = document.getElementById(`content-${postId}`);
  el.classList.remove('truncated');
  el.nextElementSibling.remove();
}

/* ============ POST CREATION ============ */
function openPostModal(type = 'general') {
  document.getElementById('postModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';

  const extras = document.getElementById('post-extra-fields');
  extras.innerHTML = '';

  if (type === 'event') {
    extras.innerHTML = `
      <div class="form-row">
        <div class="form-group">
          <label>Fecha del evento</label>
          <input type="datetime-local" name="event_date" required>
        </div>
        <div class="form-group">
          <label>Lugar</label>
          <input type="text" name="event_location" placeholder="Ej: Auditorio B">
        </div>
      </div>`;
    document.getElementById('post-audience').value = 'public';
  } else if (type === 'poll') {
    extras.innerHTML = `
      <div class="form-group">
        <label>Pregunta de la encuesta</label>
        <input type="text" name="poll_question" placeholder="¿Cuál es tu pregunta?" required>
      </div>
      <div id="poll-options">
        <div class="form-group"><input type="text" name="poll_option[]" placeholder="Opción 1" required></div>
        <div class="form-group"><input type="text" name="poll_option[]" placeholder="Opción 2" required></div>
      </div>
      <button type="button" class="btn-ghost" onclick="addPollOption()">+ Agregar opción</button>`;
  } else if (type === 'lost') {
    extras.innerHTML = `
      <div class="form-row">
        <div class="form-group">
          <label>¿Perdiste o encontraste?</label>
          <select name="lost_type">
            <option value="lost">Perdí un objeto</option>
            <option value="found">Encontré un objeto</option>
          </select>
        </div>
        <div class="form-group">
          <label>Lugar</label>
          <input type="text" name="lost_location" placeholder="Ej: Biblioteca central">
        </div>
      </div>`;
  }
}

function closePostModal() {
  document.getElementById('postModal').classList.add('hidden');
  document.body.style.overflow = '';
  document.getElementById('post-form').reset();
  document.getElementById('media-preview').innerHTML = '';
  document.getElementById('char-count').textContent = '0';
  selectedTags.clear();
  document.querySelectorAll('.tag-btn.selected').forEach(b => b.classList.remove('selected'));
}

function setupPostForm() {
  document.getElementById('openPostModal').addEventListener('click', () => openPostModal());

  // Character counter
  document.getElementById('post-content').addEventListener('input', function () {
    document.getElementById('char-count').textContent = this.value.length;
    if (this.value.length > 1800) {
      document.getElementById('char-count').style.color = 'var(--uni-red)';
    } else {
      document.getElementById('char-count').style.color = '';
    }
  });

  // Form submit
  document.getElementById('post-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('submit-post');
    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Publicando...';

    const formData = new FormData(e.target);
    formData.set('tags', JSON.stringify([...selectedTags]));

    try {
      const res = await fetch(`${API}?service=feed&path=${encodeURIComponent('feed/posts')}`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${UL_TOKEN}` },
        body: formData
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);

      closePostModal();
      showToast('¡Publicación creada! 🎉', 'success');
      loadFeed(true); // Reload feed
    } catch (err) {
      showToast(err.message || 'Error al publicar', 'error');
    } finally {
      btn.disabled = false;
      btn.querySelector('.btn-text').textContent = 'Publicar';
    }
  });
}

function setupTagButtons() {
  document.querySelectorAll('.tag-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tag = btn.dataset.tag;
      if (selectedTags.has(tag)) {
        selectedTags.delete(tag);
        btn.classList.remove('selected');
      } else if (selectedTags.size < 3) {
        selectedTags.add(tag);
        btn.classList.add('selected');
      } else {
        showToast('Máximo 3 etiquetas', 'info');
      }
    });
  });
}

function setupMediaPreview() {
  document.getElementById('post-media')?.addEventListener('change', function () {
    const preview = document.getElementById('media-preview');
    preview.innerHTML = '';
    [...this.files].slice(0, 4).forEach(file => {
      if (!file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = e => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.className = 'media-preview-item';
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  });
}

/* ============ FILTERS ============ */
function setupFilterButtons() {
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter;
      loadFeed(true);
    });
  });
}

/* ============ INFINITE SCROLL ============ */
function setupInfiniteScroll() {
  const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && hasMore && !isLoading) {
      loadFeed();
    }
  }, { threshold: 0.5 });

  const loader = document.getElementById('feed-loader');
  if (loader) observer.observe(loader);
}

/* ============ WIDGETS ============ */
async function loadWidgets() {
  await Promise.all([loadMyGroups(), loadEvents(), loadMarketplaceQuick()]);
}

async function loadMyGroups() {
  try {
    const { groups } = await apiFetch('academic/my-groups?limit=5');
    const container = document.getElementById('my-groups-list');
    if (!groups.length) {
      container.innerHTML = `<p style="padding:16px;font-size:13px;color:var(--text-muted);text-align:center">Sin grupos activos</p>`;
      return;
    }
    container.innerHTML = groups.map(g => `
      <div class="group-item" onclick="window.location.href='groups.php?id=${g.group_id}'">
        <div class="group-icon">${g.icon || '📚'}</div>
        <div class="group-info">
          <div class="group-name">${escHtml(g.name)}</div>
          <div class="group-count">${g.member_count} miembros</div>
        </div>
        ${g.unread ? `<div class="group-unread">${g.unread}</div>` : ''}
      </div>`).join('');
  } catch { /* silent */ }
}

async function loadEvents() {
  try {
    const { events } = await apiFetch('academic/events?limit=4&upcoming=1');
    const container = document.getElementById('events-list');
    if (!events.length) {
      container.innerHTML = `<p style="padding:16px;font-size:13px;color:var(--text-muted);text-align:center">Sin eventos próximos</p>`;
      return;
    }
    container.innerHTML = events.map(e => {
      const d = new Date(e.event_date);
      return `
        <div class="event-item" onclick="window.location.href='calendar.php?event=${e.event_id}'">
          <div class="event-date-box">
            <div class="event-day">${d.getDate()}</div>
            <div class="event-month">${d.toLocaleString('es', {month:'short'}).toUpperCase()}</div>
          </div>
          <div class="event-info">
            <div class="event-name">${escHtml(e.title)}</div>
            <div class="event-loc">📍 ${escHtml(e.location || 'Campus')}</div>
          </div>
        </div>`;
    }).join('');
  } catch { /* silent */ }
}

async function loadMarketplaceQuick() {
  try {
    const { listings } = await apiFetch('marketplace/listings?limit=4&sort=recent');
    const container = document.getElementById('marketplace-quick');
    if (!listings.length) {
      container.innerHTML = `<p style="padding:16px;font-size:13px;color:var(--text-muted);text-align:center">Sin anuncios recientes</p>`;
      return;
    }
    container.innerHTML = listings.map(l => `
      <div class="mkt-item" onclick="window.location.href='marketplace.php?id=${l.listing_id}'">
        <div class="mkt-thumb" style="background:var(--gray-100)">
          ${l.thumbnail ? `<img src="${escHtml(l.thumbnail)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius-md)">` : '📦'}
        </div>
        <div>
          <div class="mkt-name">${escHtml(l.title)}</div>
          <div class="mkt-price">$${parseFloat(l.price).toFixed(2)}</div>
        </div>
      </div>`).join('');
  } catch { /* silent */ }
}

/* ============ REPORT MODAL ============ */
function openReportModal(postId) {
  document.getElementById('report-post-id').value = postId;
  document.getElementById('reportModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

document.getElementById('report-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  if (!fd.get('reason')) { showToast('Selecciona un motivo', 'info'); return; }
  try {
    await apiFetch(`moderation/reports`, {
      method: 'POST',
      body: JSON.stringify({
        post_id: fd.get('post_id'),
        reason: fd.get('reason'),
        details: fd.get('details')
      })
    });
    closeModal('reportModal');
    showToast('Reporte enviado. Gracias por mantener UniLink seguro 🛡', 'success');
  } catch { showToast('Error al enviar reporte', 'error'); }
});

/* ============ POST OPTIONS MENU ============ */
function togglePostMenu(event, postId, isOwner, isMod) {
  event.stopPropagation();
  const existing = document.querySelector('.post-options-menu');
  if (existing) existing.remove();

  const btn = event.currentTarget;
  const menu = document.createElement('div');
  menu.className = 'post-options-menu';
  menu.style.cssText = `position:absolute;right:0;top:100%;background:var(--white);border:1px solid var(--border);border-radius:var(--radius-md);box-shadow:var(--shadow-lg);min-width:160px;z-index:200;overflow:hidden`;

  const items = [];
  if (isOwner) {
    items.push({ label: '✏️ Editar', action: () => editPost(postId) });
    items.push({ label: '🗑 Eliminar', action: () => deletePost(postId), danger: true });
  }
  items.push({ label: '🚩 Reportar', action: () => openReportModal(postId) });
  if (isMod) {
    items.push({ label: '🛡 Moderar', action: () => moderatePost(postId), danger: true });
  }

  items.forEach(item => {
    const el = document.createElement('button');
    el.style.cssText = `display:flex;align-items:center;gap:8px;width:100%;padding:10px 16px;font-size:14px;background:none;border:none;cursor:pointer;text-align:left;color:${item.danger ? 'var(--uni-red)' : 'var(--text-primary)'};transition:background 150ms`;
    el.onmouseover = () => el.style.background = 'var(--gray-50)';
    el.onmouseout  = () => el.style.background = 'none';
    el.textContent = item.label;
    el.onclick = () => { menu.remove(); item.action(); };
    menu.appendChild(el);
  });

  btn.style.position = 'relative';
  btn.appendChild(menu);
  document.addEventListener('click', () => menu.remove(), { once: true });
}

async function deletePost(postId) {
  if (!confirm('¿Eliminar esta publicación?')) return;
  try {
    await apiFetch(`feed/posts/${postId}`, { method: 'DELETE' });
    document.querySelector(`[data-post-id="${postId}"]`)?.remove();
    showToast('Publicación eliminada', 'success');
  } catch { showToast('Error al eliminar', 'error'); }
}

/* ============ PANIC BUTTON ============ */
function triggerPanic() {
  if (!confirm('⚠️ ¿Confirmas que necesitas asistencia de seguridad del campus? Esta acción alertará al equipo de seguridad.')) return;
  apiFetch('moderation/panic', {
    method: 'POST',
    body: JSON.stringify({
      location: navigator.geolocation ? 'solicitando...' : 'desconocida',
      timestamp: new Date().toISOString()
    })
  });
  // Request geolocation
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      apiFetch('moderation/panic', {
        method: 'POST',
        body: JSON.stringify({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          timestamp: new Date().toISOString()
        })
      });
    });
  }
  alert('🚨 Alerta enviada a seguridad del campus. Permanece en un lugar seguro.');
}

/* ============ MOBILE SIDEBAR ============ */
document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
  document.getElementById('sidebar').classList.add('open');
});
document.getElementById('sidebarClose')?.addEventListener('click', () => {
  document.getElementById('sidebar').classList.remove('open');
});

/* ============ GLOBAL SEARCH ============ */
function setupSearch() {
  const input = document.getElementById('globalSearch');
  const results = document.getElementById('searchResults');
  let searchTimeout;

  input.addEventListener('focus', () => {
    if (input.value.length > 2) results.classList.add('show');
  });
  input.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    if (input.value.length < 2) { results.classList.remove('show'); return; }
    searchTimeout = setTimeout(() => doSearch(input.value), 400);
  });
  document.addEventListener('click', (e) => {
    if (!input.contains(e.target) && !results.contains(e.target)) {
      results.classList.remove('show');
    }
  });
}

async function doSearch(q) {
  const results = document.getElementById('searchResults');
  results.innerHTML = '<div style="padding:16px;font-size:13px;color:var(--text-muted)">Buscando...</div>';
  results.classList.add('show');

  try {
    const data = await apiFetch(`users/search?q=${encodeURIComponent(q)}&limit=8`);
    if (!data.results.length) {
      results.innerHTML = '<div style="padding:16px;font-size:13px;color:var(--text-muted);text-align:center">Sin resultados</div>';
      return;
    }
    results.innerHTML = data.results.map(r => `
      <div class="search-result-item" onclick="window.location.href='${r.type === 'user' ? 'profile' : 'groups'}.php?id=${r.id}'">
        <div class="avatar avatar-sm">${r.name[0].toUpperCase()}</div>
        <div>
          <div style="font-size:14px;font-weight:600">${escHtml(r.name)}</div>
          <div class="search-result-meta">${escHtml(r.subtitle || '')}</div>
        </div>
        <span class="badge ${r.type === 'user' ? 'badge-blue' : 'badge-green'}" style="margin-left:auto">
          ${r.type === 'user' ? 'Estudiante' : 'Grupo'}
        </span>
      </div>`).join('');
  } catch {
    results.innerHTML = '<div style="padding:16px;font-size:13px;color:var(--uni-red)">Error en búsqueda</div>';
  }
}

/* ============ HELPERS ============ */
function addPollOption() {
  const container = document.getElementById('poll-options');
  const count = container.children.length + 1;
  if (count > 6) { showToast('Máximo 6 opciones', 'info'); return; }
  const div = document.createElement('div');
  div.className = 'form-group';
  div.innerHTML = `<input type="text" name="poll_option[]" placeholder="Opción ${count}">`;
  container.appendChild(div);
}

function closeModal(id) {
  document.getElementById(id).classList.add('hidden');
  document.body.style.overflow = '';
}

function logout() {
  if (confirm('¿Cerrar sesión?')) {
    window.location.href = '/backend/api-gateway/auth.php?action=logout';
  }
}
