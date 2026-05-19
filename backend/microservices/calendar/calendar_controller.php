<?php
/**
 * Microservicio de calendario academico.
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';

$method = REQUEST_METHOD;
$path = REQUEST_PATH;
$user = CURRENT_USER;

if (str_starts_with($path, 'academic/events')) {
    $path = 'calendar/events' . substr($path, strlen('academic/events'));
}

if ($method === 'GET' && $path === 'calendar/events') {
    ['page' => $page, 'limit' => $limit, 'offset' => $offset] = getPagination();
    $upcoming = (bool)($_GET['upcoming'] ?? false);
    $facultyId = (int)($_GET['faculty_id'] ?? $user['faculty_id'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    $month = (int)($_GET['month'] ?? 0);

    $db = calendarDB();
    $where = ['e.is_public = 1'];
    $params = [];

    if ($upcoming) {
        $where[] = 'e.event_date >= NOW()';
    }
    if ($facultyId) {
        $where[] = '(e.faculty_id = ? OR e.faculty_id IS NULL)';
        $params[] = $facultyId;
    }
    if ($year && $month) {
        $where[] = 'YEAR(e.event_date) = ? AND MONTH(e.event_date) = ?';
        $params[] = $year;
        $params[] = $month;
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT e.*, CONCAT(u.first_name,' ',u.last_name) AS organizer_name, f.name AS faculty_name
        FROM academic_calendar e
        JOIN users u ON u.user_id = e.created_by
        LEFT JOIN faculties f ON f.faculty_id = e.faculty_id
        WHERE $whereSQL
        ORDER BY e.event_date ASC
        LIMIT ? OFFSET ?");
    $stmt->execute([...$params, $limit, $offset]);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM academic_calendar e WHERE $whereSQL");
    $countStmt->execute($params);

    Response::paginated($stmt->fetchAll(PDO::FETCH_ASSOC), (int)$countStmt->fetchColumn(), $page, $limit);
}

if ($method === 'GET' && preg_match('#^calendar/events/(\d+)$#', $path, $m)) {
    $stmt = calendarDB()->prepare("
        SELECT e.*, CONCAT(u.first_name,' ',u.last_name) AS organizer_name, f.name AS faculty_name
        FROM academic_calendar e
        JOIN users u ON u.user_id = e.created_by
        LEFT JOIN faculties f ON f.faculty_id = e.faculty_id
        WHERE e.event_id = ? AND e.is_public = 1");
    $stmt->execute([(int)$m[1]]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        Response::error('Evento no encontrado.', 404);
    }
    Response::success($event);
}

if ($method === 'POST' && $path === 'calendar/events') {
    $body = REQUEST_BODY;
    foreach (['title', 'event_date'] as $field) {
        if (empty($body[$field])) {
            Response::error("$field es requerido.", 422);
        }
    }

    $allowedRoles = ['professor', 'staff', 'admin', 'moderator'];
    if (!in_array($user['role'], $allowedRoles, true)) {
        Response::error('Solo profesores y personal pueden crear eventos en el calendario.', 403);
    }

    $stmt = calendarDB()->prepare("
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

    Response::success(['event_id' => (int)calendarDB()->lastInsertId()], 'Evento creado.', 201);
}

Response::error('Ruta de calendario no encontrada.', 404);

function calendarDB(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = getDBConnection();
    return $pdo;
}
