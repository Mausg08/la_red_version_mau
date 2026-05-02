<?php
/**
 * UniLink — feed_model.php
 */
class FeedModel {
    private static ?PDO $pdo = null;

    private static function db(): PDO {
        if (self::$pdo) return self::$pdo;
        self::$pdo = getDBConnection();
        return self::$pdo;
    }

    public static function getPosts(array $opts): array {
        $userId    = $opts['user_id'];
        $facultyId = $opts['faculty_id'];
        $filter    = $opts['filter'] ?? 'all';
        $limit     = $opts['limit']  ?? 10;
        $offset    = $opts['offset'] ?? 0;

        $where  = ["p.status = 'published'"];
        $params = [$userId]; // for user_liked subquery

        switch ($filter) {
            case 'academic':
                $where[] = "p.type IN ('academic','aviso')";
                break;
            case 'events':
                $where[] = "p.type = 'event'";
                break;
            case 'groups':
                $where[] = "EXISTS (SELECT 1 FROM group_post_links gpl
                              JOIN group_members gm ON gm.group_id = gpl.group_id AND gm.user_id = ?)";
                $params[] = $userId;
                break;
            default:
                // Audience filter: show public + user's faculty + their groups
                $where[] = "(p.audience = 'public'
                             OR (p.audience = 'faculty' AND p.faculty_id = ?)
                             OR p.user_id = ?)";
                $params[] = $facultyId;
                $params[] = $userId;
                break;
        }

        $whereSQL = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $stmt = self::db()->prepare("
            SELECT p.post_id, p.content, p.audience, p.type,
                   p.event_date, p.event_location,
                   p.likes_count, p.comments_count, p.created_at,
                   u.user_id   AS author_id,
                   CONCAT(u.first_name,' ',u.last_name) AS author_name,
                   u.avatar    AS author_avatar,
                   u.role      AS author_role,
                   f.name      AS faculty_name,
                   EXISTS(SELECT 1 FROM post_likes WHERE post_id=p.post_id AND user_id=?) AS user_liked
            FROM posts p
            JOIN users u    ON p.user_id    = u.user_id
            LEFT JOIN faculties f ON p.faculty_id = f.faculty_id
            WHERE $whereSQL
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?");
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        // Attach tags and media
        foreach ($posts as &$post) {
            $post['tags']  = self::getPostTags($post['post_id']);
            $post['media'] = self::getPostMedia($post['post_id']);
        }

        return $posts;
    }

    public static function countPosts(array $opts): int {
        $userId    = $opts['user_id'];
        $facultyId = $opts['faculty_id'];
        $filter    = $opts['filter'] ?? 'all';

        $where  = ["p.status = 'published'"];
        $params = [];

        switch ($filter) {
            case 'academic': $where[] = "p.type IN ('academic','aviso')"; break;
            case 'events':   $where[] = "p.type = 'event'"; break;
            default:
                $where[]  = "(p.audience='public' OR (p.audience='faculty' AND p.faculty_id=?) OR p.user_id=?)";
                $params[] = $facultyId;
                $params[] = $userId;
        }

        $whereSQL = implode(' AND ', $where);
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM posts p WHERE $whereSQL");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function createPost(array $data): ?int {
        $db   = self::db();
        $stmt = $db->prepare("
            INSERT INTO posts
              (user_id, faculty_id, content, audience, type, event_date, event_location, status)
            VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $data['user_id'],
            $data['faculty_id'],
            $data['content'],
            $data['audience'],
            $data['type'],
            $data['event_date'],
            $data['event_location'],
            $data['status'],
        ]);
        $postId = (int)$db->lastInsertId();
        if (!$postId) return null;

        // Insert tags
        if (!empty($data['tags'])) {
            $tagStmt = $db->prepare("INSERT IGNORE INTO post_tags (post_id, tag) VALUES (?,?)");
            foreach ($data['tags'] as $tag) {
                if ($tag) $tagStmt->execute([$postId, strtolower($tag)]);
            }
        }

        // Insert media
        if (!empty($data['media'])) {
            $mediaStmt = $db->prepare("INSERT INTO post_media (post_id, url) VALUES (?,?)");
            foreach ($data['media'] as $url) {
                $mediaStmt->execute([$postId, $url]);
            }
        }

        return $postId;
    }

    public static function getPostById(int $postId, int $userId): ?array {
        $stmt = self::db()->prepare("
            SELECT p.*, CONCAT(u.first_name,' ',u.last_name) AS author_name, u.avatar,
                   EXISTS(SELECT 1 FROM post_likes WHERE post_id=? AND user_id=?) AS user_liked
            FROM posts p JOIN users u ON u.user_id=p.user_id
            WHERE p.post_id = ?");
        $stmt->execute([$postId, $userId, $postId]);
        $post = $stmt->fetch();
        if (!$post) return null;
        $post['tags']  = self::getPostTags($postId);
        $post['media'] = self::getPostMedia($postId);
        return $post;
    }

    public static function deletePost(int $postId): void {
        self::db()->prepare("UPDATE posts SET status='removed' WHERE post_id=?")->execute([$postId]);
    }

    public static function addLike(int $postId, int $userId): void {
        $db = self::db();
        $db->prepare("INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?,?)")->execute([$postId, $userId]);
        $db->prepare("UPDATE posts SET likes_count = (SELECT COUNT(*) FROM post_likes WHERE post_id=?) WHERE post_id=?")
           ->execute([$postId, $postId]);
    }

    public static function removeLike(int $postId, int $userId): void {
        $db = self::db();
        $db->prepare("DELETE FROM post_likes WHERE post_id=? AND user_id=?")->execute([$postId, $userId]);
        $db->prepare("UPDATE posts SET likes_count = (SELECT COUNT(*) FROM post_likes WHERE post_id=?) WHERE post_id=?")
           ->execute([$postId, $postId]);
    }

    public static function getLikesCount(int $postId): int {
        $stmt = self::db()->prepare("SELECT likes_count FROM posts WHERE post_id=?");
        $stmt->execute([$postId]);
        return (int)$stmt->fetchColumn();
    }

    public static function getComments(int $postId, int $limit = 20): array {
        $stmt = self::db()->prepare("
            SELECT c.comment_id, c.content, c.created_at,
                   CONCAT(u.first_name,' ',u.last_name) AS author_name, u.avatar
            FROM comments c JOIN users u ON u.user_id=c.user_id
            WHERE c.post_id=? AND c.is_flagged=0
            ORDER BY c.created_at ASC LIMIT ?");
        $stmt->execute([$postId, $limit]);
        return $stmt->fetchAll();
    }

    public static function addComment(int $postId, int $userId, string $content, bool $flagged = false): int {
        $db = self::db();
        $db->prepare("INSERT INTO comments (post_id, user_id, content, is_flagged) VALUES (?,?,?,?)")
           ->execute([$postId, $userId, $content, $flagged]);
        $id = (int)$db->lastInsertId();
        $db->prepare("UPDATE posts SET comments_count = comments_count + 1 WHERE post_id=?")->execute([$postId]);
        return $id;
    }

    private static function getPostTags(int $postId): array {
        $stmt = self::db()->prepare("SELECT tag FROM post_tags WHERE post_id=?");
        $stmt->execute([$postId]);
        return array_column($stmt->fetchAll(), 'tag');
    }

    private static function getPostMedia(int $postId): array {
        $stmt = self::db()->prepare("SELECT url, media_type FROM post_media WHERE post_id=? ORDER BY sort_order");
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }
}
