<?php
/**
 * UniLink — rate_limiter.php
 * Limitador de peticiones por IP (file-based para XAMPP)
 */

class RateLimiter {

    /**
     * Verifica si el cliente está dentro del límite de peticiones.
     *
     * @param string $key    Identificador único (ej. IP del cliente)
     * @param int    $limit  Máximo de peticiones permitidas en la ventana
     * @param int    $window Tamaño de la ventana en segundos
     * @return bool  true = permitido | false = límite superado
     */
    public static function check(string $key, int $limit, int $window): bool {
        // Intentar con Redis si está disponible
        if (extension_loaded('redis')) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->select(1);
                $count = $redis->incr("rl:$key");
                if ($count === 1) $redis->expire("rl:$key", $window);
                return $count <= $limit;
            } catch (Exception $e) {
                // Redis no disponible, caer al modo archivo
            }
        }

        // Fallback: rate limiting basado en archivos temporales
        $file = sys_get_temp_dir() . '/ul_rl_' . md5($key) . '.json';
        $now  = time();
        $data = ['count' => 0, 'reset' => $now + $window];

        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $stored = $raw !== false ? json_decode($raw, true) : null;
            if ($stored && $stored['reset'] > $now) {
                $data = $stored;
            }
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return $data['count'] <= $limit;
    }
}
