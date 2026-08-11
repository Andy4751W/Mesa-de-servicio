<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

$rol = (int) ($_SESSION['rol'] ?? 0);
$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$nombreUsuario = trim((string) ($_SESSION['usuario'] ?? 'Usuario'));
$idPaisOperacion = paisExigirContexto();
$rutaBaseTickets = (
    $rol === 1
    && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'solicitudes.php'
) ? 'solicitudes.php' : 'flujoTicket.php';

if (!in_array($rol, [1, 2], true)) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparFlujo(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function fechaFlujo(?string $fecha): string
{
    if (!$fecha) {
        return 'Pendiente';
    }

    $marca = strtotime($fecha);

    return $marca ? date('d/m/Y H:i', $marca) : $fecha;
}

function tiempoFlujo(int|string|null $minutos): string
{
    if ($minutos === null || $minutos === '') {
        return 'Sin dato';
    }

    $total = max(0, (int) $minutos);
    $minutosDia = defined('CALENDARIO_MINUTOS_DIA_SLA')
        ? max(1, (int) CALENDARIO_MINUTOS_DIA_SLA)
        : 600;
    $dias = intdiv($total, $minutosDia);
    $horas = intdiv($total % $minutosDia, 60);
    $resto = $total % 60;

    if ($dias > 0) {
        return $dias . ' día(s) hábil(es) ' . $horas . ' h ' . $resto . ' min';
    }

    if ($horas > 0) {
        return $horas . ' h ' . $resto . ' min';
    }

    return $resto . ' min';
}

/**
 * @param array<int, array<string, mixed>> $nodos
 * @return array<int, array<string, mixed>>
 */
function ordenarArbolCasos(array $nodos, int $idCasoPrincipal): array
{
    $porPadre = [];
    $ids = [];

    foreach ($nodos as $nodo) {
        $id = (int) $nodo['id_ticket_etapa'];
        $padre = (int) ($nodo['id_ticket_etapa_padre'] ?? 0);
        $ids[$id] = true;
        $porPadre[$padre][] = $nodo;
    }

    foreach ($porPadre as &$grupo) {
        usort(
            $grupo,
            static fn (array $a, array $b): int =>
                [(int) $a['orden'], (int) $a['id_ticket_etapa']]
                <=> [(int) $b['orden'], (int) $b['id_ticket_etapa']]
        );
    }
    unset($grupo);

    $ordenados = [];
    $visitados = [];
    $recorrer = static function (
        int $idPadre,
        int $nivel,
        int $numeroEtapa = 0,
        string $codigoTicketPadre = ''
    ) use (
        &$recorrer,
        &$ordenados,
        &$visitados,
        $porPadre,
        $idCasoPrincipal
    ): void {
        foreach ($porPadre[$idPadre] ?? [] as $indice => $nodo) {
            $id = (int) $nodo['id_ticket_etapa'];

            if (isset($visitados[$id])) {
                continue;
            }

            $visitados[$id] = true;
            $nodo['nivel_visual'] = $nivel;
            $esEtapaOficial = $idPadre === 0;
            $etapaNodo = $esEtapaOficial ? $indice + 1 : $numeroEtapa;
            $codigoTicket = $esEtapaOficial
                ? (string) $etapaNodo
                : $codigoTicketPadre . '.' . ($indice + 1);
            $nodo['es_etapa_oficial'] = $esEtapaOficial;
            $nodo['es_derivacion'] = !$esEtapaOficial;
            $nodo['numero_etapa'] = $etapaNodo;
            $nodo['codigo_caso'] = (string) $idCasoPrincipal;
            $nodo['codigo_ticket'] = $codigoTicket;
            $ordenados[] = $nodo;
            $recorrer(
                $id,
                $nivel + 1,
                $etapaNodo,
                $codigoTicket
            );
        }
    };
    $recorrer(0, 0, 0, '');

    /* Registros antiguos sin relación padre se conservan visibles. */
    foreach ($nodos as $nodo) {
        $id = (int) $nodo['id_ticket_etapa'];

        if (!isset($visitados[$id])) {
            $nodo['nivel_visual'] = max(0, (int) ($nodo['nivel'] ?? 0));
            $esDerivacion = (int) ($nodo['id_ticket_etapa_padre'] ?? 0) > 0;
            $nodo['es_etapa_oficial'] = !$esDerivacion;
            $nodo['es_derivacion'] = $esDerivacion;
            $nodo['numero_etapa'] = max(1, (int) ($nodo['orden'] ?? 1));
            $nodo['codigo_caso'] = (string) $idCasoPrincipal;
            $nodo['codigo_ticket'] = $esDerivacion
                ? (string) $id
                : (string) $nodo['numero_etapa'];
            $ordenados[] = $nodo;
        }
    }

    return $ordenados;
}

function urlTicketFlujo(string $rutaBase, int $rol, int $idTicket): string
{
    $parametros = ['id_ticket' => $idTicket];

    if ($rol === 2) {
        $bandeja = isset($GLOBALS['bandejaGestor'])
            ? (string) $GLOBALS['bandejaGestor']
            : (string) ($_GET['bandeja'] ?? 'abiertos');
        $parametros['modo'] = 'mis_tickets';
        $parametros['bandeja'] = in_array($bandeja, ['abiertos', 'cerrados'], true)
            ? $bandeja
            : 'abiertos';

        $busqueda = trim((string) ($_GET['buscar'] ?? ''));
        $estadoBusqueda = trim((string) ($_GET['estado_busqueda'] ?? 'todos'));

        if ($busqueda !== '') {
            $parametros['buscar'] = substr($busqueda, 0, 120);
        }
        if ($estadoBusqueda !== '' && $estadoBusqueda !== 'todos') {
            $parametros['estado_busqueda'] = substr($estadoBusqueda, 0, 60);
        }
    }

    return $rutaBase . '?' . http_build_query($parametros);
}

function textoEstadoCaso(string $estado): string
{
    return match ($estado) {
        'en_proceso' => 'En proceso',
        'en_espera_solicitante' => 'En espera',
        'listo_cierre' => 'Listo · pendiente de cierre',
        'completada' => 'Completado',
        'cancelada' => 'Cancelado',
        'bloqueada' => 'Bloqueado',
        'pausada' => 'Pausado',
        'pendiente' => 'Pendiente',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function textoEstadoTicket(string $estado): string
{
    return match ($estado) {
        'en_proceso' => 'En proceso',
        'pendiente_calificacion' => 'Pendiente de calificación',
        'cerrado' => 'Cerrado definitivamente',
        'cancelado', 'cancelada' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

/**
 * @param array<int, array<int, array<string, mixed>>> $hijosPorPadre
 */
function contarDescendientesCaso(int $idPadre, array $hijosPorPadre): int
{
    $total = 0;

    foreach ($hijosPorPadre[$idPadre] ?? [] as $hijo) {
        $idHijo = (int) $hijo['id_ticket_etapa'];
        $total += 1 + contarDescendientesCaso($idHijo, $hijosPorPadre);
    }

    return $total;
}

/**
 * Renderiza una rama compacta. El <details> pertenece al caso padre, por lo
 * que al contraerlo solo desaparecen sus descendientes y el padre permanece.
 *
 * @param array<string, mixed> $nodo
 * @param array<int, array<int, array<string, mixed>>> $hijosPorPadre
 * @param array<int, bool> $rutaSeleccionada
 */
function renderizarRamaCasos(
    array $nodo,
    array $hijosPorPadre,
    string $rutaBase,
    int $idTicket,
    int $idSeleccionado,
    array $rutaSeleccionada
): void {
    $idNodo = (int) $nodo['id_ticket_etapa'];
    $idNodoDestino = (int) ($nodo['id_nodo_destino'] ?? $idNodo);
    $hijos = $hijosPorPadre[$idNodo] ?? [];
    $totalHijos = count($hijos);
    $totalDescendientes = contarDescendientesCaso($idNodo, $hijosPorPadre);
    $codigoCaso = (string) ($nodo['codigo_caso'] ?? $idTicket);
    $codigoTicket = (string) ($nodo['codigo_ticket'] ?? '');
    $esCasoPrincipal = !empty($nodo['es_caso_principal']);
    $esDerivacion = !$esCasoPrincipal
        && !empty($nodo['es_derivacion']);
    $numeroEtapa = max(1, (int) ($nodo['numero_etapa'] ?? 1));
    $totalEtapas = max($numeroEtapa, (int) ($nodo['total_etapas'] ?? $numeroEtapa));
    $estado = (string) ($nodo['estado'] ?? 'pendiente');
    $estadoClase = preg_replace('/[^a-z0-9_-]/', '', strtolower($estado))
        ?: 'pendiente';
    $esSeleccionado = $idSeleccionado === $idNodoDestino;
    $ramaActiva = $esCasoPrincipal
        ? !empty($rutaSeleccionada)
        : isset($rutaSeleccionada[$idNodo]);
    $urlNodo = $rutaBase . '?' . http_build_query([
        'id_ticket' => $idTicket,
        'id_nodo' => $idNodoDestino,
        'id_chat' => $idNodoDestino,
    ]);
    $momentoSla = match ($estado) {
        'pausada' => 'Pausado desde ' . fechaFlujo($nodo['fecha_pausa'] ?? null),
        'listo_cierre' => 'Listo desde '
            . fechaFlujo($nodo['fecha_marcado_listo'] ?? null)
            . ' · vencimiento visible '
            . fechaFlujo($nodo['fecha_vencimiento'] ?? null),
        default => 'Vence ' . fechaFlujo($nodo['fecha_vencimiento'] ?? null),
    };
    $contenido = static function () use (
        $nodo,
        $codigoCaso,
        $codigoTicket,
        $esCasoPrincipal,
        $esDerivacion,
        $numeroEtapa,
        $totalEtapas,
        $estado,
        $momentoSla,
        $urlNodo,
        $esSeleccionado
    ): void {
        ?>
        <div class="case-row-main">
            <div class="case-code-line">
                <span class="case-code"><?=
                    $esDerivacion
                        ? 'Ticket ' . escaparFlujo($codigoTicket)
                        : 'Caso ' . escaparFlujo($codigoCaso)
                ?></span>
                <span class="case-status <?= escaparFlujo($estado) ?>"><?= escaparFlujo(textoEstadoCaso($estado)) ?></span>
            </div>
            <?php if (!$esDerivacion): ?>
                <span class="case-service">Etapa <?= $numeroEtapa ?> de <?= $totalEtapas ?></span>
            <?php endif; ?>
            <strong class="case-title"><?= escaparFlujo($nodo['catalogo_nombre'] ?? 'Área') ?></strong>
            <span class="case-service"><?= escaparFlujo($nodo['servicio_nombre'] ?? 'Servicio') ?></span>
        </div>
        <div class="case-row-meta">
            <span><b>Gestor:</b> <?= escaparFlujo($nodo['gestor_nombre'] ?? 'Pendiente') ?></span>
            <span><b>SLA:</b> <?= escaparFlujo($nodo['estado_sla_actual'] ?? 'sin iniciar') ?></span>
            <span><?= escaparFlujo($momentoSla) ?></span>
        </div>
        <a
            class="btn primary case-open"
            href="<?= escaparFlujo($urlNodo) ?>"
            aria-current="<?= $esSeleccionado ? 'page' : 'false' ?>"
        ><?= $esDerivacion ? 'Ver ticket' : 'Ver caso' ?></a>
        <?php
    };

    if ($totalHijos > 0) {
        ?>
        <details
            class="case-branch <?= escaparFlujo($estadoClase) ?> <?= $esSeleccionado ? 'selected' : '' ?>"
            data-case-branch
            data-case-id="<?= $idNodo ?>"
            <?= $ramaActiva ? 'open data-selected-path="true"' : '' ?>
        >
            <summary class="case-row">
                <span class="branch-chevron" aria-hidden="true"></span>
                <?php $contenido(); ?>
                <span class="branch-action">
                    <span class="when-open">− Ocultar <?= $totalDescendientes ?> ticket<?= $totalDescendientes === 1 ? '' : 's' ?> derivado<?= $totalDescendientes === 1 ? '' : 's' ?></span>
                    <span class="when-closed">＋ Mostrar <?= $totalDescendientes ?> ticket<?= $totalDescendientes === 1 ? '' : 's' ?> derivado<?= $totalDescendientes === 1 ? '' : 's' ?></span>
                </span>
            </summary>
            <div class="case-children">
                <?php foreach ($hijos as $hijo): ?>
                    <?php renderizarRamaCasos(
                        $hijo,
                        $hijosPorPadre,
                        $rutaBase,
                        $idTicket,
                        $idSeleccionado,
                        $rutaSeleccionada
                    ); ?>
                <?php endforeach; ?>
            </div>
        </details>
        <?php

        return;
    }
    ?>
    <article class="case-leaf <?= escaparFlujo($estadoClase) ?> <?= $esSeleccionado ? 'selected' : '' ?>">
        <div class="case-row">
            <span class="branch-dot" aria-hidden="true"></span>
            <?php $contenido(); ?>
        </div>
    </article>
    <?php
}

function redirigirFlujo(
    string $mensaje,
    int $idTicket = 0,
    int $idNodo = 0,
    int $idChat = 0
): never {
    global $rutaBaseTickets, $rol;
    $parametros = ['msg' => $mensaje];

    if ($rol === 2) {
        $parametros['modo'] = 'mis_tickets';
        $bandeja = isset($GLOBALS['bandejaGestor'])
            ? (string) $GLOBALS['bandejaGestor']
            : (string) ($_GET['bandeja'] ?? 'abiertos');
        $parametros['bandeja'] = in_array($bandeja, ['abiertos', 'cerrados'], true)
            ? $bandeja
            : 'abiertos';

        $busqueda = trim((string) ($_GET['buscar'] ?? ''));
        $estadoBusqueda = trim((string) ($_GET['estado_busqueda'] ?? 'todos'));

        if ($busqueda !== '') {
            $parametros['buscar'] = substr($busqueda, 0, 120);
        }
        if ($estadoBusqueda !== '' && $estadoBusqueda !== 'todos') {
            $parametros['estado_busqueda'] = substr($estadoBusqueda, 0, 60);
        }
    }

    if ($idTicket > 0) {
        $parametros['id_ticket'] = $idTicket;
    }

    if ($idNodo > 0) {
        $parametros['id_nodo'] = $idNodo;
    }

    if ($idChat > 0) {
        $parametros['id_chat'] = $idChat;
    }

    header('Location: ' . $rutaBaseTickets . '?' . http_build_query($parametros));
    exit;
}

$estructuraGestorPorEtapa = flujoColumnaExiste(
    $conn,
    'proceso_etapas',
    'id_gestor'
) && flujoColumnaExiste($conn, 'proceso_etapas', 'id_sla');

if (
    !flujoModuloInstalado($conn)
    || !$estructuraGestorPorEtapa
    || !flujoModuloAprobacionCasosInstalado($conn)
) {
    ?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalación pendiente</title><style>body{font-family:Segoe UI,Arial;background:#f3f6fb;color:#243b53;padding:40px}.box{max-width:760px;margin:auto;background:#fff;border:1px solid #dfe7f1;border-radius:18px;padding:28px}.btn{display:inline-block;padding:11px 16px;border-radius:10px;background:#0f6fec;color:#fff;text-decoration:none}</style></head><body><section class="box"><h1>Instalación pendiente</h1><p>El administrador debe importar <strong>migracion_aprobacion_reapertura_y_encuestas.sql</strong> en la base <strong>mesa_servicio</strong>.</p><a class="btn" href="<?= $rol === 1 ? 'panelAdmin.php' : 'panelGestor.php' ?>">Volver al panel</a></section><script src="assets/js/controlSesion.js" defer></script></body></html><?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals((string) $_SESSION['csrf_token'], $token)
    ) {
        redirigirFlujo('solicitud_invalida');
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $idTicket = filter_input(INPUT_POST, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;

    if ($idTicket > 0) {
        paisExigirTicket($conn, $idTicket);
    }

    try {
        if ($accion === 'crear_ticket') {
            throw new RuntimeException(
                'Los tickets solo pueden ser creados desde el portal del solicitante.'
            );
        }

        $ticket = flujoObtenerTicket($conn, $idTicket);

        if (
            !$ticket
            || !flujoPuedeVerTicket($conn, $idTicket, $idUsuario, $rol)
        ) {
            throw new RuntimeException('No tiene acceso al ticket.');
        }

        if ($accion === 'crear_caso_hijo') {
            if ($rol !== 2) {
                throw new RuntimeException(
                    'Solo el gestor asignado puede crear casos hijos.'
                );
            }

            $idNodoPadre = filter_input(
                INPUT_POST,
                'id_ticket_etapa_padre',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $filasDerivacion = $_POST['derivaciones'] ?? [];

            if (!is_array($filasDerivacion) || !$filasDerivacion) {
                /* Compatibilidad temporal con el formulario anterior. */
                $filasDerivacion = [[
                    'id_servicio_destino' => $_POST['id_servicio_destino'] ?? 0,
                    'motivo_derivacion' => $_POST['motivo_derivacion'] ?? '',
                ]];
            }

            $serviciosDestinoValidos = [];
            foreach (flujoServiciosDisponibles($conn) as $servicioDisponible) {
                $serviciosDestinoValidos[(int) $servicioDisponible['id_servicio']] =
                    (int) $servicioDisponible['id_catalogo'];
            }

            $derivaciones = [];
            foreach ($filasDerivacion as $filaDerivacion) {
                if (!is_array($filaDerivacion)) {
                    continue;
                }

                $idServicioDestino = (int) (
                    $filaDerivacion['id_servicio_destino'] ?? 0
                );
                $usaSeleccionCatalogo = array_key_exists(
                    'id_catalogo_destino',
                    $filaDerivacion
                );
                $idCatalogoDestino = (int) (
                    $filaDerivacion['id_catalogo_destino'] ?? 0
                );

                if (
                    $usaSeleccionCatalogo
                    && (
                        $idCatalogoDestino < 1
                        || !isset($serviciosDestinoValidos[$idServicioDestino])
                        || $serviciosDestinoValidos[$idServicioDestino]
                            !== $idCatalogoDestino
                    )
                ) {
                    throw new RuntimeException(
                        'Seleccione un servicio válido del catálogo indicado.'
                    );
                }

                $derivaciones[] = [
                    'id_servicio_destino' => $idServicioDestino,
                    'motivo_derivacion' => trim((string) (
                        $filaDerivacion['motivo_derivacion'] ?? ''
                    )),
                ];
            }

            flujoCrearCasosHijos(
                $conn,
                $idTicket,
                $idNodoPadre,
                $derivaciones,
                $idUsuario,
                $rol
            );
            redirigirFlujo('casos_hijos_creados', $idTicket, $idNodoPadre);
        }

        if ($accion === 'enviar_mensaje') {
            $idTicketEtapa = filter_input(
                INPUT_POST,
                'id_ticket_etapa',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
            flujoEnviarConversacion(
                $conn,
                $ticket,
                $idTicketEtapa,
                $idUsuario,
                $rol,
                $mensaje,
                isset($_FILES['adjuntos'])
                    ? (array) $_FILES['adjuntos']
                    : []
            );

            redirigirFlujo(
                'mensaje_enviado',
                $idTicket,
                $idTicketEtapa,
                $idTicketEtapa
            );
        }

        if ($accion === 'guardar_checklist') {
            if (!in_array($rol, [1, 2], true)) {
                throw new RuntimeException('No está autorizado para gestionar el checklist.');
            }

            $idTicketEtapa = filter_input(
                INPUT_POST,
                'id_ticket_etapa',
                FILTER_VALIDATE_INT
            ) ?: 0;
            flujoGuardarChecklist(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                $rol,
                (array) ($_POST['completado'] ?? []),
                (array) ($_POST['observacion'] ?? [])
            );
            redirigirFlujo(
                'checklist_guardado',
                $idTicket,
                $idTicketEtapa,
                $idTicketEtapa
            );
        }

        if (in_array($accion, ['marcar_listo', 'solicitar_cierre_definitivo'], true)) {
            if ($rol !== 2) {
                throw new RuntimeException(
                    'Solo el gestor asignado puede marcar el caso como listo.'
                );
            }

            $idTicketEtapa = filter_input(
                INPUT_POST,
                'id_ticket_etapa',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $idSolucion = filter_input(
                INPUT_POST,
                'id_solucion',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $comentario = trim((string) ($_POST['comentario_cierre'] ?? ''));
            flujoMarcarEtapaLista(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                $rol,
                $idSolucion,
                $comentario,
                $accion === 'solicitar_cierre_definitivo'
            );
            redirigirFlujo(
                $accion === 'solicitar_cierre_definitivo'
                    ? 'cierre_definitivo_solicitado'
                    : 'etapa_lista',
                $idTicket,
                $idTicketEtapa,
                $idTicketEtapa
            );
        }

        if ($accion === 'cerrar_caso') {
            $idTicketEtapa = filter_input(
                INPUT_POST,
                'id_ticket_etapa',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $calificacionArea = filter_input(
                INPUT_POST,
                'calificacion_area',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $calificacionTiempo = filter_input(
                INPUT_POST,
                'calificacion_tiempo',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $comentarioCalificacion = trim((string) (
                $_POST['comentario_calificacion'] ?? ''
            ));
            flujoCompletarEtapa(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                $rol,
                $calificacionArea,
                $calificacionTiempo,
                $comentarioCalificacion
            );
            redirigirFlujo(
                'caso_cerrado',
                $idTicket,
                $idTicketEtapa,
                $idTicketEtapa
            );
        }

        if ($accion === 'reabrir_derivacion') {
            $idTicketEtapa = filter_input(
                INPUT_POST,
                'id_ticket_etapa',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $motivoReapertura = trim((string) (
                $_POST['motivo_reapertura'] ?? ''
            ));
            flujoReabrirDerivacion(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                $rol,
                $motivoReapertura
            );
            redirigirFlujo(
                'derivacion_reabierta',
                $idTicket,
                $idTicketEtapa,
                $idTicketEtapa
            );
        }

        if ($accion === 'calificar_cerrar_ticket') {
            if ($rol !== 2 || (int) $ticket['id_usuario'] !== $idUsuario) {
                throw new RuntimeException(
                    'Solo el gestor que creó el ticket puede cerrarlo definitivamente.'
                );
            }

            $total = flujoCalificarCerrarTicket(
                $conn,
                $idTicket,
                $idUsuario,
                (array) ($_POST['calificacion_area'] ?? []),
                (array) ($_POST['calificacion_tiempo'] ?? []),
                (array) ($_POST['comentario'] ?? [])
            );

            redirigirFlujo('ticket_cerrado', $idTicket);
        }

        /* Compatibilidad con formularios antiguos instalados antes de la
           encuesta consolidada. */
        if ($accion === 'calificar_etapa') {
            throw new RuntimeException(
                'Este formulario quedó deshabilitado. Cada caso debe marcarse Listo y ser calificado y cerrado por su propio creador.'
            );
        }

        redirigirFlujo('solicitud_invalida', $idTicket);
    } catch (Throwable $e) {
        error_log('Flujo de ticket: ' . $e->getMessage());
        $_SESSION['error_flujo'] = get_class($e) === RuntimeException::class
            ? $e->getMessage()
            : 'No fue posible completar la operación. Inténtelo nuevamente.';
        redirigirFlujo('error_operacion', $idTicket);
    }
}

$procesosDisponibles = [];
$catalogosDisponibles = [];
$flujosPorServicio = [];
if ($rol === 2) {
    $resultado = $conn->query(
        "SELECT
            p.id_proceso,
            p.nombre,
            p.descripcion,
            c_inicial.id_catalogo,
            c_inicial.nombre AS catalogo_nombre,
            c_inicial.descripcion AS catalogo_descripcion,
            c_inicial.imagen AS catalogo_imagen,
            s_inicial.id_servicio,
            s_inicial.nombre AS servicio_nombre,
            s_inicial.descripcion AS servicio_descripcion,
            sl_inicial.nombre AS servicio_sla,
            sl_inicial.tiempo_respuesta AS servicio_sla_tiempo,
            sl_inicial.unidad AS servicio_sla_unidad,
            (
                SELECT COUNT(*)
                FROM proceso_etapas AS pe_total
                WHERE pe_total.id_proceso = p.id_proceso
                  AND pe_total.estado = 'activo'
            ) AS etapas
         FROM procesos AS p
         INNER JOIN proceso_etapas AS pe_inicial
            ON pe_inicial.id_proceso = p.id_proceso
           AND pe_inicial.estado = 'activo'
           AND pe_inicial.orden = (
                SELECT MIN(pe_primera.orden)
                FROM proceso_etapas AS pe_primera
                WHERE pe_primera.id_proceso = p.id_proceso
                  AND pe_primera.estado = 'activo'
           )
         INNER JOIN servicios AS s_inicial
            ON s_inicial.id_servicio = pe_inicial.id_servicio
           AND s_inicial.estado = 'activo'
         INNER JOIN catalogos AS c_inicial
            ON c_inicial.id_catalogo = s_inicial.id_catalogo
           AND c_inicial.estado = 'activo'
         INNER JOIN usuarios AS u_inicial
            ON u_inicial.id_usuario = COALESCE(
                pe_inicial.id_gestor,
                s_inicial.id_gestor
            )
           AND u_inicial.id_rol = 2
           AND u_inicial.estado = 'activo'
         INNER JOIN sla AS sl_inicial
            ON sl_inicial.id_sla = COALESCE(
                pe_inicial.id_sla,
                s_inicial.id_sla
            )
           AND sl_inicial.estado = 'activo'
         WHERE p.estado = 'activo'
           AND p.id_pais_operacion = {$idPaisOperacion}
           AND NOT EXISTS (
                SELECT 1
                FROM proceso_etapas AS pe_revision
                LEFT JOIN servicios AS s_revision
                   ON s_revision.id_servicio = pe_revision.id_servicio
                  AND s_revision.estado = 'activo'
                LEFT JOIN usuarios AS u_revision
                   ON u_revision.id_usuario = COALESCE(
                        pe_revision.id_gestor,
                        s_revision.id_gestor
                   )
                  AND u_revision.id_rol = 2
                  AND u_revision.estado = 'activo'
                LEFT JOIN sla AS sl_revision
                   ON sl_revision.id_sla = COALESCE(
                        pe_revision.id_sla,
                        s_revision.id_sla
                   )
                  AND sl_revision.estado = 'activo'
                WHERE pe_revision.id_proceso = p.id_proceso
                  AND pe_revision.estado = 'activo'
                  AND (
                    s_revision.id_servicio IS NULL
                    OR u_revision.id_usuario IS NULL
                    OR sl_revision.id_sla IS NULL
                  )
           )
         ORDER BY c_inicial.orden, c_inicial.nombre, s_inicial.nombre, p.nombre"
    );
    while ($fila = $resultado->fetch_assoc()) {
        $procesosDisponibles[] = $fila;
        $idServicioInicial = (int) $fila['id_servicio'];
        $flujosPorServicio[$idServicioInicial][] = $fila;
    }

    $resultadoCatalogos = $conn->query(
        "SELECT
            c.id_catalogo,
            c.nombre AS catalogo_nombre,
            c.descripcion AS catalogo_descripcion,
            c.imagen AS catalogo_imagen,
            c.orden AS catalogo_orden,
            s.id_servicio,
            s.nombre AS servicio_nombre,
            s.descripcion AS servicio_descripcion,
            sl.nombre AS servicio_sla,
            sl.tiempo_respuesta AS servicio_sla_tiempo,
            sl.unidad AS servicio_sla_unidad,
            u.nombre AS servicio_gestor
         FROM catalogos AS c
         LEFT JOIN servicios AS s
            ON s.id_catalogo = c.id_catalogo
           AND s.estado = 'activo'
         LEFT JOIN sla AS sl
            ON sl.id_sla = s.id_sla
           AND sl.estado = 'activo'
         LEFT JOIN usuarios AS u
            ON u.id_usuario = s.id_gestor
           AND u.id_rol = 2
           AND u.estado = 'activo'
         WHERE c.estado = 'activo'
           AND c.id_pais_operacion = {$idPaisOperacion}
         ORDER BY c.orden, c.nombre, s.nombre"
    );

    while ($fila = $resultadoCatalogos->fetch_assoc()) {
        $idCatalogo = (int) $fila['id_catalogo'];

        if (!isset($catalogosDisponibles[$idCatalogo])) {
            $catalogosDisponibles[$idCatalogo] = [
                'id_catalogo' => $idCatalogo,
                'nombre' => (string) $fila['catalogo_nombre'],
                'descripcion' => (string) ($fila['catalogo_descripcion'] ?? ''),
                'imagen' => (string) ($fila['catalogo_imagen'] ?? ''),
                'orden' => (int) $fila['catalogo_orden'],
                'servicios' => [],
            ];
        }

        $idServicio = (int) ($fila['id_servicio'] ?? 0);

        if ($idServicio > 0) {
            $fila['flujos'] = $flujosPorServicio[$idServicio] ?? [];
            $catalogosDisponibles[$idCatalogo]['servicios'][] = $fila;
        }
    }

    $catalogosDisponibles = array_values($catalogosDisponibles);
}

$ticketsUsuario = flujoTicketsUsuario($conn, $idUsuario, $rol);
$idTicketSeleccionado = filter_input(INPUT_GET, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
$bandejaGestor = (string) ($_GET['bandeja'] ?? 'abiertos');
$busquedaGestor = substr(trim((string) ($_GET['buscar'] ?? '')), 0, 120);
$estadoBusquedaGestor = substr(trim((string) ($_GET['estado_busqueda'] ?? 'todos')), 0, 60);

if (!in_array($bandejaGestor, ['abiertos', 'cerrados'], true)) {
    $bandejaGestor = 'abiertos';
}

if ($rol === 2 && $idTicketSeleccionado > 0) {
    foreach ($ticketsUsuario as $ticketUsuario) {
        if ((int) ($ticketUsuario['id_ticket'] ?? 0) !== $idTicketSeleccionado) {
            continue;
        }

        $bandejaGestor = strtolower(trim((string) ($ticketUsuario['estado_flujo'] ?? ''))) === 'cerrado'
            ? 'cerrados'
            : 'abiertos';
        break;
    }
}

$totalCasosAbiertos = 0;
$totalCasosCerrados = 0;

foreach ($ticketsUsuario as $ticketUsuario) {
    if (strtolower(trim((string) ($ticketUsuario['estado_flujo'] ?? ''))) === 'cerrado') {
        $totalCasosCerrados++;
    } else {
        $totalCasosAbiertos++;
    }
}

$tickets = $rol === 2
    ? array_values(array_filter(
        $ticketsUsuario,
        static function (array $ticket) use ($bandejaGestor): bool {
            $cerrado = strtolower(trim((string) ($ticket['estado_flujo'] ?? ''))) === 'cerrado';

            return $bandejaGestor === 'cerrados' ? $cerrado : !$cerrado;
        }
    ))
    : $ticketsUsuario;

$estadosGestorDisponibles = [];

if ($rol === 2) {
    foreach ($tickets as $ticketGestor) {
        $estadoGestor = (string) ($ticketGestor['estado_flujo'] ?? 'en_proceso');
        $estadosGestorDisponibles[$estadoGestor] = textoEstadoTicket($estadoGestor);
    }
    asort($estadosGestorDisponibles, SORT_NATURAL | SORT_FLAG_CASE);

    if (
        $estadoBusquedaGestor !== 'todos'
        && !array_key_exists($estadoBusquedaGestor, $estadosGestorDisponibles)
    ) {
        $estadoBusquedaGestor = 'todos';
    }
}

$serviciosDerivacion = flujoServiciosDisponibles($conn);
$catalogosDerivacion = [];

foreach ($serviciosDerivacion as $servicioDerivacion) {
    $idCatalogoDerivacion = (int) ($servicioDerivacion['id_catalogo'] ?? 0);

    if (
        $idCatalogoDerivacion > 0
        && !isset($catalogosDerivacion[$idCatalogoDerivacion])
    ) {
        $catalogosDerivacion[$idCatalogoDerivacion] = [
            'id_catalogo' => $idCatalogoDerivacion,
            'nombre' => (string) (
                $servicioDerivacion['catalogo_nombre'] ?? 'Catálogo'
            ),
        ];
    }
}

$serviciosDerivacionJson = json_encode(
    array_map(
        static fn (array $servicio): array => [
            'id_servicio' => (int) ($servicio['id_servicio'] ?? 0),
            'id_catalogo' => (int) ($servicio['id_catalogo'] ?? 0),
            'nombre' => (string) ($servicio['servicio_nombre'] ?? ''),
            'gestor' => (string) ($servicio['gestor_nombre'] ?? 'Sin gestor'),
            'sla' => (string) ($servicio['sla_nombre'] ?? 'Sin SLA'),
        ],
        $serviciosDerivacion
    ),
    JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
);

if (!is_string($serviciosDerivacionJson)) {
    $serviciosDerivacionJson = '[]';
}
$notificaciones = [];
$stmtNotificaciones = $conn->prepare(
    "SELECT n.id_notificacion, n.id_ticket, n.titulo, n.mensaje, n.leida, n.creada_en
     FROM notificaciones AS n
     LEFT JOIN tickets AS t ON t.id_ticket = n.id_ticket
     WHERE n.id_usuario = ?
       AND (n.id_ticket IS NULL OR t.id_pais_operacion = ?)
     ORDER BY n.leida ASC, n.creada_en DESC
     LIMIT 8"
);
$stmtNotificaciones->bind_param('ii', $idUsuario, $idPaisOperacion);
$stmtNotificaciones->execute();
$resultadoNotificaciones = $stmtNotificaciones->get_result();
while ($notificacion = $resultadoNotificaciones->fetch_assoc()) {
    $notificaciones[] = $notificacion;
}
$stmtNotificaciones->close();
$idNodoSeleccionado = filter_input(INPUT_GET, 'id_nodo', FILTER_VALIDATE_INT) ?: 0;
$idChatSeleccionado = filter_input(INPUT_GET, 'id_chat', FILTER_VALIDATE_INT) ?: 0;
$ticketSeleccionado = null;
$etapas = [];
$etapaActual = null;
$checklist = [];
$checklistObligatorioTotal = 0;
$checklistObligatorioPendiente = 0;
$conversaciones = [];
$conversacionActual = null;
$comunicaciones = [];
$adjuntos = [];
$historial = [];
$puedeEscribir = false;
$casoSeleccionadoCerrado = false;
$etapasPorId = [];
$hijosPorPadre = [];
$hijosArbolVisual = [];
$rutaCasoSeleccionado = [];
$etapasOficiales = [];
$casoPrincipalVisual = null;
$solucionesCaso = [];
$etapaEncuestaLegacy = null;
$moduloCalificacionDetallada = flujoModuloCalificacionDetalladaInstalado($conn);

if ($idTicketSeleccionado > 0) {
    paisExigirTicket($conn, $idTicketSeleccionado);
    if (!flujoPuedeVerTicket($conn, $idTicketSeleccionado, $idUsuario, $rol)) {
        http_response_code(403);
        exit('El ticket todavía no está habilitado para su área.');
    }

    $ticketSeleccionado = flujoObtenerTicket($conn, $idTicketSeleccionado);
    $etapas = ordenarArbolCasos(
        flujoObtenerEtapasTicket($conn, $idTicketSeleccionado),
        $idTicketSeleccionado
    );
    $etapaActual = flujoSeleccionarNodoTicket(
        $conn,
        $idTicketSeleccionado,
        $idUsuario,
        $rol,
        $idNodoSeleccionado
    );

    foreach ($etapas as $etapaArbol) {
        $idEtapaArbol = (int) $etapaArbol['id_ticket_etapa'];
        $idPadreArbol = (int) ($etapaArbol['id_ticket_etapa_padre'] ?? 0);
        $etapasPorId[$idEtapaArbol] = $etapaArbol;
        $hijosPorPadre[$idPadreArbol][] = $etapaArbol;

        if (
            $etapaEncuestaLegacy === null
            && $idPadreArbol === 0
        ) {
            $etapaEncuestaLegacy = $etapaArbol;
        }

        if ($idPadreArbol === 0) {
            $etapasOficiales[] = $etapaArbol;
        }
    }

    if ($etapaActual) {
        $idEtapaActual = (int) $etapaActual['id_ticket_etapa'];
        $etapaActual = array_merge(
            $etapaActual,
            $etapasPorId[$idEtapaActual] ?? []
        );
        $casoSeleccionadoCerrado = in_array(
            (string) $etapaActual['estado'],
            ['completada', 'cancelada'],
            true
        );

        if (!$casoSeleccionadoCerrado) {
            $solucionesCaso = flujoSolucionesServicio(
                $conn,
                (int) ($etapaActual['id_servicio'] ?? 0)
            );
        }

        $idRuta = $idEtapaActual;
        while ($idRuta > 0 && isset($etapasPorId[$idRuta])) {
            $rutaCasoSeleccionado[$idRuta] = true;
            $idRuta = (int) (
                $etapasPorId[$idRuta]['id_ticket_etapa_padre'] ?? 0
            );
        }
    }

    /*
     * El arbol visible contiene un solo caso principal. Las etapas oficiales
     * son el historial secuencial de ese mismo caso y las unicas ramas son
     * los tickets creados por derivacion en cada etapa.
     */
    $idRaizActual = (int) ($ticketSeleccionado['id_etapa_actual'] ?? 0);
    while (
        $idRaizActual > 0
        && isset($etapasPorId[$idRaizActual])
        && (int) ($etapasPorId[$idRaizActual]['id_ticket_etapa_padre'] ?? 0) > 0
    ) {
        $idRaizActual = (int) $etapasPorId[$idRaizActual]['id_ticket_etapa_padre'];
    }

    $etapaPrincipalActual = $etapasPorId[$idRaizActual] ?? null;

    if (!$etapaPrincipalActual && $etapasOficiales) {
        foreach ($etapasOficiales as $etapaOficial) {
            if (!in_array(
                (string) ($etapaOficial['estado'] ?? ''),
                ['completada', 'cancelada', 'bloqueada'],
                true
            )) {
                $etapaPrincipalActual = $etapaOficial;
                break;
            }
        }
    }

    if (!$etapaPrincipalActual && $etapasOficiales) {
        $etapaPrincipalActual = $etapasOficiales[count($etapasOficiales) - 1];
    }

    $hijosArbolVisual = $hijosPorPadre;
    $hijosArbolVisual[0] = [];

    foreach ($etapasOficiales as $etapaOficial) {
        $idEtapaOficial = (int) $etapaOficial['id_ticket_etapa'];
        foreach ($hijosPorPadre[$idEtapaOficial] ?? [] as $ticketDerivado) {
            $hijosArbolVisual[0][] = $ticketDerivado;
        }
    }

    if ($etapaPrincipalActual) {
        $idNodoDestinoPrincipal = (int) $etapaPrincipalActual['id_ticket_etapa'];
        $casoPrincipalVisual = $etapaPrincipalActual;
        $casoPrincipalVisual['id_ticket_etapa'] = 0;
        $casoPrincipalVisual['id_nodo_destino'] = $idNodoDestinoPrincipal;
        $casoPrincipalVisual['es_caso_principal'] = true;
        $casoPrincipalVisual['es_etapa_oficial'] = true;
        $casoPrincipalVisual['es_derivacion'] = false;
        $casoPrincipalVisual['codigo_caso'] = (string) $idTicketSeleccionado;
        $casoPrincipalVisual['total_etapas'] = count($etapasOficiales);
    }

    $conversaciones = flujoConversacionesDisponibles(
        $conn,
        $idTicketSeleccionado,
        $idUsuario,
        $rol
    );

    if ($casoSeleccionadoCerrado && $etapaActual) {
        $idCasoCerrado = (int) $etapaActual['id_ticket_etapa'];
        $conversaciones = array_values(array_filter(
            $conversaciones,
            static fn (array $conversacion): bool =>
                (int) $conversacion['id_ticket_etapa'] === $idCasoCerrado
        ));
    }
    $idChatPreferido = $idChatSeleccionado;

    if ($idChatPreferido < 1 && $etapaActual) {
        $idChatPreferido = (int) $etapaActual['id_ticket_etapa'];
    }

    foreach ($conversaciones as $conversacion) {
        if ((int) $conversacion['id_ticket_etapa'] === $idChatPreferido) {
            $conversacionActual = $conversacion;
            break;
        }
    }

    if (!$conversacionActual && $conversaciones) {
        $conversacionActual = $conversaciones[0];
    }

    $puedeEscribir = $ticketSeleccionado && $conversacionActual
        ? flujoPuedeEscribirNodo(
            $conn,
            $ticketSeleccionado,
            (int) $conversacionActual['id_ticket_etapa'],
            $idUsuario,
            $rol
        )
        : false;

    if ($etapaActual) {
        $checklist = flujoChecklistEtapa(
            $conn,
            (int) $etapaActual['id_ticket_etapa']
        );
        foreach ($checklist as $itemChecklist) {
            if ((int) ($itemChecklist['obligatorio'] ?? 0) !== 1) {
                continue;
            }

            $checklistObligatorioTotal++;
            if ((int) ($itemChecklist['completado'] ?? 0) !== 1) {
                $checklistObligatorioPendiente++;
            }
        }
    }

    if ($conversacionActual) {
        $idConversacionActual = (int) $conversacionActual['id_ticket_etapa'];
        $comunicaciones = flujoComunicacionesNodo(
            $conn,
            $idTicketSeleccionado,
            $idConversacionActual
        );
        $adjuntos = flujoAdjuntosNodo(
            $conn,
            $idTicketSeleccionado,
            $idConversacionActual
        );
    }

    if ($etapaActual) {
        $idCasoHistorial = (int) $etapaActual['id_ticket_etapa'];
        $stmt = $conn->prepare(
            "SELECT
                h.id_historial,
                h.accion,
                h.detalle,
                h.creado_en,
                COALESCE(u.nombre, 'Sistema') AS usuario,
                u.id_rol AS usuario_rol
             FROM solicitud_historial AS h
             LEFT JOIN usuarios AS u ON u.id_usuario = h.id_usuario
             WHERE h.id_ticket = ?
               AND h.id_ticket_etapa = ?
             ORDER BY h.creado_en, h.id_historial"
        );
        $stmt->bind_param('ii', $idTicketSeleccionado, $idCasoHistorial);
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($fila = $resultado->fetch_assoc()) {
            $historial[] = $fila;
        }
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "UPDATE notificaciones
         SET leida = 1, leida_en = COALESCE(leida_en, NOW())
         WHERE id_usuario = ? AND id_ticket = ? AND leida = 0"
    );
    $stmt->bind_param('ii', $idUsuario, $idTicketSeleccionado);
    $stmt->execute();
    $stmt->close();
}

$esGestorNodo = false;
$esCreadorNodo = false;
$estadoNodo = '';
$puedeGestionar = false;
$puedeCrearHijo = false;
$puedeCerrarNodo = false;
$tipoCalificacionNodo = null;
$esTicketDerivadoActual = false;
$etiquetaNodoActual = '';

if ($etapaActual) {
    $esTicketDerivadoActual = (int) (
        $etapaActual['id_ticket_etapa_padre'] ?? 0
    ) > 0;
    $etiquetaNodoActual = $esTicketDerivadoActual
        ? 'Ticket ' . (string) ($etapaActual['codigo_ticket'] ?? '')
        : 'Caso ' . (string) $idTicketSeleccionado;
    $esGestorNodo = $rol === 2
        && (int) $etapaActual['id_gestor'] === $idUsuario;
    $idCreadorNodo = (int) (
        $etapaActual['creado_por']
        ?? $ticketSeleccionado['id_usuario']
        ?? 0
    );
    $esCreadorNodo = $rol === 2 && $idCreadorNodo === $idUsuario;
    $estadoNodo = (string) $etapaActual['estado'];
    $puedeGestionar = $esGestorNodo
        && in_array(
            $estadoNodo,
            ['pendiente', 'en_proceso', 'en_espera_solicitante'],
            true
        );
    $puedeCrearHijo = $esGestorNodo
        && in_array(
            $estadoNodo,
            ['pendiente', 'en_proceso', 'en_espera_solicitante', 'pausada'],
            true
        );
    $puedeCerrarNodo = $esCreadorNodo && $estadoNodo === 'listo_cierre';
    $tipoCalificacionNodo = flujoTipoCalificacionCaso(
        $conn,
        (int) $etapaActual['id_ticket'],
        $etapaActual
    );
}

$mensajes = [
    'ticket_creado' => ['ok', 'Caso creado y primera etapa notificada.'],
    'caso_hijo_creado' => ['ok', 'Ticket derivado creado. El SLA de la etapa quedó pausado.'],
    'casos_hijos_creados' => ['ok', 'Derivaciones creadas. Los tickets derivados ya trabajan en paralelo y el SLA de la etapa quedó pausado.'],
    'mensaje_enviado' => ['ok', 'Mensaje enviado en la conversación privada del caso.'],
    'checklist_guardado' => ['ok', 'Checklist guardado correctamente.'],
    'etapa_lista' => ['ok', 'Caso marcado como listo. El creador fue notificado para calificarlo y decidir su cierre.'],
    'cierre_definitivo_solicitado' => ['ok', 'Solicitud enviada. El solicitante decidirá si cierra definitivamente el ticket por resolución en primer contacto.'],
    'caso_cerrado' => ['ok', 'Caso calificado y cerrado por su creador. El sistema verificó sus dependencias.'],
    'derivacion_reabierta' => ['ok', 'Caso reabierto. El corte anterior de Listo se anuló y el SLA volvió a contabilizar todo el tiempo.'],
    'calificacion_guardada' => ['ok', 'Calificación guardada. Complete las áreas restantes.'],
    'ticket_cerrado' => ['ok', 'Las etapas y sus tiempos de respuesta fueron calificados. El caso quedó cerrado definitivamente.'],
    'datos_incompletos' => ['error', 'Complete todos los campos obligatorios.'],
    'mensaje_vacio' => ['error', 'Escriba un mensaje o seleccione un archivo.'],
    'solicitud_invalida' => ['error', 'La solicitud no es válida.'],
    'error_operacion' => ['error', 'No fue posible completar la operación.'],
];
$mensajeActual = (string) ($_GET['msg'] ?? '');
$detalleError = trim((string) ($_SESSION['error_flujo'] ?? ''));
unset($_SESSION['error_flujo']);
$modoGestor = (string) ($_GET['modo'] ?? 'mis_tickets');
$panelCaso = (string) ($_GET['panel'] ?? 'resumen');
$abrirCierreDefinitivo = (string) ($_GET['encuesta'] ?? '') === '1';

if ($mensajeActual === 'mensaje_enviado' || $mensajeActual === 'mensaje_vacio') {
    $panelCaso = 'conversacion';
}

if (!in_array($panelCaso, ['resumen', 'conversacion', 'acciones', 'archivos'], true)) {
    $panelCaso = 'resumen';
}

if ($rol === 2) {
    if ($idTicketSeleccionado > 0) {
        $modoGestor = 'detalle';
    } else {
        $modoGestor = 'mis_tickets';
    }
}

$parametrosListadoGestor = [
    'modo' => 'mis_tickets',
    'bandeja' => $bandejaGestor,
];

if ($busquedaGestor !== '') {
    $parametrosListadoGestor['buscar'] = $busquedaGestor;
}
if ($estadoBusquedaGestor !== 'todos') {
    $parametrosListadoGestor['estado_busqueda'] = $estadoBusquedaGestor;
}

$rutaListadoGestor = 'flujoTicket.php?' . http_build_query($parametrosListadoGestor);
$tituloBandejaGestor = $bandejaGestor === 'cerrados'
    ? 'Casos cerrados'
    : 'Casos abiertos';
$descripcionBandejaGestor = $bandejaGestor === 'cerrados'
    ? 'Consulte únicamente los casos que ya se encuentran cerrados.'
    : 'Aquí aparecen todos los casos en estado diferente de cerrado.';

$rutaPanel = $rol === 1
    ? 'panelAdmin.php'
    : 'panelGestor.php';
$notificacionesNoLeidas = 0;

foreach ($notificaciones as $notificacion) {
    if ((int) $notificacion['leida'] === 0) {
        $notificacionesNoLeidas++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | Mesa de Servicio</title>
    <style>
        :root {
            --primary: #0f6fec;
            --primary-dark: #0a56bd;
            --navy: #102a43;
            --text: #243b53;
            --muted: #627d98;
            --bg: #f3f6fb;
            --surface: #fff;
            --border: #dce6f1;
            --soft: #eef5ff;
            --ok: #087443;
            --danger: #b42318;
            --warning: #a15c00;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: linear-gradient(135deg, #f5f8fc 0%, #edf3fa 100%);
            color: var(--text);
            font: 12px/1.4 Inter, "Segoe UI", Arial, sans-serif;
        }

        a { color: inherit; }

        .shell {
            width: min(1320px, calc(100% - 20px));
            margin: auto;
            padding: 8px 0 24px;
        }

        .topbar,
        .card,
        .modebar,
        .notice-panel {
            border: 1px solid var(--border);
            background: var(--surface);
            box-shadow: 0 8px 22px rgba(15, 45, 75, .055);
        }

        .topbar {
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
            background: linear-gradient(135deg, #0f6fec, #0b89e8);
            color: #fff;
            font-size: 11px;
            font-weight: 900;
            box-shadow: 0 6px 14px rgba(15, 111, 236, .22);
        }

        .topbar h1 {
            margin: 0;
            color: var(--navy);
            font-size: 16px;
            line-height: 1.1;
        }

        .topbar p {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 9px;
        }

        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .btn,
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 31px;
            padding: 6px 10px;
            border: 0;
            border-radius: 7px;
            font: inherit;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .btn.primary,
        button.primary {
            background: linear-gradient(135deg, var(--primary), #0b84e8);
            color: #fff;
            box-shadow: 0 5px 12px rgba(15, 111, 236, .18);
        }

        .btn.light,
        button.light {
            background: var(--soft);
            color: #225c93;
        }

        .btn.outline {
            border: 1px solid #cbdcf0;
            background: #fff;
            color: #225c93;
        }

        .btn.danger { background: #fff1f0; color: var(--danger); }

        .modebar {
            display: flex;
            gap: 4px;
            margin-top: 8px;
            padding: 4px;
            border-radius: 10px;
        }

        .mode-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 32px;
            padding: 6px 11px;
            border-radius: 7px;
            color: #315f8d;
            font-weight: 800;
            text-decoration: none;
        }

        .mode-link.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 5px 11px rgba(15, 111, 236, .18);
        }

        .count {
            min-width: 18px;
            padding: 1px 5px;
            border-radius: 999px;
            background: rgba(15, 111, 236, .1);
            text-align: center;
            font-size: 9px;
        }

        .mode-link.active .count { background: rgba(255, 255, 255, .2); }

        .alert {
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            font-weight: 700;
        }

        .alert.ok { background: #eaf8f1; color: var(--ok); }
        .alert.error { background: #fff0ee; color: var(--danger); }

        .notice-panel {
            margin-top: 8px;
            border-radius: 10px;
            overflow: hidden;
        }

        .notice-panel summary {
            display: flex;
            align-items: center;
            gap: 7px;
            min-height: 36px;
            padding: 7px 11px;
            color: var(--navy);
            font-weight: 850;
            cursor: pointer;
            list-style: none;
        }

        .notice-panel summary::-webkit-details-marker { display: none; }
        .notice-panel summary::before { content: "▸"; color: var(--primary); }
        .notice-panel[open] summary::before { content: "▾"; }
        .notice-panel summary .unread { color: var(--primary); font-size: 9px; }

        .notice-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 6px;
            padding: 0 8px 8px;
        }

        .notice-item {
            display: block;
            padding: 7px 9px;
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
        }

        .notice-item.unread { border-color: #80b5f2; background: #f2f8ff; }
        .notice-item strong { display: block; color: var(--navy); font-size: 10px; }
        .notice-item span { display: block; margin-top: 2px; color: var(--muted); font-size: 9px; }

        .card {
            margin-top: 8px;
            padding: 12px;
            border-radius: 11px;
        }

        .card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 9px;
        }

        .card h2,
        .card h3 {
            margin: 0;
            color: var(--navy);
        }

        .card h2 { font-size: 16px; }
        .card h3 { font-size: 13px; }
        .card-head p { margin: 2px 0 0; }
        .muted, .meta { color: var(--muted); }
        .meta { font-size: 9px; }

        [hidden] { display: none !important; }

        .ticket-wizard {
            max-width: 1280px;
            margin: 8px auto 0;
        }

        .wizard-progress {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 8px;
            padding: 7px 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 8px 22px rgba(15, 45, 75, .045);
        }

        .wizard-step {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #7890a6;
            font-size: 9px;
            font-weight: 850;
        }

        .wizard-step span {
            display: grid;
            width: 19px;
            height: 19px;
            place-items: center;
            border-radius: 50%;
            background: #eaf0f7;
            color: #526d82;
        }

        .wizard-step.active { color: var(--primary); }
        .wizard-step.active span { background: var(--primary); color: #fff; }
        .wizard-line { width: 22px; height: 1px; background: #d9e4ef; }

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
            gap: 9px;
        }

        .catalog-option {
            display: flex;
            min-height: 112px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
            color: var(--navy);
            box-shadow: 0 6px 16px rgba(15, 45, 75, .035);
            flex-direction: column;
            justify-content: center;
            gap: 7px;
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .catalog-option:hover {
            border-color: #8eb9eb;
            box-shadow: 0 9px 20px rgba(15, 111, 236, .09);
            transform: translateY(-2px);
        }

        .catalog-option.active {
            border-color: #175b91;
            background: linear-gradient(145deg, #23699f, #164f80);
            color: #fff;
            box-shadow: 0 10px 24px rgba(16, 75, 122, .22);
        }

        .catalog-visual {
            position: relative;
            display: grid;
            width: 43px;
            height: 43px;
            margin: 0 auto;
            place-items: center;
            overflow: hidden;
            border: 1px solid #d8e4f0;
            border-radius: 10px;
            background: #f7f9fc;
            color: #225c93;
            font-size: 16px;
            font-weight: 900;
        }

        .catalog-visual img {
            position: absolute;
            inset: 0;
            z-index: 1;
            width: 100%;
            height: 100%;
            padding: 5px;
            object-fit: contain;
            background: #fff;
        }

        .catalog-option strong { font-size: 11px; text-align: center; }
        .catalog-option small { color: var(--muted); font-size: 8px; text-align: center; }
        .catalog-option.active small { color: rgba(255, 255, 255, .78); }

        .service-panel { scroll-margin-top: 8px; }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 8px;
        }

        .service-option {
            display: flex;
            align-items: stretch;
            min-height: 105px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--text);
            text-align: left;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .service-option:hover,
        .service-option.active {
            border-color: #6fa9ec;
            box-shadow: 0 8px 18px rgba(15, 111, 236, .09);
            transform: translateY(-1px);
        }

        .service-option.active { background: #f2f8ff; }
        .service-option.unavailable {
            border-style: dashed;
            background: #fafbfd;
            box-shadow: none;
            cursor: not-allowed;
            opacity: .72;
        }
        .service-option.unavailable:hover { transform: none; }
        .service-name { display: block; margin: 0; color: var(--navy); font-size: 12px; font-weight: 850; }
        .service-option p { margin: 4px 0 8px; color: var(--muted); font-size: 9px; }

        .service-tags {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }

        .service-tag {
            padding: 3px 6px;
            border-radius: 999px;
            background: #eef5ff;
            color: #225c93;
            font-size: 8px;
            font-weight: 800;
        }

        .service-tag.warning { background: #fff4df; color: #925400; }

        .service-empty {
            grid-column: 1 / -1;
            padding: 18px;
            border: 1px dashed #cbd8e6;
            border-radius: 9px;
            background: #fafbfd;
            color: var(--muted);
            text-align: center;
        }

        .service-choose {
            align-self: flex-end;
            margin-top: 7px;
            color: var(--primary);
            font-size: 9px;
            font-weight: 900;
        }

        .form-card {
            max-width: 980px;
            margin-right: auto;
            margin-left: auto;
            scroll-margin-top: 8px;
        }

        .selected-service {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
            padding: 8px 10px;
            border: 1px solid #cfe1f4;
            border-radius: 8px;
            background: #f2f8ff;
        }

        .selected-service strong { display: block; color: var(--navy); }
        .selected-service small { display: block; margin-top: 2px; color: var(--muted); }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 8px 10px;
        }

        .field.full { grid-column: 1 / -1; }

        label {
            display: block;
            margin: 0 0 4px;
            color: var(--navy);
            font-weight: 800;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 34px;
            padding: 7px 9px;
            border: 1px solid #cad8e8;
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
            box-shadow: 0 0 0 3px rgba(15, 111, 236, .09);
        }

        textarea { min-height: 76px; resize: vertical; }
        input[type="file"] { padding: 5px 7px; }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
            margin-top: 9px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .manager-case-filters {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) minmax(190px, .34fr) auto;
            gap: 10px;
            align-items: end;
            margin: 0 0 12px;
            padding: 11px;
            border: 1px solid #dbe7f2;
            border-radius: 11px;
            background: #f8fbff;
        }

        .manager-filter-field { display: grid; gap: 5px; }
        .manager-filter-field label { color: var(--navy); font-size: 10px; font-weight: 850; }
        .manager-filter-field input,
        .manager-filter-field select { width: 100%; height: 38px; padding: 8px 10px; border-radius: 9px; }
        .manager-search-control { position: relative; }
        .manager-search-control input { padding-right: 38px; }
        .manager-filter-clear {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 28px;
            height: 28px;
            min-height: 28px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 0;
            border-radius: 7px;
            color: #617b91;
            background: #eef4fa;
            cursor: pointer;
            font-size: 17px;
        }

        .manager-filter-count {
            min-width: 118px;
            padding: 10px 11px;
            color: #486985;
            text-align: center;
            font-size: 10px;
            font-weight: 850;
            white-space: nowrap;
        }

        .manager-filter-empty td { padding: 28px; color: var(--muted); text-align: center; }

        body.manager-ticket-modal-open { overflow: hidden; }
        .manager-ticket-modal {
            position: fixed;
            inset: 0;
            z-index: 2147483100;
            display: grid;
            place-items: center;
            padding: 18px;
            background: rgba(7, 25, 45, .62);
            backdrop-filter: blur(4px);
        }

        .manager-ticket-modal-window {
            width: min(1280px, 100%);
            height: min(900px, calc(100dvh - 36px));
            max-height: calc(100dvh - 36px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #cbdbea;
            border-radius: 16px;
            background: #f4f7fb;
            box-shadow: 0 30px 90px rgba(4, 25, 48, .36);
        }

        .manager-ticket-modal-bar {
            min-height: 54px;
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            background: #fff;
        }

        .manager-ticket-modal-bar strong,
        .manager-ticket-modal-bar small { display: block; }
        .manager-ticket-modal-bar strong { color: var(--navy); font-size: 14px; }
        .manager-ticket-modal-bar small { margin-top: 2px; color: var(--muted); font-size: 9px; }
        .manager-ticket-modal-close {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 34px;
            padding: 7px 10px;
            border: 1px solid #cbdbea;
            border-radius: 9px;
            color: #315b7e;
            background: #fff;
            font: inherit;
            font-weight: 850;
            cursor: pointer;
        }

        .manager-ticket-modal-close span:first-child { font-size: 18px; line-height: 1; }
        .manager-ticket-modal-scroll {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            gap: 10px;
            padding: 12px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .manager-ticket-modal-scroll > .card,
        .manager-ticket-modal-scroll > .closure-banner { margin: 0; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th,
        td {
            padding: 8px 9px;
            border-bottom: 1px solid #e8eef5;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f7f9fc;
            color: #526d82;
            font-size: 9px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #f8fbff; }
        .ticket-title { color: var(--navy); font-weight: 800; }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 3px 7px;
            background: var(--soft);
            color: #225c93;
            font-size: 9px;
            font-weight: 850;
        }

        .empty {
            padding: 22px 10px;
            color: var(--muted);
            text-align: center;
        }

        .ticket-summary {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: start;
        }

        .ticket-summary h2 { margin-top: 5px; }
        .ticket-summary p { margin: 6px 0 0; }

        .description {
            padding-top: 8px;
            border-top: 1px solid #edf1f6;
            white-space: pre-wrap;
        }

        .timeline {
            display: grid;
            gap: 6px;
            padding: 2px 0 4px;
        }

        .audit-log {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(245px, 1fr));
            gap: 6px;
        }

        .audit-item {
            position: relative;
            min-height: 76px;
            padding: 8px 9px 8px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fbfdff;
        }

        .audit-item::before {
            content: "";
            position: absolute;
            top: 9px;
            bottom: 9px;
            left: 6px;
            width: 3px;
            border-radius: 999px;
            background: #6aa8ee;
        }

        .audit-item strong { color: var(--navy); }
        .audit-item p { margin: 4px 0; white-space: pre-wrap; }

        .action-summary {
            margin-bottom: 10px;
            padding: 11px;
            border: 1px solid #bfd6ea;
            border-radius: 10px;
            background: #f6fbff;
        }
        .action-summary-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 9px;
            color: var(--navy);
        }
        .action-summary-grid,
        .action-rating-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 6px;
        }
        .action-summary-grid > div,
        .action-rating-summary > div {
            min-height: 52px;
            padding: 7px 8px;
            border: 1px solid #dce8f2;
            border-radius: 8px;
            background: #fff;
        }
        .action-summary-grid span,
        .action-rating-summary span {
            display: block;
            color: var(--muted);
            font-size: 8.5px;
            font-weight: 750;
            text-transform: uppercase;
        }
        .action-summary-grid strong,
        .action-rating-summary strong {
            display: block;
            margin-top: 4px;
            color: var(--navy);
            font-size: 10px;
        }
        .action-rating-summary { margin-top: 7px; }
        .action-detail { margin-top: 7px; }
        .action-detail summary {
            cursor: pointer;
            color: #155f9e;
            font-size: 9.5px;
            font-weight: 800;
        }
        .action-detail p {
            margin: 6px 0 0;
            padding: 8px;
            border-radius: 7px;
            background: #fff;
            white-space: pre-wrap;
        }

        .step {
            position: relative;
            width: calc(100% - min(calc(var(--level, 0) * 28px), 55%));
            margin-left: min(calc(var(--level, 0) * 28px), 55%);
            min-height: 86px;
            padding: 8px 9px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #fff;
        }

        .step::before {
            content: "";
            position: absolute;
            top: 18px;
            right: 100%;
            width: min(calc(var(--level, 0) * 28px), 55%);
            border-top: 2px solid #b8cce0;
        }

        .step-link {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            z-index: 1;
        }

        .step-content { position: relative; z-index: 2; pointer-events: none; }
        .step.selected { box-shadow: 0 0 0 2px rgba(15,111,236,.22); }

        .step.bloqueada { opacity: .55; background: #f7f9fb; }
        .step.pendiente,
        .step.en_proceso,
        .step.en_espera_solicitante { border-color: #70abef; background: #f2f8ff; }
        .step.pausada { border-color: #e2aa55; background: #fff8ec; }
        .step.completada { border-color: #7bc6a2; background: #f0faf5; }
        .step strong { display: block; margin-bottom: 3px; color: var(--navy); }
        .step .meta { margin-top: 2px; }

        .tree-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .case-tree {
            display: grid;
            gap: 7px;
            padding: 2px 0 4px;
        }

        .case-branch,
        .case-leaf {
            margin: 0;
            border: 0;
        }

        .case-branch > summary {
            list-style: none;
            cursor: pointer;
        }

        .case-branch > summary::-webkit-details-marker { display: none; }

        .case-row {
            display: grid;
            grid-template-columns: 26px minmax(220px, 1.25fr) minmax(230px, .9fr) auto;
            gap: 7px 10px;
            align-items: center;
            min-height: 66px;
            padding: 9px 10px;
            border: 1px solid var(--border);
            border-left: 4px solid #8ba6bf;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(15, 45, 75, .035);
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .case-branch > summary:hover,
        .case-leaf .case-row:hover {
            border-color: #9fc2e9;
            background: #fbfdff;
            box-shadow: 0 7px 18px rgba(15, 83, 148, .08);
        }

        .case-branch.selected > summary,
        .case-leaf.selected .case-row {
            border-color: #4c96ea;
            background: #f2f8ff;
            box-shadow: 0 0 0 2px rgba(15, 111, 236, .14);
        }

        .case-branch.pausada > summary,
        .case-leaf.pausada .case-row { border-left-color: #e2a03d; }
        .case-branch.completada > summary,
        .case-leaf.completada .case-row { border-left-color: #42a878; }
        .case-branch.en_proceso > summary,
        .case-leaf.en_proceso .case-row { border-left-color: #368ce4; }
        .case-branch.pendiente > summary,
        .case-leaf.pendiente .case-row { border-left-color: #6ba7e7; }
        .case-branch.bloqueada > summary,
        .case-leaf.bloqueada .case-row {
            border-left-color: #aebdcb;
            background: #f8fafc;
            opacity: .72;
        }

        .branch-chevron,
        .branch-dot {
            display: grid;
            width: 24px;
            height: 24px;
            place-items: center;
            border-radius: 7px;
            background: #edf5ff;
            color: var(--primary);
            font-size: 12px;
            font-weight: 900;
        }

        .branch-chevron::before { content: "+"; }
        .case-branch[open] > summary .branch-chevron::before { content: "−"; }
        .branch-dot::before { content: "•"; }

        .case-row-main { min-width: 0; }

        .case-code-line {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 2px;
        }

        .case-code {
            color: #0b5fae;
            font-size: 10px;
            font-weight: 900;
        }

        .case-status {
            display: inline-flex;
            padding: 2px 6px;
            border-radius: 999px;
            background: #eef3f8;
            color: #526d82;
            font-size: 8px;
            font-weight: 850;
        }

        .case-status.completada { background: #e7f7ef; color: #087443; }
        .case-status.listo_cierre { background: #fff5d8; color: #8a5a00; }
        .checklist-gate {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin: 0 0 14px;
            padding: 12px 14px;
            border: 1px solid #f2c36b;
            border-radius: 12px;
            background: #fff8e8;
            color: #744a00;
            font-size: 14px;
            line-height: 1.45;
        }
        .checklist-gate[hidden] { display: none; }
        .checklist-gate-icon { font-size: 20px; line-height: 1; }
        .checklist-gated-button[aria-disabled="true"] {
            opacity: .58;
            cursor: not-allowed;
            filter: grayscale(.15);
        }
        .checklist-row-missing {
            border-left: 3px solid #e7a52b;
            padding-left: 9px;
        }
        .case-status.pausada { background: #fff2dc; color: #9a5a00; }
        .case-status.en_proceso,
        .case-status.pendiente { background: #e8f3ff; color: #0b63b6; }

        .case-title,
        .case-service { display: block; }
        .case-title { overflow: hidden; color: var(--navy); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .case-service { margin-top: 1px; overflow: hidden; color: var(--text); text-overflow: ellipsis; white-space: nowrap; }

        .case-row-meta {
            display: grid;
            gap: 2px;
            color: var(--muted);
            font-size: 9px;
        }

        .case-row-meta b { color: #46647f; }
        .case-open { position: relative; z-index: 2; min-width: 78px; }

        .branch-action {
            grid-column: 2 / 4;
            color: #1767ad;
            font-size: 9px;
            font-weight: 850;
        }

        .case-branch:not([open]) .when-open,
        .case-branch[open] .when-closed { display: none; }

        .case-children {
            display: grid;
            gap: 7px;
            margin: 7px 0 1px 18px;
            padding-left: 17px;
            border-left: 2px solid #c8dbed;
        }

        .case-children > .case-branch,
        .case-children > .case-leaf { position: relative; }

        .case-children > .case-branch::before,
        .case-children > .case-leaf::before {
            content: "";
            position: absolute;
            top: 31px;
            right: 100%;
            width: 17px;
            border-top: 2px solid #c8dbed;
        }

        .case-dialog {
            width: min(1180px, calc(100% - 28px));
            max-width: none;
            height: min(850px, calc(100dvh - 28px));
            max-height: none;
            margin: auto;
            padding: 0;
            border: 0;
            border-radius: 15px;
            background: #f4f7fb;
            color: var(--text);
            box-shadow: 0 28px 90px rgba(9, 32, 56, .32);
        }

        .case-dialog::backdrop {
            background: rgba(8, 28, 49, .66);
            backdrop-filter: blur(3px);
        }

        .case-modal-shell {
            display: flex;
            height: 100%;
            flex-direction: column;
            overflow: hidden;
        }

        .case-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 15px 17px 12px;
            border-bottom: 1px solid var(--border);
            background: #fff;
        }

        .case-modal-title-line {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
        }

        .case-modal-header h2 { margin: 0; color: var(--navy); font-size: 18px; }
        .case-modal-header p { margin: 3px 0 0; color: var(--muted); }

        .modal-close {
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            min-height: 34px;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #fff;
            color: #526d82;
            font-size: 18px;
        }

        .case-tabs {
            display: flex;
            gap: 4px;
            padding: 7px 12px 0;
            border-bottom: 1px solid var(--border);
            background: #fff;
            overflow-x: auto;
        }

        .case-tab {
            min-height: 36px;
            padding: 7px 11px;
            border-bottom: 3px solid transparent;
            border-radius: 7px 7px 0 0;
            background: transparent;
            color: #526d82;
            white-space: nowrap;
        }

        .case-tab.active {
            border-bottom-color: var(--primary);
            background: #eef6ff;
            color: #0b5fae;
        }

        .case-modal-content {
            flex: 1;
            min-height: 0;
            padding: 12px;
            overflow-y: auto;
        }

        .case-pane { display: grid; gap: 10px; }
        .case-pane[hidden] { display: none !important; }

        .case-dialog-panes,
        .case-dialog-panes > div { display: contents; }

        .case-dialog .card {
            margin: 0;
            padding: 12px;
            border-radius: 11px;
            box-shadow: none;
        }

        .case-dialog .case-pane + .case-pane { margin-top: 0; }

        .case-panel-section {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: #fff;
        }

        .case-panel-section > h3 { margin: 0 0 8px; color: var(--navy); font-size: 13px; }

        .case-panel-toggle {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            overflow: hidden;
        }

        .case-panel-toggle > summary {
            padding: 10px 12px;
            color: var(--navy);
            font-weight: 850;
            cursor: pointer;
            list-style: none;
        }

        .case-panel-toggle > summary::-webkit-details-marker { display: none; }
        .case-panel-toggle > summary::before { content: "+"; display: inline-block; width: 18px; color: var(--primary); }
        .case-panel-toggle[open] > summary::before { content: "−"; }
        .case-panel-toggle-body { padding: 0 12px 12px; }

        .case-panel-toggle.derivation-panel,
        .case-panel-toggle.derivation-panel[open] {
            position: relative;
            z-index: 25;
            overflow: visible;
        }

        .case-modal-content .stage-summary {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin: 0;
        }

        .case-modal-content .chat { max-height: 410px; }
        .case-modal-content .audit-log { grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); }

        body.case-modal-open { overflow: hidden; }

        .detail-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(300px, .75fr);
            gap: 8px;
            align-items: start;
        }

        .detail-grid .card { margin-top: 0; margin-bottom: 8px; }

        .case-dialog .detail-grid.case-dialog-panes,
        .case-dialog .detail-grid.case-dialog-panes > div {
            display: contents;
        }

        .case-dialog .detail-grid.case-dialog-panes .card { margin-bottom: 0; }

        .chat {
            max-height: 300px;
            overflow-y: auto;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f7f9fc;
        }

        .conversation-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 6px;
            margin-bottom: 8px;
        }

        .conversation-tab {
            display: block;
            padding: 8px 9px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fbfdff;
            color: var(--navy);
            text-decoration: none;
        }

        .conversation-tab.active {
            border-color: #6aa8ee;
            background: #edf6ff;
            box-shadow: 0 0 0 1px rgba(15,111,236,.13);
        }

        .conversation-tab strong,
        .conversation-tab span { display: block; }
        .conversation-tab span { margin-top: 2px; color: var(--muted); font-size: 9px; }

        .message {
            max-width: 86%;
            margin: 6px 0;
            padding: 7px 9px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #fff;
        }

        .message.mine { margin-left: auto; border-color: #91bdee; background: #edf6ff; }
        .message-head { color: #225c93; font-size: 9px; font-weight: 850; }
        .message p { margin: 3px 0; white-space: pre-wrap; }

        .compose {
            display: grid;
            grid-template-columns: 1fr 220px auto;
            gap: 6px;
            align-items: end;
            margin-top: 7px;
        }

        .compose textarea { min-height: 48px; }
        .compose .btn { min-height: 34px; }

        .derivation-list {
            display: grid;
            gap: 8px;
            padding: 8px;
        }

        .derivation-row {
            display: grid;
            grid-template-columns:
                minmax(155px, .7fr)
                minmax(225px, 1fr)
                minmax(240px, 1.15fr)
                auto;
            gap: 7px;
            align-items: end;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #fbfdff;
        }

        .derivation-row textarea { min-height: 58px; }
        .derivation-row .remove-derivation { align-self: end; }

        .derivation-service-combobox {
            position: relative;
        }

        .derivation-service-toggle {
            position: absolute;
            z-index: 2;
            top: 1px;
            right: 1px;
            bottom: 1px;
            width: 34px;
            padding: 0;
            border: 0;
            border-left: 1px solid #d5e1ec;
            border-radius: 0 5px 5px 0;
            background: #f8fbff;
            color: #315a7e;
            cursor: pointer;
            font-size: 13px;
            line-height: 1;
            box-shadow: none;
        }

        .derivation-service-toggle::before {
            content: "▾";
        }

        .derivation-service-toggle[aria-expanded="true"]::before {
            content: "▴";
        }

        .derivation-service-toggle:hover,
        .derivation-service-toggle:focus-visible {
            background: #e8f2fd;
            color: #084f91;
            outline: 2px solid #8bc2f5;
            outline-offset: -2px;
        }

        .derivation-service-toggle:disabled {
            background: #eef3f8;
            color: #9cabb9;
            cursor: not-allowed;
        }

        .derivation-service-search {
            padding-right: 42px;
        }

        .derivation-service-search:disabled {
            cursor: not-allowed;
            background: #eef3f8;
            color: #74889d;
        }

        .derivation-service-menu {
            position: absolute;
            z-index: 1000;
            top: auto;
            right: 0;
            bottom: calc(100% + 4px);
            left: 0;
            max-height: min(230px, 42vh);
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 3px 0;
            border: 1px solid #8da9c2;
            border-radius: 5px;
            background: #fff;
            box-shadow: 0 -12px 30px rgba(23, 75, 120, .2);
        }

        .derivation-service-option {
            display: block;
            width: 100%;
            min-height: 30px;
            padding: 7px 10px;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: #123c62;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.35;
            text-align: left;
            box-shadow: none;
        }

        .derivation-service-option:hover,
        .derivation-service-option:focus {
            background: #e3effc;
            color: #084f91;
            outline: none;
        }

        .derivation-service-empty,
        .derivation-service-help {
            color: var(--muted);
            font-size: 9px;
        }

        .derivation-service-empty {
            padding: 10px;
            text-align: center;
        }

        .derivation-service-help {
            display: block;
            margin-top: 4px;
            line-height: 1.35;
        }

        .derivation-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 7px;
            padding: 0 8px 8px;
        }

        .closed-case {
            padding: 10px;
            border: 1px solid #8bc9aa;
            border-radius: 9px;
            background: #effaf5;
            color: #22543d;
        }

        .closed-case strong { color: #145c3b; }
        .closed-case p { margin: 5px 0 0; white-space: pre-wrap; }

        .ready-approval {
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #e8c66d;
            border-radius: 11px;
            background: #fffaf0;
        }
        .ready-approval-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .ready-approval-head strong { color: #684500; }
        .ready-approval-head p { margin: 4px 0 0; color: #7b6840; }
        .case-rating-form {
            margin-top: 12px;
            padding: 11px;
            border: 1px solid #d9e5ef;
            border-radius: 9px;
            background: #fff;
        }
        .case-rating-form h3 { margin: 0; color: var(--navy); font-size: 13px; }
        .case-rating-form > p { margin: 4px 0 0; }
        .approval-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
        .reopen-form {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #f0cbc6;
            border-radius: 9px;
            background: #fff8f7;
        }
        .reopen-form label { display: block; margin-bottom: 4px; color: #8f3e37; font-size: 9px; font-weight: 800; }
        .reopen-form textarea { width: 100%; min-height: 62px; }

        .files { display: grid; gap: 6px; }

        .file {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 7px 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .file strong { color: var(--navy); font-size: 10px; }

        .stage-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px;
            margin: 8px 0;
        }

        .stage-data {
            padding: 7px 8px;
            border-radius: 7px;
            background: #f6f9fc;
        }

        .stage-data span { display: block; color: var(--muted); font-size: 8px; }
        .stage-data strong { display: block; margin-top: 1px; color: var(--navy); font-size: 10px; }

        .check {
            padding: 7px 0;
            border-bottom: 1px solid #edf1f6;
        }

        .check label { display: flex; align-items: flex-start; gap: 7px; margin: 0 0 5px; }
        .check input[type="checkbox"] { width: auto; min-height: 0; margin-top: 2px; }

        .survey {
            margin-top: 7px;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .survey select { max-width: 180px; }

        .closure-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 10px;
            padding: 13px 15px;
            border: 1px solid #f2cf75;
            border-radius: 12px;
            color: #704b00;
            background: linear-gradient(135deg, #fff9e8, #fff4cb);
            box-shadow: 0 6px 18px rgba(130, 92, 0, .08);
        }

        .closure-banner strong { display: block; color: #594000; font-size: 13px; }
        .closure-banner p { margin: 3px 0 0; font-size: 10px; }

        .final-rating-dialog {
            width: min(1040px, calc(100% - 20px));
            max-height: calc(100vh - 24px);
            padding: 0;
            overflow: hidden;
            border: 0;
            border-radius: 15px;
            color: var(--text);
            background: var(--surface);
            box-shadow: 0 24px 80px rgba(16, 42, 67, .28);
        }

        .final-rating-dialog::backdrop { background: rgba(16, 42, 67, .58); }
        .final-rating-shell { display: flex; max-height: calc(100vh - 24px); flex-direction: column; }
        .final-rating-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 15px 17px;
            color: #fff;
            background: linear-gradient(135deg, #0f6fec, #0a4fae);
        }
        .final-rating-head h2 { margin: 0; color: #fff; font-size: 17px; }
        .final-rating-head p { margin: 4px 0 0; color: #dcecff; font-size: 10px; }
        .final-rating-body { padding: 13px 15px; overflow: auto; background: #f6f9fc; }
        .final-rating-intro {
            margin-bottom: 10px;
            padding: 10px 12px;
            border: 1px solid #dbe7f3;
            border-radius: 10px;
            color: #34536d;
            background: #fff;
        }
        .final-rating-intro strong { color: var(--navy); }
        .rating-case-list { display: grid; gap: 9px; }
        .rating-case {
            padding: 11px;
            border: 1px solid #dce6f0;
            border-radius: 11px;
            background: #fff;
        }
        .rating-case-head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: start;
        }
        .rating-case-title { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .rating-case-title strong { color: var(--navy); font-size: 12px; }
        .rating-case-title span { color: var(--muted); font-size: 9.5px; }
        .rating-case-times {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
            flex-wrap: wrap;
        }
        .rating-case-times span {
            padding: 4px 7px;
            border-radius: 999px;
            color: #315875;
            background: #eef5fb;
            font-size: 8.5px;
            font-weight: 750;
        }
        .rating-case-times .inside { color: #087443; background: #eaf8f1; }
        .rating-case-times .outside { color: #a33b32; background: #fff0ee; }
        .rating-fields {
            display: grid;
            grid-template-columns: minmax(170px, .7fr) minmax(170px, .7fr) minmax(260px, 1.6fr);
            gap: 8px;
            margin-top: 9px;
        }
        .rating-fields label { display: block; margin-bottom: 4px; color: #587087; font-size: 9px; font-weight: 800; }
        .rating-fields select,
        .rating-fields textarea { width: 100%; }
        .rating-fields textarea { min-height: 54px; }
        .final-rating-actions {
            display: flex;
            justify-content: flex-end;
            gap: 7px;
            position: sticky;
            bottom: -13px;
            margin: 12px -15px -13px;
            padding: 10px 15px;
            border-top: 1px solid #dce6f0;
            background: #fff;
        }

        @media (max-width: 940px) {
            .detail-grid { grid-template-columns: 1fr; }
            .compose { grid-template-columns: 1fr; }
            .derivation-row { grid-template-columns: 1fr; }
            .topbar { align-items: flex-start; }
            .case-row { grid-template-columns: 26px minmax(0, 1fr) auto; }
            .case-row-main { grid-column: 2; }
            .case-row-meta { grid-column: 2; }
            .case-open { grid-column: 3; grid-row: 1 / span 2; }
            .branch-action { grid-column: 2 / 4; }
            .case-modal-content .stage-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .shell { width: min(100% - 12px, 1320px); padding-top: 6px; }
            .topbar { flex-direction: column; }
            .topbar .actions { width: 100%; }
            .topbar .actions .btn { flex: 1 1 auto; }
            .closure-banner { align-items: stretch; flex-direction: column; }
            .rating-case-head,
            .rating-fields { grid-template-columns: 1fr; }
            .rating-case-times { justify-content: flex-start; }
            .modebar { overflow-x: auto; }
            .mode-link { flex: 1 0 auto; justify-content: center; }
            .wizard-progress { overflow-x: auto; }
            .wizard-step { flex: 0 0 auto; }
            .catalog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .service-grid { grid-template-columns: 1fr; }
            .selected-service { align-items: flex-start; flex-direction: column; }
            .form-grid { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
            .card-head, .ticket-summary { grid-template-columns: 1fr; flex-direction: column; }
            .stage-summary { grid-template-columns: 1fr; }
            .file { align-items: flex-start; flex-direction: column; }
            .tree-toolbar { width: 100%; }
            .tree-toolbar .btn { flex: 1; }
            .case-row { grid-template-columns: 24px minmax(0, 1fr); }
            .case-row-main,
            .case-row-meta,
            .case-open,
            .branch-action { grid-column: 2; grid-row: auto; }
            .case-open { justify-self: start; }
            .case-children { margin-left: 11px; padding-left: 12px; }
            .case-children > .case-branch::before,
            .case-children > .case-leaf::before { width: 12px; }
            .case-dialog { width: calc(100% - 10px); height: calc(100dvh - 10px); }
            .case-modal-header { padding: 12px; }
            .case-modal-header h2 { font-size: 15px; }
            .case-modal-content { padding: 7px; }
            .case-modal-content .stage-summary { grid-template-columns: 1fr; }
            .manager-case-filters { grid-template-columns: 1fr; }
            .manager-filter-count { padding: 0 2px; text-align: left; }
            .manager-ticket-modal { padding: 6px; }
            .manager-ticket-modal-window { max-height: calc(100dvh - 12px); border-radius: 12px; }
            .manager-ticket-modal-bar { padding: 8px 10px; }
            .manager-ticket-modal-close-label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
            .manager-ticket-modal-scroll { padding: 7px; }
        }
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">MS</span>
            <div>
                <h1>Casos</h1>
                <p><?= escaparFlujo($nombreUsuario) ?> · <?= $rol === 1 ? 'Administrador' : 'Gestor' ?></p>
            </div>
        </div>
        <div class="actions">
            <a class="btn outline" href="<?= escaparFlujo($rutaPanel) ?>">← Volver al panel</a>
            <?php if ($rol === 2 && $idTicketSeleccionado > 0): ?>
                <a class="btn light" href="<?= escaparFlujo($rutaListadoGestor) ?>"><?= escaparFlujo($tituloBandejaGestor) ?></a>
            <?php elseif ($rol === 1): ?>
                <?php if ($rutaBaseTickets === 'solicitudes.php' && $idTicketSeleccionado > 0): ?>
                    <a class="btn light" href="solicitudes.php">Listado</a>
                <?php endif; ?>
                <a class="btn light" href="descargarSolicitudesExcel.php">Descargar base</a>
                <a class="btn light" href="procesos.php">Configurar tickets</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($rol === 2): ?>
        <nav class="modebar" aria-label="Opciones de tickets">
            <a class="mode-link <?= $bandejaGestor === 'abiertos' ? 'active' : '' ?>" href="flujoTicket.php?modo=mis_tickets&amp;bandeja=abiertos">
                Casos abiertos <span class="count"><?= $totalCasosAbiertos ?></span>
            </a>
            <a class="mode-link <?= $bandejaGestor === 'cerrados' ? 'active' : '' ?>" href="flujoTicket.php?modo=mis_tickets&amp;bandeja=cerrados">
                Casos cerrados <span class="count"><?= $totalCasosCerrados ?></span>
            </a>
        </nav>
    <?php endif; ?>

    <?php if (isset($mensajes[$mensajeActual])): ?>
        <div class="alert <?= escaparFlujo($mensajes[$mensajeActual][0]) ?>">
            <?= escaparFlujo($mensajes[$mensajeActual][1]) ?>
            <?php if ($detalleError !== '' && $mensajeActual === 'error_operacion'): ?>
                <div><?= escaparFlujo($detalleError) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($notificaciones): ?>
        <details class="notice-panel">
            <summary>
                Notificaciones
                <span class="count"><?= count($notificaciones) ?></span>
                <?php if ($notificacionesNoLeidas > 0): ?>
                    <span class="unread"><?= $notificacionesNoLeidas ?> sin leer</span>
                <?php endif; ?>
            </summary>
            <div class="notice-grid">
                <?php foreach ($notificaciones as $notificacion): ?>
                    <a
                        class="notice-item <?= (int) $notificacion['leida'] === 0 ? 'unread' : '' ?>"
                        href="<?= escaparFlujo(urlTicketFlujo($rutaBaseTickets, $rol, (int) $notificacion['id_ticket'])) ?>"
                    >
                        <strong><?= escaparFlujo($notificacion['titulo']) ?></strong>
                        <span><?= escaparFlujo($notificacion['mensaje']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <?php if (false): ?>
        <div class="ticket-wizard">
            <div class="wizard-progress" aria-label="Pasos para crear el ticket">
                <div class="wizard-step active" data-wizard-step="1"><span>1</span> Área</div>
                <i class="wizard-line" aria-hidden="true"></i>
                <div class="wizard-step" data-wizard-step="2"><span>2</span> Servicio</div>
                <i class="wizard-line" aria-hidden="true"></i>
                <div class="wizard-step" data-wizard-step="3"><span>3</span> Solicitud</div>
            </div>

            <section class="card">
                <div class="card-head">
                    <div>
                        <h2>Seleccione el catálogo o área</h2>
                        <p class="muted">Las opciones se actualizan automáticamente con la configuración del administrador.</p>
                    </div>
                    <span class="badge">Paso 1</span>
                </div>

                <?php if (!$catalogosDisponibles): ?>
                    <div class="empty">
                        No hay servicios disponibles. El administrador debe revisar el gestor, SLA y estado de los servicios.
                    </div>
                <?php else: ?>
                    <div class="catalog-grid" id="catalogGrid">
                        <?php foreach ($catalogosDisponibles as $catalogo): ?>
                            <button
                                type="button"
                                class="catalog-option"
                                data-catalogo="<?= (int) $catalogo['id_catalogo'] ?>"
                                data-nombre="<?= escaparFlujo($catalogo['nombre']) ?>"
                                aria-pressed="false"
                            >
                                <span class="catalog-visual" aria-hidden="true">
                                    <span><?= escaparFlujo(strtoupper(substr((string) $catalogo['nombre'], 0, 1))) ?></span>
                                    <?php if ($catalogo['imagen'] !== ''): ?>
                                        <img
                                            src="<?= escaparFlujo(seguridadUrlImagenCatalogo(
                                                (int) $catalogo['id_catalogo'],
                                                $catalogo['imagen']
                                            )) ?>"
                                            alt=""
                                            onerror="this.style.display='none'"
                                        >
                                    <?php endif; ?>
                                </span>
                                <strong><?= escaparFlujo($catalogo['nombre']) ?></strong>
                                <small><?= count($catalogo['servicios']) ?> servicio<?= count($catalogo['servicios']) === 1 ? '' : 's' ?> disponible<?= count($catalogo['servicios']) === 1 ? '' : 's' ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($catalogosDisponibles): ?>
                <section class="card service-panel" id="servicePanel" hidden>
                    <div class="card-head">
                        <div>
                            <h2 id="servicePanelTitle">Servicios disponibles</h2>
                            <p class="muted">Seleccione el servicio que necesita solicitar.</p>
                        </div>
                        <span class="badge">Paso 2</span>
                    </div>
                    <div class="service-grid" id="serviceGrid">
                        <?php foreach ($catalogosDisponibles as $catalogo): ?>
                            <?php if (!$catalogo['servicios']): ?>
                                <div class="service-empty" data-catalogo="<?= (int) $catalogo['id_catalogo'] ?>" hidden>
                                    Este catálogo todavía no tiene servicios activos.
                                </div>
                            <?php endif; ?>
                            <?php foreach ($catalogo['servicios'] as $servicio): ?>
                                <?php if ($servicio['flujos']): ?>
                                    <?php foreach ($servicio['flujos'] as $flujo): ?>
                                        <button
                                            type="button"
                                            class="service-option"
                                            data-catalogo="<?= (int) $catalogo['id_catalogo'] ?>"
                                            data-proceso="<?= (int) $flujo['id_proceso'] ?>"
                                            data-catalogo-nombre="<?= escaparFlujo($catalogo['nombre']) ?>"
                                            data-servicio-nombre="<?= escaparFlujo($servicio['servicio_nombre']) ?>"
                                            data-flujo-nombre="<?= escaparFlujo($flujo['nombre']) ?>"
                                            hidden
                                        >
                                            <span>
                                                <span class="service-name"><?= escaparFlujo($servicio['servicio_nombre']) ?></span>
                                                <p><?= escaparFlujo($servicio['servicio_descripcion'] ?: $flujo['descripcion']) ?></p>
                                            </span>
                                            <span>
                                                <span class="service-tags">
                                                    <span class="service-tag">Flujo: <?= escaparFlujo($flujo['nombre']) ?></span>
                                                    <span class="service-tag"><?= (int) $flujo['etapas'] ?> etapa<?= (int) $flujo['etapas'] === 1 ? '' : 's' ?> secuencial<?= (int) $flujo['etapas'] === 1 ? '' : 'es' ?></span>
                                                    <?php if ($servicio['servicio_sla']): ?>
                                                        <span class="service-tag">SLA: <?= escaparFlujo($servicio['servicio_sla']) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="service-choose">Seleccionar servicio →</span>
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <button
                                        type="button"
                                        class="service-option unavailable"
                                        data-catalogo="<?= (int) $catalogo['id_catalogo'] ?>"
                                        hidden
                                        disabled
                                    >
                                        <span>
                                            <span class="service-name"><?= escaparFlujo($servicio['servicio_nombre']) ?></span>
                                            <p><?= escaparFlujo($servicio['servicio_descripcion']) ?></p>
                                        </span>
                                        <span>
                                            <span class="service-tags">
                                                <span class="service-tag warning">Sin flujo de inicio configurado</span>
                                                <?php if ($servicio['servicio_sla']): ?>
                                                    <span class="service-tag">SLA: <?= escaparFlujo($servicio['servicio_sla']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="service-choose">Configurar en Flujos por servicio</span>
                                        </span>
                                    </button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card form-card" id="ticketFormPanel" hidden>
                    <div class="card-head">
                        <div>
                            <h2>Complete la solicitud</h2>
                            <p class="muted">El caso conservará el mismo número durante todas las etapas. Cada gestor podrá crear tickets derivados cuando su etapa esté activa.</p>
                        </div>
                        <span class="badge">Paso 3</span>
                    </div>

                    <div class="selected-service">
                        <div>
                            <strong id="selectedServiceName">Servicio seleccionado</strong>
                            <small id="selectedServiceFlow"></small>
                        </div>
                        <button class="light" id="changeServiceButton" type="button">Cambiar servicio</button>
                    </div>

                    <form method="post" enctype="multipart/form-data" id="ticketCreateForm">
                        <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="accion" value="crear_ticket">
                        <input type="hidden" id="id_proceso" name="id_proceso" value="" required>
                        <div class="form-grid">
                            <div class="field">
                                <label for="titulo">Asunto</label>
                                <input id="titulo" name="titulo" maxlength="180" required>
                            </div>
                            <div class="field">
                                <label for="urgencia">Urgencia</label>
                                <select id="urgencia" name="urgencia">
                                    <option value="baja">Baja</option>
                                    <option value="moderada" selected>Moderada</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                            <div class="field full">
                                <label for="descripcion">Descripción</label>
                                <textarea id="descripcion" name="descripcion" maxlength="15000" required></textarea>
                            </div>
                            <div class="field full">
                                <label for="adjuntos_nuevo">Adjuntos</label>
                                <input id="adjuntos_nuevo" type="file" name="adjuntos[]" multiple>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a class="btn light" href="flujoTicket.php?modo=mis_tickets&amp;bandeja=abiertos">Cancelar</a>
                            <button class="primary" type="submit">Crear ticket</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    <?php elseif (!$ticketSeleccionado): ?>
        <section class="card">
            <div class="card-head">
                <div>
                    <h2><?= $rol === 2 ? escaparFlujo($tituloBandejaGestor) : 'Casos' ?></h2>
                    <p class="muted"><?= $rol === 2
                        ? escaparFlujo($descripcionBandejaGestor)
                        : 'Consulte el estado y abra un caso para revisar sus etapas y tickets derivados.' ?></p>
                </div>
            </div>

            <?php if (!$tickets): ?>
                <div class="empty">
                    <strong><?= $rol === 2 && $bandejaGestor === 'cerrados'
                        ? 'No tiene casos cerrados.'
                        : 'No tiene casos abiertos asignados.' ?></strong>
                    <?php if ($rol === 2 && $bandejaGestor === 'abiertos'): ?><p>Los casos aparecerán aquí cuando su etapa sea asignada al gestor o cuando reciba un ticket derivado.</p><?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($rol === 2): ?>
                    <div class="manager-case-filters" aria-label="Filtros de casos">
                        <div class="manager-filter-field">
                            <label for="buscar-casos-gestor">Buscar casos</label>
                            <div class="manager-search-control">
                                <input
                                    id="buscar-casos-gestor"
                                    type="search"
                                    value="<?= escaparFlujo($busquedaGestor) ?>"
                                    maxlength="120"
                                    autocomplete="off"
                                    placeholder="Número, asunto, flujo, solicitante, estado o fecha"
                                >
                                <button class="manager-filter-clear" id="limpiar-filtro-gestor" type="button" aria-label="Limpiar búsqueda" hidden>×</button>
                            </div>
                        </div>
                        <div class="manager-filter-field">
                            <label for="estado-casos-gestor">Estado</label>
                            <select id="estado-casos-gestor">
                                <option value="todos">Todos los estados</option>
                                <?php foreach ($estadosGestorDisponibles as $valorEstado => $etiquetaEstado): ?>
                                    <option value="<?= escaparFlujo($valorEstado) ?>" <?= $estadoBusquedaGestor === $valorEstado ? 'selected' : '' ?>><?= escaparFlujo($etiquetaEstado) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <span class="manager-filter-count" id="contador-casos-gestor" aria-live="polite"><?= count($tickets) ?> caso(s)</span>
                    </div>
                <?php endif; ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Número de caso</th>
                                <th>Asunto</th>
                                <th>Flujo asignado</th>
                                <th>Solicitante</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php $estadoFilaGestor = (string) ($ticket['estado_flujo'] ?? 'en_proceso'); ?>
                                <tr<?= $rol === 2 ? ' data-manager-case-row data-manager-case-state="' . escaparFlujo($estadoFilaGestor) . '"' : '' ?>>
                                    <td><span class="badge">Caso <?= (int) $ticket['id_ticket'] ?></span></td>
                                    <td class="ticket-title"><?= escaparFlujo($ticket['titulo']) ?></td>
                                    <td><?= escaparFlujo($ticket['proceso_nombre']) ?></td>
                                    <td><?= escaparFlujo($ticket['creador_nombre'] ?? $ticket['solicitante_nombre']) ?></td>
                                    <td><span class="badge"><?= escaparFlujo(textoEstadoTicket((string) $ticket['estado_flujo'])) ?></span></td>
                                    <td><?= escaparFlujo(fechaFlujo($ticket['fecha_creacion'] ?? null)) ?></td>
                                    <td>
                                        <?php
                                            $esCierrePendiente = $rol === 2
                                                && (int) ($ticket['id_usuario'] ?? 0) === $idUsuario
                                                && (string) ($ticket['estado_flujo'] ?? '') === 'pendiente_calificacion';
                                            $urlFila = urlTicketFlujo(
                                                $rutaBaseTickets,
                                                $rol,
                                                (int) $ticket['id_ticket']
                                            ) . ($esCierrePendiente ? '&encuesta=1' : '');
                                        ?>
                                        <a class="btn <?= $esCierrePendiente ? 'primary' : 'light' ?>"<?= $rol === 2 ? ' data-manager-case-view' : '' ?> href="<?= escaparFlujo($urlFila) ?>">
                                            <?= $esCierrePendiente ? 'Calificar y cerrar' : 'Ver' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rol === 2): ?>
                                <tr class="manager-filter-empty" id="sin-resultados-gestor" hidden>
                                    <td colspan="7">No se encontraron casos con los filtros seleccionados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <?php if ($rol === 2): ?>
            <div
                class="manager-ticket-modal"
                id="manager-ticket-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="manager-ticket-modal-title"
                data-close-url="<?= escaparFlujo($rutaListadoGestor) ?>"
            >
                <div class="manager-ticket-modal-window" role="document">
                    <header class="manager-ticket-modal-bar">
                        <div>
                            <strong id="manager-ticket-modal-title">Detalle y gestión del caso <?= (int) $ticketSeleccionado['id_ticket'] ?></strong>
                            <small>La información completa permanece dentro de esta ventana.</small>
                        </div>
                        <button class="manager-ticket-modal-close" type="button" data-manager-ticket-close>
                            <span aria-hidden="true">×</span><span class="manager-ticket-modal-close-label">Cerrar</span>
                        </button>
                    </header>
                    <div class="manager-ticket-modal-scroll">
        <?php endif; ?>
        <section class="card ticket-summary">
            <div>
                <span class="badge">Caso <?= (int) $ticketSeleccionado['id_ticket'] ?></span>
                <h2><?= escaparFlujo($ticketSeleccionado['titulo']) ?></h2>
                <p class="muted">
                    <?= escaparFlujo($ticketSeleccionado['proceso_nombre']) ?> ·
                    Solicitante: <?= escaparFlujo($ticketSeleccionado['solicitante_nombre'] ?? $ticketSeleccionado['creador_nombre']) ?>
                </p>
                <p class="description"><?= escaparFlujo($ticketSeleccionado['descripcion']) ?></p>
            </div>
            <span class="badge"><?= escaparFlujo(textoEstadoTicket((string) $ticketSeleccionado['estado_flujo'])) ?></span>
        </section>

        <?php if (
            $rol === 2
            && (int) $ticketSeleccionado['id_usuario'] === $idUsuario
            && $ticketSeleccionado['estado_flujo'] === 'pendiente_calificacion'
            && $etapaEncuestaLegacy
        ): ?>
            <section class="closure-banner" aria-label="Cierre definitivo pendiente">
                <div>
                    <strong>Caso anterior: falta la encuesta única del servicio solicitado</strong>
                    <p>Este formulario de compatibilidad no solicita calificar todas las áreas.</p>
                </div>
                <button class="primary" type="button" data-open-final-rating>
                    Calificar servicio y cerrar caso
                </button>
            </section>

            <dialog
                id="finalRatingDialog"
                class="final-rating-dialog"
                data-auto-open="<?= $abrirCierreDefinitivo ? 'true' : 'false' ?>"
            >
                <div class="final-rating-shell">
                    <header class="final-rating-head">
                        <div>
                            <h2>Cierre definitivo del Caso <?= $idTicketSeleccionado ?></h2>
                            <p>Esta acción registra una sola encuesta del servicio solicitado y cierra el caso anterior.</p>
                        </div>
                        <form method="dialog">
                            <button class="modal-close" type="submit" aria-label="Cerrar ventana">×</button>
                        </form>
                    </header>
                    <div class="final-rating-body">
                        <?php if (!$moduloCalificacionDetallada): ?>
                            <div class="alert error">
                                El administrador debe importar <strong>migracion_calificacion_cierre_definitivo.sql</strong> antes de utilizar este formulario.
                            </div>
                        <?php else: ?>
                            <div class="final-rating-intro">
                                <strong>Califique únicamente el servicio solicitado.</strong>
                                Las evaluaciones de los tickets derivados se registran por separado cuando el gestor que creó cada derivación confirma su cierre.
                            </div>
                            <form method="post" data-final-rating-form>
                                <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="accion" value="calificar_cerrar_ticket">
                                <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                                <div class="rating-case-list">
                                    <?php foreach ([$etapaEncuestaLegacy] as $etapa): ?>
                                        <?php if (!$etapa || (string) $etapa['estado'] !== 'completada') continue; ?>
                                        <?php
                                            $idEtapaCalificar = (int) $etapa['id_ticket_etapa'];
                                            $resultadoSla = (string) ($etapa['resultado_sla'] ?? 'sin_iniciar');
                                            $claseResultado = $resultadoSla === 'dentro_sla'
                                                ? 'inside'
                                                : ($resultadoSla === 'fuera_sla' ? 'outside' : '');
                                            $textoResultado = $resultadoSla === 'dentro_sla'
                                                ? 'Dentro del SLA'
                                                : ($resultadoSla === 'fuera_sla' ? 'Fuera del SLA' : 'Sin evaluación SLA');
                                        ?>
                                        <article class="rating-case">
                                            <div class="rating-case-head">
                                                <div>
                                                    <div class="rating-case-title">
                                                        <span class="case-code"><?=
                                                            !empty($etapa['es_derivacion'])
                                                                ? 'Ticket ' . escaparFlujo($etapa['codigo_ticket'] ?? '')
                                                                : 'Caso ' . escaparFlujo($idTicketSeleccionado)
                                                        ?></span>
                                                        <strong><?= escaparFlujo($etapa['catalogo_nombre'] . ' · ' . $etapa['servicio_nombre']) ?></strong>
                                                    </div>
                                                    <div class="rating-case-title" style="margin-top:4px">
                                                        <span>Gestor asignado: <?= escaparFlujo($etapa['gestor_nombre'] ?? 'Sin asignar') ?></span>
                                                    </div>
                                                </div>
                                                <div class="rating-case-times">
                                                    <span>SLA: <?= escaparFlujo((string) $etapa['sla_tiempo'] . ' ' . (string) $etapa['sla_unidad']) ?></span>
                                                    <span>Tiempo real: <?= escaparFlujo(tiempoFlujo($etapa['minutos_atencion'] ?? null)) ?></span>
                                                    <span class="<?= escaparFlujo($claseResultado) ?>"><?= escaparFlujo($textoResultado) ?></span>
                                                </div>
                                            </div>
                                            <div class="rating-fields">
                                                <div>
                                                    <label for="area-<?= $idEtapaCalificar ?>">Calificación del área *</label>
                                                    <select id="area-<?= $idEtapaCalificar ?>" name="calificacion_area[<?= $idEtapaCalificar ?>]" required>
                                                        <option value="">Seleccione</option>
                                                        <?php for ($nota = 5; $nota >= 1; $nota--): ?>
                                                            <option value="<?= $nota ?>" <?= (int) ($etapa['calificacion_area'] ?? 0) === $nota ? 'selected' : '' ?>><?= $nota ?> - <?= ['','Muy deficiente','Deficiente','Aceptable','Muy buena','Excelente'][$nota] ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="tiempo-<?= $idEtapaCalificar ?>">Tiempo de respuesta *</label>
                                                    <select id="tiempo-<?= $idEtapaCalificar ?>" name="calificacion_tiempo[<?= $idEtapaCalificar ?>]" required>
                                                        <option value="">Seleccione</option>
                                                        <?php for ($nota = 5; $nota >= 1; $nota--): ?>
                                                            <option value="<?= $nota ?>" <?= (int) ($etapa['calificacion_tiempo'] ?? 0) === $nota ? 'selected' : '' ?>><?= $nota ?> - <?= ['','Muy deficiente','Deficiente','Aceptable','Muy buena','Excelente'][$nota] ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="comentario-<?= $idEtapaCalificar ?>">Observación opcional</label>
                                                    <textarea id="comentario-<?= $idEtapaCalificar ?>" name="comentario[<?= $idEtapaCalificar ?>]" maxlength="1000" placeholder="Indique qué fue positivo o qué debería mejorar"><?= escaparFlujo($etapa['comentario_calificacion'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                                <div class="final-rating-actions">
                                    <button class="light" type="button" data-close-final-rating>Volver</button>
                                    <button class="primary" type="submit">Enviar encuesta y cerrar definitivamente</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </dialog>
        <?php endif; ?>

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Caso principal y tickets derivados</h2>
                    <p class="muted">El número del caso permanece igual durante todo el flujo. Las derivaciones se numeran según la etapa que las originó: 1.1, 2.1, 2.2, etc.</p>
                </div>
                <div class="tree-toolbar">
                    <button class="btn light" type="button" data-tree-expand>Expandir todo</button>
                    <button class="btn outline" type="button" data-tree-collapse>Contraer todo</button>
                </div>
            </div>
            <div class="case-tree" data-case-tree data-tree-key="ticket-<?= $idTicketSeleccionado ?>">
                <?php if (!$casoPrincipalVisual): ?>
                    <p class="muted">Este caso todavía no tiene una etapa disponible.</p>
                <?php else: ?>
                    <?php renderizarRamaCasos(
                        $casoPrincipalVisual,
                        $hijosArbolVisual,
                        $rutaBaseTickets,
                        $idTicketSeleccionado,
                        (int) ($etapaActual['id_ticket_etapa'] ?? 0),
                        $rutaCasoSeleccionado
                    ); ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($etapaActual): ?>
        <dialog
            id="caseDialog"
            class="case-dialog"
            data-auto-open="<?= $idNodoSeleccionado > 0 ? 'true' : 'false' ?>"
            data-initial-panel="<?= escaparFlujo($panelCaso) ?>"
        >
            <div class="case-modal-shell">
                <header class="case-modal-header">
                    <div>
                        <div class="case-modal-title-line">
                            <span class="case-code"><?= escaparFlujo($etiquetaNodoActual) ?></span>
                            <span class="case-status <?= escaparFlujo($etapaActual['estado']) ?>"><?= escaparFlujo(textoEstadoCaso((string) $etapaActual['estado'])) ?></span>
                        </div>
                        <h2><?= escaparFlujo($etapaActual['catalogo_nombre'] . ' · ' . $etapaActual['servicio_nombre']) ?></h2>
                        <p><?= $esTicketDerivadoActual
                            ? 'Revise la información de esta derivación del caso principal.'
                            : 'Revise la etapa actual y la información completa del mismo caso.' ?></p>
                    </div>
                    <form method="dialog">
                        <button class="modal-close" type="submit" aria-label="Cerrar ventana">×</button>
                    </form>
                </header>

                <nav class="case-tabs" aria-label="Secciones del caso">
                    <?php foreach ([
                        'resumen' => 'Resumen y gestión',
                        'conversacion' => 'Conversación',
                        'acciones' => 'Acciones (' . count($historial) . ')',
                        'archivos' => 'Archivos (' . count($adjuntos) . ')',
                    ] as $clavePanel => $tituloPanel): ?>
                        <button
                            class="case-tab <?= $panelCaso === $clavePanel ? 'active' : '' ?>"
                            type="button"
                            data-case-tab="<?= escaparFlujo($clavePanel) ?>"
                            aria-selected="<?= $panelCaso === $clavePanel ? 'true' : 'false' ?>"
                        ><?= escaparFlujo($tituloPanel) ?></button>
                    <?php endforeach; ?>
                </nav>

                <div class="case-modal-content">
        <section
            class="card case-pane"
            data-case-panel="acciones"
            <?= $panelCaso === 'acciones' ? '' : 'hidden' ?>
        >
            <div class="card-head">
                <div>
                    <h2>Acciones de <?= escaparFlujo($etiquetaNodoActual) ?></h2>
                    <p class="muted">Solo se muestran las acciones registradas en la etapa o derivación seleccionada.</p>
                </div>
                <span class="badge"><?= count($historial) ?> eventos</span>
            </div>
            <div class="action-summary">
                <div class="action-summary-head">
                    <strong>Resumen completo de trazabilidad</strong>
                    <span class="case-status <?= escaparFlujo((string) $etapaActual['estado']) ?>"><?= escaparFlujo(textoEstadoCaso((string) $etapaActual['estado'])) ?></span>
                </div>
                <div class="action-summary-grid">
                    <div><span>Creador del caso</span><strong><?= escaparFlujo($etapaActual['creador_caso_nombre'] ?? 'Usuario eliminado') ?></strong></div>
                    <div><span>Gestor asignado</span><strong><?= escaparFlujo($etapaActual['gestor_nombre'] ?? 'Sin asignar') ?></strong></div>
                    <div><span>Activación</span><strong><?= escaparFlujo(fechaFlujo($etapaActual['fecha_activacion'] ?? null)) ?></strong></div>
                    <div><span>Vencimiento visible</span><strong><?= escaparFlujo(fechaFlujo($etapaActual['fecha_vencimiento'] ?? null)) ?></strong></div>
                    <div><span>Marcado Listo por</span><strong><?= escaparFlujo($etapaActual['marcador_listo_nombre'] ?? 'Sin marcar') ?></strong></div>
                    <div><span>Fecha de Listo</span><strong><?= escaparFlujo(fechaFlujo($etapaActual['fecha_marcado_listo'] ?? null)) ?></strong></div>
                    <div><span>Tiempo oficial hasta Listo</span><strong><?= escaparFlujo(tiempoFlujo($etapaActual['minutos_hasta_listo'] ?? null)) ?></strong></div>
                    <div><span>SLA para indicadores</span><strong><?= escaparFlujo(match ((string) ($etapaActual['resultado_sla_listo'] ?? $etapaActual['resultado_sla'] ?? 'sin_iniciar')) {
                        'dentro_sla' => 'Dentro del SLA',
                        'fuera_sla' => 'Fuera del SLA',
                        default => 'Sin evaluación',
                    }) ?></strong></div>
                    <div><span>Estado SLA visible actual</span><strong><?= escaparFlujo((string) ($etapaActual['estado_sla_actual'] ?? 'sin_iniciar')) ?></strong></div>
                    <div><span>Reaperturas</span><strong><?= (int) ($etapaActual['cantidad_reaperturas'] ?? 0) ?></strong></div>
                    <div><span>Última reapertura</span><strong><?= escaparFlujo(fechaFlujo($etapaActual['fecha_ultima_reapertura'] ?? null)) ?></strong></div>
                    <div><span>Cerrado por</span><strong><?= escaparFlujo($etapaActual['cerrador_caso_nombre'] ?? 'Sin cerrar') ?></strong></div>
                    <div><span>Fecha de cierre</span><strong><?= escaparFlujo(fechaFlujo($etapaActual['fecha_finalizacion'] ?? null)) ?></strong></div>
                    <div><span>Solución seleccionada</span><strong><?= escaparFlujo($etapaActual['solucion_nombre'] ?? 'Sin solución') ?></strong></div>
                </div>

                <?php if (trim((string) ($etapaActual['comentario_cierre'] ?? '')) !== ''): ?>
                    <details class="action-detail"><summary>Ver observación completa de la solución</summary><p><?= nl2br(escaparFlujo($etapaActual['comentario_cierre'])) ?></p></details>
                <?php endif; ?>

                <div class="action-rating-summary">
                    <div>
                        <span>Tipo de calificación</span>
                        <strong><?= escaparFlujo(match ((string) ($etapaActual['tipo_calificacion'] ?? '')) {
                            'encuesta_servicio' => 'Encuesta única del servicio solicitado',
                            'evaluacion_derivacion' => 'Evaluación interna de la derivación',
                            'evaluacion_caso' => 'Evaluación operativa del caso',
                            default => 'Pendiente de calificación',
                        }) ?></strong>
                    </div>
                    <div><span>Evaluador</span><strong><?= escaparFlujo($etapaActual['evaluador_nombre'] ?? 'Sin calificar') ?></strong></div>
                    <div><span>Gestión del área</span><strong><?= ($etapaActual['calificacion_area'] ?? null) === null ? 'Pendiente' : (int) $etapaActual['calificacion_area'] . '/5' ?></strong></div>
                    <div><span>Tiempo de respuesta</span><strong><?= ($etapaActual['calificacion_tiempo'] ?? null) === null ? 'Pendiente' : (int) $etapaActual['calificacion_tiempo'] . '/5' ?></strong></div>
                    <div><span>Calificación general</span><strong><?= ($etapaActual['calificacion'] ?? null) === null ? 'Pendiente' : (int) $etapaActual['calificacion'] . '/5' ?></strong></div>
                </div>
                <?php if (trim((string) ($etapaActual['comentario_calificacion'] ?? '')) !== ''): ?>
                    <details class="action-detail"><summary>Ver comentario completo de la calificación</summary><p><?= nl2br(escaparFlujo($etapaActual['comentario_calificacion'])) ?></p></details>
                <?php endif; ?>
            </div>
            <div class="audit-log">
                <?php if (!$historial): ?><p class="muted">Este caso no tiene acciones registradas todavía.</p><?php endif; ?>
                <?php foreach ($historial as $evento): ?>
                    <article class="audit-item">
                        <strong><?= escaparFlujo($evento['accion']) ?></strong>
                        <p><?= escaparFlujo($evento['detalle']) ?></p>
                        <div class="meta">
                            <?= escaparFlujo($evento['usuario']) ?> ·
                            <?= escaparFlujo(fechaFlujo($evento['creado_en'])) ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="detail-grid case-dialog-panes">
            <div>
                <section
                    class="card case-pane"
                    data-case-panel="conversacion"
                    <?= $panelCaso === 'conversacion' ? '' : 'hidden' ?>
                >
                    <div class="card-head">
                        <div>
                            <h2>Conversaciones separadas por etapa</h2>
                            <p class="muted">Las etapas del flujo comunican al solicitante con su gestor; las derivaciones son chats internos entre gestores.</p>
                        </div>
                    </div>
                    <div class="conversation-list">
                        <?php foreach ($conversaciones as $conversacion): ?>
                            <?php
                                $idConversacion = (int) $conversacion['id_ticket_etapa'];
                                $esConversacionActual = $conversacionActual
                                    && (int) $conversacionActual['id_ticket_etapa'] === $idConversacion;
                                $esOrigen = (int) $conversacion['id_gestor_origen'] === $idUsuario;
                                $esDestino = (int) $conversacion['id_gestor_destino'] === $idUsuario;
                                $esDerivacionInterna = (string) ($conversacion['tipo_conversacion'] ?? '') === 'derivacion';
                                $rolConversacion = $rol === 1
                                    ? ($esDerivacionInterna
                                        ? 'Auditoría · derivación interna'
                                        : 'Auditoría · chat del flujo')
                                    : ($esDerivacionInterna
                                        ? ($esOrigen && $esDestino
                                            ? 'Derivación interna propia'
                                            : ($esOrigen
                                                ? 'Derivación interna enviada'
                                                : 'Derivación interna recibida'))
                                        : 'Chat con el solicitante');
                                $urlConversacion = $rutaBaseTickets . '?' . http_build_query([
                                    'id_ticket' => $idTicketSeleccionado,
                                    'id_nodo' => $idNodoSeleccionado ?: $idConversacion,
                                    'id_chat' => $idConversacion,
                                    'panel' => 'conversacion',
                                ]);
                            ?>
                            <a class="conversation-tab <?= $esConversacionActual ? 'active' : '' ?>" href="<?= escaparFlujo($urlConversacion) ?>">
                                <strong><?=
                                    $esDerivacionInterna
                                        ? 'Ticket ' . escaparFlujo($conversacion['codigo_ticket'] ?? '')
                                        : 'Caso ' . escaparFlujo($conversacion['codigo_caso'])
                                            . ' · Etapa ' . (int) ($conversacion['numero_etapa'] ?? 1)
                                ?></strong>
                                <span><?= escaparFlujo($rolConversacion) ?></span>
                                <span><?= escaparFlujo($conversacion['gestor_origen'] ?: ($esDerivacionInterna ? 'Gestor de origen' : 'Solicitante')) ?> ↔ <?= escaparFlujo($conversacion['gestor_destino'] ?: 'Gestor asignado') ?></span>
                                <span><?= (int) $conversacion['total_mensajes'] ?> mensaje(s) · <?= (int) $conversacion['total_adjuntos'] ?> archivo(s)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($conversacionActual): ?>
                        <div class="notice-panel" style="margin-bottom:8px">
                            <?php $chatActualInterno = (string) ($conversacionActual['tipo_conversacion'] ?? '') === 'derivacion'; ?>
                            <strong><?= $chatActualInterno
                                ? 'Derivación interna · Ticket ' . escaparFlujo($conversacionActual['codigo_ticket'] ?? '')
                                : 'Caso ' . escaparFlujo($conversacionActual['codigo_caso'])
                                    . ' · Chat de la etapa ' . (int) ($conversacionActual['numero_etapa'] ?? 1)
                            ?></strong>
                            <div class="meta">
                                <?= $chatActualInterno ? 'Abierto por' : 'Solicitante' ?> <?= escaparFlujo($conversacionActual['gestor_origen'] ?: ($chatActualInterno ? 'Gestor de origen' : 'Solicitante')) ?> ·
                                asignado a <?= escaparFlujo($conversacionActual['gestor_destino'] ?: 'Gestor destino') ?>
                            </div>
                        </div>
                        <div class="chat">
                            <?php if (!$comunicaciones): ?><p class="muted">Todavía no hay mensajes en esta conversación.</p><?php endif; ?>
                            <?php foreach ($comunicaciones as $mensaje): ?>
                                <?php $esPropio = (int) $mensaje['id_emisor'] === $idUsuario; ?>
                                <article class="message <?= $esPropio ? 'mine' : '' ?>">
                                    <div class="message-head"><?= escaparFlujo($mensaje['emisor'] ?: 'Usuario') ?></div>
                                    <p><?= escaparFlujo($mensaje['mensaje']) ?></p>
                                    <div class="meta"><?= escaparFlujo(fechaFlujo($mensaje['creado_en'])) ?></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">No tiene conversaciones habilitadas en este ticket.</p>
                    <?php endif; ?>

                    <?php if ($puedeEscribir && $conversacionActual): ?>
                        <form class="compose" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="accion" value="enviar_mensaje">
                            <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                            <input type="hidden" name="id_ticket_etapa" value="<?= (int) $conversacionActual['id_ticket_etapa'] ?>">
                            <div>
                                <label for="mensaje_chat">Mensaje</label>
                                <textarea id="mensaje_chat" name="mensaje" maxlength="10000"></textarea>
                            </div>
                            <div>
                                <label for="adjuntos_chat">Adjuntos</label>
                                <input id="adjuntos_chat" type="file" name="adjuntos[]" multiple>
                            </div>
                            <button class="primary" type="submit">Enviar</button>
                        </form>
                    <?php elseif ($conversacionActual): ?>
                        <p class="muted"><?= $rol === 1
                            ? 'Administración puede auditar esta conversación, pero no puede responder.'
                            : 'Esta conversación está disponible en modo lectura porque el caso ya finalizó.' ?></p>
                    <?php endif; ?>
                </section>

                <section
                    class="card case-pane"
                    data-case-panel="archivos"
                    <?= $panelCaso === 'archivos' ? '' : 'hidden' ?>
                >
                    <div class="card-head"><h2>Archivos</h2></div>
                    <div class="files">
                        <?php if (!$adjuntos): ?><p class="muted">No hay archivos adjuntos en esta conversación.</p><?php endif; ?>
                        <?php foreach ($adjuntos as $archivo): ?>
                            <div class="file">
                                <div>
                                    <strong><?= escaparFlujo($archivo['nombre_original']) ?></strong>
                                    <div class="meta">
                                        <?= $chatActualInterno
                                            ? 'Ticket ' . escaparFlujo($conversacionActual['codigo_ticket'] ?? '')
                                            : 'Caso ' . escaparFlujo($conversacionActual['codigo_caso'] ?? '')
                                                . ' · Etapa ' . (int) ($conversacionActual['numero_etapa'] ?? 1)
                                        ?> ·
                                        <?= escaparFlujo(($archivo['usuario'] ?: 'Usuario') . ' · ' . fechaFlujo($archivo['creado_en'])) ?>
                                    </div>
                                </div>
                                <a class="btn light" href="descargarAdjunto.php?id=<?= (int) $archivo['id_adjunto'] ?>">Descargar</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <div>
                <?php if ($etapaActual): ?>
                    <section
                        class="card case-pane"
                        data-case-panel="resumen"
                        <?= $panelCaso === 'resumen' ? '' : 'hidden' ?>
                    >
                        <div class="card-head">
                            <div>
                                <h2><?= escaparFlujo($etiquetaNodoActual) ?> seleccionado</h2>
                                <p class="muted"><?= escaparFlujo($etapaActual['catalogo_nombre'] . ' · ' . $etapaActual['servicio_nombre']) ?></p>
                            </div>
                            <span class="badge"><?= escaparFlujo($etapaActual['estado']) ?></span>
                        </div>

                        <?php if ($casoSeleccionadoCerrado): ?>
                            <div class="closed-case">
                                <strong>Caso cerrado · modo consulta</strong>
                                <p>Marcado listo: <?= escaparFlujo(fechaFlujo($etapaActual['fecha_marcado_listo'] ?? $etapaActual['fecha_finalizacion'])) ?></p>
                                <p>Cerrado por su creador: <?= escaparFlujo(fechaFlujo($etapaActual['fecha_finalizacion'])) ?></p>
                                <p>Tiempo medido hasta Listo: <?= escaparFlujo(tiempoFlujo($etapaActual['minutos_hasta_listo'] ?? $etapaActual['minutos_atencion'])) ?></p>
                                <p>Resultado SLA para indicadores: <?= escaparFlujo($etapaActual['resultado_sla']) ?></p>
                                <?php if (trim((string) ($etapaActual['solucion_nombre'] ?? '')) !== ''): ?>
                                    <p><strong>Solución seleccionada:</strong> <?= escaparFlujo($etapaActual['solucion_nombre']) ?></p>
                                <?php elseif (trim((string) ($etapaActual['comentario_cierre'] ?? '')) !== ''): ?>
                                    <p><strong>Solución seleccionada:</strong> Cierre anterior sin clasificación</p>
                                <?php endif; ?>
                                <?php if (trim((string) ($etapaActual['comentario_cierre'] ?? '')) !== ''): ?>
                                    <details>
                                        <summary><strong>Ver observación de la solución</strong></summary>
                                        <p><?= nl2br(escaparFlujo($etapaActual['comentario_cierre'])) ?></p>
                                    </details>
                                <?php endif; ?>
                                <?php if (($etapaActual['calificacion'] ?? null) !== null): ?>
                                    <p><strong>Calificación del cierre:</strong> área <?= (int) $etapaActual['calificacion_area'] ?>/5 · tiempo <?= (int) $etapaActual['calificacion_tiempo'] ?>/5 · general <?= (int) $etapaActual['calificacion'] ?>/5</p>
                                    <?php if (trim((string) ($etapaActual['comentario_calificacion'] ?? '')) !== ''): ?>
                                        <details>
                                            <summary><strong>Ver comentario de la calificación</strong></summary>
                                            <p><?= nl2br(escaparFlujo($etapaActual['comentario_calificacion'])) ?></p>
                                        </details>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <p>La comunicación, los archivos y las acciones de este caso permanecen disponibles únicamente para consulta.</p>
                            </div>
                        <?php else: ?>
                        <div class="stage-summary">
                            <div class="stage-data"><span>Gestor</span><strong><?= escaparFlujo($etapaActual['gestor_nombre']) ?></strong></div>
                            <div class="stage-data"><span>SLA</span><strong><?= escaparFlujo($etapaActual['sla_nombre']) ?></strong></div>
                            <div class="stage-data"><span>Activación</span><strong><?= escaparFlujo(fechaFlujo($etapaActual['fecha_activacion'])) ?></strong></div>
                            <div class="stage-data"><span>Vencimiento</span><strong><?= escaparFlujo(fechaFlujo($etapaActual['fecha_vencimiento'])) ?></strong></div>
                        </div>
                        <?php if (trim((string) ($etapaActual['motivo_derivacion'] ?? '')) !== ''): ?>
                            <p class="description"><strong>Motivo de apertura:</strong> <?= escaparFlujo($etapaActual['motivo_derivacion']) ?></p>
                        <?php endif; ?>
                        <?php
                            $esGestorNodo = $rol === 2
                                && (int) $etapaActual['id_gestor'] === $idUsuario;
                            $idCreadorNodo = (int) (
                                $etapaActual['creado_por']
                                ?? $ticketSeleccionado['id_usuario']
                                ?? 0
                            );
                            $esCreadorNodo = $rol === 2
                                && $idCreadorNodo === $idUsuario;
                            $estadoNodo = (string) $etapaActual['estado'];
                            $puedeGestionar = $esGestorNodo
                                && in_array(
                                    $estadoNodo,
                                    ['pendiente', 'en_proceso', 'en_espera_solicitante'],
                                    true
                                );
                            $puedeCrearHijo = $esGestorNodo
                                && in_array(
                                    $estadoNodo,
                                    ['pendiente', 'en_proceso', 'en_espera_solicitante', 'pausada'],
                                    true
                                );
                            $puedeCerrarNodo = $esCreadorNodo
                                && $estadoNodo === 'listo_cierre';
                            $tipoCalificacionNodo = flujoTipoCalificacionCaso(
                                $conn,
                                $idTicketSeleccionado,
                                $etapaActual
                            );
                        ?>

                        <?php if ($estadoNodo === 'listo_cierre'): ?>
                            <?php
                                $resultadoCorte = (string) (
                                    $etapaActual['resultado_sla_listo']
                                    ?? 'sin_iniciar'
                                );
                                $slaVisible = (string) (
                                    $etapaActual['estado_sla_actual']
                                    ?? 'en_tiempo'
                                );
                                $textoTipoCalificacion = match ($tipoCalificacionNodo) {
                                    'encuesta_servicio' => 'Encuesta única del servicio solicitado',
                                    'evaluacion_derivacion' => 'Evaluación interna de la derivación',
                                    default => 'Evaluación operativa del caso',
                                };
                            ?>
                            <div class="ready-approval">
                                <div class="ready-approval-head">
                                    <div>
                                        <strong><?= !empty($etapaActual['solicita_cierre_definitivo']) ? 'Cierre definitivo solicitado · resolución en primer contacto' : 'Listo · pendiente de decisión del creador' ?></strong>
                                        <p>El gestor asignado terminó su gestión el <?= escaparFlujo(fechaFlujo($etapaActual['fecha_marcado_listo'] ?? null)) ?>.</p>
                                    </div>
                                    <span class="badge"><?= escaparFlujo($slaVisible === 'vencido' ? 'Visualmente vencido' : 'Visualmente en tiempo') ?></span>
                                </div>
                                <div class="stage-summary">
                                    <div class="stage-data"><span>Tiempo para indicador</span><strong><?= escaparFlujo(tiempoFlujo($etapaActual['minutos_hasta_listo'] ?? null)) ?></strong></div>
                                    <div class="stage-data"><span>Resultado para dashboard</span><strong><?= escaparFlujo($resultadoCorte === 'dentro_sla' ? 'Dentro del SLA' : 'Fuera del SLA') ?></strong></div>
                                    <div class="stage-data"><span>Solución</span><strong><?= escaparFlujo($etapaActual['solucion_nombre'] ?? 'Sin clasificar') ?></strong></div>
                                    <div class="stage-data"><span>Reaperturas</span><strong><?= (int) ($etapaActual['cantidad_reaperturas'] ?? 0) ?></strong></div>
                                </div>
                                <?php if (trim((string) ($etapaActual['comentario_cierre'] ?? '')) !== ''): ?>
                                    <details class="case-panel-toggle" style="margin-top:10px">
                                        <summary>Ver solución informada por el gestor</summary>
                                        <div class="case-panel-toggle-body"><p><?= nl2br(escaparFlujo($etapaActual['comentario_cierre'])) ?></p></div>
                                    </details>
                                <?php endif; ?>

                                <?php if ($puedeCerrarNodo): ?>
                                    <form method="post" class="case-rating-form" onsubmit="return confirm('¿Confirma la calificación y el cierre de este caso?')">
                                        <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="cerrar_caso">
                                        <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                                        <input type="hidden" name="id_ticket_etapa" value="<?= (int) $etapaActual['id_ticket_etapa'] ?>">
                                        <h3><?= escaparFlujo($textoTipoCalificacion) ?></h3>
                                        <p class="muted"><?= !empty($etapaActual['solicita_cierre_definitivo']) ? 'Al aprobar, el ticket se cerrará definitivamente y las etapas futuras que no iniciaron quedarán canceladas.' : 'Ningún caso padre o hijo puede cerrarse sin esta calificación.' ?></p>
                                        <div class="rating-fields">
                                            <div>
                                                <label for="calificacion_area_caso">Gestión del área *</label>
                                                <select id="calificacion_area_caso" name="calificacion_area" required>
                                                    <option value="">Seleccione</option>
                                                    <?php for ($nota = 5; $nota >= 1; $nota--): ?>
                                                        <option value="<?= $nota ?>"><?= $nota ?> - <?= ['','Muy deficiente','Deficiente','Aceptable','Muy buena','Excelente'][$nota] ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="calificacion_tiempo_caso">Tiempo de respuesta *</label>
                                                <select id="calificacion_tiempo_caso" name="calificacion_tiempo" required>
                                                    <option value="">Seleccione</option>
                                                    <?php for ($nota = 5; $nota >= 1; $nota--): ?>
                                                        <option value="<?= $nota ?>"><?= $nota ?> - <?= ['','Muy deficiente','Deficiente','Aceptable','Muy buena','Excelente'][$nota] ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="comentario_calificacion_caso">Observación opcional</label>
                                                <textarea id="comentario_calificacion_caso" name="comentario_calificacion" maxlength="1000" placeholder="Indique qué fue positivo o qué debe mejorar"></textarea>
                                            </div>
                                        </div>
                                        <div class="approval-actions">
                                            <button class="primary" type="submit"><?= !empty($etapaActual['solicita_cierre_definitivo']) ? 'Calificar y aprobar cierre definitivo' : 'Calificar y cerrar caso' ?></button>
                                        </div>
                                    </form>
                                    <form method="post" class="reopen-form" onsubmit="return confirm('¿Reabrir este caso? El corte de Listo se anulará y el SLA incluirá todo el tiempo transcurrido.')">
                                        <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="reabrir_derivacion">
                                        <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                                        <input type="hidden" name="id_ticket_etapa" value="<?= (int) $etapaActual['id_ticket_etapa'] ?>">
                                        <label for="motivo_reapertura">Motivo obligatorio de reapertura</label>
                                        <textarea id="motivo_reapertura" name="motivo_reapertura" maxlength="1000" required placeholder="Explique por qué la solución informada no permite cerrar el caso"></textarea>
                                        <div class="approval-actions"><button class="btn danger" type="submit"><?= (int) ($etapaActual['id_ticket_etapa_padre'] ?? 0) > 0 ? 'Reabrir derivación' : 'Reabrir caso' ?></button></div>
                                    </form>
                                <?php else: ?>
                                    <div class="alert info" style="margin-top:12px">
                                        Solo el creador de este caso puede calificarlo, cerrarlo o reabrirlo. Mientras tanto, el vencimiento visible continúa; el dashboard conserva el corte realizado al marcar Listo.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($estadoNodo !== 'listo_cierre' && $puedeCrearHijo): ?>
                            <details class="case-panel-toggle derivation-panel" style="margin:10px 0">
                                <summary>Derivar a varias áreas · crear tickets derivados</summary>
                                <form class="derivation-form" method="post" data-max-derivaciones="20">
                                    <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="accion" value="crear_caso_hijo">
                                    <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                                    <input type="hidden" name="id_ticket_etapa_padre" value="<?= (int) $etapaActual['id_ticket_etapa'] ?>">
                                    <div class="derivation-list" data-derivation-list>
                                        <div class="derivation-row" data-derivation-row>
                                            <div>
                                                <label>Catálogo destino</label>
                                                <select name="derivaciones[0][id_catalogo_destino]" data-derivation-catalog required>
                                                    <option value="">Seleccione el catálogo</option>
                                                    <?php foreach ($catalogosDerivacion as $catalogoDestino): ?>
                                                        <option value="<?= (int) $catalogoDestino['id_catalogo'] ?>">
                                                            <?= escaparFlujo($catalogoDestino['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Buscar servicio destino</label>
                                                <div class="derivation-service-combobox" data-service-combobox>
                                                    <input
                                                        class="derivation-service-search"
                                                        type="search"
                                                        data-service-search
                                                        autocomplete="off"
                                                        placeholder="Seleccione primero un catálogo"
                                                        role="combobox"
                                                        aria-autocomplete="list"
                                                        aria-expanded="false"
                                                        required
                                                        disabled
                                                    >
                                                    <button
                                                        class="derivation-service-toggle"
                                                        type="button"
                                                        data-service-toggle
                                                        aria-label="Mostrar todos los servicios del catálogo"
                                                        aria-expanded="false"
                                                        disabled
                                                    ></button>
                                                    <input type="hidden" name="derivaciones[0][id_servicio_destino]" data-service-id>
                                                    <div class="derivation-service-menu" data-service-menu role="listbox" hidden></div>
                                                </div>
                                                <small class="derivation-service-help" data-service-help>Los servicios se habilitan al seleccionar el catálogo.</small>
                                            </div>
                                            <div>
                                                <label>Motivo y requerimiento</label>
                                                <textarea name="derivaciones[0][motivo_derivacion]" maxlength="2000" required placeholder="Explique qué necesita del área destino"></textarea>
                                            </div>
                                            <button class="btn danger remove-derivation" type="button" data-remove-derivation hidden>Quitar</button>
                                        </div>
                                    </div>
                                    <div class="derivation-footer">
                                        <button class="btn light" type="button" data-add-derivation>＋ Agregar otra área</button>
                                        <span class="muted" data-derivation-count>1 derivación preparada</span>
                                        <button class="primary" type="submit">Crear tickets derivados</button>
                                    </div>
                                    <p class="muted" style="padding:0 8px 8px">Puede enviar hasta 20 áreas en una sola operación. El padre se pausa una vez y todos los hijos comienzan a trabajar en paralelo.</p>
                                    <script type="application/json" data-derivation-services-json><?= $serviciosDerivacionJson ?></script>
                                    <template data-derivation-template>
                                        <div class="derivation-row" data-derivation-row>
                                            <div>
                                                <label>Catálogo destino</label>
                                                <select name="derivaciones[__INDEX__][id_catalogo_destino]" data-derivation-catalog required>
                                                    <option value="">Seleccione el catálogo</option>
                                                    <?php foreach ($catalogosDerivacion as $catalogoDestino): ?>
                                                        <option value="<?= (int) $catalogoDestino['id_catalogo'] ?>">
                                                            <?= escaparFlujo($catalogoDestino['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Buscar servicio destino</label>
                                                <div class="derivation-service-combobox" data-service-combobox>
                                                    <input
                                                        class="derivation-service-search"
                                                        type="search"
                                                        data-service-search
                                                        autocomplete="off"
                                                        placeholder="Seleccione primero un catálogo"
                                                        role="combobox"
                                                        aria-autocomplete="list"
                                                        aria-expanded="false"
                                                        required
                                                        disabled
                                                    >
                                                    <button
                                                        class="derivation-service-toggle"
                                                        type="button"
                                                        data-service-toggle
                                                        aria-label="Mostrar todos los servicios del catálogo"
                                                        aria-expanded="false"
                                                        disabled
                                                    ></button>
                                                    <input type="hidden" name="derivaciones[__INDEX__][id_servicio_destino]" data-service-id>
                                                    <div class="derivation-service-menu" data-service-menu role="listbox" hidden></div>
                                                </div>
                                                <small class="derivation-service-help" data-service-help>Los servicios se habilitan al seleccionar el catálogo.</small>
                                            </div>
                                            <div>
                                                <label>Motivo y requerimiento</label>
                                                <textarea name="derivaciones[__INDEX__][motivo_derivacion]" maxlength="2000" required placeholder="Explique qué necesita del área destino"></textarea>
                                            </div>
                                            <button class="btn danger remove-derivation" type="button" data-remove-derivation>Quitar</button>
                                        </div>
                                    </template>
                                </form>
                            </details>
                        <?php elseif (
                            $estadoNodo !== 'listo_cierre'
                            &&
                            in_array(
                                $estadoNodo,
                                ['pendiente', 'en_proceso', 'en_espera_solicitante', 'pausada'],
                                true
                            )
                        ): ?>
                            <div class="alert info" style="margin:10px 0">
                                <?php if ($rol === 1): ?>
                                    La trazabilidad está disponible, pero el ticket derivado debe crearlo el gestor asignado a esta etapa.
                                <?php else: ?>
                                    Solo <strong><?= escaparFlujo($etapaActual['gestor_nombre']) ?></strong>, gestor asignado a esta etapa, puede derivar el caso a otra área.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($estadoNodo !== 'listo_cierre' && $puedeGestionar): ?>
                            <details class="case-panel-toggle" style="margin-top:10px">
                                <summary>Checklist del caso</summary>
                                <div class="case-panel-toggle-body">
                                    <form method="post" data-case-checklist-form>
                                        <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="guardar_checklist">
                                        <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                                        <input type="hidden" name="id_ticket_etapa" value="<?= (int) $etapaActual['id_ticket_etapa'] ?>">
                                        <?php if (!$checklist): ?>
                                            <p class="muted">Esta etapa no tiene elementos configurados.</p>
                                            <?php if ($rol === 1 && !empty($etapaActual['id_proceso_etapa'])): ?>
                                                <div class="notice-panel">
                                                    <strong>Configure la plantilla de esta etapa.</strong>
                                                    <p>Los elementos activos se copiarán automáticamente a los casos nuevos.</p>
                                                    <a class="btn light" href="checklists.php?id_proceso=<?= (int) ($ticketSeleccionado['id_proceso'] ?? 0) ?>&amp;id_etapa=<?= (int) $etapaActual['id_proceso_etapa'] ?>">Administrar checklist</a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php foreach ($checklist as $item): ?>
                                            <div class="check" <?= (int) $item['obligatorio'] === 1 ? 'data-required-checklist-row' : '' ?>>
                                                <label>
                                                    <input type="checkbox" name="completado[<?= (int) $item['id_ticket_checklist'] ?>]" value="1" <?= (int) $item['completado'] === 1 ? 'checked' : '' ?> <?= (int) $item['obligatorio'] === 1 ? 'data-required-checklist' : '' ?>>
                                                    <span>
                                                        <strong><?= escaparFlujo($item['nombre']) ?><?= (int) $item['obligatorio'] === 1 ? ' *' : '' ?></strong>
                                                        <span class="meta"><?= escaparFlujo($item['descripcion']) ?></span>
                                                    </span>
                                                </label>
                                                <input name="observacion[<?= (int) $item['id_ticket_checklist'] ?>]" value="<?= escaparFlujo($item['observacion']) ?>" placeholder="Observación opcional">
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="form-actions"><button class="light" type="submit">Guardar checklist</button></div>
                                    </form>
                                </div>
                            </details>

                            <details class="case-panel-toggle" style="margin-top:10px">
                                <summary>Marcar caso como listo para revisión</summary>
                                <div class="case-panel-toggle-body">
                                    <form method="post" data-checklist-protected-form data-confirm-message="¿Confirma que la solución está lista para revisión? El creador del caso será notificado para calificar, cerrar o reabrir?">
                                        <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="marcar_listo">
                                        <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                                        <input type="hidden" name="id_ticket_etapa" value="<?= (int) $etapaActual['id_ticket_etapa'] ?>">
                                        <?php if ($solucionesCaso): ?>
                                            <label for="id_solucion">Solución aplicada *</label>
                                            <select id="id_solucion" name="id_solucion" required data-solution-select>
                                                <option value="">Seleccione una solución predeterminada</option>
                                                <?php foreach ($solucionesCaso as $solucionCaso): ?>
                                                    <option
                                                        value="<?= (int) $solucionCaso['id_solucion'] ?>"
                                                        data-description="<?= escaparFlujo($solucionCaso['descripcion'] ?? '') ?>"
                                                    ><?= escaparFlujo($solucionCaso['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="muted" data-solution-help>Seleccione la opción que corresponde al trabajo realizado.</p>
                                            <?php if ($rol === 1): ?>
                                                <p><a class="btn light" href="soluciones.php?id_servicio=<?= (int) ($etapaActual['id_servicio'] ?? 0) ?>">Administrar soluciones</a></p>
                                            <?php endif; ?>
                                            <div data-solution-observation hidden>
                                                <label for="comentario_cierre">¿Qué hizo para solucionar el caso? *</label>
                                                <textarea id="comentario_cierre" name="comentario_cierre" maxlength="2000" required disabled placeholder="Describa obligatoriamente el diagnóstico, la acción realizada y el resultado obtenido"></textarea>
                                            </div>
                                            <div class="checklist-gate" data-checklist-gate <?= $checklistObligatorioPendiente === 0 ? 'hidden' : '' ?>>
                                                <span class="checklist-gate-icon" aria-hidden="true">⚠</span>
                                                <div><strong>Checklist obligatorio pendiente.</strong><br><span data-checklist-gate-text>Complete y guarde los <?= $checklistObligatorioPendiente ?> ítem(s) obligatorio(s) pendiente(s) antes de continuar.</span></div>
                                            </div>
                                            <div class="form-actions"><button class="primary checklist-gated-button" type="submit" data-checklist-action="Marcar caso como listo" aria-disabled="<?= $checklistObligatorioPendiente > 0 ? 'true' : 'false' ?>">Marcar caso como listo</button></div>
                                        <?php else: ?>
                                            <div class="notice-panel">
                                                <strong>Este servicio no tiene soluciones predeterminadas activas.</strong>
                                                <p>El administrador debe configurarlas antes de que el caso pueda marcarse como listo.</p>
                                                <?php if ($rol === 1): ?>
                                                    <a class="btn light" href="soluciones.php?id_servicio=<?= (int) ($etapaActual['id_servicio'] ?? 0) ?>">Configurar soluciones</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </details>

                            <?php if (empty($etapaActual['es_derivacion']) && (int) ($etapaActual['id_ticket_etapa_padre'] ?? 0) === 0): ?>
                            <details class="case-panel-toggle" style="margin-top:10px">
                                <summary>Solicitar cierre definitivo · resuelto en primer contacto</summary>
                                <div class="case-panel-toggle-body">
                                    <div class="alert info" style="margin-bottom:12px">
                                        Use esta opción únicamente cuando esta etapa resolvió completamente la necesidad y no es necesario continuar con las siguientes etapas del flujo.
                                    </div>
                                    <form method="post" data-checklist-protected-form data-confirm-message="¿Confirma que el caso fue resuelto en primer contacto? Si el solicitante aprueba, las etapas siguientes no se ejecutarán y el ticket se cerrará definitivamente?">
                                        <input type="hidden" name="csrf_token" value="<?= escaparFlujo($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="solicitar_cierre_definitivo">
                                        <input type="hidden" name="id_ticket" value="<?= $idTicketSeleccionado ?>">
                                        <input type="hidden" name="id_ticket_etapa" value="<?= (int) $etapaActual['id_ticket_etapa'] ?>">
                                        <?php if ($solucionesCaso): ?>
                                            <label for="id_solucion_primer_contacto">Solución aplicada *</label>
                                            <select id="id_solucion_primer_contacto" name="id_solucion" required>
                                                <option value="">Seleccione una solución predeterminada</option>
                                                <?php foreach ($solucionesCaso as $solucionCaso): ?>
                                                    <option value="<?= (int) $solucionCaso['id_solucion'] ?>"><?= escaparFlujo($solucionCaso['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="comentario_primer_contacto">Justificación y solución aplicada *</label>
                                            <textarea id="comentario_primer_contacto" name="comentario_cierre" maxlength="2000" required placeholder="Explique qué resolvió, cómo lo hizo y por qué no es necesario continuar el flujo"></textarea>
                                            <div class="checklist-gate" data-checklist-gate <?= $checklistObligatorioPendiente === 0 ? 'hidden' : '' ?>>
                                                <span class="checklist-gate-icon" aria-hidden="true">⚠</span>
                                                <div><strong>Checklist obligatorio pendiente.</strong><br><span data-checklist-gate-text>Complete y guarde los <?= $checklistObligatorioPendiente ?> ítem(s) obligatorio(s) pendiente(s) antes de continuar.</span></div>
                                            </div>
                                            <div class="form-actions"><button class="primary checklist-gated-button" type="submit" data-checklist-action="Solicitar cierre definitivo" aria-disabled="<?= $checklistObligatorioPendiente > 0 ? 'true' : 'false' ?>">Solicitar cierre definitivo</button></div>
                                        <?php else: ?>
                                            <div class="notice-panel"><strong>Este servicio no tiene soluciones predeterminadas activas.</strong><p>El administrador debe configurarlas antes de solicitar el cierre.</p></div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </details>
                            <?php endif; ?>
                        <?php elseif ($estadoNodo !== 'listo_cierre'): ?>
                            <p class="muted">
                                <?= $estadoNodo === 'pausada'
                                    ? 'Este caso está pausado mientras finalizan sus hijos.'
                                    : 'Solo el gestor asignado puede gestionar este caso.' ?>
                            </p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

            </div>
        </div>
                </div>
            </div>
        </dialog>
        <?php endif; ?>
        <?php if ($rol === 2): ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script>
    (() => {
        window.__MESA_GESTOR_BANDEJAS_MODAL_VERSION__ = '2026-08-10.2';
        window.__MESA_DERIVACION_CATALOGO_VERSION__ = '2026-08-10.3';

        const entradaBusquedaGestor = document.getElementById('buscar-casos-gestor');
        const selectorEstadoGestor = document.getElementById('estado-casos-gestor');
        const botonLimpiarGestor = document.getElementById('limpiar-filtro-gestor');
        const contadorGestor = document.getElementById('contador-casos-gestor');
        const filaSinResultadosGestor = document.getElementById('sin-resultados-gestor');
        const filasGestor = Array.from(document.querySelectorAll('[data-manager-case-row]'));
        let tareaFiltroGestor = 0;

        const normalizarTextoGestor = (valor) => String(valor || '')
            .toLocaleLowerCase('es')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();

        const guardarFiltrosGestor = () => {
            if (!window.history || typeof window.history.replaceState !== 'function') return;

            const url = new URL(window.location.href);
            const busqueda = entradaBusquedaGestor?.value.trim() || '';
            const estado = selectorEstadoGestor?.value || 'todos';

            if (busqueda) url.searchParams.set('buscar', busqueda);
            else url.searchParams.delete('buscar');

            if (estado !== 'todos') url.searchParams.set('estado_busqueda', estado);
            else url.searchParams.delete('estado_busqueda');

            window.history.replaceState({}, '', url.toString());
        };

        const aplicarFiltrosGestor = (persistir = false) => {
            if (!entradaBusquedaGestor || !selectorEstadoGestor || !filasGestor.length) return;

            const texto = normalizarTextoGestor(entradaBusquedaGestor.value);
            const estado = selectorEstadoGestor.value || 'todos';
            let visibles = 0;

            filasGestor.forEach((fila) => {
                const coincideTexto = !texto || normalizarTextoGestor(fila.textContent).includes(texto);
                const coincideEstado = estado === 'todos' || fila.dataset.managerCaseState === estado;
                const visible = coincideTexto && coincideEstado;
                fila.hidden = !visible;
                if (visible) visibles += 1;
            });

            if (contadorGestor) {
                contadorGestor.textContent = visibles === 1
                    ? '1 caso encontrado'
                    : `${visibles} casos encontrados`;
            }
            if (filaSinResultadosGestor) filaSinResultadosGestor.hidden = visibles !== 0;
            if (botonLimpiarGestor) botonLimpiarGestor.hidden = entradaBusquedaGestor.value.length === 0;
            if (persistir) guardarFiltrosGestor();
        };

        if (entradaBusquedaGestor && selectorEstadoGestor && filasGestor.length) {
            entradaBusquedaGestor.addEventListener('input', () => {
                if (tareaFiltroGestor) window.cancelAnimationFrame(tareaFiltroGestor);
                tareaFiltroGestor = window.requestAnimationFrame(() => {
                    tareaFiltroGestor = 0;
                    aplicarFiltrosGestor(true);
                });
            });
            selectorEstadoGestor.addEventListener('change', () => aplicarFiltrosGestor(true));
            botonLimpiarGestor?.addEventListener('click', () => {
                entradaBusquedaGestor.value = '';
                aplicarFiltrosGestor(true);
                entradaBusquedaGestor.focus();
            });
            document.querySelectorAll('[data-manager-case-view]').forEach((enlace) => {
                enlace.addEventListener('click', () => {
                    const url = new URL(enlace.href, window.location.href);
                    const busqueda = entradaBusquedaGestor.value.trim();
                    const estado = selectorEstadoGestor.value || 'todos';

                    if (busqueda) url.searchParams.set('buscar', busqueda);
                    else url.searchParams.delete('buscar');

                    if (estado !== 'todos') url.searchParams.set('estado_busqueda', estado);
                    else url.searchParams.delete('estado_busqueda');

                    enlace.href = url.toString();
                });
            });
            aplicarFiltrosGestor(false);
        }

        const modalTicketGestor = document.getElementById('manager-ticket-modal');

        if (modalTicketGestor) {
            const cerrarModalTicketGestor = () => {
                const ruta = modalTicketGestor.dataset.closeUrl
                    || 'flujoTicket.php?modo=mis_tickets&bandeja=abiertos';
                window.location.assign(ruta);
            };

            document.body.classList.add('manager-ticket-modal-open');
            modalTicketGestor.querySelector('[data-manager-ticket-close]')
                ?.addEventListener('click', cerrarModalTicketGestor);
            modalTicketGestor.addEventListener('click', (event) => {
                if (event.target === modalTicketGestor) cerrarModalTicketGestor();
            });
            document.addEventListener('keydown', (event) => {
                const casoInterno = document.getElementById('caseDialog');
                const encuestaInterna = document.getElementById('finalRatingDialog');

                if (
                    event.key === 'Escape'
                    && !casoInterno?.open
                    && !encuestaInterna?.open
                ) {
                    event.preventDefault();
                    cerrarModalTicketGestor();
                }
            });
            window.requestAnimationFrame(() => {
                modalTicketGestor.querySelector('[data-manager-ticket-close]')?.focus();
            });
        }

        const finalRatingDialog = document.getElementById('finalRatingDialog');

        if (finalRatingDialog) {
            document.querySelectorAll('[data-open-final-rating]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (!finalRatingDialog.open) finalRatingDialog.showModal();
                });
            });

            finalRatingDialog.querySelector('[data-close-final-rating]')
                ?.addEventListener('click', () => finalRatingDialog.close());

            finalRatingDialog.querySelector('[data-final-rating-form]')
                ?.addEventListener('submit', (event) => {
                    if (!window.confirm(
                        '¿Confirma la encuesta única del servicio solicitado y el cierre definitivo de este ticket anterior?'
                    )) {
                        event.preventDefault();
                    }
                });

            if (
                finalRatingDialog.dataset.autoOpen === 'true'
                && !finalRatingDialog.open
            ) {
                finalRatingDialog.showModal();
            }
        }

        const tree = document.querySelector('[data-case-tree]');

        if (tree) {
            const branches = Array.from(tree.querySelectorAll('[data-case-branch]'));
            const storageKey = `mesa-servicio-${tree.dataset.treeKey || 'arbol'}`;

            try {
                const saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}');

                branches.forEach((branch) => {
                    const id = branch.dataset.caseId || '';

                    if (branch.dataset.selectedPath !== 'true' && Object.prototype.hasOwnProperty.call(saved, id)) {
                        branch.open = Boolean(saved[id]);
                    }
                });
            } catch (error) {
                /* El árbol funciona aunque el navegador bloquee localStorage. */
            }

            const persist = () => {
                const state = {};
                branches.forEach((branch) => {
                    state[branch.dataset.caseId || ''] = branch.open;
                });

                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(state));
                } catch (error) {
                    /* Sin persistencia, el control de ramas sigue disponible. */
                }
            };

            branches.forEach((branch) => branch.addEventListener('toggle', persist));

            tree.querySelectorAll('.case-open').forEach((link) => {
                link.addEventListener('click', (event) => event.stopPropagation());
            });

            document.querySelector('[data-tree-expand]')?.addEventListener('click', () => {
                branches.forEach((branch) => { branch.open = true; });
                persist();
            });

            document.querySelector('[data-tree-collapse]')?.addEventListener('click', () => {
                branches.forEach((branch) => { branch.open = false; });
                persist();
            });
        }

        const dialog = document.getElementById('caseDialog');

        if (!dialog) {
            return;
        }

        const tabs = Array.from(dialog.querySelectorAll('[data-case-tab]'));
        const panels = Array.from(dialog.querySelectorAll('[data-case-panel]'));

        const activatePanel = (name) => {
            const validName = tabs.some((tab) => tab.dataset.caseTab === name)
                ? name
                : 'resumen';

            tabs.forEach((tab) => {
                const active = tab.dataset.caseTab === validName;
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.casePanel !== validName;
            });

            dialog.querySelector('.case-modal-content')?.scrollTo({top: 0});
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => activatePanel(tab.dataset.caseTab || 'resumen'));
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        dialog.addEventListener('close', () => {
            document.body.classList.remove('case-modal-open');
        });

        activatePanel(dialog.dataset.initialPanel || 'resumen');

        if (dialog.dataset.autoOpen === 'true') {
            window.requestAnimationFrame(() => {
                if (!dialog.open) {
                    dialog.showModal();
                    document.body.classList.add('case-modal-open');
                }
            });
        }

        const solutionSelect = dialog.querySelector('[data-solution-select]');
        const solutionObservation = dialog.querySelector('[data-solution-observation]');
        const solutionTextarea = solutionObservation?.querySelector('textarea');
        const solutionHelp = dialog.querySelector('[data-solution-help]');

        if (solutionSelect && solutionObservation && solutionTextarea) {
            const refreshSolution = () => {
                const option = solutionSelect.options[solutionSelect.selectedIndex];
                const selected = solutionSelect.value !== '';
                solutionObservation.hidden = !selected;
                solutionTextarea.disabled = !selected;

                if (solutionHelp) {
                    solutionHelp.textContent = selected && option?.dataset.description
                        ? option.dataset.description
                        : 'Seleccione la opción que corresponde al trabajo realizado.';
                }

                if (selected) {
                    window.requestAnimationFrame(() => solutionTextarea.focus());
                }
            };

            solutionSelect.addEventListener('change', refreshSolution);
            refreshSolution();
        }
    })();
</script>
<script>
    (() => {
        document.querySelectorAll('.derivation-form').forEach((form) => {
            const list = form.querySelector('[data-derivation-list]');
            const template = form.querySelector('[data-derivation-template]');
            const addButton = form.querySelector('[data-add-derivation]');
            const counter = form.querySelector('[data-derivation-count]');
            const servicesData = form.querySelector('[data-derivation-services-json]');
            const maximum = Number(form.dataset.maxDerivaciones || 20);
            let nextIndex = 1;
            let comboSequence = 0;
            let services = [];

            if (!list || !template || !addButton || !servicesData) {
                return;
            }

            try {
                services = JSON.parse(servicesData.textContent || '[]');
            } catch (error) {
                services = [];
            }

            const normalize = (value) => String(value || '')
                .toLocaleLowerCase('es')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();

            const rows = () => Array.from(list.querySelectorAll('[data-derivation-row]'));

            const closeServiceMenu = (row) => {
                const input = row.querySelector('[data-service-search]');
                const menu = row.querySelector('[data-service-menu]');
                const toggle = row.querySelector('[data-service-toggle]');

                if (menu) menu.hidden = true;
                if (input) input.setAttribute('aria-expanded', 'false');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            };

            const closeOtherServiceMenus = (currentRow = null) => {
                rows().forEach((row) => {
                    if (row !== currentRow) closeServiceMenu(row);
                });
            };

            const selectService = (row, service) => {
                const input = row.querySelector('[data-service-search]');
                const serviceId = row.querySelector('[data-service-id]');
                const help = row.querySelector('[data-service-help]');

                if (!input || !serviceId) return;

                input.value = String(service.nombre || '');
                input.setCustomValidity('');
                serviceId.value = String(service.id_servicio || '');

                if (help) {
                    help.textContent = `Seleccionado · Gestor: ${service.gestor || 'Sin gestor'} · SLA: ${service.sla || 'Sin SLA'}`;
                }

                closeServiceMenu(row);
            };

            const renderServiceOptions = (row, showAll = false) => {
                const catalog = row.querySelector('[data-derivation-catalog]');
                const input = row.querySelector('[data-service-search]');
                const menu = row.querySelector('[data-service-menu]');
                const toggle = row.querySelector('[data-service-toggle]');
                const help = row.querySelector('[data-service-help]');

                if (!catalog || !input || !menu || !toggle || !catalog.value) {
                    closeServiceMenu(row);
                    return;
                }

                const catalogId = String(catalog.value);
                const query = showAll ? '' : normalize(input.value);
                const matches = services.filter((service) =>
                    String(service.id_catalogo) === catalogId
                    && (!query || normalize(service.nombre).startsWith(query))
                );

                menu.replaceChildren();

                if (!matches.length) {
                    const empty = document.createElement('div');
                    empty.className = 'derivation-service-empty';
                    empty.textContent = query
                        ? `No hay servicios que comiencen por “${input.value.trim()}”.`
                        : 'Este catálogo no tiene servicios disponibles.';
                    menu.appendChild(empty);
                } else {
                    matches.forEach((service) => {
                        const option = document.createElement('button');

                        option.type = 'button';
                        option.className = 'derivation-service-option';
                        option.setAttribute('role', 'option');
                        option.dataset.serviceOption = String(service.id_servicio || '');
                        option.textContent = String(service.nombre || 'Servicio');
                        option.setAttribute(
                            'aria-label',
                            `${service.nombre || 'Servicio'} · Gestor: ${service.gestor || 'Sin gestor'} · SLA: ${service.sla || 'Sin SLA'}`
                        );
                        option.addEventListener('click', () => selectService(row, service));
                        option.addEventListener('keydown', (event) => {
                            const options = Array.from(
                                menu.querySelectorAll('[data-service-option]')
                            );
                            const index = options.indexOf(option);

                            if (event.key === 'ArrowDown') {
                                event.preventDefault();
                                options[Math.min(index + 1, options.length - 1)]?.focus();
                            } else if (event.key === 'ArrowUp') {
                                event.preventDefault();

                                if (index <= 0) input.focus();
                                else options[index - 1]?.focus();
                            } else if (event.key === 'Escape') {
                                event.preventDefault();
                                closeServiceMenu(row);
                                input.focus();
                            }
                        });
                        menu.appendChild(option);
                    });
                }

                if (help && !row.querySelector('[data-service-id]')?.value) {
                    if (showAll || !query) {
                        help.textContent = matches.length === 1
                            ? '1 servicio disponible. Puede seleccionarlo o escribir para filtrar.'
                            : `${matches.length} servicios disponibles. Puede seleccionar uno o escribir para filtrar.`;
                    } else {
                        help.textContent = matches.length === 1
                            ? '1 servicio coincide con el inicio escrito.'
                            : `${matches.length} servicios coinciden con el inicio escrito.`;
                    }
                }

                closeOtherServiceMenus(row);
                menu.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-expanded', 'true');
            };

            const resetService = (row) => {
                const catalog = row.querySelector('[data-derivation-catalog]');
                const input = row.querySelector('[data-service-search]');
                const serviceId = row.querySelector('[data-service-id]');
                const toggle = row.querySelector('[data-service-toggle]');
                const help = row.querySelector('[data-service-help]');

                if (!input || !serviceId || !toggle) return;

                input.value = '';
                input.setCustomValidity('');
                serviceId.value = '';
                input.disabled = !catalog?.value;
                toggle.disabled = !catalog?.value;
                input.placeholder = catalog?.value
                    ? 'Seleccione con la flecha o escriba, por ejemplo: cont'
                    : 'Seleccione primero un catálogo';

                if (help) {
                    help.textContent = catalog?.value
                        ? 'Use la flecha para ver todos los servicios o escriba para filtrarlos.'
                        : 'Los servicios se habilitan al seleccionar el catálogo.';
                }

                closeServiceMenu(row);
            };

            const initializeRow = (row) => {
                const catalog = row.querySelector('[data-derivation-catalog]');
                const input = row.querySelector('[data-service-search]');
                const serviceId = row.querySelector('[data-service-id]');
                const menu = row.querySelector('[data-service-menu]');
                const toggle = row.querySelector('[data-service-toggle]');

                if (!catalog || !input || !serviceId || !menu || !toggle) return;

                comboSequence += 1;
                menu.id = `derivation-service-list-${comboSequence}`;
                input.setAttribute('aria-controls', menu.id);
                toggle.setAttribute('aria-controls', menu.id);

                catalog.addEventListener('change', () => {
                    resetService(row);

                    if (catalog.value) {
                        input.focus();
                        renderServiceOptions(row, true);
                    }
                });

                toggle.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    if (toggle.disabled) return;

                    if (!menu.hidden) {
                        closeServiceMenu(row);
                        return;
                    }

                    input.focus({preventScroll: true});
                    renderServiceOptions(row, true);
                });

                input.addEventListener('focus', () => renderServiceOptions(row));
                input.addEventListener('input', () => {
                    serviceId.value = '';
                    input.setCustomValidity('');
                    renderServiceOptions(row);
                });
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        renderServiceOptions(row);
                        menu.querySelector('[data-service-option]')?.focus();
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        closeServiceMenu(row);
                    }
                });

                resetService(row);
            };

            const refresh = () => {
                const total = rows().length;
                list.querySelectorAll('[data-remove-derivation]').forEach((button) => {
                    button.hidden = total === 1;
                });
                addButton.disabled = total >= maximum;

                if (counter) {
                    counter.textContent = `${total} derivación${total === 1 ? '' : 'es'} preparada${total === 1 ? '' : 's'}`;
                }
            };

            addButton.addEventListener('click', () => {
                if (rows().length >= maximum) {
                    return;
                }

                const wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
                const row = wrapper.firstElementChild;

                if (row) {
                    list.appendChild(row);
                    initializeRow(row);
                    refresh();
                    row.querySelector('[data-derivation-catalog]')?.focus();
                }
            });

            list.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-derivation]');

                if (!button || rows().length === 1) {
                    return;
                }

                button.closest('[data-derivation-row]')?.remove();
                refresh();
            });

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-derivation-catalog]')) {
                    return;
                }

                if (!event.target.closest('[data-service-combobox]')) {
                    closeOtherServiceMenus();
                }
            });

            form.addEventListener('submit', (event) => {
                let firstInvalid = null;
                const selected = rows().map((row) => {
                    const catalog = row.querySelector('[data-derivation-catalog]');
                    const input = row.querySelector('[data-service-search]');
                    const serviceId = row.querySelector('[data-service-id]');
                    const selectedService = services.find((service) =>
                        String(service.id_servicio) === String(serviceId?.value || '')
                        && String(service.id_catalogo) === String(catalog?.value || '')
                    );

                    if (!catalog?.value) {
                        firstInvalid ||= catalog;
                    } else if (!selectedService) {
                        input?.setCustomValidity(
                            'Escriba el inicio y seleccione un servicio de la lista.'
                        );
                        firstInvalid ||= input;
                    } else {
                        input?.setCustomValidity('');
                    }

                    return serviceId?.value || '';
                });

                if (firstInvalid) {
                    event.preventDefault();
                    firstInvalid.reportValidity();
                    firstInvalid.focus();
                    return;
                }

                const repeated = selected.some((value, index) =>
                    value !== '' && selected.indexOf(value) !== index
                );

                if (repeated) {
                    event.preventDefault();
                    window.alert('No repita la misma área y servicio en esta derivación múltiple.');
                    return;
                }

                if (!window.confirm(`¿Crear ${selected.length} ticket(s) derivado(s) y pausar el SLA de la etapa actual?`)) {
                    event.preventDefault();
                }
            });

            rows().forEach(initializeRow);
            refresh();
        });
    })();
</script>
<?php if ($rol === 2 && $etapaActual && $puedeGestionar): ?>
<script>
    (() => {
        const checklistForm = document.querySelector('[data-case-checklist-form]');
        const protectedForms = Array.from(document.querySelectorAll('[data-checklist-protected-form]'));
        const requiredChecks = checklistForm
            ? Array.from(checklistForm.querySelectorAll('[data-required-checklist]'))
            : [];
        const initiallyPending = <?= (int) $checklistObligatorioPendiente ?>;
        let checklistWasEdited = false;

        const pendingRows = () => requiredChecks.filter((checkbox) => !checkbox.checked);

        const markMissingRows = () => {
            requiredChecks.forEach((checkbox) => {
                checkbox.closest('[data-required-checklist-row]')?.classList.toggle(
                    'checklist-row-missing',
                    !checkbox.checked
                );
            });
        };

        const blockMessage = () => {
            const pending = pendingRows().length;
            if (checklistWasEdited) {
                return pending > 0
                    ? `Faltan ${pending} ítem(s) obligatorio(s). Márquelos y pulse “Guardar checklist” antes de continuar.`
                    : 'El checklist tiene cambios sin guardar. Pulse “Guardar checklist” antes de continuar.';
            }
            return `Faltan ${Math.max(initiallyPending, pendingRows().length)} ítem(s) obligatorio(s). Complete y guarde el checklist antes de continuar.`;
        };

        const isBlocked = () => initiallyPending > 0 || checklistWasEdited;

        const refreshGate = () => {
            const blocked = isBlocked();
            protectedForms.forEach((form) => {
                const gate = form.querySelector('[data-checklist-gate]');
                const text = form.querySelector('[data-checklist-gate-text]');
                const button = form.querySelector('[data-checklist-action]');
                if (gate) gate.hidden = !blocked;
                if (text && blocked) text.textContent = blockMessage();
                if (button) button.setAttribute('aria-disabled', blocked ? 'true' : 'false');
            });
            markMissingRows();
        };

        requiredChecks.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                checklistWasEdited = true;
                refreshGate();
            });
        });

        protectedForms.forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (isBlocked()) {
                    event.preventDefault();
                    window.alert(`⚠ Checklist obligatorio pendiente\n\n${blockMessage()}`);
                    checklistForm?.closest('details')?.setAttribute('open', '');
                    checklistForm?.scrollIntoView({behavior: 'smooth', block: 'center'});
                    pendingRows()[0]?.focus({preventScroll: true});
                    return;
                }

                const confirmation = form.dataset.confirmMessage || '';
                if (confirmation && !window.confirm(confirmation)) {
                    event.preventDefault();
                }
            });
        });

        refreshGate();
    })();
</script>
<?php endif; ?>
<?php if ($rol === 2 && $modoGestor === 'nuevo' && !$ticketSeleccionado): ?>
<script>
    (() => {
        const catalogButtons = Array.from(document.querySelectorAll('.catalog-option'));
        const serviceButtons = Array.from(document.querySelectorAll('.service-option'));
        const serviceEmptyStates = Array.from(document.querySelectorAll('.service-empty'));
        const servicePanel = document.getElementById('servicePanel');
        const servicePanelTitle = document.getElementById('servicePanelTitle');
        const formPanel = document.getElementById('ticketFormPanel');
        const processInput = document.getElementById('id_proceso');
        const selectedServiceName = document.getElementById('selectedServiceName');
        const selectedServiceFlow = document.getElementById('selectedServiceFlow');
        const changeServiceButton = document.getElementById('changeServiceButton');
        const ticketForm = document.getElementById('ticketCreateForm');
        const wizardSteps = Array.from(document.querySelectorAll('[data-wizard-step]'));

        if (!catalogButtons.length || !servicePanel || !formPanel || !processInput) {
            return;
        }

        const activateStep = (step) => {
            wizardSteps.forEach((item) => {
                const current = Number(item.dataset.wizardStep || 0);
                item.classList.toggle('active', current <= step);
            });
        };

        const showServices = (catalogButton) => {
            const catalogId = catalogButton.dataset.catalogo || '';
            const catalogName = catalogButton.dataset.nombre || 'Área';

            catalogButtons.forEach((button) => {
                const active = button === catalogButton;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            serviceButtons.forEach((button) => {
                button.hidden = button.dataset.catalogo !== catalogId;
                button.classList.remove('active');
            });

            serviceEmptyStates.forEach((emptyState) => {
                emptyState.hidden = emptyState.dataset.catalogo !== catalogId;
            });

            servicePanelTitle.textContent = `Servicios de ${catalogName}`;
            servicePanel.hidden = false;
            formPanel.hidden = true;
            processInput.value = '';
            activateStep(2);

            requestAnimationFrame(() => {
                servicePanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            });
        };

        catalogButtons.forEach((button) => {
            button.addEventListener('click', () => showServices(button));
        });

        serviceButtons.forEach((button) => {
            button.addEventListener('click', () => {
                serviceButtons.forEach((item) => item.classList.remove('active'));
                button.classList.add('active');

                processInput.value = button.dataset.proceso || '';
                selectedServiceName.textContent = `${button.dataset.catalogoNombre} · ${button.dataset.servicioNombre}`;
                selectedServiceFlow.textContent = `Flujo de atención: ${button.dataset.flujoNombre}`;
                formPanel.hidden = false;
                activateStep(3);

                requestAnimationFrame(() => {
                    formPanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                });
            });
        });

        if (changeServiceButton) {
            changeServiceButton.addEventListener('click', () => {
                formPanel.hidden = true;
                processInput.value = '';
                serviceButtons.forEach((item) => item.classList.remove('active'));
                activateStep(2);
                servicePanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            });
        }

        if (ticketForm) {
            ticketForm.addEventListener('submit', (event) => {
                if (processInput.value === '') {
                    event.preventDefault();
                    window.alert('Seleccione primero el catálogo y el servicio que desea solicitar.');
                    servicePanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                }
            });
        }
    })();
</script>
<?php endif; ?>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
