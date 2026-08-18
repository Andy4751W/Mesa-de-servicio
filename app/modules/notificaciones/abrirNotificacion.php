<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

seguridadExigirRol([1, 2, 3]);

$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$rol = (int) ($_SESSION['rol'] ?? 0);
$idPais = paisExigirContexto();
$idNotificacion = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
) ?: 0;

function notificacionRutaInicial(int $rol): string
{
    return match ($rol) {
        1 => 'solicitudes.php',
        2 => 'flujoTicket.php?modo=mis_tickets',
        default => 'panelSolicitante.php?vista=tickets',
    };
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion !== 'marcar_todas') {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'message' => 'La acción solicitada no es válida.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE notificaciones AS n
         LEFT JOIN tickets AS t ON t.id_ticket = n.id_ticket
         SET n.leida = 1,
             n.leida_en = COALESCE(n.leida_en, NOW())
         WHERE n.id_usuario = ?
           AND n.leida = 0
           AND (n.id_ticket IS NULL OR t.id_pais_operacion = ?)"
    );
    $stmt->bind_param('ii', $idUsuario, $idPais);
    $stmt->execute();
    $actualizadas = $stmt->affected_rows;
    $stmt->close();

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'updated' => max(0, $actualizadas),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($idNotificacion < 1 || $idUsuario < 1) {
    header('Location: ' . notificacionRutaInicial($rol), true, 303);
    exit;
}

$stmt = $conn->prepare(
    "SELECT
        n.id_ticket,
        n.id_ticket_etapa,
        t.estado_flujo
     FROM notificaciones AS n
     LEFT JOIN tickets AS t ON t.id_ticket = n.id_ticket
     WHERE n.id_notificacion = ?
       AND n.id_usuario = ?
       AND (n.id_ticket IS NULL OR t.id_pais_operacion = ?)
     LIMIT 1"
);
$stmt->bind_param('iii', $idNotificacion, $idUsuario, $idPais);
$stmt->execute();
$notificacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$notificacion) {
    header('Location: ' . notificacionRutaInicial($rol), true, 303);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE notificaciones
     SET leida = 1,
         leida_en = COALESCE(leida_en, NOW())
     WHERE id_notificacion = ? AND id_usuario = ?"
);
$stmt->bind_param('ii', $idNotificacion, $idUsuario);
$stmt->execute();
$stmt->close();

$idTicket = (int) ($notificacion['id_ticket'] ?? 0);
$idEtapa = (int) ($notificacion['id_ticket_etapa'] ?? 0);

if ($idTicket < 1) {
    header('Location: ' . notificacionRutaInicial($rol), true, 303);
    exit;
}

if ($rol === 1) {
    $ruta = 'solicitudes.php?' . http_build_query([
        'id_ticket' => $idTicket,
    ]);
} elseif ($rol === 2) {
    $parametros = [
        'modo' => 'mis_tickets',
        'bandeja' => (string) ($notificacion['estado_flujo'] ?? '') === 'cerrado'
            ? 'cerrados'
            : 'abiertos',
        'id_ticket' => $idTicket,
    ];

    if ($idEtapa > 0) {
        $parametros['id_nodo'] = $idEtapa;
        $parametros['id_chat'] = $idEtapa;
    }

    $ruta = 'flujoTicket.php?' . http_build_query($parametros);
} else {
    $parametros = [
        'vista' => 'tickets',
        'id_ticket' => $idTicket,
    ];

    if ($idEtapa > 0) {
        $parametros['id_chat'] = $idEtapa;
    }

    $ruta = 'panelSolicitante.php?' . http_build_query($parametros);
}

header('Location: ' . $ruta, true, 303);
exit;
