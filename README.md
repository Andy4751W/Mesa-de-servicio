# Mesa de Servicio – Gestión de Solicitudes y SLA

Aplicación web modular para la gestión centralizada de solicitudes, incidentes, requerimientos y procesos internos. Permite registrar tickets, asignar responsables, controlar tiempos de atención, administrar acuerdos de nivel de servicio —SLA— y mantener trazabilidad completa desde la creación de una solicitud hasta su cierre y evaluación.

El sistema está desarrollado en PHP y MariaDB/MySQL y cuenta con una arquitectura organizada por módulos, controles de acceso por rol, almacenamiento privado de archivos y herramientas de seguimiento administrativo.

## Objetivo del proyecto

Centralizar la recepción, clasificación, asignación, atención, seguimiento y cierre de las solicitudes internas de una organización.

La plataforma permite:

* Controlar el ciclo de vida completo de los tickets.
* Establecer responsables por área y servicio.
* Configurar tiempos de atención mediante SLA.
* Crear procesos con múltiples etapas.
* Derivar solicitudes entre diferentes áreas.
* Mantener conversaciones y adjuntos privados por caso.
* Registrar reaperturas, soluciones, calificaciones e historial.
* Consultar indicadores de operación y cumplimiento.
* Garantizar trazabilidad y seguridad de la información.

## Perfiles de usuario                                                                                              
Administrador: Gestiona usuarios, roles, catálogos, servicios, soluciones, SLA, procesos, festivos, configuraciones e indicadores. 
Gestor: Atiende los casos asignados, registra avances, adjunta evidencias, crea derivaciones, conversa con participantes y marca los casos como listos.   

FUNCIONALIDADES PRINCIPALES

### Gestión de solicitudes

* Creación de tickets por parte de los solicitantes.
* Clasificación por área, servicio, prioridad y urgencia.
* Asignación automática o administrativa de gestores.
* Consulta del estado y avance de cada solicitud.
* Historial completo de acciones y cambios.
* Notificaciones internas relacionadas con el ticket.
* Exportación del consolidado de solicitudes.
* Búsqueda y filtrado de registros.

### Flujos de atención

El sistema permite configurar procesos compuestos por una o varias etapas.

Cada etapa puede tener:

* Área responsable.
* Servicio relacionado.
* Gestor asignado.
* SLA independiente.
* Instrucciones de atención.
* Lista de verificación.
* Requisitos de evidencia.
* Comentario obligatorio de cierre.
* Soluciones predeterminadas.

Las etapas pueden ejecutarse secuencialmente o generar casos derivados.

### Casos padre–hijo

Una solicitud puede dividirse en diferentes casos y subcasos mediante una estructura jerárquica.

El motor de flujos permite:

* Crear múltiples niveles de derivación.
* Identificar cada caso mediante numeración jerárquica.
* Pausar el SLA del caso padre mientras existan hijos pendientes.
* Ejecutar casos hermanos de manera paralela.
* Reanudar automáticamente el caso padre cuando finalizan sus hijos.
* Conservar responsables, tiempos, conversaciones y archivos independientes.
* Consultar el árbol completo del ticket.
* Mantener trazabilidad individual por cada caso.

### Comunicación y archivos

Cada ticket o derivación dispone de un espacio de comunicación privado.

Incluye:

* Mensajes entre los participantes autorizados.
* Adjuntos asociados al ticket o caso específico.
* Evidencias para listas de verificación.
* Validación de pertenencia antes de consultar mensajes o archivos.
* Descarga autenticada.
* Historial de comunicaciones.

Los archivos nuevos se almacenan fuera del directorio público del servidor y reciben nombres internos aleatorios. El sistema valida el tipo MIME y admite archivos de hasta 5 MB.

### Gestión del SLA

El sistema calcula los tiempos de atención utilizando una jornada laboral de lunes a viernes, de 08:00 a 18:00, en la zona horaria `America/Bogota`.

Reglas implementadas:

* Sábados y domingos no consumen SLA.
* Los festivos nacionales no se calculan automáticamente.
* Solo se excluyen las fechas registradas y activas en el panel administrativo.
* Se pueden configurar días completos o rangos horarios parciales.
* Los registros inactivos no modifican el cálculo.
* El SLA puede configurarse en minutos, horas o días.
* Los tiempos se pausan y reanudan de acuerdo con el flujo.
* Al marcar un caso como **Listo**, se registra el corte oficial del SLA.
* Si el caso es reabierto, el cálculo vuelve a activarse.
* El sistema conserva los minutos consumidos, el vencimiento y el resultado dentro o fuera del SLA.

### Cierre, aprobación y reapertura

Los gestores no cierran directamente los casos. Primero deben marcarlos como **Listos** y registrar:

* Solución aplicada.
* Comentario de cierre.
* Evidencias requeridas.
* Lista de verificación completa.

Posteriormente, el creador o solicitante puede:

* Aprobar la solución.
* Calificar la gestión del área.
* Calificar el tiempo de respuesta.
* Registrar un comentario.
* Solicitar la reapertura.

El sistema conserva la fecha de cada reapertura, la cantidad de reaperturas y el historial completo del caso.

### Indicadores y reportes

La aplicación incluye un dashboard administrativo con visualización en mosaicos.

Presenta información sobre:

* Total de tickets.
* Casos recibidos y atendidos.
* Casos activos.
* Tickets por estado.
* Tickets por urgencia.
* Cumplimiento del SLA.
* Tiempo promedio de atención.
* Desempeño por gestor.
* Desempeño por área.
* Volumen por servicio.
* Soluciones más utilizadas.
* Calificación de la gestión.
* Calificación del tiempo de respuesta.
* Calificaciones recientes.
* Reaperturas y evolución mensual.

