<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

/*
 * El registro público fue retirado. Solo el administrador puede crear
 * cuentas de Administrador o Gestor desde el módulo Usuarios.
 */
if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('El registro público de usuarios no está habilitado.');
}

header('Location: crearUsuarios.php', true, 303);
exit;
