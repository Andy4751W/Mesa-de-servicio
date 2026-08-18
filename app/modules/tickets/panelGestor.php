<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 2) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$idPaisOperacion = paisExigirContexto();

function escaparPanelGestor(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function fechaPanelGestor(mixed $valor): string
{
    $valor = trim((string) $valor);
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    return $fecha && $fecha->format('Y-m-d') === $valor ? $valor : '';
}

function filasPanelGestor(mysqli $conn, string $sql): array
{
    $resultado = $conn->query($sql);

    if ($resultado === false) {
        throw new RuntimeException('No fue posible consultar las métricas del gestor.');
    }

    return $resultado->fetch_all(MYSQLI_ASSOC);
}

function filaPanelGestor(mysqli $conn, string $sql): array
{
    return filasPanelGestor($conn, $sql)[0] ?? [];
}

function numeroPanelGestor(float|int|string|null $valor, int $decimales = 0): string
{
    return number_format((float) ($valor ?? 0), $decimales, ',', '.');
}

function porcentajePanelGestor(float|int|string|null $valor): string
{
    if ($valor === null || $valor === '') {
        return 'Sin datos';
    }

    return numeroPanelGestor($valor, 1) . '%';
}

function tiempoPanelGestor(float|int|string|null $minutos): string
{
    if ($minutos === null || $minutos === '') {
        return 'Sin datos';
    }

    $minutos = max(0, (int) round((float) $minutos));
    $minutosDia = defined('CALENDARIO_MINUTOS_DIA_SLA')
        ? max(1, (int) CALENDARIO_MINUTOS_DIA_SLA)
        : 600;
    $dias = intdiv($minutos, $minutosDia);
    $horas = intdiv($minutos % $minutosDia, 60);
    $resto = $minutos % 60;

    if ($dias > 0) {
        return $dias . ' d ' . $horas . ' h';
    }

    if ($horas > 0) {
        return $horas . ' h ' . $resto . ' min';
    }

    return $resto . ' min';
}

/**
 * @param array<int, array{label:string,value:int,color:string}> $segmentos
 */
function gradientePanelGestor(array $segmentos): string
{
    $total = array_sum(array_column($segmentos, 'value'));

    if ($total < 1) {
        return 'conic-gradient(#e8eef5 0 100%)';
    }

    $partes = [];
    $inicio = 0.0;

    foreach ($segmentos as $segmento) {
        $fin = $inicio + ((int) $segmento['value'] / $total * 100);
        $partes[] = $segmento['color'] . ' '
            . number_format($inicio, 3, '.', '') . '% '
            . number_format($fin, 3, '.', '') . '%';
        $inicio = $fin;
    }

    return 'conic-gradient(' . implode(',', $partes) . ')';
}

$idGestor = (int) ($_SESSION['usuario_id'] ?? 0);
$nombreGestor = trim((string) ($_SESSION['usuario'] ?? 'Gestor'));
$moduloInstalado = flujoModuloInstalado($conn);
$metricasDisponibles = $moduloInstalado
    && flujoModuloSolucionesInstalado($conn)
    && flujoModuloAprobacionCasosInstalado($conn);

$resumen = [
    'tickets' => 0,
    'abiertos' => 0,
    'cerrados' => 0,
    'pausados' => 0,
];
$metricas = [
    'casos' => 0,
    'en_gestion' => 0,
    'pausados' => 0,
    'resueltos' => 0,
    'programados' => 0,
    'otros' => 0,
    'dentro_sla' => 0,
    'fuera_sla' => 0,
    'evaluados_sla' => 0,
    'tiempo_promedio' => null,
    'soluciones' => 0,
    'reaperturas' => 0,
];
$calificaciones = [
    'promedio' => null,
    'gestion' => null,
    'tiempo' => null,
    'total' => 0,
];
$distribucionCalificaciones = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$soluciones = [];
$servicios = [];
$tendencia = [];
$errorMetricas = '';

$hoy = new DateTimeImmutable('today', calendarioZonaHoraria());
$periodoRapido = strtolower(trim((string) ($_GET['periodo'] ?? '')));
$desde = fechaPanelGestor($_GET['desde'] ?? '');
$hasta = fechaPanelGestor($_GET['hasta'] ?? '');

if ($periodoRapido === '30_dias') {
    $desde = $hoy->modify('-29 days')->format('Y-m-d');
    $hasta = $hoy->format('Y-m-d');
} elseif ($periodoRapido === 'mes') {
    $desde = $hoy->modify('first day of this month')->format('Y-m-d');
    $hasta = $hoy->format('Y-m-d');
} elseif ($periodoRapido === 'historico') {
    $desde = '';
    $hasta = '';
}

if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$periodoTexto = 'Todo el histórico personal';
if ($desde !== '' || $hasta !== '') {
    $periodoTexto = ($desde !== '' ? date('d/m/Y', strtotime($desde)) : 'Inicio')
        . ' — '
        . ($hasta !== '' ? date('d/m/Y', strtotime($hasta)) : 'Hoy');
}

if ($moduloInstalado) {
    $stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT t.id_ticket) AS tickets,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(t.estado_flujo)) = 'cerrado' THEN t.id_ticket END) AS cerrados,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(t.estado_flujo)) <> 'cerrado' OR t.estado_flujo IS NULL THEN t.id_ticket END) AS abiertos,
                SUM(te.estado = 'pausada') AS pausados
         FROM ticket_etapas AS te
         INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
         WHERE te.id_gestor = ?
           AND t.id_pais_operacion = ?
           AND t.id_proceso IS NOT NULL"
    );
    $stmt->bind_param('ii', $idGestor, $idPaisOperacion);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $resumen['tickets'] = (int) ($fila['tickets'] ?? 0);
    $resumen['abiertos'] = (int) ($fila['abiertos'] ?? 0);
    $resumen['cerrados'] = (int) ($fila['cerrados'] ?? 0);
    $resumen['pausados'] = (int) ($fila['pausados'] ?? 0);
}

