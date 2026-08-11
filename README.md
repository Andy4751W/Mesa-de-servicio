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

```bash
php scripts/verificarEstructura.php
```

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
