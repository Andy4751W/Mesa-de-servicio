<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparSolucion(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirigirSoluciones(string $mensaje, int $idServicio = 0): never
{
    $parametros = ['msg' => $mensaje];

    if ($idServicio > 0) {
        $parametros['id_servicio'] = $idServicio;
    }

    header('Location: soluciones.php?' . http_build_query($parametros));
    exit;
}

$moduloInstalado = flujoModuloSolucionesInstalado($conn);
$idServicioFiltro = filter_input(INPUT_GET, 'id_servicio', FILTER_VALIDATE_INT) ?: 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals((string) $_SESSION['csrf_token'], $token)
    ) {
        redirigirSoluciones('solicitud_invalida', $idServicioFiltro);
    }

    if (!$moduloInstalado) {
        redirigirSoluciones('instalacion_pendiente', $idServicioFiltro);
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $idSolucion = filter_input(INPUT_POST, 'id_solucion', FILTER_VALIDATE_INT) ?: 0;
    $idServicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT) ?: 0;
    $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

    try {
        if (in_array($accion, ['crear', 'editar'], true)) {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $orden = filter_input(INPUT_POST, 'orden', FILTER_VALIDATE_INT);

            if (
                $idServicio < 1
                || $nombre === ''
                || strlen($nombre) > 180
                || strlen($descripcion) > 500
            ) {
                redirigirSoluciones('datos_incompletos', $idServicio);
            }

            if ($orden === false || $orden === null || $orden < 0) {
                $orden = 0;
            }

            $stmt = $conn->prepare(
                "SELECT id_servicio
                 FROM servicios
                 WHERE id_servicio = ?
                 LIMIT 1"
            );
            $stmt->bind_param('i', $idServicio);
            $stmt->execute();
            $stmt->store_result();
            $servicioValido = $stmt->num_rows > 0;
            $stmt->close();

            if (!$servicioValido) {
                redirigirSoluciones('servicio_invalido');
            }

            $stmt = $conn->prepare(
                "SELECT id_solucion
                 FROM soluciones_servicio
                 WHERE id_servicio = ?
                   AND nombre = ?
                   AND id_solucion <> ?
                 LIMIT 1"
            );
            $stmt->bind_param('isi', $idServicio, $nombre, $idSolucion);
            $stmt->execute();
            $stmt->store_result();
            $duplicada = $stmt->num_rows > 0;
            $stmt->close();

            if ($duplicada) {
                redirigirSoluciones('solucion_duplicada', $idServicio);
            }

            if ($orden === 0) {
                $stmt = $conn->prepare(
                    "SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
                     FROM soluciones_servicio
                     WHERE id_servicio = ?"
                );
                $stmt->bind_param('i', $idServicio);
                $stmt->execute();
                $orden = (int) ($stmt->get_result()->fetch_assoc()['siguiente'] ?? 1);
                $stmt->close();
            }

            if ($accion === 'crear') {
                $stmt = $conn->prepare(
                    "INSERT INTO soluciones_servicio
                        (id_servicio, nombre, descripcion, estado, orden, creado_por, actualizado_por)
                     VALUES (?, ?, NULLIF(?, ''), 'activo', ?, ?, ?)"
                );
                $stmt->bind_param(
                    'issiii',
                    $idServicio,
                    $nombre,
                    $descripcion,
                    $orden,
                    $idUsuario,
                    $idUsuario
                );
                $stmt->execute();
                $stmt->close();
                redirigirSoluciones('creada', $idServicio);
            }

            if ($idSolucion < 1) {
                redirigirSoluciones('solicitud_invalida', $idServicio);
            }

            $stmt = $conn->prepare(
                "UPDATE soluciones_servicio
                 SET id_servicio = ?,
                     nombre = ?,
                     descripcion = NULLIF(?, ''),
                     orden = ?,
                     actualizado_por = ?
                 WHERE id_solucion = ?"
            );
            $stmt->bind_param(
                'issiii',
                $idServicio,
                $nombre,
                $descripcion,
                $orden,
                $idUsuario,
                $idSolucion
            );
            $stmt->execute();
            $stmt->close();
            redirigirSoluciones('actualizada', $idServicio);
        }

        if (in_array($accion, ['eliminar', 'reactivar'], true) && $idSolucion > 0) {
            $nuevoEstado = $accion === 'eliminar' ? 'inhabilitado' : 'activo';
            $stmt = $conn->prepare(
                "UPDATE soluciones_servicio
                 SET estado = ?, actualizado_por = ?
                 WHERE id_solucion = ?"
            );
            $stmt->bind_param('sii', $nuevoEstado, $idUsuario, $idSolucion);
            $stmt->execute();
            $stmt->close();
            redirigirSoluciones(
                $accion === 'eliminar' ? 'eliminada' : 'reactivada',
                $idServicio
            );
        }

        redirigirSoluciones('solicitud_invalida', $idServicio);
    } catch (Throwable $e) {
        error_log('Soluciones por servicio: ' . $e->getMessage());
        redirigirSoluciones('error_operacion', $idServicio);
    }
}

