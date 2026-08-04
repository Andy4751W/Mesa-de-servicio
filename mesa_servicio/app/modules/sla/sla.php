<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparSla(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirigirSla(string $mensaje): never
{
    header('Location: sla.php?msg=' . rawurlencode($mensaje));
    exit;
}

function unidadValida(string $unidad): bool
{
    return in_array($unidad, ['minutos', 'horas', 'dias'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $accion = $_POST['accion'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        redirigirSla('solicitud_invalida');
    }

    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $tiempo = filter_input(
                INPUT_POST,
                'tiempo_respuesta',
                FILTER_VALIDATE_INT
            );
            $unidad = (string) ($_POST['unidad'] ?? '');

            if (
                $nombre === ''
                || !$tiempo
                || $tiempo < 1
                || !unidadValida($unidad)
            ) {
                redirigirSla('datos_incompletos');
            }

            if ($accion === 'crear') {
                $stmt = $conn->prepare(
                    "INSERT INTO sla
                        (nombre, tiempo_respuesta, unidad, estado)
                     VALUES (?, ?, ?, 'activo')"
                );
                $stmt->bind_param('sis', $nombre, $tiempo, $unidad);
                $stmt->execute();
                $stmt->close();

                redirigirSla('sla_creado');
            }

            $idSla = filter_input(INPUT_POST, 'id_sla', FILTER_VALIDATE_INT);

            if (!$idSla) {
                redirigirSla('solicitud_invalida');
            }

            $stmt = $conn->prepare(
                "UPDATE sla
                 SET nombre = ?, tiempo_respuesta = ?, unidad = ?
                 WHERE id_sla = ?"
            );
            $stmt->bind_param('sisi', $nombre, $tiempo, $unidad, $idSla);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $verificar = $conn->prepare(
                    "SELECT id_sla FROM sla WHERE id_sla = ?"
                );
                $verificar->bind_param('i', $idSla);
                $verificar->execute();
                $verificar->store_result();
                $existe = $verificar->num_rows > 0;
                $verificar->close();

                if (!$existe) {
                    $stmt->close();
                    redirigirSla('sla_no_encontrado');
                }
            }

            $stmt->close();
            redirigirSla('sla_actualizado');
        }

        if ($accion === 'cambiar_estado') {
            $idSla = filter_input(INPUT_POST, 'id_sla', FILTER_VALIDATE_INT);
            $nuevoEstado = (string) ($_POST['nuevo_estado'] ?? '');

            if (
                !$idSla
                || !in_array($nuevoEstado, ['activo', 'inhabilitado'], true)
            ) {
                redirigirSla('solicitud_invalida');
            }

            $stmt = $conn->prepare(
                "UPDATE sla SET estado = ? WHERE id_sla = ?"
            );
            $stmt->bind_param('si', $nuevoEstado, $idSla);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $stmt->close();
                redirigirSla('sla_no_encontrado');
            }

            $stmt->close();
            redirigirSla(
                $nuevoEstado === 'activo'
                    ? 'sla_habilitado'
                    : 'sla_inhabilitado'
            );
        }

        if ($accion === 'eliminar') {
            $idSla = filter_input(INPUT_POST, 'id_sla', FILTER_VALIDATE_INT);

            if (!$idSla) {
                redirigirSla('solicitud_invalida');
            }

            $stmtUso = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM servicios
                 WHERE id_sla = ?"
            );
            $stmtUso->bind_param('i', $idSla);
            $stmtUso->execute();
            $filaUso = $stmtUso->get_result()->fetch_assoc();
            $stmtUso->close();

            if ((int) ($filaUso['total'] ?? 0) > 0) {
                redirigirSla('sla_en_uso');
            }

            $stmt = $conn->prepare("DELETE FROM sla WHERE id_sla = ?");
            $stmt->bind_param('i', $idSla);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $stmt->close();
                redirigirSla('sla_no_encontrado');
            }

            $stmt->close();
            redirigirSla('sla_eliminado');
        }

        redirigirSla('solicitud_invalida');
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            redirigirSla('sla_duplicado');
        }

        if ($e->getCode() === 1451) {
            redirigirSla('sla_en_uso');
        }

        redirigirSla('error_operacion');
    } catch (Throwable $e) {
        redirigirSla('error_operacion');
    }
}

