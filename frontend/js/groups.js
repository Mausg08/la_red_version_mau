/**
 * UniLink — groups.js
 * Grupos NRC, explorar, crear, unirse
 */

let groupTab = 'mine';
let groupSearchTimer;
const groupFilters = { q: '', type: '', joined: true };

document.addEventListener('DOMContentLoaded', () => {
  loadGroups();
  setupCreateForm();
});

/* ====== TABS ====== */
function switchGroupTab(tab) {
  groupTab = tab;
  document.querySelectorAll('.mod-tab').forEach((t, i) => {
    const tabs = ['mine', 'explore', 'nrc'];
    t.classList.toggle('active', tabs[i] === tab);
  });
  document.getElementById('tab-groups-explore').classList.toggle('hidden', tab !== 'explore');
  document.getElementById('tab-groups-nrc').classList.toggle('hidden', tab !== 'nrc');

  if (tab === 'mine')    { groupFilters.joined = true;  groupFilters.type = ''; }
  if (tab === 'explore') { groupFilters.joined = false; }
  if (tab !== 'nrc')     loadGroups();
}

/* ====== LOAD GROUPS ====== */
async function loadGroups() {
  const grid = document.getElementById('groups-grid');
  grid.innerHTML = Array(6).fill('<div class="skeleton-card" style="height:160px"></div>').join('');
  document.getElementById('groups-empty').classList.add('hidden');

  try {
    const params = new URLSearchParams({
      limit: 24,
      ...(groupFilters.q    ? { q: groupFilters.q }    : {}),
      ...(groupFilters.type ? { type: groupFilters.type } : {}),
      ...(groupFilters.joined ? { joined: '1' } : {}),
    });

    const endpoint = groupTab === 'mine'
      ? `academic/my-groups?limit=24`
      : `academic/groups?${params}`;

    const res = await apiFetch(endpoint);
    const groups = res.groups || res.data || [];

    if (!groups.length) {
      grid.innerHTML = '';
      document.getElementById('groups-empty').classList.remove('hidden');
      return;
    }
    grid.innerHTML = '';
    groups.forEach(g => grid.appendChild(renderGroupCard(g)));
  } catch (e) {
    grid.innerHTML = '';
    showToast('Error al cargar grupos', 'error');
  }
}

/* ====== RENDER GROUP CARD ====== */
function renderGroupCard(g) {
  const el = document.createElement('div');
  el.className = 'group-card';

  const typeLabels = {
    nrc: 'Materia NRC', faculty: 'Facultad', career: 'Carrera',
    club: 'Club', study: 'Grupo de estudio', general: 'General'
  };
  const isMember = g.is_member == 1 || g.member_role;

  el.innerHTML = `
    <div class="group-card-header">
      <div class="group-card-icon">${g.icon || '👥'}</div>
      <div style="flex:1;min-width:0">
        <div class="group-card-name">${escHtml(g.name)}</div>
        <div class="group-card-type">${typeLabels[g.type] || g.type}${g.nrc_code ? ` · NRC ${escHtml(g.nrc_code)}` : ''}</div>
      </div>
    </div>
    ${g.description ? `<div class="group-card-desc">${escHtml(g.description)}</div>` : ''}
    <div class="group-card-footer">
      <div class="group-card-count">
        👥 ${g.member_count || 0} miembros
        ${g.unread > 0 ? `<span class="group-unread">${g.unread}</span>` : ''}
      </div>
      ${isMember
        ? `<button class="btn-leave" onclick="event.stopPropagation();leaveGroup(${g.group_id})">Salir</button>
           <button class="btn-joined">✓ Unido</button>`
        : `<button class="btn-join" onclick="event.stopPropagation();joinGroup(${g.group_id}, this)">Unirse</button>`
      }
    </div>`;

  el.onclick = () => openGroupDetail(g.group_id);
  return el;
}

/* ====== JOIN / LEAVE ====== */
async function joinGroup(id, btn) {
  btn.disabled = true;
  btn.textContent = 'Uniéndote...';
  try {
    await apiFetch(`academic/groups/${id}/join`, { method: 'POST' });
    showToast('Te uniste al grupo 🎉', 'success');
    loadGroups();
  } catch (e) {
    showToast(e.message || 'Error al unirse', 'error');
    btn.disabled = false;
    btn.textContent = 'Unirse';
  }
}

