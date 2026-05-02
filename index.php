<?php
// INDEX.PHP VERSIÓN 2 - Limpiar cualquier sesión vieja ANTES de verificar
if (session_status() === PHP_SESSION_NONE) session_start();

// Si tiene ?expired=1 o ?deactivated=1, limpiar sesión y mostrar página limpia
if (isset($_GET['expired']) || isset($_GET['deactivated'])) {
    $_SESSION = [];
    session_destroy();
    // Reiniciar sesión limpia
    session_start();
}

// Redirigir solo si tiene sesión VÁLIDA (no solo si existe)
if (!empty($_SESSION['jwt_token']) && !empty($_SESSION['user'])) {
    require_once __DIR__ . '/backend/shared/helpers.php';
    require_once __DIR__ . '/backend/shared/jwt.php';
    $payload = JWT::verify($_SESSION['jwt_token']);
    if ($payload) {
        header('Location: /RedSocial_BUAP/frontend/pages/feed.php');
        exit;
    } else {
        // Token inválido — limpiar
        $_SESSION = [];
        session_destroy();
        session_start();
    }
}

define('BASE_PATH', '/RedSocial_BUAP');

// Mensaje a mostrar según parámetro URL
$errorMsg = '';
if (isset($_GET['expired']))     $errorMsg = 'Tu sesión expiró. Inicia sesión de nuevo.';
if (isset($_GET['deactivated'])) $errorMsg = 'Tu cuenta fue desactivada. Contacta a soporte.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniLink — Tu Red Universitaria BUAP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/css/main.css">
  <link rel="stylesheet" href="frontend/css/auth.css">
  <style>
    .account-type-badge {
      display: none;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      margin-top: 6px;
    }
    .account-type-badge.student   { display:flex; background:#E8F0FD; color:#2557A7; border:1px solid #c5d9f8; }
    .account-type-badge.professor { display:flex; background:#FFF3E0; color:#E65100; border:1px solid #FFCC80; }
    .account-type-badge.invalid   { display:flex; background:#FEECEC; color:#C0392B; border:1px solid #FBBCBC; }

    .toggle-pass {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 18px;
      padding: 4px 6px;
      z-index: 5;
      opacity: 0.7;
      line-height: 1;
    }
    .toggle-pass:hover { opacity: 1; }
    .input-wrap { position: relative; display: flex; align-items: center; }
    .input-wrap .input-icon { position:absolute; left:12px; pointer-events:none; z-index:1; }
    .input-wrap input { padding-left:38px !important; padding-right:48px !important; width:100%; }
  </style>
</head>
<body class="auth-page">

  <div class="bg-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
    <div class="shape shape-4"></div>
  </div>

  <div class="auth-container">

    <!-- Panel izquierdo -->
    <div class="auth-brand">
      <div class="brand-logo">
        <span class="logo-icon">U</span>
        <span class="logo-text">UniLink</span>
      </div>
      <h1 class="brand-headline">Tu universidad,<br><em>conectada.</em></h1>
      <p class="brand-sub">Comparte, aprende, compra y participa con toda la comunidad de la BUAP desde un solo lugar.</p>

      <div class="brand-features">
        <div class="feat-item"><div class="feat-icon feat-blue">📚</div><span>Marketplace universitario</span></div>
        <div class="feat-item"><div class="feat-icon feat-green">🎓</div><span>Grupos por materia (NRC)</span></div>
        <div class="feat-item"><div class="feat-icon feat-orange">🛡️</div><span>Moderación por facultad</span></div>
        <div class="feat-item"><div class="feat-icon feat-red">🚨</div><span>Botón de pánico campus</span></div>
      </div>

      <div style="margin-top:28px;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,0.15)">
        <div style="padding:12px 16px;background:rgba(255,255,255,0.06)">
          <p style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">Correos aceptados</p>
          <div style="display:flex;flex-direction:column;gap:6px">
            <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:rgba(255,255,255,0.08);border-radius:8px">
              <span>🎓</span>
              <div>
                <p style="font-size:13px;font-weight:600;color:#fff">Alumnos</p>
                <p style="font-size:12px;color:rgba(255,255,255,0.5)">@alumno.buap.mx</p>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:rgba(255,255,255,0.08);border-radius:8px">
              <span>👨‍🏫</span>
              <div>
                <p style="font-size:13px;font-weight:600;color:#fff">Profesores</p>
                <p style="font-size:12px;color:rgba(255,255,255,0.5)">@correo.buap.mx</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Panel derecho -->
    <div class="auth-form-panel">
      <div class="auth-tabs">
        <button class="auth-tab active" data-tab="login">Iniciar sesión</button>
        <button class="auth-tab" data-tab="register">Registrarse</button>
      </div>

      <!-- LOGIN -->
      <form id="form-login" class="auth-form active" novalidate>
        <div class="form-group">
          <label for="email-login">Correo institucional BUAP</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" id="email-login" name="email"
                   placeholder="tu.correo@alumno.buap.mx" autocomplete="email">
          </div>
          <div id="login-type-badge" class="account-type-badge"></div>
          <small class="field-hint">Alumnos: @alumno.buap.mx &nbsp;·&nbsp; Profesores: @correo.buap.mx</small>
        </div>

        <div class="form-group">
          <label for="password-login">Contraseña</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="password-login" name="password"
                   placeholder="••••••••" autocomplete="current-password">
            <button type="button" class="toggle-pass" id="toggle-login-pass" title="Mostrar contraseña">👁️</button>
          </div>
        </div>

        <div class="form-options">
          <label class="checkbox-label">
            <input type="checkbox" name="remember"> Recordarme
          </label>
          <a href="frontend/pages/forgot-password.php" class="link-small">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="btn-primary btn-full">
          <span class="btn-text">Entrar a UniLink</span>
        </button>
        <!-- Mostrar error del servidor si viene en URL -->
        <div id="login-error" class="form-error <?= $errorMsg ? '' : 'hidden' ?>">
          <?= htmlspecialchars($errorMsg) ?>
        </div>
      </form>

      <!-- REGISTRO -->
      <form id="form-register" class="auth-form" novalidate>
        <div class="form-row">
          <div class="form-group">
            <label>Nombre(s)</label>
            <input type="text" name="first_name" placeholder="Nombre" required>
          </div>
          <div class="form-group">
            <label>Apellidos</label>
            <input type="text" name="last_name" placeholder="Apellido" required>
          </div>
        </div>

        <div class="form-group">
          <label for="reg-email">Correo institucional BUAP</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" id="reg-email" name="email"
                   placeholder="tu.correo@alumno.buap.mx" autocomplete="email">
          </div>
          <div id="reg-type-badge" class="account-type-badge"></div>
          <small class="field-hint">Alumnos: @alumno.buap.mx &nbsp;·&nbsp; Profesores: @correo.buap.mx</small>
        </div>

        <div class="form-group">
          <label>Matrícula / Número de empleado</label>
          <input type="text" name="student_id" placeholder="Ej: 202312345" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Facultad</label>
            <select name="faculty_id" required>
              <option value="">— Selecciona —</option>
              <option value="1">Ingeniería</option>
              <option value="2">Diseño</option>
              <option value="3">Negocios</option>
              <option value="4">Medicina</option>
              <option value="5">Ciencias Sociales</option>
              <option value="6">Arquitectura</option>
            </select>
          </div>
          <div class="form-group" id="semester-group">
            <label>Semestre</label>
            <select name="semester" id="reg-semester">
              <?php for($i=1;$i<=10;$i++): ?>
              <option value="<?= $i ?>"><?= $i ?>° semestre</option>
              <?php endfor; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="reg-password">Contraseña</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="reg-password" name="password"
                   placeholder="Mínimo 8 caracteres" autocomplete="new-password">
            <button type="button" class="toggle-pass" id="toggle-reg-pass" title="Mostrar contraseña">👁️</button>
          </div>
          <div class="password-strength" id="pass-strength"></div>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="terms" required>
            Acepto los <a href="#" class="link-inline">Términos de uso</a> y la
            <a href="#" class="link-inline">Política de privacidad</a>
          </label>
        </div>

        <button type="submit" class="btn-primary btn-full">
          <span class="btn-text">Crear mi cuenta</span>
        </button>
        <div id="register-error" class="form-error hidden"></div>
      </form>

      <div class="auth-footer">
        <p>UniLink es exclusivo para la comunidad de la <strong>BUAP</strong>.</p>
        <p>Alumnos: <strong>@alumno.buap.mx</strong> &nbsp;·&nbsp; Profesores: <strong>@correo.buap.mx</strong></p>
      </div>
    </div>
  </div>

<script>
(function () {
  var BASE = '/RedSocial_BUAP';
  var STUDENT_RE   = /^[a-zA-Z0-9._%+\-]+@alumno\.buap\.mx$/i;
  var PROFESSOR_RE = /^[a-zA-Z0-9._%+\-]+@correo\.buap\.mx$/i;

  function detectType(email) {
    var e = (email || '').trim();
    if (STUDENT_RE.test(e))   return 'student';
    if (PROFESSOR_RE.test(e)) return 'professor';
    return null;
  }
  function isValid(email) { return detectType(email) !== null; }

  // ── Tabs ──
  function setupTabs() {
    document.querySelectorAll('.auth-tab').forEach(function(tab) {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.auth-tab').forEach(function(t){ t.classList.remove('active'); });
        document.querySelectorAll('.auth-form').forEach(function(f){ f.classList.remove('active'); });
        tab.classList.add('active');
        var form = document.getElementById('form-' + tab.dataset.tab);
        if (form) form.classList.add('active');
      });
    });
  }

  // ── Toggle contraseña ──
  function setupToggle(btnId, inputId) {
    var btn   = document.getElementById(btnId);
    var input = document.getElementById(inputId);
    if (!btn || !input) return;
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      if (input.type === 'password') {
        input.type = 'text'; btn.textContent = '🙈';
      } else {
        input.type = 'password'; btn.textContent = '👁️';
      }
      input.focus();
    });
  }

  // ── Badge tipo de cuenta ──
  function setupBadge(inputId, badgeId, onType) {
    var input = document.getElementById(inputId);
    var badge = document.getElementById(badgeId);
    if (!input || !badge) return;
    function refresh() {
      var email = input.value.trim();
      var type  = detectType(email);
      badge.className = 'account-type-badge';
      badge.innerHTML = '';
      if (!email) { if (onType) onType(null); return; }
      if (type === 'student') {
        badge.classList.add('student');
        badge.innerHTML = '🎓 <span>Cuenta de <strong>Alumno BUAP</strong></span>';
      } else if (type === 'professor') {
        badge.classList.add('professor');
        badge.innerHTML = '👨‍🏫 <span>Cuenta de <strong>Profesor BUAP</strong></span>';
      } else if (email.indexOf('@') !== -1) {
        badge.classList.add('invalid');
        badge.innerHTML = '⚠️ <span>Usa @alumno.buap.mx o @correo.buap.mx</span>';
      }
      if (onType) onType(type);
    }
    input.addEventListener('input', refresh);
    input.addEventListener('blur',  refresh);
  }

  function handleRegType(type) {
    var g = document.getElementById('semester-group');
    var s = document.getElementById('reg-semester');
    if (!g || !s) return;
    if (type === 'professor') {
      g.style.opacity = '0.4'; g.style.pointerEvents = 'none';
    } else {
      g.style.opacity = '1'; g.style.pointerEvents = '';
    }
  }

  // ── Fortaleza contraseña ──
  function setupStrength() {
    var input = document.getElementById('reg-password');
    var meter = document.getElementById('pass-strength');
    if (!input || !meter) return;
    input.addEventListener('input', function() {
      var p = input.value, s = 0;
      if (p.length >= 8) s++;
      if (/[A-Z]/.test(p)) s++;
      if (/[0-9]/.test(p)) s++;
      if (/[^A-Za-z0-9]/.test(p)) s++;
      meter.dataset.strength = s;
    });
  }

  // ── UI helpers ──
  function showError(id, msg) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.style.cssText = '';
    el.classList.remove('hidden');
    el.scrollIntoView({ behavior:'smooth', block:'nearest' });
  }
  function clearError(id) {
    var el = document.getElementById(id);
    if (el) { el.classList.add('hidden'); el.textContent = ''; }
  }
  function showSuccess(msg) {
    var el = document.getElementById('login-error');
    if (!el) return;
    el.textContent = msg;
    el.style.cssText = 'background:#E3F5F0;border-color:#9FE1CB;color:#0F6E56;display:block';
    el.classList.remove('hidden');
    setTimeout(function(){ el.classList.add('hidden'); el.removeAttribute('style'); }, 6000);
  }
  function setLoading(btn, loading, label) {
    btn.disabled = loading;
    var span = btn.querySelector('.btn-text');
    if (span) span.textContent = label;
  }

  // ── LOGIN ──
  function setupLogin() {
    var form = document.getElementById('form-login');
    if (!form) return;
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      clearError('login-error');

      var email    = (form.querySelector('[name="email"]').value || '').trim();
      var password = form.querySelector('[name="password"]').value || '';
      var btn      = form.querySelector('button[type="submit"]');

      if (!isValid(email)) {
        showError('login-error', 'Solo puedes ingresar con un correo BUAP: @alumno.buap.mx o @correo.buap.mx');
        return;
      }
      if (!password) {
        showError('login-error', 'Ingresa tu contraseña.');
        return;
      }

      setLoading(btn, true, 'Verificando...');

      try {
        // PASO 1: Autenticar → obtener JWT
        var loginRes = await fetch(BASE + '/backend/api-gateway/index.php?service=auth&path=auth/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: email, password: password })
        });

        var loginText = await loginRes.text();
        var loginData;
        try {
          loginData = JSON.parse(loginText);
        } catch(parseErr) {
          showError('login-error', 'Error del servidor (respuesta no es JSON). Verifica los logs de Apache.');
          console.error('Respuesta cruda del login:', loginText);
          return;
        }

        if (!loginData.success) {
          showError('login-error', loginData.message || 'Correo o contraseña incorrectos.');
          return;
        }

        var token = loginData.data ? loginData.data.token : loginData.token;
        if (!token) {
          showError('login-error', 'El servidor no devolvió un token. Revisa la consola.');
          console.error('Respuesta login sin token:', loginData);
          return;
        }

        sessionStorage.setItem('ul_token', token);

        // PASO 2: Crear sesión PHP
        var sessRes = await fetch(BASE + '/backend/api-gateway/auth.php?action=set_session', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'set_session', token: token })
        });

        var sessText = await sessRes.text();
        var sessData;
        try {
          sessData = JSON.parse(sessText);
        } catch(parseErr) {
          showError('login-error', 'Error al crear sesión PHP. Respuesta no es JSON.');
          console.error('Respuesta cruda del set_session:', sessText);
          return;
        }

        if (!sessData.success) {
          showError('login-error', 'Error al crear sesión: ' + (sessData.message || 'desconocido'));
          console.error('set_session falló:', sessData);
          return;
        }

        // PASO 3: Redirigir al feed
        window.location.href = BASE + '/frontend/pages/feed.php';

      } catch(err) {
        showError('login-error', 'Error de red: ' + err.message);
        console.error('Error de fetch:', err);
      } finally {
        setLoading(btn, false, 'Entrar a UniLink');
      }
    });
  }

  // ── REGISTRO ──
  function setupRegister() {
    var form = document.getElementById('form-register');
    if (!form) return;
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      clearError('register-error');
      var email    = (form.querySelector('[name="email"]').value || '').trim();
      var password = form.querySelector('[name="password"]').value || '';
      var btn      = form.querySelector('button[type="submit"]');

      if (!isValid(email)) {
        showError('register-error', 'Solo puedes registrarte con un correo BUAP.');
        return;
      }
      if (password.length < 8) {
        showError('register-error', 'La contraseña debe tener al menos 8 caracteres.');
        return;
      }

      var accountType = detectType(email);
      setLoading(btn, true, 'Creando cuenta...');

      try {
        var body = {
          email:        email,
          password:     password,
          first_name:   (form.querySelector('[name="first_name"]').value || '').trim(),
          last_name:    (form.querySelector('[name="last_name"]').value  || '').trim(),
          student_id:   (form.querySelector('[name="student_id"]').value || '').trim(),
          faculty_id:   form.querySelector('[name="faculty_id"]').value,
          semester:     form.querySelector('[name="semester"]').value || '1',
          account_type: accountType
        };

        var res  = await fetch(BASE + '/backend/api-gateway/index.php?service=auth&path=auth/register', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        var text = await res.text();
        var data;
        try { data = JSON.parse(text); }
        catch(e) { showError('register-error', 'Error del servidor: ' + text.substring(0, 200)); return; }

        if (!data.success) {
          showError('register-error', data.message || 'Error al crear la cuenta.');
          return;
        }

        var label = (accountType === 'professor') ? 'Profesor' : 'Alumno';
        showSuccess('¡Cuenta de ' + label + ' creada! Ya puedes iniciar sesión.');
        document.querySelector('[data-tab="login"]').click();
        document.getElementById('email-login').value = email;

      } catch(err) {
        showError('register-error', 'Error de conexión: ' + err.message);
      } finally {
        setLoading(btn, false, 'Crear mi cuenta');
      }
    });
  }

  // ── INIT ──
  document.addEventListener('DOMContentLoaded', function() {
    // Limpiar el ?expired=1 de la URL sin recargar la página
    if (window.location.search.includes('expired') || window.location.search.includes('deactivated')) {
      var cleanUrl = window.location.pathname;
      window.history.replaceState({}, document.title, cleanUrl);
    }

    setupTabs();
    setupToggle('toggle-login-pass', 'password-login');
    setupToggle('toggle-reg-pass',   'reg-password');
    setupBadge('email-login', 'login-type-badge');
    setupBadge('reg-email',   'reg-type-badge', handleRegType);
    setupStrength();
    setupLogin();
    setupRegister();
  });

})();
</script>

</body>
</html>
