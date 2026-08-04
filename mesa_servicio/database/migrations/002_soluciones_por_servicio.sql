USE `mesa_servicio`;

CREATE TABLE IF NOT EXISTS `soluciones_servicio` (
  `id_solucion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_servicio` int(11) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `estado` enum('activo','inhabilitado') NOT NULL DEFAULT 'activo',
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `creado_por` int(11) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_solucion`),
  KEY `idx_soluciones_servicio_estado` (`id_servicio`,`estado`,`orden`),
  KEY `idx_soluciones_creado_por` (`creado_por`),
  KEY `idx_soluciones_actualizado_por` (`actualizado_por`),
  CONSTRAINT `fk_soluciones_servicio`
    FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`)
    ON UPDATE CASCADE,
  CONSTRAINT `fk_soluciones_creado_por`
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_soluciones_actualizado_por`
    FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `ticket_etapas`
  ADD COLUMN IF NOT EXISTS `id_solucion` int(10) UNSIGNED DEFAULT NULL
    AFTER `resultado_sla`,
  ADD COLUMN IF NOT EXISTS `solucion_nombre` varchar(180) DEFAULT NULL
    AFTER `id_solucion`,
  ADD INDEX IF NOT EXISTS `idx_ticket_etapas_solucion`
    (`id_solucion`,`id_servicio`,`estado`);

SELECT
  DATABASE() AS `base_aplicada`,
  'Módulo de soluciones por servicio instalado correctamente' AS `resultado`;
