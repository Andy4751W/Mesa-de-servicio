# Mesa de Servicio organizada

Versión consolidada: 4 de agosto de 2026.

Este paquete conserva el funcionamiento recibido y organiza el proyecto por responsabilidad. Las URL públicas mantienen sus nombres anteriores, pero el código real se encuentra fuera de `public/`.

## Estructura

```text
mesa_servicio_organizada/
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
5. Si la base actual ya funciona, no importe nuevamente la base ni las migraciones funcionales. Compruebe únicamente que exista `seguridad_intentos_login`.
6. Abra `http://localhost/mesa_servicio_organizada/` o `http://localhost/mesa_servicio_organizada/public/login.html`.

Para una instalación nueva, siga [docs/GUIA_INSTALACION_Y_DESPLIEGUE.md](docs/GUIA_INSTALACION_Y_DESPLIEGUE.md).

## Comprobación rápida

Desde la raíz del proyecto:

```bash
php scripts/verificarEstructura.php
```

El resultado esperado indica 34 entradas públicas verificadas.

## Dónde modificar cada función

Consulte [docs/MAPA_DE_ARCHIVOS.md](docs/MAPA_DE_ARCHIVOS.md). No edite los controladores mínimos de `public/`; modifique el PHP correspondiente dentro de `app/`.

