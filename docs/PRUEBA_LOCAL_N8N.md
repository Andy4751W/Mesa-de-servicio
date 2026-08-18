# Prueba local de correos con n8n

Este ambiente permite terminar y demostrar la recuperación de contraseña sin
acceso al n8n, al SMTP ni a Microsoft 365 de la empresa.

La prueba usa dos servicios locales y sirve tanto para recuperar contraseñas
como para notificar las acciones de los casos:

- **n8n Community Edition** en `http://localhost:5678`.
- **MailDev** en `http://localhost:1080`, que captura los correos sin enviarlos
  a Internet. El SMTP local escucha en `127.0.0.1:1025`.

La aplicación continúa usando el mismo Webhook que utilizará en producción.
Cuando entreguen los accesos corporativos no será necesario cambiar la lógica
PHP ni la base de datos.

## 1. Requisito

Instale Node.js en Windows. No necesita comprar una licencia de n8n Cloud ni
disponer de un servidor SMTP.

## 2. Iniciar el ambiente

Abra dos terminales de Windows y manténgalas abiertas. En la primera ejecute:

```text
npx.cmd n8n@2.35.0
```

En la segunda ejecute:

```text
npx.cmd --yes maildev
```

Como alternativa, la carpeta `n8n/local` conserva el ambiente Docker con
Mailpit. No ejecute MailDev y Mailpit al mismo tiempo porque ambos usan el
puerto SMTP `1025`.

## 3. Configurar n8n por primera vez

1. Abra `http://localhost:5678`.
2. Cree el usuario propietario local solicitado por n8n.
3. Seleccione **Import from File**.
4. Importe `n8n/recuperacion_password_local_mailpit.json`.

### Credencial del Webhook

1. Abra el nodo **Webhook Mesa de Servicio**.
2. En Authentication deje **Header Auth**.
3. Cree una credencial Header Auth:
   - Name: `X-Mesa-Secret`
   - Value: use el mismo secreto local de al menos 32 caracteres configurado
     en `app/config/configuracion.local.php`.
4. Guarde la credencial y asígnela al nodo.

### Credencial SMTP local

1. Abra el nodo **Enviar a MailDev local**.
2. Cree una credencial SMTP con estos valores:

| Campo | Valor |
| --- | --- |
| User | vacío |
| Password | vacío |
| Host | `127.0.0.1` |
| Port | `1025` |
| SSL/TLS | desactivado |
| Disable STARTTLS | activado |

3. Guarde la credencial con el nombre `MailDev local`.
4. Asigne la credencial al nodo.
5. Active el flujo.

La URL de producción del Webhook local debe quedar así:

```text
http://localhost:5678/webhook/mesa-servicio-correo
```

No utilice la URL que contiene `webhook-test`.

## 4. Conectar la aplicación local

Abra `app/config/configuracion.local.php`, conserve la configuración actual de
la base de datos y agregue:

```php
'n8n_webhook_url' => 'http://127.0.0.1:5678/webhook/mesa-servicio-correo',
'n8n_webhook_secret' => 'EL_MISMO_SECRETO_DEL_HEADER_AUTH',
'recovery_pepper' => 'OTRO_SECRETO_LOCAL_DIFERENTE',
```

El secreto del Webhook debe coincidir exactamente con el valor de la
credencial Header Auth. No copie `N8N_ENCRYPTION_KEY` dentro de PHP.

Si la Mesa de Servicio también está ejecutándose dentro de Docker, use
`http://host.docker.internal:5678/...` y configure `MESA_ENV=development` solo
en ese ambiente local.

## 5. Preparar la base de datos

Realice una copia de seguridad e importe una sola vez:

```text
database/migrations/001_recuperacion_password.sql
database/migrations/002_notificaciones_email_tickets.sql
```

Confirme que exista la tabla:

```sql
USE mesa_servicio;
SHOW TABLES LIKE 'recuperaciones_password';
```

## 6. Probar el proceso completo

1. Verifique que exista un usuario activo con correo en la Mesa de Servicio.
2. Abra la pantalla de inicio de sesión.
3. Seleccione **¿Olvidó su contraseña?**.
4. Ingrese el correo del usuario.
5. Abra `http://localhost:1080`.
6. Abra el mensaje capturado y copie el código.
7. Ingrese el código y establezca una contraseña nueva.
8. Verifique que la contraseña anterior ya no funcione.
9. Verifique que el mismo código no pueda usarse nuevamente.

El correo escrito en la plataforma puede ser real o ficticio. MailDev lo
captura localmente y nunca lo envía por Internet.

Para probar las notificaciones de casos siga también
`docs/NOTIFICACIONES_CASOS_N8N.md`.

## 7. Detener y volver a iniciar

Para detenerlos presione `Ctrl+C` en cada terminal. n8n conserva localmente sus
flujos y credenciales; MailDev es un buzón temporal de prueba.

## 8. Pasar a la empresa cuando entreguen los accesos

1. Importe `n8n/recuperacion_password_microsoft365.json` en el n8n corporativo.
2. Cree una nueva credencial Header Auth corporativa.
3. Asigne la credencial Microsoft Outlook o SMTP que entregue la empresa.
4. Configure el buzón remitente autorizado.
5. Active el flujo y copie su Production URL.
6. En `configuracion.local.php` cambie únicamente:
   - `n8n_webhook_url`
   - `n8n_webhook_secret`
7. Mantenga `recovery_pepper` protegido en el servidor de la aplicación.

No se debe copiar la credencial `MailDev local` ni ningún secreto local al
servidor de la empresa. El código PHP, la pantalla y la migración SQL sí son los
mismos.
