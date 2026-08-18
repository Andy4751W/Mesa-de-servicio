<?php
declare(strict_types=1);

require_once __DIR__ . '/calendarioLaboral.php';
require_once __DIR__ . '/correoN8n.php';

/**
 * Motor central de tickets con casos padre e hijo.
 *
 * Cada etapa oficial del flujo conserva una conversación independiente entre
 * el solicitante y el gestor asignado. Los casos hijos creados por derivación
 * mantienen una conversación interna separada entre sus gestores. Al crear
 * hijos se pausa el SLA del padre, los hermanos trabajan en paralelo y el
 * padre se reanuda cuando todos finalizan.
 */

function flujoTablaExiste(mysqli $conn, string $tabla): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla)) {
        return false;
    }

    $patron = $conn->real_escape_string(addcslashes($tabla, '\\_%'));
    $resultado = $conn->query("SHOW TABLES LIKE '{$patron}'");

    return $resultado !== false && $resultado->num_rows > 0;
}

function flujoColumnaExiste(
    mysqli $conn,
    string $tabla,
    string $columna
): bool {
    if (
        !preg_match('/^[A-Za-z0-9_]+$/', $tabla)
        || !preg_match('/^[A-Za-z0-9_]+$/', $columna)
    ) {
        return false;
    }

    $patron = $conn->real_escape_string(addcslashes($columna, '\\_%'));
    $resultado = $conn->query(
        "SHOW COLUMNS FROM `{$tabla}` LIKE '{$patron}'"
    );

    return $resultado !== false && $resultado->num_rows > 0;
}

function flujoModuloInstalado(mysqli $conn): bool
{
    foreach (
        [
            'procesos',
            'proceso_etapas',
            'proceso_etapa_checklist',
            'ticket_etapas',
            'ticket_etapa_checklist',
            'notificaciones',
            'ticket_notificaciones_email_preferencias',
        ] as $tabla
    ) {
        if (!flujoTablaExiste($conn, $tabla)) {
            return false;
        }
    }

    return flujoColumnaExiste($conn, 'tickets', 'id_proceso')
        && flujoColumnaExiste($conn, 'tickets', 'id_etapa_actual')
        && flujoColumnaExiste($conn, 'tickets', 'estado_flujo')
        && flujoColumnaExiste($conn, 'servicios', 'id_gestor')
        && flujoColumnaExiste(
            $conn,
            'solicitud_comunicaciones',
            'id_ticket_etapa'
        )
        && flujoColumnaExiste(
            $conn,
            'solicitud_adjuntos',
            'id_ticket_etapa'
        )
        && flujoColumnaExiste(
            $conn,
            'solicitud_calificaciones',
            'id_ticket_etapa'
        )
        && flujoColumnaExiste(
            $conn,
            'solicitud_historial',
            'id_ticket_etapa'
        )
        && flujoColumnaExiste(
            $conn,
            'ticket_etapas',
            'id_ticket_etapa_padre'
        )
        && flujoColumnaExiste($conn, 'ticket_etapas', 'nivel')
        && flujoColumnaExiste($conn, 'ticket_etapas', 'sla_minutos_total')
        && flujoColumnaExiste(
            $conn,
            'ticket_etapas',
            'sla_minutos_consumidos'
        )
        && flujoColumnaExiste(
            $conn,
            'ticket_etapas',
            'fecha_ultima_reanudacion'
        )
        && flujoColumnaExiste($conn, 'ticket_etapas', 'fecha_pausa')
        && flujoColumnaExiste($conn, 'ticket_etapas', 'cantidad_pausas')
        && flujoColumnaExiste($conn, 'ticket_etapas', 'creado_por')
        && flujoColumnaExiste($conn, 'ticket_etapas', 'motivo_derivacion');
}

function flujoModuloSolucionesInstalado(mysqli $conn): bool
{
    return flujoTablaExiste($conn, 'soluciones_servicio')
        && flujoColumnaExiste($conn, 'ticket_etapas', 'id_solucion')
        && flujoColumnaExiste($conn, 'ticket_etapas', 'solucion_nombre');
}

function flujoModuloCalificacionDetalladaInstalado(mysqli $conn): bool
{
    return flujoTablaExiste($conn, 'solicitud_calificaciones')
        && flujoColumnaExiste(
            $conn,
            'solicitud_calificaciones',
            'calificacion_area'
        )
        && flujoColumnaExiste(
            $conn,
            'solicitud_calificaciones',
            'calificacion_tiempo'
        );
}

function flujoModuloAprobacionCasosInstalado(mysqli $conn): bool
{
    foreach (
        [
            'fecha_marcado_listo',
            'minutos_hasta_listo',
            'resultado_sla_listo',
            'marcado_listo_por',
            'fecha_ultima_reapertura',
            'cantidad_reaperturas',
        ] as $columna
    ) {
        if (!flujoColumnaExiste($conn, 'ticket_etapas', $columna)) {
            return false;
        }
    }

    return flujoModuloCalificacionDetalladaInstalado($conn)
        && flujoColumnaExiste(
            $conn,
            'ticket_etapas',
            'solicita_cierre_definitivo'
        )
        && flujoColumnaExiste(
            $conn,
            'solicitud_calificaciones',
            'tipo_calificacion'
        );
}

function flujoAhora(): string
{
    return (new DateTimeImmutable('now', calendarioZonaHoraria()))
        ->format('Y-m-d H:i:s');
}

function flujoRegistrarHistorial(
    mysqli $conn,
    int $idTicket,
    ?int $idUsuario,
    string $accion,
    string $detalle = '',
    ?int $idTicketEtapa = null
): void {
    $stmt = $conn->prepare(
        "INSERT INTO solicitud_historial
            (id_ticket, id_ticket_etapa, id_usuario, accion, detalle)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'iiiss',
        $idTicket,
        $idTicketEtapa,
        $idUsuario,
        $accion,
        $detalle
    );
    $stmt->execute();
    $idHistorial = (int) $conn->insert_id;
    $stmt->close();

    flujoProgramarCorreoHistorial($conn, $idHistorial);
}

/**
 * Difiere el correo hasta el cierre de la petición. Así solo se notifica un
 * evento que realmente quedó confirmado por la transacción de la acción.
 */
function flujoProgramarCorreoHistorial(
    mysqli $conn,
    int $idHistorial
): void {
    static $despachadorRegistrado = false;

    if ($idHistorial < 1) {
        return;
    }

    if (!isset($GLOBALS['mesa_correos_historial'])) {
        $GLOBALS['mesa_correos_historial'] = [];
    }

    $clave = spl_object_id($conn) . ':' . $idHistorial;
    $GLOBALS['mesa_correos_historial'][$clave] = [$conn, $idHistorial];

    if (!$despachadorRegistrado) {
        register_shutdown_function(
            'flujoDespacharCorreosHistorialPendientes'
        );
        $despachadorRegistrado = true;
    }
}

function flujoDespacharCorreosHistorialPendientes(): void
{
    $pendientes = $GLOBALS['mesa_correos_historial'] ?? [];
    $GLOBALS['mesa_correos_historial'] = [];

    if (!is_array($pendientes)) {
        return;
    }

    foreach ($pendientes as $pendiente) {
        if (
            !is_array($pendiente)
            || !isset($pendiente[0], $pendiente[1])
            || !$pendiente[0] instanceof mysqli
        ) {
            continue;
        }

        try {
            flujoEnviarCorreoHistorial(
                $pendiente[0],
                (int) $pendiente[1]
            );
        } catch (Throwable $e) {
            error_log(
                'No fue posible preparar la notificación del histórico: '
                . $e->getMessage()
            );
        }
    }
}

function flujoTextoCorreo(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function flujoUrlCorreoCaso(
    int $idTicket,
    int $idNodo,
    string $tipoDestinatario
): string {
    if ($idTicket < 1 || PHP_SAPI === 'cli') {
        return '';
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $script = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    if (
        $host === ''
        || preg_match('/^[A-Za-z0-9.\-\[\]:]+$/', $host) !== 1
        || $script === ''
    ) {
        return '';
    }

    $rutaBase = rtrim(str_replace('\\', '/', dirname($script)), '/');

    if ($rutaBase === '.' || $rutaBase === '') {
        $rutaBase = '';
    }

    $esHttps = !empty($_SERVER['HTTPS'])
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $esquema = $esHttps ? 'https' : 'http';

    if ($tipoDestinatario === 'solicitante') {
        $archivo = 'panelSolicitante.php';
        $parametros = [
            'vista' => 'tickets',
            'id_ticket' => $idTicket,
        ];
    } else {
        $archivo = 'flujoTicket.php';
        $parametros = ['id_ticket' => $idTicket];

        if ($idNodo > 0) {
            $parametros['id_nodo'] = $idNodo;
            $parametros['id_chat'] = $idNodo;
        }
    }

    return $esquema
        . '://'
        . $host
        . $rutaBase
        . '/'
        . $archivo
        . '?'
        . http_build_query($parametros);
}

function flujoEnviarCorreoHistorial(
    mysqli $conn,
    int $idHistorial
): void {
    if ($idHistorial < 1) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT
            h.id_historial,
            h.id_ticket,
            h.id_ticket_etapa,
            h.id_usuario AS id_actor,
            h.accion,
            h.detalle,
            h.creado_en,
            t.titulo AS ticket_titulo,
            t.id_usuario AS id_solicitante,
            t.id_tecnico AS id_gestor_heredado,
            COALESCE(preferencia_solicitante.habilitada, 1)
                AS notificar_solicitante,
            COALESCE(preferencia_gestor.habilitada, 1)
                AS notificar_gestor,
            solicitante.nombre AS solicitante_nombre,
            solicitante.email AS solicitante_email,
            solicitante.estado AS solicitante_estado,
            actor.nombre AS actor_nombre,
            te.id_ticket_etapa AS id_nodo,
            te.id_ticket_etapa_padre,
            te.nivel,
            te.id_gestor AS id_gestor_etapa,
            te.catalogo_nombre,
            te.servicio_nombre,
            gestor.nombre AS gestor_nombre,
            gestor.email AS gestor_email,
            gestor.estado AS gestor_estado
         FROM solicitud_historial AS h
         INNER JOIN tickets AS t ON t.id_ticket = h.id_ticket
         LEFT JOIN ticket_etapas AS te
            ON te.id_ticket = t.id_ticket
           AND te.id_ticket_etapa = COALESCE(
                h.id_ticket_etapa,
                t.id_etapa_actual
           )
         LEFT JOIN usuarios AS solicitante
            ON solicitante.id_usuario = t.id_usuario
         LEFT JOIN usuarios AS actor
            ON actor.id_usuario = h.id_usuario
         LEFT JOIN usuarios AS gestor
            ON gestor.id_usuario = COALESCE(te.id_gestor, t.id_tecnico)
         LEFT JOIN ticket_notificaciones_email_preferencias
            AS preferencia_solicitante
            ON preferencia_solicitante.id_ticket = t.id_ticket
           AND preferencia_solicitante.id_usuario = t.id_usuario
         LEFT JOIN ticket_notificaciones_email_preferencias
            AS preferencia_gestor
            ON preferencia_gestor.id_ticket = t.id_ticket
           AND preferencia_gestor.id_usuario = COALESCE(
                te.id_gestor,
                t.id_tecnico
           )
         WHERE h.id_historial = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $idHistorial);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$evento) {
        // La transacción que creó el histórico no fue confirmada.
        return;
    }

    $idTicket = (int) $evento['id_ticket'];
    $idNodo = (int) ($evento['id_nodo'] ?? 0);
    $idSolicitante = (int) ($evento['id_solicitante'] ?? 0);
    $idGestor = (int) (
        $evento['id_gestor_etapa']
        ?? $evento['id_gestor_heredado']
        ?? 0
    );
    $esEtapaPublica = $idNodo < 1 || (
        (int) ($evento['id_ticket_etapa_padre'] ?? 0) === 0
        && (int) ($evento['nivel'] ?? 0) === 0
    );
    $accionEvento = trim((string) ($evento['accion'] ?? ''));
    $esEventoInternoSolicitante = in_array(
        $accionEvento,
        [
            'Derivación creada',
            'Derivación múltiple creada',
            'Caso padre reanudado',
        ],
        true
    );
    $destinatarios = [];

    // El gestor solo es elegible si el caso del evento sigue asignado a él.
    if (
        $idGestor > 0
        && (int) ($evento['notificar_gestor'] ?? 1) === 1
        && (string) ($evento['gestor_estado'] ?? '') === 'activo'
        && filter_var(
            (string) ($evento['gestor_email'] ?? ''),
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $destinatarios[strtolower((string) $evento['gestor_email'])] = [
            'nombre' => (string) ($evento['gestor_nombre'] ?? 'Gestor'),
            'email' => (string) $evento['gestor_email'],
            'tipo' => 'gestor',
        ];
    }

    // El solicitante recibe solo eventos públicos y únicamente por elección.
    if (
        $esEtapaPublica
        && !$esEventoInternoSolicitante
        && (int) ($evento['notificar_solicitante'] ?? 0) === 1
        && $idSolicitante > 0
        && (string) ($evento['solicitante_estado'] ?? '') === 'activo'
        && filter_var(
            (string) ($evento['solicitante_email'] ?? ''),
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $destinatarios[
            strtolower((string) $evento['solicitante_email'])
        ] = [
            'nombre' => (string) (
                $evento['solicitante_nombre'] ?? 'Solicitante'
            ),
            'email' => (string) $evento['solicitante_email'],
            'tipo' => 'solicitante',
        ];
    }

    if (!$destinatarios) {
        return;
    }

    $accion = $accionEvento !== '' ? $accionEvento : 'Actualización';
    $detalle = trim((string) ($evento['detalle'] ?? ''));
    $actor = trim((string) ($evento['actor_nombre'] ?? 'Sistema'));
    $tituloTicket = trim((string) ($evento['ticket_titulo'] ?? 'Solicitud'));
    $area = trim((string) ($evento['catalogo_nombre'] ?? ''));
    $servicio = trim((string) ($evento['servicio_nombre'] ?? ''));
    $contexto = trim($area . ($area !== '' && $servicio !== '' ? ' / ' : '') . $servicio);
    $asunto = "Caso #{$idTicket} | {$accion}";

    foreach ($destinatarios as $destinatario) {
        $nombreSeguro = flujoTextoCorreo($destinatario['nombre']);
        $accionSegura = flujoTextoCorreo($accion);
        $detalleSeguro = nl2br(flujoTextoCorreo($detalle));
        $actorSeguro = flujoTextoCorreo($actor);
        $tituloSeguro = flujoTextoCorreo($tituloTicket);
        $contextoSeguro = flujoTextoCorreo($contexto);
        $fechaCorreo = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $evento['creado_en'],
            calendarioZonaHoraria()
        );
        $fechaLegible = $fechaCorreo
            ? $fechaCorreo->format('d/m/Y · H:i')
            : (string) $evento['creado_en'];
        $fechaSegura = flujoTextoCorreo($fechaLegible);
        $tipoDestinatario = (string) $destinatario['tipo'];
        $rolSeguro = $tipoDestinatario === 'gestor'
            ? 'Gestor asignado'
            : 'Solicitante';
        $introduccion = $tipoDestinatario === 'gestor'
            ? 'Se registró una nueva acción en un caso asignado a su gestión.'
            : 'Se registró una nueva acción pública en su caso.';
        $enlaceCaso = flujoUrlCorreoCaso(
            $idTicket,
            $idNodo,
            $tipoDestinatario
        );
        $enlaceCasoSeguro = flujoTextoCorreo($enlaceCaso);
        $detalleHtml = $detalle !== ''
            ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;border:1px solid #dbe5f0;border-radius:10px;background:#f7f9fc"><tr><td style="padding:16px 18px"><p style="margin:0 0 7px;color:#52667a;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">Detalle de la acción</p><div style="color:#243b53;font-size:14px;line-height:1.6">'
                . $detalleSeguro . '</div></td></tr></table>'
            : '';
        $contextoHtml = $contexto !== ''
            ? '<tr><td style="padding:7px 0;color:#64788b;font-size:13px;width:145px">Área y servicio</td><td style="padding:7px 0;color:#1d3550;font-size:13px;font-weight:700">'
                . $contextoSeguro . '</td></tr>'
            : '';
        $botonHtml = $enlaceCaso !== ''
            ? '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:22px 0 0"><tr><td style="border-radius:8px;background:#1769aa"><a href="'
                . $enlaceCasoSeguro
                . '" style="display:inline-block;padding:12px 19px;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none">Consultar caso en la Mesa de Servicio</a></td></tr></table>'
            : '';
        $html = <<<HTML
<!doctype html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#edf2f7;font-family:Arial,Helvetica,sans-serif;color:#1d3550">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0">Nueva actualización registrada en el Caso #{$idTicket}: {$accionSegura}</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#edf2f7">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;margin:28px 12px;background:#ffffff;border:1px solid #d7e1ec;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(31,55,80,.08)">
        <tr>
          <td style="padding:0;background:#0d355f;height:6px;font-size:1px;line-height:1px">&nbsp;</td>
        </tr>
        <tr>
          <td style="padding:20px 26px;border-bottom:1px solid #e3eaf2;background:#ffffff">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td width="48" valign="middle"><div style="width:40px;height:40px;line-height:40px;border-radius:9px;background:#1769aa;color:#ffffff;font-size:14px;font-weight:800;text-align:center">MS</div></td>
                <td valign="middle"><p style="margin:0;color:#0d355f;font-size:17px;font-weight:800">Mesa de Servicio</p><p style="margin:3px 0 0;color:#6b7f92;font-size:11px;letter-spacing:.06em;text-transform:uppercase">Gestión y seguimiento de casos</p></td>
                <td align="right" valign="middle"><span style="display:inline-block;padding:6px 10px;border-radius:999px;background:#eaf3fb;color:#1769aa;font-size:11px;font-weight:700">Caso #{$idTicket}</span></td>
              </tr>
            </table>
          </td>
        </tr>
        <tr><td style="padding:28px 26px 26px">
          <p style="margin:0 0 8px;color:#1769aa;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">{$rolSeguro}</p>
          <h1 style="margin:0 0 12px;color:#0d355f;font-size:24px;line-height:1.25">Nueva actualización del caso</h1>
          <p style="margin:0 0 5px;color:#243b53;font-size:14px;line-height:1.6">Hola, <strong>{$nombreSeguro}</strong>.</p>
          <p style="margin:0;color:#52667a;font-size:14px;line-height:1.6">{$introduccion}</p>

          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;border-top:1px solid #e3eaf2;border-bottom:1px solid #e3eaf2">
            <tr><td style="padding:16px 0 8px;color:#64788b;font-size:13px;width:145px">Acción registrada</td><td style="padding:16px 0 8px;color:#0d355f;font-size:15px;font-weight:800">{$accionSegura}</td></tr>
            <tr><td style="padding:7px 0;color:#64788b;font-size:13px">Asunto del caso</td><td style="padding:7px 0;color:#1d3550;font-size:13px;font-weight:700">{$tituloSeguro}</td></tr>
            {$contextoHtml}
            <tr><td style="padding:7px 0;color:#64788b;font-size:13px">Registrado por</td><td style="padding:7px 0;color:#1d3550;font-size:13px;font-weight:700">{$actorSeguro}</td></tr>
            <tr><td style="padding:7px 0 16px;color:#64788b;font-size:13px">Fecha y hora</td><td style="padding:7px 0 16px;color:#1d3550;font-size:13px;font-weight:700">{$fechaSegura}</td></tr>
          </table>

          {$detalleHtml}
          {$botonHtml}
          <p style="margin:20px 0 0;color:#6b7f92;font-size:12px;line-height:1.55">Puede activar o desactivar estas notificaciones desde el detalle del caso.</p>
        </td></tr>
        <tr><td style="padding:17px 26px;border-top:1px solid #e3eaf2;background:#f7f9fc"><p style="margin:0;color:#75889a;font-size:11px;line-height:1.5">Mensaje automático de la Mesa de Servicio. Por favor, no responda directamente a este correo.</p></td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        try {
            correoN8nEnviarSinEspera(
                (string) $destinatario['email'],
                $asunto,
                $html,
                'ticket_update'
            );
        } catch (Throwable $e) {
            error_log(
                "No fue posible enviar la notificación del Caso {$idTicket} "
                . 'a ' . $destinatario['tipo'] . ': ' . $e->getMessage()
            );
        }
    }
}

function flujoNotificacionesEmailHabilitadas(
    mysqli $conn,
    int $idTicket,
    int $idUsuario
): bool {
    if ($idTicket < 1 || $idUsuario < 1) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT habilitada
         FROM ticket_notificaciones_email_preferencias
         WHERE id_ticket = ? AND id_usuario = ?
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idTicket, $idUsuario);
    $stmt->execute();
    $preferencia = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // La ausencia de preferencia significa activado por defecto.
    return !$preferencia || (int) $preferencia['habilitada'] === 1;
}

