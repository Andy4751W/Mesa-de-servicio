<?php
declare(strict_types=1);

if (defined('MESA_CONTROLES_CUENTA_RENDERIZADOS')) {
    return;
}
define('MESA_CONTROLES_CUENTA_RENDERIZADOS', true);

function mesaCuentaEscapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$mesaRol = (int) ($_SESSION['rol'] ?? 0);
$mesaUsuario = trim((string) ($_SESSION['usuario'] ?? 'Usuario'));
$mesaCodigoPais = strtoupper(trim((string) ($_SESSION['pais_operacion_codigo'] ?? 'CO')));
$mesaNombrePais = trim((string) ($_SESSION['pais_operacion_nombre'] ?? ''));

if (!in_array($mesaRol, [1, 2, 3], true)) {
    return;
}
if (!in_array($mesaCodigoPais, ['CO', 'PE'], true)) {
    $mesaCodigoPais = 'CO';
}
if ($mesaNombrePais === '') {
    $mesaNombrePais = $mesaCodigoPais === 'PE' ? 'Perú' : 'Colombia';
}

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
        ['flujoTicket.php?modo=mis_tickets&bandeja=abiertos', 'AB', 'Casos abiertos'],
        ['flujoTicket.php?modo=mis_tickets&bandeja=cerrados', 'CE', 'Casos cerrados'],
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
        parse_str($consulta, $objetivo);
        parse_str($consultaActual, $actual);
        $bandejaObjetivo = (string) ($objetivo['bandeja'] ?? 'abiertos');
        $bandejaActual = (string) ($actual['bandeja'] ?? 'abiertos');

        return $bandejaObjetivo === $bandejaActual;
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
$mesaColor = $mesaEsPeru ? '#c81e3a' : '#1167d8';
$mesaOscuro = $mesaEsPeru ? '#171f2a' : '#072b58';
?>
<style id="mesa-php-shell-style" data-mesa-php-shell>
    :root{--mesa-shell-color:<?= mesaCuentaEscapar($mesaColor) ?>;--mesa-shell-dark:<?= mesaCuentaEscapar($mesaOscuro) ?>;--mesa-shell-width:252px}
    #gc-global-sidebar,#gc-global-top,#gc-account-menu,#gc-profile-modal,#gc-sidebar-floating,#svc-sidebar,#svc-topbar,#svc-account-menu,#svc-profile-modal,#svc-sidebar-floating,#svc-country-context{display:none!important}
    [data-gc-user-trigger],[data-gc-sidebar-toggle],[data-svc-user-trigger],[data-svc-sidebar-toggle]{display:none!important}
    body.mesa-php-shell{display:block!important;min-height:100vh!important;margin:0!important;padding:72px 0 24px var(--mesa-shell-width)!important;background:#f1f5f9!important;transition:padding-left .18s ease}
    body.mesa-php-shell>.layout{display:block!important;min-height:0!important}
    body.mesa-php-shell>.layout>.sidebar,body.mesa-php-shell>.layout>aside.sidebar,body.mesa-php-shell>.layout>.main>.topbar{display:none!important}
    body.mesa-php-shell>.layout>.main{width:100%!important;min-width:0!important;max-width:none!important}
    #mesa-php-sidebar{position:fixed;inset:0 auto 0 0;z-index:2147482000;width:var(--mesa-shell-width);display:flex;flex-direction:column;padding:18px 16px;color:#fff;background:linear-gradient(165deg,var(--mesa-shell-dark),#07192f 58%,#051323 100%);box-shadow:14px 0 40px rgba(5,25,48,.15);font:12px/1.4 Inter,"Segoe UI",Arial,sans-serif;transition:transform .18s ease}
    .mesa-php-brand{display:flex;align-items:center;gap:10px;padding:2px 7px 18px;border-bottom:1px solid rgba(255,255,255,.1)}
    .mesa-php-flag{width:40px;height:40px;display:grid;flex:0 0 auto;place-items:center;border:1px solid rgba(255,255,255,.25);border-radius:11px;color:#fff;background:<?= $mesaEsPeru ? 'linear-gradient(90deg,#d9102f 0 33%,#fff 33% 67%,#d9102f 67%)' : 'linear-gradient(145deg,#f4cf18 0 48%,#1769cc 48% 72%,#cf2842 72%)' ?>;font-size:9px;font-weight:950}
    .mesa-php-brand-copy{min-width:0}.mesa-php-brand-copy strong,.mesa-php-brand-copy small{display:block}.mesa-php-brand-copy strong{font-size:13px}.mesa-php-brand-copy small{margin-top:1px;color:#9bb9d5;font-size:8px}
    .mesa-php-toggle{width:34px;height:34px;display:grid;flex:0 0 auto;place-items:center;margin-left:auto;border:1px solid rgba(255,255,255,.22);border-radius:9px;color:#fff;background:rgba(255,255,255,.08);cursor:pointer;font-size:17px;font-weight:900}.mesa-php-toggle:hover{background:rgba(255,255,255,.16)}
    .mesa-php-caption{margin:18px 9px 7px;color:#7897b5;font-size:8px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
    .mesa-php-nav{min-height:0;display:grid;gap:4px;overflow-y:auto;padding-right:3px}.mesa-php-nav a{display:grid;grid-template-columns:29px minmax(0,1fr);align-items:center;gap:8px;min-height:39px;padding:8px 10px;border:1px solid transparent;border-radius:10px;color:#c6d8e9;text-decoration:none;font-size:10px;font-weight:760}.mesa-php-nav a:hover{color:#fff;background:rgba(42,168,255,.12)}.mesa-php-nav a.active{border-color:rgba(255,255,255,.16);color:#092d56;background:#fff;box-shadow:0 7px 18px rgba(0,0,0,.15)}
    .mesa-php-nav-code{width:27px;height:25px;display:grid;place-items:center;border-radius:7px;color:#80c9ff;background:rgba(42,168,255,.12);font-size:8px;font-weight:950}.mesa-php-nav a.active .mesa-php-nav-code{color:#fff;background:var(--mesa-shell-color)}
    #mesa-php-topbar{position:fixed;inset:0 0 auto var(--mesa-shell-width);z-index:2147481900;height:64px;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:0 25px;border-bottom:1px solid #dbe7f1;background:rgba(255,255,255,.98);box-shadow:0 5px 20px rgba(20,60,95,.06);font:760 11px/1.3 Inter,"Segoe UI",Arial,sans-serif;transition:left .18s ease}
    #mesa-php-topbar>strong{color:#15263a;font-size:13px}.mesa-php-top-right{display:flex;align-items:center;gap:12px}.mesa-php-operation{display:inline-flex;align-items:center;gap:7px;color:var(--mesa-shell-color);font-size:8px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.mesa-php-operation:before{width:7px;height:7px;border-radius:50%;background:#18a96b;box-shadow:0 0 0 4px rgba(24,169,107,.12);content:""}
    .mesa-php-account{position:relative}.mesa-php-account summary{display:flex;align-items:center;gap:8px;padding:4px 7px;border:1px solid transparent;border-radius:11px;list-style:none;cursor:pointer}.mesa-php-account summary::-webkit-details-marker{display:none}.mesa-php-account[open] summary,.mesa-php-account summary:hover{border-color:#d7e5f1;background:#f2f7fc}.mesa-php-account img{width:36px;height:36px;display:block;border:2px solid #fff;border-radius:11px;object-fit:cover;background:#fff;box-shadow:0 5px 14px rgba(17,103,216,.18)}.mesa-php-user-copy strong,.mesa-php-user-copy small{display:block}.mesa-php-user-copy strong{max-width:180px;overflow:hidden;color:#182d42;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.mesa-php-user-copy small{margin-top:1px;color:#718397;font-size:8px;font-weight:650}.mesa-php-chevron{color:#718397;font-size:14px}
    .mesa-php-account-menu{position:absolute;top:calc(100% + 8px);right:0;width:225px;padding:6px;border:1px solid #dbe5ee;border-radius:13px;background:#fff;box-shadow:0 18px 45px rgba(16,42,67,.2)}.mesa-php-account-menu button,.mesa-php-account-menu a{width:100%;display:grid;grid-template-columns:34px minmax(0,1fr);align-items:center;gap:9px;padding:9px;border:0;border-radius:9px;color:#263f57;background:transparent;text-align:left;text-decoration:none;cursor:pointer;font:12px/1.3 Inter,"Segoe UI",Arial,sans-serif}.mesa-php-account-menu button:hover,.mesa-php-account-menu a:hover{background:#f1f6fb}.mesa-php-menu-icon{width:32px;height:32px;display:grid;place-items:center;border-radius:9px;color:var(--mesa-shell-color);background:#edf5ff;font-size:9px;font-weight:900}.mesa-php-menu-icon.logout{color:#a43a43;background:#fff1f1;font-size:15px}.mesa-php-account-menu strong,.mesa-php-account-menu small{display:block}.mesa-php-account-menu strong{color:#1c3349;font-size:11px}.mesa-php-account-menu small{margin-top:2px;color:#788b9e;font-size:8px}
    #mesa-php-profile-modal{position:fixed;inset:0;z-index:2147483600;display:grid;place-items:center;padding:16px;background:rgba(8,28,48,.55);backdrop-filter:blur(4px)}#mesa-php-profile-modal[hidden]{display:none!important}.mesa-php-profile-card{width:min(760px,95vw);overflow:hidden;border:1px solid #dce6ef;border-radius:17px;background:#fff;box-shadow:0 30px 90px rgba(7,28,48,.32)}.mesa-php-profile-head{height:54px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 16px;border-bottom:1px solid #e3ebf2}.mesa-php-profile-head strong,.mesa-php-profile-head small{display:block}.mesa-php-profile-head strong{color:#152b40;font-size:14px}.mesa-php-profile-head small{margin-top:2px;color:#75899c;font-size:9px}.mesa-php-profile-close{width:33px;height:33px;border:1px solid #d9e4ed;border-radius:9px;color:#536a80;background:#f8fafc;cursor:pointer;font-size:20px}.mesa-php-profile-body{position:relative;height:min(690px,calc(100vh - 104px));background:#eef3f7}.mesa-php-profile-loader{position:absolute;inset:0;display:grid;place-items:center;color:#718397;font-size:11px;font-weight:800}.mesa-php-profile-body iframe{position:relative;width:100%;height:100%;display:block;border:0;background:#eef3f7;opacity:0}.mesa-php-profile-loaded iframe{opacity:1}.mesa-php-profile-loaded .mesa-php-profile-loader{display:none}
    #mesa-php-floating{position:fixed;top:14px;left:14px;z-index:2147483400;display:none}.mesa-php-floating-button{width:36px;height:36px;display:grid;place-items:center;border:1px solid #cbdbea;border-radius:9px;color:var(--mesa-shell-color);background:#fff;box-shadow:0 7px 20px rgba(16,42,67,.16);cursor:pointer;font-size:18px;font-weight:900}
    #mesa-php-country{position:fixed;right:14px;bottom:12px;z-index:2147483000;display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border:1px solid #cbdbea;border-radius:999px;color:var(--mesa-shell-color);background:rgba(255,255,255,.96);box-shadow:0 8px 22px rgba(16,42,67,.13);font:800 11px/1.2 Inter,"Segoe UI",Arial,sans-serif}#mesa-php-country:before{width:8px;height:8px;border-radius:50%;background:var(--mesa-shell-color);content:""}
    body.mesa-php-sidebar-collapsed{padding-left:0!important}body.mesa-php-sidebar-collapsed #mesa-php-sidebar{pointer-events:none;transform:translateX(-105%)}body.mesa-php-sidebar-collapsed #mesa-php-topbar{left:0;padding-left:64px}body.mesa-php-sidebar-collapsed #mesa-php-floating{display:block}
    @media(max-width:700px){:root{--mesa-shell-width:min(86vw,285px)}body.mesa-php-shell{padding:62px 0 18px!important}#mesa-php-sidebar{transform:translateX(-105%)}body.mesa-php-mobile-open #mesa-php-sidebar{transform:translateX(0)}#mesa-php-topbar{left:0;height:56px;padding:0 10px 0 58px}#mesa-php-topbar>strong,.mesa-php-operation{display:none}.mesa-php-top-right{margin-left:auto}.mesa-php-user-copy strong{max-width:105px}#mesa-php-floating{display:block}body.mesa-php-mobile-open #mesa-php-floating{display:none}.mesa-php-profile-card{width:100%;border-radius:13px}.mesa-php-profile-body{height:calc(100vh - 88px)}}
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
        <details class="mesa-php-account" id="mesa-php-account">
            <summary aria-label="Abrir opciones de la cuenta">
                <img data-mesa-profile-image src="imagenPerfil.php" alt="Imagen de perfil">
                <span class="mesa-php-user-copy"><strong><?= mesaCuentaEscapar($mesaUsuario) ?></strong><small><?= mesaCuentaEscapar($mesaCargo) ?></small></span>
                <span class="mesa-php-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="mesa-php-account-menu" role="menu">
                <button type="button" role="menuitem" data-mesa-open-profile><span class="mesa-php-menu-icon">PF</span><span><strong>Administrar perfil</strong><small>Datos, imagen y contraseña</small></span></button>
                <?php if ($mesaRol === 1): ?>
                    <a role="menuitem" href="seleccionarPais.php"><span class="mesa-php-menu-icon">CP</span><span><strong>Cambiar país</strong><small>Seleccionar Colombia o Perú</small></span></a>
                <?php endif; ?>
                <a role="menuitem" href="logout.php"><span class="mesa-php-menu-icon logout">↪</span><span><strong>Cerrar sesión</strong><small>Salir de forma segura</small></span></a>
            </div>
        </details>
    </div>
</header>
<div id="mesa-php-floating"><button class="mesa-php-floating-button" type="button" data-mesa-sidebar-toggle aria-label="Abrir panel izquierdo" title="Abrir panel izquierdo">☰</button></div>
<div id="mesa-php-country" role="status"><?= mesaCuentaEscapar($mesaCodigoPais . ' · ' . $mesaNombrePais) ?></div>
<div id="mesa-php-profile-modal" role="dialog" aria-modal="true" aria-labelledby="mesa-php-profile-title" hidden>
    <section class="mesa-php-profile-card">
        <header class="mesa-php-profile-head"><div><strong id="mesa-php-profile-title">Administrar perfil</strong><small>Información y seguridad de la cuenta</small></div><button class="mesa-php-profile-close" type="button" data-mesa-close-profile aria-label="Cerrar ventana">×</button></header>
        <div class="mesa-php-profile-body"><div class="mesa-php-profile-loader">Cargando perfil…</div><iframe id="mesa-php-profile-frame" title="Administrar perfil"></iframe></div>
    </section>
</div>
<script id="mesa-php-shell-script">
(function(){
    'use strict';
    window.__MESA_PHP_SHELL_VERSION__='2026-08-10.7';
    var body=document.body;
    var modal=document.getElementById('mesa-php-profile-modal');
    var frame=document.getElementById('mesa-php-profile-frame');
    var account=document.getElementById('mesa-php-account');
    body.classList.add('mesa-php-shell');
    function desktop(){return window.innerWidth>700}
    function saved(){try{return localStorage.getItem('mesa_php_sidebar_cerrada')==='1'}catch(e){return false}}
    function setSidebar(closed,store){
        if(desktop()){
            body.classList.toggle('mesa-php-sidebar-collapsed',closed);
            body.classList.remove('mesa-php-mobile-open');
            if(store){try{localStorage.setItem('mesa_php_sidebar_cerrada',closed?'1':'0')}catch(e){}}
        }else{
            body.classList.toggle('mesa-php-mobile-open',!body.classList.contains('mesa-php-mobile-open'));
        }
    }
    document.querySelectorAll('[data-mesa-sidebar-toggle]').forEach(function(button){button.addEventListener('click',function(){setSidebar(desktop()?!body.classList.contains('mesa-php-sidebar-collapsed'):false,true)})});
    document.querySelector('[data-mesa-open-profile]').addEventListener('click',function(){
        account.open=false;modal.hidden=false;modal.classList.remove('mesa-php-profile-loaded');body.style.overflow='hidden';
        frame.src='perfil.php?modal=1&embed=1&v=2026-08-10.7';
    });
    function closeProfile(){modal.hidden=true;modal.classList.remove('mesa-php-profile-loaded');body.style.overflow='';frame.src='about:blank'}
    document.querySelector('[data-mesa-close-profile]').addEventListener('click',closeProfile);
    modal.addEventListener('click',function(event){if(event.target===modal)closeProfile()});
    frame.addEventListener('load',function(){modal.classList.add('mesa-php-profile-loaded')});
    document.addEventListener('keydown',function(event){if(event.key==='Escape'&&!modal.hidden)closeProfile()});
    document.addEventListener('click',function(event){if(account.open&&!account.contains(event.target))account.open=false});
    window.addEventListener('resize',function(){if(desktop()){body.classList.remove('mesa-php-mobile-open');body.classList.toggle('mesa-php-sidebar-collapsed',saved())}});
    window.addEventListener('message',function(event){if(event.origin!==location.origin||!event.data)return;if(event.data.tipo==='mesa-profile-updated'){document.querySelectorAll('[data-mesa-profile-image]').forEach(function(image){image.src='imagenPerfil.php?v='+Date.now()})}});
    if(desktop())body.classList.toggle('mesa-php-sidebar-collapsed',saved());
}());
</script>
<script src="assets/js/controlSesion.js?v=2026-08-10.7" defer></script>
