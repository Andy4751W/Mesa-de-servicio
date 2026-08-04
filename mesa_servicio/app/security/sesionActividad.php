<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';

seguridadAplicarCabeceras(true);
seguridadIniciarSesion();

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'motivo' => 'metodo']);
    exit;
}

if (!seguridadOrigenMismoSitio()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'motivo' => 'origen']);
    exit;
}

$ahora = time();
$motivo = seguridadMotivoVencimiento($ahora);

if ($motivo !== null) {
    seguridadCerrarSesion();
    http_response_code(401);
    echo json_encode(['ok' => false, 'motivo' => $motivo]);
    exit;
}

$accion = (string) ($_POST['accion'] ?? 'estado');

if ($accion === 'actividad') {
    $_SESSION['ultima_interaccion'] = $ahora;
} elseif ($accion !== 'estado') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'motivo' => 'accion']);
    exit;
}

echo json_encode([
    'ok' => true,
    'tiempos' => seguridadTiemposRestantes($ahora),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
