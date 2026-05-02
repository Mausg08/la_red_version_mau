/**
 * UniLink — auth.js
 * Dominios aceptados:
 *   @alumno.buap.mx  → alumno   (student)
 *   @correo.buap.mx  → profesor (professor)
 */

// ── Patrones de validación ───────────────────────────────────
const STUDENT_PATTERN   = /^[a-zA-Z0-9._%+\-]+@alumno\.buap\.mx$/i;
const PROFESSOR_PATTERN = /^[a-zA-Z0-9._%+\-]+@correo\.buap\.mx$/i;

function detectAccountType(email) {
  if (STUDENT_PATTERN.test(email.trim()))   return 'student';
  if (PROFESSOR_PATTERN.test(email.trim())) return 'professor';
  return null;
}

function isInstitutionalEmail(email) {
  return detectAccountType(email) !== null;
}

// ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  setupTabs();
  setupPasswordToggles();   // ← fix del ojo
  setupPasswordStrength();
  setupEmailBadges();
  setupForms();
  checkUrlMessages();
});

/* ====== TABS ====== */
function setupTabs() {
  document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(`form-${tab.dataset.tab}`)?.classList.add('active');
    });
  });
  if (window.location.hash === '#register') {
    document.querySelector('[data-tab="register"]')?.click();
  }
}

/* ====== TOGGLE CONTRASEÑA (fix) ====== */
/**
 * setupPasswordToggles busca todos los botones .toggle-pass y
 * les asigna el listener correcto sin depender del atributo onclick.
 * También se expone togglePassword() para compatibilidad con el HTML inline.
 */
function setupPasswordToggles() {
  document.querySelectorAll('.toggle-pass').forEach(btn => {
    // Remover el onclick inline para evitar doble disparo
    btn.removeAttribute('onclick');

    btn.addEventListener('click', () => {
      // El input está justo antes del botón dentro del mismo .input-wrap
      const wrap  = btn.closest('.input-wrap');
      const input = wrap ? wrap.querySelector('input[type="password"], input[type="text"]') : null;
      if (!input) return;

      if (input.type === 'password') {
        input.type      = 'text';
        btn.textContent = '🙈';
        btn.setAttribute('title', 'Ocultar contraseña');
      } else {
        input.type      = 'password';
        btn.textContent = '👁';
        btn.setAttribute('title', 'Mostrar contraseña');
      }
    });
  });
}

// Función global por si algún lugar del HTML la llama directamente
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  if (input.type === 'password') {
    input.type      = 'text';
    btn.textContent = '🙈';
  } else {
    input.type      = 'password';
    btn.textContent = '👁';
  }
}

/* ====== BADGE DINÁMICO (muestra si es alumno o profesor) ====== */
function setupEmailBadges() {
  attachBadge('email-login', 'login-type-badge');
  attachBadge('reg-email',   'reg-type-badge', handleRegEmailType);
}

function attachBadge(inputId, badgeId, onTypeChange) {
  const input = document.getElementById(inputId);
  const badge = document.getElementById(badgeId);
  if (!input || !badge) return;

  const refresh = () => {
    const email = input.value.trim();
    const type  = detectAccountType(email);

    // Reset
    badge.className    = 'account-type-badge';
    badge.style.display = 'none';
    input.style.borderColor = '';

    if (!email) { if (onTypeChange) onTypeChange(null); return; }

    if (type === 'student') {
      badge.innerHTML     = '🎓 <span>Cuenta de <strong>Alumno BUAP</strong></span>';
      badge.classList.add('student');
      badge.style.display = 'flex';
      input.style.borderColor = '#2557A7';
    } else if (type === 'professor') {
      badge.innerHTML     = '👨‍🏫 <span>Cuenta de <strong>Profesor BUAP</strong></span>';
      badge.classList.add('professor');
      badge.style.display = 'flex';
      input.style.borderColor = '#E65100';
    } else if (email.includes('@')) {
      badge.innerHTML     = '⚠️ <span>Dominio no válido. Usa @alumno.buap.mx o @correo.buap.mx</span>';
      badge.classList.add('error');
      badge.style.display = 'flex';
      input.style.borderColor = '#C0392B';
    }

    if (onTypeChange) onTypeChange(type);
  };

  input.addEventListener('input', refresh);
  input.addEventListener('blur',  refresh);
}

/** Oculta/muestra el campo Semestre según sea alumno o profesor */
function handleRegEmailType(type) {
  const semGroup  = document.getElementById('semester-group');
  const semSelect = document.getElementById('reg-semester');
  if (!semGroup || !semSelect) return;

  if (type === 'professor') {
    semGroup.style.opacity       = '0.4';
    semGroup.style.pointerEvents = 'none';
    semSelect.removeAttribute('required');
  } else {
    semGroup.style.opacity       = '1';
    semGroup.style.pointerEvents = '';
    semSelect.setAttribute('required', 'required');
  }
}

