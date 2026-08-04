USE mesa_servicio;

CREATE TABLE IF NOT EXISTS seguridad_intentos_login (
    clave CHAR(64) NOT NULL,
    intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_intento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_hasta DATETIME NULL,
    PRIMARY KEY (clave),
    INDEX idx_seguridad_bloqueo (bloqueado_hasta),
    INDEX idx_seguridad_ultimo_intento (ultimo_intento)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Puede programarse diariamente sin afectar intentos recientes:
-- DELETE FROM seguridad_intentos_login
-- WHERE ultimo_intento < DATE_SUB(NOW(), INTERVAL 7 DAY);
