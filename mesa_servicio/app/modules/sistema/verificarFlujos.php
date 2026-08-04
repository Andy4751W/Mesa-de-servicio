<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (!seguridadDiagnosticoHabilitado()) {
    http_response_code(404);
    exit('Recurso no disponible.');
}

function escaparVerificacionFlujo(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function indiceVerificacionFlujo(
    mysqli $conn,
    string $tabla,
    string $indice
): bool {
    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?
         LIMIT 1"
    );
    $stmt->bind_param('ss', $tabla, $indice);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    return $existe;
}

function restriccionVerificacionFlujo(
    mysqli $conn,
    string $tabla,
    string $restriccion,
    string $tipo
): bool {
    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ?
           AND CONSTRAINT_TYPE = ?
         LIMIT 1"
    );
    $stmt->bind_param('sss', $tabla, $restriccion, $tipo);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    return $existe;
}

function detalleServicioPendiente(array $servicio): string
{
    $faltantes = [];
    if ((int) ($servicio['falta_gestor'] ?? 0) === 1) {
        $faltantes[] = 'gestor';
    }
    if ((int) ($servicio['falta_sla'] ?? 0) === 1) {
        $faltantes[] = 'SLA';
    }

    return $faltantes ? 'Falta ' . implode(' y ', $faltantes) : 'Pendiente';
}

$tablas = [
    'procesos',
    'proceso_etapas',
    'proceso_etapa_checklist',
    'ticket_etapas',
    'ticket_etapa_checklist',
    'notificaciones',
];
$columnas = [
    ['servicios', 'id_gestor'],
    ['tickets', 'id_proceso'],
    ['tickets', 'estado_flujo'],
    ['tickets', 'id_etapa_actual'],
    ['solicitud_comunicaciones', 'id_ticket_etapa'],
    ['solicitud_adjuntos', 'id_ticket_etapa'],
    ['solicitud_calificaciones', 'id_ticket_etapa'],
];
$resultados = [];

foreach ($tablas as $tabla) {
    $resultados[] = [
        'elemento' => 'Tabla ' . $tabla,
        'ok' => flujoTablaExiste($conn, $tabla),
    ];
}

foreach ($columnas as [$tabla, $columna]) {
    $resultados[] = [
        'elemento' => "Columna {$tabla}.{$columna}",
        'ok' => flujoColumnaExiste($conn, $tabla, $columna),
    ];
}

$resultados[] = [
    'elemento' => 'Índice de calificación por etapa',
    'ok' => indiceVerificacionFlujo(
        $conn,
        'solicitud_calificaciones',
        'uq_calificacion_etapa'
    ),
];
$resultados[] = [
    'elemento' => 'Índice único anterior retirado',
    'ok' => !indiceVerificacionFlujo(
        $conn,
        'solicitud_calificaciones',
        'uq_calificacion_ticket'
    ),
];
$resultados[] = [
    'elemento' => 'Clave foránea de calificaciones',
    'ok' => restriccionVerificacionFlujo(
        $conn,
        'solicitud_calificaciones',
        'fk_calificacion_ticket',
        'FOREIGN KEY'
    ),
];

$serviciosSinConfigurar = [];
$procesosInvalidos = [];

if (flujoModuloInstalado($conn)) {
    $resultado = $conn->query(
        "SELECT c.nombre AS catalogo, s.nombre AS servicio,
            CASE WHEN u.id_usuario IS NULL THEN 1 ELSE 0 END AS falta_gestor,
            CASE WHEN sl.id_sla IS NULL THEN 1 ELSE 0 END AS falta_sla
         FROM servicios s
         INNER JOIN catalogos c ON c.id_catalogo = s.id_catalogo
         LEFT JOIN usuarios u
            ON u.id_usuario = s.id_gestor
           AND u.id_rol = 2
           AND u.estado = 'activo'
         LEFT JOIN sla sl
            ON sl.id_sla = s.id_sla
           AND sl.estado = 'activo'
         WHERE s.estado = 'activo'
           AND (u.id_usuario IS NULL OR sl.id_sla IS NULL)
         ORDER BY c.nombre, s.nombre"
    );
    while ($fila = $resultado->fetch_assoc()) {
        $serviciosSinConfigurar[] = $fila;
    }

    $resultado = $conn->query(
        "SELECT p.id_proceso, p.nombre,
            COUNT(pe.id_proceso_etapa) AS total,
            SUM(CASE WHEN s.id_gestor IS NOT NULL
                           AND u.id_rol = 2
                           AND u.estado = 'activo'
                           AND sl.estado = 'activo'
                     THEN 1 ELSE 0 END) AS validas
         FROM procesos p
         LEFT JOIN proceso_etapas pe
            ON pe.id_proceso = p.id_proceso AND pe.estado = 'activo'
         LEFT JOIN servicios s ON s.id_servicio = pe.id_servicio
         LEFT JOIN usuarios u ON u.id_usuario = s.id_gestor
         LEFT JOIN sla sl ON sl.id_sla = s.id_sla
         WHERE p.estado = 'activo'
         GROUP BY p.id_proceso, p.nombre
         HAVING total = 0 OR validas <> total"
    );
    while ($fila = $resultado->fetch_assoc()) {
        $procesosInvalidos[] = $fila;
    }
}

