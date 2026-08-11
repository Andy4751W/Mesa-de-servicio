<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
$idPaisOperacion = paisExigirContexto();

$idCatalogo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($idCatalogo < 1) {
    http_response_code(404);
    exit('Imagen no encontrada.');
}

$stmt = $conn->prepare(
    "SELECT imagen
     FROM catalogos
     WHERE id_catalogo = ? AND id_pais_operacion = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $idCatalogo, $idPaisOperacion);
$stmt->execute();
$catalogo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$rutaRegistrada = trim((string) ($catalogo['imagen'] ?? ''));

if ($rutaRegistrada === '') {
    http_response_code(404);
    exit('Imagen no encontrada.');
}

$nombre = basename(str_replace('\\', '/', $rutaRegistrada));
$base = false;
$ruta = false;
$rutaPrivadaEsperada = 'private/catalogos/' . $nombre;

if ($nombre !== '' && hash_equals($rutaPrivadaEsperada, $rutaRegistrada)) {
    $base = realpath(seguridadDirectorioPrivado('catalogos'));
    $ruta = $base
        ? realpath($base . DIRECTORY_SEPARATOR . $nombre)
        : false;
} else {
    /* Compatibilidad temporal con imágenes guardadas antes del endurecimiento. */
    $rutaPublicaEsperada = 'uploads/' . $nombre;

    if ($nombre !== '' && hash_equals($rutaPublicaEsperada, $rutaRegistrada)) {
        $base = realpath(PUBLIC_ROOT . '/uploads');
        $ruta = $base
            ? realpath($base . DIRECTORY_SEPARATOR . $nombre)
            : false;
    }
}

if (
    !$base
    || !$ruta
    || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)
    || !is_file($ruta)
) {
    http_response_code(404);
    exit('Imagen no encontrada.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$tipoMime = (string) $finfo->file($ruta);
$tiposPermitidos = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];

if (!in_array($tipoMime, $tiposPermitidos, true)) {
    http_response_code(415);
    exit('Formato de imagen no permitido.');
}

header('Content-Type: ' . $tipoMime);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="catalogo-' . $idCatalogo . '"');
header('Cache-Control: private, max-age=300, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
