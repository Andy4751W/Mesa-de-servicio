<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (
    !flujoModuloInstalado($conn)
    || !flujoModuloSolucionesInstalado($conn)
    || !flujoModuloAprobacionCasosInstalado($conn)
) {
    http_response_code(409);
    exit('El módulo de Tickets todavía no está instalado por completo.');
}

function etiquetaExcelSolicitud(?string $valor): string
{
    $valor = trim((string) $valor);

    if ($valor === '') {
        return '';
    }

    return ucfirst(str_replace('_', ' ', $valor));
}

function fechaExcelSolicitud(?string $fecha): string
{
    if (!$fecha) {
        return '';
    }

    $marca = strtotime($fecha);

    return $marca ? date('d/m/Y H:i', $marca) : $fecha;
}

function limpiarXmlSolicitud(mixed $valor): string
{
    $texto = (string) $valor;
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto) ?? '';

    return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function columnaExcelSolicitud(int $indice): string
{
    $columna = '';

    while ($indice > 0) {
        $indice--;
        $columna = chr(65 + ($indice % 26)) . $columna;
        $indice = intdiv($indice, 26);
    }

    return $columna;
}

function celdaExcelSolicitud(
    int $columna,
    int $fila,
    mixed $valor,
    int $estilo = 0
): string {
    $referencia = columnaExcelSolicitud($columna) . $fila;
    $texto = (string) $valor;
    $texto = function_exists('mb_substr')
        ? mb_substr($texto, 0, 30000, 'UTF-8')
        : substr($texto, 0, 30000);

    return '<c r="' . $referencia . '" t="inlineStr" s="' . $estilo . '">'
        . '<is><t xml:space="preserve">'
        . limpiarXmlSolicitud($texto)
        . '</t></is></c>';
}

function valorCsvSeguroSolicitud(mixed $valor): string
{
    $texto = (string) $valor;

    /*
     * Excel interpreta como fórmula cualquier celda CSV que empiece por
     * =, +, - o @. El apóstrofo obliga a tratar el contenido como texto.
     */
    if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $texto) === 1) {
        return "'" . $texto;
    }

    return $texto;
}

function descargarCsvSolicitudes(array $encabezados, array $filas): never
{
    $nombre = 'base_tickets_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $salida = fopen('php://output', 'wb');

    if ($salida === false) {
        throw new RuntimeException('No fue posible generar el archivo.');
    }

    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, $encabezados, ';');

    foreach ($filas as $fila) {
        fputcsv(
            $salida,
            array_map('valorCsvSeguroSolicitud', $fila),
            ';'
        );
    }

    fclose($salida);
    exit;
}

$conn->query('SET SESSION group_concat_max_len = 100000');

