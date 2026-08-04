<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}

/* Al seleccionar una fila se conserva la vista completa del ticket. */
$idTicketSeleccionado = filter_input(
    INPUT_GET,
    'id_ticket',
    FILTER_VALIDATE_INT
) ?: 0;

if ($idTicketSeleccionado > 0) {
    require __DIR__ . '/flujoTicket.php';
    exit;
}

function escaparTablaTickets(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function etiquetaEstadoTabla(string $estado): string
{
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En proceso',
        'en_espera_solicitante' => 'En espera',
        'pausada' => 'Pausado por casos hijos',
        'listo_cierre' => 'Listo · pendiente de cierre',
        'bloqueada' => 'Bloqueado',
        'completada' => 'Completado',
        'pendiente_calificacion' => 'Pendiente de calificación',
        'cerrado' => 'Cerrado',
        'cancelado', 'cancelada' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function claseEstadoTabla(string $estado): string
{
    return match ($estado) {
        'pendiente', 'en_proceso' => 'proceso',
        'en_espera_solicitante', 'pausada', 'listo_cierre' => 'calificacion',
        'completada', 'cerrado' => 'cerrado',
        'pendiente_calificacion' => 'calificacion',
        'cancelado', 'cancelada' => 'cancelado',
        default => 'neutro',
    };
}

$casos = [];
$moduloDisponible = flujoModuloInstalado($conn)
    && flujoModuloSolucionesInstalado($conn)
    && flujoModuloAprobacionCasosInstalado($conn);

if ($moduloDisponible) {
    $resultado = $conn->query(
        "SELECT
            t.id_ticket,
            t.titulo,
            t.estado_flujo,
            te.id_ticket_etapa,
            te.id_ticket_etapa_padre,
            te.nivel,
            te.orden,
            te.catalogo_nombre,
            te.servicio_nombre,
            te.estado AS estado_caso,
            te.fecha_marcado_listo,
            te.minutos_hasta_listo,
            te.resultado_sla_listo,
            te.solucion_nombre,
            te.comentario_cierre AS observacion_solucion,
            cal.calificacion,
            cal.calificacion_area,
            cal.calificacion_tiempo,
            cal.tipo_calificacion,
            cal.comentario AS comentario_calificacion,
            COALESCE(creador_caso.nombre, creador_ticket.nombre, 'Usuario eliminado') AS creador,
            COALESCE(evaluador.nombre, 'Sin calificar') AS evaluador,
            COALESCE(
                NULLIF(gestor.nombre, ''),
                NULLIF(te.gestor_nombre, ''),
                'Sin asignar'
            ) AS gestor_actual
         FROM tickets AS t
         INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
         INNER JOIN ticket_etapas AS te ON te.id_ticket = t.id_ticket
         LEFT JOIN usuarios AS creador_ticket
            ON creador_ticket.id_usuario = t.id_usuario
         LEFT JOIN usuarios AS creador_caso
            ON creador_caso.id_usuario = COALESCE(NULLIF(te.creado_por, 0), t.id_usuario)
         LEFT JOIN usuarios AS gestor
            ON gestor.id_usuario = te.id_gestor
         LEFT JOIN solicitud_calificaciones AS cal
            ON cal.id_ticket_etapa = te.id_ticket_etapa
         LEFT JOIN usuarios AS evaluador
            ON evaluador.id_usuario = cal.id_solicitante
         WHERE t.id_proceso IS NOT NULL
         ORDER BY t.actualizado_en DESC, t.id_ticket DESC, te.orden, te.id_ticket_etapa"
    );

    if ($resultado !== false) {
        while ($caso = $resultado->fetch_assoc()) {
            $caso['codigo_caso'] = flujoCodigoCaso(
                $conn,
                (int) $caso['id_ticket'],
                (int) $caso['id_ticket_etapa']
            );
            $casos[] = $caso;
        }
    }
}

$nombreAdministrador = trim(
    (string) ($_SESSION['usuario'] ?? 'Administrador')
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | Administración</title>
    <style>
        :root {
            --primary:#0f6fec;
            --primary-dark:#0b4fae;
            --navy:#102a43;
            --text:#243b53;
            --muted:#6b7f93;
            --border:#dce6f0;
            --background:#f4f7fb;
            --surface:#ffffff;
        }
        * { box-sizing:border-box; }
        body {
            min-height:100vh;
            margin:0;
            color:var(--text);
            background:var(--background);
            font:12.5px/1.4 Inter,"Segoe UI",Arial,sans-serif;
        }
        .shell {
            width:min(1180px,calc(100% - 24px));
            margin:auto;
            padding:12px 0 24px;
        }
        .topbar {
            min-height:58px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
            padding:9px 12px 9px 16px;
            border:1px solid var(--border);
            border-radius:12px;
            background:var(--surface);
            box-shadow:0 5px 18px rgba(16,42,67,.05);
        }
        .heading { display:flex;align-items:center;gap:10px;min-width:0; }
        .mark {
            width:34px;height:34px;display:grid;place-items:center;
            flex:0 0 auto;border-radius:9px;color:#fff;
            background:linear-gradient(145deg,var(--primary),var(--primary-dark));
            font-size:10px;font-weight:850;letter-spacing:.04em;
        }
        h1 { margin:0;color:var(--navy);font-size:17px;line-height:1.15; }
        .subtitle { margin:2px 0 0;color:var(--muted);font-size:10.5px; }
        .actions { display:flex;align-items:center;gap:6px;flex-wrap:wrap; }
        .btn {
            min-height:32px;display:inline-flex;align-items:center;justify-content:center;
            padding:7px 10px;border:1px solid #d8e5f2;border-radius:8px;
            color:#24577f;background:#f7fbff;text-decoration:none;
            font-size:10.5px;font-weight:750;white-space:nowrap;
        }
        .btn.primary { color:#fff;border-color:var(--primary);background:var(--primary); }
        .btn.logout { color:#a33b32;background:#fff8f7;border-color:#f0d9d5; }
        .panel {
            margin-top:10px;
            overflow:hidden;
            border:1px solid var(--border);
            border-radius:12px;
            background:var(--surface);
            box-shadow:0 7px 22px rgba(16,42,67,.05);
        }
        .panel-head {
            min-height:47px;display:flex;align-items:center;justify-content:space-between;
            gap:12px;padding:9px 14px;border-bottom:1px solid var(--border);
        }
        .panel-head h2 { margin:0;color:var(--navy);font-size:14px; }
        .count {
            padding:3px 8px;border-radius:999px;color:#2b628f;
            background:#edf5fd;font-size:10px;font-weight:800;
        }
        .table-wrap { width:100%;overflow:auto; }
        table { width:100%;border-collapse:collapse;min-width:1280px; }
        th {
            padding:8px 12px;text-align:left;color:#647b91;background:#f8fafc;
            border-bottom:1px solid var(--border);font-size:9.5px;
            font-weight:800;letter-spacing:.045em;text-transform:uppercase;
        }
        td {
            height:44px;padding:7px 12px;border-bottom:1px solid #edf2f7;
            vertical-align:middle;
        }
        tbody tr { cursor:pointer;transition:background .14s ease; }
        tbody tr:hover { background:#f5f9fe; }
        tbody tr:last-child td { border-bottom:0; }
        .ticket-link { color:#0d62c7;font-weight:850;text-decoration:none; }
        .ticket-link:hover { text-decoration:underline; }
        .requester { color:var(--navy);font-weight:700; }
        .stage { color:#48647d; }
        .manager { color:#315875;font-weight:650; }
        .solution {
            display:inline-flex;padding:4px 8px;border-radius:7px;
            color:#087458;background:#eaf8f2;font-size:9.5px;font-weight:800;
        }
        .solution.pending { color:#687b8d;background:#eef2f6; }
        .rating-mini {
            display:grid;gap:2px;min-width:125px;padding:5px 7px;border-radius:8px;
            color:#5f4500;background:#fff7dc;font-size:9px;font-weight:750;
        }
        .rating-mini strong { color:#765200;font-size:9.5px; }
        .observation summary {
            width:max-content;cursor:pointer;padding:4px 8px;border-radius:7px;
            color:#245f8d;background:#eef6fd;font-size:9.5px;font-weight:800;
        }
        .observation p {
            max-width:360px;margin:7px 0 0;padding:8px;border:1px solid #dce7f1;
            border-radius:8px;color:#405d75;background:#f9fbfd;white-space:pre-wrap;
        }
        .status {
            display:inline-flex;align-items:center;gap:5px;padding:4px 8px;
            border-radius:999px;font-size:9.5px;font-weight:800;white-space:nowrap;
        }
        .status::before { content:"";width:6px;height:6px;border-radius:50%;background:currentColor; }
        .status.proceso { color:#1767b5;background:#eaf4ff; }
        .status.calificacion { color:#9a6500;background:#fff6d9; }
        .status.cerrado { color:#087443;background:#eaf8f1; }
        .status.cancelado { color:#a33b32;background:#fff0ee; }
        .status.neutro { color:#65788a;background:#eef2f6; }
        .empty { padding:34px 18px;text-align:center;color:var(--muted); }
        .installation {
            margin:12px;padding:12px;border-radius:9px;color:#8a5b00;background:#fff8e1;
        }
        @media (max-width:760px) {
            .shell { width:min(100% - 14px,1180px);padding-top:7px; }
            .topbar { align-items:flex-start;flex-direction:column; }
            .actions { width:100%; }
            .btn { flex:1; }
        }
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="heading">
            <div class="mark">MS</div>
            <div>
                <h1>Tickets</h1>
                <p class="subtitle"><?= escaparTablaTickets($nombreAdministrador) ?> · Administración</p>
            </div>
        </div>
        <nav class="actions" aria-label="Acciones administrativas">
            <a class="btn primary" href="descargarSolicitudesExcel.php">Descargar base</a>
            <a class="btn" href="procesos.php">Configurar tickets</a>
            <a class="btn" href="panelAdmin.php">Volver al panel</a>
            <a class="btn logout" href="logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <section class="panel">
        <div class="panel-head">
            <h2>Listado general de casos</h2>
            <span class="count"><?= count($casos) ?> caso<?= count($casos) === 1 ? '' : 's' ?></span>
        </div>

        <?php if (!$moduloDisponible): ?>
            <div class="installation">
                El módulo de Tickets, soluciones y calificación detallada todavía no está instalado por completo.
            </div>
        <?php elseif (!$casos): ?>
            <div class="empty">No hay casos registrados.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width:105px">Caso</th>
                        <th style="width:80px">Ticket</th>
                        <th>Gestor creador</th>
                        <th>Área / servicio</th>
                        <th style="width:150px">Gestor a cargo</th>
                        <th style="width:145px">Estado del caso</th>
                        <th style="width:155px">Calificación</th>
                        <th style="width:175px">Solución</th>
                        <th style="width:175px">Observación</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($casos as $caso): ?>
                        <?php
                            $urlTicket = 'solicitudes.php?' . http_build_query([
                                'id_ticket' => (int) $caso['id_ticket'],
                                'id_nodo' => (int) $caso['id_ticket_etapa'],
                            ]);
                            $nombreSolucion = trim((string) ($caso['solucion_nombre'] ?? ''));

                            if (
                                $nombreSolucion === ''
                                && $caso['estado_caso'] === 'completada'
                                && trim((string) ($caso['observacion_solucion'] ?? '')) !== ''
                            ) {
                                $nombreSolucion = 'Cierre anterior sin clasificación';
                            }
                        ?>
                        <tr
                            tabindex="0"
                            data-href="<?= escaparTablaTickets($urlTicket) ?>"
                            aria-label="Abrir caso <?= escaparTablaTickets($caso['codigo_caso']) ?>"
                        >
                            <td>
                                <a class="ticket-link" href="<?= escaparTablaTickets($urlTicket) ?>">
                                    <?= escaparTablaTickets($caso['codigo_caso']) ?>
                                </a>
                            </td>
                            <td>#<?= (int) $caso['id_ticket'] ?></td>
                            <td class="requester"><?= escaparTablaTickets($caso['creador']) ?></td>
                            <td class="stage"><?= escaparTablaTickets($caso['catalogo_nombre'] . ' / ' . $caso['servicio_nombre']) ?></td>
                            <td class="manager"><?= escaparTablaTickets($caso['gestor_actual']) ?></td>
                            <td>
                                <span class="status <?= escaparTablaTickets(claseEstadoTabla((string) $caso['estado_caso'])) ?>">
                                    <?= escaparTablaTickets(etiquetaEstadoTabla((string) $caso['estado_caso'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($caso['calificacion'] !== null): ?>
                                    <span class="rating-mini">
                                        <strong>General <?= (int) $caso['calificacion'] ?>/5</strong>
                                        <span>Área <?= (int) $caso['calificacion_area'] ?>/5 · Tiempo <?= (int) $caso['calificacion_tiempo'] ?>/5</span>
                                        <span><?= escaparTablaTickets(match ((string) ($caso['tipo_calificacion'] ?? 'historica')) {
                                            'encuesta_servicio' => 'Encuesta del servicio',
                                            'evaluacion_derivacion' => 'Evaluación de derivación',
                                            'evaluacion_caso' => 'Evaluación del caso',
                                            default => 'Evaluación histórica',
                                        }) ?> · <?= escaparTablaTickets($caso['evaluador']) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="solution pending">Sin calificar</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="solution <?= $nombreSolucion === '' ? 'pending' : '' ?>"><?= escaparTablaTickets($nombreSolucion !== '' ? $nombreSolucion : 'Sin solución') ?></span></td>
                            <td>
                                <?php if (trim((string) ($caso['observacion_solucion'] ?? '')) !== ''): ?>
                                    <details class="observation"><summary>Ver observación</summary><p><?= escaparTablaTickets($caso['observacion_solucion']) ?></p></details>
                                <?php else: ?>
                                    <span class="solution pending">Sin observación</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<script>
    document.querySelectorAll('tr[data-href]').forEach(function (fila) {
        fila.addEventListener('click', function (evento) {
            if (evento.target.closest('a, button, summary, details')) return;
            window.location.href = fila.dataset.href;
        });
        fila.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter' || evento.key === ' ') {
                evento.preventDefault();
                window.location.href = fila.dataset.href;
            }
        });
    });
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
