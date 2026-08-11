<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este proceso solo puede ejecutarse desde la línea de comandos.');
}

require_once APP_ROOT . '/config/conexion.php';

$aplicar = in_array('--apply', $argv ?? [], true);
$resumen = [
    'listos' => 0,
    'migrados' => 0,
    'faltantes' => 0,
    'invalidos' => 0,
    'errores' => 0,
];

/**
 * @param array<string, int> $resumen
 */
function migrarArchivoPrivado(
    mysqli $conn,
    string $tabla,
    string $columnaId,
    int $id,
    string $columnaRuta,
    string $rutaAnterior,
    string $prefijoAnterior,
    string $subdirectorioPrivado,
    bool $aplicar,
    array &$resumen
): void {
    $nombre = basename(str_replace('\\', '/', $rutaAnterior));
    $esperada = $prefijoAnterior . $nombre;

    if ($nombre === '' || !hash_equals($esperada, $rutaAnterior)) {
        $resumen['invalidos']++;
        return;
    }

    $basePublica = realpath(
        PUBLIC_ROOT . '/' . rtrim($prefijoAnterior, '/')
    );
    $origen = realpath(PUBLIC_ROOT . '/' . $rutaAnterior);

    if (
        !$basePublica
        || !$origen
        || !str_starts_with($origen, $basePublica . DIRECTORY_SEPARATOR)
        || !is_file($origen)
    ) {
        $resumen['faltantes']++;
        return;
    }

    $resumen['listos']++;

    if (!$aplicar) {
        return;
    }

    $directorio = seguridadDirectorioPrivado($subdirectorioPrivado);

    if (
        !is_dir($directorio)
        && !mkdir($directorio, 0750, true)
        && !is_dir($directorio)
    ) {
        $resumen['errores']++;
        return;
    }

    $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;
    $creado = false;

    if (is_file($destino)) {
        if (!hash_equals((string) hash_file('sha256', $origen), (string) hash_file('sha256', $destino))) {
            $resumen['errores']++;
            return;
        }
    } elseif (!copy($origen, $destino)) {
        $resumen['errores']++;
        return;
    } else {
        $creado = true;
        @chmod($destino, 0640);
    }

    $rutaNueva = 'private/' . $subdirectorioPrivado . '/' . $nombre;

    try {
        $conn->begin_transaction();
        $sql = "UPDATE `{$tabla}`
                SET `{$columnaRuta}` = ?
                WHERE `{$columnaId}` = ?
                  AND `{$columnaRuta}` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sis', $rutaNueva, $id, $rutaAnterior);
        $stmt->execute();
        $actualizados = $stmt->affected_rows;
        $stmt->close();

        if ($actualizados !== 1) {
            throw new RuntimeException('El registro cambió durante la migración.');
        }

        $conn->commit();
        @unlink($origen);
        $resumen['migrados']++;
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignorado) {
        }

        if ($creado && is_file($destino)) {
            @unlink($destino);
        }

        error_log('Migración de archivo privado: ' . $e->getMessage());
        $resumen['errores']++;
    }
}

$catalogos = $conn->query(
    "SELECT id_catalogo, imagen
     FROM catalogos
     WHERE imagen LIKE 'uploads/%'
       AND imagen NOT LIKE 'uploads/%/%'"
);

while ($fila = $catalogos->fetch_assoc()) {
    migrarArchivoPrivado(
        $conn,
        'catalogos',
        'id_catalogo',
        (int) $fila['id_catalogo'],
        'imagen',
        (string) $fila['imagen'],
        'uploads/',
        'catalogos',
        $aplicar,
        $resumen
    );
}

$adjuntos = $conn->query(
    "SELECT id_adjunto, ruta
     FROM solicitud_adjuntos
     WHERE ruta LIKE 'uploads/solicitudes/%'"
);

while ($fila = $adjuntos->fetch_assoc()) {
    migrarArchivoPrivado(
        $conn,
        'solicitud_adjuntos',
        'id_adjunto',
        (int) $fila['id_adjunto'],
        'ruta',
        (string) $fila['ruta'],
        'uploads/solicitudes/',
        'solicitudes',
        $aplicar,
        $resumen
    );
}

echo $aplicar
    ? "Migración aplicada.\n"
    : "Simulación terminada; no se modificó ningún archivo ni registro.\n";

foreach ($resumen as $clave => $valor) {
    echo ucfirst($clave) . ': ' . $valor . PHP_EOL;
}

if (!$aplicar && $resumen['listos'] > 0) {
    echo "Ejecute nuevamente con --apply después de revisar la simulación.\n";
}

exit($resumen['errores'] > 0 ? 1 : 0);
