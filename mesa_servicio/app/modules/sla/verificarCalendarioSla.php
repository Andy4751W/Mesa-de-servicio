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

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function escaparVerificacion(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$zona = calendarioZonaHoraria();
$inicioTexto = trim((string) ($_GET['inicio'] ?? ''));
$inicioFormulario = $inicioTexto !== ''
    ? $inicioTexto
    : (new DateTimeImmutable('now', $zona))->format('Y-m-d\TH:i');

$inicio = DateTimeImmutable::createFromFormat(
    '!Y-m-d\TH:i',
    $inicioFormulario,
    $zona
);

if (!$inicio || $inicio->format('Y-m-d\TH:i') !== $inicioFormulario) {
    $inicio = new DateTimeImmutable('now', $zona);
    $inicioFormulario = $inicio->format('Y-m-d\TH:i');
}

$baseActual = '';
try {
    $resultadoBase = $conn->query('SELECT DATABASE() AS base_actual');
    $baseActual = (string) ($resultadoBase->fetch_assoc()['base_actual'] ?? '');
} catch (Throwable $e) {
    $baseActual = '';
}

$slas = [];
try {
    $resultadoSla = $conn->query(
        "SELECT id_sla, nombre, tiempo_respuesta, unidad, estado
         FROM sla
         ORDER BY estado ASC, tiempo_respuesta ASC, nombre ASC"
    );
    $slas = $resultadoSla ? $resultadoSla->fetch_all(MYSQLI_ASSOC) : [];
} catch (Throwable $e) {
    $slas = [];
}

$idSla = filter_input(INPUT_GET, 'id_sla', FILTER_VALIDATE_INT) ?: 0;
$slaSeleccionado = null;

foreach ($slas as $sla) {
    if ($idSla === 0) {
        $idSla = (int) $sla['id_sla'];
    }

    if ((int) $sla['id_sla'] === $idSla) {
        $slaSeleccionado = $sla;
    }
}

$idTicketPrueba = filter_input(
    INPUT_GET,
    'id_ticket',
    FILTER_VALIDATE_INT
) ?: 0;
$ticketPrueba = null;
$vencimientoTicket = null;

if ($idTicketPrueba > 0) {
    try {
        if (flujoModuloInstalado($conn)) {
            flujoRecalcularVencimientosActivos($conn, $idTicketPrueba);
        }

        $stmt = $conn->prepare(
            "SELECT
                t.id_ticket,
                t.fecha_creacion,
                te.id_ticket_etapa,
                te.servicio_nombre AS servicio,
                te.id_sla,
                te.sla_nombre,
                te.sla_tiempo AS tiempo_respuesta,
                te.sla_unidad AS unidad,
                COALESCE(
                    te.fecha_ultima_reanudacion,
                    te.fecha_activacion,
                    t.fecha_creacion
                ) AS inicio_calculo,
                te.fecha_vencimiento AS vencimiento_etapa
             FROM tickets AS t
             LEFT JOIN ticket_etapas AS te
                ON te.id_ticket_etapa = t.id_etapa_actual
             WHERE t.id_ticket = ?
             LIMIT 1"
        );
        $stmt->bind_param('i', $idTicketPrueba);
        $stmt->execute();
        $ticketPrueba = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ticketPrueba) {
            $vencimientoTicket = (string) (
                $ticketPrueba['vencimiento_etapa']
                ?? ''
            );

            if ($vencimientoTicket === '') {
                $vencimientoTicket = calcularVencimientoSla(
                    $conn,
                    (string) ($ticketPrueba['inicio_calculo'] ?? ''),
                    (int) ($ticketPrueba['tiempo_respuesta'] ?? 0),
                    (string) ($ticketPrueba['unidad'] ?? '')
                );
            }
        }
    } catch (Throwable $e) {
        $ticketPrueba = null;
    }
}

$feriados = [];
try {
    if (calendarioTablaExiste($conn, 'feriados')) {
        $resultadoFeriados = $conn->query(
            "SELECT
                id_feriado,
                nombre,
                tipo,
                fecha_inicio,
                fecha_fin,
                estado
             FROM feriados
             ORDER BY fecha_inicio ASC, id_feriado ASC"
        );
        $feriados = $resultadoFeriados
            ? $resultadoFeriados->fetch_all(MYSQLI_ASSOC)
            : [];
    }
} catch (Throwable $e) {
    $feriados = [];
}

$vencimiento = null;
if ($slaSeleccionado) {
    $vencimiento = calcularVencimientoSla(
        $conn,
        $inicio->format('Y-m-d H:i:s'),
        (int) $slaSeleccionado['tiempo_respuesta'],
        (string) $slaSeleccionado['unidad']
    );
}

$dias = [];
$dia = $inicio->setTime(0, 0);

