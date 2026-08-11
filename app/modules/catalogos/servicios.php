<?php
declare(strict_types=1);

/*
 * Administración de servicios.
 * Los catálogos se muestran únicamente como selector de lectura.
 */
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

function redirigirServicio($idCatalogo, $mensaje)
{
    $url = 'servicios.php?msg=' . urlencode($mensaje);

    if ($idCatalogo) {
        $url = 'servicios.php?id_catalogo=' . (int) $idCatalogo
            . '&msg=' . urlencode($mensaje);
    }

    header('Location: ' . $url);
    exit;
}

function slaActivoDisponible($conn, $idSla)
{
    if (!$idSla) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT id_sla
         FROM sla
         WHERE id_sla = ?
           AND id_pais_operacion = ?
           AND estado = 'activo'"
    );
    $idPais = paisExigirContexto();
    $stmt->bind_param("ii", $idSla, $idPais);
    $stmt->execute();
    $stmt->store_result();
    $disponible = $stmt->num_rows > 0;
    $stmt->close();

    return $disponible;
}

function moduloOpcionesServicioDisponible($conn)
{
    try {
        $resultado = $conn->query(
            "SHOW TABLES LIKE 'configuraciones_servicio'"
        );

        if (!$resultado || $resultado->num_rows === 0) {
            return false;
        }

        $columnas = $conn->query(
            "SHOW COLUMNS FROM servicios LIKE 'id_prioridad'"
        );

        return $columnas && $columnas->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function clasificacionServicioDisponible($conn): bool
{
    try {
        $columnas = $conn->query(
            "SHOW COLUMNS FROM servicios LIKE 'tipo_solicitud'"
        );

        return $columnas !== false && $columnas->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function opcionServicioDisponible($conn, $idOpcion, $tipo)
{
    if (!$idOpcion) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT id_opcion
         FROM configuraciones_servicio
         WHERE id_opcion = ?
           AND tipo = ?
           AND id_pais_operacion = ?
           AND estado_registro = 'activo'
         LIMIT 1"
    );
    $idPais = paisExigirContexto();
    $stmt->bind_param("isi", $idOpcion, $tipo, $idPais);
    $stmt->execute();
    $stmt->store_result();
    $disponible = $stmt->num_rows > 0;
    $stmt->close();

    return $disponible;
}

function relacionOpcionesServicioValida(
    $conn,
    $idHijo,
    $tipoHijo,
    $idPadre
) {
    if (!$idHijo || !$idPadre) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT id_opcion
         FROM configuraciones_servicio
         WHERE id_opcion = ?
           AND tipo = ?
           AND id_padre = ?
           AND id_pais_operacion = ?
           AND estado_registro = 'activo'
         LIMIT 1"
    );
    $idPais = paisExigirContexto();
    $stmt->bind_param("isii", $idHijo, $tipoHijo, $idPadre, $idPais);
    $stmt->execute();
    $stmt->store_result();
    $valida = $stmt->num_rows > 0;
    $stmt->close();

    return $valida;
}

function leerOpcionesServicio(
    $conn,
    $camposConfiguracion,
    $camposObligatorios
) {
    $valores = [];

    foreach ($camposConfiguracion as $campo => $tipo) {
        $valor = filter_input(INPUT_POST, $campo, FILTER_VALIDATE_INT);
        $idOpcion = $valor !== false && $valor !== null
            ? (int) $valor
            : 0;

        if (
            in_array($campo, $camposObligatorios, true)
            && $idOpcion <= 0
        ) {
            return null;
        }

        if (
            $idOpcion > 0
            && !opcionServicioDisponible($conn, $idOpcion, $tipo)
        ) {
            return null;
        }

        $valores[$campo] = $idOpcion;
    }

    $relaciones = [
        ['id_departamento', 'departamento', 'id_pais'],
        ['id_ciudad', 'ciudad', 'id_departamento'],
    ];

    foreach ($relaciones as [$campoHijo, $tipoHijo, $campoPadre]) {
        $idHijo = $valores[$campoHijo] ?? 0;
        $idPadre = $valores[$campoPadre] ?? 0;

        if (
            $idHijo > 0
            && !relacionOpcionesServicioValida(
                $conn,
                $idHijo,
                $tipoHijo,
                $idPadre
            )
        ) {
            return null;
        }
    }

    return $valores;
}

$camposConfiguracionServicio = [
    'id_pais' => 'pais',
    'id_departamento' => 'departamento',
    'id_ciudad' => 'ciudad',
    'id_prioridad' => 'prioridad',
    'id_urgencia' => 'urgencia',
    'id_nivel' => 'nivel',
    'id_impacto' => 'impacto',
    'id_estado' => 'estado',
];

$camposConfiguracionObligatorios = [
    'id_prioridad',
    'id_urgencia',
    'id_nivel',
    'id_impacto',
    'id_estado',
];

$moduloOpcionesInstalado = moduloOpcionesServicioDisponible($conn);

if (!clasificacionServicioDisponible($conn)) {
    http_response_code(503);
    exit('Ejecute la migración 007_clasificacion_y_atencion_servicio.sql antes de administrar servicios.');
}

// Crear, editar, eliminar e inhabilitar/habilitar servicios.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    seguridadExigirOrigenPost();
    $token = $_POST['csrf_token'] ?? '';
    $idCatalogo = filter_input(INPUT_POST, 'id_catalogo', FILTER_VALIDATE_INT);

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        redirigirServicio($idCatalogo, 'solicitud_invalida');
    }

    if (!$idCatalogo) {
        redirigirServicio(null, 'datos_incompletos');
    }

    try {
        if (isset($_POST['nuevo_servicio'])) {
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $tipoSolicitud = (string) ($_POST['tipo_solicitud'] ?? '');
            $idSla = filter_input(INPUT_POST, 'id_sla', FILTER_VALIDATE_INT);

            if (
                $nombre === ''
                || $descripcion === ''
                || !$idSla
                || !in_array($tipoSolicitud, ['requerimiento', 'incidente'], true)
            ) {
                redirigirServicio($idCatalogo, 'datos_incompletos');
            }

            if (!$moduloOpcionesInstalado) {
                redirigirServicio($idCatalogo, 'configuracion_pendiente');
            }

            $opcionesServicio = leerOpcionesServicio(
                $conn,
                $camposConfiguracionServicio,
                $camposConfiguracionObligatorios
            );

            if ($opcionesServicio === null) {
                redirigirServicio(
                    $idCatalogo,
                    'configuracion_no_disponible'
                );
            }

            if (!slaActivoDisponible($conn, $idSla)) {
                redirigirServicio($idCatalogo, 'sla_no_disponible');
            }

            $stmtCatalogo = $conn->prepare(
                "SELECT id_catalogo
                 FROM catalogos
                 WHERE id_catalogo = ?
                   AND id_pais_operacion = ?
                   AND estado = 'activo'"
            );
            $stmtCatalogo->bind_param("ii", $idCatalogo, $idPaisOperacion);
            $stmtCatalogo->execute();
            $stmtCatalogo->store_result();

            if ($stmtCatalogo->num_rows === 0) {
                $stmtCatalogo->close();
                redirigirServicio($idCatalogo, 'catalogo_no_disponible');
            }
            $stmtCatalogo->close();

            $stmt = $conn->prepare(
                "INSERT INTO servicios
                    (
                        id_pais_operacion,
                        id_catalogo,
                        id_sla,
                        nombre,
                        descripcion,
                        tipo_solicitud,
                        estado,
                        id_pais,
                        id_departamento,
                        id_ciudad,
                        id_prioridad,
                        id_urgencia,
                        id_nivel,
                        id_impacto,
                        id_estado
                    )
                 VALUES (
                    ?, ?, ?, ?, ?, ?, 'activo',
                    NULLIF(?, 0),
                    NULLIF(?, 0),
                    NULLIF(?, 0),
                    ?, ?, ?, ?, ?
                 )"
            );
            $stmt->bind_param(
                "iiisssiiiiiiii",
                $idPaisOperacion,
                $idCatalogo,
                $idSla,
                $nombre,
                $descripcion,
                $tipoSolicitud,
                $opcionesServicio['id_pais'],
                $opcionesServicio['id_departamento'],
                $opcionesServicio['id_ciudad'],
                $opcionesServicio['id_prioridad'],
                $opcionesServicio['id_urgencia'],
                $opcionesServicio['id_nivel'],
                $opcionesServicio['id_impacto'],
                $opcionesServicio['id_estado']
            );

            if (!$stmt->execute()) {
                throw new RuntimeException('No fue posible crear el servicio.');
            }

            $stmt->close();
            redirigirServicio($idCatalogo, 'servicio_creado');
        }

        if (isset($_POST['editar_servicio'])) {
            $idServicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $tipoSolicitud = (string) ($_POST['tipo_solicitud'] ?? '');
            $idSla = filter_input(INPUT_POST, 'id_sla', FILTER_VALIDATE_INT);

            if (
                !$idServicio
                || $nombre === ''
                || $descripcion === ''
                || !$idSla
                || !in_array($tipoSolicitud, ['requerimiento', 'incidente'], true)
            ) {
                redirigirServicio($idCatalogo, 'datos_incompletos');
            }

            if (!$moduloOpcionesInstalado) {
                redirigirServicio($idCatalogo, 'configuracion_pendiente');
            }

            $opcionesServicio = leerOpcionesServicio(
                $conn,
                $camposConfiguracionServicio,
                $camposConfiguracionObligatorios
            );

            if ($opcionesServicio === null) {
                redirigirServicio(
                    $idCatalogo,
                    'configuracion_no_disponible'
                );
            }

            if (!slaActivoDisponible($conn, $idSla)) {
                redirigirServicio($idCatalogo, 'sla_no_disponible');
            }

            $stmt = $conn->prepare(
                "UPDATE servicios
                 SET
                    nombre = ?,
                    descripcion = ?,
                    tipo_solicitud = ?,
                    id_sla = ?,
                    id_pais = NULLIF(?, 0),
                    id_departamento = NULLIF(?, 0),
                    id_ciudad = NULLIF(?, 0),
                    id_prioridad = ?,
                    id_urgencia = ?,
                    id_nivel = ?,
                    id_impacto = ?,
                    id_estado = ?
                 WHERE id_servicio = ?
                   AND id_catalogo = ?
                   AND id_pais_operacion = ?"
            );
            $stmt->bind_param(
                "sssiiiiiiiiiiii",
                $nombre,
                $descripcion,
                $tipoSolicitud,
                $idSla,
                $opcionesServicio['id_pais'],
                $opcionesServicio['id_departamento'],
                $opcionesServicio['id_ciudad'],
                $opcionesServicio['id_prioridad'],
                $opcionesServicio['id_urgencia'],
                $opcionesServicio['id_nivel'],
                $opcionesServicio['id_impacto'],
                $opcionesServicio['id_estado'],
                $idServicio,
                $idCatalogo,
                $idPaisOperacion
            );

            if (!$stmt->execute()) {
                throw new RuntimeException('No fue posible editar el servicio.');
            }

            $stmt->close();
            redirigirServicio($idCatalogo, 'servicio_actualizado');
        }

        if (isset($_POST['cambiar_estado_servicio'])) {
            $idServicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);
            $nuevoEstado = $_POST['nuevo_estado'] ?? '';

            if (
                !$idServicio ||
                !in_array($nuevoEstado, ['activo', 'inhabilitado'], true)
            ) {
                redirigirServicio($idCatalogo, 'solicitud_invalida');
            }

            $stmt = $conn->prepare(
                "UPDATE servicios
                 SET estado = ?
                 WHERE id_servicio = ?
                   AND id_catalogo = ?
                   AND id_pais_operacion = ?"
            );
            $stmt->bind_param("siii", $nuevoEstado, $idServicio, $idCatalogo, $idPaisOperacion);

            if (!$stmt->execute()) {
                throw new RuntimeException('No fue posible cambiar el estado del servicio.');
            }

            $stmt->close();
            redirigirServicio(
                $idCatalogo,
                $nuevoEstado === 'activo'
                    ? 'servicio_habilitado'
                    : 'servicio_inhabilitado'
            );
        }

        if (isset($_POST['eliminar_servicio'])) {
            $idServicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);

            if (!$idServicio) {
                redirigirServicio($idCatalogo, 'solicitud_invalida');
            }

            $stmt = $conn->prepare(
                "DELETE FROM servicios
                 WHERE id_servicio = ?
                   AND id_catalogo = ?
                   AND id_pais_operacion = ?"
            );
            $stmt->bind_param("iii", $idServicio, $idCatalogo, $idPaisOperacion);

            if (!$stmt->execute()) {
                throw new RuntimeException('No fue posible eliminar el servicio.');
            }

            if ($stmt->affected_rows === 0) {
                $stmt->close();
                redirigirServicio($idCatalogo, 'servicio_no_encontrado');
            }

            $stmt->close();
            redirigirServicio($idCatalogo, 'servicio_eliminado');
        }
    } catch (Throwable $e) {
        error_log('Error en servicios.php: ' . $e->getMessage());
        $mensaje = isset($_POST['eliminar_servicio'])
            ? 'error_eliminar_servicio'
            : 'error_servicio';
        redirigirServicio($idCatalogo, $mensaje);
    }
}

