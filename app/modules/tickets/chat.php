<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

/*
 * El chat heredado no separaba las conversaciones por caso. Se deshabilita
 * para impedir que el acceso directo eluda la autorización padre-hijo.
 */
$idTicket = filter_input(INPUT_GET, 'id_ticket', FILTER_VALIDATE_INT) ?: 0;
$destino = 'flujoTicket.php';

if ($idTicket > 0) {
    $destino .= '?id_ticket=' . $idTicket;
}

header('Location: ' . $destino, true, 302);
exit;
