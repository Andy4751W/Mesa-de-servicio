<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}
$idPaisOperacion = paisExigirContexto();
$ciudadHorarioBase = paisContextoCodigo() === 'PE' ? 'Lima' : 'Bogotá';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparFeriado(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function tablaFeriadosExiste(mysqli $conn): bool
{
    try {
        $resultado = $conn->query("SHOW TABLES LIKE 'feriados'");

        return $resultado !== false && $resultado->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function redirigirFeriados(string $mensaje): never
{
    header('Location: feriados.php?msg=' . rawurlencode($mensaje));
    exit;
}

function normalizarRangoFeriado(
    string $tipo,
    array $entrada
): ?array {
    $zona = new DateTimeZone(paisZonaHorariaActual());

    if ($tipo === 'dia_completo') {
        $inicioTexto = trim((string) ($entrada['fecha_inicio_dia'] ?? ''));
        $finTexto = trim((string) ($entrada['fecha_fin_dia'] ?? ''));
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $inicioTexto, $zona);
        $finInclusivo = DateTimeImmutable::createFromFormat('!Y-m-d', $finTexto, $zona);

        if (
            !$inicio
            || !$finInclusivo
            || $inicio->format('Y-m-d') !== $inicioTexto
            || $finInclusivo->format('Y-m-d') !== $finTexto
            || $finInclusivo < $inicio
        ) {
            return null;
        }

        return [
            $inicio->format('Y-m-d 00:00:00'),
            $finInclusivo->modify('+1 day')->format('Y-m-d 00:00:00'),
        ];
    }

    if ($tipo !== 'rango_horario') {
        return null;
    }

    $inicioTexto = trim((string) ($entrada['fecha_inicio_hora'] ?? ''));
    $finTexto = trim((string) ($entrada['fecha_fin_hora'] ?? ''));
    $inicio = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $inicioTexto,
        $zona
    );
    $fin = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $finTexto,
        $zona
    );

    if (
        !$inicio
        || !$fin
        || $inicio->format('Y-m-d\TH:i') !== $inicioTexto
        || $fin->format('Y-m-d\TH:i') !== $finTexto
        || $fin <= $inicio
    ) {
        return null;
    }

    return [
        $inicio->format('Y-m-d H:i:s'),
        $fin->format('Y-m-d H:i:s'),
    ];
}