// En esta pantalla solo se muestran los catálogos activos.
$catalogos = $conn->query(
    "SELECT id_catalogo, nombre, descripcion, imagen, orden
     FROM catalogos
     WHERE estado = 'activo'
       AND id_pais_operacion = {$idPaisOperacion}
     ORDER BY orden ASC, nombre ASC"
);

$slasTodos = [];
$slasActivos = [];
$resultadoSlas = $conn->query(
    "SELECT id_sla, nombre, tiempo_respuesta, unidad, estado
     FROM sla
     WHERE id_pais_operacion = {$idPaisOperacion}
     ORDER BY estado ASC, tiempo_respuesta ASC, nombre ASC"
);

while ($sla = $resultadoSlas->fetch_assoc()) {
    $sla['id_sla'] = (int) $sla['id_sla'];
    $sla['tiempo_respuesta'] = (int) $sla['tiempo_respuesta'];
    $slasTodos[] = $sla;

    if ($sla['estado'] === 'activo') {
        $slasActivos[] = $sla;
    }
}

$opcionesConfiguracionActivas = array_fill_keys(
    array_values($camposConfiguracionServicio),
    []
);
$opcionesConfiguracionTodas = $opcionesConfiguracionActivas;

if ($moduloOpcionesInstalado) {
    $resultadoOpciones = $conn->query(
        "SELECT
            id_opcion,
            tipo,
            id_padre,
            nombre,
            descripcion,
            color,
            estado_registro,
            orden
         FROM configuraciones_servicio
         WHERE id_pais_operacion = {$idPaisOperacion}
         ORDER BY
            tipo,
            FIELD(estado_registro, 'activo', 'inhabilitado'),
            orden,
            nombre"
    );

    while ($opcion = $resultadoOpciones->fetch_assoc()) {
        $tipoOpcion = $opcion['tipo'];

        if (!array_key_exists($tipoOpcion, $opcionesConfiguracionTodas)) {
            continue;
        }

        $opcion['id_opcion'] = (int) $opcion['id_opcion'];
        $opcion['id_padre'] = (int) ($opcion['id_padre'] ?? 0);
        $opcionesConfiguracionTodas[$tipoOpcion][] = $opcion;

        if ($opcion['estado_registro'] === 'activo') {
            $opcionesConfiguracionActivas[$tipoOpcion][] = $opcion;
        }
    }
}

