# Base de datos

## Instalación nueva

1. Importe `install/001_base_mesa_servicio_limpia.sql`.
2. Importe `migrations/004_seguridad.sql` para crear el control de intentos de acceso.

La base limpia ya incorpora historial por caso, soluciones, estado **Listo**, reaperturas y calificaciones. No ejecute las migraciones `001`, `002` y `003` después de importar la base limpia.

## Base existente

No ejecute migraciones por fecha ni por nombre sin revisar primero el esquema. Las migraciones `001`, `002` y `003` se conservan únicamente para instalaciones antiguas que todavía no tengan esas estructuras.

Compruebe el control de seguridad con:

```sql
USE mesa_servicio;
SHOW TABLES LIKE 'seguridad_intentos_login';
```

Si no aparece resultado, importe solamente `migrations/004_seguridad.sql`.

## SQL de referencia

`reference/sla_recibido.sql` es el volcado parcial recibido de la tabla SLA. No debe importarse junto con la base limpia porque intentaría crear nuevamente la misma tabla.

