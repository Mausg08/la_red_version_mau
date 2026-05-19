<?php
/**
 * Compatibilidad para rutas antiguas /academic/*.
 * Las funciones reales viven en microservicios separados.
 */

$path = REQUEST_PATH;

if ($path === 'academic/my-groups' || str_starts_with($path, 'academic/groups')) {
    require __DIR__ . '/../groups/groups_controller.php';
}

if (str_starts_with($path, 'academic/events')) {
    require __DIR__ . '/../calendar/calendar_controller.php';
}

if (str_starts_with($path, 'academic/polls')) {
    require __DIR__ . '/../polls/polls_controller.php';
}

require_once __DIR__ . '/../../shared/response.php';
Response::error('Ruta academica no encontrada.', 404);
