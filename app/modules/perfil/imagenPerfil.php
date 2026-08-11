<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/perfil.php';

seguridadExigirRol([1, 2, 3]);
paisExigirContexto();

$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$imagen = perfilImagenActual($idUsuario);

if ($imagen === null) {
    $iniciales = htmlspecialchars(
        perfilIniciales((string) ($_SESSION['usuario'] ?? 'Usuario')),
        ENT_QUOTES | ENT_XML1,
        'UTF-8'
    );
    $color = paisContextoCodigo() === 'PE' ? '#c81e3a' : '#1167d8';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="'
        . $color . '"/><stop offset="1" stop-color="#102a43"/></linearGradient></defs>'
        . '<rect width="240" height="240" rx="120" fill="url(#g)"/>'
        . '<text x="120" y="139" text-anchor="middle" fill="#fff" font-family="Segoe UI,Arial,sans-serif" font-size="72" font-weight="800">'
        . $iniciales . '</text></svg>';

    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Content-Length: ' . strlen($svg));
    header('Content-Disposition: inline; filename="perfil.svg"');
    header('Cache-Control: private, max-age=120, must-revalidate');
    echo $svg;
    exit;
}

$etag = '"' . hash(
    'sha256',
    $imagen['ruta'] . '|' . $imagen['modificado'] . '|' . $imagen['tamano']
) . '"';

if (hash_equals($etag, trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')))) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $imagen['mime']);
header('Content-Length: ' . $imagen['tamano']);
header('Content-Disposition: inline; filename="perfil"');
header('Cache-Control: private, max-age=300, must-revalidate');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
readfile($imagen['ruta']);
exit;
