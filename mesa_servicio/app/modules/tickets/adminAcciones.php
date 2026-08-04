<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
seguridadExigirRol([1]);

/*
 * Endpoint heredado deshabilitado por seguridad. Antes permitía exponer
 * hashes, eliminar usuarios y asignar una contraseña fija. La gestión segura
 * se realiza desde crearUsuarios.php y editarUsuario.php.
 */
http_response_code(410);
exit('Esta operación antigua fue retirada. Use el módulo Usuarios.');
