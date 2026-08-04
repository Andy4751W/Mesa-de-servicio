-- Mesa de servicio: aprobación por creador, reapertura y encuestas por caso.
-- Compatible con MariaDB 10.4 y phpMyAdmin sin acceso a information_schema.
-- Ejecute una sola vez después de las migraciones anteriores.

USE `mesa_servicio`;

ALTER TABLE `ticket_etapas`
  MODIFY COLUMN `estado`
    enum(
      'bloqueada',
      'pendiente',
      'en_proceso',
      'en_espera_solicitante',
      'pausada',
      'listo_cierre',
      'completada',
      'cancelada'
    ) NOT NULL DEFAULT 'bloqueada',
  ADD COLUMN IF NOT EXISTS `fecha_marcado_listo` datetime DEFAULT NULL
    AFTER `fecha_pausa`,
  ADD COLUMN IF NOT EXISTS `minutos_hasta_listo` int(10) UNSIGNED DEFAULT NULL
    AFTER `fecha_marcado_listo`,
  ADD COLUMN IF NOT EXISTS `resultado_sla_listo`
    enum('dentro_sla','fuera_sla') DEFAULT NULL
    AFTER `minutos_hasta_listo`,
  ADD COLUMN IF NOT EXISTS `marcado_listo_por` int(11) DEFAULT NULL
    AFTER `resultado_sla_listo`,
  ADD COLUMN IF NOT EXISTS `fecha_ultima_reapertura` datetime DEFAULT NULL
    AFTER `marcado_listo_por`,
  ADD COLUMN IF NOT EXISTS `cantidad_reaperturas` int(10) UNSIGNED NOT NULL DEFAULT 0
    AFTER `fecha_ultima_reapertura`,
  ADD INDEX IF NOT EXISTS `idx_ticket_etapas_aprobacion`
    (`estado`,`creado_por`,`id_gestor`,`fecha_marcado_listo`);

ALTER TABLE `solicitud_calificaciones`
  ADD COLUMN IF NOT EXISTS `tipo_calificacion`
    enum(
      'encuesta_servicio',
      'evaluacion_derivacion',
      'evaluacion_caso',
      'historica'
    ) NOT NULL DEFAULT 'historica'
    AFTER `calificacion_tiempo`,
  ADD INDEX IF NOT EXISTS `idx_calificaciones_tipo_fecha`
    (`tipo_calificacion`,`creado_en`);

-- Asegura que cada etapa raíz tenga identificado al creador del ticket.
UPDATE `ticket_etapas` AS te
INNER JOIN `tickets` AS t ON t.`id_ticket` = te.`id_ticket`
SET te.`creado_por` = t.`id_usuario`
WHERE te.`creado_por` IS NULL
  AND te.`id_ticket_etapa_padre` IS NULL;

-- Conserva las mediciones de los casos cerrados antes de esta actualización.
UPDATE `ticket_etapas`
SET
  `fecha_marcado_listo` = COALESCE(`fecha_marcado_listo`, `fecha_finalizacion`),
  `minutos_hasta_listo` = COALESCE(`minutos_hasta_listo`, `minutos_atencion`),
  `resultado_sla_listo` = COALESCE(
    `resultado_sla_listo`,
    NULLIF(`resultado_sla`, 'sin_iniciar')
  ),
  `marcado_listo_por` = COALESCE(`marcado_listo_por`, `completado_por`)
WHERE `estado` = 'completada';

-- Clasifica las evaluaciones existentes sin crear nuevas encuestas.
UPDATE `solicitud_calificaciones` AS cal
INNER JOIN `ticket_etapas` AS te
  ON te.`id_ticket_etapa` = cal.`id_ticket_etapa`
LEFT JOIN (
  SELECT
    `id_ticket`,
    MIN(`orden`) AS `primer_orden_raiz`
  FROM `ticket_etapas`
  WHERE `id_ticket_etapa_padre` IS NULL
  GROUP BY `id_ticket`
) AS raiz ON raiz.`id_ticket` = te.`id_ticket`
SET cal.`tipo_calificacion` = CASE
  WHEN te.`id_ticket_etapa_padre` IS NOT NULL
    THEN 'evaluacion_derivacion'
  WHEN te.`orden` = raiz.`primer_orden_raiz`
    THEN 'encuesta_servicio'
  ELSE 'evaluacion_caso'
END
WHERE cal.`tipo_calificacion` = 'historica';

SELECT
  DATABASE() AS `base_aplicada`,
  'Aprobación, reapertura, SLA hasta Listo y encuestas por caso instalados correctamente' AS `resultado`;
