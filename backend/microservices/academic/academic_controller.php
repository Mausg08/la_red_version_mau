<?php
/**
 * UniLink — academic/academic_controller.php
 * Grupos NRC, calendario de eventos, encuestas
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';

$method = REQUEST_METHOD;
$path   = REQUEST_PATH;
$user   = CURRENT_USER;

// ============================================================
//  GET /academic/my-groups
// ============================================================
if ($method === 'GET' && str_contains($path, 'my-groups')) {
    $limit = min(20, (int)($_GET['limit'] ?? 10));
    $db    = getDB_academic();

    $stmt = $db->prepare("
        SELECT g.group_id, g.name, g.type, g.icon, g.nrc_code,
               g.member_count, gm.role AS member_role,
               0 AS unread
        FROM groups_table g
        JOIN group_members gm ON gm.group_id = g.group_id AND gm.user_id = ?
        ORDER BY g.name
        LIMIT ?");
    $stmt->execute([$user['user_id'], $limit]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['groups' => $groups]);
}

// ============================================================
//  GET /academic/groups  — list all groups (with search)
// ============================================================
if ($method === 'GET' && preg_match('#^academic/groups$#', $path)) {
    ['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPagination();
    $q          = sanitize($_GET['q']          ?? '');
    $type       = sanitize($_GET['type']       ?? '');
    $faculty_id = (int)($_GET['faculty_id']   ?? 0);
    $joined_only = (bool)($_GET['joined']     ?? false);

    $db    = getDB_academic();
    $where = ['1=1'];
    $params = [];

    if ($q) {
        $where[]  = '(g.name LIKE ? OR g.nrc_code LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($type)       { $where[] = 'g.type = ?';       $params[] = $type; }
    if ($faculty_id) { $where[] = 'g.faculty_id = ?'; $params[] = $faculty_id; }
    if ($joined_only) {
        $where[] = 'EXISTS (SELECT 1 FROM group_members gm2 WHERE gm2.group_id=g.group_id AND gm2.user_id=?)';
        $params[] = $user['user_id'];
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT g.*,
               f.name AS faculty_name,
               EXISTS (SELECT 1 FROM group_members gm WHERE gm.group_id=g.group_id AND gm.user_id=?) AS is_member
        FROM groups_table g
        LEFT JOIN faculties f ON f.faculty_id = g.faculty_id
        WHERE $whereSQL
        ORDER BY g.member_count DESC
        LIMIT ? OFFSET ?");

    array_unshift($params, $user['user_id']);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM groups_table g WHERE $whereSQL");
    $countStmt->execute(array_slice($params, 1, -2));
    $total = (int)$countStmt->fetchColumn();

    Response::paginated($groups, $total, $page, $limit);
}

// ============================================================
//  GET /academic/groups/{id}
// ============================================================
if ($method === 'GET' && preg_match('#^academic/groups/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    $db = getDB_academic();

    $stmt = $db->prepare("
        SELECT g.*, f.name AS faculty_name,
               EXISTS (SELECT 1 FROM group_members gm WHERE gm.group_id=g.group_id AND gm.user_id=?) AS is_member,
               (SELECT role FROM group_members WHERE group_id=g.group_id AND user_id=?) AS my_role
        FROM groups_table g
        LEFT JOIN faculties f ON f.faculty_id = g.faculty_id
        WHERE g.group_id = ?");
    $stmt->execute([$user['user_id'], $user['user_id'], $id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) Response::error('Grupo no encontrado.', 404);

    // Recent members
    $members = $db->prepare("
        SELECT u.user_id, CONCAT(u.first_name,' ',u.last_name) AS name,
               u.avatar, gm.role, gm.joined_at
        FROM group_members gm
        JOIN users u ON u.user_id = gm.user_id
        WHERE gm.group_id = ?
        ORDER BY gm.joined_at DESC
        LIMIT 20");
    $members->execute([$id]);
    $group['members'] = $members->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['group' => $group]);
}

// ============================================================
//  POST /academic/groups/{id}/join
// ============================================================
if ($method === 'POST' && preg_match('#^academic/groups/(\d+)/join$#', $path, $m)) {
    $id = (int)$m[1];
    $db = getDB_academic();

    // Check if already member
    $check = $db->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
    $check->execute([$id, $user['user_id']]);
    if ($check->fetchColumn()) Response::error('Ya eres miembro de este grupo.', 409);

    $db->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?,?)")
       ->execute([$id, $user['user_id']]);
    $db->prepare("UPDATE groups_table SET member_count = member_count + 1 WHERE group_id=?")
       ->execute([$id]);

    Response::success(null, 'Te has unido al grupo.', 201);
}

// ============================================================
//  DELETE /academic/groups/{id}/leave
// ============================================================
if ($method === 'DELETE' && preg_match('#^academic/groups/(\d+)/leave$#', $path, $m)) {
    $id = (int)$m[1];
    $db = getDB_academic();

    $stmt = $db->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=? AND role != 'admin'");
    $stmt->execute([$id, $user['user_id']]);
    if (!$stmt->rowCount()) Response::error('No puedes salir de este grupo (eres admin o no eres miembro).', 403);

    $db->prepare("UPDATE groups_table SET member_count = GREATEST(0, member_count - 1) WHERE group_id=?")
       ->execute([$id]);

    Response::success(null, 'Saliste del grupo.');
}

// ============================================================
//  POST /academic/groups — Create group
// ============================================================
if ($method === 'POST' && preg_match('#^academic/groups$#', $path)) {
    $body = REQUEST_BODY;
    $name       = sanitize($body['name']       ?? '');
    $description= sanitize($body['description']?? '');
    $type       = sanitize($body['type']       ?? 'general');
    $nrc        = sanitize($body['nrc_code']   ?? '');
    $icon       = sanitize($body['icon']       ?? '👥');

    if (!$name) Response::error('El nombre del grupo es requerido.', 422);

    $db = getDB_academic();
    $stmt = $db->prepare("
        INSERT INTO groups_table (name, description, type, nrc_code, faculty_id, icon, created_by)
        VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$name, $description, $type, $nrc ?: null, $user['faculty_id'], $icon, $user['user_id']]);
    $gid = (int)$db->lastInsertId();

    // Creator becomes admin
    $db->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?,?,'admin')")
       ->execute([$gid, $user['user_id']]);

    Response::success(['group_id' => $gid], 'Grupo creado.', 201);
}

// ============================================================
//  GET /academic/events
// ============================================================
if ($method === 'GET' && preg_match('#^academic/events$#', $path)) {
    ['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPagination();
    $upcoming   = (bool)($_GET['upcoming']    ?? false);
    $faculty_id = (int)($_GET['faculty_id']  ?? $user['faculty_id'] ?? 0);

    $db    = getDB_academic();
    $where = ['e.is_public = 1'];
    $params = [];

    if ($upcoming)   { $where[] = 'e.event_date >= NOW()'; }
    if ($faculty_id) { $where[] = '(e.faculty_id = ? OR e.faculty_id IS NULL)'; $params[] = $faculty_id; }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT e.*, CONCAT(u.first_name,' ',u.last_name) AS organizer_name
        FROM academic_calendar e
        JOIN users u ON u.user_id = e.created_by
        WHERE $whereSQL
        ORDER BY e.event_date ASC
        LIMIT ? OFFSET ?");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM academic_calendar e WHERE $whereSQL");
    $countStmt->execute(array_slice($params, 0, -2));
    $total = (int)$countStmt->fetchColumn();

    Response::paginated($events, $total, $page, $limit);
}

// ============================================================
//  POST /academic/events
// ============================================================
if ($method === 'POST' && preg_match('#^academic/events$#', $path)) {
    $body = REQUEST_BODY;
    $required = ['title', 'event_date'];
    foreach ($required as $f) {
        if (empty($body[$f])) Response::error("$f es requerido.", 422);
    }

    // Only professors, staff and admins can create institutional events
    $allowed_roles = ['professor','staff','admin','moderator'];
    if (!in_array($user['role'], $allowed_roles)) {
        // Students can only create posts-as-events, not calendar entries
        Response::error('Solo profesores y personal pueden crear eventos en el calendario.', 403);
    }

    $db = getDB_academic();
    $stmt = $db->prepare("
        INSERT INTO academic_calendar
          (title, description, event_date, end_date, location, type, faculty_id, created_by, is_public)
        VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        sanitize($body['title']),
        sanitize($body['description'] ?? ''),
        $body['event_date'],
        $body['end_date'] ?? null,
        sanitize($body['location'] ?? ''),
        sanitize($body['type'] ?? 'otro'),
        $user['faculty_id'] ?? null,
        $user['user_id'],
        (bool)($body['is_public'] ?? true),
    ]);

    Response::success(['event_id' => (int)$db->lastInsertId()], 'Evento creado.', 201);
}

// ============================================================
//  DB helper
// ============================================================
function getDB_academic(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    // For XAMPP: all tables are in red_social (single DB).
    // For Docker multi-service: change to red_social
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
