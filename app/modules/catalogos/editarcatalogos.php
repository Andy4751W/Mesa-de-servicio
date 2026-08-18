<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
seguridadExigirRol([1]);
$idPaisOperacion = paisExigirContexto();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirigir($mensaje, $tipo = 'msg')
{
    header("Location: editarcatalogos.php?{$tipo}=" . urlencode($mensaje));
    exit;
}

function guardarImagen($archivo, $imagenActual = null)
{
    if (
        !isset($archivo['error']) ||
        $archivo['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return $imagenActual;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('La imagen no pudo cargarse.');
    }

    if ($archivo['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('La imagen supera el tamaño máximo de 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tipoMime = $finfo->file($archivo['tmp_name']);
    $extensionesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($extensionesPermitidas[$tipoMime])) {
        throw new RuntimeException('Solo se permiten imágenes JPG, PNG, GIF o WEBP.');
    }

    $directorioFisico = seguridadDirectorioPrivado('catalogos');
    if (
        !is_dir($directorioFisico)
        && !mkdir($directorioFisico, 0750, true)
        && !is_dir($directorioFisico)
    ) {
        throw new RuntimeException('No fue posible crear el directorio de imágenes.');
    }

    $nombreArchivo = 'catalogo_' . bin2hex(random_bytes(8)) . '.' . $extensionesPermitidas[$tipoMime];
    $destinoFisico = $directorioFisico . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $destinoFisico)) {
        throw new RuntimeException('No fue posible guardar la imagen.');
    }

    @chmod($destinoFisico, 0640);

    return 'private/catalogos/' . $nombreArchivo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    seguridadExigirOrigenPost();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        redirigir('La solicitud no es válida. Actualice la página e inténtelo nuevamente.', 'error');
    }

    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $nombre = seguridadTexto($_POST['nombre'] ?? '', 120);
            $descripcion = seguridadTexto($_POST['descripcion'] ?? '', 500);

            if ($nombre === '') {
                throw new RuntimeException('El nombre del catálogo es obligatorio.');
            }

            $imagen = guardarImagen($_FILES['imagen'] ?? []);
            $estado = 'activo';

            $stmt = $conn->prepare(
                "INSERT INTO catalogos
                    (id_pais_operacion, nombre, descripcion, imagen, estado)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("issss", $idPaisOperacion, $nombre, $descripcion, $imagen, $estado);

            if (!$stmt->execute()) {
                throw new RuntimeException('No fue posible crear el catálogo.');
            }

            $stmt->close();
            redirigir('creado');
        }

        if ($accion === 'editar') {
            $idCatalogo = filter_input(INPUT_POST, 'id_catalogo', FILTER_VALIDATE_INT);
            $nombre = seguridadTexto($_POST['nombre'] ?? '', 120);
            $descripcion = seguridadTexto($_POST['descripcion'] ?? '', 500);

            if (!$idCatalogo || $nombre === '') {
                throw new RuntimeException('Los datos del catálogo están incompletos.');
            }

            $stmtImagen = $conn->prepare(
                "SELECT imagen FROM catalogos
                 WHERE id_catalogo = ? AND id_pais_operacion = ?"
            );
            $stmtImagen->bind_param("ii", $idCatalogo, $idPaisOperacion);
            $stmtImagen->execute();
            $stmtImagen->bind_result($imagenActual);

            if (!$stmtImagen->fetch()) {
                $stmtImagen->close();
                throw new RuntimeException('El catálogo que intenta editar no existe.');
            }
            $stmtImagen->close();

            $imagen = guardarImagen($_FILES['imagen'] ?? [], $imagenActual);

            $stmt = $conn->prepare(
                "UPDATE catalogos
                 SET nombre = ?, descripcion = ?, imagen = ?
                 WHERE id_catalogo = ? AND id_pais_operacion = ?"
            );
            $stmt->bind_param("sssii", $nombre, $descripcion, $imagen, $idCatalogo, $idPaisOperacion);

            if (!$stmt->execute()) {
                throw new RuntimeException('No fue posible actualizar el catálogo.');
            }

            $stmt->close();
            redirigir('actualizado');
        }

        if ($accion === 'cambiar_estado') {
            $idCatalogo = filter_input(INPUT_POST, 'id_catalogo', FILTER_VALIDATE_INT);
            $nuevoEstado = $_POST['nuevo_estado'] ?? '';

            if (!$idCatalogo || !in_array($nuevoEstado, ['activo', 'inhabilitado'], true)) {
                throw new RuntimeException('El cambio de estado solicitado no es válido.');
            }

            $stmt = $conn->prepare(
                "UPDATE catalogos SET estado = ?
                 WHERE id_catalogo = ? AND id_pais_operacion = ?"
            );
            $stmt->bind_param("sii", $nuevoEstado, $idCatalogo, $idPaisOperacion);

            if (!$stmt->execute()) {
                throw new RuntimeException('No fue posible cambiar el estado del catálogo.');
            }

            $stmt->close();
            redirigir($nuevoEstado === 'activo' ? 'habilitado' : 'inhabilitado');
        }

        if ($accion === 'eliminar') {
            $idCatalogo = filter_input(INPUT_POST, 'id_catalogo', FILTER_VALIDATE_INT);

            if (!$idCatalogo) {
                throw new RuntimeException('El catálogo seleccionado no es válido.');
            }

            $conn->begin_transaction();

            try {
                if (!paisRegistroPertenece($conn, 'catalogos', $idCatalogo)) {
                    throw new RuntimeException('El catálogo no pertenece al país seleccionado.');
                }
                // Primero se eliminan todos los servicios asociados al catálogo.
                $stmtServicios = $conn->prepare(
                    "DELETE FROM servicios
                     WHERE id_catalogo = ? AND id_pais_operacion = ?"
                );
                $stmtServicios->bind_param("ii", $idCatalogo, $idPaisOperacion);

                if (!$stmtServicios->execute()) {
                    throw new RuntimeException('No fue posible eliminar los servicios asociados.');
                }
                $stmtServicios->close();

                // Después se elimina definitivamente el catálogo.
                $stmt = $conn->prepare(
                    "DELETE FROM catalogos
                     WHERE id_catalogo = ? AND id_pais_operacion = ?"
                );
                $stmt->bind_param("ii", $idCatalogo, $idPaisOperacion);

                if (!$stmt->execute()) {
                    throw new RuntimeException('No fue posible eliminar el catálogo.');
                }

                if ($stmt->affected_rows === 0) {
                    throw new RuntimeException('El catálogo que intenta eliminar no existe.');
                }

                $stmt->close();
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            redirigir('eliminado');
        }

        throw new RuntimeException('La acción solicitada no existe.');
    } catch (Throwable $e) {
        error_log('Catálogos: ' . $e->getMessage());
        $mensajeSeguro = get_class($e) === RuntimeException::class
            ? $e->getMessage()
            : 'No fue posible completar la operación.';
        redirigir($mensajeSeguro, 'error');
    }
}

