<?php
declare(strict_types=1);

require_once __DIR__ . '/pais.php';

/**
 * Calendario laboral común de la mesa de servicio.
 *
 * Jornada base: lunes a viernes, de 08:00 a 18:00 en la zona horaria del país.
 * Sábados y domingos siempre son no laborables. Cualquier festivo o periodo
 * adicional solo se excluye si está activo en la tabla `feriados`.
 */

const CALENDARIO_HORA_INICIO = 8;
const CALENDARIO_HORA_FIN = 18;
const CALENDARIO_MINUTOS_DIA_SLA = 600;

function calendarioVersion(): string
{
    return '2026.08.05.1';
}

function calendarioZonaHoraria(): DateTimeZone
{
    static $zonas = [];
    $nombre = paisZonaHorariaActual();

    if (!isset($zonas[$nombre])) {
        $zonas[$nombre] = new DateTimeZone($nombre);
    }

    return $zonas[$nombre];
}

function calendarioTablaExiste(mysqli $conn, string $tabla): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla)) {
        return false;
    }

    try {
        $patron = $conn->real_escape_string(addcslashes($tabla, '\\_%'));
        $resultado = $conn->query("SHOW TABLES LIKE '{$patron}'");

        return $resultado !== false && $resultado->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return array<int, array{
 *     nombre: string,
 *     inicio: DateTimeImmutable,
 *     fin: DateTimeImmutable
 * }>
 */
function calendarioFeriadosActivos(mysqli $conn): array
{
    static $cache = [];
    $idPais = paisExigirContexto();
    $clave = spl_object_id($conn) . ':' . $idPais;

    if (array_key_exists($clave, $cache)) {
        return $cache[$clave];
    }

    $cache[$clave] = [];

    try {
        $stmt = $conn->prepare(
            "SELECT id_feriado, nombre, tipo, fecha_inicio, fecha_fin
             FROM feriados
             WHERE id_pais_operacion = ?
               AND LOWER(TRIM(CAST(estado AS CHAR)))
                   IN ('activo', 'habilitado', '1')
             ORDER BY fecha_inicio ASC"
        );
        $stmt->bind_param('i', $idPais);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if (!$resultado) {
            return $cache[$clave];
        }

        $zona = calendarioZonaHoraria();

        while ($fila = $resultado->fetch_assoc()) {
            try {
                $inicio = new DateTimeImmutable(
                    (string) $fila['fecha_inicio'],
                    $zona
                );
                $fin = new DateTimeImmutable(
                    (string) $fila['fecha_fin'],
                    $zona
                );

                /*
                 * Compatibilidad con registros antiguos de día completo que
                 * pudieron guardar la misma fecha en inicio y fin. El formulario
                 * actual ya guarda el final como medianoche exclusiva del día
                 * siguiente.
                 */
                if (
                    (string) ($fila['tipo'] ?? '') === 'dia_completo'
                    && $fin <= $inicio
                ) {
                    $inicio = $inicio->setTime(0, 0);
                    $fin = $inicio->modify('+1 day');
                }

                if ($fin > $inicio) {
                    $cache[$clave][] = [
                        'nombre' => trim((string) ($fila['nombre'] ?? '')),
                        'inicio' => $inicio,
                        'fin' => $fin,
                    ];
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log(
            'No fue posible leer los feriados del calendario SLA: '
            . $e->getMessage()
        );
        return $cache[$clave];
    }

    return $cache[$clave];
}

/**
 * Consulta directamente los feriados que se cruzan con una jornada concreta.
 * La caché es únicamente por fecha y por petición PHP; nunca se reutiliza entre
 * recargas del navegador.
 *
 * @return array<int, array{
 *     nombre: string,
 *     inicio: DateTimeImmutable,
 *     fin: DateTimeImmutable
 * }>
 */
function calendarioFeriadosDelDia(
    mysqli $conn,
    DateTimeImmutable $inicioJornada,
    DateTimeImmutable $finJornada
): array {
    static $cacheDias = [];

    $clave = spl_object_id($conn)
        . ':'
        . paisExigirContexto()
        . ':'
        . $inicioJornada->format('Y-m-d');

    if (array_key_exists($clave, $cacheDias)) {
        return $cacheDias[$clave];
    }

    $cacheDias[$clave] = [];

    foreach (calendarioFeriadosActivos($conn) as $feriado) {
        if (
            $feriado['inicio'] < $finJornada
            && $feriado['fin'] > $inicioJornada
        ) {
            $cacheDias[$clave][] = $feriado;
        }
    }

    return $cacheDias[$clave];
}

/**
 * Devuelve los tramos hábiles efectivos de un día, después de restar feriados.
 *
 * @return array<int, array{0: DateTimeImmutable, 1: DateTimeImmutable}>
 */
function calendarioIntervalosDelDia(
    mysqli $conn,
    DateTimeImmutable $dia
): array {
    $dia = $dia->setTimezone(calendarioZonaHoraria())->setTime(0, 0);

    if ((int) $dia->format('N') > 5) {
        return [];
    }

    $inicioJornada = $dia->setTime(CALENDARIO_HORA_INICIO, 0);
    $finJornada = $dia->setTime(CALENDARIO_HORA_FIN, 0);
    $intervalos = [[$inicioJornada, $finJornada]];

    foreach (
        calendarioFeriadosDelDia($conn, $inicioJornada, $finJornada)
        as $feriado
    ) {
        $inicioFeriado = $feriado['inicio'];
        $finFeriado = $feriado['fin'];

        if ($finFeriado <= $inicioJornada || $inicioFeriado >= $finJornada) {
            continue;
        }

        $restantes = [];

        foreach ($intervalos as [$inicioTramo, $finTramo]) {
            if ($finFeriado <= $inicioTramo || $inicioFeriado >= $finTramo) {
                $restantes[] = [$inicioTramo, $finTramo];
                continue;
            }

            if ($inicioFeriado > $inicioTramo) {
                $restantes[] = [
                    $inicioTramo,
                    $inicioFeriado < $finTramo
                        ? $inicioFeriado
                        : $finTramo,
                ];
            }

            if ($finFeriado < $finTramo) {
                $restantes[] = [
                    $finFeriado > $inicioTramo
                        ? $finFeriado
                        : $inicioTramo,
                    $finTramo,
                ];
            }
        }

        $intervalos = array_values(array_filter(
            $restantes,
            static fn (array $tramo): bool => $tramo[1] > $tramo[0]
        ));

        if (!$intervalos) {
            break;
        }
    }

    return $intervalos;
}

function calendarioMinutosSla(int $tiempo, string $unidad): int
{
    if ($tiempo < 1) {
        return 0;
    }

    return match ($unidad) {
        'minutos' => $tiempo,
        'horas' => $tiempo * 60,
        default => 0,
    };
}

/**
 * Suma días hábiles completos. El día de activación o reanudación no se
 * cuenta y el vencimiento queda al cierre del último día hábil. Por ejemplo,
 * un SLA de tres días reanudado el 04/08/2026 vence el 07/08/2026 a las
 * 18:00 si no existe un festivo activo registrado para alguna de esas fechas.
 */
function calcularVencimientoSlaDias(
    mysqli $conn,
    DateTimeImmutable $inicio,
    int $diasPendientes
): ?string {
    if ($diasPendientes < 1) {
        return null;
    }

    $dia = $inicio->setTime(0, 0)->modify('+1 day');

    for ($contador = 0; $contador < 3660; $contador++) {
        $intervalos = calendarioIntervalosDelDia($conn, $dia);

        if (!$intervalos) {
            $dia = $dia->modify('+1 day')->setTime(0, 0);
            continue;
        }

        $diasPendientes--;

        if ($diasPendientes > 0) {
            $dia = $dia->modify('+1 day')->setTime(0, 0);
            continue;
        }

        $ultimoIntervalo = $intervalos[count($intervalos) - 1];
        return $ultimoIntervalo[1]->format('Y-m-d H:i:s');
    }

    return null;
}

function calcularVencimientoSla(
    mysqli $conn,
    ?string $fechaInicio,
    int $tiempo,
    string $unidad
): ?string {
    if (!$fechaInicio || $tiempo < 1) {
        return null;
    }

    try {
        $cursor = new DateTimeImmutable(
            $fechaInicio,
            calendarioZonaHoraria()
        );
    } catch (Throwable $e) {
        return null;
    }

    if ($unidad === 'dias') {
        return calcularVencimientoSlaDias($conn, $cursor, $tiempo);
    }

    $minutos = calendarioMinutosSla($tiempo, $unidad);

    if ($minutos < 1) {
        return null;
    }

    $segundosPendientes = $minutos * 60;
    $dia = $cursor->setTime(0, 0);

    for ($contador = 0; $contador < 3660; $contador++) {
        foreach (calendarioIntervalosDelDia($conn, $dia) as [$inicio, $fin]) {
            $desde = $cursor > $inicio ? $cursor : $inicio;

            if ($desde >= $fin) {
                continue;
            }

            $disponibles = $fin->getTimestamp() - $desde->getTimestamp();

            if ($segundosPendientes <= $disponibles) {
                return $desde
                    ->modify('+' . $segundosPendientes . ' seconds')
                    ->format('Y-m-d H:i:s');
            }

            $segundosPendientes -= $disponibles;
            $cursor = $fin;
        }

        $dia = $dia->modify('+1 day')->setTime(0, 0);
        $cursor = $dia;
    }

    return null;
}

function minutosHabilesTranscurridos(
    mysqli $conn,
    string $fechaInicio,
    ?DateTimeImmutable $fechaFin = null
): int {
    try {
        $inicio = new DateTimeImmutable(
            $fechaInicio,
            calendarioZonaHoraria()
        );
    } catch (Throwable $e) {
        return 0;
    }

    $fin = ($fechaFin ?? new DateTimeImmutable('now', calendarioZonaHoraria()))
        ->setTimezone(calendarioZonaHoraria());

    if ($inicio >= $fin) {
        return 0;
    }

    $segundos = 0;
    $dia = $inicio->setTime(0, 0);
    $ultimoDia = $fin->setTime(0, 0);

    while ($dia <= $ultimoDia) {
        foreach (calendarioIntervalosDelDia($conn, $dia) as [$desde, $hasta]) {
            $inicioEfectivo = $inicio > $desde ? $inicio : $desde;
            $finEfectivo = $fin < $hasta ? $fin : $hasta;

            if ($finEfectivo > $inicioEfectivo) {
                $segundos += $finEfectivo->getTimestamp()
                    - $inicioEfectivo->getTimestamp();
            }
        }

        $dia = $dia->modify('+1 day')->setTime(0, 0);
    }

    return (int) floor($segundos / 60);
}

function horasHabilesCalendario(
    mysqli $conn,
    string $fechaInicio,
    ?DateTimeImmutable $fechaFin = null
): float {
    return minutosHabilesTranscurridos($conn, $fechaInicio, $fechaFin) / 60;
}
