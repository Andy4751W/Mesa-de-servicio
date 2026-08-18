<?php
declare(strict_types=1);

/**
 * Contexto de país (tenant) de la Mesa de Servicio.
 *
 * Los administradores seleccionan el país después de autenticarse. Los
 * gestores y solicitantes reciben el país configurado en su usuario. Todas
 * las validaciones críticas deben basarse en este contexto del servidor y no
 * en valores enviados por formularios o por la URL.
 */

const PAIS_OPERACION_COLOMBIA = 1;
const PAIS_OPERACION_PERU = 2;

function paisModuloInstalado(mysqli $conn): bool
{
    try {
        $tabla = $conn->query("SHOW TABLES LIKE 'paises_operacion'");
        $columnaUsuarios = $conn->query(
            "SHOW COLUMNS FROM usuarios LIKE 'id_pais_operacion'"
        );
        $columnaTickets = $conn->query(
            "SHOW COLUMNS FROM tickets LIKE 'id_pais_operacion'"
        );

        return $tabla !== false
            && $tabla->num_rows > 0
            && $columnaUsuarios !== false
            && $columnaUsuarios->num_rows > 0
            && $columnaTickets !== false
            && $columnaTickets->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** @return array<int, array<string, mixed>> */
function paisOperacionesActivas(mysqli $conn): array
{
    $resultado = $conn->query(
        "SELECT id_pais_operacion, codigo, nombre, zona_horaria,
                color_primario, color_secundario
         FROM paises_operacion
         WHERE estado = 'activo'
         ORDER BY orden, nombre"
    );

    return $resultado === false ? [] : $resultado->fetch_all(MYSQLI_ASSOC);
}

/** @return array<string, mixed>|null */
function paisBuscarOperacion(mysqli $conn, int $idPais): ?array
{
    if ($idPais < 1) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT id_pais_operacion, codigo, nombre, zona_horaria,
                color_primario, color_secundario
         FROM paises_operacion
         WHERE id_pais_operacion = ? AND estado = 'activo'
         LIMIT 1"
    );
    $stmt->bind_param('i', $idPais);
    $stmt->execute();
    $pais = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $pais ?: null;
}

/** @param array<string, mixed> $pais */
function paisGuardarContexto(array $pais): void
{
    $_SESSION['pais_operacion_id'] = (int) $pais['id_pais_operacion'];
    $_SESSION['pais_operacion_codigo'] = (string) $pais['codigo'];
    $_SESSION['pais_operacion_nombre'] = (string) $pais['nombre'];
    $_SESSION['pais_operacion_zona'] = (string) $pais['zona_horaria'];
    $_SESSION['pais_operacion_color'] = (string) $pais['color_primario'];
    $_SESSION['pais_operacion_color_secundario'] = (string) $pais['color_secundario'];

    $zona = trim((string) $pais['zona_horaria']);
    if ($zona !== '') {
        date_default_timezone_set($zona);
    }
}

function paisLimpiarContexto(): void
{
    foreach (
        [
            'pais_operacion_id',
            'pais_operacion_codigo',
            'pais_operacion_nombre',
            'pais_operacion_zona',
            'pais_operacion_color',
            'pais_operacion_color_secundario',
        ] as $clave
    ) {
        unset($_SESSION[$clave]);
    }
}

function paisContextoId(): int
{
    return (int) ($_SESSION['pais_operacion_id'] ?? 0);
}

function paisContextoNombre(): string
{
    return (string) ($_SESSION['pais_operacion_nombre'] ?? '');
}

function paisContextoCodigo(): string
{
    return (string) ($_SESSION['pais_operacion_codigo'] ?? '');
}

function paisZonaHorariaActual(): string
{
    $zona = trim((string) ($_SESSION['pais_operacion_zona'] ?? ''));

    return $zona !== '' ? $zona : 'America/Bogota';
}

function paisColorActual(): string
{
    return (string) ($_SESSION['pais_operacion_color'] ?? '#0f6fec');
}

function paisColorSecundarioActual(): string
{
    return (string) ($_SESSION['pais_operacion_color_secundario'] ?? '#facc15');
}

/** @param array<string, mixed> $usuario */
function paisInicializarUsuario(mysqli $conn, array $usuario): void
{
    $rol = (int) ($usuario['id_rol'] ?? 0);

    if ($rol === 1) {
        $paisActual = paisBuscarOperacion($conn, paisContextoId());
        if ($paisActual) {
            paisGuardarContexto($paisActual);
        } else {
            paisLimpiarContexto();
        }
        return;
    }

    $idPais = (int) ($usuario['id_pais_operacion'] ?? 0);
    $pais = paisBuscarOperacion($conn, $idPais);

    if (!$pais) {
        http_response_code(403);
        exit('Su usuario no tiene un país de operación activo asignado.');
    }

    paisGuardarContexto($pais);
}

function paisExigirContexto(): int
{
    $idPais = paisContextoId();

    if ($idPais < 1) {
        http_response_code(403);
        exit('Seleccione un país de operación antes de continuar.');
    }

    return $idPais;
}

function paisExigirTicket(mysqli $conn, int $idTicket): void
{
    $idPais = paisExigirContexto();
    $stmt = $conn->prepare(
        'SELECT 1 FROM tickets WHERE id_ticket = ? AND id_pais_operacion = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $idTicket, $idPais);
    $stmt->execute();
    $stmt->store_result();
    $permitido = $stmt->num_rows === 1;
    $stmt->close();

    if (!$permitido) {
        http_response_code(403);
        exit('El ticket no pertenece al país de operación seleccionado.');
    }
}

function paisRegistroPertenece(
    mysqli $conn,
    string $entidad,
    int $idRegistro
): bool {
    $entidades = [
        'catalogos' => 'id_catalogo',
        'configuraciones_servicio' => 'id_opcion',
        'feriados' => 'id_feriado',
        'procesos' => 'id_proceso',
        'servicios' => 'id_servicio',
        'sla' => 'id_sla',
        'tickets' => 'id_ticket',
    ];

    if ($idRegistro < 1 || !isset($entidades[$entidad])) {
        return false;
    }

    $idPais = paisExigirContexto();
    $columna = $entidades[$entidad];
    $stmt = $conn->prepare(
        "SELECT 1 FROM `{$entidad}`
         WHERE `{$columna}` = ? AND id_pais_operacion = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $idRegistro, $idPais);
    $stmt->execute();
    $stmt->store_result();
    $pertenece = $stmt->num_rows === 1;
    $stmt->close();

    return $pertenece;
}

function paisHtmlNombre(): string
{
    return htmlspecialchars(paisContextoNombre(), ENT_QUOTES, 'UTF-8');
}

function paisHtmlEstilo(): string
{
    $primario = htmlspecialchars(paisColorActual(), ENT_QUOTES, 'UTF-8');
    $secundario = htmlspecialchars(paisColorSecundarioActual(), ENT_QUOTES, 'UTF-8');

    return "--pais-primary:{$primario};--pais-secondary:{$secundario};";
}