$catalogos = $conn->query(
    "SELECT id_catalogo, nombre, descripcion, imagen, estado
     FROM catalogos
     WHERE id_pais_operacion = {$idPaisOperacion}
     ORDER BY nombre ASC"
);

$mensajes = [
    'creado' => '✅ Catálogo creado correctamente.',
    'actualizado' => '✏️ Catálogo actualizado correctamente.',
    'eliminado' => '🗑️ Catálogo y servicios asociados eliminados correctamente.',
    'inhabilitado' => '⚠️ Catálogo inhabilitado.',
    'habilitado' => '✅ Catálogo habilitado.',
];

$mensajeActual = $_GET['msg'] ?? '';
$errorActual = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="editarcatalogos-version" content="interfaz-2.0">
    <title>Administrar Catálogos</title>
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
        textarea {
            font: inherit;
        }
        .contenedor {
            width: min(1320px, calc(100% - 40px));
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
        .btn-volver {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 9px 13px;
            border: 1px solid #dce6f1;
            border-radius: 9px;
            color: #315779;
            background: #f7faff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition:
                color 0.18s ease,
                border-color 0.18s ease,
                background 0.18s ease;
        }
        .btn-volver:hover {
            color: var(--primary-dark);
            border-color: #bfd2e5;
            background: #edf5ff;
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
        .alerta.error {
            border-color: #f1c3c8;
            color: #922b36;
            background: #fdecee;
        }
        .panel {
            margin-bottom: 24px;
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
        .panel-acciones {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-cuerpo {
            padding: 22px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 18px;
        }
        .campo-completo {
            grid-column: 1 / -1;
        }
        label {
            display: block;
            margin: 0 0 6px;
            color: #334e68;
            font-size: 11px;
            font-weight: 750;
        }
        input,
        textarea {
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
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(31, 95, 153, 0.13);
        }
        input[type="file"] {
            padding: 7px;
            background: #f9fbfe;
        }
        input[type="file"]::file-selector-button {
            margin-right: 10px;
            padding: 7px 10px;
            border: 0;
            border-radius: 7px;
            color: #315779;
            background: #e9f1f9;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        textarea {
            min-height: 88px;
            resize: vertical;
        }
        .ayuda {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 10px;
        }
        .form-acciones {
            display: flex;
            justify-content: flex-end;
            gap: 9px;
            padding-top: 3px;
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
                transform 0.18s ease,
                border-color 0.18s ease,
                background 0.18s ease;
        }
        .btn-crear,
        .btn-actualizar {
            color: #fff;
            border-color: var(--primary);
            background: var(--primary);
            box-shadow: 0 7px 16px rgba(31, 95, 153, 0.2);
        }
        .btn-crear:hover,
        .btn-actualizar:hover {
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
            padding: 10px 9px;
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
        th:nth-child(1) { width: 64px; }
        th:nth-child(2) { width: 19%; }
        th:nth-child(3) { width: 31%; }
        th:nth-child(4) { width: 94px; }
        th:nth-child(5) { width: 115px; }
        th:nth-child(6) { width: 112px; }
        .fila-catalogo:nth-of-type(4n + 3) {
            background: #f9fbfe;
        }
        .fila-catalogo:hover {
            background: #f1f6fc;
        }
        .imagen-catalogo {
            display: block;
            width: 44px;
            height: 44px;
            margin: auto;
            padding: 4px;
            border: 1px solid #dce5ef;
            border-radius: 10px;
            background: #f7faff;
            object-fit: contain;
        }
        .estado-catalogo {
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
        .estado-catalogo::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            content: "";
        }
        .estado-catalogo.activo {
            color: #15703b;
            background: #e5f6eb;
        }
        .estado-catalogo.inhabilitado {
            color: #755a14;
            background: #fff3cf;
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
        .fila-catalogo:nth-last-child(-n + 2) .acciones-desplegable {
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
        .fila-edicion .form-grid {
            align-items: end;
        }
        .fila-edicion .form-acciones {
            grid-column: 1 / -1;
        }
        .sin-registros {
            margin: 0;
            padding: 28px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }
        .modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(10, 31, 52, 0.58);
            backdrop-filter: blur(3px);
        }
        .modal.abierto {
            display: flex;
        }
        .modal-dialogo {
            width: min(680px, 100%);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 28px 70px rgba(8, 34, 58, 0.28);
        }
        .modal-encabezado {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 22px 16px;
            border-bottom: 1px solid var(--border);
        }
        .modal-encabezado h2,
        .modal-encabezado p {
            margin: 0;
        }
        .modal-encabezado h2 {
            color: var(--navy);
            font-size: 21px;
        }
        .modal-encabezado p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }
        .modal-cerrar {
            display: grid;
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            place-items: center;
            border: 1px solid #d4dfe9;
            border-radius: 9px;
            color: #486581;
            background: #fff;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }
        .modal-cerrar:hover {
            background: #f1f6fc;
        }
        .modal-cuerpo {
            padding: 22px;
        }
        body.modal-abierto {
            overflow: hidden;
        }
        @media (max-width: 850px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .panel-encabezado {
                align-items: flex-start;
                flex-direction: column;
            }
            .panel-acciones {
                width: 100%;
                justify-content: space-between;
            }
            .tabla-contenedor {
                overflow-x: auto;
            }
            table {
                min-width: 860px;
            }
        }
        @media (max-width: 620px) {
            .contenedor {
                width: min(100% - 24px, 1320px);
                padding-top: 12px;
            }
            .brand-subtitle {
                display: none;
            }
            .btn-volver {
                width: 100%;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .campo-completo,
            .fila-edicion .form-acciones {
                grid-column: auto;
            }
            .form-acciones {
                align-items: stretch;
                flex-direction: column;
            }
            .form-acciones .btn {
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
    <div class="contenedor">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">MS</div>
                <div>
                    <p class="brand-name">Mesa de Servicio</p>
                    <p class="brand-subtitle">Administración de catálogos</p>
                </div>
            </div>

            <a href="catalogos.php" class="btn-volver">← Volver a catálogos</a>
        </header>

        <?php if (isset($mensajes[$mensajeActual])): ?>
            <div class="alerta exito"><?php echo escapar($mensajes[$mensajeActual]); ?></div>
        <?php endif; ?>

        <?php if ($errorActual !== ''): ?>
            <div class="alerta error">❌ <?php echo escapar($errorActual); ?></div>
        <?php endif; ?>

        <section id="listado" class="panel">
            <div class="panel-encabezado">
                <div>
                    <h2>Catálogos registrados</h2>
                    <p>Consulte y administre el estado e información de cada catálogo.</p>
                </div>
                <div class="panel-acciones">
                    <span class="panel-etiqueta">Listado general</span>
                    <button type="button" class="btn btn-crear" onclick="abrirModalCrear()">
                        + Crear nuevo catálogo
                    </button>
                </div>
            </div>

            <?php if ($catalogos->num_rows > 0): ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Imagen</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cat = $catalogos->fetch_assoc()): ?>
                                <tr class="fila-catalogo">
                                    <td><?php echo (int) $cat['id_catalogo']; ?></td>
                                    <td><?php echo escapar($cat['nombre']); ?></td>
                                    <td><?php echo escapar($cat['descripcion']); ?></td>
                                    <td>
                                        <img
                                            class="imagen-catalogo"
                                            src="<?php echo escapar(seguridadUrlImagenCatalogo(
                                                (int) $cat['id_catalogo'],
                                                $cat['imagen']
                                            )); ?>"
                                            alt="Icono de <?php echo escapar($cat['nombre']); ?>"
                                        >
                                    </td>
                                    <td>
                                        <?php
                                        $estadoCatalogo = $cat['estado'] === 'inhabilitado'
                                            ? 'inhabilitado'
                                            : 'activo';
                                        ?>
                                        <span class="estado-catalogo <?php echo escapar($estadoCatalogo); ?>">
                                            <?php echo escapar(ucfirst($estadoCatalogo)); ?>
                                        </span>
                                    </td>
                                    <td class="celda-acciones">
                                        <details class="acciones-menu">
                                            <summary>Acciones</summary>
                                            <div class="acciones-desplegable">
                                            <button
                                                type="button"
                                                class="accion-item accion-editar"
                                                onclick="mostrarEdicion(<?php echo (int) $cat['id_catalogo']; ?>)"
                                            >Editar</button>

                                            <form method="POST">
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo escapar($_SESSION['csrf_token']); ?>"
                                                >
                                                <input type="hidden" name="accion" value="cambiar_estado">
                                                <input
                                                    type="hidden"
                                                    name="id_catalogo"
                                                    value="<?php echo (int) $cat['id_catalogo']; ?>"
                                                >
                                                <?php if ($cat['estado'] === 'activo'): ?>
                                                    <input type="hidden" name="nuevo_estado" value="inhabilitado">
                                                    <button type="submit" class="accion-item accion-estado">
                                                        Inhabilitar
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="nuevo_estado" value="activo">
                                                    <button type="submit" class="accion-item accion-habilitar">
                                                        Habilitar
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <form method="POST" onsubmit="return confirmarEliminacion();">
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo escapar($_SESSION['csrf_token']); ?>"
                                                >
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input
                                                    type="hidden"
                                                    name="id_catalogo"
                                                    value="<?php echo (int) $cat['id_catalogo']; ?>"
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
                                    id="edicion-<?php echo (int) $cat['id_catalogo']; ?>"
                                    class="fila-edicion"
                                >
                                    <td colspan="6">
                                        <form method="POST" enctype="multipart/form-data">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo escapar($_SESSION['csrf_token']); ?>"
                                            >
                                            <input type="hidden" name="accion" value="editar">
                                            <input
                                                type="hidden"
                                                name="id_catalogo"
                                                value="<?php echo (int) $cat['id_catalogo']; ?>"
                                            >
                                            <div class="form-grid">
                                                <div>
                                                    <label for="nombre-<?php echo (int) $cat['id_catalogo']; ?>">
                                                        Nombre
                                                    </label>
                                                    <input
                                                        id="nombre-<?php echo (int) $cat['id_catalogo']; ?>"
                                                        type="text"
                                                        name="nombre"
                                                        maxlength="150"
                                                        value="<?php echo escapar($cat['nombre']); ?>"
                                                        required
                                                    >
                                                </div>

                                                <div>
                                                    <label for="imagen-<?php echo (int) $cat['id_catalogo']; ?>">
                                                        Reemplazar imagen
                                                    </label>
                                                    <input
                                                        id="imagen-<?php echo (int) $cat['id_catalogo']; ?>"
                                                        type="file"
                                                        name="imagen"
                                                        accept=".jpg,.jpeg,.png,.gif,.webp"
                                                    >
                                                </div>

                                                <div class="campo-completo">
                                                    <label for="descripcion-<?php echo (int) $cat['id_catalogo']; ?>">
                                                        Descripción
                                                    </label>
                                                    <textarea
                                                        id="descripcion-<?php echo (int) $cat['id_catalogo']; ?>"
                                                        name="descripcion"
                                                    ><?php echo escapar($cat['descripcion']); ?></textarea>
                                                </div>

                                                <div class="campo-completo form-acciones">
                                                    <button type="submit" class="btn btn-actualizar">
                                                        Guardar cambios
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-cancelar"
                                                        onclick="mostrarEdicion(<?php echo (int) $cat['id_catalogo']; ?>)"
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
                <div class="sin-registros">No hay catálogos registrados.</div>
            <?php endif; ?>
        </section>
    </div>

    <div id="modal-crear-catalogo" class="modal" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-crear" aria-hidden="true">
        <div class="modal-dialogo">
            <div class="modal-encabezado">
                <div>
                    <h2 id="titulo-modal-crear">Crear nuevo catálogo</h2>
                    <p>Registre una nueva clase de servicio y el icono que la identificará.</p>
                </div>
                <button type="button" class="modal-cerrar" aria-label="Cerrar ventana" onclick="cerrarModalCrear()">×</button>
            </div>
            <div class="modal-cuerpo">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo escapar($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="accion" value="crear">
                    <div class="form-grid">
                        <div>
                            <label for="nuevo-nombre">Nombre</label>
                            <input id="nuevo-nombre" type="text" name="nombre" maxlength="120" required>
                        </div>
                        <div>
                            <label for="nueva-imagen">Imagen</label>
                            <input id="nueva-imagen" type="file" name="imagen" accept=".jpg,.jpeg,.png,.gif,.webp">
                            <span class="ayuda">JPG, PNG, GIF o WEBP. Máximo 2 MB.</span>
                        </div>
                        <div class="campo-completo">
                            <label for="nueva-descripcion">Descripción</label>
                            <textarea id="nueva-descripcion" name="descripcion" maxlength="500"></textarea>
                        </div>
                        <div class="campo-completo form-acciones">
                            <button type="button" class="btn btn-cancelar" onclick="cerrarModalCrear()">Cancelar</button>
                            <button type="submit" class="btn btn-crear">Guardar catálogo</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modalCrear = document.getElementById('modal-crear-catalogo');
        const campoNombreNuevo = document.getElementById('nuevo-nombre');

        function abrirModalCrear() {
            modalCrear.classList.add('abierto');
            modalCrear.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-abierto');
            window.setTimeout(function () {
                campoNombreNuevo.focus();
            }, 50);
        }

        function cerrarModalCrear() {
            modalCrear.classList.remove('abierto');
            modalCrear.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-abierto');
        }

        function mostrarEdicion(id) {
            const fila = document.getElementById('edicion-' + id);
            const estaVisible = fila.style.display === 'table-row';
            fila.style.display = estaVisible ? 'none' : 'table-row';

            document.querySelectorAll('.acciones-menu[open]').forEach(function (menu) {
                menu.removeAttribute('open');
            });
        }

        function confirmarEliminacion() {
            return window.confirm(
                '¿Está seguro de eliminar este catálogo? También se eliminarán permanentemente todos sus servicios. Esta acción no se puede deshacer.'
            );
        }

        window.addEventListener('click', function (event) {
            if (event.target === modalCrear) {
                cerrarModalCrear();
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

            if (modalCrear.classList.contains('abierto')) {
                cerrarModalCrear();
            }

            document.querySelectorAll('.acciones-menu[open]').forEach(function (menu) {
                menu.removeAttribute('open');
            });
        });
    </script>
    <script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
