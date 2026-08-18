<?php
declare(strict_types=1);

$mesaPaginaSolicitada = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
if ($mesaPaginaSolicitada === 'seleccionarpais.php') {
    return;
}

if (defined('MESA_CONTROLES_CUENTA_RENDERIZADOS')) {
    return;
}
define('MESA_CONTROLES_CUENTA_RENDERIZADOS', true);

require_once APP_ROOT . '/core/temasInterfaz.php';

function mesaCuentaEscapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$mesaRol = (int) ($_SESSION['rol'] ?? 0);
$mesaIdUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$mesaUsuario = trim((string) ($_SESSION['usuario'] ?? 'Usuario'));
$mesaCodigoPais = strtoupper(trim((string) ($_SESSION['pais_operacion_codigo'] ?? 'CO')));
$mesaNombrePais = trim((string) ($_SESSION['pais_operacion_nombre'] ?? ''));
$mesaIdPais = (int) ($_SESSION['pais_operacion_id'] ?? 0);

if (!in_array($mesaRol, [1, 2, 3], true)) {
    return;
}
if (!in_array($mesaCodigoPais, ['CO', 'PE'], true)) {
    $mesaCodigoPais = 'CO';
}
if ($mesaNombrePais === '') {
    $mesaNombrePais = $mesaCodigoPais === 'PE' ? 'Perú' : 'Colombia';
}

$mesaTemaClave = 'corporativo';
if (
    $mesaIdUsuario > 0
    && isset($conn)
    && $conn instanceof mysqli
) {
    $mesaTemaClave = temaInterfazUsuario(
        $conn,
        $mesaIdUsuario,
        $mesaCodigoPais
    );
}
$mesaPaleta = temaInterfazResolver($mesaTemaClave, $mesaCodigoPais);

function mesaCuentaTiempoNotificacion(?string $fecha): string
{
    $marca = $fecha ? strtotime($fecha) : false;

    if ($marca === false) {
        return '';
    }

    $segundos = max(0, time() - $marca);

    if ($segundos < 60) {
        return 'Ahora';
    }
    if ($segundos < 3600) {
        return 'Hace ' . max(1, intdiv($segundos, 60)) . ' min';
    }
    if ($segundos < 86400) {
        return 'Hace ' . max(1, intdiv($segundos, 3600)) . ' h';
    }
    if ($segundos < 604800) {
        return 'Hace ' . max(1, intdiv($segundos, 86400)) . ' d';
    }

    return date('d/m/Y H:i', $marca);
}

$mesaNotificaciones = [];
$mesaNotificacionesNoLeidas = 0;

if (
    $mesaIdUsuario > 0
    && $mesaIdPais > 0
    && isset($conn)
    && $conn instanceof mysqli
) {
    try {
        $mesaStmtConteo = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM notificaciones AS n
             LEFT JOIN tickets AS t ON t.id_ticket = n.id_ticket
             WHERE n.id_usuario = ?
               AND n.leida = 0
               AND (n.id_ticket IS NULL OR t.id_pais_operacion = ?)"
        );
        $mesaStmtConteo->bind_param('ii', $mesaIdUsuario, $mesaIdPais);
        $mesaStmtConteo->execute();
        $mesaFilaConteo = $mesaStmtConteo->get_result()->fetch_assoc();
        $mesaNotificacionesNoLeidas = (int) (
            $mesaFilaConteo['total'] ?? 0
        );
        $mesaStmtConteo->close();

        $mesaStmtNotificaciones = $conn->prepare(
            "SELECT
                n.id_notificacion,
                n.id_ticket,
                n.id_ticket_etapa,
                n.titulo,
                n.mensaje,
                n.leida,
                n.creada_en
             FROM notificaciones AS n
             LEFT JOIN tickets AS t ON t.id_ticket = n.id_ticket
             WHERE n.id_usuario = ?
               AND (n.id_ticket IS NULL OR t.id_pais_operacion = ?)
             ORDER BY n.leida ASC, n.creada_en DESC, n.id_notificacion DESC
             LIMIT 15"
        );
        $mesaStmtNotificaciones->bind_param(
            'ii',
            $mesaIdUsuario,
            $mesaIdPais
        );
        $mesaStmtNotificaciones->execute();
        $mesaResultadoNotificaciones =
            $mesaStmtNotificaciones->get_result();

        while ($mesaNotificacion =
            $mesaResultadoNotificaciones->fetch_assoc()
        ) {
            $mesaNotificaciones[] = $mesaNotificacion;
        }
        $mesaStmtNotificaciones->close();
    } catch (Throwable $e) {
        error_log(
            'No fue posible cargar la campana de notificaciones: '
            . $e->getMessage()
        );
        $mesaNotificaciones = [];
        $mesaNotificacionesNoLeidas = 0;
    }
}

$mesaMostrarAlertaNovedades = !empty(
    $_SESSION['mesa_alertar_novedades_al_ingresar']
) && $mesaNotificacionesNoLeidas > 0;
unset($_SESSION['mesa_alertar_novedades_al_ingresar']);

$mesaCargo = match ($mesaRol) {
    1 => 'Administrador General',
    2 => 'Gestor',
    default => 'Solicitante',
};
$mesaArchivoActual = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$mesaConsultaActual = (string) ($_SERVER['QUERY_STRING'] ?? '');

$mesaNavegacion = match ($mesaRol) {
    1 => [
        ['panelAdmin.php', 'IN', 'Centro de control'],
        ['solicitudes.php', 'TK', 'Tickets'],
        ['crearUsuarios.php', 'US', 'Usuarios'],
        ['indicadores.php', 'BI', 'Indicadores'],
        ['catalogos.php', 'CA', 'Catálogos'],
        ['servicios.php', 'SV', 'Servicios'],
        ['configuraciones.php?tipo=prioridad', 'PR', 'Prioridades'],
        ['soluciones.php', 'SO', 'Soluciones'],
        ['procesos.php', 'FL', 'Flujos'],
        ['sla.php', 'SL', 'SLA'],
        ['feriados.php', 'FE', 'Festivos'],
    ],
    2 => [
        ['panelGestor.php', 'IN', 'Resumen operativo'],
        ['flujoTicket.php?modo=mis_tickets', 'TK', 'Casos asignados'],
    ],
    default => [
        ['panelSolicitante.php?vista=nueva', 'NV', 'Crear solicitud'],
        ['panelSolicitante.php?vista=tickets', 'TK', 'Estado de solicitudes'],
    ],
};

function mesaCuentaRutaActiva(string $url, string $archivoActual, string $consultaActual): bool
{
    $partes = parse_url($url);
    $archivo = strtolower(basename((string) ($partes['path'] ?? '')));
    $consulta = (string) ($partes['query'] ?? '');

    if ($archivo === 'solicitudes.php' && in_array($archivoActual, ['solicitudes.php', 'chat.php'], true)) {
        return true;
    }
    if ($archivo === 'crearusuarios.php' && in_array($archivoActual, ['crearusuarios.php', 'editarusuario.php', 'registro.php'], true)) {
        return true;
    }
    if ($archivo === 'catalogos.php' && in_array($archivoActual, ['catalogos.php', 'editarcatalogos.php'], true)) {
        return true;
    }
    if ($archivo === 'procesos.php' && in_array($archivoActual, ['procesos.php', 'verificarflujos.php'], true)) {
        return true;
    }
    if ($archivo === 'feriados.php' && in_array($archivoActual, ['feriados.php', 'verificarcalendariosla.php'], true)) {
        return true;
    }
    if ($archivo === 'flujoticket.php' && $archivoActual === 'flujoticket.php') {
        return str_contains($consultaActual, 'modo=mis_tickets');
    }
    if ($archivo === 'panelsolicitante.php' && $archivoActual === 'panelsolicitante.php') {
        parse_str($consulta, $objetivo);
        parse_str($consultaActual, $actual);
        return ($objetivo['vista'] ?? 'nueva') === ($actual['vista'] ?? 'nueva');
    }
    if ($archivo !== $archivoActual) {
        return false;
    }
    if ($consulta === '') {
        return true;
    }
    parse_str($consulta, $objetivo);
    parse_str($consultaActual, $actual);
    foreach ($objetivo as $clave => $valor) {
        if (($actual[$clave] ?? null) !== $valor) {
            return false;
        }
    }
    return true;
}

