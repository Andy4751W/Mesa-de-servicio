<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$idPaisOperacion = paisExigirContexto();
$nombrePaisOperacion = trim(paisContextoNombre());
$codigoPaisOperacion = trim(paisContextoCodigo());

if ($nombrePaisOperacion === '') {
    $nombrePaisOperacion = 'País activo';
}

if ($codigoPaisOperacion === '') {
    $codigoPaisOperacion = 'PA';
}

function escaparAdminCasos(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function etiquetaEstadoAdminCasos(string $estado): string
{
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En proceso',
        'en_espera_solicitante' => 'En espera del solicitante',
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

function claseEstadoAdminCasos(string $estado): string
{
    return match ($estado) {
        'pendiente', 'en_proceso' => 'proceso',
        'en_espera_solicitante', 'pausada', 'listo_cierre',
        'pendiente_calificacion' => 'espera',
        'completada', 'cerrado' => 'cerrado',
        'cancelado', 'cancelada' => 'cancelado',
        default => 'neutro',
    };
}

function responderJsonAdminCasos(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

/**
 * Devuelve los nodos en orden visual y asigna una numeración estable:
 * Etapa 1, Caso hijo 1.1, Caso hijo 1.1.1, etc.
 *
 * @param array<int, array<string, mixed>> $etapas
 * @return array<int, array<string, mixed>>
 */
function prepararArbolAdminCasos(array $etapas): array
{
    $porPadre = [];
    $porId = [];

    foreach ($etapas as $etapa) {
        $id = (int) ($etapa['id_ticket_etapa'] ?? 0);
        if ($id < 1) {
            continue;
        }

        $padre = (int) ($etapa['id_ticket_etapa_padre'] ?? 0);
        $porId[$id] = $etapa;
        $porPadre[$padre][] = $etapa;
    }

    foreach ($porPadre as &$grupo) {
        usort(
            $grupo,
            static fn (array $a, array $b): int => [
                (int) ($a['orden'] ?? 0),
                (int) ($a['id_ticket_etapa'] ?? 0),
            ] <=> [
                (int) ($b['orden'] ?? 0),
                (int) ($b['id_ticket_etapa'] ?? 0),
            ]
        );
    }
    unset($grupo);

    $salida = [];
    $visitados = [];
    $agregar = static function (
        array $nodo,
        int $nivel,
        string $codigo,
        bool $esEtapa
    ) use (&$agregar, &$salida, &$visitados, $porPadre): void {
        $id = (int) ($nodo['id_ticket_etapa'] ?? 0);
        if ($id < 1 || isset($visitados[$id])) {
            return;
        }

        $visitados[$id] = true;
        $salida[] = [
            'id' => $id,
            'padre_id' => (int) ($nodo['id_ticket_etapa_padre'] ?? 0),
            'nivel' => $nivel,
            'codigo' => $codigo,
            'tipo' => $esEtapa ? 'etapa' : 'hijo',
            'catalogo' => (string) ($nodo['catalogo_nombre'] ?? ''),
            'servicio' => (string) ($nodo['servicio_nombre'] ?? ''),
            'gestor' => (string) ($nodo['gestor_nombre'] ?? 'Sin asignar'),
            'creador' => (string) ($nodo['creador_caso_nombre'] ?? 'Usuario eliminado'),
            'estado' => (string) ($nodo['estado'] ?? 'pendiente'),
            'sla_nombre' => (string) ($nodo['sla_nombre'] ?? 'Sin SLA'),
            'sla_tiempo' => (int) ($nodo['sla_tiempo'] ?? 0),
            'sla_unidad' => (string) ($nodo['sla_unidad'] ?? ''),
            'sla_estado' => (string) ($nodo['estado_sla_actual'] ?? 'sin_iniciar'),
            'sla_consumido' => (int) ($nodo['sla_minutos_consumidos_actuales'] ?? 0),
            'sla_total' => (int) ($nodo['sla_minutos_total'] ?? 0),
            'fecha_creacion' => $nodo['creado_en'] ?? null,
            'fecha_activacion' => $nodo['fecha_activacion'] ?? null,
            'fecha_vencimiento' => $nodo['fecha_vencimiento'] ?? null,
            'fecha_listo' => $nodo['fecha_marcado_listo'] ?? null,
            'fecha_finalizacion' => $nodo['fecha_finalizacion'] ?? null,
            'minutos_solucion' => $nodo['minutos_hasta_listo']
                ?? $nodo['minutos_atencion']
                ?? null,
            'resultado_sla' => (string) (
                $nodo['resultado_sla_listo']
                ?? $nodo['resultado_sla']
                ?? 'sin_iniciar'
            ),
            'solucion' => (string) ($nodo['solucion_nombre'] ?? ''),
            'observacion' => (string) ($nodo['comentario_cierre'] ?? ''),
            'motivo_derivacion' => (string) ($nodo['motivo_derivacion'] ?? ''),
            'reaperturas' => (int) ($nodo['cantidad_reaperturas'] ?? 0),
            'calificacion' => $nodo['calificacion'] !== null
                ? (int) $nodo['calificacion']
                : null,
            'calificacion_area' => $nodo['calificacion_area'] !== null
                ? (int) $nodo['calificacion_area']
                : null,
            'calificacion_tiempo' => $nodo['calificacion_tiempo'] !== null
                ? (int) $nodo['calificacion_tiempo']
                : null,
            'comentario_calificacion' => (string) ($nodo['comentario_calificacion'] ?? ''),
            'evaluador' => (string) ($nodo['evaluador_nombre'] ?? 'Sin calificar'),
            'es_actual' => !empty($nodo['es_actual']),
        ];

        foreach ($porPadre[$id] ?? [] as $indice => $hijo) {
            $agregar(
                $hijo,
                $nivel + 1,
                $codigo . '.' . ($indice + 1),
                false
            );
        }
    };

    foreach ($porPadre[0] ?? [] as $indice => $raiz) {
        $agregar($raiz, 0, (string) ($indice + 1), true);
    }

    /* Los registros históricos sin una raíz válida permanecen consultables. */
    foreach ($porId as $id => $etapa) {
        if (!isset($visitados[$id])) {
            $etapa['id_ticket_etapa_padre'] = null;
            $agregar($etapa, 0, (string) $id, false);
        }
    }

    return $salida;
}

function etiquetaAuditoriaAdminCasos(
    ?array $nodo,
    int $idTicket,
    int $idTicketEtapa = 0
): string {
    if (!$nodo) {
        return $idTicketEtapa > 0
            ? 'Etapa o derivación #' . $idTicketEtapa
            : 'Caso padre ' . $idTicket;
    }

    return ($nodo['tipo'] ?? '') === 'hijo'
        ? 'Caso hijo ' . (string) ($nodo['codigo'] ?? $idTicketEtapa)
        : 'Etapa ' . (string) ($nodo['codigo'] ?? $idTicketEtapa);
}

$moduloDisponible = flujoModuloInstalado($conn)
    && flujoModuloSolucionesInstalado($conn)
    && flujoModuloAprobacionCasosInstalado($conn);

/*
 * El detalle se consulta solo al abrir un caso. De esta manera, aunque existan
 * miles de casos, la página inicial no carga todos los árboles y sus datos.
 */
if ((string) ($_GET['ajax'] ?? '') === 'arbol') {
    if (!$moduloDisponible) {
        responderJsonAdminCasos([
            'ok' => false,
            'mensaje' => 'El módulo de tickets todavía no está instalado por completo.',
        ], 503);
    }

    $idTicket = filter_input(INPUT_GET, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
    if ($idTicket < 1) {
        responderJsonAdminCasos([
            'ok' => false,
            'mensaje' => 'El número de caso no es válido.',
        ], 422);
    }

    try {
        $stmt = $conn->prepare(
            "SELECT
                t.id_ticket,
                t.titulo,
                t.descripcion,
                t.estado_flujo,
                t.id_etapa_actual,
                t.fecha_creacion,
                t.fecha_finalizacion,
                t.actualizado_en,
                p.nombre AS flujo_nombre,
                COALESCE(u.nombre, 'Usuario eliminado') AS solicitante
             FROM tickets AS t
             INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
             LEFT JOIN usuarios AS u ON u.id_usuario = t.id_usuario
             WHERE t.id_ticket = ?
               AND t.id_pais_operacion = ?
             LIMIT 1"
        );
        $stmt->bind_param('ii', $idTicket, $idPaisOperacion);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ticket) {
            responderJsonAdminCasos([
                'ok' => false,
                'mensaje' => 'El caso no existe en el país activo.',
            ], 404);
        }

        $idEtapaActual = (int) ($ticket['id_etapa_actual'] ?? 0);
        $etapas = flujoObtenerEtapasTicket($conn, $idTicket);
        foreach ($etapas as &$etapa) {
            $etapa['es_actual'] = (int) ($etapa['id_ticket_etapa'] ?? 0)
                === $idEtapaActual;
        }
        unset($etapa);

        $nodos = prepararArbolAdminCasos($etapas);
        $totalHijos = count(array_filter(
            $nodos,
            static fn (array $nodo): bool => $nodo['tipo'] === 'hijo'
        ));
        $totalEtapas = count($nodos) - $totalHijos;

        $nodosPorId = [];
        foreach ($nodos as $nodo) {
            $nodosPorId[(int) $nodo['id']] = $nodo;
        }

        /*
         * La auditoría administrativa se carga junto con el árbol únicamente
         * cuando se abre un caso. Cada conversación conserva la etapa o
         * derivación a la que pertenece y permanece estrictamente en lectura.
         */
        $conversacionesAuditoria = [];
        $conversaciones = flujoConversacionesDisponibles(
            $conn,
            $idTicket,
            (int) ($_SESSION['usuario_id'] ?? 0),
            1
        );

        foreach ($conversaciones as $conversacion) {
            $idTicketEtapa = (int) ($conversacion['id_ticket_etapa'] ?? 0);
            if ($idTicketEtapa < 1) {
                continue;
            }

            $mensajesAuditoria = [];
            foreach (
                flujoComunicacionesNodo($conn, $idTicket, $idTicketEtapa)
                as $mensaje
            ) {
                $mensajesAuditoria[] = [
                    'id' => (int) ($mensaje['id_comunicacion'] ?? 0),
                    'emisor' => (string) ($mensaje['emisor'] ?? 'Usuario eliminado'),
                    'emisor_rol' => (int) ($mensaje['emisor_rol'] ?? 0),
                    'tipo' => (string) ($mensaje['tipo'] ?? 'publica'),
                    'mensaje' => (string) ($mensaje['mensaje'] ?? ''),
                    'fecha' => $mensaje['creado_en'] ?? null,
                ];
            }

            $nodoConversacion = $nodosPorId[$idTicketEtapa] ?? null;
            $conversacionesAuditoria[] = [
                'id' => $idTicketEtapa,
                'nodo_id' => $idTicketEtapa,
                'etiqueta' => etiquetaAuditoriaAdminCasos(
                    $nodoConversacion,
                    $idTicket,
                    $idTicketEtapa
                ),
                'catalogo' => (string) ($conversacion['catalogo_nombre'] ?? ''),
                'servicio' => (string) ($conversacion['servicio_nombre'] ?? ''),
                'tipo' => (string) ($conversacion['tipo_conversacion'] ?? 'flujo'),
                'origen' => (string) (
                    $conversacion['gestor_origen']
                    ?? (($conversacion['tipo_conversacion'] ?? '') === 'derivacion'
                        ? 'Gestor de origen'
                        : ($ticket['solicitante'] ?? 'Solicitante'))
                ),
                'destino' => (string) (
                    $conversacion['gestor_destino'] ?? 'Gestor asignado'
                ),
                'total_archivos' => (int) ($conversacion['total_adjuntos'] ?? 0),
                'mensajes' => $mensajesAuditoria,
            ];
        }

        $accionesAuditoria = [];
        $stmt = $conn->prepare(
            "SELECT
                h.id_historial,
                h.id_ticket_etapa,
                h.accion,
                h.detalle,
                h.creado_en,
                COALESCE(u.nombre, 'Sistema') AS usuario,
                COALESCE(u.id_rol, 0) AS usuario_rol
             FROM solicitud_historial AS h
             LEFT JOIN usuarios AS u ON u.id_usuario = h.id_usuario
             WHERE h.id_ticket = ?
             ORDER BY h.creado_en, h.id_historial"
        );
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $resultadoAcciones = $stmt->get_result();
        while ($accion = $resultadoAcciones->fetch_assoc()) {
            $idTicketEtapa = (int) ($accion['id_ticket_etapa'] ?? 0);
            $accionesAuditoria[] = [
                'id' => (int) ($accion['id_historial'] ?? 0),
                'nodo_id' => $idTicketEtapa,
                'origen' => etiquetaAuditoriaAdminCasos(
                    $nodosPorId[$idTicketEtapa] ?? null,
                    $idTicket,
                    $idTicketEtapa
                ),
                'accion' => (string) ($accion['accion'] ?? 'Acción registrada'),
                'detalle' => (string) ($accion['detalle'] ?? ''),
                'usuario' => (string) ($accion['usuario'] ?? 'Sistema'),
                'usuario_rol' => (int) ($accion['usuario_rol'] ?? 0),
                'fecha' => $accion['creado_en'] ?? null,
            ];
        }
        $stmt->close();

        $archivosAuditoria = [];
        $stmt = $conn->prepare(
            "SELECT
                a.id_adjunto,
                a.id_ticket_etapa,
                a.nombre_original,
                a.tipo_mime,
                a.tamano,
                a.creado_en,
                COALESCE(u.nombre, 'Usuario eliminado') AS usuario
             FROM solicitud_adjuntos AS a
             LEFT JOIN usuarios AS u ON u.id_usuario = a.id_usuario
             WHERE a.id_ticket = ?
             ORDER BY a.creado_en, a.id_adjunto"
        );
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $resultadoArchivos = $stmt->get_result();
        while ($archivo = $resultadoArchivos->fetch_assoc()) {
            $idTicketEtapa = (int) ($archivo['id_ticket_etapa'] ?? 0);
            $archivosAuditoria[] = [
                'id' => (int) ($archivo['id_adjunto'] ?? 0),
                'nodo_id' => $idTicketEtapa,
                'origen' => etiquetaAuditoriaAdminCasos(
                    $nodosPorId[$idTicketEtapa] ?? null,
                    $idTicket,
                    $idTicketEtapa
                ),
                'nombre' => (string) ($archivo['nombre_original'] ?? 'Archivo'),
                'tipo' => (string) ($archivo['tipo_mime'] ?? ''),
                'tamano' => (int) ($archivo['tamano'] ?? 0),
                'usuario' => (string) ($archivo['usuario'] ?? 'Usuario eliminado'),
                'fecha' => $archivo['creado_en'] ?? null,
            ];
        }
        $stmt->close();

        responderJsonAdminCasos([
            'ok' => true,
            'caso' => [
                'id' => (int) $ticket['id_ticket'],
                'titulo' => (string) ($ticket['titulo'] ?? ''),
                'descripcion' => (string) ($ticket['descripcion'] ?? ''),
                'solicitante' => (string) ($ticket['solicitante'] ?? ''),
                'flujo' => (string) ($ticket['flujo_nombre'] ?? ''),
                'estado' => (string) ($ticket['estado_flujo'] ?? ''),
                'fecha_creacion' => $ticket['fecha_creacion'] ?? null,
                'fecha_finalizacion' => $ticket['fecha_finalizacion'] ?? null,
                'actualizado_en' => $ticket['actualizado_en'] ?? null,
                'total_etapas' => $totalEtapas,
                'total_hijos' => $totalHijos,
            ],
            'nodos' => $nodos,
            'auditoria' => [
                'conversaciones' => $conversacionesAuditoria,
                'acciones' => $accionesAuditoria,
                'archivos' => $archivosAuditoria,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('Árbol administrativo de casos: ' . $e->getMessage());
        responderJsonAdminCasos([
            'ok' => false,
            'mensaje' => 'No fue posible cargar la trazabilidad del caso.',
        ], 500);
    }
}

$casosPadre = [];

if ($moduloDisponible) {
    $resultado = $conn->query(
        "SELECT
            t.id_ticket,
            t.titulo,
            t.descripcion,
            t.estado_flujo,
            t.fecha_creacion,
            t.actualizado_en,
            p.nombre AS flujo_nombre,
            te.id_ticket_etapa,
            te.catalogo_nombre,
            te.servicio_nombre,
            te.estado AS estado_caso,
            COALESCE(solicitante.nombre, 'Usuario eliminado') AS solicitante,
            COALESCE(
                NULLIF(gestor.nombre, ''),
                NULLIF(te.gestor_nombre, ''),
                'Sin asignar'
            ) AS gestor_actual,
            (
                SELECT COUNT(*)
                FROM ticket_etapas AS te_total
                WHERE te_total.id_ticket = t.id_ticket
                  AND te_total.id_ticket_etapa_padre IS NULL
            ) AS total_etapas,
            (
                SELECT COUNT(*)
                FROM ticket_etapas AS te_hijo
                WHERE te_hijo.id_ticket = t.id_ticket
                  AND te_hijo.id_ticket_etapa_padre IS NOT NULL
            ) AS total_hijos,
            EXISTS (
                SELECT 1
                FROM ticket_etapas AS te_hijo_activo
                WHERE te_hijo_activo.id_ticket = t.id_ticket
                  AND te_hijo_activo.id_ticket_etapa_padre IS NOT NULL
                  AND te_hijo_activo.estado NOT IN ('completada', 'cancelada')
            ) AS tiene_hijo_activo
         FROM tickets AS t
         INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
         LEFT JOIN usuarios AS solicitante ON solicitante.id_usuario = t.id_usuario
         LEFT JOIN ticket_etapas AS te
            ON te.id_ticket_etapa = COALESCE(
                (
                    SELECT te_activa.id_ticket_etapa
                    FROM ticket_etapas AS te_activa
                    WHERE te_activa.id_ticket = t.id_ticket
                      AND te_activa.id_ticket_etapa_padre IS NULL
                      AND te_activa.estado IN (
                          'pendiente',
                          'en_proceso',
                          'en_espera_solicitante',
                          'pausada',
                          'listo_cierre'
                      )
                    ORDER BY te_activa.orden, te_activa.id_ticket_etapa
                    LIMIT 1
                ),
                (
                    SELECT te_ultima.id_ticket_etapa
                    FROM ticket_etapas AS te_ultima
                    WHERE te_ultima.id_ticket = t.id_ticket
                      AND te_ultima.id_ticket_etapa_padre IS NULL
                    ORDER BY te_ultima.orden DESC, te_ultima.id_ticket_etapa DESC
                    LIMIT 1
                )
            )
         LEFT JOIN usuarios AS gestor ON gestor.id_usuario = te.id_gestor
         WHERE t.id_proceso IS NOT NULL
           AND t.id_pais_operacion = {$idPaisOperacion}
         ORDER BY t.actualizado_en DESC, t.id_ticket DESC"
    );

    if ($resultado !== false) {
        while ($caso = $resultado->fetch_assoc()) {
            $estadoFlujo = (string) ($caso['estado_flujo'] ?? '');
            $caso['estado_actual'] = in_array(
                $estadoFlujo,
                ['cerrado', 'pendiente_calificacion'],
                true
            )
                ? $estadoFlujo
                : (string) ($caso['estado_caso'] ?? $estadoFlujo);
            $casosPadre[] = $caso;
        }
    }
}

$nombreAdministrador = trim((string) (
    $_SESSION['usuario'] ?? 'Administrador'
));
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
            min-height:100vh;margin:0;color:var(--text);background:var(--background);
            font:12.5px/1.4 Inter,"Segoe UI",Arial,sans-serif;
        }
        body.modal-caso-abierto { overflow:hidden; }
        button,input { font:inherit; }
        .shell { width:min(1180px,calc(100% - 24px));margin:auto;padding:12px 0 24px; }
        .topbar {
            min-height:58px;display:flex;align-items:center;justify-content:space-between;
            gap:14px;padding:9px 12px 9px 16px;border:1px solid var(--border);
            border-radius:12px;background:var(--surface);box-shadow:0 5px 18px rgba(16,42,67,.05);
        }
        .heading { display:flex;align-items:center;gap:10px;min-width:0; }
        .mark {
            width:34px;height:34px;display:grid;place-items:center;flex:0 0 auto;
            border-radius:9px;color:#fff;background:linear-gradient(145deg,var(--primary),var(--primary-dark));
            font-size:10px;font-weight:850;letter-spacing:.04em;
        }
        h1 { margin:0;color:var(--navy);font-size:17px;line-height:1.15; }
        .subtitle { margin:2px 0 0;color:var(--muted);font-size:10.5px; }
        .actions { display:flex;align-items:center;gap:6px;flex-wrap:wrap; }
        .btn {
            min-height:32px;display:inline-flex;align-items:center;justify-content:center;
            padding:7px 10px;border:1px solid #d8e5f2;border-radius:8px;
            color:#24577f;background:#f7fbff;text-decoration:none;font-size:10.5px;
            font-weight:750;white-space:nowrap;cursor:pointer;
        }
        .btn.primary { color:#fff;border-color:var(--primary);background:var(--primary); }
        .panel {
            margin-top:10px;overflow:hidden;border:1px solid var(--border);border-radius:12px;
            background:var(--surface);box-shadow:0 7px 22px rgba(16,42,67,.05);
        }
        .panel-head {
            min-height:47px;display:flex;align-items:center;justify-content:space-between;
            gap:12px;padding:9px 14px;border-bottom:1px solid var(--border);
        }
        .panel-title { display:flex;align-items:center;gap:9px;flex-wrap:wrap; }
        .panel-head h2 { margin:0;color:var(--navy);font-size:14px; }
        .country-scope,.count {
            display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;
            color:#2b628f;background:#edf5fd;font-size:9.5px;font-weight:800;
        }
        .country-scope { gap:6px;border:1px solid #d7e5f3;background:#f2f8fe; }
        .country-scope::before {
            width:6px;height:6px;border-radius:50%;background:#21a76a;content:"";
            box-shadow:0 0 0 3px rgba(33,167,106,.12);
        }
        .filter-bar {
            display:flex;align-items:end;justify-content:space-between;gap:14px;
            padding:11px 14px;border-bottom:1px solid var(--border);background:#fbfdff;
        }
        .filter-copy label { display:block;color:var(--navy);font-size:10.5px;font-weight:850; }
        .filter-copy p { margin:2px 0 0;color:var(--muted);font-size:9.5px; }
        .filter-control { width:min(430px,100%);display:flex;align-items:center;gap:6px; }
        .filter-control input {
            width:100%;min-height:34px;padding:7px 10px;border:1px solid #cfddea;
            border-radius:8px;color:var(--navy);background:#fff;outline:none;
        }
        .filter-control input:focus {
            border-color:var(--primary);box-shadow:0 0 0 3px rgba(15,111,236,.12);
        }
        .clear-filter {
            min-height:34px;padding:7px 10px;border:1px solid #d8e5f2;border-radius:8px;
            color:#315f85;background:#fff;font-size:10px;font-weight:800;cursor:pointer;
        }
        .clear-filter:disabled { opacity:.48;cursor:not-allowed; }
        .table-wrap { width:100%;overflow:auto; }
        table { width:100%;border-collapse:collapse;min-width:1050px; }
        th {
            padding:8px 12px;text-align:left;color:#647b91;background:#f8fafc;
            border-bottom:1px solid var(--border);font-size:9.5px;font-weight:800;
            letter-spacing:.045em;text-transform:uppercase;
        }
        td { height:48px;padding:7px 12px;border-bottom:1px solid #edf2f7;vertical-align:middle; }
        tbody tr { transition:background .14s ease; }
        tbody tr:hover { background:#f5f9fe; }
        tbody tr:last-child td { border-bottom:0; }
        .case-link {
            padding:0;border:0;color:#0d62c7;background:transparent;font-weight:850;
            text-decoration:none;cursor:pointer;
        }
        .case-link:hover,.case-link:focus { text-decoration:underline; }
        .case-number { font-size:13px; }
        .subject { display:grid;gap:1px;min-width:190px; }
        .subject strong { color:var(--navy);font-size:11px; }
        .subject span,.muted { color:var(--muted);font-size:9.5px; }
        .stage { display:grid;gap:2px;min-width:210px; }
        .stage strong { color:#274b68;font-size:10.5px; }
        .stage span { color:var(--muted);font-size:9.5px; }
        .manager,.requester { color:#315875;font-weight:700; }
        .status {
            display:inline-flex;align-items:center;gap:5px;padding:4px 8px;
            border-radius:999px;font-size:9.5px;font-weight:800;white-space:nowrap;
        }
        .status::before { content:"";width:6px;height:6px;border-radius:50%;background:currentColor; }
        .status.proceso { color:#1767b5;background:#eaf4ff; }
        .status.espera { color:#9a6500;background:#fff6d9; }
        .status.cerrado { color:#087443;background:#eaf8f1; }
        .status.cancelado { color:#a33b32;background:#fff0ee; }
        .status.neutro { color:#65788a;background:#eef2f6; }
        .branch-count { display:grid;gap:2px; }
        .branch-count strong { color:#274b68;font-size:10.5px; }
        .branch-count span { color:var(--muted);font-size:9px; }
        .branch-active { color:#8a6100!important; }
        .empty { padding:34px 18px;text-align:center;color:var(--muted); }
        .filter-empty { border-top:1px solid var(--border);background:#fbfdff; }
        .installation { margin:12px;padding:12px;border-radius:9px;color:#8a5b00;background:#fff8e1; }

        .case-modal {
            position:fixed;inset:0;z-index:2147483646;display:none;align-items:center;
            justify-content:center;padding:20px;background:rgba(8,27,45,.68);
        }
        .case-modal.open { display:flex; }
        .case-dialog {
            width:min(1320px,calc(100vw - 34px));height:min(860px,calc(100vh - 34px));
            display:grid;grid-template-rows:auto auto auto minmax(0,1fr);overflow:hidden;
            border:1px solid #cbdced;border-radius:16px;background:#f5f8fc;
            box-shadow:0 26px 80px rgba(4,21,38,.36);
        }
        .modal-head {
            display:flex;align-items:center;justify-content:space-between;gap:16px;
            padding:13px 16px;border-bottom:1px solid #dbe6f0;background:#fff;
        }
        .modal-title { display:flex;align-items:center;gap:10px;min-width:0; }
        .modal-title-icon {
            width:36px;height:36px;display:grid;place-items:center;flex:0 0 auto;
            border-radius:10px;color:#fff;background:linear-gradient(145deg,var(--primary),var(--primary-dark));
            font-size:17px;font-weight:900;
        }
        .modal-title h2 { margin:0;color:var(--navy);font-size:16px; }
        .modal-title p { margin:2px 0 0;color:var(--muted);font-size:9.5px; }
        .modal-close {
            width:36px;height:36px;display:grid;place-items:center;flex:0 0 auto;
            border:1px solid #d6e2ed;border-radius:9px;color:#385b77;background:#fff;
            font-size:20px;line-height:1;cursor:pointer;
        }
        .modal-summary {
            display:grid;grid-template-columns:minmax(0,2fr) repeat(4,minmax(105px,.7fr));
            gap:8px;padding:10px 14px;border-bottom:1px solid #dbe6f0;background:#fbfdff;
        }
        .summary-main,.summary-stat {
            min-width:0;padding:9px 10px;border:1px solid #dce7f1;border-radius:9px;background:#fff;
        }
        .summary-main span,.summary-stat span {
            display:block;color:var(--muted);font-size:8.5px;font-weight:800;
            letter-spacing:.035em;text-transform:uppercase;
        }
        .summary-main strong,.summary-stat strong {
            display:block;margin-top:2px;overflow:hidden;color:var(--navy);
            font-size:10.5px;text-overflow:ellipsis;white-space:nowrap;
        }
        .audit-command-bar {
            min-height:54px;display:flex;align-items:center;justify-content:space-between;
            gap:14px;padding:8px 14px;border-bottom:1px solid #dbe6f0;background:#fff;
        }
        .audit-command-copy { min-width:0;display:grid;gap:2px; }
        .audit-command-copy strong { color:var(--navy);font-size:11.5px; }
        .audit-command-copy span {
            overflow:hidden;color:var(--muted);font-size:9px;text-overflow:ellipsis;white-space:nowrap;
        }
        .audit-command-actions { display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap; }
        .audit-command {
            min-height:34px;display:inline-flex;align-items:center;gap:6px;padding:7px 10px;
            border:1px solid #cfe0ef;border-radius:8px;color:#315f85;background:#f7fbff;
            font-size:9.5px;font-weight:850;cursor:pointer;transition:.15s ease;
        }
        .audit-command:hover,.audit-command.active {
            color:#fff;border-color:var(--primary);background:var(--primary);
        }
        .audit-command-count {
            min-width:19px;display:inline-grid;place-items:center;padding:2px 5px;border-radius:999px;
            color:#075dbd;background:#e7f2ff;font-size:8px;font-weight:900;
        }
        .audit-command:hover .audit-command-count,.audit-command.active .audit-command-count {
            color:#075dbd;background:#fff;
        }
        .modal-workspace { position:relative;min-height:0;overflow:hidden; }
        .modal-content {
            width:100%;height:100%;min-height:0;padding:10px;overflow:hidden;
        }
        .tree-panel {
            min-height:0;overflow:auto;border:1px solid #dce6f0;border-radius:11px;background:#fff;
        }
        .modal-content > .tree-panel { width:100%;height:100%; }
        .audit-drawer {
            position:absolute;top:10px;right:10px;bottom:10px;z-index:8;
            width:min(520px,calc(100% - 32px));display:grid;grid-template-rows:auto minmax(0,1fr);
            overflow:hidden;border:1px solid #b9cee2;border-radius:12px;background:#fff;
            box-shadow:0 18px 48px rgba(16,42,67,.24);
            transform:translateX(calc(100% + 28px));visibility:hidden;
            transition:transform .2s ease,visibility .2s ease,width .2s ease,height .2s ease;
        }
        .audit-drawer.open { transform:translateX(0);visibility:visible; }
        .audit-drawer.minimized {
            bottom:auto;width:min(330px,calc(100% - 32px));height:48px;
            grid-template-rows:48px;
        }
        .audit-drawer.minimized .audit-drawer-body { display:none; }
        .audit-drawer-head {
            min-height:48px;display:flex;align-items:center;justify-content:space-between;gap:10px;
            padding:8px 9px 8px 12px;border-bottom:1px solid #dbe6f0;background:#f8fbff;
        }
        .audit-drawer.minimized .audit-drawer-head { border-bottom:0; }
        .audit-drawer-title { min-width:0;display:grid;gap:1px; }
        .audit-drawer-title strong { color:var(--navy);font-size:11px; }
        .audit-drawer-title span {
            overflow:hidden;color:var(--muted);font-size:8.5px;text-overflow:ellipsis;white-space:nowrap;
        }
        .audit-drawer-tools { display:flex;align-items:center;gap:5px; }
        .audit-drawer-tool {
            width:31px;height:31px;display:grid;place-items:center;padding:0;border:1px solid #d2e0ed;
            border-radius:8px;color:#315f85;background:#fff;font-size:16px;font-weight:800;cursor:pointer;
        }
        .audit-drawer-tool:hover { color:#075dbd;border-color:#9fc4e8;background:#edf6ff; }
        .audit-drawer-body { min-height:0;overflow:auto;background:#fff; }
        .section-head {
            position:sticky;top:0;z-index:2;display:flex;align-items:center;
            justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid #e1e9f1;
            background:#fff;
        }
        .section-head h3 { margin:0;color:var(--navy);font-size:11.5px; }
        .section-head span { color:var(--muted);font-size:9px; }
        .tree-content { min-width:720px;padding:14px; }
        .tree-root,.tree-node {
            position:relative;width:100%;display:grid;grid-template-columns:auto minmax(0,1fr) auto;
            align-items:center;gap:9px;padding:10px;border:1px solid #dbe6f0;border-radius:10px;
            color:var(--text);background:#fff;text-align:left;cursor:pointer;transition:.15s ease;
        }
        .tree-root { border-color:#b8d6f8;background:#f3f8ff; }
        .tree-node:hover,.tree-root:hover { border-color:#8fbeeF;background:#f6faff; }
        .tree-node.selected,.tree-root.selected {
            border-color:var(--primary);box-shadow:0 0 0 3px rgba(15,111,236,.11);background:#f4f9ff;
        }
        .node-code {
            min-width:62px;display:inline-flex;align-items:center;justify-content:center;
            padding:5px 8px;border-radius:7px;color:#075dbd;background:#e9f3ff;
            font-size:9.5px;font-weight:900;white-space:nowrap;
        }
        .tree-root .node-code { color:#fff;background:var(--primary); }
        .node-copy { min-width:0;display:grid;gap:1px; }
        .node-copy strong { overflow:hidden;color:var(--navy);font-size:10.5px;text-overflow:ellipsis;white-space:nowrap; }
        .node-copy span { overflow:hidden;color:var(--muted);font-size:9px;text-overflow:ellipsis;white-space:nowrap; }
        .current-mark {
            display:inline-flex;padding:3px 6px;border-radius:999px;color:#075dbd;
            background:#e6f2ff;font-size:8px;font-weight:850;white-space:nowrap;
        }
        .tree-children {
            position:relative;display:grid;gap:8px;margin:8px 0 0 24px;padding-left:18px;
            border-left:2px solid #d8e6f3;
        }
        .tree-branch { position:relative;display:grid;gap:8px; }
        .tree-branch::before {
            position:absolute;top:24px;left:-18px;width:18px;height:2px;background:#d8e6f3;content:"";
        }
        .loading-state,.error-state {
            min-height:260px;display:grid;place-items:center;padding:30px;text-align:center;color:var(--muted);
        }
        .loading-ring {
            width:34px;height:34px;margin:0 auto 10px;border:3px solid #dce9f7;
            border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .detail-content { padding:12px; }
        .detail-hero {
            display:grid;gap:7px;padding:12px;border-radius:10px;color:#fff;
            background:linear-gradient(135deg,#0d64cd,#0c4f9f);
        }
        .detail-hero small { opacity:.8;font-size:8.5px;font-weight:800;text-transform:uppercase; }
        .detail-hero h4 { margin:0;font-size:14px; }
        .detail-hero p { margin:0;opacity:.9;font-size:9.5px;white-space:pre-wrap; }
        .detail-grid { display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:9px; }
        .detail-card {
            min-width:0;padding:9px;border:1px solid #e0e8f0;border-radius:8px;background:#fbfdff;
        }
        .detail-card.wide { grid-column:1 / -1; }
        .detail-card span { display:block;color:var(--muted);font-size:8px;font-weight:800;text-transform:uppercase; }
        .detail-card strong,.detail-card p {
            display:block;margin:3px 0 0;color:var(--navy);font-size:9.5px;overflow-wrap:anywhere;
        }
        .detail-card p { white-space:pre-wrap;font-weight:500; }
        .rating-line { display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-top:4px; }
        .rating-pill {
            padding:4px 7px;border-radius:7px;color:#725200;background:#fff3c7;
            font-size:8.5px;font-weight:850;
        }
        .audit-tabs {
            position:sticky;top:39px;z-index:3;display:flex;align-items:center;gap:3px;
            padding:0 10px;border-bottom:1px solid #dbe6f0;background:#f8fbff;
        }
        .audit-tab {
            min-height:38px;padding:8px 11px;border:0;border-bottom:3px solid transparent;
            color:#536d84;background:transparent;font-size:10px;font-weight:850;cursor:pointer;
        }
        .audit-tab:hover { color:#0d62c7;background:#eef6ff; }
        .audit-tab.active { color:#075dbd;border-bottom-color:var(--primary);background:#eaf4ff; }
        .audit-intro {
            display:flex;align-items:flex-start;justify-content:space-between;gap:10px;
            margin:10px 12px 0;padding:9px 10px;border:1px solid #dbe7f2;
            border-radius:9px;background:#f8fbff;
        }
        .audit-intro strong { display:block;color:var(--navy);font-size:10.5px; }
        .audit-intro p { margin:2px 0 0;color:var(--muted);font-size:9px; }
        .readonly-pill {
            flex:0 0 auto;padding:4px 7px;border-radius:999px;color:#315f85;
            background:#e7f2fc;font-size:8px;font-weight:850;white-space:nowrap;
        }
        .audit-context {
            margin:9px 12px 0;border:1px solid #dce6f0;border-radius:9px;background:#fff;
        }
        .audit-context > summary {
            padding:9px 10px;color:#284b68;background:#fbfdff;font-size:9.5px;
            font-weight:850;cursor:pointer;list-style-position:inside;
        }
        .audit-context .detail-content { padding:0 10px 10px; }
        .audit-pane { padding:10px 12px 14px; }
        .conversation-layout { display:grid;grid-template-columns:minmax(175px,.72fr) minmax(0,1.28fr);gap:9px; }
        .conversation-list {
            display:grid;align-content:start;gap:6px;max-height:390px;overflow:auto;
            padding-right:2px;
        }
        .conversation-option {
            width:100%;display:grid;gap:3px;padding:9px;border:1px solid #dce6f0;
            border-radius:9px;color:var(--text);background:#fff;text-align:left;cursor:pointer;
        }
        .conversation-option:hover { border-color:#a9caec;background:#f8fbff; }
        .conversation-option.active {
            border-color:var(--primary);background:#edf6ff;box-shadow:0 0 0 2px rgba(15,111,236,.08);
        }
        .conversation-option strong { color:var(--navy);font-size:9.5px; }
        .conversation-option span { color:var(--muted);font-size:8.5px; }
        .conversation-window { min-width:0;border:1px solid #dce6f0;border-radius:9px;background:#fbfdff;overflow:hidden; }
        .conversation-head { padding:9px 10px;border-bottom:1px solid #dce6f0;background:#fff; }
        .conversation-head strong { display:block;color:var(--navy);font-size:10.5px; }
        .conversation-head span { display:block;margin-top:2px;color:var(--muted);font-size:8.5px; }
        .message-list { display:grid;align-content:start;gap:7px;min-height:180px;max-height:325px;overflow:auto;padding:10px; }
        .message { max-width:86%;display:grid;gap:3px;padding:8px 9px;border-radius:9px;background:#fff;box-shadow:0 1px 4px rgba(16,42,67,.08); }
        .message.gestor { justify-self:end;color:#fff;background:#0f6fec; }
        .message.solicitante { justify-self:start;border:1px solid #e0e8f0; }
        .message.system { justify-self:center;max-width:96%;color:#5b7184;background:#edf2f7; }
        .message strong { font-size:8.5px; }
        .message p { margin:0;font-size:9.5px;white-space:pre-wrap;overflow-wrap:anywhere; }
        .message small { opacity:.78;font-size:7.8px; }
        .audit-empty { padding:28px 14px;text-align:center;color:var(--muted);font-size:9.5px; }
        .timeline { position:relative;display:grid;gap:8px;padding-left:15px; }
        .timeline::before { position:absolute;inset:5px auto 5px 4px;width:2px;background:#dce8f3;content:""; }
        .timeline-event { position:relative;padding:9px 10px;border:1px solid #dce6f0;border-radius:9px;background:#fff; }
        .timeline-event::before {
            position:absolute;top:13px;left:-16px;width:8px;height:8px;border:2px solid #fff;
            border-radius:50%;background:var(--primary);box-shadow:0 0 0 1px #9fc5ef;content:"";
        }
        .timeline-head { display:flex;align-items:flex-start;justify-content:space-between;gap:8px; }
        .timeline-head strong { color:var(--navy);font-size:10px; }
        .timeline-head time { color:var(--muted);font-size:8px;white-space:nowrap; }
        .timeline-event p { margin:4px 0 0;color:#49647b;font-size:9px;white-space:pre-wrap;overflow-wrap:anywhere; }
        .event-meta { margin-top:5px;color:#71869a;font-size:8px; }
        .file-list { display:grid;gap:7px; }
        .file-row {
            display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:10px;
            padding:10px;border:1px solid #dce6f0;border-radius:9px;background:#fff;
        }
        .file-copy { min-width:0;display:grid;gap:2px; }
        .file-copy strong { overflow:hidden;color:var(--navy);font-size:10px;text-overflow:ellipsis;white-space:nowrap; }
        .file-copy span { color:var(--muted);font-size:8.5px;overflow-wrap:anywhere; }
        .download-file {
            min-height:30px;display:inline-flex;align-items:center;justify-content:center;padding:6px 9px;
            border:1px solid #bcd6ee;border-radius:8px;color:#075dbd;background:#edf6ff;
            text-decoration:none;font-size:9px;font-weight:850;white-space:nowrap;
        }
        .download-file:hover { color:#fff;border-color:var(--primary);background:var(--primary); }
        .modal-hint {
            padding:9px 12px;border-top:1px solid #e4ebf2;color:#5b7184;
            background:#f9fbfd;font-size:8.5px;text-align:center;
        }
        @media (max-width:900px) {
            .modal-summary { grid-template-columns:1fr 1fr; }
            .summary-main { grid-column:1 / -1; }
            .audit-command-bar { align-items:flex-start;flex-direction:column; }
            .audit-command-actions { width:100%;justify-content:flex-start; }
            .conversation-layout { grid-template-columns:1fr; }
            .conversation-list { max-height:210px; }
        }
        @media (max-width:760px) {
            .shell { width:min(100% - 14px,1180px);padding-top:7px; }
            .topbar { align-items:flex-start;flex-direction:column; }
            .actions { width:100%; }
            .btn { flex:1; }
            .filter-bar { align-items:stretch;flex-direction:column; }
            .filter-control { width:100%; }
            .case-modal { padding:6px; }
            .case-dialog { width:100%;height:100%;border-radius:10px; }
            .modal-summary { grid-template-columns:1fr 1fr; }
            .audit-command { flex:1;justify-content:center; }
            .modal-content { padding:7px; }
            .audit-drawer { top:7px;right:7px;bottom:7px;width:calc(100% - 14px); }
            .audit-drawer.minimized { width:min(310px,calc(100% - 14px)); }
            .tree-content { min-width:620px; }
            .tree-children { margin-left:13px;padding-left:12px; }
            .tree-branch::before { left:-12px;width:12px; }
            .detail-grid { grid-template-columns:1fr; }
            .detail-card.wide { grid-column:auto; }
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
                <p class="subtitle"><?= escaparAdminCasos($nombreAdministrador) ?> · Administración</p>
            </div>
        </div>
        <nav class="actions" aria-label="Acciones administrativas">
            <a class="btn primary" href="descargarSolicitudesExcel.php">Descargar base</a>
            <a class="btn" href="procesos.php">Configurar tickets</a>
            <a class="btn" href="panelAdmin.php">Volver al panel</a>
        </nav>
    </header>

    <section class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <h2>Casos padre</h2>
                <span class="country-scope">
                    <?= escaparAdminCasos($codigoPaisOperacion) ?> · <?= escaparAdminCasos($nombrePaisOperacion) ?>
                </span>
            </div>
            <span class="count" id="conteoCasosPadre">
                <?= count($casosPadre) ?> caso<?= count($casosPadre) === 1 ? '' : 's' ?>
            </span>
        </div>

        <div class="filter-bar">
            <div class="filter-copy">
                <label for="filtroCasosPadre">Buscar caso padre</label>
                <p>Los casos hijos se consultan dentro del árbol de cada caso.</p>
            </div>
            <div class="filter-control">
                <input
                    type="search"
                    id="filtroCasosPadre"
                    placeholder="Número, asunto, solicitante, servicio, gestor o estado"
                    autocomplete="off"
                    <?= !$casosPadre ? 'disabled' : '' ?>
                >
                <button type="button" class="clear-filter" id="limpiarFiltroCasos" disabled>Limpiar</button>
            </div>
        </div>

        <?php if (!$moduloDisponible): ?>
            <div class="installation">
                El módulo de Tickets, soluciones y calificación detallada todavía no está instalado por completo.
            </div>
        <?php elseif (!$casosPadre): ?>
            <div class="empty">No hay casos registrados.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width:125px">Caso padre</th>
                        <th>Asunto</th>
                        <th style="width:145px">Solicitante</th>
                        <th>Etapa actual</th>
                        <th style="width:145px">Gestor actual</th>
                        <th style="width:165px">Estado</th>
                        <th style="width:130px">Ramificación</th>
                        <th style="width:115px">Actualizado</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($casosPadre as $caso): ?>
                        <?php
                            $estadoActual = (string) ($caso['estado_actual'] ?? '');
                            $nombreEtapa = trim(implode(' / ', array_filter([
                                (string) ($caso['catalogo_nombre'] ?? ''),
                                (string) ($caso['servicio_nombre'] ?? ''),
                            ])));
                            if ($nombreEtapa === '') {
                                $nombreEtapa = 'Etapa no disponible';
                            }
                            $totalHijos = (int) ($caso['total_hijos'] ?? 0);
                            $fechaActualizacion = strtotime((string) ($caso['actualizado_en'] ?? ''));
                        ?>
                        <tr
                            data-case-row
                            data-ticket-id="<?= (int) $caso['id_ticket'] ?>"
                            data-search="<?= escaparAdminCasos(implode(' ', [
                                (string) $caso['id_ticket'],
                                (string) ($caso['titulo'] ?? ''),
                                (string) ($caso['descripcion'] ?? ''),
                                (string) ($caso['solicitante'] ?? ''),
                                (string) ($caso['flujo_nombre'] ?? ''),
                                (string) ($caso['catalogo_nombre'] ?? ''),
                                (string) ($caso['servicio_nombre'] ?? ''),
                                (string) ($caso['gestor_actual'] ?? ''),
                                etiquetaEstadoAdminCasos($estadoActual),
                            ])) ?>"
                        >
                            <td>
                                <button
                                    type="button"
                                    class="case-link case-number"
                                    data-open-case="<?= (int) $caso['id_ticket'] ?>"
                                    aria-label="Abrir árbol del Caso <?= (int) $caso['id_ticket'] ?>"
                                >Caso <?= (int) $caso['id_ticket'] ?></button>
                            </td>
                            <td>
                                <div class="subject">
                                    <strong><?= escaparAdminCasos($caso['titulo'] ?? 'Sin asunto') ?></strong>
                                    <span><?= escaparAdminCasos($caso['flujo_nombre'] ?? 'Flujo no disponible') ?></span>
                                </div>
                            </td>
                            <td class="requester"><?= escaparAdminCasos($caso['solicitante'] ?? 'Usuario eliminado') ?></td>
                            <td>
                                <div class="stage">
                                    <strong><?= escaparAdminCasos($nombreEtapa) ?></strong>
                                    <span><?= (int) ($caso['total_etapas'] ?? 0) ?> etapa<?= (int) ($caso['total_etapas'] ?? 0) === 1 ? '' : 's' ?> en el flujo</span>
                                </div>
                            </td>
                            <td class="manager"><?= escaparAdminCasos($caso['gestor_actual'] ?? 'Sin asignar') ?></td>
                            <td>
                                <span class="status <?= escaparAdminCasos(claseEstadoAdminCasos($estadoActual)) ?>">
                                    <?= escaparAdminCasos(etiquetaEstadoAdminCasos($estadoActual)) ?>
                                </span>
                            </td>
                            <td>
                                <div class="branch-count">
                                    <strong><?= $totalHijos ?> caso<?= $totalHijos === 1 ? '' : 's' ?> hijo<?= $totalHijos === 1 ? '' : 's' ?></strong>
                                    <span class="<?= (int) ($caso['tiene_hijo_activo'] ?? 0) === 1 ? 'branch-active' : '' ?>">
                                        <?= (int) ($caso['tiene_hijo_activo'] ?? 0) === 1 ? 'Con gestión activa' : ($totalHijos > 0 ? 'Sin hijos activos' : 'Sin derivaciones') ?>
                                    </span>
                                </div>
                            </td>
                            <td class="muted"><?= $fechaActualizacion ? date('d/m/Y H:i', $fechaActualizacion) : 'Sin fecha' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="empty filter-empty" id="sinCasosPadre" hidden>
                No hay casos padre que coincidan con la búsqueda.
            </div>
        <?php endif; ?>
    </section>
</main>

<div class="case-modal" id="modalArbolCaso" role="dialog" aria-modal="true" aria-labelledby="tituloModalCaso" hidden>
    <section class="case-dialog">
        <header class="modal-head">
            <div class="modal-title">
                <div class="modal-title-icon">⌘</div>
                <div>
                    <h2 id="tituloModalCaso">Trazabilidad del caso</h2>
                    <p id="subtituloModalCaso">Seleccione un caso para consultar su árbol.</p>
                </div>
            </div>
            <button type="button" class="modal-close" id="cerrarModalCaso" aria-label="Cerrar ventana">×</button>
        </header>
        <div class="modal-summary" id="resumenModalCaso" hidden></div>
        <section class="audit-command-bar" id="barraAuditoria" aria-label="Auditoría administrativa" hidden>
            <div class="audit-command-copy">
                <strong>Auditoría administrativa</strong>
                <span id="contextoAuditoria">Seleccione el caso padre, una etapa o un caso hijo.</span>
            </div>
            <div class="audit-command-actions">
                <button type="button" class="audit-command" data-audit-open="conversacion">
                    Conversación
                    <span class="audit-command-count" id="conteoConversaciones">0</span>
                </button>
                <button type="button" class="audit-command" data-audit-open="acciones">
                    Acciones
                    <span class="audit-command-count" id="conteoAcciones">0</span>
                </button>
                <button type="button" class="audit-command" data-audit-open="archivos">
                    Archivos
                    <span class="audit-command-count" id="conteoArchivos">0</span>
                </button>
            </div>
        </section>
        <div class="modal-workspace">
            <div class="modal-content" id="contenidoModalCaso">
                <div class="tree-panel">
                    <div class="loading-state">
                        <div><div class="loading-ring"></div>Cargando trazabilidad…</div>
                    </div>
                </div>
            </div>
            <aside class="audit-drawer" id="panelAuditoria" aria-hidden="true" aria-label="Panel de auditoría administrativa">
                <header class="audit-drawer-head">
                    <div class="audit-drawer-title">
                        <strong id="tituloPanelAuditoria">Auditoría administrativa</strong>
                        <span id="subtituloPanelAuditoria">Solo lectura</span>
                    </div>
                    <div class="audit-drawer-tools">
                        <button type="button" class="audit-drawer-tool" id="minimizarPanelAuditoria" aria-label="Minimizar panel" title="Minimizar">−</button>
                        <button type="button" class="audit-drawer-tool" id="cerrarPanelAuditoria" aria-label="Cerrar panel" title="Cerrar">×</button>
                    </div>
                </header>
                <div class="audit-drawer-body" id="contenidoPanelAuditoria"></div>
            </aside>
        </div>
    </section>
</div>

<script>
(function () {
    'use strict';

    const filas = Array.from(document.querySelectorAll('[data-case-row]'));
    const filtro = document.getElementById('filtroCasosPadre');
    const limpiar = document.getElementById('limpiarFiltroCasos');
    const conteo = document.getElementById('conteoCasosPadre');
    const sinResultados = document.getElementById('sinCasosPadre');
    const modal = document.getElementById('modalArbolCaso');
    const cerrar = document.getElementById('cerrarModalCaso');
    const tituloModal = document.getElementById('tituloModalCaso');
    const subtituloModal = document.getElementById('subtituloModalCaso');
    const resumenModal = document.getElementById('resumenModalCaso');
    const contenidoModal = document.getElementById('contenidoModalCaso');
    const barraAuditoria = document.getElementById('barraAuditoria');
    const contextoAuditoria = document.getElementById('contextoAuditoria');
    const botonesAuditoria = Array.from(document.querySelectorAll('[data-audit-open]'));
    const conteoConversaciones = document.getElementById('conteoConversaciones');
    const conteoAcciones = document.getElementById('conteoAcciones');
    const conteoArchivos = document.getElementById('conteoArchivos');
    const panelAuditoria = document.getElementById('panelAuditoria');
    const tituloPanelAuditoria = document.getElementById('tituloPanelAuditoria');
    const subtituloPanelAuditoria = document.getElementById('subtituloPanelAuditoria');
    const contenidoPanelAuditoria = document.getElementById('contenidoPanelAuditoria');
    const minimizarAuditoria = document.getElementById('minimizarPanelAuditoria');
    const cerrarAuditoria = document.getElementById('cerrarPanelAuditoria');
    let botonOrigen = null;
    let controladorPeticion = null;
    let casoActual = null;
    let seleccionAuditoria = null;
    let pestanaAuditoria = 'conversacion';
    let idConversacionAuditoria = 0;
    let contextoAuditoriaAbierto = true;
    let panelAuditoriaAbierto = false;
    let panelAuditoriaMinimizado = false;

    function normalizar(valor) {
        return String(valor || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('es')
            .trim();
    }

    function filtrarCasos() {
        if (!filtro) return;
        const termino = normalizar(filtro.value);
        let visibles = 0;

        filas.forEach(function (fila) {
            const coincide = termino === '' || normalizar(
                (fila.dataset.search || '') + ' ' + (fila.textContent || '')
            ).includes(termino);
            fila.hidden = !coincide;
            if (coincide) visibles += 1;
        });

        if (conteo) {
            conteo.textContent = visibles + ' caso' + (visibles === 1 ? '' : 's');
        }
        if (limpiar) limpiar.disabled = termino === '';
        if (sinResultados) sinResultados.hidden = termino === '' || visibles > 0;
    }

    if (filtro) filtro.addEventListener('input', filtrarCasos);
    if (limpiar) {
        limpiar.addEventListener('click', function () {
            if (!filtro) return;
            filtro.value = '';
            filtrarCasos();
            filtro.focus();
        });
    }

    function crearElemento(etiqueta, clase, texto) {
        const elemento = document.createElement(etiqueta);
        if (clase) elemento.className = clase;
        if (texto !== undefined && texto !== null) elemento.textContent = String(texto);
        return elemento;
    }

    function etiquetaEstado(estado) {
        const etiquetas = {
            pendiente: 'Pendiente',
            en_proceso: 'En proceso',
            en_espera_solicitante: 'En espera del solicitante',
            pausada: 'Pausado',
            listo_cierre: 'Listo · pendiente de cierre',
            bloqueada: 'Bloqueado',
            completada: 'Completado',
            pendiente_calificacion: 'Pendiente de calificación',
            cerrado: 'Cerrado',
            cancelado: 'Cancelado',
            cancelada: 'Cancelado'
        };
        return etiquetas[estado] || String(estado || 'Sin estado').replaceAll('_', ' ');
    }

    function claseEstado(estado) {
        if (['pendiente', 'en_proceso'].includes(estado)) return 'proceso';
        if (['en_espera_solicitante', 'pausada', 'listo_cierre', 'pendiente_calificacion'].includes(estado)) return 'espera';
        if (['completada', 'cerrado'].includes(estado)) return 'cerrado';
        if (['cancelado', 'cancelada'].includes(estado)) return 'cancelado';
        return 'neutro';
    }

    function formatearFecha(valor) {
        if (!valor) return 'Sin dato';
        const fecha = new Date(String(valor).replace(' ', 'T'));
        if (Number.isNaN(fecha.getTime())) return String(valor);
        return new Intl.DateTimeFormat('es-CO', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: false
        }).format(fecha);
    }

    function formatearTiempo(minutos) {
        if (minutos === null || minutos === undefined || minutos === '') return 'Sin dato';
        const total = Math.max(0, Number(minutos) || 0);
        const horas = Math.floor(total / 60);
        const resto = total % 60;
        if (horas > 0) return horas + ' h ' + resto + ' min';
        return resto + ' min';
    }

    function estadoSla(valor) {
        const mapa = {
            dentro_sla: 'Dentro del SLA',
            fuera_sla: 'Fuera del SLA',
            en_tiempo: 'En tiempo',
            vencido: 'Vencido',
            pausado: 'Pausado',
            sin_iniciar: 'Sin iniciar'
        };
        return mapa[valor] || String(valor || 'Sin dato').replaceAll('_', ' ');
    }

    function agregarTarjeta(contenedor, etiqueta, valor, amplia, comoParrafo) {
        const tarjeta = crearElemento('div', 'detail-card' + (amplia ? ' wide' : ''));
        tarjeta.appendChild(crearElemento('span', '', etiqueta));
        const contenido = crearElemento(comoParrafo ? 'p' : 'strong', '', valor || 'Sin dato');
        tarjeta.appendChild(contenido);
        contenedor.appendChild(tarjeta);
        return tarjeta;
    }

    function construirFichaCaso(caso) {
        const contenido = crearElemento('div', 'detail-content');
        const hero = crearElemento('div', 'detail-hero');
        hero.appendChild(crearElemento('small', '', 'Caso padre ' + caso.id));
        hero.appendChild(crearElemento('h4', '', caso.titulo || 'Sin asunto'));
        hero.appendChild(crearElemento('p', '', caso.descripcion || 'Sin descripción'));
        contenido.appendChild(hero);

        const grid = crearElemento('div', 'detail-grid');
        agregarTarjeta(grid, 'Solicitante', caso.solicitante);
        agregarTarjeta(grid, 'Estado general', etiquetaEstado(caso.estado));
        agregarTarjeta(grid, 'Flujo', caso.flujo);
        agregarTarjeta(grid, 'Creado', formatearFecha(caso.fecha_creacion));
        agregarTarjeta(grid, 'Etapas oficiales', caso.total_etapas);
        agregarTarjeta(grid, 'Casos hijos', caso.total_hijos);
        agregarTarjeta(grid, 'Última actualización', formatearFecha(caso.actualizado_en), true);
        contenido.appendChild(grid);
        return contenido;
    }

    function construirFichaNodo(nodo) {
        const esHijo = nodo.tipo === 'hijo';
        const contenido = crearElemento('div', 'detail-content');
        const hero = crearElemento('div', 'detail-hero');
        hero.appendChild(crearElemento('small', '', esHijo ? 'Caso hijo ' + nodo.codigo : 'Etapa ' + nodo.codigo));
        hero.appendChild(crearElemento('h4', '', (nodo.catalogo || 'Área') + ' / ' + (nodo.servicio || 'Servicio')));
        hero.appendChild(crearElemento('p', '', etiquetaEstado(nodo.estado) + (nodo.es_actual ? ' · Etapa actual' : '')));
        contenido.appendChild(hero);

        const grid = crearElemento('div', 'detail-grid');
        agregarTarjeta(grid, 'Gestor a cargo', nodo.gestor);
        agregarTarjeta(grid, 'Creador', nodo.creador);
        agregarTarjeta(grid, 'SLA configurado', (nodo.sla_nombre || 'Sin SLA') + (nodo.sla_tiempo ? ' · ' + nodo.sla_tiempo + ' ' + nodo.sla_unidad : ''));
        agregarTarjeta(grid, 'Estado del SLA', estadoSla(nodo.resultado_sla !== 'sin_iniciar' ? nodo.resultado_sla : nodo.sla_estado));
        agregarTarjeta(grid, 'Activación', formatearFecha(nodo.fecha_activacion));
        agregarTarjeta(grid, 'Vencimiento', formatearFecha(nodo.fecha_vencimiento));
        agregarTarjeta(grid, 'Marcado listo', formatearFecha(nodo.fecha_listo));
        agregarTarjeta(grid, 'Tiempo de solución', formatearTiempo(nodo.minutos_solucion));
        agregarTarjeta(grid, 'Finalización', formatearFecha(nodo.fecha_finalizacion));
        agregarTarjeta(grid, 'Reaperturas', nodo.reaperturas);

        if (esHijo || nodo.motivo_derivacion) {
            agregarTarjeta(grid, 'Motivo de derivación', nodo.motivo_derivacion || 'Sin motivo registrado', true, true);
        }
        agregarTarjeta(grid, 'Solución', nodo.solucion || 'Sin solución registrada', true, true);
        agregarTarjeta(grid, 'Observación de cierre', nodo.observacion || 'Sin observación registrada', true, true);

        const calificacion = crearElemento('div', 'detail-card wide');
        calificacion.appendChild(crearElemento('span', '', 'Calificación'));
        if (nodo.calificacion === null) {
            calificacion.appendChild(crearElemento('strong', '', 'Sin calificar'));
        } else {
            const linea = crearElemento('div', 'rating-line');
            linea.appendChild(crearElemento('span', 'rating-pill', 'General ' + nodo.calificacion + '/5'));
            linea.appendChild(crearElemento('span', 'rating-pill', 'Gestión ' + nodo.calificacion_area + '/5'));
            linea.appendChild(crearElemento('span', 'rating-pill', 'Tiempo ' + nodo.calificacion_tiempo + '/5'));
            calificacion.appendChild(linea);
            calificacion.appendChild(crearElemento('p', '', (nodo.comentario_calificacion || 'Sin comentario') + ' · ' + nodo.evaluador));
        }
        grid.appendChild(calificacion);
        contenido.appendChild(grid);
        return contenido;
    }

    function auditoriaActual() {
        const auditoria = casoActual && casoActual.auditoria ? casoActual.auditoria : {};
        return {
            conversaciones: Array.isArray(auditoria.conversaciones) ? auditoria.conversaciones : [],
            acciones: Array.isArray(auditoria.acciones) ? auditoria.acciones : [],
            archivos: Array.isArray(auditoria.archivos) ? auditoria.archivos : []
        };
    }

    function formatearTamano(bytes) {
        const total = Math.max(0, Number(bytes) || 0);
        if (total < 1024) return total + ' B';
        if (total < 1024 * 1024) return (total / 1024).toFixed(1) + ' KB';
        if (total < 1024 * 1024 * 1024) return (total / (1024 * 1024)).toFixed(1) + ' MB';
        return (total / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
    }

    function asegurarConversacionSeleccionada(conversaciones, idNodoPreferido) {
        if (
            idNodoPreferido > 0
            && conversaciones.some(function (item) { return Number(item.nodo_id) === idNodoPreferido; })
        ) {
            idConversacionAuditoria = idNodoPreferido;
            return;
        }

        if (!conversaciones.some(function (item) { return Number(item.id) === idConversacionAuditoria; })) {
            idConversacionAuditoria = conversaciones.length ? Number(conversaciones[0].id) : 0;
        }
    }

    function renderizarConversaciones(contenedor, conversaciones) {
        if (!conversaciones.length) {
            contenedor.appendChild(crearElemento('div', 'audit-empty', 'Este caso todavía no tiene conversaciones registradas.'));
            return;
        }

        asegurarConversacionSeleccionada(conversaciones, 0);
        const conversacionActiva = conversaciones.find(function (item) {
            return Number(item.id) === idConversacionAuditoria;
        }) || conversaciones[0];
        const layout = crearElemento('div', 'conversation-layout');
        const lista = crearElemento('div', 'conversation-list');

        conversaciones.forEach(function (conversacion) {
            const activa = Number(conversacion.id) === Number(conversacionActiva.id);
            const opcion = crearElemento('button', 'conversation-option' + (activa ? ' active' : ''));
            opcion.type = 'button';
            opcion.setAttribute('aria-pressed', activa ? 'true' : 'false');
            opcion.appendChild(crearElemento('strong', '', conversacion.etiqueta || 'Conversación'));
            opcion.appendChild(crearElemento('span', '', (conversacion.catalogo || 'Área') + ' · ' + (conversacion.servicio || 'Servicio')));
            opcion.appendChild(crearElemento('span', '',
                (Array.isArray(conversacion.mensajes) ? conversacion.mensajes.length : 0)
                + ' mensaje(s) · ' + (Number(conversacion.total_archivos) || 0) + ' archivo(s)'
            ));
            opcion.addEventListener('click', function () {
                idConversacionAuditoria = Number(conversacion.id);
                renderizarPanelAuditoria();
            });
            lista.appendChild(opcion);
        });
        layout.appendChild(lista);

        const ventana = crearElemento('section', 'conversation-window');
        const cabecera = crearElemento('div', 'conversation-head');
        cabecera.appendChild(crearElemento('strong', '', conversacionActiva.etiqueta || 'Conversación'));
        cabecera.appendChild(crearElemento('span', '',
            (conversacionActiva.origen || 'Origen') + ' ↔ ' + (conversacionActiva.destino || 'Destino')
            + ' · Auditoría de solo lectura'
        ));
        ventana.appendChild(cabecera);

        const mensajes = crearElemento('div', 'message-list');
        const listaMensajes = Array.isArray(conversacionActiva.mensajes)
            ? conversacionActiva.mensajes
            : [];
        if (!listaMensajes.length) {
            mensajes.appendChild(crearElemento('div', 'audit-empty', 'No hay mensajes en esta conversación.'));
        } else {
            listaMensajes.forEach(function (mensaje) {
                const rol = Number(mensaje.emisor_rol);
                const clase = rol === 2 ? 'gestor' : (rol === 3 ? 'solicitante' : 'system');
                const burbuja = crearElemento('article', 'message ' + clase);
                burbuja.appendChild(crearElemento('strong', '', mensaje.emisor || 'Usuario eliminado'));
                burbuja.appendChild(crearElemento('p', '', mensaje.mensaje || ''));
                burbuja.appendChild(crearElemento('small', '',
                    formatearFecha(mensaje.fecha)
                    + (mensaje.tipo === 'interna' ? ' · Comunicación interna' : '')
                ));
                mensajes.appendChild(burbuja);
            });
        }
        ventana.appendChild(mensajes);
        layout.appendChild(ventana);
        contenedor.appendChild(layout);
    }

    function renderizarAcciones(contenedor, acciones) {
        if (!acciones.length) {
            contenedor.appendChild(crearElemento('div', 'audit-empty', 'No hay acciones registradas para este caso.'));
            return;
        }

        const linea = crearElemento('div', 'timeline');
        acciones.forEach(function (accion) {
            const evento = crearElemento('article', 'timeline-event');
            const cabecera = crearElemento('div', 'timeline-head');
            cabecera.appendChild(crearElemento('strong', '', accion.accion || 'Acción registrada'));
            const fecha = crearElemento('time', '', formatearFecha(accion.fecha));
            if (accion.fecha) fecha.dateTime = String(accion.fecha).replace(' ', 'T');
            cabecera.appendChild(fecha);
            evento.appendChild(cabecera);
            if (accion.detalle) evento.appendChild(crearElemento('p', '', accion.detalle));
            evento.appendChild(crearElemento('div', 'event-meta',
                (accion.origen || 'Caso') + ' · ' + (accion.usuario || 'Sistema')
            ));
            linea.appendChild(evento);
        });
        contenedor.appendChild(linea);
    }

    function renderizarArchivos(contenedor, archivos) {
        if (!archivos.length) {
            contenedor.appendChild(crearElemento('div', 'audit-empty', 'No hay archivos adjuntos en el caso ni en sus derivaciones.'));
            return;
        }

        const lista = crearElemento('div', 'file-list');
        archivos.forEach(function (archivo) {
            const fila = crearElemento('article', 'file-row');
            const copia = crearElemento('div', 'file-copy');
            copia.appendChild(crearElemento('strong', '', archivo.nombre || 'Archivo'));
            copia.appendChild(crearElemento('span', '',
                (archivo.origen || 'Caso') + ' · ' + (archivo.usuario || 'Usuario eliminado')
            ));
            copia.appendChild(crearElemento('span', '',
                formatearFecha(archivo.fecha) + ' · ' + formatearTamano(archivo.tamano)
                + (archivo.tipo ? ' · ' + archivo.tipo : '')
            ));
            fila.appendChild(copia);
            const descargar = crearElemento('a', 'download-file', 'Descargar');
            descargar.href = 'descargarAdjunto.php?id=' + encodeURIComponent(archivo.id);
            descargar.setAttribute('aria-label', 'Descargar ' + (archivo.nombre || 'archivo'));
            fila.appendChild(descargar);
            lista.appendChild(fila);
        });
        contenedor.appendChild(lista);
    }

    function etiquetaSeleccionAuditoria() {
        if (!seleccionAuditoria) return 'Seleccione un nodo del árbol';
        const esCaso = seleccionAuditoria.tipo === 'caso';
        const dato = seleccionAuditoria.dato;
        return esCaso
            ? 'Caso padre ' + dato.id
            : (dato.tipo === 'hijo' ? 'Caso hijo ' + dato.codigo : 'Etapa ' + dato.codigo);
    }

    function actualizarBarraAuditoria() {
        const auditoria = auditoriaActual();
        if (conteoConversaciones) conteoConversaciones.textContent = String(auditoria.conversaciones.length);
        if (conteoAcciones) conteoAcciones.textContent = String(auditoria.acciones.length);
        if (conteoArchivos) conteoArchivos.textContent = String(auditoria.archivos.length);
        if (contextoAuditoria) {
            contextoAuditoria.textContent = etiquetaSeleccionAuditoria()
                + ' · Seleccione una opción para abrirla en el panel derecho.';
        }
        botonesAuditoria.forEach(function (boton) {
            const activa = panelAuditoriaAbierto && boton.dataset.auditOpen === pestanaAuditoria;
            boton.classList.toggle('active', activa);
            boton.setAttribute('aria-pressed', activa ? 'true' : 'false');
        });
    }

    function aplicarEstadoPanelAuditoria() {
        if (!panelAuditoria) return;
        panelAuditoria.classList.toggle('open', panelAuditoriaAbierto);
        panelAuditoria.classList.toggle('minimized', panelAuditoriaAbierto && panelAuditoriaMinimizado);
        panelAuditoria.setAttribute('aria-hidden', panelAuditoriaAbierto ? 'false' : 'true');
        if (minimizarAuditoria) {
            minimizarAuditoria.textContent = panelAuditoriaMinimizado ? '□' : '−';
            minimizarAuditoria.title = panelAuditoriaMinimizado ? 'Restaurar' : 'Minimizar';
            minimizarAuditoria.setAttribute(
                'aria-label',
                panelAuditoriaMinimizado ? 'Restaurar panel' : 'Minimizar panel'
            );
        }
        actualizarBarraAuditoria();
    }

    function renderizarPanelAuditoria() {
        if (!contenidoPanelAuditoria || !seleccionAuditoria) return;
        contenidoPanelAuditoria.replaceChildren();

        const esCaso = seleccionAuditoria.tipo === 'caso';
        const dato = seleccionAuditoria.dato;
        const etiquetaSeleccion = etiquetaSeleccionAuditoria();
        const titulos = {
            conversacion: 'Conversaciones',
            acciones: 'Acciones del caso',
            archivos: 'Archivos del caso'
        };
        if (tituloPanelAuditoria) {
            tituloPanelAuditoria.textContent = titulos[pestanaAuditoria] || 'Auditoría administrativa';
        }
        if (subtituloPanelAuditoria) {
            subtituloPanelAuditoria.textContent = etiquetaSeleccion + ' · Solo lectura';
        }

        const auditoria = auditoriaActual();
        const intro = crearElemento('div', 'audit-intro');
        const textoIntro = crearElemento('div', '');
        textoIntro.appendChild(crearElemento('strong', '', 'Auditoría completa del caso y sus derivaciones'));
        textoIntro.appendChild(crearElemento('p', '',
            pestanaAuditoria === 'conversacion'
                ? 'Seleccione una conversación para revisar sus mensajes sin intervenir en ella.'
                : (pestanaAuditoria === 'acciones'
                    ? 'Se muestran cronológicamente todas las acciones del caso padre y de sus casos hijos.'
                    : 'Se muestran todos los adjuntos, indicando la etapa o derivación de origen.')
        ));
        intro.appendChild(textoIntro);
        intro.appendChild(crearElemento('span', 'readonly-pill', 'Solo lectura'));
        contenidoPanelAuditoria.appendChild(intro);

        const contexto = crearElemento('details', 'audit-context');
        contexto.open = contextoAuditoriaAbierto;
        contexto.appendChild(crearElemento('summary', '', 'Información de ' + etiquetaSeleccion));
        contexto.appendChild(esCaso ? construirFichaCaso(dato) : construirFichaNodo(dato));
        contexto.addEventListener('toggle', function () {
            contextoAuditoriaAbierto = contexto.open;
        });
        contenidoPanelAuditoria.appendChild(contexto);

        const contenido = crearElemento('div', 'audit-pane');
        if (pestanaAuditoria === 'acciones') {
            renderizarAcciones(contenido, auditoria.acciones);
        } else if (pestanaAuditoria === 'archivos') {
            renderizarArchivos(contenido, auditoria.archivos);
        } else {
            renderizarConversaciones(contenido, auditoria.conversaciones);
        }
        contenidoPanelAuditoria.appendChild(contenido);
        aplicarEstadoPanelAuditoria();
    }

    function abrirPanelAuditoria(tipo) {
        if (!seleccionAuditoria) return;
        pestanaAuditoria = tipo;
        panelAuditoriaAbierto = true;
        panelAuditoriaMinimizado = false;
        renderizarPanelAuditoria();
    }

    function cerrarPanelLateralAuditoria() {
        panelAuditoriaAbierto = false;
        panelAuditoriaMinimizado = false;
        aplicarEstadoPanelAuditoria();
    }

    function renderizarDetalleCaso(caso) {
        seleccionAuditoria = { tipo: 'caso', dato: caso };
        contextoAuditoriaAbierto = true;
        asegurarConversacionSeleccionada(auditoriaActual().conversaciones, 0);
        actualizarBarraAuditoria();
        if (panelAuditoriaAbierto) renderizarPanelAuditoria();
    }

    function renderizarDetalleNodo(nodo) {
        seleccionAuditoria = { tipo: 'nodo', dato: nodo };
        contextoAuditoriaAbierto = true;
        asegurarConversacionSeleccionada(auditoriaActual().conversaciones, Number(nodo.id));
        actualizarBarraAuditoria();
        if (panelAuditoriaAbierto) renderizarPanelAuditoria();
    }

    function crearBotonNodo(nodo, seleccionar) {
        const boton = crearElemento('button', 'tree-node');
        boton.type = 'button';
        boton.dataset.nodeId = String(nodo.id);
        boton.appendChild(crearElemento('span', 'node-code', nodo.tipo === 'hijo' ? nodo.codigo : 'Etapa ' + nodo.codigo));

        const copia = crearElemento('span', 'node-copy');
        copia.appendChild(crearElemento('strong', '', (nodo.catalogo || 'Área') + ' / ' + (nodo.servicio || 'Servicio')));
        copia.appendChild(crearElemento('span', '', (nodo.gestor || 'Sin asignar') + ' · ' + etiquetaEstado(nodo.estado)));
        boton.appendChild(copia);

        if (nodo.es_actual) {
            boton.appendChild(crearElemento('span', 'current-mark', 'Actual'));
        } else {
            boton.appendChild(crearElemento('span', 'status ' + claseEstado(nodo.estado), etiquetaEstado(nodo.estado)));
        }
        boton.addEventListener('click', function () { seleccionar(nodo, boton); });
        return boton;
    }

    function renderizarArbol(datos) {
        casoActual = datos;
        seleccionAuditoria = null;
        pestanaAuditoria = 'conversacion';
        idConversacionAuditoria = 0;
        contextoAuditoriaAbierto = true;
        panelAuditoriaAbierto = false;
        panelAuditoriaMinimizado = false;
        tituloModal.textContent = 'Caso ' + datos.caso.id + ' · trazabilidad';
        subtituloModal.textContent = 'Explore el árbol completo y abra la auditoría desde los botones superiores.';
        resumenModal.hidden = false;
        resumenModal.replaceChildren();

        const principal = crearElemento('div', 'summary-main');
        principal.appendChild(crearElemento('span', '', 'Asunto'));
        principal.appendChild(crearElemento('strong', '', datos.caso.titulo || 'Sin asunto'));
        resumenModal.appendChild(principal);
        [
            ['Solicitante', datos.caso.solicitante],
            ['Estado', etiquetaEstado(datos.caso.estado)],
            ['Etapas', datos.caso.total_etapas],
            ['Casos hijos', datos.caso.total_hijos]
        ].forEach(function (item) {
            const bloque = crearElemento('div', 'summary-stat');
            bloque.appendChild(crearElemento('span', '', item[0]));
            bloque.appendChild(crearElemento('strong', '', item[1]));
            resumenModal.appendChild(bloque);
        });
        if (barraAuditoria) barraAuditoria.hidden = false;
        aplicarEstadoPanelAuditoria();

        contenidoModal.replaceChildren();
        const panelArbol = crearElemento('section', 'tree-panel');
        const headArbol = crearElemento('div', 'section-head');
        headArbol.appendChild(crearElemento('h3', '', 'Árbol del caso'));
        headArbol.appendChild(crearElemento('span', '', datos.nodos.length + ' nodo' + (datos.nodos.length === 1 ? '' : 's')));
        panelArbol.appendChild(headArbol);
        const arbol = crearElemento('div', 'tree-content');
        panelArbol.appendChild(arbol);
        contenidoModal.appendChild(panelArbol);

        const botones = [];
        function seleccionarNodo(nodo, boton) {
            botones.forEach(function (elemento) { elemento.classList.remove('selected'); });
            boton.classList.add('selected');
            renderizarDetalleNodo(nodo);
        }

        const raiz = crearElemento('button', 'tree-root selected');
        raiz.type = 'button';
        raiz.appendChild(crearElemento('span', 'node-code', 'Caso ' + datos.caso.id));
        const copiaRaiz = crearElemento('span', 'node-copy');
        copiaRaiz.appendChild(crearElemento('strong', '', datos.caso.titulo || 'Sin asunto'));
        copiaRaiz.appendChild(crearElemento('span', '', datos.caso.solicitante + ' · ' + etiquetaEstado(datos.caso.estado)));
        raiz.appendChild(copiaRaiz);
        raiz.appendChild(crearElemento('span', 'status ' + claseEstado(datos.caso.estado), etiquetaEstado(datos.caso.estado)));
        raiz.addEventListener('click', function () {
            botones.forEach(function (elemento) { elemento.classList.remove('selected'); });
            raiz.classList.add('selected');
            renderizarDetalleCaso(datos.caso);
        });
        botones.push(raiz);
        arbol.appendChild(raiz);

        const porPadre = new Map();
        datos.nodos.forEach(function (nodo) {
            const padre = Number(nodo.padre_id) || 0;
            if (!porPadre.has(padre)) porPadre.set(padre, []);
            porPadre.get(padre).push(nodo);
        });

        function agregarRamas(idPadre, contenedor) {
            const hijos = porPadre.get(idPadre) || [];
            hijos.forEach(function (nodo) {
                const rama = crearElemento('div', 'tree-branch');
                const boton = crearBotonNodo(nodo, seleccionarNodo);
                botones.push(boton);
                rama.appendChild(boton);
                const descendientes = porPadre.get(Number(nodo.id)) || [];
                if (descendientes.length) {
                    const hijosContenedor = crearElemento('div', 'tree-children');
                    agregarRamas(Number(nodo.id), hijosContenedor);
                    rama.appendChild(hijosContenedor);
                }
                contenedor.appendChild(rama);
            });
        }

        const ramasRaiz = crearElemento('div', 'tree-children');
        agregarRamas(0, ramasRaiz);
        if (ramasRaiz.children.length) arbol.appendChild(ramasRaiz);
        else arbol.appendChild(crearElemento('div', 'empty', 'Este caso todavía no tiene etapas registradas.'));

        renderizarDetalleCaso(datos.caso);
    }

    function mostrarError(mensaje) {
        resumenModal.hidden = true;
        if (barraAuditoria) barraAuditoria.hidden = true;
        cerrarPanelLateralAuditoria();
        contenidoModal.replaceChildren();
        const panel = crearElemento('div', 'tree-panel error-state', mensaje || 'No fue posible cargar el caso.');
        contenidoModal.appendChild(panel);
    }

    async function abrirCaso(idTicket, origen) {
        if (!modal || !idTicket) return;
        botonOrigen = origen || document.activeElement;
        modal.hidden = false;
        modal.classList.add('open');
        document.body.classList.add('modal-caso-abierto');
        tituloModal.textContent = 'Caso ' + idTicket + ' · trazabilidad';
        subtituloModal.textContent = 'Cargando el árbol del caso…';
        resumenModal.hidden = true;
        if (barraAuditoria) barraAuditoria.hidden = true;
        cerrarPanelLateralAuditoria();
        contenidoModal.innerHTML = '<div class="tree-panel"><div class="loading-state"><div><div class="loading-ring"></div>Cargando trazabilidad…</div></div></div>';
        cerrar.focus();

        if (controladorPeticion) controladorPeticion.abort();
        controladorPeticion = new AbortController();

        try {
            const respuesta = await fetch(
                'solicitudes.php?ajax=arbol&id_ticket=' + encodeURIComponent(idTicket),
                {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' },
                    signal: controladorPeticion.signal
                }
            );
            const datos = await respuesta.json();
            if (!respuesta.ok || !datos.ok) {
                throw new Error(datos.mensaje || 'No fue posible cargar el caso.');
            }
            renderizarArbol(datos);
        } catch (error) {
            if (error && error.name === 'AbortError') return;
            subtituloModal.textContent = 'No fue posible cargar la trazabilidad.';
            mostrarError(error && error.message ? error.message : 'No fue posible cargar el caso.');
        }
    }

    function cerrarCaso() {
        if (!modal || !modal.classList.contains('open')) return;
        if (controladorPeticion) controladorPeticion.abort();
        modal.classList.remove('open');
        modal.hidden = true;
        document.body.classList.remove('modal-caso-abierto');
        resumenModal.hidden = true;
        if (barraAuditoria) barraAuditoria.hidden = true;
        cerrarPanelLateralAuditoria();
        casoActual = null;
        seleccionAuditoria = null;
        pestanaAuditoria = 'conversacion';
        idConversacionAuditoria = 0;
        if (botonOrigen && typeof botonOrigen.focus === 'function') botonOrigen.focus();
    }

    document.querySelectorAll('[data-open-case]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            abrirCaso(Number(boton.dataset.openCase), boton);
        });
    });
    if (cerrar) cerrar.addEventListener('click', cerrarCaso);
    botonesAuditoria.forEach(function (boton) {
        boton.addEventListener('click', function () {
            abrirPanelAuditoria(boton.dataset.auditOpen || 'conversacion');
        });
    });
    if (minimizarAuditoria) {
        minimizarAuditoria.addEventListener('click', function () {
            if (!panelAuditoriaAbierto) return;
            panelAuditoriaMinimizado = !panelAuditoriaMinimizado;
            aplicarEstadoPanelAuditoria();
        });
    }
    if (cerrarAuditoria) cerrarAuditoria.addEventListener('click', cerrarPanelLateralAuditoria);
    if (modal) {
        modal.addEventListener('click', function (evento) {
            if (evento.target === modal) cerrarCaso();
        });
    }
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && modal && modal.classList.contains('open')) {
            if (panelAuditoriaAbierto) cerrarPanelLateralAuditoria();
            else cerrarCaso();
        }
    });

    /* La capa se lleva al nivel superior para que nunca quede debajo del menú global. */
    if (modal && modal.parentElement !== document.body) document.body.appendChild(modal);
}());
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
