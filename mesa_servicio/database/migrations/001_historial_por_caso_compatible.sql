-- Mesa de servicio: historial independiente por caso.
-- Versión compatible con instalaciones donde information_schema está restringida.
-- Motor validado para la base entregada: MariaDB 10.4.x.
--
-- IMPORTANTE:
-- 1. Haga primero una copia de seguridad.
-- 2. Ejecute este archivo una sola vez.
-- 3. No cambie el nombre de la base salvo que su instalación use otro nombre.

USE `mesa_servicio`;

-- Relaciona cada evento del historial con el caso concreto del árbol.
ALTER TABLE `solicitud_historial`
    ADD COLUMN `id_ticket_etapa` BIGINT(20) UNSIGNED NULL AFTER `id_ticket`;

-- Los eventos históricos de tickets que solo tienen un caso se pueden
-- asignar sin ambigüedad. Los demás permanecen en NULL deliberadamente.
UPDATE `solicitud_historial` AS h
INNER JOIN (
    SELECT `id_ticket`, MIN(`id_ticket_etapa`) AS `id_ticket_etapa`
    FROM `ticket_etapas`
    GROUP BY `id_ticket`
    HAVING COUNT(*) = 1
) AS unico ON unico.`id_ticket` = h.`id_ticket`
SET h.`id_ticket_etapa` = unico.`id_ticket_etapa`
WHERE h.`id_ticket_etapa` IS NULL;

-- Optimiza la consulta del histórico de cada caso.
ALTER TABLE `solicitud_historial`
    ADD INDEX `idx_historial_caso_fecha`
        (`id_ticket`, `id_ticket_etapa`, `creado_en`);

-- Conserva la integridad de la relación caso-historial.
ALTER TABLE `solicitud_historial`
    ADD CONSTRAINT `fk_historial_ticket_etapa`
        FOREIGN KEY (`id_ticket_etapa`)
        REFERENCES `ticket_etapas` (`id_ticket_etapa`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

SELECT
    DATABASE() AS `base_aplicada`,
    'Migración aplicada correctamente' AS `resultado`,
    COUNT(*) AS `eventos_vinculados_a_casos`
FROM `solicitud_historial`
WHERE `id_ticket_etapa` IS NOT NULL;
