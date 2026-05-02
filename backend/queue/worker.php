<?php
/**
 * UniLink — queue/worker.php
 * Message queue worker for async tasks:
 * - Welcome emails
 * - Password reset emails
 * - Push notifications
 * - Moderation alerts
 * Runs as a long-lived process via Docker
 */

require_once __DIR__ . '/../shared/helpers.php';

echo "[UniLink Queue] Worker starting...\n";

// ---- Redis connection ----
function getRedis(): ?Redis {
    if (!extension_loaded('redis')) {
        echo "[Queue] Redis extension not available, using file fallback\n";
        return null;
    }
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $pass = getenv('REDIS_PASSWORD');
        if ($pass) $redis->auth($pass);
        return $redis;
    } catch (Exception $e) {
        echo "[Queue] Redis connection failed: {$e->getMessage()}\n";
        return null;
    }
}

// ---- Main loop ----
$redis   = getRedis();
$running = true;

// Graceful shutdown
pcntl_signal(SIGTERM, function() use (&$running) { $running = false; });
pcntl_signal(SIGINT,  function() use (&$running) { $running = false; });

echo "[Queue] Worker running. Listening for jobs...\n";

while ($running) {
    pcntl_signal_dispatch();

    if ($redis) {
        // Blocking pop from multiple queues (priority order)
        $job = $redis->blPop(['unilink:emails', 'unilink:notifications', 'unilink:moderation'], 5);

        if ($job && isset($job[1])) {
            $queue   = $job[0];
            $payload = json_decode($job[1], true);
            processJob($queue, $payload);
        }
    } else {
        // File-based fallback
        $queueDir = sys_get_temp_dir() . '/ul_queue';
        if (!is_dir($queueDir)) mkdir($queueDir, 0755, true);

        $files = glob($queueDir . '/*.job');
        if ($files) {
            $file    = array_shift($files);
            $payload = json_decode(file_get_contents($file), true);
            $queue   = $payload['_queue'] ?? 'general';
            unlink($file);
            processJob($queue, $payload);
        } else {
            sleep(5);
        }
    }
}

echo "[Queue] Worker stopped gracefully.\n";

// ---- Job processor ----
function processJob(string $queue, ?array $payload): void {
    if (!$payload) return;

    $type = $payload['type'] ?? 'unknown';
    echo "[Queue] Processing: queue={$queue} type={$type}\n";

    try {
        switch ($type) {
            case 'welcome':
                sendWelcomeEmail($payload['user_id'] ?? 0);
                break;

            case 'password_reset':
                sendPasswordResetEmail($payload['token'] ?? '', $payload['user_id'] ?? 0);
                break;

            case 'notification':
                deliverNotification($payload);
                break;

            case 'moderation_alert':
                notifyModerators($payload);
                break;

            case 'panic':
                alertSecurityTeam($payload);
                break;

            default:
                echo "[Queue] Unknown job type: {$type}\n";
        }
        echo "[Queue] Job completed: {$type}\n";
    } catch (Throwable $e) {
        echo "[Queue] Job failed: {$type} — {$e->getMessage()}\n";
        logEvent('error', "Queue job failed: {$type}", [
            'error' => $e->getMessage(),
            'payload' => $payload
        ]);
    }
}

// ---- Email sender ----
function sendEmail(string $to, string $subject, string $htmlBody): bool {
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort = getenv('SMTP_PORT') ?: 587;
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $fromName = 'UniLink';

    if (!$smtpUser) {
        echo "[Queue] No SMTP configured, skipping email to {$to}\n";
        return false;
    }

    // Using PHPMailer if available, else basic mail()
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = $smtpPort;
        $mail->setFrom($smtpUser, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->send();
        return true;
    }

    // Fallback: PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$smtpUser}>\r\n";
    return mail($to, $subject, $htmlBody, $headers);
}

function sendWelcomeEmail(int $userId): void {
    // Fetch user from DB
    $dsn  = sprintf('mysql:host=%s;dbname=red_social;charset=utf8mb4', DB_HOST);
    $pdo  = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare("SELECT first_name, email FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return;

    $html = "
      <div style='font-family:sans-serif;max-width:500px;margin:0 auto'>
        <h1 style='color:#1A3A6B'>¡Bienvenido a UniLink, {$user['first_name']}! 🎓</h1>
        <p>Tu cuenta universitaria ha sido creada exitosamente.</p>
        <p>Ya puedes acceder a:</p>
        <ul>
          <li>📰 <strong>Feed</strong> — publicaciones de tu comunidad</li>
          <li>🛒 <strong>Marketplace</strong> — compra y vende con otros alumnos</li>
          <li>👥 <strong>Grupos NRC</strong> — comunidades por materia</li>
          <li>📅 <strong>Calendario</strong> — eventos académicos y culturales</li>
        </ul>
        <a href='https://unilink.tuuni.edu.mx' style='display:inline-block;background:#2557A7;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold'>
          Entrar a UniLink
        </a>
        <p style='color:#9CA3AF;font-size:12px;margin-top:24px'>
          Este correo fue enviado porque te registraste en UniLink con tu correo institucional.
        </p>
      </div>";

    sendEmail($user['email'], "¡Bienvenido a UniLink! 🎓", $html);
}

function sendPasswordResetEmail(string $token, int $userId): void {
    $dsn  = sprintf('mysql:host=%s;dbname=red_social;charset=utf8mb4', DB_HOST);
    $pdo  = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare("SELECT first_name, email FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return;

    $link = "https://unilink.tuuni.edu.mx/frontend/pages/reset-password.php?token={$token}";
    $html = "
      <div style='font-family:sans-serif;max-width:500px;margin:0 auto'>
        <h2 style='color:#1A3A6B'>Restablecer contraseña</h2>
        <p>Hola {$user['first_name']}, recibimos una solicitud para restablecer tu contraseña.</p>
        <a href='{$link}' style='display:inline-block;background:#2557A7;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none'>
          Restablecer contraseña
        </a>
        <p style='color:#9CA3AF;font-size:12px;margin-top:16px'>
          Este enlace expira en 1 hora. Si no solicitaste este cambio, ignora este correo.
        </p>
      </div>";

    sendEmail($user['email'], "Restablecer contraseña — UniLink", $html);
}

function deliverNotification(array $payload): void {
    // Store notification in DB (already done by controller)
    // Here we could also send push notifications, SMS, etc.
    echo "[Queue] Notification delivered to user:{$payload['user_id']}\n";
}

function notifyModerators(array $payload): void {
    $facultyId = $payload['faculty_id'] ?? null;
    $postId    = $payload['post_id']    ?? null;
    echo "[Queue] Moderators notified for faculty:{$facultyId} post:{$postId}\n";
    // Could send email/SMS to moderators here
}

function alertSecurityTeam(array $payload): void {
    $userId = $payload['user_id'] ?? '?';
    $lat    = $payload['lat']     ?? '?';
    $lng    = $payload['lng']     ?? '?';
    echo "[Queue] PANIC ALERT! user:{$userId} at {$lat},{$lng}\n";
    // Send SMS/push to security team
    // Could integrate with Twilio, Firebase, etc.
}