$mesaEsPeru = $mesaCodigoPais === 'PE';
$mesaColor = $mesaPaleta['primary'];
$mesaOscuro = $mesaPaleta['dark'];
$mesaColorRgb = temaInterfazRgb($mesaColor);
?>
<style id="mesa-php-shell-style" data-mesa-php-shell>
    :root{
        --mesa-shell-color:<?= mesaCuentaEscapar($mesaColor) ?>;
        --mesa-shell-dark:<?= mesaCuentaEscapar($mesaOscuro) ?>;
        --mesa-theme-accent:<?= mesaCuentaEscapar($mesaPaleta['accent']) ?>;
        --mesa-theme-bg:<?= mesaCuentaEscapar($mesaPaleta['background']) ?>;
        --mesa-theme-surface:<?= mesaCuentaEscapar($mesaPaleta['surface']) ?>;
        --mesa-theme-soft:<?= mesaCuentaEscapar($mesaPaleta['soft']) ?>;
        --mesa-theme-text:<?= mesaCuentaEscapar($mesaPaleta['text']) ?>;
        --mesa-theme-heading:<?= mesaCuentaEscapar($mesaPaleta['heading']) ?>;
        --mesa-theme-muted:<?= mesaCuentaEscapar($mesaPaleta['muted']) ?>;
        --mesa-theme-border:<?= mesaCuentaEscapar($mesaPaleta['border']) ?>;
        --mesa-theme-sidebar-text:<?= mesaCuentaEscapar($mesaPaleta['sidebar_text']) ?>;
        --mesa-theme-sidebar-muted:<?= mesaCuentaEscapar($mesaPaleta['sidebar_muted']) ?>;
        --mesa-theme-on-primary:<?= mesaCuentaEscapar($mesaPaleta['on_primary']) ?>;
        --mesa-state-success:#087443;
        --mesa-state-warning:#f5b82e;
        --mesa-state-danger:#c92a35;
        --mesa-state-on:#ffffff;
        --mesa-state-on-warning:#251800;
        --mesa-shell-width:252px;
        --primary:var(--mesa-shell-color);
        --primary-dark:var(--mesa-shell-dark);
        --primary-rgb:<?= mesaCuentaEscapar($mesaColorRgb) ?>;
        --primary-color:var(--mesa-shell-color);
        --blue:var(--mesa-shell-color);
        --secondary:var(--mesa-theme-accent);
        --accent:var(--mesa-theme-accent);
        --accent-soft:var(--mesa-theme-soft);
        --navy:var(--mesa-theme-heading);
        --ink:var(--mesa-theme-heading);
        --text:var(--mesa-theme-text);
        --muted:var(--mesa-theme-muted);
        --border:var(--mesa-theme-border);
        --line:var(--mesa-theme-border);
        --surface:var(--mesa-theme-surface);
        --soft:var(--mesa-theme-soft);
        --primary-soft:var(--mesa-theme-soft);
        --bg:var(--mesa-theme-bg);
        --background:var(--mesa-theme-bg);
        --sidebar:var(--mesa-shell-dark);
        --co:var(--mesa-shell-color);
        --co-bright:var(--mesa-theme-accent);
        --co-dark:var(--mesa-shell-dark);
        --peru:var(--mesa-shell-color);
        --peru-dark:var(--mesa-shell-dark);
        --country:var(--mesa-shell-color);
        --card-accent:var(--mesa-shell-color);
        --card-soft:var(--mesa-theme-soft);
        --azul:var(--mesa-shell-color);
        --azul-oscuro:var(--mesa-shell-dark);
        --primario:var(--mesa-shell-color);
    }
    html{color-scheme:<?= mesaCuentaEscapar($mesaPaleta['scheme']) ?>;background:var(--mesa-theme-bg)}
    #gc-global-sidebar,#gc-global-top,#gc-account-menu,#gc-profile-modal,#gc-sidebar-floating,#svc-sidebar,#svc-topbar,#svc-account-menu,#svc-profile-modal,#svc-sidebar-floating,#svc-country-context{display:none!important}
    [data-gc-user-trigger],[data-gc-sidebar-toggle],[data-svc-user-trigger],[data-svc-sidebar-toggle]{display:none!important}
    body.mesa-php-shell{display:block!important;min-height:100vh!important;margin:0!important;padding:72px 0 24px var(--mesa-shell-width)!important;color:var(--mesa-theme-text)!important;background:var(--mesa-theme-bg)!important;transition:padding-left .18s ease}
    body.mesa-php-shell>.layout{display:block!important;min-height:0!important}
    body.mesa-php-shell>.layout>.sidebar,body.mesa-php-shell>.layout>aside.sidebar,body.mesa-php-shell>.layout>.main>.topbar{display:none!important}
    body.mesa-php-shell>.layout>.main{width:100%!important;min-width:0!important;max-width:none!important}
    #mesa-php-sidebar{position:fixed;inset:0 auto 0 0;z-index:2147482000;width:var(--mesa-shell-width);display:flex;flex-direction:column;padding:18px 16px;color:#fff;background:linear-gradient(165deg,var(--mesa-shell-dark),color-mix(in srgb,var(--mesa-shell-dark) 82%,var(--mesa-theme-accent)) 58%,color-mix(in srgb,var(--mesa-shell-dark) 92%,#000) 100%);box-shadow:14px 0 40px rgba(5,25,48,.15);font:12px/1.4 Inter,"Segoe UI",Arial,sans-serif;transition:transform .18s ease}
    .mesa-php-brand{display:flex;align-items:center;gap:10px;padding:2px 7px 18px;border-bottom:1px solid rgba(255,255,255,.1)}
    .mesa-php-flag{width:40px;height:40px;display:grid;flex:0 0 auto;place-items:center;border:1px solid rgba(255,255,255,.25);border-radius:11px;color:#fff;background:<?= $mesaEsPeru ? 'linear-gradient(90deg,#d9102f 0 33%,#fff 33% 67%,#d9102f 67%)' : 'linear-gradient(145deg,#f4cf18 0 48%,#1769cc 48% 72%,#cf2842 72%)' ?>;font-size:9px;font-weight:950}
    .mesa-php-brand-copy{min-width:0}.mesa-php-brand-copy strong,.mesa-php-brand-copy small{display:block}.mesa-php-brand-copy strong{font-size:14px}.mesa-php-brand-copy small{margin-top:2px;color:var(--mesa-theme-sidebar-muted);font-size:10px}
    .mesa-php-toggle{width:34px;height:34px;display:grid;flex:0 0 auto;place-items:center;margin-left:auto;border:1px solid rgba(255,255,255,.22);border-radius:9px;color:#fff;background:rgba(255,255,255,.08);cursor:pointer;font-size:17px;font-weight:900}.mesa-php-toggle:hover{background:rgba(255,255,255,.16)}
    .mesa-php-caption{margin:18px 9px 7px;color:var(--mesa-theme-sidebar-muted);font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
    .mesa-php-nav{min-height:0;display:grid;gap:4px;overflow-y:auto;padding-right:3px}.mesa-php-nav a{display:grid;grid-template-columns:29px minmax(0,1fr);align-items:center;gap:8px;min-height:40px;padding:8px 10px;border:1px solid transparent;border-radius:10px;color:var(--mesa-theme-sidebar-text);text-decoration:none;font-size:12px;font-weight:760}.mesa-php-nav a:hover{color:#fff;background:color-mix(in srgb,var(--mesa-theme-accent) 19%,transparent)}.mesa-php-nav a.active{border-color:rgba(255,255,255,.16);color:var(--mesa-theme-heading);background:var(--mesa-theme-surface);box-shadow:0 7px 18px rgba(0,0,0,.15)}
    .mesa-php-nav-code{width:27px;height:25px;display:grid;place-items:center;border-radius:7px;color:#a6dcff;background:rgba(42,168,255,.14);font-size:10px;font-weight:950}.mesa-php-nav a.active .mesa-php-nav-code{color:#fff;background:var(--mesa-shell-color)}
    #mesa-php-topbar{position:fixed;inset:0 0 auto var(--mesa-shell-width);z-index:2147481900;height:64px;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:0 25px;border-bottom:1px solid #dbe7f1;background:rgba(255,255,255,.98);box-shadow:0 5px 20px rgba(20,60,95,.06);font:760 11px/1.3 Inter,"Segoe UI",Arial,sans-serif;transition:left .18s ease}
    #mesa-php-topbar>strong{color:#15263a;font-size:14px}.mesa-php-top-right{display:flex;align-items:center;gap:12px}.mesa-php-operation{display:inline-flex;align-items:center;gap:7px;color:var(--mesa-shell-color);font-size:10px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.mesa-php-operation:before{width:7px;height:7px;border-radius:50%;background:#18a96b;box-shadow:0 0 0 4px rgba(24,169,107,.12);content:""}
    .mesa-php-notifications{position:relative}.mesa-php-bell{position:relative;width:42px;height:42px;display:grid;place-items:center;border:1px solid transparent;border-radius:50%;color:#385570;background:transparent;cursor:pointer}.mesa-php-bell:hover,.mesa-php-bell[aria-expanded="true"]{border-color:#d4e3f0;color:var(--mesa-shell-color);background:#f0f6fc}.mesa-php-bell svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.mesa-php-bell-badge{position:absolute;top:1px;right:0;min-width:19px;height:19px;display:grid;place-items:center;padding:0 5px;border:2px solid #fff;border-radius:999px;color:#fff;background:#e7374d;font-size:9px;font-weight:950;line-height:1}.mesa-php-notification-panel{position:absolute;top:calc(100% + 9px);right:-56px;width:min(390px,calc(100vw - 24px));overflow:hidden;border:1px solid #d7e3ed;border-radius:16px;background:#fff;box-shadow:0 22px 60px rgba(9,35,61,.24)}.mesa-php-notification-panel[hidden]{display:none!important}.mesa-php-notification-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:15px 16px 12px;border-bottom:1px solid #e6edf4}.mesa-php-notification-head strong,.mesa-php-notification-head small{display:block}.mesa-php-notification-head strong{color:#142d45;font-size:17px}.mesa-php-notification-head small{margin-top:3px;color:#6d8295;font-size:10px}.mesa-php-notification-head-actions{display:grid;justify-items:end;gap:6px}.mesa-php-notification-total{display:inline-flex;padding:5px 8px;border-radius:999px;color:var(--mesa-shell-color);background:#eaf3fd;font-size:9px;font-weight:900}.mesa-php-mark-all{padding:3px 0;border:0;color:var(--mesa-shell-color);background:transparent;cursor:pointer;font:850 10px/1.2 Inter,"Segoe UI",Arial,sans-serif;white-space:nowrap}.mesa-php-mark-all:hover{text-decoration:underline}.mesa-php-mark-all:disabled{opacity:.55;cursor:wait;text-decoration:none}.mesa-php-notification-list{max-height:min(480px,calc(100vh - 150px));display:grid;overflow-y:auto;overscroll-behavior:contain}.mesa-php-notification-item{position:relative;display:grid;grid-template-columns:42px minmax(0,1fr);gap:10px;padding:12px 15px;color:inherit;border-bottom:1px solid #edf2f6;background:#fff;text-decoration:none}.mesa-php-notification-item:hover{background:#f5f9fd}.mesa-php-notification-item.unread{background:#edf6ff}.mesa-php-notification-item.unread:hover{background:#e4f1ff}.mesa-php-notification-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:50%;color:var(--mesa-shell-color);background:#e0efff}.mesa-php-notification-icon svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.mesa-php-notification-copy{min-width:0}.mesa-php-notification-copy strong,.mesa-php-notification-copy span,.mesa-php-notification-copy time{display:block}.mesa-php-notification-copy strong{padding-right:12px;overflow:hidden;color:#17314a;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.mesa-php-notification-copy span{display:-webkit-box;margin-top:3px;overflow:hidden;color:#526c82;font-size:10px;font-weight:600;line-height:1.4;-webkit-box-orient:vertical;-webkit-line-clamp:2}.mesa-php-notification-copy time{margin-top:5px;color:#7990a4;font-size:9px}.mesa-php-notification-item.unread .mesa-php-notification-copy time{color:var(--mesa-shell-color);font-weight:850}.mesa-php-notification-dot{position:absolute;top:17px;right:13px;width:7px;height:7px;border-radius:50%;background:var(--mesa-shell-color)}.mesa-php-notification-empty{display:grid;place-items:center;min-height:190px;padding:28px;color:#668096;text-align:center}.mesa-php-notification-empty svg{width:36px;height:36px;margin-bottom:9px;fill:none;stroke:#8da5ba;stroke-width:1.6}.mesa-php-notification-empty strong,.mesa-php-notification-empty span{display:block}.mesa-php-notification-empty strong{color:#29445d;font-size:12px}.mesa-php-notification-empty span{margin-top:4px;font-size:10px}
    .mesa-php-login-notice{position:fixed;top:78px;right:22px;z-index:2147483500;width:min(390px,calc(100vw - 24px));display:grid;grid-template-columns:42px minmax(0,1fr) 28px;gap:10px;align-items:start;padding:13px;border:1px solid #b9d8f6;border-radius:15px;background:#fff;box-shadow:0 18px 50px rgba(12,63,112,.22);animation:mesaNoticeIn .28s ease both}.mesa-php-login-notice[hidden]{display:none!important}.mesa-php-login-notice-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:50%;color:#fff;background:linear-gradient(145deg,var(--mesa-shell-color),var(--mesa-shell-dark))}.mesa-php-login-notice-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.9}.mesa-php-login-notice-copy strong,.mesa-php-login-notice-copy span{display:block}.mesa-php-login-notice-copy strong{color:#17314a;font-size:12px}.mesa-php-login-notice-copy span{margin-top:3px;color:#526c82;font-size:10px;font-weight:650;line-height:1.45}.mesa-php-login-notice-copy button{margin-top:8px;padding:6px 9px;border:0;border-radius:7px;color:#fff;background:var(--mesa-shell-color);cursor:pointer;font:800 10px/1.2 Inter,"Segoe UI",Arial,sans-serif}.mesa-php-login-notice-close{width:28px;height:28px;border:0;border-radius:8px;color:#60788d;background:#eef4f9;cursor:pointer;font-size:17px}@keyframes mesaNoticeIn{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:none}}
    .mesa-php-account{position:relative}.mesa-php-account summary{min-width:44px;min-height:44px;display:flex;align-items:center;gap:8px;padding:4px 7px;border:1px solid transparent;border-radius:11px;list-style:none;cursor:pointer;font-size:14px}.mesa-php-account summary::-webkit-details-marker{display:none}.mesa-php-account[open] summary,.mesa-php-account summary:hover{border-color:#d7e5f1;background:#f2f7fc}.mesa-php-account img{width:36px;height:36px;display:block;border:2px solid #fff;border-radius:11px;object-fit:cover;background:#fff;box-shadow:0 5px 14px rgba(17,103,216,.18)}.mesa-php-user-copy strong,.mesa-php-user-copy small{display:block}.mesa-php-user-copy strong{max-width:180px;overflow:hidden;color:#182d42;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.mesa-php-user-copy small{margin-top:1px;color:#526d82;font-size:10px;font-weight:700}.mesa-php-chevron{color:#526d82;font-size:14px}
    .mesa-php-account-menu{position:absolute;top:calc(100% + 8px);right:0;width:240px;padding:6px;border:1px solid #dbe5ee;border-radius:13px;background:#fff;box-shadow:0 18px 45px rgba(16,42,67,.2)}.mesa-php-account-menu button,.mesa-php-account-menu a{width:100%;min-height:48px;display:grid;grid-template-columns:34px minmax(0,1fr);align-items:center;gap:9px;padding:9px;border:0;border-radius:9px;color:#263f57;background:transparent;text-align:left;text-decoration:none;cursor:pointer;font:14px/1.3 Inter,"Segoe UI",Arial,sans-serif}.mesa-php-account-menu button:hover,.mesa-php-account-menu a:hover{background:#f1f6fb}.mesa-php-menu-icon{width:32px;height:32px;display:grid;place-items:center;border-radius:9px;color:var(--mesa-shell-color);background:#edf5ff;font-size:10px;font-weight:900}.mesa-php-menu-icon.logout{color:#a43a43;background:#fff1f1;font-size:15px}.mesa-php-account-menu strong,.mesa-php-account-menu small{display:block}.mesa-php-account-menu strong{color:#1c3349;font-size:12px}.mesa-php-account-menu small{margin-top:2px;color:#526d82;font-size:10px}
    #mesa-php-profile-modal{position:fixed;inset:0;z-index:2147483600;display:grid;place-items:center;padding:16px;background:rgba(8,28,48,.55);backdrop-filter:blur(4px)}#mesa-php-profile-modal[hidden]{display:none!important}.mesa-php-profile-card{width:min(760px,95vw);overflow:hidden;border:1px solid #dce6ef;border-radius:17px;background:#fff;box-shadow:0 30px 90px rgba(7,28,48,.32)}.mesa-php-profile-head{height:54px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 16px;border-bottom:1px solid #e3ebf2}.mesa-php-profile-head strong,.mesa-php-profile-head small{display:block}.mesa-php-profile-head strong{color:#152b40;font-size:14px}.mesa-php-profile-head small{margin-top:2px;color:#75899c;font-size:9px}.mesa-php-profile-close{width:33px;height:33px;border:1px solid #d9e4ed;border-radius:9px;color:#536a80;background:#f8fafc;cursor:pointer;font-size:20px}.mesa-php-profile-body{position:relative;height:min(690px,calc(100vh - 104px));background:#eef3f7}.mesa-php-profile-loader{position:absolute;inset:0;display:grid;place-items:center;color:#526d82;font-size:11px;font-weight:800}.mesa-php-profile-body iframe{position:relative;width:100%;height:100%;display:block;border:0;background:#eef3f7;opacity:0}.mesa-php-profile-loaded iframe{opacity:1}.mesa-php-profile-loaded .mesa-php-profile-loader{display:none}
    #mesa-php-appearance-modal{position:fixed;inset:0;z-index:2147483610;display:grid;place-items:center;padding:16px;background:rgba(8,28,48,.6);backdrop-filter:blur(5px)}#mesa-php-appearance-modal[hidden]{display:none!important}.mesa-php-appearance-card{width:min(1040px,96vw);overflow:hidden;border:1px solid #dce6ef;border-radius:18px;background:#fff;box-shadow:0 32px 96px rgba(7,28,48,.36)}.mesa-php-appearance-head{min-height:64px;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:11px 18px;border-bottom:1px solid #e3ebf2}.mesa-php-appearance-head strong,.mesa-php-appearance-head small{display:block}.mesa-php-appearance-head strong{color:#152b40;font-size:17px}.mesa-php-appearance-head small{margin-top:3px;color:#75899c;font-size:11px;line-height:1.4}.mesa-php-appearance-close{width:38px;height:38px;display:grid;flex:0 0 auto;place-items:center;border:1px solid #d9e4ed;border-radius:11px;color:#536a80;background:#f8fafc;cursor:pointer;font-size:22px}.mesa-php-appearance-body{position:relative;height:min(760px,calc(100vh - 112px));background:#eef3f7}.mesa-php-appearance-loader{position:absolute;inset:0;display:grid;place-items:center;color:#526d82;font-size:13px;font-weight:800}.mesa-php-appearance-body iframe{position:relative;width:100%;height:100%;display:block;border:0;background:#eef3f7;opacity:0}.mesa-php-appearance-loaded iframe{opacity:1}.mesa-php-appearance-loaded .mesa-php-appearance-loader{display:none}
    #mesa-php-floating{position:fixed;top:14px;left:14px;z-index:2147483400;display:none}.mesa-php-floating-button{width:36px;height:36px;display:grid;place-items:center;border:1px solid #cbdbea;border-radius:9px;color:var(--mesa-shell-color);background:#fff;box-shadow:0 7px 20px rgba(16,42,67,.16);cursor:pointer;font-size:18px;font-weight:900}
    #mesa-php-country{position:fixed;right:14px;bottom:12px;z-index:2147483000;display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border:1px solid #cbdbea;border-radius:999px;color:var(--mesa-shell-color);background:rgba(255,255,255,.96);box-shadow:0 8px 22px rgba(16,42,67,.13);font:800 11px/1.2 Inter,"Segoe UI",Arial,sans-serif}#mesa-php-country:before{width:8px;height:8px;border-radius:50%;background:var(--mesa-shell-color);content:""}
    body.mesa-php-sidebar-collapsed{padding-left:0!important}body.mesa-php-sidebar-collapsed #mesa-php-sidebar{visibility:hidden;pointer-events:none;transform:translateX(-105%)}body.mesa-php-sidebar-collapsed #mesa-php-topbar{left:0;padding-left:64px}body.mesa-php-sidebar-collapsed #mesa-php-floating{display:block}
    /* Capa transversal de legibilidad y reflujo. Se carga al final de cada vista. */
    html:has(body.mesa-php-shell),body.mesa-php-shell{max-width:100%;overflow-x:clip}
    body.mesa-php-shell :is(a,button,summary,input,select,textarea){min-width:24px;min-height:24px;font-size:14px!important}
    body.mesa-php-shell h1{font-size:clamp(24px,3vw,32px)!important;line-height:1.2}
    body.mesa-php-shell h2{font-size:18px!important;line-height:1.25}
    body.mesa-php-shell :is(.layout,.main,.content,.shell,.page-shell,.grid,.dashboard,.card,.panel,.table-wrap){min-width:0;max-width:100%}
    body.mesa-php-shell .table-wrap{overflow-x:auto;overscroll-behavior-x:contain}
    body.mesa-php-shell :is(.parameter span,.hub-label,.sidebar-action-code,.badge,.trend-label,.trend-axis,.dimension-line,.chart-meta small,.ring-wrap small,.mesa-php-profile-head small){font-size:9px!important}
    /* El tema se aplica después de los estilos propios de cada módulo. */
    body.mesa-php-shell :is(.card,.panel,.stat,.tabs,.table-wrap,.case-detail-dialog,.chat-panel,.case-chat-panel){border-color:var(--mesa-theme-border)}
    body.mesa-php-shell :is(.card,.panel,.stat,.tabs,.case-detail-dialog,.chat-panel,.case-chat-panel){background-color:var(--mesa-theme-surface)}
    body.mesa-php-shell :is(.btn.primary,.button.primary,.tab.active,.mark,.upload-action,.chat-send,.case-chat-send){border-color:var(--mesa-shell-color)!important;color:var(--mesa-theme-on-primary)!important;background:linear-gradient(145deg,var(--mesa-shell-color),color-mix(in srgb,var(--mesa-shell-color) 72%,var(--mesa-theme-accent)))!important}
    body.mesa-php-shell :is(.chat-header,.case-chat-header){background:linear-gradient(135deg,var(--mesa-shell-dark),color-mix(in srgb,var(--mesa-shell-color) 48%,var(--mesa-shell-dark)) 70%,color-mix(in srgb,var(--mesa-theme-accent) 52%,var(--mesa-shell-dark)))!important}
    body.mesa-php-shell :is(.status:not(.cerrado):not(.cancelado):not(.completada):not(.cancelada),.badge:not(.danger):not(.success),.view-link,.theme-link){color:var(--mesa-shell-color)}
    body.mesa-php-shell :is(.hero,.form-header,.dashboard-hero,.page-hero){border-color:color-mix(in srgb,var(--mesa-shell-color) 28%,var(--mesa-theme-border))!important;color:#fff!important;background:linear-gradient(120deg,var(--mesa-shell-dark) 0%,color-mix(in srgb,var(--mesa-shell-color) 46%,var(--mesa-shell-dark)) 64%,color-mix(in srgb,var(--mesa-theme-accent) 50%,var(--mesa-shell-dark)) 100%)!important}
    body.mesa-php-shell :is(.mark,.brand-mark,.avatar,.drawer-icon,.chat-launcher,.case-chat-launcher,.btn-primary,.btn-crear,.btn-guardar,.btn-nuevo,.btn-actualizar,.open-btn){border-color:var(--mesa-shell-color)!important;color:var(--mesa-theme-on-primary)!important;background:linear-gradient(145deg,var(--mesa-shell-color),color-mix(in srgb,var(--mesa-shell-color) 68%,var(--mesa-theme-accent)))!important}
    body.mesa-php-shell :is(.module-code,.module-icon,.nav-code,.parameter span,.section-icon,.chat-file-icon,.case-chat-file-icon){color:var(--mesa-shell-color)!important;background:var(--mesa-theme-soft)!important}
    body.mesa-php-shell :is(.tab.active,.case-tab.active,.table-mode-toggle.active,.chat-stage-option.is-active,.case-chat-stage-option.is-active,.branch-active){border-color:var(--mesa-shell-color)!important;color:var(--mesa-theme-on-primary)!important;background:var(--mesa-shell-color)!important}
    body.mesa-php-shell :is(.ticket,.module-arrow,.comment-detail summary,.card-toggle,.chat-author,.case-chat-author,.chat-download,.case-chat-download){color:var(--mesa-shell-color)!important}
    body.mesa-php-shell :is(.hub-head,.card-head,.panel-head,.section-head,.heading)::before{background:linear-gradient(var(--mesa-shell-color),var(--mesa-theme-accent))!important}
    body.mesa-php-shell :is(.module:hover,.module-row:hover,.selectable-row:hover,.chat-stage-option:hover,.case-chat-stage-option:hover){border-color:color-mix(in srgb,var(--mesa-shell-color) 35%,var(--mesa-theme-border))!important;background:var(--mesa-theme-soft)!important}
    body.mesa-php-shell :is(input,select,textarea){accent-color:var(--mesa-shell-color)}
    body.mesa-php-shell :is(input,select,textarea):focus{border-color:var(--mesa-shell-color)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--mesa-shell-color) 13%,transparent)!important}
    /* Superficies completas: elimina blancos o azules fijos de los módulos. */
    #mesa-php-topbar{border-color:var(--mesa-theme-border)!important;background:color-mix(in srgb,var(--mesa-theme-surface) 96%,transparent)!important;box-shadow:0 5px 20px color-mix(in srgb,var(--mesa-shell-dark) 18%,transparent)!important}
    #mesa-php-topbar>strong{color:var(--mesa-theme-heading)!important}
    .mesa-php-bell{color:var(--mesa-theme-text)!important}
    .mesa-php-bell:hover,.mesa-php-bell[aria-expanded="true"],.mesa-php-account[open] summary,.mesa-php-account summary:hover{border-color:var(--mesa-theme-border)!important;color:var(--mesa-shell-color)!important;background:var(--mesa-theme-soft)!important}
    .mesa-php-user-copy strong,.mesa-php-account-menu strong,.mesa-php-notification-head strong,.mesa-php-notification-copy strong,.mesa-php-notification-empty strong,.mesa-php-login-notice-copy strong,.mesa-php-profile-head strong{color:var(--mesa-theme-heading)!important}
    .mesa-php-user-copy small,.mesa-php-chevron,.mesa-php-account-menu small,.mesa-php-notification-head small,.mesa-php-notification-copy span,.mesa-php-notification-copy time,.mesa-php-notification-empty,.mesa-php-login-notice-copy span,.mesa-php-profile-head small,.mesa-php-profile-loader{color:var(--mesa-theme-muted)!important}
    .mesa-php-notification-panel,.mesa-php-account-menu,.mesa-php-login-notice,.mesa-php-profile-card{border-color:var(--mesa-theme-border)!important;background:var(--mesa-theme-surface)!important;box-shadow:0 22px 60px color-mix(in srgb,var(--mesa-shell-dark) 46%,transparent)!important}
    .mesa-php-notification-head,.mesa-php-notification-item,.mesa-php-profile-head{border-color:var(--mesa-theme-border)!important;background:var(--mesa-theme-surface)!important}
    .mesa-php-notification-item:hover,.mesa-php-account-menu button:hover,.mesa-php-account-menu a:hover{background:var(--mesa-theme-soft)!important}
    .mesa-php-notification-item.unread{background:color-mix(in srgb,var(--mesa-shell-color) 13%,var(--mesa-theme-surface))!important}
    .mesa-php-notification-icon,.mesa-php-notification-total,.mesa-php-menu-icon{color:var(--mesa-shell-color)!important;background:var(--mesa-theme-soft)!important}
    .mesa-php-profile-body,.mesa-php-profile-body iframe{background:var(--mesa-theme-bg)!important}
    .mesa-php-profile-close,.mesa-php-login-notice-close,.mesa-php-floating-button,#mesa-php-country{border-color:var(--mesa-theme-border)!important;color:var(--mesa-shell-color)!important;background:var(--mesa-theme-surface)!important}
    .mesa-php-appearance-card{border-color:var(--mesa-theme-border)!important;background:var(--mesa-theme-surface)!important;box-shadow:0 24px 72px color-mix(in srgb,var(--mesa-shell-dark) 52%,transparent)!important}.mesa-php-appearance-head{border-color:var(--mesa-theme-border)!important;background:var(--mesa-theme-surface)!important}.mesa-php-appearance-head strong{color:var(--mesa-theme-heading)!important}.mesa-php-appearance-head small,.mesa-php-appearance-loader{color:var(--mesa-theme-muted)!important}.mesa-php-appearance-body,.mesa-php-appearance-body iframe{background:var(--mesa-theme-bg)!important}.mesa-php-appearance-close{border-color:var(--mesa-theme-border)!important;color:var(--mesa-shell-color)!important;background:var(--mesa-theme-soft)!important}
    .mesa-php-nav a.active{color:var(--mesa-theme-heading)!important;background:var(--mesa-theme-surface)!important}
    .mesa-php-nav a.active .mesa-php-nav-code{color:var(--mesa-theme-on-primary)!important;background:var(--mesa-shell-color)!important}
    body.mesa-php-shell :is(.topbar,.card,.panel,.stat,.tabs,.table-wrap,.box,.form-card,.hub,.hub-head,.manager-ticket-modal-bar,.case-detail-dialog,.case-detail-modal-bar,.chat-panel,.case-chat-panel,.chat-compose,.case-chat-compose){border-color:var(--mesa-theme-border)!important;color:var(--mesa-theme-text)!important;background:var(--mesa-theme-surface)!important}
    body.mesa-php-shell :is(.manager-case-filters,.case-filters,.filters,.filter,.detail-item,.case,.module,.module-row,.catalog-option,.service-option,.selected-service,.service-summary,.file-dropzone,.file-item,.email-preference,.solution-box,.rating-card,.wizard,.wizard-progress,.manager-ticket-modal-window,.chat-stage,.case-chat-stage,.chat-timeline,.case-chat-timeline,.chat-readonly,.case-chat-readonly){border-color:var(--mesa-theme-border)!important;color:var(--mesa-theme-text)!important;background:var(--mesa-theme-soft)!important}
    body.mesa-php-shell :is(h1,h2,h3,h4,h5,label,legend,.brand strong,.card-head strong,.panel-head strong,.detail-item strong,.selected-service strong,.service-summary strong,.catalog-option strong,.service-option strong,.module strong,.module h2,.case strong,td strong,.chat-text,.case-chat-text,.chat-file-copy strong,.case-chat-file-copy strong){color:var(--mesa-theme-heading)!important}
    body.mesa-php-shell :is(.hero,.form-header,.dashboard-hero,.page-hero,.chat-header,.case-chat-header) :is(h1,h2,h3,h4,strong,p,span,small){color:#fff!important}
    body.mesa-php-shell :is(.muted,.help,.case-filter-count,.manager-filter-count,.service-summary p,.service-option p,.detail-item span,.catalog-option small,.module p,.case small,.chat-stage-label,.case-chat-stage-label,.chat-stage-current small,.case-chat-stage-current small,.chat-time,.case-chat-time,.file-status){color:var(--mesa-theme-muted)!important}
    body.mesa-php-shell :is(input,select,textarea,.chat-stage-picker summary,.case-chat-stage-picker summary){border-color:var(--mesa-theme-border)!important;color:var(--mesa-theme-text)!important;background:color-mix(in srgb,var(--mesa-theme-surface) 72%,var(--mesa-theme-soft))!important}
    body.mesa-php-shell :is(input,textarea)::placeholder{color:var(--mesa-theme-muted)!important;opacity:.88}
    body.mesa-php-shell option{color:var(--mesa-theme-text);background:var(--mesa-theme-surface)}
    body.mesa-php-shell table{color:var(--mesa-theme-text)!important;background:var(--mesa-theme-surface)!important}
    body.mesa-php-shell :is(thead,th){border-color:var(--mesa-theme-border)!important;color:var(--mesa-theme-muted)!important;background:var(--mesa-theme-soft)!important}
    body.mesa-php-shell :is(tbody,tr,td){border-color:var(--mesa-theme-border)!important;color:var(--mesa-theme-text)!important;background-color:var(--mesa-theme-surface)!important}
    body.mesa-php-shell tbody tr:hover td{background:var(--mesa-theme-soft)!important}
    body.mesa-php-shell .btn:not(.light):not(.outline):not(.reopen):not(.email-toggle):not(.danger):not(.btn-danger):not(.btn-warning):not(.btn-success):not(.btn-cancel):not(.btn-cancelar):not(.btn-soft){border-color:var(--mesa-shell-color)!important;color:var(--mesa-theme-on-primary)!important;background:var(--mesa-shell-color)!important}
    body.mesa-php-shell :is(.btn.light,.btn.outline,.btn-soft,.btn-volver,.btn.email-toggle,.view-link){border-color:color-mix(in srgb,var(--mesa-shell-color) 45%,var(--mesa-theme-border))!important;color:var(--mesa-shell-color)!important;background:var(--mesa-theme-soft)!important}
    body.mesa-php-shell :is(.tab,.case-tab,.table-mode-toggle){border-color:var(--mesa-theme-border)!important;color:var(--mesa-theme-text)!important;background:var(--mesa-theme-surface)!important}
    body.mesa-php-shell :is(.chat-stage-options,.case-chat-stage-options){border-color:var(--mesa-theme-border)!important;background:var(--mesa-theme-surface)!important}
    body.mesa-php-shell :is(.chat-stage-option,.case-chat-stage-option){color:var(--mesa-theme-text)!important;background:var(--mesa-theme-surface)!important}
    body.mesa-php-shell :is(.chat-bubble,.case-chat-bubble){border-color:var(--mesa-theme-border)!important;color:var(--mesa-theme-text)!important;background:var(--mesa-theme-surface)!important}
    body.mesa-php-shell :is(.chat-bubble.mine,.case-chat-bubble.mine){border-color:color-mix(in srgb,var(--mesa-shell-color) 55%,var(--mesa-theme-border))!important;background:color-mix(in srgb,var(--mesa-shell-color) 18%,var(--mesa-theme-surface))!important}
    body.mesa-php-shell :is(.chat-file-card,.case-chat-file-card,.chat-attach,.case-chat-attach){color:var(--mesa-theme-text)!important;background:var(--mesa-theme-soft)!important}
    /* Chat: jerarquía visual común para solicitante y gestor. */
    body.mesa-php-shell :is(.chat-panel,.case-chat-panel){
        border-color:color-mix(in srgb,var(--mesa-shell-color) 55%,var(--mesa-theme-border))!important;
        background:var(--mesa-theme-surface)!important;
        box-shadow:0 24px 70px color-mix(in srgb,var(--mesa-shell-dark) 62%,transparent)!important;
    }
    body.mesa-php-shell :is(.chat-header,.case-chat-header){
        border-bottom:1px solid color-mix(in srgb,var(--mesa-theme-accent) 42%,transparent)!important;
    }
    body.mesa-php-shell :is(.chat-close,.case-chat-close){
        border:1px solid color-mix(in srgb,#fff 72%,transparent)!important;
        color:#fff!important;
        background:color-mix(in srgb,#fff 15%,transparent)!important;
    }
    body.mesa-php-shell :is(.chat-stage,.case-chat-stage){
        border-color:var(--mesa-theme-border)!important;
        background:var(--mesa-theme-surface)!important;
    }
    body.mesa-php-shell :is(.chat-stage-label,.case-chat-stage-label,.case-chat-stage label){
        color:var(--mesa-theme-heading)!important;
    }
    body.mesa-php-shell :is(.chat-stage-picker summary,.case-chat-stage-picker summary){
        border-color:color-mix(in srgb,var(--mesa-shell-color) 48%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-text)!important;
        background:var(--mesa-theme-soft)!important;
        box-shadow:0 5px 14px color-mix(in srgb,var(--mesa-shell-dark) 28%,transparent)!important;
    }
    body.mesa-php-shell :is(.chat-stage-current strong,.case-chat-stage-current strong){color:var(--mesa-theme-heading)!important}
    body.mesa-php-shell :is(.chat-stage-current small,.case-chat-stage-current small){color:var(--mesa-theme-muted)!important}
    body.mesa-php-shell :is(.chat-stage-number,.case-chat-stage-number){
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell :is(.chat-stage-arrow,.case-chat-stage-arrow){
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell :is(.chat-stage-options,.case-chat-stage-options){
        border-color:var(--mesa-theme-border)!important;
        background:var(--mesa-theme-surface)!important;
        box-shadow:0 18px 38px color-mix(in srgb,var(--mesa-shell-dark) 54%,transparent)!important;
    }
    body.mesa-php-shell :is(.chat-stage-option,.case-chat-stage-option){
        color:var(--mesa-theme-text)!important;
        background:var(--mesa-theme-surface)!important;
    }
    body.mesa-php-shell :is(.chat-stage-option:hover,.case-chat-stage-option:hover){
        color:var(--mesa-theme-heading)!important;
        background:var(--mesa-theme-soft)!important;
    }
    body.mesa-php-shell :is(.chat-stage-option.is-active,.case-chat-stage-option.is-active){
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell :is(.chat-stage-option.is-active,.case-chat-stage-option.is-active) :is(strong,small,.chat-stage-check,.case-chat-stage-check){
        color:var(--mesa-theme-on-primary)!important;
    }
    body.mesa-php-shell :is(.chat-timeline,.case-chat-timeline){
        background:
            radial-gradient(circle at 12px 12px,color-mix(in srgb,var(--mesa-theme-border) 65%,transparent) 1px,transparent 1.2px),
            color-mix(in srgb,var(--mesa-theme-bg) 80%,var(--mesa-shell-dark))!important;
        background-size:24px 24px,auto!important;
    }
    body.mesa-php-shell :is(.chat-bubble,.case-chat-bubble){
        border-color:var(--mesa-theme-border)!important;
        color:var(--mesa-theme-text)!important;
        background:var(--mesa-theme-surface)!important;
        box-shadow:0 4px 14px color-mix(in srgb,var(--mesa-shell-dark) 30%,transparent)!important;
    }
    body.mesa-php-shell :is(.chat-bubble.mine,.case-chat-bubble.mine){
        border-color:color-mix(in srgb,var(--mesa-shell-color) 78%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-on-primary)!important;
        background:linear-gradient(145deg,var(--mesa-shell-color),color-mix(in srgb,var(--mesa-shell-color) 78%,var(--mesa-shell-dark)))!important;
    }
    body.mesa-php-shell :is(.chat-header-copy span,.case-chat-header-copy span){font-size:10px!important}
    body.mesa-php-shell :is(.chat-author,.case-chat-author){
        color:var(--mesa-theme-heading)!important;
        font-size:10.5px!important;
        line-height:1.3!important;
        letter-spacing:.01em!important;
        text-transform:capitalize;
    }
    body.mesa-php-shell :is(.chat-text,.case-chat-text){
        color:var(--mesa-theme-text)!important;
        font-size:12.5px!important;
        line-height:1.5!important;
    }
    body.mesa-php-shell :is(.chat-time,.case-chat-time){
        color:var(--mesa-theme-muted)!important;
        font-size:9px!important;
    }
    body.mesa-php-shell :is(.chat-bubble.mine,.case-chat-bubble.mine) :is(
        .chat-author,.case-chat-author,.chat-text,.case-chat-text,.chat-time,.case-chat-time,
        .chat-file-copy strong,.case-chat-file-copy strong,.chat-file-copy span,.case-chat-file-copy span,
        .chat-download,.case-chat-download,.read-mark
    ){color:var(--mesa-theme-on-primary)!important}
    body.mesa-php-shell :is(.chat-file-card,.case-chat-file-card){
        border:1px solid color-mix(in srgb,var(--mesa-shell-color) 34%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-text)!important;
        background:var(--mesa-theme-soft)!important;
    }
    body.mesa-php-shell :is(.chat-file-icon,.case-chat-file-icon){
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell :is(.chat-file-copy strong,.case-chat-file-copy strong){
        color:var(--mesa-theme-heading)!important;
        font-size:10.5px!important;
    }
    body.mesa-php-shell :is(.chat-file-copy span,.case-chat-file-copy span){
        color:var(--mesa-theme-muted)!important;
        font-size:9px!important;
    }
    body.mesa-php-shell :is(.chat-bubble.mine,.case-chat-bubble.mine) :is(.chat-file-card,.case-chat-file-card){
        border-color:color-mix(in srgb,var(--mesa-theme-on-primary) 35%,transparent)!important;
        background:color-mix(in srgb,var(--mesa-theme-on-primary) 13%,var(--mesa-shell-color))!important;
    }
    body.mesa-php-shell :is(.chat-bubble.mine,.case-chat-bubble.mine) :is(.chat-file-icon,.case-chat-file-icon){
        color:var(--mesa-theme-on-primary)!important;
        background:color-mix(in srgb,var(--mesa-theme-on-primary) 18%,transparent)!important;
    }
    body.mesa-php-shell :is(.chat-download,.case-chat-download,.chat-time .read-mark,.case-chat-time .read-mark){color:var(--mesa-theme-accent)!important}
    body.mesa-php-shell :is(.chat-compose,.case-chat-compose,.chat-readonly,.case-chat-readonly){
        border-color:var(--mesa-theme-border)!important;
        color:var(--mesa-theme-heading)!important;
        background:var(--mesa-theme-surface)!important;
    }
    body.mesa-php-shell :is(.chat-attach,.case-chat-attach){
        border:1px solid color-mix(in srgb,var(--mesa-shell-color) 42%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-heading)!important;
        background:var(--mesa-theme-soft)!important;
    }
    body.mesa-php-shell :is(.chat-empty,.case-chat-empty){color:var(--mesa-theme-muted)!important}
    body.mesa-php-shell :is(.manager-ticket-modal-scroll,.case-detail-scroll){background:var(--mesa-theme-bg)!important}
    /* Cobertura final de los componentes que conservaban colores fijos por módulo. */
    body.mesa-php-shell :is(
        .top,.filter-panel,.kpi,.chart-card,.performance,.trend-card,.module-card,.config-card,
        .case-row,.case-modal-header,.case-tabs,.case-panel-section,.case-panel-toggle,
        .case-dialog .card,.stage,.stage-head,.stage-data,.audit-item,
        .action-summary,.action-summary-grid>div,.action-rating-summary>div,
        .catalog-choice-body,.summary-main,.summary-stat,.tree-panel,.detail-panel,
        .tree-root,.tree-node,.detail-tabs,.detail-tab,.section-head,.modal-head,.modal-card,.modal-contenido,
        .modal-encabezado,dialog,.dialog-head,.dialog-body,.drawer-head,.drawer-section,
        .drawer-foot,.solution-comment,.chart-row,.ring-wrap,.legend span,.trend-value,
        .donut-item,.rating-donut,.related-row,.summarybar,.sla-panel,.panel-encabezado,
        .summary-card,.acciones-desplegable,.fila-edicion,.parameters-section,.parameter,
        .service-link
    ){
        border-color:var(--mesa-theme-border)!important;
        color:var(--mesa-theme-text)!important;
        background:var(--mesa-theme-surface)!important;
    }
    body.mesa-php-shell :is(
        .case-dialog,.case-modal-content,.modal-summary,.modal-content,.filter-bar,
        .service-filter,.detail-drawer,.solution-comment-list,.card-head,
        .case-chat-stage-options,.chat-stage-options,.panel-etiqueta,.tiempo-sla,
        .servicios-conteo
    ){
        border-color:var(--mesa-theme-border)!important;
        color:var(--mesa-theme-text)!important;
        background:var(--mesa-theme-soft)!important;
    }
    body.mesa-php-shell :is(
        .modal-header,.modal-body,.modal-cuerpo,.dialog-body,.drawer-body,.card-body,
        .case-panel-toggle-body,.tree-content,.detail-content
    ){
        border-color:var(--mesa-theme-border)!important;
        color:var(--mesa-theme-text)!important;
        background:var(--mesa-theme-surface)!important;
    }
    body.mesa-php-shell :is(
        .case-chat-picker summary,.chat-stage-picker summary,.acciones-menu summary,
        .detail-filter,.conversation-message,.history-event,.node-file,.detail-card,
        .derivation-row,.action-detail p
    ){
        border-color:var(--mesa-theme-border)!important;
        color:var(--mesa-theme-text)!important;
        background:color-mix(in srgb,var(--mesa-theme-surface) 72%,var(--mesa-theme-soft))!important;
    }
    body.mesa-php-shell .catalog-choice input:checked+.catalog-choice-body,
    body.mesa-php-shell :is(
        .case-branch.selected>summary,.case-leaf.selected .case-row,
        .tree-node.selected,.tree-root.selected,.service-link.active,
        .metric-row:hover,.metric-row:focus
    ){
        border-color:var(--mesa-shell-color)!important;
        background:color-mix(in srgb,var(--mesa-shell-color) 16%,var(--mesa-theme-surface))!important;
        box-shadow:0 0 0 2px color-mix(in srgb,var(--mesa-shell-color) 25%,transparent)!important;
    }
    body.mesa-php-shell .selectable-row:focus td{
        background:color-mix(in srgb,var(--mesa-shell-color) 16%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell :is(.trend-chart,.trend-grid){
        border-color:var(--mesa-theme-border)!important;
        background:repeating-linear-gradient(
            to top,
            var(--mesa-theme-surface) 0,
            var(--mesa-theme-surface) 35px,
            var(--mesa-theme-border) 36px
        )!important;
    }
    body.mesa-php-shell .trend-grid{
        background:repeating-linear-gradient(
            to bottom,
            transparent 0,
            transparent calc(33.333% - 1px),
            var(--mesa-theme-border) calc(33.333% - 1px),
            var(--mesa-theme-border) 33.333%
        )!important;
    }
    body.mesa-php-shell .donut:after,
    body.mesa-php-shell .ring::after,
    body.mesa-php-shell .donut-chart::before{
        border-color:var(--mesa-theme-border)!important;
        background:var(--mesa-theme-surface)!important;
    }
    body.mesa-php-shell :is(
        .case-row-main,.case-row-meta,.case-row-meta b,.case-title,.case-service,
        .summary-main strong,.summary-stat strong,.stage-data strong,.catalog-choice strong,
        .chart-label,.chart-meta strong,.donut-label,.donut-value,.metric-name,.metric-score strong,
        .solution-comment p,.related-row,.modal-title h2,.detail-head-copy h3,
        .node-copy strong,.conversation-message strong,.history-event strong,
        .conversation-message p,.history-event p
    ){color:var(--mesa-theme-heading)!important}
    body.mesa-php-shell :is(
        .summary-main span,.summary-stat span,.stage-data span,.chart-meta small,
        .donut-value small,.solution-comment small,.solution-comment-head time,
        .related-row span,.modal-title p,.detail-head-copy span,.notice,
        .node-copy span,.event-meta
    ){color:var(--mesa-theme-muted)!important}

    /* Dashboard del gestor: elimina los azules oscuros heredados por el módulo.
       La información principal queda clara y el color se reserva para las gráficas. */
    body.mesa-php-shell :is(
        .kpi-title,.legend-row,.solution-head,.trend-label,
        .performance td,.performance td strong
    ){color:var(--mesa-theme-text)!important}
    body.mesa-php-shell :is(
        .brand small,.filter-heading p,.kpi small,.chart-title span,.section-head p,.donut-center small,
        .empty-note,.trend-legend,.metric-na,.performance .table-note
    ){
        color:var(--mesa-theme-muted)!important;
        opacity:1!important;
    }
    body.mesa-php-shell :is(
        .legend-row strong,.solution-head strong,.donut-center strong,
        .performance th,.trend-card h3,.performance h3
    ){color:var(--mesa-theme-heading)!important}
    body.mesa-php-shell :is(.section-pill,.period-tag){
        border-color:var(--mesa-shell-color)!important;
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell :is(.solution-track,.meter){
        background:color-mix(in srgb,var(--mesa-theme-heading) 16%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell .trend-chart :is(.trend-label,.trend-month){
        color:var(--mesa-theme-muted)!important;
        opacity:1!important;
    }
    body.mesa-php-shell :is(.trend-legend,.legend-row) i{opacity:1!important}
    body.mesa-php-shell :is(.modal-close,.close,.cerrar,.drawer-close,.clear-filter,.card-toggle,.table-mode-toggle,.view-controls button){
        border-color:var(--mesa-theme-border)!important;
        color:var(--mesa-shell-color)!important;
        background:var(--mesa-theme-soft)!important;
    }
    /* Jerarquía de acciones: evita botones blancos y textos del mismo color
       que el fondo, especialmente en Verde esmeralda y temas oscuros. */
    body.mesa-php-shell .button:not(.soft):not(.secondary):not(.danger),
    body.mesa-php-shell :is(.btn-primary,.btn-crear,.btn-guardar,.btn-nuevo,.btn-actualizar){
        border-color:var(--mesa-shell-color)!important;
        color:var(--mesa-theme-on-primary)!important;
        background:linear-gradient(145deg,var(--mesa-shell-color),color-mix(in srgb,var(--mesa-shell-color) 72%,var(--mesa-theme-accent)))!important;
    }
    body.mesa-php-shell :is(
        .button.soft,.button.secondary,.btn-soft,.btn-volver,.btn.light,.btn.outline,
        .btn.email-toggle,.view-link,.seleccion-chip,.clear-filter
    ){
        border-color:color-mix(in srgb,var(--mesa-shell-color) 52%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-heading)!important;
        background:color-mix(in srgb,var(--mesa-shell-color) 11%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell :is(
        .button.soft,.button.secondary,.btn-soft,.btn-volver,.btn.light,.btn.outline,
        .btn.email-toggle,.view-link,.seleccion-chip,.clear-filter
    ):hover{
        border-color:var(--mesa-shell-color)!important;
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell .row-actions .button.secondary,
    body.mesa-php-shell .accion-editar{
        border-color:var(--mesa-shell-color)!important;
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell :is(.btn-warning,.row-actions .button.danger,.accion-estado){
        border-color:#d99b10!important;
        color:var(--mesa-state-on-warning)!important;
        background:var(--mesa-state-warning)!important;
    }
    body.mesa-php-shell :is(.btn-success,.accion-habilitar){
        border-color:var(--mesa-state-success)!important;
        color:var(--mesa-state-on)!important;
        background:var(--mesa-state-success)!important;
    }
    body.mesa-php-shell :is(.btn-danger,.accion-eliminar){
        border-color:var(--mesa-state-danger)!important;
        color:var(--mesa-state-on)!important;
        background:var(--mesa-state-danger)!important;
    }
    body.mesa-php-shell :is(.accion-editar,.accion-estado,.accion-habilitar,.accion-eliminar)::before{
        color:inherit!important;
        background:color-mix(in srgb,#fff 18%,transparent)!important;
    }
    body.mesa-php-shell .acciones-desplegable{display:grid!important;gap:4px!important}
    body.mesa-php-shell .accion-item:hover{filter:brightness(1.08);transform:translateY(-1px)}

    /* Estados semánticos sólidos y legibles en cualquier paleta. */
    body.mesa-php-shell :is(
        .badge.active,.status.activo,.estado-servicio.activo,.period-badge.vigente,
        .email-preference-status,.case-status.completada
    ){
        border-color:var(--mesa-state-success)!important;
        color:var(--mesa-state-on)!important;
        background:var(--mesa-state-success)!important;
    }
    body.mesa-php-shell :is(
        .badge.off,.status.inhabilitado,.estado-servicio.inhabilitado,
        .period-badge.proxima,.email-preference-status.off
    ){
        border-color:#d99b10!important;
        color:var(--mesa-state-on-warning)!important;
        background:var(--mesa-state-warning)!important;
    }
    body.mesa-php-shell :is(.period-badge.finalizada,.badge.closed){
        border-color:var(--mesa-theme-border)!important;
        color:var(--mesa-theme-heading)!important;
        background:var(--mesa-theme-soft)!important;
    }
    body.mesa-php-shell :is(.service-classification,.sla-info,.attention-tag,.type-badge){
        border-color:color-mix(in srgb,var(--mesa-shell-color) 38%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-heading)!important;
        background:color-mix(in srgb,var(--mesa-shell-color) 13%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell .service-classification.incidente{
        border-color:color-mix(in srgb,var(--mesa-state-danger) 55%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-heading)!important;
        background:color-mix(in srgb,var(--mesa-state-danger) 16%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell :is(.alert.ok,.alert.exito,.alerta.exito){
        border-color:color-mix(in srgb,var(--mesa-state-success) 52%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-heading)!important;
        background:color-mix(in srgb,var(--mesa-state-success) 14%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell :is(.alert.aviso,.alerta.aviso){
        border-color:color-mix(in srgb,var(--mesa-state-warning) 60%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-heading)!important;
        background:color-mix(in srgb,var(--mesa-state-warning) 15%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell :is(.alert.error,.alerta.error){
        border-color:color-mix(in srgb,var(--mesa-state-danger) 55%,var(--mesa-theme-border))!important;
        color:var(--mesa-theme-heading)!important;
        background:color-mix(in srgb,var(--mesa-state-danger) 14%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell .closed-case{
        border:1px solid color-mix(in srgb,var(--mesa-state-success) 58%,var(--mesa-theme-border))!important;
        border-left:5px solid var(--mesa-state-success)!important;
        color:var(--mesa-theme-text)!important;
        background:color-mix(in srgb,var(--mesa-state-success) 8%,var(--mesa-theme-surface))!important;
    }
    body.mesa-php-shell .closed-case :is(strong,summary){color:var(--mesa-theme-heading)!important}
    body.mesa-php-shell .closed-case p{color:var(--mesa-theme-text)!important}

    /* Textos y ventanas fluidos: las descripciones largas ya no amplían el modal. */
    body.mesa-php-shell :is(
        .manager-ticket-modal-window,.manager-ticket-modal-scroll,.ticket-summary,
        .ticket-summary>div,.case-modal-content,.case-dialog,.card,.panel,.modal-content,
        .modal-card,.modal-contenido,.description,.closed-case,.action-detail p
    ){min-width:0!important;max-width:100%!important}
    body.mesa-php-shell :is(
        .description,.closed-case p,.ticket-summary h2,.ticket-summary p,
        .action-detail p,.audit-item p,.conversation-message p,.history-event p,
        td,th,.service-classification,.attention-tag,.sla-info
    ){
        overflow-wrap:anywhere!important;
        word-break:break-word!important;
    }
    body.mesa-php-shell :is(.manager-ticket-modal-window,.manager-ticket-modal-scroll,.case-modal-content){overflow-x:hidden!important}
    body.mesa-php-shell :is(.service-filter button[type="submit"],.form-actions button[type="submit"],.modal-actions button[type="submit"],.row-actions button.primary){
        border-color:var(--mesa-shell-color)!important;
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell .detail-tab.active{
        border-color:var(--mesa-shell-color)!important;
        color:var(--mesa-theme-on-primary)!important;
        background:var(--mesa-shell-color)!important;
    }
    body.mesa-php-shell .detail-hero{
        border-color:var(--mesa-shell-color)!important;
        color:var(--mesa-theme-on-primary)!important;
        background:linear-gradient(135deg,var(--mesa-shell-dark),var(--mesa-shell-color))!important;
    }
    body.mesa-php-shell dialog::backdrop{background:color-mix(in srgb,var(--mesa-shell-dark) 72%,transparent)!important}
    @media(max-width:900px){
        body.mesa-php-shell :is(.service-selection-layout,.request-support-grid,.details-grid,.charts){grid-template-columns:minmax(0,1fr)!important}
        body.mesa-php-shell .charts .chart-card:last-child{grid-column:auto!important}
        body.mesa-php-shell .dashboard{grid-template-columns:minmax(0,1fr)!important}
        body.mesa-php-shell .dashboard>*{grid-column:1!important;min-width:0}
        body.mesa-php-shell :is(.kpis,.grid-stats,.detail-grid){grid-template-columns:repeat(2,minmax(0,1fr))!important}
        body.mesa-php-shell :is(.manager-case-filters,.case-filters){grid-template-columns:minmax(0,1fr)!important}
        body.mesa-php-shell :is(.manager-filter-field,.case-filter-field,.manager-search-control,.case-search-control){min-width:0!important}
        body.mesa-php-shell :is(.manager-filter-count,.case-filter-count){min-width:0!important;padding:0 2px!important;text-align:left!important}
        body.mesa-php-shell :is(.filter,.filters,.topbar,.actions){max-width:100%;flex-wrap:wrap}
        body.mesa-php-shell :is(.filter,.filters) :is(.field,input,select,textarea){min-width:0;max-width:100%}
        body.mesa-php-shell .metric-row{grid-template-columns:minmax(0,.9fr) minmax(0,1fr) auto!important}
        body.mesa-php-shell :is(.table-wrap,.card,.panel){max-width:100%;min-width:0}
        body.mesa-php-shell :is(.top,.top-actions,.barra-acciones,.row-actions,.modal-actions){max-width:100%;flex-wrap:wrap!important}
        body.mesa-php-shell :is(.top-actions,.barra-acciones) > :is(a,button){flex:1 1 140px;min-width:0}
        body.mesa-php-shell :is(.table-wrap,.tabla-contenedor){max-width:100%;overflow-x:auto!important;overscroll-behavior-inline:contain}
        body.mesa-php-shell .catalogos{grid-template-columns:repeat(auto-fit,minmax(135px,1fr))!important}
    }
    @media(max-width:700px){:root{--mesa-shell-width:min(86vw,285px)}body.mesa-php-shell{padding:62px 0 18px!important}#mesa-php-sidebar{visibility:hidden;pointer-events:none;transform:translateX(-105%)}body.mesa-php-mobile-open #mesa-php-sidebar{visibility:visible;pointer-events:auto;transform:translateX(0)}#mesa-php-topbar{left:0;height:56px;padding:0 8px 0 56px}#mesa-php-topbar>strong,.mesa-php-operation{display:none}.mesa-php-top-right{margin-left:auto;gap:5px}.mesa-php-notification-panel{position:fixed;top:61px;right:7px;left:7px;width:auto}.mesa-php-login-notice{top:66px;right:8px;width:calc(100vw - 16px)}.mesa-php-user-copy{display:none}.mesa-php-user-copy strong{max-width:105px}#mesa-php-floating{display:block}body.mesa-php-mobile-open #mesa-php-floating{display:none}.mesa-php-profile-card,.mesa-php-appearance-card{width:100%;border-radius:13px}.mesa-php-profile-body{height:calc(100vh - 88px)}#mesa-php-appearance-modal{padding:6px}.mesa-php-appearance-head{min-height:58px;padding:8px 11px}.mesa-php-appearance-head strong{font-size:15px}.mesa-php-appearance-head small{font-size:10px}.mesa-php-appearance-body{height:calc(100dvh - 70px)}body.mesa-php-shell .email-preference{align-items:stretch!important;flex-direction:column!important}body.mesa-php-shell .email-preference form,body.mesa-php-shell .email-preference .email-toggle{width:100%!important}body.mesa-php-shell :is(.manager-ticket-modal,.case-modal){padding:6px!important}body.mesa-php-shell :is(.manager-ticket-modal-window,.case-modal-content){width:100%!important;height:calc(100dvh - 12px)!important;max-height:calc(100dvh - 12px)!important;border-radius:12px!important}body.mesa-php-shell .manager-ticket-modal-close-label{display:none}body.mesa-php-shell .catalogos{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
</style>
<aside id="mesa-php-sidebar" aria-label="Navegación principal">
    <div class="mesa-php-brand">
        <span class="mesa-php-flag"><?= mesaCuentaEscapar($mesaCodigoPais) ?></span>
        <span class="mesa-php-brand-copy"><strong>Mesa de Servicio</strong><small>Portal corporativo <?= mesaCuentaEscapar($mesaNombrePais) ?></small></span>
        <button class="mesa-php-toggle" type="button" data-mesa-sidebar-toggle aria-label="Cerrar panel izquierdo" title="Cerrar panel izquierdo">‹</button>
    </div>
    <p class="mesa-php-caption"><?= $mesaRol === 1 ? 'Administración nacional' : 'Navegación' ?></p>
    <nav class="mesa-php-nav">
        <?php foreach ($mesaNavegacion as [$mesaUrl, $mesaCodigo, $mesaTitulo]): ?>
            <?php $mesaActiva = mesaCuentaRutaActiva($mesaUrl, $mesaArchivoActual, $mesaConsultaActual); ?>
            <a class="<?= $mesaActiva ? 'active' : '' ?>" href="<?= mesaCuentaEscapar($mesaUrl) ?>"<?= $mesaActiva ? ' aria-current="page"' : '' ?>>
                <span class="mesa-php-nav-code"><?= mesaCuentaEscapar($mesaCodigo) ?></span><span><?= mesaCuentaEscapar($mesaTitulo) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
<header id="mesa-php-topbar">
    <strong><?= $mesaRol === 1 ? 'Centro de administración nacional' : 'Mesa de Servicio · ' . mesaCuentaEscapar($mesaNombrePais) ?></strong>
    <div class="mesa-php-top-right">
        <span class="mesa-php-operation">Operación disponible</span>
        <div class="mesa-php-notifications" id="mesa-php-notifications">
            <button
                class="mesa-php-bell"
                type="button"
                data-mesa-notifications-toggle
                aria-label="Abrir notificaciones<?= $mesaNotificacionesNoLeidas > 0 ? ': ' . $mesaNotificacionesNoLeidas . ' sin leer' : '' ?>"
                aria-controls="mesa-php-notification-panel"
                aria-expanded="false"
                title="Notificaciones"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                <?php if ($mesaNotificacionesNoLeidas > 0): ?>
                    <span class="mesa-php-bell-badge" aria-hidden="true"><?= $mesaNotificacionesNoLeidas > 99 ? '99+' : $mesaNotificacionesNoLeidas ?></span>
                <?php endif; ?>
            </button>
            <section
                class="mesa-php-notification-panel"
                id="mesa-php-notification-panel"
                aria-label="Notificaciones de casos"
                hidden
            >
                <header class="mesa-php-notification-head">
                    <span><strong>Notificaciones</strong><small>Novedades de sus casos y tickets</small></span>
                    <?php if ($mesaNotificacionesNoLeidas > 0): ?>
                        <span class="mesa-php-notification-head-actions">
                            <span class="mesa-php-notification-total"><?= $mesaNotificacionesNoLeidas ?> nueva<?= $mesaNotificacionesNoLeidas === 1 ? '' : 's' ?></span>
                            <button
                                class="mesa-php-mark-all"
                                type="button"
                                data-mesa-mark-all
                                data-csrf="<?= mesaCuentaEscapar($_SESSION['csrf_token'] ?? '') ?>"
                            >Marcar todo como leído</button>
                        </span>
                    <?php endif; ?>
                </header>
                <div class="mesa-php-notification-list">
                    <?php if ($mesaNotificaciones): ?>
                        <?php foreach ($mesaNotificaciones as $mesaNotificacion): ?>
                            <?php
                            $mesaIdNotificacion = (int) (
                                $mesaNotificacion['id_notificacion'] ?? 0
                            );
                            $mesaEsNoLeida = (int) (
                                $mesaNotificacion['leida'] ?? 0
                            ) === 0;
                            ?>
                            <a
                                class="mesa-php-notification-item <?= $mesaEsNoLeida ? 'unread' : '' ?>"
                                href="abrirNotificacion.php?id=<?= $mesaIdNotificacion ?>"
                            >
                                <span class="mesa-php-notification-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 7h8M8 11h5M6 3h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-7l-4 3v-3H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg></span>
                                <span class="mesa-php-notification-copy">
                                    <strong><?= mesaCuentaEscapar($mesaNotificacion['titulo'] ?? 'Nueva actividad') ?></strong>
                                    <span><?= mesaCuentaEscapar($mesaNotificacion['mensaje'] ?? '') ?></span>
                                    <time datetime="<?= mesaCuentaEscapar($mesaNotificacion['creada_en'] ?? '') ?>"><?= mesaCuentaEscapar(mesaCuentaTiempoNotificacion($mesaNotificacion['creada_en'] ?? null)) ?></time>
                                </span>
                                <?= $mesaEsNoLeida ? '<i class="mesa-php-notification-dot" aria-label="Sin leer"></i>' : '' ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="mesa-php-notification-empty">
                            <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><strong>Todo está al día</strong><span>No tiene novedades pendientes.</span></span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <details class="mesa-php-account" id="mesa-php-account">
            <summary aria-label="Abrir opciones de la cuenta">
                <img data-mesa-profile-image src="imagenPerfil.php" alt="Imagen de perfil">
                <span class="mesa-php-user-copy"><strong><?= mesaCuentaEscapar($mesaUsuario) ?></strong><small><?= mesaCuentaEscapar($mesaCargo) ?></small></span>
                <span class="mesa-php-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="mesa-php-account-menu" role="menu">
                <button type="button" role="menuitem" data-mesa-open-profile><span class="mesa-php-menu-icon">PF</span><span><strong>Administrar perfil</strong><small>Datos, imagen y contraseña</small></span></button>
                <button type="button" role="menuitem" data-mesa-open-appearance><span class="mesa-php-menu-icon">AP</span><span><strong>Cambiar apariencia</strong><small>Colores privados de su interfaz</small></span></button>
                <?php if ($mesaRol === 1): ?>
                    <a role="menuitem" href="seleccionarPais.php"><span class="mesa-php-menu-icon">CP</span><span><strong>Cambiar país</strong><small>Seleccionar Colombia o Perú</small></span></a>
                <?php endif; ?>
                <a role="menuitem" href="logout.php"><span class="mesa-php-menu-icon logout">↪</span><span><strong>Cerrar sesión</strong><small>Salir de forma segura</small></span></a>
            </div>
        </details>
    </div>
</header>
<?php if ($mesaMostrarAlertaNovedades): ?>
    <aside class="mesa-php-login-notice" id="mesa-php-login-notice" role="status" aria-live="polite">
        <span class="mesa-php-login-notice-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg></span>
        <span class="mesa-php-login-notice-copy"><strong>Tiene novedades en sus casos</strong><span>Hay <?= $mesaNotificacionesNoLeidas ?> notificación<?= $mesaNotificacionesNoLeidas === 1 ? '' : 'es' ?> sin leer. Pulse la campanita para consultar el detalle.</span><button type="button" data-mesa-open-notifications>Ver notificaciones</button></span>
        <button class="mesa-php-login-notice-close" type="button" data-mesa-close-login-notice aria-label="Cerrar alerta">×</button>
    </aside>
<?php endif; ?>
<div id="mesa-php-floating"><button class="mesa-php-floating-button" type="button" data-mesa-sidebar-toggle aria-label="Abrir panel izquierdo" title="Abrir panel izquierdo">☰</button></div>
<div id="mesa-php-country" role="status"><?= mesaCuentaEscapar($mesaCodigoPais . ' · ' . $mesaNombrePais) ?></div>
<div id="mesa-php-profile-modal" role="dialog" aria-modal="true" aria-labelledby="mesa-php-profile-title" hidden>
    <section class="mesa-php-profile-card">
        <header class="mesa-php-profile-head"><div><strong id="mesa-php-profile-title">Administrar perfil</strong><small>Información personal y seguridad</small></div><button class="mesa-php-profile-close" type="button" data-mesa-close-profile aria-label="Cerrar ventana">×</button></header>
        <div class="mesa-php-profile-body"><div class="mesa-php-profile-loader">Cargando perfil…</div><iframe id="mesa-php-profile-frame" title="Administrar perfil"></iframe></div>
    </section>
</div>
<div id="mesa-php-appearance-modal" role="dialog" aria-modal="true" aria-labelledby="mesa-php-appearance-title" hidden>
    <section class="mesa-php-appearance-card">
        <header class="mesa-php-appearance-head"><div><strong id="mesa-php-appearance-title">Cambiar apariencia</strong><small>Elija los colores privados de su interfaz</small></div><button class="mesa-php-appearance-close" type="button" data-mesa-close-appearance aria-label="Cerrar ventana de apariencia">×</button></header>
        <div class="mesa-php-appearance-body"><div class="mesa-php-appearance-loader">Cargando apariencias…</div><iframe id="mesa-php-appearance-frame" title="Cambiar apariencia"></iframe></div>
    </section>
</div>
<script id="mesa-php-shell-script">
(function(){
    'use strict';
    window.__MESA_PHP_SHELL_VERSION__='2026-08-14.10';
    var body=document.body;
    var modal=document.getElementById('mesa-php-profile-modal');
    var frame=document.getElementById('mesa-php-profile-frame');
    var appearanceModal=document.getElementById('mesa-php-appearance-modal');
    var appearanceFrame=document.getElementById('mesa-php-appearance-frame');
    var account=document.getElementById('mesa-php-account');
    var notifications=document.getElementById('mesa-php-notifications');
    var notificationToggle=notifications?.querySelector('[data-mesa-notifications-toggle]');
    var notificationPanel=document.getElementById('mesa-php-notification-panel');
    var markAllNotifications=notifications?.querySelector('[data-mesa-mark-all]');
    var loginNotice=document.getElementById('mesa-php-login-notice');
    var sidebar=document.getElementById('mesa-php-sidebar');
    body.classList.add('mesa-php-shell');
    body.dataset.mesaTheme=<?= json_encode($mesaTemaClave, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    body.dataset.mesaScheme=<?= json_encode($mesaPaleta['scheme'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    function desktop(){return window.innerWidth>700}
    function saved(){try{return localStorage.getItem('mesa_php_sidebar_cerrada')==='1'}catch(e){return false}}
    function syncSidebarAccessibility(){
        var closed=desktop()?body.classList.contains('mesa-php-sidebar-collapsed'):!body.classList.contains('mesa-php-mobile-open');
        sidebar.toggleAttribute('inert',closed);
        sidebar.setAttribute('aria-hidden',closed?'true':'false');
    }
    function setSidebar(closed,store){
        if(desktop()){
            body.classList.toggle('mesa-php-sidebar-collapsed',closed);
            body.classList.remove('mesa-php-mobile-open');
            if(store){try{localStorage.setItem('mesa_php_sidebar_cerrada',closed?'1':'0')}catch(e){}}
        }else{
            body.classList.toggle('mesa-php-mobile-open',!body.classList.contains('mesa-php-mobile-open'));
        }
        syncSidebarAccessibility();
    }
    function setNotifications(open){
        if(!notificationToggle||!notificationPanel)return;
        notificationPanel.hidden=!open;
        notificationToggle.setAttribute('aria-expanded',open?'true':'false');
        if(open){
            account.open=false;
            loginNotice?.setAttribute('hidden','');
            notificationPanel.querySelector('a,button')?.focus({preventScroll:true});
        }
    }
    document.querySelectorAll('[data-mesa-sidebar-toggle]').forEach(function(button){button.addEventListener('click',function(){setSidebar(desktop()?!body.classList.contains('mesa-php-sidebar-collapsed'):false,true)})});
    notificationToggle?.addEventListener('click',function(){
        setNotifications(notificationPanel?.hidden===true);
    });
    document.querySelector('[data-mesa-open-notifications]')?.addEventListener('click',function(event){
        event.stopPropagation();
        setNotifications(true);
    });
    document.querySelector('[data-mesa-close-login-notice]')?.addEventListener('click',function(){
        loginNotice?.setAttribute('hidden','');
    });
    markAllNotifications?.addEventListener('click',async function(){
        if(markAllNotifications.disabled)return;
        var originalText=markAllNotifications.textContent;
        var csrf=markAllNotifications.dataset.csrf||'';
        markAllNotifications.disabled=true;
        markAllNotifications.textContent='Marcando…';

        try{
            var data=new FormData();
            data.append('accion','marcar_todas');
            data.append('csrf_token',csrf);
            var response=await fetch('abrirNotificacion.php',{
                method:'POST',
                body:data,
                headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
                credentials:'same-origin'
            });
            var result=await response.json().catch(function(){return{}});
            if(!response.ok||result.ok!==true)throw new Error(result.message||'No fue posible actualizar las notificaciones.');

            notifications.querySelector('.mesa-php-bell-badge')?.remove();
            notificationPanel.querySelectorAll('.mesa-php-notification-item.unread').forEach(function(item){
                item.classList.remove('unread');
                item.querySelector('.mesa-php-notification-dot')?.remove();
            });
            notificationPanel.querySelector('.mesa-php-notification-head-actions')?.remove();
            notificationToggle.setAttribute('aria-label','Abrir notificaciones');
            loginNotice?.setAttribute('hidden','');
        }catch(error){
            markAllNotifications.disabled=false;
            markAllNotifications.textContent=error instanceof Error?'Intentar de nuevo':originalText;
            markAllNotifications.title=error instanceof Error?error.message:'';
        }
    });
    function openProfile(){
        closeAppearance();
        account.open=false;modal.hidden=false;modal.classList.remove('mesa-php-profile-loaded');body.style.overflow='hidden';
        frame.src='perfil.php?modal=1&embed=1&v=2026-08-14.10';
    }
    function openAppearance(){
        closeProfile();
        account.open=false;appearanceModal.hidden=false;appearanceModal.classList.remove('mesa-php-appearance-loaded');body.style.overflow='hidden';
        appearanceFrame.src='apariencia.php?modal=1&embed=1&v=2026-08-14.10';
    }
    document.querySelector('[data-mesa-open-profile]')?.addEventListener('click',openProfile);
    document.querySelector('[data-mesa-open-appearance]')?.addEventListener('click',openAppearance);
    function closeProfile(){modal.hidden=true;modal.classList.remove('mesa-php-profile-loaded');frame.src='about:blank';if(appearanceModal.hidden)body.style.overflow=''}
    function closeAppearance(){appearanceModal.hidden=true;appearanceModal.classList.remove('mesa-php-appearance-loaded');appearanceFrame.src='about:blank';if(modal.hidden)body.style.overflow=''}
    document.querySelector('[data-mesa-close-profile]').addEventListener('click',closeProfile);
    document.querySelector('[data-mesa-close-appearance]').addEventListener('click',closeAppearance);
    modal.addEventListener('click',function(event){if(event.target===modal)closeProfile()});
    appearanceModal.addEventListener('click',function(event){if(event.target===appearanceModal)closeAppearance()});
    frame.addEventListener('load',function(){modal.classList.add('mesa-php-profile-loaded')});
    appearanceFrame.addEventListener('load',function(){appearanceModal.classList.add('mesa-php-appearance-loaded')});
    document.addEventListener('keydown',function(event){
        if(event.key!=='Escape')return;
        if(!appearanceModal.hidden){closeAppearance();return}
        if(!modal.hidden){closeProfile();return}
        if(notificationPanel&&!notificationPanel.hidden){setNotifications(false);notificationToggle?.focus();return}
        if(account.open)account.open=false;
    });
    document.addEventListener('click',function(event){
        if(account.open&&!account.contains(event.target))account.open=false;
        if(notifications&&!notifications.contains(event.target)&&notificationPanel&&!notificationPanel.hidden)setNotifications(false);
    });
    account.addEventListener('toggle',function(){if(account.open)setNotifications(false)});
    window.addEventListener('resize',function(){if(desktop()){body.classList.remove('mesa-php-mobile-open');body.classList.toggle('mesa-php-sidebar-collapsed',saved())}syncSidebarAccessibility()});
    window.addEventListener('message',function(event){
        if(event.origin!==location.origin||!event.data)return;
        if(event.data.tipo==='mesa-profile-updated'){
            document.querySelectorAll('[data-mesa-profile-image]').forEach(function(image){image.src='imagenPerfil.php?v='+Date.now()});
        }
        if(event.data.tipo==='mesa-theme-updated'){
            window.location.reload();
        }
    });
    if(desktop())body.classList.toggle('mesa-php-sidebar-collapsed',saved());
    syncSidebarAccessibility();
    if(loginNotice){window.setTimeout(function(){loginNotice?.setAttribute('hidden','')},12000)}
}());
</script>
<script src="assets/js/controlSesion.js?v=2026-08-13.1" defer></script>
