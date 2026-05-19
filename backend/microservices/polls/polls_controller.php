<?php
/**
 * Microservicio de encuestas.
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';

$method = REQUEST_METHOD;
$path = REQUEST_PATH;
$user = CURRENT_USER;

if (str_starts_with($path, 'academic/polls')) {
    $path = 'polls' . substr($path, strlen('academic/polls'));
}

if ($method === 'GET' && $path === 'polls') {
    ['page' => $page, 'limit' => $limit, 'offset' => $offset] = getPagination();
    $category = sanitize($_GET['category'] ?? '');
    $status = sanitize($_GET['status'] ?? '');

    $db = pollsDB();
    $where = ["(
        p.audience = 'public'
        OR (p.audience = 'faculty' AND p.faculty_id = ?)
        OR (
            p.audience = 'group'
            AND EXISTS (
                SELECT 1 FROM group_members gm
                WHERE gm.group_id = p.group_id AND gm.user_id = ?
            )
        )
        OR p.creator_id = ?
    )"];
    $params = [$user['faculty_id'] ?? 0, $user['user_id'], $user['user_id']];

    if ($category) {
        $where[] = 'p.category = ?';
        $params[] = $category;
    }
    if ($status) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT p.*, CONCAT(u.first_name,' ',u.last_name) AS creator_name,
               g.name AS group_name, f.name AS faculty_name,
               EXISTS (SELECT 1 FROM poll_votes pv WHERE pv.poll_id = p.poll_id AND pv.user_id = ?) AS user_voted
        FROM polls p
        JOIN users u ON u.user_id = p.creator_id
        LEFT JOIN groups_table g ON g.group_id = p.group_id
        LEFT JOIN faculties f ON f.faculty_id = p.faculty_id
        WHERE $whereSQL
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?");
    $stmt->execute([$user['user_id'], ...$params, $limit, $offset]);
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    attachPollOptions($db, $polls);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM polls p WHERE $whereSQL");
    $countStmt->execute($params);

    Response::paginated($polls, (int)$countStmt->fetchColumn(), $page, $limit);
}

if ($method === 'POST' && $path === 'polls') {
    $body = REQUEST_BODY;
    $title = sanitize($body['title'] ?? '');
    $description = sanitize($body['description'] ?? '');
    $pollType = sanitize($body['poll_type'] ?? 'options');
    $category = sanitize($body['category'] ?? 'general');
    $audience = sanitize($body['audience'] ?? 'public');
    $groupId = (int)($body['group_id'] ?? 0);
    $options = $body['options'] ?? [];

    if (!$title) {
        Response::error('La pregunta es requerida.', 422);
    }
    if (!in_array($pollType, ['options', 'rating', 'yesno'], true)) {
        Response::error('Tipo de encuesta invalido.', 422);
    }
    if (!in_array($audience, ['public', 'faculty', 'group'], true)) {
        Response::error('Alcance de encuesta invalido.', 422);
    }
    if ($pollType === 'options' && count($options) < 2) {
        Response::error('Agrega al menos 2 opciones.', 422);
    }
    if ($audience === 'group') {
        if (!$groupId) {
            Response::error('Selecciona un grupo para la encuesta.', 422);
        }
        $member = pollsDB()->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
        $member->execute([$groupId, $user['user_id']]);
        if (!$member->fetchColumn()) {
            Response::error('Solo puedes publicar encuestas en grupos donde eres miembro.', 403);
        }
    }

    $db = pollsDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO polls (creator_id, faculty_id, group_id, audience, title, description, poll_type, category, closes_at)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $user['user_id'],
            $audience === 'faculty' ? ($user['faculty_id'] ?? null) : null,
            $audience === 'group' ? $groupId : null,
            $audience,
            $title,
            $description,
            $pollType,
            $category,
            $body['closes_at'] ?? null,
        ]);
        $pollId = (int)$db->lastInsertId();

        if ($pollType === 'yesno') {
            $options = ['Si', 'No'];
        }

        if ($pollType !== 'rating') {
            $optStmt = $db->prepare("INSERT INTO poll_options (poll_id, text, sort_order) VALUES (?,?,?)");
            foreach (array_slice($options, 0, 8) as $index => $option) {
                $text = sanitize((string)$option);
                if ($text !== '') {
                    $optStmt->execute([$pollId, $text, $index]);
                }
            }
        }

        $db->commit();
        Response::success(['poll_id' => $pollId], 'Encuesta publicada.', 201);
    } catch (Throwable $e) {
        $db->rollBack();
        Response::error('Error al publicar encuesta.', 500);
    }
}

if ($method === 'POST' && preg_match('#^polls/(\d+)/vote$#', $path, $m)) {
    $pollId = (int)$m[1];
    $body = REQUEST_BODY;
    $vote = $body['vote'] ?? null;
    $type = sanitize($body['type'] ?? '');
    $db = pollsDB();

    $pollStmt = $db->prepare("SELECT * FROM polls WHERE poll_id = ? AND status = 'active'");
    $pollStmt->execute([$pollId]);
    $poll = $pollStmt->fetch(PDO::FETCH_ASSOC);
    if (!$poll) {
        Response::error('Encuesta no encontrada o cerrada.', 404);
    }

    if ($type === '') {
        $type = $poll['poll_type'];
    }

    $already = $db->prepare("SELECT 1 FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $already->execute([$pollId, $user['user_id']]);
    if ($already->fetchColumn()) {
        Response::error('Ya votaste en esta encuesta.', 409);
    }

    $db->beginTransaction();
    try {
        if ($type === 'rating') {
            $rating = min(5, max(1, (int)$vote));
            $db->prepare("INSERT INTO poll_votes (poll_id, user_id, rating) VALUES (?,?,?)")
                ->execute([$pollId, $user['user_id'], $rating]);
            $db->prepare("
                UPDATE polls
                SET total_votes = total_votes + 1,
                    avg_rating = (SELECT AVG(rating) FROM poll_votes WHERE poll_id = ?)
                WHERE poll_id = ?")->execute([$pollId, $pollId]);
        } else {
            $optionId = resolvePollOption($db, $pollId, $vote, $type);
            if (!$optionId) {
                Response::error('Opcion invalida.', 422);
            }
            $db->prepare("INSERT INTO poll_votes (poll_id, user_id, option_id) VALUES (?,?,?)")
                ->execute([$pollId, $user['user_id'], $optionId]);
            $db->prepare("UPDATE poll_options SET votes = votes + 1 WHERE option_id = ?")
                ->execute([$optionId]);
            $db->prepare("UPDATE polls SET total_votes = total_votes + 1 WHERE poll_id = ?")
                ->execute([$pollId]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        Response::error('Error al registrar voto.', 500);
    }

    $fresh = fetchPoll($db, $pollId, $user['user_id']);
    Response::success(['poll' => $fresh], 'Voto registrado.', 201);
}

Response::error('Ruta de encuestas no encontrada.', 404);

function pollsDB(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = getDBConnection();
    ensurePollScopeColumns($pdo);
    return $pdo;
}

function ensurePollScopeColumns(PDO $db): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $stmt = $db->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'polls'
          AND COLUMN_NAME IN ('group_id', 'audience')");
    $stmt->execute();
    $columns = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!isset($columns['group_id'])) {
        $db->exec("ALTER TABLE polls ADD COLUMN group_id INT UNSIGNED NULL AFTER faculty_id");
    }
    if (!isset($columns['audience'])) {
        $db->exec("ALTER TABLE polls ADD COLUMN audience ENUM('public','faculty','group') DEFAULT 'public' AFTER group_id");
    }
}

function attachPollOptions(PDO $db, array &$polls): void {
    if (!$polls) {
        return;
    }
    $ids = array_column($polls, 'poll_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT option_id, poll_id, text, votes FROM poll_options WHERE poll_id IN ($placeholders) ORDER BY sort_order");
    $stmt->execute($ids);
    $byPoll = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $option) {
        $byPoll[$option['poll_id']][] = $option;
    }
    foreach ($polls as &$poll) {
        $poll['options'] = $byPoll[$poll['poll_id']] ?? [];
    }
}

function fetchPoll(PDO $db, int $pollId, int $userId): array {
    $stmt = $db->prepare("
        SELECT p.*, CONCAT(u.first_name,' ',u.last_name) AS creator_name,
               g.name AS group_name, f.name AS faculty_name,
               EXISTS (SELECT 1 FROM poll_votes pv WHERE pv.poll_id = p.poll_id AND pv.user_id = ?) AS user_voted
        FROM polls p
        JOIN users u ON u.user_id = p.creator_id
        LEFT JOIN groups_table g ON g.group_id = p.group_id
        LEFT JOIN faculties f ON f.faculty_id = p.faculty_id
        WHERE p.poll_id = ?");
    $stmt->execute([$userId, $pollId]);
    $polls = [$stmt->fetch(PDO::FETCH_ASSOC)];
    attachPollOptions($db, $polls);
    return $polls[0];
}

function resolvePollOption(PDO $db, int $pollId, $vote, string $type): ?int {
    if ($type === 'yesno') {
        $index = $vote === 'no' ? 1 : 0;
        $stmt = $db->prepare("SELECT option_id FROM poll_options WHERE poll_id = ? ORDER BY sort_order LIMIT 1 OFFSET $index");
        $stmt->execute([$pollId]);
        $found = $stmt->fetchColumn();
        return $found ? (int)$found : null;
    }

    $optionId = (int)$vote;
    $stmt = $db->prepare("SELECT option_id FROM poll_options WHERE poll_id = ? AND option_id = ?");
    $stmt->execute([$pollId, $optionId]);
    $found = $stmt->fetchColumn();
    return $found ? (int)$found : null;
}