$estructuraCompleta = !array_filter(
    $resultados,
    static fn (array $resultado): bool => !$resultado['ok']
);
$configuracionCompleta = !$serviciosSinConfigurar && !$procesosInvalidos;

if (!$estructuraCompleta) {
    $mensajeEstado = 'La estructura está incompleta. Importe el SQL de reparación y vuelva a verificar.';
    $claseEstado = 'bad';
} elseif (!$configuracionCompleta) {
    $mensajeEstado = 'La estructura está correcta, pero todavía faltan gestores, SLA o etapas por configurar.';
    $claseEstado = 'warn';
} else {
    $mensajeEstado = 'La instalación y la configuración están listas para realizar pruebas.';
    $claseEstado = 'ok';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar flujos | Mesa de Servicio</title>
    <style>body{margin:0;padding:28px;background:#f3f6fb;color:#243b53;font:14px/1.5 Inter,"Segoe UI",Arial}.shell{max-width:950px;margin:auto}.card{background:#fff;border:1px solid #dfe7f1;border-radius:16px;padding:20px;margin-bottom:16px}.ok{color:#087443}.warn{color:#9a6700}.bad{color:#b42318}.row{display:flex;justify-content:space-between;gap:18px;padding:8px 0;border-bottom:1px solid #edf1f6}.btn{display:inline-block;padding:10px 14px;border-radius:9px;background:#0f6fec;color:#fff;text-decoration:none;font-weight:700}</style>
</head>
<body><main class="shell"><section class="card"><h1>Verificación de procesos secuenciales</h1><p class="<?= $claseEstado ?>"><?= escaparVerificacionFlujo($mensajeEstado) ?></p><a class="btn" href="procesos.php">Volver a procesos</a></section><section class="card"><h2>Estructura</h2><?php foreach($resultados as $resultado): ?><div class="row"><span><?= escaparVerificacionFlujo($resultado['elemento']) ?></span><strong class="<?= $resultado['ok']?'ok':'bad' ?>"><?= $resultado['ok']?'Correcto':'Falta' ?></strong></div><?php endforeach; ?></section><section class="card"><h2>Servicios pendientes de gestor o SLA</h2><?php if(!$serviciosSinConfigurar): ?><p class="ok">Todos los servicios activos están configurados.</p><?php else: ?><?php foreach($serviciosSinConfigurar as $servicio): ?><div class="row"><span><?= escaparVerificacionFlujo($servicio['catalogo'].' / '.$servicio['servicio']) ?></span><strong class="bad"><?= escaparVerificacionFlujo(detalleServicioPendiente($servicio)) ?></strong></div><?php endforeach; ?><?php endif; ?></section><section class="card"><h2>Procesos activos inválidos</h2><?php if(!$procesosInvalidos): ?><p class="ok">Todos los procesos activos tienen etapas válidas.</p><?php else: ?><?php foreach($procesosInvalidos as $proceso): ?><div class="row"><span><?= escaparVerificacionFlujo($proceso['nombre']) ?></span><strong class="bad"><?= (int)$proceso['validas'] ?>/<?= (int)$proceso['total'] ?> etapas válidas</strong></div><?php endforeach; ?><?php endif; ?></section></main><script src="assets/js/controlSesion.js" defer></script></body></html>
