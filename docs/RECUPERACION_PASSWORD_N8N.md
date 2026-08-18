# Recuperación de contraseña con n8n y Microsoft 365

Para desarrollar y probar sin accesos corporativos, siga primero
`docs/PRUEBA_LOCAL_N8N.md`. Ese ambiente simula el correo con Mailpit y después
se migra al flujo descrito en este documento.

Esta integración no necesita n8n Cloud, Power Automate, SendGrid, Twilio ni
otro servicio de pago. Utiliza:

- n8n Community Edition instalado en infraestructura existente de la empresa.
- Un buzón de Microsoft 365 ya disponible o un buzón compartido.
- El nodo Microsoft Outlook incluido en n8n.

> n8n Cloud es pago. Para mantener costo cero se debe usar la edición Community
> autoalojada. Si no existe un servidor o equipo disponible permanentemente,
> sí habría un costo de infraestructura.

## 1. Actualizar la base de datos

Realice una copia de seguridad y luego importe una sola vez:

```text
database/migrations/001_recuperacion_password.sql
```

La migración crea la tabla de códigos temporales. No almacena el código en
texto legible.

## 2. Importar el flujo en n8n

1. Abra n8n.
2. Seleccione **Import from File**.
3. Importe `n8n/recuperacion_password_microsoft365.json`.
4. Abra el nodo **Webhook Mesa de Servicio**.
5. Cree una credencial **Header Auth** con estos datos:
   - Name: `X-Mesa-Secret`
   - Value: un secreto aleatorio de al menos 32 caracteres.
6. Asigne esa credencial al nodo Webhook.
7. Abra **Enviar código por Microsoft 365** y cree o seleccione la credencial
   Microsoft Outlook OAuth2 de la empresa.
8. Si se enviará desde un buzón compartido como
   `notificacion@empresa.com`, seleccione ese remitente en **From** dentro de
   las opciones del nodo.
9. Pruebe el flujo y luego actívelo.
10. Copie la **Production URL** del Webhook. No use la URL de prueba.

El flujo está configurado para no conservar los datos de ejecuciones exitosas
ni fallidas, porque el cuerpo contiene un código temporal de seguridad.

## 3. Configurar la Mesa de Servicio

Copie `app/config/configuracion.ejemplo.php` como
`app/config/configuracion.local.php`. Conserve sus datos actuales de base de
datos y agregue:

```php
'n8n_webhook_url' => 'https://n8n.empresa.com/webhook/mesa-servicio-correo',
'n8n_webhook_secret' => 'SECRETO_QUE_COINCIDE_CON_HEADER_AUTH',
'recovery_pepper' => 'OTRO_SECRETO_DIFERENTE',
```

Genere dos secretos diferentes. En Linux, Git Bash o una terminal con OpenSSL:

```bash
openssl rand -hex 32
openssl rand -hex 32
```

También se pueden configurar como variables de entorno:

```text
MESA_N8N_WEBHOOK_URL
MESA_N8N_WEBHOOK_SECRET
MESA_RECOVERY_PEPPER
```

Nunca publique estos secretos en GitHub ni los coloque directamente dentro del
flujo exportado de n8n.

## 4. Requisitos técnicos

- PHP 8.1 o superior.
- Extensión PHP cURL habilitada.
- La Mesa de Servicio debe poder conectarse al Webhook de n8n.
- En producción, el Webhook debe usar HTTPS. Se permite HTTP únicamente para
  `localhost`, `127.0.0.1` o `::1`, o cuando `MESA_ENV=development`.
- n8n debe permanecer encendido para que los códigos puedan enviarse.

## 5. Funcionamiento de seguridad

1. El usuario ingresa su correo en **¿Olvidó su contraseña?**.
2. La plataforma responde siempre con un mensaje genérico para no revelar si
   el correo existe.
3. Si la cuenta está activa, genera un código de seis dígitos.
4. La base de datos guarda únicamente un HMAC del código.
5. n8n recibe el correo protegido por Header Auth y lo envía por Microsoft 365.
6. El código vence en 10 minutos, permite cinco intentos y se invalida al usarlo.
7. Se limitan las solicitudes repetidas por usuario y dirección IP.

## 6. Prueba funcional

1. Registre un usuario de prueba con un correo al que tenga acceso.
2. En la pantalla de ingreso seleccione **¿Olvidó su contraseña?**.
3. Solicite el código.
4. Compruebe en n8n que el flujo terminó correctamente.
5. Ingrese el código y una contraseña nueva.
6. Verifique que el código no pueda utilizarse nuevamente.
7. Verifique que la contraseña anterior ya no permita iniciar sesión.

Si el correo no llega, revise primero la ejecución del nodo Microsoft Outlook,
la carpeta de correo no deseado y que la URL configurada sea la de producción.
