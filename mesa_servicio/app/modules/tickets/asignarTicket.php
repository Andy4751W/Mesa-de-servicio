<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panelAdmin.php');
    exit;
}

seguridadExigirOrigenPost();
seguridadExigirCsrfPost();

$idTicket = filter_input(INPUT_POST, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
$idGestor = filter_input(INPUT_POST, 'id_tecnico', FILTER_VALIDATE_INT) ?: 0;

if (!$idTicket || !$idGestor) {
    http_response_code(422);
    exit('Datos no válidos.');
}

$stmt = $conn->prepare(
    "SELECT 1 FROM usuarios
     WHERE id_usuario = ? AND id_rol = 2 AND estado = 'activo'
     LIMIT 1"
);
$stmt->bind_param('i', $idGestor);
$stmt->execute();
$stmt->store_result();
$gestorValido = $stmt->num_rows > 0;
$stmt->close();

if (!$gestorValido) {
    http_response_code(422);
    exit('El gestor no está disponible.');
}

$resultadoColumna = $conn->query(
    "SHOW COLUMNS FROM tickets LIKE 'id_proceso'"
);
$condicion = $resultadoColumna && $resultadoColumna->num_rows > 0
    ? ' AND id_proceso IS NULL'
    : '';
$stmt = $conn->prepare(
    "UPDATE tickets
     SET id_tecnico = ?, estado = 'en_proceso'
     WHERE id_ticket = ?"
    . $condicion
);
$stmt->bind_param('ii', $idGestor, $idTicket);
$stmt->execute();
$actualizados = $stmt->affected_rows;
$stmt->close();

if ($actualizados < 1) {
    http_response_code(403);
    exit('Los tickets se asignan automáticamente al gestor de cada etapa.');
}

header('Location: solicitudes.php?id_ticket=' . $idTicket);
exit;
