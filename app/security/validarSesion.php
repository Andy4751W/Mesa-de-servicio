<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';

seguridadAplicarCabeceras(true);
seguridadIniciarSesion();
seguridadTokenCsrf();

require_once APP_ROOT . '/config/conexion.php';
require_once APP_ROOT . '/core/pais.php';

if (!paisModuloInstalado($conn)) {
    http_response_code(503);
    exit('La separación por país todavía no está instalada. Ejecute la migración 005.');
}

function cerrarSesionYVolver(string $error): never
{
    seguridadCerrarSesion();
    header('Location: login.html?error=' . rawurlencode($error));
    exit;
}

$ahora = time();
$ultimaRotacion = (int) ($_SESSION['ultima_rotacion'] ?? $ahora);
$agenteActual = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
$agenteSesion = (string) ($_SESSION['agente_sesion'] ?? $agenteActual);
$motivoVencimiento = seguridadMotivoVencimiento($ahora);

if (
    $motivoVencimiento !== null
    || !hash_equals($agenteSesion, $agenteActual)
) {
    cerrarSesionYVolver(
        $motivoVencimiento ?? 'sesion_expirada'
    );
}

if ($ahora - $ultimaRotacion > 900) {
    session_regenerate_id(true);
    $_SESSION['ultima_rotacion'] = $ahora;
}

$_SESSION['agente_sesion'] = $agenteActual;

$idUsuario = filter_var(
    $_SESSION['usuario_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$idUsuario) {
    cerrarSesionYVolver('credenciales');
}

try {
    $stmt = $conn->prepare(
        "SELECT nombre, id_rol, id_pais_operacion, estado
         FROM usuarios
         WHERE id_usuario = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmt->close();

    $estado = strtolower(trim((string) ($usuario['estado'] ?? '')));

    if (!$usuario || $estado !== 'activo') {
        cerrarSesionYVolver('sesion_inhabilitada');
    }

    $rolActual = (int) ($usuario['id_rol'] ?? 0);

    if (!in_array($rolActual, [1, 2, 3], true)) {
        cerrarSesionYVolver('rol');
    }

    $_SESSION['rol'] = $rolActual;
    $_SESSION['usuario'] = (string) ($usuario['nombre'] ?? 'Usuario');

    paisInicializarUsuario($conn, $usuario);

    if (
        $rolActual === 1
        && paisContextoId() < 1
        && !defined('MESA_PERMITE_SIN_PAIS')
    ) {
        header('Location: seleccionarPais.php', true, 303);
        exit;
    }
} catch (Throwable $e) {
    error_log('No fue posible validar la sesión: ' . $e->getMessage());
    cerrarSesionYVolver('sistema');
}

/* Toda modificación autenticada exige origen propio y token antifalsificación. */
seguridadExigirOrigenPost();
seguridadExigirCsrfPost();
