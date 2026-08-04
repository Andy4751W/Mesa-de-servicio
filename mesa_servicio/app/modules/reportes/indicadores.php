<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}

function escaparIndicador(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function normalizarFechaIndicador(mixed $valor): string
{
    $valor = trim((string) $valor);

    if ($valor === '') {
        return '';
    }

    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    return $fecha && $fecha->format('Y-m-d') === $valor ? $valor : '';
}

function filasIndicador(mysqli $conn, string $sql): array
{
    $resultado = $conn->query($sql);

    if ($resultado === false) {
        throw new RuntimeException('No fue posible consultar los indicadores.');
    }

    return $resultado->fetch_all(MYSQLI_ASSOC);
}

function filaIndicador(mysqli $conn, string $sql): array
{
    return filasIndicador($conn, $sql)[0] ?? [];
}

function porcentajeIndicador(float|int|string|null $valor): string
{
    if ($valor === null || $valor === '') {
        return 'Sin datos';
    }

    return number_format((float) $valor, 1, ',', '.') . '%';
}

function numeroIndicador(float|int|string|null $valor, int $decimales = 0): string
{
    return number_format((float) ($valor ?? 0), $decimales, ',', '.');
}

function tiempoIndicador(float|int|string|null $minutos): string
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

function anchoIndicador(float|int|string|null $valor, float $maximo): string
{
    $numero = max(0, (float) ($valor ?? 0));

    if ($numero <= 0 || $maximo <= 0) {
        return '0';
    }

    return number_format(max(3, min(100, ($numero / $maximo) * 100)), 2, '.', '');
}

function etiquetaEstadoIndicador(string $estado): string
{
    return match ($estado) {
        'en_proceso' => 'En proceso',
        'pendiente_calificacion' => 'Pendiente de calificación',
        'cerrado' => 'Cerrado',
        'cancelado', 'cancelada' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function claseEstadoIndicador(string $estado): string
{
    return match ($estado) {
        'en_proceso' => 'blue',
        'pendiente_calificacion' => 'amber',
        'cerrado' => 'green',
        'cancelado', 'cancelada' => 'red',
        default => 'gray',
    };
}

function claseUrgenciaIndicador(string $urgencia): string
{
    return match (strtolower($urgencia)) {
        'urgente' => 'red',
        'alta' => 'amber',
        'moderada' => 'blue',
        'baja' => 'green',
        default => 'gray',
    };
}

function porcentajeParteIndicador(float|int|string|null $parte, float|int|string|null $total): float
{
    $totalNumero = (float) ($total ?? 0);

    if ($totalNumero <= 0) {
        return 0;
    }

    return round(100 * (float) ($parte ?? 0) / $totalNumero, 1);
}

function etiquetaTipoCalificacion(string $tipo): string
{
    return match ($tipo) {
        'encuesta_servicio' => 'Encuesta del servicio solicitado',
        'evaluacion_derivacion' => 'Evaluación interna de derivación',
        'evaluacion_caso' => 'Evaluación operativa del caso',
        default => 'Evaluación histórica',
    };
}

$desde = normalizarFechaIndicador($_GET['desde'] ?? '');
$hasta = normalizarFechaIndicador($_GET['hasta'] ?? '');
$idServicioSolucion = filter_input(
    INPUT_GET,
    'id_servicio_solucion',
    FILTER_VALIDATE_INT
) ?: 0;

if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$condiciones = ['t.id_proceso IS NOT NULL'];

if ($desde !== '') {
    $condiciones[] = "t.fecha_creacion >= '{$desde} 00:00:00'";
}

if ($hasta !== '') {
    $condiciones[] = "t.fecha_creacion < DATE_ADD('{$hasta}', INTERVAL 1 DAY)";
}

$whereTickets = implode(' AND ', $condiciones);
$serviciosSolucionFiltro = [];
$resultadoServiciosSolucion = $conn->query(
    "SELECT
        te.id_servicio,
        CONCAT(te.catalogo_nombre, ' / ', te.servicio_nombre) AS nombre
     FROM ticket_etapas AS te
     WHERE te.id_servicio IS NOT NULL
     GROUP BY te.id_servicio, te.catalogo_nombre, te.servicio_nombre
     ORDER BY te.catalogo_nombre, te.servicio_nombre"
);

if ($resultadoServiciosSolucion !== false) {
    $serviciosSolucionFiltro = $resultadoServiciosSolucion->fetch_all(MYSQLI_ASSOC);
}

$idsServicioSolucion = array_map(
    static fn (array $servicio): int => (int) $servicio['id_servicio'],
    $serviciosSolucionFiltro
);

if (
    $idServicioSolucion > 0
    && !in_array($idServicioSolucion, $idsServicioSolucion, true)
) {
    $idServicioSolucion = 0;
}

$filtroSolucionServicio = $idServicioSolucion > 0
    ? ' AND te.id_servicio = ' . $idServicioSolucion
    : '';
$periodo = 'Toda la base histórica';

if ($desde !== '' || $hasta !== '') {
    $periodo = ($desde !== '' ? date('d/m/Y', strtotime($desde)) : 'Inicio')
        . ' — '
        . ($hasta !== '' ? date('d/m/Y', strtotime($hasta)) : 'Hoy');
}

$resumen = [
    'total' => 0,
    'en_proceso' => 0,
    'pendiente_calificacion' => 0,
    'cerrados' => 0,
    'solicitantes' => 0,
    'promedio_ciclo' => null,
];
$resumenEtapas = [
    'etapas' => 0,
    'completadas' => 0,
    'listos_cierre' => 0,
    'pausadas' => 0,
    'derivaciones' => 0,
    'casos_reabiertos' => 0,
    'fuera_sla' => 0,
    'dentro_sla' => 0,
    'evaluadas_sla' => 0,
    'promedio_etapa' => null,
];
$resumenCalificacion = [
    'promedio' => null,
    'promedio_area' => null,
    'promedio_tiempo' => null,
    'calificaciones' => 0,
];
$resumenEncuestaServicio = $resumenCalificacion;
$resumenEvaluacionInterna = $resumenCalificacion;
$porEstado = [];
$porTipo = [];
$porGestor = [];
$porArea = [];
$porServicio = [];
$porSolucionServicio = [];
$comentariosPorSolucion = [];
$tendencia = [];
$porUrgencia = [];
$distribucionCalificacionArea = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$distribucionCalificacionTiempo = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$detalleTickets = [];
$detalleCalificaciones = [];
$errorDashboard = '';
$moduloDisponible = flujoModuloInstalado($conn)
    && flujoModuloSolucionesInstalado($conn)
    && flujoModuloAprobacionCasosInstalado($conn);

if ($moduloDisponible) {
    try {
        $resumen = array_merge($resumen, filaIndicador(
            $conn,
            "SELECT
                COUNT(*) AS total,
                SUM(t.estado_flujo = 'en_proceso') AS en_proceso,
                SUM(t.estado_flujo = 'pendiente_calificacion') AS pendiente_calificacion,
                SUM(t.estado_flujo = 'cerrado') AS cerrados,
                COUNT(DISTINCT t.id_usuario) AS solicitantes,
                AVG(CASE
                    WHEN t.fecha_finalizacion IS NOT NULL THEN
                        TIMESTAMPDIFF(MINUTE, t.fecha_creacion, t.fecha_finalizacion)
                    ELSE NULL
                END) AS promedio_ciclo
             FROM tickets AS t
             WHERE {$whereTickets}"
        ));

        $resumenEtapas = array_merge($resumenEtapas, filaIndicador(
            $conn,
            "SELECT
                COUNT(*) AS etapas,
                SUM(te.estado IN ('listo_cierre', 'completada')) AS completadas,
                SUM(te.estado = 'listo_cierre') AS listos_cierre,
                SUM(te.estado = 'pausada') AS pausadas,
                SUM(te.id_ticket_etapa_padre IS NOT NULL) AS derivaciones,
                SUM(te.cantidad_reaperturas > 0) AS casos_reabiertos,
                SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) = 'fuera_sla') AS fuera_sla,
                SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) = 'dentro_sla') AS dentro_sla,
                SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) IN ('dentro_sla', 'fuera_sla')) AS evaluadas_sla,
                AVG(CASE
                    WHEN te.estado IN ('listo_cierre', 'completada')
                    THEN COALESCE(te.minutos_hasta_listo, te.minutos_atencion)
                END) AS promedio_etapa
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE {$whereTickets}"
        ));

        $resumenCalificacion = array_merge($resumenCalificacion, filaIndicador(
            $conn,
            "SELECT
                AVG(cal.calificacion) AS promedio,
                AVG(cal.calificacion_area) AS promedio_area,
                AVG(cal.calificacion_tiempo) AS promedio_tiempo,
                COUNT(*) AS calificaciones
             FROM solicitud_calificaciones AS cal
             INNER JOIN tickets AS t ON t.id_ticket = cal.id_ticket
             WHERE {$whereTickets}"
        ));

        $resumenEncuestaServicio = array_merge(
            $resumenEncuestaServicio,
            filaIndicador(
                $conn,
                "SELECT
                    AVG(cal.calificacion) AS promedio,
                    AVG(cal.calificacion_area) AS promedio_area,
                    AVG(cal.calificacion_tiempo) AS promedio_tiempo,
                    COUNT(*) AS calificaciones
                 FROM solicitud_calificaciones AS cal
                 INNER JOIN tickets AS t ON t.id_ticket = cal.id_ticket
                 WHERE {$whereTickets}
                   AND cal.tipo_calificacion = 'encuesta_servicio'"
            )
        );

        $resumenEvaluacionInterna = array_merge(
            $resumenEvaluacionInterna,
            filaIndicador(
                $conn,
                "SELECT
                    AVG(cal.calificacion) AS promedio,
                    AVG(cal.calificacion_area) AS promedio_area,
                    AVG(cal.calificacion_tiempo) AS promedio_tiempo,
                    COUNT(*) AS calificaciones
                 FROM solicitud_calificaciones AS cal
                 INNER JOIN tickets AS t ON t.id_ticket = cal.id_ticket
                 WHERE {$whereTickets}
                   AND cal.tipo_calificacion IN ('evaluacion_derivacion', 'evaluacion_caso')"
            )
        );

        $porEstado = filasIndicador(
            $conn,
            "SELECT t.estado_flujo AS nombre, COUNT(*) AS total
             FROM tickets AS t
             WHERE {$whereTickets}
             GROUP BY t.estado_flujo
             ORDER BY total DESC"
        );

        $porTipo = filasIndicador(
            $conn,
            "SELECT p.nombre, COUNT(*) AS total
             FROM tickets AS t
             INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
             WHERE {$whereTickets}
             GROUP BY p.id_proceso, p.nombre
             ORDER BY total DESC, p.nombre
             LIMIT 12"
        );

        $porGestor = filasIndicador(
            $conn,
            "SELECT
                COALESCE(NULLIF(gestor.nombre, ''), NULLIF(te.gestor_nombre, ''), 'Sin asignar') AS nombre,
                COUNT(DISTINCT te.id_ticket) AS tickets,
                COUNT(*) AS etapas,
                SUM(te.estado IN ('listo_cierre', 'completada')) AS completadas,
                SUM(te.estado IN ('pendiente', 'en_proceso', 'en_espera_solicitante')) AS activas,
                AVG(CASE WHEN te.estado IN ('listo_cierre', 'completada') THEN COALESCE(te.minutos_hasta_listo, te.minutos_atencion) END) AS promedio_minutos,
                ROUND(
                    100 * SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) = 'dentro_sla') /
                    NULLIF(SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) IN ('dentro_sla', 'fuera_sla')), 0),
                    1
                ) AS cumplimiento_sla,
                AVG(cal.calificacion) AS calificacion,
                AVG(cal.calificacion_area) AS calificacion_area,
                AVG(cal.calificacion_tiempo) AS calificacion_tiempo,
                COUNT(cal.id_calificacion) AS encuestas
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             LEFT JOIN usuarios AS gestor ON gestor.id_usuario = te.id_gestor
             LEFT JOIN solicitud_calificaciones AS cal
                ON cal.id_ticket_etapa = te.id_ticket_etapa
             WHERE {$whereTickets}
             GROUP BY COALESCE(NULLIF(gestor.nombre, ''), NULLIF(te.gestor_nombre, ''), 'Sin asignar')
             ORDER BY completadas DESC, etapas DESC, nombre"
        );

        $porArea = filasIndicador(
            $conn,
            "SELECT
                te.catalogo_nombre AS nombre,
                COUNT(DISTINCT te.id_ticket) AS tickets,
                COUNT(*) AS etapas,
                SUM(te.estado IN ('listo_cierre', 'completada')) AS completadas,
                SUM(te.estado IN ('pendiente', 'en_proceso', 'en_espera_solicitante')) AS activas,
                AVG(CASE WHEN te.estado IN ('listo_cierre', 'completada') THEN COALESCE(te.minutos_hasta_listo, te.minutos_atencion) END) AS promedio_minutos,
                ROUND(
                    100 * SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) = 'dentro_sla') /
                    NULLIF(SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) IN ('dentro_sla', 'fuera_sla')), 0),
                    1
                ) AS cumplimiento_sla,
                AVG(cal.calificacion) AS calificacion,
                AVG(cal.calificacion_area) AS calificacion_area,
                AVG(cal.calificacion_tiempo) AS calificacion_tiempo,
                COUNT(cal.id_calificacion) AS encuestas
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             LEFT JOIN solicitud_calificaciones AS cal
                ON cal.id_ticket_etapa = te.id_ticket_etapa
             WHERE {$whereTickets}
             GROUP BY te.catalogo_nombre
             ORDER BY etapas DESC, te.catalogo_nombre"
        );

        $porServicio = filasIndicador(
            $conn,
            "SELECT
                CONCAT(te.catalogo_nombre, ' / ', te.servicio_nombre) AS nombre,
                COUNT(*) AS etapas,
                SUM(te.estado IN ('listo_cierre', 'completada')) AS completadas,
                AVG(CASE WHEN te.estado IN ('listo_cierre', 'completada') THEN COALESCE(te.minutos_hasta_listo, te.minutos_atencion) END) AS promedio_minutos,
                ROUND(
                    100 * SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) = 'dentro_sla') /
                    NULLIF(SUM(COALESCE(te.resultado_sla_listo, te.resultado_sla) IN ('dentro_sla', 'fuera_sla')), 0),
                    1
                ) AS cumplimiento_sla,
                AVG(cal.calificacion) AS calificacion,
                AVG(cal.calificacion_area) AS calificacion_area,
                AVG(cal.calificacion_tiempo) AS calificacion_tiempo,
                COUNT(cal.id_calificacion) AS encuestas
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             LEFT JOIN solicitud_calificaciones AS cal
                ON cal.id_ticket_etapa = te.id_ticket_etapa
             WHERE {$whereTickets}
             GROUP BY te.catalogo_nombre, te.servicio_nombre
             ORDER BY etapas DESC, nombre
             LIMIT 15"
        );

        $porSolucionServicio = filasIndicador(
            $conn,
            "SELECT
                te.id_servicio,
                CONCAT(te.catalogo_nombre, ' / ', te.servicio_nombre) AS servicio,
                COALESCE(
                    NULLIF(te.solucion_nombre, ''),
                    'Cierre anterior sin clasificación'
                ) AS solucion,
                COUNT(*) AS total,
                MAX(COALESCE(te.fecha_marcado_listo, te.fecha_finalizacion)) AS ultimo_uso
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE {$whereTickets}
               AND te.estado IN ('listo_cierre', 'completada')
               AND (
                    NULLIF(te.solucion_nombre, '') IS NOT NULL
                    OR NULLIF(te.comentario_cierre, '') IS NOT NULL
               )
               {$filtroSolucionServicio}
             GROUP BY
                te.id_servicio,
                te.catalogo_nombre,
                te.servicio_nombre,
                COALESCE(
                    NULLIF(te.solucion_nombre, ''),
                    'Cierre anterior sin clasificación'
                )
             ORDER BY servicio, total DESC, solucion"
        );

        $comentariosSoluciones = filasIndicador(
            $conn,
            "SELECT
                te.id_servicio,
                te.id_ticket,
                te.id_ticket_etapa,
                COALESCE(
                    NULLIF(te.solucion_nombre, ''),
                    'Cierre anterior sin clasificación'
                ) AS solucion,
                te.comentario_cierre AS comentario,
                COALESCE(
                    NULLIF(gestor.nombre, ''),
                    NULLIF(te.gestor_nombre, ''),
                    'Sin gestor registrado'
                ) AS gestor,
                COALESCE(te.fecha_marcado_listo, te.fecha_finalizacion) AS fecha_registro
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             LEFT JOIN usuarios AS gestor ON gestor.id_usuario = te.id_gestor
             WHERE {$whereTickets}
               AND te.estado IN ('listo_cierre', 'completada')
               AND NULLIF(TRIM(te.comentario_cierre), '') IS NOT NULL
               {$filtroSolucionServicio}
             ORDER BY
                te.id_servicio,
                solucion,
                fecha_registro DESC,
                te.id_ticket DESC"
        );

        foreach ($comentariosSoluciones as $comentarioSolucion) {
            $comentarioSolucion['codigo_caso'] = flujoCodigoCaso(
                $conn,
                (int) $comentarioSolucion['id_ticket'],
                (int) $comentarioSolucion['id_ticket_etapa']
            );
            $claveComentarios = (string) $comentarioSolucion['id_servicio']
                . '|'
                . (string) $comentarioSolucion['solucion'];
            $comentariosPorSolucion[$claveComentarios][] = $comentarioSolucion;
        }

        $tendencia = filasIndicador(
            $conn,
            "SELECT
                DATE_FORMAT(t.fecha_creacion, '%Y-%m') AS clave,
                DATE_FORMAT(t.fecha_creacion, '%m/%Y') AS etiqueta,
                COUNT(*) AS creados,
                SUM(t.fecha_finalizacion IS NOT NULL) AS finalizados
             FROM tickets AS t
             WHERE {$whereTickets}
             GROUP BY clave, etiqueta
             ORDER BY clave"
        );
        $tendencia = array_slice($tendencia, -12);

        $porUrgencia = filasIndicador(
            $conn,
            "SELECT t.urgencia AS nombre, COUNT(*) AS total
             FROM tickets AS t
             WHERE {$whereTickets}
             GROUP BY t.urgencia
             ORDER BY FIELD(t.urgencia, 'urgente', 'alta', 'moderada', 'baja')"
        );

        foreach (filasIndicador(
            $conn,
            "SELECT cal.calificacion_area AS calificacion, COUNT(*) AS total
             FROM solicitud_calificaciones AS cal
             INNER JOIN tickets AS t ON t.id_ticket = cal.id_ticket
             WHERE {$whereTickets}
               AND cal.calificacion_area IS NOT NULL
             GROUP BY cal.calificacion_area"
        ) as $fila) {
            $valor = (int) $fila['calificacion'];
            if (isset($distribucionCalificacionArea[$valor])) {
                $distribucionCalificacionArea[$valor] = (int) $fila['total'];
            }
        }

        foreach (filasIndicador(
            $conn,
            "SELECT cal.calificacion_tiempo AS calificacion, COUNT(*) AS total
             FROM solicitud_calificaciones AS cal
             INNER JOIN tickets AS t ON t.id_ticket = cal.id_ticket
             WHERE {$whereTickets}
               AND cal.calificacion_tiempo IS NOT NULL
             GROUP BY cal.calificacion_tiempo"
        ) as $fila) {
            $valor = (int) $fila['calificacion'];
            if (isset($distribucionCalificacionTiempo[$valor])) {
                $distribucionCalificacionTiempo[$valor] = (int) $fila['total'];
            }
        }

        $detalleTickets = filasIndicador(
            $conn,
            "SELECT
                t.id_ticket,
                p.nombre AS tipo_ticket,
                COALESCE(solicitante.nombre, 'Usuario eliminado') AS solicitante,
                t.estado_flujo,
                t.fecha_creacion,
                COALESCE(te.catalogo_nombre, 'Sin etapa activa') AS area_actual,
                COALESCE(NULLIF(gestor.nombre, ''), NULLIF(te.gestor_nombre, ''), 'Sin gestor activo') AS gestor_actual,
                COALESCE(avance.total_etapas, 0) AS total_etapas,
                COALESCE(avance.completadas, 0) AS etapas_completadas,
                avance.cumplimiento_sla,
                cal.promedio_calificacion,
                cal.promedio_area,
                cal.promedio_tiempo
             FROM tickets AS t
             INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
             LEFT JOIN usuarios AS solicitante ON solicitante.id_usuario = t.id_usuario
             LEFT JOIN ticket_etapas AS te ON te.id_ticket_etapa = t.id_etapa_actual
             LEFT JOIN usuarios AS gestor ON gestor.id_usuario = te.id_gestor
             LEFT JOIN (
                SELECT
                    id_ticket,
                    COUNT(*) AS total_etapas,
                    SUM(estado = 'completada') AS completadas,
                    ROUND(
                        100 * SUM(COALESCE(resultado_sla_listo, resultado_sla) = 'dentro_sla') /
                        NULLIF(SUM(COALESCE(resultado_sla_listo, resultado_sla) IN ('dentro_sla', 'fuera_sla')), 0),
                        1
                    ) AS cumplimiento_sla
                FROM ticket_etapas
                GROUP BY id_ticket
             ) AS avance ON avance.id_ticket = t.id_ticket
             LEFT JOIN (
                SELECT
                    id_ticket,
                    AVG(calificacion) AS promedio_calificacion,
                    AVG(calificacion_area) AS promedio_area,
                    AVG(calificacion_tiempo) AS promedio_tiempo
                FROM solicitud_calificaciones
                GROUP BY id_ticket
             ) AS cal ON cal.id_ticket = t.id_ticket
             WHERE {$whereTickets}
             ORDER BY t.actualizado_en DESC, t.id_ticket DESC"
        );

        $detalleCalificaciones = filasIndicador(
            $conn,
            "SELECT
                t.id_ticket,
                te.id_ticket_etapa,
                te.catalogo_nombre,
                te.servicio_nombre,
                COALESCE(NULLIF(gestor.nombre, ''), NULLIF(te.gestor_nombre, ''), 'Sin asignar') AS gestor,
                COALESCE(evaluador.nombre, 'Usuario eliminado') AS evaluador,
                te.sla_nombre,
                COALESCE(te.minutos_hasta_listo, te.minutos_atencion) AS minutos_atencion,
                COALESCE(te.resultado_sla_listo, te.resultado_sla) AS resultado_sla,
                cal.calificacion,
                cal.calificacion_area,
                cal.calificacion_tiempo,
                cal.tipo_calificacion,
                cal.comentario,
                cal.creado_en
             FROM solicitud_calificaciones AS cal
             INNER JOIN tickets AS t ON t.id_ticket = cal.id_ticket
             INNER JOIN ticket_etapas AS te
                ON te.id_ticket_etapa = cal.id_ticket_etapa
             LEFT JOIN usuarios AS gestor ON gestor.id_usuario = te.id_gestor
             LEFT JOIN usuarios AS evaluador
                ON evaluador.id_usuario = cal.id_solicitante
             WHERE {$whereTickets}
             ORDER BY cal.creado_en DESC, t.id_ticket DESC, te.orden"
        );

        foreach ($detalleCalificaciones as &$evaluacion) {
            $evaluacion['codigo_caso'] = flujoCodigoCaso(
                $conn,
                (int) $evaluacion['id_ticket'],
                (int) $evaluacion['id_ticket_etapa']
            );
        }
        unset($evaluacion);
    } catch (Throwable $e) {
        error_log('Indicadores: ' . $e->getMessage());
        $errorDashboard = 'No fue posible cargar todos los indicadores. Verifique la estructura del módulo de Tickets.';
    }
}

