<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

$idAdjunto = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$rol = (int) ($_SESSION['rol'] ?? 0);
$idPaisOperacion = paisExigirContexto();

if (!$idAdjunto || !flujoModuloInstalado($conn)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$stmt = $conn->prepare(
    "SELECT
        a.*,
        t.id_proceso,
        t.id_usuario AS id_solicitante_ticket,
        t.id_tecnico AS id_gestor_ticket
     FROM solicitud_adjuntos AS a
     INNER JOIN tickets AS t ON t.id_ticket = a.id_ticket
     WHERE a.id_adjunto = ?
       AND t.id_pais_operacion = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $idAdjunto, $idPaisOperacion);
$stmt->execute();
$adjunto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$adjunto) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$idTicket = (int) $adjunto['id_ticket'];
$idTicketEtapa = (int) ($adjunto['id_ticket_etapa'] ?? 0);
$esTicketConFlujo = (int) ($adjunto['id_proceso'] ?? 0) > 0;
$puedeDescargar = false;

if ($esTicketConFlujo) {
    $puedeDescargar = $rol === 1
        || (
            $idTicketEtapa > 0
            && flujoPuedeVerTicket($conn, $idTicket, $idUsuario, $rol)
            && flujoPuedeVerConversacionNodo(
                $conn,
                $idTicket,
                $idTicketEtapa,
                $idUsuario,
                $rol
            )
        );
} else {
    $puedeDescargar = $rol === 1
        || (
            $rol === 2
            && (int) ($adjunto['id_gestor_ticket'] ?? 0) === $idUsuario
        )
        || (
            $rol === 3
            && (int) ($adjunto['id_solicitante_ticket'] ?? 0) === $idUsuario
        );
}

if (!$puedeDescargar) {
    http_response_code(403);
    exit('No participa en la conversación a la que pertenece este archivo.');
}

$rutaRegistrada = ltrim((string) $adjunto['ruta'], '/\\');
$nombreGuardado = basename((string) ($adjunto['nombre_guardado'] ?? ''));
$rutaPrivadaEsperada = 'private/solicitudes/' . $nombreGuardado;

if (
    $nombreGuardado !== ''
    && hash_equals($rutaPrivadaEsperada, $rutaRegistrada)
) {
    $base = realpath(seguridadDirectorioPrivado('solicitudes'));
    $ruta = $base
        ? realpath($base . DIRECTORY_SEPARATOR . $nombreGuardado)
        : false;
} else {
    /* Compatibilidad de lectura para adjuntos anteriores a esta actualización. */
    $base = realpath(PUBLIC_ROOT . '/uploads/solicitudes');
    $ruta = realpath(PUBLIC_ROOT . '/' . $rutaRegistrada);
}

if (
    !$base
    || !$ruta
    || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)
    || !is_file($ruta)
) {
    http_response_code(404);
    exit('El archivo físico no está disponible.');
}

$nombre = preg_replace(
    '/[^A-Za-z0-9._ -]/u',
    '_',
    (string) $adjunto['nombre_original']
);
$nombre = $nombre !== '' ? $nombre : 'adjunto';

$tiposPermitidos = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'text/plain',
    'text/csv',
    'application/zip',
    'application/x-zip-compressed',
    'application/msword',
    'application/vnd.ms-excel',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
$tipoMime = (string) ($adjunto['tipo_mime'] ?? '');

if (!in_array($tipoMime, $tiposPermitidos, true)) {
    $tipoMime = 'application/octet-stream';
}

$solicitaVistaPrevia = (string) ($_GET['inline'] ?? '') === '1';
$tiposVistaPrevia = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
];
$disposicion = $solicitaVistaPrevia
    && in_array($tipoMime, $tiposVistaPrevia, true)
        ? 'inline'
        : 'attachment';

header('Content-Type: ' . $tipoMime);
header('Content-Length: ' . filesize($ruta));
header(
    "Content-Disposition: {$disposicion}; filename*=UTF-8''"
    . rawurlencode($nombre)
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($ruta);
exit;
