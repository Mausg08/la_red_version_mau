<?php
/**
 * UniLink — response.php
 * Respuestas JSON estandarizadas
 */

class Response {

    public static function success($data = null, string $message = 'OK', int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $code = 400, $errors = null): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function paginated(array $items, int $total, int $page, int $limit): void {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'    => $total,
                'page'     => $page,
                'limit'    => $limit,
                'has_more' => ($page * $limit) < $total,
                'pages'    => (int) ceil($total / $limit),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