$totalesSolucionPorServicio = [];
foreach ($porSolucionServicio as $filaSolucion) {
    $claveServicio = (string) $filaSolucion['id_servicio'];
    $totalesSolucionPorServicio[$claveServicio] =
        ($totalesSolucionPorServicio[$claveServicio] ?? 0)
        + (int) $filaSolucion['total'];
}

foreach ($porSolucionServicio as &$filaSolucion) {
    $claveServicio = (string) $filaSolucion['id_servicio'];
    $filaSolucion['clave_comentarios'] = $claveServicio
        . '|'
        . (string) $filaSolucion['solucion'];
    $totalServicio = (int) ($totalesSolucionPorServicio[$claveServicio] ?? 0);
    $filaSolucion['porcentaje'] = $totalServicio > 0
        ? round(100 * (int) $filaSolucion['total'] / $totalServicio, 1)
        : 0;
}
unset($filaSolucion);

$evaluadasSla = (int) ($resumenEtapas['evaluadas_sla'] ?? 0);
$cumplimientoSla = $evaluadasSla > 0
    ? round(100 * (int) $resumenEtapas['dentro_sla'] / $evaluadasSla, 1)
    : null;
$promedioCalificacion = $resumenCalificacion['promedio'] !== null
    ? round((float) $resumenCalificacion['promedio'], 2)
    : null;
$promedioCalificacionArea = $resumenCalificacion['promedio_area'] !== null
    ? round((float) $resumenCalificacion['promedio_area'], 2)
    : null;
$promedioCalificacionTiempo = $resumenCalificacion['promedio_tiempo'] !== null
    ? round((float) $resumenCalificacion['promedio_tiempo'], 2)
    : null;
$promedioEncuestaServicio = $resumenEncuestaServicio['promedio'] !== null
    ? round((float) $resumenEncuestaServicio['promedio'], 2)
    : null;
$promedioEvaluacionInterna = $resumenEvaluacionInterna['promedio'] !== null
    ? round((float) $resumenEvaluacionInterna['promedio'], 2)
    : null;
$porcentajeCalificacionArea = $promedioCalificacionArea !== null
    ? min(100, max(0, $promedioCalificacionArea / 5 * 100))
    : 0;
$porcentajeCalificacionTiempo = $promedioCalificacionTiempo !== null
    ? min(100, max(0, $promedioCalificacionTiempo / 5 * 100))
    : 0;