Cada sección puede visualizarse como gráfico o tabla, minimizarse o maximizarse. Al seleccionar un gestor, área, servicio, solución o ticket, se abre un panel lateral con su información relacionada.

## Seguridad

La aplicación incorpora controles de seguridad para su despliegue en servidor:

* Validación de autenticación y permisos desde el servidor.
* Roles de administrador, gestor y solicitante.
* Protección CSRF en formularios y solicitudes internas.
* Validación del origen de las solicitudes POST.
* Consultas preparadas para reducir el riesgo de inyección SQL.
* Escape de contenido para prevenir ataques XSS.
* Encabezados de seguridad y política CSP.
* Cookies de sesión `HttpOnly` y `SameSite=Lax`.
* Cookies seguras cuando se utiliza HTTPS.
* Regeneración periódica del identificador de sesión.
* Vinculación de la sesión con el navegador.
* Cierre después de 5 minutos sin interacción.
* Duración máxima absoluta de 30 minutos por sesión.
* Advertencia 60 segundos antes del cierre.
* Bloqueo durante 15 minutos después de 5 accesos fallidos.
* Contraseñas almacenadas mediante hashes seguros.
* Contraseña mínima de 12 caracteres para usuarios nuevos.
* Almacenamiento privado de adjuntos e imágenes de catálogos.
* Descargas únicamente para participantes autorizados.
* Ocultamiento de errores internos de la base de datos.
* Rechazo del usuario `root` y de contraseñas vacías en producción.
* Protección contra fórmulas maliciosas en archivos exportados.
* Bloqueo de acceso web a configuración, scripts, documentación y SQL.

## Arquitectura del proyecto

```text
mesa_servicio/
├── app/
│   ├── config/          Conexión y configuración local
│   ├── security/        Autenticación, sesiones, CSRF y permisos
│   ├── core/            Calendario laboral, SLA y motor de flujos
│   └── modules/
│       ├── admin/       Usuarios y administración
│       ├── auth/        Inicio y cierre de sesión
│       ├── catalogos/   Catálogos, servicios y soluciones
│       ├── reportes/    Indicadores y dashboard
│       ├── sistema/     Verificadores administrativos
│       ├── sla/         SLA y festivos manuales
│       └── tickets/     Solicitudes, chats, adjuntos y procesos
├── public/              Controladores y recursos públicos
├── scripts/             Automatizaciones de línea de comandos
├── database/            Base, migraciones y SQL de referencia
├── docs/                Documentación técnica
├── index.php            Punto de entrada
└── README.md
```

La carpeta `public` contiene únicamente los puntos de entrada accesibles desde el navegador. La lógica funcional permanece organizada dentro de `app`, evitando la exposición directa de la conexión, configuración, seguridad y motor interno.

## Tecnologías utilizadas

* PHP 8.1 o superior.
* PHP 8.2 como versión validada.
* MariaDB 10.4 o MySQL compatible.
* MySQLi con consultas preparadas.
* HTML5.
* CSS3.
* JavaScript nativo.
* Apache.
* XAMPP para entornos locales.
* UTF-8 mediante `utf8mb4`.

## Requisitos

* PHP 8.1 o superior.
* MariaDB 10.4 o MySQL compatible.
* Extensión PHP `mysqli`.
* Extensión PHP `fileinfo`.
* Apache con `AllowOverride All`.
* HTTPS obligatorio en producción.
* Ruta de almacenamiento privada fuera del `DocumentRoot`.

## Instalación general

1. Clonar o descargar el repositorio.
2. Crear la base de datos `mesa_servicio`.
3. Para una instalación nueva, importar la base y posteriormente la migración de seguridad.
4. Crear un usuario de base de datos exclusivo con permisos limitados.
5. Copiar `app/config/configuracion.local.php.example` como `configuracion.local.php`.
6. Configurar la conexión y una ruta privada de almacenamiento.
7. Crear las carpetas privadas `solicitudes` y `catalogos`.
8. Configurar Apache para que el `DocumentRoot` apunte a `public`.
9. Habilitar HTTPS en producción.
10. Ejecutar la validación de estructura:

```bash
php scripts/verificarEstructura.php
```

Para una base existente que ya se encuentre funcionando, la reorganización de carpetas no requiere volver a importar el SQL.

## Automatizaciones

El proyecto incluye scripts para:

* Cerrar tickets que cumplan las condiciones de inactividad.
* Migrar archivos históricos al almacenamiento privado.
* Verificar la estructura y correspondencia de las rutas públicas.

Estos scripts solo deben ejecutarse desde la línea de comandos o mediante una tarea programada.

## Validación técnica

La versión organizada fue comprobada con los siguientes resultados:

* 81 archivos PHP analizados sin errores sintácticos.
* JavaScript de control de sesión validado.
* 34 rutas públicas relacionadas con sus implementaciones.
* Protección de los directorios internos.
* Configuración local excluida del repositorio.
* Adjuntos, archivos ZIP y registros excluidos mediante `.gitignore`.
* Base compatible con MariaDB 10.4 y PHP 8.2.

Estas comprobaciones no sustituyen las pruebas funcionales y de seguridad que deben ejecutarse en el servidor definitivo antes de habilitar el acceso a los usuarios.

## Estado del proyecto


El proyecto conserva la operación de solicitudes, flujos padre–hijo, chats privados, adjuntos, soluciones, estado **Listo**, reaperturas, calificaciones, indicadores, sesiones controladas y festivos administrados manualmente.

## Uso y confidencialidad

Este sistema está orientado a la gestión interna de solicitudes. La configuración local, los archivos adjuntos, los datos personales, los hashes de contraseñas y la información de producción no deben publicarse en repositorios abiertos.
