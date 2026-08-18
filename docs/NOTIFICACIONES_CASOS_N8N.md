# Notificaciones por correo de casos y tickets

La Mesa de Servicio puede enviar a n8n un correo por cada nueva acción del
histórico. La misma integración sirve en el entorno local con MailDev y en la
empresa con Microsoft 365.

## Reglas aplicadas

- Solicitante y gestor reciben correos de forma predeterminada.
- Cada persona controla su propia preferencia desde el detalle del caso.
- Desactivar los correos para un usuario no cambia la preferencia del otro.
- El gestor solo recibe y puede cambiar la preferencia mientras la etapa o el
  ticket seleccionado continúe asignado a su usuario.
- El solicitante recibe las acciones públicas de su caso. Las derivaciones y
  conversaciones internas entre gestores no se envían al solicitante.
- Si el caso cambia de gestor, el gestor anterior deja de ser destinatario y
  el nuevo gestor queda habilitado de forma predeterminada.
- Si n8n o el correo no está disponible, la acción del caso se conserva y el
  fallo de correo queda solamente en el registro de errores de PHP.

## Contenido del correo

Cada notificación utiliza una plantilla HTML corporativa y adaptable a
pantallas pequeñas. Muestra el número y asunto del caso, la acción realizada,
el área o servicio, la persona que registró el cambio, la fecha, el detalle y
un botón para consultar el caso. El texto introductorio y el enlace se ajustan
automáticamente según el destinatario sea el solicitante o el gestor asignado.

La plantilla no carga imágenes, fuentes ni servicios externos, por lo que
funciona igual en MailDev y en el servidor de correo de la empresa.

## Campana de novedades dentro de la Mesa de Servicio

Las notificaciones internas aparecen en una campana ubicada junto al perfil
del usuario. El contador rojo muestra cuántas novedades permanecen sin leer y,
al pulsarlo, se abre un panel tipo red social con las últimas actividades,
fecha relativa y acceso directo al caso o al chat relacionado.

Después de iniciar sesión se muestra una sola alerta cuando existen novedades
pendientes. La alerta invita a consultar la campana y desaparece al abrirla,
cerrarla o después de unos segundos. Al seleccionar una notificación, esta se
marca como leída y se abre el caso correspondiente. Esto no reemplaza ni
deshabilita los correos de n8n; ambos canales funcionan en paralelo.

## Conversación integrada del caso

El solicitante y el gestor abren la conversación desde un botón flotante en el
detalle del caso. El panel conserva los chats independientes de cada etapa y
las derivaciones internas autorizadas, pero los presenta como una conversación
tipo mensajería instantánea.

Los mensajes y archivos se organizan en una sola línea de tiempo. Las imágenes
se muestran con vista previa y los documentos aparecen como tarjetas dentro
del chat con su nombre, tamaño y acceso de descarga. Este cambio no requiere
una tabla ni una migración nueva.

Al enviar, el mensaje aparece de inmediato en la conversación y se confirma
sin recargar la ficha completa. Las notificaciones informativas se entregan a
n8n sin esperar a que termine el correo, por lo que MailDev o Microsoft 365 no
bloquean el chat. La recuperación de contraseña mantiene el envío confirmado,
ya que en ese proceso sí es necesario detectar un fallo del correo.

## Instalar sobre una base existente

Realice una copia de seguridad e importe una sola vez:

```text
database/migrations/002_notificaciones_email_tickets.sql
```

La ausencia de una fila de preferencia significa `activada`. La tabla solo
guarda las decisiones de quienes usan el botón para cambiar ese valor.

## Actualizar n8n

El nodo **Validar evento** debe permitir estos dos valores:

- `password_recovery`
- `ticket_update`

Los archivos incluidos en este paquete ya contienen la validación correcta:

- Local: `n8n/recuperacion_password_local_mailpit.json`
- Empresa: `n8n/recuperacion_password_microsoft365.json`

Si conserva el flujo que ya importó en n8n, abra **Validar evento** y reemplace
la primera expresión por:

```text
{{ ['password_recovery', 'ticket_update'].includes($json.body.event) ? 'valid' : 'invalid' }}
```

El valor de comparación debe ser `valid`. Guarde y publique nuevamente el
flujo. No cambie el Header Auth ni el secreto que ya funciona.

## Prueba local

1. Inicie n8n y MailDev.
2. Importe la migración `002_notificaciones_email_tickets.sql`.
3. Publique el flujo actualizado en n8n.
4. Abra un caso como solicitante y confirme que indica
   **Notificaciones activadas**.
5. Registre una acción nueva en el caso.
6. Abra `http://localhost:1080` y compruebe los mensajes del solicitante y del
   gestor asignado.
7. Pulse **No recibir notificaciones** con uno de los usuarios, registre otra
   acción y confirme que solo el otro destinatario recibe el mensaje.