$servicios = [];
$resultadoServicios = $conn->query(
    "SELECT
        s.id_servicio,
        s.nombre AS servicio,
        s.estado,
        c.nombre AS catalogo
     FROM servicios AS s
     INNER JOIN catalogos AS c ON c.id_catalogo = s.id_catalogo
     ORDER BY c.nombre, s.nombre, s.id_servicio"
);

if ($resultadoServicios !== false) {
    $servicios = $resultadoServicios->fetch_all(MYSQLI_ASSOC);
}

$idsServicios = array_map(
    static fn (array $servicio): int => (int) $servicio['id_servicio'],
    $servicios
);

if ($idServicioFiltro < 1 || !in_array($idServicioFiltro, $idsServicios, true)) {
    $idServicioFiltro = $idsServicios[0] ?? 0;
}

$soluciones = [];
$servicioActual = null;

foreach ($servicios as $servicio) {
    if ((int) $servicio['id_servicio'] === $idServicioFiltro) {
        $servicioActual = $servicio;
        break;
    }
}

if ($moduloInstalado && $idServicioFiltro > 0) {
    $stmt = $conn->prepare(
        "SELECT
            ss.*,
            (SELECT COUNT(*)
             FROM ticket_etapas AS te
             WHERE te.id_solucion = ss.id_solucion) AS total_usos
         FROM soluciones_servicio AS ss
         WHERE ss.id_servicio = ?
         ORDER BY ss.estado = 'activo' DESC, ss.orden, ss.nombre"
    );
    $stmt->bind_param('i', $idServicioFiltro);
    $stmt->execute();
    $soluciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$mensajes = [
    'creada' => ['ok', 'La solución se creó correctamente.'],
    'actualizada' => ['ok', 'La solución se actualizó para cierres futuros. Los casos históricos conservaron el nombre aplicado.'],
    'eliminada' => ['ok', 'La solución se retiró de las opciones disponibles. El histórico se conserva.'],
    'reactivada' => ['ok', 'La solución volvió a estar disponible.'],
    'solucion_duplicada' => ['error', 'Ya existe una solución con ese nombre para el servicio.'],
    'datos_incompletos' => ['error', 'Complete correctamente el nombre, el servicio y la descripción.'],
    'servicio_invalido' => ['error', 'El servicio seleccionado no existe.'],
    'instalacion_pendiente' => ['error', 'Primero debe ejecutar la migración del módulo de soluciones.'],
    'solicitud_invalida' => ['error', 'La solicitud no es válida. Recargue la página e inténtelo nuevamente.'],
    'error_operacion' => ['error', 'No fue posible completar la operación. Revise el registro del servidor.'],
];
$mensajeActual = $mensajes[(string) ($_GET['msg'] ?? '')] ?? null;
$activas = count(array_filter(
    $soluciones,
    static fn (array $solucion): bool => $solucion['estado'] === 'activo'
));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soluciones por servicio | Mesa de Servicio</title>
    <style>
        :root{--primary:#0f6fec;--green:#0f8b72;--navy:#102a43;--text:#243b53;--muted:#6b7f93;--border:#dce6f0;--bg:#f3f6fb;--surface:#fff;--red:#a83b33}
        *{box-sizing:border-box}body{min-height:100vh;margin:0;color:var(--text);background:var(--bg);font:12px/1.45 Inter,"Segoe UI",Arial,sans-serif}.shell{width:min(1260px,calc(100% - 24px));margin:auto;padding:12px 0 28px}.topbar,.panel,.form-card{border:1px solid var(--border);border-radius:13px;background:var(--surface);box-shadow:0 7px 24px rgba(16,42,67,.055)}.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px}.brand{display:flex;align-items:center;gap:10px}.mark{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;color:#fff;background:linear-gradient(145deg,#17a180,#0b6e5a);font-weight:850}.brand h1{margin:0;color:var(--navy);font-size:17px}.brand p{margin:2px 0 0;color:var(--muted);font-size:10px}.actions{display:flex;gap:7px;flex-wrap:wrap}.btn,button{min-height:34px;display:inline-flex;align-items:center;justify-content:center;padding:7px 11px;border:1px solid #d6e3ef;border-radius:9px;color:#24577f;background:#f7fbff;text-decoration:none;font:inherit;font-weight:750;cursor:pointer}.btn.primary,button.primary{color:#fff;border-color:var(--primary);background:var(--primary)}button.danger{color:var(--red);border-color:#efd4d1;background:#fff7f6}button.success{color:#087458;border-color:#bfe7db;background:#effaf6}.grid{display:grid;grid-template-columns:minmax(270px,360px) minmax(0,1fr);gap:10px;margin-top:10px}.form-card,.panel{overflow:hidden}.card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid var(--border)}.card-head h2{margin:0;color:var(--navy);font-size:14px}.card-head p{margin:2px 0 0;color:var(--muted);font-size:10px}.card-body{padding:14px}.field{margin-bottom:11px}.field label{display:block;margin-bottom:4px;color:#526c83;font-size:10px;font-weight:800}.field input,.field select,.field textarea{width:100%;padding:8px 10px;border:1px solid #cfdbe7;border-radius:8px;color:var(--text);background:#fff;font:inherit}.field textarea{min-height:100px;resize:vertical}.field small{display:block;margin-top:4px;color:var(--muted)}.form-actions{display:flex;justify-content:flex-end;gap:7px}.service-filter{display:flex;align-items:flex-end;gap:8px;padding:10px 14px;border-bottom:1px solid var(--border);background:#f8fbfd}.service-filter .field{flex:1;margin:0}.service-filter button{min-width:84px}.alert{margin-top:10px;padding:10px 12px;border-radius:9px}.alert.ok{color:#087458;background:#eaf8f2}.alert.error{color:#9d3d32;background:#fff0ee}.install{margin-top:10px;padding:14px;border:1px solid #efd89b;border-radius:10px;color:#7e5900;background:#fff8df}.summary{display:flex;gap:6px;flex-wrap:wrap}.chip{padding:4px 8px;border-radius:999px;color:#276086;background:#edf5fd;font-size:9px;font-weight:800}.table-wrap{overflow:auto}table{width:100%;min-width:720px;border-collapse:collapse}th{padding:8px 10px;text-align:left;color:#687d90;background:#f7f9fc;border-bottom:1px solid var(--border);font-size:9px;text-transform:uppercase}td{padding:9px 10px;border-bottom:1px solid #edf2f6;vertical-align:top}.solution-name{color:var(--navy);font-weight:800}.status{display:inline-flex;padding:3px 7px;border-radius:999px;font-size:8.5px;font-weight:800}.status.active{color:#087458;background:#e9f8f1}.status.inactive{color:#8b5b00;background:#fff4d5}.row-actions{display:flex;gap:5px;justify-content:flex-end}.empty{padding:28px;text-align:center;color:var(--muted)}details summary{cursor:pointer;color:#29628e;font-weight:750}dialog{width:min(570px,calc(100% - 24px));padding:0;border:0;border-radius:14px;box-shadow:0 25px 70px rgba(16,42,67,.28)}dialog::backdrop{background:rgba(15,35,55,.52)}.dialog-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border)}.dialog-head h2{margin:0;font-size:14px}.dialog-body{padding:14px}.close{width:34px;padding:0;font-size:18px}.notice{margin:0 0 10px;color:var(--muted);font-size:10px}@media(max-width:820px){.shell{width:calc(100% - 14px);padding-top:7px}.topbar{align-items:flex-start;flex-direction:column}.actions{width:100%}.actions .btn{flex:1}.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="brand"><div class="mark">SO</div><div><h1>Soluciones por servicio</h1><p>Catálogo predeterminado para cerrar casos padres e hijos</p></div></div>
        <nav class="actions"><a class="btn" href="servicios.php">Ver servicios</a><a class="btn" href="panelAdmin.php">Volver al panel</a><a class="btn" href="logout.php">Cerrar sesión</a></nav>
    </header>

    <?php if ($mensajeActual): ?><div class="alert <?= escaparSolucion($mensajeActual[0]) ?>"><?= escaparSolucion($mensajeActual[1]) ?></div><?php endif; ?>
    <?php if (!$moduloInstalado): ?><div class="install"><strong>Instalación pendiente.</strong> Importe <code>migracion_soluciones_por_servicio.sql</code> en la base <code>mesa_servicio</code>.</div><?php endif; ?>

    <div class="grid">
        <section class="form-card">
            <div class="card-head"><div><h2>Nueva solución</h2><p>La opción quedará disponible únicamente para el servicio elegido.</p></div></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= escaparSolucion($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="crear">
                    <div class="field"><label for="create_service">Servicio *</label><select id="create_service" name="id_servicio" required <?= !$moduloInstalado ? 'disabled' : '' ?>><option value="">Seleccione</option><?php foreach ($servicios as $servicio): ?><option value="<?= (int) $servicio['id_servicio'] ?>" <?= (int) $servicio['id_servicio'] === $idServicioFiltro ? 'selected' : '' ?>><?= escaparSolucion($servicio['catalogo'] . ' / ' . $servicio['servicio']) ?><?= $servicio['estado'] !== 'activo' ? ' (inhabilitado)' : '' ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="create_name">Nombre de la solución *</label><input id="create_name" name="nombre" maxlength="180" required placeholder="Ej. Reinicio de computador" <?= !$moduloInstalado ? 'disabled' : '' ?>></div>
                    <div class="field"><label for="create_description">Descripción o guía</label><textarea id="create_description" name="descripcion" maxlength="500" placeholder="Indique cuándo debe seleccionarse esta solución" <?= !$moduloInstalado ? 'disabled' : '' ?>></textarea><small>Esta guía se mostrará al gestor cuando seleccione la solución.</small></div>
                    <div class="field"><label for="create_order">Orden</label><input id="create_order" type="number" min="0" name="orden" value="0" <?= !$moduloInstalado ? 'disabled' : '' ?>><small>Use 0 para asignar automáticamente la siguiente posición.</small></div>
                    <div class="form-actions"><button class="primary" type="submit" <?= !$moduloInstalado ? 'disabled' : '' ?>>Crear solución</button></div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="card-head"><div><h2><?= escaparSolucion($servicioActual ? $servicioActual['catalogo'] . ' / ' . $servicioActual['servicio'] : 'Soluciones configuradas') ?></h2><p>Editar o eliminar afecta las opciones futuras; los casos cerrados conservan su histórico.</p></div><div class="summary"><span class="chip"><?= count($soluciones) ?> registradas</span><span class="chip"><?= $activas ?> activas</span></div></div>
            <form class="service-filter" method="get"><div class="field"><label for="service_filter">Consultar otro servicio</label><select id="service_filter" name="id_servicio"><?php foreach ($servicios as $servicio): ?><option value="<?= (int) $servicio['id_servicio'] ?>" <?= (int) $servicio['id_servicio'] === $idServicioFiltro ? 'selected' : '' ?>><?= escaparSolucion($servicio['catalogo'] . ' / ' . $servicio['servicio']) ?></option><?php endforeach; ?></select></div><button type="submit">Consultar</button></form>
            <?php if (!$soluciones): ?><div class="empty">Este servicio todavía no tiene soluciones configuradas.</div><?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Orden</th><th>Solución</th><th>Descripción</th><th>Estado</th><th>Usos</th><th></th></tr></thead><tbody>
            <?php foreach ($soluciones as $solucion): ?>
                <tr>
                    <td><?= (int) $solucion['orden'] ?></td>
                    <td class="solution-name"><?= escaparSolucion($solucion['nombre']) ?></td>
                    <td><?php if (trim((string) ($solucion['descripcion'] ?? '')) !== ''): ?><details><summary>Ver guía</summary><p><?= nl2br(escaparSolucion($solucion['descripcion'])) ?></p></details><?php else: ?><span class="notice">Sin descripción</span><?php endif; ?></td>
                    <td><span class="status <?= $solucion['estado'] === 'activo' ? 'active' : 'inactive' ?>"><?= $solucion['estado'] === 'activo' ? 'Activa' : 'Retirada' ?></span></td>
                    <td><?= (int) $solucion['total_usos'] ?></td>
                    <td><div class="row-actions"><button type="button" data-edit-solution data-id="<?= (int) $solucion['id_solucion'] ?>" data-service="<?= (int) $solucion['id_servicio'] ?>" data-name="<?= escaparSolucion($solucion['nombre']) ?>" data-description="<?= escaparSolucion($solucion['descripcion'] ?? '') ?>" data-order="<?= (int) $solucion['orden'] ?>">Editar</button><form method="post" onsubmit="return confirm('<?= $solucion['estado'] === 'activo' ? '¿Eliminar esta solución de las opciones futuras? El histórico no se borrará.' : '¿Reactivar esta solución?' ?>')"><input type="hidden" name="csrf_token" value="<?= escaparSolucion($_SESSION['csrf_token']) ?>"><input type="hidden" name="accion" value="<?= $solucion['estado'] === 'activo' ? 'eliminar' : 'reactivar' ?>"><input type="hidden" name="id_solucion" value="<?= (int) $solucion['id_solucion'] ?>"><input type="hidden" name="id_servicio" value="<?= (int) $solucion['id_servicio'] ?>"><button class="<?= $solucion['estado'] === 'activo' ? 'danger' : 'success' ?>" type="submit"><?= $solucion['estado'] === 'activo' ? 'Eliminar' : 'Reactivar' ?></button></form></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </section>
    </div>
</main>

<dialog id="editDialog">
    <div class="dialog-head"><h2>Editar solución</h2><button class="close" type="button" data-close-dialog aria-label="Cerrar">×</button></div>
    <div class="dialog-body">
        <p class="notice">El nuevo nombre se usará en cierres futuros. Los registros históricos conservarán el nombre seleccionado originalmente.</p>
        <form method="post" id="editForm">
            <input type="hidden" name="csrf_token" value="<?= escaparSolucion($_SESSION['csrf_token']) ?>"><input type="hidden" name="accion" value="editar"><input type="hidden" name="id_solucion" id="edit_id">
            <div class="field"><label for="edit_service">Servicio *</label><select id="edit_service" name="id_servicio" required><?php foreach ($servicios as $servicio): ?><option value="<?= (int) $servicio['id_servicio'] ?>"><?= escaparSolucion($servicio['catalogo'] . ' / ' . $servicio['servicio']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="edit_name">Nombre *</label><input id="edit_name" name="nombre" maxlength="180" required></div>
            <div class="field"><label for="edit_description">Descripción o guía</label><textarea id="edit_description" name="descripcion" maxlength="500"></textarea></div>
            <div class="field"><label for="edit_order">Orden</label><input id="edit_order" type="number" min="0" name="orden" required></div>
            <div class="form-actions"><button type="button" data-close-dialog>Cancelar</button><button class="primary" type="submit">Guardar cambios</button></div>
        </form>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('editDialog');
    if (!dialog) return;
    document.querySelectorAll('[data-edit-solution]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('edit_id').value = button.dataset.id || '';
            document.getElementById('edit_service').value = button.dataset.service || '';
            document.getElementById('edit_name').value = button.dataset.name || '';
            document.getElementById('edit_description').value = button.dataset.description || '';
            document.getElementById('edit_order').value = button.dataset.order || '0';
            dialog.showModal();
        });
    });
    document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
