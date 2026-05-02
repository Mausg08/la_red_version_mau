/**
 * UniLink — calendar.js
 * Month / Week / List views, event loading, create events
 */

const MONTHS_ES  = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const DAYS_ES    = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
const TYPE_COLORS = {
  clase:'ev-clase', examen:'ev-examen', taller:'ev-taller',
  conferencia:'ev-conferencia', deportivo:'ev-deportivo',
  cultural:'ev-cultural', institutional:'ev-institutional', otro:'ev-otro',
};
const TYPE_BG = {
  clase:'#2557A7', examen:'#C0392B', taller:'#B45309', conferencia:'#7C3AED',
  deportivo:'#0F6E56', cultural:'#D97706', institutional:'#1A3A6B', otro:'#6B7280',
};
const TYPE_LABELS = {
  clase:'Clase', examen:'Examen', taller:'Taller', conferencia:'Conferencia',
  deportivo:'Deportivo', cultural:'Cultural', institutional:'Institucional', otro:'Otro',
};

let currentDate   = new Date();
let currentView   = 'month';
let allEvents     = [];
let eventsLoaded  = false;

document.addEventListener('DOMContentLoaded', () => {
  loadEvents().then(renderCalendar);
  loadUpcoming();
  setupNav();
  setupCreateForm();

  const qs = new URLSearchParams(window.location.search);
  if (qs.get('event')) showEventById(parseInt(qs.get('event')));
});

/* ======== EVENT LOADING ======== */
async function loadEvents() {
  try {
    const year  = currentDate.getFullYear();
    const month = currentDate.getMonth() + 1;
    const { data } = await apiFetch(`academic/events?limit=100&year=${year}&month=${month}`);
    allEvents = data || [];
    eventsLoaded = true;
  } catch { allEvents = []; }
}

async function loadUpcoming() {
  try {
    const { data } = await apiFetch('academic/events?upcoming=1&limit=8');
    const container = document.getElementById('upcoming-list');
    if (!data?.length) {
      container.innerHTML = '<p style="padding:16px;font-size:13px;color:var(--text-muted);text-align:center">Sin próximos eventos</p>';
      return;
    }
    container.innerHTML = data.map(e => {
      const d = new Date(e.event_date);
      const cls = TYPE_COLORS[e.type] || 'ev-otro';
      return `
        <div class="upcoming-item" onclick="showEventDetail(${JSON.stringify(e).replace(/"/g,'&quot;')})">
          <div class="upcoming-date-box ${cls}" style="min-width:44px;height:44px">
            <span class="upcoming-day">${d.getDate()}</span>
            <span class="upcoming-month">${MONTHS_ES[d.getMonth()].substring(0,3)}</span>
          </div>
          <div>
            <div class="upcoming-title">${escHtml(e.title)}</div>
            <div class="upcoming-loc">${e.location ? '📍 '+escHtml(e.location) : TYPE_LABELS[e.type]||''}</div>
          </div>
        </div>`;
    }).join('');
  } catch { /* silent */ }
}

/* ======== RENDER DISPATCHER ======== */
function renderCalendar() {
  const typeFilter = document.getElementById('cal-type-filter')?.value || '';
  const events = typeFilter ? allEvents.filter(e => e.type === typeFilter) : allEvents;

  document.getElementById('cal-month-label').textContent =
    `${MONTHS_ES[currentDate.getMonth()]} ${currentDate.getFullYear()}`;

  const view = document.getElementById('cal-view');
  if (currentView === 'month') renderMonthView(view, events);
  else if (currentView === 'week') renderWeekView(view, events);
  else renderListView(view, events);
}

/* ======== MONTH VIEW ======== */
function renderMonthView(container, events) {
  const year  = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const today = new Date();

  const firstDay  = new Date(year, month, 1);
  const startDay  = new Date(firstDay);
  startDay.setDate(startDay.getDate() - firstDay.getDay()); // Sunday start

  const eventsByDay = {};
  events.forEach(e => {
    const key = new Date(e.event_date).toDateString();
    if (!eventsByDay[key]) eventsByDay[key] = [];
    eventsByDay[key].push(e);
  });

  let html = `
    <div class="cal-month-grid">
      <div class="cal-week-headers">
        ${DAYS_ES.map(d => `<div class="cal-weekday">${d}</div>`).join('')}
      </div>
      <div class="cal-weeks">`;

  const cur = new Date(startDay);
  for (let week = 0; week < 6; week++) {
    // Stop if we've gone past the month and the week starts in the next month
    if (week > 3 && cur.getMonth() !== month) break;

    html += '<div class="cal-week-row">';
    for (let day = 0; day < 7; day++) {
      const isToday      = cur.toDateString() === today.toDateString();
      const isOtherMonth = cur.getMonth() !== month;
      const dayKey       = cur.toDateString();
      const dayEvents    = eventsByDay[dayKey] || [];
      const dateNum      = cur.getDate();
      const dateISO      = cur.toISOString().split('T')[0];

      const evPills = dayEvents.slice(0, 3).map(e =>
        `<span class="cal-event-pill ${TYPE_COLORS[e.type]||'ev-otro'}"
               onclick="event.stopPropagation();showEventById(${e.event_id})"
               title="${escHtml(e.title)}">${escHtml(e.title)}</span>`
      ).join('');
      const moreLink = dayEvents.length > 3
        ? `<span class="cal-more-link" onclick="event.stopPropagation();showDayEvents('${dateISO}')">+${dayEvents.length-3} más</span>`
        : '';

      html += `
        <div class="cal-day${isOtherMonth?' other-month':''}${isToday?' today':''}"
             onclick="handleDayClick('${dateISO}')">
          <div class="day-num">${dateNum}</div>
          ${evPills}${moreLink}
        </div>`;
      cur.setDate(cur.getDate() + 1);
    }
    html += '</div>';
  }

  html += '</div></div>';
  container.innerHTML = html;
}