$maxEstado = max(array_merge([0], array_map(static fn (array $fila): int => (int) $fila['total'], $porEstado)));
$maxTipo = max(array_merge([0], array_map(static fn (array $fila): int => (int) $fila['total'], $porTipo)));
$maxGestor = max(array_merge([0], array_map(static fn (array $fila): int => (int) $fila['completadas'], $porGestor)));
$maxArea = max(array_merge([0], array_map(static fn (array $fila): int => (int) $fila['etapas'], $porArea)));
$maxGestorVolumen = max(array_merge([0], array_map(static fn (array $fila): int => max((int) $fila['etapas'], (int) $fila['completadas'], (int) $fila['activas']), $porGestor)));
$maxAreaVolumen = max(array_merge([0], array_map(static fn (array $fila): int => max((int) $fila['etapas'], (int) $fila['completadas'], (int) $fila['activas']), $porArea)));
$maxServicio = max(array_merge([0], array_map(static fn (array $fila): int => max((int) $fila['etapas'], (int) $fila['completadas']), $porServicio)));
$maxSolucion = max(array_merge([0], array_map(static fn (array $fila): int => (int) $fila['total'], $porSolucionServicio)));
$maxUrgencia = max(array_merge([0], array_map(static fn (array $fila): int => (int) $fila['total'], $porUrgencia)));
$maxCalificacionDimension = max(array_merge(
    [0],
    array_values($distribucionCalificacionArea),
    array_values($distribucionCalificacionTiempo)
));
$maxTendencia = max(array_merge(
    [0],
    array_map(
        static fn (array $fila): int => max((int) $fila['creados'], (int) $fila['finalizados']),
        $tendencia
    )
));
$totalEstado = array_sum(array_map(static fn (array $fila): int => (int) $fila['total'], $porEstado));
$totalUrgencia = array_sum(array_map(static fn (array $fila): int => (int) $fila['total'], $porUrgencia));
$totalTipo = array_sum(array_map(static fn (array $fila): int => (int) $fila['total'], $porTipo));
$totalGestorAtendido = array_sum(array_map(static fn (array $fila): int => (int) $fila['completadas'], $porGestor));
$totalArea = array_sum(array_map(static fn (array $fila): int => (int) $fila['etapas'], $porArea));
$totalTendenciaCreados = array_sum(array_map(static fn (array $fila): int => (int) $fila['creados'], $tendencia));
$totalTendenciaFinalizados = array_sum(array_map(static fn (array $fila): int => (int) $fila['finalizados'], $tendencia));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indicadores | Mesa de Servicio</title>
    <style>
        :root{--primary:#0f6fec;--navy:#102a43;--text:#243b53;--muted:#6b7f93;--border:#dce6f0;--bg:#f3f6fb;--surface:#fff;--green:#0f8b72;--amber:#c77a08;--red:#c2413b;--purple:#7554d8}
        *{box-sizing:border-box}body{min-height:100vh;margin:0;color:var(--text);background:var(--bg);font:12px/1.4 Inter,"Segoe UI",Arial,sans-serif}.shell{width:min(1440px,calc(100% - 22px));margin:auto;padding:10px 0 28px}.topbar,.filter,.card,.kpi{border:1px solid var(--border);background:var(--surface);box-shadow:0 5px 18px rgba(16,42,67,.045)}.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:56px;padding:8px 12px 8px 15px;border-radius:12px}.title-wrap{display:flex;align-items:center;gap:10px}.mark{width:34px;height:34px;display:grid;place-items:center;border-radius:9px;color:#fff;background:linear-gradient(145deg,#0f79f1,#0b4fae);font-size:10px;font-weight:850}.topbar h1{margin:0;color:var(--navy);font-size:17px}.topbar p{margin:1px 0 0;color:var(--muted);font-size:10px}.actions{display:flex;gap:6px;flex-wrap:wrap}.btn,button{min-height:31px;display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border:1px solid #d8e5f2;border-radius:8px;color:#24577f;background:#f7fbff;text-decoration:none;font:inherit;font-weight:750;cursor:pointer}.btn.primary,button.primary{border-color:var(--primary);color:#fff;background:var(--primary)}.filter{display:flex;align-items:flex-end;gap:8px;margin-top:8px;padding:9px 12px;border-radius:11px}.filter-title{margin-right:auto}.filter-title strong{display:block;color:var(--navy);font-size:12px}.filter-title span{color:var(--muted);font-size:9.5px}.field label{display:block;margin-bottom:3px;color:#667d92;font-size:9px;font-weight:800;text-transform:uppercase}.field input,.field select{width:160px;height:31px;padding:5px 8px;border:1px solid #cfdae6;border-radius:7px;color:var(--text);background:#fff;font:inherit}.field select{width:270px}.alert{margin-top:8px;padding:10px 12px;border-radius:9px;color:#9b4c12;background:#fff5e9}.kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:7px;margin-top:8px}.kpi{min-height:82px;padding:10px 11px;border-radius:11px}.kpi span{display:block;color:var(--muted);font-size:9.5px;font-weight:750}.kpi strong{display:block;margin-top:5px;color:var(--navy);font-size:20px;line-height:1}.kpi small{display:block;margin-top:5px;color:#8293a4;font-size:8.5px}.dashboard{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:8px;margin-top:8px}.card{grid-column:span 6;overflow:hidden;border-radius:11px}.card.wide{grid-column:span 12}.card.third{grid-column:span 4}.card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:43px;padding:8px 11px;border-bottom:1px solid var(--border)}.card-head h2{margin:0;color:var(--navy);font-size:12.5px}.card-head span{color:var(--muted);font-size:9px}.card-body{padding:11px}.chart-list{display:grid;gap:8px}.chart-row{display:grid;grid-template-columns:minmax(105px,1.1fr) minmax(150px,2fr) auto;align-items:center;gap:8px}.chart-label{overflow:hidden;color:#405b73;font-size:10px;font-weight:650;text-overflow:ellipsis;white-space:nowrap}.track{height:8px;overflow:hidden;border-radius:999px;background:#edf2f7}.bar{height:100%;min-width:0;border-radius:inherit;background:linear-gradient(90deg,var(--primary),#43a0f2)}.bar.green{background:linear-gradient(90deg,#0f8b72,#38b99e)}.bar.purple{background:linear-gradient(90deg,#6b4fd3,#9b83ec)}.bar.amber{background:linear-gradient(90deg,#d18512,#f2b44c)}.chart-value{min-width:34px;text-align:right;color:var(--navy);font-size:9.5px;font-weight:800}.rings{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;align-items:center}.ring-wrap{text-align:center}.ring{--value:0;width:100px;height:100px;display:grid;place-items:center;margin:auto;border-radius:50%;background:conic-gradient(var(--green) calc(var(--value)*1%),#e8eef4 0)}.ring.rating{background:conic-gradient(var(--purple) calc(var(--value)*1%),#e8eef4 0)}.ring.time{background:conic-gradient(var(--amber) calc(var(--value)*1%),#e8eef4 0)}.ring::after{content:"";grid-area:1/1;width:70px;height:70px;border-radius:50%;background:#fff}.ring strong{z-index:1;grid-area:1/1;color:var(--navy);font-size:16px}.ring-wrap span{display:block;margin-top:7px;color:var(--muted);font-size:9.5px}.trend{display:flex;align-items:flex-end;gap:7px;height:175px;padding-top:10px;overflow-x:auto}.trend-item{min-width:55px;flex:1;text-align:center}.trend-bars{height:135px;display:flex;align-items:flex-end;justify-content:center;gap:3px;border-bottom:1px solid #dce5ee}.trend-bar{width:13px;min-height:0;border-radius:4px 4px 0 0;background:#2b86e5}.trend-bar.closed{background:#27a785}.trend-item small{display:block;margin-top:5px;color:#788b9d;font-size:8px}.legend{display:flex;gap:12px;color:var(--muted);font-size:8.5px}.legend i{display:inline-block;width:7px;height:7px;margin-right:4px;border-radius:2px;background:#2b86e5}.legend i.closed{background:#27a785}.table-wrap{max-width:100%;overflow:auto}.table-wrap.tall{max-height:480px}table{width:100%;border-collapse:collapse;min-width:820px}th{position:sticky;top:0;z-index:1;padding:7px 9px;text-align:left;color:#687e92;background:#f7f9fc;border-bottom:1px solid var(--border);font-size:8.5px;font-weight:850;text-transform:uppercase}td{padding:7px 9px;border-bottom:1px solid #edf2f6;color:#3d5a73;font-size:9.5px}tbody tr:hover{background:#f7fbff}.name{color:var(--navy);font-weight:750}.badge{display:inline-flex;padding:3px 6px;border-radius:999px;font-size:8px;font-weight:800;white-space:nowrap}.badge.blue{color:#1767b5;background:#eaf4ff}.badge.green{color:#087443;background:#eaf8f1}.badge.amber{color:#946000;background:#fff4d3}.badge.red{color:#a83b32;background:#fff0ee}.badge.gray{color:#647789;background:#eef2f6}.ticket{color:#0d62c7;font-weight:850;text-decoration:none}.stars{color:#d58b0a;font-weight:850}.progress{display:flex;align-items:center;gap:6px}.progress .track{width:70px;height:6px}.dimension-bars{display:grid;gap:3px;min-width:150px}.dimension-line{display:grid;grid-template-columns:42px 1fr 24px;gap:5px;align-items:center;font-size:8px}.dimension-line .area{color:var(--purple)}.dimension-line .time{color:var(--amber)}.comment-detail summary{cursor:pointer;color:#1767b5;font-size:9px;font-weight:750}.comment-detail p{max-width:340px;margin:5px 0 0;white-space:pre-wrap}.empty{padding:24px;text-align:center;color:var(--muted)}@media(max-width:1180px){.kpis{grid-template-columns:repeat(4,1fr)}.card.third{grid-column:span 6}}@media(max-width:760px){.shell{width:calc(100% - 12px);padding-top:6px}.topbar,.filter{align-items:flex-start;flex-direction:column}.actions{width:100%}.actions .btn{flex:1}.filter-title{margin:0}.filter{display:grid;grid-template-columns:1fr 1fr}.filter-title{grid-column:1/-1}.field input,.field select{width:100%}.kpis{grid-template-columns:repeat(2,1fr)}.card,.card.third{grid-column:span 12}.chart-row{grid-template-columns:100px minmax(100px,1fr) auto}.rings{grid-template-columns:1fr}.ring{width:105px;height:105px}.ring::after{width:73px;height:73px}}@media print{body{background:#fff}.shell{width:100%;padding:0}.actions,.filter{display:none}.topbar,.card,.kpi{box-shadow:none}.table-wrap.tall{max-height:none;overflow:visible}.card{break-inside:avoid}}
        /* Presentación compacta para mostrar más indicadores sin perder legibilidad. */
        body{font-size:11.5px;line-height:1.35;background:linear-gradient(180deg,#eef4fb 0,#f6f8fb 260px,#f3f6fb 100%)}
        .shell{width:min(1580px,calc(100% - 16px));padding:8px 0 24px}
        .topbar{min-height:54px;padding:7px 11px;border-radius:11px;box-shadow:0 6px 18px rgba(16,42,67,.06)}
        .mark{width:34px;height:34px;border-radius:9px;font-size:10px;box-shadow:0 5px 12px rgba(15,111,236,.18)}
        .topbar h1{font-size:17px;letter-spacing:-.15px}.topbar p{margin-top:1px;font-size:9.5px}
        .btn,button{min-height:30px;padding:5px 10px;border-radius:7px;transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease}
        .btn:hover,button:hover{border-color:#9fc6ee;box-shadow:0 4px 10px rgba(15,111,236,.1);transform:translateY(-1px)}
        .filter{gap:7px;margin-top:7px;padding:8px 11px;border-radius:10px;box-shadow:0 5px 15px rgba(16,42,67,.045)}
        .filter-title strong{font-size:11.5px}.filter-title span{font-size:9px}.field label{font-size:8.5px;letter-spacing:.25px}
        .field input,.field select{height:30px;padding:5px 8px;border-radius:7px}.field select{width:270px}
        .kpis{grid-template-columns:repeat(6,minmax(0,1fr));gap:6px;margin-top:7px}
        .kpi{position:relative;min-height:74px;padding:8px 10px 8px 13px;border-radius:10px;overflow:hidden;box-shadow:0 5px 16px rgba(16,42,67,.045)}
        .kpi::before{content:"";position:absolute;inset:0 auto 0 0;width:3px;background:linear-gradient(180deg,var(--primary),#52a5f7)}
        .kpi span{font-size:8.7px;text-transform:uppercase;letter-spacing:.2px}.kpi strong{margin-top:5px;font-size:20px;letter-spacing:-.25px}.kpi small{margin-top:5px;font-size:8px}
        .dashboard{gap:8px;margin-top:8px}.card{border-radius:11px;box-shadow:0 6px 18px rgba(16,42,67,.045)}
        .card-head{min-height:42px;padding:7px 10px;background:linear-gradient(180deg,#fff,#fbfdff)}
        .card-head h2{font-size:12px;letter-spacing:0}.card-head p{margin:2px 0 0;color:var(--muted);font-size:9px}.card-head>span{font-size:8.5px}
        .card-body{padding:10px}.chart-list{gap:5px}.chart-row{grid-template-columns:minmax(105px,1.15fr) minmax(140px,2fr) 56px;gap:8px;padding:6px 7px;border:1px solid #edf2f7;border-radius:8px;background:linear-gradient(90deg,#fff,#fbfdff)}
        .chart-label{font-size:9.3px}.track{height:6px;background:#eaf0f6;box-shadow:inset 0 1px 2px rgba(16,42,67,.05)}.bar{box-shadow:0 1px 3px rgba(15,111,236,.18)}.bar.blue{background:linear-gradient(90deg,#1676df,#52a5f7)}.bar.red{background:linear-gradient(90deg,#bf3d38,#ef716a)}.bar.gray{background:linear-gradient(90deg,#70869b,#a7b5c2)}
        .chart-meta{min-width:56px;text-align:right;line-height:1.05}.chart-meta strong{display:block;color:var(--navy);font-size:9.5px}.chart-meta small{display:block;margin-top:3px;color:#8495a6;font-size:7.5px}.chart-value{font-size:9px}
        .rings{gap:6px;align-items:stretch}.ring-wrap{display:flex;min-width:0;align-items:center;justify-content:center;flex-direction:column;padding:7px 4px;border:1px solid #e7edf4;border-radius:9px;background:linear-gradient(180deg,#fff,#f8fafc)}.ring{width:70px;height:70px;box-shadow:inset 0 0 0 1px rgba(16,42,67,.025)}.ring::after{width:51px;height:51px;box-shadow:0 0 0 1px #edf2f6}.ring strong{font-size:12px}.ring-wrap span{margin-top:5px;font-size:8px;font-weight:700}.ring-wrap small{display:block;margin-top:2px;color:#8a9aaa;font-size:7.5px}
        .trend-card .card-body{padding:8px 10px 9px}.legend{gap:6px}.legend span{display:inline-flex;align-items:center;gap:4px;padding:3px 6px;border:1px solid #e4ebf2;border-radius:999px;background:#fff}.legend i{margin:0}.legend b{color:var(--navy);font-size:8.5px}.trend-chart{display:grid;grid-template-columns:32px minmax(0,1fr);gap:8px;height:152px}.trend-axis{display:flex;flex-direction:column;justify-content:space-between;padding:4px 0 21px;text-align:right;color:#8192a3;font-size:7.5px}.trend-plot{position:relative;min-width:0;padding:3px 5px 0}.trend-grid{position:absolute;inset:3px 5px 21px;border-bottom:1px solid #cad6e2;background:repeating-linear-gradient(to bottom,transparent 0,transparent calc(33.333% - 1px),#e9eff5 calc(33.333% - 1px),#e9eff5 33.333%)}.trend-series{--months:1;position:relative;z-index:1;display:grid;grid-template-columns:repeat(var(--months),minmax(54px,1fr));align-items:end;height:146px;overflow-x:auto}.trend-series.single{width:min(280px,100%);margin:0 auto}.trend-item{min-width:54px;display:grid;grid-template-rows:124px 22px;text-align:center}.trend-bars{height:124px;display:flex;align-items:flex-end;justify-content:center;gap:7px;border:0}.trend-column{position:relative;width:clamp(15px,2vw,23px);height:var(--height);min-height:0;border-radius:5px 5px 2px 2px;background:linear-gradient(180deg,#3793ef,#176fce);box-shadow:0 4px 9px rgba(23,111,206,.2)}.trend-column.closed{background:linear-gradient(180deg,#35b99f,#10866f);box-shadow:0 4px 9px rgba(16,134,111,.18)}.trend-column.has-value{min-height:8px}.trend-value{position:absolute;bottom:calc(100% + 3px);left:50%;min-width:18px;padding:1px 3px;transform:translateX(-50%);border:1px solid #e3eaf1;border-radius:4px;color:#334e68;background:#fff;font-size:7.5px;font-weight:800;line-height:1.25;box-shadow:0 2px 5px rgba(16,42,67,.08)}.trend-item>small{align-self:end;margin:0;padding-top:5px;color:#6f8295;font-size:7.8px;font-weight:700}
        table{min-width:900px}th{padding:6px 8px;font-size:8px;letter-spacing:.2px}td{padding:6px 8px;font-size:9.2px;vertical-align:top}tbody tr:nth-child(even){background:#fbfdff}tbody tr:hover{background:#f1f7ff}
        .badge{padding:3px 6px;font-size:8px}.ticket{font-size:9.5px}.progress .track{width:76px;height:6px}
        .comment-detail summary{display:inline-flex;align-items:center;gap:5px;padding:4px 7px;border:1px solid #cfe1f5;border-radius:7px;color:#1767b5;background:#f5faff;font-size:9px;list-style:none}
        .comment-detail summary::-webkit-details-marker{display:none}.comment-detail summary::before{content:"+";display:grid;place-items:center;width:15px;height:15px;border-radius:50%;color:#fff;background:#2b86e5;font-size:11px;line-height:1}
        .comment-detail[open] summary::before{content:"−"}.comment-detail summary span{display:inline-grid;place-items:center;min-width:19px;height:19px;padding:0 5px;border-radius:999px;color:#fff;background:#2b86e5;font-size:9px}
        .solution-comments{min-width:165px}.solution-comment-list{display:grid;gap:6px;min-width:300px;max-width:480px;max-height:260px;margin-top:6px;padding:7px;overflow:auto;border:1px solid #dce8f4;border-radius:8px;background:#f7faff;box-shadow:0 6px 18px rgba(16,42,67,.07)}
        .solution-comment{padding:8px;border:1px solid #e1eaf3;border-radius:7px;background:#fff}.solution-comment-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.solution-comment-head time{color:var(--muted);font-size:8px;white-space:nowrap}.solution-comment small{display:block;margin-top:2px;color:#6b7f93;font-size:8.5px}.solution-comment p{max-width:none;margin:5px 0 0;color:#334e68;font-size:9.5px;line-height:1.4;white-space:pre-wrap}.usage-count{display:inline-grid;place-items:center;min-width:26px;height:26px;padding:0 7px;border-radius:7px;color:#0b5fc0;background:#eaf4ff;font-weight:850}.solution-progress{min-width:135px}.muted-text{color:#8193a5;font-size:9px}.solutions-table{min-width:1080px}
        @media(max-width:1180px){.shell{width:calc(100% - 14px)}.kpis{grid-template-columns:repeat(4,minmax(0,1fr))}.card.third{grid-column:span 6}.solution-comment-list{min-width:270px}}
        @media(max-width:760px){body{font-size:11px}.shell{width:calc(100% - 10px);padding-top:5px}.topbar{padding:8px}.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.kpi{min-height:70px;padding:8px 9px 8px 12px}.card-head{align-items:flex-start;flex-direction:column}.chart-row{grid-template-columns:95px minmax(90px,1fr) auto}.solution-comment-list{min-width:230px;max-width:270px}.field select{width:100%}}
        @media print{body{font-size:9px;background:#fff}.shell{width:100%;padding:0}.kpis{grid-template-columns:repeat(5,1fr)}.kpi{min-height:72px;padding:9px;box-shadow:none}.kpi strong{font-size:18px}.dashboard{gap:7px}.card-head{min-height:42px;padding:7px 9px}.card-body{padding:9px}th,td{padding:5px 6px}.comment-detail>summary{display:none}.comment-detail>.solution-comment-list,.comment-detail>p{display:block!important;max-height:none;box-shadow:none}.solution-comment-list{min-width:0}}

        /* Resúmenes visuales alimentados por las mismas consultas de cada tabla. */
        .visual-card{grid-column:span 6}.visual-card .card-body{padding:9px 10px 10px}.visual-card .table-wrap{display:none;border-top:1px solid var(--border)}.visual-card.is-table-open .table-wrap{display:block}.visual-card.is-table-open .visual-summary{display:none}
        .visual-summary{min-height:238px}.visual-legend{display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-bottom:7px;color:#6f8295;font-size:7.8px}.visual-legend span{display:inline-flex;align-items:center;gap:4px}.visual-legend i{width:8px;height:8px;border-radius:2px;background:#2b86e5}.visual-legend i.green{background:#21a585}.visual-legend i.amber{background:#e7a72f}.visual-legend i.gray{background:#9aaaba}
        .metric-chart{display:grid;gap:5px}.metric-row{width:100%;min-height:27px;display:grid;grid-template-columns:minmax(105px,31%) minmax(150px,1fr) 52px;align-items:center;gap:7px;padding:4px 6px;border:1px solid transparent;border-radius:7px;color:inherit;background:transparent;text-align:left;transform:none}
        .metric-row:hover,.metric-row:focus{border-color:#d8e7f5;background:#f7fbff;box-shadow:none;transform:none;outline:none}.metric-name{overflow:hidden;color:#334e68;font-size:8.5px;font-weight:750;text-overflow:ellipsis;white-space:nowrap}.metric-bars{display:grid;gap:3px}.metric-line{display:grid;grid-template-columns:1fr 25px;align-items:center;gap:5px}.metric-track{height:5px;overflow:hidden;border-radius:999px;background:#edf2f7}.metric-fill{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#176fce,#52a5f7)}.metric-fill.green{background:linear-gradient(90deg,#10866f,#42bea6)}.metric-fill.amber{background:linear-gradient(90deg,#c77a08,#f2b94f)}.metric-fill.gray{background:linear-gradient(90deg,#758a9d,#b2bdc7)}.metric-line small{color:#657a8e;font-size:7.5px;text-align:right}.metric-score{display:grid;justify-items:end;gap:1px}.metric-score strong{color:#173f63;font-size:9px}.metric-score small{color:#7b8ea0;font-size:7px}
        .visual-empty{min-height:210px;display:grid;place-items:center;color:#7c90a3;font-size:9px}.table-mode-toggle{min-width:78px;min-height:27px;padding:4px 7px;border-color:#d5e3ef;color:#315f84;background:#fff;font-size:8.3px}.table-mode-toggle::before{content:"▦";font-size:11px}.visual-card.is-table-open .table-mode-toggle::before{content:"▥"}
        .chart-caption{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:7px;padding-top:6px;border-top:1px solid #edf2f6;color:#8192a3;font-size:7.7px}.chart-caption b{color:#46627b;font-weight:750}

        /* Navegación compacta: cada bloque puede contraerse y el detalle se consulta en un panel lateral. */
        .view-controls{display:flex;align-items:center;gap:5px;padding-left:5px;border-left:1px solid #dce6f0}
        .view-controls button{min-height:28px;padding:4px 8px;color:#365f82;background:#fff;font-size:8.7px}
        .card-head-main{min-width:0;flex:1}.card-head-actions{display:flex;align-items:center;justify-content:flex-end;gap:7px;margin-left:auto}
        .card-head-actions>.card-meta{max-width:420px;color:var(--muted);font-size:8.5px;text-align:right}
        .card-toggle{min-width:92px;min-height:27px;padding:4px 8px;border-color:#d4e2ef;color:#24577f;background:#f8fbff;font-size:8.5px;white-space:nowrap}
        .card-toggle::before{content:"−";display:grid;place-items:center;width:15px;height:15px;border-radius:5px;color:#fff;background:#5d7892;font-size:12px;line-height:1}
        .card.is-collapsed .card-toggle::before{content:"+";background:#1475d3}.card.is-collapsed .card-head{border-bottom-color:transparent}
        .card.is-collapsed>:not(.card-head){display:none}.card.is-collapsed{align-self:start}.card.is-collapsed .card-head{min-height:38px;background:#fff}
        .card.is-collapsed .card-head-main p{display:none}.card.is-collapsed .card-head-actions>.card-meta{display:none}
        .selectable-row{cursor:pointer;transition:background .16s ease,box-shadow .16s ease}.selectable-row:focus{position:relative;z-index:2;outline:2px solid #75afe9;outline-offset:-2px;background:#f1f7ff}
        .selectable-row td:first-child{position:relative;padding-right:27px}.selectable-row td:first-child::after{content:"›";position:absolute;top:50%;right:9px;display:grid;place-items:center;width:16px;height:16px;transform:translateY(-50%);border-radius:5px;color:#1767b5;background:#eaf4ff;font-size:16px;font-weight:800;line-height:1}
        .selectable-row:hover td:first-child::after{color:#fff;background:#1767b5}.row-hint{color:#71879b;font-size:8px}
        .drawer-backdrop{position:fixed;z-index:80;inset:0;background:rgba(15,35,55,.28);backdrop-filter:blur(1px)}
        .detail-drawer{position:fixed;z-index:90;top:0;right:0;width:min(540px,94vw);height:100vh;display:flex;flex-direction:column;transform:translateX(102%);border-left:1px solid #cad8e6;background:#f5f8fc;box-shadow:-18px 0 45px rgba(16,42,67,.2);transition:transform .22s ease}
        .detail-drawer.is-open{transform:translateX(0)}.drawer-head{display:flex;align-items:flex-start;gap:12px;padding:16px 17px 13px;border-bottom:1px solid #dce6f0;background:#fff}
        .drawer-icon{flex:0 0 auto;width:34px;height:34px;display:grid;place-items:center;border-radius:9px;color:#fff;background:linear-gradient(145deg,#0f79f1,#0b4fae);font-size:12px;font-weight:850}
        .drawer-title{min-width:0;flex:1}.drawer-title span{display:block;color:#70859a;font-size:8.5px;font-weight:800;letter-spacing:.4px;text-transform:uppercase}.drawer-title h2{overflow:hidden;margin:2px 0 0;color:var(--navy);font-size:16px;line-height:1.25;text-overflow:ellipsis;white-space:nowrap}
        .drawer-close{flex:0 0 auto;width:31px;min-width:31px;height:31px;min-height:31px;padding:0;border-radius:50%;color:#49657d;background:#f3f7fb;font-size:17px}
        .drawer-body{flex:1;padding:13px 15px 20px;overflow:auto}.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.detail-item{min-width:0;padding:9px 10px;border:1px solid #e0e8f0;border-radius:8px;background:#fff}
        .detail-item span{display:block;margin-bottom:3px;color:#718599;font-size:7.8px;font-weight:800;letter-spacing:.25px;text-transform:uppercase}.detail-item strong{display:block;overflow-wrap:anywhere;color:#243b53;font-size:10px;line-height:1.35}.detail-item.full{grid-column:1/-1}
        .drawer-section{margin-top:12px;padding:11px;border:1px solid #dde7f0;border-radius:10px;background:#fff}.drawer-section h3{margin:0 0 8px;color:var(--navy);font-size:10px}.related-list{display:grid;gap:5px;margin:0;padding:0;list-style:none}.related-row{width:100%;min-height:34px;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 9px;border:1px solid #e4ebf2;border-radius:7px;color:#334e68;background:#f9fbfd;text-align:left;font-size:8.8px}.related-row:hover{border-color:#b8d3ed;background:#f1f7ff;transform:none}.related-row b{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.related-row span{flex:0 0 auto;color:#6e8295;font-size:8px}
        .drawer-empty{margin:0;color:#7d90a2;font-size:9px}.drawer-foot{display:flex;align-items:center;justify-content:flex-end;gap:7px;padding:10px 15px;border-top:1px solid #dce6f0;background:#fff}.drawer-foot .btn{min-height:29px;font-size:9px}.drawer-foot .ticket-action[hidden]{display:none}
        body.drawer-open{overflow:hidden}
        @media(max-width:900px){.view-controls{width:100%;justify-content:flex-end;padding:6px 0 0;border-top:1px solid #e4ebf2;border-left:0}.topbar{flex-wrap:wrap}.card-head-actions>.card-meta{display:none}.visual-card{grid-column:span 12}}
        @media(max-width:760px){.card-head{align-items:center;flex-direction:row}.card-head-main{overflow:hidden}.card-head-main h2{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.card-toggle{min-width:82px}.detail-grid{grid-template-columns:1fr}.detail-item.full{grid-column:auto}.drawer-head{padding:13px}.drawer-body{padding:10px}.view-controls button{flex:1}.metric-row{grid-template-columns:minmax(88px,32%) minmax(105px,1fr) 43px}.visual-summary{min-height:215px}}
        @media print{.view-controls,.card-toggle,.drawer-backdrop,.detail-drawer{display:none!important}.card.is-collapsed>:not(.card-head){display:block!important}.card.is-collapsed .card-head{border-bottom-color:var(--border)}.selectable-row td:first-child::after{display:none}}
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="title-wrap"><div class="mark">BI</div><div><h1>Indicadores de Tickets</h1><p><?= escaparIndicador($periodo) ?> · actualizado <?= date('d/m/Y H:i') ?></p></div></div>
        <nav class="actions">
            <div class="view-controls" aria-label="Controles de visualización">
                <button id="collapseAllCards" type="button">Minimizar todo</button>
                <button id="expandAllCards" type="button">Maximizar todo</button>
            </div>
            <button type="button" onclick="window.print()">Imprimir reporte</button>
            <a class="btn primary" href="descargarSolicitudesExcel.php">Descargar base</a>
            <a class="btn" href="solicitudes.php">Ver tickets</a>
            <a class="btn" href="panelAdmin.php">Volver</a>
        </nav>
    </header>

    <form class="filter" method="get">
        <div class="filter-title"><strong>Periodo de análisis</strong><span>Sin fechas se analiza toda la base histórica.</span></div>
        <div class="field"><label for="desde">Desde</label><input id="desde" type="date" name="desde" value="<?= escaparIndicador($desde) ?>"></div>
        <div class="field"><label for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" value="<?= escaparIndicador($hasta) ?>"></div>
        <div class="field"><label for="id_servicio_solucion">Analizar soluciones de</label><select id="id_servicio_solucion" name="id_servicio_solucion"><option value="0">Todos los servicios</option><?php foreach ($serviciosSolucionFiltro as $servicioFiltro): ?><option value="<?= (int) $servicioFiltro['id_servicio'] ?>" <?= (int) $servicioFiltro['id_servicio'] === $idServicioSolucion ? 'selected' : '' ?>><?= escaparIndicador($servicioFiltro['nombre']) ?></option><?php endforeach; ?></select></div>
        <button class="primary" type="submit">Aplicar</button><a class="btn" href="indicadores.php">Limpiar</a>
    </form>

    <?php if (!$moduloDisponible): ?><div class="alert">El módulo de Tickets todavía no está instalado por completo.</div><?php endif; ?>
    <?php if ($errorDashboard !== ''): ?><div class="alert"><?= escaparIndicador($errorDashboard) ?></div><?php endif; ?>

    <section class="kpis" aria-label="Resumen general">
        <article class="kpi"><span>Tickets totales</span><strong><?= numeroIndicador($resumen['total']) ?></strong><small><?= numeroIndicador($resumen['solicitantes']) ?> solicitantes</small></article>
        <article class="kpi"><span>Casos del flujo</span><strong><?= numeroIndicador($resumenEtapas['etapas']) ?></strong><small>Padres, hijos y descendientes</small></article>
        <article class="kpi"><span>Derivaciones creadas</span><strong><?= numeroIndicador($resumenEtapas['derivaciones']) ?></strong><small>Casos hijos registrados</small></article>
        <article class="kpi"><span>En proceso</span><strong><?= numeroIndicador($resumen['en_proceso']) ?></strong><small>Actualmente activos</small></article>
        <article class="kpi"><span>Casos con SLA pausado</span><strong><?= numeroIndicador($resumenEtapas['pausadas']) ?></strong><small>Esperando el cierre de hijos</small></article>
        <article class="kpi"><span>Casos reabiertos</span><strong><?= numeroIndicador($resumenEtapas['casos_reabiertos']) ?></strong><small>Con una o más reaperturas</small></article>
        <article class="kpi"><span>Pendientes encuesta</span><strong><?= numeroIndicador($resumen['pendiente_calificacion']) ?></strong><small>Esperando calificación</small></article>
        <article class="kpi"><span>Listos pendientes de cierre</span><strong><?= numeroIndicador($resumenEtapas['listos_cierre']) ?></strong><small>Esperando decisión del creador</small></article>
        <article class="kpi"><span>Tickets cerrados</span><strong><?= numeroIndicador($resumen['cerrados']) ?></strong><small>Proceso finalizado</small></article>
        <article class="kpi"><span>Cumplimiento SLA</span><strong><?= porcentajeIndicador($cumplimientoSla) ?></strong><small><?= numeroIndicador($evaluadasSla) ?> etapas evaluadas</small></article>
        <article class="kpi"><span>Casos fuera del SLA</span><strong><?= numeroIndicador($resumenEtapas['fuera_sla']) ?></strong><small>Según el corte oficial en Listo</small></article>
        <article class="kpi"><span>Calificación general</span><strong><?= $promedioCalificacion === null ? 'Sin datos' : numeroIndicador($promedioCalificacion, 2) . '/5' ?></strong><small><?= numeroIndicador($resumenCalificacion['calificaciones']) ?> evaluaciones</small></article>
        <article class="kpi"><span>Calificación de áreas</span><strong><?= $promedioCalificacionArea === null ? 'Sin datos' : numeroIndicador($promedioCalificacionArea, 2) . '/5' ?></strong><small>Gestión percibida</small></article>
        <article class="kpi"><span>Calificación de tiempos</span><strong><?= $promedioCalificacionTiempo === null ? 'Sin datos' : numeroIndicador($promedioCalificacionTiempo, 2) . '/5' ?></strong><small>Oportunidad percibida</small></article>
        <article class="kpi"><span>Encuesta del servicio solicitado</span><strong><?= $promedioEncuestaServicio === null ? 'Sin datos' : numeroIndicador($promedioEncuestaServicio, 2) . '/5' ?></strong><small><?= numeroIndicador($resumenEncuestaServicio['calificaciones']) ?> encuestas únicas</small></article>
        <article class="kpi"><span>Evaluaciones internas</span><strong><?= $promedioEvaluacionInterna === null ? 'Sin datos' : numeroIndicador($promedioEvaluacionInterna, 2) . '/5' ?></strong><small><?= numeroIndicador($resumenEvaluacionInterna['calificaciones']) ?> casos y derivaciones</small></article>
        <article class="kpi"><span>Tiempo promedio por área</span><strong><?= tiempoIndicador($resumenEtapas['promedio_etapa']) ?></strong><small>Minutos hábiles de atención</small></article>
        <article class="kpi"><span>Ciclo promedio del ticket</span><strong><?= tiempoIndicador($resumen['promedio_ciclo']) ?></strong><small>Creación hasta cierre</small></article>
    </section>

    <section class="dashboard">
        <article class="card wide trend-card"><div class="card-head"><h2>Evolución de tickets</h2><div class="legend"><span><i></i>Creados <b><?= numeroIndicador($totalTendenciaCreados) ?></b></span><span><i class="closed"></i>Finalizados <b><?= numeroIndicador($totalTendenciaFinalizados) ?></b></span></div></div><div class="card-body">
            <?php if (!$tendencia): ?><div class="empty">No hay información para este periodo.</div><?php else: ?>
                <div class="trend-chart" role="img" aria-label="Comparativo mensual de tickets creados y finalizados">
                    <div class="trend-axis"><span><?= numeroIndicador($maxTendencia) ?></span><span><?= numeroIndicador($maxTendencia / 2, $maxTendencia < 2 ? 1 : 0) ?></span><span>0</span></div>
                    <div class="trend-plot">
                        <div class="trend-grid" aria-hidden="true"></div>
                        <div class="trend-series <?= count($tendencia) === 1 ? 'single' : '' ?>" style="--months:<?= count($tendencia) ?>">
                            <?php foreach ($tendencia as $mes): ?>
                                <?php $creadosMes = (int) $mes['creados']; $finalizadosMes = (int) $mes['finalizados']; ?>
                                <div class="trend-item" title="<?= escaparIndicador($mes['etiqueta']) ?>: <?= $creadosMes ?> creados, <?= $finalizadosMes ?> finalizados">
                                    <div class="trend-bars">
                                        <div class="trend-column <?= $creadosMes > 0 ? 'has-value' : '' ?>" style="--height:<?= anchoIndicador($creadosMes, $maxTendencia) ?>%"><span class="trend-value"><?= $creadosMes ?></span></div>
                                        <div class="trend-column closed <?= $finalizadosMes > 0 ? 'has-value' : '' ?>" style="--height:<?= anchoIndicador($finalizadosMes, $maxTendencia) ?>%"><span class="trend-value"><?= $finalizadosMes ?></span></div>
                                    </div>
                                    <small><?= escaparIndicador($mes['etiqueta']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div></article>

        <article class="card third"><div class="card-head"><h2>Tickets por estado</h2><span>Distribución actual</span></div><div class="card-body chart-list">
            <?php foreach ($porEstado as $fila): ?><div class="chart-row"><span class="chart-label"><?= escaparIndicador(etiquetaEstadoIndicador((string)$fila['nombre'])) ?></span><span class="track"><span class="bar <?= escaparIndicador(claseEstadoIndicador((string)$fila['nombre'])) ?>" style="width:<?= anchoIndicador($fila['total'],$maxEstado) ?>%"></span></span><span class="chart-meta"><strong><?= (int)$fila['total'] ?></strong><small><?= numeroIndicador(porcentajeParteIndicador($fila['total'], $totalEstado), 1) ?>%</small></span></div><?php endforeach; ?>
            <?php if(!$porEstado): ?><div class="empty">Sin datos.</div><?php endif; ?>
        </div></article>

        <article class="card third"><div class="card-head"><h2>Cumplimiento y satisfacción</h2><span>Etapas evaluadas</span></div><div class="card-body rings">
            <div class="ring-wrap"><div class="ring" style="--value:<?= escaparIndicador((string)($cumplimientoSla ?? 0)) ?>"><strong><?= porcentajeIndicador($cumplimientoSla) ?></strong></div><span>Cumplimiento SLA</span><small><?= numeroIndicador($evaluadasSla) ?> etapas</small></div>
            <div class="ring-wrap"><div class="ring rating" style="--value:<?= escaparIndicador((string)$porcentajeCalificacionArea) ?>"><strong><?= $promedioCalificacionArea === null?'—':numeroIndicador($promedioCalificacionArea,2) ?></strong></div><span>Gestión del área</span><small>Escala de 1 a 5</small></div>
            <div class="ring-wrap"><div class="ring time" style="--value:<?= escaparIndicador((string)$porcentajeCalificacionTiempo) ?>"><strong><?= $promedioCalificacionTiempo === null?'—':numeroIndicador($promedioCalificacionTiempo,2) ?></strong></div><span>Tiempo de respuesta</span><small>Escala de 1 a 5</small></div>
        </div></article>

        <article class="card third"><div class="card-head"><h2>Tickets por urgencia</h2><span>Clasificación de entrada</span></div><div class="card-body chart-list">
            <?php foreach ($porUrgencia as $fila): ?><div class="chart-row"><span class="chart-label"><?= escaparIndicador(ucfirst((string)$fila['nombre'])) ?></span><span class="track"><span class="bar <?= escaparIndicador(claseUrgenciaIndicador((string)$fila['nombre'])) ?>" style="width:<?= anchoIndicador($fila['total'],$maxUrgencia) ?>%"></span></span><span class="chart-meta"><strong><?= (int)$fila['total'] ?></strong><small><?= numeroIndicador(porcentajeParteIndicador($fila['total'], $totalUrgencia), 1) ?>%</small></span></div><?php endforeach; ?>
            <?php if(!$porUrgencia): ?><div class="empty">Sin datos.</div><?php endif; ?>
        </div></article>

        <article class="card"><div class="card-head"><h2>Tickets por tipo</h2><span>Tipos con mayor demanda</span></div><div class="card-body chart-list">
            <?php foreach ($porTipo as $fila): ?><div class="chart-row"><span class="chart-label" title="<?= escaparIndicador($fila['nombre']) ?>"><?= escaparIndicador($fila['nombre']) ?></span><span class="track"><span class="bar purple" style="width:<?= anchoIndicador($fila['total'],$maxTipo) ?>%"></span></span><span class="chart-meta"><strong><?= (int)$fila['total'] ?></strong><small><?= numeroIndicador(porcentajeParteIndicador($fila['total'], $totalTipo), 1) ?>%</small></span></div><?php endforeach; ?>
            <?php if(!$porTipo): ?><div class="empty">Sin datos.</div><?php endif; ?>
        </div></article>

        <article class="card"><div class="card-head"><h2>Distribución de calificaciones</h2><span>Área frente a tiempo de respuesta</span></div><div class="card-body chart-list">
            <?php for($estrella=5;$estrella>=1;$estrella--): ?><div class="chart-row"><span class="chart-label stars"><?= $estrella ?> ★</span><span class="dimension-bars"><span class="dimension-line"><b class="area">Área</b><span class="track"><span class="bar purple" style="width:<?= anchoIndicador($distribucionCalificacionArea[$estrella],$maxCalificacionDimension) ?>%"></span></span><strong><?= $distribucionCalificacionArea[$estrella] ?></strong></span><span class="dimension-line"><b class="time">Tiempo</b><span class="track"><span class="bar amber" style="width:<?= anchoIndicador($distribucionCalificacionTiempo[$estrella],$maxCalificacionDimension) ?>%"></span></span><strong><?= $distribucionCalificacionTiempo[$estrella] ?></strong></span></span><strong class="chart-value"><?= $distribucionCalificacionArea[$estrella] + $distribucionCalificacionTiempo[$estrella] ?></strong></div><?php endfor; ?>
        </div></article>

        <article class="card visual-card" data-card-key="gestores">
            <div class="card-head">
                <div class="card-head-main"><h2>Desempeño detallado por gestor</h2><p>Asignados, atendidos, activos y cumplimiento.</p></div>
                <button class="table-mode-toggle" type="button">Ver tabla</button>
            </div>
            <div class="card-body visual-summary">
                <div class="visual-legend"><span><i></i>Asignados</span><span><i class="green"></i>Atendidos</span><span><i class="amber"></i>Activos</span></div>
                <div class="metric-chart">
                    <?php foreach (array_slice($porGestor, 0, 8) as $fila): ?>
                        <button class="metric-row detail-trigger" type="button" data-detail-kind="gestor" data-detail-title="Gestor: <?= escaparIndicador($fila['nombre']) ?>">
                            <span class="metric-name" title="<?= escaparIndicador($fila['nombre']) ?>"><?= escaparIndicador($fila['nombre']) ?></span>
                            <span class="metric-bars">
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill" style="width:<?= anchoIndicador($fila['etapas'], $maxGestorVolumen) ?>%"></span></span><small><?= (int) $fila['etapas'] ?></small></span>
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill green" style="width:<?= anchoIndicador($fila['completadas'], $maxGestorVolumen) ?>%"></span></span><small><?= (int) $fila['completadas'] ?></small></span>
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill amber" style="width:<?= anchoIndicador($fila['activas'], $maxGestorVolumen) ?>%"></span></span><small><?= (int) $fila['activas'] ?></small></span>
                            </span>
                            <span class="metric-score"><strong><?= porcentajeIndicador($fila['cumplimiento_sla']) ?></strong><small>SLA</small></span>
                        </button>
                    <?php endforeach; ?>
                    <?php if (!$porGestor): ?><div class="visual-empty">Sin datos de gestores.</div><?php endif; ?>
                </div>
                <div class="chart-caption"><span>Top 8 por volumen del periodo</span><b>Selecciona un gestor para ver sus métricas</b></div>
            </div>
            <div class="table-wrap"><table><thead><tr><th>Gestor</th><th>Tickets</th><th>Casos asignados</th><th>Atendidos</th><th>Activos</th><th>Tiempo hasta Listo</th><th>Cumplimiento SLA</th><th>Gestión del área</th><th>Tiempo de respuesta</th><th>General</th><th>Evaluaciones</th></tr></thead><tbody>
                <?php foreach($porGestor as $fila): ?><tr class="selectable-row" data-detail-kind="gestor" data-detail-title="Gestor: <?= escaparIndicador($fila['nombre']) ?>"><td class="name"><?= escaparIndicador($fila['nombre']) ?></td><td><?= numeroIndicador($fila['tickets']) ?></td><td><?= numeroIndicador($fila['etapas']) ?></td><td><?= numeroIndicador($fila['completadas']) ?></td><td><?= numeroIndicador($fila['activas']) ?></td><td><?= escaparIndicador(tiempoIndicador($fila['promedio_minutos'])) ?></td><td><?= escaparIndicador(porcentajeIndicador($fila['cumplimiento_sla'])) ?></td><td class="stars"><?= $fila['calificacion_area']===null?'Sin datos':numeroIndicador($fila['calificacion_area'],2).' ★' ?></td><td class="stars"><?= $fila['calificacion_tiempo']===null?'Sin datos':numeroIndicador($fila['calificacion_tiempo'],2).' ★' ?></td><td class="stars"><?= $fila['calificacion']===null?'Sin datos':numeroIndicador($fila['calificacion'],2).' ★' ?></td><td><?= numeroIndicador($fila['encuestas']) ?></td></tr><?php endforeach; ?>
                <?php if(!$porGestor): ?><tr><td colspan="11" class="empty">Sin datos de gestores.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>

        <article class="card visual-card" data-card-key="areas">
            <div class="card-head">
                <div class="card-head-main"><h2>Desempeño por área</h2><p>Casos recibidos, atendidos, activos y SLA.</p></div>
                <button class="table-mode-toggle" type="button">Ver tabla</button>
            </div>
            <div class="card-body visual-summary">
                <div class="visual-legend"><span><i></i>Recibidos</span><span><i class="green"></i>Atendidos</span><span><i class="amber"></i>Activos</span></div>
                <div class="metric-chart">
                    <?php foreach (array_slice($porArea, 0, 8) as $fila): ?>
                        <button class="metric-row detail-trigger" type="button" data-detail-kind="area" data-detail-title="Área: <?= escaparIndicador($fila['nombre']) ?>" data-area="<?= escaparIndicador($fila['nombre']) ?>">
                            <span class="metric-name" title="<?= escaparIndicador($fila['nombre']) ?>"><?= escaparIndicador($fila['nombre']) ?></span>
                            <span class="metric-bars">
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill" style="width:<?= anchoIndicador($fila['etapas'], $maxAreaVolumen) ?>%"></span></span><small><?= (int) $fila['etapas'] ?></small></span>
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill green" style="width:<?= anchoIndicador($fila['completadas'], $maxAreaVolumen) ?>%"></span></span><small><?= (int) $fila['completadas'] ?></small></span>
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill amber" style="width:<?= anchoIndicador($fila['activas'], $maxAreaVolumen) ?>%"></span></span><small><?= (int) $fila['activas'] ?></small></span>
                            </span>
                            <span class="metric-score"><strong><?= porcentajeIndicador($fila['cumplimiento_sla']) ?></strong><small>SLA</small></span>
                        </button>
                    <?php endforeach; ?>
                    <?php if (!$porArea): ?><div class="visual-empty">Sin datos de áreas.</div><?php endif; ?>
                </div>
                <div class="chart-caption"><span>Top 8 por casos recibidos</span><b>Selecciona un área para ver servicios y tickets</b></div>
            </div>
            <div class="table-wrap"><table><thead><tr><th>Área</th><th>Tickets</th><th>Casos recibidos</th><th>Atendidos</th><th>Activos</th><th>Tiempo hasta Listo</th><th>Cumplimiento SLA</th><th>Gestión del área</th><th>Tiempo de respuesta</th><th>General</th><th>Evaluaciones</th></tr></thead><tbody>
                <?php foreach($porArea as $fila): ?><tr class="selectable-row" data-detail-kind="area" data-detail-title="Área: <?= escaparIndicador($fila['nombre']) ?>" data-area="<?= escaparIndicador($fila['nombre']) ?>"><td class="name"><?= escaparIndicador($fila['nombre']) ?></td><td><?= numeroIndicador($fila['tickets']) ?></td><td><?= numeroIndicador($fila['etapas']) ?></td><td><?= numeroIndicador($fila['completadas']) ?></td><td><?= numeroIndicador($fila['activas']) ?></td><td><?= escaparIndicador(tiempoIndicador($fila['promedio_minutos'])) ?></td><td><?= escaparIndicador(porcentajeIndicador($fila['cumplimiento_sla'])) ?></td><td class="stars"><?= $fila['calificacion_area']===null?'Sin datos':numeroIndicador($fila['calificacion_area'],2).' ★' ?></td><td class="stars"><?= $fila['calificacion_tiempo']===null?'Sin datos':numeroIndicador($fila['calificacion_tiempo'],2).' ★' ?></td><td class="stars"><?= $fila['calificacion']===null?'Sin datos':numeroIndicador($fila['calificacion'],2).' ★' ?></td><td><?= numeroIndicador($fila['encuestas']) ?></td></tr><?php endforeach; ?>
                <?php if(!$porArea): ?><tr><td colspan="11" class="empty">Sin datos de áreas.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>

        <article class="card visual-card" data-card-key="servicios">
            <div class="card-head">
                <div class="card-head-main"><h2>Servicios con mayor volumen</h2><p>Comparativo de casos y atenciones por servicio.</p></div>
                <button class="table-mode-toggle" type="button">Ver tabla</button>
            </div>
            <div class="card-body visual-summary">
                <div class="visual-legend"><span><i></i>Casos</span><span><i class="green"></i>Atendidos</span></div>
                <div class="metric-chart">
                    <?php foreach (array_slice($porServicio, 0, 8) as $fila): ?>
                        <?php $partesServicio = explode(' / ', (string) $fila['nombre'], 2); $areaServicio = $partesServicio[0] ?? ''; ?>
                        <button class="metric-row detail-trigger" type="button" data-detail-kind="servicio" data-detail-title="Servicio: <?= escaparIndicador($fila['nombre']) ?>" data-area="<?= escaparIndicador($areaServicio) ?>">
                            <span class="metric-name" title="<?= escaparIndicador($fila['nombre']) ?>"><?= escaparIndicador($fila['nombre']) ?></span>
                            <span class="metric-bars">
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill" style="width:<?= anchoIndicador($fila['etapas'], $maxServicio) ?>%"></span></span><small><?= (int) $fila['etapas'] ?></small></span>
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill green" style="width:<?= anchoIndicador($fila['completadas'], $maxServicio) ?>%"></span></span><small><?= (int) $fila['completadas'] ?></small></span>
                            </span>
                            <span class="metric-score"><strong><?= porcentajeIndicador($fila['cumplimiento_sla']) ?></strong><small>SLA</small></span>
                        </button>
                    <?php endforeach; ?>
                    <?php if (!$porServicio): ?><div class="visual-empty">Sin datos de servicios.</div><?php endif; ?>
                </div>
                <div class="chart-caption"><span>Top 8 por volumen del periodo</span><b>Selecciona un servicio para ver el detalle</b></div>
            </div>
            <div class="table-wrap"><table><thead><tr><th>Área / servicio</th><th>Casos</th><th>Atendidos</th><th>Tiempo hasta Listo</th><th>Cumplimiento SLA</th><th>Gestión del área</th><th>Tiempo de respuesta</th><th>General</th><th>Evaluaciones</th></tr></thead><tbody>
                <?php foreach($porServicio as $fila): ?><?php $partesServicio = explode(' / ', (string) $fila['nombre'], 2); $areaServicio = $partesServicio[0] ?? ''; ?><tr class="selectable-row" data-detail-kind="servicio" data-detail-title="Servicio: <?= escaparIndicador($fila['nombre']) ?>" data-area="<?= escaparIndicador($areaServicio) ?>"><td class="name"><?= escaparIndicador($fila['nombre']) ?></td><td><?= numeroIndicador($fila['etapas']) ?></td><td><?= numeroIndicador($fila['completadas']) ?></td><td><?= escaparIndicador(tiempoIndicador($fila['promedio_minutos'])) ?></td><td><?= escaparIndicador(porcentajeIndicador($fila['cumplimiento_sla'])) ?></td><td class="stars"><?= $fila['calificacion_area']===null?'Sin datos':numeroIndicador($fila['calificacion_area'],2).' ★' ?></td><td class="stars"><?= $fila['calificacion_tiempo']===null?'Sin datos':numeroIndicador($fila['calificacion_tiempo'],2).' ★' ?></td><td class="stars"><?= $fila['calificacion']===null?'Sin datos':numeroIndicador($fila['calificacion'],2).' ★' ?></td><td><?= numeroIndicador($fila['encuestas']) ?></td></tr><?php endforeach; ?>
                <?php if(!$porServicio): ?><tr><td colspan="9" class="empty">Sin datos de servicios.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>

        <article class="card visual-card" data-card-key="calificaciones">
            <div class="card-head">
                <div class="card-head-main"><h2>Calificaciones recientes por caso</h2><p>Comparativo entre gestión del área y tiempo de respuesta.</p></div>
                <button class="table-mode-toggle" type="button">Ver tabla</button>
            </div>
            <div class="card-body visual-summary">
                <div class="visual-legend"><span><i></i>Gestión del área</span><span><i class="amber"></i>Tiempo</span></div>
                <div class="metric-chart">
                    <?php foreach (array_slice($detalleCalificaciones, 0, 8) as $evaluacion): ?>
                        <button class="metric-row detail-trigger" type="button" data-detail-kind="calificacion" data-detail-title="Caso <?= escaparIndicador($evaluacion['codigo_caso']) ?> · Ticket #<?= (int) $evaluacion['id_ticket'] ?>" data-ticket-id="<?= (int) $evaluacion['id_ticket'] ?>" data-ticket-url="solicitudes.php?id_ticket=<?= (int) $evaluacion['id_ticket'] ?>&amp;id_nodo=<?= (int) $evaluacion['id_ticket_etapa'] ?>" data-area="<?= escaparIndicador($evaluacion['catalogo_nombre']) ?>">
                            <span class="metric-name" title="<?= escaparIndicador($evaluacion['catalogo_nombre'].' / '.$evaluacion['servicio_nombre']) ?>">Caso <?= escaparIndicador($evaluacion['codigo_caso']) ?></span>
                            <span class="metric-bars">
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill" style="width:<?= anchoIndicador($evaluacion['calificacion_area'], 5) ?>%"></span></span><small><?= numeroIndicador($evaluacion['calificacion_area'], 0) ?>★</small></span>
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill amber" style="width:<?= anchoIndicador($evaluacion['calificacion_tiempo'], 5) ?>%"></span></span><small><?= numeroIndicador($evaluacion['calificacion_tiempo'], 0) ?>★</small></span>
                            </span>
                            <span class="metric-score"><strong><?= numeroIndicador($evaluacion['calificacion'], 1) ?> ★</strong><small>General</small></span>
                        </button>
                    <?php endforeach; ?>
                    <?php if (!$detalleCalificaciones): ?><div class="visual-empty">Sin calificaciones en el periodo.</div><?php endif; ?>
                </div>
                <div class="chart-caption"><span>Últimas 8 evaluaciones</span><b>Selecciona un caso para revisar la evaluación</b></div>
            </div>
            <div class="table-wrap tall"><table><thead><tr><th>Ticket</th><th>Caso</th><th>Tipo</th><th>Evaluador</th><th>Área / servicio</th><th>Gestor asignado</th><th>Tiempo hasta Listo</th><th>Resultado SLA</th><th>Gestión del área</th><th>Tiempo de respuesta</th><th>General</th><th>Comentario</th><th>Fecha</th></tr></thead><tbody>
                <?php foreach($detalleCalificaciones as $evaluacion): ?><tr class="selectable-row" data-detail-kind="calificacion" data-detail-title="Caso <?= escaparIndicador($evaluacion['codigo_caso']) ?> · Ticket #<?= (int)$evaluacion['id_ticket'] ?>" data-ticket-id="<?= (int)$evaluacion['id_ticket'] ?>" data-ticket-url="solicitudes.php?id_ticket=<?= (int)$evaluacion['id_ticket'] ?>&amp;id_nodo=<?= (int)$evaluacion['id_ticket_etapa'] ?>" data-area="<?= escaparIndicador($evaluacion['catalogo_nombre']) ?>"><td><a class="ticket" href="solicitudes.php?id_ticket=<?= (int)$evaluacion['id_ticket'] ?>&id_nodo=<?= (int)$evaluacion['id_ticket_etapa'] ?>">#<?= (int)$evaluacion['id_ticket'] ?></a></td><td class="name"><?= escaparIndicador($evaluacion['codigo_caso']) ?></td><td><span class="badge blue"><?= escaparIndicador(etiquetaTipoCalificacion((string)$evaluacion['tipo_calificacion'])) ?></span></td><td><?= escaparIndicador($evaluacion['evaluador']) ?></td><td><?= escaparIndicador($evaluacion['catalogo_nombre'].' / '.$evaluacion['servicio_nombre']) ?></td><td><?= escaparIndicador($evaluacion['gestor']) ?></td><td><?= escaparIndicador(tiempoIndicador($evaluacion['minutos_atencion'])) ?></td><td><span class="badge <?= $evaluacion['resultado_sla']==='dentro_sla'?'green':($evaluacion['resultado_sla']==='fuera_sla'?'red':'gray') ?>"><?= escaparIndicador(etiquetaEstadoIndicador((string)$evaluacion['resultado_sla'])) ?></span></td><td class="stars"><?= numeroIndicador($evaluacion['calificacion_area'],0) ?> ★</td><td class="stars"><?= numeroIndicador($evaluacion['calificacion_tiempo'],0) ?> ★</td><td class="stars"><?= numeroIndicador($evaluacion['calificacion'],0) ?> ★</td><td><?php if(trim((string)$evaluacion['comentario'])!==''): ?><details class="comment-detail"><summary>Ver comentario</summary><p><?= escaparIndicador($evaluacion['comentario']) ?></p></details><?php else: ?>Sin comentario<?php endif; ?></td><td><?= escaparIndicador(date('d/m/Y H:i',strtotime((string)$evaluacion['creado_en']))) ?></td></tr><?php endforeach; ?>
                <?php if(!$detalleCalificaciones): ?><tr><td colspan="13" class="empty">Todavía no hay calificaciones detalladas en el periodo.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>

        <article class="card visual-card solutions-card" data-card-key="soluciones">
            <div class="card-head">
                <div class="card-head-main">
                    <h2>Soluciones más utilizadas por servicio</h2>
                    <p>Frecuencia, participación y observaciones registradas por los gestores.</p>
                </div>
                <button class="table-mode-toggle" type="button">Ver tabla</button>
            </div>
            <div class="card-body visual-summary">
                <div class="visual-legend"><span><i class="green"></i>Usos</span><span><i class="amber"></i>Participación</span></div>
                <div class="metric-chart">
                    <?php foreach (array_slice($porSolucionServicio, 0, 8) as $fila): ?>
                        <?php $partesSolucion = explode(' / ', (string) $fila['servicio'], 2); $areaSolucion = $partesSolucion[0] ?? ''; ?>
                        <button class="metric-row detail-trigger" type="button" data-detail-kind="solucion" data-detail-title="Solución: <?= escaparIndicador($fila['solucion'].' · '.$fila['servicio']) ?>" data-area="<?= escaparIndicador($areaSolucion) ?>">
                            <span class="metric-name" title="<?= escaparIndicador($fila['servicio'].' · '.$fila['solucion']) ?>"><?= escaparIndicador($fila['solucion']) ?></span>
                            <span class="metric-bars">
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill green" style="width:<?= anchoIndicador($fila['total'], $maxSolucion) ?>%"></span></span><small><?= (int) $fila['total'] ?></small></span>
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill amber" style="width:<?= anchoIndicador($fila['porcentaje'], 100) ?>%"></span></span><small><?= numeroIndicador($fila['porcentaje'], 0) ?>%</small></span>
                            </span>
                            <span class="metric-score"><strong><?= escaparIndicador($fila['ultimo_uso'] ? date('d/m', strtotime((string) $fila['ultimo_uso'])) : '—') ?></strong><small>Último uso</small></span>
                        </button>
                    <?php endforeach; ?>
                    <?php if (!$porSolucionServicio): ?><div class="visual-empty">Sin soluciones registradas en el periodo.</div><?php endif; ?>
                </div>
                <div class="chart-caption"><span><?= $idServicioSolucion > 0 ? 'Servicio seleccionado en el filtro' : 'Comparativo de todos los servicios' ?></span><b>Selecciona una solución para ver sus datos</b></div>
            </div>
            <div class="table-wrap">
                <table class="solutions-table">
                    <thead>
                        <tr>
                            <th>Área / servicio</th>
                            <th>Solución seleccionada</th>
                            <th>Usos</th>
                            <th>Participación dentro del servicio</th>
                            <th>Comentarios</th>
                            <th>Último uso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porSolucionServicio as $fila): ?>
                            <?php
                                $comentariosSolucion = $comentariosPorSolucion[
                                    (string) $fila['clave_comentarios']
                                ] ?? [];
                            ?>
                            <?php $partesSolucion = explode(' / ', (string) $fila['servicio'], 2); $areaSolucion = $partesSolucion[0] ?? ''; ?>
                            <tr class="selectable-row" data-detail-kind="solucion" data-detail-title="Solución: <?= escaparIndicador($fila['solucion'].' · '.$fila['servicio']) ?>" data-area="<?= escaparIndicador($areaSolucion) ?>">
                                <td class="name"><?= escaparIndicador($fila['servicio']) ?></td>
                                <td><?= escaparIndicador($fila['solucion']) ?></td>
                                <td><span class="usage-count"><?= numeroIndicador($fila['total']) ?></span></td>
                                <td>
                                    <div class="progress solution-progress">
                                        <span class="track"><span class="bar green" style="width:<?= anchoIndicador($fila['porcentaje'], 100) ?>%"></span></span>
                                        <strong><?= porcentajeIndicador($fila['porcentaje']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($comentariosSolucion): ?>
                                        <details class="comment-detail solution-comments">
                                            <summary>Ver comentarios <span><?= count($comentariosSolucion) ?></span></summary>
                                            <div class="solution-comment-list">
                                                <?php foreach ($comentariosSolucion as $comentarioSolucion): ?>
                                                    <article class="solution-comment">
                                                        <div class="solution-comment-head">
                                                            <a class="ticket" href="solicitudes.php?id_ticket=<?= (int) $comentarioSolucion['id_ticket'] ?>&id_nodo=<?= (int) $comentarioSolucion['id_ticket_etapa'] ?>">
                                                                Caso <?= escaparIndicador($comentarioSolucion['codigo_caso']) ?>
                                                            </a>
                                                            <time><?= escaparIndicador($comentarioSolucion['fecha_registro'] ? date('d/m/Y H:i', strtotime((string) $comentarioSolucion['fecha_registro'])) : 'Sin fecha') ?></time>
                                                        </div>
                                                        <small>Gestor: <?= escaparIndicador($comentarioSolucion['gestor']) ?></small>
                                                        <p><?= escaparIndicador($comentarioSolucion['comentario']) ?></p>
                                                    </article>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <span class="muted-text">Sin observación</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= escaparIndicador($fila['ultimo_uso'] ? date('d/m/Y H:i', strtotime((string) $fila['ultimo_uso'])) : 'Sin datos') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$porSolucionServicio): ?>
                            <tr><td colspan="6" class="empty">No hay casos con solución en el servicio y periodo seleccionados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="card visual-card" data-card-key="tickets">
            <div class="card-head">
                <div class="card-head-main"><h2>Detalle general de tickets</h2><p><?= numeroIndicador(count($detalleTickets)) ?> registros en el periodo.</p></div>
                <button class="table-mode-toggle" type="button">Ver tabla</button>
            </div>
            <div class="card-body visual-summary">
                <div class="visual-legend"><span><i class="green"></i>Progreso</span><span><i></i>Cumplimiento SLA</span></div>
                <div class="metric-chart">
                    <?php foreach (array_slice($detalleTickets, 0, 8) as $ticket): ?>
                        <?php $totalEtapas = max(1, (int) $ticket['total_etapas']); $avance = 100 * (int) $ticket['etapas_completadas'] / $totalEtapas; ?>
                        <button class="metric-row detail-trigger" type="button" data-detail-kind="ticket" data-detail-title="Ticket #<?= (int) $ticket['id_ticket'] ?>" data-ticket-id="<?= (int) $ticket['id_ticket'] ?>" data-ticket-url="solicitudes.php?id_ticket=<?= (int) $ticket['id_ticket'] ?>" data-area="<?= escaparIndicador($ticket['area_actual']) ?>">
                            <span class="metric-name" title="<?= escaparIndicador($ticket['tipo_ticket']) ?>">#<?= (int) $ticket['id_ticket'] ?> · <?= escaparIndicador($ticket['tipo_ticket']) ?></span>
                            <span class="metric-bars">
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill green" style="width:<?= anchoIndicador($avance, 100) ?>%"></span></span><small><?= (int) round($avance) ?>%</small></span>
                                <span class="metric-line"><span class="metric-track"><span class="metric-fill" style="width:<?= anchoIndicador($ticket['cumplimiento_sla'], 100) ?>%"></span></span><small><?= numeroIndicador($ticket['cumplimiento_sla'] ?? 0, 0) ?>%</small></span>
                            </span>
                            <span class="metric-score"><strong><?= escaparIndicador(etiquetaEstadoIndicador((string) $ticket['estado_flujo'])) ?></strong><small><?= escaparIndicador($ticket['area_actual']) ?></small></span>
                        </button>
                    <?php endforeach; ?>
                    <?php if (!$detalleTickets): ?><div class="visual-empty">No hay tickets en el periodo.</div><?php endif; ?>
                </div>
                <div class="chart-caption"><span>Últimos 8 tickets actualizados</span><b>Selecciona un ticket para ver todos sus datos</b></div>
            </div>
            <div class="table-wrap tall"><table><thead><tr><th>Ticket</th><th>Tipo</th><th>Solicitante</th><th>Estado</th><th>Área actual</th><th>Gestor actual</th><th>Progreso</th><th>SLA acumulado</th><th>Gestión del área</th><th>Tiempo de respuesta</th><th>General</th><th>Creación</th></tr></thead><tbody>
                <?php foreach($detalleTickets as $ticket): ?><?php $totalEtapas=max(1,(int)$ticket['total_etapas']);$avance=100*(int)$ticket['etapas_completadas']/$totalEtapas; ?><tr class="selectable-row" data-detail-kind="ticket" data-detail-title="Ticket #<?= (int)$ticket['id_ticket'] ?>" data-ticket-id="<?= (int)$ticket['id_ticket'] ?>" data-ticket-url="solicitudes.php?id_ticket=<?= (int)$ticket['id_ticket'] ?>" data-area="<?= escaparIndicador($ticket['area_actual']) ?>"><td><a class="ticket" href="solicitudes.php?id_ticket=<?= (int)$ticket['id_ticket'] ?>">#<?= (int)$ticket['id_ticket'] ?></a></td><td class="name"><?= escaparIndicador($ticket['tipo_ticket']) ?></td><td><?= escaparIndicador($ticket['solicitante']) ?></td><td><span class="badge <?= escaparIndicador(claseEstadoIndicador((string)$ticket['estado_flujo'])) ?>"><?= escaparIndicador(etiquetaEstadoIndicador((string)$ticket['estado_flujo'])) ?></span></td><td><?= escaparIndicador($ticket['area_actual']) ?></td><td><?= escaparIndicador($ticket['gestor_actual']) ?></td><td><div class="progress"><span class="track"><span class="bar green" style="width:<?= anchoIndicador($avance,100) ?>%"></span></span><span><?= (int)$ticket['etapas_completadas'] ?>/<?= (int)$ticket['total_etapas'] ?></span></div></td><td><?= escaparIndicador(porcentajeIndicador($ticket['cumplimiento_sla'])) ?></td><td class="stars"><?= $ticket['promedio_area']===null?'—':numeroIndicador($ticket['promedio_area'],2).' ★' ?></td><td class="stars"><?= $ticket['promedio_tiempo']===null?'—':numeroIndicador($ticket['promedio_tiempo'],2).' ★' ?></td><td class="stars"><?= $ticket['promedio_calificacion']===null?'—':numeroIndicador($ticket['promedio_calificacion'],2).' ★' ?></td><td><?= escaparIndicador(date('d/m/Y H:i',strtotime((string)$ticket['fecha_creacion']))) ?></td></tr><?php endforeach; ?>
                <?php if(!$detalleTickets): ?><tr><td colspan="12" class="empty">No hay tickets para el periodo seleccionado.</td></tr><?php endif; ?>
            </tbody></table></div>
        </article>
    </section>
</main>

<div id="drawerBackdrop" class="drawer-backdrop" hidden></div>
<aside id="detailDrawer" class="detail-drawer" aria-hidden="true" aria-labelledby="drawerTitle">
    <header class="drawer-head">
        <div class="drawer-icon">BI</div>
        <div class="drawer-title"><span id="drawerKind">Detalle del indicador</span><h2 id="drawerTitle">Información seleccionada</h2></div>
        <button id="drawerClose" class="drawer-close" type="button" aria-label="Cerrar detalle">×</button>
    </header>
    <div id="drawerBody" class="drawer-body"></div>
    <footer class="drawer-foot">
        <a id="drawerTicketLink" class="btn primary ticket-action" href="#" hidden>Abrir ticket completo</a>
        <button id="drawerCloseBottom" type="button">Cerrar</button>
    </footer>
</aside>

<script>
(function () {
    'use strict';

    var cards = Array.prototype.slice.call(document.querySelectorAll('.dashboard > .card'));
    var collapseAll = document.getElementById('collapseAllCards');
    var expandAll = document.getElementById('expandAllCards');
    var drawer = document.getElementById('detailDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    var drawerTitle = document.getElementById('drawerTitle');
    var drawerKind = document.getElementById('drawerKind');
    var drawerBody = document.getElementById('drawerBody');
    var drawerTicketLink = document.getElementById('drawerTicketLink');
    var storagePrefix = 'mesa-indicadores-card:';

    function slug(text) {
        return String(text || 'seccion')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }

    function storeCard(card, collapsed) {
        try {
            window.localStorage.setItem(storagePrefix + card.dataset.cardKey, collapsed ? '1' : '0');
        } catch (error) {
            /* La interfaz sigue funcionando aunque el navegador bloquee almacenamiento local. */
        }
    }

    function savedCardState(card) {
        try {
            return window.localStorage.getItem(storagePrefix + card.dataset.cardKey);
        } catch (error) {
            return null;
        }
    }

    function setCardCollapsed(card, collapsed, persist) {
        var toggle = card.querySelector(':scope > .card-head .card-toggle');
        card.classList.toggle('is-collapsed', collapsed);
        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.lastChild.nodeValue = collapsed ? ' Maximizar' : ' Minimizar';
        }
        if (persist !== false) {
            storeCard(card, collapsed);
        }
    }

    cards.forEach(function (card, index) {
        var head = card.querySelector(':scope > .card-head');
        if (!head) return;

        var heading = head.querySelector('h2');
        card.dataset.cardKey = card.dataset.cardKey || slug(heading ? heading.textContent : 'seccion-' + index);

        var main = head.querySelector(':scope > .card-head-main');
        if (!main) {
            main = document.createElement('div');
            main.className = 'card-head-main';
            if (heading) main.appendChild(heading);
            head.insertBefore(main, head.firstChild);
        }

        var actions = document.createElement('div');
        actions.className = 'card-head-actions';
        Array.prototype.slice.call(head.children).forEach(function (child) {
            if (child === main || child === actions) return;
            if (child.matches('span') && !child.classList.contains('card-meta')) {
                child.classList.add('card-meta');
            }
            actions.appendChild(child);
        });

        var toggle = document.createElement('button');
        toggle.className = 'card-toggle';
        toggle.type = 'button';
        toggle.appendChild(document.createTextNode(' Minimizar'));
        toggle.addEventListener('click', function () {
            setCardCollapsed(card, !card.classList.contains('is-collapsed'));
        });
        actions.appendChild(toggle);
        head.appendChild(actions);

        var saved = savedCardState(card);
        setCardCollapsed(card, saved === '1', false);
    });

    document.querySelectorAll('.table-mode-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var card = button.closest('.visual-card');
            var tableOpen = card.classList.toggle('is-table-open');
            button.lastChild.nodeValue = tableOpen ? ' Ver gráfico' : ' Ver tabla';
            button.setAttribute('aria-pressed', tableOpen ? 'true' : 'false');
        });
    });

    if (collapseAll) {
        collapseAll.addEventListener('click', function () {
            cards.forEach(function (card) { setCardCollapsed(card, true); });
        });
    }

    if (expandAll) {
        expandAll.addEventListener('click', function () {
            cards.forEach(function (card) { setCardCollapsed(card, false); });
        });
    }

    function createDetailItem(label, value) {
        var item = document.createElement('div');
        item.className = 'detail-item' + (String(value).length > 75 ? ' full' : '');
        var labelNode = document.createElement('span');
        var valueNode = document.createElement('strong');
        labelNode.textContent = label || 'Dato';
        valueNode.textContent = value || 'Sin datos';
        item.appendChild(labelNode);
        item.appendChild(valueNode);
        return item;
    }

    function rowValues(row) {
        var table = row.closest('table');
        var headers = table ? Array.prototype.slice.call(table.querySelectorAll('thead th')) : [];
        return Array.prototype.slice.call(row.cells || []).map(function (cell, index) {
            return {
                label: headers[index] ? headers[index].textContent.trim() : 'Dato ' + (index + 1),
                value: cell.textContent.replace(/\s+/g, ' ').trim()
            };
        });
    }

    function kindLabel(kind) {
        var labels = {
            area: 'Detalle del área',
            servicio: 'Detalle del servicio',
            gestor: 'Detalle del gestor',
            ticket: 'Detalle del ticket',
            calificacion: 'Detalle de la evaluación',
            solucion: 'Detalle de la solución'
        };
        return labels[kind] || 'Detalle del indicador';
    }

    function relatedSection(title, rows, labelBuilder) {
        if (!rows.length) return null;
        var section = document.createElement('section');
        section.className = 'drawer-section';
        var heading = document.createElement('h3');
        var list = document.createElement('ul');
        heading.textContent = title + ' (' + rows.length + ')';
        list.className = 'related-list';
        rows.forEach(function (row) {
            var item = document.createElement('li');
            var button = document.createElement('button');
            var main = document.createElement('b');
            var meta = document.createElement('span');
            button.type = 'button';
            button.className = 'related-row';
            main.textContent = labelBuilder(row).main;
            meta.textContent = labelBuilder(row).meta;
            button.appendChild(main);
            button.appendChild(meta);
            button.addEventListener('click', function () { openRowDetail(row); });
            item.appendChild(button);
            list.appendChild(item);
        });
        section.appendChild(heading);
        section.appendChild(list);
        return section;
    }

    function openRowDetail(row) {
        var kind = row.dataset.detailKind || 'indicador';
        var title = row.dataset.detailTitle || 'Información seleccionada';
        var grid = document.createElement('div');
        var area = row.dataset.area || '';
        grid.className = 'detail-grid';
        drawerBody.textContent = '';

        rowValues(row).forEach(function (detail) {
            grid.appendChild(createDetailItem(detail.label, detail.value));
        });
        drawerBody.appendChild(grid);

        if (kind === 'area' && area) {
            var services = Array.prototype.slice.call(document.querySelectorAll('tr.selectable-row[data-detail-kind="servicio"]')).filter(function (candidate) {
                return candidate.dataset.area === area;
            });
            var tickets = Array.prototype.slice.call(document.querySelectorAll('tr.selectable-row[data-detail-kind="ticket"]')).filter(function (candidate) {
                return candidate.dataset.area === area;
            });
            var serviceSection = relatedSection('Servicios del área', services, function (candidate) {
                return {main: candidate.cells[0].textContent.trim(), meta: candidate.cells[1].textContent.trim() + ' casos'};
            });
            var ticketSection = relatedSection('Tickets actualmente en el área', tickets, function (candidate) {
                return {main: candidate.dataset.detailTitle, meta: candidate.cells[3].textContent.trim()};
            });
            if (serviceSection) drawerBody.appendChild(serviceSection);
            if (ticketSection) drawerBody.appendChild(ticketSection);
        }

        if ((kind === 'servicio' || kind === 'solucion') && area) {
            var areaTickets = Array.prototype.slice.call(document.querySelectorAll('tr.selectable-row[data-detail-kind="ticket"]')).filter(function (candidate) {
                return candidate.dataset.area === area;
            });
            var relatedTickets = relatedSection('Tickets actuales del área relacionada', areaTickets, function (candidate) {
                return {main: candidate.dataset.detailTitle, meta: candidate.cells[3].textContent.trim()};
            });
            if (relatedTickets) drawerBody.appendChild(relatedTickets);
        }

        drawerKind.textContent = kindLabel(kind);
        drawerTitle.textContent = title;
        if (row.dataset.ticketUrl) {
            drawerTicketLink.href = row.dataset.ticketUrl;
            drawerTicketLink.hidden = false;
        } else {
            drawerTicketLink.hidden = true;
        }

        backdrop.hidden = false;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('drawer-open');
        document.getElementById('drawerClose').focus();
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('drawer-open');
        window.setTimeout(function () { backdrop.hidden = true; }, 220);
    }

    document.querySelectorAll('tr.selectable-row').forEach(function (row) {
        row.tabIndex = 0;
        row.setAttribute('role', 'button');
        row.setAttribute('aria-label', 'Ver ' + (row.dataset.detailTitle || 'detalle'));
        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button, summary, details, input, select, textarea')) return;
            openRowDetail(row);
        });
        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openRowDetail(row);
            }
        });
    });

    document.querySelectorAll('.detail-trigger').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var match = Array.prototype.slice.call(document.querySelectorAll('tr.selectable-row')).find(function (row) {
                return row.dataset.detailKind === trigger.dataset.detailKind
                    && row.dataset.detailTitle === trigger.dataset.detailTitle;
            });
            if (match) openRowDetail(match);
        });
    });

    document.getElementById('drawerClose').addEventListener('click', closeDrawer);
    document.getElementById('drawerCloseBottom').addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer();
    });
}());
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
