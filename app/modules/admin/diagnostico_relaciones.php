<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
seguridadExigirRol([1]);

if (!seguridadDiagnosticoHabilitado()) {
    http_response_code(404);
    exit('Recurso no disponible.');
}

function escapar(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function consultar(mysqli $conn, string $sql, string $base): array
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $base);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $filas = $resultado->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $filas;
}

$error = null;
$base = '';
$relaciones = [];
$columnasUsuario = [];
$triggers = [];

try {
    $resultadoBase = $conn->query('SELECT DATABASE() AS base_actual');
    $filaBase = $resultadoBase->fetch_assoc();
    $base = (string) ($filaBase['base_actual'] ?? '');

    if ($base === '') {
        throw new RuntimeException(
            'La conexión no tiene una base de datos seleccionada. Revise conexion.php.'
        );
    }

    $relaciones = consultar(
        $conn,
        "SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE REFERENCED_TABLE_SCHEMA = ?
           AND REFERENCED_TABLE_NAME = 'usuarios'
         ORDER BY TABLE_NAME, COLUMN_NAME",
        $base
    );

    $columnasUsuario = consultar(
        $conn,
        "SELECT
            TABLE_NAME,
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ?
           AND (
               COLUMN_NAME = 'id_usuario'
               OR COLUMN_NAME LIKE '%usuario%'
           )
         ORDER BY TABLE_NAME, ORDINAL_POSITION",
        $base
    );

    $triggers = consultar(
        $conn,
        "SELECT
            TRIGGER_NAME,
            EVENT_MANIPULATION,
            ACTION_TIMING,
            EVENT_OBJECT_TABLE
         FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = ?
           AND EVENT_OBJECT_TABLE = 'usuarios'
         ORDER BY TRIGGER_NAME",
        $base
    );
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico de relaciones de usuarios</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f4f6f8;
            color: #1f2937;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
        main {
            width: min(1100px, 100%);
            margin: 0 auto;
            padding: 24px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
        }
        h1 { margin-top: 0; font-size: 25px; }
        h2 { margin: 28px 0 10px; font-size: 18px; }
        .base {
            padding: 12px;
            border-left: 5px solid #0d6efd;
            background: #eaf3ff;
        }
        .error, .vacio {
            padding: 12px;
            border-radius: 5px;
        }
        .error { background: #f8d7da; color: #842029; }
        .vacio { background: #fff3cd; color: #664d03; }
        .tabla-contenedor { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 9px;
            border: 1px solid #d1d5db;
            text-align: left;
            white-space: nowrap;
        }
        th { background: #0d6efd; color: #fff; }
        code {
            padding: 2px 5px;
            border-radius: 4px;
            background: #eef0f2;
        }
        .nota {
            margin-top: 28px;
            padding: 12px;
            background: #e8f5e9;
            color: #1b5e20;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<main>
    <h1>Diagnóstico de eliminación de usuarios</h1>

    <?php if ($error !== null): ?>
        <div class="error">
            <strong>Error:</strong> <?= escapar($error) ?>
        </div>
    <?php else: ?>
        <div class="base">
            Base usada realmente por <code>conexion.php</code>:
            <strong><?= escapar($base) ?></strong>
        </div>

        <h2>Claves foráneas que apuntan a usuarios</h2>
        <?php if ($relaciones === []): ?>
            <div class="vacio">
                No se encontraron claves foráneas que apunten a la tabla
                <code>usuarios</code> en esta base.
            </div>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                    <tr>
                        <th>Tabla relacionada</th>
                        <th>Columna</th>
                        <th>Restricción</th>
                        <th>Tabla destino</th>
                        <th>Columna destino</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($relaciones as $fila): ?>
                        <tr>
                            <td><?= escapar($fila['TABLE_NAME']) ?></td>
                            <td><?= escapar($fila['COLUMN_NAME']) ?></td>
                            <td><?= escapar($fila['CONSTRAINT_NAME']) ?></td>
                            <td><?= escapar($fila['REFERENCED_TABLE_NAME']) ?></td>
                            <td><?= escapar($fila['REFERENCED_COLUMN_NAME']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2>Columnas posiblemente relacionadas con usuarios</h2>
        <?php if ($columnasUsuario === []): ?>
            <div class="vacio">
                No se encontraron columnas cuyo nombre contenga “usuario”.
            </div>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                    <tr>
                        <th>Tabla</th>
                        <th>Columna</th>
                        <th>Tipo</th>
                        <th>Permite NULL</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($columnasUsuario as $fila): ?>
                        <tr>
                            <td><?= escapar($fila['TABLE_NAME']) ?></td>
                            <td><?= escapar($fila['COLUMN_NAME']) ?></td>
                            <td><?= escapar($fila['COLUMN_TYPE']) ?></td>
                            <td><?= escapar($fila['IS_NULLABLE']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2>Disparadores de la tabla usuarios</h2>
        <?php if ($triggers === []): ?>
            <div class="vacio">
                La tabla <code>usuarios</code> no tiene disparadores.
            </div>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                    <tr>
                        <th>Disparador</th>
                        <th>Evento</th>
                        <th>Momento</th>
                        <th>Tabla</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($triggers as $fila): ?>
                        <tr>
                            <td><?= escapar($fila['TRIGGER_NAME']) ?></td>
                            <td><?= escapar($fila['EVENT_MANIPULATION']) ?></td>
                            <td><?= escapar($fila['ACTION_TIMING']) ?></td>
                            <td><?= escapar($fila['EVENT_OBJECT_TABLE']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="nota">
            Este archivo únicamente consulta la estructura de la base de datos.
            No crea, modifica ni elimina información.
        </div>
    <?php endif; ?>
</main>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
