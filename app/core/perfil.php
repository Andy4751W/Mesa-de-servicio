<?php
declare(strict_types=1);

/**
 * Almacenamiento privado de las imágenes de perfil.
 *
 * La imagen se vincula al id de la sesión y nunca depende de una ruta o de un
 * identificador suministrado por el navegador.
 */

const PERFIL_IMAGEN_MAX_BYTES = 3145728;
const PERFIL_IMAGEN_MAX_PIXELES = 25000000;
const PERFIL_IMAGEN_MAX_LADO = 6000;

function perfilDirectorioImagenes(bool $crear = false): string
{
    $directorio = seguridadDirectorioPrivado('perfiles');

    if ($crear && !is_dir($directorio)) {
        if (!mkdir($directorio, 0750, true) && !is_dir($directorio)) {
            throw new RuntimeException('No fue posible preparar el directorio de perfiles.');
        }
    }

    return $directorio;
}

/** @return array{ruta:string,mime:string,modificado:int,tamano:int}|null */
function perfilImagenActual(int $idUsuario): ?array
{
    if ($idUsuario < 1) {
        return null;
    }

    $directorio = perfilDirectorioImagenes();

    if (!is_dir($directorio)) {
        return null;
    }

    $base = realpath($directorio);

    if ($base === false) {
        return null;
    }

    $patron = $base . DIRECTORY_SEPARATOR . 'usuario-' . $idUsuario . '-*.*';
    $candidatos = glob($patron, GLOB_NOSORT) ?: [];
    $validos = [];

    foreach ($candidatos as $candidato) {
        $nombre = basename($candidato);

        if (
            preg_match(
                '/^usuario-' . preg_quote((string) $idUsuario, '/')
                . '-[a-f0-9]{32}\.(?:jpg|png|webp)$/',
                $nombre
            ) !== 1
        ) {
            continue;
        }

        $ruta = realpath($candidato);

        if (
            $ruta === false
            || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)
            || !is_file($ruta)
        ) {
            continue;
        }

        $validos[] = $ruta;
    }

    if ($validos === []) {
        return null;
    }

    usort(
        $validos,
        static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a)
    );

    $ruta = $validos[0];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($ruta);

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }

    return [
        'ruta' => $ruta,
        'mime' => $mime,
        'modificado' => (int) filemtime($ruta),
        'tamano' => (int) filesize($ruta),
    ];
}

/** @param array<string, mixed> $archivo */
function perfilGuardarImagen(int $idUsuario, array $archivo): void
{
    if ($idUsuario < 1) {
        throw new DomainException('No fue posible identificar el usuario.');
    }

    $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        $mensaje = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño permitido de 3 MB.',
            UPLOAD_ERR_NO_FILE => 'Seleccione una imagen para continuar.',
            default => 'No fue posible recibir la imagen. Inténtelo nuevamente.',
        };
        throw new DomainException($mensaje);
    }

    $temporal = (string) ($archivo['tmp_name'] ?? '');
    $tamano = (int) ($archivo['size'] ?? 0);

    if (
        $temporal === ''
        || !is_uploaded_file($temporal)
        || $tamano < 1
        || $tamano > PERFIL_IMAGEN_MAX_BYTES
    ) {
        throw new DomainException('La imagen debe ser válida y pesar máximo 3 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($temporal);
    $extensiones = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensiones[$mime])) {
        throw new DomainException('Use una imagen en formato JPG, PNG o WebP.');
    }

    $dimensiones = @getimagesize($temporal);
    $ancho = (int) ($dimensiones[0] ?? 0);
    $alto = (int) ($dimensiones[1] ?? 0);

    if (
        $ancho < 1
        || $alto < 1
        || $ancho > PERFIL_IMAGEN_MAX_LADO
        || $alto > PERFIL_IMAGEN_MAX_LADO
        || ($ancho * $alto) > PERFIL_IMAGEN_MAX_PIXELES
    ) {
        throw new DomainException('La imagen tiene dimensiones no permitidas.');
    }

    $directorio = perfilDirectorioImagenes(true);
    $token = bin2hex(random_bytes(16));
    $nombre = 'usuario-' . $idUsuario . '-' . $token . '.' . $extensiones[$mime];
    $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

    if (!move_uploaded_file($temporal, $destino)) {
        throw new RuntimeException('No fue posible guardar la imagen de perfil.');
    }

    @chmod($destino, 0640);

    foreach (glob($directorio . DIRECTORY_SEPARATOR . 'usuario-' . $idUsuario . '-*.*') ?: [] as $anterior) {
        if ($anterior !== $destino && is_file($anterior)) {
            @unlink($anterior);
        }
    }
}

function perfilIniciales(string $nombre): string
{
    $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $iniciales = '';

    foreach (array_slice($partes, 0, 2) as $parte) {
        $iniciales .= function_exists('mb_substr')
            ? mb_substr($parte, 0, 1, 'UTF-8')
            : substr($parte, 0, 1);
    }

    $iniciales = function_exists('mb_strtoupper')
        ? mb_strtoupper($iniciales, 'UTF-8')
        : strtoupper($iniciales);

    return $iniciales !== '' ? $iniciales : 'US';
}

