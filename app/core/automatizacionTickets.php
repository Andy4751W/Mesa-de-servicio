<?php
declare(strict_types=1);

require_once __DIR__ . '/motorFlujos.php';

/**
 * Automatización común para el cierre de tickets sin respuesta.
 *
 * Se cuentan únicamente las horas entre las 08:00 y las 18:00,
 * de lunes a viernes, usando la zona horaria de cada operación.
 */

const HORAS_HABILES_CIERRE_TICKET = 48.0;
const HORA_INICIO_LABORAL = 8;
const HORA_FIN_LABORAL = 18;

function tablaAutomatizacionExiste(mysqli $conn, string $tabla): bool
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

function columnaAutomatizacionExiste(
    mysqli $conn,
    string $tabla,
    string $columna
): bool {
    if (
        !preg_match('/^[A-Za-z0-9_]+$/', $tabla)
        || !preg_match('/^[A-Za-z0-9_]+$/', $columna)
    ) {
        return false;
    }

    try {
        $patron = $conn->real_escape_string(addcslashes($columna, '\\_%'));
        $resultado = $conn->query(
            "SHOW COLUMNS FROM `{$tabla}` LIKE '{$patron}'"
        );

        return $resultado !== false && $resultado->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function horasHabilesTranscurridas(
    string $fechaInicio,
    ?DateTimeImmutable $fechaFin = null,
    string $zonaHoraria = 'America/Bogota'
): float {
    try {
        $zona = new DateTimeZone($zonaHoraria);
        $inicio = new DateTimeImmutable($fechaInicio, $zona);
    } catch (Throwable $e) {
        return 0.0;
    }

    $fin = $fechaFin ?? new DateTimeImmutable('now', $zona);

    if ($inicio >= $fin) {
        return 0.0;
    }

    $horas = 0.0;
    $dia = $inicio->setTime(0, 0);
    $ultimoDia = $fin->setTime(0, 0);

    while ($dia <= $ultimoDia) {
        $numeroDia = (int) $dia->format('N');

        if ($numeroDia <= 5) {
            $inicioJornada = $dia->setTime(HORA_INICIO_LABORAL, 0);
            $finJornada = $dia->setTime(HORA_FIN_LABORAL, 0);
            $desde = $inicio > $inicioJornada ? $inicio : $inicioJornada;
            $hasta = $fin < $finJornada ? $fin : $finJornada;

            if ($hasta > $desde) {
                $horas += ($hasta->getTimestamp() - $desde->getTimestamp())
                    / 3600;
            }
        }

        $dia = $dia->modify('+1 day');
    }

    return $horas;
}

function procesarCierresAutomaticos(
    mysqli $conn,
    ?int $idPaisOperacion = null
): int
{
    $estructuraLista = tablaAutomatizacionExiste(
        $conn,
        'solicitud_historial'
    )
        && tablaAutomatizacionExiste($conn, 'solicitud_calificaciones')
        && columnaAutomatizacionExiste(
            $conn,
            'tickets',
            'esperando_solicitante_desde'
        )
        && columnaAutomatizacionExiste($conn, 'tickets', 'cierre_tipo')
        && columnaAutomatizacionExiste($conn, 'tickets', 'motivo_cierre');

    if (!$estructuraLista) {
        return 0;
    }

    /*
     * Los tickets con atención por etapas solo se cierran cuando el
     * solicitante califica todas las áreas. La regla heredada de 48 horas no
     * debe saltarse ese requisito.
     */
    $excluirFlujos = columnaAutomatizacionExiste(
        $conn,
        'tickets',
        'id_proceso'
    )
        ? ' AND t.id_proceso IS NULL'
        : '';

    $filtroPais = $idPaisOperacion !== null && $idPaisOperacion > 0
        ? ' AND t.id_pais_operacion = ' . (int) $idPaisOperacion
        : '';

    $resultado = $conn->query(
        "SELECT
            t.id_ticket,
            t.id_pais_operacion,
            t.esperando_solicitante_desde,
            COALESCE(po.zona_horaria, 'America/Bogota') AS zona_horaria
         FROM tickets AS t
         LEFT JOIN paises_operacion AS po
            ON po.id_pais_operacion = t.id_pais_operacion
         LEFT JOIN solicitud_calificaciones AS c
            ON c.id_ticket = t.id_ticket
         WHERE t.estado IN ('en_espera', 'resuelta')
           AND t.esperando_solicitante_desde IS NOT NULL
           AND c.id_calificacion IS NULL"
        . $excluirFlujos
        . $filtroPais
    );

    if (!$resultado) {
        return 0;
    }

    $cerrados = 0;

    while ($ticket = $resultado->fetch_assoc()) {
        $horas = horasHabilesTranscurridas(
            (string) $ticket['esperando_solicitante_desde'],
            null,
            (string) $ticket['zona_horaria']
        );

        if ($horas < HORAS_HABILES_CIERRE_TICKET) {
            continue;
        }

        $idTicket = (int) $ticket['id_ticket'];
        $idPaisTicket = (int) $ticket['id_pais_operacion'];

        try {
            $conn->begin_transaction();

            $motivo = 'Cierre automático por 48 horas hábiles sin respuesta del solicitante.';
            $stmt = $conn->prepare(
                "UPDATE tickets
                 SET estado = 'cerrada',
                     fecha_finalizacion = COALESCE(fecha_finalizacion, NOW()),
                     cierre_tipo = 'automatico',
                     motivo_cierre = ?,
                     esperando_solicitante_desde = NULL
                 WHERE id_ticket = ?
                   AND id_pais_operacion = ?
                   AND estado IN ('en_espera', 'resuelta')"
            );
            $stmt->bind_param('sii', $motivo, $idTicket, $idPaisTicket);
            $stmt->execute();
            $afectados = $stmt->affected_rows;
            $stmt->close();

            if ($afectados > 0) {
                $accion = 'Cierre automático';
                flujoRegistrarHistorial(
                    $conn,
                    $idTicket,
                    null,
                    $accion,
                    $motivo
                );
                $cerrados++;
            }

            $conn->commit();
        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                // La transacción pudo no haberse iniciado.
            }

            error_log(
                "No fue posible cerrar automáticamente el ticket {$idTicket}: "
                . $e->getMessage()
            );
        }
    }

    return $cerrados;
}
