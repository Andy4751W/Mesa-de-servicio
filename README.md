<<<<<<< HEAD
# Mesa de Servicio multipaís: Colombia y Perú

Versión consolidada: 6 de agosto de 2026.

Este paquete conserva la arquitectura modular recibida y agrega dos operaciones completamente separadas: **Colombia** y **Perú**. Después de iniciar sesión, el administrador elige el país que desea gestionar; los gestores y solicitantes ingresan directamente al país donde fueron registrados.

La separación no es únicamente visual. Usuarios, gestores, tickets, catálogos, servicios, soluciones, procesos, SLA, feriados e indicadores se consultan y modifican dentro del país activo. Colombia utiliza un portal corporativo azul con panel modular tipo bento; Perú conserva su portal operativo rojo con módulos agrupados. Ambos mantienen una barra lateral persistente y una identidad claramente diferenciada.

En Colombia y Perú, al seleccionar una opción de la barra lateral no se recarga el portal completo. La navegación y el encabezado permanecen visibles mientras únicamente cambia el área central. Los formularios, filtros y redirecciones de cada módulo continúan funcionando dentro de esa área, y los botones Atrás y Adelante del navegador conservan el módulo seleccionado.

La insignia inferior con el código y nombre del país es únicamente informativa. Para cambiar de operación se utiliza la opción **Cambiar país** de la barra lateral.

## Estructura

```text
mesa_servicio_multipais/
├── app/
│   ├── config/          conexión y configuración local
│   ├── security/        autenticación, sesión, CSRF y permisos
│   ├── core/            calendario SLA, motor de flujos y automatización
│   └── modules/
│       ├── admin/       usuarios, configuraciones y panel administrativo
│       ├── auth/        inicio y cierre de sesión
│       ├── catalogos/   catálogos, servicios y soluciones
│       ├── reportes/    indicadores
│       ├── sistema/     verificadores administrativos
│       ├── sla/         SLA, festivos manuales y pruebas
│       └── tickets/     solicitudes, casos, chat, adjuntos y procesos
├── public/              único directorio accesible por navegador
│   ├── assets/          JavaScript e imágenes públicas
│   └── uploads/         compatibilidad temporal con archivos históricos
├── scripts/             tareas exclusivas de línea de comandos
├── database/            instalación, migraciones y SQL de referencia
└── docs/                guías técnicas
```

## Reglas funcionales conservadas

- Selector Colombia/Perú exclusivo para administradores globales.
- Gestores y solicitantes vinculados permanentemente con un país.
- Ubicación normalizada en el orden **País > Departamento > Ciudad** para usuarios y servicios.
- Tickets y casos visibles únicamente dentro del país donde fueron creados.
- Catálogos, servicios, flujos, SLA, feriados e indicadores independientes.
- Interfaz colombiana corporativa azul e interfaz peruana operativa roja.
- Navegación lateral persistente y contenido dinámico en ambos países.
- Árbol padre–hijo y numeración jerárquica de casos.
- Chats y adjuntos privados por caso.
- Estado **Listo**, aprobación del creador, reapertura y calificación obligatoria.
- Corte oficial del SLA al marcar **Listo** y reactivación al reabrir.
- Sábados y domingos no consumen SLA.
- Los festivos solo se descuentan cuando el administrador los registra y activa.
- Cierre por 5 minutos sin interacción y máximo absoluto de 30 minutos.
- Bloqueo de acceso por 15 minutos después de 5 intentos fallidos.

## Inicio rápido en XAMPP

1. Copie la carpeta completa dentro de `C:\xampp\htdocs`.
2. Copie `app/config/configuracion.local.php.example` como `app/config/configuracion.local.php`.
3. Complete el usuario de MySQL y la ruta privada. No use `root` en producción.
4. Cree fuera de `htdocs` las carpetas `solicitudes` y `catalogos` indicadas en `storage_path`.
5. Realice una copia de seguridad de la base de datos.
6. Importe una sola vez `database/migrations/005_separacion_colombia_peru.sql`. Esta migración conserva todos los datos históricos y los asigna inicialmente a Colombia.
7. Importe una sola vez `database/migrations/006_jerarquia_pais_departamento_ciudad.sql` para retirar Lugar y sincronizar usuarios, perfiles y servicios.
8. Abra `http://localhost/mesa_servicio_multipais/` o `http://localhost/mesa_servicio_multipais/public/login.html`.

Para una instalación nueva, siga [docs/GUIA_INSTALACION_Y_DESPLIEGUE.md](docs/GUIA_INSTALACION_Y_DESPLIEGUE.md).

## Comprobación rápida

Desde la raíz del proyecto:
=======
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
>>>>>>> cce54a47d35cae2eb87c6c9cf6d2920f218360ae

```bash
php scripts/verificarEstructura.php
```

<<<<<<< HEAD
El resultado esperado indica 35 entradas públicas verificadas.

## Puesta en marcha multipaís

Después de ejecutar la migración:

1. Inicie sesión con un administrador; aparecerán los módulos **Colombia** y **Perú**.
2. Entre en Perú y cree allí sus gestores y solicitantes. Quedarán vinculados únicamente a Perú.
3. Configure los catálogos, servicios, SLA, feriados y flujos peruanos desde el portal de Perú.
4. Regrese al selector, entre en Colombia y conserve o ajuste la configuración colombiana existente.
5. Pruebe con un gestor de cada país y confirme que ninguno pueda consultar tickets del otro.

La guía detallada se encuentra en [docs/SEPARACION_MULTIPAIS.md](docs/SEPARACION_MULTIPAIS.md).

## Dónde modificar cada función

Consulte [docs/MAPA_DE_ARCHIVOS.md](docs/MAPA_DE_ARCHIVOS.md). No edite los controladores mínimos de `public/`; modifique el PHP correspondiente dentro de `app/`.
=======
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
>>>>>>> cce54a47d35cae2eb87c6c9cf6d2920f218360ae
