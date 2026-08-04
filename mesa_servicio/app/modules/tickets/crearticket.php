<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 3) {
    http_response_code(403);
    exit('Acceso denegado.');
}

/* Endpoint anterior deshabilitado: el ticket solo puede nacer desde su flujo. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: flujoTicket.php', true, $_SERVER['REQUEST_METHOD'] === 'POST' ? 303 : 302);
exit;
