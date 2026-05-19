<?php
/**
 * Microservicio de directorio universitario.
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';

$method = REQUEST_METHOD;
$path = REQUEST_PATH;
$user = CURRENT_USER;

if ($path === 'users/directory') {
    $path = 'directory/users';
}

if ($method === 'GET' && $path === 'directory/users') {
    ['page' => $page, 'limit' => $limit, 'offset' => $offset] = getPagination();
    $facultyId = (int)($_GET['faculty_id'] ?? 0);
    $careerId = (int)($_GET['career_id'] ?? 0);
    $q = sanitize($_GET['q'] ?? '');

    $db = directoryDB();
    $where = ["u.status = 'active'"];
    $params = [];

    if ($q) {
        $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.student_id LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($facultyId) {
        $where[] = 'u.faculty_id = ?';
        $params[] = $facultyId;
    }
    if ($careerId) {
        $where[] = 'u.career_id = ?';
        $params[] = $careerId;
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT u.user_id,
               CONCAT(u.first_name,' ',u.last_name) AS name,
               u.student_id, u.semester, u.avatar, u.role, u.bio,
               f.name AS faculty_name, c.name AS career_name,
               CASE WHEN u.show_phone = 1 AND u.user_id != ? THEN u.phone ELSE NULL END AS phone,
               EXISTS (
                   SELECT 1 FROM user_follows uf
                   WHERE uf.follower_id = ? AND uf.following_id = u.user_id
               ) AS is_contact,
               u.email AS email
        FROM users u
        LEFT JOIN faculties f ON f.faculty_id = u.faculty_id
        LEFT JOIN careers c ON c.career_id = u.career_id
        WHERE $whereSQL
        ORDER BY u.first_name, u.last_name
        LIMIT ? OFFSET ?");
    $stmt->execute([$user['user_id'], $user['user_id'], ...$params, $limit, $offset]);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
    $countStmt->execute($params);

    Response::paginated($stmt->fetchAll(PDO::FETCH_ASSOC), (int)$countStmt->fetchColumn(), $page, $limit);
}

if ($method === 'GET' && $path === 'directory/contacts') {
    ['page' => $page, 'limit' => $limit, 'offset' => $offset] = getPagination();
    $db = directoryDB();

    $stmt = $db->prepare("
        SELECT u.user_id,
               CONCAT(u.first_name,' ',u.last_name) AS name,
               u.student_id, u.semester, u.avatar, u.role, u.bio,
               f.name AS faculty_name, c.name AS career_name,
               CASE WHEN u.show_phone = 1 THEN u.phone ELSE NULL END AS phone,
               u.email,
               uf.created_at AS contact_since,
               1 AS is_contact
        FROM user_follows uf
        JOIN users u ON u.user_id = uf.following_id AND u.status = 'active'
        LEFT JOIN faculties f ON f.faculty_id = u.faculty_id
        LEFT JOIN careers c ON c.career_id = u.career_id
        WHERE uf.follower_id = ?
        ORDER BY uf.created_at DESC
        LIMIT ? OFFSET ?");
    $stmt->execute([$user['user_id'], $limit, $offset]);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
    $countStmt->execute([$user['user_id']]);

    Response::paginated($stmt->fetchAll(PDO::FETCH_ASSOC), (int)$countStmt->fetchColumn(), $page, $limit);
}

if ($method === 'POST' && preg_match('#^directory/contacts/(\d+)$#', $path, $m)) {
    $contactId = (int)$m[1];
    if ($contactId === (int)$user['user_id']) {
        Response::error('No puedes agregarte a ti mismo.', 422);
    }

    $db = directoryDB();
    $exists = $db->prepare("SELECT 1 FROM users WHERE user_id = ? AND status = 'active'");
    $exists->execute([$contactId]);
    if (!$exists->fetchColumn()) {
        Response::error('Usuario no encontrado.', 404);
    }

    $stmt = $db->prepare("INSERT IGNORE INTO user_follows (follower_id, following_id) VALUES (?, ?)");
    $stmt->execute([$user['user_id'], $contactId]);

    Response::success(null, 'Contacto agregado.', 201);
}

if ($method === 'DELETE' && preg_match('#^directory/contacts/(\d+)$#', $path, $m)) {
    $stmt = directoryDB()->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$user['user_id'], (int)$m[1]]);
    Response::success(null, 'Contacto eliminado.');
}

Response::error('Ruta de directorio no encontrada.', 404);

function directoryDB(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = getDBConnection();
    return $pdo;
}