$sql = "
    SELECT
        t.id_ticket, t.estado, t.estado_flujo, t.titulo, t.descripcion,
        t.urgencia, t.prioridad, t.fecha_creacion, t.fecha_finalizacion,
        t.cierre_tipo, t.motivo_cierre, t.actualizado_en,
        p.nombre AS tipo_ticket,
        p.descripcion AS descripcion_tipo_ticket,
        solicitante.id_usuario AS solicitante_id,
        solicitante.cedula AS solicitante_cedula,
        solicitante.nombre AS solicitante,
        solicitante.proceso AS solicitante_proceso,
        solicitante.cu1 AS solicitante_cu1,
        solicitante.cu3 AS solicitante_cu3,
        solicitante.email AS correo_solicitante,
        solicitante.descripcion_cu1 AS solicitante_descripcion_cu1,
        solicitante.ciudad AS solicitante_ciudad,
        solicitante.empresa AS solicitante_empresa,
        solicitante.id_rol AS solicitante_id_rol,
        rol_solicitante.nombre_rol AS solicitante_rol,
        solicitante.estado AS solicitante_estado,
        solicitante.creado_en AS solicitante_creado_en,
        solicitante.actualizado_en AS solicitante_actualizado_en,
        (SELECT COUNT(*) FROM ticket_etapas AS total_te
         WHERE total_te.id_ticket = t.id_ticket) AS total_etapas,
        (SELECT COUNT(*) FROM ticket_etapas AS completa_te
         WHERE completa_te.id_ticket = t.id_ticket
           AND completa_te.estado = 'completada') AS etapas_completadas,
        te.id_ticket_etapa,
        te.id_ticket_etapa_padre,
        COALESCE(NULLIF(te.creado_por, 0), t.id_usuario) AS creador_caso_id,
        COALESCE(creador_caso.nombre, solicitante.nombre, 'Usuario eliminado') AS creador_caso,
        te.nivel AS etapa_nivel,
        te.orden AS etapa_orden,
        CASE WHEN t.id_etapa_actual = te.id_ticket_etapa THEN 'Sí' ELSE 'No' END AS etapa_actual,
        COALESCE(pe.nombre_etapa, te.servicio_nombre) AS etapa_nombre,
        pe.instrucciones AS etapa_instrucciones,
        te.catalogo_nombre AS etapa_area,
        te.servicio_nombre AS etapa_servicio,
        te.id_gestor AS gestor_id,
        COALESCE(gestor.nombre, te.gestor_nombre, 'Sin asignar') AS gestor,
        te.estado AS etapa_estado,
        te.sla_nombre AS sla,
        te.sla_tiempo,
        te.sla_unidad,
        te.fecha_activacion,
        te.fecha_vencimiento,
        te.fecha_marcado_listo,
        te.minutos_hasta_listo,
        te.resultado_sla_listo,
        te.marcado_listo_por,
        COALESCE(marcador_listo.nombre, 'Sin marcar') AS marcador_listo,
        te.fecha_ultima_reapertura,
        te.cantidad_reaperturas,
        te.fecha_finalizacion AS etapa_fecha_finalizacion,
        te.minutos_atencion,
        te.resultado_sla,
        te.id_solucion,
        te.solucion_nombre,
        te.comentario_cierre,
        (SELECT COUNT(*) FROM ticket_etapa_checklist AS tc
         WHERE tc.id_ticket_etapa = te.id_ticket_etapa) AS checklist_total,
        (SELECT COUNT(*) FROM ticket_etapa_checklist AS tc
         WHERE tc.id_ticket_etapa = te.id_ticket_etapa
           AND tc.completado = 1) AS checklist_completado,
        (SELECT COUNT(*) FROM ticket_etapa_checklist AS tc
         WHERE tc.id_ticket_etapa = te.id_ticket_etapa
           AND tc.obligatorio = 1 AND tc.completado = 0) AS checklist_obligatorio_pendiente,
        (SELECT GROUP_CONCAT(
                    CONCAT(
                        IF(tc.completado = 1, '[Listo] ', '[Pendiente] '),
                        tc.nombre,
                        IF(tc.observacion IS NULL OR tc.observacion = '', '', CONCAT(' — ', tc.observacion)),
                        IF(tc.evidencia_ruta IS NULL OR tc.evidencia_ruta = '', '', CONCAT(' — Evidencia: ', tc.evidencia_ruta))
                    )
                    ORDER BY tc.orden, tc.id_ticket_checklist SEPARATOR ' | '
                )
         FROM ticket_etapa_checklist AS tc
         WHERE tc.id_ticket_etapa = te.id_ticket_etapa) AS checklist_detalle,
        (SELECT COUNT(*) FROM solicitud_comunicaciones AS sc
         WHERE sc.id_ticket = t.id_ticket AND sc.tipo = 'publica') AS mensajes_ticket,
        (SELECT COUNT(*) FROM solicitud_comunicaciones AS sc
         WHERE sc.id_ticket = t.id_ticket
           AND sc.id_ticket_etapa = te.id_ticket_etapa
           AND sc.tipo = 'publica') AS mensajes_etapa,
        (SELECT GROUP_CONCAT(
                    CONCAT(
                        '[', DATE_FORMAT(sc.creado_en, '%Y-%m-%d %H:%i'), '] ',
                        COALESCE(emisor.nombre, 'Usuario eliminado'), ': ',
                        REPLACE(REPLACE(sc.mensaje, CHAR(13), ' '), CHAR(10), ' ')
                    )
                    ORDER BY sc.creado_en, sc.id_comunicacion SEPARATOR ' | '
                )
         FROM solicitud_comunicaciones AS sc
         LEFT JOIN usuarios AS emisor ON emisor.id_usuario = sc.id_emisor
         WHERE sc.id_ticket = t.id_ticket AND sc.tipo = 'publica') AS historial_chat,
        (SELECT COUNT(*) FROM solicitud_adjuntos AS sa
         WHERE sa.id_ticket = t.id_ticket) AS adjuntos_ticket,
        (SELECT COUNT(*) FROM solicitud_adjuntos AS sa
         WHERE sa.id_ticket = t.id_ticket
           AND sa.id_ticket_etapa = te.id_ticket_etapa) AS adjuntos_etapa,
        (SELECT GROUP_CONCAT(sa.nombre_original ORDER BY sa.creado_en, sa.id_adjunto SEPARATOR ' | ')
         FROM solicitud_adjuntos AS sa
         WHERE sa.id_ticket = t.id_ticket) AS listado_adjuntos,
        cal.calificacion,
        cal.calificacion_area,
        cal.calificacion_tiempo,
        cal.tipo_calificacion,
        cal.id_solicitante AS evaluador_id,
        COALESCE(evaluador.nombre, 'Sin calificar') AS evaluador,
        cal.comentario AS comentario_calificacion,
        cal.creado_en AS fecha_calificacion
    FROM tickets AS t
    INNER JOIN procesos AS p ON p.id_proceso = t.id_proceso
    LEFT JOIN usuarios AS solicitante ON solicitante.id_usuario = t.id_usuario
    LEFT JOIN roles AS rol_solicitante ON rol_solicitante.id_rol = solicitante.id_rol
    LEFT JOIN ticket_etapas AS te ON te.id_ticket = t.id_ticket
    LEFT JOIN proceso_etapas AS pe ON pe.id_proceso_etapa = te.id_proceso_etapa
    LEFT JOIN usuarios AS gestor ON gestor.id_usuario = te.id_gestor
    LEFT JOIN usuarios AS creador_caso
        ON creador_caso.id_usuario = COALESCE(NULLIF(te.creado_por, 0), t.id_usuario)
    LEFT JOIN usuarios AS marcador_listo
        ON marcador_listo.id_usuario = te.marcado_listo_por
    LEFT JOIN solicitud_calificaciones AS cal
        ON cal.id_ticket = t.id_ticket
       AND cal.id_ticket_etapa = te.id_ticket_etapa
    LEFT JOIN usuarios AS evaluador
        ON evaluador.id_usuario = cal.id_solicitante
    WHERE t.id_proceso IS NOT NULL
    ORDER BY t.id_ticket DESC, te.orden ASC
