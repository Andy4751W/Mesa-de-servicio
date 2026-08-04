<?php
declare(strict_types=1);

/**
 * Controles de seguridad comunes para la Mesa de Servicio.
 * Requiere PHP 8.1 o superior.
 */

const SEGURIDAD_SESION_INACTIVIDAD = 300;
const SEGURIDAD_SESION_MAXIMA = 1800;
const SEGURIDAD_SESION_AVISO = 60;

function seguridadEntorno(): string
{
    $entorno = strtolower(trim((string) (getenv('MESA_ENV') ?: 'production')));

    return in_array($entorno, ['production', 'staging', 'development'], true)
        ? $entorno
        : 'production';
}

function seguridadEsProduccion(): bool
{
    return seguridadEntorno() === 'production';
}

function seguridadEsHttps(): bool
{
    if ((string) getenv('MESA_FORCE_HTTPS') === '1') {
        return true;
    }

    return isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

function seguridadAplicarCabeceras(bool $contenidoPrivado = true): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "base-uri 'self'; frame-ancestors 'none'; form-action 'self'; "
        . "object-src 'none'; img-src 'self' data: blob:; "
        . "font-src 'self' data:; style-src 'self' 'unsafe-inline'; "
        . "script-src 'self' 'unsafe-inline'; connect-src 'self'"
    );

    if ($contenidoPrivado) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    if (seguridadEsHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function seguridadIniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', seguridadEsHttps() ? '1' : '0');
    ini_set('session.gc_maxlifetime', (string) SEGURIDAD_SESION_MAXIMA);

    session_name('MESA_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => seguridadEsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function seguridadMotivoVencimiento(?int $ahora = null): ?string
{
    if (empty($_SESSION['usuario_id'])) {
        return 'credenciales';
    }

    $ahora ??= time();
    $inicio = (int) ($_SESSION['inicio_sesion'] ?? 0);
    $interaccion = (int) ($_SESSION['ultima_interaccion'] ?? 0);

    if ($inicio < 1 || $interaccion < 1) {
        return 'sesion_expirada';
    }

    if ($ahora - $inicio >= SEGURIDAD_SESION_MAXIMA) {
        return 'tiempo_maximo';
    }

    if ($ahora - $interaccion >= SEGURIDAD_SESION_INACTIVIDAD) {
        return 'inactividad';
    }

    return null;
}

/** @return array{inactividad:int,maximo:int,aviso:int} */
function seguridadTiemposRestantes(?int $ahora = null): array
{
    $ahora ??= time();
    $inicio = (int) ($_SESSION['inicio_sesion'] ?? $ahora);
    $interaccion = (int) ($_SESSION['ultima_interaccion'] ?? $ahora);

    return [
        'inactividad' => max(
            0,
            SEGURIDAD_SESION_INACTIVIDAD - ($ahora - $interaccion)
        ),
        'maximo' => max(
            0,
            SEGURIDAD_SESION_MAXIMA - ($ahora - $inicio)
        ),
        'aviso' => SEGURIDAD_SESION_AVISO,
    ];
}

function seguridadTokenCsrf(): string
{
    seguridadIniciarSesion();

    if (
        !isset($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || strlen($_SESSION['csrf_token']) !== 64
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function seguridadCsrfValido(mixed $token): bool
{
    return is_string($token)
        && strlen($token) === 64
        && hash_equals(seguridadTokenCsrf(), $token);
}

function seguridadExigirCsrfPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!seguridadCsrfValido($token)) {
        http_response_code(403);
        exit('La solicitud no es válida. Actualice la página e inténtelo de nuevo.');
    }
}

function seguridadOrigenMismoSitio(): bool
{
    $origen = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $referencia = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $fuente = $origen !== '' ? $origen : $referencia;

    if ($fuente === '') {
        return false;
    }

    $esquemaActual = seguridadEsHttps() ? 'https' : 'http';
    $hostEncabezado = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $destino = parse_url($esquemaActual . '://' . $hostEncabezado);
    $origenParseado = parse_url($fuente);

    if (!is_array($destino) || !is_array($origenParseado)) {
        return false;
    }

    $hostFuente = strtolower((string) ($origenParseado['host'] ?? ''));
    $hostActual = strtolower((string) ($destino['host'] ?? ''));
    $esquemaFuente = strtolower((string) ($origenParseado['scheme'] ?? ''));
    $puertoFuente = (int) ($origenParseado['port'] ?? (
        $esquemaFuente === 'https' ? 443 : 80
    ));
    $puertoActual = (int) ($destino['port'] ?? (
        $esquemaActual === 'https' ? 443 : 80
    ));

    return $hostFuente !== ''
        && $hostActual !== ''
        && hash_equals($hostActual, $hostFuente)
        && hash_equals($esquemaActual, $esquemaFuente)
        && $puertoActual === $puertoFuente;
}

function seguridadExigirOrigenPost(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && !seguridadOrigenMismoSitio()
    ) {
        http_response_code(403);
        exit('Origen de solicitud no permitido.');
    }
}

/** @param list<int> $roles */
function seguridadExigirRol(array $roles): void
{
    $rol = (int) ($_SESSION['rol'] ?? 0);

    if (!in_array($rol, $roles, true)) {
        http_response_code(403);
        exit('Acceso denegado.');
    }
}

function seguridadPasswordValida(string $password): bool
{
    $longitud = function_exists('mb_strlen')
        ? mb_strlen($password, 'UTF-8')
        : strlen($password);

    return $longitud >= 12 && $longitud <= 128;
}

function seguridadTexto(mixed $valor, int $maximo): string
{
    $texto = trim((string) $valor);

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $maximo, 'UTF-8');
    }

    return substr($texto, 0, $maximo);
}

function seguridadIpCliente(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function seguridadDiagnosticoHabilitado(): bool
{
    return !seguridadEsProduccion()
        || (string) getenv('MESA_ENABLE_DIAGNOSTICS') === '1';
}

function seguridadDirectorioPrivado(string $subdirectorio = ''): string
{
    if (!defined('MESA_STORAGE_PATH')) {
        throw new RuntimeException(
            'El almacenamiento privado no está configurado.'
        );
    }

    $base = rtrim((string) constant('MESA_STORAGE_PATH'), '/\\');
    $subdirectorio = trim($subdirectorio, '/\\');

    return $subdirectorio === ''
        ? $base
        : $base . DIRECTORY_SEPARATOR . $subdirectorio;
}

function seguridadUrlImagenCatalogo(int $idCatalogo, mixed $ruta): string
{
    return $idCatalogo > 0 && trim((string) $ruta) !== ''
        ? 'imagenCatalogo.php?id=' . $idCatalogo
        : 'assets/images/default-catalog.svg';
}

function seguridadCerrarSesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => (bool) $parametros['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}
