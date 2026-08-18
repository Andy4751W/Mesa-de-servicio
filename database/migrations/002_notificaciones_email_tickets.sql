-- Preferencia individual de correo por caso para solicitante y gestor.
-- Ejecute este archivo una sola vez sobre la base mesa_servicio existente.

USE `mesa_servicio`;

CREATE TABLE IF NOT EXISTS `ticket_notificaciones_email_preferencias` (
  `id_preferencia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_ticket` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `habilitada` tinyint(1) NOT NULL DEFAULT 1,
  `creada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizada_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_preferencia`),
  UNIQUE KEY `uq_preferencia_ticket_usuario` (`id_ticket`,`id_usuario`),
  KEY `idx_preferencia_usuario` (`id_usuario`,`habilitada`),
  CONSTRAINT `fk_preferencia_email_ticket`
    FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_preferencia_email_usuario`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