";

$resultado = $conn->query($sql);

if ($resultado === false) {
    http_response_code(500);
    exit('No fue posible generar la base de tickets.');
}
$encabezados = [
    'ID ticket', 'Estado general', 'Estado del flujo', 'Tipo de ticket',
    'Descripción del tipo de ticket', 'Título', 'Descripción', 'Urgencia',
    'Prioridad', 'Creación del ticket', 'Finalización del ticket',
    'Tipo de cierre', 'Motivo de cierre', 'Última actualización del ticket',
    'ID solicitante', 'Cédula solicitante', 'Nombre del solicitante',
    'Proceso o área del solicitante', 'CU1 solicitante', 'CU3 solicitante',
    'Correo solicitante', 'Descripción CU1 solicitante', 'Ciudad solicitante',
    'Empresa solicitante', 'ID rol solicitante', 'Rol solicitante',
    'Estado solicitante', 'Fecha de registro del solicitante',
    'Última actualización del solicitante', 'Total de etapas',
    'Etapas completadas', 'ID interno del caso', 'Código jerárquico del caso',
    'ID interno del caso padre', 'ID creador del caso', 'Creador del caso',
    'Nivel del caso', 'Orden del caso',
    'Es caso actual', 'Nombre de etapa', 'Instrucciones de etapa',
    'Área del caso', 'Servicio del caso', 'ID gestor', 'Gestor',
    'Estado del caso', 'SLA',
    'Tiempo SLA', 'Unidad SLA', 'Activación de etapa', 'Vencimiento visible de etapa',
    'Fecha en que se marcó Listo', 'Minutos hábiles hasta Listo',
    'Resultado SLA al marcar Listo', 'ID gestor que marcó Listo',
    'Gestor que marcó Listo', 'Última reapertura', 'Cantidad de reaperturas',
    'Finalización de etapa', 'Minutos de atención', 'Resultado SLA',
    'ID solución seleccionada', 'Solución seleccionada',
    'Observación obligatoria de la solución', 'Ítems de checklist',
    'Ítems de checklist completados', 'Obligatorios pendientes',
    'Detalle del checklist', 'Mensajes del ticket', 'Mensajes de la etapa',
    'Historial unificado del chat', 'Adjuntos del ticket', 'Adjuntos de la etapa',
    'Listado de adjuntos', 'Calificación general (1-5)',
    'Calificación del área (1-5)',
    'Calificación del tiempo de respuesta (1-5)',
    'Tipo de calificación', 'ID evaluador', 'Evaluador',
    'Comentario de calificación',
    'Fecha de calificación',
];
$filas = [];

