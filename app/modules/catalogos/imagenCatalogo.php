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

function responderMarcadorCatalogo(int $idCatalogo): never
{
    $codigo = 'CAT';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160" role="img" aria-label="Catálogo sin imagen">'
        . '<rect width="160" height="160" rx="26" fill="#eef5ff"/>'
        . '<path d="M43 52h74v60H43z" fill="#fff" stroke="#8bb7e8" stroke-width="5"/>'
        . '<path d="m50 103 20-22 16 14 12-11 19 19" fill="none" stroke="#1167d8" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="96" cy="69" r="9" fill="#2aa8ff"/>'
        . '<text x="80" y="139" text-anchor="middle" font-family="Segoe UI,Arial,sans-serif" font-size="14" font-weight="700" fill="#315b7e">'
        . $codigo . ' ' . $idCatalogo . '</text></svg>';

    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Content-Length: ' . strlen($svg));
    header('Cache-Control: private, max-age=300, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo $svg;
    exit;
}

if ($rutaRegistrada === '') {
    responderMarcadorCatalogo($idCatalogo);
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
    responderMarcadorCatalogo($idCatalogo);
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
    responderMarcadorCatalogo($idCatalogo);
}

header('Content-Type: ' . $tipoMime);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="catalogo-' . $idCatalogo . '"');
header('Cache-Control: private, max-age=300, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
