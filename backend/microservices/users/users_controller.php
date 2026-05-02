<?php
/**
 * UniLink — users/users_controller.php
 * Directorio seguro, perfil, búsqueda, notificaciones, admin
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';
require_once __DIR__ . '/user_model.php';

$method = REQUEST_METHOD;
$path   = REQUEST_PATH;
$user   = CURRENT_USER;

// ============================================================
//  GET /users/search
// ============================================================
if ($method === 'GET' && str_contains($path, 'users/search')) {
    $q     = sanitize($_GET['q']     ?? '');
    $limit = min(20, (int)($_GET['limit'] ?? 8));

    if (mb_strlen($q) < 2) Response::success(['results' => []]);

    $results = UserModel::search($q, $limit, $user['user_id']);
    Response::success(['results' => $results]);
}

// ============================================================
//  GET /users/directory  — Protected directory
// ============================================================
if ($method === 'GET' && str_contains($path, 'users/directory')) {
    ['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPagination();
    $faculty_id = (int)($_GET['faculty_id'] ?? 0);
    $career_id  = (int)($_GET['career_id']  ?? 0);
    $q          = sanitize($_GET['q']       ?? '');

    $db     = UserModel::getDB();
    $where  = ["u.status = 'active'"];
    $params = [];

    if ($q) {
        $where[]  = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.student_id LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($faculty_id) { $where[] = 'u.faculty_id = ?'; $params[] = $faculty_id; }
    if ($career_id)  { $where[] = 'u.career_id = ?';  $params[] = $career_id; }

    $whereSQL = implode(' AND ', $where);
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare("
        SELECT u.user_id,
               CONCAT(u.first_name,' ',u.last_name) AS name,
               u.student_id, u.semester, u.avatar, u.role, u.bio,
               f.name AS faculty_name, c.name AS career_name,
               -- Phone only shown if user enabled it AND requester is not the same person
               CASE WHEN u.show_phone = 1 AND u.user_id != ? THEN u.phone ELSE NULL END AS phone,
               u.email AS email
        FROM users u
        LEFT JOIN faculties f ON f.faculty_id = u.faculty_id
        LEFT JOIN careers   c ON c.career_id  = u.career_id
        WHERE $whereSQL
        ORDER BY u.first_name, u.last_name
        LIMIT ? OFFSET ?");
    array_unshift($params, $user['user_id']);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
    $countStmt->execute(array_slice($params, 1, -2));
    $total = (int)$countStmt->fetchColumn();

    Response::paginated($users, $total, $page, $limit);
}

// ============================================================
//  GET /users/{id}  — Public profile
// ============================================================
if ($method === 'GET' && preg_match('#^users/(\d+)$#', $path, $m)) {
    $id      = (int)$m[1];
    $profile = UserModel::getPublicProfile($id, $user['user_id']);
    if (!$profile) Response::error('Usuario no encontrado.', 404);
    Response::success(['user' => $profile]);
}

// ============================================================
//  GET /users/{id}/contact  — Get protected contact info
// ============================================================
if ($method === 'GET' && preg_match('#^users/(\d+)/contact$#', $path, $m)) {
    $id = (int)$m[1];
    $db = UserModel::getDB();
    $stmt = $db->prepare("
        SELECT email,
               CASE WHEN show_phone=1 THEN phone ELSE NULL END AS phone
        FROM users WHERE user_id=? AND status='active'");
    $stmt->execute([$id]);
    $contact = $stmt->fetch();
    if (!$contact) Response::error('Usuario no encontrado.', 404);
    Response::success(['contact' => $contact]);
}

// ============================================================
//  PUT /users/me  — Update own profile
// ============================================================
if ($method === 'PUT' && str_contains($path, 'users/me')) {
    $body = REQUEST_BODY;
    $allowed = ['bio', 'show_phone', 'phone', 'semester'];
    $updates = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "$field = ?";
            $params[]  = is_string($body[$field]) ? sanitize($body[$field]) : $body[$field];
        }
    }

    // Avatar upload
    if (!empty($_FILES['avatar'])) {
        $url = uploadFile($_FILES['avatar'], 'avatars');
        if ($url) { $updates[] = 'avatar = ?'; $params[] = $url; }
    }

    if (empty($updates)) Response::error('Sin cambios para guardar.', 422);

    $params[] = $user['user_id'];
    UserModel::getDB()->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE user_id=?")
        ->execute($params);

    Response::success(null, 'Perfil actualizado.');
}

// ============================================================
//  GET /users/notifications
// ============================================================
if ($method === 'GET' && str_contains($path, 'users/notifications')) {
    $limit = min(50, (int)($_GET['limit'] ?? 15));
    $db    = UserModel::getDB();

    $stmt = $db->prepare("
        SELECT n.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name
        FROM notifications n
        LEFT JOIN users u ON u.user_id = n.sender_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ?");
    $stmt->execute([$user['user_id'], $limit]);
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unread = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $unread->execute([$user['user_id']]);

    Response::success([
        'notifications' => $notifs,
        'unread_count'  => (int)$unread->fetchColumn(),
    ]);
}

// ============================================================
//  PATCH /users/notifications/{id}/read
// ============================================================
if ($method === 'PATCH' && preg_match('#^users/notifications/(\d+)/read$#', $path, $m)) {
    UserModel::getDB()
        ->prepare("UPDATE notifications SET is_read=1 WHERE notif_id=? AND user_id=?")
        ->execute([(int)$m[1], $user['user_id']]);
    Response::success(null, 'Notificación marcada como leída.');
}

// ============================================================
//  PATCH /users/notifications/read-all
// ============================================================
if ($method === 'PATCH' && str_contains($path, 'notifications/read-all')) {
    UserModel::getDB()
        ->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")
        ->execute([$user['user_id']]);
    Response::success(null, 'Todas las notificaciones marcadas como leídas.');
}

// ============================================================
//  PATCH /users/{id}/role  (admin only)
// ============================================================
if ($method === 'PATCH' && preg_match('#^users/(\d+)/role$#', $path, $m)) {
    if ($user['role'] !== 'admin') Response::error('Solo administradores.', 403);
    $role = sanitize(REQUEST_BODY['role'] ?? '');
    $valid = ['student','professor','admin','moderator','staff'];
    if (!in_array($role, $valid)) Response::error('Rol inválido.', 422);
    UserModel::getDB()->prepare("UPDATE users SET role=? WHERE user_id=?")->execute([$role, (int)$m[1]]);
    Response::success(null, 'Rol actualizado.');
}

// ============================================================
//  PATCH /users/{id}/status  (admin only)
// ============================================================
if ($method === 'PATCH' && preg_match('#^users/(\d+)/status$#', $path, $m)) {
    if ($user['role'] !== 'admin') Response::error('Solo administradores.', 403);
    $status = sanitize(REQUEST_BODY['status'] ?? '');
    $valid  = ['active','inactive','suspended'];
    if (!in_array($status, $valid)) Response::error('Estado inválido.', 422);
    UserModel::getDB()->prepare("UPDATE users SET status=? WHERE user_id=?")->execute([$status, (int)$m[1]]);
    Response::success(null, 'Estado actualizado.');
}

// ============================================================
//  GET /users/admin  (admin only — user management table)
// ============================================================
if ($method === 'GET' && str_contains($path, 'users/admin')) {
    if ($user['role'] !== 'admin') Response::error('Solo administradores.', 403);
    ['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPagination();
    $role  = sanitize($_GET['role'] ?? '');
    $q     = sanitize($_GET['q']   ?? '');
    $db    = UserModel::getDB();
    $where = ['1=1'];
    $params = [];

    if ($role) { $where[] = 'u.role = ?'; $params[] = $role; }
    if ($q) {
        $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)";
        $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
    }
    $whereSQL = implode(' AND ', $where);
    $params[] = $limit; $params[] = $offset;

    $stmt = $db->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.student_id,
               u.role, u.status, u.last_login, f.name AS faculty_name
        FROM users u
        LEFT JOIN faculties f ON f.faculty_id = u.faculty_id
        WHERE $whereSQL ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
    $countStmt->execute(array_slice($params, 0, -2));
    $total = (int)$countStmt->fetchColumn();

    Response::paginated($users, $total, $page, $limit);
}