while ($ticket = $resultado->fetch_assoc()) {
    $codigoCaso = !empty($ticket['id_ticket_etapa'])
        ? flujoCodigoCaso(
            $conn,
            (int) $ticket['id_ticket'],
            (int) $ticket['id_ticket_etapa']
        )
        : '';
    $filas[] = [
        (string) $ticket['id_ticket'],
        etiquetaExcelSolicitud($ticket['estado']),
        etiquetaExcelSolicitud($ticket['estado_flujo']),
        (string) ($ticket['tipo_ticket'] ?? ''),
        (string) ($ticket['descripcion_tipo_ticket'] ?? ''),
        (string) $ticket['titulo'],
        (string) $ticket['descripcion'],
        etiquetaExcelSolicitud($ticket['urgencia']),
        etiquetaExcelSolicitud($ticket['prioridad']),
        fechaExcelSolicitud($ticket['fecha_creacion']),
        fechaExcelSolicitud($ticket['fecha_finalizacion']),
        etiquetaExcelSolicitud($ticket['cierre_tipo']),
        (string) ($ticket['motivo_cierre'] ?? ''),
        fechaExcelSolicitud($ticket['actualizado_en']),
        (string) ($ticket['solicitante_id'] ?? ''),
        (string) ($ticket['solicitante_cedula'] ?? ''),
        (string) ($ticket['solicitante'] ?? 'Usuario eliminado'),
        (string) ($ticket['solicitante_proceso'] ?? ''),
        (string) ($ticket['solicitante_cu1'] ?? ''),
        (string) ($ticket['solicitante_cu3'] ?? ''),
        (string) ($ticket['correo_solicitante'] ?? ''),
        (string) ($ticket['solicitante_descripcion_cu1'] ?? ''),
        (string) ($ticket['solicitante_ciudad'] ?? ''),
        (string) ($ticket['solicitante_empresa'] ?? ''),
        (string) ($ticket['solicitante_id_rol'] ?? ''),
        (string) ($ticket['solicitante_rol'] ?? ''),
        etiquetaExcelSolicitud($ticket['solicitante_estado']),
        fechaExcelSolicitud($ticket['solicitante_creado_en']),
        fechaExcelSolicitud($ticket['solicitante_actualizado_en']),
        (string) ($ticket['total_etapas'] ?? 0),
        (string) ($ticket['etapas_completadas'] ?? 0),
        (string) ($ticket['id_ticket_etapa'] ?? ''),
        $codigoCaso,
        (string) ($ticket['id_ticket_etapa_padre'] ?? ''),
        (string) ($ticket['creador_caso_id'] ?? ''),
        (string) ($ticket['creador_caso'] ?? 'Usuario eliminado'),
        (string) ($ticket['etapa_nivel'] ?? ''),
        (string) ($ticket['etapa_orden'] ?? ''),
        (string) ($ticket['etapa_actual'] ?? 'No'),
        (string) ($ticket['etapa_nombre'] ?? ''),
        (string) ($ticket['etapa_instrucciones'] ?? ''),
        (string) ($ticket['etapa_area'] ?? ''),
        (string) ($ticket['etapa_servicio'] ?? ''),
        (string) ($ticket['gestor_id'] ?? ''),
        (string) ($ticket['gestor'] ?? 'Sin asignar'),
        etiquetaExcelSolicitud($ticket['etapa_estado']),
        (string) ($ticket['sla'] ?? 'Sin SLA'),
        (string) ($ticket['sla_tiempo'] ?? ''),
        etiquetaExcelSolicitud($ticket['sla_unidad']),
        fechaExcelSolicitud($ticket['fecha_activacion']),
        fechaExcelSolicitud($ticket['fecha_vencimiento']),
        fechaExcelSolicitud($ticket['fecha_marcado_listo']),
        (string) ($ticket['minutos_hasta_listo'] ?? ''),
        etiquetaExcelSolicitud($ticket['resultado_sla_listo']),
        (string) ($ticket['marcado_listo_por'] ?? ''),
        (string) ($ticket['marcador_listo'] ?? 'Sin marcar'),
        fechaExcelSolicitud($ticket['fecha_ultima_reapertura']),
        (string) ($ticket['cantidad_reaperturas'] ?? 0),
        fechaExcelSolicitud($ticket['etapa_fecha_finalizacion']),
        (string) ($ticket['minutos_atencion'] ?? ''),
        etiquetaExcelSolicitud($ticket['resultado_sla']),
        (string) ($ticket['id_solucion'] ?? ''),
        (string) ($ticket['solucion_nombre'] ?? (
            trim((string) ($ticket['comentario_cierre'] ?? '')) !== ''
                ? 'Cierre anterior sin clasificación'
                : ''
        )),
        (string) ($ticket['comentario_cierre'] ?? ''),
        (string) ($ticket['checklist_total'] ?? 0),
        (string) ($ticket['checklist_completado'] ?? 0),
        (string) ($ticket['checklist_obligatorio_pendiente'] ?? 0),
        (string) ($ticket['checklist_detalle'] ?? ''),
        (string) ($ticket['mensajes_ticket'] ?? 0),
        (string) ($ticket['mensajes_etapa'] ?? 0),
        (string) ($ticket['historial_chat'] ?? ''),
        (string) ($ticket['adjuntos_ticket'] ?? 0),
        (string) ($ticket['adjuntos_etapa'] ?? 0),
        (string) ($ticket['listado_adjuntos'] ?? ''),
        $ticket['calificacion'] === null ? 'Sin calificar' : (string) $ticket['calificacion'],
        $ticket['calificacion_area'] === null ? 'Sin calificar' : (string) $ticket['calificacion_area'],
        $ticket['calificacion_tiempo'] === null ? 'Sin calificar' : (string) $ticket['calificacion_tiempo'],
        etiquetaExcelSolicitud($ticket['tipo_calificacion']),
        (string) ($ticket['evaluador_id'] ?? ''),
        (string) ($ticket['evaluador'] ?? 'Sin calificar'),
        (string) ($ticket['comentario_calificacion'] ?? ''),
        fechaExcelSolicitud($ticket['fecha_calificacion']),
    ];
}