/* ======== WEEK VIEW ======== */
function renderWeekView(container, events) {
  const today  = new Date();
  const dow    = currentDate.getDay();
  const weekStart = new Date(currentDate);
  weekStart.setDate(currentDate.getDate() - dow);

  const days = Array.from({length:7}, (_, i) => {
    const d = new Date(weekStart);
    d.setDate(weekStart.getDate() + i);
    return d;
  });

  const eventsByDay = {};
  events.forEach(e => {
    const key = new Date(e.event_date).toDateString();
    if (!eventsByDay[key]) eventsByDay[key] = [];
    eventsByDay[key].push(e);
  });

  let html = `<div class="cal-week-view"><div class="cal-week-header">
    <div style="border-right:1px solid var(--border)"></div>
    ${days.map(d => {
      const isToday = d.toDateString() === today.toDateString();
      return `<div class="cal-week-day-col${isToday?' today':''}">
        <span class="cal-week-day-num">${d.getDate()}</span>
        ${DAYS_ES[d.getDay()]}
      </div>`;
    }).join('')}
  </div>
  <div style="display:grid;grid-template-columns:60px repeat(7,1fr);min-height:400px">
    <div style="border-right:1px solid var(--border);padding:8px;font-size:11px;color:var(--text-muted)"></div>
    ${days.map(d => {
      const dayEvents = eventsByDay[d.toDateString()] || [];
      return `<div style="padding:6px;border-right:1px solid var(--border);border-top:1px solid var(--border)">
        ${dayEvents.map(e =>
          `<div class="cal-event-pill ${TYPE_COLORS[e.type]||'ev-otro'}"
                style="margin-bottom:4px"
                onclick="showEventById(${e.event_id})"
                title="${escHtml(e.title)}">${escHtml(e.title)}</div>`
        ).join('')}
      </div>`;
    }).join('')}
  </div></div>`;

  container.innerHTML = html;
}

/* ======== LIST VIEW ======== */
function renderListView(container, events) {
  if (!events.length) {
    container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted)">Sin eventos este mes</div>';
    return;
  }

  const sorted = [...events].sort((a,b) => new Date(a.event_date) - new Date(b.event_date));
  container.innerHTML = `<div class="cal-list">${sorted.map(e => {
    const d = new Date(e.event_date);
    return `
      <div class="cal-list-item" onclick="showEventById(${e.event_id})">
        <div class="cal-list-date">
          <div class="cal-list-day">${d.getDate()}</div>
          <div class="cal-list-month">${MONTHS_ES[d.getMonth()].substring(0,3)}</div>
        </div>
        <div class="cal-list-info">
          <div class="cal-list-title">${escHtml(e.title)}</div>
          <div class="cal-list-meta">
            <span class="cal-event-pill ${TYPE_COLORS[e.type]||'ev-otro'}" style="display:inline-flex">${TYPE_LABELS[e.type]||'Otro'}</span>
            ${e.location ? `<span>📍 ${escHtml(e.location)}</span>` : ''}
            <span>🕐 ${d.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'})}</span>
          </div>
        </div>
      </div>`;
  }).join('')}</div>`;
}

/* ======== EVENT DETAIL ======== */
async function showEventById(id) {
  const cached = allEvents.find(e => e.event_id === id);
  if (cached) { showEventDetail(cached); return; }
  try {
    const { data } = await apiFetch(`academic/events/${id}`);
    showEventDetail(data);
  } catch { showToast('Error al cargar evento', 'error'); }
}

