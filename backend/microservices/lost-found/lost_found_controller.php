<?php
/**
 * Microservicio de objetos perdidos y encontrados.
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';

$method = REQUEST_METHOD;
$path = REQUEST_PATH;
$user = CURRENT_USER;

if ($method === 'GET' && $path === 'lost-found/items') {
    ['page' => $page, 'limit' => $limit, 'offset' => $offset] = getPagination();
    $lostType = sanitize($_GET['lost_type'] ?? '');
    $status = sanitize($_GET['status'] ?? 'active');
    $q = sanitize($_GET['q'] ?? '');

    $db = lostFoundDB();
    $where = ['l.is_lost_found = 1'];
    $params = [];

    if ($status) {
        $where[] = 'l.status = ?';
        $params[] = $status;
    }
    if ($lostType) {
        $where[] = 'l.lost_type = ?';
        $params[] = $lostType;
    }
    if ($q) {
        $where[] = '(l.title LIKE ? OR l.description LIKE ? OR l.lost_location LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    $whereSQL = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS seller_name,
               (SELECT url FROM listing_images WHERE listing_id=l.listing_id ORDER BY sort_order LIMIT 1) AS thumbnail
        FROM listings l
        JOIN users u ON l.seller_id = u.user_id
        WHERE $whereSQL
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?");
    $stmt->execute([...$params, $limit, $offset]);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM listings l WHERE $whereSQL");
    $countStmt->execute($params);

    Response::paginated($stmt->fetchAll(PDO::FETCH_ASSOC), (int)$countStmt->fetchColumn(), $page, $limit);
}

if ($method === 'POST' && $path === 'lost-found/items') {
    $title = sanitize($_POST['title'] ?? REQUEST_BODY['title'] ?? '');
    $description = sanitize($_POST['description'] ?? REQUEST_BODY['description'] ?? '');
    $lostType = sanitize($_POST['lost_type'] ?? REQUEST_BODY['lost_type'] ?? '');
    $lostLocation = sanitize($_POST['lost_location'] ?? REQUEST_BODY['lost_location'] ?? '');

    if (!$title) {
        Response::error('La descripcion del objeto es requerida.', 422);
    }
    if (!in_array($lostType, ['lost', 'found'], true)) {
        Response::error('Tipo de reporte invalido.', 422);
    }
    if (!$lostLocation) {
        Response::error('El lugar es requerido.', 422);
    }

    $db = lostFoundDB();
    $stmt = $db->prepare("
        INSERT INTO listings
            (seller_id, title, description, price, category, condition_val, faculty_id, is_lost_found, lost_type, lost_location)
        VALUES (?, ?, ?, 0, 'otros', 'buen_estado', ?, 1, ?, ?)");
    $stmt->execute([
        $user['user_id'],
        $title,
        $description,
        $user['faculty_id'] ?? null,
        $lostType,
        $lostLocation,
    ]);
    $listingId = (int)$db->lastInsertId();

    if (!empty($_FILES['images'])) {
        $files = normalizeLostFoundFiles($_FILES['images']);
        $imgStmt = $db->prepare("INSERT INTO listing_images (listing_id, url, sort_order) VALUES (?,?,?)");
        foreach (array_slice($files, 0, 4) as $index => $file) {
            $url = uploadFile($file, 'lost-found');
            if ($url) {
                $imgStmt->execute([$listingId, $url, $index]);
            }
        }
    }

    Response::success(['item_id' => $listingId], 'Reporte publicado.', 201);
}

if ($method === 'PATCH' && preg_match('#^lost-found/items/(\d+)/resolved$#', $path, $m)) {
    $stmt = lostFoundDB()->prepare("
        UPDATE listings
        SET status = 'removed'
        WHERE listing_id = ? AND seller_id = ? AND is_lost_found = 1");
    $stmt->execute([(int)$m[1], $user['user_id']]);
    if (!$stmt->rowCount()) {
        Response::error('No tienes permiso para cerrar este reporte.', 403);
    }
    Response::success(null, 'Reporte cerrado.');
}

Response::error('Ruta de objetos perdidos no encontrada.', 404);

function lostFoundDB(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = getDBConnection();
    return $pdo;
}

function normalizeLostFoundFiles(array $files): array {
    $result = [];
    if (is_array($files['name'])) {
        foreach ($files['name'] as $index => $name) {
            $result[] = [
                'name' => $name,
                'type' => $files['type'][$index],
                'tmp_name' => $files['tmp_name'][$index],
                'error' => $files['error'][$index],
                'size' => $files['size'][$index],
            ];
        }
    } else {
        $result[] = $files;
    }
    return array_filter($result, fn($file) => $file['error'] === UPLOAD_ERR_OK);
}
