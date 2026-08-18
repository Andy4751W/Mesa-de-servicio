# Base de datos

## Instalación nueva

1. Importe `install/001_base_mesa_servicio_limpia.sql`.
2. Importe `migrations/004_seguridad.sql` para crear el control de intentos de acceso.
3. Importe `migrations/005_separacion_colombia_peru.sql` para crear los países y separar la información operativa.
4. Importe `migrations/006_jerarquia_pais_departamento_ciudad.sql` para actualizar ubicaciones, perfiles y servicios a **País > Departamento > Ciudad**.

La base limpia ya incorpora historial por caso, soluciones, estado **Listo**, reaperturas y calificaciones. No ejecute las migraciones `001`, `002` y `003` después de importar la base limpia.

## Base existente

No ejecute migraciones por fecha ni por nombre sin revisar primero el esquema. Las migraciones `001`, `002` y `003` se conservan únicamente para instalaciones antiguas que todavía no tengan esas estructuras.

Realice primero una copia de seguridad. Compruebe el control de seguridad y la separación multipaís con:

```sql
USE mesa_servicio;
SHOW TABLES LIKE 'seguridad_intentos_login';
SHOW TABLES LIKE 'paises_operacion';
```

Si no aparece resultado, importe solamente `migrations/004_seguridad.sql`.

Si no aparece `paises_operacion`, importe una sola vez `migrations/005_separacion_colombia_peru.sql`. La migración asigna los registros existentes a Colombia, conserva los administradores como usuarios globales y habilita una operación vacía e independiente para Perú.

No vuelva a ejecutar la migración `005` si la tabla `paises_operacion` ya existe.

Después de la migración multipaís, importe una sola vez `migrations/006_jerarquia_pais_departamento_ciudad.sql`. Esta migración conserva el módulo País, relaciona los departamentos con cada país, relaciona las ciudades con cada departamento, actualiza las ubicaciones de usuarios y servicios y retira Lugar.

No vuelva a ejecutar la migración `006` si la tabla `usuarios` ya contiene las columnas `id_pais`, `id_departamento` e `id_ciudad` y la tabla `servicios` ya no contiene `id_lugar`.

## SQL de referencia

`reference/sla_recibido.sql` es el volcado parcial recibido de la tabla SLA. No debe importarse junto con la base limpia porque intentaría crear nuevamente la misma tabla.
