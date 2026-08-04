# Guía de instalación y despliegue

## 1. Requisitos

- PHP 8.1 o superior; validado con PHP 8.2.
- MariaDB 10.4 o MySQL compatible.
- Extensiones PHP `mysqli` y `fileinfo`.
- HTTPS obligatorio en producción.
- Apache con `AllowOverride All` cuando se utilicen archivos `.htaccess`.

## 2. Base de datos

Para una instalación nueva, importe la base limpia y después la migración de seguridad, según `database/README.md`.

Para la instalación que ya estaba funcionando, no vuelva a importar la base. La reorganización de carpetas no requiere cambios SQL.

Cree un usuario exclusivo:

```sql
CREATE USER IF NOT EXISTS 'mesa_app'@'localhost'
IDENTIFIED BY 'CAMBIE_ESTA_CLAVE_LARGA_Y_UNICA';

GRANT SELECT, INSERT, UPDATE, DELETE
ON mesa_servicio.*
TO 'mesa_app'@'localhost';

FLUSH PRIVILEGES;
```

## 3. Configuración

Copie:

```text
app/config/configuracion.local.php.example
```

como:

```text
app/config/configuracion.local.php
```

Complete la conexión y una ruta privada absoluta. En XAMPP puede usar:

```php
'storage_path' => 'C:/xampp/mesa_servicio_private',
```

Cree:

```text
C:\xampp\mesa_servicio_private\solicitudes
C:\xampp\mesa_servicio_private\catalogos
```

No coloque `configuracion.local.php` dentro de `public/` ni lo incluya en repositorios.

## 4. Publicación web

### XAMPP

Copie la carpeta en `C:\xampp\htdocs\mesa_servicio_organizada` y abra:

```text
http://localhost/mesa_servicio_organizada/
```

La entrada raíz redirige a `public/login.html`.

### Servidor productivo recomendado

Configure el `DocumentRoot` directamente hacia la carpeta `public`:

```apache
<VirtualHost *:443>
    ServerName mesa.ejemplo.com
    DocumentRoot "/ruta/mesa_servicio_organizada/public"

    <Directory "/ruta/mesa_servicio_organizada/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Así `app`, `database`, `docs` y `scripts` quedan físicamente fuera de la zona pública. Los `.htaccess` incluidos agregan una segunda capa de protección cuando todo el proyecto está dentro de `htdocs`.

## 5. Archivos históricos

Los archivos nuevos se guardan en `storage_path`. Si todavía existen imágenes o adjuntos antiguos dentro de `public/uploads`, simule primero la migración:

```bash
php scripts/migrarArchivosPrivados.php
```

Después de revisar el resultado:

```bash
php scripts/migrarArchivosPrivados.php --apply
```

La aplicación mantiene compatibilidad temporal con archivos históricos mientras se realiza este traslado.

## 6. Tarea automática

Programe el cierre automático con:

```bash
php scripts/cerrarTicketsInactivos.php
```

Este archivo rechaza solicitudes web y solo puede ejecutarse por línea de comandos.

## 7. Pruebas antes de publicar

1. Ejecute `php scripts/verificarEstructura.php`.
2. Valide el inicio de sesión de administrador, gestor y solicitante.
3. Compruebe el cierre por 5 minutos de inactividad y por 30 minutos máximos.
4. Cree un ticket con padre e hijos, marque un caso como **Listo**, reábralo y ciérrelo con calificación.
5. Compruebe que sábado y domingo no consuman SLA.
6. Registre un festivo manual, actívelo y verifique que no consuma SLA.
7. Descargue un adjunto como participante y confirme que otro usuario reciba acceso denegado.
8. Abra `indicadores.php` y valide filtros, detalle lateral y exportación.
9. Confirme que una URL bajo `/app`, `/database` o `/scripts` responda 403.

