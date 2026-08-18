-- Recuperación segura de contraseña mediante código temporal.
-- Ejecute este archivo una sola vez sobre la base mesa_servicio existente.

USE `mesa_servicio`;

CREATE TABLE IF NOT EXISTS `recuperaciones_password` (
  `id_recuperacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `codigo_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `intentos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `solicitado_ip_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_recuperacion`),
  KEY `idx_recuperacion_usuario_activa` (`id_usuario`,`usado_en`,`expira_en`),
  KEY `idx_recuperacion_ip_fecha` (`solicitado_ip_hash`,`creado_en`),
  CONSTRAINT `fk_recuperacion_usuario`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