$moduloInstalado = tablaFeriadosExiste($conn);
$idAdministrador = (int) $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $accion = (string) ($_POST['accion'] ?? '');

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        redirigirFeriados('solicitud_invalida');
    }

    if (!$moduloInstalado) {
        redirigirFeriados('instalacion_pendiente');
    }

    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $tipo = (string) ($_POST['tipo'] ?? '');
            $rango = normalizarRangoFeriado($tipo, $_POST);

            if (
                $nombre === ''
                || strlen($nombre) > 160
                || strlen($descripcion) > 500
                || !$rango
            ) {
                redirigirFeriados('datos_incompletos');
            }

            [$fechaInicio, $fechaFin] = $rango;

            if ($accion === 'crear') {
                $stmt = $conn->prepare(
                    "INSERT INTO feriados
                        (
                            id_pais_operacion,
                            nombre,
                            tipo,
                            fecha_inicio,
                            fecha_fin,
                            descripcion,
                            estado,
                            id_creado_por
                        )
                     VALUES (?, ?, ?, ?, ?, ?, 'activo', ?)"
                );
                $stmt->bind_param(
                    'isssssi',
                    $idPaisOperacion,
                    $nombre,
                    $tipo,
                    $fechaInicio,
                    $fechaFin,
                    $descripcion,
                    $idAdministrador
                );
                $stmt->execute();
                $stmt->close();
                redirigirFeriados('feriado_creado');
            }

            $idFeriado = filter_input(
                INPUT_POST,
                'id_feriado',
                FILTER_VALIDATE_INT
            );

            if (!$idFeriado) {
                redirigirFeriados('solicitud_invalida');
            }

            $stmt = $conn->prepare(
                "UPDATE feriados
                 SET nombre = ?,
                     tipo = ?,
                     fecha_inicio = ?,
                     fecha_fin = ?,
                     descripcion = ?
                 WHERE id_feriado = ? AND id_pais_operacion = ?"
            );
            $stmt->bind_param(
                'sssssii',
                $nombre,
                $tipo,
                $fechaInicio,
                $fechaFin,
                $descripcion,
                $idFeriado,
                $idPaisOperacion
            );
            $stmt->execute();
            $stmt->close();
            redirigirFeriados('feriado_actualizado');
        }

        if ($accion === 'cambiar_estado') {
            $idFeriado = filter_input(
                INPUT_POST,
                'id_feriado',
                FILTER_VALIDATE_INT
            );
            $estado = (string) ($_POST['nuevo_estado'] ?? '');

            if (
                !$idFeriado
                || !in_array($estado, ['activo', 'inhabilitado'], true)
            ) {
                redirigirFeriados('solicitud_invalida');
            }

            $stmt = $conn->prepare(
                'UPDATE feriados SET estado = ?
                 WHERE id_feriado = ? AND id_pais_operacion = ?'
            );
            $stmt->bind_param('sii', $estado, $idFeriado, $idPaisOperacion);
            $stmt->execute();
            $stmt->close();
            redirigirFeriados(
                $estado === 'activo'
                    ? 'feriado_habilitado'
                    : 'feriado_inhabilitado'
            );
        }

        if ($accion === 'eliminar') {
            $idFeriado = filter_input(
                INPUT_POST,
                'id_feriado',
                FILTER_VALIDATE_INT
            );

            if (!$idFeriado) {
                redirigirFeriados('solicitud_invalida');
            }

            $stmt = $conn->prepare(
                'DELETE FROM feriados
                 WHERE id_feriado = ? AND id_pais_operacion = ?'
            );
            $stmt->bind_param('ii', $idFeriado, $idPaisOperacion);
            $stmt->execute();
            $stmt->close();
            redirigirFeriados('feriado_eliminado');
        }

        redirigirFeriados('solicitud_invalida');
    } catch (mysqli_sql_exception $e) {
        error_log('Error administrando feriados: ' . $e->getMessage());
        redirigirFeriados('error_operacion');
    }
}

$feriados = [];
$resumen = ['total' => 0, 'activos' => 0, 'vigentes' => 0, 'proximos' => 0];

if ($moduloInstalado) {
    $resultado = $conn->query(
        "SELECT
            f.*,
            COALESCE(u.nombre, 'Usuario eliminado') AS creado_por
         FROM feriados AS f
         LEFT JOIN usuarios AS u
            ON u.id_usuario = f.id_creado_por
         WHERE f.id_pais_operacion = {$idPaisOperacion}
         ORDER BY
            FIELD(f.estado, 'activo', 'inhabilitado'),
            CASE WHEN f.fecha_fin >= NOW() THEN 0 ELSE 1 END,
            f.fecha_inicio ASC,
            f.id_feriado DESC
         LIMIT 500"
    );
    $feriados = $resultado->fetch_all(MYSQLI_ASSOC);

    $resultadoResumen = $conn->query(
        "SELECT
            COUNT(*) AS total,
            SUM(estado = 'activo') AS activos,
            SUM(
                estado = 'activo'
                AND NOW() >= fecha_inicio
                AND NOW() < fecha_fin
            ) AS vigentes,
            SUM(
                estado = 'activo'
                AND fecha_inicio > NOW()
            ) AS proximos
         FROM feriados
         WHERE id_pais_operacion = {$idPaisOperacion}"
    );
    $datosResumen = $resultadoResumen->fetch_assoc() ?: [];

    foreach ($resumen as $clave => $valor) {
        $resumen[$clave] = (int) ($datosResumen[$clave] ?? 0);
    }
}

