<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}
$idPaisOperacion = paisExigirContexto();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparConfiguracion(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function tablaConfiguracionExiste(mysqli $conn): bool
{
    try {
        $resultado = $conn->query(
            "SHOW TABLES LIKE 'configuraciones_servicio'"
        );

        return $resultado !== false && $resultado->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function redirigirConfiguracion(
    string $mensaje,
    string $tipo = ''
): never {
    $parametros = ['msg' => $mensaje];
    $destino = defined('CONFIGURACION_URL')
        ? (string) CONFIGURACION_URL
        : 'configuraciones.php';

    if ($tipo !== '') {
        $parametros['tipo'] = $tipo;
    }

    header('Location: ' . $destino . '?' . http_build_query($parametros));
    exit;
}

$tiposConfiguracion = [
    'pais' => [
        'plural' => 'Países',
        'singular' => 'País',
        'descripcion' => 'Registre los países donde actúa la empresa.',
        'codigo' => 'PA',
        'color' => '#7c3aed',
        'suave' => '#f1eafe',
        'padre' => null,
        'columna' => 'id_pais',
    ],
    'departamento' => [
        'plural' => 'Departamentos',
        'singular' => 'Departamento',
        'descripcion' => 'Registre los departamentos asociados a cada país.',
        'codigo' => 'DE',
        'color' => '#6d28d9',
        'suave' => '#eee9fe',
        'padre' => 'pais',
        'columna' => 'id_departamento',
    ],
    'ciudad' => [
        'plural' => 'Ciudades',
        'singular' => 'Ciudad',
        'descripcion' => 'Registre las ciudades asociadas a cada departamento.',
        'codigo' => 'CI',
        'color' => '#8b5cf6',
        'suave' => '#f3efff',
        'padre' => 'departamento',
        'columna' => 'id_ciudad',
    ],
    'prioridad' => [
        'plural' => 'Prioridades',
        'singular' => 'Prioridad',
        'descripcion' => 'Administre las prioridades utilizadas en los servicios.',
        'codigo' => 'PR',
        'color' => '#db2777',
        'suave' => '#fce7f3',
        'padre' => null,
        'columna' => 'id_prioridad',
    ],
    'urgencia' => [
        'plural' => 'Urgencias',
        'singular' => 'Urgencia',
        'descripcion' => 'Administre los grados de urgencia de las solicitudes.',
        'codigo' => 'UR',
        'color' => '#e11d48',
        'suave' => '#ffe4e9',
        'padre' => null,
        'columna' => 'id_urgencia',
    ],
    'nivel' => [
        'plural' => 'Niveles',
        'singular' => 'Nivel',
        'descripcion' => 'Administre los niveles de atención o escalamiento.',
        'codigo' => 'NV',
        'color' => '#0f6fec',
        'suave' => '#e8f2ff',
        'padre' => null,
        'columna' => 'id_nivel',
    ],
    'impacto' => [
        'plural' => 'Impactos',
        'singular' => 'Impacto',
        'descripcion' => 'Defina el alcance que puede tener una solicitud.',
        'codigo' => 'IM',
        'color' => '#0e9f9a',
        'suave' => '#e5f8f6',
        'padre' => null,
        'columna' => 'id_impacto',
    ],
    'estado' => [
        'plural' => 'Estados',
        'singular' => 'Estado',
        'descripcion' => 'Administre los estados disponibles para los servicios.',
        'codigo' => 'ES',
        'color' => '#d97706',
        'suave' => '#fff3df',
        'padre' => null,
        'columna' => 'id_estado',
    ],
];

$moduloInstalado = tablaConfiguracionExiste($conn);
$tipoActual = (string) ($_GET['tipo'] ?? '');

if ($tipoActual !== '' && !isset($tiposConfiguracion[$tipoActual])) {
    $tipoActual = '';
}

/*
 * Cada lista se presenta como un módulo individual desde panelAdmin.php.
 * Evitamos una pantalla intermedia de "configuración general".
 */
if ($tipoActual === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panelAdmin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals((string) $_SESSION['csrf_token'], $token)
    ) {
        redirigirConfiguracion('solicitud_invalida', $tipoActual);
    }

    $tipo = (string) ($_POST['tipo'] ?? '');

    if (!$moduloInstalado || !isset($tiposConfiguracion[$tipo])) {
        redirigirConfiguracion('instalacion_pendiente', $tipoActual);
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $idOpcion = filter_input(
        INPUT_POST,
        'id_opcion',
        FILTER_VALIDATE_INT
    ) ?: 0;

    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $color = strtolower(trim((string) ($_POST['color'] ?? '#0f6fec')));
            $orden = filter_input(
                INPUT_POST,
                'orden',
                FILTER_VALIDATE_INT
            );
            $idPadre = filter_input(
                INPUT_POST,
                'id_padre',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $tipoPadre = $tiposConfiguracion[$tipo]['padre'];

            if (
                $nombre === ''
                || strlen($nombre) > 120
                || strlen($descripcion) > 255
                || !preg_match('/^#[0-9a-f]{6}$/', $color)
            ) {
                redirigirConfiguracion('datos_incompletos', $tipo);
            }

            if ($tipoPadre !== null) {
                if (!$idPadre) {
                    redirigirConfiguracion('padre_requerido', $tipo);
                }

                $stmtPadre = $conn->prepare(
                    "SELECT id_opcion
                     FROM configuraciones_servicio
                     WHERE id_opcion = ?
                       AND tipo = ?
                       AND id_pais_operacion = ?
                       AND estado_registro = 'activo'
                     LIMIT 1"
                );
                $stmtPadre->bind_param('isi', $idPadre, $tipoPadre, $idPaisOperacion);
                $stmtPadre->execute();
                $stmtPadre->store_result();
                $padreValido = $stmtPadre->num_rows > 0;
                $stmtPadre->close();

                if (!$padreValido) {
                    redirigirConfiguracion('padre_requerido', $tipo);
                }
            } else {
                $idPadre = 0;
            }

            if ($orden === false || $orden === null || $orden < 0) {
                $orden = 0;
            }

            if ($accion === 'crear') {
                if ($orden === 0) {
                    $stmtOrden = $conn->prepare(
                        "SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
                         FROM configuraciones_servicio
                         WHERE tipo = ? AND id_pais_operacion = ?"
                    );
                    $stmtOrden->bind_param('si', $tipo, $idPaisOperacion);
                    $stmtOrden->execute();
                    $orden = (int) (
                        $stmtOrden->get_result()->fetch_assoc()['siguiente'] ?? 1
                    );
                    $stmtOrden->close();
                }

                $stmt = $conn->prepare(
                    "INSERT INTO configuraciones_servicio
                        (
                            id_pais_operacion,
                            tipo,
                            id_padre,
                            nombre,
                            descripcion,
                            color,
                            estado_registro,
                            orden
                        )
                     VALUES (?, ?, NULLIF(?, 0), ?, ?, ?, 'activo', ?)"
                );
                $stmt->bind_param(
                    'isisssi',
                    $idPaisOperacion,
                    $tipo,
                    $idPadre,
                    $nombre,
                    $descripcion,
                    $color,
                    $orden
                );
                $stmt->execute();
                $stmt->close();

                redirigirConfiguracion('opcion_creada', $tipo);
            }

            if (!$idOpcion) {
                redirigirConfiguracion('opcion_no_encontrada', $tipo);
            }

            $stmt = $conn->prepare(
                "UPDATE configuraciones_servicio
                 SET
                    id_padre = NULLIF(?, 0),
                    nombre = ?,
                    descripcion = ?,
                    color = ?,
                    orden = ?
                 WHERE id_opcion = ?
                   AND tipo = ?
                   AND id_pais_operacion = ?"
            );
            $stmt->bind_param(
                'isssiisi',
                $idPadre,
                $nombre,
                $descripcion,
                $color,
                $orden,
                $idOpcion,
                $tipo,
                $idPaisOperacion
            );
            $stmt->execute();
            $stmt->close();

            redirigirConfiguracion('opcion_actualizada', $tipo);
        }

        if ($accion === 'estado') {
            $nuevoEstado = (string) ($_POST['nuevo_estado'] ?? '');

            if (
                !$idOpcion
                || !in_array(
                    $nuevoEstado,
                    ['activo', 'inhabilitado'],
                    true
                )
            ) {
                redirigirConfiguracion('solicitud_invalida', $tipo);
            }

            $stmt = $conn->prepare(
                "UPDATE configuraciones_servicio
                 SET estado_registro = ?
                 WHERE id_opcion = ?
                   AND tipo = ?
                   AND id_pais_operacion = ?"
            );
            $stmt->bind_param('sisi', $nuevoEstado, $idOpcion, $tipo, $idPaisOperacion);
            $stmt->execute();
            $stmt->close();

            redirigirConfiguracion(
                $nuevoEstado === 'activo'
                    ? 'opcion_habilitada'
                    : 'opcion_inhabilitada',
                $tipo
            );
        }

        if ($accion === 'eliminar') {
            if (!$idOpcion) {
                redirigirConfiguracion('opcion_no_encontrada', $tipo);
            }

            $columnaServicio = $tiposConfiguracion[$tipo]['columna'];
            $conn->begin_transaction();

            if ($tipo === 'pais') {
                $stmt = $conn->prepare(
                    "UPDATE usuarios
                     SET id_pais = NULL, id_departamento = NULL, id_ciudad = NULL, ciudad = 'Sin asignar'
                     WHERE id_pais = ?
                       AND id_pais_operacion = ?"
                );
                $stmt->bind_param('ii', $idOpcion, $idPaisOperacion);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "UPDATE servicios
                     SET id_pais = NULL, id_departamento = NULL, id_ciudad = NULL
                     WHERE id_pais = ?
                       AND id_pais_operacion = ?"
                );
                $stmt->bind_param('ii', $idOpcion, $idPaisOperacion);
                $stmt->execute();
                $stmt->close();
            } elseif ($tipo === 'departamento') {
                $stmt = $conn->prepare(
                    "UPDATE usuarios
                     SET id_departamento = NULL, id_ciudad = NULL, ciudad = 'Sin asignar'
                     WHERE id_departamento = ?
                       AND id_pais_operacion = ?"
                );
                $stmt->bind_param('ii', $idOpcion, $idPaisOperacion);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "UPDATE servicios
                     SET id_departamento = NULL, id_ciudad = NULL
                     WHERE id_departamento = ?
                       AND id_pais_operacion = ?"
                );
                $stmt->bind_param('ii', $idOpcion, $idPaisOperacion);
                $stmt->execute();
                $stmt->close();
            } elseif ($tipo === 'ciudad') {
                $stmt = $conn->prepare(
                    "UPDATE usuarios
                     SET id_ciudad = NULL, ciudad = 'Sin asignar'
                     WHERE id_ciudad = ?
                       AND id_pais_operacion = ?"
                );
                $stmt->bind_param('ii', $idOpcion, $idPaisOperacion);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $conn->prepare(
                "UPDATE servicios
                 SET `{$columnaServicio}` = NULL
                 WHERE `{$columnaServicio}` = ?
                   AND id_pais_operacion = ?"
            );
            $stmt->bind_param('ii', $idOpcion, $idPaisOperacion);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "UPDATE configuraciones_servicio
                 SET id_padre = NULL
                 WHERE id_padre = ?
                   AND id_pais_operacion = ?"
            );
            $stmt->bind_param('ii', $idOpcion, $idPaisOperacion);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "DELETE FROM configuraciones_servicio
                 WHERE id_opcion = ?
                   AND tipo = ?
                   AND id_pais_operacion = ?"
            );
            $stmt->bind_param('isi', $idOpcion, $tipo, $idPaisOperacion);
            $stmt->execute();
            $eliminada = $stmt->affected_rows > 0;
            $stmt->close();

            if (!$eliminada) {
                throw new RuntimeException('La opción no existe.');
            }

            $conn->commit();
            redirigirConfiguracion('opcion_eliminada', $tipo);
        }

        redirigirConfiguracion('solicitud_invalida', $tipo);
    } catch (mysqli_sql_exception $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // La operación pudo no iniciar una transacción.
        }

        error_log(
            'Error SQL en configuraciones.php: '
            . $e->getMessage()
        );

        redirigirConfiguracion(
            $e->getCode() === 1062
                ? 'opcion_duplicada'
                : 'error_operacion',
            $tipo
        );
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // La operación pudo no iniciar una transacción.
        }

        error_log(
            'Error en configuraciones.php: '
            . $e->getMessage()
        );
        redirigirConfiguracion('error_operacion', $tipo);
    }
}

