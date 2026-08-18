# Base de datos

## Recuperación de contraseña con n8n

El proyecto incluye recuperación de contraseña mediante código temporal y
envío por un Webhook privado de n8n hacia Microsoft 365. Para instalarla:

1. Importe `database/migrations/001_recuperacion_password.sql` sobre la base existente.
2. Importe `n8n/recuperacion_password_microsoft365.json` en n8n.
3. Configure `app/config/configuracion.local.php` a partir de
   `app/config/configuracion.ejemplo.php`.
4. Siga la guía `docs/RECUPERACION_PASSWORD_N8N.md`.

La alternativa sin costo de licencia es n8n Community Edition autoalojada en
infraestructura existente. n8n Cloud no forma parte de esta implementación.

## Notificaciones de casos y tickets

El mismo Webhook de n8n envía las nuevas acciones del histórico al solicitante
y al gestor asignado. Los correos están activados por defecto y cada usuario
puede desactivarlos desde el detalle del caso.

Para una base existente, importe una sola vez
`database/migrations/002_notificaciones_email_tickets.sql` y actualice el flujo
de n8n incluido en el paquete. Consulte
`docs/NOTIFICACIONES_CASOS_N8N.md`.

### Desarrollo sin accesos corporativos

La carpeta `n8n/local` incluye un ambiente de prueba con n8n y Mailpit. Permite
ver los códigos en un buzón local sin utilizar el SMTP ni el n8n de la empresa.
Ejecute `n8n/local/iniciar-local.bat` y siga
`docs/PRUEBA_LOCAL_N8N.md`.

## Instalación nueva

1. Cree una base denominada `mesa_servicio` con conjunto de caracteres `utf8mb4`.
2. Importe `database/install/mesa_servicio.sql`.
3. Configure `app/config/configuracion.local.php`.

El archivo de instalación ya incorpora la recuperación de contraseña, las
preferencias de notificación por correo, el
control de intentos de acceso, la separación Colombia/Perú, la jerarquía
País > Departamento > Ciudad, los flujos, los SLA y las calificaciones.

## Base existente

Realice primero una copia de seguridad. Para agregar únicamente la recuperación
de contraseña, compruebe si la tabla ya existe:

```sql
USE mesa_servicio;
SHOW TABLES LIKE 'recuperaciones_password';
```

Si no aparece resultado, importe
`database/migrations/001_recuperacion_password.sql` una sola vez. No reimporte
la base completa sobre una instalación en uso porque contiene datos de
referencia y podría alterar la información existente.
