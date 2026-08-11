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
$rolActual = (int) ($_SESSION['rol'] ?? 0);
$cargoActual = match ($rolActual) {
    1 => 'Administrador global',
    2 => 'Gestor',
    3 => 'Solicitante',
    default => 'Usuario',
};
$nombreCompleto = trim((string) ($_SESSION['usuario'] ?? 'Usuario'));
$partesNombre = preg_split('/\s+/u', $nombreCompleto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$cantidadPartes = count($partesNombre);

if ($cantidadPartes >= 3) {
    $nombreCorto = $partesNombre[0] . ' ' . $partesNombre[$cantidadPartes - 2];
} elseif ($cantidadPartes === 2) {
    $nombreCorto = $partesNombre[0] . ' ' . $partesNombre[1];
} else {
    $nombreCorto = $partesNombre[0] ?? 'Usuario';
}

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
    'pais' => [
        'id' => (int) ($_SESSION['pais_operacion_id'] ?? 0),
        'codigo' => (string) ($_SESSION['pais_operacion_codigo'] ?? ''),
        'nombre' => (string) ($_SESSION['pais_operacion_nombre'] ?? ''),
        'color' => (string) ($_SESSION['pais_operacion_color'] ?? '#0f6fec'),
        'rol' => $rolActual,
        'administrador' => $rolActual === 1,
        'usuario' => $nombreCorto,
        'cargo' => $cargoActual,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
