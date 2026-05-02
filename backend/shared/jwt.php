<?php
/**
 * UniLink — jwt.php
 * Solo maneja JWT. Response y RateLimiter tienen sus propios archivos.
 */

class JWT {

    public static function generate(array $payload): string {
        $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;
        $body    = base64_encode(json_encode($payload));
        $sig     = self::sign("$header.$body");
        return "$header.$body.$sig";
    }

    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $sig] = $parts;

        if (self::sign("$header.$body") !== $sig) return null;

        $payload = json_decode(base64_decode($body), true);
        if (!$payload || $payload['exp'] < time()) return null;

        return $payload;
    }

    private static function sign(string $data): string {
        return base64_encode(hash_hmac('sha256', $data, JWT_SECRET, true));
    }
}
