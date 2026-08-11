<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este proceso solo puede ejecutarse desde la línea de comandos.');
}

require_once APP_ROOT . '/config/conexion.php';
require_once APP_ROOT . '/core/automatizacionTickets.php';

try {
    $cerrados = procesarCierresAutomaticos($conn);
    echo '[' . date('Y-m-d H:i:s') . "] Tickets cerrados: {$cerrados}"
        . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(
        STDERR,
        '[' . date('Y-m-d H:i:s') . '] Error: ' . $e->getMessage()
            . PHP_EOL
    );
    exit(1);
}
