let directoryView = 'all';
let directoryTimer;

document.addEventListener('DOMContentLoaded', () => {
  loadDirectory(true);
});

function switchDirectoryView(view) {
  directoryView = view;
  document.querySelectorAll('.directory-tabs .mod-tab')
    .forEach(btn => btn.classList.toggle('active', btn.dataset.view === view));
  loadDirectory(true);
}

function debounceDirectorySearch() {
  clearTimeout(directoryTimer);
  directoryTimer = setTimeout(() => loadDirectory(true), 350);
}

async function loadDirectory(reset = false) {
  const grid = document.getElementById('directory-grid');
  const empty = document.getElementById('directory-empty');
  grid.innerHTML = Array(6).fill('<div class="skeleton-card" style="height:180px"></div>').join('');
  empty.classList.add('hidden');

  const q = document.getElementById('directory-search')?.value.trim() || '';
  const faculty = document.getElementById('directory-faculty')?.value || '';
  const params = new URLSearchParams({
    limit: 50,
    ...(q ? { q } : {}),
    ...(faculty ? { faculty_id: faculty } : {}),
  });

  const endpoint = directoryView === 'contacts'
    ? `directory/contacts?${params}`
    : `directory/users?${params}`;

  try {
    const res = await apiFetch(endpoint);
    const users = res.data || [];
    grid.innerHTML = '';

    if (!users.length) {
      empty.classList.remove('hidden');
      return;
    }

    users.forEach(user => grid.appendChild(renderDirectoryUser(user)));
  } catch (err) {
    grid.innerHTML = '';
    showToast(err.message || 'Error al cargar directorio', 'error');
  }
}

function renderDirectoryUser(user) {
  const el = document.createElement('div');
  el.className = 'person-card';
  const initials = (user.name || '?').split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase();
  const isMe = Number(user.user_id) === Number(UL_USER.id);
  const isContact = Number(user.is_contact) === 1;

  el.innerHTML = `
    <div class="person-head">
      <div class="avatar">${escHtml(initials)}</div>
      <div>
        <div class="person-name">${escHtml(user.name)}</div>
        <div class="person-meta">${escHtml(user.role || 'usuario')} ${user.student_id ? ' · ' + escHtml(user.student_id) : ''}</div>
      </div>
    </div>
    <div class="person-meta">${escHtml(user.faculty_name || 'Sin facultad')}</div>
    <div class="person-meta">${escHtml(user.career_name || '')}${user.semester ? ' · Semestre ' + escHtml(user.semester) : ''}</div>
    ${user.bio ? `<p style="font-size:13px;color:var(--text-secondary);margin-top:10px">${escHtml(user.bio)}</p>` : ''}
    <div class="person-actions">
      <button class="btn-secondary" onclick="window.location.href='profile.php?id=${user.user_id}'">Perfil</button>
      ${isMe ? '' : isContact
        ? `<button class="btn-danger" onclick="removeContact(${user.user_id})">Quitar</button>`
        : `<button class="btn-primary" onclick="addContact(${user.user_id})">Agregar</button>`}
    </div>
  `;

  return el;
}

async function addContact(userId) {
  try {
    await apiFetch(`directory/contacts/${userId}`, { method: 'POST' });
    showToast('Contacto agregado', 'success');
    loadDirectory(true);
  } catch (err) {
    showToast(err.message || 'No se pudo agregar', 'error');
  }
}

async function removeContact(userId) {
  try {
    await apiFetch(`directory/contacts/${userId}`, { method: 'DELETE' });
    showToast('Contacto eliminado', 'info');
    loadDirectory(true);
  } catch (err) {
    showToast(err.message || 'No se pudo eliminar', 'error');
  }
}
