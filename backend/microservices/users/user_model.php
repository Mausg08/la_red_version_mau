<?php
/**
 * UniLink — user_model.php
 * Data access layer for users table
 */
class UserModel {
    private static ?PDO $pdo = null;

    public static function getDB(): PDO {
        if (self::$pdo) return self::$pdo;

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=red_social;charset=utf8mb4',
            DB_HOST,
            DB_PORT
        );

        self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);

        return self::$pdo;
    }

    public static function findByEmail(string $email): ?array {
        $stmt = self::getDB()->prepare("
            SELECT u.*, f.name AS faculty_name
            FROM users u
            LEFT JOIN faculties f ON f.faculty_id = u.faculty_id
            WHERE u.email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array {
        $stmt = self::getDB()->prepare("
            SELECT u.*, f.name AS faculty_name
            FROM users u
            LEFT JOIN faculties f ON f.faculty_id = u.faculty_id
            WHERE u.user_id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): ?int {
        $db   = self::getDB();
        $stmt = $db->prepare("
            INSERT INTO users
              (email, password_hash, first_name, last_name, student_id, faculty_id, semester, role, status)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $data['email'],
            $data['password'],
            $data['first_name'],
            $data['last_name'],
            $data['student_id'],
            $data['faculty_id'],
            $data['semester'],
            $data['role'],
            $data['status'],
        ]);
        return (int)$db->lastInsertId() ?: null;
    }

    public static function updateLastLogin(int $id): void {
        self::getDB()->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
            ->execute([$id]);
    }

    public static function storeResetToken(int $userId, string $token, int $expiresAt): void {
        $hash = hash('sha256', $token);
        self::getDB()->prepare("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
            VALUES (?,?,FROM_UNIXTIME(?))
            ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash), expires_at=VALUES(expires_at)")
            ->execute([$userId, $hash, $expiresAt]);
    }

    public static function getPublicProfile(int $id, int $requesterId): ?array {
        $stmt = self::getDB()->prepare("
            SELECT u.user_id,
                   CONCAT(u.first_name,' ',u.last_name) AS name,
                   u.first_name, u.last_name, u.bio, u.avatar, u.role, u.semester,
                   u.reputation_score, u.last_login,
                   f.name AS faculty_name, c.name AS career_name,
                   CASE WHEN u.show_phone=1 AND u.user_id != ? THEN u.phone ELSE NULL END AS phone,
                   u.email,
                   (SELECT COUNT(*) FROM user_follows WHERE following_id=u.user_id) AS followers_count,
                   (SELECT COUNT(*) FROM user_follows WHERE follower_id=u.user_id)  AS following_count,
                   (SELECT COUNT(*) FROM posts WHERE user_id=u.user_id AND status='published') AS posts_count,
                   EXISTS(SELECT 1 FROM user_follows WHERE follower_id=? AND following_id=u.user_id) AS is_following
            FROM users u
            LEFT JOIN faculties f ON f.faculty_id = u.faculty_id
            LEFT JOIN careers   c ON c.career_id  = u.career_id
            WHERE u.user_id = ? AND u.status = 'active'");
        $stmt->execute([$requesterId, $requesterId, $id]);
        return $stmt->fetch() ?: null;
    }

    public static function search(string $q, int $limit, int $excludeUserId): array {
        $db   = self::getDB();
        $like = "%$q%";

        $userStmt = $db->prepare("
            SELECT user_id AS id, CONCAT(first_name,' ',last_name) AS name,
                   'user' AS type, role AS subtitle, avatar
            FROM users
            WHERE (CONCAT(first_name,' ',last_name) LIKE ? OR student_id LIKE ?)
              AND status='active' AND user_id != ?
            LIMIT ?");
        $userStmt->execute([$like, $like, $excludeUserId, (int)ceil($limit / 2)]);
        return $userStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class UserStatusCheck {
    public static function isActive(int $userId): bool {
        static $cache = [];
        if (isset($cache[$userId])) return $cache[$userId];
        try {
            $stmt = UserModel::getDB()->prepare("SELECT status FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row    = $stmt->fetch();
            $active = $row && $row['status'] === 'active';
            $cache[$userId] = $active;
            return $active;
        } catch (\Exception $e) {
            return true;
        }
    }
}
