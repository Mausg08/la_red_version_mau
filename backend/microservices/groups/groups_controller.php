<?php
/**
 * Microservicio de grupos: Mis Grupos, exploracion, detalle y publicaciones.
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';

$method = REQUEST_METHOD;
$path = REQUEST_PATH;
$user = CURRENT_USER;

if ($path === 'academic/my-groups') {
    $path = 'groups/my-groups';
} elseif (str_starts_with($path, 'academic/groups')) {
    $path = 'groups' . substr($path, strlen('academic/groups'));
}

if ($method === 'GET' && $path === 'groups/my-groups') {
    $limit = min(20, (int)($_GET['limit'] ?? 10));
    $db = groupsDB();

    $stmt = $db->prepare("
        SELECT g.group_id, g.name, g.type, g.icon, g.nrc_code,
               g.member_count, gm.role AS member_role,
               0 AS unread
        FROM groups_table g
        JOIN group_members gm ON gm.group_id = g.group_id AND gm.user_id = ?
        ORDER BY g.name
        LIMIT ?");
    $stmt->execute([$user['user_id'], $limit]);

    Response::success(['groups' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'GET' && $path === 'groups') {
    ['page' => $page, 'limit' => $limit, 'offset' => $offset] = getPagination();
    $q = sanitize($_GET['q'] ?? '');
    $type = sanitize($_GET['type'] ?? '');
    $facultyId = (int)($_GET['faculty_id'] ?? 0);
    $joinedOnly = (bool)($_GET['joined'] ?? false);

    $db = groupsDB();
    $where = ['1=1'];
    $params = [];

    if ($q) {
        $where[] = '(g.name LIKE ? OR g.nrc_code LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($type) {
        $where[] = 'g.type = ?';
        $params[] = $type;
    }
    if ($facultyId) {
        $where[] = 'g.faculty_id = ?';
        $params[] = $facultyId;
    }
    if ($joinedOnly) {
        $where[] = 'EXISTS (SELECT 1 FROM group_members gm2 WHERE gm2.group_id=g.group_id AND gm2.user_id=?)';
        $params[] = $user['user_id'];
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT g.*, f.name AS faculty_name,
               EXISTS (SELECT 1 FROM group_members gm WHERE gm.group_id=g.group_id AND gm.user_id=?) AS is_member
        FROM groups_table g
        LEFT JOIN faculties f ON f.faculty_id = g.faculty_id
        WHERE $whereSQL
        ORDER BY g.member_count DESC
        LIMIT ? OFFSET ?");

    $queryParams = $params;
    array_unshift($queryParams, $user['user_id']);
    $queryParams[] = $limit;
    $queryParams[] = $offset;
    $stmt->execute($queryParams);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM groups_table g WHERE $whereSQL");
    $countStmt->execute($params);

    Response::paginated($stmt->fetchAll(PDO::FETCH_ASSOC), (int)$countStmt->fetchColumn(), $page, $limit);
}

if ($method === 'GET' && preg_match('#^groups/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    $db = groupsDB();

    $stmt = $db->prepare("
        SELECT g.*, f.name AS faculty_name,
               EXISTS (SELECT 1 FROM group_members gm WHERE gm.group_id=g.group_id AND gm.user_id=?) AS is_member,
               (SELECT role FROM group_members WHERE group_id=g.group_id AND user_id=?) AS my_role
        FROM groups_table g
        LEFT JOIN faculties f ON f.faculty_id = g.faculty_id
        WHERE g.group_id = ?");
    $stmt->execute([$user['user_id'], $user['user_id'], $id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        Response::error('Grupo no encontrado.', 404);
    }

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

if ($method === 'GET' && preg_match('#^groups/(\d+)/members$#', $path, $m)) {
    $stmt = groupsDB()->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.avatar, gm.role, gm.joined_at
        FROM group_members gm
        JOIN users u ON u.user_id = gm.user_id
        WHERE gm.group_id = ?
        ORDER BY gm.joined_at DESC");
    $stmt->execute([(int)$m[1]]);
    Response::success(['members' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'GET' && preg_match('#^groups/(\d+)/posts$#', $path, $m)) {
    $limit = min(50, (int)($_GET['limit'] ?? 20));
    $stmt = groupsDB()->prepare("
        SELECT p.post_id, p.content, p.created_at,
               u.user_id, u.first_name, u.last_name, u.avatar
        FROM group_posts p
        JOIN users u ON u.user_id = p.user_id
        WHERE p.group_id = ?
        ORDER BY p.created_at DESC
        LIMIT ?");
    $stmt->execute([(int)$m[1], $limit]);
    Response::success(['posts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method === 'POST' && preg_match('#^groups/(\d+)/posts$#', $path, $m)) {
    $id = (int)$m[1];
    $content = sanitize(REQUEST_BODY['content'] ?? '');
    if (!$content) {
        Response::error('El contenido no puede estar vacio.', 422);
    }

    $db = groupsDB();
    $check = $db->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
    $check->execute([$id, $user['user_id']]);
    if (!$check->fetchColumn()) {
        Response::error('Debes ser miembro del grupo para publicar.', 403);
    }

    $stmt = $db->prepare("INSERT INTO group_posts (group_id, user_id, content) VALUES (?,?,?)");
    $stmt->execute([$id, $user['user_id'], $content]);
    Response::success(['post_id' => (int)$db->lastInsertId()], 'Publicacion creada.', 201);
}

if ($method === 'POST' && preg_match('#^groups/(\d+)/join$#', $path, $m)) {
    $id = (int)$m[1];
    $db = groupsDB();

    $check = $db->prepare("SELECT 1 FROM group_members WHERE group_id=? AND user_id=?");
    $check->execute([$id, $user['user_id']]);
    if ($check->fetchColumn()) {
        Response::error('Ya eres miembro de este grupo.', 409);
    }

    $db->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?,?)")->execute([$id, $user['user_id']]);
    $db->prepare("UPDATE groups_table SET member_count = member_count + 1 WHERE group_id=?")->execute([$id]);
    Response::success(null, 'Te has unido al grupo.', 201);
}

if ($method === 'DELETE' && preg_match('#^groups/(\d+)/leave$#', $path, $m)) {
    $id = (int)$m[1];
    $db = groupsDB();

    $stmt = $db->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=? AND role != 'admin'");
    $stmt->execute([$id, $user['user_id']]);
    if (!$stmt->rowCount()) {
        Response::error('No puedes salir de este grupo.', 403);
    }

    $db->prepare("UPDATE groups_table SET member_count = GREATEST(0, member_count - 1) WHERE group_id=?")->execute([$id]);
    Response::success(null, 'Saliste del grupo.');
}

if ($method === 'POST' && $path === 'groups') {
    $body = REQUEST_BODY;
    $name = sanitize($body['name'] ?? '');
    $description = sanitize($body['description'] ?? '');
    $type = sanitize($body['type'] ?? 'general');
    $nrc = sanitize($body['nrc_code'] ?? '');
    $icon = sanitize($body['icon'] ?? '?');

    if (!$name) {
        Response::error('El nombre del grupo es requerido.', 422);
    }

    $db = groupsDB();
    $stmt = $db->prepare("
        INSERT INTO groups_table (name, description, type, nrc_code, faculty_id, icon, created_by)
        VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$name, $description, $type, $nrc ?: null, $user['faculty_id'] ?? null, $icon, $user['user_id']]);
    $groupId = (int)$db->lastInsertId();

    $db->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?,?,'admin')")
        ->execute([$groupId, $user['user_id']]);

    Response::success(['group_id' => $groupId], 'Grupo creado.', 201);
}

Response::error('Ruta de grupos no encontrada.', 404);

function groupsDB(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = getDBConnection();
    return $pdo;
}
