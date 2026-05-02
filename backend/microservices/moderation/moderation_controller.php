<?php
/**
 * UniLink — moderation/moderation_controller.php
 * Reportes, acciones, alertas de pánico, log
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';

$method = REQUEST_METHOD;
$path   = REQUEST_PATH;
$user   = CURRENT_USER;

// Moderators and admins only (except POST reports and POST panic — any user)
$mod_only_routes = ['moderation/reports', 'moderation/actions', 'moderation/flagged', 'moderation/stats', 'moderation/log'];
$is_mod_route    = array_reduce($mod_only_routes, fn($c, $r) => $c || str_starts_with($path, $r), false);

if ($is_mod_route && !in_array($user['role'], ['admin','moderator'])) {
    Response::error('Acceso restringido a moderadores.', 403);
}

// ============================================================
//  GET /moderation/stats
// ============================================================
if ($method === 'GET' && str_contains($path, 'moderation/stats')) {
    $db = getDB_mod();

    $facultyFilter = '';
    $params        = [];
    if ($user['role'] === 'moderator') {
        $facultyFilter = 'AND r.faculty_id = ?';
        $params[]      = $user['faculty_id'];
    }

    $pending = $db->prepare("SELECT COUNT(*) FROM reports WHERE status='pending' $facultyFilter");
    $pending->execute($params);

    $resolvedToday = $db->prepare("SELECT COUNT(*) FROM reports WHERE status='resolved' AND DATE(reviewed_at)=CURDATE() $facultyFilter");
    $resolvedToday->execute($params);

    $panicActive = $db->prepare("SELECT COUNT(*) FROM panic_alerts WHERE status='active'");
    $panicActive->execute();

    Response::success(['stats' => [
        'pending_reports' => (int)$pending->fetchColumn(),
        'resolved_today'  => (int)$resolvedToday->fetchColumn(),
        'active_panic'    => (int)$panicActive->fetchColumn(),
    ]]);
}

// ============================================================
//  GET /moderation/reports
// ============================================================
if ($method === 'GET' && preg_match('#^moderation/reports$#', $path)) {
    $status    = sanitize($_GET['status'] ?? 'pending');
    $reason    = sanitize($_GET['reason'] ?? '');
    $limit     = min(50, (int)($_GET['limit'] ?? 20));

    $db     = getDB_mod();
    $where  = ['1=1'];
    $params = [];

    if ($status) { $where[] = 'r.status = ?'; $params[] = $status; }
    if ($reason) { $where[] = 'r.reason = ?'; $params[] = $reason; }

    // Moderators only see their faculty's reports
    if ($user['role'] === 'moderator') {
        $where[]  = 'r.faculty_id = ?';
        $params[] = $user['faculty_id'];
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT r.*,
               CONCAT(reporter.first_name,' ',reporter.last_name) AS reporter_name,
               p.content  AS post_content,
               p.created_at AS post_created_at,
               CONCAT(author.first_name,' ',author.last_name) AS author_name
        FROM reports r
        JOIN users reporter ON reporter.user_id = r.reporter_id
        LEFT JOIN posts p ON p.post_id = r.post_id
        LEFT JOIN users author ON author.user_id = p.user_id
        WHERE $whereSQL
        ORDER BY r.created_at DESC
        LIMIT ?");
    $params[] = $limit;
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['reports' => $reports]);
}

// ============================================================
//  POST /moderation/reports  (any authenticated user)
// ============================================================
if ($method === 'POST' && preg_match('#^moderation/reports$#', $path)) {
    $body      = REQUEST_BODY;
    $post_id   = (int)($body['post_id']   ?? 0);
    $reason    = sanitize($body['reason'] ?? '');
    $details   = sanitize($body['details'] ?? '');

    if (!$post_id || !$reason) Response::error('Datos incompletos.', 422);

    // Prevent self-reporting loop
    $db = getDB_mod();
    $post = $db->prepare("SELECT user_id, faculty_id FROM posts WHERE post_id=?");
    $post->execute([$post_id]);
    $postData = $post->fetch();
    if (!$postData) Response::error('Publicación no encontrada.', 404);
    if ($postData['user_id'] === $user['user_id']) Response::error('No puedes reportarte a ti mismo.', 422);

    // Check duplicate report
    $dup = $db->prepare("SELECT 1 FROM reports WHERE reporter_id=? AND post_id=? AND status='pending'");
    $dup->execute([$user['user_id'], $post_id]);
    if ($dup->fetchColumn()) Response::error('Ya tienes un reporte pendiente para esta publicación.', 409);

    $stmt = $db->prepare("
        INSERT INTO reports (reporter_id, post_id, reason, details, faculty_id)
        VALUES (?,?,?,?,?)");
    $stmt->execute([$user['user_id'], $post_id, $reason, $details, $postData['faculty_id']]);

    // Notify moderators via Redis queue
    // MessageQueue::publish('moderation', ['type'=>'new_report', 'faculty_id'=>$postData['faculty_id']]);

    Response::success(null, 'Reporte enviado.', 201);
}

// ============================================================
//  POST /moderation/actions
// ============================================================
if ($method === 'POST' && preg_match('#^moderation/actions$#', $path)) {
    $body      = REQUEST_BODY;
    $action    = sanitize($body['action']    ?? '');
    $report_id = (int)($body['report_id']   ?? 0);
    $target_id = (int)($body['target_id']   ?? 0);
    $reason    = sanitize($body['reason']   ?? '');

    $valid_actions = ['remove_post','warn_user','suspend_user','dismiss','restore_post'];
    if (!in_array($action, $valid_actions)) Response::error('Acción no válida.', 422);

    $db = getDB_mod();

    // Execute action
    switch ($action) {
        case 'remove_post':
            if (!$target_id) Response::error('post_id requerido.', 422);
            $db->prepare("UPDATE posts SET status='removed' WHERE post_id=?")->execute([$target_id]);
            // Log
            logModAction($db, $user['user_id'], 'remove_post', $target_id, 'post', $reason ?: 'Reporte de usuario');
            break;

        case 'warn_user':
            $postAuthor = $db->prepare("SELECT user_id FROM posts WHERE post_id=?");
            $postAuthor->execute([$target_id]);
            $authorId = $postAuthor->fetchColumn();
            if ($authorId) {
                $db->prepare("INSERT INTO notifications (user_id,type,message) VALUES (?,'moderation','Has recibido una advertencia del equipo de moderación.')")
                   ->execute([$authorId]);
            }
            logModAction($db, $user['user_id'], 'warn_user', $authorId, 'user', $reason);
            break;

        case 'suspend_user':
            $postAuthor = $db->prepare("SELECT user_id FROM posts WHERE post_id=?");
            $postAuthor->execute([$target_id]);
            $authorId = $postAuthor->fetchColumn();
            if ($authorId) {
                $db->prepare("UPDATE users SET status='suspended' WHERE user_id=?")->execute([$authorId]);
                logModAction($db, $user['user_id'], 'suspend_user', $authorId, 'user', $reason);
            }
            break;

        case 'dismiss':
            // No action on content, just close the report
            logModAction($db, $user['user_id'], 'dismiss_report', $report_id, 'post', 'Reporte desestimado');
            break;
    }

    // Update report status
    if ($report_id) {
        $db->prepare("UPDATE reports SET status='resolved', moderator_id=?, reviewed_at=NOW() WHERE report_id=?")
           ->execute([$user['user_id'], $report_id]);
    }

    Response::success(null, 'Acción ejecutada y registrada en el log.');
}

// ============================================================
//  GET /moderation/flagged
// ============================================================
if ($method === 'GET' && str_contains($path, 'moderation/flagged')) {
    $limit = min(50, (int)($_GET['limit'] ?? 20));
    $db    = getDB_mod();

    $where  = ["p.status = 'flagged'"];
    $params = [];
    if ($user['role'] === 'moderator') {
        $where[]  = 'p.faculty_id = ?';
        $params[] = $user['faculty_id'];
    }
    $whereSQL = implode(' AND ', $where);
    $params[] = $limit;

    $stmt = $db->prepare("
        SELECT p.post_id, p.content, p.created_at,
               CONCAT(u.first_name,' ',u.last_name) AS author_name
        FROM posts p
        JOIN users u ON u.user_id = p.user_id
        WHERE $whereSQL
        ORDER BY p.created_at DESC
        LIMIT ?");
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['posts' => $posts]);
}

// ============================================================
//  PATCH /moderation/flagged/{id}/approve
// ============================================================
if ($method === 'PATCH' && preg_match('#^moderation/flagged/(\d+)/approve$#', $path, $m)) {
    $db = getDB_mod();
    $db->prepare("UPDATE posts SET status='published' WHERE post_id=?")->execute([(int)$m[1]]);
    logModAction($db, $user['user_id'], 'restore_post', (int)$m[1], 'post', 'Aprobado por moderador');
    Response::success(null, 'Publicación aprobada.');
}

// ============================================================
//  POST /moderation/panic  (any user)
// ============================================================
if ($method === 'POST' && preg_match('#^moderation/panic$#', $path)) {
    $body = REQUEST_BODY;
    $db   = getDB_mod();
    $stmt = $db->prepare("
        INSERT INTO panic_alerts (user_id, latitude, longitude, location_text)
        VALUES (?,?,?,?)");
    $stmt->execute([
        $user['user_id'],
        $body['lat'] ?? null,
        $body['lng'] ?? null,
        sanitize($body['location'] ?? 'desconocida'),
    ]);

    // Notify all active admins/moderators
    // MessageQueue::publish('panic', ['user_id'=>$user['user_id'], 'alert_id'=>$db->lastInsertId()]);

    logEvent('critical', 'PANIC ALERT', [
        'user_id'   => $user['user_id'],
        'timestamp' => date('c'),
        'lat'       => $body['lat'] ?? null,
        'lng'       => $body['lng'] ?? null,
    ]);

    Response::success(null, 'Alerta enviada a seguridad del campus.', 201);
}

// ============================================================
//  GET /moderation/panic
// ============================================================
if ($method === 'GET' && preg_match('#^moderation/panic$#', $path)) {
    if (!in_array($user['role'], ['admin','moderator'])) Response::error('Acceso restringido.', 403);
    $db = getDB_mod();
    $stmt = $db->prepare("
        SELECT pa.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.email
        FROM panic_alerts pa
        JOIN users u ON u.user_id = pa.user_id
        ORDER BY pa.created_at DESC LIMIT 20");
    $stmt->execute();
    Response::success(['alerts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// PATCH /moderation/panic/{id}/acknowledge  &  /resolve
if ($method === 'PATCH' && preg_match('#^moderation/panic/(\d+)/(acknowledge|resolve)$#', $path, $m)) {
    $status = $m[2] === 'acknowledge' ? 'acknowledged' : 'resolved';
    getDB_mod()->prepare("UPDATE panic_alerts SET status=? WHERE alert_id=?")->execute([$status, (int)$m[1]]);
    Response::success(null, 'Alerta actualizada.');
}

// ============================================================
//  GET /moderation/log
// ============================================================
if ($method === 'GET' && str_contains($path, 'moderation/log')) {
    $limit = min(100, (int)($_GET['limit'] ?? 50));
    $db    = getDB_mod();
    $stmt  = $db->prepare("
        SELECT ml.*, CONCAT(u.first_name,' ',u.last_name) AS moderator_name
        FROM moderation_log ml
        LEFT JOIN users u ON u.user_id = ml.moderator_id
        ORDER BY ml.created_at DESC
        LIMIT ?");
    $stmt->execute([$limit]);
    Response::success(['log' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ============================================================
//  Helpers
// ============================================================
function logModAction(PDO $db, int $modId, string $action, ?int $targetId, string $targetType, string $reason = ''): void {
    $db->prepare("INSERT INTO moderation_log (moderator_id, action, target_id, target_type, reason) VALUES (?,?,?,?,?)")
       ->execute([$modId, $action, $targetId, $targetType, $reason]);
}

function getDB_mod(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    // Moderation uses the main DB (can also be MongoDB for logs)
    $dsn = sprintf('mysql:host=%s;dbname=red_social;charset=utf8mb4', DB_HOST);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
