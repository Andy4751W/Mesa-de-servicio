<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 3) {
    http_response_code(403);
    exit('Acceso denegado.');
}

/* La creación de tickets pertenece exclusivamente al portal solicitante. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: panelSolicitante.php?vista=nueva', true, $_SERVER['REQUEST_METHOD'] === 'POST' ? 303 : 302);
exit;
