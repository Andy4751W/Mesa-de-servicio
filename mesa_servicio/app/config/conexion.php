<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/seguridad.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$rutaConfiguracion = (string) (getenv('MESA_CONFIG_FILE') ?: '');

if ($rutaConfiguracion === '') {
    $rutaConfiguracion = __DIR__ . '/configuracion.local.php';
}

$configuracionLocal = [];

if (is_file($rutaConfiguracion)) {
    $configuracionLeida = require $rutaConfiguracion;

    if (is_array($configuracionLeida)) {
        $configuracionLocal = $configuracionLeida;
    }
}

$valorConexion = static function (
    string $variable,
    string $clave,
    string $predeterminado = ''
) use ($configuracionLocal): string {
    $entorno = getenv($variable);

    if ($entorno !== false && $entorno !== '') {
        return (string) $entorno;
    }

    return isset($configuracionLocal[$clave])
        ? (string) $configuracionLocal[$clave]
        : $predeterminado;
};

$servidor = $valorConexion('MESA_DB_HOST', 'db_host', 'localhost');
$puerto = (int) $valorConexion('MESA_DB_PORT', 'db_port', '3306');
$usuario = $valorConexion('MESA_DB_USER', 'db_user');
$password = $valorConexion('MESA_DB_PASSWORD', 'db_password');
$baseDatos = $valorConexion('MESA_DB_NAME', 'db_name', 'mesa_servicio');
$almacenamientoConfigurado = (
    (getenv('MESA_STORAGE_PATH') !== false
        && (string) getenv('MESA_STORAGE_PATH') !== '')
    || !empty($configuracionLocal['storage_path'])
);
$almacenamiento = $valorConexion(
    'MESA_STORAGE_PATH',
    'storage_path',
    dirname(__DIR__) . '/mesa_servicio_private'
);

if (
    $usuario === ''
    || $baseDatos === ''
    || (
        seguridadEsProduccion()
        && (strtolower($usuario) === 'root' || $password === '')
    )
) {
    error_log('Configuración de base de datos insegura o incompleta.');
    http_response_code(503);
    exit('El servicio no se encuentra disponible temporalmente.');
}

$rutaAbsoluta = str_starts_with($almacenamiento, '/')
    || preg_match('/^[A-Za-z]:[\\\\\/]/', $almacenamiento) === 1;
$documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
$almacenamientoNormalizado = rtrim(
    str_replace('\\', '/', $almacenamiento),
    '/'
);
$documentRootNormalizado = rtrim(
    str_replace('\\', '/', $documentRoot),
    '/'
);
$dentroDelDirectorioPublico = $documentRootNormalizado !== ''
    && (
        $almacenamientoNormalizado === $documentRootNormalizado
        || str_starts_with(
            $almacenamientoNormalizado . '/',
            $documentRootNormalizado . '/'
        )
    );

if (
    !$rutaAbsoluta
    || (seguridadEsProduccion() && !$almacenamientoConfigurado)
    || (seguridadEsProduccion() && $dentroDelDirectorioPublico)
) {
    error_log('La ruta de almacenamiento privado es insegura o inválida.');
    http_response_code(503);
    exit('El servicio no se encuentra disponible temporalmente.');
}

if (!defined('MESA_STORAGE_PATH')) {
    define('MESA_STORAGE_PATH', $almacenamientoNormalizado);
}

try {
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $conn->real_connect(
        $servidor,
        $usuario,
        $password,
        $baseDatos,
        $puerto
    );
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '-05:00'");
} catch (Throwable $e) {
    error_log('Error de conexión a la base de datos: ' . $e->getMessage());
    http_response_code(503);
    exit('El servicio no se encuentra disponible temporalmente.');
}