$resultadoSla = $conn->query(
    "SELECT
        sl.id_sla,
        sl.nombre,
        sl.tiempo_respuesta,
        sl.unidad,
        sl.estado,
        COUNT(s.id_servicio) AS servicios_asignados
     FROM sla AS sl
     LEFT JOIN servicios AS s ON s.id_sla = sl.id_sla
     GROUP BY
        sl.id_sla,
        sl.nombre,
        sl.tiempo_respuesta,
        sl.unidad,
        sl.estado
     ORDER BY sl.estado ASC, sl.tiempo_respuesta ASC, sl.nombre ASC"
);

$mensajes = [
    'sla_creado' => ['exito', '✅ SLA creado correctamente.'],
    'sla_actualizado' => ['exito', '✏️ SLA actualizado correctamente.'],
    'sla_eliminado' => ['exito', '🗑️ SLA eliminado correctamente.'],
    'sla_habilitado' => ['exito', '✅ SLA habilitado correctamente.'],
    'sla_inhabilitado' => ['aviso', '⚠️ SLA inhabilitado correctamente.'],
    'sla_en_uso' => [
        'error',
        '❌ El SLA está asignado a uno o más servicios. Reasigne esos servicios antes de eliminarlo.'
    ],
    'sla_duplicado' => ['error', '❌ Ya existe un SLA con ese nombre.'],
    'sla_no_encontrado' => ['error', '❌ El SLA seleccionado no existe.'],
    'datos_incompletos' => [
        'error',
        '❌ Ingrese un nombre, un tiempo mayor que cero y una unidad válida.'
    ],
    'solicitud_invalida' => ['error', '❌ La solicitud no es válida.'],
    'error_operacion' => ['error', '❌ No fue posible completar la operación.'],
];

