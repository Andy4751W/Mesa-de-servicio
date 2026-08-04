<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/automatizacionTickets.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 3) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Location: flujoTicket.php', true, 303);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparSolicitante(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirigirSolicitante(
    string $mensaje,
    string $vista = 'nueva',
    int $idTicket = 0
): never {
    $parametros = [
        'vista' => $vista,
        'msg' => $mensaje,
    ];

    if ($idTicket > 0) {
        $parametros['id_ticket'] = $idTicket;
    }

    header('Location: panelSolicitante.php?' . http_build_query($parametros));
    exit;
}

function tablaSolicitanteExiste(mysqli $conn, string $tabla): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla)) {
        return false;
    }

    try {
        $patron = $conn->real_escape_string(addcslashes($tabla, '\\_%'));
        $resultado = $conn->query(
            "SHOW TABLES FROM `mesa_servicio` LIKE '{$patron}'"
        );

        return $resultado !== false && $resultado->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function columnaSolicitanteExiste(
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
            "SHOW COLUMNS FROM `{$tabla}` FROM `mesa_servicio`
             LIKE '{$patron}'"
        );

        return $resultado !== false && $resultado->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function formatoFechaSolicitante(?string $fecha): string
{
    if (!$fecha) {
        return 'Sin fecha';
    }

    $marca = strtotime($fecha);

    return $marca ? date('d/m/Y H:i', $marca) : $fecha;
}

function formatoTamanoSolicitante(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    return number_format(max($bytes, 0) / 1024, 1) . ' KB';
}

function normalizarArchivosSolicitante(array $entrada): array
{
    if (!isset($entrada['name'])) {
        return [];
    }

    if (!is_array($entrada['name'])) {
        return [[
            'name' => $entrada['name'] ?? '',
            'type' => $entrada['type'] ?? '',
            'tmp_name' => $entrada['tmp_name'] ?? '',
            'error' => $entrada['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $entrada['size'] ?? 0,
        ]];
    }

    $archivos = [];

    foreach ($entrada['name'] as $indice => $nombre) {
        $archivos[] = [
            'name' => $nombre,
            'type' => $entrada['type'][$indice] ?? '',
            'tmp_name' => $entrada['tmp_name'][$indice] ?? '',
            'error' => $entrada['error'][$indice] ?? UPLOAD_ERR_NO_FILE,
            'size' => $entrada['size'][$indice] ?? 0,
        ];
    }

    return $archivos;
}

function hayArchivosSolicitante(array $entrada): bool
{
    foreach (normalizarArchivosSolicitante($entrada) as $archivo) {
        if ((int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return true;
        }
    }

    return false;
}

function guardarAdjuntosSolicitante(
    mysqli $conn,
    int $idTicket,
    int $idUsuario,
    array $entrada,
    array &$rutasFisicas
): int {
    $archivos = array_values(array_filter(
        normalizarArchivosSolicitante($entrada),
        static fn (array $archivo): bool =>
            (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ));

    if (!$archivos) {
        return 0;
    }

    if (count($archivos) > 5) {
        throw new RuntimeException(
            'Puede adjuntar máximo 5 archivos por envío.'
        );
    }

    $extensionesPermitidas = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/msword' => 'doc',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];

    $directorio = seguridadDirectorioPrivado('solicitudes');

    if (
        !is_dir($directorio)
        && !mkdir($directorio, 0750, true)
        && !is_dir($directorio)
    ) {
        throw new RuntimeException(
            'No fue posible crear la carpeta para los adjuntos.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $guardados = 0;

    foreach ($archivos as $archivo) {
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Uno de los archivos no pudo cargarse correctamente.'
            );
        }

        $tamano = (int) ($archivo['size'] ?? 0);

        if ($tamano < 1 || $tamano > 5 * 1024 * 1024) {
            throw new RuntimeException(
                'Cada archivo debe pesar máximo 5 MB.'
            );
        }

        $temporal = (string) ($archivo['tmp_name'] ?? '');
        $tipoMime = (string) $finfo->file($temporal);

        if (!isset($extensionesPermitidas[$tipoMime])) {
            throw new RuntimeException(
                'Formato no permitido. Use PDF, imagen, TXT, CSV, ZIP, Word, Excel o PowerPoint.'
            );
        }

        $nombreOriginal = trim(basename((string) ($archivo['name'] ?? 'adjunto')));

        if ($nombreOriginal === '') {
            $nombreOriginal = 'adjunto.' . $extensionesPermitidas[$tipoMime];
        }

        if (strlen($nombreOriginal) > 255) {
            $nombreOriginal = substr($nombreOriginal, 0, 240)
                . '.' . $extensionesPermitidas[$tipoMime];
        }

        $nombreGuardado = 'solicitud_' . $idTicket . '_'
            . bin2hex(random_bytes(12))
            . '.' . $extensionesPermitidas[$tipoMime];
        $rutaRelativa = 'private/solicitudes/' . $nombreGuardado;
        $rutaFisica = $directorio . DIRECTORY_SEPARATOR . $nombreGuardado;

        if (!move_uploaded_file($temporal, $rutaFisica)) {
            throw new RuntimeException(
                'No fue posible guardar uno de los archivos.'
            );
        }

        @chmod($rutaFisica, 0640);

        $rutasFisicas[] = $rutaFisica;

        $stmt = $conn->prepare(
            "INSERT INTO solicitud_adjuntos
                (
                    id_ticket,
                    id_usuario,
                    nombre_original,
                    nombre_guardado,
                    ruta,
                    tipo_mime,
                    tamano
                )
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'iissssi',
            $idTicket,
            $idUsuario,
            $nombreOriginal,
            $nombreGuardado,
            $rutaRelativa,
            $tipoMime,
            $tamano
        );
        $stmt->execute();
        $stmt->close();
        $guardados++;
    }

    return $guardados;
}

function registrarHistorialSolicitante(
    mysqli $conn,
    int $idTicket,
    int $idUsuario,
    string $accion,
    string $detalle = ''
): void {
    $stmt = $conn->prepare(
        "INSERT INTO solicitud_historial
            (id_ticket, id_usuario, accion, detalle)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('iiss', $idTicket, $idUsuario, $accion, $detalle);
    $stmt->execute();
    $stmt->close();
}

function obtenerTicketPropioSolicitante(
    mysqli $conn,
    int $idTicket,
    int $idUsuario
): ?array {
    $condicionSinFlujo = columnaSolicitanteExiste(
        $conn,
        'tickets',
        'id_proceso'
    ) ? ' AND id_proceso IS NULL' : '';
    $stmt = $conn->prepare(
        "SELECT id_ticket, estado
         FROM tickets
         WHERE id_ticket = ?
           AND id_usuario = ?"
        . $condicionSinFlujo .
        "
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idTicket, $idUsuario);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $ticket ?: null;
}

$idUsuario = (int) $_SESSION['usuario_id'];
$nombreUsuario = trim((string) ($_SESSION['usuario'] ?? 'Solicitante'));
$tienePrioridadAnterior = columnaSolicitanteExiste(
    $conn,
    'tickets',
    'prioridad'
);

$moduloInstalado = tablaSolicitanteExiste($conn, 'tickets')
    && tablaSolicitanteExiste($conn, 'catalogos')
    && tablaSolicitanteExiste($conn, 'servicios')
    && tablaSolicitanteExiste($conn, 'sla')
    && tablaSolicitanteExiste($conn, 'solicitud_comunicaciones')
    && tablaSolicitanteExiste($conn, 'solicitud_adjuntos')
    && tablaSolicitanteExiste($conn, 'solicitud_historial')
    && tablaSolicitanteExiste($conn, 'solicitud_calificaciones')
    && columnaSolicitanteExiste($conn, 'tickets', 'urgencia')
    && columnaSolicitanteExiste($conn, 'tickets', 'id_servicio')
    && columnaSolicitanteExiste(
        $conn,
        'tickets',
        'esperando_solicitante_desde'
    )
    && columnaSolicitanteExiste($conn, 'tickets', 'cierre_tipo')
    && columnaSolicitanteExiste($conn, 'tickets', 'motivo_cierre');

$condicionSinFlujo = columnaSolicitanteExiste(
    $conn,
    'tickets',
    'id_proceso'
) ? ' AND t.id_proceso IS NULL' : '';

if ($moduloInstalado) {
    procesarCierresAutomaticos($conn);
}

$estadosValidos = [
    'abierto' => 'Abierto',
    'en_proceso' => 'En proceso',
    'en_espera' => 'En espera',
    'resuelta' => 'Resuelta',
    'resuelto' => 'Resuelto',
    'cerrada' => 'Cerrada',
    'cancelada' => 'Cancelada',
];

$urgenciasValidas = [
    'baja' => 'Baja',
    'moderada' => 'Moderada',
    'alta' => 'Alta',
    'urgente' => 'Urgente',
];

$mensajes = [
    'ticket_creado' => [
        'exito',
        'La solicitud fue registrada correctamente.'
    ],
    'mensaje_enviado' => [
        'exito',
        'El mensaje y sus archivos fueron enviados correctamente.'
    ],
    'ticket_calificado' => [
        'exito',
        'Gracias por calificar la atención. El ticket y la conversación fueron cerrados.'
    ],
    'datos_incompletos' => [
        'error',
        'Complete todos los campos obligatorios.'
    ],
    'servicio_no_disponible' => [
        'error',
        'El servicio seleccionado ya no se encuentra disponible.'
    ],
    'ticket_no_disponible' => [
        'error',
        'La solicitud no existe o no pertenece a su usuario.'
    ],
    'ticket_cerrado' => [
        'aviso',
        'La conversación está cerrada porque la solicitud finalizó o fue cancelada.'
    ],
    'mensaje_vacio' => [
        'error',
        'Escriba un mensaje o seleccione por lo menos un archivo.'
    ],
    'calificacion_invalida' => [
        'error',
        'Seleccione una calificación de 1 a 5 estrellas.'
    ],
    'ticket_no_resuelto' => [
        'aviso',
        'El ticket debe estar marcado como resuelto antes de calificarlo y cerrarlo.'
    ],
    'solicitud_invalida' => [
        'error',
        'La operación solicitada no es válida. Actualice la página.'
    ],
    'instalacion_pendiente' => [
        'aviso',
        'Debe ejecutar primero crear_modulo_solicitudes.sql en la base mesa_servicio.'
    ],
    'error_operacion' => [
        'error',
        'No fue posible completar la operación. Verifique la base de datos y vuelva a intentarlo.'
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals((string) $_SESSION['csrf_token'], $token)
    ) {
        redirigirSolicitante('solicitud_invalida');
    }

    if (!$moduloInstalado) {
        redirigirSolicitante('instalacion_pendiente');
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $rutasCreadas = [];

    try {
        if ($accion === 'crear_ticket') {
            $idServicio = filter_input(
                INPUT_POST,
                'id_servicio',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $urgencia = (string) ($_POST['urgencia'] ?? 'moderada');

            if (
                !$idServicio
                || $titulo === ''
                || $descripcion === ''
                || !isset($urgenciasValidas[$urgencia])
            ) {
                redirigirSolicitante('datos_incompletos');
            }

            if (strlen($titulo) > 180 || strlen($descripcion) > 15000) {
                redirigirSolicitante('datos_incompletos');
            }

            $stmt = $conn->prepare(
                "SELECT s.id_servicio
                 FROM servicios AS s
                 INNER JOIN catalogos AS c
                    ON c.id_catalogo = s.id_catalogo
                 INNER JOIN sla AS sl
                    ON sl.id_sla = s.id_sla
                 WHERE s.id_servicio = ?
                   AND s.estado = 'activo'
                   AND c.estado = 'activo'
                 LIMIT 1"
            );
            $stmt->bind_param('i', $idServicio);
            $stmt->execute();
            $stmt->store_result();
            $servicioDisponible = $stmt->num_rows > 0;
            $stmt->close();

            if (!$servicioDisponible) {
                redirigirSolicitante('servicio_no_disponible');
            }

            $conn->begin_transaction();

            if ($tienePrioridadAnterior) {
                $prioridadAnterior = match ($urgencia) {
                    'baja' => 'baja',
                    'moderada' => 'media',
                    'alta', 'urgente' => 'alta',
                    default => 'media',
                };

                $stmt = $conn->prepare(
                    "INSERT INTO tickets
                        (
                            titulo,
                            descripcion,
                            estado,
                            urgencia,
                            prioridad,
                            id_usuario,
                            id_tecnico,
                            id_servicio,
                            fecha_creacion
                        )
                     VALUES (?, ?, 'abierto', ?, ?, ?, NULL, ?, NOW())"
                );
                $stmt->bind_param(
                    'ssssii',
                    $titulo,
                    $descripcion,
                    $urgencia,
                    $prioridadAnterior,
                    $idUsuario,
                    $idServicio
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO tickets
                        (
                            titulo,
                            descripcion,
                            estado,
                            urgencia,
                            id_usuario,
                            id_tecnico,
                            id_servicio,
                            fecha_creacion
                        )
                     VALUES (?, ?, 'abierto', ?, ?, NULL, ?, NOW())"
                );
                $stmt->bind_param(
                    'sssii',
                    $titulo,
                    $descripcion,
                    $urgencia,
                    $idUsuario,
                    $idServicio
                );
            }

            $stmt->execute();
            $idTicketNuevo = (int) $conn->insert_id;
            $stmt->close();

            $cantidadAdjuntos = 0;

            if (isset($_FILES['adjuntos'])) {
                $cantidadAdjuntos = guardarAdjuntosSolicitante(
                    $conn,
                    $idTicketNuevo,
                    $idUsuario,
                    $_FILES['adjuntos'],
                    $rutasCreadas
                );
            }

            registrarHistorialSolicitante(
                $conn,
                $idTicketNuevo,
                $idUsuario,
                'Solicitud creada',
                'El solicitante registró la solicitud.'
                . ($cantidadAdjuntos > 0
                    ? " Incluyó {$cantidadAdjuntos} archivo(s)."
                    : '')
            );

            $conn->commit();
            redirigirSolicitante('ticket_creado', 'tickets', $idTicketNuevo);
        }

        if ($accion === 'calificar_cerrar') {
            $idTicket = filter_input(
                INPUT_POST,
                'id_ticket',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $calificacion = filter_input(
                INPUT_POST,
                'calificacion',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $comentario = trim(
                (string) ($_POST['comentario_calificacion'] ?? '')
            );

            if (
                $calificacion < 1
                || $calificacion > 5
                || strlen($comentario) > 1000
            ) {
                redirigirSolicitante(
                    'calificacion_invalida',
                    'tickets',
                    $idTicket
                );
            }

            $stmt = $conn->prepare(
                "SELECT
                    t.id_ticket,
                    t.id_tecnico,
                    t.estado,
                    c.id_calificacion
                 FROM tickets AS t
                 LEFT JOIN solicitud_calificaciones AS c
                    ON c.id_ticket = t.id_ticket
                 WHERE t.id_ticket = ?
                   AND t.id_usuario = ?
                   {$condicionSinFlujo}
                 LIMIT 1"
            );
            $stmt->bind_param('ii', $idTicket, $idUsuario);
            $stmt->execute();
            $ticketCalificacion = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$ticketCalificacion) {
                redirigirSolicitante(
                    'ticket_no_disponible',
                    'tickets'
                );
            }

            if (
                !in_array(
                    $ticketCalificacion['estado'],
                    ['resuelta', 'resuelto'],
                    true
                )
                || (int) $ticketCalificacion['id_tecnico'] <= 0
            ) {
                redirigirSolicitante(
                    'ticket_no_resuelto',
                    'tickets',
                    $idTicket
                );
            }

            if ($ticketCalificacion['id_calificacion'] !== null) {
                redirigirSolicitante(
                    'ticket_cerrado',
                    'tickets',
                    $idTicket
                );
            }

            $idGestorCalificado = (int) $ticketCalificacion['id_tecnico'];
            $conn->begin_transaction();
            $stmt = $conn->prepare(
                "INSERT INTO solicitud_calificaciones
                    (
                        id_ticket,
                        id_solicitante,
                        id_gestor,
                        calificacion,
                        comentario
                    )
                 VALUES (?, ?, ?, ?, NULLIF(?, ''))"
            );
            $stmt->bind_param(
                'iiiis',
                $idTicket,
                $idUsuario,
                $idGestorCalificado,
                $calificacion,
                $comentario
            );
            $stmt->execute();
            $stmt->close();

            $motivoCierre = 'Cerrado por el solicitante después de calificar la atención.';
            $stmt = $conn->prepare(
                "UPDATE tickets
                 SET estado = 'cerrada',
                     fecha_finalizacion = NOW(),
                     esperando_solicitante_desde = NULL,
                     cierre_tipo = 'solicitante',
                     motivo_cierre = ?
                 WHERE id_ticket = ?
                   AND id_usuario = ?
                   AND estado IN ('resuelta', 'resuelto')"
            );
            $stmt->bind_param(
                'sii',
                $motivoCierre,
                $idTicket,
                $idUsuario
            );
            $stmt->execute();
            $ticketsActualizados = $stmt->affected_rows;
            $stmt->close();

            if ($ticketsActualizados !== 1) {
                throw new RuntimeException(
                    'El ticket cambió de estado antes de guardar la calificación. Actualice la página.'
                );
            }

            registrarHistorialSolicitante(
                $conn,
                $idTicket,
                $idUsuario,
                'Calificación y cierre',
                "El solicitante calificó la atención con {$calificacion} de 5 estrellas."
            );

            $conn->commit();
            redirigirSolicitante(
                'ticket_calificado',
                'tickets',
                $idTicket
            );
        }

        if ($accion === 'enviar_mensaje') {
            $idTicket = filter_input(
                INPUT_POST,
                'id_ticket',
                FILTER_VALIDATE_INT
            ) ?: 0;
            $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
            $tieneArchivos = isset($_FILES['adjuntos'])
                && hayArchivosSolicitante($_FILES['adjuntos']);
            $ticket = obtenerTicketPropioSolicitante(
                $conn,
                $idTicket,
                $idUsuario
            );

            if (!$ticket) {
                redirigirSolicitante('ticket_no_disponible', 'tickets');
            }

            if (in_array($ticket['estado'], ['cerrada', 'cancelada'], true)) {
                redirigirSolicitante('ticket_cerrado', 'tickets', $idTicket);
            }

            if ($mensaje === '' && !$tieneArchivos) {
                redirigirSolicitante('mensaje_vacio', 'tickets', $idTicket);
            }

            if (strlen($mensaje) > 5000) {
                redirigirSolicitante('mensaje_vacio', 'tickets', $idTicket);
            }

            $conn->begin_transaction();
            $mensajeGuardado = $mensaje !== ''
                ? $mensaje
                : 'Archivo(s) adjunto(s).';
            $tipo = 'publica';

            $stmt = $conn->prepare(
                "INSERT INTO solicitud_comunicaciones
                    (id_ticket, id_emisor, tipo, mensaje)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'iiss',
                $idTicket,
                $idUsuario,
                $tipo,
                $mensajeGuardado
            );
            $stmt->execute();
            $stmt->close();

            $cantidadAdjuntos = 0;

            if ($tieneArchivos) {
                $cantidadAdjuntos = guardarAdjuntosSolicitante(
                    $conn,
                    $idTicket,
                    $idUsuario,
                    $_FILES['adjuntos'],
                    $rutasCreadas
                );
            }

            registrarHistorialSolicitante(
                $conn,
                $idTicket,
                $idUsuario,
                'Comunicación del solicitante',
                $cantidadAdjuntos > 0
                    ? "Mensaje enviado con {$cantidadAdjuntos} archivo(s)."
                    : 'Mensaje enviado desde el chat.'
            );

            $stmt = $conn->prepare(
                "UPDATE tickets
                 SET estado = CASE
                        WHEN estado IN ('en_espera', 'resuelta', 'resuelto')
                            THEN 'en_proceso'
                        ELSE estado
                     END,
                     esperando_solicitante_desde = NULL,
                     cierre_tipo = NULL,
                     motivo_cierre = NULL
                 WHERE id_ticket = ?
                   AND id_usuario = ?"
            );
            $stmt->bind_param('ii', $idTicket, $idUsuario);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            redirigirSolicitante('mensaje_enviado', 'tickets', $idTicket);
        }

        redirigirSolicitante('solicitud_invalida');
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // La transacción pudo no haberse iniciado.
        }

        foreach ($rutasCreadas as $rutaFisica) {
            if (is_file($rutaFisica)) {
                unlink($rutaFisica);
            }
        }

        error_log(
            'Error en panelSolicitante.php: '
            . $e->getMessage()
        );

        $mensajeError = get_class($e) === RuntimeException::class
            ? $e->getMessage()
            : '';

        $_SESSION['error_solicitante'] = $mensajeError;
        redirigirSolicitante('error_operacion');
    }
}

$vista = 'inicio';

$mensajeActual = (string) ($_GET['msg'] ?? '');
$errorDetalle = trim((string) ($_SESSION['error_solicitante'] ?? ''));
unset($_SESSION['error_solicitante']);

$catalogos = [];
$servicios = [];
$tickets = [];
$ticketSeleccionado = null;
$encuestaPendiente = null;
$comunicaciones = [];
$adjuntos = [];
$conteos = [
    'total' => 0,
    'abiertos' => 0,
    'proceso' => 0,
    'resueltos' => 0,
];
$idTicketSeleccionado = filter_input(
    INPUT_GET,
    'id_ticket',
    FILTER_VALIDATE_INT
) ?: 0;
$filtroEstado = (string) ($_GET['estado'] ?? '');

if (!isset($estadosValidos[$filtroEstado])) {
    $filtroEstado = '';
}

if ($moduloInstalado) {
    $resultadoCatalogos = $conn->query(
        "SELECT id_catalogo, nombre, descripcion, imagen, orden
         FROM catalogos
         WHERE estado = 'activo'
         ORDER BY orden ASC, nombre ASC"
    );
    $catalogos = $resultadoCatalogos->fetch_all(MYSQLI_ASSOC);

    $resultadoServicios = $conn->query(
        "SELECT
            s.id_servicio,
            s.id_catalogo,
            s.nombre,
            s.descripcion,
            sl.nombre AS sla_nombre,
            sl.tiempo_respuesta,
            sl.unidad
         FROM servicios AS s
         INNER JOIN catalogos AS c
            ON c.id_catalogo = s.id_catalogo
         INNER JOIN sla AS sl
            ON sl.id_sla = s.id_sla
         WHERE s.estado = 'activo'
           AND c.estado = 'activo'
         ORDER BY c.orden ASC, c.nombre ASC, s.nombre ASC"
    );
    $servicios = $resultadoServicios->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(t.estado = 'abierto') AS abiertos,
            SUM(t.estado IN ('en_proceso', 'en_espera')) AS proceso,
            SUM(t.estado IN ('resuelta', 'cerrada')) AS resueltos
         FROM tickets AS t
         WHERE t.id_usuario = ?
         {$condicionSinFlujo}"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resumen = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $conteos = [
        'total' => (int) ($resumen['total'] ?? 0),
        'abiertos' => (int) ($resumen['abiertos'] ?? 0),
        'proceso' => (int) ($resumen['proceso'] ?? 0),
        'resueltos' => (int) ($resumen['resueltos'] ?? 0),
    ];

    $sqlTickets = "
        SELECT
            t.id_ticket,
            t.titulo,
            t.descripcion,
            t.estado,
            t.urgencia,
            t.id_tecnico,
            t.fecha_creacion,
            t.fecha_finalizacion,
            t.esperando_solicitante_desde,
            t.cierre_tipo,
            t.motivo_cierre,
            EXISTS(
                SELECT 1
                FROM solicitud_calificaciones AS cal_estado
                WHERE cal_estado.id_ticket = t.id_ticket
            ) AS tiene_calificacion,
            s.nombre AS servicio,
            c.nombre AS catalogo,
            sl.nombre AS sla_nombre,
            tecnico.nombre AS tecnico,
            CASE sl.unidad
                WHEN 'minutos' THEN DATE_ADD(
                    t.fecha_creacion,
                    INTERVAL sl.tiempo_respuesta MINUTE
                )
                WHEN 'horas' THEN DATE_ADD(
                    t.fecha_creacion,
                    INTERVAL sl.tiempo_respuesta HOUR
                )
                WHEN 'dias' THEN DATE_ADD(
                    t.fecha_creacion,
                    INTERVAL sl.tiempo_respuesta DAY
                )
                ELSE NULL
            END AS fecha_vencimiento
        FROM tickets AS t
        LEFT JOIN servicios AS s
            ON s.id_servicio = t.id_servicio
        LEFT JOIN catalogos AS c
            ON c.id_catalogo = s.id_catalogo
        LEFT JOIN sla AS sl
            ON sl.id_sla = s.id_sla
        LEFT JOIN usuarios AS tecnico
            ON tecnico.id_usuario = t.id_tecnico
        WHERE t.id_usuario = ?
        {$condicionSinFlujo}
    ";

    if ($filtroEstado !== '') {
        $sqlTickets .= " AND t.estado = ? ";
    }

    $sqlTickets .= "
        ORDER BY
            FIELD(
                t.estado,
                'abierto',
                'en_proceso',
                'en_espera',
                'resuelta',
                'cerrada',
                'cancelada'
            ),
            t.id_ticket DESC
        LIMIT 150
    ";

    $stmt = $conn->prepare($sqlTickets);

    if ($filtroEstado !== '') {
        $stmt->bind_param('is', $idUsuario, $filtroEstado);
    } else {
        $stmt->bind_param('i', $idUsuario);
    }

    $stmt->execute();
    $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($idTicketSeleccionado > 0) {
        $stmt = $conn->prepare(
            $sqlTickets = "
                SELECT
                    t.id_ticket,
                    t.titulo,
                    t.descripcion,
                    t.estado,
                    t.urgencia,
                    t.id_tecnico,
                    t.fecha_creacion,
                    t.fecha_finalizacion,
                    t.esperando_solicitante_desde,
                    t.cierre_tipo,
                    t.motivo_cierre,
                    s.nombre AS servicio,
                    c.nombre AS catalogo,
                    sl.nombre AS sla_nombre,
                    tecnico.nombre AS tecnico,
                    cal.calificacion,
                    cal.comentario AS comentario_calificacion,
                    cal.creado_en AS fecha_calificacion,
                    CASE sl.unidad
                        WHEN 'minutos' THEN DATE_ADD(
                            t.fecha_creacion,
                            INTERVAL sl.tiempo_respuesta MINUTE
                        )
                        WHEN 'horas' THEN DATE_ADD(
                            t.fecha_creacion,
                            INTERVAL sl.tiempo_respuesta HOUR
                        )
                        WHEN 'dias' THEN DATE_ADD(
                            t.fecha_creacion,
                            INTERVAL sl.tiempo_respuesta DAY
                        )
                        ELSE NULL
                    END AS fecha_vencimiento
                FROM tickets AS t
                LEFT JOIN servicios AS s
                    ON s.id_servicio = t.id_servicio
                LEFT JOIN catalogos AS c
                    ON c.id_catalogo = s.id_catalogo
                LEFT JOIN sla AS sl
                    ON sl.id_sla = s.id_sla
                LEFT JOIN usuarios AS tecnico
                    ON tecnico.id_usuario = t.id_tecnico
                LEFT JOIN solicitud_calificaciones AS cal
                    ON cal.id_ticket = t.id_ticket
                WHERE t.id_usuario = ?
                  AND t.id_ticket = ?
                  {$condicionSinFlujo}
                LIMIT 1
            "
        );
        $stmt->bind_param('ii', $idUsuario, $idTicketSeleccionado);
        $stmt->execute();
        $ticketSeleccionado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ticketSeleccionado) {
            $stmt = $conn->prepare(
                "SELECT
                    c.id_comunicacion,
                    c.id_emisor,
                    c.mensaje,
                    c.creado_en,
                    COALESCE(u.nombre, 'Usuario eliminado') AS emisor
                 FROM solicitud_comunicaciones AS c
                 LEFT JOIN usuarios AS u
                    ON u.id_usuario = c.id_emisor
                 WHERE c.id_ticket = ?
                   AND c.tipo = 'publica'
                 ORDER BY c.creado_en ASC, c.id_comunicacion ASC"
            );
            $stmt->bind_param('i', $idTicketSeleccionado);
            $stmt->execute();
            $comunicaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $stmt = $conn->prepare(
                "SELECT
                    a.id_adjunto,
                    a.nombre_original,
                    a.tipo_mime,
                    a.tamano,
                    a.creado_en,
                    a.id_usuario,
                    COALESCE(u.nombre, 'Usuario eliminado') AS usuario
                 FROM solicitud_adjuntos AS a
                 LEFT JOIN usuarios AS u
                    ON u.id_usuario = a.id_usuario
                 WHERE a.id_ticket = ?
                 ORDER BY a.creado_en DESC, a.id_adjunto DESC"
            );
            $stmt->bind_param('i', $idTicketSeleccionado);
            $stmt->execute();
            $adjuntos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }

    $stmt = $conn->prepare(
        "SELECT
            t.id_ticket,
            t.titulo,
            COALESCE(g.nombre, 'Gestor asignado') AS gestor
         FROM tickets AS t
         LEFT JOIN usuarios AS g
            ON g.id_usuario = t.id_tecnico
         LEFT JOIN solicitud_calificaciones AS cal
            ON cal.id_ticket = t.id_ticket
         WHERE t.id_usuario = ?
           AND t.estado IN ('resuelta', 'resuelto')
           AND t.id_tecnico IS NOT NULL
           AND cal.id_calificacion IS NULL
           {$condicionSinFlujo}
         ORDER BY t.actualizado_en DESC, t.id_ticket DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $encuestaPendiente = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

$flujoDisponible = flujoModuloInstalado($conn);
$totalTicketsFlujo = $flujoDisponible
    ? count(flujoTicketsUsuario($conn, $idUsuario, 3))
    : 0;
$encuestaPendiente = null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel del solicitante | Mesa de Servicio</title>
    <style>
        :root {
            --primary: #0f6fec;
            --primary-dark: #0b4fae;
            --navy: #102a43;
            --text: #243b53;
            --muted: #627d98;
            --border: #dfe8f3;
            --surface: #ffffff;
            --background: #f3f6fb;
            --soft-blue: #edf5ff;
            --teal: #0ea5a4;
            --success: #087443;
            --success-bg: #ecfdf3;
            --warning: #8a5b00;
            --warning-bg: #fff8e1;
            --danger: #b42318;
            --danger-bg: #fff1f0;
            --shadow: 0 18px 50px rgba(16, 42, 67, .10);
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 8% 0%, rgba(15,111,236,.08), transparent 25%),
                radial-gradient(circle at 100% 100%, rgba(14,165,164,.07), transparent 24%),
                var(--background);
        }

        button, input, select, textarea { font: inherit; }

        a { color: inherit; }

        .app {
            width: min(1240px, calc(100% - 40px));
            margin: 0 auto;
            padding: 28px 0 56px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 14px 18px;
            border: 1px solid rgba(223,232,243,.92);
            border-radius: 18px;
            background: rgba(255,255,255,.94);
            box-shadow: 0 10px 30px rgba(16,42,67,.07);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 13px;
            color: #fff;
            background: linear-gradient(145deg, var(--primary), var(--primary-dark));
            box-shadow: 0 8px 18px rgba(15,111,236,.24);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .brand strong {
            display: block;
            color: var(--navy);
            font-size: 15px;
        }

        .brand small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            min-height: 40px;
            padding: 7px 13px;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: #365b7d;
            background: #f8fbff;
            font-size: 13px;
            font-weight: 700;
        }

        .user-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #20c77a;
            box-shadow: 0 0 0 4px rgba(32,199,122,.13);
        }

        .logout {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border: 1px solid #f2c8c5;
            border-radius: 10px;
            color: #a62b22;
            background: #fff7f6;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: background .2s ease, border-color .2s ease;
        }

        .logout:hover {
            border-color: #e89c96;
            background: #fff0ef;
        }

        .logout svg, .btn svg, .tab svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
        }

        .hero {
            position: relative;
            isolation: isolate;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: end;
            gap: 30px;
            margin-top: 24px;
            padding: 38px 42px;
            overflow: hidden;
            border-radius: 24px;
            color: #fff;
            background: linear-gradient(135deg, #0b4fae 0%, #0f6fec 54%, #1c9bea 100%);
            box-shadow: 0 20px 45px rgba(15,111,236,.20);
        }

        .hero::before, .hero::after {
            position: absolute;
            z-index: -1;
            content: "";
            border-radius: 50%;
            border: 40px solid rgba(255,255,255,.07);
        }

        .hero::before {
            width: 260px;
            height: 260px;
            top: -160px;
            right: -70px;
        }

        .hero::after {
            width: 200px;
            height: 200px;
            right: 160px;
            bottom: -155px;
        }

        .eyebrow {
            display: inline-flex;
            margin-bottom: 14px;
            padding: 7px 11px;
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 999px;
            background: rgba(255,255,255,.10);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(30px, 4vw, 44px);
            line-height: 1.08;
            letter-spacing: -.035em;
        }

        .hero p {
            max-width: 670px;
            margin: 14px 0 0;
            color: rgba(255,255,255,.84);
            line-height: 1.6;
        }

        .hero-stat {
            min-width: 150px;
            padding: 18px;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 16px;
            background: rgba(255,255,255,.12);
            backdrop-filter: blur(8px);
        }

        .hero-stat strong {
            display: block;
            font-size: 28px;
        }

        .hero-stat span {
            display: block;
            margin-top: 4px;
            color: rgba(255,255,255,.78);
            font-size: 12px;
        }

        .navigation {
            display: flex;
            gap: 10px;
            margin: 24px 0;
            padding: 7px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: rgba(255,255,255,.86);
            box-shadow: 0 8px 25px rgba(16,42,67,.05);
        }

        .tab {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 10px 18px;
            border-radius: 10px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 750;
            text-decoration: none;
            transition: color .2s ease, background .2s ease;
        }

        .tab:hover { color: var(--primary); background: var(--soft-blue); }

        .tab.active {
            color: #fff;
            background: var(--primary);
            box-shadow: 0 8px 18px rgba(15,111,236,.20);
        }

        .tab-count {
            min-width: 23px;
            height: 23px;
            display: inline-grid;
            place-items: center;
            padding: 0 7px;
            border-radius: 999px;
            color: inherit;
            background: rgba(98,125,152,.12);
            font-size: 11px;
        }

        .tab.active .tab-count { background: rgba(255,255,255,.18); }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-bottom: 20px;
            padding: 14px 16px;
            border: 1px solid;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.45;
        }

        .alert::before {
            width: 21px;
            height: 21px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 50%;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
        }

        .alert.exito {
            color: var(--success);
            border-color: #abefc6;
            background: var(--success-bg);
        }

        .alert.exito::before { content: "✓"; background: #12b76a; }

        .alert.aviso {
            color: var(--warning);
            border-color: #f6d98b;
            background: var(--warning-bg);
        }

        .alert.aviso::before { content: "!"; background: #d89913; }

        .alert.error {
            color: var(--danger);
            border-color: #fecdca;
            background: var(--danger-bg);
        }

        .alert.error::before { content: "!"; background: #d92d20; }

        .panel {
            padding: 28px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .panel + .panel { margin-top: 20px; }

        .section-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .section-heading h2, .section-heading h3 {
            margin: 0;
            color: var(--navy);
            letter-spacing: -.02em;
        }

        .section-heading h2 { font-size: 23px; }
        .section-heading h3 { font-size: 19px; }

        .section-heading p {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .step-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex: 0 0 auto;
            padding: 7px 11px;
            border-radius: 999px;
            color: var(--primary-dark);
            background: var(--soft-blue);
            font-size: 12px;
            font-weight: 800;
        }

        .step-chip span {
            width: 22px;
            height: 22px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #fff;
            background: var(--primary);
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .catalog-card, .service-card {
            width: 100%;
            border: 1px solid var(--border);
            color: var(--text);
            background: #fff;
            text-align: left;
            cursor: pointer;
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }

        .catalog-card {
            display: grid;
            grid-template-columns: 52px 1fr 22px;
            align-items: center;
            gap: 13px;
            min-height: 92px;
            padding: 15px;
            border-radius: 14px;
        }

        .catalog-card:hover, .service-card:hover {
            transform: translateY(-2px);
            border-color: #9dc5f6;
            box-shadow: 0 12px 25px rgba(15,111,236,.10);
        }

        .catalog-card.active {
            border-color: var(--primary);
            background: var(--soft-blue);
            box-shadow: 0 0 0 3px rgba(15,111,236,.10);
        }

        .catalog-icon {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            overflow: hidden;
            border-radius: 13px;
            background: #eef5fc;
        }

        .catalog-icon img {
            width: 38px;
            height: 38px;
            object-fit: contain;
        }

        .catalog-card strong, .service-card strong {
            display: block;
            color: var(--navy);
            font-size: 15px;
        }

        .catalog-card small, .service-card p {
            display: -webkit-box;
            margin-top: 6px;
            overflow: hidden;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .card-arrow {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: #829ab1;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
        }

        .services-panel[hidden], .request-panel[hidden] { display: none; }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .service-card {
            min-height: 145px;
            padding: 18px;
            border-radius: 14px;
        }

        .service-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .service-symbol {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: var(--teal);
            background: #e7f8f6;
        }

        .service-symbol svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 1.8;
        }

        .sla-tag {
            display: inline-flex;
            max-width: 150px;
            padding: 5px 8px;
            overflow: hidden;
            border-radius: 999px;
            color: #486581;
            background: #f1f5f9;
            font-size: 10px;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .service-card strong { margin-top: 15px; }
        .service-card p { margin-bottom: 0; }

        .selected-service {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
            padding: 15px 17px;
            border: 1px solid #b9d7fa;
            border-radius: 13px;
            background: var(--soft-blue);
        }

        .selected-service span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .selected-service strong {
            display: block;
            margin-top: 5px;
            color: var(--navy);
        }

        .selected-service em {
            color: var(--primary-dark);
            font-size: 12px;
            font-style: normal;
            font-weight: 750;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .field.full { grid-column: 1 / -1; }

        .field label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-size: 13px;
            font-weight: 750;
        }

        .required { color: #d92d20; }

        .field input, .field select, .field textarea, .chat-compose textarea {
            width: 100%;
            outline: none;
            border: 1px solid #cbd8e6;
            border-radius: 10px;
            color: var(--navy);
            background: #fbfdff;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }

        .field input, .field select {
            min-height: 46px;
            padding: 0 13px;
        }

        .field textarea {
            min-height: 150px;
            padding: 13px;
            resize: vertical;
            line-height: 1.5;
        }

        .field input:focus, .field select:focus, .field textarea:focus,
        .chat-compose textarea:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(15,111,236,.10);
        }

        .field-help {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
        }

        .file-box {
            position: relative;
            min-height: 92px;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border: 1px dashed #9db7cf;
            border-radius: 12px;
            background: #f8fbff;
            cursor: pointer;
        }

        .file-box:hover { border-color: var(--primary); background: var(--soft-blue); }

        .file-box input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .file-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 11px;
            color: var(--primary);
            background: #e3f0ff;
        }

        .file-icon svg {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 1.8;
        }

        .file-box strong { display: block; color: var(--navy); font-size: 13px; }
        .file-box span { display: block; margin-top: 5px; color: var(--muted); font-size: 11px; }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        .btn {
            min-height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 16px;
            border: 1px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 750;
            text-decoration: none;
            transition: transform .2s ease, background .2s ease, box-shadow .2s ease;
        }

        .btn:hover { transform: translateY(-1px); }

        .btn-primary {
            color: #fff;
            background: var(--primary);
            box-shadow: 0 8px 18px rgba(15,111,236,.20);
        }

        .btn-primary:hover { background: var(--primary-dark); }

        .btn-soft {
            border-color: var(--border);
            color: #486581;
            background: #f7faff;
        }

        .btn-soft:hover { background: #edf4fb; }
        .btn:disabled { opacity: .65; cursor: wait; transform: none; }

        .empty {
            padding: 30px;
            border: 1px dashed #bfd0df;
            border-radius: 13px;
            color: var(--muted);
            background: #fafcff;
            text-align: center;
            line-height: 1.55;
        }

        .ticket-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            padding: 17px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fff;
        }

        .stat-card span { color: var(--muted); font-size: 11px; font-weight: 700; }
        .stat-card strong { display: block; margin-top: 7px; color: var(--navy); font-size: 25px; }

        .filter-bar {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .filter-bar label {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
        }

        .filter-bar select {
            min-width: 190px;
            min-height: 40px;
            padding: 0 11px;
            border: 1px solid #cbd8e6;
            border-radius: 9px;
            color: var(--text);
            background: #fff;
        }

        .ticket-layout {
            display: grid;
            grid-template-columns: minmax(310px, .78fr) minmax(0, 1.22fr);
            gap: 20px;
            align-items: start;
        }

        .ticket-list {
            max-height: 760px;
            display: grid;
            gap: 10px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .ticket-card {
            display: block;
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: #fff;
            text-decoration: none;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }

        .ticket-card:hover {
            transform: translateY(-1px);
            border-color: #9dc5f6;
            box-shadow: 0 10px 20px rgba(16,42,67,.07);
        }

        .ticket-card.active {
            border-color: var(--primary);
            background: var(--soft-blue);
            box-shadow: 0 0 0 3px rgba(15,111,236,.08);
        }

        .ticket-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .ticket-id { color: var(--primary); font-size: 11px; font-weight: 800; }

        .ticket-card h3 {
            display: -webkit-box;
            margin: 11px 0 8px;
            overflow: hidden;
            color: var(--navy);
            font-size: 14px;
            line-height: 1.4;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .ticket-card p {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.45;
        }

        .ticket-card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 12px;
            color: #829ab1;
            font-size: 10px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
        }

        .status::before {
            width: 6px;
            height: 6px;
            content: "";
            border-radius: 50%;
            background: currentColor;
        }

        .status.abierto { color: #087443; background: #e7f8ee; }
        .status.en_proceso { color: #0b5ec2; background: #e8f2ff; }
        .status.en_espera { color: #8a5b00; background: #fff4cf; }
        .status.resuelta, .status.cerrada { color: #526d82; background: #edf2f7; }
        .status.cancelada { color: #a72836; background: #fdecee; }

        .ticket-detail {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 14px 35px rgba(16,42,67,.08);
        }

        .detail-header {
            padding: 22px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #fff, #f9fbfe);
        }

        .detail-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .detail-header h2 {
            margin: 7px 0 0;
            color: var(--navy);
            font-size: 20px;
            line-height: 1.35;
        }

        .detail-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 18px;
        }

        .meta-item {
            padding: 11px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
        }

        .meta-item span {
            display: block;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .meta-item strong {
            display: block;
            margin-top: 5px;
            overflow: hidden;
            color: var(--text);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .description-box {
            margin: 18px 22px 0;
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: 11px;
            color: #3f5870;
            background: #f9fbfd;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .chat-section { padding: 22px; }

        .chat-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 14px;
        }

        .chat-heading h3 { margin: 0; color: var(--navy); font-size: 17px; }
        .chat-heading span { color: var(--muted); font-size: 11px; }

        .chat-box {
            height: 330px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow-y: auto;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: #f5f8fc;
        }

        .message {
            max-width: 78%;
            align-self: flex-start;
        }

        .message.mine { align-self: flex-end; }

        .message-author {
            margin: 0 5px 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .message.mine .message-author { text-align: right; }

        .message-bubble {
            padding: 11px 13px;
            border: 1px solid var(--border);
            border-radius: 13px 13px 13px 4px;
            color: var(--text);
            background: #fff;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            box-shadow: 0 5px 12px rgba(16,42,67,.05);
        }

        .message.mine .message-bubble {
            border-color: var(--primary);
            border-radius: 13px 13px 4px 13px;
            color: #fff;
            background: var(--primary);
        }

        .message-time {
            display: block;
            margin-top: 6px;
            color: #829ab1;
            font-size: 9px;
        }

        .message.mine .message-time { color: rgba(255,255,255,.72); }

        .chat-empty {
            margin: auto;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
            line-height: 1.5;
        }

        .chat-compose {
            margin-top: 14px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
        }

        .chat-compose textarea {
            min-height: 82px;
            padding: 11px;
            resize: vertical;
            line-height: 1.45;
        }

        .compose-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 10px;
        }

        .compact-file {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--primary);
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
        }

        .compact-file input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .compact-file svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
        }

        .attachments {
            padding: 0 22px 22px;
        }

        .attachments h3 { margin: 0 0 12px; color: var(--navy); font-size: 16px; }

        .attachment-list { display: grid; gap: 8px; }

        .attachment {
            display: grid;
            grid-template-columns: 38px 1fr auto;
            align-items: center;
            gap: 11px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fafcff;
        }

        .attachment-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            color: var(--primary);
            background: var(--soft-blue);
            font-size: 10px;
            font-weight: 800;
        }

        .attachment strong {
            display: block;
            overflow: hidden;
            color: var(--text);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .attachment small {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 10px;
        }

        .download-link {
            padding: 7px 9px;
            border-radius: 8px;
            color: var(--primary);
            background: var(--soft-blue);
            font-size: 11px;
            font-weight: 750;
            text-decoration: none;
        }

        .download-link:hover { color: #fff; background: var(--primary); }

        .rating-panel {
            margin: 11px 13px 0;
            padding: 12px;
            border: 1px solid #b9d7fa;
            border-radius: 10px;
            background: #f6faff;
        }

        .rating-panel h3 {
            margin: 0;
            color: var(--navy);
            font-size: 14px;
        }

        .rating-panel p {
            margin: 4px 0 9px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.4;
        }

        .rating-stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 3px;
        }

        .rating-stars input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .rating-stars label {
            color: #cbd8e6;
            cursor: pointer;
            font-size: 26px;
            line-height: 1;
            transition: color .15s ease, transform .15s ease;
        }

        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label {
            color: #f5a623;
        }

        .rating-stars label:hover { transform: translateY(-1px); }

        .rating-comment {
            width: 100%;
            min-height: 58px;
            margin-top: 9px;
            padding: 8px;
            border: 1px solid #cbd8e6;
            border-radius: 8px;
            outline: none;
            resize: vertical;
            font-size: 11px;
        }

        .rating-summary {
            margin: 11px 13px 0;
            padding: 10px 12px;
            border: 1px solid #c8ead8;
            border-radius: 9px;
            color: #235d43;
            background: #f1fbf6;
            font-size: 11px;
        }

        .rating-summary .stars {
            margin: 4px 0;
            color: #f5a623;
            font-size: 18px;
            letter-spacing: 2px;
        }

        .survey-overlay {
            position: fixed;
            z-index: 3000;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(16, 42, 67, .58);
            backdrop-filter: blur(4px);
        }

        .survey-overlay.visible {
            display: flex;
            animation: survey-fade .2s ease both;
        }

        .survey-dialog {
            width: min(440px, 100%);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(16,42,67,.24);
            animation: survey-in .25s ease both;
        }

        .survey-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            padding: 16px 17px 12px;
        }

        .survey-title {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .survey-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 10px;
            color: #a86400;
            background: #fff3d6;
            font-size: 20px;
        }

        .survey-header h2 {
            margin: 0;
            color: var(--navy);
            font-size: 17px;
        }

        .survey-header p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 10px;
        }

        .survey-close {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border: 0;
            border-radius: 8px;
            color: #627d98;
            background: #f2f6fa;
            cursor: pointer;
            font-size: 18px;
        }

        .survey-ticket {
            margin: 0 17px;
            padding: 9px 11px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f8fbff;
        }

        .survey-ticket span {
            display: block;
            color: var(--primary);
            font-size: 9px;
            font-weight: 800;
        }

        .survey-ticket strong {
            display: block;
            margin-top: 3px;
            overflow: hidden;
            color: var(--navy);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .survey-ticket small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
        }

        .survey-dialog .rating-panel {
            margin: 11px 17px 17px;
            border: 0;
            background: transparent;
            padding: 0;
        }

        .survey-actions {
            display: flex;
            justify-content: flex-end;
            gap: 7px;
            margin-top: 9px;
        }

        body.survey-open { overflow: hidden; }

        @keyframes survey-fade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes survey-in {
            from { opacity: 0; transform: translateY(10px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Vista compacta de escritorio */
        .app {
            width: min(1320px, calc(100% - 24px));
            padding: 10px 0 24px;
        }

        .topbar {
            gap: 14px;
            padding: 8px 12px;
            border-radius: 13px;
        }

        .brand { gap: 9px; }

        .brand-mark {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            font-size: 11px;
        }

        .brand strong { font-size: 13px; }
        .brand small { margin-top: 1px; font-size: 10px; }
        .user-actions { gap: 7px; }

        .user-pill,
        .logout {
            min-height: 34px;
            padding: 6px 10px;
            font-size: 11px;
        }

        .logout { border-radius: 8px; }
        .logout svg, .btn svg, .tab svg { width: 16px; height: 16px; }

        .hero {
            align-items: center;
            gap: 12px;
            min-height: 62px;
            margin-top: 8px;
            padding: 8px 14px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(15,111,236,.14);
        }

        .hero > div:first-child {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .hero::before,
        .hero::after {
            border-width: 28px;
        }

        .eyebrow {
            flex: 0 0 auto;
            margin: 0;
            padding: 3px 7px;
            font-size: 8px;
        }

        .hero h1 {
            flex: 0 0 auto;
            font-size: clamp(17px, 1.7vw, 20px);
            line-height: 1;
        }

        .hero p {
            max-width: 650px;
            margin: 0;
            font-size: 10px;
            line-height: 1.3;
        }

        .hero-stat {
            min-width: 96px;
            padding: 6px 9px;
            border-radius: 9px;
        }

        .hero-stat strong { font-size: 17px; }
        .hero-stat span { margin-top: 1px; font-size: 8px; }

        .navigation {
            gap: 6px;
            margin: 10px 0 12px;
            padding: 4px;
            border-radius: 11px;
        }

        .home-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 230px));
            gap: 10px;
            margin-top: 12px;
        }

        .home-action {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 56px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--navy);
            text-decoration: none;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }

        .home-action:hover {
            border-color: #83b7f4;
            box-shadow: 0 8px 20px rgba(15,111,236,.1);
            transform: translateY(-1px);
        }

        .home-action.primary {
            border-color: transparent;
            background: linear-gradient(135deg, #0f6fec, #0b86e9);
            color: #fff;
        }

        .home-action-icon {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 8px;
            background: #eef5ff;
            color: var(--primary);
            font-size: 18px;
            font-weight: 800;
        }

        .home-action.primary .home-action-icon {
            background: rgba(255,255,255,.18);
            color: #fff;
        }

        .home-action strong { display: block; font-size: 12px; }
        .home-action small { display: block; margin-top: 2px; color: var(--muted); font-size: 9px; }
        .home-action.primary small { color: rgba(255,255,255,.82); }

        .tab {
            min-height: 35px;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 8px;
            font-size: 12px;
        }

        .tab-count {
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            font-size: 9px;
        }

        .alert {
            gap: 8px;
            margin-bottom: 12px;
            padding: 9px 12px;
            border-radius: 9px;
            font-size: 12px;
        }

        .alert::before {
            width: 18px;
            height: 18px;
            font-size: 10px;
        }

        .panel {
            padding: 15px;
            border-radius: 13px;
            box-shadow: 0 10px 28px rgba(16, 42, 67, .07);
        }

        .panel + .panel { margin-top: 12px; }

        .section-heading {
            align-items: center;
            gap: 12px;
            margin-bottom: 11px;
        }

        .section-heading h2 { font-size: 18px; }
        .section-heading h3 { font-size: 16px; }

        .section-heading p {
            margin-top: 3px;
            font-size: 11px;
            line-height: 1.35;
        }

        .step-chip {
            gap: 5px;
            padding: 4px 8px;
            font-size: 10px;
        }

        .step-chip span {
            width: 18px;
            height: 18px;
        }

        .catalog-grid,
        .service-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 9px;
        }

        .catalog-card {
            grid-template-columns: 39px 1fr 16px;
            gap: 9px;
            min-height: 66px;
            padding: 9px;
            border-radius: 10px;
        }

        .catalog-icon {
            width: 39px;
            height: 39px;
            border-radius: 9px;
        }

        .catalog-icon img {
            width: 29px;
            height: 29px;
        }

        .catalog-card strong,
        .service-card strong {
            font-size: 13px;
        }

        .catalog-card small,
        .service-card p {
            margin-top: 3px;
            font-size: 10px;
            line-height: 1.3;
        }

        .card-arrow { width: 16px; height: 16px; }

        .service-card {
            min-height: 102px;
            padding: 11px;
            border-radius: 10px;
        }

        .service-symbol {
            width: 31px;
            height: 31px;
            border-radius: 8px;
        }

        .service-symbol svg { width: 18px; height: 18px; }

        .sla-tag {
            max-width: 130px;
            padding: 4px 7px;
            font-size: 9px;
        }

        .service-card strong { margin-top: 9px; }

        .selected-service {
            gap: 12px;
            margin-bottom: 12px;
            padding: 9px 11px;
            border-radius: 9px;
        }

        .selected-service span { font-size: 9px; }
        .selected-service strong { margin-top: 3px; font-size: 13px; }
        .selected-service em { font-size: 10px; }

        .form-grid { gap: 11px; }
        .field label { margin-bottom: 5px; font-size: 11px; }

        .field input,
        .field select {
            min-height: 37px;
            padding: 0 10px;
        }

        .field textarea {
            min-height: 96px;
            padding: 10px;
        }

        .field input,
        .field select,
        .field textarea,
        .chat-compose textarea {
            border-radius: 8px;
            font-size: 12px;
        }

        .field-help { margin-top: 4px; font-size: 9px; }

        .file-box {
            min-height: 66px;
            gap: 10px;
            padding: 10px;
            border-radius: 9px;
        }

        .file-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
        }

        .file-icon svg { width: 18px; height: 18px; }
        .file-box strong { font-size: 11px; }
        .file-box span { margin-top: 3px; font-size: 9px; }

        .form-actions { gap: 7px; margin-top: 12px; }

        .btn {
            min-height: 36px;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 11px;
        }

        .empty {
            padding: 18px;
            border-radius: 10px;
            font-size: 12px;
        }

        .ticket-stats {
            gap: 9px;
            margin-bottom: 12px;
        }

        .stat-card {
            padding: 10px 12px;
            border-radius: 10px;
        }

        .stat-card span { font-size: 9px; }
        .stat-card strong { margin-top: 3px; font-size: 19px; }

        .filter-bar {
            gap: 10px;
            margin-bottom: 11px;
        }

        .filter-bar label { margin-bottom: 4px; font-size: 9px; }

        .filter-bar select {
            min-width: 165px;
            min-height: 35px;
            font-size: 11px;
        }

        .ticket-layout {
            grid-template-columns: minmax(280px, .72fr) minmax(0, 1.28fr);
            gap: 12px;
        }

        .ticket-list {
            max-height: 600px;
            gap: 7px;
        }

        .ticket-card {
            padding: 10px;
            border-radius: 9px;
        }

        .ticket-card h3 {
            margin: 7px 0 5px;
            font-size: 12px;
        }

        .ticket-card p { font-size: 10px; }

        .ticket-card-meta {
            margin-top: 7px;
            font-size: 9px;
        }

        .status {
            gap: 4px;
            padding: 4px 7px;
            font-size: 9px;
        }

        .ticket-detail { border-radius: 12px; }

        .detail-header {
            padding: 13px;
        }

        .detail-header h2 {
            margin-top: 4px;
            font-size: 16px;
        }

        .detail-meta {
            gap: 7px;
            margin-top: 10px;
        }

        .meta-item {
            padding: 8px;
            border-radius: 8px;
        }

        .meta-item span { font-size: 8px; }
        .meta-item strong { margin-top: 3px; font-size: 10px; }

        .description-box {
            margin: 11px 13px 0;
            padding: 10px;
            border-radius: 8px;
            font-size: 11px;
            line-height: 1.45;
        }

        .chat-section { padding: 13px; }
        .chat-heading { margin-bottom: 9px; }
        .chat-heading h3 { font-size: 14px; }
        .chat-heading span { font-size: 9px; }

        .chat-box {
            height: 245px;
            gap: 8px;
            padding: 10px;
            border-radius: 9px;
        }

        .message-author { margin-bottom: 3px; font-size: 9px; }

        .message-bubble {
            padding: 8px 10px;
            border-radius: 10px 10px 10px 3px;
            font-size: 11px;
        }

        .message.mine .message-bubble {
            border-radius: 10px 10px 3px 10px;
        }

        .chat-compose {
            margin-top: 9px;
            padding: 9px;
            border-radius: 9px;
        }

        .chat-compose textarea {
            min-height: 58px;
            padding: 8px;
        }

        .compose-actions { margin-top: 7px; }
        .compact-file { font-size: 10px; }

        .attachments { padding: 0 13px 13px; }
        .attachments h3 { margin-bottom: 8px; font-size: 13px; }
        .attachment-list { gap: 6px; }

        .attachment {
            grid-template-columns: 32px 1fr auto;
            gap: 8px;
            padding: 7px;
            border-radius: 8px;
        }

        .attachment-icon {
            width: 32px;
            height: 32px;
            border-radius: 7px;
            font-size: 8px;
        }

        .attachment strong { font-size: 10px; }
        .attachment small { margin-top: 2px; font-size: 8px; }
        .download-link { padding: 5px 7px; font-size: 9px; }

        @media (max-width: 980px) {
            .catalog-grid, .service-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .ticket-layout { grid-template-columns: 1fr; }
            .ticket-list { max-height: 440px; }
        }

        @media (max-width: 720px) {
            .app { width: min(100% - 24px, 1240px); padding-top: 12px; }
            .topbar { align-items: flex-start; }
            .user-pill { display: none; }
            .hero { grid-template-columns: 1fr; padding: 8px 10px; }
            .hero > div:first-child { flex-wrap: wrap; }
            .hero p { display: none; }
            .hero-stat { display: none; }
            .navigation { overflow-x: auto; }
            .tab { flex: 1 0 auto; }
            .panel { padding: 20px; }
            .home-actions { grid-template-columns: 1fr; }
            .ticket-stats { grid-template-columns: repeat(2, 1fr); }
            .form-grid, .detail-meta { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
        }

        @media (max-width: 520px) {
            .brand small { display: none; }
            .logout span { display: none; }
            .catalog-grid, .service-grid { grid-template-columns: 1fr; }
            .section-heading { display: block; }
            .step-chip { margin-top: 12px; }
            .selected-service { align-items: flex-start; flex-direction: column; }
            .form-actions, .compose-actions { align-items: stretch; flex-direction: column; }
            .btn { width: 100%; }
            .filter-bar { align-items: stretch; flex-direction: column; }
            .filter-bar select { width: 100%; }
            .detail-title-row { flex-direction: column; }
            .message { max-width: 92%; }
            .attachment { grid-template-columns: 36px minmax(0, 1fr); }
            .download-link { grid-column: 1 / -1; text-align: center; }
        }
    </style>
</head>
<body>
<div class="app">
    <header class="topbar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">MS</div>
            <div>
                <strong>Mesa de Servicio</strong>
                <small>Portal del solicitante</small>
            </div>
        </div>

        <div class="user-actions">
            <div class="user-pill">
                <span class="user-dot" aria-hidden="true"></span>
                <?= escaparSolicitante($nombreUsuario) ?>
            </div>
            <a class="logout" href="logout.php">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <path d="m16 17 5-5-5-5M21 12H9"/>
                </svg>
                <span>Cerrar sesión</span>
            </a>
        </div>
    </header>

    <section class="hero">
        <div>
            <span class="eyebrow">Centro de atención</span>
            <h1>Hola, <?= escaparSolicitante($nombreUsuario) ?></h1>
            <p>
                Cree y consulte sus tickets, siga cada área y converse con el gestor asignado.
            </p>
        </div>
        <div class="hero-stat">
            <strong><?= (int) $totalTicketsFlujo ?></strong>
            <span>tickets registrados</span>
        </div>
    </section>

    <nav class="navigation" aria-label="Opciones del solicitante">
        <a class="tab" href="flujoTicket.php?modo=nuevo">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            Crear ticket
        </a>
        <a class="tab" href="flujoTicket.php?modo=mis_tickets">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 5h16v14H4zM8 9h8M8 13h5"/>
            </svg>
            Mis tickets
            <span class="tab-count"><?= (int) $totalTicketsFlujo ?></span>
        </a>
    </nav>

    <?php if (isset($mensajes[$mensajeActual])): ?>
        <div class="alert <?= escaparSolicitante($mensajes[$mensajeActual][0]) ?>">
            <span>
                <?= escaparSolicitante($mensajes[$mensajeActual][1]) ?>
                <?php if ($mensajeActual === 'error_operacion' && $errorDetalle !== ''): ?>
                    <?= escaparSolicitante($errorDetalle) ?>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if (!$flujoDisponible): ?>
        <div class="alert aviso">
            <span>
                El módulo de Tickets todavía no está instalado por completo.
                Importe <strong>migracion_flujos_secuenciales.sql</strong> sobre
                la base <strong>mesa_servicio</strong>.
            </span>
        </div>
    <?php elseif ($vista === 'inicio'): ?>
        <section class="panel">
            <div class="section-heading">
                <div>
                    <h2>Gestión de tickets</h2>
                    <p>Cree una solicitud o consulte su avance en un solo lugar.</p>
                </div>
            </div>
            <p style="color:var(--muted);max-width:760px">
                Cada ticket avanza de forma ordenada por las áreas configuradas. Podrá revisar
                la etapa actual, el SLA, el chat, la trazabilidad y las encuestas de satisfacción.
            </p>
            <div class="home-actions">
                <a class="home-action primary" href="flujoTicket.php?modo=nuevo">
                    <span class="home-action-icon">+</span>
                    <span><strong>Crear ticket</strong><small>Registrar una nueva solicitud</small></span>
                </a>
                <a class="home-action" href="flujoTicket.php?modo=mis_tickets">
                    <span class="home-action-icon">≡</span>
                    <span><strong>Mis tickets</strong><small><?= (int) $totalTicketsFlujo ?> tickets registrados</small></span>
                </a>
            </div>
        </section>
    <?php elseif ($vista === 'nueva'): ?>
        <section class="panel">
            <div class="section-heading">
                <div>
                    <h2>Seleccione el catálogo</h2>
                    <p>Elija el área a la que corresponde su necesidad.</p>
                </div>
                <div class="step-chip"><span>1</span> Catálogo</div>
            </div>

            <?php if ($catalogos): ?>
                <div class="catalog-grid" id="catalogGrid">
                    <?php foreach ($catalogos as $catalogo): ?>
                        <button
                            type="button"
                            class="catalog-card"
                            data-catalogo="<?= (int) $catalogo['id_catalogo'] ?>"
                            data-nombre="<?= escaparSolicitante($catalogo['nombre']) ?>"
                            aria-pressed="false"
                        >
                            <span class="catalog-icon">
                                <img
                                    src="<?= escaparSolicitante(seguridadUrlImagenCatalogo(
                                        (int) $catalogo['id_catalogo'],
                                        $catalogo['imagen']
                                    )) ?>"
                                    alt=""
                                    onerror="this.onerror=null;this.src='assets/images/default-catalog.svg'"
                                >
                            </span>
                            <span>
                                <strong><?= escaparSolicitante($catalogo['nombre']) ?></strong>
                                <small><?= escaparSolicitante($catalogo['descripcion'] ?: 'Servicios disponibles') ?></small>
                            </span>
                            <svg class="card-arrow" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m9 18 6-6-6-6"/>
                            </svg>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty">
                    No hay catálogos activos disponibles en este momento.
                </div>
            <?php endif; ?>
        </section>

        <section class="panel services-panel" id="servicesPanel" hidden>
            <div class="section-heading">
                <div>
                    <h2 id="servicesTitle">Seleccione el servicio</h2>
                    <p>Elija la opción que mejor describa su solicitud.</p>
                </div>
                <div class="step-chip"><span>2</span> Servicio</div>
            </div>

            <div class="service-grid" id="serviceGrid">
                <?php foreach ($servicios as $servicio): ?>
                    <button
                        type="button"
                        class="service-card"
                        data-catalogo="<?= (int) $servicio['id_catalogo'] ?>"
                        data-servicio="<?= (int) $servicio['id_servicio'] ?>"
                        data-nombre="<?= escaparSolicitante($servicio['nombre']) ?>"
                        data-descripcion="<?= escaparSolicitante($servicio['descripcion']) ?>"
                        data-sla="<?= escaparSolicitante($servicio['sla_nombre']) ?>"
                        hidden
                    >
                        <span class="service-top">
                            <span class="service-symbol">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 5h16v14H4zM8 9h8M8 13h5"/>
                                </svg>
                            </span>
                            <span class="sla-tag"><?= escaparSolicitante($servicio['sla_nombre']) ?></span>
                        </span>
                        <strong><?= escaparSolicitante($servicio['nombre']) ?></strong>
                        <p><?= escaparSolicitante($servicio['descripcion'] ?: 'Sin descripción adicional.') ?></p>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="empty" id="servicesEmpty" hidden>
                Este catálogo no tiene servicios activos disponibles.
            </div>
        </section>

        <section class="panel request-panel" id="requestPanel" hidden>
            <div class="section-heading">
                <div>
                    <h2>Complete la solicitud</h2>
                    <p>Describa claramente su necesidad para facilitar la atención.</p>
                </div>
                <div class="step-chip"><span>3</span> Detalles</div>
            </div>

            <div class="selected-service">
                <div>
                    <span>Servicio seleccionado</span>
                    <strong id="selectedServiceName">—</strong>
                </div>
                <em id="selectedServiceSla">—</em>
            </div>

            <form
                id="requestForm"
                method="POST"
                enctype="multipart/form-data"
            >
                <input type="hidden" name="csrf_token" value="<?= escaparSolicitante($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="accion" value="crear_ticket">
                <input type="hidden" id="selectedServiceId" name="id_servicio" value="">

                <div class="form-grid">
                    <div class="field full">
                        <label for="titulo">
                            Título de la solicitud <span class="required">*</span>
                        </label>
                        <input
                            id="titulo"
                            type="text"
                            name="titulo"
                            maxlength="180"
                            placeholder="Ejemplo: No puedo acceder al correo corporativo"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="urgencia">
                            Urgencia <span class="required">*</span>
                        </label>
                        <select id="urgencia" name="urgencia" required>
                            <option value="baja">Baja</option>
                            <option value="moderada" selected>Moderada</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                        <span class="field-help">
                            Seleccione según el impacto real de la solicitud.
                        </span>
                    </div>

                    <div class="field">
                        <label>Archivos adjuntos</label>
                        <label class="file-box" for="requestFiles">
                            <input
                                id="requestFiles"
                                type="file"
                                name="adjuntos[]"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                            >
                            <span class="file-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M21.4 11.6 12 21a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 1 1-2.8-2.8l8.5-8.5"/>
                                </svg>
                            </span>
                            <span>
                                <strong>Seleccione hasta 5 archivos</strong>
                                <span id="requestFilesText">Máximo 5 MB por archivo.</span>
                            </span>
                        </label>
                    </div>

                    <div class="field full">
                        <label for="descripcion">
                            Descripción detallada <span class="required">*</span>
                        </label>
                        <textarea
                            id="descripcion"
                            name="descripcion"
                            maxlength="15000"
                            placeholder="Explique qué sucede, desde cuándo ocurre y cualquier información que ayude a resolver la solicitud."
                            required
                        ></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-soft" id="changeService">
                        Cambiar servicio
                    </button>
                    <button type="submit" class="btn btn-primary" id="createTicketButton">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        <span>Crear solicitud</span>
                    </button>
                </div>
            </form>
        </section>
    <?php else: ?>
        <section class="ticket-stats" aria-label="Resumen de tickets">
            <div class="stat-card"><span>Total</span><strong><?= (int) $conteos['total'] ?></strong></div>
            <div class="stat-card"><span>Abiertos</span><strong><?= (int) $conteos['abiertos'] ?></strong></div>
            <div class="stat-card"><span>En gestión</span><strong><?= (int) $conteos['proceso'] ?></strong></div>
            <div class="stat-card"><span>Finalizados</span><strong><?= (int) $conteos['resueltos'] ?></strong></div>
        </section>

        <section class="panel">
            <div class="filter-bar">
                <div>
                    <h2 style="margin:0;color:var(--navy);font-size:23px">Mis tickets</h2>
                    <p style="margin:7px 0 0;color:var(--muted);font-size:13px">
                        Consulte el estado y converse con el equipo encargado.
                    </p>
                </div>
                <form method="GET">
                    <input type="hidden" name="vista" value="tickets">
                    <label for="estadoFiltro">Filtrar por estado</label>
                    <select id="estadoFiltro" name="estado" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadosValidos as $valor => $etiqueta): ?>
                            <option value="<?= escaparSolicitante($valor) ?>" <?= $filtroEstado === $valor ? 'selected' : '' ?>>
                                <?= escaparSolicitante($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if (!$tickets): ?>
                <div class="empty">
                    No tiene tickets para el filtro seleccionado.
                    <br>
                    <a href="panelSolicitante.php?vista=nueva" style="color:var(--primary);font-weight:750">
                        Crear una nueva solicitud
                    </a>
                </div>
            <?php else: ?>
                <div class="ticket-layout">
                    <div class="ticket-list">
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                            $urlTicket = 'panelSolicitante.php?' . http_build_query([
                                'vista' => 'tickets',
                                'estado' => $filtroEstado,
                                'id_ticket' => (int) $ticket['id_ticket'],
                            ]);
                            ?>
                            <a
                                class="ticket-card <?= $idTicketSeleccionado === (int) $ticket['id_ticket'] ? 'active' : '' ?>"
                                href="<?= escaparSolicitante($urlTicket) ?>"
                            >
                                <div class="ticket-card-top">
                                    <span class="ticket-id">#<?= (int) $ticket['id_ticket'] ?></span>
                                    <span class="status <?= escaparSolicitante($ticket['estado']) ?>">
                                        <?= escaparSolicitante(
                                            $ticket['estado'] === 'cerrada'
                                            && (int) ($ticket['tiene_calificacion'] ?? 0) === 1
                                                ? 'Cerrado con calificación'
                                                : ($estadosValidos[$ticket['estado']] ?? $ticket['estado'])
                                        ) ?>
                                    </span>
                                </div>
                                <h3><?= escaparSolicitante($ticket['titulo']) ?></h3>
                                <p>
                                    <?= escaparSolicitante($ticket['catalogo'] ?: 'Sin catálogo') ?>
                                    ·
                                    <?= escaparSolicitante($ticket['servicio'] ?: 'Sin servicio') ?>
                                </p>
                                <div class="ticket-card-meta">
                                    <span><?= escaparSolicitante(formatoFechaSolicitante($ticket['fecha_creacion'])) ?></span>
                                    <span><?= escaparSolicitante($urgenciasValidas[$ticket['urgencia']] ?? $ticket['urgencia']) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($ticketSeleccionado): ?>
                        <article class="ticket-detail">
                            <header class="detail-header">
                                <div class="detail-title-row">
                                    <div>
                                        <span class="ticket-id">SOLICITUD #<?= (int) $ticketSeleccionado['id_ticket'] ?></span>
                                        <h2><?= escaparSolicitante($ticketSeleccionado['titulo']) ?></h2>
                                    </div>
                                    <span class="status <?= escaparSolicitante($ticketSeleccionado['estado']) ?>">
                                        <?= escaparSolicitante(
                                            $ticketSeleccionado['estado'] === 'cerrada'
                                            && $ticketSeleccionado['calificacion'] !== null
                                                ? 'Cerrado con calificación'
                                                : (
                                                    $estadosValidos[$ticketSeleccionado['estado']]
                                                    ?? $ticketSeleccionado['estado']
                                                )
                                        ) ?>
                                    </span>
                                </div>

                                <div class="detail-meta">
                                    <div class="meta-item">
                                        <span>Servicio</span>
                                        <strong><?= escaparSolicitante($ticketSeleccionado['servicio'] ?: 'Sin servicio') ?></strong>
                                    </div>
                                    <div class="meta-item">
                                        <span>Responsable</span>
                                        <strong><?= escaparSolicitante($ticketSeleccionado['tecnico'] ?: 'Pendiente de asignación') ?></strong>
                                    </div>
                                    <div class="meta-item">
                                        <span>Vencimiento SLA</span>
                                        <strong><?= escaparSolicitante(formatoFechaSolicitante($ticketSeleccionado['fecha_vencimiento'])) ?></strong>
                                    </div>
                                </div>
                            </header>

                            <div class="description-box"><?= escaparSolicitante($ticketSeleccionado['descripcion']) ?></div>

                            <?php if (
                                in_array(
                                    $ticketSeleccionado['estado'],
                                    ['resuelta', 'resuelto'],
                                    true
                                )
                                && $ticketSeleccionado['calificacion'] === null
                            ): ?>
                                <form
                                    class="rating-panel js-rating-form"
                                    method="POST"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escaparSolicitante($_SESSION['csrf_token']) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="calificar_cerrar"
                                    >
                                    <input
                                        type="hidden"
                                        name="id_ticket"
                                        value="<?= (int) $ticketSeleccionado['id_ticket'] ?>"
                                    >
                                    <h3>Califique la atención para cerrar el ticket</h3>
                                    <p>
                                        El gestor marcó esta solicitud como resuelta.
                                        Seleccione de 1 a 5 estrellas y envíe la encuesta.
                                    </p>
                                    <div
                                        class="rating-stars"
                                        aria-label="Calificación de satisfacción"
                                    >
                                        <?php for ($estrella = 5; $estrella >= 1; $estrella--): ?>
                                            <input
                                                id="ticket-<?= (int) $ticketSeleccionado['id_ticket'] ?>-star-<?= $estrella ?>"
                                                type="radio"
                                                name="calificacion"
                                                value="<?= $estrella ?>"
                                                required
                                            >
                                            <label
                                                for="ticket-<?= (int) $ticketSeleccionado['id_ticket'] ?>-star-<?= $estrella ?>"
                                                title="<?= $estrella ?> de 5"
                                                aria-label="<?= $estrella ?> de 5 estrellas"
                                            >★</label>
                                        <?php endfor; ?>
                                    </div>
                                    <textarea
                                        class="rating-comment"
                                        name="comentario_calificacion"
                                        maxlength="1000"
                                        placeholder="Comentario opcional sobre la atención recibida"
                                    ></textarea>
                                    <div class="survey-actions">
                                        <button
                                            type="submit"
                                            class="btn btn-primary js-rating-button"
                                        >
                                            Enviar calificación y cerrar
                                        </button>
                                    </div>
                                </form>
                            <?php elseif ($ticketSeleccionado['calificacion'] !== null): ?>
                                <div class="rating-summary">
                                    <strong>Ticket cerrado con calificación</strong>
                                    <div class="stars">
                                        <?= str_repeat('★', (int) $ticketSeleccionado['calificacion']) ?>
                                        <?= str_repeat('☆', 5 - (int) $ticketSeleccionado['calificacion']) ?>
                                    </div>
                                    <?php if (trim((string) $ticketSeleccionado['comentario_calificacion']) !== ''): ?>
                                        <div><?= escaparSolicitante($ticketSeleccionado['comentario_calificacion']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($ticketSeleccionado['cierre_tipo'] === 'automatico'): ?>
                                <div class="alert aviso" style="margin:11px 13px 0">
                                    <span><?= escaparSolicitante($ticketSeleccionado['motivo_cierre']) ?></span>
                                </div>
                            <?php endif; ?>

                            <section class="chat-section">
                                <div class="chat-heading">
                                    <h3>Comunicación</h3>
                                    <a
                                        href="panelSolicitante.php?vista=tickets&id_ticket=<?= (int) $ticketSeleccionado['id_ticket'] ?>"
                                        style="color:var(--primary);font-size:11px;font-weight:750;text-decoration:none"
                                    >
                                        Actualizar conversación
                                    </a>
                                </div>

                                <div class="chat-box" id="chatBox">
                                    <?php if (!$comunicaciones): ?>
                                        <div class="chat-empty">
                                            Aún no hay mensajes en esta solicitud.<br>
                                            Puede iniciar la conversación con el equipo encargado.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($comunicaciones as $comunicacion): ?>
                                            <?php $esPropio = (int) $comunicacion['id_emisor'] === $idUsuario; ?>
                                            <div class="message <?= $esPropio ? 'mine' : '' ?>">
                                                <div class="message-author">
                                                    <?= $esPropio ? 'Usted' : escaparSolicitante($comunicacion['emisor']) ?>
                                                </div>
                                                <div class="message-bubble">
                                                    <?= escaparSolicitante($comunicacion['mensaje']) ?>
                                                    <span class="message-time">
                                                        <?= escaparSolicitante(formatoFechaSolicitante($comunicacion['creado_en'])) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if (!in_array($ticketSeleccionado['estado'], ['cerrada', 'cancelada'], true)): ?>
                                    <form
                                        class="chat-compose"
                                        method="POST"
                                        enctype="multipart/form-data"
                                        id="chatForm"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= escaparSolicitante($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="enviar_mensaje">
                                        <input type="hidden" name="id_ticket" value="<?= (int) $ticketSeleccionado['id_ticket'] ?>">
                                        <textarea
                                            name="mensaje"
                                            maxlength="5000"
                                            placeholder="Escriba un mensaje para el equipo encargado..."
                                        ></textarea>
                                        <div class="compose-actions">
                                            <label class="compact-file" for="chatFiles">
                                                <input
                                                    id="chatFiles"
                                                    type="file"
                                                    name="adjuntos[]"
                                                    multiple
                                                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                                >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M21.4 11.6 12 21a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 1 1-2.8-2.8l8.5-8.5"/>
                                                </svg>
                                                <span id="chatFilesText">Adjuntar archivos</span>
                                            </label>
                                            <button type="submit" class="btn btn-primary" id="sendMessageButton">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="m22 2-7 20-4-9-9-4Z"/>
                                                    <path d="M22 2 11 13"/>
                                                </svg>
                                                <span>Enviar mensaje</span>
                                            </button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="alert aviso" style="margin-top:14px;margin-bottom:0">
                                        <span>Esta conversación se encuentra cerrada.</span>
                                    </div>
                                <?php endif; ?>
                            </section>

                            <section class="attachments">
                                <h3>Documentos de la solicitud</h3>
                                <?php if (!$adjuntos): ?>
                                    <div class="empty" style="padding:18px">
                                        No se han adjuntado documentos.
                                    </div>
                                <?php else: ?>
                                    <div class="attachment-list">
                                        <?php foreach ($adjuntos as $adjunto): ?>
                                            <?php
                                            $extension = strtoupper(
                                                pathinfo(
                                                    (string) $adjunto['nombre_original'],
                                                    PATHINFO_EXTENSION
                                                ) ?: 'ARCH'
                                            );
                                            ?>
                                            <div class="attachment">
                                                <div class="attachment-icon"><?= escaparSolicitante(substr($extension, 0, 4)) ?></div>
                                                <div>
                                                    <strong><?= escaparSolicitante($adjunto['nombre_original']) ?></strong>
                                                    <small>
                                                        <?= escaparSolicitante(formatoTamanoSolicitante((int) $adjunto['tamano'])) ?>
                                                        ·
                                                        <?= escaparSolicitante($adjunto['usuario']) ?>
                                                        ·
                                                        <?= escaparSolicitante(formatoFechaSolicitante($adjunto['creado_en'])) ?>
                                                    </small>
                                                </div>
                                                <a
                                                    class="download-link"
                                                    href="descargarAdjunto.php?id=<?= (int) $adjunto['id_adjunto'] ?>"
                                                >
                                                    Descargar
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        </article>
                    <?php else: ?>
                        <div class="empty">
                            Seleccione un ticket de la lista para consultar sus detalles,
                            documentos y conversación.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php if ($moduloInstalado): ?>
    <div
        class="survey-overlay <?= $encuestaPendiente ? 'visible' : '' ?>"
        id="surveyModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="surveyTitle"
        aria-hidden="<?= $encuestaPendiente ? 'false' : 'true' ?>"
    >
        <div class="survey-dialog">
            <div class="survey-header">
                <div class="survey-title">
                    <div class="survey-icon" aria-hidden="true">★</div>
                    <div>
                        <h2 id="surveyTitle">Califique la atención recibida</h2>
                        <p>El gestor marcó su solicitud como resuelta.</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="survey-close"
                    id="closeSurvey"
                    title="Calificar después"
                    aria-label="Calificar después"
                >&times;</button>
            </div>

            <div class="survey-ticket">
                <span id="surveyTicketNumber">
                    TICKET #<?= (int) ($encuestaPendiente['id_ticket'] ?? 0) ?>
                </span>
                <strong id="surveyTicketTitle">
                    <?= escaparSolicitante($encuestaPendiente['titulo'] ?? 'Solicitud resuelta') ?>
                </strong>
                <small>
                    Gestor:
                    <span id="surveyManager" style="display:inline;color:inherit;font-size:inherit;font-weight:inherit">
                        <?= escaparSolicitante($encuestaPendiente['gestor'] ?? 'Gestor asignado') ?>
                    </span>
                </small>
            </div>

            <form
                class="rating-panel js-rating-form"
                method="POST"
                id="ratingForm"
            >
                <input type="hidden" name="csrf_token" value="<?= escaparSolicitante($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="accion" value="calificar_cerrar">
                <input
                    type="hidden"
                    id="surveyTicketId"
                    name="id_ticket"
                    value="<?= (int) ($encuestaPendiente['id_ticket'] ?? 0) ?>"
                >
                <h3>¿Qué tan satisfecho está con la atención?</h3>
                <p>
                    Seleccione de 1 a 5 estrellas. Al enviar la encuesta,
                    el ticket y la conversación quedarán cerrados.
                </p>
                <div class="rating-stars" aria-label="Calificación de satisfacción">
                    <?php for ($estrella = 5; $estrella >= 1; $estrella--): ?>
                        <input
                            id="survey-star-<?= $estrella ?>"
                            type="radio"
                            name="calificacion"
                            value="<?= $estrella ?>"
                            required
                        >
                        <label
                            for="survey-star-<?= $estrella ?>"
                            title="<?= $estrella ?> de 5"
                            aria-label="<?= $estrella ?> de 5 estrellas"
                        >★</label>
                    <?php endfor; ?>
                </div>
                <textarea
                    class="rating-comment"
                    name="comentario_calificacion"
                    maxlength="1000"
                    placeholder="Comentario opcional sobre la atención recibida"
                ></textarea>
                <div class="survey-actions">
                    <button type="button" class="btn btn-soft" id="laterSurvey">
                        Calificar después
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary js-rating-button"
                        id="ratingButton"
                    >
                        Enviar y cerrar ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    const catalogButtons = document.querySelectorAll('.catalog-card');
    const serviceCards = document.querySelectorAll('.service-card');
    const servicesPanel = document.getElementById('servicesPanel');
    const servicesTitle = document.getElementById('servicesTitle');
    const servicesEmpty = document.getElementById('servicesEmpty');
    const requestPanel = document.getElementById('requestPanel');
    const selectedServiceId = document.getElementById('selectedServiceId');
    const selectedServiceName = document.getElementById('selectedServiceName');
    const selectedServiceSla = document.getElementById('selectedServiceSla');
    const changeService = document.getElementById('changeService');

    catalogButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const catalogId = button.dataset.catalogo;
            const catalogName = button.dataset.nombre;
            let visibleServices = 0;

            catalogButtons.forEach(function (item) {
                const active = item === button;
                item.classList.toggle('active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            serviceCards.forEach(function (service) {
                const visible = service.dataset.catalogo === catalogId;
                service.hidden = !visible;

                if (visible) {
                    visibleServices++;
                }
            });

            servicesTitle.textContent = 'Servicios de ' + catalogName;
            servicesEmpty.hidden = visibleServices > 0;
            servicesPanel.hidden = false;
            requestPanel.hidden = true;
            servicesPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    serviceCards.forEach(function (card) {
        card.addEventListener('click', function () {
            selectedServiceId.value = card.dataset.servicio;
            selectedServiceName.textContent = card.dataset.nombre;
            selectedServiceSla.textContent = card.dataset.sla || 'SLA asignado';
            requestPanel.hidden = false;
            requestPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if (changeService) {
        changeService.addEventListener('click', function () {
            requestPanel.hidden = true;
            servicesPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    function actualizarNombreArchivos(input, destino, textoVacio) {
        if (!input || !destino) {
            return;
        }

        input.addEventListener('change', function () {
            const cantidad = input.files.length;

            if (cantidad === 0) {
                destino.textContent = textoVacio;
            } else if (cantidad === 1) {
                destino.textContent = input.files[0].name;
            } else {
                destino.textContent = cantidad + ' archivos seleccionados';
            }
        });
    }

    actualizarNombreArchivos(
        document.getElementById('requestFiles'),
        document.getElementById('requestFilesText'),
        'Máximo 5 MB por archivo.'
    );

    actualizarNombreArchivos(
        document.getElementById('chatFiles'),
        document.getElementById('chatFilesText'),
        'Adjuntar archivos'
    );

    const requestForm = document.getElementById('requestForm');

    if (requestForm) {
        requestForm.addEventListener('submit', function (event) {
            const input = document.getElementById('requestFiles');

            if (!selectedServiceId.value) {
                event.preventDefault();
                window.alert('Seleccione primero un servicio.');
                return;
            }

            if (input && input.files.length > 5) {
                event.preventDefault();
                window.alert('Puede adjuntar máximo 5 archivos.');
                return;
            }

            const button = document.getElementById('createTicketButton');
            button.disabled = true;
            button.querySelector('span').textContent = 'Creando solicitud...';
        });
    }

    const chatForm = document.getElementById('chatForm');

    if (chatForm) {
        chatForm.addEventListener('submit', function (event) {
            const message = chatForm.querySelector('textarea');
            const files = document.getElementById('chatFiles');

            if (message.value.trim() === '' && files.files.length === 0) {
                event.preventDefault();
                window.alert('Escriba un mensaje o seleccione un archivo.');
                return;
            }

            if (files.files.length > 5) {
                event.preventDefault();
                window.alert('Puede adjuntar máximo 5 archivos.');
                return;
            }

            const button = document.getElementById('sendMessageButton');
            button.disabled = true;
            button.querySelector('span').textContent = 'Enviando...';
        });
    }

    const chatBox = document.getElementById('chatBox');

    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    const ratingForms = document.querySelectorAll('.js-rating-form');
    const surveyModal = document.getElementById('surveyModal');
    const surveyTicketId = document.getElementById('surveyTicketId');
    const surveyTicketNumber = document.getElementById('surveyTicketNumber');
    const surveyTicketTitle = document.getElementById('surveyTicketTitle');
    const surveyManager = document.getElementById('surveyManager');
    const closeSurvey = document.getElementById('closeSurvey');
    const laterSurvey = document.getElementById('laterSurvey');
    let dismissedSurveyTicket = 0;

    function hideSurvey() {
        if (!surveyModal) {
            return;
        }

        dismissedSurveyTicket = Number(surveyTicketId.value || 0);
        surveyModal.classList.remove('visible');
        surveyModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('survey-open');
    }

    function showSurvey(data) {
        if (
            !surveyModal
            || !data
            || Number(data.id_ticket) === dismissedSurveyTicket
        ) {
            return;
        }

        surveyTicketId.value = data.id_ticket;
        surveyTicketNumber.textContent = 'TICKET #' + data.id_ticket;
        surveyTicketTitle.textContent = data.titulo || 'Solicitud resuelta';
        surveyManager.textContent = data.gestor || 'Gestor asignado';
        surveyModal.classList.add('visible');
        surveyModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('survey-open');
    }

    if (surveyModal && surveyModal.classList.contains('visible')) {
        document.body.classList.add('survey-open');
    }

    if (closeSurvey) {
        closeSurvey.addEventListener('click', hideSurvey);
    }

    if (laterSurvey) {
        laterSurvey.addEventListener('click', hideSurvey);
    }

    async function checkPendingSurvey() {
        if (!surveyModal) {
            return;
        }

        try {
            const response = await fetch(
                'consultarEncuesta.php?_=' + Date.now(),
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                }
            );

            if (!response.ok) {
                return;
            }

            const result = await response.json();

            if (result.ok && result.encuesta) {
                showSurvey(result.encuesta);
            }
        } catch (error) {
            // La siguiente consulta volverá a intentarlo automáticamente.
        }
    }

    if (surveyModal) {
        checkPendingSurvey();
        window.setInterval(checkPendingSurvey, 10000);
    }

    ratingForms.forEach(function (ratingForm) {
        ratingForm.addEventListener('submit', function (event) {
            const selected = ratingForm.querySelector(
                'input[name="calificacion"]:checked'
            );

            if (!selected) {
                event.preventDefault();
                window.alert('Seleccione una calificación de 1 a 5 estrellas.');
                return;
            }

            if (!window.confirm(
                '¿Desea enviar la calificación y cerrar definitivamente el ticket?'
            )) {
                event.preventDefault();
                return;
            }

            const button = ratingForm.querySelector('.js-rating-button');

            if (button) {
                button.disabled = true;
                button.textContent = 'Guardando y cerrando...';
            }
        });
    });
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
