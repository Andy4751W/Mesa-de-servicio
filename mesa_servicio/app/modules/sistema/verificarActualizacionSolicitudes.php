<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
seguridadExigirRol([1]);

if (!seguridadDiagnosticoHabilitado()) {
    http_response_code(404);
    exit('Recurso no disponible.');
}

clearstatcache(true);

function escaparVerificacion(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function revisarArchivo(string $ruta, array $reglas): array
{
    $resultado = [
        'ruta' => $ruta,
        'existe' => is_file($ruta),
        'tamano' => 0,
        'modificado' => '',
        'sha256' => '',
        'reglas' => [],
    ];

    if (!$resultado['existe']) {
        return $resultado;
    }

    $contenido = (string) file_get_contents($ruta);
    $resultado['tamano'] = (int) filesize($ruta);
    $resultado['modificado'] = date('d/m/Y H:i:s', (int) filemtime($ruta));
    $resultado['sha256'] = hash_file('sha256', $ruta) ?: '';

    foreach ($reglas as $nombre => $regla) {
        $buscar = (string) $regla['buscar'];
        $debeExistir = (bool) ($regla['debe_existir'] ?? true);
        $encontrado = str_contains($contenido, $buscar);

        $resultado['reglas'][] = [
            'nombre' => $nombre,
            'correcto' => $debeExistir ? $encontrado : !$encontrado,
            'detalle' => $debeExistir
                ? 'Debe estar incluido'
                : 'No debe aparecer',
        ];
    }

    return $resultado;
}

$solicitudes = revisarArchivo(
    APP_ROOT . '/modules/tickets/solicitudes.php',
    [
        'Botón principal Eliminar retirado' => [
            'buscar' => 'id="botonEliminar"',
            'debe_existir' => false,
        ],
        'Formulario para borrar tickets retirado' => [
            'buscar' => 'formEliminarSolicitud',
            'debe_existir' => false,
        ],
        'DELETE directo de tickets retirado' => [
            'buscar' => 'DELETE FROM tickets',
            'debe_existir' => false,
        ],
        'Bloqueo de eliminación instalado en PHP' => [
            'buscar' => "redirigirSolicitud('eliminacion_no_permitida'",
        ],
        'Columna SLA aplicado instalada' => [
            'buscar' => '<th>SLA aplicado</th>',
        ],
        'Motor de calendario laboral instalado' => [
            'buscar' => 'calendarioVersion()',
        ],
    ]
);

$exportador = revisarArchivo(
    APP_ROOT . '/modules/tickets/descargarSolicitudesExcel.php',
    [
        'ID del solicitante incluido' => ['buscar' => "'ID solicitante'"],
        'Cédula del solicitante incluida' => ['buscar' => "'Cédula solicitante'"],
        'Proceso del solicitante incluido' => ['buscar' => "'Proceso solicitante'"],
        'CU1 y CU3 incluidos' => ['buscar' => "'CU1 solicitante'"],
        'Descripción CU1 incluida' => [
            'buscar' => "'Descripción CU1 solicitante'",
        ],
        'Ciudad y empresa incluidas' => ['buscar' => "'Ciudad solicitante'"],
        'Rol y estado incluidos' => ['buscar' => "'Rol solicitante'"],
        'Contraseña excluida del SELECT' => [
            'buscar' => 'solicitante.password',
            'debe_existir' => false,
        ],
    ]
);

$archivos = [
    'solicitudes.php' => $solicitudes,
    'descargarSolicitudesExcel.php' => $exportador,
];
$todoCorrecto = true;

foreach ($archivos as $archivo) {
    if (!$archivo['existe']) {
        $todoCorrecto = false;
        continue;
    }

    foreach ($archivo['reglas'] as $regla) {
        $todoCorrecto = $todoCorrecto && $regla['correcto'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificar actualización de solicitudes</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px;
            font-family: Arial, sans-serif;
            color: #102a43;
            background: #eef4fb;
        }
        main { width: min(1050px, 100%); margin: auto; }
        .hero, .card {
            background: #fff;
            border: 1px solid #d9e5f2;
            border-radius: 16px;
            box-shadow: 0 12px 35px rgba(20, 61, 102, .08);
        }
        .hero { padding: 24px; margin-bottom: 18px; }
        h1, h2 { margin-top: 0; }
        .resultado {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 11px;
            font-weight: 700;
        }
        .ok { color: #087443; background: #e8f8ef; border: 1px solid #b8e8ca; }
        .error { color: #a61b29; background: #fff0f1; border: 1px solid #f2bec4; }
        .meta { color: #526d82; font-size: 13px; line-height: 1.7; }
        .card { padding: 20px; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px; border-bottom: 1px solid #e4edf6; text-align: left; }
        th { color: #315b7d; font-size: 12px; text-transform: uppercase; }
        .estado { font-weight: 700; white-space: nowrap; }
        code { overflow-wrap: anywhere; }
        @media (max-width: 700px) {
            body { padding: 12px; }
            .table-wrap { overflow-x: auto; }
            table { min-width: 680px; }
        }
    </style>
</head>
<body>
<main>
    <section class="hero">
        <h1>Verificación de actualización de solicitudes</h1>
        <div class="meta">
            Verificación administrativa habilitada temporalmente.
            No modifica archivos ni reinicia servicios del servidor.
        </div>
        <div class="resultado <?= $todoCorrecto ? 'ok' : 'error' ?>">
            <?= $todoCorrecto
                ? '✓ Esta carpeta contiene la actualización correcta.'
                : '✕ Apache todavía está leyendo archivos incompletos o anteriores.' ?>
        </div>
    </section>

    <?php foreach ($archivos as $nombre => $archivo): ?>
        <section class="card">
            <h2><?= escaparVerificacion($nombre) ?></h2>
            <div class="meta">
                Ruta real: <code><?= escaparVerificacion(realpath($archivo['ruta']) ?: $archivo['ruta']) ?></code><br>
                Tamaño: <?= number_format((int) $archivo['tamano'], 0, ',', '.') ?> bytes ·
                Modificado: <?= escaparVerificacion($archivo['modificado'] ?: 'No disponible') ?><br>
                SHA-256: <code><?= escaparVerificacion($archivo['sha256'] ?: 'No disponible') ?></code>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Validación</th><th>Resultado</th><th>Condición</th></tr></thead>
                    <tbody>
                    <?php if (!$archivo['existe']): ?>
                        <tr><td>Archivo disponible</td><td class="estado error">FALTA</td><td>Debe existir en esta carpeta</td></tr>
                    <?php else: ?>
                        <?php foreach ($archivo['reglas'] as $regla): ?>
                            <tr>
                                <td><?= escaparVerificacion($regla['nombre']) ?></td>
                                <td class="estado <?= $regla['correcto'] ? 'ok' : 'error' ?>">
                                    <?= $regla['correcto'] ? 'CORRECTO' : 'INCORRECTO' ?>
                                </td>
                                <td><?= escaparVerificacion($regla['detalle']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</main>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