$mensajes = [
    'opcion_creada' => ['exito', 'La opción fue creada correctamente.'],
    'opcion_actualizada' => ['exito', 'La opción fue actualizada correctamente.'],
    'opcion_habilitada' => ['exito', 'La opción fue habilitada correctamente.'],
    'opcion_inhabilitada' => ['aviso', 'La opción fue inhabilitada correctamente.'],
    'opcion_eliminada' => [
        'exito',
        'La opción fue eliminada. Sus asignaciones en servicios y perfiles se limpiaron.'
    ],
    'opcion_duplicada' => [
        'error',
        'Ya existe una opción con ese nombre dentro de la categoría.'
    ],
    'opcion_no_encontrada' => ['error', 'La opción seleccionada no existe.'],
    'padre_requerido' => [
        'error',
        'Seleccione correctamente la ubicación superior.'
    ],
    'datos_incompletos' => ['error', 'Complete correctamente los campos obligatorios.'],
    'solicitud_invalida' => [
        'error',
        'La operación no es válida. Actualice la página e intente nuevamente.'
    ],
    'instalacion_pendiente' => [
        'aviso',
        'Debe ejecutar crear_modulo_configuraciones.sql en phpMyAdmin.'
    ],
    'error_operacion' => [
        'error',
        'No fue posible completar la operación. Verifique la base de datos.'
    ],
];