if ($metricasDisponibles) {
    $condicionesEtapa = [
        'te.id_gestor = ' . $idGestor,
        't.id_pais_operacion = ' . $idPaisOperacion,
        't.id_proceso IS NOT NULL',
    ];
    $condicionesCalificacion = [
        'cal.id_gestor = ' . $idGestor,
        't.id_pais_operacion = ' . $idPaisOperacion,
        't.id_proceso IS NOT NULL',
    ];

    if ($desde !== '') {
        $condicionesEtapa[] = "te.creado_en >= '{$desde} 00:00:00'";
        $condicionesCalificacion[] = "cal.creado_en >= '{$desde} 00:00:00'";
    }

    if ($hasta !== '') {
        $condicionesEtapa[] = "te.creado_en < DATE_ADD('{$hasta}', INTERVAL 1 DAY)";
        $condicionesCalificacion[] = "cal.creado_en < DATE_ADD('{$hasta}', INTERVAL 1 DAY)";
    }

    $whereEtapa = implode(' AND ', $condicionesEtapa);
    $whereCalificacion = implode(' AND ', $condicionesCalificacion);

    try {
        $metricas = array_merge($metricas, filaPanelGestor(
            $conn,
            "SELECT
                COUNT(*) AS casos,
                SUM(te.estado IN ('pendiente', 'en_proceso', 'en_espera_solicitante')) AS en_gestion,
                SUM(te.estado = 'pausada') AS pausados,
                SUM(te.estado IN ('listo_cierre', 'completada')) AS resueltos,
                SUM(te.estado = 'bloqueada') AS programados,
                SUM(te.estado = 'cancelada') AS otros,
                SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) = 'dentro_sla') AS dentro_sla,
                SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) = 'fuera_sla') AS fuera_sla,
                SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) IN ('dentro_sla', 'fuera_sla')) AS evaluados_sla,
                AVG(CASE
                    WHEN te.estado IN ('listo_cierre', 'completada')
                    THEN COALESCE(te.minutos_hasta_listo, te.minutos_atencion)
                END) AS tiempo_promedio,
                SUM(
                    te.estado IN ('listo_cierre', 'completada')
                    AND NULLIF(TRIM(te.solucion_nombre), '') IS NOT NULL
                ) AS soluciones,
                SUM(te.cantidad_reaperturas) AS reaperturas
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE {$whereEtapa}"
        ));

        $calificaciones = array_merge($calificaciones, filaPanelGestor(
            $conn,
            "SELECT
                AVG(cal.calificacion) AS promedio,
                AVG(cal.calificacion_area) AS gestion,
                AVG(cal.calificacion_tiempo) AS tiempo,
                COUNT(*) AS total,
                SUM(cal.calificacion = 1) AS estrellas_1,
                SUM(cal.calificacion = 2) AS estrellas_2,
                SUM(cal.calificacion = 3) AS estrellas_3,
                SUM(cal.calificacion = 4) AS estrellas_4,
                SUM(cal.calificacion = 5) AS estrellas_5
             FROM solicitud_calificaciones AS cal
             INNER JOIN tickets AS t ON t.id_ticket = cal.id_ticket
             WHERE {$whereCalificacion}"
        ));

        for ($estrella = 1; $estrella <= 5; $estrella++) {
            $distribucionCalificaciones[$estrella] = (int) ($calificaciones['estrellas_' . $estrella] ?? 0);
        }

        $soluciones = filasPanelGestor(
            $conn,
            "SELECT te.solucion_nombre AS nombre, COUNT(*) AS total
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE {$whereEtapa}
               AND te.estado IN ('listo_cierre', 'completada')
               AND NULLIF(TRIM(te.solucion_nombre), '') IS NOT NULL
             GROUP BY te.solucion_nombre
             ORDER BY total DESC, te.solucion_nombre"
        );

        $servicios = filasPanelGestor(
            $conn,
            "SELECT
                CONCAT(te.catalogo_nombre, ' / ', te.servicio_nombre) AS nombre,
                COUNT(*) AS casos,
                SUM(te.estado IN ('listo_cierre', 'completada')) AS resueltos,
                AVG(CASE
                    WHEN te.estado IN ('listo_cierre', 'completada')
                    THEN COALESCE(te.minutos_hasta_listo, te.minutos_atencion)
                END) AS tiempo_promedio,
                ROUND(
                    100 * SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) = 'dentro_sla') /
                    NULLIF(SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) IN ('dentro_sla', 'fuera_sla')), 0),
                    1
                ) AS cumplimiento_sla,
                AVG(cal.calificacion) AS calificacion
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             LEFT JOIN solicitud_calificaciones AS cal
                ON cal.id_ticket_etapa = te.id_ticket_etapa
               AND cal.id_gestor = {$idGestor}
             WHERE {$whereEtapa}
             GROUP BY te.catalogo_nombre, te.servicio_nombre
             ORDER BY casos DESC, nombre
             LIMIT 6"
        );

        $tendencia = filasPanelGestor(
            $conn,
            "SELECT DATE_FORMAT(te.creado_en, '%Y-%m') AS periodo,
                    DATE_FORMAT(te.creado_en, '%m/%Y') AS etiqueta,
                    COUNT(*) AS asignados,
                    SUM(te.estado IN ('listo_cierre', 'completada')) AS resueltos
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE {$whereEtapa}
             GROUP BY DATE_FORMAT(te.creado_en, '%Y-%m'), DATE_FORMAT(te.creado_en, '%m/%Y')
             ORDER BY periodo DESC
             LIMIT 12"
        );
        $tendencia = array_reverse($tendencia);
    } catch (Throwable $e) {
        error_log('Dashboard personal del gestor: ' . $e->getMessage());
        $errorMetricas = 'No fue posible cargar todas sus métricas. Verifique que las migraciones vigentes estén instaladas.';
    }
}

