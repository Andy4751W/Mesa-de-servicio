<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

/** @var array<string, string> $entradas */
$entradas = require APP_ROOT . '/routes.php';
$entrada = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
$archivo = $entradas[$entrada] ?? null;

if ($archivo === null || !is_file($archivo)) {
    http_response_code(404);
    exit('Recurso no encontrado.');
}

$paginasVisuales = [
    'catalogos.php', 'checklists.php', 'chat.php', 'configuraciones.php',
    'crearUsuarios.php', 'crearticket.php', 'diagnostico_relaciones.php',
    'editarUsuario.php', 'editarcatalogos.php', 'feriados.php', 'flujoTicket.php',
    'indicadores.php', 'panelAdmin.php', 'panelGestor.php', 'panelSolicitante.php',
    'procesos.php', 'Registro.php', 'servicios.php', 'sla.php', 'solicitudes.php',
    'soluciones.php', 'verificarActualizacionSolicitudes.php',
    'verificarCalendarioSla.php', 'verificarFlujos.php',
];

$esPaginaVisual = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && in_array($entrada, $paginasVisuales, true);

if (!$esPaginaVisual) {
    require $archivo;
    return;
}

ob_start();
require $archivo;
$salida = (string) ob_get_clean();

// Evita que una vista antigua cargue otra interfaz o un controlador visual.
$salida = (string) preg_replace(
    '~<script\b[^>]*\bsrc=["\'][^"\']*assets/js/controlSesion\.js[^"\']*["\'][^>]*>\s*</script>~i',
    '',
    $salida
);

if (stripos($salida, 'data-mesa-php-shell') === false && stripos($salida, '</body>') !== false) {
    ob_start();
    require APP_ROOT . '/components/controlesCuenta.php';
    $controles = (string) ob_get_clean();
    $salida = (string) preg_replace('/<\/body>/i', $controles . '</body>', $salida, 1);
} elseif (stripos($salida, '</body>') !== false) {
    // Una vista antigua pudo incluir el componente directamente. En ese caso
    // se conserva la interfaz PHP y se repone una sola carga del monitor.
    $monitorSesion = '<script src="assets/js/controlSesion.js?v=2026-08-10.5" defer></script>';
    $salida = (string) preg_replace('/<\/body>/i', $monitorSesion . '</body>', $salida, 1);
}

echo $salida;
