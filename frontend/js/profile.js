/**
 * UniLink — profile.js
 * Load user profile, own posts, follow/unfollow, edit profile
 */

let profilePostsPage = 1;
let profileHasMore   = true;

document.addEventListener('DOMContentLoaded', () => {
  loadProfile();
  loadProfilePosts();
});

/* ====== LOAD PROFILE ====== */
async function loadProfile() {
  try {
    const { user } = await apiFetch(`users/${PROFILE_ID}`);
    renderProfile(user);

    if (IS_OWN_PROFILE) {
      // Pre-fill edit form
      document.getElementById('edit-bio').value          = user.bio || '';
      document.getElementById('edit-semester').value     = user.semester || 1;
      document.getElementById('edit-phone').value        = user.phone || '';
      document.getElementById('edit-show-phone').checked = !!user.show_phone;
    }
  } catch {
    document.getElementById('profile-info-section').innerHTML =
      '<p style="color:var(--uni-red);padding:20px">Error al cargar el perfil.</p>';
  }
}

function renderProfile(u) {
  const initials = (u.first_name?.[0] || '') + (u.last_name?.[0] || '');
  const avatarEl = document.getElementById('profile-avatar-el');
  if (u.avatar) {
    avatarEl.innerHTML = `<img src="${escHtml(u.avatar)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
  } else {
    avatarEl.textContent = initials.toUpperCase();
  }

  const roleLabels = {
    student:'Estudiante', professor:'Profesor', admin:'Administrador',
    moderator:'Moderador', staff:'Administrativo'
  };
  const roleBadgeClass = { admin:'badge-red', professor:'badge-orange', moderator:'badge-blue' };

  const starsHtml = Array.from({length:5}, (_,i) =>
    `<span style="color:${i < Math.round((u.reputation_score||0)/20) ? '#F59E0B' : 'var(--gray-300)'}">★</span>`
  ).join('');

  document.getElementById('profile-info-section').innerHTML = `
    <div class="profile-name">${escHtml(u.name || u.first_name+' '+u.last_name)}</div>
    <div class="profile-role-badge">
      <span class="badge ${roleBadgeClass[u.role] || 'badge-blue'}">${roleLabels[u.role] || u.role}</span>
    </div>
    <div class="profile-faculty-info">
      ${u.faculty_name ? `🏛 ${escHtml(u.faculty_name)}` : ''}
      ${u.career_name  ? ` · 📚 ${escHtml(u.career_name)}` : ''}
      ${u.semester     ? ` · ${u.semester}° semestre` : ''}
    </div>
    ${u.bio ? `<div class="profile-bio">${escHtml(u.bio)}</div>` : ''}

    <div class="profile-stats">
      <div class="profile-stat">
        <span class="profile-stat-num">${u.posts_count || 0}</span>
        <span class="profile-stat-label">Posts</span>
      </div>
      <div class="profile-stat">
        <span class="profile-stat-num">${u.followers_count || 0}</span>
        <span class="profile-stat-label">Seguidores</span>
      </div>
      <div class="profile-stat">
        <span class="profile-stat-num">${u.following_count || 0}</span>
        <span class="profile-stat-label">Siguiendo</span>
      </div>
    </div>

    <div class="profile-actions">
      ${IS_OWN_PROFILE
        ? `<button class="btn-primary" onclick="document.getElementById('editProfileModal').classList.remove('hidden')">
             ✏️ Editar perfil
           </button>`
        : `<button class="btn-primary" id="follow-btn" onclick="toggleFollow(${u.user_id}, ${u.is_following})">
             ${u.is_following ? '✓ Siguiendo' : '+ Seguir'}
           </button>
           ${u.email ? `<button class="btn-secondary" onclick="contactUser('${escHtml(u.email)}')">💬 Contactar</button>` : ''}`
      }
    </div>

    ${u.reputation_score > 0 ? `
      <div class="profile-reputation">
        <span class="rep-stars">${starsHtml}</span>
        <span style="font-weight:600">${Math.round(u.reputation_score / 20 * 10) / 10} / 5</span>
        <span style="color:var(--text-muted)">Reputación Marketplace</span>
      </div>` : ''}

    ${u.phone ? `
      <div style="margin-top:12px;font-size:13px;color:var(--text-secondary)">
        📱 ${escHtml(u.phone)}
      </div>` : ''}`;

  document.getElementById('posts-section-title').textContent =
    IS_OWN_PROFILE ? 'Mis publicaciones' : `Publicaciones de ${u.first_name}`;
}

/* ====== LOAD PROFILE POSTS ====== */
async function loadProfilePosts() {
  if (!profileHasMore) return;
  document.getElementById('profile-feed-loader').classList.remove('hidden');
  try {
    const res    = await apiFetch(`feed/posts?author=${PROFILE_ID}&page=${profilePostsPage}&limit=10`);
    const posts  = res.posts || res.data || [];
    const meta   = res.meta || {};
    const container = document.getElementById('profile-feed');

    if (profilePostsPage === 1 && !posts.length) {
      container.innerHTML = `
        <div class="card card-body" style="text-align:center;padding:40px;color:var(--text-muted)">
          <p style="font-size:32px;margin-bottom:8px">✍️</p>
          <p>${IS_OWN_PROFILE ? 'Aún no has publicado nada.' : 'Sin publicaciones aún.'}</p>
        </div>`;
      return;
    }

    posts.forEach(post => {
      const el = renderPost(post); // from feed.js (loaded globally via utils)
      container.appendChild(el);
    });

    profileHasMore = meta.has_more || false;
    profilePostsPage++;
  } catch { /* silent */ }
  finally {
    document.getElementById('profile-feed-loader').classList.add('hidden');
  }
}

/* ====== FOLLOW / UNFOLLOW ====== */
async function toggleFollow(userId, isFollowing) {
  const btn = document.getElementById('follow-btn');
  try {
    if (isFollowing) {
      await apiFetch(`users/${userId}/unfollow`, { method: 'DELETE' });
      btn.textContent = '+ Seguir';
      btn.onclick = () => toggleFollow(userId, false);
    } else {
      await apiFetch(`users/${userId}/follow`, { method: 'POST' });
      btn.textContent = '✓ Siguiendo';
      btn.onclick = () => toggleFollow(userId, true);
    }
  } catch (e) {
    showToast(e.message || 'Error', 'error');
  }
}

/* ====== EDIT PROFILE ====== */
document.getElementById('edit-profile-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = formToJSON(e.target);
  fd.show_phone = !!document.getElementById('edit-show-phone').checked;
  try {
    await apiFetch('users/me', { method: 'PUT', body: JSON.stringify(fd) });
    closeModal('editProfileModal');
    showToast('Perfil actualizado ✓', 'success');
    loadProfile();
  } catch (err) {
    showToast(err.message || 'Error al guardar', 'error');
  }
});

/* ====== AVATAR UPLOAD ====== */
async function uploadAvatar(input) {
  const file = input.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('avatar', file);
  try {
    const res = await fetch('/backend/api-gateway/index.php?service=users&path=users/me', {
      method: 'PUT',
      headers: { 'Authorization': `Bearer ${UL_TOKEN}` },
      body: fd
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    showToast('Foto actualizada ✓', 'success');
    loadProfile();
  } catch (err) {
    showToast(err.message || 'Error al subir foto', 'error');
  }
}

function contactUser(email) {
  window.open(`mailto:${email}`, '_blank');
}

function closeModal(id) {
  document.getElementById(id)?.classList.add('hidden');
  document.body.style.overflow = '';
}

/* Lazy load more posts on scroll */
window.addEventListener('scroll', () => {
  if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 400)) {
    loadProfilePosts();
  }
});
