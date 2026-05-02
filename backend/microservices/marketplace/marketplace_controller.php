<?php
/**
 * UniLink - marketplace/marketplace_controller.php
 * CRUD for listings, lost/found items and reviews.
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';

$method = REQUEST_METHOD;
$path   = REQUEST_PATH;
$user   = CURRENT_USER;

// ---- GET /marketplace/listings ----
if ($method === 'GET' && preg_match('#^marketplace/listings$#', $path)) {
    ['page'=>$page,'limit'=>$limit,'offset'=>$offset] = getPagination();

    $category    = sanitize($_GET['category'] ?? '');
    $sort        = sanitize($_GET['sort'] ?? 'recent');
    $search      = sanitize($_GET['search'] ?? ($_GET['q'] ?? ''));
    $condition   = sanitize($_GET['condition'] ?? '');
    $status      = sanitize($_GET['status'] ?? 'active');
    $isLostFound = isset($_GET['is_lost_found']) ? (int)$_GET['is_lost_found'] : null;
    $lostType    = sanitize($_GET['lost_type'] ?? '');
    $priceMin    = (float)($_GET['price_min'] ?? 0);
    $priceMax    = (float)($_GET['price_max'] ?? 0);

    $db     = getDB();
    $where  = ['1=1'];
    $params = [];

    if ($status)      { $where[] = 'l.status = ?';        $params[] = $status; }
    if ($category)    { $where[] = 'l.category = ?';      $params[] = $category; }
    if ($condition)   { $where[] = 'l.condition_val = ?'; $params[] = $condition; }
    if ($isLostFound !== null) { $where[] = 'l.is_lost_found = ?'; $params[] = $isLostFound; }
    if ($lostType)    { $where[] = 'l.lost_type = ?';     $params[] = $lostType; }
    if ($priceMin)    { $where[] = 'l.price >= ?';        $params[] = $priceMin; }
    if ($priceMax)    { $where[] = 'l.price <= ?';        $params[] = $priceMax; }
    if ($search)      { $where[] = '(l.title LIKE ? OR l.description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

    $order = match ($sort) {
        'price_asc'  => 'l.price ASC',
        'price_desc' => 'l.price DESC',
        'popular'    => 'l.views DESC',
        default      => 'l.created_at DESC',
    };

    $whereSQL = implode(' AND ', $where);
    $sql = "
        SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS seller_name,
               AVG(r.rating) AS seller_rating,
               (SELECT url FROM listing_images WHERE listing_id=l.listing_id ORDER BY sort_order LIMIT 1) AS thumbnail
        FROM listings l
        JOIN users u ON l.seller_id = u.user_id
        LEFT JOIN reviews r ON r.seller_id = l.seller_id
        WHERE $whereSQL
        GROUP BY l.listing_id
        ORDER BY $order
        LIMIT ? OFFSET ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([...$params, $limit, $offset]);
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM listings l WHERE $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    Response::paginated($listings, $total, $page, $limit);
}

// ---- GET /marketplace/listings/{id} ----
if ($method === 'GET' && preg_match('#^marketplace/listings/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    $db = getDB();

    $stmt = $db->prepare("
        SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS seller_name,
               AVG(r.rating) AS seller_rating, COUNT(r.review_id) AS reviews_count
        FROM listings l
        JOIN users u ON l.seller_id = u.user_id
        LEFT JOIN reviews r ON r.seller_id = l.seller_id
        WHERE l.listing_id = ?
        GROUP BY l.listing_id");
    $stmt->execute([$id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) Response::error('Anuncio no encontrado.', 404);

    $db->prepare("UPDATE listings SET views = views + 1 WHERE listing_id = ?")->execute([$id]);

    $imgStmt = $db->prepare("SELECT url FROM listing_images WHERE listing_id = ? ORDER BY sort_order");
    $imgStmt->execute([$id]);
    $listing['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['listing' => $listing]);
}

// ---- POST /marketplace/listings ----
if ($method === 'POST' && preg_match('#^marketplace/listings$#', $path)) {
    $title        = sanitize($_POST['title'] ?? REQUEST_BODY['title'] ?? '');
    $description  = sanitize($_POST['description'] ?? REQUEST_BODY['description'] ?? '');
    $price        = (float)($_POST['price'] ?? REQUEST_BODY['price'] ?? 0);
    $category     = sanitize($_POST['category'] ?? REQUEST_BODY['category'] ?? 'otros');
    $condition    = sanitize($_POST['condition_val'] ?? REQUEST_BODY['condition_val'] ?? 'buen_estado');
    $isLostFound  = (int)($_POST['is_lost_found'] ?? REQUEST_BODY['is_lost_found'] ?? 0);
    $lostType     = sanitize($_POST['lost_type'] ?? REQUEST_BODY['lost_type'] ?? '');
    $lostLocation = sanitize($_POST['lost_location'] ?? REQUEST_BODY['lost_location'] ?? '');

    if (!$title) Response::error('El titulo es requerido.', 422);
    if ($price < 0) Response::error('El precio no puede ser negativo.', 422);
    if ($isLostFound && !in_array($lostType, ['lost', 'found'], true)) Response::error('Tipo de reporte invalido.', 422);
    if ($isLostFound && !$lostLocation) Response::error('El lugar es requerido.', 422);

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO listings
            (seller_id, title, description, price, category, condition_val, faculty_id, is_lost_found, lost_type, lost_location)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user['user_id'],
        $title,
        $description,
        $price,
        $category,
        $condition,
        $user['faculty_id'] ?? null,
        $isLostFound,
        $isLostFound ? $lostType : null,
        $isLostFound ? $lostLocation : null,
    ]);
    $listingId = (int)$db->lastInsertId();

    if (!empty($_FILES['images'])) {
        $files = normalizeListingFiles($_FILES['images']);
        $imgStmt = $db->prepare("INSERT INTO listing_images (listing_id, url, sort_order) VALUES (?,?,?)");
        foreach (array_slice($files, 0, 4) as $i => $file) {
            $url = uploadFile($file, 'marketplace');
            if ($url) $imgStmt->execute([$listingId, $url, $i]);
        }
    }

    Response::success(['listing_id' => $listingId], 'Anuncio publicado.', 201);
}

// ---- PATCH /marketplace/listings/{id}/sold ----
if ($method === 'PATCH' && preg_match('#^marketplace/listings/(\d+)/sold$#', $path, $m)) {
    $id = (int)$m[1];
    $stmt = getDB()->prepare("UPDATE listings SET status='sold' WHERE listing_id=? AND seller_id=?");
    $stmt->execute([$id, $user['user_id']]);
    if (!$stmt->rowCount()) Response::error('No tienes permiso.', 403);
    Response::success(null, 'Marcado como vendido.');
}

// ---- DELETE /marketplace/listings/{id} ----
if ($method === 'DELETE' && preg_match('#^marketplace/listings/(\d+)$#', $path, $m)) {
    $id    = (int)$m[1];
    $db    = getDB();
    $isMod = in_array($user['role'], ['admin','moderator'], true);
    $sql   = $isMod
        ? "UPDATE listings SET status='removed' WHERE listing_id=?"
        : "UPDATE listings SET status='removed' WHERE listing_id=? AND seller_id=?";
    $params = $isMod ? [$id] : [$id, $user['user_id']];

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if (!$stmt->rowCount()) Response::error('No tienes permiso.', 403);
    Response::success(null, 'Anuncio eliminado.');
}

// ---- POST /marketplace/reviews ----
if ($method === 'POST' && preg_match('#^marketplace/reviews$#', $path)) {
    $body      = REQUEST_BODY;
    $listingId = (int)($body['listing_id'] ?? 0);
    $sellerId  = (int)($body['seller_id'] ?? 0);
    $rating    = min(5, max(1, (int)($body['rating'] ?? 0)));
    $comment   = sanitize($body['comment'] ?? '');

    if (!$listingId || !$rating) Response::error('Datos incompletos.', 422);
    if ($sellerId === $user['user_id']) Response::error('No puedes resenarte a ti mismo.', 422);

    $db = getDB();
    try {
        $stmt = $db->prepare("INSERT INTO reviews (listing_id, reviewer_id, seller_id, rating, comment) VALUES (?,?,?,?,?)");
        $stmt->execute([$listingId, $user['user_id'], $sellerId, $rating, $comment]);
        $db->prepare("UPDATE users SET reputation_score = (SELECT COALESCE(AVG(rating)*20,0) FROM reviews WHERE seller_id=?) WHERE user_id=?")
           ->execute([$sellerId, $sellerId]);
        Response::success(null, 'Resena publicada.', 201);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'unique_review')) {
            Response::error('Ya dejaste una resena para este anuncio.', 409);
        }
        Response::error('Error al publicar resena.', 500);
    }
}

function getDB(string $service = ''): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = getDBConnection();
    return $pdo;
}

function normalizeListingFiles(array $files): array {
    $result = [];
    if (is_array($files['name'])) {
        foreach ($files['name'] as $i => $name) {
            $result[] = [
                'name'     => $name,
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
    } else {
        $result[] = $files;
    }
    return array_filter($result, fn($f) => $f['error'] === UPLOAD_ERR_OK);
}
