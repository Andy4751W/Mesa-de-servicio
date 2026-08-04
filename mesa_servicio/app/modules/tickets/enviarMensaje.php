<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

/*
 * El envío heredado guardaba archivos dentro del directorio público y no
 * comprobaba la participación en el caso. Todo mensaje debe pasar ahora por
 * flujoTicket.php y motorFlujos.php.
 */
http_response_code(410);
exit('Este canal fue retirado. Envíe el mensaje desde el caso correspondiente.');