$configuracionesObligatoriasDisponibles = $moduloOpcionesInstalado;

foreach (
    ['prioridad', 'urgencia', 'nivel', 'impacto', 'estado']
    as $tipoObligatorio
) {
    if (empty($opcionesConfiguracionActivas[$tipoObligatorio])) {
        $configuracionesObligatoriasDisponibles = false;
        break;
    }
}

$nombresOpcionesServicio = [];

foreach (['prioridad', 'urgencia'] as $tipoResumen) {
    foreach ($opcionesConfiguracionTodas[$tipoResumen] ?? [] as $opcionResumen) {
        $nombresOpcionesServicio[(int) $opcionResumen['id_opcion']] = [
            'nombre' => (string) $opcionResumen['nombre'],
            'color' => (string) ($opcionResumen['color'] ?? '#64748b'),
        ];
    }
}

$etiquetasConfiguracionServicio = [
    'id_pais' => 'País',
    'id_departamento' => 'Departamento',
    'id_ciudad' => 'Ciudad',
    'id_prioridad' => 'Prioridad',
    'id_urgencia' => 'Urgencia',
    'id_nivel' => 'Nivel',
    'id_impacto' => 'Impacto',
    'id_estado' => 'Estado',
];

$padresConfiguracionServicio = [
    'departamento' => 'pais',
    'ciudad' => 'departamento',
];

$unidadesSla = [
    'minutos' => 'minuto(s)',
    'horas' => 'hora(s)',
    'dias' => 'día(s)',
];

$servicios = null;
$idCatalogoSeleccionado = filter_input(INPUT_GET, 'id_catalogo', FILTER_VALIDATE_INT);
$catalogoSeleccionado = null;
$camposSeleccionConfiguracion = $moduloOpcionesInstalado
    ? "
        s.id_pais,
        s.id_departamento,
        s.id_ciudad,
        s.id_prioridad,
        s.id_urgencia,
        s.id_nivel,
        s.id_impacto,
        s.id_estado,"
    : "
        NULL AS id_pais,
        NULL AS id_departamento,
        NULL AS id_ciudad,
        NULL AS id_prioridad,
        NULL AS id_urgencia,
        NULL AS id_nivel,
        NULL AS id_impacto,
        NULL AS id_estado,";

if ($idCatalogoSeleccionado) {
    $stmt = $conn->prepare(
        "SELECT id_catalogo, nombre
         FROM catalogos
         WHERE id_catalogo = ?
           AND id_pais_operacion = ?
           AND estado = 'activo'"
    );
    $stmt->bind_param("ii", $idCatalogoSeleccionado, $idPaisOperacion);
    $stmt->execute();
    $resultadoCatalogo = $stmt->get_result();
    $catalogoSeleccionado = $resultadoCatalogo->fetch_assoc();
    $stmt->close();

    if ($catalogoSeleccionado) {
        $stmtServicios = $conn->prepare(
            "SELECT
                s.id_servicio,
                s.nombre,
                s.descripcion,
                s.tipo_solicitud,
                s.id_sla,
                {$camposSeleccionConfiguracion}
                s.estado,
                sl.nombre AS sla_nombre,
                sl.tiempo_respuesta AS sla_tiempo,
                sl.unidad AS sla_unidad,
                sl.estado AS sla_estado
             FROM servicios AS s
             LEFT JOIN sla AS sl ON sl.id_sla = s.id_sla
             WHERE s.id_catalogo = ?
               AND s.id_pais_operacion = ?
             ORDER BY s.nombre ASC"
        );
        $stmtServicios->bind_param("ii", $idCatalogoSeleccionado, $idPaisOperacion);
        $stmtServicios->execute();
        $servicios = $stmtServicios->get_result();
    }
}

$mensajes = [
    'servicio_creado' => ['exito', '✅ Servicio creado correctamente.'],
    'servicio_actualizado' => ['exito', '✏️ Servicio actualizado correctamente.'],
    'servicio_eliminado' => ['exito', '🗑️ Servicio eliminado correctamente.'],
    'servicio_inhabilitado' => ['aviso', '⚠️ Servicio inhabilitado correctamente.'],
    'servicio_habilitado' => ['exito', '✅ Servicio habilitado correctamente.'],
    'servicio_no_encontrado' => ['error', '❌ El servicio seleccionado no existe.'],
    'error_servicio' => ['error', '❌ No fue posible procesar la operación del servicio.'],
    'error_eliminar_servicio' => [
        'error',
        '❌ No fue posible eliminar el servicio. Puede estar asociado a solicitudes existentes.'
    ],
    'solicitud_invalida' => ['error', '❌ La solicitud no es válida. Inténtelo nuevamente.'],
    'datos_incompletos' => ['error', '❌ Complete los campos obligatorios.'],
    'catalogo_no_disponible' => ['error', '❌ El catálogo seleccionado no está disponible.'],
    'sla_no_disponible' => [
        'error',
        '❌ Seleccione un SLA activo y disponible para el servicio.'
    ],
    'configuracion_pendiente' => [
        'aviso',
        '⚠️ Instale los módulos de opciones antes de administrar servicios.'
    ],
    'configuracion_no_disponible' => [
        'error',
        '❌ Seleccione opciones activas y complete los parámetros obligatorios del servicio.'
    ],
];

