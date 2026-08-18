-- Preferencia privada de apariencia para cada cuenta de usuario.
-- Ejecutar una sola vez en la base de datos de la Mesa de Servicio.

CREATE TABLE IF NOT EXISTS `usuario_preferencias_interfaz` (
  `id_usuario` int(11) NOT NULL,
  `tema` varchar(40) NOT NULL DEFAULT 'corporativo',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  CONSTRAINT `fk_preferencia_interfaz_usuario`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

