# Mapa de archivos

Los PHP ubicados en `public/` son controladores mínimos que conservan las URL actuales. La tabla siguiente indica dónde está el código funcional.

| Función | Carpeta o archivo principal |
| --- | --- |
| Conexión MySQL | `app/config/conexion.php` |
| Credenciales y almacenamiento | `app/config/configuracion.local.php` |
| Seguridad común, CSRF y sesiones | `app/security/seguridad.php` |
| Validación de usuario y rol | `app/security/validarSesion.php` |
| Actividad y vencimiento de sesión | `app/security/sesionActividad.php` y `public/assets/js/controlSesion.js` |
| Inicio y cierre de sesión | `app/modules/auth/` |
| Panel y usuarios administradores | `app/modules/admin/` |
| Catálogos, servicios y soluciones | `app/modules/catalogos/` |
| Configuración y calendario SLA | `app/modules/sla/` y `app/core/calendarioLaboral.php` |
| Motor padre–hijo, reaperturas y cierres | `app/core/motorFlujos.php` |
| Tickets, casos, chat y adjuntos | `app/modules/tickets/` |
| Indicadores y reportes | `app/modules/reportes/indicadores.php` |
| Verificaciones administrativas | `app/modules/sistema/` |
| Cierre automático y migración de archivos | `scripts/` |
| Base y migraciones | `database/` |
| Relación URL → implementación | `app/routes.php` |

## Regla para agregar una página nueva

1. Guarde la implementación en el módulo correspondiente dentro de `app/modules/`.
2. Registre la relación en `app/routes.php`.
3. Cree en `public/` un archivo con el nombre de la URL y este contenido:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/_entry.php';
```

4. Ejecute `php scripts/verificarEstructura.php`.

