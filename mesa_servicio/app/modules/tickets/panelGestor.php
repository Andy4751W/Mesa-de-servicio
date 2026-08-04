<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/motorFlujos.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 2) {
    http_response_code(403);
    exit('Acceso denegado.');
}

function escaparPanelGestor(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$idGestor = (int) ($_SESSION['usuario_id'] ?? 0);
$nombreGestor = trim((string) ($_SESSION['usuario'] ?? 'Gestor'));
$moduloInstalado = flujoModuloInstalado($conn);
$resumen = [
    'tickets' => 0,
    'activos' => 0,
    'pausados' => 0,
    'completados' => 0,
];

if ($moduloInstalado) {
    $resumen['tickets'] = count(flujoTicketsUsuario($conn, $idGestor, 2));
    $stmt = $conn->prepare(
        "SELECT
            SUM(te.estado IN ('pendiente', 'en_proceso', 'en_espera_solicitante')) AS activos,
            SUM(te.estado = 'pausada') AS pausados,
            SUM(te.estado = 'completada') AS completados
         FROM ticket_etapas AS te
         INNER JOIN tickets AS t ON t.id_ticket = te.id_ticket
         WHERE te.id_gestor = ?
           AND t.id_proceso IS NOT NULL"
    );
    $stmt->bind_param('i', $idGestor);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $resumen['activos'] = (int) ($fila['activos'] ?? 0);
    $resumen['pausados'] = (int) ($fila['pausados'] ?? 0);
    $resumen['completados'] = (int) ($fila['completados'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del gestor | Mesa de Servicio</title>
    <style>
        :root{--primary:#0f6fec;--navy:#102a43;--text:#304b63;--muted:#6b8195;--border:#dce6f0;--surface:#fff;--soft:#eef5ff;--bg:#f3f6fb;--ok:#087443;--warn:#a15c00}
        *{box-sizing:border-box}body{margin:0;color:var(--text);background:linear-gradient(135deg,#f6f9fc,#edf3f9);font:12px/1.4 Inter,"Segoe UI",Arial,sans-serif}.shell{width:min(1180px,calc(100% - 18px));margin:auto;padding:8px 0 26px}.topbar,.hero,.module,.stat,.alert{border:1px solid var(--border);background:var(--surface);box-shadow:0 6px 18px rgba(16,42,67,.05)}.topbar{min-height:52px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 10px;border-radius:12px}.brand,.actions{display:flex;align-items:center;gap:8px}.mark{width:34px;height:34px;display:grid;place-items:center;border-radius:9px;color:#fff;background:linear-gradient(145deg,#0f79f1,#0b55b7);font-weight:900}.brand strong{display:block;color:var(--navy);font-size:13px}.brand small{color:var(--muted);font-size:9px}.btn{min-height:31px;display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border:1px solid #d7e4f0;border-radius:8px;color:#285c88;background:#f8fbff;text-decoration:none;font-weight:800}.btn.primary{border-color:var(--primary);color:#fff;background:var(--primary)}.btn.danger{color:#a73535;background:#fff7f7}.hero{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:8px;padding:12px 14px;border-radius:12px;background:linear-gradient(120deg,#0d5fc5,#1889eb);color:#fff}.hero h1{margin:0;font-size:20px}.hero p{max-width:700px;margin:3px 0 0;color:#e6f2ff;font-size:10px}.hero .btn{border-color:rgba(255,255,255,.35);color:#0d5fc5;background:#fff}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin-top:8px}.stat{padding:10px 11px;border-radius:10px}.stat span{display:block;color:var(--muted);font-size:9px}.stat strong{display:block;margin-top:3px;color:var(--navy);font-size:19px}.modules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:8px}.module{display:flex;align-items:center;gap:11px;min-height:92px;padding:12px;border-radius:12px;text-decoration:none;transition:.18s transform,.18s border-color}.module:hover{transform:translateY(-2px);border-color:#9ec5ef}.module-icon{flex:0 0 42px;width:42px;height:42px;display:grid;place-items:center;border-radius:11px;color:#0d63c8;background:var(--soft);font-size:18px}.module h2{margin:0;color:var(--navy);font-size:14px}.module p{margin:3px 0 0;color:var(--muted);font-size:10px}.badge{display:inline-flex;margin-top:6px;padding:3px 7px;border-radius:999px;color:var(--ok);background:#eaf8f1;font-size:8px;font-weight:850}.alert{margin-top:8px;padding:10px 12px;border-radius:10px;color:var(--warn);background:#fff8eb}@media(max-width:680px){.shell{width:calc(100% - 10px)}.topbar,.hero{align-items:flex-start;flex-direction:column}.actions{width:100%}.actions .btn,.hero .btn{flex:1}.stats,.modules{grid-template-columns:1fr 1fr}}@media(max-width:430px){.stats,.modules{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="shell">
    <header class="topbar">
        <div class="brand">
            <span class="mark">MS</span>
            <div><strong>Mesa de Servicio</strong><small>Panel del gestor · <?= escaparPanelGestor($nombreGestor) ?></small></div>
        </div>
        <nav class="actions">
            <a class="btn" href="flujoTicket.php?modo=mis_tickets">Mis tickets</a>
            <a class="btn danger" href="logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <section class="hero">
        <div>
            <h1>Gestión de tickets</h1>
            <p>Cree casos padre, atienda los servicios asignados y derive casos hijos. El SLA se pausa y reanuda automáticamente según sus dependencias.</p>
        </div>
        <?php if ($moduloInstalado): ?><a class="btn" href="flujoTicket.php?modo=nuevo">＋ Crear ticket</a><?php endif; ?>
    </section>

    <?php if (!$moduloInstalado): ?>
        <div class="alert">Importe <strong>migracion_casos_padre_hijo.sql</strong> para habilitar el módulo.</div>
    <?php else: ?>
        <section class="stats" aria-label="Resumen del gestor">
            <article class="stat"><span>Tickets creados o asignados</span><strong><?= $resumen['tickets'] ?></strong></article>
            <article class="stat"><span>Casos activos propios</span><strong><?= $resumen['activos'] ?></strong></article>
            <article class="stat"><span>Casos propios pausados</span><strong><?= $resumen['pausados'] ?></strong></article>
            <article class="stat"><span>Casos propios completados</span><strong><?= $resumen['completados'] ?></strong></article>
        </section>

        <section class="modules" aria-label="Opciones de tickets">
            <a class="module" href="flujoTicket.php?modo=nuevo">
                <span class="module-icon">＋</span>
                <div><h2>Crear ticket</h2><p>Seleccione el área y servicio existentes para abrir el caso padre.</p><span class="badge">Nuevo caso</span></div>
            </a>
            <a class="module" href="flujoTicket.php?modo=mis_tickets">
                <span class="module-icon">▦</span>
                <div><h2>Mis tickets</h2><p>Consulte árboles, SLA, checklist, chat, soluciones e histórico completo.</p><span class="badge"><?= $resumen['tickets'] ?> disponibles</span></div>
            </a>
        </section>
    <?php endif; ?>
</main>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