if (!class_exists('ZipArchive')) {
    descargarCsvSolicitudes($encabezados, $filas);
}

$filasXml = [];
$encabezadoXml = '';

foreach ($encabezados as $indice => $encabezado) {
    $encabezadoXml .= celdaExcelSolicitud($indice + 1, 1, $encabezado, 1);
}

$filasXml[] = '<row r="1" ht="27" customHeight="1">' . $encabezadoXml . '</row>';

foreach ($filas as $indiceFila => $fila) {
    $numeroFila = $indiceFila + 2;
    $celdas = '';

    foreach ($fila as $indiceColumna => $valor) {
        $celdas .= celdaExcelSolicitud(
            $indiceColumna + 1,
            $numeroFila,
            $valor,
            2
        );
    }

    $filasXml[] = '<row r="' . $numeroFila . '">' . $celdas . '</row>';
}

$ultimaFila = max(1, count($filas) + 1);
$ultimaColumna = columnaExcelSolicitud(count($encabezados));
$anchos = array_fill(0, count($encabezados), 22);
foreach ([4 => 38, 6 => 48, 20 => 32, 35 => 40, 49 => 45, 53 => 55, 56 => 70, 59 => 45, 61 => 45] as $indice => $ancho) {
    if (isset($anchos[$indice])) {
        $anchos[$indice] = $ancho;
    }
}
$columnasXml = '';

