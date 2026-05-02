/**
 * UniLink — polls.js
 * Opinion monitor: load polls, vote, create, results
 */

let pollsFilter = '';
const userVotes = {}; // pollId → optionId or rating

document.addEventListener('DOMContentLoaded', () => {
  loadPolls();
  setupCreatePollForm();
});

/* ======== LOAD POLLS ======== */
async function loadPolls() {
  const grid = document.getElementById('polls-grid');
  grid.innerHTML = Array(6).fill('<div class="skeleton-card" style="height:220px"></div>').join('');
  document.getElementById('polls-empty').classList.add('hidden');

  try {
    const params = pollsFilter && pollsFilter !== 'activa'
      ? `category=${pollsFilter}`
      : pollsFilter === 'activa' ? 'status=active' : '';

    const { data: polls } = await apiFetch(`academic/polls?${params}&limit=30`);

    grid.innerHTML = '';
    if (!polls?.length) {
      document.getElementById('polls-empty').classList.remove('hidden');
      return;
    }

    polls.forEach(p => grid.appendChild(renderPollCard(p)));
    buildSummaryBanner(polls);
  } catch {
    grid.innerHTML = '';
    showToast('Error al cargar encuestas', 'error');
  }
}

/* ======== RENDER POLL CARD ======== */
function renderPollCard(poll) {
  const el = document.createElement('div');
  el.className = 'poll-card';
  el.id = `poll-${poll.poll_id}`;

  const isActive  = poll.status === 'active';
  const hasVoted  = poll.user_voted || !!userVotes[poll.poll_id];
  const totalVotes = poll.total_votes || 0;
  const closesStr  = poll.closes_at ? `Cierra ${timeAgo(poll.closes_at)}` : '';

  const catIcons = { cafeteria:'🍽', laboratorio:'🔬', transporte:'🚌', biblioteca:'📚', academico:'🎓', general:'📊' };

  el.innerHTML = `
    <div class="poll-card-header">
      <div class="poll-question">${catIcons[poll.category]||'📊'} ${escHtml(poll.title)}</div>
      <span class="poll-status-badge poll-status-${poll.status}">${poll.status === 'active' ? 'Activa' : 'Cerrada'}</span>
    </div>
    ${poll.description ? `<p class="poll-description">${escHtml(poll.description)}</p>` : ''}
    <div id="poll-body-${poll.poll_id}">
      ${buildPollBody(poll, hasVoted)}
    </div>
    <div class="poll-card-footer">
      <span>${totalVotes} votos${closesStr ? ` · ${closesStr}` : ''}</span>
      ${isActive && !hasVoted
        ? `<button class="poll-vote-btn" id="vote-btn-${poll.poll_id}" onclick="submitVote(${poll.poll_id}, '${poll.poll_type}')">Votar</button>`
        : hasVoted ? '<span style="color:var(--uni-green);font-weight:600">✓ Ya votaste</span>' : ''
      }
    </div>`;

  return el;
}

function buildPollBody(poll, hasVoted) {
  const show = hasVoted || poll.status === 'closed';

  if (poll.poll_type === 'rating') {
    if (show && poll.avg_rating) {
      const avg = parseFloat(poll.avg_rating);
      const stars = Array.from({length:5}, (_,i) =>
        `<span style="color:${i<Math.round(avg)?'#F59E0B':'var(--gray-300)'}">★</span>`
      ).join('');
      return `<div style="text-align:center;padding:8px 0">
        <div style="font-size:36px;font-weight:800;font-family:var(--font-display)">${avg.toFixed(1)}</div>
        <div style="font-size:20px">${stars}</div>
        <div style="font-size:12px;color:var(--text-muted)">Promedio de ${poll.total_votes} votos</div>
      </div>`;
    }
    return `<div class="poll-rating-stars" id="stars-${poll.poll_id}">
      ${Array.from({length:5},(_,i) =>
        `<span class="poll-star" data-val="${i+1}" onclick="selectRating(${poll.poll_id}, ${i+1})">★</span>`
      ).join('')}
    </div>`;
  }

  if (poll.poll_type === 'yesno') {
    const yesVotes = poll.options?.find(o=>o.text==='Sí')?.votes || 0;
    const noVotes  = poll.options?.find(o=>o.text==='No')?.votes  || 0;
    const total    = yesVotes + noVotes || 1;
    if (show) {
      return `<div class="poll-yesno">
        <div class="poll-yesno-btn yes selected" style="cursor:default">
          ✅ Sí<br><strong>${Math.round(yesVotes/total*100)}%</strong>
        </div>
        <div class="poll-yesno-btn no" style="cursor:default">
          ❌ No<br><strong>${Math.round(noVotes/total*100)}%</strong>
        </div>
      </div>`;
    }
    return `<div class="poll-yesno">
      <div class="poll-yesno-btn yes" onclick="selectYesNo(${poll.poll_id}, 'yes', this)">✅ Sí</div>
      <div class="poll-yesno-btn no"  onclick="selectYesNo(${poll.poll_id}, 'no', this)">❌ No</div>
    </div>`;
  }

  // Options type
  const options = poll.options || [];
  const total   = options.reduce((s,o) => s + (o.votes||0), 0) || 1;
  return `<div class="poll-options">
    ${options.map(opt => {
      const pct = Math.round((opt.votes||0)/total*100);
      return `<div class="poll-option${show?' voted':''}"
                   onclick="selectOption(${poll.poll_id}, ${opt.option_id}, this)"
                   data-option="${opt.option_id}">
        ${show ? `<div class="poll-option-bar" style="width:${pct}%"></div>` : ''}
        <div class="poll-option-text">
          <span>${escHtml(opt.text)}</span>
          ${show ? `<span class="poll-option-pct">${pct}%</span>` : ''}
        </div>
      </div>`;
    }).join('')}
  </div>`;
}

