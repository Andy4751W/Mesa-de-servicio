<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

seguridadExigirRol([3]);

$idPaisOperacion = paisExigirContexto();
$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$nombreUsuario = trim((string) ($_SESSION['usuario'] ?? 'Solicitante'));

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparPortalSolicitante(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function fechaPortalSolicitante(?string $fecha): string
{
    if (!$fecha) {
        return 'Pendiente';
    }

    $marca = strtotime($fecha);

    return $marca ? date('d/m/Y H:i', $marca) : $fecha;
}

function textoEstadoPortalSolicitante(string $estado): string
{
    return match ($estado) {
        'en_proceso' => 'En gestión',
        'pendiente_calificacion' => 'Solución lista',
        'cerrado', 'cerrada' => 'Cerrado',
        'cancelado', 'cancelada' => 'Cancelado',
        'abierto' => 'Abierto',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function textoEstadoCasoSolicitante(string $estado): string
{
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En gestión',
        'en_espera_solicitante' => 'En espera',
        'pausada' => 'Pausado por dependencia',
        'listo_cierre' => 'Solución lista',
        'completada' => 'Completado',
        'bloqueada' => 'Aún no iniciado',
        'cancelada' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function redirigirPortalSolicitante(
    string $mensaje,
    string $vista = 'tickets',
    int $idTicket = 0,
    int $idChat = 0,
    string $fragmento = ''
): never {
    $parametros = ['vista' => $vista, 'msg' => $mensaje];

    if ($idTicket > 0) {
        $parametros['id_ticket'] = $idTicket;
    }

    if ($idChat > 0) {
        $parametros['id_chat'] = $idChat;
    }

    $ancla = preg_match('/^[A-Za-z0-9_-]+$/', $fragmento)
        ? '#' . $fragmento
        : '';
    header(
        'Location: panelSolicitante.php?'
            . http_build_query($parametros)
            . $ancla,
        true,
        303
    );
    exit;
}

if (
    !flujoModuloInstalado($conn)
    || !flujoModuloAprobacionCasosInstalado($conn)
    || !flujoColumnaExiste($conn, 'servicios', 'tipo_solicitud')
    || !flujoColumnaExiste($conn, 'tickets', 'tipo_solicitud')
) {
    http_response_code(503);
    exit('El módulo de casos necesita la migración 007_clasificacion_y_atencion_servicio.sql. Comuníquese con el administrador.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    seguridadExigirOrigenPost();
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
        redirigirPortalSolicitante('solicitud_invalida', 'nueva');
    }

    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'calificar_cerrar') {
        $idTicket = filter_input(INPUT_POST, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
        $idTicketEtapa = filter_input(INPUT_POST, 'id_ticket_etapa', FILTER_VALIDATE_INT) ?: 0;
        $calificacionArea = filter_input(INPUT_POST, 'calificacion_area', FILTER_VALIDATE_INT) ?: 0;
        $calificacionTiempo = filter_input(INPUT_POST, 'calificacion_tiempo', FILTER_VALIDATE_INT) ?: 0;
        $comentario = trim((string) ($_POST['comentario_calificacion'] ?? ''));

        if (
            $idTicket < 1
            || $idTicketEtapa < 1
            || $calificacionArea < 1
            || $calificacionArea > 5
            || $calificacionTiempo < 1
            || $calificacionTiempo > 5
            || strlen($comentario) > 1000
        ) {
            redirigirPortalSolicitante('calificacion_invalida', 'tickets', $idTicket);
        }

        try {
            paisExigirTicket($conn, $idTicket);

            if (!flujoPuedeVerTicket($conn, $idTicket, $idUsuario, 3)) {
                throw new RuntimeException('La solicitud no pertenece a su usuario.');
            }

            flujoCompletarEtapa(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                3,
                $calificacionArea,
                $calificacionTiempo,
                $comentario
            );

            $ticketActualizado = flujoObtenerTicket($conn, $idTicket);
            $mensaje = (string) ($ticketActualizado['estado_flujo'] ?? '') === 'cerrado'
                ? 'ticket_cerrado'
                : 'caso_calificado';
            redirigirPortalSolicitante($mensaje, 'tickets', $idTicket);
        } catch (Throwable $e) {
            error_log('Error al calificar el caso: ' . $e->getMessage());
            $_SESSION['error_portal_solicitante'] = $e instanceof RuntimeException
                ? $e->getMessage()
                : '';
            redirigirPortalSolicitante('error_calificacion', 'tickets', $idTicket);
        }
    }

    if ($accion === 'reabrir_caso') {
        $idTicket = filter_input(INPUT_POST, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
        $idTicketEtapa = filter_input(INPUT_POST, 'id_ticket_etapa', FILTER_VALIDATE_INT) ?: 0;
        $motivo = trim((string) ($_POST['motivo_reapertura'] ?? ''));

        if ($idTicket < 1 || $idTicketEtapa < 1 || $motivo === '' || strlen($motivo) > 1000) {
            redirigirPortalSolicitante('reapertura_invalida', 'tickets', $idTicket);
        }

        try {
            paisExigirTicket($conn, $idTicket);

            if (!flujoPuedeVerTicket($conn, $idTicket, $idUsuario, 3)) {
                throw new RuntimeException('La solicitud no pertenece a su usuario.');
            }

            flujoReabrirDerivacion(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                3,
                $motivo
            );
            redirigirPortalSolicitante('caso_reabierto', 'tickets', $idTicket);
        } catch (Throwable $e) {
            error_log('Error al reabrir el caso: ' . $e->getMessage());
            $_SESSION['error_portal_solicitante'] = $e instanceof RuntimeException
                ? $e->getMessage()
                : '';
            redirigirPortalSolicitante('error_reapertura', 'tickets', $idTicket);
        }
    }

    if ($accion === 'enviar_mensaje') {
        $idTicket = filter_input(INPUT_POST, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
        $idTicketEtapa = filter_input(INPUT_POST, 'id_ticket_etapa', FILTER_VALIDATE_INT) ?: 0;
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));

        try {
            paisExigirTicket($conn, $idTicket);
            $ticket = flujoObtenerTicket($conn, $idTicket);

            if (
                !$ticket
                || !flujoPuedeVerTicket($conn, $idTicket, $idUsuario, 3)
            ) {
                throw new RuntimeException(
                    'La solicitud no pertenece a su usuario.'
                );
            }

            flujoEnviarConversacion(
                $conn,
                $ticket,
                $idTicketEtapa,
                $idUsuario,
                3,
                $mensaje,
                isset($_FILES['adjuntos'])
                    ? (array) $_FILES['adjuntos']
                    : []
            );
            redirigirPortalSolicitante(
                'mensaje_enviado',
                'tickets',
                $idTicket,
                $idTicketEtapa,
                'conversacion-solicitante'
            );
        } catch (Throwable $e) {
            error_log(
                'Error al enviar mensaje del solicitante: '
                . $e->getMessage()
            );
            $_SESSION['error_portal_solicitante'] = $e instanceof RuntimeException
                ? $e->getMessage()
                : '';
            redirigirPortalSolicitante(
                'error_mensaje',
                'tickets',
                $idTicket,
                $idTicketEtapa,
                'conversacion-solicitante'
            );
        }
    }

    if ($accion !== 'crear_ticket') {
        redirigirPortalSolicitante('accion_no_permitida', 'tickets');
    }

    $idCatalogo = filter_input(INPUT_POST, 'id_catalogo', FILTER_VALIDATE_INT) ?: 0;
    $idProceso = filter_input(INPUT_POST, 'id_proceso', FILTER_VALIDATE_INT) ?: 0;
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

    if (
        $idCatalogo < 1
        || $idProceso < 1
        || $titulo === ''
        || $descripcion === ''
        || strlen($titulo) > 180
        || strlen($descripcion) > 15000
    ) {
        redirigirPortalSolicitante('datos_incompletos', 'nueva');
    }

    if (!paisRegistroPertenece($conn, 'procesos', $idProceso)) {
        redirigirPortalSolicitante('servicio_no_disponible', 'nueva');
    }

    $stmtServicioCatalogo = $conn->prepare(
        "SELECT 1
         FROM procesos AS p
         INNER JOIN proceso_etapas AS pe
            ON pe.id_proceso = p.id_proceso
           AND pe.estado = 'activo'
         INNER JOIN servicios AS s
            ON s.id_servicio = pe.id_servicio
           AND s.estado = 'activo'
         INNER JOIN catalogos AS c
            ON c.id_catalogo = s.id_catalogo
           AND c.estado = 'activo'
         WHERE p.id_proceso = ?
           AND p.id_pais_operacion = ?
           AND p.estado = 'activo'
           AND c.id_catalogo = ?
           AND c.id_pais_operacion = ?
           AND pe.orden = (
                SELECT MIN(pe_inicio.orden)
                FROM proceso_etapas AS pe_inicio
                WHERE pe_inicio.id_proceso = p.id_proceso
                  AND pe_inicio.estado = 'activo'
           )
         LIMIT 1"
    );
    $stmtServicioCatalogo->bind_param(
        'iiii',
        $idProceso,
        $idPaisOperacion,
        $idCatalogo,
        $idPaisOperacion
    );
    $stmtServicioCatalogo->execute();
    $stmtServicioCatalogo->store_result();
    $servicioPerteneceCatalogo = $stmtServicioCatalogo->num_rows > 0;
    $stmtServicioCatalogo->close();

    if (!$servicioPerteneceCatalogo) {
        redirigirPortalSolicitante('servicio_no_disponible', 'nueva');
    }

    try {
        $idTicketNuevo = flujoCrearTicket(
            $conn,
            $idProceso,
            $idUsuario,
            $titulo,
            $descripcion
        );
        $etapaInicial = flujoEtapaActual($conn, $idTicketNuevo);
        $mensaje = 'ticket_creado';

        if ($etapaInicial && isset($_FILES['adjuntos'])) {
            try {
                flujoGuardarAdjuntos(
                    $conn,
                    $idTicketNuevo,
                    (int) $etapaInicial['id_ticket_etapa'],
                    $idUsuario,
                    $_FILES['adjuntos']
                );
            } catch (Throwable $errorAdjunto) {
                error_log(
                    'Ticket creado sin uno o más adjuntos: '
                    . $errorAdjunto->getMessage()
                );
                $mensaje = 'ticket_creado_sin_adjuntos';
            }
        }

        redirigirPortalSolicitante($mensaje, 'tickets', $idTicketNuevo);
    } catch (Throwable $e) {
        error_log('Error al crear ticket del solicitante: ' . $e->getMessage());
        $_SESSION['error_portal_solicitante'] = $e instanceof RuntimeException
            ? $e->getMessage()
            : '';
        redirigirPortalSolicitante('error_operacion', 'nueva');
    }
}

$vista = (string) ($_GET['vista'] ?? 'tickets');

if (!in_array($vista, ['nueva', 'tickets'], true)) {
    $vista = 'tickets';
}

$busquedaCasos = trim((string) ($_GET['buscar'] ?? ''));

if (strlen($busquedaCasos) > 120) {
    $busquedaCasos = substr($busquedaCasos, 0, 120);
}

$estadoBusquedaCasos = (string) ($_GET['estado_busqueda'] ?? 'todos');
$estadosBusquedaPermitidos = [
    'todos',
    'abierto',
    'en_proceso',
    'pendiente_calificacion',
    'cerrado',
    'cancelado',
];

if (!in_array($estadoBusquedaCasos, $estadosBusquedaPermitidos, true)) {
    $estadoBusquedaCasos = 'todos';
}

$mensajes = [
    'ticket_creado' => ['ok', 'La solicitud fue creada y asignada correctamente.'],
    'ticket_creado_sin_adjuntos' => ['aviso', 'La solicitud fue creada, pero uno o más archivos no pudieron guardarse.'],
    'caso_calificado' => ['ok', 'La etapa fue aprobada y el mismo caso continúa con la siguiente etapa.'],
    'ticket_cerrado' => ['ok', 'La calificación fue registrada y el caso quedó cerrado completamente.'],
    'calificacion_invalida' => ['error', 'Califique de 1 a 5 la gestión y el tiempo de respuesta.'],
    'error_calificacion' => ['error', 'No fue posible guardar la calificación ni cerrar el caso.'],
    'caso_reabierto' => ['ok', 'La etapa del caso fue reabierta y regresó al gestor asignado.'],
    'mensaje_enviado' => ['ok', 'El mensaje fue enviado al gestor de la etapa.'],
    'error_mensaje' => ['error', 'No fue posible enviar el mensaje.'],
    'reapertura_invalida' => ['error', 'Explique por qué la solución no resolvió la solicitud.'],
    'error_reapertura' => ['error', 'No fue posible reabrir la etapa del caso.'],
    'datos_incompletos' => ['error', 'Complete todos los campos obligatorios.'],
    'servicio_no_disponible' => ['error', 'El servicio seleccionado ya no está disponible.'],
    'solicitud_invalida' => ['error', 'La solicitud no es válida. Actualice la página.'],
    'accion_no_permitida' => ['error', 'Su perfil solo puede crear solicitudes, consultar su estado, conversar con el gestor del flujo y calificar cuando corresponda.'],
    'error_operacion' => ['error', 'No fue posible crear la solicitud. Inténtelo nuevamente.'],
];
$mensajeActual = (string) ($_GET['msg'] ?? '');
$detalleError = trim((string) ($_SESSION['error_portal_solicitante'] ?? ''));
unset($_SESSION['error_portal_solicitante']);

$catalogosSolicitud = [];
$resultadoCatalogosSolicitud = $conn->query(
    "SELECT id_catalogo, nombre, descripcion, imagen, orden
     FROM catalogos
     WHERE id_pais_operacion = {$idPaisOperacion}
       AND estado = 'activo'
     ORDER BY orden ASC, nombre ASC, id_catalogo ASC"
);

while ($catalogoSolicitud = $resultadoCatalogosSolicitud->fetch_assoc()) {
    $catalogosSolicitud[] = $catalogoSolicitud;
}

$servicios = flujoServiciosDisponibles($conn);
$cantidadServiciosCatalogo = [];

foreach ($servicios as $servicioDisponible) {
    $idCatalogoServicio = (int) ($servicioDisponible['id_catalogo'] ?? 0);

    if ($idCatalogoServicio < 1) {
        continue;
    }

    $cantidadServiciosCatalogo[$idCatalogoServicio] =
        ($cantidadServiciosCatalogo[$idCatalogoServicio] ?? 0) + 1;
}
$tickets = flujoTicketsUsuario($conn, $idUsuario, 3);
$ticketsConSolucionLista = [];
$stmtListos = $conn->prepare(
    "SELECT DISTINCT t.id_ticket
     FROM tickets AS t
     INNER JOIN ticket_etapas AS te ON te.id_ticket = t.id_ticket
     WHERE t.id_usuario = ?
       AND t.id_pais_operacion = ?
       AND te.id_ticket_etapa_padre IS NULL
       AND te.estado = 'listo_cierre'"
);
$stmtListos->bind_param('ii', $idUsuario, $idPaisOperacion);
$stmtListos->execute();
$resultadoListos = $stmtListos->get_result();

while ($filaLista = $resultadoListos->fetch_assoc()) {
    $ticketsConSolucionLista[(int) $filaLista['id_ticket']] = true;
}

$stmtListos->close();
$idTicketSeleccionado = filter_input(INPUT_GET, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
$idChatSeleccionado = filter_input(INPUT_GET, 'id_chat', FILTER_VALIDATE_INT) ?: 0;
$ticketSeleccionado = null;
$etapasTicket = [];
$etapaPendienteCalificacion = null;
$conversacionesSolicitante = [];
$conversacionSolicitanteActual = null;
$comunicacionesSolicitante = [];
$adjuntosSolicitante = [];
$puedeEscribirSolicitante = false;

if ($idTicketSeleccionado > 0) {
    paisExigirTicket($conn, $idTicketSeleccionado);

    if (!flujoPuedeVerTicket($conn, $idTicketSeleccionado, $idUsuario, 3)) {
        http_response_code(403);
        exit('La solicitud no existe o no pertenece a su usuario.');
    }

    $ticketSeleccionado = flujoObtenerTicket($conn, $idTicketSeleccionado);
    $etapasTicket = array_values(array_filter(
        flujoObtenerEtapasTicket($conn, $idTicketSeleccionado),
        static fn (array $etapa): bool =>
            (int) ($etapa['id_ticket_etapa_padre'] ?? 0) === 0
    ));

    foreach ($etapasTicket as $etapaTicket) {
        $idCreadorCaso = (int) (
            $etapaTicket['creado_por']
            ?? $ticketSeleccionado['id_usuario']
            ?? 0
        );

        if (
            (string) ($etapaTicket['estado'] ?? '') === 'listo_cierre'
            && (int) ($etapaTicket['id_ticket_etapa_padre'] ?? 0) === 0
            && $idCreadorCaso === $idUsuario
        ) {
            $etapaPendienteCalificacion = $etapaTicket;
            break;
        }
    }

    $conversacionesSolicitante = array_values(array_filter(
        flujoConversacionesDisponibles(
            $conn,
            $idTicketSeleccionado,
            $idUsuario,
            3
        ),
        static fn (array $conversacion): bool =>
            (string) ($conversacion['tipo_conversacion'] ?? '') === 'flujo'
    ));

    if ($idChatSeleccionado > 0) {
        foreach ($conversacionesSolicitante as $conversacionSolicitante) {
            if (
                (int) $conversacionSolicitante['id_ticket_etapa']
                === $idChatSeleccionado
            ) {
                $conversacionSolicitanteActual = $conversacionSolicitante;
                break;
            }
        }
    }

    if (!$conversacionSolicitanteActual) {
        foreach ($conversacionesSolicitante as $conversacionSolicitante) {
            if (in_array(
                (string) ($conversacionSolicitante['estado'] ?? ''),
                ['pendiente', 'en_proceso', 'en_espera_solicitante', 'pausada'],
                true
            )) {
                $conversacionSolicitanteActual = $conversacionSolicitante;
                break;
            }
        }
    }

    if (!$conversacionSolicitanteActual && $conversacionesSolicitante) {
        $conversacionSolicitanteActual = $conversacionesSolicitante[
            count($conversacionesSolicitante) - 1
        ];
    }

    if ($conversacionSolicitanteActual) {
        $idConversacionSolicitante = (int) (
            $conversacionSolicitanteActual['id_ticket_etapa'] ?? 0
        );

        if (!flujoPuedeVerConversacionNodo(
            $conn,
            $idTicketSeleccionado,
            $idConversacionSolicitante,
            $idUsuario,
            3
        )) {
            http_response_code(403);
            exit('No tiene acceso a esta conversación.');
        }

        $comunicacionesSolicitante = flujoComunicacionesNodo(
            $conn,
            $idTicketSeleccionado,
            $idConversacionSolicitante
        );
        $adjuntosSolicitante = flujoAdjuntosNodo(
            $conn,
            $idTicketSeleccionado,
            $idConversacionSolicitante
        );
        $puedeEscribirSolicitante = flujoPuedeEscribirNodo(
            $conn,
            $ticketSeleccionado,
            $idConversacionSolicitante,
            $idUsuario,
            3
        );
    }
}

$resumen = ['total' => count($tickets), 'activos' => 0, 'listos' => 0, 'cerrados' => 0];

foreach ($tickets as $ticketResumen) {
    $estado = (string) ($ticketResumen['estado_flujo'] ?? 'en_proceso');
    $idTicketResumen = (int) ($ticketResumen['id_ticket'] ?? 0);

    if ($estado === 'cerrado') {
        $resumen['cerrados']++;
    } elseif (isset($ticketsConSolucionLista[$idTicketResumen])) {
        $resumen['listos']++;
    } else {
        $resumen['activos']++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal del solicitante | Mesa de Servicio</title>
    <style>
        :root{--primary:#0f6fec;--primary-dark:#0b4fae;--navy:#102a43;--text:#304b63;--muted:#6b8195;--border:#dce6f0;--surface:#fff;--soft:#eef5ff;--bg:#f3f6fb;--ok:#087443;--warn:#9a6500;--danger:#b42318}
        *{box-sizing:border-box}body{margin:0;color:var(--text);background:linear-gradient(135deg,#f7fafe,#edf3f9);font:13px/1.45 Inter,"Segoe UI",Arial,sans-serif}.shell{width:min(1160px,calc(100% - 24px));margin:auto;padding:12px 0 30px}.topbar,.card,.stat,.alert{border:1px solid var(--border);background:var(--surface);box-shadow:0 8px 24px rgba(16,42,67,.06)}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:10px 12px;border-radius:14px}.brand{display:flex;align-items:center;gap:10px}.mark{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;color:#fff;background:linear-gradient(145deg,var(--primary),var(--primary-dark));font-weight:900}.brand strong{display:block;color:var(--navy);font-size:14px}.brand small{color:var(--muted);font-size:10px}.logout,.btn,.tab{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:800}.logout{min-height:34px;padding:7px 11px;border:1px solid #eed9d9;border-radius:9px;color:#a73535;background:#fff8f8}.tabs{display:flex;gap:7px;margin-top:10px;padding:7px;border:1px solid var(--border);border-radius:12px;background:#fff}.tab{min-height:36px;padding:8px 13px;border-radius:8px;color:#486985}.tab.active,.tab:hover{color:#fff;background:var(--primary)}.grid-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:10px 0}.stat{padding:12px;border-radius:11px}.stat span{display:block;color:var(--muted);font-size:10px}.stat strong{display:block;margin-top:2px;color:var(--navy);font-size:22px}.card{margin-top:10px;padding:17px;border-radius:14px}.card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:15px}.card h1,.card h2{margin:0;color:var(--navy)}.card h1{font-size:22px}.card h2{font-size:17px}.muted{margin:4px 0 0;color:var(--muted)}.alert{margin-top:10px;padding:10px 12px;border-radius:10px}.alert.ok{border-color:#bfe9d2;color:var(--ok);background:#effbf4}.alert.aviso{border-color:#f1db9b;color:var(--warn);background:#fff9e9}.alert.error{border-color:#f0c4c1;color:var(--danger);background:#fff4f3}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.field{display:grid;gap:6px}.field.full{grid-column:1/-1}.field label{color:var(--navy);font-size:11px;font-weight:850}.field input,.field select,.field textarea{width:100%;padding:10px 11px;border:1px solid #cfddea;border-radius:9px;color:var(--text);background:#fff;outline:none}.field textarea{min-height:130px;resize:vertical}.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 13%,transparent)}.help{color:var(--muted);font-size:10px}.actions{display:flex;justify-content:flex-end;margin-top:14px}.btn{min-height:38px;padding:9px 15px;border:0;border-radius:9px;cursor:pointer}.btn.primary{color:#fff;background:var(--primary)}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:11px}table{width:100%;border-collapse:collapse;min-width:760px}th,td{padding:11px 12px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:middle}th{color:#52708c;background:#f7faff;font-size:10px;text-transform:uppercase;letter-spacing:.05em}td strong{color:var(--navy)}.status{display:inline-flex;padding:4px 8px;border-radius:999px;color:#255a83;background:#eaf4ff;font-size:10px;font-weight:850}.status.cerrado,.status.completada{color:#087443;background:#eaf8f1}.status.cancelado,.status.cancelada{color:#a73535;background:#fff0f0}.view-link{display:inline-flex;padding:6px 9px;border:1px solid #cfe0ef;border-radius:7px;color:var(--primary);text-decoration:none;font-size:10px;font-weight:850}.empty{padding:25px;color:var(--muted);text-align:center}.detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:13px}.detail-item{padding:10px;border:1px solid var(--border);border-radius:9px;background:#f9fbfd}.detail-item span{display:block;color:var(--muted);font-size:9px}.detail-item strong{display:block;margin-top:3px;color:var(--navy);font-size:12px}.case-list{display:grid;gap:7px;margin-top:14px}.case{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr) auto;gap:10px;align-items:center;padding:11px;border:1px solid var(--border);border-radius:10px;background:#fff}.case strong,.case span{display:block}.case small{color:var(--muted)}@media(max-width:760px){.topbar,.card-head{align-items:stretch;flex-direction:column}.tabs{overflow:auto}.grid-stats,.detail-grid{grid-template-columns:1fr 1fr}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.case{grid-template-columns:1fr}.shell{width:calc(100% - 12px)}}
    </style>
    <style>
        .rating-card{margin-top:15px;padding:15px;border:1px solid color-mix(in srgb,var(--primary) 28%,#dce6f0);border-radius:12px;background:color-mix(in srgb,var(--primary) 5%,#fff)}
        .rating-card h3{margin:0;color:var(--navy);font-size:16px}.rating-card>p{margin:5px 0 12px;color:var(--muted)}.solution-box{margin-bottom:12px;padding:11px;border:1px solid var(--border);border-radius:9px;background:#fff}.solution-box span{display:block;color:var(--muted);font-size:9px;text-transform:uppercase}.solution-box strong{display:block;margin-top:3px;color:var(--navy)}.solution-box p{margin:7px 0 0;white-space:pre-line}.rating-grid{display:grid;grid-template-columns:1fr 1fr;gap:11px}.rating-grid .field:last-child{grid-column:1/-1}.rating-grid textarea{min-height:82px}.reopen-form{margin-top:13px;padding-top:13px;border-top:1px solid var(--border)}.reopen-form .reopen-title{display:block;color:var(--danger);font-weight:850}.reopen-form p{margin:3px 0 9px;color:var(--muted)}.btn.reopen{border:1px solid #efc4c1;color:var(--danger);background:#fff4f3}@media(max-width:620px){.rating-grid{grid-template-columns:1fr}.rating-grid .field:last-child{grid-column:auto}}
    </style>
    <style>
        .catalog-picker{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px}.catalog-choice{position:relative;display:block;min-width:0;cursor:pointer}.catalog-choice input{position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none}.catalog-choice-body{display:flex;min-height:72px;align-items:center;gap:10px;padding:10px;border:1px solid #cfdeeb;border-radius:11px;background:#fff;box-shadow:0 5px 14px rgba(16,42,67,.04);transition:border-color .16s ease,box-shadow .16s ease,transform .16s ease}.catalog-choice:hover .catalog-choice-body{transform:translateY(-1px);border-color:color-mix(in srgb,var(--primary) 45%,#cfdeeb);box-shadow:0 8px 18px rgba(16,42,67,.08)}.catalog-choice input:focus-visible+.catalog-choice-body{outline:3px solid color-mix(in srgb,var(--primary) 20%,transparent);outline-offset:2px}.catalog-choice input:checked+.catalog-choice-body{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,#fff);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 13%,transparent)}.catalog-choice img{width:40px;height:40px;display:block;flex:0 0 auto;border:1px solid #e0e9f2;border-radius:10px;object-fit:cover;background:#f6f9fc}.catalog-choice-text{min-width:0;display:block}.catalog-choice strong{display:block;overflow:hidden;color:var(--navy);font-size:12px;text-overflow:ellipsis;white-space:nowrap}.catalog-choice small{display:block;margin-top:2px;color:var(--muted);font-size:9px}.service-feedback{display:block;min-height:15px;margin-top:1px;color:var(--muted);font-size:10px}.service-feedback.warning{color:var(--warn)}@media(max-width:620px){.catalog-picker{grid-template-columns:1fr 1fr}.catalog-choice-body{min-height:64px;padding:8px}.catalog-choice img{width:34px;height:34px}}@media(max-width:410px){.catalog-picker{grid-template-columns:1fr}}
    </style>
    <style>
        .service-selection-layout{grid-column:1/-1;display:grid;grid-template-columns:minmax(0,.92fr) minmax(330px,1.08fr);gap:13px;align-items:stretch}.service-selection-layout>.field{align-content:start}.service-summary{min-height:132px;display:grid;grid-template-columns:42px minmax(0,1fr);gap:11px;padding:14px;border:1px solid #cfe0ef;border-radius:12px;background:linear-gradient(145deg,#f8fbff,#eef6ff);box-shadow:0 8px 20px rgba(16,42,67,.05)}.service-summary.empty{align-items:center;border-style:dashed;background:#f9fbfd}.service-summary-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:11px;color:#fff;background:linear-gradient(145deg,var(--primary),var(--primary-dark));font-size:10px;font-weight:950;letter-spacing:.04em}.service-summary-copy{min-width:0}.service-summary-kicker{display:block;color:#3972a7;font-size:8px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.service-summary strong{display:block;margin-top:2px;color:var(--navy);font-size:14px}.service-summary p{margin:5px 0 9px;color:#526f88;font-size:10px;line-height:1.45}.service-tags{display:flex;flex-wrap:wrap;gap:5px}.service-tag{display:inline-flex;align-items:center;gap:4px;min-height:23px;padding:4px 8px;border:1px solid #cfe0ef;border-radius:999px;color:#255a83;background:#fff;font-size:8px;font-weight:850}.service-tag.type{color:#6542a6;border-color:#ddd0f5;background:#f7f2ff}.service-tag.priority{color:#9a4c00;border-color:#f0d5ae;background:#fff8eb}.service-tag.urgency{color:#a73535;border-color:#efc8c8;background:#fff4f4}.request-support-grid{grid-column:1/-1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;align-items:start}.request-text-column{min-width:0;display:grid;gap:13px;align-content:start}.description-field textarea{height:64px;min-height:64px;max-height:360px;resize:none;overflow-y:hidden;line-height:1.45}.description-field textarea.is-scrollable{overflow-y:auto}.file-field{min-width:0;margin-top:0}.file-input{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0!important}.file-dropzone{min-height:64px;display:flex;align-items:center;gap:10px;padding:9px 11px;border:1px dashed #a9c6e2;border-radius:10px;color:#456985;background:#f7faff;text-align:left;cursor:pointer;transition:.18s ease}.file-dropzone:hover,.file-dropzone.dragover{border-color:var(--primary);background:#edf5ff;box-shadow:0 0 0 3px rgba(15,111,236,.07)}.upload-icon{width:34px;height:34px;display:grid;flex:0 0 auto;place-items:center;border:1px solid #d8e7f5;border-radius:9px;color:var(--primary);background:#fff}.upload-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.upload-copy{min-width:0;flex:1}.upload-copy strong{display:block;color:var(--navy);font-size:11px}.upload-copy span{display:block;margin-top:1px;color:var(--muted);font-size:9px}.upload-action{display:inline-flex;min-height:31px;align-items:center;justify-content:center;padding:7px 11px;border-radius:8px;color:#fff;background:var(--primary);font-size:9px;font-weight:850;white-space:nowrap;box-shadow:0 4px 10px rgba(15,111,236,.16)}.file-dropzone:hover .upload-action,.file-dropzone.dragover .upload-action{background:var(--primary-dark)}.file-list{display:grid;gap:6px;margin-top:7px}.file-list[hidden]{display:none}.file-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:7px 9px;border:1px solid #dbe6f0;border-radius:9px;background:#fff}.file-item strong{display:block;overflow:hidden;color:var(--navy);font-size:10px;text-overflow:ellipsis;white-space:nowrap}.file-item small{display:block;margin-top:1px;color:var(--muted);font-size:8px}.file-remove{width:27px;height:27px;display:grid;place-items:center;border:1px solid #efd0d0;border-radius:8px;color:#a73535;background:#fff6f6;cursor:pointer}.file-status{display:block;min-height:14px;margin-top:4px;color:var(--muted);font-size:8px}.file-status.error{color:var(--danger)}.detail-grid{grid-template-columns:repeat(auto-fit,minmax(145px,1fr))}@media(max-width:760px){.service-selection-layout,.request-support-grid{grid-template-columns:1fr}.service-summary{min-height:118px}.file-dropzone{min-height:60px}}@media(max-width:480px){.file-dropzone{align-items:flex-start;flex-wrap:wrap}.upload-copy{padding-top:1px}.upload-action{width:100%;min-height:30px}.file-status{line-height:1.35}}
    </style>
    <style>
        .service-summary{grid-template-columns:minmax(0,1fr)}
        .service-summary.empty{place-items:center;text-align:center}
        .service-summary.empty .service-summary-copy{display:grid;place-items:center}
        .service-summary.empty strong{margin:0;max-width:390px;color:var(--muted);font-size:11px;font-weight:700;line-height:1.5}
        .service-summary [hidden]{display:none!important}
    </style>
    <style>
        .flow-chat{margin-top:15px;padding:14px;border:1px solid #cfe0ef;border-radius:12px;background:#f9fbfe}.flow-chat-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px}.flow-chat-head h3{margin:0;color:var(--navy);font-size:16px}.flow-chat-head p{margin:3px 0 0;color:var(--muted);font-size:11px}.flow-chat-rule{display:inline-flex;padding:4px 8px;border-radius:999px;color:#0b5aa7;background:#e7f2ff;font-size:9px;font-weight:850;white-space:nowrap}.flow-chat-tabs{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:7px;margin-bottom:10px}.flow-chat-tab{display:block;padding:9px;border:1px solid var(--border);border-radius:9px;color:var(--text);background:#fff;text-decoration:none}.flow-chat-tab:hover,.flow-chat-tab.active{border-color:var(--primary);background:#edf5ff;box-shadow:0 0 0 1px color-mix(in srgb,var(--primary) 18%,transparent)}.flow-chat-tab strong,.flow-chat-tab span{display:block}.flow-chat-tab strong{color:var(--navy);font-size:11px}.flow-chat-tab span{margin-top:2px;color:var(--muted);font-size:9px}.flow-chat-current{padding:10px;border:1px solid var(--border);border-radius:9px;background:#fff}.flow-chat-current>strong{display:block;color:var(--navy)}.flow-chat-current>span{display:block;margin-top:2px;color:var(--muted);font-size:10px}.flow-chat-messages{display:grid;gap:8px;max-height:310px;overflow:auto;margin-top:9px;padding:10px;border:1px solid #e0e8f0;border-radius:9px;background:#f6f9fc}.flow-message{max-width:82%;padding:9px 10px;border:1px solid #d7e3ed;border-radius:10px;background:#fff}.flow-message.mine{justify-self:end;border-color:#9fc8f5;background:#eaf4ff}.flow-message strong{display:block;color:var(--navy);font-size:10px}.flow-message p{margin:3px 0;white-space:pre-wrap;overflow-wrap:anywhere}.flow-message small{color:var(--muted);font-size:8px}.flow-chat-files{display:grid;gap:6px;margin-top:9px}.flow-chat-file{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px;border:1px solid var(--border);border-radius:8px;background:#fff}.flow-chat-file span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.flow-chat-file a{flex:0 0 auto;color:var(--primary);font-size:10px;font-weight:850;text-decoration:none}.flow-compose{display:grid;grid-template-columns:minmax(0,1fr) minmax(190px,.45fr) auto;gap:8px;align-items:end;margin-top:10px}.flow-compose .field textarea{min-height:72px}.flow-compose .btn{height:40px}.flow-chat-readonly{margin:9px 0 0;padding:8px;border-radius:8px;color:var(--muted);background:#edf2f7;font-size:10px}@media(max-width:760px){.flow-chat-head{display:grid}.flow-chat-rule{justify-self:start}.flow-compose{grid-template-columns:1fr}.flow-message{max-width:94%}}
    </style>
    <style>
        [hidden]{display:none!important}
        .case-filters{display:grid;grid-template-columns:minmax(240px,1fr) minmax(170px,.32fr) auto;gap:10px;align-items:end;margin:0 0 13px;padding:11px;border:1px solid #dbe7f2;border-radius:11px;background:#f8fbff}
        .case-filter-field{display:grid;gap:5px}.case-filter-field label{color:var(--navy);font-size:10px;font-weight:850}.case-filter-field input,.case-filter-field select{width:100%;height:38px;padding:8px 10px;border:1px solid #cbdbea;border-radius:9px;color:var(--text);background:#fff;outline:none}.case-filter-field input:focus,.case-filter-field select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(15,111,236,.11)}
        .case-search-control{position:relative}.case-search-control input{padding-right:38px}.case-filter-clear{position:absolute;top:5px;right:5px;width:28px;height:28px;display:grid;place-items:center;border:0;border-radius:7px;color:#617b91;background:#eef4fa;cursor:pointer;font-size:17px;line-height:1}.case-filter-clear:hover{color:var(--navy);background:#e1ebf5}
        .case-filter-count{min-width:104px;padding:10px 11px;color:#486985;text-align:center;font-size:10px;font-weight:850;white-space:nowrap}.case-filter-empty td{padding:28px;text-align:center;color:var(--muted)}
        body.case-modal-open{overflow:hidden}.case-detail-modal{position:fixed;inset:0;z-index:2147483647;width:100vw;height:100vh;height:100dvh;display:grid;place-items:center;padding:22px;background:rgba(7,25,45,.64);backdrop-filter:blur(4px);isolation:isolate}.case-detail-dialog{width:min(1080px,100%);max-height:calc(100vh - 44px);max-height:calc(100dvh - 44px);display:flex;flex-direction:column;overflow:hidden;border:1px solid #cbdbea;border-radius:16px;background:#fff;box-shadow:0 28px 80px rgba(4,25,48,.3)}.case-detail-modal-bar{position:sticky;top:0;z-index:2;display:flex;flex:0 0 auto;align-items:center;justify-content:space-between;gap:12px;padding:11px 14px;border-bottom:1px solid var(--border);background:#f7faff}.case-detail-modal-bar strong{color:var(--navy);font-size:13px}.case-detail-close{display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:7px 10px;border:1px solid #cbdbea;border-radius:9px;color:#315b7e;background:#fff;font:inherit;font-weight:850;cursor:pointer}.case-detail-close:hover{border-color:#a9c5df;color:var(--primary);background:#f2f7fc}.case-detail-close span{font-size:18px;line-height:1}.case-detail-scroll{min-height:0;overflow-y:auto;overscroll-behavior:contain}.case-detail-card{margin:0;border:0;border-radius:0;box-shadow:none}.case-detail-card>.card-head{padding-right:2px}.case-detail-card #conversacion-solicitante{scroll-margin-top:12px}
        @media(max-width:760px){.case-filters{grid-template-columns:1fr}.case-filter-count{text-align:left;padding:0 2px}.case-detail-modal{padding:8px}.case-detail-dialog{max-height:calc(100vh - 16px);border-radius:12px}.case-detail-modal-bar{padding:9px 10px}.case-detail-close{padding:7px}.case-detail-close-label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.case-detail-card{padding:13px}}
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="brand">
            <span class="mark">MS</span>
            <div>
                <strong>Mesa de Servicio · <?= escaparPortalSolicitante(paisContextoNombre()) ?></strong>
                <small>Solicitante · <?= escaparPortalSolicitante($nombreUsuario) ?></small>
            </div>
        </div>
    </header>

    <nav class="tabs" aria-label="Opciones del solicitante">
        <a class="tab <?= $vista === 'nueva' ? 'active' : '' ?>" href="panelSolicitante.php?vista=nueva">＋ Crear caso</a>
        <a class="tab <?= $vista === 'tickets' ? 'active' : '' ?>" href="panelSolicitante.php?vista=tickets">Mis solicitudes · <?= $resumen['total'] ?></a>
    </nav>

    <?php if (isset($mensajes[$mensajeActual])): ?>
        <div class="alert <?= escaparPortalSolicitante($mensajes[$mensajeActual][0]) ?>">
            <?= escaparPortalSolicitante($mensajes[$mensajeActual][1]) ?>
            <?php if (in_array($mensajeActual, ['error_operacion', 'error_calificacion', 'error_reapertura', 'error_mensaje'], true) && $detalleError !== ''): ?>
                <?= escaparPortalSolicitante($detalleError) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($vista === 'nueva'): ?>
        <section class="card">
            <div class="card-head">
                <div>
                    <h1>Crear una solicitud</h1>
                    <p class="muted">Elija primero el catálogo y después el servicio que necesita.</p>
                </div>
            </div>

            <?php if (!$catalogosSolicitud): ?>
                <div class="empty">No hay catálogos disponibles en este momento.</div>
            <?php else: ?>
                <form id="form-nueva-solicitud" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= escaparPortalSolicitante($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="crear_ticket">
                    <div class="form-grid">
                        <div class="field full">
                            <label id="etiqueta-catalogo">Catálogo *</label>
                            <div class="catalog-picker" role="radiogroup" aria-labelledby="etiqueta-catalogo">
                                <?php foreach ($catalogosSolicitud as $catalogoSolicitud): ?>
                                    <?php
                                        $idCatalogoSolicitud = (int) $catalogoSolicitud['id_catalogo'];
                                        $totalServiciosCatalogo = (int) ($cantidadServiciosCatalogo[$idCatalogoSolicitud] ?? 0);
                                    ?>
                                    <label class="catalog-choice">
                                        <input
                                            type="radio"
                                            name="id_catalogo"
                                            value="<?= $idCatalogoSolicitud ?>"
                                            required
                                            data-catalogo-solicitud
                                        >
                                        <span class="catalog-choice-body">
                                            <img
                                                src="<?= escaparPortalSolicitante(seguridadUrlImagenCatalogo($idCatalogoSolicitud, $catalogoSolicitud['imagen'] ?? null)) ?>"
                                                alt=""
                                            >
                                            <span class="catalog-choice-text">
                                                <strong><?= escaparPortalSolicitante($catalogoSolicitud['nombre']) ?></strong>
                                                <small>
                                                    <?= $totalServiciosCatalogo ?>
                                                    <?= $totalServiciosCatalogo === 1 ? 'servicio disponible' : 'servicios disponibles' ?>
                                                </small>
                                            </span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="service-selection-layout">
                            <div class="field">
                                <label for="id_proceso">Servicio *</label>
                                <select id="id_proceso" name="id_proceso" required disabled>
                                    <option value="">Seleccione primero un catálogo</option>
                                </select>
                                <span id="estado-servicios-catalogo" class="service-feedback" aria-live="polite">Los servicios se cargarán según el catálogo seleccionado.</span>
                            </div>
                            <aside id="resumen-servicio" class="service-summary empty" aria-live="polite">
                                <div class="service-summary-copy">
                                    <strong id="resumen-servicio-nombre">Seleccione un servicio para que aparezca la información de él en este apartado.</strong>
                                    <p id="resumen-servicio-descripcion" hidden></p>
                                    <div class="service-tags" id="resumen-servicio-etiquetas" hidden>
                                        <span class="service-tag type" id="resumen-servicio-tipo"></span>
                                        <span class="service-tag priority" id="resumen-servicio-prioridad"></span>
                                        <span class="service-tag urgency" id="resumen-servicio-urgencia"></span>
                                    </div>
                                </div>
                            </aside>
                        </div>
                        <div class="request-support-grid">
                            <div class="request-text-column">
                                <div class="field">
                                    <label for="titulo">Asunto *</label>
                                    <input id="titulo" name="titulo" maxlength="180" required placeholder="Ejemplo: No puedo ingresar al correo corporativo">
                                </div>
                                <div class="field description-field">
                                    <label for="descripcion">Descripción *</label>
                                    <textarea id="descripcion" name="descripcion" maxlength="15000" rows="2" required placeholder="Explique qué sucede y desde cuándo."></textarea>
                                </div>
                            </div>
                            <div class="field file-field">
                                <label for="adjuntos">Archivos opcionales</label>
                                <input class="file-input" id="adjuntos" type="file" name="adjuntos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx">
                                <label class="file-dropzone" id="zona-adjuntos" for="adjuntos">
                                    <span class="upload-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>
                                    </span>
                                    <span class="upload-copy">
                                        <strong>Adjuntar archivos</strong>
                                        <span>Arrástrelos aquí o búsquelos en su equipo.</span>
                                    </span>
                                    <span class="upload-action">Seleccionar</span>
                                </label>
                                <div class="file-list" id="lista-adjuntos" hidden></div>
                                <span class="file-status" id="estado-adjuntos">Máximo cinco archivos de 5 MB cada uno.</span>
                            </div>
                        </div>
                    </div>
                    <div class="actions"><button class="btn primary" type="submit">Crear solicitud</button></div>
                </form>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="grid-stats" aria-label="Resumen de solicitudes">
            <article class="stat"><span>Total</span><strong><?= $resumen['total'] ?></strong></article>
            <article class="stat"><span>En gestión</span><strong><?= $resumen['activos'] ?></strong></article>
            <article class="stat"><span>Solución lista</span><strong><?= $resumen['listos'] ?></strong></article>
            <article class="stat"><span>Cerrados</span><strong><?= $resumen['cerrados'] ?></strong></article>
        </section>

        <section class="card">
            <div class="card-head"><div><h1>Estado de mis solicitudes</h1><p class="muted">Aquí solo aparecen los casos creados por su usuario en <?= escaparPortalSolicitante(paisContextoNombre()) ?>.</p></div></div>
            <?php if (!$tickets): ?>
                <div class="empty">Todavía no ha creado solicitudes.</div>
            <?php else: ?>
                <div class="case-filters" aria-label="Filtros de casos">
                    <div class="case-filter-field">
                        <label for="buscar-casos">Buscar casos</label>
                        <div class="case-search-control">
                            <input
                                id="buscar-casos"
                                type="search"
                                value="<?= escaparPortalSolicitante($busquedaCasos) ?>"
                                maxlength="120"
                                autocomplete="off"
                                placeholder="Número, asunto, servicio, estado o fecha"
                            >
                            <button class="case-filter-clear" id="limpiar-filtro-casos" type="button" aria-label="Limpiar búsqueda" hidden>×</button>
                        </div>
                    </div>
                    <div class="case-filter-field">
                        <label for="estado-casos">Estado</label>
                        <select id="estado-casos">
                            <option value="todos" <?= $estadoBusquedaCasos === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                            <option value="abierto" <?= $estadoBusquedaCasos === 'abierto' ? 'selected' : '' ?>>Abiertos</option>
                            <option value="en_proceso" <?= $estadoBusquedaCasos === 'en_proceso' ? 'selected' : '' ?>>En gestión</option>
                            <option value="pendiente_calificacion" <?= $estadoBusquedaCasos === 'pendiente_calificacion' ? 'selected' : '' ?>>Solución lista</option>
                            <option value="cerrado" <?= $estadoBusquedaCasos === 'cerrado' ? 'selected' : '' ?>>Cerrados</option>
                            <option value="cancelado" <?= $estadoBusquedaCasos === 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                        </select>
                    </div>
                    <span class="case-filter-count" id="contador-casos" aria-live="polite"><?= count($tickets) ?> caso(s)</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Caso</th><th>Asunto</th><th>Servicio</th><th>Estado</th><th>Creado</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                                $estadoTicket = isset($ticketsConSolucionLista[(int) $ticket['id_ticket']])
                                    ? 'pendiente_calificacion'
                                    : (string) ($ticket['estado_flujo'] ?? $ticket['estado'] ?? 'en_proceso');
                                $estadoFiltroTicket = match ($estadoTicket) {
                                    'cerrada' => 'cerrado',
                                    'cancelada' => 'cancelado',
                                    default => $estadoTicket,
                                };
                                $parametrosDetalle = [
                                    'vista' => 'tickets',
                                    'id_ticket' => (int) $ticket['id_ticket'],
                                ];

                                if ($busquedaCasos !== '') {
                                    $parametrosDetalle['buscar'] = $busquedaCasos;
                                }

                                if ($estadoBusquedaCasos !== 'todos') {
                                    $parametrosDetalle['estado_busqueda'] = $estadoBusquedaCasos;
                                }
                            ?>
                            <tr data-case-row data-case-state="<?= escaparPortalSolicitante($estadoFiltroTicket) ?>">
                                <td><strong>Caso <?= (int) $ticket['id_ticket'] ?></strong></td>
                                <td><?= escaparPortalSolicitante($ticket['titulo']) ?></td>
                                <td><?= escaparPortalSolicitante($ticket['proceso_nombre'] ?? 'Solicitud') ?></td>
                                <td><span class="status <?= escaparPortalSolicitante($estadoTicket) ?>"><?= escaparPortalSolicitante(textoEstadoPortalSolicitante($estadoTicket)) ?></span></td>
                                <td><?= escaparPortalSolicitante(fechaPortalSolicitante($ticket['fecha_creacion'] ?? null)) ?></td>
                                <td><a class="view-link" data-case-view href="panelSolicitante.php?<?= escaparPortalSolicitante(http_build_query($parametrosDetalle)) ?>">Ver estado</a></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="case-filter-empty" id="sin-resultados-casos" hidden>
                                <td colspan="6">No se encontraron casos con los filtros seleccionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($ticketSeleccionado): ?>
            <div class="case-detail-modal" id="modal-estado-caso" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-estado-caso">
                <div class="case-detail-dialog" role="document">
                    <div class="case-detail-modal-bar">
                        <strong>Detalle y seguimiento del caso</strong>
                        <button class="case-detail-close" type="button" data-case-modal-close>
                            <span aria-hidden="true">×</span>
                            <span class="case-detail-close-label">Cerrar</span>
                        </button>
                    </div>
                    <div class="case-detail-scroll">
            <section class="card case-detail-card">
                <div class="card-head">
                    <div><h2 id="titulo-modal-estado-caso">Caso <?= (int) $ticketSeleccionado['id_ticket'] ?> · <?= escaparPortalSolicitante($ticketSeleccionado['titulo']) ?></h2><p class="muted">El número del caso se conserva durante todas las etapas del flujo.</p></div>
                    <?php
                        $estadoSeleccionado = $etapaPendienteCalificacion
                            ? 'pendiente_calificacion'
                            : (string) ($ticketSeleccionado['estado_flujo'] ?? 'en_proceso');
                    ?>
                    <span class="status <?= escaparPortalSolicitante($estadoSeleccionado) ?>"><?= escaparPortalSolicitante(textoEstadoPortalSolicitante($estadoSeleccionado)) ?></span>
                </div>
                <div class="detail-grid">
                    <div class="detail-item"><span>Proceso</span><strong><?= escaparPortalSolicitante($ticketSeleccionado['proceso_nombre'] ?? 'Solicitud') ?></strong></div>
                    <div class="detail-item"><span>Clasificación</span><strong><?= escaparPortalSolicitante(ucfirst((string) ($ticketSeleccionado['tipo_solicitud'] ?? 'Requerimiento'))) ?></strong></div>
                    <div class="detail-item"><span>Prioridad</span><strong><?= escaparPortalSolicitante(ucfirst((string) ($ticketSeleccionado['prioridad'] ?? 'Media'))) ?></strong></div>
                    <div class="detail-item"><span>Urgencia</span><strong><?= escaparPortalSolicitante(ucfirst((string) $ticketSeleccionado['urgencia'])) ?></strong></div>
                    <div class="detail-item"><span>Fecha de creación</span><strong><?= escaparPortalSolicitante(fechaPortalSolicitante($ticketSeleccionado['fecha_creacion'] ?? null)) ?></strong></div>
                    <div class="detail-item"><span>Estado general</span><strong><?= escaparPortalSolicitante(textoEstadoPortalSolicitante($estadoSeleccionado)) ?></strong></div>
                </div>
                <div class="case-list">
                    <?php foreach ($etapasTicket as $indiceEtapa => $etapa): ?>
                        <?php $estadoCaso = (string) ($etapa['estado'] ?? 'pendiente'); ?>
                        <article class="case">
                            <div><strong>Etapa <?= $indiceEtapa + 1 ?> · <?= escaparPortalSolicitante($etapa['catalogo_nombre'] ?? 'Área') ?></strong><small><?= escaparPortalSolicitante($etapa['servicio_nombre'] ?? 'Servicio') ?></small></div>
                            <div><span>Gestor asignado</span><small><?= escaparPortalSolicitante($etapa['gestor_nombre'] ?? 'Pendiente') ?></small></div>
                            <span class="status <?= escaparPortalSolicitante($estadoCaso) ?>"><?= escaparPortalSolicitante(textoEstadoCasoSolicitante($estadoCaso)) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>

                <section class="flow-chat" id="conversacion-solicitante">
                    <div class="flow-chat-head">
                        <div>
                            <h3>Conversación con el gestor del flujo</h3>
                            <p>Cada etapa oficial tiene un chat independiente. Las derivaciones internas entre gestores no aparecen en este portal.</p>
                        </div>
                        <span class="flow-chat-rule">Solo etapas del flujo</span>
                    </div>

                    <?php if (!$conversacionesSolicitante): ?>
                        <div class="empty">La primera conversación se habilitará cuando el área inicie la atención.</div>
                    <?php else: ?>
                        <nav class="flow-chat-tabs" aria-label="Conversaciones por etapa del flujo">
                            <?php foreach ($conversacionesSolicitante as $indiceConversacion => $conversacionSolicitante): ?>
                                <?php
                                    $idConversacion = (int) $conversacionSolicitante['id_ticket_etapa'];
                                    $esConversacionActual = $conversacionSolicitanteActual
                                        && (int) $conversacionSolicitanteActual['id_ticket_etapa'] === $idConversacion;
                                    $parametrosConversacion = [
                                        'vista' => 'tickets',
                                        'id_ticket' => $idTicketSeleccionado,
                                        'id_chat' => $idConversacion,
                                    ];

                                    if ($busquedaCasos !== '') {
                                        $parametrosConversacion['buscar'] = $busquedaCasos;
                                    }

                                    if ($estadoBusquedaCasos !== 'todos') {
                                        $parametrosConversacion['estado_busqueda'] = $estadoBusquedaCasos;
                                    }

                                    $urlConversacion = 'panelSolicitante.php?'
                                        . http_build_query($parametrosConversacion)
                                        . '#conversacion-solicitante';
                                ?>
                                <a class="flow-chat-tab <?= $esConversacionActual ? 'active' : '' ?>" href="<?= escaparPortalSolicitante($urlConversacion) ?>">
                                    <strong>Etapa <?= $indiceConversacion + 1 ?> · <?= escaparPortalSolicitante($conversacionSolicitante['catalogo_nombre']) ?></strong>
                                    <span><?= escaparPortalSolicitante($conversacionSolicitante['servicio_nombre']) ?></span>
                                    <span><?= escaparPortalSolicitante(textoEstadoCasoSolicitante((string) $conversacionSolicitante['estado'])) ?> · <?= (int) $conversacionSolicitante['total_mensajes'] ?> mensaje(s)</span>
                                </a>
                            <?php endforeach; ?>
                        </nav>

                        <?php if ($conversacionSolicitanteActual): ?>
                            <div class="flow-chat-current">
                                <strong><?= escaparPortalSolicitante($conversacionSolicitanteActual['catalogo_nombre']) ?> · <?= escaparPortalSolicitante($conversacionSolicitanteActual['servicio_nombre']) ?></strong>
                                <span>Gestor asignado: <?= escaparPortalSolicitante($conversacionSolicitanteActual['gestor_destino'] ?: 'Pendiente de asignación') ?></span>
                            </div>

                            <div class="flow-chat-messages" aria-live="polite">
                                <?php if (!$comunicacionesSolicitante): ?>
                                    <p class="muted">Todavía no hay mensajes en esta etapa.</p>
                                <?php endif; ?>
                                <?php foreach ($comunicacionesSolicitante as $mensajeConversacion): ?>
                                    <?php $esMensajePropio = (int) $mensajeConversacion['id_emisor'] === $idUsuario; ?>
                                    <article class="flow-message <?= $esMensajePropio ? 'mine' : '' ?>">
                                        <strong><?= escaparPortalSolicitante($mensajeConversacion['emisor'] ?: 'Usuario') ?></strong>
                                        <p><?= escaparPortalSolicitante($mensajeConversacion['mensaje']) ?></p>
                                        <small><?= escaparPortalSolicitante(fechaPortalSolicitante($mensajeConversacion['creado_en'])) ?></small>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($adjuntosSolicitante): ?>
                                <div class="flow-chat-files" aria-label="Archivos de esta etapa">
                                    <?php foreach ($adjuntosSolicitante as $archivoConversacion): ?>
                                        <div class="flow-chat-file">
                                            <span><?= escaparPortalSolicitante($archivoConversacion['nombre_original']) ?> · <?= escaparPortalSolicitante($archivoConversacion['usuario'] ?: 'Usuario') ?></span>
                                            <a href="descargarAdjunto.php?id=<?= (int) $archivoConversacion['id_adjunto'] ?>">Descargar</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($puedeEscribirSolicitante): ?>
                                <form class="flow-compose" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= escaparPortalSolicitante($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="accion" value="enviar_mensaje">
                                    <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                                    <input type="hidden" name="id_ticket_etapa" value="<?= (int) $conversacionSolicitanteActual['id_ticket_etapa'] ?>">
                                    <div class="field">
                                        <label for="mensaje_solicitante">Mensaje</label>
                                        <textarea id="mensaje_solicitante" name="mensaje" maxlength="10000" placeholder="Escriba al gestor responsable de esta etapa"></textarea>
                                    </div>
                                    <div class="field">
                                        <label for="adjuntos_solicitante">Adjuntos opcionales</label>
                                        <input id="adjuntos_solicitante" type="file" name="adjuntos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx">
                                    </div>
                                    <button class="btn primary" type="submit">Enviar</button>
                                </form>
                            <?php else: ?>
                                <p class="flow-chat-readonly">Este chat está cerrado y permanece disponible únicamente para consulta. Cuando el flujo avance, se habilitará una conversación nueva con la siguiente área.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <?php if ($etapaPendienteCalificacion): ?>
                    <div class="rating-card">
                        <h3>La solución está lista para su aprobación</h3>
                        <p>Para aprobar esta etapa del mismo caso, califique la gestión del área y el tiempo de respuesta.</p>
                        <div class="solution-box">
                            <span>Solución aplicada</span>
                            <strong><?= escaparPortalSolicitante($etapaPendienteCalificacion['solucion_nombre'] ?? 'Solución registrada') ?></strong>
                            <?php if (trim((string) ($etapaPendienteCalificacion['comentario_cierre'] ?? '')) !== ''): ?>
                                <p><?= escaparPortalSolicitante($etapaPendienteCalificacion['comentario_cierre']) ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="post" onsubmit="return confirm('¿Confirma la calificación y la aprobación de esta etapa?')">
                            <input type="hidden" name="csrf_token" value="<?= escaparPortalSolicitante($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="accion" value="calificar_cerrar">
                            <input type="hidden" name="id_ticket" value="<?= (int) $ticketSeleccionado['id_ticket'] ?>">
                            <input type="hidden" name="id_ticket_etapa" value="<?= (int) $etapaPendienteCalificacion['id_ticket_etapa'] ?>">
                            <div class="rating-grid">
                                <div class="field">
                                    <label for="calificacion_area">Gestión del área *</label>
                                    <select id="calificacion_area" name="calificacion_area" required>
                                        <option value="">Seleccione</option>
                                        <?php for ($nota = 5; $nota >= 1; $nota--): ?>
                                            <option value="<?= $nota ?>"><?= $nota ?> - <?= ['','Muy deficiente','Deficiente','Aceptable','Muy buena','Excelente'][$nota] ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="calificacion_tiempo">Tiempo de respuesta *</label>
                                    <select id="calificacion_tiempo" name="calificacion_tiempo" required>
                                        <option value="">Seleccione</option>
                                        <?php for ($nota = 5; $nota >= 1; $nota--): ?>
                                            <option value="<?= $nota ?>"><?= $nota ?> - <?= ['','Muy deficiente','Deficiente','Aceptable','Muy buena','Excelente'][$nota] ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="comentario_calificacion">Comentario opcional</label>
                                    <textarea id="comentario_calificacion" name="comentario_calificacion" maxlength="1000" placeholder="Cuéntenos qué estuvo bien o qué debe mejorar."></textarea>
                                </div>
                            </div>
                            <div class="actions"><button class="btn primary" type="submit">Calificar y aprobar etapa</button></div>
                        </form>
                        <form class="reopen-form" method="post" onsubmit="return confirm('¿Desea devolver esta etapa? El mismo caso volverá al gestor asignado y el SLA se reanudará.')">
                            <input type="hidden" name="csrf_token" value="<?= escaparPortalSolicitante($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="accion" value="reabrir_caso">
                            <input type="hidden" name="id_ticket" value="<?= (int) $ticketSeleccionado['id_ticket'] ?>">
                            <input type="hidden" name="id_ticket_etapa" value="<?= (int) $etapaPendienteCalificacion['id_ticket_etapa'] ?>">
                            <span class="reopen-title">¿La solución no resolvió su solicitud?</span>
                            <p>Explique el motivo para devolver esta etapa del caso al gestor asignado.</p>
                            <div class="field">
                                <label for="motivo_reapertura">Motivo de reapertura *</label>
                                <textarea id="motivo_reapertura" name="motivo_reapertura" maxlength="1000" required placeholder="Indique qué continúa fallando o por qué la solución no fue suficiente."></textarea>
                            </div>
                            <div class="actions"><button class="btn reopen" type="submit">Devolver etapa</button></div>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script type="application/json" id="datos-servicios-solicitud"><?=
    json_encode(
        array_map(
            static fn (array $servicio): array => [
                'id_catalogo' => (int) ($servicio['id_catalogo'] ?? 0),
                'id_proceso' => (int) ($servicio['id_proceso'] ?? 0),
                'nombre' => (string) ($servicio['servicio_nombre'] ?? 'Servicio'),
                'descripcion' => (string) ($servicio['servicio_descripcion'] ?? ''),
                'tipo_solicitud' => (string) ($servicio['tipo_solicitud'] ?? 'requerimiento'),
                'prioridad' => (string) ($servicio['prioridad_nombre'] ?? 'Media'),
                'urgencia' => (string) ($servicio['urgencia_nombre'] ?? 'Moderada'),
                'sla' => (string) ($servicio['sla_nombre'] ?? ''),
            ],
            $servicios
        ),
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    )
?></script>
<script>
    (function () {
        'use strict';

        var selectorServicio = document.getElementById('id_proceso');
        var datosElemento = document.getElementById('datos-servicios-solicitud');
        var estadoServicios = document.getElementById('estado-servicios-catalogo');
        var catalogos = document.querySelectorAll('[data-catalogo-solicitud]');
        var resumenServicio = document.getElementById('resumen-servicio');
        var resumenNombre = document.getElementById('resumen-servicio-nombre');
        var resumenDescripcion = document.getElementById('resumen-servicio-descripcion');
        var resumenEtiquetas = document.getElementById('resumen-servicio-etiquetas');
        var resumenTipo = document.getElementById('resumen-servicio-tipo');
        var resumenPrioridad = document.getElementById('resumen-servicio-prioridad');
        var resumenUrgencia = document.getElementById('resumen-servicio-urgencia');
        var entradaDescripcion = document.getElementById('descripcion');
        var entradaAdjuntos = document.getElementById('adjuntos');
        var zonaAdjuntos = document.getElementById('zona-adjuntos');
        var listaAdjuntos = document.getElementById('lista-adjuntos');
        var estadoAdjuntos = document.getElementById('estado-adjuntos');
        var servicios = [];
        var archivosSeleccionados = [];

        if (!selectorServicio || !datosElemento || !estadoServicios || !catalogos.length) {
            return;
        }

        try {
            servicios = JSON.parse(datosElemento.textContent || '[]');
        } catch (error) {
            servicios = [];
        }

        function agregarOpcion(valor, texto) {
            var opcion = document.createElement('option');
            opcion.value = String(valor || '');
            opcion.textContent = texto;
            selectorServicio.appendChild(opcion);
        }

        function etiquetaTipo(valor) {
            return String(valor || '').toLowerCase() === 'incidente'
                ? 'Incidente'
                : 'Requerimiento';
        }

        function mostrarResumen(servicio) {
            if (!resumenServicio || !resumenNombre || !resumenDescripcion) {
                return;
            }

            if (!servicio) {
                resumenServicio.classList.add('empty');
                resumenNombre.textContent = 'Seleccione un servicio para que aparezca la información de él en este apartado.';
                resumenDescripcion.textContent = '';
                resumenDescripcion.hidden = true;
                if (resumenEtiquetas) resumenEtiquetas.hidden = true;
                return;
            }

            resumenServicio.classList.remove('empty');
            resumenNombre.textContent = servicio.nombre || 'Servicio';
            resumenDescripcion.textContent = servicio.descripcion
                || 'El administrador no registró una descripción para este servicio.';
            resumenDescripcion.hidden = false;
            resumenTipo.textContent = etiquetaTipo(servicio.tipo_solicitud);
            resumenPrioridad.textContent = 'Prioridad: ' + (servicio.prioridad || 'Media');
            resumenUrgencia.textContent = 'Urgencia: ' + (servicio.urgencia || 'Moderada');
            resumenEtiquetas.hidden = false;
        }

        function cargarServicios(idCatalogo) {
            var disponibles = servicios.filter(function (servicio) {
                return Number(servicio.id_catalogo) === Number(idCatalogo)
                    && Number(servicio.id_proceso) > 0;
            });

            selectorServicio.innerHTML = '';
            selectorServicio.disabled = disponibles.length === 0;
            estadoServicios.classList.toggle('warning', disponibles.length === 0);
            mostrarResumen(null);

            if (!disponibles.length) {
                agregarOpcion('', 'No hay servicios disponibles en este catálogo');
                estadoServicios.textContent = 'Este catálogo todavía no tiene servicios habilitados con flujo, gestor y SLA.';
                return;
            }

            agregarOpcion('', 'Seleccione un servicio');
            disponibles.forEach(function (servicio) {
                agregarOpcion(servicio.id_proceso, servicio.nombre);
            });
            estadoServicios.textContent = disponibles.length === 1
                ? 'Se encontró 1 servicio disponible.'
                : 'Se encontraron ' + disponibles.length + ' servicios disponibles.';
        }

        selectorServicio.addEventListener('change', function () {
            var seleccionado = servicios.find(function (servicio) {
                return Number(servicio.id_proceso) === Number(selectorServicio.value);
            });
            mostrarResumen(seleccionado || null);
        });

        catalogos.forEach(function (catalogo) {
            catalogo.addEventListener('change', function () {
                if (catalogo.checked) {
                    cargarServicios(catalogo.value);
                    selectorServicio.focus();
                }
            });
        });

        for (var indice = 0; indice < catalogos.length; indice += 1) {
            if (catalogos[indice].checked) {
                cargarServicios(catalogos[indice].value);
                break;
            }
        }

        function ajustarAlturaDescripcion() {
            if (!entradaDescripcion) return;
            entradaDescripcion.style.height = '64px';
            var alturaNecesaria = Math.min(entradaDescripcion.scrollHeight, 360);
            entradaDescripcion.style.height = Math.max(64, alturaNecesaria) + 'px';
            entradaDescripcion.classList.toggle('is-scrollable', entradaDescripcion.scrollHeight > 360);
        }

        if (entradaDescripcion) {
            entradaDescripcion.addEventListener('input', ajustarAlturaDescripcion);
            ajustarAlturaDescripcion();
        }

        function formatoTamano(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function sincronizarAdjuntos() {
            if (!entradaAdjuntos || !listaAdjuntos || !estadoAdjuntos) return;

            if (typeof DataTransfer !== 'undefined') {
                var transferencia = new DataTransfer();
                archivosSeleccionados.forEach(function (archivo) {
                    transferencia.items.add(archivo);
                });
                entradaAdjuntos.files = transferencia.files;
            }

            listaAdjuntos.innerHTML = '';
            listaAdjuntos.hidden = archivosSeleccionados.length === 0;

            archivosSeleccionados.forEach(function (archivo, indiceArchivo) {
                var item = document.createElement('div');
                item.className = 'file-item';
                var datos = document.createElement('span');
                var nombre = document.createElement('strong');
                var tamano = document.createElement('small');
                var quitar = document.createElement('button');
                nombre.textContent = archivo.name;
                tamano.textContent = formatoTamano(archivo.size);
                quitar.type = 'button';
                quitar.className = 'file-remove';
                quitar.setAttribute('aria-label', 'Quitar ' + archivo.name);
                quitar.textContent = '×';
                quitar.addEventListener('click', function () {
                    archivosSeleccionados.splice(indiceArchivo, 1);
                    sincronizarAdjuntos();
                });
                datos.appendChild(nombre);
                datos.appendChild(tamano);
                item.appendChild(datos);
                item.appendChild(quitar);
                listaAdjuntos.appendChild(item);
            });

            estadoAdjuntos.classList.remove('error');
            estadoAdjuntos.textContent = archivosSeleccionados.length
                ? archivosSeleccionados.length + ' de 5 archivos seleccionados.'
                : 'Máximo cinco archivos de 5 MB cada uno.';
        }

        function agregarArchivos(archivos) {
            if (!estadoAdjuntos) return;
            var rechazados = [];
            var extensionesPermitidas = [
                'pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'csv', 'zip',
                'doc', 'docx', 'xls', 'xlsx'
            ];

            Array.from(archivos || []).forEach(function (archivo) {
                var partesNombre = archivo.name.toLowerCase().split('.');
                var extension = partesNombre.length > 1 ? partesNombre.pop() : '';
                var repetido = archivosSeleccionados.some(function (actual) {
                    return actual.name === archivo.name
                        && actual.size === archivo.size
                        && actual.lastModified === archivo.lastModified;
                });

                if (repetido) return;
                if (!extensionesPermitidas.includes(extension)) {
                    rechazados.push(archivo.name + ' no tiene un formato permitido');
                    return;
                }
                if (archivo.size > 5 * 1024 * 1024) {
                    rechazados.push(archivo.name + ' supera 5 MB');
                    return;
                }
                if (archivosSeleccionados.length >= 5) {
                    rechazados.push('Solo se permiten cinco archivos');
                    return;
                }
                archivosSeleccionados.push(archivo);
            });

            sincronizarAdjuntos();
            if (rechazados.length) {
                estadoAdjuntos.classList.add('error');
                estadoAdjuntos.textContent = rechazados.join('. ') + '.';
            }
        }

        if (entradaAdjuntos && zonaAdjuntos) {
            entradaAdjuntos.addEventListener('change', function () {
                agregarArchivos(entradaAdjuntos.files);
            });
            ['dragenter', 'dragover'].forEach(function (evento) {
                zonaAdjuntos.addEventListener(evento, function (event) {
                    event.preventDefault();
                    zonaAdjuntos.classList.add('dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (evento) {
                zonaAdjuntos.addEventListener(evento, function (event) {
                    event.preventDefault();
                    zonaAdjuntos.classList.remove('dragover');
                });
            });
            zonaAdjuntos.addEventListener('drop', function (event) {
                agregarArchivos(event.dataTransfer ? event.dataTransfer.files : []);
            });
        }
    }());
</script>
<script>
    (function () {
        'use strict';

        window.__MESA_SOLICITANTE_ESTADO_MODAL_VERSION__ = '2026-08-10.2';

        var entradaBusqueda = document.getElementById('buscar-casos');
        var selectorEstado = document.getElementById('estado-casos');
        var botonLimpiar = document.getElementById('limpiar-filtro-casos');
        var contadorCasos = document.getElementById('contador-casos');
        var filaSinResultados = document.getElementById('sin-resultados-casos');
        var filasCasos = Array.from(document.querySelectorAll('[data-case-row]'));
        var tareaFiltro = 0;

        function normalizarTexto(valor) {
            return String(valor || '')
                .toLocaleLowerCase('es')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
        }

        function guardarFiltrosEnUrl() {
            if (!window.history || typeof window.history.replaceState !== 'function') {
                return;
            }

            var url = new URL(window.location.href);
            var busqueda = entradaBusqueda ? entradaBusqueda.value.trim() : '';
            var estado = selectorEstado ? selectorEstado.value : 'todos';

            if (busqueda) {
                url.searchParams.set('buscar', busqueda);
            } else {
                url.searchParams.delete('buscar');
            }

            if (estado && estado !== 'todos') {
                url.searchParams.set('estado_busqueda', estado);
            } else {
                url.searchParams.delete('estado_busqueda');
            }

            window.history.replaceState({}, '', url.toString());
        }

        function aplicarFiltros(persistir) {
            if (!entradaBusqueda || !selectorEstado || !filasCasos.length) {
                return;
            }

            var texto = normalizarTexto(entradaBusqueda.value);
            var estado = selectorEstado.value || 'todos';
            var visibles = 0;

            filasCasos.forEach(function (fila) {
                var coincideTexto = !texto
                    || normalizarTexto(fila.textContent).includes(texto);
                var coincideEstado = estado === 'todos'
                    || fila.dataset.caseState === estado;
                var visible = coincideTexto && coincideEstado;
                fila.hidden = !visible;

                if (visible) {
                    visibles += 1;
                }
            });

            if (contadorCasos) {
                contadorCasos.textContent = visibles === 1
                    ? '1 caso encontrado'
                    : visibles + ' casos encontrados';
            }

            if (filaSinResultados) {
                filaSinResultados.hidden = visibles !== 0;
            }

            if (botonLimpiar) {
                botonLimpiar.hidden = entradaBusqueda.value.length === 0;
            }

            if (persistir) {
                guardarFiltrosEnUrl();
            }
        }

        function programarFiltro() {
            if (tareaFiltro) {
                window.cancelAnimationFrame(tareaFiltro);
            }

            tareaFiltro = window.requestAnimationFrame(function () {
                tareaFiltro = 0;
                aplicarFiltros(true);
            });
        }

        if (entradaBusqueda && selectorEstado && filasCasos.length) {
            entradaBusqueda.addEventListener('input', programarFiltro);
            selectorEstado.addEventListener('change', function () {
                aplicarFiltros(true);
            });

            if (botonLimpiar) {
                botonLimpiar.addEventListener('click', function () {
                    entradaBusqueda.value = '';
                    aplicarFiltros(true);
                    entradaBusqueda.focus();
                });
            }

            document.querySelectorAll('[data-case-view]').forEach(function (enlace) {
                enlace.addEventListener('click', function () {
                    var url = new URL(enlace.href, window.location.href);
                    var busqueda = entradaBusqueda.value.trim();
                    var estado = selectorEstado.value || 'todos';

                    if (busqueda) {
                        url.searchParams.set('buscar', busqueda);
                    } else {
                        url.searchParams.delete('buscar');
                    }

                    if (estado !== 'todos') {
                        url.searchParams.set('estado_busqueda', estado);
                    } else {
                        url.searchParams.delete('estado_busqueda');
                    }

                    enlace.href = url.toString();
                });
            });

            aplicarFiltros(false);
        }

        var modalCaso = document.getElementById('modal-estado-caso');

        if (!modalCaso) {
            return;
        }

        // El componente global de navegación usa capas fijas de alta prioridad.
        // Mantener el modal como hijo directo de body evita que un contenedor
        // intermedio lo recorte o cree un contexto de apilamiento por debajo.
        if (modalCaso.parentElement !== document.body) {
            document.body.appendChild(modalCaso);
        }

        var botonCerrarModal = modalCaso.querySelector('[data-case-modal-close]');
        var focoAnterior = document.activeElement;
        document.body.classList.add('case-modal-open');

        function cerrarModalCaso() {
            modalCaso.hidden = true;
            document.body.classList.remove('case-modal-open');

            if (window.history && typeof window.history.replaceState === 'function') {
                var url = new URL(window.location.href);
                url.searchParams.delete('id_ticket');
                url.searchParams.delete('id_chat');
                url.hash = '';
                window.history.replaceState({}, '', url.toString());
            }

            if (focoAnterior && typeof focoAnterior.focus === 'function') {
                focoAnterior.focus();
            } else if (entradaBusqueda) {
                entradaBusqueda.focus();
            }
        }

        if (botonCerrarModal) {
            botonCerrarModal.addEventListener('click', cerrarModalCaso);
            window.requestAnimationFrame(function () {
                botonCerrarModal.focus();
            });
        }

        modalCaso.addEventListener('click', function (event) {
            if (event.target === modalCaso) {
                cerrarModalCaso();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (modalCaso.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                cerrarModalCaso();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            var controles = Array.from(modalCaso.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(function (control) {
                return !control.hidden && control.offsetParent !== null;
            });

            if (!controles.length) {
                return;
            }

            var primero = controles[0];
            var ultimo = controles[controles.length - 1];

            if (event.shiftKey && document.activeElement === primero) {
                event.preventDefault();
                ultimo.focus();
            } else if (!event.shiftKey && document.activeElement === ultimo) {
                event.preventDefault();
                primero.focus();
            }
        });
    }());
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