function flujoPuedeConfigurarNotificacionesEmail(
    mysqli $conn,
    int $idTicket,
    int $idUsuario,
    int $rol,
    int $idTicketEtapa = 0
): bool {
    if ($idTicket < 1 || $idUsuario < 1) {
        return false;
    }

    if ($rol === 3) {
        $stmt = $conn->prepare(
            "SELECT 1
             FROM tickets
             WHERE id_ticket = ? AND id_usuario = ?
             LIMIT 1"
        );
        $stmt->bind_param('ii', $idTicket, $idUsuario);
    } elseif ($rol === 2 && $idTicketEtapa > 0) {
        $stmt = $conn->prepare(
            "SELECT 1
             FROM ticket_etapas
             WHERE id_ticket = ?
               AND id_ticket_etapa = ?
               AND id_gestor = ?
             LIMIT 1"
        );
        $stmt->bind_param(
            'iii',
            $idTicket,
            $idTicketEtapa,
            $idUsuario
        );
    } else {
        return false;
    }

    $stmt->execute();
    $stmt->store_result();
    $permitido = $stmt->num_rows === 1;
    $stmt->close();

    return $permitido;
}

function flujoConfigurarNotificacionesEmail(
    mysqli $conn,
    int $idTicket,
    int $idUsuario,
    int $rol,
    bool $habilitada,
    int $idTicketEtapa = 0
): void {
    if (!flujoPuedeConfigurarNotificacionesEmail(
        $conn,
        $idTicket,
        $idUsuario,
        $rol,
        $idTicketEtapa
    )) {
        throw new RuntimeException(
            'Solo puede configurar correos de un caso propio o asignado.'
        );
    }

    $valor = $habilitada ? 1 : 0;
    $stmt = $conn->prepare(
        "INSERT INTO ticket_notificaciones_email_preferencias
            (id_ticket, id_usuario, habilitada)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            habilitada = VALUES(habilitada),
            actualizada_en = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param('iii', $idTicket, $idUsuario, $valor);
    $stmt->execute();
    $stmt->close();
}

function flujoNotificar(
    mysqli $conn,
    int $idUsuario,
    int $idTicket,
    ?int $idTicketEtapa,
    string $titulo,
    string $mensaje
): void {
    if ($idUsuario < 1) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO notificaciones
            (
                id_usuario,
                id_ticket,
                id_ticket_etapa,
                titulo,
                mensaje
            )
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'iiiss',
        $idUsuario,
        $idTicket,
        $idTicketEtapa,
        $titulo,
        $mensaje
    );
    $stmt->execute();
    $stmt->close();
}

function flujoSlaMinutosTotales(int $tiempo, string $unidad): int
{
    if ($tiempo < 1) {
        return 0;
    }

    return match ($unidad) {
        'dias' => $tiempo * CALENDARIO_MINUTOS_DIA_SLA,
        'horas' => $tiempo * 60,
        default => $tiempo,
    };
}

function flujoCopiarChecklistPlantilla(
    mysqli $conn,
    int $idTicketEtapa,
    int $idProcesoEtapa
): void {
    $stmt = $conn->prepare(
        "INSERT INTO ticket_etapa_checklist
            (
                id_ticket_etapa,
                id_checklist_plantilla,
                nombre,
                descripcion,
                obligatorio,
                requiere_evidencia,
                orden
            )
         SELECT
            ?,
            pc.id_checklist,
            pc.nombre,
            pc.descripcion,
            pc.obligatorio,
            pc.requiere_evidencia,
            pc.orden
         FROM proceso_etapa_checklist AS pc
         WHERE pc.id_proceso_etapa = ?
           AND pc.estado = 'activo'
           AND NOT EXISTS (
                SELECT 1
                FROM ticket_etapa_checklist AS tc
                WHERE tc.id_ticket_etapa = ?
                  AND tc.id_checklist_plantilla = pc.id_checklist
           )
         ORDER BY pc.orden, pc.id_checklist"
    );
    $stmt->bind_param(
        'iii',
        $idTicketEtapa,
        $idProcesoEtapa,
        $idTicketEtapa
    );
    $stmt->execute();
    $stmt->close();
}

function flujoSincronizarChecklistEtapaAbierta(
    mysqli $conn,
    int $idTicketEtapa
): void {
    $stmt = $conn->prepare(
        "SELECT id_proceso_etapa, estado
         FROM ticket_etapas
         WHERE id_ticket_etapa = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $idTicketEtapa);
    $stmt->execute();
    $etapa = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$etapa
        || (int) ($etapa['id_proceso_etapa'] ?? 0) < 1
        || !in_array(
            (string) $etapa['estado'],
            ['pendiente', 'en_proceso', 'en_espera_solicitante', 'pausada'],
            true
        )
    ) {
        return;
    }

    flujoCopiarChecklistPlantilla(
        $conn,
        $idTicketEtapa,
        (int) $etapa['id_proceso_etapa']
    );
}

/**
 * @return array<string, mixed>|null
 */
function flujoObtenerNodo(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa
): ?array {
    $stmt = $conn->prepare(
        "SELECT te.*
         FROM ticket_etapas AS te
         WHERE te.id_ticket = ?
           AND te.id_ticket_etapa = ?
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idTicket, $idTicketEtapa);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

/**
 * Devuelve el identificador visible del nodo.
 *
 * Las etapas oficiales pertenecen siempre al mismo caso principal y por eso
 * muestran el id del ticket (Caso 1, Caso 2, etc.). Solo las derivaciones
 * reciben un numero de ticket jerarquico basado en la etapa que las origino:
 * 1.1, 1.2, 2.1, 2.1.1, etc.
 */
function flujoCodigoCaso(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa
): string {
    $ruta = [];
    $actual = $idTicketEtapa;
    $visitados = [];

    while ($actual > 0 && !isset($visitados[$actual]) && count($ruta) < 100) {
        $visitados[$actual] = true;
        $stmt = $conn->prepare(
            "SELECT id_ticket_etapa, id_ticket_etapa_padre, orden
             FROM ticket_etapas
             WHERE id_ticket = ? AND id_ticket_etapa = ?
             LIMIT 1"
        );
        $stmt->bind_param('ii', $idTicket, $actual);
        $stmt->execute();
        $nodo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$nodo) {
            break;
        }

        $ruta[] = $nodo;
        $actual = (int) ($nodo['id_ticket_etapa_padre'] ?? 0);
    }

    if (!$ruta) {
        return (string) $idTicketEtapa;
    }

    $ruta = array_reverse($ruta);

    /* Una etapa oficial nunca crea un caso nuevo. */
    if (count($ruta) === 1) {
        return (string) $idTicket;
    }

    $raiz = $ruta[0];
    $ordenRaiz = (int) $raiz['orden'];
    $idRaiz = (int) $raiz['id_ticket_etapa'];
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS posicion
         FROM ticket_etapas
         WHERE id_ticket = ?
           AND id_ticket_etapa_padre IS NULL
           AND (orden < ? OR (orden = ? AND id_ticket_etapa <= ?))"
    );
    $stmt->bind_param(
        'iiii',
        $idTicket,
        $ordenRaiz,
        $ordenRaiz,
        $idRaiz
    );
    $stmt->execute();
    $posicionEtapa = (int) $stmt->get_result()->fetch_assoc()['posicion'];
    $stmt->close();
    $codigo = (string) max(1, $posicionEtapa);

    foreach (array_slice($ruta, 1) as $nodo) {
        $idPadre = (int) $nodo['id_ticket_etapa_padre'];
        $orden = (int) $nodo['orden'];
        $idNodo = (int) $nodo['id_ticket_etapa'];
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS posicion
             FROM ticket_etapas
             WHERE id_ticket = ?
               AND id_ticket_etapa_padre = ?
               AND (orden < ? OR (orden = ? AND id_ticket_etapa <= ?))"
        );
        $stmt->bind_param(
            'iiiii',
            $idTicket,
            $idPadre,
            $orden,
            $orden,
            $idNodo
        );
        $stmt->execute();
        $posicion = (int) $stmt->get_result()->fetch_assoc()['posicion'];
        $stmt->close();
        $codigo .= '.' . max(1, $posicion);
    }

    return $codigo;
}

function flujoNumeroEtapaNodo(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa
): int {
    $actual = $idTicketEtapa;
    $raiz = null;
    $visitados = [];

    while ($actual > 0 && !isset($visitados[$actual])) {
        $visitados[$actual] = true;
        $stmt = $conn->prepare(
            "SELECT id_ticket_etapa, id_ticket_etapa_padre, orden
             FROM ticket_etapas
             WHERE id_ticket = ? AND id_ticket_etapa = ?
             LIMIT 1"
        );
        $stmt->bind_param('ii', $idTicket, $actual);
        $stmt->execute();
        $nodo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$nodo) {
            break;
        }

        $raiz = $nodo;
        $actual = (int) ($nodo['id_ticket_etapa_padre'] ?? 0);
    }

    if (!$raiz) {
        return 0;
    }

    $orden = (int) $raiz['orden'];
    $idRaiz = (int) $raiz['id_ticket_etapa'];
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS posicion
         FROM ticket_etapas
         WHERE id_ticket = ?
           AND id_ticket_etapa_padre IS NULL
           AND (orden < ? OR (orden = ? AND id_ticket_etapa <= ?))"
    );
    $stmt->bind_param('iiii', $idTicket, $orden, $orden, $idRaiz);
    $stmt->execute();
    $posicion = (int) $stmt->get_result()->fetch_assoc()['posicion'];
    $stmt->close();

    return max(1, $posicion);
}

function flujoEtiquetaNodo(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa
): string {
    $nodo = flujoObtenerNodo($conn, $idTicket, $idTicketEtapa);
    $esDerivacion = (int) ($nodo['id_ticket_etapa_padre'] ?? 0) > 0;

    if ($esDerivacion) {
        return 'Ticket ' . flujoCodigoCaso($conn, $idTicket, $idTicketEtapa);
    }

    return 'Caso ' . $idTicket . ' · etapa '
        . flujoNumeroEtapaNodo($conn, $idTicket, $idTicketEtapa);
}

/**
 * @return array{origen:int,destino:int}|null
 */
function flujoParticipantesConversacion(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa
): ?array {
    $stmt = $conn->prepare(
        "SELECT
            te.id_gestor AS destino,
            COALESCE(
                NULLIF(te.creado_por, 0),
                CASE
                    WHEN te.id_ticket_etapa_padre IS NULL THEN t.id_usuario
                    ELSE padre.id_gestor
                END
            ) AS origen
         FROM ticket_etapas AS te
         INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
         LEFT JOIN ticket_etapas AS padre
            ON padre.id_ticket_etapa = te.id_ticket_etapa_padre
         WHERE te.id_ticket = ?
           AND te.id_ticket_etapa = ?
           AND te.estado <> 'bloqueada'
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idTicket, $idTicketEtapa);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        return null;
    }

    return [
        'origen' => (int) ($fila['origen'] ?? 0),
        'destino' => (int) ($fila['destino'] ?? 0),
    ];
}