foreach ($anchos as $indice => $ancho) {
    $numero = $indice + 1;
    $columnasXml .= '<col min="' . $numero . '" max="' . $numero
        . '" width="' . $ancho . '" customWidth="1"/>';
}

$hojaXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<dimension ref="A1:' . $ultimaColumna . $ultimaFila . '"/>'
    . '<sheetViews><sheetView workbookViewId="0">'
    . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
    . '</sheetView></sheetViews>'
    . '<sheetFormatPr defaultRowHeight="18"/>'
    . '<cols>' . $columnasXml . '</cols>'
    . '<sheetData>' . implode('', $filasXml) . '</sheetData>'
    . '<autoFilter ref="A1:' . $ultimaColumna . $ultimaFila . '"/>'
    . '</worksheet>';

$estilosXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="10"/><name val="Calibri"/><family val="2"/></font>
    <font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Calibri"/><family val="2"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F5F99"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFDCE6F1"/></left>
      <right style="thin"><color rgb="FFDCE6F1"/></right>
      <top style="thin"><color rgb="FFDCE6F1"/></top>
      <bottom style="thin"><color rgb="FFDCE6F1"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1">
      <alignment vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1">
      <alignment vertical="top" wrapText="1"/>
    </xf>
  </cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>';

$libroXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
 xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Tickets" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

$relacionesLibroXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

$relacionesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

$tiposXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

$temporal = tempnam(sys_get_temp_dir(), 'base_tickets_xlsx_');

if ($temporal === false) {
    descargarCsvSolicitudes($encabezados, $filas);
}

$zip = new ZipArchive();

if ($zip->open($temporal, ZipArchive::OVERWRITE) !== true) {
    @unlink($temporal);
    descargarCsvSolicitudes($encabezados, $filas);
}

$zip->addFromString('[Content_Types].xml', $tiposXml);
$zip->addFromString('_rels/.rels', $relacionesXml);
$zip->addFromString('xl/workbook.xml', $libroXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $relacionesLibroXml);
$zip->addFromString('xl/styles.xml', $estilosXml);
$zip->addFromString('xl/worksheets/sheet1.xml', $hojaXml);
$zip->close();

$nombre = 'base_tickets_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($temporal));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($temporal);
@unlink($temporal);
exit;
