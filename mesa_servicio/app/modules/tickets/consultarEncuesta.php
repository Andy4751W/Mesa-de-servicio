<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ((int) ($_SESSION['rol'] ?? 0) !== 3) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'encuesta' => null,
    ]);
    exit;
}

$idSolicitante = (int) ($_SESSION['usuario_id'] ?? 0);

try {
    $condicionSinFlujo = flujoColumnaExiste(
        $conn,
        'tickets',
        'id_proceso'
    ) ? ' AND t.id_proceso IS NULL' : '';
    $stmt = $conn->prepare(
        "SELECT
            t.id_ticket,
            t.titulo,
            COALESCE(g.nombre, 'Gestor asignado') AS gestor
         FROM tickets AS t
         LEFT JOIN usuarios AS g
            ON g.id_usuario = t.id_tecnico
         LEFT JOIN solicitud_calificaciones AS c
            ON c.id_ticket = t.id_ticket
         WHERE t.id_usuario = ?
           AND t.estado IN ('resuelta', 'resuelto')
           AND t.id_tecnico IS NOT NULL
           AND c.id_calificacion IS NULL
           {$condicionSinFlujo}
         ORDER BY t.actualizado_en DESC, t.id_ticket DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $idSolicitante);
    $stmt->execute();
    $encuesta = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'encuesta' => $encuesta
            ? [
                'id_ticket' => (int) $encuesta['id_ticket'],
                'titulo' => (string) $encuesta['titulo'],
                'gestor' => (string) $encuesta['gestor'],
            ]
            : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Error en consultarEncuesta.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'encuesta' => null,
    ]);
}
