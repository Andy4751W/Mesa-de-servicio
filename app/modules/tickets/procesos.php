<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}
$idPaisOperacion = paisExigirContexto();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparProceso(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirigirProceso(string $mensaje, int $idProceso = 0): never
{
    $parametros = ['msg' => $mensaje];

    if ($idProceso > 0) {
        $parametros['id_proceso'] = $idProceso;
    }

    header('Location: procesos.php?' . http_build_query($parametros));
    exit;
}

function nombreProcesoServicio(
    string $catalogo,
    string $servicio,
    int $idServicio
): string {
    $nombre = trim($catalogo . ' · ' . $servicio);

    if (strlen($nombre) > 150) {
        $nombre = substr($nombre, 0, 150);
    }

    return $nombre !== '' ? $nombre : 'Servicio ' . $idServicio;
}

function sincronizarServiciosConProcesos(
    mysqli $conn,
    int $idAdministrador
): void {
    $idPaisOperacion = paisExigirContexto();
    $servicios = [];
    $resultado = $conn->query(
        "SELECT s.id_servicio, s.nombre AS servicio,
                c.nombre AS catalogo
         FROM servicios AS s
         INNER JOIN catalogos AS c ON c.id_catalogo = s.id_catalogo
         WHERE s.estado = 'activo'
           AND c.estado = 'activo'
           AND s.id_pais_operacion = {$idPaisOperacion}
         ORDER BY c.orden, c.nombre, s.nombre"
    );

    while ($fila = $resultado->fetch_assoc()) {
        $servicios[] = $fila;
    }

    $procesosPorServicio = [];
    $resultado = $conn->query(
        "SELECT pe.id_servicio, p.id_proceso
         FROM procesos AS p
         INNER JOIN proceso_etapas AS pe
            ON pe.id_proceso = p.id_proceso
           AND pe.estado = 'activo'
         WHERE pe.orden = (
            SELECT MIN(pe_inicio.orden)
            FROM proceso_etapas AS pe_inicio
            WHERE pe_inicio.id_proceso = p.id_proceso
              AND pe_inicio.estado = 'activo'
         )
           AND p.id_pais_operacion = {$idPaisOperacion}
         ORDER BY FIELD(p.estado, 'activo', 'inhabilitado'), p.id_proceso"
    );

    while ($fila = $resultado->fetch_assoc()) {
        $idServicio = (int) $fila['id_servicio'];

        if (!isset($procesosPorServicio[$idServicio])) {
            $procesosPorServicio[$idServicio] = (int) $fila['id_proceso'];
        }
    }

    foreach ($servicios as $servicio) {
        $idServicio = (int) $servicio['id_servicio'];

        if (isset($procesosPorServicio[$idServicio])) {
            continue;
        }

        $nombreBase = nombreProcesoServicio(
            (string) $servicio['catalogo'],
            (string) $servicio['servicio'],
            $idServicio
        );
        $nombreProceso = $nombreBase;
        $stmt = $conn->prepare(
            "SELECT id_proceso FROM procesos
             WHERE nombre = ? AND id_pais_operacion = ? LIMIT 1"
        );
        $stmt->bind_param('si', $nombreProceso, $idPaisOperacion);
        $stmt->execute();
        $nombreOcupado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($nombreOcupado) {
            $sufijo = ' · S' . $idServicio;
            $nombreProceso = substr($nombreBase, 0, 160 - strlen($sufijo)) . $sufijo;
        }

        $descripcion = 'Flujo sincronizado automáticamente con el servicio.';
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare(
                "INSERT INTO procesos
                    (id_pais_operacion, nombre, descripcion, creado_por, actualizado_por)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'issii',
                $idPaisOperacion,
                $nombreProceso,
                $descripcion,
                $idAdministrador,
                $idAdministrador
            );
            $stmt->execute();
            $idProceso = (int) $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO proceso_etapas
                    (id_proceso, id_servicio, orden, nombre_etapa, instrucciones)
                 VALUES (?, ?, 1, NULL, NULL)"
            );
            $stmt->bind_param('ii', $idProceso, $idServicio);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!flujoModuloInstalado($conn)) {
    ?><!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8"><title>Instalación requerida</title>
    <style>body{font-family:Segoe UI,Arial;background:#f3f6fb;color:#243b53;padding:40px}.box{max-width:760px;margin:auto;background:#fff;border:1px solid #dfe7f1;border-radius:18px;padding:28px;box-shadow:0 16px 40px #1232}.btn{display:inline-block;margin-top:16px;padding:11px 16px;border-radius:10px;background:#0f6fec;color:#fff;text-decoration:none}</style></head>
    <body><section class="box"><h1>Instalación pendiente</h1>
    <p>Importe primero <strong>migracion_flujos_secuenciales.sql</strong> sobre la base de datos <strong>mesa_servicio</strong>.</p>
    <a class="btn" href="panelAdmin.php">Volver al panel</a></section></body></html><?php
    exit;
}

$idAdministrador = (int) $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals((string) $_SESSION['csrf_token'], $token)
    ) {
        redirigirProceso('solicitud_invalida');
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $idProceso = filter_input(INPUT_POST, 'id_proceso', FILTER_VALIDATE_INT) ?: 0;

    if ($idProceso > 0 && !paisRegistroPertenece($conn, 'procesos', $idProceso)) {
        redirigirProceso('solicitud_invalida');
    }

    try {
        if ($accion === 'guardar_servicio') {
            $idServicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT) ?: 0;
            $idGestor = filter_input(INPUT_POST, 'id_gestor', FILTER_VALIDATE_INT) ?: 0;
            $idSla = filter_input(INPUT_POST, 'id_sla', FILTER_VALIDATE_INT) ?: 0;

            if (!$idServicio || !$idGestor || !$idSla) {
                redirigirProceso('datos_incompletos', $idProceso);
            }

            if (!paisRegistroPertenece($conn, 'servicios', $idServicio)) {
                redirigirProceso('configuracion_invalida', $idProceso);
            }

            $stmt = $conn->prepare(
                "SELECT 1 FROM usuarios
                 WHERE id_usuario = ?
                   AND id_rol = 2
                   AND id_pais_operacion = ?
                   AND estado = 'activo'
                 LIMIT 1"
            );
            $stmt->bind_param('ii', $idGestor, $idPaisOperacion);
            $stmt->execute();
            $stmt->store_result();
            $gestorValido = $stmt->num_rows > 0;
            $stmt->close();

            $stmt = $conn->prepare(
                "SELECT 1 FROM sla
                 WHERE id_sla = ? AND estado = 'activo'
                   AND id_pais_operacion = ?
                 LIMIT 1"
            );
            $stmt->bind_param('ii', $idSla, $idPaisOperacion);
            $stmt->execute();
            $stmt->store_result();
            $slaValido = $stmt->num_rows > 0;
            $stmt->close();

            if (!$gestorValido || !$slaValido) {
                redirigirProceso('configuracion_invalida', $idProceso);
            }

            $stmt = $conn->prepare(
                "UPDATE servicios
                 SET id_gestor = ?, id_sla = ?
                 WHERE id_servicio = ?
                   AND id_pais_operacion = ?
                   AND estado = 'activo'"
            );
            $stmt->bind_param('iiii', $idGestor, $idSla, $idServicio, $idPaisOperacion);
            $stmt->execute();
            $stmt->close();
            redirigirProceso('servicio_configurado', $idProceso);
        }

        if ($accion === 'cambiar_estado_flujo') {
            $estado = (string) ($_POST['estado'] ?? 'activo');

            if (
                !$idProceso
                || !in_array($estado, ['activo', 'inhabilitado'], true)
            ) {
                redirigirProceso('solicitud_invalida', $idProceso);
            }

            $stmt = $conn->prepare(
                "UPDATE procesos
                 SET estado = ?, actualizado_por = ?
                 WHERE id_proceso = ?"
            );
            $stmt->bind_param('sii', $estado, $idAdministrador, $idProceso);
            $stmt->execute();
            $stmt->close();
            redirigirProceso('estado_actualizado', $idProceso);
        }

        if ($accion === 'agregar_etapa') {
            $idServicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT) ?: 0;
            $nombreEtapa = trim((string) ($_POST['nombre_etapa'] ?? ''));
            $instrucciones = trim((string) ($_POST['instrucciones'] ?? ''));

            if (!$idProceso || !$idServicio) {
                redirigirProceso('datos_incompletos', $idProceso);
            }

            if (!paisRegistroPertenece($conn, 'servicios', $idServicio)) {
                redirigirProceso('servicio_sin_configurar', $idProceso);
            }

            $stmt = $conn->prepare(
                "SELECT s.id_servicio
                 FROM servicios AS s
                 INNER JOIN usuarios AS u
                    ON u.id_usuario = s.id_gestor
                   AND u.id_rol = 2
                   AND u.estado = 'activo'
                 INNER JOIN sla AS sl
                    ON sl.id_sla = s.id_sla
                   AND sl.estado = 'activo'
                 WHERE s.id_servicio = ?
                   AND s.id_pais_operacion = ?
                   AND s.estado = 'activo'
                 LIMIT 1"
            );
            $stmt->bind_param('ii', $idServicio, $idPaisOperacion);
            $stmt->execute();
            $stmt->store_result();
            $valido = $stmt->num_rows > 0;
            $stmt->close();

            if (!$valido) {
                redirigirProceso('servicio_sin_configurar', $idProceso);
            }

            $stmt = $conn->prepare(
                "SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
                 FROM proceso_etapas
                 WHERE id_proceso = ?"
            );
            $stmt->bind_param('i', $idProceso);
            $stmt->execute();
            $orden = (int) $stmt->get_result()->fetch_assoc()['siguiente'];
            $stmt->close();
            $stmt = $conn->prepare(
                "INSERT INTO proceso_etapas
                    (
                        id_proceso,
                        id_servicio,
                        orden,
                        nombre_etapa,
                        instrucciones
                    )
                 VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))"
            );
            $stmt->bind_param(
                'iiiss',
                $idProceso,
                $idServicio,
                $orden,
                $nombreEtapa,
                $instrucciones
            );
            $stmt->execute();
            $stmt->close();
            redirigirProceso('etapa_agregada', $idProceso);
        }

        if ($accion === 'mover_etapa') {
            $idEtapa = filter_input(INPUT_POST, 'id_proceso_etapa', FILTER_VALIDATE_INT) ?: 0;
            $direccion = (string) ($_POST['direccion'] ?? '');

            if (!$idProceso || !$idEtapa || !in_array($direccion, ['subir', 'bajar'], true)) {
                redirigirProceso('solicitud_invalida', $idProceso);
            }

            $operador = $direccion === 'subir' ? '<' : '>';
            $ordenSql = $direccion === 'subir' ? 'DESC' : 'ASC';
            $conn->begin_transaction();
            $stmt = $conn->prepare(
                "SELECT orden FROM proceso_etapas
                 WHERE id_proceso_etapa = ? AND id_proceso = ?
                 FOR UPDATE"
            );
            $stmt->bind_param('ii', $idEtapa, $idProceso);
            $stmt->execute();
            $actual = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$actual) {
                throw new RuntimeException('Etapa no encontrada.');
            }

            $stmt = $conn->prepare(
                "SELECT id_proceso_etapa
                 FROM proceso_etapas
                 WHERE id_proceso = ? AND estado = 'activo'
                 ORDER BY orden, id_proceso_etapa
                 LIMIT 1"
            );
            $stmt->bind_param('i', $idProceso);
            $stmt->execute();
            $etapaBase = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($etapaBase && (int) $etapaBase['id_proceso_etapa'] === $idEtapa) {
                $conn->rollback();
                redirigirProceso('etapa_base_protegida', $idProceso);
            }

            $ordenActual = (int) $actual['orden'];
            $sqlVecina = "SELECT id_proceso_etapa, orden
                          FROM proceso_etapas
                          WHERE id_proceso = ? AND orden {$operador} ?
                          ORDER BY orden {$ordenSql}
                          LIMIT 1 FOR UPDATE";
            $stmt = $conn->prepare($sqlVecina);
            $stmt->bind_param('ii', $idProceso, $ordenActual);
            $stmt->execute();
            $vecina = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($vecina) {
                $idVecina = (int) $vecina['id_proceso_etapa'];
                $ordenVecina = (int) $vecina['orden'];

                if (
                    $direccion === 'subir'
                    && $etapaBase
                    && (int) $etapaBase['id_proceso_etapa'] === $idVecina
                ) {
                    $conn->rollback();
                    redirigirProceso('etapa_base_protegida', $idProceso);
                }

                $temporal = 1000000 + $idEtapa;
                $stmt = $conn->prepare(
                    "UPDATE proceso_etapas SET orden = ?
                     WHERE id_proceso_etapa = ?"
                );
                $stmt->bind_param('ii', $temporal, $idEtapa);
                $stmt->execute();
                $stmt->bind_param('ii', $ordenActual, $idVecina);
                $stmt->execute();
                $stmt->bind_param('ii', $ordenVecina, $idEtapa);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            redirigirProceso('etapa_movida', $idProceso);
        }

        if ($accion === 'eliminar_etapa') {
            $idEtapa = filter_input(INPUT_POST, 'id_proceso_etapa', FILTER_VALIDATE_INT) ?: 0;
            $stmt = $conn->prepare(
                "SELECT id_proceso_etapa
                 FROM proceso_etapas
                 WHERE id_proceso = ? AND estado = 'activo'
                 ORDER BY orden, id_proceso_etapa
                 LIMIT 1"
            );
            $stmt->bind_param('i', $idProceso);
            $stmt->execute();
            $etapaBase = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($etapaBase && (int) $etapaBase['id_proceso_etapa'] === $idEtapa) {
                redirigirProceso('etapa_base_protegida', $idProceso);
            }

            $stmt = $conn->prepare(
                "DELETE FROM proceso_etapas
                 WHERE id_proceso_etapa = ? AND id_proceso = ?"
            );
            $stmt->bind_param('ii', $idEtapa, $idProceso);
            $stmt->execute();
            $stmt->close();
            redirigirProceso('etapa_eliminada', $idProceso);
        }

        if ($accion === 'agregar_checklist') {
            $idEtapa = filter_input(INPUT_POST, 'id_proceso_etapa', FILTER_VALIDATE_INT) ?: 0;
            $nombre = trim((string) ($_POST['nombre_checklist'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion_checklist'] ?? ''));
            $obligatorio = isset($_POST['obligatorio']) ? 1 : 0;

            if (!$idProceso || !$idEtapa || $nombre === '') {
                redirigirProceso('datos_incompletos', $idProceso);
            }

            $stmt = $conn->prepare(
                "SELECT COALESCE(MAX(pc.orden), 0) + 1 AS siguiente
                 FROM proceso_etapa_checklist AS pc
                 INNER JOIN proceso_etapas AS pe
                    ON pe.id_proceso_etapa = pc.id_proceso_etapa
                 WHERE pc.id_proceso_etapa = ?
                   AND pe.id_proceso = ?"
            );
            $stmt->bind_param('ii', $idEtapa, $idProceso);
            $stmt->execute();
            $orden = (int) $stmt->get_result()->fetch_assoc()['siguiente'];
            $stmt->close();
            $stmt = $conn->prepare(
                "INSERT INTO proceso_etapa_checklist
                    (
                        id_proceso_etapa,
                        nombre,
                        descripcion,
                        obligatorio,
                        orden
                    )
                 VALUES (?, ?, NULLIF(?, ''), ?, ?)"
            );
            $stmt->bind_param(
                'issii',
                $idEtapa,
                $nombre,
                $descripcion,
                $obligatorio,
                $orden
            );
            $stmt->execute();
            $stmt->close();
            redirigirProceso('checklist_agregado', $idProceso);
        }

        if ($accion === 'actualizar_checklist') {
            $idChecklist = filter_input(INPUT_POST, 'id_checklist', FILTER_VALIDATE_INT) ?: 0;
            $nombre = trim((string) ($_POST['nombre_checklist'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion_checklist'] ?? ''));
            $obligatorio = isset($_POST['obligatorio']) ? 1 : 0;

            if (!$idProceso || !$idChecklist || $nombre === '') {
                redirigirProceso('datos_incompletos', $idProceso);
            }

            $stmt = $conn->prepare(
                "UPDATE proceso_etapa_checklist AS pc
                 INNER JOIN proceso_etapas AS pe
                    ON pe.id_proceso_etapa = pc.id_proceso_etapa
                 SET pc.nombre = ?,
                     pc.descripcion = NULLIF(?, ''),
                     pc.obligatorio = ?
                 WHERE pc.id_checklist = ?
                   AND pe.id_proceso = ?"
            );
            $stmt->bind_param(
                'ssiii',
                $nombre,
                $descripcion,
                $obligatorio,
                $idChecklist,
                $idProceso
            );
            $stmt->execute();
            $stmt->close();
            redirigirProceso('checklist_actualizado', $idProceso);
        }

        if ($accion === 'eliminar_checklist') {
            $idChecklist = filter_input(INPUT_POST, 'id_checklist', FILTER_VALIDATE_INT) ?: 0;
            $stmt = $conn->prepare(
                "DELETE pc
                 FROM proceso_etapa_checklist AS pc
                 INNER JOIN proceso_etapas AS pe
                    ON pe.id_proceso_etapa = pc.id_proceso_etapa
                 WHERE pc.id_checklist = ?
                   AND pe.id_proceso = ?"
            );
            $stmt->bind_param('ii', $idChecklist, $idProceso);
            $stmt->execute();
            $stmt->close();
            redirigirProceso('checklist_eliminado', $idProceso);
        }

        redirigirProceso('solicitud_invalida', $idProceso);
    } catch (mysqli_sql_exception $e) {
        if ($conn->errno === 0) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
            }
        }
        error_log('Procesos: ' . $e->getMessage());
        redirigirProceso('registro_duplicado', $idProceso);
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }
        error_log('Procesos: ' . $e->getMessage());
        redirigirProceso('error_operacion', $idProceso);
    }
}

$errorSincronizacion = '';

try {
    sincronizarServiciosConProcesos($conn, $idAdministrador);
} catch (Throwable $e) {
    $errorSincronizacion = 'No fue posible sincronizar todos los servicios.';
    error_log('Sincronización de servicios y flujos: ' . $e->getMessage());
}

$gestores = [];
$resultado = $conn->query(
    "SELECT id_usuario, nombre, email
     FROM usuarios
     WHERE id_rol = 2
       AND id_pais_operacion = {$idPaisOperacion}
       AND estado = 'activo'
     ORDER BY nombre"
);
while ($fila = $resultado->fetch_assoc()) {
    $gestores[] = $fila;
}

$slas = [];
$resultado = $conn->query(
    "SELECT id_sla, nombre, tiempo_respuesta, unidad
     FROM sla
     WHERE estado = 'activo'
       AND id_pais_operacion = {$idPaisOperacion}
     ORDER BY nombre"
);
while ($fila = $resultado->fetch_assoc()) {
    $slas[] = $fila;
}

$servicios = [];
$resultado = $conn->query(
    "SELECT
        s.id_servicio,
        s.nombre AS servicio,
        s.descripcion AS servicio_descripcion,
        s.id_gestor,
        s.id_sla,
        c.id_catalogo,
        c.nombre AS catalogo,
        c.orden AS catalogo_orden,
        u.nombre AS gestor,
        sl.nombre AS sla_nombre,
        p.id_proceso,
        p.estado AS flujo_estado,
        (SELECT COUNT(*) FROM proceso_etapas pe_conteo
         WHERE pe_conteo.id_proceso = p.id_proceso
           AND pe_conteo.estado = 'activo') AS etapas
     FROM servicios AS s
     INNER JOIN catalogos AS c ON c.id_catalogo = s.id_catalogo
     LEFT JOIN usuarios AS u ON u.id_usuario = s.id_gestor
     LEFT JOIN sla AS sl ON sl.id_sla = s.id_sla
     LEFT JOIN procesos AS p ON p.id_proceso = (
        SELECT p_inicio.id_proceso
        FROM procesos AS p_inicio
        INNER JOIN proceso_etapas AS pe_inicio
           ON pe_inicio.id_proceso = p_inicio.id_proceso
          AND pe_inicio.estado = 'activo'
        WHERE pe_inicio.id_servicio = s.id_servicio
          AND pe_inicio.orden = (
            SELECT MIN(pe_min.orden)
            FROM proceso_etapas AS pe_min
            WHERE pe_min.id_proceso = p_inicio.id_proceso
              AND pe_min.estado = 'activo'
          )
        ORDER BY FIELD(p_inicio.estado, 'activo', 'inhabilitado'),
                 p_inicio.id_proceso
        LIMIT 1
     )
     WHERE s.estado = 'activo'
       AND c.estado = 'activo'
       AND s.id_pais_operacion = {$idPaisOperacion}
     ORDER BY c.orden, c.nombre, s.nombre"
);
while ($fila = $resultado->fetch_assoc()) {
    $servicios[] = $fila;
}

$idProcesoSeleccionado = filter_input(INPUT_GET, 'id_proceso', FILTER_VALIDATE_INT) ?: 0;
$procesoSeleccionado = null;
$etapas = [];

if ($idProcesoSeleccionado > 0) {
    $stmt = $conn->prepare(
        "SELECT p.*,
                s.id_servicio AS id_servicio_origen,
                s.nombre AS servicio_origen,
                s.descripcion AS servicio_descripcion,
                s.id_gestor,
                s.id_sla,
                c.id_catalogo,
                c.nombre AS catalogo_origen,
                u.nombre AS gestor_origen,
                sl.nombre AS sla_origen,
                sl.tiempo_respuesta,
                sl.unidad
         FROM procesos AS p
         INNER JOIN proceso_etapas AS pe
            ON pe.id_proceso = p.id_proceso
           AND pe.estado = 'activo'
           AND pe.orden = (
                SELECT MIN(pe_inicio.orden)
                FROM proceso_etapas AS pe_inicio
                WHERE pe_inicio.id_proceso = p.id_proceso
                  AND pe_inicio.estado = 'activo'
           )
         INNER JOIN servicios AS s ON s.id_servicio = pe.id_servicio
         INNER JOIN catalogos AS c ON c.id_catalogo = s.id_catalogo
         LEFT JOIN usuarios AS u ON u.id_usuario = s.id_gestor
         LEFT JOIN sla AS sl ON sl.id_sla = s.id_sla
         WHERE p.id_proceso = ?
           AND p.id_pais_operacion = ?
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idProcesoSeleccionado, $idPaisOperacion);
    $stmt->execute();
    $procesoSeleccionado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($procesoSeleccionado) {
        $stmt = $conn->prepare(
            "SELECT pe.*, s.nombre AS servicio, c.nombre AS catalogo,
                    u.nombre AS gestor, sl.nombre AS sla_nombre,
                    sl.tiempo_respuesta, sl.unidad
             FROM proceso_etapas pe
             INNER JOIN servicios s ON s.id_servicio = pe.id_servicio
             INNER JOIN catalogos c ON c.id_catalogo = s.id_catalogo
             LEFT JOIN usuarios u ON u.id_usuario = s.id_gestor
             LEFT JOIN sla sl ON sl.id_sla = s.id_sla
             WHERE pe.id_proceso = ?
             ORDER BY pe.orden, pe.id_proceso_etapa"
        );
        $stmt->bind_param('i', $idProcesoSeleccionado);
        $stmt->execute();
        $resultado = $stmt->get_result();

        while ($fila = $resultado->fetch_assoc()) {
            $idEtapa = (int) $fila['id_proceso_etapa'];
            $fila['checklist'] = [];
            $stmtChecklist = $conn->prepare(
                "SELECT * FROM proceso_etapa_checklist
                 WHERE id_proceso_etapa = ?
                 ORDER BY orden, id_checklist"
            );
            $stmtChecklist->bind_param('i', $idEtapa);
            $stmtChecklist->execute();
            $resultadoChecklist = $stmtChecklist->get_result();
            while ($item = $resultadoChecklist->fetch_assoc()) {
                $fila['checklist'][] = $item;
            }
            $stmtChecklist->close();
            $etapas[] = $fila;
        }
        $stmt->close();
    }
}

$mensajes = [
    'servicio_configurado' => ['ok', 'Gestor y SLA asignados al servicio.'],
    'estado_actualizado' => ['ok', 'Estado del flujo actualizado.'],
    'etapa_agregada' => ['ok', 'Etapa agregada al flujo del servicio.'],
    'etapa_movida' => ['ok', 'Orden de atención actualizado.'],
    'etapa_eliminada' => ['ok', 'Etapa eliminada de la plantilla.'],
    'etapa_base_protegida' => ['info', 'La primera etapa corresponde al servicio solicitado y no se puede mover ni eliminar.'],
    'checklist_agregado' => ['ok', 'Elemento agregado al checklist.'],
    'checklist_actualizado' => ['ok', 'Elemento del checklist actualizado.'],
    'checklist_eliminado' => ['ok', 'Elemento eliminado del checklist.'],
    'datos_incompletos' => ['error', 'Complete todos los datos obligatorios.'],
    'servicio_sin_configurar' => ['error', 'El servicio necesita un gestor y un SLA activos.'],
    'configuracion_invalida' => ['error', 'El gestor o SLA seleccionado no está disponible.'],
    'registro_duplicado' => ['error', 'Ya existe un registro con esos datos.'],
    'solicitud_invalida' => ['error', 'La solicitud no es válida.'],
    'error_operacion' => ['error', 'No fue posible completar la operación.'],
];
$mensajeActual = (string) ($_GET['msg'] ?? '');
$totalFlujosActivos = 0;
$idsServiciosEtapas = [];

foreach ($servicios as $servicio) {
    if (($servicio['flujo_estado'] ?? '') === 'activo') {
        $totalFlujosActivos++;
    }
}

foreach ($etapas as $etapa) {
    $idsServiciosEtapas[] = (int) $etapa['id_servicio'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flujos por servicio | Mesa de Servicio</title>
    <style>
        :root {
            --primary: #0f6fec;
            --primary-dark: #0a58bf;
            --navy: #102a43;
            --text: #243b53;
            --muted: #627d98;
            --bg: #f3f6fb;
            --surface: #fff;
            --border: #dce6f1;
            --soft: #eef5ff;
            --danger: #b42318;
            --ok: #087443;
            --warning: #9a5a00;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: linear-gradient(135deg, #f5f8fc, #edf3fa);
            color: var(--text);
            font: 12px/1.38 Inter, "Segoe UI", Arial, sans-serif;
        }

        .shell {
            width: min(1320px, calc(100% - 18px));
            margin: auto;
            padding: 8px 0 22px;
        }

        .top,
        .card {
            border: 1px solid var(--border);
            background: var(--surface);
            box-shadow: 0 7px 20px rgba(15, 45, 75, .05);
        }

        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 54px;
            padding: 7px 10px;
            border-radius: 12px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
        }

        .brand-mark {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 9px;
            background: linear-gradient(135deg, #0f6fec, #0b88e8);
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            box-shadow: 0 5px 12px rgba(15, 111, 236, .2);
        }

        .top h1 { margin: 0; color: var(--navy); font-size: 16px; line-height: 1.1; }
        .top p { margin: 2px 0 0; color: var(--muted); font-size: 9px; }

        .actions,
        .inline {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        .btn,
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 5px 9px;
            border: 0;
            border-radius: 7px;
            font: inherit;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .btn.primary,
        button.primary { background: var(--primary); color: #fff; }
        .btn.light,
        button.light { background: var(--soft); color: #225c93; }
        .btn.outline { border: 1px solid #cad9e9; background: #fff; color: #225c93; }
        .btn.danger,
        button.danger { background: #fff0ee; color: var(--danger); }
        .mini { min-height: 27px; padding: 4px 7px; font-size: 9px; }

        .summarybar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 7px;
            padding: 6px 10px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #fff;
            color: var(--muted);
            font-size: 9px;
        }

        .summarybar strong { color: var(--navy); font-size: 11px; }

        .alert {
            margin-top: 7px;
            padding: 7px 10px;
            border-radius: 8px;
            font-weight: 750;
        }

        .alert.ok { background: #eaf8f1; color: var(--ok); }
        .alert.info { background: #eef5ff; color: #225c93; }
        .alert.error { background: #fff0ee; color: var(--danger); }

        .layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: 8px;
            margin-top: 8px;
            align-items: start;
        }

        .card {
            padding: 10px;
            border-radius: 11px;
        }

        .card + .card { margin-top: 8px; }

        .card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .card h2,
        .card h3 { margin: 0; color: var(--navy); }
        .card h2 { font-size: 14px; }
        .card h3 { font-size: 11px; }
        .muted,
        .meta { color: var(--muted); }
        .meta { font-size: 9px; }

        .service-sidebar {
            position: sticky;
            top: 8px;
            max-height: calc(100vh - 24px);
            overflow: hidden;
        }

        .service-search { margin-bottom: 7px; }

        .service-list {
            max-height: calc(100vh - 135px);
            overflow-y: auto;
            padding-right: 2px;
        }

        .service-link {
            display: block;
            margin-top: 5px;
            padding: 7px 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: inherit;
            text-decoration: none;
            transition: border-color .16s ease, background .16s ease;
        }

        .service-link:hover { border-color: #83b5ee; background: #f7fbff; }
        .service-link.active { border-color: #65a2ea; background: #eef6ff; }
        .service-link strong { display: block; margin-top: 1px; color: var(--navy); font-size: 10px; }
        .service-link small { display: block; color: var(--muted); font-size: 8px; }

        .service-link-footer {
            display: flex;
            justify-content: space-between;
            gap: 5px;
            margin-top: 4px;
            color: var(--muted);
            font-size: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 6px;
            border-radius: 999px;
            background: var(--soft);
            color: #225c93;
            font-size: 8px;
            font-weight: 850;
        }

        .badge.ok { background: #eaf8f1; color: var(--ok); }
        .badge.off { background: #fff1ef; color: var(--danger); }
        .badge.base { background: #e9f1ff; color: var(--primary-dark); }

        .service-title {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .service-title h2 { font-size: 17px; }
        .service-title p { margin: 3px 0 0; }

        .config-grid {
            display: grid;
            grid-template-columns: minmax(160px, 1fr) minmax(160px, 1fr) auto;
            gap: 7px;
            align-items: end;
        }

        .flow-state {
            display: flex;
            align-items: end;
            gap: 5px;
        }

        label {
            display: block;
            margin: 0 0 3px;
            color: var(--navy);
            font-size: 9px;
            font-weight: 800;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 31px;
            padding: 6px 8px;
            border: 1px solid #cbd9e8;
            border-radius: 7px;
            background: #fff;
            color: var(--text);
            font: inherit;
            outline: none;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #6aa7ee;
            box-shadow: 0 0 0 3px rgba(15, 111, 236, .08);
        }

        textarea { min-height: 48px; resize: vertical; }
        input[type="checkbox"] { width: auto; min-height: 0; }

        .warning-box {
            margin-top: 7px;
            padding: 7px 9px;
            border: 1px solid #f0d39b;
            border-radius: 7px;
            background: #fff8e9;
            color: var(--warning);
            font-size: 9px;
        }

        .add-stage-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1.2fr) minmax(150px, .8fr) minmax(200px, 1fr) auto;
            gap: 6px;
            align-items: end;
        }

        .stage {
            margin-top: 7px;
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
            border-radius: 9px;
            background: #fff;
            overflow: hidden;
        }

        .stage.base-stage { border-left-color: #087443; }

        .stage-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 9px;
            background: #fbfcfe;
        }

        .stage-title {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
        }

        .stage-number {
            display: grid;
            flex: 0 0 23px;
            width: 23px;
            height: 23px;
            place-items: center;
            border-radius: 7px;
            background: var(--primary);
            color: #fff;
            font-size: 9px;
            font-weight: 900;
        }

        .base-stage .stage-number { background: var(--ok); }
        .stage-title strong { display: block; color: var(--navy); font-size: 10px; }

        .stage-instructions {
            margin: 0;
            padding: 6px 9px;
            border-top: 1px solid #edf1f6;
            color: var(--muted);
            font-size: 9px;
        }

        .checklist-panel { border-top: 1px solid #e8eef5; }

        .checklist-panel summary {
            padding: 6px 9px;
            color: #315f8d;
            font-size: 9px;
            font-weight: 850;
            cursor: pointer;
            list-style: none;
        }

        .checklist-panel summary::-webkit-details-marker { display: none; }
        .checklist-panel summary::before { content: "▸ "; }
        .checklist-panel[open] summary::before { content: "▾ "; }

        .checklist-body { padding: 0 9px 8px; }

        .check-item {
            display: grid;
            grid-template-columns: minmax(150px, .8fr) minmax(190px, 1fr) auto auto;
            gap: 5px;
            align-items: center;
            padding: 5px 0;
            border-top: 1px solid #edf1f6;
        }

        .required-check {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin: 0;
            white-space: nowrap;
        }

        .new-check {
            display: grid;
            grid-template-columns: minmax(150px, .8fr) minmax(190px, 1fr) auto auto;
            gap: 5px;
            align-items: center;
            padding-top: 6px;
            border-top: 1px solid #dce6f1;
        }

        .empty {
            padding: 26px 12px;
            color: var(--muted);
            text-align: center;
        }

        .empty strong { display: block; margin-bottom: 4px; color: var(--navy); font-size: 13px; }

        @media (max-width: 980px) {
            .layout { grid-template-columns: 1fr; }
            .service-sidebar { position: static; max-height: none; }
            .service-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); max-height: 310px; gap: 5px; }
            .service-link { margin: 0; }
            .add-stage-grid { grid-template-columns: 1fr 1fr; }
            .check-item,
            .new-check { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 640px) {
            .shell { width: min(100% - 10px, 1320px); padding-top: 5px; }
            .top { align-items: flex-start; flex-direction: column; }
            .top .actions { width: 100%; }
            .top .actions .btn { flex: 1; }
            .summarybar { flex-wrap: wrap; }
            .service-list { grid-template-columns: 1fr; }
            .config-grid,
            .add-stage-grid,
            .check-item,
            .new-check { grid-template-columns: 1fr; }
            .stage-head,
            .service-title { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
<main class="shell">
    <header class="top">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">MS</span>
            <div>
                <h1>Flujos por servicio</h1>
                <p>Asigne gestor, SLA, etapas y checklist a los servicios existentes.</p>
            </div>
        </div>
        <div class="actions">
            <a class="btn light" href="solicitudes.php">Ver tickets</a>
            <a class="btn outline" href="panelAdmin.php">← Volver al panel</a>
        </div>
    </header>

    <div class="summarybar">
        <span><strong><?= count($servicios) ?></strong> servicios sincronizados</span>
        <span><strong><?= $totalFlujosActivos ?></strong> flujos activos</span>
        <span>Los servicios se crean y editan desde el módulo Servicios.</span>
    </div>

    <?php if ($errorSincronizacion !== ''): ?>
        <div class="alert error"><?= escaparProceso($errorSincronizacion) ?></div>
    <?php endif; ?>

    <?php if (isset($mensajes[$mensajeActual])): ?>
        <div class="alert <?= escaparProceso($mensajes[$mensajeActual][0]) ?>">
            <?= escaparProceso($mensajes[$mensajeActual][1]) ?>
        </div>
    <?php endif; ?>

    <div class="layout">
        <aside class="card service-sidebar">
            <div class="card-head">
                <div>
                    <h2>Servicios</h2>
                    <span class="meta">Seleccione uno para configurar su flujo.</span>
                </div>
                <span class="badge"><?= count($servicios) ?></span>
            </div>
            <input class="service-search" id="serviceSearch" type="search" placeholder="Buscar servicio o área...">
            <div class="service-list" id="serviceList">
                <?php foreach ($servicios as $servicio): ?>
                    <a
                        class="service-link <?= (int) $servicio['id_proceso'] === $idProcesoSeleccionado ? 'active' : '' ?>"
                        href="procesos.php?id_proceso=<?= (int) $servicio['id_proceso'] ?>"
                        data-search="<?= escaparProceso(strtolower($servicio['catalogo'] . ' ' . $servicio['servicio'])) ?>"
                    >
                        <small><?= escaparProceso($servicio['catalogo']) ?></small>
                        <strong><?= escaparProceso($servicio['servicio']) ?></strong>
                        <span class="service-link-footer">
                            <span><?= (int) $servicio['etapas'] ?> etapa<?= (int) $servicio['etapas'] === 1 ? '' : 's' ?></span>
                            <span class="badge <?= $servicio['flujo_estado'] === 'activo' ? 'ok' : 'off' ?>">
                                <?= escaparProceso($servicio['flujo_estado']) ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <section>
            <?php if ($procesoSeleccionado): ?>
                <article class="card">
                    <div class="service-title">
                        <div>
                            <span class="badge"><?= escaparProceso($procesoSeleccionado['catalogo_origen']) ?></span>
                            <h2><?= escaparProceso($procesoSeleccionado['servicio_origen']) ?></h2>
                            <p class="muted"><?= escaparProceso($procesoSeleccionado['servicio_descripcion']) ?></p>
                        </div>
                        <form class="flow-state" method="post">
                            <input type="hidden" name="csrf_token" value="<?= escaparProceso($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="accion" value="cambiar_estado_flujo">
                            <input type="hidden" name="id_proceso" value="<?= $idProcesoSeleccionado ?>">
                            <div>
                                <label for="estado_flujo">Estado del flujo</label>
                                <select id="estado_flujo" name="estado">
                                    <option value="activo" <?= $procesoSeleccionado['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                                    <option value="inhabilitado" <?= $procesoSeleccionado['estado'] === 'inhabilitado' ? 'selected' : '' ?>>Inhabilitado</option>
                                </select>
                            </div>
                            <button class="light" type="submit">Guardar</button>
                        </form>
                    </div>
                </article>

                <article class="card">
                    <div class="card-head">
                        <div>
                            <h2>Responsable y SLA</h2>
                            <span class="meta">Asignación del servicio que inicia este flujo.</span>
                        </div>
                    </div>
                    <form class="config-grid" method="post">
                        <input type="hidden" name="csrf_token" value="<?= escaparProceso($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="accion" value="guardar_servicio">
                        <input type="hidden" name="id_proceso" value="<?= $idProcesoSeleccionado ?>">
                        <input type="hidden" name="id_servicio" value="<?= (int) $procesoSeleccionado['id_servicio_origen'] ?>">
                        <div>
                            <label for="id_gestor">Gestor</label>
                            <select id="id_gestor" name="id_gestor" required>
                                <option value="">Seleccione un gestor</option>
                                <?php foreach ($gestores as $gestor): ?>
                                    <option value="<?= (int) $gestor['id_usuario'] ?>" <?= (int) $procesoSeleccionado['id_gestor'] === (int) $gestor['id_usuario'] ? 'selected' : '' ?>>
                                        <?= escaparProceso($gestor['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="id_sla">SLA</label>
                            <select id="id_sla" name="id_sla" required>
                                <option value="">Seleccione un SLA</option>
                                <?php foreach ($slas as $sla): ?>
                                    <option value="<?= (int) $sla['id_sla'] ?>" <?= (int) $procesoSeleccionado['id_sla'] === (int) $sla['id_sla'] ? 'selected' : '' ?>>
                                        <?= escaparProceso($sla['nombre'] . ' · ' . $sla['tiempo_respuesta'] . ' ' . $sla['unidad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="primary" type="submit">Guardar asignación</button>
                    </form>
                    <?php if (!(int) $procesoSeleccionado['id_gestor'] || !(int) $procesoSeleccionado['id_sla']): ?>
                        <div class="warning-box">Asigne gestor y SLA para habilitar este servicio al solicitante.</div>
                    <?php endif; ?>
                </article>

                <article class="card">
                    <div class="card-head">
                        <div>
                            <h2>Agregar siguiente etapa</h2>
                            <span class="meta">Seleccione otro servicio ya configurado para continuar el flujo.</span>
                        </div>
                    </div>
                    <form class="add-stage-grid" method="post">
                        <input type="hidden" name="csrf_token" value="<?= escaparProceso($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="accion" value="agregar_etapa">
                        <input type="hidden" name="id_proceso" value="<?= $idProcesoSeleccionado ?>">
                        <div>
                            <label for="servicio_etapa">Área / servicio</label>
                            <select id="servicio_etapa" name="id_servicio" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($servicios as $servicio): ?>
                                    <?php if (!(int) $servicio['id_gestor'] || !(int) $servicio['id_sla'] || in_array((int) $servicio['id_servicio'], $idsServiciosEtapas, true)) continue; ?>
                                    <option value="<?= (int) $servicio['id_servicio'] ?>">
                                        <?= escaparProceso($servicio['catalogo'] . ' / ' . $servicio['servicio'] . ' · ' . $servicio['gestor']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="nombre_etapa">Nombre opcional</label>
                            <input id="nombre_etapa" name="nombre_etapa" maxlength="160" placeholder="Ej. Validación final">
                        </div>
                        <div>
                            <label for="instrucciones">Instrucciones</label>
                            <input id="instrucciones" name="instrucciones" maxlength="1000" placeholder="Indicaciones para el gestor">
                        </div>
                        <button class="primary" type="submit">Agregar al flujo</button>
                    </form>
                </article>

                <article class="card">
                    <div class="card-head">
                        <div>
                            <h2>Orden y checklist</h2>
                            <span class="meta">La primera etapa es el servicio solicitado; las siguientes pueden reordenarse.</span>
                        </div>
                        <span class="badge"><?= count($etapas) ?> etapas</span>
                    </div>

                    <?php foreach ($etapas as $indice => $etapa): ?>
                        <?php $esEtapaBase = $indice === 0; ?>
                        <section class="stage <?= $esEtapaBase ? 'base-stage' : '' ?>">
                            <div class="stage-head">
                                <div class="stage-title">
                                    <span class="stage-number"><?= (int) $etapa['orden'] ?></span>
                                    <div>
                                        <strong><?= escaparProceso($etapa['catalogo'] . ' / ' . $etapa['servicio']) ?></strong>
                                        <span class="meta">
                                            Gestor: <?= escaparProceso($etapa['gestor'] ?: 'Sin asignar') ?> ·
                                            SLA: <?= escaparProceso($etapa['sla_nombre'] ?: 'Sin SLA') ?>
                                        </span>
                                    </div>
                                    <?php if ($esEtapaBase): ?><span class="badge base">Servicio solicitado</span><?php endif; ?>
                                </div>

                                <?php if (!$esEtapaBase): ?>
                                    <div class="inline">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= escaparProceso($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="accion" value="mover_etapa">
                                            <input type="hidden" name="id_proceso" value="<?= $idProcesoSeleccionado ?>">
                                            <input type="hidden" name="id_proceso_etapa" value="<?= (int) $etapa['id_proceso_etapa'] ?>">
                                            <button class="light mini" name="direccion" value="subir" title="Subir">↑</button>
                                            <button class="light mini" name="direccion" value="bajar" title="Bajar">↓</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('¿Eliminar esta etapa del flujo?')">
                                            <input type="hidden" name="csrf_token" value="<?= escaparProceso($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="accion" value="eliminar_etapa">
                                            <input type="hidden" name="id_proceso" value="<?= $idProcesoSeleccionado ?>">
                                            <input type="hidden" name="id_proceso_etapa" value="<?= (int) $etapa['id_proceso_etapa'] ?>">
                                            <button class="danger mini" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (trim((string) $etapa['instrucciones']) !== ''): ?>
                                <p class="stage-instructions"><?= escaparProceso($etapa['instrucciones']) ?></p>
                            <?php endif; ?>

                            <details class="checklist-panel">
                                <summary>Checklist · <?= count($etapa['checklist']) ?> elemento<?= count($etapa['checklist']) === 1 ? '' : 's' ?></summary>
                                <div class="checklist-body">
                                    <?php foreach ($etapa['checklist'] as $item): ?>
                                        <form class="check-item" method="post">
                                            <input type="hidden" name="csrf_token" value="<?= escaparProceso($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="accion" value="actualizar_checklist">
                                            <input type="hidden" name="id_proceso" value="<?= $idProcesoSeleccionado ?>">
                                            <input type="hidden" name="id_checklist" value="<?= (int) $item['id_checklist'] ?>">
                                            <input name="nombre_checklist" value="<?= escaparProceso($item['nombre']) ?>" required>
                                            <input name="descripcion_checklist" value="<?= escaparProceso($item['descripcion']) ?>" placeholder="Descripción opcional">
                                            <label class="required-check">
                                                <input type="checkbox" name="obligatorio" <?= (int) $item['obligatorio'] === 1 ? 'checked' : '' ?>> Obligatorio
                                            </label>
                                            <div class="inline">
                                                <button class="light mini" type="submit">Guardar</button>
                                                <button class="danger mini" name="accion" value="eliminar_checklist" onclick="return confirm('¿Eliminar este elemento?')">Eliminar</button>
                                            </div>
                                        </form>
                                    <?php endforeach; ?>

                                    <form class="new-check" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= escaparProceso($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="agregar_checklist">
                                        <input type="hidden" name="id_proceso" value="<?= $idProcesoSeleccionado ?>">
                                        <input type="hidden" name="id_proceso_etapa" value="<?= (int) $etapa['id_proceso_etapa'] ?>">
                                        <input name="nombre_checklist" placeholder="Nuevo elemento" required>
                                        <input name="descripcion_checklist" placeholder="Descripción opcional">
                                        <label class="required-check"><input type="checkbox" name="obligatorio" checked> Obligatorio</label>
                                        <button class="primary mini" type="submit">Agregar</button>
                                    </form>
                                </div>
                            </details>
                        </section>
                    <?php endforeach; ?>
                </article>
            <?php else: ?>
                <article class="card empty">
                    <strong>Seleccione un servicio</strong>
                    Elija un servicio de la lista para asignar su gestor y configurar el flujo de atención.
                </article>
            <?php endif; ?>
        </section>
    </div>
</main>
<script>
    (() => {
        const search = document.getElementById('serviceSearch');
        const links = Array.from(document.querySelectorAll('.service-link'));

        if (!search) return;

        search.addEventListener('input', () => {
            const term = search.value.trim().toLowerCase();
            links.forEach((link) => {
                link.hidden = term !== '' && !String(link.dataset.search || '').includes(term);
            });
        });
    })();
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
