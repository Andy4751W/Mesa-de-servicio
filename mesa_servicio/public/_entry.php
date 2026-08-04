<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

/** @var array<string, string> $entradas */
$entradas = require APP_ROOT . '/routes.php';

$entrada = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
$archivo = $entradas[$entrada] ?? null;

if ($archivo === null || !is_file($archivo)) {
    http_response_code(404);
    exit('Recurso no encontrado.');
}

require $archivo;
