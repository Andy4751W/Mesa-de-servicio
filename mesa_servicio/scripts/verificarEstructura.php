<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

/** @var array<string, string> $rutas */
$rutas = require APP_ROOT . '/routes.php';
$errores = [];

if (PHP_VERSION_ID < 80100) {
    $errores[] = 'Se requiere PHP 8.1 o superior.';
}

foreach (['mysqli', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        $errores[] = "Falta la extensión PHP {$extension}.";
    }
}

foreach ($rutas as $entrada => $implementacion) {
    $controlador = PUBLIC_ROOT . DIRECTORY_SEPARATOR . $entrada;

    if (!is_file($controlador)) {
        $errores[] = "Falta la entrada pública: public/{$entrada}";
    }

    if (!is_file($implementacion)) {
        $errores[] = "Falta la implementación de {$entrada}: {$implementacion}";
    }
}

$obligatorios = [
    APP_ROOT . '/config/configuracion.local.php.example',
    APP_ROOT . '/security/seguridad.php',
    APP_ROOT . '/core/calendarioLaboral.php',
    PROJECT_ROOT . '/database/install/001_base_mesa_servicio_limpia.sql',
    PROJECT_ROOT . '/database/migrations/004_seguridad.sql',
    PUBLIC_ROOT . '/assets/js/controlSesion.js',
    PUBLIC_ROOT . '/assets/images/default-catalog.svg',
];

foreach ($obligatorios as $archivo) {
    if (!is_file($archivo)) {
        $errores[] = 'Falta el archivo obligatorio: ' . $archivo;
    }
}

if ($errores !== []) {
    fwrite(STDERR, "Estructura incompleta:\n- " . implode("\n- ", $errores) . "\n");
    exit(1);
}

echo 'Estructura correcta: ' . count($rutas) . " entradas públicas verificadas.\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo "Extensiones requeridas: disponibles.\n";
exit(0);

