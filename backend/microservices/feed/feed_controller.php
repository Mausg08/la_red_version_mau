<?php
/**
 * UniLink — feed/feed_controller.php
 * Feed posts: list, create, like, comment, delete
 */
require_once __DIR__ . '/../../shared/helpers.php';
require_once __DIR__ . '/../../shared/response.php';
require_once __DIR__ . '/feed_model.php';

$method = REQUEST_METHOD;
$path   = REQUEST_PATH;
$user   = CURRENT_USER;

// ---- GET /feed/posts ----
if ($method === 'GET' && preg_match('#^feed/posts$#', $path)) {
    ['page'=>$page, 'limit'=>$limit, 'offset'=>$offset] = getPagination();
    $filter = sanitize($_GET['filter'] ?? 'all');

    $posts = FeedModel::getPosts([
        'user_id'    => $user['user_id'],
        'faculty_id' => $user['faculty_id'],
        'filter'     => $filter,
        'limit'      => $limit,
        'offset'     => $offset,
    ]);

    $total = FeedModel::countPosts([
        'user_id'    => $user['user_id'],
        'faculty_id' => $user['faculty_id'],
        'filter'     => $filter,
    ]);

    Response::paginated($posts, $total, $page, $limit);
}

// ---- POST /feed/posts ---- (Create post)
if ($method === 'POST' && preg_match('#^feed/posts$#', $path)) {
    $content  = sanitize($_POST['content'] ?? REQUEST_BODY['content'] ?? '');
    $audience = sanitize($_POST['audience'] ?? REQUEST_BODY['audience'] ?? 'public');
    $type     = sanitize($_POST['type'] ?? REQUEST_BODY['type'] ?? 'general');
    $tags     = json_decode($_POST['tags'] ?? REQUEST_BODY['tags'] ?? '[]', true) ?: [];

    if (!$content) Response::error('El contenido no puede estar vacío.', 422);
    if (mb_strlen($content) > 2000) Response::error('Máximo 2000 caracteres.', 422);

    // Content moderation: basic keyword filter
    $flagged = ContentFilter::check($content);
    $status  = $flagged ? 'flagged' : 'published';

    // Upload media
    $media_urls = [];
    if (!empty($_FILES['media'])) {
        $files = normalizeFilesArray($_FILES['media']);
        foreach (array_slice($files, 0, 4) as $file) {
            $url = uploadFile($file, 'posts');
            if ($url) $media_urls[] = $url;
        }
    }

    $post_id = FeedModel::createPost([
        'user_id'    => $user['user_id'],
        'faculty_id' => $user['faculty_id'],
        'content'    => $content,
        'audience'   => $audience,
        'type'       => $type,
        'tags'       => array_map('sanitize', array_slice($tags, 0, 3)),
        'media'      => $media_urls,
        'status'     => $status,
        'event_date' => !empty($_POST['event_date']) ? sanitize($_POST['event_date']) : null,
        'event_location' => !empty($_POST['event_location']) ? sanitize($_POST['event_location']) : null,
    ]);

    if (!$post_id) Response::error('Error al crear la publicación.', 500);

    // If flagged, notify moderators via queue
    if ($flagged) {
        // MessageQueue::publish('moderation', ['post_id'=>$post_id, 'reason'=>'auto_flagged']);
    }

    $post = FeedModel::getPostById($post_id, $user['user_id']);
    Response::success($post, 'Publicación creada.', 201);
}

// ---- DELETE /feed/posts/{id} ----
if ($method === 'DELETE' && preg_match('#^feed/posts/(\d+)$#', $path, $m)) {
    $post_id = (int)$m[1];
    $post = FeedModel::getPostById($post_id, $user['user_id']);

    if (!$post) Response::error('Publicación no encontrada.', 404);

    $is_owner = $post['author_id'] === $user['user_id'];
    $is_mod   = in_array($user['role'], ['admin','moderator']);

    if (!$is_owner && !$is_mod) {
        Response::error('No tienes permiso para eliminar esta publicación.', 403);
    }

    FeedModel::deletePost($post_id);

    // Broadcast via WebSocket (Socket.io server handles this)
    // SocketBroadcast::emit('post_removed', ['post_id'=>$post_id, 'reason'=>$is_mod?'moderated':'deleted']);

    Response::success(null, 'Publicación eliminada.');
}

// ---- POST /feed/posts/{id}/like ----
if ($method === 'POST' && preg_match('#^feed/posts/(\d+)/like$#', $path, $m)) {
    $post_id = (int)$m[1];
    FeedModel::addLike($post_id, $user['user_id']);
    $count = FeedModel::getLikesCount($post_id);
    Response::success(['likes_count' => $count]);
}

// ---- DELETE /feed/posts/{id}/like ----
if ($method === 'DELETE' && preg_match('#^feed/posts/(\d+)/like$#', $path, $m)) {
    $post_id = (int)$m[1];
    FeedModel::removeLike($post_id, $user['user_id']);
    $count = FeedModel::getLikesCount($post_id);
    Response::success(['likes_count' => $count]);
}

// ---- GET /feed/posts/{id}/comments ----
if ($method === 'GET' && preg_match('#^feed/posts/(\d+)/comments$#', $path, $m)) {
    $post_id  = (int)$m[1];
    $comments = FeedModel::getComments($post_id, 20);
    Response::success(['comments' => $comments]);
}

// ---- POST /feed/posts/{id}/comments ----
if ($method === 'POST' && preg_match('#^feed/posts/(\d+)/comments$#', $path, $m)) {
    $post_id = (int)$m[1];
    $content = sanitize(REQUEST_BODY['content'] ?? '');

    if (!$content || mb_strlen($content) > 500) {
        Response::error('Comentario inválido.', 422);
    }

    $flagged    = ContentFilter::check($content);
    $comment_id = FeedModel::addComment($post_id, $user['user_id'], $content, $flagged);

    Response::success(['comment_id' => $comment_id], 'Comentario publicado.', 201);
}

// ---- Helper: normalize $_FILES array ----
function normalizeFilesArray(array $files): array {
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

// ---- Content Filter (basic) ----
class ContentFilter {
    private static array $blocklist = [
        // Add your institution's inappropriate words here
        'spam', 'promo', 'oferta falsa'
    ];

    public static function check(string $text): bool {
        $lower = mb_strtolower($text);
        foreach (self::$blocklist as $word) {
            if (str_contains($lower, $word)) return true;
        }
        return false;
    }
}