$evaluadosSla = (int) ($metricas['evaluados_sla'] ?? 0);
$cumplimientoSla = $evaluadosSla > 0
    ? round(100 * (int) $metricas['dentro_sla'] / $evaluadosSla, 1)
    : null;
$sinEvaluarSla = max(0, (int) $metricas['casos'] - $evaluadosSla);
$detalleCalificacion = (int) $calificaciones['total'] > 0
    ? 'Gestión '
        . ($calificaciones['gestion'] === null ? '—' : numeroPanelGestor($calificaciones['gestion'], 1))
        . ' · Tiempo '
        . ($calificaciones['tiempo'] === null ? '—' : numeroPanelGestor($calificaciones['tiempo'], 1))
    : 'Sin evaluaciones recibidas';

$segmentosSla = [
    ['label' => 'Dentro del SLA', 'value' => (int) $metricas['dentro_sla'], 'color' => '#16a66a'],
    ['label' => 'Fuera del SLA', 'value' => (int) $metricas['fuera_sla'], 'color' => '#e05252'],
    ['label' => 'Sin corte SLA', 'value' => $sinEvaluarSla, 'color' => '#dce5ee'],
];
$segmentosEstado = [
    ['label' => 'En gestión', 'value' => (int) $metricas['en_gestion'], 'color' => '#1171dc'],
    ['label' => 'Pausados', 'value' => (int) $metricas['pausados'], 'color' => '#f1a82f'],
    ['label' => 'Resueltos', 'value' => (int) $metricas['resueltos'], 'color' => '#16a66a'],
    ['label' => 'Programados', 'value' => (int) $metricas['programados'], 'color' => '#8c6ee8'],
    ['label' => 'Cancelados', 'value' => (int) $metricas['otros'], 'color' => '#94a5b5'],
];
$coloresCalificacion = [1 => '#e05252', 2 => '#f18b38', 3 => '#f1c24b', 4 => '#54bda0', 5 => '#1171dc'];
$segmentosCalificacion = [];
foreach ($distribucionCalificaciones as $estrella => $cantidad) {
    $segmentosCalificacion[] = [
        'label' => $estrella . ' estrella' . ($estrella === 1 ? '' : 's'),
        'value' => $cantidad,
        'color' => $coloresCalificacion[$estrella],
    ];
}

$totalSoluciones = array_sum(array_map(static fn (array $fila): int => (int) $fila['total'], $soluciones));
$productividad = (int) $metricas['casos'] > 0
    ? round(100 * (int) $metricas['resueltos'] / (int) $metricas['casos'], 1)
    : null;
$tasaReapertura = (int) $metricas['resueltos'] > 0
    ? round(100 * (int) $metricas['reaperturas'] / (int) $metricas['resueltos'], 1)
    : null;
