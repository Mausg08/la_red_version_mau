<?php
/**
 * UniLink — users/auth_controller.php
 *
 * Dominios institucionales BUAP aceptados:
 *   @alumno.buap.mx  → rol automático: student
 *   @correo.buap.mx  → rol automático: professor
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/jwt.php';
require_once __DIR__ . '/../../shared/response.php';
require_once __DIR__ . '/user_model.php';

$method = REQUEST_METHOD;
$path   = REQUEST_PATH;

// ============================================================
//  POST auth/login
// ============================================================
if ($method === 'POST' && str_contains($path, 'login')) {
    $email    = sanitize(REQUEST_BODY['email'] ?? '');
    $password = REQUEST_BODY['password'] ?? '';

    if (!$email || !$password) {
        Response::error('Correo y contraseña requeridos.', 422);
    }

    // ── Validación de dominio BUAP (doble capa: cliente + servidor) ──
    if (!isInstitutionalEmail($email)) {
        Response::error(
            'Acceso restringido. Solo pueden ingresar miembros de la BUAP: ' .
            '@alumno.buap.mx (alumnos) o @correo.buap.mx (profesores).',
            422
        );
    }

    $user = UserModel::findByEmail($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        logEvent('warn', 'Failed login attempt', ['email' => $email]);
        Response::error('Correo o contraseña incorrectos.', 401);
    }

    if ($user['status'] !== 'active') {
        Response::error('Tu cuenta está suspendida o inactiva. Contacta a soporte.', 403);
    }

    $token = JWT::generate([
        'user_id'    => $user['user_id'],
        'email'      => $user['email'],
        'role'       => $user['role'],
        'faculty_id' => $user['faculty_id'],
    ]);

    UserModel::updateLastLogin($user['user_id']);

    Response::success([
        'token' => $token,
        'user'  => [
            'user_id'      => $user['user_id'],
            'first_name'   => $user['first_name'],
            'last_name'    => $user['last_name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'faculty_id'   => $user['faculty_id'],
            'faculty_name' => $user['faculty_name'] ?? '',
            'avatar'       => $user['avatar'],
        ]
    ], 'Sesión iniciada correctamente.');
}

// ============================================================
//  POST auth/register
// ============================================================
if ($method === 'POST' && str_contains($path, 'register')) {
    $body = REQUEST_BODY;

    $required = ['email','password','first_name','last_name','student_id','faculty_id'];
    $errors   = [];
    foreach ($required as $field) {
        if (empty($body[$field])) $errors[] = "$field es requerido";
    }
    if ($errors) Response::error('Datos incompletos.', 422, $errors);

    $email = sanitize($body['email']);

    // ── Validación de dominio BUAP en el servidor ──────────────
    if (!isInstitutionalEmail($email)) {
        Response::error(
            'Solo puedes registrarte con un correo institucional BUAP: ' .
            '@alumno.buap.mx (alumnos) o @correo.buap.mx (profesores).',
            422
        );
    }

    // Verificar duplicado
    if (UserModel::findByEmail($email)) {
        Response::error('Ya existe una cuenta con este correo.', 409);
    }

    if (strlen($body['password']) < 8) {
        Response::error('La contraseña debe tener al menos 8 caracteres.', 422);
    }

    // ── Asignar rol automáticamente según el dominio ───────────
    // El cliente sugiere account_type, pero el servidor lo
    // determina de forma autoritativa desde el correo.
    $accountType = detectAccountType($email);

    $role = match($accountType) {
        'professor' => 'professor',
        'student'   => 'student',
        default     => 'student',  // fallback (nunca debería llegar aquí)
    };

    // Semestre solo aplica para alumnos
    $semester = ($role === 'student')
        ? min(12, max(1, (int)($body['semester'] ?? 1)))
        : null;

    $user_id = UserModel::create([
        'email'      => $email,
        'password'   => password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]),
        'first_name' => sanitize($body['first_name']),
        'last_name'  => sanitize($body['last_name']),
        'student_id' => strtoupper(sanitize($body['student_id'])),
        'faculty_id' => (int)$body['faculty_id'],
        'semester'   => $semester,
        'role'       => $role,
        'status'     => 'active',
    ]);

    if (!$user_id) {
        Response::error('Error al crear la cuenta. Intenta de nuevo.', 500);
    }

    $roleLabel = $role === 'professor' ? 'Profesor' : 'Alumno';

    Response::success(
        ['user_id' => $user_id, 'role' => $role],
        "¡Bienvenido a UniLink BUAP! Cuenta de {$roleLabel} creada exitosamente.",
        201
    );
}

// ============================================================
//  POST auth/logout
// ============================================================
if ($method === 'POST' && str_contains($path, 'logout')) {
    Response::success(null, 'Sesión cerrada.');
}

// ============================================================
//  POST auth/forgot-password
// ============================================================
if ($method === 'POST' && str_contains($path, 'forgot-password')) {
    $email = sanitize(REQUEST_BODY['email'] ?? '');
    if (!$email) Response::error('Correo requerido.', 422);

    if (!isInstitutionalEmail($email)) {
        Response::error(
            'Ingresa tu correo institucional BUAP (@alumno.buap.mx o @correo.buap.mx).',
            422
        );
    }

    $user = UserModel::findByEmail($email);
    if ($user) {
        $reset_token = bin2hex(random_bytes(32));
        UserModel::storeResetToken($user['user_id'], $reset_token, time() + 3600);
    }

    // Respuesta genérica para evitar enumeración de correos
    Response::success(null, 'Si el correo existe, recibirás instrucciones en breve.');
}