$mensajes = [
    'feriado_creado' => ['exito', 'El festivo o periodo no laborable fue creado y ya se excluye del cálculo del SLA.'],
    'feriado_actualizado' => ['exito', 'El festivo o periodo no laborable fue actualizado correctamente.'],
    'feriado_habilitado' => ['exito', 'El registro fue habilitado y vuelve a afectar el SLA.'],
    'feriado_inhabilitado' => ['aviso', 'El registro fue inhabilitado y dejó de afectar el SLA.'],
    'feriado_eliminado' => ['exito', 'El registro fue eliminado correctamente.'],
    'datos_incompletos' => ['error', 'Revise el nombre y el rango de fechas indicado.'],
    'solicitud_invalida' => ['error', 'La operación solicitada no es válida.'],
    'instalacion_pendiente' => ['aviso', 'Primero debe ejecutar crear_modulo_feriados.sql.'],
    'error_operacion' => ['error', 'No fue posible completar la operación.'],
];
$mensajeActual = (string) ($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feriados | Mesa de Servicio</title>
    <style>
        :root {
            --primary: #0f6fec;
            --primary-dark: #0b4fae;
            --navy: #102a43;
            --text: #243b53;
            --muted: #627d98;
            --surface: #fff;
            --background: #f3f6fb;
            --border: #dfe7f1;
            --success: #15703b;
            --warning: #94620a;
            --danger: #a72836;
            --shadow: 0 16px 40px rgba(15,45,75,.09);
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at 7% 0%, rgba(15,111,236,.09), transparent 26%),
                var(--background);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
        }
        button, input, select, textarea { font: inherit; }
        .page-shell {
            width: min(1240px, calc(100% - 32px));
            margin: 0 auto;
            padding: 18px 0 32px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 14px;
            padding: 11px 15px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: rgba(255,255,255,.95);
            box-shadow: 0 8px 24px rgba(15,45,75,.06);
        }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand-mark {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(145deg, #0f7af5, #0b4fae);
            font-size: 13px;
            font-weight: 800;
        }
        .brand p { margin: 0; }
        .brand-name { color: var(--navy); font-size: 14px; font-weight: 750; }
        .brand-subtitle { color: var(--muted); font-size: 10px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            min-height: 36px;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border: 1px solid transparent;
            border-radius: 9px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 750;
            cursor: pointer;
        }
        .btn-primary { color: #fff; background: var(--primary); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-soft { color: #315779; border-color: var(--border); background: #f8fbff; }
        .btn-warning { color: #805406; border-color: #f0d8a3; background: #fff8e8; }
        .btn-danger { color: var(--danger); border-color: #efc7cd; background: #fff5f6; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .hero {
            position: relative;
            min-height: 64px;
            display: grid;
            grid-template-columns: auto minmax(250px,1fr) auto;
            align-items: center;
            gap: 13px;
            padding: 10px 15px;
            overflow: hidden;
            border-radius: 14px;
            color: #fff;
            background: linear-gradient(120deg, #102a43, #176aa9 64%, #2691c9);
            box-shadow: 0 8px 22px rgba(31,62,93,.12);
        }
        .hero::after {
            position: absolute;
            top: -58px;
            right: -18px;
            width: 110px;
            height: 110px;
            border: 16px solid rgba(255,255,255,.07);
            border-radius: 50%;
            content: "";
        }
        .eyebrow {
            display: inline-flex;
            margin: 0;
            padding: 4px 7px;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 999px;
            background: rgba(255,255,255,.1);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .hero-copy { min-width: 0; }
        .hero h1 { margin: 0; font-size: 18px; line-height: 1.15; }
        .hero p { max-width: 760px; margin: 2px 0 0; color: rgba(255,255,255,.84); font-size: 10px; line-height: 1.3; }
        .schedule-chip {
            display: inline-flex;
            position: relative;
            z-index: 1;
            margin: 0;
            padding: 5px 8px;
            border-radius: 8px;
            background: rgba(255,255,255,.14);
            font-size: 8px;
            font-weight: 750;
            white-space: nowrap;
        }
        .alert {
            margin: 13px 0;
            padding: 11px 13px;
            border: 1px solid;
            border-radius: 10px;
            font-size: 11px;
        }
        .alert.exito { color: #125b32; border-color: #b9e5c8; background: #edfaf2; }
        .alert.aviso { color: #76530c; border-color: #efd99f; background: #fff8e3; }
        .alert.error { color: #8c2632; border-color: #efc4ca; background: #fff1f3; }
        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin: 14px 0;
        }
        .summary-card {
            padding: 13px 15px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 6px 18px rgba(31,62,93,.05);
        }
        .summary-card small { display: block; color: var(--muted); font-size: 9px; text-transform: uppercase; }
        .summary-card strong { display: block; margin-top: 3px; color: var(--navy); font-size: 22px; }
        .panel {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: #fff;
            box-shadow: var(--shadow);
        }
        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 15px 17px;
            border-bottom: 1px solid var(--border);
        }
        .panel-heading h2, .panel-heading p { margin: 0; }
        .panel-heading h2 { color: var(--navy); font-size: 18px; }
        .panel-heading p { margin-top: 2px; color: var(--muted); font-size: 10px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 1050px; border-collapse: collapse; font-size: 10.5px; }
        th, td { padding: 10px; border-right: 1px solid #e8eef5; border-bottom: 1px solid #e8eef5; text-align: left; vertical-align: middle; }
        th { color: #fff; background: #1f5f99; font-size: 9px; letter-spacing: .04em; text-transform: uppercase; }
        th:last-child, td:last-child { border-right: 0; }
        tbody tr:nth-child(even) { background: #f9fbfe; }
        .status, .type-badge, .period-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 750;
            white-space: nowrap;
        }
        .status.activo { color: #126438; background: #e4f6eb; }
        .status.inhabilitado { color: #687b8d; background: #eef2f6; }
        .type-badge { color: #315779; background: #edf5fc; }
        .period-badge.vigente { color: #126438; background: #e4f6eb; }
        .period-badge.proxima { color: #755a14; background: #fff3cf; }
        .period-badge.finalizada { color: #687b8d; background: #eef2f6; }
        .cell-title { color: var(--navy); font-weight: 750; }
        .cell-subtitle { display: block; max-width: 240px; margin-top: 2px; overflow: hidden; color: var(--muted); text-overflow: ellipsis; white-space: nowrap; }
        .row-actions { display: flex; gap: 6px; align-items: center; }
        .row-actions form { margin: 0; }
        .row-actions .btn { min-height: 30px; padding: 5px 8px; font-size: 9px; }
        .empty { padding: 35px; color: var(--muted); text-align: center; font-size: 12px; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            inset: 0;
            padding: 18px;
            overflow-y: auto;
            background: rgba(8,30,52,.65);
            backdrop-filter: blur(4px);
        }
        .modal.visible { display: grid; place-items: center; }
        .modal-card {
            width: min(680px, 100%);
            max-height: calc(100vh - 36px);
            overflow-y: auto;
            border-radius: 17px;
            background: #fff;
            box-shadow: 0 28px 65px rgba(2,20,42,.32);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
        }
        .modal-header h2, .modal-header p { margin: 0; }
        .modal-header h2 { color: var(--navy); font-size: 19px; }
        .modal-header p { margin-top: 2px; color: var(--muted); font-size: 10px; }
        .close {
            width: 34px;
            height: 34px;
            border: 1px solid var(--border);
            border-radius: 9px;
            color: #486581;
            background: #f8fbff;
            font-size: 20px;
            cursor: pointer;
        }
        .modal-body { padding: 18px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 13px; }
        .full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 5px; color: #334e68; font-size: 10px; font-weight: 750; }
        input, select, textarea {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid #cfdbe8;
            border-radius: 9px;
            color: var(--text);
            background: #fff;
            font-size: 11px;
            outline: none;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15,111,236,.12); }
        textarea { min-height: 80px; resize: vertical; }
        .form-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 15px; padding-top: 14px; border-top: 1px solid var(--border); }
        .hidden { display: none !important; }
        @media (max-width: 760px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .summary { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
        }
        @media (max-width: 440px) {
            .page-shell { width: min(100% - 20px, 1240px); }
            .hero { grid-template-columns: 1fr; gap: 5px; padding: 10px 12px; }
            .schedule-chip { white-space: normal; }
            .summary { grid-template-columns: 1fr 1fr; }
            .actions .btn { flex: 1; }
        }
    </style>
</head>
<body>
<main class="page-shell">
    <header class="topbar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">MS</div>
            <div>
                <p class="brand-name">Mesa de Servicio</p>
                <p class="brand-subtitle">Calendario laboral y SLA</p>
            </div>
        </div>
        <nav class="actions" aria-label="Acciones del calendario">
            <a href="panelAdmin.php" class="btn btn-soft">← Volver al panel</a>
            <button
                type="button"
                class="btn btn-primary"
                onclick="abrirFeriado()"
                <?= !$moduloInstalado ? 'disabled' : '' ?>
            >Añadir festivo</button>
            <a href="verificarCalendarioSla.php" class="btn btn-soft">Verificar SLA</a>
            <a href="feriados.php" class="btn btn-soft">Actualizar</a>
        </nav>
    </header>

    <section class="hero">
        <span class="eyebrow">Configuración de SLA</span>
        <div class="hero-copy">
            <h1>Feriados y tiempos no laborables</h1>
            <p>
                El SLA excluye sábados, domingos y los periodos activos registrados en este calendario.
            </p>
        </div>
        <span class="schedule-chip">L–V · 08:00–18:00 · <?= escaparFeriado($ciudadHorarioBase) ?> · calendario manual</span>
    </section>

    <?php if (!$moduloInstalado): ?>
        <div class="alert aviso">
            Para habilitar este módulo, ejecute <strong>crear_modulo_feriados.sql</strong>
            en la base de datos <strong>mesa_servicio</strong>.
        </div>
    <?php elseif (isset($mensajes[$mensajeActual])): ?>
        <div class="alert <?= escaparFeriado($mensajes[$mensajeActual][0]) ?>">
            <?= escaparFeriado($mensajes[$mensajeActual][1]) ?>
        </div>
    <?php endif; ?>

    <section class="summary" aria-label="Resumen del calendario">
        <div class="summary-card"><small>Total de registros</small><strong><?= $resumen['total'] ?></strong></div>
        <div class="summary-card"><small>Activas</small><strong><?= $resumen['activos'] ?></strong></div>
        <div class="summary-card"><small>En curso ahora</small><strong><?= $resumen['vigentes'] ?></strong></div>
        <div class="summary-card"><small>Próximas</small><strong><?= $resumen['proximos'] ?></strong></div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Festivos y periodos no laborables</h2>
                <p>Todo festivo que deba detener el SLA debe aparecer aquí con estado activo.</p>
            </div>
        </div>

        <?php if ($feriados): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Festivo o periodo</th>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Periodo</th>
                        <th>Registrado por</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($feriados as $feriado): ?>
                        <?php
                        $zona = new DateTimeZone('America/Bogota');
                        $inicio = new DateTimeImmutable(
                            (string) $feriado['fecha_inicio'],
                            $zona
                        );
                        $fin = new DateTimeImmutable(
                            (string) $feriado['fecha_fin'],
                            $zona
                        );
                        $ahora = new DateTimeImmutable('now', $zona);
                        $esCompleto = $feriado['tipo'] === 'dia_completo';
                        $finVisible = $esCompleto ? $fin->modify('-1 second') : $fin;
                        $periodo = $ahora >= $inicio && $ahora < $fin
                            ? 'vigente'
                            : ($inicio > $ahora ? 'proxima' : 'finalizada');
                        $finDiaFormulario = $esCompleto
                            ? $fin->modify('-1 day')->format('Y-m-d')
                            : '';
                        ?>
                        <tr>
                            <td><span class="status <?= escaparFeriado($feriado['estado']) ?>"><?= escaparFeriado(ucfirst($feriado['estado'])) ?></span></td>
                            <td>
                                <span class="cell-title"><?= escaparFeriado($feriado['nombre']) ?></span>
                                <?php if ($feriado['descripcion']): ?>
                                    <span class="cell-subtitle" title="<?= escaparFeriado($feriado['descripcion']) ?>"><?= escaparFeriado($feriado['descripcion']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="type-badge"><?= $esCompleto ? 'Día completo' : 'Rango horario' ?></span></td>
                            <td><?= escaparFeriado($esCompleto ? $inicio->format('d/m/Y') : $inicio->format('d/m/Y H:i')) ?></td>
                            <td><?= escaparFeriado($esCompleto ? $finVisible->format('d/m/Y') : $finVisible->format('d/m/Y H:i')) ?></td>
                            <td><span class="period-badge <?= $periodo ?>"><?= escaparFeriado(ucfirst($periodo)) ?></span></td>
                            <td><?= escaparFeriado($feriado['creado_por']) ?></td>
                            <td>
                                <div class="row-actions">
                                    <button
                                        type="button"
                                        class="btn btn-soft"
                                        data-id="<?= (int) $feriado['id_feriado'] ?>"
                                        data-nombre="<?= escaparFeriado($feriado['nombre']) ?>"
                                        data-tipo="<?= escaparFeriado($feriado['tipo']) ?>"
                                        data-descripcion="<?= escaparFeriado($feriado['descripcion']) ?>"
                                        data-inicio-dia="<?= $esCompleto ? escaparFeriado($inicio->format('Y-m-d')) : '' ?>"
                                        data-fin-dia="<?= escaparFeriado($finDiaFormulario) ?>"
                                        data-inicio-hora="<?= !$esCompleto ? escaparFeriado($inicio->format('Y-m-d\TH:i')) : '' ?>"
                                        data-fin-hora="<?= !$esCompleto ? escaparFeriado($fin->format('Y-m-d\TH:i')) : '' ?>"
                                        onclick="editarFeriado(this)"
                                    >Editar</button>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= escaparFeriado($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id_feriado" value="<?= (int) $feriado['id_feriado'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $feriado['estado'] === 'activo' ? 'inhabilitado' : 'activo' ?>">
                                        <button type="submit" class="btn btn-warning"><?= $feriado['estado'] === 'activo' ? 'Inhabilitar' : 'Habilitar' ?></button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('¿Eliminar definitivamente este registro?');">
                                        <input type="hidden" name="csrf_token" value="<?= escaparFeriado($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_feriado" value="<?= (int) $feriado['id_feriado'] ?>">
                                        <button type="submit" class="btn btn-danger">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <?= $moduloInstalado
                    ? 'No hay festivos registrados. En este momento el SLA solo excluye sábados y domingos.'
                    : 'El módulo todavía no está instalado.' ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<div id="modalFeriado" class="modal">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <div class="modal-header">
            <div>
                <h2 id="tituloModal">Añadir festivo</h2>
                <p id="subtituloModal">Indique el periodo que no consumirá tiempo del SLA.</p>
            </div>
            <button type="button" class="close" onclick="cerrarFeriado()" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="formFeriado">
                <input type="hidden" name="csrf_token" value="<?= escaparFeriado($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="accion" id="accionFeriado" value="crear">
                <input type="hidden" name="id_feriado" id="idFeriado" value="">

                <div class="form-grid">
                    <div class="full">
                        <label for="nombreFeriado">Nombre del festivo o periodo *</label>
                        <input id="nombreFeriado" type="text" name="nombre" maxlength="160" placeholder="Ejemplo: Día de la Independencia" required>
                    </div>
                    <div class="full">
                        <label for="tipoFeriado">Tipo de periodo *</label>
                        <select id="tipoFeriado" name="tipo" onchange="cambiarTipoFeriado()" required>
                            <option value="dia_completo">Día completo o varios días</option>
                            <option value="rango_horario">Rango de horas</option>
                        </select>
                    </div>
                    <div id="grupoInicioDia">
                        <label for="inicioDia">Fecha inicial *</label>
                        <input id="inicioDia" type="date" name="fecha_inicio_dia">
                    </div>
                    <div id="grupoFinDia">
                        <label for="finDia">Fecha final *</label>
                        <input id="finDia" type="date" name="fecha_fin_dia">
                    </div>
                    <div id="grupoInicioHora" class="hidden">
                        <label for="inicioHora">Inicio del rango *</label>
                        <input id="inicioHora" type="datetime-local" name="fecha_inicio_hora">
                    </div>
                    <div id="grupoFinHora" class="hidden">
                        <label for="finHora">Fin del rango *</label>
                        <input id="finHora" type="datetime-local" name="fecha_fin_hora">
                    </div>
                    <div class="full">
                        <label for="descripcionFeriado">Descripción</label>
                        <textarea id="descripcionFeriado" name="descripcion" maxlength="500" placeholder="Motivo o detalle del periodo no laborable."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-soft" onclick="cerrarFeriado()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar registro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const modalFeriado = document.getElementById('modalFeriado');

    function cambiarTipoFeriado() {
        const completo = document.getElementById('tipoFeriado').value === 'dia_completo';
        document.getElementById('grupoInicioDia').classList.toggle('hidden', !completo);
        document.getElementById('grupoFinDia').classList.toggle('hidden', !completo);
        document.getElementById('grupoInicioHora').classList.toggle('hidden', completo);
        document.getElementById('grupoFinHora').classList.toggle('hidden', completo);
        document.getElementById('inicioDia').required = completo;
        document.getElementById('finDia').required = completo;
        document.getElementById('inicioHora').required = !completo;
        document.getElementById('finHora').required = !completo;
    }

    function abrirFeriado() {
        document.getElementById('formFeriado').reset();
        document.getElementById('accionFeriado').value = 'crear';
        document.getElementById('idFeriado').value = '';
        document.getElementById('tituloModal').textContent = 'Añadir festivo';
        document.getElementById('tipoFeriado').value = 'dia_completo';
        cambiarTipoFeriado();
        modalFeriado.classList.add('visible');
    }

    function editarFeriado(boton) {
        document.getElementById('formFeriado').reset();
        document.getElementById('accionFeriado').value = 'editar';
        document.getElementById('idFeriado').value = boton.dataset.id;
        document.getElementById('tituloModal').textContent = 'Editar festivo o periodo';
        document.getElementById('nombreFeriado').value = boton.dataset.nombre || '';
        document.getElementById('descripcionFeriado').value = boton.dataset.descripcion || '';
        document.getElementById('tipoFeriado').value = boton.dataset.tipo || 'dia_completo';
        document.getElementById('inicioDia').value = boton.dataset.inicioDia || '';
        document.getElementById('finDia').value = boton.dataset.finDia || '';
        document.getElementById('inicioHora').value = boton.dataset.inicioHora || '';
        document.getElementById('finHora').value = boton.dataset.finHora || '';
        cambiarTipoFeriado();
        modalFeriado.classList.add('visible');
    }

    function cerrarFeriado() {
        modalFeriado.classList.remove('visible');
    }

    modalFeriado.addEventListener('click', function (event) {
        if (event.target === modalFeriado) {
            cerrarFeriado();
        }
    });

    window.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            cerrarFeriado();
        }
    });
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
