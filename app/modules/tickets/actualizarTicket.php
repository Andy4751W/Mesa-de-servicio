<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 2) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panelGestor.php');
    exit;
}

seguridadExigirOrigenPost();
seguridadExigirCsrfPost();

$idTicket = filter_input(INPUT_POST, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
$estado = (string) ($_POST['estado'] ?? '');
$idGestor = (int) $_SESSION['usuario_id'];
$idPaisOperacion = paisExigirContexto();
$estados = ['abierto', 'en_proceso', 'en_espera', 'resuelta'];

if (!$idTicket || !in_array($estado, $estados, true)) {
    http_response_code(422);
    exit('Datos no válidos.');
}

/* Los tickets por etapas solo pueden avanzar mediante su checklist. */
$tieneFlujos = false;
$resultadoColumna = $conn->query(
    "SHOW COLUMNS FROM tickets LIKE 'id_proceso'"
);
$tieneFlujos = $resultadoColumna && $resultadoColumna->num_rows > 0;
$condicion = $tieneFlujos ? ' AND id_proceso IS NULL' : '';
$stmt = $conn->prepare(
    "UPDATE tickets
     SET estado = ?
     WHERE id_ticket = ?
       AND id_tecnico = ?"
    . ' AND id_pais_operacion = ?'
    . $condicion
);
$stmt->bind_param('siii', $estado, $idTicket, $idGestor, $idPaisOperacion);
$stmt->execute();
$actualizados = $stmt->affected_rows;
$stmt->close();

if ($actualizados < 1) {
    http_response_code(403);
    exit('El ticket no pertenece al gestor o se administra desde el módulo de Tickets.');
}

header('Location: panelGestor.php?msg=estado_actualizado');
exit;
