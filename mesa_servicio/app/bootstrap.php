<?php
declare(strict_types=1);

/**
 * Punto de arranque único de la aplicación.
 *
 * Todos los controladores públicos cargan este archivo antes de ejecutar el
 * módulo correspondiente. Así las rutas internas no dependen de la carpeta
 * desde la que se invoque cada PHP.
 */

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public');
}

date_default_timezone_set('America/Bogota');