/* ====== FORTALEZA DE CONTRASEÑA ====== */
function setupPasswordStrength() {
  const input = document.getElementById('reg-password');
  const meter = document.getElementById('pass-strength');
  if (!input || !meter) return;

  input.addEventListener('input', () => {
    const p = input.value;
    let score = 0;
    if (p.length >= 8)          score++;
    if (/[A-Z]/.test(p))        score++;
    if (/[0-9]/.test(p))        score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;
    meter.dataset.strength = score;
  });
}

/* ====== FORMULARIOS ====== */
function setupForms() {

  // ── LOGIN ────────────────────────────────────────────────
  document.getElementById('form-login')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn      = e.target.querySelector('button[type="submit"]');
    const email    = e.target.querySelector('[name="email"]').value.trim();
    const password = e.target.querySelector('[name="password"]').value;

    clearError('login-error');

    if (!isInstitutionalEmail(email)) {
      showError('login-error',
        'Solo puedes ingresar con un correo BUAP: @alumno.buap.mx (alumnos) o @correo.buap.mx (profesores).');
      return;
    }

    setLoading(btn, true, 'Verificando...');
    try {
      const res  = await fetch('/backend/api-gateway/index.php?service=auth&path=auth/login', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ email, password }),
      });
      const data = await res.json();

      if (!data.success) { showError('login-error', data.message || 'Correo o contraseña incorrectos.'); return; }

      sessionStorage.setItem('ul_token', data.data.token);
      await fetch('/backend/api-gateway/auth.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ action: 'set_session', token: data.data.token }),
      });
      window.location.href = '/frontend/pages/feed.php';
    } catch {
      showError('login-error', 'Error de conexión. Verifica tu internet.');
    } finally {
      setLoading(btn, false, 'Entrar a UniLink');
    }
  });

  // ── REGISTRO ─────────────────────────────────────────────
  document.getElementById('form-register')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn      = e.target.querySelector('button[type="submit"]');
    const email    = e.target.querySelector('[name="email"]').value.trim();
    const password = e.target.querySelector('[name="password"]').value;

    clearError('register-error');

    if (!isInstitutionalEmail(email)) {
      showError('register-error',
        'Solo puedes registrarte con un correo BUAP:\n• Alumnos: @alumno.buap.mx\n• Profesores: @correo.buap.mx');
      return;
    }

    if (password.length < 8) {
      showError('register-error', 'La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    const accountType = detectAccountType(email);
    setLoading(btn, true, 'Creando cuenta...');

    try {
      const body = {
        email,
        password,
        first_name:   e.target.querySelector('[name="first_name"]').value.trim(),
        last_name:    e.target.querySelector('[name="last_name"]').value.trim(),
        student_id:   e.target.querySelector('[name="student_id"]').value.trim(),
        faculty_id:   e.target.querySelector('[name="faculty_id"]').value,
        semester:     e.target.querySelector('[name="semester"]')?.value || '1',
        account_type: accountType,
      };

      const res  = await fetch('/backend/api-gateway/index.php?service=auth&path=auth/register', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(body),
      });
      const data = await res.json();

      if (!data.success) {
        showError('register-error', data.message || 'Error al crear la cuenta.');
        return;
      }

      const label = accountType === 'professor' ? 'Profesor' : 'Alumno';
      showSuccess(`¡Cuenta de ${label} creada! Ya puedes iniciar sesión.`);
      document.querySelector('[data-tab="login"]')?.click();
      document.getElementById('email-login').value = email;
    } catch {
      showError('register-error', 'Error de conexión. Intenta de nuevo.');
    } finally {
      setLoading(btn, false, 'Crear mi cuenta');
    }
  });
}

/* ====== HELPERS ====== */
function setLoading(btn, loading, label) {
  btn.disabled = loading;
  const span = btn.querySelector('.btn-text');
  if (span) span.textContent = label;
}

function showError(id, msg) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = msg;
  el.style.cssText = '';          // restaurar estilos por defecto del .form-error
  el.classList.remove('hidden');
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function clearError(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('hidden');
}

function showSuccess(msg) {
  const el = document.getElementById('login-error');
  if (!el) return;
  el.textContent   = msg;
  el.style.cssText = 'background:#E3F5F0;border-color:#9FE1CB;color:#0F6E56';
  el.classList.remove('hidden');
  setTimeout(() => { el.classList.add('hidden'); el.removeAttribute('style'); }, 6000);
}

function checkUrlMessages() {
  const p = new URLSearchParams(window.location.search);
  if (p.get('expired')     === '1') showError('login-error', 'Tu sesión expiró. Inicia sesión de nuevo.');
  if (p.get('deactivated') === '1') showError('login-error', 'Tu cuenta fue desactivada. Contacta a soporte.');
}