$mensajeActual = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="catalogos-version" content="sla-1.3-interfaz">
    <title>Servicios | Mesa de Servicio</title>
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
        textarea,
        select {
            font: inherit;
        }
        .page-shell {
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
        .barra-acciones {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 7px;
        }
        .btn-volver,
        .open-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 9px 12px;
            border: 1px solid #dce6f1;
            border-radius: 9px;
            color: #315779;
            background: #f7faff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition:
                color 0.18s ease,
                border-color 0.18s ease,
                background 0.18s ease,
                transform 0.18s ease;
        }
        .btn-volver:hover,
        .open-btn:hover {
            color: var(--primary-dark);
            border-color: #bfd2e5;
            background: #edf5ff;
        }
        .hero {
            position: relative;
            min-height: 58px;
            padding: 9px 14px;
            overflow: hidden;
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(120deg, #132f4c 0%, #1f5f99 62%, #2b76aa 100%);
            box-shadow: 0 8px 20px rgba(15, 45, 75, 0.10);
        }
        .hero::after {
            position: absolute;
            top: -45px;
            right: -18px;
            width: 112px;
            height: 112px;
            border: 16px solid rgba(255, 255, 255, 0.07);
            border-radius: 50%;
            content: "";
        }
        .hero-copy {
            position: relative;
            z-index: 1;
            max-width: 720px;
        }
        .eyebrow {
            display: none;
        }
        .hero h1,
        .hero p {
            margin: 0;
        }
        .hero h1 {
            font-size: 17px;
            line-height: 1.18;
            letter-spacing: -0.2px;
        }
        .hero p {
            max-width: 650px;
            margin-top: 2px;
            color: rgba(255, 255, 255, 0.83);
            overflow: hidden;
            font-size: 11px;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .content-section {
            margin-top: 14px;
        }
        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
        }
        .section-heading h2,
        .section-heading p {
            margin: 0;
        }
        .section-heading h2 {
            color: var(--navy);
            font-size: 21px;
            letter-spacing: -0.3px;
        }
        .section-heading p {
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }
        .seleccion-chip {
            flex: 0 0 auto;
            padding: 7px 11px;
            border: 1px solid #dce7f3;
            border-radius: 9px;
            color: #486581;
            background: rgba(255, 255, 255, 0.84);
            font-size: 11px;
            font-weight: 700;
        }
        .catalogos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 14px;
            margin: 0;
        }
        .catalogo {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 0;
            min-height: 128px;
            padding: 18px 14px;
            border: 1px solid var(--border);
            border-radius: 16px;
            color: var(--text);
            background: var(--surface);
            box-shadow: 0 8px 22px rgba(31, 62, 93, 0.065);
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            user-select: none;
            transition:
                transform 0.18s ease,
                opacity 0.15s ease,
                border-color 0.18s ease,
                box-shadow 0.18s ease,
                color 0.18s ease;
        }
        .catalogo:hover {
            transform: translateY(-3px);
            border-color: #a9c2db;
            box-shadow: 0 13px 28px rgba(31, 62, 93, 0.12);
        }
        .catalogo.seleccionado {
            color: #fff;
            border-color: var(--primary);
            background: linear-gradient(145deg, var(--primary), var(--primary-dark));
            box-shadow: 0 13px 28px rgba(22, 63, 101, 0.24);
        }
        .catalogo img {
            width: 48px;
            height: 48px;
            margin-bottom: 11px;
            padding: 5px;
            border: 1px solid #dce5ef;
            border-radius: 11px;
            background: #f7faff;
            object-fit: contain;
        }
        .catalogo span {
            font-size: 13px;
            font-weight: 750;
        }
        .ayuda-orden {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            color: var(--muted);
            font-size: 11px;
        }
        .estado-orden {
            display: inline-block;
            min-height: 18px;
            font-weight: 750;
        }
        .estado-orden.exito { color: #15703b; }
        .estado-orden.error { color: #922b36; }
        .servicios-panel {
            margin-top: 29px;
        }
        .servicios-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
        }
        .servicios-toolbar h2,
        .servicios-toolbar p {
            margin: 0;
        }
        .servicios-toolbar h2 {
            color: var(--navy);
            font-size: 21px;
            letter-spacing: -0.3px;
        }
        .servicios-toolbar p {
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }
        .btn-nuevo {
            flex: 0 0 auto;
            color: #fff;
            border-color: var(--primary);
            background: var(--primary);
            box-shadow: 0 7px 16px rgba(31, 95, 153, 0.22);
        }
        .btn-nuevo:hover {
            color: #fff;
            border-color: var(--primary-dark);
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .tabla-contenedor {
            overflow: visible;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: 0 10px 28px rgba(31, 62, 93, 0.07);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: var(--surface);
            font-size: 12px;
        }
        th, td {
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
        tbody .fila-servicio:nth-of-type(4n + 3) {
            background: #f9fbfe;
        }
        tbody .fila-servicio:hover {
            background: #f1f6fc;
        }
        th:nth-child(1) { width: 55px; }
        th:nth-child(2) { width: 15%; }
        th:nth-child(3) { width: 21%; }
        th:nth-child(4) { width: 116px; }
        th:nth-child(5) { width: 19%; }
        th:nth-child(6) { width: 165px; }
        th:nth-child(7) { width: 92px; }
        th:nth-child(8) { width: 105px; }
        .sla-info {
            display: inline-flex;
            flex-direction: column;
            gap: 2px;
            padding: 6px 9px;
            border-radius: 9px;
            color: #315779;
            background: #edf5fc;
        }
        .sla-info strong {
            font-size: 11px;
        }
        .sla-info small {
            color: var(--muted);
            font-size: 10px;
        }
        .service-classification,
        .attention-tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 25px;
            padding: 5px 8px;
            border: 1px solid #d8e4ef;
            border-radius: 999px;
            color: #315779;
            background: #f5f9fd;
            font-size: 9px;
            font-weight: 800;
        }
        .service-classification.incidente {
            color: #a73535;
            border-color: #efcaca;
            background: #fff3f3;
        }
        .attention-summary {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px;
        }
        .estado-servicio {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 80px;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 750;
            text-transform: capitalize;
        }
        .estado-servicio::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            content: "";
        }
        .estado-servicio.activo {
            color: #15703b;
            background: #e5f6eb;
        }
        .estado-servicio.inhabilitado {
            color: #755a14;
            background: #fff3cf;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 35px;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-edit { background: var(--primary); }
        .btn-cancel {
            color: #486581;
            border: 1px solid #d4dfe9;
            background: #fff;
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
            width: 165px;
            padding: 6px;
            border: 1px solid #dce5ef;
            border-radius: 11px;
            background: #fff;
            box-shadow: 0 16px 34px rgba(15, 45, 75, 0.2);
            text-align: left;
        }
        .fila-servicio:nth-last-child(-n + 2) .acciones-desplegable {
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
            text-decoration: none;
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
            grid-template-columns: 1fr 2fr 1fr;
            gap: 15px;
            align-items: end;
        }
        .form-edicion .acciones-edicion {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding-top: 6px;
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
        .modal-content {
            width: min(820px, 100%);
            max-height: calc(100vh - 48px);
            margin: 0 auto;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 28px 65px rgba(2, 20, 42, 0.32);
        }
        .modal-header {
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
        .modal-header h3,
        .modal-header p {
            margin: 0;
        }
        .modal-header h3 {
            color: var(--navy);
            font-size: 20px;
        }
        .modal-header p {
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }
        .modal-body {
            padding: 21px 22px 24px;
        }
        .close {
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
        .close:hover {
            color: var(--danger);
            background: #fff0f2;
        }
        label {
            display: block;
            margin: 0 0 6px;
            color: #334e68;
            font-size: 11px;
            font-weight: 750;
        }
        .modal-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }
        .form-span-full,
        .config-section,
        .config-helper {
            grid-column: 1 / -1;
        }
        .config-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 5px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .config-section strong {
            color: var(--navy);
            font-size: 13px;
        }
        .config-section a {
            color: var(--primary);
            font-size: 11px;
            font-weight: 750;
            text-decoration: none;
        }
        .config-section a:hover {
            text-decoration: underline;
        }
        .config-helper {
            margin: -7px 0 0;
            color: var(--muted);
            font-size: 11px;
        }
        .config-helper.required {
            color: #775a14;
        }
        .config-service-form select:disabled {
            color: #8aa0b5;
            background: #f2f5f8;
            cursor: not-allowed;
        }
        input, textarea, select {
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
        textarea:focus,
        select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(31, 95, 153, 0.13);
        }
        textarea { min-height: 90px; resize: vertical; }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 9px;
            margin-top: 19px;
            padding-top: 17px;
            border-top: 1px solid var(--border);
        }
        .btn-guardar {
            min-height: 39px;
            padding: 9px 15px;
            border: 1px solid var(--primary);
            border-radius: 9px;
            color: #fff;
            background: var(--primary);
            box-shadow: 0 7px 16px rgba(31, 95, 153, 0.22);
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
        }
        .alerta {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 47px;
            padding: 11px 15px;
            margin: 0 0 18px;
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
        td .alerta {
            display: inline-flex;
            min-height: 0;
            margin: 0;
            padding: 5px 8px;
            font-size: 10px;
        }
        .sin-registros {
            margin: 0;
            padding: 22px;
            border: 1px dashed #cbd8e5;
            border-radius: 13px;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.72);
            font-size: 13px;
            text-align: center;
        }
        @media (max-width: 850px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .barra-acciones {
                justify-content: flex-start;
                width: 100%;
            }
            .hero {
                min-height: 58px;
                padding: 9px 14px;
            }
            .catalogos {
                grid-template-columns: repeat(3, minmax(145px, 1fr));
            }
            .servicios-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .tabla-contenedor {
                overflow-x: auto;
            }
            table {
                min-width: 1120px;
            }
            .form-edicion { grid-template-columns: 1fr; }
            .form-edicion .acciones-edicion { grid-column: auto; }
        }
        @media (max-width: 560px) {
            .page-shell {
                width: min(100% - 24px, 1320px);
                padding-top: 12px;
            }
            .brand-subtitle {
                display: none;
            }
            .barra-acciones a {
                flex: 1 1 130px;
            }
            .catalogos {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .modal-form-grid {
                grid-template-columns: 1fr;
            }
            .form-span-full,
            .config-section,
            .config-helper {
                grid-column: auto;
            }
            .catalogo {
                min-height: 116px;
            }
            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }
            .seleccion-chip {
                display: none;
            }
            .modal {
                padding: 12px;
            }
            .modal-content {
                max-height: calc(100vh - 24px);
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
    <main class="page-shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">MS</div>
                <div>
                    <p class="brand-name">Mesa de Servicio</p>
                    <p class="brand-subtitle">Administración de servicios</p>
                </div>
            </div>

            <nav class="barra-acciones" aria-label="Acciones de administración">
                <a href="panelAdmin.php" class="btn-volver">← Volver al panel</a>
                <a href="catalogos.php" class="open-btn">Ver catálogos</a>
                <a href="sla.php" class="open-btn">Administrar SLA</a>
            </nav>
        </header>

        <?php if (isset($mensajes[$mensajeActual])): ?>
            <div class="alerta <?php echo escapar($mensajes[$mensajeActual][0]); ?>">
                <?php echo escapar($mensajes[$mensajeActual][1]); ?>
            </div>
        <?php endif; ?>

        <section class="hero">
            <div class="hero-copy">
                <span class="eyebrow">Administración</span>
                <h1>Servicios</h1>
                <p>
                    Seleccione un catálogo y gestione sus servicios, parámetros,
                    estados y tiempos de respuesta.
                </p>
            </div>
        </section>

        <section class="content-section" aria-labelledby="titulo-catalogos">
            <div class="section-heading">
                <div>
                    <h2 id="titulo-catalogos">Seleccione un catálogo</h2>
                    <p class="ayuda-orden">
                        Los catálogos funcionan únicamente como selector de servicios.
                    </p>
                </div>
                <span class="seleccion-chip">Selector de catálogo</span>
            </div>

            <div
                id="listaCatalogos"
                class="catalogos"
            >
        <?php while ($cat = $catalogos->fetch_assoc()): ?>
            <a
                href="servicios.php?id_catalogo=<?php echo (int) $cat['id_catalogo']; ?>"
                class="catalogo <?php echo $idCatalogoSeleccionado === (int) $cat['id_catalogo'] ? 'seleccionado' : ''; ?>"
                title="<?php echo escapar($cat['descripcion']); ?>"
            >
                <img
                    src="<?php echo escapar(seguridadUrlImagenCatalogo(
                        (int) $cat['id_catalogo'],
                        $cat['imagen']
                    )); ?>"
                    alt="Icono de <?php echo escapar($cat['nombre']); ?>"
                >
                <span><?php echo escapar($cat['nombre']); ?></span>
            </a>
        <?php endwhile; ?>
            </div>
        </section>

        <?php if ($idCatalogoSeleccionado && !$catalogoSeleccionado): ?>
            <div class="alerta error">El catálogo seleccionado no existe o está inhabilitado.</div>
        <?php endif; ?>

        <?php if ($catalogoSeleccionado): ?>
            <section class="servicios-panel" aria-labelledby="titulo-servicios">
                <div class="servicios-toolbar">
                    <div>
                        <h2 id="titulo-servicios">
                            Servicios de <?php echo escapar($catalogoSeleccionado['nombre']); ?>
                        </h2>
                        <p>Consulte y administre los servicios asociados a esta clase.</p>
                    </div>

                    <?php if ($slasActivos && $configuracionesObligatoriasDisponibles): ?>
                        <button
                            type="button"
                            class="open-btn btn-nuevo"
                            onclick="abrirModal('modalServicio')"
                        >
                            Añadir servicio
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (!$slasActivos): ?>
                    <div class="alerta aviso">
                        Debe crear o habilitar al menos un SLA antes de añadir servicios.
                        <a href="sla.php">Administrar SLA</a>
                    </div>
                <?php endif; ?>

                <?php if (!$moduloOpcionesInstalado): ?>
                    <div class="alerta aviso">
                        Ejecute <strong>crear_modulo_configuraciones.sql</strong>
                        antes de añadir o editar servicios.
                    </div>
                <?php elseif (!$configuracionesObligatoriasDisponibles): ?>
                    <div class="alerta aviso">
                        Cree o habilite al menos una prioridad, urgencia, nivel,
                        impacto y estado antes de añadir servicios.
                        <a href="panelAdmin.php">Ir a los módulos</a>
                    </div>
                <?php endif; ?>

                <div id="modalServicio" class="modal">
                    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-servicio">
                        <div class="modal-header">
                            <div>
                                <h3 id="titulo-modal-servicio">Añadir servicio</h3>
                                <p>Complete la información y asigne el tiempo de respuesta.</p>
                            </div>
                            <button
                                type="button"
                                class="close"
                                aria-label="Cerrar"
                                onclick="cerrarModal('modalServicio')"
                            >&times;</button>
                        </div>

                        <div class="modal-body">
                            <form
                                method="POST"
                                action="servicios.php"
                                class="config-service-form"
                            >
                                <input type="hidden" name="csrf_token" value="<?php echo escapar($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="nuevo_servicio" value="1">
                                <input
                                    type="hidden"
                                    name="id_catalogo"
                                    value="<?php echo (int) $idCatalogoSeleccionado; ?>"
                                >

                                <div class="modal-form-grid">
                                    <div>
                                        <label for="nombre-servicio">Nombre</label>
                                        <input id="nombre-servicio" type="text" name="nombre" maxlength="150" required>
                                    </div>

                                    <div class="form-span-full">
                                        <label for="descripcion-servicio">Descripción</label>
                                        <textarea id="descripcion-servicio" name="descripcion" maxlength="2000" required></textarea>
                                    </div>

                                    <div>
                                        <label for="tipo-solicitud-servicio">Clasificación del servicio</label>
                                        <select id="tipo-solicitud-servicio" name="tipo_solicitud" required>
                                            <option value="">Seleccione una clasificación</option>
                                            <option value="requerimiento">Requerimiento</option>
                                            <option value="incidente">Incidente</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="sla-servicio">SLA de respuesta</label>
                                        <select id="sla-servicio" name="id_sla" required>
                                            <option value="">Seleccione un SLA</option>
                                            <?php foreach ($slasActivos as $slaActivo): ?>
                                                <option value="<?php echo (int) $slaActivo['id_sla']; ?>">
                                                    <?php
                                                    echo escapar(
                                                        $slaActivo['nombre']
                                                        . ' - '
                                                        . $slaActivo['tiempo_respuesta']
                                                        . ' '
                                                        . ($unidadesSla[$slaActivo['unidad']]
                                                            ?? $slaActivo['unidad'])
                                                    );
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="config-section">
                                        <strong>Ubicación del servicio</strong>
                                        <a href="panelAdmin.php">Administrar módulos</a>
                                    </div>
                                    <p class="config-helper">
                                        Estos campos son opcionales. Los departamentos y las
                                        ciudades se filtran según la selección anterior.
                                    </p>

                                    <?php
                                    foreach (
                                        [
                                            'id_pais' => 'pais',
                                            'id_departamento' => 'departamento',
                                            'id_ciudad' => 'ciudad',
                                        ]
                                        as $campoConfiguracion => $tipoConfiguracion
                                    ):
                                        $tipoPadre = $padresConfiguracionServicio[$tipoConfiguracion]
                                            ?? '';
                                    ?>
                                        <div>
                                            <label for="<?php echo escapar($campoConfiguracion); ?>-servicio">
                                                <?php echo escapar($etiquetasConfiguracionServicio[$campoConfiguracion]); ?>
                                            </label>
                                            <select
                                                id="<?php echo escapar($campoConfiguracion); ?>-servicio"
                                                name="<?php echo escapar($campoConfiguracion); ?>"
                                                data-config-tipo="<?php echo escapar($tipoConfiguracion); ?>"
                                                data-config-padre="<?php echo escapar($tipoPadre); ?>"
                                            >
                                                <option value="">No aplica</option>
                                                <?php
                                                foreach (
                                                    $opcionesConfiguracionActivas[$tipoConfiguracion]
                                                    as $opcionConfiguracion
                                                ):
                                                ?>
                                                    <option
                                                        value="<?php echo (int) $opcionConfiguracion['id_opcion']; ?>"
                                                        data-padre="<?php echo (int) $opcionConfiguracion['id_padre']; ?>"
                                                    >
                                                        <?php echo escapar($opcionConfiguracion['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="config-section">
                                        <strong>Parámetros de atención</strong>
                                        <a href="panelAdmin.php">Administrar módulos</a>
                                    </div>
                                    <p class="config-helper required">
                                        Todos los parámetros de atención son obligatorios.
                                    </p>

                                    <?php
                                    foreach (
                                        [
                                            'id_prioridad' => 'prioridad',
                                            'id_urgencia' => 'urgencia',
                                            'id_nivel' => 'nivel',
                                            'id_impacto' => 'impacto',
                                            'id_estado' => 'estado',
                                        ]
                                        as $campoConfiguracion => $tipoConfiguracion
                                    ):
                                    ?>
                                        <div>
                                            <label for="<?php echo escapar($campoConfiguracion); ?>-servicio">
                                                <?php echo escapar($etiquetasConfiguracionServicio[$campoConfiguracion]); ?>
                                            </label>
                                            <select
                                                id="<?php echo escapar($campoConfiguracion); ?>-servicio"
                                                name="<?php echo escapar($campoConfiguracion); ?>"
                                                data-config-tipo="<?php echo escapar($tipoConfiguracion); ?>"
                                                required
                                            >
                                                <option value="">Seleccione una opción</option>
                                                <?php
                                                foreach (
                                                    $opcionesConfiguracionActivas[$tipoConfiguracion]
                                                    as $opcionConfiguracion
                                                ):
                                                ?>
                                                    <option value="<?php echo (int) $opcionConfiguracion['id_opcion']; ?>">
                                                        <?php echo escapar($opcionConfiguracion['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="modal-actions">
                                    <button type="submit" class="btn-guardar">Guardar servicio</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

        <?php if ($servicios && $servicios->num_rows > 0): ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Clasificación</th>
                            <th>SLA</th>
                            <th>Prioridad y urgencia</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($srv = $servicios->fetch_assoc()): ?>
                            <tr class="fila-servicio">
                                <td><?php echo (int) $srv['id_servicio']; ?></td>
                                <td><?php echo escapar($srv['nombre']); ?></td>
                                <td><?php echo escapar($srv['descripcion']); ?></td>
                                <td>
                                    <?php $tipoSolicitudServicio = (string) ($srv['tipo_solicitud'] ?? 'requerimiento'); ?>
                                    <span class="service-classification <?php echo escapar($tipoSolicitudServicio); ?>">
                                        <?php echo escapar(ucfirst($tipoSolicitudServicio)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($srv['sla_nombre']): ?>
                                        <span class="sla-info">
                                            <strong><?php echo escapar($srv['sla_nombre']); ?></strong>
                                            <small>
                                                <?php
                                                echo (int) $srv['sla_tiempo'];
                                                echo ' ';
                                                echo escapar(
                                                    $unidadesSla[$srv['sla_unidad']]
                                                    ?? $srv['sla_unidad']
                                                );
                                                ?>
                                            </small>
                                        </span>
                                    <?php else: ?>
                                        <span class="alerta error">Sin SLA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $prioridadServicio = $nombresOpcionesServicio[(int) ($srv['id_prioridad'] ?? 0)]['nombre'] ?? 'Sin prioridad';
                                    $urgenciaServicio = $nombresOpcionesServicio[(int) ($srv['id_urgencia'] ?? 0)]['nombre'] ?? 'Sin urgencia';
                                    ?>
                                    <span class="attention-summary">
                                        <span class="attention-tag">P: <?php echo escapar($prioridadServicio); ?></span>
                                        <span class="attention-tag">U: <?php echo escapar($urgenciaServicio); ?></span>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $estadoServicio = $srv['estado'] === 'inhabilitado'
                                        ? 'inhabilitado'
                                        : 'activo';
                                    ?>
                                    <span class="estado-servicio <?php echo escapar($estadoServicio); ?>">
                                        <?php echo escapar(ucfirst($estadoServicio)); ?>
                                    </span>
                                </td>
                                <td class="celda-acciones">
                                    <details class="acciones-menu">
                                        <summary>Acciones</summary>
                                        <div class="acciones-desplegable">
                                        <button
                                            type="button"
                                            class="accion-item accion-editar"
                                            onclick="mostrarEdicionServicio(<?php echo (int) $srv['id_servicio']; ?>)"
                                        >Editar</button>

                                        <form method="POST" action="servicios.php">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo escapar($_SESSION['csrf_token']); ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="id_catalogo"
                                                value="<?php echo (int) $idCatalogoSeleccionado; ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="id_servicio"
                                                value="<?php echo (int) $srv['id_servicio']; ?>"
                                            >
                                            <input type="hidden" name="cambiar_estado_servicio" value="1">

                                            <?php if ($srv['estado'] === 'activo'): ?>
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

                                        <form
                                            method="POST"
                                            action="servicios.php"
                                            onsubmit="return confirmarEliminarServicio();"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo escapar($_SESSION['csrf_token']); ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="id_catalogo"
                                                value="<?php echo (int) $idCatalogoSeleccionado; ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="id_servicio"
                                                value="<?php echo (int) $srv['id_servicio']; ?>"
                                            >
                                            <input type="hidden" name="eliminar_servicio" value="1">
                                            <button type="submit" class="accion-item accion-eliminar">
                                                Eliminar
                                            </button>
                                        </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>

                            <tr
                                id="edicion-servicio-<?php echo (int) $srv['id_servicio']; ?>"
                                class="fila-edicion"
                            >
                                <td colspan="8">
                                    <form
                                        method="POST"
                                        action="servicios.php"
                                        class="config-service-form"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo escapar($_SESSION['csrf_token']); ?>"
                                        >
                                        <input type="hidden" name="editar_servicio" value="1">
                                        <input
                                            type="hidden"
                                            name="id_catalogo"
                                            value="<?php echo (int) $idCatalogoSeleccionado; ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="id_servicio"
                                            value="<?php echo (int) $srv['id_servicio']; ?>"
                                        >

                                        <div class="form-edicion">
                                            <div>
                                                <label for="nombre-servicio-<?php echo (int) $srv['id_servicio']; ?>">
                                                    Nombre
                                                </label>
                                                <input
                                                    id="nombre-servicio-<?php echo (int) $srv['id_servicio']; ?>"
                                                    type="text"
                                                    name="nombre"
                                                    maxlength="150"
                                                    value="<?php echo escapar($srv['nombre']); ?>"
                                                    required
                                                >
                                            </div>

                                            <div>
                                                <label for="descripcion-servicio-<?php echo (int) $srv['id_servicio']; ?>">
                                                    Descripción
                                                </label>
                                                <textarea
                                                    id="descripcion-servicio-<?php echo (int) $srv['id_servicio']; ?>"
                                                    name="descripcion"
                                                    maxlength="2000"
                                                    required
                                                ><?php echo escapar($srv['descripcion']); ?></textarea>
                                            </div>

                                            <div>
                                                <label for="tipo-solicitud-<?php echo (int) $srv['id_servicio']; ?>">
                                                    Clasificación del servicio
                                                </label>
                                                <select
                                                    id="tipo-solicitud-<?php echo (int) $srv['id_servicio']; ?>"
                                                    name="tipo_solicitud"
                                                    required
                                                >
                                                    <option value="requerimiento" <?php echo ($srv['tipo_solicitud'] ?? 'requerimiento') === 'requerimiento' ? 'selected' : ''; ?>>Requerimiento</option>
                                                    <option value="incidente" <?php echo ($srv['tipo_solicitud'] ?? '') === 'incidente' ? 'selected' : ''; ?>>Incidente</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label for="sla-servicio-<?php echo (int) $srv['id_servicio']; ?>">
                                                    SLA de respuesta
                                                </label>
                                                <select
                                                    id="sla-servicio-<?php echo (int) $srv['id_servicio']; ?>"
                                                    name="id_sla"
                                                    required
                                                >
                                                    <?php if ($srv['sla_estado'] !== 'activo'): ?>
                                                        <option value="" selected>
                                                            <?php
                                                            echo escapar(
                                                                ($srv['sla_nombre'] ?: 'SLA no disponible')
                                                                . ' - seleccione otro SLA'
                                                            );
                                                            ?>
                                                        </option>
                                                    <?php else: ?>
                                                        <option value="">Seleccione un SLA</option>
                                                    <?php endif; ?>

                                                    <?php foreach ($slasActivos as $slaActivo): ?>
                                                        <option
                                                            value="<?php echo (int) $slaActivo['id_sla']; ?>"
                                                            <?php
                                                            echo (int) $srv['id_sla']
                                                                === (int) $slaActivo['id_sla']
                                                                    ? 'selected'
                                                                    : '';
                                                            ?>
                                                        >
                                                            <?php
                                                            echo escapar(
                                                                $slaActivo['nombre']
                                                                . ' - '
                                                                . $slaActivo['tiempo_respuesta']
                                                                . ' '
                                                                . ($unidadesSla[$slaActivo['unidad']]
                                                                    ?? $slaActivo['unidad'])
                                                            );
                                                            ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="config-section">
                                                <strong>Ubicación del servicio</strong>
                                                <a href="panelAdmin.php">Administrar módulos</a>
                                            </div>
                                            <p class="config-helper">
                                                La ubicación es opcional y respeta la jerarquía
                                                país, departamento y ciudad.
                                            </p>

                                            <?php
                                            foreach (
                                                [
                                                    'id_pais' => 'pais',
                                                    'id_departamento' => 'departamento',
                                                    'id_ciudad' => 'ciudad',
                                                ]
                                                as $campoConfiguracion => $tipoConfiguracion
                                            ):
                                                $tipoPadre = $padresConfiguracionServicio[$tipoConfiguracion]
                                                    ?? '';
                                                $idActualConfiguracion = (int) (
                                                    $srv[$campoConfiguracion] ?? 0
                                                );
                                            ?>
                                                <div>
                                                    <label for="<?php echo escapar($campoConfiguracion); ?>-<?php echo (int) $srv['id_servicio']; ?>">
                                                        <?php echo escapar($etiquetasConfiguracionServicio[$campoConfiguracion]); ?>
                                                    </label>
                                                    <select
                                                        id="<?php echo escapar($campoConfiguracion); ?>-<?php echo (int) $srv['id_servicio']; ?>"
                                                        name="<?php echo escapar($campoConfiguracion); ?>"
                                                        data-config-tipo="<?php echo escapar($tipoConfiguracion); ?>"
                                                        data-config-padre="<?php echo escapar($tipoPadre); ?>"
                                                    >
                                                        <option value="">No aplica</option>
                                                        <?php
                                                        foreach (
                                                            $opcionesConfiguracionTodas[$tipoConfiguracion]
                                                            as $opcionConfiguracion
                                                        ):
                                                            $opcionSeleccionada =
                                                                $idActualConfiguracion
                                                                === (int) $opcionConfiguracion['id_opcion'];

                                                            if (
                                                                $opcionConfiguracion['estado_registro']
                                                                !== 'activo'
                                                                && !$opcionSeleccionada
                                                            ) {
                                                                continue;
                                                            }
                                                        ?>
                                                            <option
                                                                value="<?php echo (int) $opcionConfiguracion['id_opcion']; ?>"
                                                                data-padre="<?php echo (int) $opcionConfiguracion['id_padre']; ?>"
                                                                <?php echo $opcionSeleccionada ? 'selected' : ''; ?>
                                                            >
                                                                <?php
                                                                echo escapar(
                                                                    $opcionConfiguracion['nombre']
                                                                    . (
                                                                        $opcionConfiguracion['estado_registro']
                                                                        === 'activo'
                                                                            ? ''
                                                                            : ' (inhabilitada)'
                                                                    )
                                                                );
                                                                ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php endforeach; ?>

                                            <div class="config-section">
                                                <strong>Parámetros de atención</strong>
                                                <a href="panelAdmin.php">Administrar módulos</a>
                                            </div>
                                            <p class="config-helper required">
                                                Seleccione opciones activas para guardar los cambios.
                                            </p>

                                            <?php
                                            foreach (
                                                [
                                                    'id_prioridad' => 'prioridad',
                                                    'id_urgencia' => 'urgencia',
                                                    'id_nivel' => 'nivel',
                                                    'id_impacto' => 'impacto',
                                                    'id_estado' => 'estado',
                                                ]
                                                as $campoConfiguracion => $tipoConfiguracion
                                            ):
                                                $idActualConfiguracion = (int) (
                                                    $srv[$campoConfiguracion] ?? 0
                                                );
                                            ?>
                                                <div>
                                                    <label for="<?php echo escapar($campoConfiguracion); ?>-<?php echo (int) $srv['id_servicio']; ?>">
                                                        <?php echo escapar($etiquetasConfiguracionServicio[$campoConfiguracion]); ?>
                                                    </label>
                                                    <select
                                                        id="<?php echo escapar($campoConfiguracion); ?>-<?php echo (int) $srv['id_servicio']; ?>"
                                                        name="<?php echo escapar($campoConfiguracion); ?>"
                                                        data-config-tipo="<?php echo escapar($tipoConfiguracion); ?>"
                                                        required
                                                    >
                                                        <option value="">Seleccione una opción</option>
                                                        <?php
                                                        foreach (
                                                            $opcionesConfiguracionTodas[$tipoConfiguracion]
                                                            as $opcionConfiguracion
                                                        ):
                                                            $opcionSeleccionada =
                                                                $idActualConfiguracion
                                                                === (int) $opcionConfiguracion['id_opcion'];

                                                            if (
                                                                $opcionConfiguracion['estado_registro']
                                                                !== 'activo'
                                                                && !$opcionSeleccionada
                                                            ) {
                                                                continue;
                                                            }
                                                        ?>
                                                            <option
                                                                value="<?php echo (int) $opcionConfiguracion['id_opcion']; ?>"
                                                                <?php echo $opcionSeleccionada ? 'selected' : ''; ?>
                                                            >
                                                                <?php
                                                                echo escapar(
                                                                    $opcionConfiguracion['nombre']
                                                                    . (
                                                                        $opcionConfiguracion['estado_registro']
                                                                        === 'activo'
                                                                            ? ''
                                                                            : ' (inhabilitada)'
                                                                    )
                                                                );
                                                                ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php endforeach; ?>

                                            <div class="acciones-edicion">
                                                <button type="submit" class="btn btn-edit">
                                                    Guardar cambios
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-cancel"
                                                    onclick="mostrarEdicionServicio(<?php echo (int) $srv['id_servicio']; ?>)"
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
            <p class="sin-registros">Este catálogo todavía no tiene servicios registrados.</p>
        <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <script>
        function abrirModal(id) {
            document.getElementById(id).style.display = 'block';
        }

        function cerrarModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function mostrarEdicionServicio(id) {
            const fila = document.getElementById('edicion-servicio-' + id);
            const estaVisible = fila.style.display === 'table-row';
            fila.style.display = estaVisible ? 'none' : 'table-row';

            document.querySelectorAll('.acciones-menu[open]').forEach(function (menu) {
                menu.removeAttribute('open');
            });
        }

        function confirmarEliminarServicio() {
            return window.confirm(
                '¿Está seguro de eliminar este servicio? Esta acción no se puede deshacer.'
            );
        }

        function inicializarOpcionesDeServicio() {
            const dependencias = {
                departamento: 'pais',
                ciudad: 'departamento'
            };

            document.querySelectorAll('.config-service-form').forEach(function (formulario) {
                const selects = {};

                formulario
                    .querySelectorAll('select[data-config-tipo]')
                    .forEach(function (select) {
                        selects[select.dataset.configTipo] = select;
                    });

                function filtrarSelect(tipoHijo) {
                    const tipoPadre = dependencias[tipoHijo];
                    const selectPadre = selects[tipoPadre];
                    const selectHijo = selects[tipoHijo];

                    if (!selectPadre || !selectHijo) {
                        return;
                    }

                    const idPadre = selectPadre.value;
                    let seleccionValida = selectHijo.value === '';

                    Array.from(selectHijo.options).forEach(function (opcion) {
                        if (opcion.value === '') {
                            opcion.hidden = false;
                            opcion.disabled = false;
                            return;
                        }

                        const coincide = idPadre !== ''
                            && opcion.dataset.padre === idPadre;

                        opcion.hidden = !coincide;
                        opcion.disabled = !coincide;

                        if (coincide && opcion.selected) {
                            seleccionValida = true;
                        }
                    });

                    if (!seleccionValida) {
                        selectHijo.value = '';
                    }

                    selectHijo.disabled = idPadre === '';
                }

                function actualizarDesde(tipoPadre) {
                    if (tipoPadre === 'pais') {
                        filtrarSelect('departamento');
                        filtrarSelect('ciudad');
                    } else if (tipoPadre === 'departamento') {
                        filtrarSelect('ciudad');
                    }
                }

                ['pais', 'departamento'].forEach(function (tipoPadre) {
                    if (!selects[tipoPadre]) {
                        return;
                    }

                    selects[tipoPadre].addEventListener('change', function () {
                        actualizarDesde(tipoPadre);
                    });
                });

                filtrarSelect('departamento');
                filtrarSelect('ciudad');
            });
        }

        inicializarOpcionesDeServicio();

        window.addEventListener('click', function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
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

            document.querySelectorAll('.modal').forEach(function (modal) {
                modal.style.display = 'none';
            });

            document.querySelectorAll('.acciones-menu[open]').forEach(function (menu) {
                menu.removeAttribute('open');
            });
        });
    </script>
    <script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