for ($indice = 0; $indice < 15; $indice++) {
    $intervalos = calendarioIntervalosDelDia($conn, $dia);
    $tramos = [];
    $numeroDia = (int) $dia->format('N');
    $esFinSemana = $numeroDia > 5;
    $feriadosDia = $esFinSemana
        ? []
        : calendarioFeriadosDelDia(
            $conn,
            $dia->setTime(CALENDARIO_HORA_INICIO, 0),
            $dia->setTime(CALENDARIO_HORA_FIN, 0)
        );
    $nombresFeriados = [];

    foreach ($feriadosDia as $feriadoDia) {
        $nombreFeriado = trim((string) ($feriadoDia['nombre'] ?? ''));
        if ($nombreFeriado !== '') {
            $nombresFeriados[$nombreFeriado] = true;
        }
    }

    foreach ($intervalos as [$desde, $hasta]) {
        $tramos[] = $desde->format('H:i') . '–' . $hasta->format('H:i');
    }

    $dias[] = [
        'fecha' => $dia->format('d/m/Y'),
        'nombre' => [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ][$numeroDia],
        'tramos' => $tramos,
        'motivo' => $esFinSemana
            ? 'Fin de semana'
            : ($nombresFeriados
                ? implode(', ', array_keys($nombresFeriados))
                : 'Jornada ordinaria'),
    ];

    $dia = $dia->modify('+1 day');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación del calendario SLA</title>
    <style>
        :root{--navy:#102a43;--primary:#0f6fec;--muted:#627d98;--line:#dce6f2;--bg:#f3f7fc;--ok:#087f5b;--warn:#b45309}
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:#243b53;font:14px Inter,"Segoe UI",Arial,sans-serif}
        .page{width:min(1180px,calc(100% - 30px));margin:20px auto}.top,.card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 12px 30px rgba(16,42,67,.07)}
        .top{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:18px 22px}.top h1{margin:0;color:var(--navy);font-size:22px}.top p{margin:4px 0 0;color:var(--muted)}
        .back{padding:9px 13px;border-radius:9px;background:#edf5ff;color:#0b5fc7;text-decoration:none;font-weight:700}.grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:15px}.card{padding:20px}.card h2{margin:0 0 14px;color:var(--navy);font-size:17px}
        .form{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end}.field label{display:block;margin-bottom:6px;font-weight:700;color:var(--navy)}input,select{width:100%;min-height:40px;padding:8px 10px;border:1px solid #cbd9ea;border-radius:8px;background:#fff}.btn{min-height:40px;padding:8px 15px;border:0;border-radius:8px;background:var(--primary);color:#fff;font-weight:750;cursor:pointer}
        .result{margin-top:15px;padding:15px;border-radius:10px;background:#eaf7f2;color:#075f46}.result strong{display:block;margin-top:5px;font-size:20px}.notice{margin-top:12px;padding:12px;border-radius:9px;background:#fff7e6;color:#8a4b08;line-height:1.5}.db{display:inline-block;margin-top:8px;padding:5px 9px;border-radius:999px;background:#eef4fb;color:#315779;font-weight:700}.path{display:block;margin-top:7px;color:#627d98;font:11px Consolas,monospace;overflow-wrap:anywhere}
        table{width:100%;border-collapse:collapse}th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{color:#315779;background:#f7faff;font-size:12px}.active{color:var(--ok);font-weight:750}.disabled{color:#9f2936;font-weight:750}.working{color:var(--ok);font-weight:700}.closed{color:var(--warn);font-weight:700}.full{grid-column:1/-1}.scroll{max-height:350px;overflow:auto}
        @media(max-width:800px){.grid,.form{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.full{grid-column:auto}}
    </style>
</head>
<body>
<main class="page">
    <header class="top">
        <div>
            <h1>Verificación del calendario laboral SLA</h1>
            <p>Comprueba los días hábiles y los festivos registrados que está leyendo la aplicación.</p>
            <span class="db">Base conectada: <?= escaparVerificacion($baseActual ?: 'No identificada') ?></span>
            <span class="db">Motor laboral: <?= escaparVerificacion(calendarioVersion()) ?></span>
            <span class="path">Panel ejecutado: <?= escaparVerificacion((string) realpath(__FILE__)) ?></span>
            <span class="path">Calendario ejecutado: <?= escaparVerificacion((string) realpath(APP_ROOT . '/core/calendarioLaboral.php')) ?></span>
        </div>
        <a class="back" href="feriados.php">← Volver a Feriados</a>
    </header>

    <div class="grid">
        <section class="card full">
            <h2>Probar un vencimiento</h2>
            <form class="form" method="GET">
                <div class="field">
                    <label for="inicio">Fecha y hora de creación</label>
                    <input id="inicio" type="datetime-local" name="inicio" value="<?= escaparVerificacion($inicioFormulario) ?>" required>
                </div>
                <div class="field">
                    <label for="id_sla">SLA</label>
                    <select id="id_sla" name="id_sla" required>
                        <?php foreach ($slas as $sla): ?>
                            <option value="<?= (int) $sla['id_sla'] ?>" <?= (int) $sla['id_sla'] === $idSla ? 'selected' : '' ?>>
                                <?= escaparVerificacion($sla['nombre']) ?> · <?= (int) $sla['tiempo_respuesta'] ?> <?= escaparVerificacion($sla['unidad'] === 'dias' ? 'día(s) hábil(es)' : $sla['unidad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn" type="submit">Calcular</button>
            </form>

            <?php if ($slaSeleccionado && $vencimiento): ?>
                <div class="result">
                    Vencimiento calculado excluyendo sábados, domingos y festivos o periodos activos registrados en el panel:
                    <strong><?= escaparVerificacion((new DateTimeImmutable($vencimiento, $zona))->format('d/m/Y H:i')) ?></strong>
                </div>
            <?php else: ?>
                <div class="notice">No fue posible calcular el vencimiento. Verifique que exista por lo menos un SLA.</div>
            <?php endif; ?>

            <div class="notice">
                El número guardado en el SLA no cambia al crear un feriado. Por ejemplo, un SLA continúa siendo de “3 días hábiles”; lo que cambia es su fecha de vencimiento.
            </div>
        </section>

        <section class="card full">
            <h2>Comprobar un ticket del panel administrativo</h2>
            <form class="form" method="GET">
                <div class="field">
                    <label for="id_ticket">ID del ticket</label>
                    <input id="id_ticket" type="number" min="1" name="id_ticket" value="<?= $idTicketPrueba ?: '' ?>" placeholder="Ejemplo: 9" required>
                </div>
                <div></div>
                <button class="btn" type="submit">Revisar ticket</button>
            </form>

            <?php if ($idTicketPrueba > 0 && !$ticketPrueba): ?>
                <div class="notice">No se encontró el ticket #<?= $idTicketPrueba ?>.</div>
            <?php elseif ($ticketPrueba): ?>
                <div class="result">
                    Ticket #<?= (int) $ticketPrueba['id_ticket'] ?> ·
                    <?= !empty($ticketPrueba['id_ticket_etapa'])
                        ? 'Caso actual: ' . escaparVerificacion(
                            flujoCodigoCaso(
                                $conn,
                                (int) $ticketPrueba['id_ticket'],
                                (int) $ticketPrueba['id_ticket_etapa']
                            )
                        ) . ' · '
                        : '' ?>
                    Servicio: <?= escaparVerificacion($ticketPrueba['servicio'] ?: 'Sin servicio') ?> ·
                    SLA realmente asignado: <?= escaparVerificacion($ticketPrueba['sla_nombre'] ?: 'Sin SLA') ?>,
                    <?= (int) ($ticketPrueba['tiempo_respuesta'] ?? 0) ?>
                    <?= escaparVerificacion(($ticketPrueba['unidad'] ?? '') === 'dias' ? 'día(s) hábil(es)' : (string) ($ticketPrueba['unidad'] ?? '')) ?>.
                    <strong>
                        <?= $vencimientoTicket
                            ? escaparVerificacion((new DateTimeImmutable($vencimientoTicket, $zona))->format('d/m/Y H:i'))
                            : 'No fue posible calcular el vencimiento' ?>
                    </strong>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Festivos y periodos registrados (<?= count($feriados) ?>)</h2>
            <div class="scroll">
                <table>
                    <thead><tr><th>Nombre</th><th>Inicio</th><th>Fin</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php if (!$feriados): ?>
                        <tr><td colspan="4">No hay festivos registrados. El SLA solo excluirá sábados y domingos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($feriados as $feriado): ?>
                            <?php $activo = in_array(strtolower(trim((string) $feriado['estado'])), ['activo','habilitado','1'], true); ?>
                            <tr>
                                <td><?= escaparVerificacion($feriado['nombre']) ?></td>
                                <td><?= escaparVerificacion($feriado['fecha_inicio']) ?></td>
                                <td><?= escaparVerificacion($feriado['fecha_fin']) ?></td>
                                <td class="<?= $activo ? 'active' : 'disabled' ?>"><?= escaparVerificacion($feriado['estado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Próximos 15 días</h2>
            <div class="scroll">
                <table>
                    <thead><tr><th>Día</th><th>Fecha</th><th>Horario que consume SLA</th><th>Motivo</th></tr></thead>
                    <tbody>
                    <?php foreach ($dias as $registro): ?>
                        <tr>
                            <td><?= escaparVerificacion($registro['nombre']) ?></td>
                            <td><?= escaparVerificacion($registro['fecha']) ?></td>
                            <td class="<?= $registro['tramos'] ? 'working' : 'closed' ?>">
                                <?= $registro['tramos'] ? escaparVerificacion(implode(', ', $registro['tramos'])) : 'No laborable' ?>
                            </td>
                            <td><?= escaparVerificacion($registro['motivo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