$mensajeActual = (string) ($_GET['msg'] ?? '');
$opciones = [];
$opcionesPadre = [];

if ($moduloInstalado) {
    $stmt = $conn->prepare(
        "SELECT
            o.id_opcion,
            o.tipo,
            o.id_padre,
            o.nombre,
            o.descripcion,
            o.color,
            o.estado_registro,
            o.orden,
            padre.nombre AS nombre_padre
         FROM configuraciones_servicio AS o
         LEFT JOIN configuraciones_servicio AS padre
            ON padre.id_opcion = o.id_padre
         WHERE o.tipo = ?
           AND o.id_pais_operacion = ?
         ORDER BY o.orden ASC, o.nombre ASC"
    );
    $stmt->bind_param('si', $tipoActual, $idPaisOperacion);
    $stmt->execute();
    $opciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $tipoPadreActual = $tiposConfiguracion[$tipoActual]['padre'];

    if ($tipoPadreActual !== null) {
        $stmt = $conn->prepare(
            "SELECT
                id_opcion,
                nombre,
                estado_registro
             FROM configuraciones_servicio
             WHERE tipo = ?
               AND id_pais_operacion = ?
               AND estado_registro = 'activo'
             ORDER BY orden, nombre"
        );
        $stmt->bind_param('si', $tipoPadreActual, $idPaisOperacion);
        $stmt->execute();
        $opcionesPadre = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$metaActual = $tipoActual !== ''
    ? $tiposConfiguracion[$tipoActual]
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escaparConfiguracion($metaActual['plural'] ?? 'Administración') ?> | Mesa de Servicio</title>
    <style>
        :root {
            --primary: #0f6fec;
            --primary-dark: #0b4fae;
            --navy: #102a43;
            --text: #243b53;
            --muted: #627d98;
            --border: #dfe8f3;
            --surface: #ffffff;
            --background: #f3f6fb;
            --success: #087443;
            --success-bg: #ecfdf3;
            --warning: #8a5b00;
            --warning-bg: #fff8e1;
            --danger: #b42318;
            --danger-bg: #fff1f0;
            --accent: <?= escaparConfiguracion($metaActual['color'] ?? '#0f6fec') ?>;
            --accent-soft: <?= escaparConfiguracion($metaActual['suave'] ?? '#e8f2ff') ?>;
            --shadow: 0 18px 48px rgba(16,42,67,.10);
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at 7% 0%, rgba(15,111,236,.09), transparent 26%),
                radial-gradient(circle at 100% 100%, rgba(14,159,154,.07), transparent 25%),
                var(--background);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        button, input, select, textarea { font: inherit; }
        a { color: inherit; }

        .shell {
            width: min(1180px, calc(100% - 40px));
            margin: 0 auto;
            padding: 28px 0 52px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 14px 18px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: rgba(255,255,255,.93);
            box-shadow: 0 8px 25px rgba(16,42,67,.06);
        }

        .brand { display: flex; align-items: center; gap: 12px; }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(145deg, var(--primary), var(--primary-dark));
            box-shadow: 0 8px 18px rgba(15,111,236,.24);
            font-weight: 800;
        }

        .brand strong { display: block; color: var(--navy); font-size: 15px; }
        .brand small { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; }

        .back {
            min-height: 39px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: #486581;
            background: #f8fbff;
            font-size: 13px;
            font-weight: 750;
            text-decoration: none;
        }

        .back:hover { color: var(--primary); background: #edf5ff; }

        .back svg, .btn svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
        }

        .hero {
            position: relative;
            isolation: isolate;
            min-height: 64px;
            display: grid;
            grid-template-columns: auto minmax(0,1fr);
            align-items: center;
            gap: 13px;
            margin-top: 12px;
            padding: 10px 16px;
            overflow: hidden;
            border-radius: 14px;
            color: #fff;
            background: linear-gradient(130deg, var(--primary-dark), var(--primary) 62%, #1b97e9);
            box-shadow: 0 9px 24px rgba(15,111,236,.15);
        }

        .hero.detail {
            background: linear-gradient(130deg, #102a43, var(--accent));
        }

        .hero::after {
            position: absolute;
            z-index: -1;
            width: 105px;
            height: 105px;
            right: -18px;
            top: -58px;
            border: 16px solid rgba(255,255,255,.07);
            border-radius: 50%;
            content: "";
        }

        .eyebrow {
            display: inline-flex;
            margin: 0;
            padding: 4px 7px;
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 999px;
            background: rgba(255,255,255,.10);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .hero-text { min-width: 0; }
        .hero h1 { margin: 0; font-size: 18px; line-height: 1.15; letter-spacing: -.02em; }
        .hero p { max-width: 880px; margin: 2px 0 0; color: rgba(255,255,255,.82); font-size: 10px; line-height: 1.3; }

        .content { margin-top: 13px; }

        .alert {
            display: flex;
            gap: 11px;
            margin-bottom: 18px;
            padding: 13px 15px;
            border: 1px solid;
            border-radius: 11px;
            font-size: 13px;
            line-height: 1.45;
        }

        .alert::before {
            width: 20px;
            height: 20px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 50%;
            color: #fff;
            font-weight: 800;
        }

        .alert.exito { color: var(--success); border-color: #abefc6; background: var(--success-bg); }
        .alert.exito::before { content: "✓"; background: #12b76a; }
        .alert.aviso { color: var(--warning); border-color: #f6d98b; background: var(--warning-bg); }
        .alert.aviso::before { content: "!"; background: #d89913; }
        .alert.error { color: var(--danger); border-color: #fecdca; background: var(--danger-bg); }
        .alert.error::before { content: "!"; background: #d92d20; }

        .config-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 16px;
        }

        .config-card {
            --card-accent: #0f6fec;
            --card-soft: #e8f2ff;
            min-height: 155px;
            display: grid;
            grid-template-columns: 56px 1fr;
            align-items: center;
            gap: 16px;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: #fff;
            box-shadow: 0 9px 25px rgba(16,42,67,.06);
            text-decoration: none;
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }

        .config-card:hover {
            transform: translateY(-3px);
            border-color: var(--card-accent);
            box-shadow: 0 16px 30px rgba(16,42,67,.11);
        }

        .config-icon {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            color: var(--card-accent);
            background: var(--card-soft);
            font-size: 14px;
            font-weight: 850;
            letter-spacing: .04em;
        }

        .config-card h2 { margin: 0; color: var(--navy); font-size: 17px; }
        .config-card p { margin: 6px 0 0; color: var(--muted); font-size: 12px; line-height: 1.5; }
        .config-count { display: inline-block; margin-top: 10px; color: var(--card-accent); font-size: 11px; font-weight: 800; }

        .panel {
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: #fff;
            box-shadow: var(--shadow);
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }

        .toolbar h2 { margin: 0; color: var(--navy); font-size: 21px; }
        .toolbar p { margin: 5px 0 0; color: var(--muted); font-size: 12px; }
        .toolbar-actions { display: flex; align-items: center; gap: 9px; }

        .search {
            width: 230px;
            min-height: 41px;
            padding: 0 12px;
            outline: none;
            border: 1px solid #cbd8e6;
            border-radius: 9px;
            color: var(--text);
            background: #fbfdff;
        }

        .search:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15,111,236,.10); }

        .btn {
            min-height: 41px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 14px;
            border: 1px solid transparent;
            border-radius: 9px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
        }

        .btn-primary { color: #fff; background: var(--primary); box-shadow: 0 7px 16px rgba(15,111,236,.20); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-soft { color: #486581; border-color: var(--border); background: #f8fbff; }
        .btn-soft:hover { background: #edf4fb; }
        .btn-warning { color: #8a5b00; background: #fff0bd; }
        .btn-success { color: #087443; background: #dcfce7; }
        .btn-danger { color: #a72836; background: #fdecee; }

        .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 13px 14px; border-bottom: 1px solid var(--border); text-align: left; font-size: 12px; }
        th { color: #486581; background: #f7f9fc; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        tbody tr:hover { background: #f9fbfe; }
        tbody tr:last-child td { border-bottom: 0; }

        .name-cell { display: flex; align-items: center; gap: 10px; }
        .color-dot { width: 13px; height: 13px; flex: 0 0 auto; border-radius: 50%; box-shadow: 0 0 0 3px rgba(98,125,152,.10); }
        .name-cell strong { color: var(--navy); }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
        }

        .status::before { width: 6px; height: 6px; content: ""; border-radius: 50%; background: currentColor; }
        .status.activo { color: #087443; background: #e5f6eb; }
        .status.inhabilitado { color: #66788a; background: #edf2f7; }

        .row-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .row-actions form { margin: 0; }
        .row-actions .btn { min-height: 32px; padding: 5px 9px; font-size: 10px; }

        .empty {
            padding: 30px;
            border: 1px dashed #bfd0df;
            border-radius: 12px;
            color: var(--muted);
            background: #fafcff;
            text-align: center;
        }

        .modal {
            position: fixed;
            z-index: 1000;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(10,27,44,.58);
        }

        .modal.open { display: flex; }

        .modal-card {
            width: min(560px,100%);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 25px 70px rgba(0,0,0,.25);
        }

        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 24px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 { margin: 0; color: var(--navy); font-size: 20px; }
        .modal-header p { margin: 6px 0 0; color: var(--muted); font-size: 12px; }
        .close { width: 34px; height: 34px; border: 0; border-radius: 9px; color: #526d82; background: #eef3f8; cursor: pointer; font-size: 21px; }

        .modal-body { padding: 23px 24px 25px; }
        .form-grid { display: grid; grid-template-columns: 1fr 130px; gap: 16px; }
        .field.full { grid-column: 1 / -1; }
        .field label { display: block; margin-bottom: 7px; color: var(--text); font-size: 12px; font-weight: 800; }
        .field input, .field select, .field textarea {
            width: 100%;
            outline: none;
            border: 1px solid #cbd8e6;
            border-radius: 9px;
            color: var(--navy);
            background: #fbfdff;
        }

        .field input, .field select { min-height: 44px; padding: 0 11px; }
        .field input[type="color"] { padding: 5px; cursor: pointer; }
        .field textarea { min-height: 105px; padding: 11px; resize: vertical; }
        .field input:focus, .field select:focus, .field textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15,111,236,.10); }
        .modal-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 20px; }

        @media (max-width: 900px) {
            .config-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
        }

        @media (max-width: 650px) {
            .shell { width: min(100% - 24px,1180px); padding-top: 12px; }
            .brand small { display: none; }
            .hero { grid-template-columns: 1fr; gap: 5px; padding: 10px 12px; }
            .config-grid { grid-template-columns: 1fr; }
            .toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-actions { align-items: stretch; flex-direction: column; }
            .search, .btn { width: 100%; }
            .form-grid { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
        }
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">MS</div>
            <div>
                <strong>Mesa de Servicio</strong>
                <small>Módulo de <?= escaparConfiguracion(strtolower($metaActual['plural'])) ?></small>
            </div>
        </div>
        <a class="back" href="panelAdmin.php">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Volver al panel
        </a>
    </header>

    <section class="hero <?= $tipoActual !== '' ? 'detail' : '' ?>">
        <span class="eyebrow"><?= $tipoActual !== '' ? 'Lista configurable' : 'Administración' ?></span>
        <div class="hero-text">
            <h1><?= escaparConfiguracion($metaActual['plural'] ?? 'Módulo administrativo') ?></h1>
            <p>
                <?= escaparConfiguracion(
                    $metaActual['descripcion']
                    ?? 'Administre las listas utilizadas por los servicios y las solicitudes de la plataforma.'
                ) ?>
            </p>
        </div>
    </section>

    <section class="content">
        <?php if (isset($mensajes[$mensajeActual])): ?>
            <div class="alert <?= escaparConfiguracion($mensajes[$mensajeActual][0]) ?>">
                <span><?= escaparConfiguracion($mensajes[$mensajeActual][1]) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$moduloInstalado): ?>
            <div class="alert aviso">
                <span>
                    Ejecute <strong>crear_modulo_configuraciones.sql</strong>
                    en la base <strong>mesa_servicio</strong> antes de administrar estas opciones.
                </span>
            </div>
        <?php endif; ?>

            <div class="panel">
                <div class="toolbar">
                    <div>
                        <h2>Opciones registradas</h2>
                        <p>Seleccione una acción para administrar cada registro.</p>
                    </div>
                    <div class="toolbar-actions">
                        <input
                            id="filterInput"
                            class="search"
                            type="search"
                            placeholder="Filtrar opciones..."
                            aria-label="Filtrar opciones"
                        >
                        <button
                            type="button"
                            class="btn btn-primary"
                            onclick="abrirCrear()"
                            <?= !$moduloInstalado ? 'disabled' : '' ?>
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            Añadir <?= escaparConfiguracion(strtolower($metaActual['singular'])) ?>
                        </button>
                    </div>
                </div>

                <?php if (!$opciones): ?>
                    <div class="empty">Todavía no hay opciones registradas en esta categoría.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th><?= escaparConfiguracion($metaActual['singular']) ?></th>
                                    <?php if ($metaActual['padre'] !== null): ?>
                                        <th><?= escaparConfiguracion($tiposConfiguracion[$metaActual['padre']]['singular']) ?></th>
                                    <?php endif; ?>
                                    <th>Descripción</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="optionsBody">
                            <?php foreach ($opciones as $opcion): ?>
                                <tr data-search="<?= escaparConfiguracion(strtolower($opcion['nombre'] . ' ' . $opcion['descripcion'] . ' ' . ($opcion['nombre_padre'] ?? ''))) ?>">
                                    <td><?= (int) $opcion['orden'] ?></td>
                                    <td>
                                        <div class="name-cell">
                                            <span class="color-dot" style="background:<?= escaparConfiguracion($opcion['color']) ?>"></span>
                                            <strong><?= escaparConfiguracion($opcion['nombre']) ?></strong>
                                        </div>
                                    </td>
                                    <?php if ($metaActual['padre'] !== null): ?>
                                        <td><?= escaparConfiguracion($opcion['nombre_padre'] ?: 'Sin asignar') ?></td>
                                    <?php endif; ?>
                                    <td><?= escaparConfiguracion($opcion['descripcion'] ?: 'Sin descripción') ?></td>
                                    <td>
                                        <span class="status <?= escaparConfiguracion($opcion['estado_registro']) ?>">
                                            <?= $opcion['estado_registro'] === 'activo' ? 'Activo' : 'Inhabilitado' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <button
                                                type="button"
                                                class="btn btn-soft"
                                                data-id="<?= (int) $opcion['id_opcion'] ?>"
                                                data-nombre="<?= escaparConfiguracion($opcion['nombre']) ?>"
                                                data-descripcion="<?= escaparConfiguracion($opcion['descripcion']) ?>"
                                                data-color="<?= escaparConfiguracion($opcion['color']) ?>"
                                                data-orden="<?= (int) $opcion['orden'] ?>"
                                                data-padre="<?= (int) ($opcion['id_padre'] ?? 0) ?>"
                                                onclick="abrirEditar(this)"
                                            >Editar</button>

                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= escaparConfiguracion($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="accion" value="estado">
                                                <input type="hidden" name="tipo" value="<?= escaparConfiguracion($tipoActual) ?>">
                                                <input type="hidden" name="id_opcion" value="<?= (int) $opcion['id_opcion'] ?>">
                                                <input
                                                    type="hidden"
                                                    name="nuevo_estado"
                                                    value="<?= $opcion['estado_registro'] === 'activo' ? 'inhabilitado' : 'activo' ?>"
                                                >
                                                <button
                                                    type="submit"
                                                    class="btn <?= $opcion['estado_registro'] === 'activo' ? 'btn-warning' : 'btn-success' ?>"
                                                >
                                                    <?= $opcion['estado_registro'] === 'activo' ? 'Inhabilitar' : 'Habilitar' ?>
                                                </button>
                                            </form>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('¿Eliminar esta opción? Las asignaciones existentes en servicios y perfiles quedarán vacías.');"
                                            >
                                                <input type="hidden" name="csrf_token" value="<?= escaparConfiguracion($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="tipo" value="<?= escaparConfiguracion($tipoActual) ?>">
                                                <input type="hidden" name="id_opcion" value="<?= (int) $opcion['id_opcion'] ?>">
                                                <button type="submit" class="btn btn-danger">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
    </section>
</main>

<div id="optionModal" class="modal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <div>
                <h2 id="modalTitle">Añadir <?= escaparConfiguracion(strtolower($metaActual['singular'])) ?></h2>
                <p>Complete la información de la opción configurable.</p>
            </div>
            <button type="button" class="close" onclick="cerrarModal()" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="optionForm">
                <input type="hidden" name="csrf_token" value="<?= escaparConfiguracion($_SESSION['csrf_token']) ?>">
                <input type="hidden" id="formAction" name="accion" value="crear">
                <input type="hidden" name="tipo" value="<?= escaparConfiguracion($tipoActual) ?>">
                <input type="hidden" id="formId" name="id_opcion" value="">

                <div class="form-grid">
                    <div class="field">
                        <label for="formName">Nombre *</label>
                        <input id="formName" type="text" name="nombre" maxlength="120" required>
                    </div>
                    <div class="field">
                        <label for="formOrder">Orden</label>
                        <input id="formOrder" type="number" name="orden" min="0" value="0">
                    </div>

                    <?php if ($metaActual['padre'] !== null): ?>
                        <div class="field full">
                            <label for="formParent">
                                <?= escaparConfiguracion($tiposConfiguracion[$metaActual['padre']]['singular']) ?> *
                            </label>
                            <select id="formParent" name="id_padre" required>
                                <option value="">Seleccione una opción</option>
                                <?php foreach ($opcionesPadre as $padre): ?>
                                    <option value="<?= (int) $padre['id_opcion'] ?>">
                                        <?= escaparConfiguracion($padre['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="field full">
                        <label for="formDescription">Descripción</label>
                        <textarea id="formDescription" name="descripcion" maxlength="255"></textarea>
                    </div>
                    <div class="field">
                        <label for="formColor">Color</label>
                        <input id="formColor" type="color" name="color" value="<?= escaparConfiguracion($metaActual['color']) ?>">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-soft" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="saveButton">Guardar opción</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('optionModal');

    function abrirCrear() {
        if (!modal) return;

        document.getElementById('modalTitle').textContent =
            'Añadir <?= escaparConfiguracion(strtolower($metaActual['singular'] ?? 'opción')) ?>';
        document.getElementById('formAction').value = 'crear';
        document.getElementById('formId').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formDescription').value = '';
        document.getElementById('formColor').value =
            '<?= escaparConfiguracion($metaActual['color'] ?? '#0f6fec') ?>';
        document.getElementById('formOrder').value = '0';

        const parent = document.getElementById('formParent');
        if (parent) parent.value = '';

        document.getElementById('saveButton').textContent = 'Guardar opción';
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('formName').focus();
    }

    function abrirEditar(button) {
        if (!modal) return;

        document.getElementById('modalTitle').textContent =
            'Editar <?= escaparConfiguracion(strtolower($metaActual['singular'] ?? 'opción')) ?>';
        document.getElementById('formAction').value = 'editar';
        document.getElementById('formId').value = button.dataset.id;
        document.getElementById('formName').value = button.dataset.nombre;
        document.getElementById('formDescription').value =
            button.dataset.descripcion || '';
        document.getElementById('formColor').value =
            button.dataset.color || '#0f6fec';
        document.getElementById('formOrder').value =
            button.dataset.orden || '0';

        const parent = document.getElementById('formParent');
        if (parent) parent.value = button.dataset.padre || '';

        document.getElementById('saveButton').textContent = 'Guardar cambios';
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('formName').focus();
    }

    function cerrarModal() {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) cerrarModal();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') cerrarModal();
    });

    const filterInput = document.getElementById('filterInput');

    if (filterInput) {
        filterInput.addEventListener('input', function () {
            const query = filterInput.value.trim().toLowerCase();

            document.querySelectorAll('#optionsBody tr').forEach(function (row) {
                row.hidden = !row.dataset.search.includes(query);
            });
        });
    }

    const optionForm = document.getElementById('optionForm');

    if (optionForm) {
        optionForm.addEventListener('submit', function () {
            const button = document.getElementById('saveButton');
            button.disabled = true;
            button.textContent = 'Guardando...';
        });
    }
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