$mensajeActual = (string) ($_GET['msg'] ?? '');
$unidades = [
    'minutos' => 'Minutos',
    'horas' => 'Horas',
    'dias' => 'Días',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="sla-version" content="interfaz-2.0">
    <title>Administración de SLA</title>
    <style>
        :root {
            --primary: #1f5f99;
            --primary-dark: #163f65;
            --navy: #132f4c;
            --text: #263b50;
            --muted: #64788b;
            --surface: #ffffff;
            --background: #f3f6fb;
            --border: #dfe7f1;
            --danger: #a72836;
            --shadow: 0 16px 40px rgba(15, 45, 75, 0.09);
        }
        * {
            box-sizing: border-box;
        }
        html {
            min-height: 100%;
        }
        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at 6% 0%, rgba(31, 95, 153, 0.09), transparent 25%),
                var(--background);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.45;
        }
        button,
        input,
        select {
            font: inherit;
        }
        main {
            width: min(1240px, calc(100% - 40px));
            margin: 0 auto;
            padding: 28px 0 40px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
            padding: 13px 17px;
            border: 1px solid rgba(223, 231, 241, 0.9);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 8px 24px rgba(15, 45, 75, 0.06);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            flex: 0 0 auto;
        }
        .brand-mark {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: 11px;
            color: #fff;
            background: linear-gradient(145deg, #2b73ad, #163f65);
            box-shadow: 0 8px 18px rgba(22, 63, 101, 0.24);
            font-size: 14px;
            font-weight: 800;
        }
        .brand-name,
        .brand-subtitle {
            margin: 0;
        }
        .brand-name {
            color: var(--navy);
            font-size: 15px;
            font-weight: 750;
        }
        .brand-subtitle {
            color: var(--muted);
            font-size: 11px;
        }
        .barra {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 7px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 37px;
            padding: 8px 13px;
            border: 1px solid transparent;
            border-radius: 9px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 750;
            cursor: pointer;
            transition:
                color 0.18s ease,
                border-color 0.18s ease,
                background 0.18s ease,
                transform 0.18s ease;
        }
        .btn-volver,
        .btn-actualizar {
            color: #315779;
            border-color: #dce6f1;
            background: #f7faff;
        }
        .btn-volver:hover,
        .btn-actualizar:hover {
            color: var(--primary-dark);
            border-color: #bfd2e5;
            background: #edf5ff;
        }
        .btn-crear,
        .btn-guardar {
            color: #fff;
            border-color: var(--primary);
            background: var(--primary);
            box-shadow: 0 7px 16px rgba(31, 95, 153, 0.2);
        }
        .btn-crear:hover,
        .btn-guardar:hover {
            border-color: var(--primary-dark);
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .btn-cancelar {
            color: #486581;
            border-color: #d4dfe9;
            background: #fff;
        }
        .btn-cancelar:hover {
            background: #f1f6fc;
        }
        .hero {
            position: relative;
            min-height: 180px;
            margin-bottom: 28px;
            padding: 35px 39px;
            overflow: hidden;
            border-radius: 22px;
            color: #fff;
            background: linear-gradient(120deg, #132f4c 0%, #1f5f99 62%, #2b76aa 100%);
            box-shadow: var(--shadow);
        }
        .hero::after {
            position: absolute;
            top: -115px;
            right: -62px;
            width: 300px;
            height: 300px;
            border: 44px solid rgba(255, 255, 255, 0.07);
            border-radius: 50%;
            content: "";
        }
        .hero-copy {
            position: relative;
            z-index: 1;
            max-width: 720px;
        }
        .eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }
        .hero h1,
        .hero p {
            margin: 0;
        }
        .hero h1 {
            font-size: clamp(29px, 4vw, 42px);
            line-height: 1.12;
            letter-spacing: -1px;
        }
        .hero p {
            max-width: 640px;
            margin-top: 11px;
            color: rgba(255, 255, 255, 0.83);
            font-size: 14px;
        }
        .alerta {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 47px;
            margin: 0 0 18px;
            padding: 11px 15px;
            border: 1px solid transparent;
            border-radius: 11px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        .alerta.exito {
            border-color: #bde7cc;
            color: #17673a;
            background: #eaf8ef;
        }
        .alerta.aviso {
            border-color: #f1dda5;
            color: #7c5a06;
            background: #fff8df;
        }
        .alerta.error {
            border-color: #f1c3c8;
            color: #922b36;
            background: #fdecee;
        }
        .sla-panel {
            overflow: visible;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
            box-shadow: 0 10px 28px rgba(31, 62, 93, 0.07);
        }
        .panel-encabezado {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 19px 22px;
            border-bottom: 1px solid var(--border);
        }
        .panel-encabezado h2,
        .panel-encabezado p {
            margin: 0;
        }
        .panel-encabezado h2 {
            color: var(--navy);
            font-size: 20px;
            letter-spacing: -0.3px;
        }
        .panel-encabezado p {
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }
        .panel-etiqueta {
            flex: 0 0 auto;
            padding: 7px 11px;
            border: 1px solid #dce7f3;
            border-radius: 9px;
            color: #486581;
            background: #f7faff;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .tabla-contenedor {
            overflow: visible;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #fff;
            font-size: 12px;
        }
        th,
        td {
            padding: 11px 9px;
            border-right: 1px solid #e6edf5;
            border-bottom: 1px solid #e6edf5;
            text-align: center;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }
        th:last-child,
        td:last-child {
            border-right: none;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        th {
            color: #fff;
            background: var(--primary);
            font-size: 10px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        th:nth-child(1) { width: 65px; }
        th:nth-child(2) { width: 125px; }
        th:nth-child(3) { width: 25%; }
        th:nth-child(4) { width: 22%; }
        th:nth-child(5) { width: 160px; }
        th:nth-child(6) { width: 118px; }
        .fila-sla:nth-of-type(4n + 3) {
            background: #f9fbfe;
        }
        .fila-sla:hover {
            background: #f1f6fc;
        }
        .estado {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 86px;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 750;
            text-transform: capitalize;
        }
        .punto {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }
        .estado.activo {
            color: #15703b;
            background: #e5f6eb;
        }
        .estado.inhabilitado {
            color: #755a14;
            background: #fff3cf;
        }
        .tiempo-sla {
            display: inline-flex;
            align-items: baseline;
            gap: 4px;
            padding: 6px 10px;
            border-radius: 9px;
            color: #315779;
            background: #edf5fc;
        }
        .tiempo-sla strong {
            font-size: 13px;
        }
        .tiempo-sla span {
            color: var(--muted);
            font-size: 10px;
        }
        .servicios-conteo {
            display: inline-flex;
            min-width: 34px;
            min-height: 28px;
            align-items: center;
            justify-content: center;
            border: 1px solid #dce7f3;
            border-radius: 8px;
            color: #315779;
            background: #f7faff;
            font-weight: 750;
        }
        .celda-acciones {
            position: relative;
            overflow: visible;
        }
        .acciones-menu {
            position: relative;
            display: inline-block;
        }
        .acciones-menu summary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 86px;
            padding: 7px 10px;
            border: 1px solid #cad8e8;
            border-radius: 8px;
            color: #315779;
            background: #f7faff;
            font-size: 10px;
            font-weight: 750;
            list-style: none;
            white-space: nowrap;
            cursor: pointer;
        }
        .acciones-menu summary::-webkit-details-marker {
            display: none;
        }
        .acciones-menu summary::after {
            content: "⌄";
            font-size: 12px;
            transition: transform 0.18s ease;
        }
        .acciones-menu[open] summary {
            color: #fff;
            border-color: var(--primary);
            background: var(--primary);
        }
        .acciones-menu[open] summary::after {
            transform: rotate(180deg);
        }
        .acciones-desplegable {
            position: absolute;
            top: calc(100% + 7px);
            right: 0;
            z-index: 60;
            width: 170px;
            padding: 6px;
            border: 1px solid #dce5ef;
            border-radius: 11px;
            background: #fff;
            box-shadow: 0 16px 34px rgba(15, 45, 75, 0.2);
            text-align: left;
        }
        .fila-sla:nth-last-child(-n + 2) .acciones-desplegable {
            top: auto;
            bottom: calc(100% + 7px);
        }
        .acciones-desplegable form {
            margin: 0;
        }
        .accion-item {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            padding: 9px 10px;
            border: none;
            border-radius: 7px;
            color: #334e68;
            background: transparent;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
            cursor: pointer;
        }
        .accion-item::before {
            display: grid;
            flex: 0 0 auto;
            width: 20px;
            height: 20px;
            place-items: center;
            border-radius: 6px;
            font-size: 11px;
        }
        .accion-editar::before {
            color: #0b5ec2;
            background: #eaf2ff;
            content: "✎";
        }
        .accion-estado::before {
            color: #526d82;
            background: #edf2f7;
            content: "◐";
        }
        .accion-habilitar::before {
            color: #0b5ec2;
            background: #eaf2ff;
            content: "✓";
        }
        .accion-eliminar {
            color: var(--danger);
        }
        .accion-eliminar::before {
            color: var(--danger);
            background: #fde8eb;
            content: "×";
        }
        .accion-item:hover {
            background: #f1f6fc;
        }
        .accion-eliminar:hover {
            background: #fff0f2;
        }
        .fila-edicion {
            display: none;
            background: #f6f9fd;
        }
        .fila-edicion td {
            padding: 20px;
            text-align: left;
        }
        .form-edicion {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
            align-items: end;
            text-align: left;
        }
        .acciones-edicion {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding-top: 5px;
        }
        label {
            display: block;
            margin: 0 0 6px;
            color: #334e68;
            font-size: 11px;
            font-weight: 750;
        }
        input,
        select {
            width: 100%;
            margin: 0;
            padding: 10px 11px;
            border: 1px solid #cfdbe8;
            border-radius: 9px;
            color: var(--text);
            outline: none;
            background: #fff;
            font-size: 12px;
            transition:
                border-color 0.18s ease,
                box-shadow 0.18s ease;
        }
        input:focus,
        select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(31, 95, 153, 0.13);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            inset: 0;
            padding: 24px;
            overflow-y: auto;
            background: rgba(9, 30, 54, 0.62);
            backdrop-filter: blur(4px);
        }
        .modal-contenido {
            width: min(520px, 100%);
            max-height: calc(100vh - 48px);
            margin: 0 auto;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 28px 65px rgba(2, 20, 42, 0.32);
        }
        .modal-encabezado {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(8px);
        }
        .modal-encabezado h2,
        .modal-encabezado p {
            margin: 0;
        }
        .modal-encabezado h2 {
            color: var(--navy);
            font-size: 20px;
        }
        .modal-encabezado p {
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }
        .modal-cuerpo {
            padding: 21px 22px 24px;
        }
        .modal-grid {
            display: grid;
            gap: 15px;
        }
        .modal-acciones {
            display: flex;
            justify-content: flex-end;
            gap: 9px;
            margin-top: 19px;
            padding-top: 17px;
            border-top: 1px solid var(--border);
        }
        .cerrar {
            display: grid;
            flex: 0 0 auto;
            width: 36px;
            height: 36px;
            padding: 0;
            place-items: center;
            border: 1px solid #dce5ef;
            border-radius: 10px;
            color: #486581;
            background: #f7f9fc;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }
        .cerrar:hover {
            color: var(--danger);
            background: #fff0f2;
        }
        .sin-registros {
            margin: 0;
            padding: 28px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }
        @media (max-width: 850px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .barra {
                justify-content: flex-start;
                width: 100%;
            }
            .hero {
                min-height: auto;
                padding: 29px 25px;
            }
            .panel-encabezado {
                align-items: flex-start;
                flex-direction: column;
            }
            .tabla-contenedor {
                overflow-x: auto;
            }
            table {
                min-width: 900px;
            }
            .form-edicion {
                grid-template-columns: 1fr;
            }
            .acciones-edicion {
                grid-column: auto;
            }
        }
        @media (max-width: 560px) {
            main {
                width: min(100% - 24px, 1240px);
                padding-top: 12px;
            }
            .brand-subtitle {
                display: none;
            }
            .barra .btn {
                flex: 1 1 130px;
            }
            .modal {
                padding: 12px;
            }
            .modal-contenido {
                max-height: calc(100vh - 24px);
            }
            .modal-acciones,
            .acciones-edicion {
                align-items: stretch;
                flex-direction: column;
            }
            .modal-acciones .btn,
            .acciones-edicion .btn {
                width: 100%;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
<main>
    <header class="topbar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">MS</div>
            <div>
                <p class="brand-name">Mesa de Servicio</p>
                <p class="brand-subtitle">Administración de tiempos de respuesta</p>
            </div>
        </div>

        <nav class="barra" aria-label="Acciones de SLA">
            <a href="panelAdmin.php" class="btn btn-volver">← Volver al panel</a>
            <button type="button" class="btn btn-crear" onclick="abrirModal()">
                Añadir SLA
            </button>
            <a href="sla.php" class="btn btn-actualizar">Actualizar</a>
        </nav>
    </header>

    <?php if (isset($mensajes[$mensajeActual])): ?>
        <div class="alerta <?= escaparSla($mensajes[$mensajeActual][0]) ?>">
            <?= escaparSla($mensajes[$mensajeActual][1]) ?>
        </div>
    <?php endif; ?>

    <section class="hero">
        <div class="hero-copy">
            <span class="eyebrow">Configuración</span>
            <h1>Administración de SLA</h1>
            <p>
                Defina los tiempos de respuesta que deberán asignarse a los
                servicios de la mesa de ayuda.
            </p>
        </div>
    </section>

    <div id="modalSla" class="modal">
        <div
            class="modal-contenido"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-modal-sla"
        >
            <div class="modal-encabezado">
                <div>
                    <h2 id="titulo-modal-sla">Añadir SLA</h2>
                    <p>Defina el nombre y el tiempo máximo de respuesta.</p>
                </div>
                <button
                    type="button"
                    class="cerrar"
                    aria-label="Cerrar"
                    onclick="cerrarModal()"
                >&times;</button>
            </div>

            <div class="modal-cuerpo">
                <form method="POST" action="sla.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escaparSla($_SESSION['csrf_token']) ?>"
                    >
                    <input type="hidden" name="accion" value="crear">

                    <div class="modal-grid">
                        <div>
                            <label for="nombre-sla">Nombre</label>
                            <input
                                id="nombre-sla"
                                type="text"
                                name="nombre"
                                maxlength="120"
                                placeholder="Ejemplo: TICs - 4 horas"
                                required
                            >
                        </div>

                        <div>
                            <label for="tiempo-sla">Tiempo de respuesta</label>
                            <input
                                id="tiempo-sla"
                                type="number"
                                name="tiempo_respuesta"
                                min="1"
                                required
                            >
                        </div>

                        <div>
                            <label for="unidad-sla">Unidad</label>
                            <select id="unidad-sla" name="unidad" required>
                                <option value="minutos">Minutos</option>
                                <option value="horas">Horas</option>
                                <option value="dias">Días</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-acciones">
                        <button type="submit" class="btn btn-guardar">Guardar SLA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <section class="sla-panel" aria-labelledby="titulo-listado-sla">
        <div class="panel-encabezado">
            <div>
                <h2 id="titulo-listado-sla">SLA configurados</h2>
                <p>Consulte los tiempos de respuesta y los servicios que los utilizan.</p>
            </div>
            <span class="panel-etiqueta">
                <?= (int) $resultadoSla->num_rows ?> registrados
            </span>
        </div>

        <?php if ($resultadoSla->num_rows > 0): ?>
            <div class="tabla-contenedor">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Estado</th>
                    <th>SLA</th>
                    <th>Tiempo de respuesta</th>
                    <th>Servicios asignados</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($sla = $resultadoSla->fetch_assoc()): ?>
                    <tr class="fila-sla">
                        <td><?= (int) $sla['id_sla'] ?></td>
                        <td>
                            <span class="estado <?= escaparSla($sla['estado']) ?>">
                                <span class="punto"></span>
                                <?= escaparSla(ucfirst($sla['estado'])) ?>
                            </span>
                        </td>
                        <td><?= escaparSla($sla['nombre']) ?></td>
                        <td>
                            <span class="tiempo-sla">
                                <strong><?= (int) $sla['tiempo_respuesta'] ?></strong>
                                <span>
                                    <?= escaparSla($unidades[$sla['unidad']] ?? $sla['unidad']) ?>
                                </span>
                            </span>
                        </td>
                        <td>
                            <span class="servicios-conteo">
                                <?= (int) $sla['servicios_asignados'] ?>
                            </span>
                        </td>
                        <td class="celda-acciones">
                            <details class="acciones-menu">
                                <summary>Acciones</summary>
                                <div class="acciones-desplegable">
                                <button
                                    type="button"
                                    class="accion-item accion-editar"
                                    onclick="alternarEdicion(<?= (int) $sla['id_sla'] ?>)"
                                >Editar</button>

                                <form method="POST" action="sla.php">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escaparSla($_SESSION['csrf_token']) ?>"
                                    >
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input
                                        type="hidden"
                                        name="id_sla"
                                        value="<?= (int) $sla['id_sla'] ?>"
                                    >

                                    <?php if ($sla['estado'] === 'activo'): ?>
                                        <input
                                            type="hidden"
                                            name="nuevo_estado"
                                            value="inhabilitado"
                                        >
                                        <button type="submit" class="accion-item accion-estado">
                                            Inhabilitar
                                        </button>
                                    <?php else: ?>
                                        <input
                                            type="hidden"
                                            name="nuevo_estado"
                                            value="activo"
                                        >
                                        <button type="submit" class="accion-item accion-habilitar">
                                            Habilitar
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <form
                                    method="POST"
                                    action="sla.php"
                                    onsubmit="return confirmarEliminacion();"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escaparSla($_SESSION['csrf_token']) ?>"
                                    >
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input
                                        type="hidden"
                                        name="id_sla"
                                        value="<?= (int) $sla['id_sla'] ?>"
                                    >
                                    <button type="submit" class="accion-item accion-eliminar">
                                        Eliminar
                                    </button>
                                </form>
                                </div>
                            </details>
                        </td>
                    </tr>

                    <tr
                        id="edicion-sla-<?= (int) $sla['id_sla'] ?>"
                        class="fila-edicion"
                    >
                        <td colspan="6">
                            <form method="POST" action="sla.php">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escaparSla($_SESSION['csrf_token']) ?>"
                                >
                                <input type="hidden" name="accion" value="editar">
                                <input
                                    type="hidden"
                                    name="id_sla"
                                    value="<?= (int) $sla['id_sla'] ?>"
                                >

                                <div class="form-edicion">
                                    <div>
                                        <label>Nombre</label>
                                        <input
                                            type="text"
                                            name="nombre"
                                            maxlength="120"
                                            value="<?= escaparSla($sla['nombre']) ?>"
                                            required
                                        >
                                    </div>
                                    <div>
                                        <label>Tiempo</label>
                                        <input
                                            type="number"
                                            name="tiempo_respuesta"
                                            min="1"
                                            value="<?= (int) $sla['tiempo_respuesta'] ?>"
                                            required
                                        >
                                    </div>
                                    <div>
                                        <label>Unidad</label>
                                        <select name="unidad" required>
                                            <?php foreach ($unidades as $valor => $etiqueta): ?>
                                                <option
                                                    value="<?= escaparSla($valor) ?>"
                                                    <?= $sla['unidad'] === $valor ? 'selected' : '' ?>
                                                ><?= escaparSla($etiqueta) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="acciones-edicion">
                                        <button type="submit" class="btn btn-guardar">
                                            Guardar cambios
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-cancelar"
                                            onclick="alternarEdicion(<?= (int) $sla['id_sla'] ?>)"
                                        >Cancelar</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="sin-registros">Todavía no hay SLA registrados.</div>
        <?php endif; ?>
    </section>
</main>

<script>
    function abrirModal() {
        document.getElementById('modalSla').style.display = 'block';
    }

    function cerrarModal() {
        document.getElementById('modalSla').style.display = 'none';
    }

    function alternarEdicion(id) {
        const fila = document.getElementById('edicion-sla-' + id);
        fila.style.display = fila.style.display === 'table-row'
            ? 'none'
            : 'table-row';

        document.querySelectorAll('.acciones-menu[open]').forEach(function (menu) {
            menu.removeAttribute('open');
        });
    }

    function confirmarEliminacion() {
        return window.confirm(
            '¿Está seguro de eliminar este SLA? Solo se eliminará si no está asignado a servicios.'
        );
    }

    window.addEventListener('click', function (event) {
        if (event.target.id === 'modalSla') {
            cerrarModal();
        }

        const menuSeleccionado = event.target.closest('.acciones-menu');

        document.querySelectorAll('.acciones-menu[open]').forEach(function (menu) {
            if (menu !== menuSeleccionado) {
                menu.removeAttribute('open');
            }
        });
    });

    window.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        cerrarModal();

        document.querySelectorAll('.acciones-menu[open]').forEach(function (menu) {
            menu.removeAttribute('open');
        });
    });
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
