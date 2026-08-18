<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/seguridad.php';

seguridadAplicarCabeceras(true);
seguridadIniciarSesion();
seguridadExigirOrigenPost();

require_once APP_ROOT . '/config/conexion.php';

function regresarAlLogin(string $error): never
{
    $erroresPermitidos = [
        'campos',
        'credenciales',
        'demasiados_intentos',
        'rol',
        'sistema',
    ];

    if (!in_array($error, $erroresPermitidos, true)) {
        $error = 'sistema';
    }

    header('Location: login.html?error=' . rawurlencode($error), true, 303);
    exit;
}

function claveIntentoLogin(string $email): string
{
    return hash(
        'sha256',
        strtolower(trim($email)) . '|' . seguridadIpCliente()
    );
}

function controlIntentosLoginDisponible(mysqli $conn): bool
{
    try {
        $resultado = $conn->query(
            "SELECT 1 FROM seguridad_intentos_login LIMIT 1"
        );

        return $resultado !== false;
    } catch (Throwable $e) {
        error_log(
            'La tabla de control de intentos no está disponible: '
            . $e->getMessage()
        );

        return false;
    }
}

function loginBloqueado(mysqli $conn, string $clave): bool
{
    try {
        $stmt = $conn->prepare(
            "SELECT bloqueado_hasta
             FROM seguridad_intentos_login
             WHERE clave = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $clave);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $fila
            && !empty($fila['bloqueado_hasta'])
            && strtotime((string) $fila['bloqueado_hasta']) > time();
    } catch (Throwable $e) {
        error_log('Control de intentos de acceso no disponible: ' . $e->getMessage());

        return false;
    }
}

function registrarFalloLogin(mysqli $conn, string $clave): void
{
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare(
            "SELECT intentos, ultimo_intento
             FROM seguridad_intentos_login
             WHERE clave = ?
             FOR UPDATE"
        );
        $stmt->bind_param('s', $clave);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $ultimo = $fila ? strtotime((string) $fila['ultimo_intento']) : 0;
        $intentos = (!$ultimo || time() - $ultimo > 900)
            ? 1
            : ((int) $fila['intentos'] + 1);
        $bloqueadoHasta = $intentos >= 5
            ? date('Y-m-d H:i:s', time() + 900)
            : null;

        $stmt = $conn->prepare(
            "INSERT INTO seguridad_intentos_login
                (clave, intentos, ultimo_intento, bloqueado_hasta)
             VALUES (?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE
                intentos = VALUES(intentos),
                ultimo_intento = NOW(),
                bloqueado_hasta = VALUES(bloqueado_hasta)"
        );
        $stmt->bind_param('sis', $clave, $intentos, $bloqueadoHasta);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignorado) {
        }
        error_log('No fue posible registrar el intento de acceso: ' . $e->getMessage());
    }
}

function limpiarFallosLogin(mysqli $conn, string $clave): void
{
    try {
        $stmt = $conn->prepare(
            'DELETE FROM seguridad_intentos_login WHERE clave = ?'
        );
        $stmt->bind_param('s', $clave);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('No fue posible limpiar los intentos de acceso: ' . $e->getMessage());
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: login.html');
    exit;
}

$identificador = seguridadTexto($_POST['email'] ?? '', 190);
$esAliasAdministrador = strcasecmp($identificador, 'Admin') === 0;
$email = $esAliasAdministrador ? 'admin' : strtolower($identificador);
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '' || strlen($password) > 1024) {
    regresarAlLogin('campos');
}

/*
 * "Admin" es un alias reservado exclusivamente para la cuenta
 * administradora principal. Los demás usuarios deben continuar ingresando
 * con un correo electrónico válido.
 */
if (!$esAliasAdministrador && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    regresarAlLogin('credenciales');
}

if (!controlIntentosLoginDisponible($conn) && seguridadEsProduccion()) {
    regresarAlLogin('sistema');
}

$claveIntento = claveIntentoLogin($email);

if (loginBloqueado($conn, $claveIntento)) {
    regresarAlLogin('demasiados_intentos');
}

try {
    if ($esAliasAdministrador) {
        $stmt = $conn->prepare(
            "SELECT id_usuario, nombre, password, id_rol, estado
             FROM usuarios
             WHERE id_rol = 1
             ORDER BY id_usuario ASC
             LIMIT 1"
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT id_usuario, nombre, password, id_rol, estado
             FROM usuarios
             WHERE email = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $email);
    }
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /*
     * El hash señuelo evita que el tiempo de respuesta revele si un correo
     * existe. Aunque la contraseña coincida con el señuelo, $usuario seguirá
     * siendo falso y el acceso será rechazado.
     */
    $hashVerificacion = $usuario
        ? (string) $usuario['password']
        : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    /*
     * Tanto el alias "Admin" como el correo del administrador validan la
     * misma contraseña almacenada en usuarios.password. De esta forma, un
     * cambio realizado desde Administración de usuarios se aplica de
     * inmediato a ambas maneras de iniciar sesión.
     */
    $passwordCorrecto = password_verify($password, $hashVerificacion);
    $credencialesValidas = $usuario
        && $passwordCorrecto
        && (!$esAliasAdministrador || (int) $usuario['id_rol'] === 1)
        && strtolower(trim((string) $usuario['estado'])) === 'activo';

    if (!$credencialesValidas) {
        registrarFalloLogin($conn, $claveIntento);
        regresarAlLogin('credenciales');
    }

    $rol = (int) $usuario['id_rol'];
    $panelesPorRol = [
        1 => 'seleccionarPais.php',
        2 => 'panelGestor.php',
        3 => 'panelSolicitante.php?vista=tickets',
    ];

    if (!isset($panelesPorRol[$rol])) {
        registrarFalloLogin($conn, $claveIntento);
        regresarAlLogin('rol');
    }

    if (password_needs_rehash((string) $usuario['password'], PASSWORD_DEFAULT)) {
        $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'UPDATE usuarios SET password = ? WHERE id_usuario = ?'
        );
        $idUsuario = (int) $usuario['id_usuario'];
        $stmt->bind_param('si', $nuevoHash, $idUsuario);
        $stmt->execute();
        $stmt->close();
    }

    limpiarFallosLogin($conn, $claveIntento);
    session_regenerate_id(true);
    $ahora = time();
    $_SESSION['usuario_id'] = (int) $usuario['id_usuario'];
    $_SESSION['usuario'] = (string) $usuario['nombre'];
    $_SESSION['rol'] = $rol;
    $_SESSION['inicio_sesion'] = $ahora;
    $_SESSION['ultima_interaccion'] = $ahora;
    $_SESSION['ultima_rotacion'] = $ahora;
    $_SESSION['mesa_alertar_novedades_al_ingresar'] = true;
    $_SESSION['agente_sesion'] = hash(
        'sha256',
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: ' . $panelesPorRol[$rol], true, 303);
    exit;
} catch (Throwable $e) {
    error_log('Error durante el inicio de sesión: ' . $e->getMessage());
    regresarAlLogin('sistema');
}