/* ======== VOTE INTERACTION ======== */
function selectOption(pollId, optionId, el) {
  if (userVotes[pollId] !== undefined) return;
  el.closest('.poll-options').querySelectorAll('.poll-option').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  userVotes[pollId] = optionId;
  document.getElementById(`vote-btn-${pollId}`)?.removeAttribute('disabled');
}

function selectRating(pollId, val) {
  userVotes[pollId] = val;
  document.querySelectorAll(`#stars-${pollId} .poll-star`).forEach((s,i) => {
    s.classList.toggle('active', i < val);
  });
  document.getElementById(`vote-btn-${pollId}`)?.removeAttribute('disabled');
}

function selectYesNo(pollId, answer, el) {
  if (userVotes[pollId]) return;
  userVotes[pollId] = answer;
  el.closest('.poll-yesno').querySelectorAll('.poll-yesno-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById(`vote-btn-${pollId}`)?.removeAttribute('disabled');
}

async function submitVote(pollId, type) {
  const vote = userVotes[pollId];
  if (vote === undefined || vote === null) {
    showToast('Selecciona una opción primero', 'info');
    return;
  }
  const btn = document.getElementById(`vote-btn-${pollId}`);
  if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

  try {
    const res = await apiFetch(`academic/polls/${pollId}/vote`, {
      method: 'POST',
      body: JSON.stringify({ vote, type })
    });
    // Refresh this poll card
    if (res.poll) {
      const card = document.getElementById(`poll-${pollId}`);
      if (card) {
        const newCard = renderPollCard({ ...res.poll, user_voted: true });
        card.replaceWith(newCard);
      }
    } else {
      loadPolls();
    }
    showToast('¡Voto registrado! Gracias por participar 🗳', 'success');
  } catch (e) {
    showToast(e.message || 'Error al votar', 'error');
    if (btn) { btn.disabled = false; btn.textContent = 'Votar'; }
  }
}

/* ======== SUMMARY BANNER ======== */
function buildSummaryBanner(polls) {
  const ratingPolls = polls.filter(p => p.poll_type === 'rating' && p.avg_rating && p.total_votes > 5);
  if (!ratingPolls.length) return;

  const banner = document.getElementById('polls-banner');
  const grid   = document.getElementById('polls-summary-grid');
  banner.style.display = 'block';

  const catColors = { cafeteria:'#E85D24', laboratorio:'#0F6E56', transporte:'#2557A7', biblioteca:'#7C3AED', academico:'#D97706' };

  grid.innerHTML = ratingPolls.slice(0, 6).map(p => {
    const avg   = parseFloat(p.avg_rating);
    const color = catColors[p.category] || '#6B7280';
    const stars = Array.from({length:5}, (_,i) =>
      `<span style="color:${i<Math.round(avg)?'#F59E0B':'var(--gray-300)'}">★</span>`
    ).join('');
    return `
      <div class="summary-item">
        <span class="summary-score" style="color:${color}">${avg.toFixed(1)}</span>
        <div class="summary-stars">${stars}</div>
        <div class="summary-label">${escHtml(p.title.substring(0,40))}</div>
        <div style="font-size:11px;color:var(--text-muted)">${p.total_votes} votos</div>
      </div>`;
  }).join('');
}

/* ======== FILTER ======== */
function filterPolls(cat) {
  pollsFilter = cat;
  document.querySelectorAll('.cat-tab').forEach((t, i) => {
    const cats = ['', 'activa', 'cafeteria', 'laboratorio', 'transporte', 'biblioteca', 'academico'];
    t.classList.toggle('active', cats[i] === cat);
  });
  loadPolls();
}

/* ======== CREATE POLL ======== */
function openCreatePollModal() {
  document.getElementById('createPollModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function setPollType(type) {
  const optSection = document.getElementById('poll-options-section');
  if (type === 'options') {
    optSection.style.display = 'block';
  } else {
    optSection.style.display = 'none';
  }
}

function addPollOptionField() {
  const list  = document.getElementById('poll-options-list');
  const count = list.children.length + 1;
  if (count > 8) { showToast('Máximo 8 opciones', 'info'); return; }
  const div = document.createElement('div');
  div.className = 'form-group';
  div.innerHTML = `<input type="text" name="option[]" placeholder="Opción ${count}">`;
  list.appendChild(div);
}

function setupCreatePollForm() {
  document.getElementById('create-poll-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn  = e.target.querySelector('[type="submit"]');
    const data = formToJSON(e.target);
    const type = e.target.querySelector('[name="poll_type"]:checked')?.value || 'options';

    let options = [];
    if (type === 'options') {
      options = Array.from(e.target.querySelectorAll('[name="option[]"]'))
        .map(i => i.value.trim()).filter(Boolean);
      if (options.length < 2) { showToast('Agrega al menos 2 opciones', 'info'); return; }
    } else if (type === 'yesno') {
      options = ['Sí', 'No'];
    }

    btn.disabled    = true;
    btn.textContent = 'Publicando...';

    try {
      await apiFetch('academic/polls', {
        method: 'POST',
        body: JSON.stringify({ ...data, poll_type: type, options })
      });
      closeModal('createPollModal');
      e.target.reset();
      showToast('Encuesta publicada 🗳', 'success');
      loadPolls();
    } catch (err) {
      showToast(err.message || 'Error al publicar', 'error');
    } finally {
      btn.disabled    = false;
      btn.textContent = 'Publicar encuesta';
    }
  });
}

function closeModal(id) {
  document.getElementById(id)?.classList.add('hidden');
  document.body.style.overflow = '';
}