function flujoPuedeVerConversacionNodo(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idUsuario,
    int $rol
): bool {
    $participantes = flujoParticipantesConversacion(
        $conn,
        $idTicket,
        $idTicketEtapa
    );

    if (!$participantes) {
        return false;
    }

    if ($rol === 1) {
        return true;
    }

    if ($rol === 2) {
        return in_array(
            $idUsuario,
            array_values($participantes),
            true
        );
    }

    if ($rol !== 3) {
        return false;
    }

    /* El solicitante solo participa en las etapas oficiales del flujo. Los
       casos hijos son derivaciones internas y nunca se exponen en su portal. */
    $stmt = $conn->prepare(
        "SELECT 1
         FROM ticket_etapas AS te
         INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
         WHERE te.id_ticket = ?
           AND te.id_ticket_etapa = ?
           AND te.id_ticket_etapa_padre IS NULL
           AND te.estado <> 'bloqueada'
           AND t.id_usuario = ?
         LIMIT 1"
    );
    $stmt->bind_param('iii', $idTicket, $idTicketEtapa, $idUsuario);
    $stmt->execute();
    $stmt->store_result();
    $puede = $stmt->num_rows > 0;
    $stmt->close();

    return $puede;
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoConversacionesDisponibles(
    mysqli $conn,
    int $idTicket,
    int $idUsuario,
    int $rol
): array {
    $sql = "SELECT
                te.id_ticket_etapa,
                te.id_ticket_etapa_padre,
                te.nivel,
                te.orden,
                te.estado,
                te.catalogo_nombre,
                te.servicio_nombre,
                CASE
                    WHEN te.id_ticket_etapa_padre IS NULL THEN 'flujo'
                    ELSE 'derivacion'
                END AS tipo_conversacion,
                te.id_gestor AS id_gestor_destino,
                destino.nombre AS gestor_destino,
                COALESCE(
                    NULLIF(te.creado_por, 0),
                    CASE
                        WHEN te.id_ticket_etapa_padre IS NULL THEN t.id_usuario
                        ELSE padre.id_gestor
                    END
                ) AS id_gestor_origen,
                origen.nombre AS gestor_origen,
                (SELECT COUNT(*)
                 FROM solicitud_comunicaciones AS sc
                 WHERE sc.id_ticket_etapa = te.id_ticket_etapa
                   AND (
                        te.id_ticket_etapa_padre IS NOT NULL
                        OR sc.tipo = 'publica'
                   )) AS total_mensajes,
                (SELECT COUNT(*)
                 FROM solicitud_adjuntos AS sa
                 WHERE sa.id_ticket_etapa = te.id_ticket_etapa) AS total_adjuntos
            FROM ticket_etapas AS te
            INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
            LEFT JOIN ticket_etapas AS padre
                ON padre.id_ticket_etapa = te.id_ticket_etapa_padre
            LEFT JOIN usuarios AS destino
                ON destino.id_usuario = te.id_gestor
            LEFT JOIN usuarios AS origen
                ON origen.id_usuario = COALESCE(
                    NULLIF(te.creado_por, 0),
                    CASE
                        WHEN te.id_ticket_etapa_padre IS NULL THEN t.id_usuario
                        ELSE padre.id_gestor
                    END
                )
            WHERE te.id_ticket = ?
              AND te.estado <> 'bloqueada'";

    if ($rol === 2) {
        $sql .= " AND (
                    te.id_gestor = ?
                    OR COALESCE(
                        NULLIF(te.creado_por, 0),
                        CASE
                            WHEN te.id_ticket_etapa_padre IS NULL THEN t.id_usuario
                            ELSE padre.id_gestor
                        END
                    ) = ?
                  )";
    } elseif ($rol === 3) {
        $sql .= " AND te.id_ticket_etapa_padre IS NULL
                  AND t.id_usuario = ?";
    } elseif ($rol !== 1) {
        return [];
    }

    $sql .= " ORDER BY te.nivel, te.orden, te.id_ticket_etapa";
    $stmt = $conn->prepare($sql);

    if ($rol === 2) {
        $stmt->bind_param('iii', $idTicket, $idUsuario, $idUsuario);
    } elseif ($rol === 3) {
        $stmt->bind_param('ii', $idTicket, $idUsuario);
    } else {
        $stmt->bind_param('i', $idTicket);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();
    $filas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $filas[] = $fila;
    }

    $stmt->close();

    foreach ($filas as &$fila) {
        $esDerivacion = (int) ($fila['id_ticket_etapa_padre'] ?? 0) > 0;
        $fila['numero_etapa'] = flujoNumeroEtapaNodo(
            $conn,
            $idTicket,
            (int) $fila['id_ticket_etapa']
        );
        $fila['codigo_nodo'] = flujoCodigoCaso(
            $conn,
            $idTicket,
            (int) $fila['id_ticket_etapa']
        );
        $fila['codigo_caso'] = (string) $idTicket;
        $fila['codigo_ticket'] = $esDerivacion
            ? (string) $fila['codigo_nodo']
            : (string) $fila['numero_etapa'];
    }
    unset($fila);

    return $filas;
}

function flujoNodoPuedeGestionar(
    array $nodo,
    int $idUsuario,
    int $rol,
    bool $permitirPausado = false
): bool {
    $estados = ['pendiente', 'en_proceso', 'en_espera_solicitante'];

    if ($permitirPausado) {
        $estados[] = 'pausada';
    }

    if (!in_array((string) ($nodo['estado'] ?? ''), $estados, true)) {
        return false;
    }

    return $rol === 1
        || ($rol === 2 && (int) ($nodo['id_gestor'] ?? 0) === $idUsuario);
}

function flujoMinutosConsumidosNodo(
    mysqli $conn,
    array $nodo,
    ?string $hasta = null,
    bool $incluirEsperaCierre = false
): int {
    $consumidos = (int) ($nodo['sla_minutos_consumidos'] ?? 0);
    $estado = (string) ($nodo['estado'] ?? '');
    $desde = (string) (
        $nodo['fecha_ultima_reanudacion']
        ?? $nodo['fecha_activacion']
        ?? ''
    );

    if (
        $desde !== ''
        && in_array(
            $estado,
            $incluirEsperaCierre
                ? ['pendiente', 'en_proceso', 'en_espera_solicitante', 'listo_cierre']
                : ['pendiente', 'en_proceso', 'en_espera_solicitante'],
            true
        )
    ) {
        if ((string) ($nodo['sla_unidad'] ?? '') === 'dias') {
            try {
                $desde = (new DateTimeImmutable(
                    $desde,
                    calendarioZonaHoraria()
                ))
                    ->setTime(0, 0)
                    ->modify('+1 day')
                    ->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
                return max(0, $consumidos);
            }
        }

        $fin = new DateTimeImmutable(
            $hasta ?: flujoAhora(),
            calendarioZonaHoraria()
        );
        $consumidos += minutosHabilesTranscurridos($conn, $desde, $fin);
    }

    return max(0, $consumidos);
}

/**
 * La primera etapa raíz representa el servicio solicitado y recibe la única
 * encuesta principal del ticket. Las etapas derivadas reciben evaluación
 * interna del gestor que abrió la derivación. Las demás etapas raíz heredadas
 * del flujo también exigen calificación, pero se identifican como evaluación
 * operativa del caso y no como otra encuesta del servicio solicitado.
 */
function flujoTipoCalificacionCaso(
    mysqli $conn,
    int $idTicket,
    array $etapa
): ?string {
    if ((int) ($etapa['id_ticket_etapa_padre'] ?? 0) > 0) {
        return 'evaluacion_derivacion';
    }

    $stmt = $conn->prepare(
        "SELECT id_ticket_etapa
         FROM ticket_etapas
         WHERE id_ticket = ?
           AND id_ticket_etapa_padre IS NULL
         ORDER BY orden, id_ticket_etapa
         LIMIT 1"
    );
    $stmt->bind_param('i', $idTicket);
    $stmt->execute();
    $primeraRaiz = (int) (
        $stmt->get_result()->fetch_assoc()['id_ticket_etapa'] ?? 0
    );
    $stmt->close();

    return $primeraRaiz === (int) ($etapa['id_ticket_etapa'] ?? 0)
        ? 'encuesta_servicio'
        : 'evaluacion_caso';
}