function showEventDetail(e) {
  const start  = new Date(e.event_date);
  const end    = e.end_date ? new Date(e.end_date) : null;
  const color  = TYPE_BG[e.type] || '#6B7280';
  const fmtDate = d => d.toLocaleDateString('es-MX', {weekday:'long',day:'numeric',month:'long',year:'numeric'});
  const fmtTime = d => d.toLocaleTimeString('es-MX', {hour:'2-digit',minute:'2-digit'});

  document.getElementById('event-detail-content').innerHTML = `
    <div class="event-detail-type-bar" style="background:${color}"></div>
    <div class="event-detail-body">
      <span class="badge" style="background:${color}20;color:${color};margin-bottom:10px;display:inline-flex">
        ${TYPE_LABELS[e.type]||'Evento'}
      </span>
      <div class="event-detail-title">${escHtml(e.title)}</div>
      ${e.description ? `<p style="font-size:14px;color:var(--text-secondary);line-height:1.65;margin-bottom:16px">${escHtml(e.description)}</p>` : ''}
      <div class="event-detail-meta">
        <div class="event-detail-meta-row">
          <span class="event-detail-meta-icon">📅</span>
          <span>${fmtDate(start)}</span>
        </div>
        <div class="event-detail-meta-row">
          <span class="event-detail-meta-icon">🕐</span>
          <span>${fmtTime(start)}${end ? ` — ${fmtTime(end)}` : ''}</span>
        </div>
        ${e.location ? `
        <div class="event-detail-meta-row">
          <span class="event-detail-meta-icon">📍</span>
          <span>${escHtml(e.location)}</span>
        </div>` : ''}
        ${e.organizer_name ? `
        <div class="event-detail-meta-row">
          <span class="event-detail-meta-icon">👤</span>
          <span>Organiza: ${escHtml(e.organizer_name)}</span>
        </div>` : ''}
        ${e.faculty_name ? `
        <div class="event-detail-meta-row">
          <span class="event-detail-meta-icon">🏛</span>
          <span>${escHtml(e.faculty_name)}</span>
        </div>` : ''}
      </div>
      <div style="display:flex;gap:10px;margin-top:4px">
        <button class="btn-secondary" style="font-size:13px" onclick="addToGoogleCalendar(${JSON.stringify(e).replace(/"/g,'&quot;')})">
          📆 Agregar a Google Calendar
        </button>
        <button class="btn-ghost" onclick="shareEvent(${e.event_id})" style="font-size:13px">
          ↗️ Compartir
        </button>
      </div>
    </div>`;

  document.getElementById('eventDetailModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function addToGoogleCalendar(e) {
  const start  = new Date(e.event_date).toISOString().replace(/[-:]/g,'').split('.')[0]+'Z';
  const end    = e.end_date ? new Date(e.end_date).toISOString().replace(/[-:]/g,'').split('.')[0]+'Z' : start;
  const url    = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(e.title)}&dates=${start}/${end}&details=${encodeURIComponent(e.description||'')}&location=${encodeURIComponent(e.location||'')}`;
  window.open(url, '_blank');
}

function shareEvent(id) {
  const url = `${location.origin}/frontend/pages/calendar.php?event=${id}`;
  if (navigator.share) navigator.share({ title: 'UniLink — Evento', url });
  else navigator.clipboard.writeText(url).then(() => showToast('Enlace copiado 📋', 'info'));
}

function handleDayClick(dateISO) {
  if (CAN_CREATE_EVENTS) {
    document.querySelector('[name="event_date"]').value = dateISO + 'T08:00';
    openEventModal();
  }
}

function showDayEvents(dateISO) {
  currentDate = new Date(dateISO + 'T12:00');
  switchView('list');
}

/* ======== NAVIGATION ======== */
function setupNav() {
  document.getElementById('cal-prev').onclick = () => {
    if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() - 1);
    else if (currentView === 'week') currentDate.setDate(currentDate.getDate() - 7);
    else currentDate.setMonth(currentDate.getMonth() - 1);
    loadEvents().then(renderCalendar);
  };
  document.getElementById('cal-next').onclick = () => {
    if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() + 1);
    else if (currentView === 'week') currentDate.setDate(currentDate.getDate() + 7);
    else currentDate.setMonth(currentDate.getMonth() + 1);
    loadEvents().then(renderCalendar);
  };
  document.getElementById('cal-today').onclick = () => {
    currentDate = new Date();
    loadEvents().then(renderCalendar);
  };
}

function switchView(view) {
  currentView = view;
  document.querySelectorAll('.view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === view));
  renderCalendar();
}

/* ======== CREATE EVENT ======== */
function openEventModal() {
  document.getElementById('createEventModal')?.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function setupCreateForm() {
  document.getElementById('create-event-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn  = e.target.querySelector('[type="submit"]');
    const data = formToJSON(e.target);
    data.is_public = e.target.querySelector('[name="is_public"]').checked;

    btn.disabled = true;
    btn.textContent = 'Creando...';

    try {
      await apiFetch('academic/events', { method: 'POST', body: JSON.stringify(data) });
      closeModal('createEventModal');
      showToast('Evento creado 🎉', 'success');
      e.target.reset();
      await loadEvents();
      renderCalendar();
      loadUpcoming();
    } catch (err) {
      showToast(err.message || 'Error al crear evento', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Crear evento';
    }
  });
}

function closeModal(id) {
  document.getElementById(id)?.classList.add('hidden');
  document.body.style.overflow = '';
}