$maximoTendencia = 0;
foreach ($tendencia as $mes) {
    $maximoTendencia = max($maximoTendencia, (int) $mes['asignados'], (int) $mes['resueltos']);
}
$solucionesPrincipales = array_slice($soluciones, 0, 5);
$maximoSolucion = 0;
foreach ($solucionesPrincipales as $solucion) {
    $maximoSolucion = max($maximoSolucion, (int) $solucion['total']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del gestor | Mesa de Servicio</title>
    <style>
        :root{--primary:#116bd5;--primary-dark:#0b4fa8;--navy:#12283f;--text:#38536c;--muted:#72869a;--border:#dbe6ef;--surface:#fff;--soft:#edf5ff;--bg:#f3f6fa;--ok:#087443;--warn:#a86b00;--danger:#c94747;--shadow:0 8px 24px rgba(18,40,63,.06)}
        *{box-sizing:border-box}body{margin:0;color:var(--text);background:linear-gradient(135deg,#f6f9fc,#edf3f9);font:12px/1.45 Inter,"Segoe UI",Arial,sans-serif}.shell{width:min(1240px,calc(100% - 18px));margin:auto;padding:8px 0 30px}.topbar,.filter-panel,.kpi,.chart-card,.performance,.module,.alert{border:1px solid var(--border);background:var(--surface);box-shadow:var(--shadow)}.topbar{min-height:54px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 12px;border-radius:13px}.brand,.actions,.filter-heading,.filter-fields,.quick-filters,.kpi-head,.chart-title,.module{display:flex;align-items:center}.brand{gap:9px}.mark{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;color:#fff;background:linear-gradient(145deg,#1685f2,#0c56b8);font-weight:900}.brand strong{display:block;color:var(--navy);font-size:14px}.brand small{display:block;margin-top:1px;color:var(--muted);font-size:9px}.actions{gap:7px}.btn{min-height:33px;display:inline-flex;align-items:center;justify-content:center;padding:7px 11px;border:1px solid #d4e2ef;border-radius:9px;color:#285d8c;background:#f8fbff;text-decoration:none;font:800 10px/1.2 Inter,"Segoe UI",Arial,sans-serif;cursor:pointer}.btn:hover{border-color:#9ec4eb;background:#eef6ff}.btn.primary{border-color:var(--primary);color:#fff;background:var(--primary)}.btn.primary:hover{background:var(--primary-dark)}
        .filter-panel{margin-top:9px;padding:13px 14px;border-radius:13px}.filter-heading{justify-content:space-between;gap:12px}.filter-heading h1{margin:0;color:var(--navy);font-size:17px}.filter-heading p{margin:2px 0 0;color:var(--muted);font-size:9px}.period-tag{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;color:#1764b6;background:#edf5ff;font-size:9px;font-weight:850}.period-tag:before{width:7px;height:7px;border-radius:50%;background:#1886e8;content:""}.filters{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;margin-top:11px;padding-top:10px;border-top:1px solid #edf1f5}.filter-fields{gap:8px}.field label{display:block;margin:0 0 3px;color:#71869a;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.05em}.field input{height:34px;padding:6px 9px;border:1px solid #d5e1eb;border-radius:8px;color:#243b53;background:#fbfdff;font:10px inherit}.quick-filters{gap:5px}.quick-filters a{padding:6px 8px;border-radius:8px;color:#5a7289;background:#f3f7fb;text-decoration:none;font-size:9px;font-weight:800}.quick-filters a:hover{color:#0e66c6;background:#eaf4ff}
        .kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin-top:9px}.kpi{min-width:0;padding:11px 12px;border-radius:12px}.kpi-head{justify-content:space-between;gap:8px}.kpi-icon{width:29px;height:29px;display:grid;place-items:center;border-radius:8px;color:#0e66c6;background:#eaf4ff;font-size:9px;font-weight:950}.kpi-icon.green{color:#0a8053;background:#e8f8f1}.kpi-icon.amber{color:#875300;background:#fff4df}.kpi-icon.purple{color:#7253c6;background:#f0ecff}.kpi-icon.red{color:#bd4444;background:#fff0f0}.kpi-title{color:var(--muted);font-size:9px}.kpi-value{display:block;margin-top:7px;overflow:hidden;color:var(--navy);font-size:22px;font-weight:900;letter-spacing:-.03em;text-overflow:ellipsis;white-space:nowrap}.kpi small{display:block;margin-top:2px;color:#8091a1;font-size:9px}.kpi .positive{color:var(--ok)}.kpi .negative{color:var(--danger)}
        .charts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:9px}.chart-card{min-width:0;padding:13px;border-radius:13px}.chart-title{justify-content:space-between;gap:8px}.chart-title h2{margin:0;color:var(--navy);font-size:13px}.chart-title span{color:#8394a5;font-size:9px}.chart-body{display:grid;grid-template-columns:120px minmax(0,1fr);align-items:center;gap:13px;margin-top:12px}.donut{position:relative;width:116px;height:116px;border-radius:50%;background:var(--donut);box-shadow:inset 0 0 0 1px rgba(28,57,83,.04)}.donut:after{position:absolute;inset:20px;display:block;border:1px solid #edf2f6;border-radius:50%;background:#fff;box-shadow:0 4px 18px rgba(16,42,67,.08);content:""}.donut-center{position:absolute;inset:0;z-index:1;display:grid;align-content:center;justify-items:center;text-align:center;pointer-events:none}.donut-center strong{color:var(--navy);font-size:20px}.donut-center small{max-width:66px;color:#7a8d9f;font-size:9px;line-height:1.2}.legend{display:grid;gap:7px}.legend-row{display:grid;grid-template-columns:8px minmax(0,1fr) auto;align-items:center;gap:6px;color:#61778c;font-size:9px}.legend-dot{width:8px;height:8px;border-radius:3px;background:var(--legend)}.legend-row strong{color:#263e55;font-size:9px}.empty-note{color:#8495a5;font-size:9px}
        .details-grid{display:grid;grid-template-columns:minmax(280px,.85fr) minmax(480px,1.5fr);gap:8px;margin-top:9px}.performance{min-width:0;padding:13px;border-radius:13px}.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:11px}.section-head h2{margin:0;color:var(--navy);font-size:13px}.section-head p{margin:2px 0 0;color:var(--muted);font-size:9px}.section-pill{padding:5px 8px;border-radius:999px;color:#1764b6;background:#edf5ff;font-size:9px;font-weight:850}.solution-list{display:grid;gap:9px}.solution-head{display:flex;justify-content:space-between;gap:8px;margin-bottom:4px;color:#415b73;font-size:9px}.solution-head span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.solution-head strong{color:var(--navy)}.solution-track{height:7px;overflow:hidden;border-radius:999px;background:#edf2f6}.solution-bar{height:100%;min-width:3px;border-radius:inherit;background:linear-gradient(90deg,#0e63c8,#29a0ef)}
        .table-wrap{overflow-x:auto;border:1px solid #e6edf3;border-radius:10px}.table-wrap:focus-visible{outline:3px solid rgba(15,111,236,.28);outline-offset:2px}table{width:100%;border-collapse:collapse;white-space:nowrap}th,td{padding:8px 9px;border-bottom:1px solid #edf2f6;text-align:left;font-size:9px}th{color:#526d82;background:#f7f9fb;font-weight:900;text-transform:uppercase;letter-spacing:.03em}td{color:#435e75}tbody tr:last-child td{border-bottom:0}td strong{color:#1f3951}.meter{width:74px;height:6px;display:inline-block;overflow:hidden;margin-right:5px;border-radius:999px;background:#e9eff4;vertical-align:middle}.meter span{height:100%;display:block;border-radius:inherit;background:#18a66b}.metric-na{color:#91a0ae}.modules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:9px}.module{gap:11px;min-height:85px;padding:12px;border-radius:12px;text-decoration:none;transition:.18s transform,.18s border-color}.module:hover{transform:translateY(-2px);border-color:#9ec5ef}.module-icon{flex:0 0 42px;width:42px;height:42px;display:grid;place-items:center;border-radius:11px;color:#0d63c8;background:var(--soft);font-size:17px}.module h2{margin:0;color:var(--navy);font-size:13px}.module p{margin:2px 0 0;color:var(--muted);font-size:9px}.badge{display:inline-flex;margin-top:5px;padding:3px 7px;border-radius:999px;color:var(--ok);background:#eaf8f1;font-size:9px;font-weight:850}.badge.closed{color:#526d82;background:#edf2f7}.alert{margin-top:9px;padding:10px 12px;border-radius:10px;color:var(--warn);background:#fff8eb}
        @media(max-width:1050px){.kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.charts{grid-template-columns:1fr 1fr}.charts .chart-card:last-child{grid-column:1/-1}.details-grid{grid-template-columns:1fr}.chart-body{grid-template-columns:112px minmax(0,1fr)}}@media(max-width:700px){.shell{width:calc(100% - 10px)}.topbar,.filter-heading,.filters{align-items:flex-start;flex-direction:column}.actions,.filter-fields{width:100%}.actions .btn,.filter-fields .field{flex:1}.field input{width:100%}.quick-filters{flex-wrap:wrap}.kpis{grid-template-columns:1fr 1fr}.charts{grid-template-columns:1fr}.charts .chart-card:last-child{grid-column:auto}.modules{grid-template-columns:1fr}.period-tag{align-self:flex-start}}@media(max-width:430px){.kpis{grid-template-columns:1fr}.chart-body{grid-template-columns:1fr;justify-items:center}.legend{width:100%}.filter-fields{display:grid;grid-template-columns:1fr 1fr}.filter-fields .btn{grid-column:1/-1}}
        .kpis{grid-template-columns:repeat(6,minmax(0,1fr))}
        .trend-card{margin-top:9px;padding:13px;border:1px solid var(--border);border-radius:13px;background:#fff;box-shadow:var(--shadow)}
        .trend-legend{display:flex;gap:14px;color:#657b90;font-size:9px}.trend-legend span{display:flex;align-items:center;gap:5px}.trend-legend i{width:8px;height:8px;border-radius:2px;background:#1474dc}.trend-legend span:last-child i{background:#19a66b}
        .trend-chart{height:180px;display:flex;align-items:flex-end;gap:8px;margin-top:12px;padding:10px 8px 0;border-bottom:1px solid #dfe8f0;background:repeating-linear-gradient(to top,#fff 0,#fff 35px,#edf2f6 36px)}
        .trend-month{height:100%;min-width:34px;flex:1;display:grid;grid-template-rows:1fr auto;gap:6px}.trend-bars{display:flex;align-items:flex-end;justify-content:center;gap:3px}.trend-bar{width:min(18px,42%);min-height:2px;border-radius:4px 4px 0 0;background:#1474dc}.trend-bar.done{background:#19a66b}.trend-label{text-align:center;color:#6f8294;font-size:9px;white-space:nowrap}
        @media(max-width:1050px){.kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.trend-chart{overflow-x:auto}.trend-month{min-width:48px}}@media(max-width:430px){.kpis{grid-template-columns:1fr}}
        /* Legibilidad: tamaños mínimos y contraste alto para textos informativos. */
        body{font-size:14px;color:#334e68}
        .brand small,.filter-heading p,.module p{font-size:11px;color:#526d82}
        .btn{font-size:12px}.period-tag,.quick-filters a{font-size:11px}
        .field label{font-size:10px;color:#486581}.field input{font-size:12px}
        .kpi-title{font-size:11px;color:#486581;font-weight:650}.kpi small{font-size:10px;color:#526d82}
        .kpi-icon{font-size:10px}.chart-title h2,.section-head h2,.module h2{font-size:15px}
        .chart-title span,.section-head p,.section-pill,.trend-legend{font-size:10px;color:#526d82}
        .donut-center small{max-width:76px;font-size:9px;color:#526d82}
        .legend-row{font-size:10px;color:#486581}.legend-row strong{font-size:11px;color:#102a43}
        .empty-note,.solution-head{font-size:11px;color:#486581}
        th,td{font-size:10px}.trend-label{font-size:9px;color:#486581}
        .badge{font-size:10px}.filter-heading h1{font-size:19px}
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="brand">
            <span class="mark">MS</span>
            <div><strong>Mesa de Servicio · <?= escaparPanelGestor(paisContextoNombre()) ?></strong><small>Dashboard personal · <?= escaparPanelGestor($nombreGestor) ?></small></div>
        </div>
        <nav class="actions">
            <a class="btn" href="flujoTicket.php?modo=mis_tickets&amp;bandeja=cerrados">Casos cerrados</a>
            <a class="btn primary" href="flujoTicket.php?modo=mis_tickets&amp;bandeja=abiertos">Gestionar casos</a>
        </nav>
    </header>

    <?php if (!$moduloInstalado): ?>
        <div class="alert">Importe <strong>migracion_casos_padre_hijo.sql</strong> para habilitar el módulo.</div>
    <?php else: ?>
        <section class="filter-panel" aria-labelledby="titulo-dashboard-gestor">
            <div class="filter-heading">
                <div><h1 id="titulo-dashboard-gestor">Indicadores de mi gestión</h1><p>Resultados calculados exclusivamente con los casos asignados y las calificaciones recibidas por su usuario.</p></div>
                <span class="period-tag"><?= escaparPanelGestor($periodoTexto) ?></span>
            </div>
            <form class="filters" method="get" action="panelGestor.php">
                <div class="filter-fields">
                    <div class="field"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= escaparPanelGestor($desde) ?>"></div>
                    <div class="field"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= escaparPanelGestor($hasta) ?>"></div>
                    <button class="btn primary" type="submit">Aplicar periodo</button>
                </div>
                <nav class="quick-filters" aria-label="Periodos rápidos">
                    <a href="panelGestor.php?periodo=mes">Este mes</a>
                    <a href="panelGestor.php?periodo=30_dias">Últimos 30 días</a>
                    <a href="panelGestor.php?periodo=historico">Todo</a>
                </nav>
            </form>
        </section>

        <?php if (!$metricasDisponibles): ?>
            <div class="alert">Las métricas avanzadas requieren la versión vigente de SLA, calificaciones y soluciones.</div>
        <?php elseif ($errorMetricas !== ''): ?>
            <div class="alert"><?= escaparPanelGestor($errorMetricas) ?></div>
        <?php endif; ?>

        <section class="kpis" aria-label="Indicadores principales personales">
            <article class="kpi"><div class="kpi-head"><span class="kpi-title">Casos asignados</span><span class="kpi-icon">CA</span></div><strong class="kpi-value"><?= numeroPanelGestor($metricas['casos']) ?></strong><small><?= numeroPanelGestor($metricas['resueltos']) ?> resueltos en el periodo</small></article>
            <article class="kpi"><div class="kpi-head"><span class="kpi-title">Cumplimiento SLA</span><span class="kpi-icon green">SL</span></div><strong class="kpi-value <?= $cumplimientoSla !== null && $cumplimientoSla >= 90 ? 'positive' : ($cumplimientoSla !== null ? 'negative' : '') ?>"><?= porcentajePanelGestor($cumplimientoSla) ?></strong><small><?= numeroPanelGestor($evaluadosSla) ?> casos con corte oficial</small></article>
            <article class="kpi"><div class="kpi-head"><span class="kpi-title">Tiempo promedio</span><span class="kpi-icon amber">TP</span></div><strong class="kpi-value"><?= escaparPanelGestor(tiempoPanelGestor($metricas['tiempo_promedio'])) ?></strong><small>Hasta marcar el caso como Listo</small></article>
            <article class="kpi"><div class="kpi-head"><span class="kpi-title">Calificación promedio</span><span class="kpi-icon purple">★</span></div><strong class="kpi-value"><?= $calificaciones['promedio'] === null ? 'Sin datos' : numeroPanelGestor($calificaciones['promedio'], 1) . ' / 5' ?></strong><small><?= escaparPanelGestor($detalleCalificacion) ?></small></article>
            <article class="kpi"><div class="kpi-head"><span class="kpi-title">Soluciones registradas</span><span class="kpi-icon red">SO</span></div><strong class="kpi-value"><?= numeroPanelGestor($metricas['soluciones']) ?></strong><small><?= numeroPanelGestor($metricas['reaperturas']) ?> reaperturas acumuladas</small></article>
            <article class="kpi"><div class="kpi-head"><span class="kpi-title">Efectividad</span><span class="kpi-icon green">EF</span></div><strong class="kpi-value <?= $productividad !== null && $productividad >= 80 ? 'positive' : '' ?>"><?= porcentajePanelGestor($productividad) ?></strong><small><?= $tasaReapertura === null ? 'Sin base de reapertura' : porcentajePanelGestor($tasaReapertura) . ' de reapertura' ?></small></article>
        </section>

        <section class="charts" aria-label="Gráficas personales">
            <article class="chart-card">
                <div class="chart-title"><h2>Cumplimiento SLA</h2><span>Corte al marcar Listo</span></div>
                <div class="chart-body">
                    <div class="donut" style="--donut:<?= escaparPanelGestor(gradientePanelGestor($segmentosSla)) ?>"><div class="donut-center"><strong><?= $cumplimientoSla === null ? '—' : porcentajePanelGestor($cumplimientoSla) ?></strong><small><?= $cumplimientoSla === null ? 'sin casos evaluados' : 'cumplimiento personal' ?></small></div></div>
                    <div class="legend">
                        <?php foreach ($segmentosSla as $segmento): ?><div class="legend-row"><span class="legend-dot" style="--legend:<?= escaparPanelGestor($segmento['color']) ?>"></span><span><?= escaparPanelGestor($segmento['label']) ?></span><strong><?= numeroPanelGestor($segmento['value']) ?></strong></div><?php endforeach; ?>
                    </div>
                </div>
            </article>

            <article class="chart-card">
                <div class="chart-title"><h2>Estado de mis casos</h2><span>Distribución operativa</span></div>
                <div class="chart-body">
                    <div class="donut" style="--donut:<?= escaparPanelGestor(gradientePanelGestor($segmentosEstado)) ?>"><div class="donut-center"><strong><?= numeroPanelGestor($metricas['casos']) ?></strong><small>casos asignados</small></div></div>
                    <div class="legend">
                        <?php foreach ($segmentosEstado as $segmento): ?><div class="legend-row"><span class="legend-dot" style="--legend:<?= escaparPanelGestor($segmento['color']) ?>"></span><span><?= escaparPanelGestor($segmento['label']) ?></span><strong><?= numeroPanelGestor($segmento['value']) ?></strong></div><?php endforeach; ?>
                    </div>
                </div>
            </article>

            <article class="chart-card">
                <div class="chart-title"><h2>Calificaciones recibidas</h2><span>Satisfacción 1 a 5</span></div>
                <div class="chart-body">
                    <div class="donut" style="--donut:<?= escaparPanelGestor(gradientePanelGestor($segmentosCalificacion)) ?>"><div class="donut-center"><strong><?= $calificaciones['promedio'] === null ? '—' : numeroPanelGestor($calificaciones['promedio'], 1) ?></strong><small>promedio de <?= numeroPanelGestor($calificaciones['total']) ?> respuestas</small></div></div>
                    <div class="legend">
                        <?php foreach (array_reverse($segmentosCalificacion) as $segmento): ?><div class="legend-row"><span class="legend-dot" style="--legend:<?= escaparPanelGestor($segmento['color']) ?>"></span><span><?= escaparPanelGestor($segmento['label']) ?></span><strong><?= numeroPanelGestor($segmento['value']) ?></strong></div><?php endforeach; ?>
                    </div>
                </div>
            </article>
        </section>

        <section class="trend-card" aria-label="Tendencia mensual personal">
            <div class="section-head"><div><h2>Tendencia de gestión</h2><p>Casos que le fueron asignados frente a los casos que dejó listos o completados.</p></div><div class="trend-legend"><span><i></i>Asignados</span><span><i></i>Resueltos</span></div></div>
            <?php if ($tendencia === []): ?>
                <p class="empty-note">No hay información mensual para el periodo seleccionado.</p>
            <?php else: ?>
                <div class="trend-chart">
                    <?php foreach ($tendencia as $mes): ?>
                        <?php
                        $altoAsignados = $maximoTendencia > 0 ? max(2, round(100 * (int) $mes['asignados'] / $maximoTendencia, 1)) : 0;
                        $altoResueltos = $maximoTendencia > 0 ? max(2, round(100 * (int) $mes['resueltos'] / $maximoTendencia, 1)) : 0;
                        ?>
                        <div class="trend-month" title="<?= escaparPanelGestor($mes['etiqueta']) ?> · <?= numeroPanelGestor($mes['asignados']) ?> asignados · <?= numeroPanelGestor($mes['resueltos']) ?> resueltos">
                            <div class="trend-bars"><span class="trend-bar" style="height:<?= escaparPanelGestor($altoAsignados) ?>%"></span><span class="trend-bar done" style="height:<?= escaparPanelGestor($altoResueltos) ?>%"></span></div>
                            <span class="trend-label"><?= escaparPanelGestor($mes['etiqueta']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="details-grid">
            <article class="performance">
                <div class="section-head"><div><h2>Soluciones más utilizadas</h2><p>Clasificaciones registradas por usted al resolver.</p></div><span class="section-pill"><?= numeroPanelGestor($totalSoluciones) ?> usos</span></div>
                <?php if ($solucionesPrincipales === []): ?>
                    <p class="empty-note">Aún no hay soluciones registradas en el periodo seleccionado.</p>
                <?php else: ?>
                    <div class="solution-list">
                        <?php foreach ($solucionesPrincipales as $solucion): ?>
                            <?php $ancho = $maximoSolucion > 0 ? max(4, round(100 * (int) $solucion['total'] / $maximoSolucion, 1)) : 0; ?>
                            <div><div class="solution-head"><span title="<?= escaparPanelGestor($solucion['nombre']) ?>"><?= escaparPanelGestor($solucion['nombre']) ?></span><strong><?= numeroPanelGestor($solucion['total']) ?></strong></div><div class="solution-track"><div class="solution-bar" style="width:<?= escaparPanelGestor($ancho) ?>%"></div></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="performance">
                <div class="section-head"><div><h2>Desempeño por servicio</h2><p>Volumen, oportunidad, tiempo y percepción de sus casos.</p></div><span class="section-pill">Top 6</span></div>
                <?php if ($servicios === []): ?>
                    <p class="empty-note">No hay servicios gestionados en el periodo seleccionado.</p>
                <?php else: ?>
                    <div class="table-wrap" tabindex="0" aria-label="Tabla de desempeño por servicio; puede desplazarse horizontalmente"><table><thead><tr><th>Servicio</th><th>Casos</th><th>Resueltos</th><th>SLA</th><th>Tiempo prom.</th><th>Calificación</th></tr></thead><tbody>
                        <?php foreach ($servicios as $servicio): ?>
                            <?php $slaServicio = $servicio['cumplimiento_sla'] === null ? null : (float) $servicio['cumplimiento_sla']; ?>
                            <tr><td><strong><?= escaparPanelGestor($servicio['nombre']) ?></strong></td><td><?= numeroPanelGestor($servicio['casos']) ?></td><td><?= numeroPanelGestor($servicio['resueltos']) ?></td><td><?php if ($slaServicio === null): ?><span class="metric-na">Sin datos</span><?php else: ?><span class="meter"><span style="width:<?= escaparPanelGestor(max(0, min(100, $slaServicio))) ?>%"></span></span><?= porcentajePanelGestor($slaServicio) ?><?php endif; ?></td><td><?= escaparPanelGestor(tiempoPanelGestor($servicio['tiempo_promedio'])) ?></td><td><?= $servicio['calificacion'] === null ? '<span class="metric-na">Sin datos</span>' : numeroPanelGestor($servicio['calificacion'], 1) . ' / 5' ?></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </article>
        </section>

        <section class="modules" aria-label="Opciones de tickets">
            <a class="module" href="flujoTicket.php?modo=mis_tickets&amp;bandeja=abiertos"><span class="module-icon">▦</span><div><h2>Casos abiertos</h2><p>Gestione sus casos pendientes, en proceso, pausados o en cualquier estado distinto de cerrado.</p><span class="badge"><?= $resumen['abiertos'] ?> disponibles</span></div></a>
            <a class="module" href="flujoTicket.php?modo=mis_tickets&amp;bandeja=cerrados"><span class="module-icon">✓</span><div><h2>Casos cerrados</h2><p>Consulte el histórico y la trazabilidad de los casos que ya finalizaron.</p><span class="badge closed"><?= $resumen['cerrados'] ?> cerrados</span></div></a>
        </section>
    <?php endif; ?>
</main>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