function flujoCalcularVencimientoRestante(
    mysqli $conn,
    array $nodo,
    string $inicio,
    int $minutosRestantes
): ?string {
    if ($minutosRestantes < 1) {
        return $inicio;
    }

    if ((string) ($nodo['sla_unidad'] ?? '') === 'dias') {
        if ($minutosRestantes % CALENDARIO_MINUTOS_DIA_SLA === 0) {
            return calcularVencimientoSla(
                $conn,
                $inicio,
                intdiv($minutosRestantes, CALENDARIO_MINUTOS_DIA_SLA),
                'dias'
            );
        }

        try {
            $inicio = (new DateTimeImmutable(
                $inicio,
                calendarioZonaHoraria()
            ))
                ->setTime(0, 0)
                ->modify('+1 day')
                ->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    return calcularVencimientoSla(
        $conn,
        $inicio,
        $minutosRestantes,
        'minutos'
    );
}

function flujoRecalcularVencimientosActivos(
    mysqli $conn,
    int $idTicket
): void {
    $stmt = $conn->prepare(
        "SELECT *
         FROM ticket_etapas
         WHERE id_ticket = ?
           AND estado IN ('pendiente', 'en_proceso', 'en_espera_solicitante')
           AND COALESCE(fecha_ultima_reanudacion, fecha_activacion) IS NOT NULL"
    );
    $stmt->bind_param('i', $idTicket);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $actualizaciones = [];

    while ($nodo = $resultado->fetch_assoc()) {
        $total = (int) ($nodo['sla_minutos_total'] ?? 0);
        $consumidos = (int) ($nodo['sla_minutos_consumidos'] ?? 0);
        $restantes = max(0, $total - $consumidos);
        $inicio = (string) (
            $nodo['fecha_ultima_reanudacion']
            ?? $nodo['fecha_activacion']
            ?? ''
        );

        if ($inicio === '') {
            continue;
        }

        $actualizaciones[] = [
            (int) $nodo['id_ticket_etapa'],
            flujoCalcularVencimientoRestante(
                $conn,
                $nodo,
                $inicio,
                $restantes
            ),
        ];
    }
    $stmt->close();

    foreach ($actualizaciones as [$idNodo, $vencimiento]) {
        $stmt = $conn->prepare(
            "UPDATE ticket_etapas
             SET fecha_vencimiento = ?
             WHERE id_ticket_etapa = ?"
        );
        $stmt->bind_param('si', $vencimiento, $idNodo);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoServiciosDisponibles(mysqli $conn): array
{
    $idPaisOperacion = paisExigirContexto();
    $resultado = $conn->query(
        "SELECT
            s.id_servicio,
            s.nombre AS servicio_nombre,
            c.id_catalogo,
            c.nombre AS catalogo_nombre,
            s.id_gestor,
            u.nombre AS gestor_nombre,
            sl.id_sla,
            sl.nombre AS sla_nombre,
            sl.tiempo_respuesta AS sla_tiempo,
            sl.unidad AS sla_unidad,
            p.id_proceso,
            pe.id_proceso_etapa
         FROM servicios AS s
         INNER JOIN catalogos AS c
            ON c.id_catalogo = s.id_catalogo
           AND c.estado = 'activo'
         INNER JOIN usuarios AS u
            ON u.id_usuario = s.id_gestor
           AND u.id_rol = 2
           AND u.estado = 'activo'
         INNER JOIN sla AS sl
            ON sl.id_sla = s.id_sla
           AND sl.estado = 'activo'
         INNER JOIN proceso_etapas AS pe
            ON pe.id_servicio = s.id_servicio
           AND pe.estado = 'activo'
         INNER JOIN procesos AS p
            ON p.id_proceso = pe.id_proceso
           AND p.estado = 'activo'
         WHERE s.estado = 'activo'
           AND s.id_pais_operacion = {$idPaisOperacion}
           AND pe.orden = (
                SELECT MIN(pe_base.orden)
                FROM proceso_etapas AS pe_base
                WHERE pe_base.id_proceso = p.id_proceso
                  AND pe_base.estado = 'activo'
           )
         ORDER BY c.orden, c.nombre, s.nombre, p.id_proceso"
    );
    $filas = [];
    $vistos = [];

    while ($fila = $resultado->fetch_assoc()) {
        $idServicio = (int) $fila['id_servicio'];

        if (isset($vistos[$idServicio])) {
            continue;
        }

        $vistos[$idServicio] = true;
        $filas[] = $fila;
    }

    return $filas;
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoSolucionesServicio(
    mysqli $conn,
    int $idServicio,
    bool $soloActivas = true
): array {
    if (
        $idServicio < 1
        || !flujoTablaExiste($conn, 'soluciones_servicio')
    ) {
        return [];
    }

    $sql = "SELECT id_solucion, id_servicio, nombre, descripcion, estado, orden
            FROM soluciones_servicio
            WHERE id_servicio = ?";

    if ($soloActivas) {
        $sql .= " AND estado = 'activo'";
    }

    $sql .= ' ORDER BY orden, nombre, id_solucion';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $idServicio);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $filas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $filas[] = $fila;
    }

    $stmt->close();

    return $filas;
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoEtapasPlantilla(mysqli $conn, int $idProceso): array
{
    $idPaisOperacion = paisExigirContexto();
    $stmt = $conn->prepare(
        "SELECT
            pe.id_proceso_etapa,
            pe.orden,
            COALESCE(NULLIF(pe.nombre_etapa, ''), s.nombre) AS nombre_etapa,
            pe.instrucciones,
            c.id_catalogo,
            c.nombre AS catalogo_nombre,
            s.id_servicio,
            s.nombre AS servicio_nombre,
            s.id_gestor,
            u.nombre AS gestor_nombre,
            sl.id_sla,
            sl.nombre AS sla_nombre,
            sl.tiempo_respuesta AS sla_tiempo,
            sl.unidad AS sla_unidad
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
         INNER JOIN usuarios AS u
            ON u.id_usuario = s.id_gestor
           AND u.id_rol = 2
           AND u.estado = 'activo'
         INNER JOIN sla AS sl
            ON sl.id_sla = s.id_sla
           AND sl.estado = 'activo'
         WHERE p.id_proceso = ?
           AND p.id_pais_operacion = ?
           AND p.estado = 'activo'
         ORDER BY pe.orden ASC, pe.id_proceso_etapa ASC"
    );
    $stmt->bind_param('ii', $idProceso, $idPaisOperacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $etapas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $etapas[] = $fila;
    }

    $stmt->close();

    return $etapas;
}

function flujoCrearTicket(
    mysqli $conn,
    int $idProceso,
    int $idSolicitante,
    string $titulo,
    string $descripcion,
    string $urgencia
): int {
    $idPaisOperacion = paisExigirContexto();
    $stmtPaisUsuario = $conn->prepare(
        "SELECT 1 FROM usuarios
         WHERE id_usuario = ? AND id_pais_operacion = ? AND estado = 'activo'
         LIMIT 1"
    );
    $stmtPaisUsuario->bind_param('ii', $idSolicitante, $idPaisOperacion);
    $stmtPaisUsuario->execute();
    $stmtPaisUsuario->store_result();
    $usuarioValido = $stmtPaisUsuario->num_rows === 1;
    $stmtPaisUsuario->close();

    if (!$usuarioValido) {
        throw new RuntimeException('El solicitante no pertenece al país de operación activo.');
    }

    $etapas = flujoEtapasPlantilla($conn, $idProceso);

    if (!$etapas) {
        throw new RuntimeException(
            'El servicio inicial debe tener flujo activo, gestor y SLA.'
        );
    }

    $primera = $etapas[0];
    $prioridad = match ($urgencia) {
        'baja' => 'baja',
        'alta', 'urgente' => 'alta',
        default => 'media',
    };

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "INSERT INTO tickets
                (
                    id_pais_operacion,
                    titulo,
                    descripcion,
                    estado,
                    urgencia,
                    prioridad,
                    id_usuario,
                    id_tecnico,
                    id_servicio,
                    id_proceso,
                    estado_flujo,
                    fecha_creacion
                )
             VALUES (?, ?, ?, 'abierto', ?, ?, ?, ?, ?, ?, 'en_proceso', NOW())"
        );
        $idGestorInicial = (int) $primera['id_gestor'];
        $idServicioInicial = (int) $primera['id_servicio'];
        $stmt->bind_param(
            'issssiiii',
            $idPaisOperacion,
            $titulo,
            $descripcion,
            $urgencia,
            $prioridad,
            $idSolicitante,
            $idGestorInicial,
            $idServicioInicial,
            $idProceso
        );
        $stmt->execute();
        $idTicket = (int) $conn->insert_id;
        $stmt->close();

        $fechaActivacionInicial = flujoAhora();
        $idEtapaActual = 0;

        foreach ($etapas as $indice => $etapaPlantilla) {
            $esPrimera = $indice === 0;
            $idProcesoEtapa = (int) $etapaPlantilla['id_proceso_etapa'];
            $ordenEtapa = (int) $etapaPlantilla['orden'];
            $idCatalogo = (int) $etapaPlantilla['id_catalogo'];
            $catalogoNombre = (string) $etapaPlantilla['catalogo_nombre'];
            $idServicio = (int) $etapaPlantilla['id_servicio'];
            $servicioNombre = (string) $etapaPlantilla['servicio_nombre'];
            $idGestor = (int) $etapaPlantilla['id_gestor'];
            $gestorNombre = (string) $etapaPlantilla['gestor_nombre'];
            $idSla = (int) $etapaPlantilla['id_sla'];
            $slaNombre = (string) $etapaPlantilla['sla_nombre'];
            $slaTiempo = (int) $etapaPlantilla['sla_tiempo'];
            $slaUnidad = (string) $etapaPlantilla['sla_unidad'];
            $slaMinutosTotal = flujoSlaMinutosTotales(
                $slaTiempo,
                $slaUnidad
            );
            $estadoEtapa = $esPrimera ? 'pendiente' : 'bloqueada';
            $fechaActivacion = $esPrimera
                ? $fechaActivacionInicial
                : null;
            $fechaVencimiento = $esPrimera
                ? calcularVencimientoSla(
                    $conn,
                    $fechaActivacionInicial,
                    $slaTiempo,
                    $slaUnidad
                )
                : null;
            $fechaReanudacion = $fechaActivacion;
            $stmt = $conn->prepare(
                "INSERT INTO ticket_etapas
                    (
                        id_ticket,
                        id_ticket_etapa_padre,
                        id_proceso_etapa,
                        nivel,
                        orden,
                        id_catalogo,
                        catalogo_nombre,
                        id_servicio,
                        servicio_nombre,
                        id_gestor,
                        gestor_nombre,
                        id_sla,
                        sla_nombre,
                        sla_tiempo,
                        sla_unidad,
                        sla_minutos_total,
                        estado,
                        fecha_activacion,
                        fecha_vencimiento,
                        fecha_ultima_reanudacion,
                        creado_por
                    )
                 VALUES (?, NULL, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'iiiisisisisisissssi',
                $idTicket,
                $idProcesoEtapa,
                $ordenEtapa,
                $idCatalogo,
                $catalogoNombre,
                $idServicio,
                $servicioNombre,
                $idGestor,
                $gestorNombre,
                $idSla,
                $slaNombre,
                $slaTiempo,
                $slaUnidad,
                $slaMinutosTotal,
                $estadoEtapa,
                $fechaActivacion,
                $fechaVencimiento,
                $fechaReanudacion,
                $idSolicitante
            );
            $stmt->execute();
            $idEtapa = (int) $conn->insert_id;
            $stmt->close();

            if ($esPrimera) {
                $idEtapaActual = $idEtapa;
            }

            flujoCopiarChecklistPlantilla(
                $conn,
                $idEtapa,
                $idProcesoEtapa
            );
        }

        $stmt = $conn->prepare(
            "UPDATE tickets
             SET id_etapa_actual = ?
             WHERE id_ticket = ?"
        );
        $stmt->bind_param('ii', $idEtapaActual, $idTicket);
        $stmt->execute();
        $stmt->close();

        flujoRegistrarHistorial(
            $conn,
            $idTicket,
            $idSolicitante,
            'Caso principal abierto',
            'Se creó el Caso '
                . $idTicket
                . ' y se activó la etapa 1 para '
                . $primera['catalogo_nombre']
                . ' / '
                . $primera['servicio_nombre']
                . '. Asunto: '
                . $titulo
                . '. Solicitud: '
                . $descripcion,
            $idEtapaActual
        );
        flujoNotificar(
            $conn,
            $idGestorInicial,
            $idTicket,
            $idEtapaActual,
            'Nuevo ticket asignado',
            "El Caso {$idTicket}, etapa 1, está disponible para su gestión."
        );

        $conn->commit();

        return $idTicket;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Crea entre uno y veinte hijos hermanos en una sola transacción.
 *
 * @param array<int, array{id_servicio_destino:int,motivo_derivacion:string}> $derivaciones
 * @return array<int, int>
 */
function flujoCrearCasosHijos(
    mysqli $conn,
    int $idTicket,
    int $idNodoPadre,
    array $derivaciones,
    int $idGestorCreador,
    int $rol
): array {
    $idPaisOperacion = paisExigirContexto();
    if ($rol !== 2) {
        throw new RuntimeException(
            'Solo el gestor asignado a la etapa puede crear tickets derivados.'
        );
    }

    if (!$derivaciones || count($derivaciones) > 20) {
        throw new RuntimeException(
            'Agregue entre 1 y 20 derivaciones en la misma operación.'
        );
    }

    $normalizadas = [];
    $serviciosIncluidos = [];

    foreach ($derivaciones as $indice => $derivacion) {
        $idServicio = (int) ($derivacion['id_servicio_destino'] ?? 0);
        $motivo = trim((string) ($derivacion['motivo_derivacion'] ?? ''));

        if ($idServicio < 1 || $motivo === '' || strlen($motivo) > 2000) {
            throw new RuntimeException(
                'Complete el área y el requerimiento de la derivación '
                    . ((int) $indice + 1)
                    . '.'
            );
        }

        if (isset($serviciosIncluidos[$idServicio])) {
            throw new RuntimeException(
                'No repita la misma área y servicio en una derivación múltiple.'
            );
        }

        $serviciosIncluidos[$idServicio] = true;
        $normalizadas[] = [
            'id_servicio_destino' => $idServicio,
            'motivo_derivacion' => $motivo,
        ];
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT te.*, t.id_usuario AS id_creador_ticket
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE te.id_ticket = ?
               AND te.id_ticket_etapa = ?
               AND t.id_pais_operacion = ?
             FOR UPDATE"
        );
        $stmt->bind_param('iii', $idTicket, $idNodoPadre, $idPaisOperacion);
        $stmt->execute();
        $padre = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$padre
            || (int) $padre['id_gestor'] !== $idGestorCreador
            || !in_array(
                (string) $padre['estado'],
                ['pendiente', 'en_proceso', 'en_espera_solicitante', 'pausada'],
                true
            )
        ) {
            throw new RuntimeException(
                'La etapa del caso no está disponible para este gestor.'
            );
        }

        $sqlDestino = "SELECT
                s.id_servicio,
                s.nombre AS servicio_nombre,
                c.id_catalogo,
                c.nombre AS catalogo_nombre,
                s.id_gestor,
                u.nombre AS gestor_nombre,
                sl.id_sla,
                sl.nombre AS sla_nombre,
                sl.tiempo_respuesta AS sla_tiempo,
                sl.unidad AS sla_unidad,
                pe.id_proceso_etapa
             FROM servicios AS s
             INNER JOIN catalogos AS c
                ON c.id_catalogo = s.id_catalogo
               AND c.estado = 'activo'
             INNER JOIN usuarios AS u
                ON u.id_usuario = s.id_gestor
               AND u.id_rol = 2
               AND u.estado = 'activo'
             INNER JOIN sla AS sl
                ON sl.id_sla = s.id_sla
               AND sl.estado = 'activo'
             INNER JOIN proceso_etapas AS pe
                ON pe.id_servicio = s.id_servicio
               AND pe.estado = 'activo'
             INNER JOIN procesos AS p
                ON p.id_proceso = pe.id_proceso
               AND p.estado = 'activo'
             WHERE s.id_servicio = ?
               AND s.id_pais_operacion = ?
               AND s.estado = 'activo'
               AND pe.orden = (
                    SELECT MIN(pe_base.orden)
                    FROM proceso_etapas AS pe_base
                    WHERE pe_base.id_proceso = p.id_proceso
                      AND pe_base.estado = 'activo'
               )
             ORDER BY p.id_proceso
             LIMIT 1";
        $stmtDestino = $conn->prepare($sqlDestino);
        $destinos = [];

        foreach ($normalizadas as $derivacion) {
            $idServicioDestino = $derivacion['id_servicio_destino'];
            $stmtDestino->bind_param('ii', $idServicioDestino, $idPaisOperacion);
            $stmtDestino->execute();
            $destino = $stmtDestino->get_result()->fetch_assoc();

            if (!$destino) {
                $stmtDestino->close();
                throw new RuntimeException(
                    'Uno de los servicios destino necesita flujo activo, gestor y SLA.'
                );
            }

            $destinos[] = [
                'configuracion' => $destino,
                'motivo_derivacion' => $derivacion['motivo_derivacion'],
            ];
        }
        $stmtDestino->close();

        $ahora = flujoAhora();
        $padreYaEstabaPausado = $padre['estado'] === 'pausada';

        if (!$padreYaEstabaPausado) {
            $consumidos = flujoMinutosConsumidosNodo($conn, $padre, $ahora);
            $stmt = $conn->prepare(
                "UPDATE ticket_etapas
                 SET estado = 'pausada',
                     sla_minutos_consumidos = ?,
                     fecha_pausa = ?,
                     fecha_ultima_reanudacion = NULL,
                     fecha_vencimiento = NULL,
                     cantidad_pausas = cantidad_pausas + 1
                 WHERE id_ticket_etapa = ?"
            );
            $stmt->bind_param('isi', $consumidos, $ahora, $idNodoPadre);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare(
            "SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
             FROM ticket_etapas
             WHERE id_ticket = ?
             FOR UPDATE"
        );
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $orden = (int) $stmt->get_result()->fetch_assoc()['siguiente'];
        $stmt->close();

        $nivel = (int) $padre['nivel'] + 1;
        $codigoPadre = flujoCodigoCaso($conn, $idTicket, $idNodoPadre);
        $numeroEtapaPadre = flujoNumeroEtapaNodo(
            $conn,
            $idTicket,
            $idNodoPadre
        );
        $origenDerivacion = (int) (
            $padre['id_ticket_etapa_padre'] ?? 0
        ) > 0
            ? 'Ticket ' . $codigoPadre
            : 'etapa ' . $numeroEtapaPadre . ' del Caso ' . $idTicket;
        $idsHijos = [];
        $resumenHijos = [];
        $ultimoHijo = null;

        foreach ($destinos as $itemDestino) {
            $destino = $itemDestino['configuracion'];
            $motivo = (string) $itemDestino['motivo_derivacion'];
            $slaTiempo = (int) $destino['sla_tiempo'];
            $slaUnidad = (string) $destino['sla_unidad'];
            $slaMinutos = flujoSlaMinutosTotales($slaTiempo, $slaUnidad);
            $vencimiento = calcularVencimientoSla(
                $conn,
                $ahora,
                $slaTiempo,
                $slaUnidad
            );
            $idProcesoEtapa = (int) $destino['id_proceso_etapa'];
            $idCatalogo = (int) $destino['id_catalogo'];
            $catalogoNombre = (string) $destino['catalogo_nombre'];
            $idServicio = (int) $destino['id_servicio'];
            $servicioNombre = (string) $destino['servicio_nombre'];
            $idGestor = (int) $destino['id_gestor'];
            $gestorNombre = (string) $destino['gestor_nombre'];
            $idSla = (int) $destino['id_sla'];
            $slaNombre = (string) $destino['sla_nombre'];
            $stmt = $conn->prepare(
                "INSERT INTO ticket_etapas
                    (
                        id_ticket_etapa_padre,
                        id_ticket,
                        id_proceso_etapa,
                        nivel,
                        orden,
                        id_catalogo,
                        catalogo_nombre,
                        id_servicio,
                        servicio_nombre,
                        id_gestor,
                        gestor_nombre,
                        id_sla,
                        sla_nombre,
                        sla_tiempo,
                        sla_unidad,
                        sla_minutos_total,
                        estado,
                        fecha_activacion,
                        fecha_vencimiento,
                        fecha_ultima_reanudacion,
                        motivo_derivacion,
                        creado_por
                    )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                         'pendiente', ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'iiiiiisisisisisissssi',
                $idNodoPadre,
                $idTicket,
                $idProcesoEtapa,
                $nivel,
                $orden,
                $idCatalogo,
                $catalogoNombre,
                $idServicio,
                $servicioNombre,
                $idGestor,
                $gestorNombre,
                $idSla,
                $slaNombre,
                $slaTiempo,
                $slaUnidad,
                $slaMinutos,
                $ahora,
                $vencimiento,
                $ahora,
                $motivo,
                $idGestorCreador
            );
            $stmt->execute();
            $idHijo = (int) $conn->insert_id;
            $stmt->close();
            $codigoHijo = flujoCodigoCaso($conn, $idTicket, $idHijo);

            flujoCopiarChecklistPlantilla($conn, $idHijo, $idProcesoEtapa);
            flujoRegistrarHistorial(
                $conn,
                $idTicket,
                $idGestorCreador,
                'Ticket abierto por derivación',
                'Se abrió el Ticket '
                    . $codigoHijo
                    . ' desde '
                    . $origenDerivacion
                    . '. Destino: '
                    . $catalogoNombre
                    . ' / '
                    . $servicioNombre
                    . '. Motivo: '
                    . $motivo,
                $idHijo
            );
            flujoNotificar(
                $conn,
                $idGestor,
                $idTicket,
                $idHijo,
                'Nuevo ticket derivado asignado',
                "Se derivó a su área el Ticket {$codigoHijo} del Caso {$idTicket}."
            );
            $idCreadorTicket = (int) ($padre['id_creador_ticket'] ?? 0);

            if (
                $idCreadorTicket > 0
                && !in_array(
                    $idCreadorTicket,
                    [$idGestorCreador, $idGestor],
                    true
                )
            ) {
                flujoNotificar(
                    $conn,
                    $idCreadorTicket,
                    $idTicket,
                    $idHijo,
                    'Nueva dependencia en su ticket',
                    "Se creó el Ticket derivado {$codigoHijo} para {$catalogoNombre} / {$servicioNombre}."
                );
            }

            $idsHijos[] = $idHijo;
            $resumenHijos[] = $codigoHijo . ' (' . $catalogoNombre . ' / '
                . $servicioNombre . ')';
            $ultimoHijo = [
                'id_ticket_etapa' => $idHijo,
                'id_gestor' => $idGestor,
                'id_servicio' => $idServicio,
            ];
            $orden++;
        }

        if (!$ultimoHijo) {
            throw new RuntimeException('No fue posible crear las derivaciones.');
        }

        $idUltimoHijo = (int) $ultimoHijo['id_ticket_etapa'];
        $idUltimoGestor = (int) $ultimoHijo['id_gestor'];
        $idUltimoServicio = (int) $ultimoHijo['id_servicio'];
        $stmt = $conn->prepare(
            "UPDATE tickets
             SET id_etapa_actual = ?,
                 id_tecnico = ?,
                 id_servicio = ?,
                 estado = 'en_proceso',
                 estado_flujo = 'en_proceso'
             WHERE id_ticket = ?"
        );
        $stmt->bind_param(
            'iiii',
            $idUltimoHijo,
            $idUltimoGestor,
            $idUltimoServicio,
            $idTicket
        );
        $stmt->execute();
        $stmt->close();

        flujoRegistrarHistorial(
            $conn,
            $idTicket,
            $idGestorCreador,
            count($idsHijos) === 1
                ? 'Derivación creada'
                : 'Derivación múltiple creada',
            'El caso '
                . $codigoPadre
                . ($padreYaEstabaPausado
                    ? ' mantuvo su SLA pausado y creó '
                    : ' pausó su SLA y creó ')
                . count($idsHijos)
                . ' caso(s) hijo(s): '
                . implode(', ', $resumenHijos)
                . '.',
            $idNodoPadre
        );

        $conn->commit();

        return $idsHijos;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function flujoCrearCasoHijo(
    mysqli $conn,
    int $idTicket,
    int $idNodoPadre,
    int $idServicioDestino,
    int $idGestorCreador,
    int $rol,
    string $motivo
): int {
    $ids = flujoCrearCasosHijos(
        $conn,
        $idTicket,
        $idNodoPadre,
        [[
            'id_servicio_destino' => $idServicioDestino,
            'motivo_derivacion' => $motivo,
        ]],
        $idGestorCreador,
        $rol
    );

    return $ids[0];
}

/**
 * @return array<string, mixed>|null
 */
function flujoObtenerTicket(mysqli $conn, int $idTicket): ?array
{
    $idPaisOperacion = paisExigirContexto();
    $stmt = $conn->prepare(
        "SELECT
            t.*,
            p.nombre AS proceso_nombre,
            u.nombre AS creador_nombre,
            u.nombre AS solicitante_nombre,
            u.email AS solicitante_email
         FROM tickets AS t
         INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
         LEFT JOIN usuarios AS u ON u.id_usuario = t.id_usuario
         WHERE t.id_ticket = ?
           AND t.id_pais_operacion = ?
           AND t.id_proceso IS NOT NULL
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idTicket, $idPaisOperacion);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoObtenerEtapasTicket(mysqli $conn, int $idTicket): array
{
    $idPaisOperacion = paisExigirContexto();
    /* Reaplica el calendario vigente, incluidos los festivos y periodos
       administrativos que hayan sido creados, modificados o inhabilitados. */
    flujoRecalcularVencimientosActivos($conn, $idTicket);

    $columnasCalificacion = flujoModuloCalificacionDetalladaInstalado($conn)
        ? "cal.calificacion_area, cal.calificacion_tiempo,"
        : "cal.calificacion AS calificacion_area,
           cal.calificacion AS calificacion_tiempo,";
    $columnaTipoCalificacion = flujoModuloAprobacionCasosInstalado($conn)
        ? "cal.tipo_calificacion,"
        : "'historica' AS tipo_calificacion,";

    $stmt = $conn->prepare(
        "SELECT
            te.*,
            COALESCE(creador_caso.nombre, creador_ticket.nombre, 'Usuario eliminado') AS creador_caso_nombre,
            COALESCE(marcador_listo.nombre, 'Sin marcar') AS marcador_listo_nombre,
            COALESCE(cerrador.nombre, 'Sin cerrar') AS cerrador_caso_nombre,
            COALESCE(evaluador.nombre, 'Sin calificar') AS evaluador_nombre,
            (SELECT COUNT(*)
             FROM ticket_etapas AS h
             WHERE h.id_ticket_etapa_padre = te.id_ticket_etapa) AS total_hijos,
            (SELECT COUNT(*)
             FROM ticket_etapas AS h
             WHERE h.id_ticket_etapa_padre = te.id_ticket_etapa
               AND h.estado NOT IN ('completada', 'cancelada')) AS hijos_pendientes,
            cal.id_calificacion,
            cal.calificacion,
            {$columnasCalificacion}
            {$columnaTipoCalificacion}
            cal.comentario AS comentario_calificacion
         FROM ticket_etapas AS te
         INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
         LEFT JOIN usuarios AS creador_ticket
            ON creador_ticket.id_usuario = t.id_usuario
         LEFT JOIN usuarios AS creador_caso
            ON creador_caso.id_usuario = COALESCE(NULLIF(te.creado_por, 0), t.id_usuario)
         LEFT JOIN usuarios AS marcador_listo
            ON marcador_listo.id_usuario = te.marcado_listo_por
         LEFT JOIN usuarios AS cerrador
            ON cerrador.id_usuario = te.completado_por
         LEFT JOIN solicitud_calificaciones AS cal
            ON cal.id_ticket_etapa = te.id_ticket_etapa
         LEFT JOIN usuarios AS evaluador
            ON evaluador.id_usuario = cal.id_solicitante
         WHERE te.id_ticket = ?
           AND t.id_pais_operacion = ?
         ORDER BY te.orden, te.id_ticket_etapa"
    );
    $stmt->bind_param('ii', $idTicket, $idPaisOperacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $filas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $consumidos = flujoMinutosConsumidosNodo(
            $conn,
            $fila,
            null,
            true
        );
        $total = (int) ($fila['sla_minutos_total'] ?? 0);
        $fila['sla_minutos_consumidos_actuales'] = $consumidos;
        $fila['sla_minutos_restantes'] = max(0, $total - $consumidos);
        $fila['minutos_indicador'] = $fila['minutos_hasta_listo']
            ?? $fila['minutos_atencion'];
        $fila['resultado_sla_indicador'] = $fila['resultado_sla_listo']
            ?? $fila['resultado_sla'];
        $fila['estado_sla_actual'] = match ((string) $fila['estado']) {
            'completada' => (string) $fila['resultado_sla'],
            'pausada' => 'pausado',
            'cancelada', 'bloqueada' => 'sin_iniciar',
            default => $total > 0 && $consumidos > $total
                ? 'vencido'
                : 'en_tiempo',
        };
        $filas[] = $fila;
    }

    $stmt->close();

    return $filas;
}

function flujoPuedeVerTicket(
    mysqli $conn,
    int $idTicket,
    int $idUsuario,
    int $rol
): bool {
    if ($rol === 1) {
        return flujoObtenerTicket($conn, $idTicket) !== null;
    }

    if ($rol === 2) {
        $idPaisOperacion = paisExigirContexto();
        $stmt = $conn->prepare(
            "SELECT 1
             FROM tickets AS t
             LEFT JOIN ticket_etapas AS te
                ON te.id_ticket = t.id_ticket
               AND te.id_gestor = ?
             WHERE t.id_ticket = ?
               AND t.id_pais_operacion = ?
               AND t.id_proceso IS NOT NULL
               AND te.id_ticket_etapa IS NOT NULL
             LIMIT 1"
        );
        $stmt->bind_param('iii', $idUsuario, $idTicket, $idPaisOperacion);
    } elseif ($rol === 3) {
        $idPaisOperacion = paisExigirContexto();
        $stmt = $conn->prepare(
            "SELECT 1
             FROM tickets AS t
             WHERE t.id_ticket = ?
               AND t.id_usuario = ?
               AND t.id_pais_operacion = ?
               AND t.id_proceso IS NOT NULL
             LIMIT 1"
        );
        $stmt->bind_param('iii', $idTicket, $idUsuario, $idPaisOperacion);
    } else {
        return false;
    }

    $stmt->execute();
    $stmt->store_result();
    $puede = $stmt->num_rows > 0;
    $stmt->close();

    return $puede;
}

function flujoPuedeEscribirChat(
    mysqli $conn,
    array $ticket,
    int $idUsuario,
    int $rol
): bool {
    if (in_array((string) $ticket['estado'], ['cerrada', 'cancelada'], true)) {
        return false;
    }

    /* Al terminar la última etapa el chat queda en modo lectura. */
    if ((int) ($ticket['id_etapa_actual'] ?? 0) < 1) {
        return false;
    }

    if ($rol === 1) {
        return true;
    }

    if ($rol !== 2 || (int) $ticket['id_etapa_actual'] < 1) {
        return false;
    }

    $idEtapaActual = (int) $ticket['id_etapa_actual'];
    $stmt = $conn->prepare(
        "SELECT 1
         FROM ticket_etapas
         WHERE id_ticket_etapa = ?
           AND id_ticket = ?
           AND (id_gestor = ? OR ? = ?)
           AND estado IN ('pendiente', 'en_proceso', 'en_espera_solicitante')
         LIMIT 1"
    );
    $idTicket = (int) $ticket['id_ticket'];
    $idCreador = (int) $ticket['id_usuario'];
    $stmt->bind_param(
        'iiiii',
        $idEtapaActual,
        $idTicket,
        $idUsuario,
        $idCreador,
        $idUsuario
    );
    $stmt->execute();
    $stmt->store_result();
    $puede = $stmt->num_rows > 0;
    $stmt->close();

    return $puede;
}

function flujoPuedeEscribirNodo(
    mysqli $conn,
    array $ticket,
    int $idTicketEtapa,
    int $idUsuario,
    int $rol
): bool {
    if (in_array((string) $ticket['estado'], ['cerrada', 'cancelada'], true)) {
        return false;
    }

    $nodo = flujoObtenerNodo(
        $conn,
        (int) $ticket['id_ticket'],
        $idTicketEtapa
    );

    if (!$nodo) {
        return false;
    }

    if (!in_array($rol, [2, 3], true)) {
        /* Administración conserva auditoría de lectura, pero no interviene en
           conversaciones operativas ni internas. */
        return false;
    }

    if (!in_array(
        (string) $nodo['estado'],
        ['pendiente', 'en_proceso', 'en_espera_solicitante', 'pausada'],
        true
    )) {
        return false;
    }

    if (
        $rol === 3
        && (
            (int) ($nodo['id_ticket_etapa_padre'] ?? 0) > 0
            || (int) ($ticket['id_usuario'] ?? 0) !== $idUsuario
        )
    ) {
        return false;
    }

    return flujoPuedeVerConversacionNodo(
        $conn,
        (int) $ticket['id_ticket'],
        $idTicketEtapa,
        $idUsuario,
        $rol
    );
}

/**
 * @return array<string, mixed>|null
 */
function flujoEtapaActual(mysqli $conn, int $idTicket): ?array
{
    $idPaisOperacion = paisExigirContexto();
    $stmt = $conn->prepare(
        "SELECT te.*
         FROM tickets AS t
         INNER JOIN ticket_etapas AS te
            ON te.id_ticket_etapa = t.id_etapa_actual
         WHERE t.id_ticket = ?
           AND t.id_pais_operacion = ?
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idTicket, $idPaisOperacion);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

/**
 * Selecciona el nodo que se mostrará en el panel lateral. La trazabilidad
 * completa sigue visible para todos los usuarios autorizados.
 *
 * @return array<string, mixed>|null
 */
function flujoSeleccionarNodoTicket(
    mysqli $conn,
    int $idTicket,
    int $idUsuario,
    int $rol,
    int $idPreferido = 0
): ?array {
    if ($idPreferido > 0) {
        $nodo = flujoObtenerNodo($conn, $idTicket, $idPreferido);

        if ($nodo) {
            return $nodo;
        }
    }

    if ($rol === 2) {
        $stmt = $conn->prepare(
            "SELECT te.*
             FROM ticket_etapas AS te
             WHERE te.id_ticket = ?
               AND te.id_gestor = ?
             ORDER BY
                (te.id_gestor = ?) DESC,
                FIELD(
                    te.estado,
                    'listo_cierre',
                    'en_proceso',
                    'pendiente',
                    'en_espera_solicitante',
                    'pausada',
                    'completada',
                    'cancelada',
                    'bloqueada'
                ),
                te.orden DESC
             LIMIT 1"
        );
        $stmt->bind_param(
            'iii',
            $idTicket,
            $idUsuario,
            $idUsuario
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT te.*
             FROM ticket_etapas AS te
             LEFT JOIN tickets AS t
                ON t.id_ticket = te.id_ticket
             WHERE te.id_ticket = ?
             ORDER BY
                (te.id_ticket_etapa = t.id_etapa_actual) DESC,
                FIELD(
                    te.estado,
                    'listo_cierre',
                    'en_proceso',
                    'pendiente',
                    'en_espera_solicitante',
                    'pausada',
                    'completada',
                    'cancelada',
                    'bloqueada'
                ),
                te.orden DESC
             LIMIT 1"
        );
        $stmt->bind_param('i', $idTicket);
    }

    $stmt->execute();
    $nodo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $nodo ?: null;
}

function flujoGuardarComunicacion(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idUsuario,
    string $mensaje
): void {
    $stmt = $conn->prepare(
        "SELECT id_ticket_etapa_padre
         FROM ticket_etapas
         WHERE id_ticket = ?
           AND id_ticket_etapa = ?
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idTicket, $idTicketEtapa);
    $stmt->execute();
    $nodo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$nodo) {
        throw new RuntimeException('La conversación indicada no existe.');
    }

    $tipo = $nodo['id_ticket_etapa_padre'] === null
        ? 'publica'
        : 'interna';
    $stmt = $conn->prepare(
        "INSERT INTO solicitud_comunicaciones
            (id_ticket, id_ticket_etapa, id_emisor, tipo, mensaje)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'iiiss',
        $idTicket,
        $idTicketEtapa,
        $idUsuario,
        $tipo,
        $mensaje
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoComunicacionesNodo(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa
): array {
    $stmt = $conn->prepare(
        "SELECT
            sc.*,
            u.nombre AS emisor,
            u.id_rol AS emisor_rol
         FROM solicitud_comunicaciones AS sc
         INNER JOIN ticket_etapas AS te
            ON te.id_ticket_etapa = sc.id_ticket_etapa
           AND te.id_ticket = sc.id_ticket
         LEFT JOIN usuarios AS u ON u.id_usuario = sc.id_emisor
         WHERE sc.id_ticket = ?
           AND sc.id_ticket_etapa = ?
           AND (
                te.id_ticket_etapa_padre IS NOT NULL
                OR sc.tipo = 'publica'
           )
         ORDER BY sc.creado_en, sc.id_comunicacion"
    );
    $stmt->bind_param('ii', $idTicket, $idTicketEtapa);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $filas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $filas[] = $fila;
    }

    $stmt->close();

    return $filas;
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoAdjuntosNodo(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa
): array {
    $stmt = $conn->prepare(
        "SELECT
            a.*,
            u.nombre AS usuario
         FROM solicitud_adjuntos AS a
         LEFT JOIN usuarios AS u ON u.id_usuario = a.id_usuario
         WHERE a.id_ticket = ?
           AND a.id_ticket_etapa = ?
         ORDER BY a.creado_en, a.id_adjunto"
    );
    $stmt->bind_param('ii', $idTicket, $idTicketEtapa);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $filas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $filas[] = $fila;
    }

    $stmt->close();

    return $filas;
}

/**
 * Une mensajes y archivos en una sola línea de tiempo para que el chat pueda
 * presentar los documentos en el punto en que fueron enviados.
 *
 * @param array<int, array<string, mixed>> $comunicaciones
 * @param array<int, array<string, mixed>> $adjuntos
 * @return array<int, array<string, mixed>>
 */
function flujoLineaTiempoConversacion(
    array $comunicaciones,
    array $adjuntos
): array {
    $eventos = [];

    foreach ($comunicaciones as $comunicacion) {
        $comunicacion['tipo_evento'] = 'mensaje';
        $comunicacion['id_evento'] = (int) (
            $comunicacion['id_comunicacion'] ?? 0
        );
        $comunicacion['id_autor'] = (int) (
            $comunicacion['id_emisor'] ?? 0
        );
        $comunicacion['autor'] = (string) (
            $comunicacion['emisor'] ?? 'Usuario'
        );
        $eventos[] = $comunicacion;
    }

    foreach ($adjuntos as $adjunto) {
        $adjunto['tipo_evento'] = 'adjunto';
        $adjunto['id_evento'] = (int) ($adjunto['id_adjunto'] ?? 0);
        $adjunto['id_autor'] = (int) ($adjunto['id_usuario'] ?? 0);
        $adjunto['autor'] = (string) ($adjunto['usuario'] ?? 'Usuario');
        $eventos[] = $adjunto;
    }

    usort(
        $eventos,
        static function (array $primero, array $segundo): int {
            $fechaPrimero = (string) ($primero['creado_en'] ?? '');
            $fechaSegundo = (string) ($segundo['creado_en'] ?? '');
            $comparacionFecha = strcmp($fechaPrimero, $fechaSegundo);

            if ($comparacionFecha !== 0) {
                return $comparacionFecha;
            }

            $ordenPrimero = (string) ($primero['tipo_evento'] ?? '')
                === 'mensaje' ? 0 : 1;
            $ordenSegundo = (string) ($segundo['tipo_evento'] ?? '')
                === 'mensaje' ? 0 : 1;

            if ($ordenPrimero !== $ordenSegundo) {
                return $ordenPrimero <=> $ordenSegundo;
            }

            return (int) ($primero['id_evento'] ?? 0)
                <=> (int) ($segundo['id_evento'] ?? 0);
        }
    );

    return $eventos;
}

function flujoFormatoTamanoArchivo(int $bytes): string
{
    if ($bytes < 1024) {
        return max(0, $bytes) . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }

    return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
}

function flujoNotificarConversacion(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idEmisor,
    bool $incluyeAdjuntos = false
): void {
    $participantes = flujoParticipantesConversacion(
        $conn,
        $idTicket,
        $idTicketEtapa
    );

    if (!$participantes) {
        return;
    }

    $nodoMensaje = flujoObtenerNodo($conn, $idTicket, $idTicketEtapa);
    $esDerivacion = (int) (
        $nodoMensaje['id_ticket_etapa_padre'] ?? 0
    ) > 0;
    $codigo = flujoCodigoCaso($conn, $idTicket, $idTicketEtapa);
    $etiqueta = $esDerivacion
        ? 'Ticket ' . $codigo
        : 'Caso ' . $idTicket . ' · etapa '
            . flujoNumeroEtapaNodo($conn, $idTicket, $idTicketEtapa);
    $destinatarios = array_unique(array_values($participantes));

    foreach ($destinatarios as $idDestinatario) {
        $idDestinatario = (int) $idDestinatario;

        if ($idDestinatario < 1 || $idDestinatario === $idEmisor) {
            continue;
        }

        flujoNotificar(
            $conn,
            $idDestinatario,
            $idTicket,
            $idTicketEtapa,
            'Nuevo mensaje en ' . $etiqueta,
            $incluyeAdjuntos
                ? "Recibió un mensaje o documento en el chat privado de {$etiqueta}."
                : "Recibió un mensaje en el chat privado de {$etiqueta}."
        );
    }
}

/**
 * @return array<int, string> rutas físicas creadas para poder revertirlas.
 */
function flujoGuardarAdjuntos(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idUsuario,
    array $entrada
): array {
    if (!isset($entrada['name'])) {
        return [];
    }

    $nombres = is_array($entrada['name'])
        ? $entrada['name']
        : [$entrada['name']];
    $temporales = is_array($entrada['tmp_name'])
        ? $entrada['tmp_name']
        : [$entrada['tmp_name']];
    $errores = is_array($entrada['error'])
        ? $entrada['error']
        : [$entrada['error']];
    $tamanos = is_array($entrada['size'])
        ? $entrada['size']
        : [$entrada['size']];

    if (count($nombres) > 5) {
        throw new RuntimeException('Puede cargar máximo cinco archivos por mensaje.');
    }

    $permitidos = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/msword' => 'doc',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];
    $directorio = seguridadDirectorioPrivado('solicitudes');

    if (
        !is_dir($directorio)
        && !mkdir($directorio, 0750, true)
        && !is_dir($directorio)
    ) {
        throw new RuntimeException('No se pudo preparar la carpeta de archivos.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $creados = [];

    foreach ($nombres as $indice => $nombreEntrada) {
        $error = (int) ($errores[$indice] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Uno de los archivos no pudo cargarse.');
        }

        $tamano = (int) ($tamanos[$indice] ?? 0);

        if ($tamano < 1 || $tamano > 5 * 1024 * 1024) {
            throw new RuntimeException('Cada archivo debe pesar máximo 5 MB.');
        }

        $temporal = (string) ($temporales[$indice] ?? '');
        $mime = (string) $finfo->file($temporal);

        if (!isset($permitidos[$mime])) {
            throw new RuntimeException('Uno de los formatos adjuntos no está permitido.');
        }

        $nombreOriginal = trim(basename((string) $nombreEntrada));
        $nombreOriginal = $nombreOriginal !== '' ? $nombreOriginal : 'adjunto';
        $nombreGuardado = 'flujo_' . $idTicket . '_'
            . bin2hex(random_bytes(12))
            . '.' . $permitidos[$mime];
        $rutaRelativa = 'private/solicitudes/' . $nombreGuardado;
        $rutaFisica = $directorio . DIRECTORY_SEPARATOR . $nombreGuardado;

        if (!move_uploaded_file($temporal, $rutaFisica)) {
            throw new RuntimeException('No fue posible guardar un archivo adjunto.');
        }

        @chmod($rutaFisica, 0640);

        $creados[] = $rutaFisica;
        $stmt = $conn->prepare(
            "INSERT INTO solicitud_adjuntos
                (
                    id_ticket,
                    id_ticket_etapa,
                    id_usuario,
                    nombre_original,
                    nombre_guardado,
                    ruta,
                    tipo_mime,
                    tamano
                )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iiissssi',
            $idTicket,
            $idTicketEtapa,
            $idUsuario,
            $nombreOriginal,
            $nombreGuardado,
            $rutaRelativa,
            $mime,
            $tamano
        );
        $stmt->execute();
        $stmt->close();
    }

    return $creados;
}

function flujoEnviarConversacion(
    mysqli $conn,
    array $ticket,
    int $idTicketEtapa,
    int $idUsuario,
    int $rol,
    string $mensaje,
    array $entradaAdjuntos = []
): void {
    $idTicket = (int) ($ticket['id_ticket'] ?? 0);
    $mensaje = trim($mensaje);
    $errores = isset($entradaAdjuntos['error'])
        ? (is_array($entradaAdjuntos['error'])
            ? $entradaAdjuntos['error']
            : [$entradaAdjuntos['error']])
        : [];
    $hayArchivos = count(array_filter(
        $errores,
        static fn (mixed $error): bool =>
            (int) $error !== UPLOAD_ERR_NO_FILE
    )) > 0;

    if (
        $idTicket < 1
        || $idTicketEtapa < 1
        || !flujoPuedeEscribirNodo(
            $conn,
            $ticket,
            $idTicketEtapa,
            $idUsuario,
            $rol
        )
    ) {
        throw new RuntimeException(
            'La conversación no está habilitada para este usuario o etapa.'
        );
    }

    if ($mensaje === '' && !$hayArchivos) {
        throw new RuntimeException('Escriba un mensaje o seleccione un archivo.');
    }

    if (strlen($mensaje) > 10000) {
        throw new RuntimeException('El mensaje supera los 10.000 caracteres.');
    }

    $nodo = flujoObtenerNodo($conn, $idTicket, $idTicketEtapa);
    $esDerivacionInterna = (int) ($nodo['id_ticket_etapa_padre'] ?? 0) > 0;
    $rutas = [];
    $conn->begin_transaction();

    try {
        if ($mensaje !== '') {
            flujoGuardarComunicacion(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                $mensaje
            );
        }

        if ($hayArchivos) {
            $rutas = flujoGuardarAdjuntos(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                $entradaAdjuntos
            );
        }

        flujoNotificarConversacion(
            $conn,
            $idTicket,
            $idTicketEtapa,
            $idUsuario,
            count($rutas) > 0
        );
        flujoRegistrarHistorial(
            $conn,
            $idTicket,
            $idUsuario,
            $esDerivacionInterna
                ? 'Comunicación interna registrada'
                : 'Comunicación con el solicitante registrada',
            'Se agregó un mensaje o archivo al '
                . ($esDerivacionInterna
                    ? 'chat interno del '
                    : 'chat de ')
                . flujoEtiquetaNodo($conn, $idTicket, $idTicketEtapa)
                . '.',
            $idTicketEtapa
        );
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();

        foreach ($rutas as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }

        throw $e;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoChecklistEtapa(mysqli $conn, int $idTicketEtapa): array
{
    flujoSincronizarChecklistEtapaAbierta($conn, $idTicketEtapa);

    $stmt = $conn->prepare(
        "SELECT *
         FROM ticket_etapa_checklist
         WHERE id_ticket_etapa = ?
         ORDER BY orden, id_ticket_checklist"
    );
    $stmt->bind_param('i', $idTicketEtapa);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $filas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $filas[] = $fila;
    }

    $stmt->close();

    return $filas;
}

function flujoGuardarChecklist(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idUsuario,
    int $rol,
    array $seleccionados,
    array $observaciones
): void {
    $etapa = flujoObtenerNodo($conn, $idTicket, $idTicketEtapa);

    if (
        !$etapa
        || !in_array(
            (string) $etapa['estado'],
            ['pendiente', 'en_proceso', 'en_espera_solicitante'],
            true
        )
        || ($rol !== 1 && (int) $etapa['id_gestor'] !== $idUsuario)
    ) {
        throw new RuntimeException('La etapa no está disponible para este gestor.');
    }

    $items = flujoChecklistEtapa($conn, $idTicketEtapa);
    $stmt = $conn->prepare(
        "UPDATE ticket_etapa_checklist
         SET completado = ?,
             observacion = NULLIF(?, ''),
             completado_por = CASE WHEN ? = 1 THEN ? ELSE NULL END,
             completado_en = CASE WHEN ? = 1 THEN NOW() ELSE NULL END
         WHERE id_ticket_checklist = ?
           AND id_ticket_etapa = ?"
    );

    foreach ($items as $item) {
        $idItem = (int) $item['id_ticket_checklist'];
        $completado = isset($seleccionados[$idItem]) ? 1 : 0;
        $observacion = trim((string) ($observaciones[$idItem] ?? ''));
        $stmt->bind_param(
            'isiiiii',
            $completado,
            $observacion,
            $completado,
            $idUsuario,
            $completado,
            $idItem,
            $idTicketEtapa
        );
        $stmt->execute();
    }

    $stmt->close();
    $stmt = $conn->prepare(
        "UPDATE ticket_etapas
         SET estado = 'en_proceso'
         WHERE id_ticket_etapa = ?
           AND estado = 'pendiente'"
    );
    $stmt->bind_param('i', $idTicketEtapa);
    $stmt->execute();
    $stmt->close();
    flujoRegistrarHistorial(
        $conn,
        $idTicket,
        $idUsuario,
        'Checklist actualizado',
        'El gestor actualizó el checklist de la etapa activa.',
        $idTicketEtapa
    );
}

function flujoMarcarEtapaLista(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idUsuario,
    int $rol,
    int $idSolucion,
    string $comentarioCierre,
    bool $solicitaCierreDefinitivo = false
): void {
    $comentarioCierre = trim($comentarioCierre);

    if ($idSolucion < 1) {
        throw new RuntimeException(
            'Debe seleccionar la solución aplicada antes de marcar el caso como listo.'
        );
    }

    if ($comentarioCierre === '' || strlen($comentarioCierre) > 2000) {
        throw new RuntimeException(
            'Debe registrar obligatoriamente qué hizo para solucionar el caso.'
        );
    }

    if ($rol !== 2) {
        throw new RuntimeException(
            'Solo el gestor asignado puede marcar el caso como listo.'
        );
    }

    if (!flujoModuloAprobacionCasosInstalado($conn)) {
        throw new RuntimeException(
            'El administrador debe instalar la migración de aprobación y reapertura de casos.'
        );
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT
                te.*,
                t.id_usuario,
                COALESCE(NULLIF(te.creado_por, 0), t.id_usuario) AS id_creador_caso
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE te.id_ticket_etapa = ?
               AND te.id_ticket = ?
             FOR UPDATE"
        );
        $stmt->bind_param('ii', $idTicketEtapa, $idTicket);
        $stmt->execute();
        $etapa = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$etapa
            || !in_array(
                (string) $etapa['estado'],
                ['pendiente', 'en_proceso', 'en_espera_solicitante'],
                true
            )
            || (int) $etapa['id_gestor'] !== $idUsuario
        ) {
            throw new RuntimeException(
                'El caso ya no está disponible para marcarlo como listo.'
            );
        }

        if (
            $solicitaCierreDefinitivo
            && (int) ($etapa['id_ticket_etapa_padre'] ?? 0) > 0
        ) {
            throw new RuntimeException(
                'El cierre definitivo por primer contacto solo está disponible para las etapas del flujo, no para derivaciones.'
            );
        }

        if (!flujoTablaExiste($conn, 'soluciones_servicio')) {
            throw new RuntimeException(
                'El administrador debe instalar el módulo de soluciones por servicio.'
            );
        }

        $idServicioEtapa = (int) ($etapa['id_servicio'] ?? 0);
        $stmt = $conn->prepare(
            "SELECT id_solucion, nombre
             FROM soluciones_servicio
             WHERE id_solucion = ?
               AND id_servicio = ?
               AND estado = 'activo'
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->bind_param('ii', $idSolucion, $idServicioEtapa);
        $stmt->execute();
        $solucion = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$solucion) {
            throw new RuntimeException(
                'La solución seleccionada no está activa o no corresponde al servicio del caso.'
            );
        }

        $nombreSolucion = trim((string) $solucion['nombre']);

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS pendientes
             FROM ticket_etapas
             WHERE id_ticket_etapa_padre = ?
               AND estado NOT IN ('completada', 'cancelada')"
        );
        $stmt->bind_param('i', $idTicketEtapa);
        $stmt->execute();
        $hijosPendientes = (int) $stmt->get_result()->fetch_assoc()['pendientes'];
        $stmt->close();

        if ($hijosPendientes > 0) {
            throw new RuntimeException(
                'No puede marcar este caso como listo mientras tenga casos hijos pendientes.'
            );
        }

        flujoCopiarChecklistPlantilla(
            $conn,
            $idTicketEtapa,
            (int) ($etapa['id_proceso_etapa'] ?? 0)
        );

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS pendientes
             FROM ticket_etapa_checklist
             WHERE id_ticket_etapa = ?
               AND obligatorio = 1
               AND completado = 0"
        );
        $stmt->bind_param('i', $idTicketEtapa);
        $stmt->execute();
        $pendientes = (int) $stmt->get_result()->fetch_assoc()['pendientes'];
        $stmt->close();

        if ($pendientes > 0) {
            throw new RuntimeException(
                'Debe completar todos los elementos obligatorios del checklist.'
            );
        }

        $ahora = flujoAhora();
        $minutos = flujoMinutosConsumidosNodo($conn, $etapa, $ahora);
        $slaTotal = (int) ($etapa['sla_minutos_total'] ?? 0);

        if ($slaTotal < 1) {
            $slaTotal = flujoSlaMinutosTotales(
                (int) $etapa['sla_tiempo'],
                (string) $etapa['sla_unidad']
            );
        }

        $resultadoSla = $slaTotal < 1 || $minutos <= $slaTotal
            ? 'dentro_sla'
            : 'fuera_sla';

        $stmt = $conn->prepare(
            "UPDATE ticket_etapas
             SET estado = 'listo_cierre',
                 solicita_cierre_definitivo = ?,
                 fecha_marcado_listo = ?,
                 minutos_hasta_listo = ?,
                 resultado_sla_listo = ?,
                 marcado_listo_por = ?,
                 id_solucion = ?,
                 solucion_nombre = ?,
                 comentario_cierre = NULLIF(?, ''),
                 resultado_sla = 'sin_iniciar',
                 minutos_atencion = NULL,
                 fecha_finalizacion = NULL,
                 completado_por = NULL
             WHERE id_ticket_etapa = ?
               AND estado IN ('pendiente', 'en_proceso', 'en_espera_solicitante')"
        );
        $stmt->bind_param(
            'isisiissi',
            $solicitaCierreDefinitivo,
            $ahora,
            $minutos,
            $resultadoSla,
            $idUsuario,
            $idSolucion,
            $nombreSolucion,
            $comentarioCierre,
            $idTicketEtapa
        );
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException(
                'El estado del caso cambió antes de marcarlo como listo.'
            );
        }

        $stmt->close();

        $etiquetaNodo = flujoEtiquetaNodo(
            $conn,
            $idTicket,
            $idTicketEtapa
        );
        $etiquetaNodo = flujoEtiquetaNodo(
            $conn,
            $idTicket,
            $idTicketEtapa
        );
        $idCreadorCaso = (int) ($etapa['id_creador_caso'] ?? 0);
        flujoRegistrarHistorial(
            $conn,
            $idTicket,
            $idUsuario,
            $solicitaCierreDefinitivo
                ? 'Cierre definitivo solicitado por primer contacto'
                : 'Atención marcada como lista',
            'El gestor asignado marcó '
                . $etiquetaNodo
                . ($solicitaCierreDefinitivo
                    ? ' como resuelto en primer contacto y solicitó el cierre definitivo del ticket. Las etapas siguientes no se activarán si el solicitante aprueba. El indicador SLA quedó cortado en '
                    : ' como listo para revisión. El indicador SLA quedó cortado en ')
                . $minutos
                . ' minuto(s) hábil(es), con resultado '
                . ($resultadoSla === 'dentro_sla'
                    ? 'dentro del SLA'
                    : 'fuera del SLA')
                . '. El vencimiento visible se conserva en '
                . (string) ($etapa['fecha_vencimiento'] ?? 'sin fecha')
                . '. Solución: '
                . $nombreSolucion
                . '. Observación: '
                . $comentarioCierre
                . '.',
            $idTicketEtapa
        );
        flujoNotificar(
            $conn,
            $idCreadorCaso,
            $idTicket,
            $idTicketEtapa,
            $solicitaCierreDefinitivo
                ? 'Solicitud de cierre definitivo'
                : 'Atención lista para revisión',
            $solicitaCierreDefinitivo
                ? "El gestor resolvió {$etiquetaNodo} en primer contacto y solicita cerrar definitivamente el ticket sin continuar el flujo. Revise la solución y apruebe o reabra."
                : "El gestor asignado marcó {$etiquetaNodo} como listo. Revise la solución y cierre o reabra la atención."
        );

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function flujoReabrirDerivacion(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idUsuario,
    int $rol,
    string $motivoReapertura
): void {
    $motivoReapertura = trim($motivoReapertura);

    if (!in_array($rol, [2, 3], true) || !flujoModuloAprobacionCasosInstalado($conn)) {
        throw new RuntimeException('No está autorizado para reabrir este caso.');
    }

    if ($motivoReapertura === '' || strlen($motivoReapertura) > 1000) {
        throw new RuntimeException(
            'Debe explicar por qué la solución informada no permite cerrar el caso.'
        );
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT
                te.*,
                t.id_usuario,
                COALESCE(NULLIF(te.creado_por, 0), t.id_usuario) AS id_creador_caso
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE te.id_ticket_etapa = ?
               AND te.id_ticket = ?
             FOR UPDATE"
        );
        $stmt->bind_param('ii', $idTicketEtapa, $idTicket);
        $stmt->execute();
        $etapa = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$etapa
            || (string) $etapa['estado'] !== 'listo_cierre'
            || (int) $etapa['id_creador_caso'] !== $idUsuario
            || (
                $rol === 3
                && (
                    (int) $etapa['id_usuario'] !== $idUsuario
                    || (int) ($etapa['id_ticket_etapa_padre'] ?? 0) > 0
                )
            )
        ) {
            throw new RuntimeException(
                'Solo el creador del caso puede reabrirlo mientras espera cierre.'
            );
        }

        $ahora = flujoAhora();
        $consumidos = flujoMinutosConsumidosNodo(
            $conn,
            $etapa,
            $ahora,
            true
        );
        $total = (int) ($etapa['sla_minutos_total'] ?? 0);
        $restantes = max(0, $total - $consumidos);
        $vence = flujoCalcularVencimientoRestante(
            $conn,
            $etapa,
            $ahora,
            $restantes
        );
        $stmt = $conn->prepare(
            "UPDATE ticket_etapas
             SET estado = 'en_proceso',
                 solicita_cierre_definitivo = 0,
                 sla_minutos_consumidos = ?,
                 fecha_ultima_reanudacion = ?,
                 fecha_vencimiento = ?,
                 fecha_marcado_listo = NULL,
                 minutos_hasta_listo = NULL,
                 resultado_sla_listo = NULL,
                 marcado_listo_por = NULL,
                 fecha_ultima_reapertura = ?,
                 cantidad_reaperturas = cantidad_reaperturas + 1,
                 id_solucion = NULL,
                 solucion_nombre = NULL,
                 comentario_cierre = NULL,
                 resultado_sla = 'sin_iniciar',
                 minutos_atencion = NULL,
                 fecha_finalizacion = NULL,
                 completado_por = NULL
             WHERE id_ticket_etapa = ?
               AND estado = 'listo_cierre'"
        );
        $stmt->bind_param(
            'isssi',
            $consumidos,
            $ahora,
            $vence,
            $ahora,
            $idTicketEtapa
        );
        $stmt->execute();
        $stmt->close();

        $idGestor = (int) ($etapa['id_gestor'] ?? 0);
        $idServicio = (int) ($etapa['id_servicio'] ?? 0);
        $stmt = $conn->prepare(
            "UPDATE tickets
             SET id_etapa_actual = ?,
                 id_tecnico = ?,
                 id_servicio = ?,
                 estado = 'en_proceso',
                 estado_flujo = 'en_proceso'
             WHERE id_ticket = ?"
        );
        $stmt->bind_param(
            'iiii',
            $idTicketEtapa,
            $idGestor,
            $idServicio,
            $idTicket
        );
        $stmt->execute();
        $stmt->close();

        $codigoCaso = flujoCodigoCaso($conn, $idTicket, $idTicketEtapa);
        $fechaListoAnterior = (string) (
            $etapa['fecha_marcado_listo'] ?? 'sin fecha'
        );
        $minutosListoAnterior = (int) (
            $etapa['minutos_hasta_listo'] ?? 0
        );
        $resultadoListoAnterior = (string) (
            $etapa['resultado_sla_listo'] ?? 'sin_iniciar'
        );
        flujoRegistrarHistorial(
            $conn,
            $idTicket,
            $idUsuario,
            'Atención reabierta',
            'El creador reabrió '
                . $etiquetaNodo
                . '. Se invalidó el corte del '
                . $fechaListoAnterior
                . ' por '
                . $minutosListoAnterior
                . ' minuto(s), resultado '
                . $resultadoListoAnterior
                . '. Al reabrir acumulaba '
                . $consumidos
                . ' minuto(s) hábil(es), incluido el tiempo de espera. Nuevo vencimiento visible: '
                . (string) $vence
                . '. Motivo de reapertura: '
                . $motivoReapertura
                . '.',
            $idTicketEtapa
        );
        flujoNotificar(
            $conn,
            $idGestor,
            $idTicket,
            $idTicketEtapa,
            'Atención reabierta',
            "El creador reabrió {$etiquetaNodo}. Debe continuar la gestión y el SLA volvió a correr."
        );

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function flujoCompletarEtapa(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idUsuario,
    int $rol,
    int $calificacionArea = 0,
    int $calificacionTiempo = 0,
    string $comentarioCalificacion = ''
): void {
    if (!in_array($rol, [2, 3], true) || !flujoModuloAprobacionCasosInstalado($conn)) {
        throw new RuntimeException('No está autorizado para cerrar este caso.');
    }

    $comentarioCalificacion = trim($comentarioCalificacion);
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT
                te.*,
                t.id_usuario,
                COALESCE(NULLIF(te.creado_por, 0), t.id_usuario) AS id_creador_caso
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE te.id_ticket_etapa = ?
               AND te.id_ticket = ?
             FOR UPDATE"
        );
        $stmt->bind_param('ii', $idTicketEtapa, $idTicket);
        $stmt->execute();
        $etapa = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$etapa
            || (string) $etapa['estado'] !== 'listo_cierre'
            || (int) $etapa['id_creador_caso'] !== $idUsuario
            || (
                $rol === 3
                && (
                    (int) $etapa['id_usuario'] !== $idUsuario
                    || (int) ($etapa['id_ticket_etapa_padre'] ?? 0) > 0
                )
            )
        ) {
            throw new RuntimeException(
                'Solo el creador del caso puede cerrarlo después de que el gestor lo marque como listo.'
            );
        }

        $tipoCalificacion = flujoTipoCalificacionCaso(
            $conn,
            $idTicket,
            $etapa
        );

        if ($tipoCalificacion !== null) {
            if (
                $calificacionArea < 1
                || $calificacionArea > 5
                || $calificacionTiempo < 1
                || $calificacionTiempo > 5
            ) {
                throw new RuntimeException(
                    'Califique de 1 a 5 la gestión del área y el tiempo de respuesta.'
                );
            }

            if (strlen($comentarioCalificacion) > 1000) {
                throw new RuntimeException(
                    'La observación de la calificación supera los 1.000 caracteres.'
                );
            }

            $calificacionGeneral = (int) round(
                ($calificacionArea + $calificacionTiempo) / 2
            );
            $idGestor = (int) ($etapa['id_gestor'] ?? 0);
            $stmt = $conn->prepare(
                "INSERT INTO solicitud_calificaciones
                    (
                        id_ticket,
                        id_ticket_etapa,
                        id_solicitante,
                        id_gestor,
                        calificacion,
                        calificacion_area,
                        calificacion_tiempo,
                        tipo_calificacion,
                        comentario
                    )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''))
                 ON DUPLICATE KEY UPDATE
                    id_solicitante = VALUES(id_solicitante),
                    id_gestor = VALUES(id_gestor),
                    calificacion = VALUES(calificacion),
                    calificacion_area = VALUES(calificacion_area),
                    calificacion_tiempo = VALUES(calificacion_tiempo),
                    tipo_calificacion = VALUES(tipo_calificacion),
                    comentario = VALUES(comentario),
                    creado_en = NOW()"
            );
            $stmt->bind_param(
                'iiiiiiiss',
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                $idGestor,
                $calificacionGeneral,
                $calificacionArea,
                $calificacionTiempo,
                $tipoCalificacion,
                $comentarioCalificacion
            );
            $stmt->execute();
            $stmt->close();
        }

        $ahora = flujoAhora();
        $minutos = (int) ($etapa['minutos_hasta_listo'] ?? 0);
        $resultadoSla = (string) (
            $etapa['resultado_sla_listo'] ?? 'sin_iniciar'
        );
        $stmt = $conn->prepare(
            "UPDATE ticket_etapas
             SET estado = 'completada',
                 fecha_finalizacion = ?,
                 minutos_atencion = ?,
                 sla_minutos_consumidos = ?,
                 fecha_ultima_reanudacion = NULL,
                 resultado_sla = ?,
                 completado_por = ?
             WHERE id_ticket_etapa = ?
               AND estado = 'listo_cierre'"
        );
        $stmt->bind_param(
            'siisii',
            $ahora,
            $minutos,
            $minutos,
            $resultadoSla,
            $idUsuario,
            $idTicketEtapa
        );
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException(
                'El estado del caso cambió antes de confirmar el cierre.'
            );
        }

        $stmt->close();

        $cierreDefinitivoPrimerContacto =
            (int) ($etapa['solicita_cierre_definitivo'] ?? 0) === 1
            && (int) ($etapa['id_ticket_etapa_padre'] ?? 0) === 0;

        if ($cierreDefinitivoPrimerContacto) {
            $stmt = $conn->prepare(
                "UPDATE ticket_etapas
                 SET estado = 'cancelada',
                     fecha_finalizacion = ?,
                     resultado_sla = 'sin_iniciar'
                 WHERE id_ticket = ?
                   AND id_ticket_etapa_padre IS NULL
                   AND orden > ?
                   AND estado = 'bloqueada'"
            );
            $ordenActual = (int) $etapa['orden'];
            $stmt->bind_param('sii', $ahora, $idTicket, $ordenActual);
            $stmt->execute();
            $etapasCanceladas = $stmt->affected_rows;
            $stmt->close();

            $motivoTicket = 'Cierre definitivo aprobado por resolución en primer contacto.';
            $stmt = $conn->prepare(
                "UPDATE tickets
                 SET id_etapa_actual = NULL,
                     estado = 'cerrada',
                     estado_flujo = 'cerrado',
                     fecha_finalizacion = ?,
                     esperando_solicitante_desde = NULL,
                     cierre_tipo = 'aprobacion_por_caso',
                     motivo_cierre = ?
                 WHERE id_ticket = ?"
            );
            $stmt->bind_param('ssi', $ahora, $motivoTicket, $idTicket);
            $stmt->execute();
            $stmt->close();

            flujoRegistrarHistorial(
                $conn,
                $idTicket,
                $idUsuario,
                'Ticket cerrado por resolución en primer contacto',
                'El solicitante aprobó el cierre definitivo solicitado por el gestor. Se cancelaron '
                    . $etapasCanceladas
                    . ' etapa(s) futura(s) que no habían iniciado.',
                $idTicketEtapa
            );
            flujoNotificar(
                $conn,
                (int) ($etapa['id_gestor'] ?? 0),
                $idTicket,
                $idTicketEtapa,
                'Cierre definitivo aprobado',
                "El solicitante aprobó el cierre definitivo del ticket #{$idTicket} por resolución en primer contacto."
            );
            $conn->commit();
            return;
        }

        /* Compatibilidad: activa una antigua etapa secuencial bloqueada. */
        $orden = (int) $etapa['orden'];
        $stmt = $conn->prepare(
            "SELECT *
             FROM ticket_etapas
             WHERE id_ticket = ?
               AND orden > ?
               AND estado = 'bloqueada'
             ORDER BY orden, id_ticket_etapa
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->bind_param('ii', $idTicket, $orden);
        $stmt->execute();
        $siguiente = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $nombreSolucion = trim((string) ($etapa['solucion_nombre'] ?? ''));
        $comentarioCierre = trim((string) ($etapa['comentario_cierre'] ?? ''));
        $etiquetaNodoCierre = flujoEtiquetaNodo(
            $conn,
            $idTicket,
            $idTicketEtapa
        );

        flujoRegistrarHistorial(
            $conn,
            $idTicket,
            $idUsuario,
            'Atención cerrada',
            'Se cerró '
                . $etiquetaNodoCierre
                . '. '
                . $etapa['catalogo_nombre']
                . ' / '
                . $etapa['servicio_nombre']
                . ' finalizó su atención '
                . ($resultadoSla === 'dentro_sla'
                    ? 'dentro del SLA.'
                    : 'fuera del SLA.')
                . ' Solución: '
                . $nombreSolucion
                . '. Observación: '
                . $comentarioCierre
                . '. Calificación: gestión '
                . $calificacionArea
                . '/5 y tiempo '
                . $calificacionTiempo
                . '/5.',
            $idTicketEtapa
        );
        $idGestorAtencion = (int) ($etapa['id_gestor'] ?? 0);

        if ($idGestorAtencion !== $idUsuario) {
            flujoNotificar(
                $conn,
                $idGestorAtencion,
                $idTicket,
                $idTicketEtapa,
                'Atención aprobada y cerrada',
                'El creador aprobó y calificó '
                    . $etiquetaNodoCierre
                    . ". Gestión: {$calificacionArea}/5; tiempo: {$calificacionTiempo}/5."
            );
        }

        if ($siguiente) {
            $idSiguiente = (int) $siguiente['id_ticket_etapa'];
            $inicioSiguiente = $ahora;
            $venceSiguiente = calcularVencimientoSla(
                $conn,
                $inicioSiguiente,
                (int) $siguiente['sla_tiempo'],
                (string) $siguiente['sla_unidad']
            );
            $stmt = $conn->prepare(
                "UPDATE ticket_etapas
                 SET estado = 'pendiente',
                     fecha_activacion = ?,
                     fecha_vencimiento = ?,
                     fecha_ultima_reanudacion = ?,
                     resultado_sla = 'sin_iniciar'
                 WHERE id_ticket_etapa = ?
                   AND estado = 'bloqueada'"
            );
            $stmt->bind_param(
                'sssi',
                $inicioSiguiente,
                $venceSiguiente,
                $inicioSiguiente,
                $idSiguiente
            );
            $stmt->execute();
            $stmt->close();

            flujoCopiarChecklistPlantilla(
                $conn,
                $idSiguiente,
                (int) ($siguiente['id_proceso_etapa'] ?? 0)
            );

            $idGestor = (int) $siguiente['id_gestor'];
            $idServicio = (int) $siguiente['id_servicio'];
            $stmt = $conn->prepare(
                "UPDATE tickets
                 SET id_etapa_actual = ?,
                     id_tecnico = ?,
                     id_servicio = ?,
                     estado = 'en_proceso',
                     estado_flujo = 'en_proceso',
                     esperando_solicitante_desde = NULL
                 WHERE id_ticket = ?"
            );
            $stmt->bind_param(
                'iiii',
                $idSiguiente,
                $idGestor,
                $idServicio,
                $idTicket
            );
            $stmt->execute();
            $stmt->close();
            flujoRegistrarHistorial(
                $conn,
                $idTicket,
                $idUsuario,
                'Caso heredado habilitado',
                'Se habilitó '
                    . $siguiente['catalogo_nombre']
                    . ' / '
                    . $siguiente['servicio_nombre']
                    . '.',
                $idSiguiente
            );
            flujoNotificar(
                $conn,
                $idGestor,
                $idTicket,
                $idSiguiente,
                'Caso habilitado para su área',
                "El caso anterior del ticket #{$idTicket} terminó. Ya puede atenderlo."
            );
            $conn->commit();
            return;
        }

        $idPadre = (int) ($etapa['id_ticket_etapa_padre'] ?? 0);

        if ($idPadre > 0) {
            $stmt = $conn->prepare(
                "SELECT * FROM ticket_etapas
                 WHERE id_ticket_etapa = ?
                   AND id_ticket = ?
                 FOR UPDATE"
            );
            $stmt->bind_param('ii', $idPadre, $idTicket);
            $stmt->execute();
            $padre = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare(
                "SELECT id_ticket_etapa, estado
                 FROM ticket_etapas
                 WHERE id_ticket_etapa_padre = ?
                 FOR UPDATE"
            );
            $stmt->bind_param('i', $idPadre);
            $stmt->execute();
            $resultadoHermanos = $stmt->get_result();
            $hermanosPendientes = 0;

            while ($hermano = $resultadoHermanos->fetch_assoc()) {
                if (!in_array(
                    (string) $hermano['estado'],
                    ['completada', 'cancelada'],
                    true
                )) {
                    $hermanosPendientes++;
                }
            }
            $stmt->close();

            if ($hermanosPendientes === 0) {

                if ($padre && $padre['estado'] === 'pausada') {
                    $totalPadre = (int) ($padre['sla_minutos_total'] ?? 0);
                    $consumidoPadre = (int) ($padre['sla_minutos_consumidos'] ?? 0);
                    $restantes = max(0, $totalPadre - $consumidoPadre);
                    $vencePadre = flujoCalcularVencimientoRestante(
                        $conn,
                        $padre,
                        $ahora,
                        $restantes
                    );
                    $stmt = $conn->prepare(
                        "UPDATE ticket_etapas
                         SET estado = 'en_proceso',
                             fecha_pausa = NULL,
                             fecha_ultima_reanudacion = ?,
                             fecha_vencimiento = ?
                         WHERE id_ticket_etapa = ?
                           AND estado = 'pausada'"
                    );
                    $stmt->bind_param('ssi', $ahora, $vencePadre, $idPadre);
                    $stmt->execute();
                    $stmt->close();

                    $idGestorPadre = (int) $padre['id_gestor'];
                    $idServicioPadre = (int) $padre['id_servicio'];
                    $stmt = $conn->prepare(
                        "UPDATE tickets
                         SET id_etapa_actual = ?,
                             id_tecnico = ?,
                             id_servicio = ?,
                             estado = 'en_proceso',
                             estado_flujo = 'en_proceso'
                         WHERE id_ticket = ?"
                    );
                    $stmt->bind_param(
                        'iiii',
                        $idPadre,
                        $idGestorPadre,
                        $idServicioPadre,
                        $idTicket
                    );
                    $stmt->execute();
                    $stmt->close();
                    flujoRegistrarHistorial(
                        $conn,
                        $idTicket,
                        $idUsuario,
                        'Caso padre reanudado',
                        'Todos los casos hijos finalizaron. Se reanudó '
                            . $padre['catalogo_nombre'] . ' / '
                            . $padre['servicio_nombre'] . ' con '
                            . $restantes . ' minuto(s) hábil(es) restantes.',
                        $idPadre
                    );
                    flujoNotificar(
                        $conn,
                        $idGestorPadre,
                        $idTicket,
                        $idPadre,
                        'Caso padre reanudado',
                        "Todos los hijos del ticket #{$idTicket} finalizaron. Su SLA volvió a correr."
                    );
                }
            } else {
                $stmt = $conn->prepare(
                    "SELECT id_ticket_etapa, id_gestor, id_servicio
                     FROM ticket_etapas
                     WHERE id_ticket_etapa_padre = ?
                       AND estado NOT IN ('completada', 'cancelada')
                     ORDER BY orden DESC
                     LIMIT 1"
                );
                $stmt->bind_param('i', $idPadre);
                $stmt->execute();
                $activo = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($activo) {
                    $stmt = $conn->prepare(
                        "UPDATE tickets
                         SET id_etapa_actual = ?, id_tecnico = ?, id_servicio = ?
                         WHERE id_ticket = ?"
                    );
                    $idActivo = (int) $activo['id_ticket_etapa'];
                    $gestorActivo = (int) $activo['id_gestor'];
                    $servicioActivo = (int) $activo['id_servicio'];
                    $stmt->bind_param(
                        'iiii',
                        $idActivo,
                        $gestorActivo,
                        $servicioActivo,
                        $idTicket
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } else {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS pendientes
                 FROM ticket_etapas
                 WHERE id_ticket = ?
                   AND estado NOT IN ('completada', 'cancelada')"
            );
            $stmt->bind_param('i', $idTicket);
            $stmt->execute();
            $pendientesTicket = (int) (
                $stmt->get_result()->fetch_assoc()['pendientes'] ?? 0
            );
            $stmt->close();

            if ($pendientesTicket === 0) {
                $idCreador = (int) $etapa['id_usuario'];
                $motivoTicket = 'Cerrado automáticamente después de que el creador aprobó y calificó cada caso.';
                $stmt = $conn->prepare(
                    "UPDATE tickets
                     SET id_etapa_actual = NULL,
                         estado = 'cerrada',
                         estado_flujo = 'cerrado',
                         fecha_finalizacion = ?,
                         esperando_solicitante_desde = NULL,
                         cierre_tipo = 'aprobacion_por_caso',
                         motivo_cierre = ?
                     WHERE id_ticket = ?"
                );
                $stmt->bind_param(
                    'ssi',
                    $ahora,
                    $motivoTicket,
                    $idTicket
                );
                $stmt->execute();
                $stmt->close();
                flujoRegistrarHistorial(
                    $conn,
                    $idTicket,
                    $idUsuario,
                    'Ticket cerrado definitivamente',
                    'Todos los casos fueron marcados como listos, aprobados y calificados por sus respectivos creadores. La encuesta principal corresponde únicamente al servicio solicitado.',
                    $idTicketEtapa
                );
                flujoNotificar(
                    $conn,
                    $idCreador,
                    $idTicket,
                    null,
                    'Ticket cerrado definitivamente',
                    "Todos los casos del ticket #{$idTicket} fueron aprobados y calificados. El ticket quedó cerrado."
                );
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Compatibilidad con tickets que ya estaban en pendiente_calificacion antes
 * de instalar la aprobación por caso. Registra únicamente la encuesta del
 * servicio solicitado y cierra ese ticket anterior.
 *
 * @param array<int|string, mixed> $calificacionesArea
 * @param array<int|string, mixed> $calificacionesTiempo
 * @param array<int|string, mixed> $comentarios
 */
function flujoCalificarCerrarTicket(
    mysqli $conn,
    int $idTicket,
    int $idCreador,
    array $calificacionesArea,
    array $calificacionesTiempo,
    array $comentarios
): int {
    $idPaisOperacion = paisExigirContexto();
    if (!flujoModuloAprobacionCasosInstalado($conn)) {
        throw new RuntimeException(
            'El administrador debe importar la migración de aprobación y encuestas por caso.'
        );
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT id_ticket, id_usuario, estado, estado_flujo
             FROM tickets
             WHERE id_ticket = ?
               AND id_usuario = ?
               AND id_pais_operacion = ?
             FOR UPDATE"
        );
        $stmt->bind_param('iii', $idTicket, $idCreador, $idPaisOperacion);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ticket) {
            throw new RuntimeException(
                'Solo el gestor que creó el ticket puede realizar el cierre definitivo.'
            );
        }

        if ((string) $ticket['estado_flujo'] !== 'pendiente_calificacion') {
            throw new RuntimeException(
                'El ticket no está pendiente de calificación o ya fue cerrado.'
            );
        }

        $stmt = $conn->prepare(
            "SELECT
                id_ticket_etapa,
                id_gestor,
                catalogo_nombre,
                servicio_nombre,
                estado
             FROM ticket_etapas
             WHERE id_ticket = ?
               AND id_ticket_etapa_padre IS NULL
               AND estado <> 'cancelada'
             ORDER BY orden, id_ticket_etapa
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $etapa = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$etapa || (string) $etapa['estado'] !== 'completada') {
            throw new RuntimeException(
                'El servicio solicitado todavía no está disponible para encuesta.'
            );
        }

        $idEtapa = (int) $etapa['id_ticket_etapa'];
        $calificacionArea = (int) ($calificacionesArea[$idEtapa] ?? 0);
        $calificacionTiempo = (int) ($calificacionesTiempo[$idEtapa] ?? 0);
        $comentario = trim((string) ($comentarios[$idEtapa] ?? ''));

        if (
            $calificacionArea < 1
            || $calificacionArea > 5
            || $calificacionTiempo < 1
            || $calificacionTiempo > 5
        ) {
            throw new RuntimeException(
                'Califique de 1 a 5 el servicio solicitado y su tiempo de respuesta.'
            );
        }

        if (strlen($comentario) > 1000) {
            throw new RuntimeException(
                'La observación supera los 1.000 caracteres.'
            );
        }

        $calificacionGeneral = (int) round(
            ($calificacionArea + $calificacionTiempo) / 2
        );
        $idGestor = (int) ($etapa['id_gestor'] ?? 0);
        $tipoCalificacion = 'encuesta_servicio';
        $stmt = $conn->prepare(
            "INSERT INTO solicitud_calificaciones
                (
                    id_ticket,
                    id_ticket_etapa,
                    id_solicitante,
                    id_gestor,
                    calificacion,
                    calificacion_area,
                    calificacion_tiempo,
                    tipo_calificacion,
                    comentario
                )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''))
             ON DUPLICATE KEY UPDATE
                id_solicitante = VALUES(id_solicitante),
                id_gestor = VALUES(id_gestor),
                calificacion = VALUES(calificacion),
                calificacion_area = VALUES(calificacion_area),
                calificacion_tiempo = VALUES(calificacion_tiempo),
                tipo_calificacion = VALUES(tipo_calificacion),
                comentario = VALUES(comentario),
                creado_en = NOW()"
        );
        $stmt->bind_param(
            'iiiiiiiss',
            $idTicket,
            $idEtapa,
            $idCreador,
            $idGestor,
            $calificacionGeneral,
            $calificacionArea,
            $calificacionTiempo,
            $tipoCalificacion,
            $comentario
        );
        $stmt->execute();
        $stmt->close();

        $area = trim((string) $etapa['catalogo_nombre']);
        $servicio = trim((string) $etapa['servicio_nombre']);
        flujoRegistrarHistorial(
            $conn,
            $idTicket,
            $idCreador,
            'Encuesta del servicio solicitado',
            "{$area} / {$servicio}: gestión {$calificacionArea}/5 y tiempo de respuesta {$calificacionTiempo}/5.",
            $idEtapa
        );

        $motivo = 'Cerrado definitivamente después de registrar una única encuesta del servicio solicitado.';
        $stmt = $conn->prepare(
            "UPDATE tickets
             SET estado = 'cerrada',
                 estado_flujo = 'cerrado',
                 fecha_finalizacion = NOW(),
                 esperando_solicitante_desde = NULL,
                 cierre_tipo = 'gestor_creador',
                 motivo_cierre = ?
             WHERE id_ticket = ?
               AND id_usuario = ?
               AND estado_flujo = 'pendiente_calificacion'"
        );
        $stmt->bind_param('sii', $motivo, $idTicket, $idCreador);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException(
                'El estado del ticket cambió antes de completar el cierre.'
            );
        }

        $stmt->close();
        flujoRegistrarHistorial(
            $conn,
            $idTicket,
            $idCreador,
            'Ticket cerrado definitivamente',
            $motivo,
            null
        );

        $conn->commit();

        return 1;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function flujoCalificarEtapa(
    mysqli $conn,
    int $idTicket,
    int $idTicketEtapa,
    int $idSolicitante,
    int $calificacion,
    string $comentario
): bool {
    if ($calificacion < 1 || $calificacion > 5) {
        throw new RuntimeException('La calificación debe estar entre 1 y 5.');
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT
                te.id_ticket_etapa,
                te.id_gestor,
                te.estado,
                t.estado_flujo
             FROM ticket_etapas AS te
             INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
             WHERE te.id_ticket_etapa = ?
               AND te.id_ticket = ?
               AND t.id_usuario = ?
             FOR UPDATE"
        );
        $stmt->bind_param(
            'iii',
            $idTicketEtapa,
            $idTicket,
            $idSolicitante
        );
        $stmt->execute();
        $etapa = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$etapa
            || $etapa['estado'] !== 'completada'
            || $etapa['estado_flujo'] !== 'pendiente_calificacion'
        ) {
            throw new RuntimeException('La etapa todavía no puede calificarse.');
        }

        $idGestor = (int) $etapa['id_gestor'];
        $calificacionDetallada = flujoModuloCalificacionDetalladaInstalado($conn);
        $stmt = $conn->prepare($calificacionDetallada
            ? "INSERT INTO solicitud_calificaciones
                (
                    id_ticket, id_ticket_etapa, id_solicitante, id_gestor,
                    calificacion, calificacion_area, calificacion_tiempo,
                    comentario
                )
               VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''))
               ON DUPLICATE KEY UPDATE
                    calificacion = VALUES(calificacion),
                    calificacion_area = VALUES(calificacion_area),
                    calificacion_tiempo = VALUES(calificacion_tiempo),
                    comentario = VALUES(comentario),
                    id_gestor = VALUES(id_gestor)"
            : "INSERT INTO solicitud_calificaciones
                (
                    id_ticket, id_ticket_etapa, id_solicitante, id_gestor,
                    calificacion, comentario
                )
               VALUES (?, ?, ?, ?, ?, NULLIF(?, ''))
               ON DUPLICATE KEY UPDATE
                    calificacion = VALUES(calificacion),
                    comentario = VALUES(comentario),
                    id_gestor = VALUES(id_gestor)"
        );

        if ($calificacionDetallada) {
            $stmt->bind_param(
                'iiiiiiis',
                $idTicket,
                $idTicketEtapa,
                $idSolicitante,
                $idGestor,
                $calificacion,
                $calificacion,
                $calificacion,
                $comentario
            );
        } else {
            $stmt->bind_param(
                'iiiiis',
                $idTicket,
                $idTicketEtapa,
                $idSolicitante,
                $idGestor,
                $calificacion,
                $comentario
            );
        }
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN c.id_calificacion IS NOT NULL THEN 1 ELSE 0 END)
                    AS calificadas
             FROM ticket_etapas AS te
             LEFT JOIN solicitud_calificaciones AS c
                ON c.id_ticket_etapa = te.id_ticket_etapa
             WHERE te.id_ticket = ?
               AND te.estado = 'completada'"
        );
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $conteo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $cerrado = (int) $conteo['total'] > 0
            && (int) $conteo['total'] === (int) $conteo['calificadas'];

        if ($cerrado) {
            $motivo = 'Cerrado automáticamente por el gestor creador después de calificar todas las áreas.';
            $stmt = $conn->prepare(
                "UPDATE tickets
                 SET estado = 'cerrada',
                     estado_flujo = 'cerrado',
                     fecha_finalizacion = NOW(),
                     esperando_solicitante_desde = NULL,
                     cierre_tipo = 'gestor_creador',
                     motivo_cierre = ?
                 WHERE id_ticket = ?"
            );
            $stmt->bind_param('si', $motivo, $idTicket);
            $stmt->execute();
            $stmt->close();
            flujoRegistrarHistorial(
                $conn,
                $idTicket,
                $idSolicitante,
                'Ticket cerrado',
                $motivo,
                $idTicketEtapa
            );
        } else {
            flujoRegistrarHistorial(
                $conn,
                $idTicket,
                $idSolicitante,
                'Etapa calificada',
                "El gestor creador calificó un caso con {$calificacion} estrella(s).",
                $idTicketEtapa
            );
        }

        $conn->commit();

        return $cerrado;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function flujoTicketsUsuario(
    mysqli $conn,
    int $idUsuario,
    int $rol
): array {
    $idPaisOperacion = paisExigirContexto();
    if ($rol === 1) {
        $sql = "SELECT DISTINCT t.*, p.nombre AS proceso_nombre,
                    u.nombre AS creador_nombre,
                    u.nombre AS solicitante_nombre
                FROM tickets AS t
                INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
                LEFT JOIN usuarios AS u ON u.id_usuario = t.id_usuario
                WHERE t.id_proceso IS NOT NULL
                  AND t.id_pais_operacion = {$idPaisOperacion}
                ORDER BY t.actualizado_en DESC, t.id_ticket DESC";
        $resultado = $conn->query($sql);
    } elseif ($rol === 2) {
        $stmt = $conn->prepare(
            "SELECT DISTINCT t.*, p.nombre AS proceso_nombre,
                    u.nombre AS creador_nombre,
                    u.nombre AS solicitante_nombre
             FROM tickets AS t
             INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
             LEFT JOIN ticket_etapas AS te ON te.id_ticket = t.id_ticket
             LEFT JOIN usuarios AS u ON u.id_usuario = t.id_usuario
             WHERE t.id_pais_operacion = ?
               AND te.id_gestor = ?
             ORDER BY t.actualizado_en DESC, t.id_ticket DESC"
        );
        $stmt->bind_param('ii', $idPaisOperacion, $idUsuario);
        $stmt->execute();
        $resultado = $stmt->get_result();
    } elseif ($rol === 3) {
        $stmt = $conn->prepare(
            "SELECT DISTINCT t.*, p.nombre AS proceso_nombre,
                    u.nombre AS creador_nombre,
                    u.nombre AS solicitante_nombre
             FROM tickets AS t
             INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
             LEFT JOIN usuarios AS u ON u.id_usuario = t.id_usuario
             WHERE t.id_pais_operacion = ? AND t.id_usuario = ?
             ORDER BY t.actualizado_en DESC, t.id_ticket DESC"
        );
        $stmt->bind_param('ii', $idPaisOperacion, $idUsuario);
        $stmt->execute();
        $resultado = $stmt->get_result();
    } else {
        return [];
    }

    $filas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $filas[] = $fila;
    }

    if (isset($stmt)) {
        $stmt->close();
    }

    return $filas;
}