async function leaveGroup(id) {
  if (!confirm('¿Salir de este grupo?')) return;
  try {
    await apiFetch(`academic/groups/${id}/leave`, { method: 'DELETE' });
    showToast('Saliste del grupo', 'info');
    loadGroups();
  } catch (e) {
    showToast(e.message || 'Error al salir', 'error');
  }
}

/* ====== JOIN BY NRC ====== */
async function joinByNRC() {
  const nrc = document.getElementById('nrc-input').value.trim();
  const result = document.getElementById('nrc-result');
  if (!nrc) { result.innerHTML = '<p style="color:var(--uni-red);font-size:13px">Ingresa un código NRC.</p>'; return; }

  result.innerHTML = '<p style="font-size:13px;color:var(--text-muted)">Buscando...</p>';

  try {
    const res = await apiFetch(`academic/groups?q=${encodeURIComponent(nrc)}&type=nrc`);
    const groups = res.data || [];

    if (!groups.length) {
      result.innerHTML = `<p style="color:var(--uni-red);font-size:13px">No se encontró ningún grupo con NRC "${escHtml(nrc)}". Puede que aún no exista.</p>`;
      return;
    }

    const g = groups[0];
    result.innerHTML = `
      <div class="group-card" style="cursor:default;margin-top:8px">
        <div class="group-card-header">
          <div class="group-card-icon">${g.icon || '📚'}</div>
          <div>
            <div class="group-card-name">${escHtml(g.name)}</div>
            <div class="group-card-type">NRC: ${escHtml(g.nrc_code || nrc)} · ${g.member_count || 0} miembros</div>
          </div>
        </div>
        <div class="modal-footer" style="padding-top:12px">
          <button class="btn-primary" onclick="joinGroup(${g.group_id}, this)">Unirme a este grupo</button>
        </div>
      </div>`;
  } catch {
    result.innerHTML = '<p style="color:var(--uni-red);font-size:13px">Error al buscar. Intenta de nuevo.</p>';
  }
}

/* ====== GROUP DETAIL (simple redirect or modal) ====== */
function openGroupDetail(id) {
  window.location.href = `group-detail.php?id=${id}`;
}

/* ====== SEARCH ====== */
function debounceGroupSearch(val) {
  clearTimeout(groupSearchTimer);
  groupSearchTimer = setTimeout(() => {
    groupFilters.q = val;
    loadGroups();
  }, 400);
}

function loadGroups() {
  groupFilters.type = document.getElementById('groupType')?.value || '';
  _loadGroups();
}

// Rename inner to avoid conflict
async function _loadGroups() {
  const grid = document.getElementById('groups-grid');
  grid.innerHTML = Array(6).fill('<div class="skeleton-card" style="height:160px"></div>').join('');

  try {
    const params = new URLSearchParams({
      limit: 24,
      ...(groupFilters.q     ? { q: groupFilters.q }       : {}),
      ...(groupFilters.type  ? { type: groupFilters.type }  : {}),
      ...(groupFilters.joined ? { joined: '1' }             : {}),
    });

    const endpoint = groupTab === 'mine'
      ? `academic/my-groups?limit=24`
      : `academic/groups?${params}`;

    const res    = await apiFetch(endpoint);
    const groups = res.groups || res.data || [];

    if (!groups.length) {
      grid.innerHTML = '';
      document.getElementById('groups-empty').classList.remove('hidden');
      return;
    }
    grid.innerHTML = '';
    groups.forEach(g => grid.appendChild(renderGroupCard(g)));
  } catch {
    grid.innerHTML = '';
    showToast('Error al cargar grupos', 'error');
  }
}

/* ====== CREATE GROUP ====== */
function openCreateGroupModal() {
  document.getElementById('createGroupModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function selectIcon(icon) {
  document.getElementById('selected-icon').value = icon;
  document.querySelectorAll('.icon-opt').forEach(b => b.classList.remove('selected'));
  document.querySelector(`.icon-opt[data-icon="${icon}"]`)?.classList.add('selected');
}

function setupCreateForm() {
  document.getElementById('create-group-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = formToJSON(e.target);
    try {
      const res = await apiFetch('academic/groups', {
        method: 'POST',
        body: JSON.stringify(fd)
      });
      closeModal('createGroupModal');
      showToast('¡Grupo creado! 🎉', 'success');
      loadGroups();
    } catch (err) {
      showToast(err.message || 'Error al crear grupo', 'error');
    }
  });
}

function closeModal(id) {
  document.getElementById(id)?.classList.add('hidden');
  document.body.style.overflow = '';
}
