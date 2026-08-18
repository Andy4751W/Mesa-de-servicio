<?php
declare(strict_types=1);

$nombreAdministrador = htmlspecialchars(
    (string) ($_SESSION['usuario'] ?? 'Administrador'),
    ENT_QUOTES,
    'UTF-8'
);

$gruposColombia = [
    [
        'clase' => 'operacion',
        'etiqueta' => 'Operación diaria',
        'titulo' => 'Gestión y seguimiento',
        'descripcion' => 'Controle usuarios, solicitudes y resultados de la operación colombiana.',
        'items' => [
            ['url' => 'solicitudes.php', 'codigo' => 'TK', 'titulo' => 'Tickets', 'texto' => 'Casos, derivaciones y trazabilidad.'],
            ['url' => 'crearUsuarios.php', 'codigo' => 'US', 'titulo' => 'Usuarios', 'texto' => 'Gestores y solicitantes de Colombia.'],
            ['url' => 'indicadores.php', 'codigo' => 'BI', 'titulo' => 'Indicadores', 'texto' => 'Cumplimiento, tiempos y desempeño.'],
        ],
    ],
    [
        'clase' => 'servicio',
        'etiqueta' => 'Arquitectura del servicio',
        'titulo' => 'Diseño y automatización',
        'descripcion' => 'Organice la oferta, las respuestas y las etapas de atención.',
        'items' => [
            ['url' => 'catalogos.php', 'codigo' => 'CA', 'titulo' => 'Catálogos', 'texto' => 'Áreas y categorías operativas.'],
            ['url' => 'servicios.php', 'codigo' => 'SV', 'titulo' => 'Servicios', 'texto' => 'Oferta, responsables y parámetros.'],
            ['url' => 'configuraciones.php?tipo=prioridad', 'codigo' => 'PR', 'titulo' => 'Prioridades', 'texto' => 'Niveles de atención asignables a los servicios.'],
            ['url' => 'soluciones.php', 'codigo' => 'SO', 'titulo' => 'Soluciones', 'texto' => 'Respuestas definidas por servicio.'],
            ['url' => 'procesos.php', 'codigo' => 'FL', 'titulo' => 'Flujos', 'texto' => 'Etapas, checklist y derivaciones.'],
        ],
    ],
    [
        'clase' => 'cumplimiento',
        'etiqueta' => 'Cumplimiento',
        'titulo' => 'SLA y calendario laboral',
        'descripcion' => 'Administre los compromisos de atención y días no laborables.',
        'items' => [
            ['url' => 'sla.php', 'codigo' => 'SL', 'titulo' => 'Acuerdos SLA', 'texto' => 'Tiempos comprometidos por servicio.'],
            ['url' => 'feriados.php', 'codigo' => 'FE', 'titulo' => 'Festivos', 'texto' => 'Calendario exclusivo de Colombia.'],
        ],
    ],
];

$parametrosColombia = [
    ['tipo' => 'pais', 'codigo' => 'PA', 'nombre' => 'Países y cobertura'],
    ['tipo' => 'departamento', 'codigo' => 'DE', 'nombre' => 'Departamentos'],
    ['tipo' => 'ciudad', 'codigo' => 'CI', 'nombre' => 'Ciudades'],
    ['tipo' => 'prioridad', 'codigo' => 'PR', 'nombre' => 'Prioridades'],
    ['tipo' => 'urgencia', 'codigo' => 'UR', 'nombre' => 'Urgencias'],
    ['tipo' => 'nivel', 'codigo' => 'NV', 'nombre' => 'Niveles'],
    ['tipo' => 'impacto', 'codigo' => 'IM', 'nombre' => 'Impactos'],
    ['tipo' => 'estado', 'codigo' => 'ES', 'nombre' => 'Estados'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administración Colombia | Mesa de Servicio</title>
  <style>
    :root{--co:#1167d8;--co-bright:#2aa8ff;--co-dark:#072b58;--ink:#15263a;--muted:#65758a;--line:#dfe8f2;--surface:#fff;--soft:#f2f7fc;--sidebar:#071c35}*{box-sizing:border-box}html,body{min-height:100%}body{margin:0;color:var(--ink);background:radial-gradient(circle at 80% 0,#e2f3ff 0,transparent 32%),#eef4f9;font:14px/1.5 Inter,"Segoe UI",Arial,sans-serif}.layout{min-height:100vh;display:grid;grid-template-columns:252px minmax(0,1fr)}.sidebar{position:sticky;top:0;height:100vh;display:flex;flex-direction:column;padding:22px 16px;color:#fff;background:linear-gradient(165deg,#092d56 0,#07192f 58%,#051323 100%);box-shadow:16px 0 45px rgba(5,25,48,.14)}.identity{display:flex;align-items:center;gap:12px;padding:2px 8px 22px;border-bottom:1px solid rgba(255,255,255,.1)}.flag{position:relative;width:44px;height:44px;display:grid;flex:0 0 auto;place-items:center;overflow:hidden;border:1px solid rgba(255,255,255,.25);border-radius:13px;background:linear-gradient(145deg,#f4cf18 0 48%,#1769cc 48% 72%,#cf2842 72%);box-shadow:0 10px 24px rgba(0,0,0,.2);color:#fff;font-size:10px;font-weight:950;letter-spacing:.08em;text-shadow:0 1px 3px rgba(0,0,0,.5)}.identity strong{display:block;font-size:14px}.identity small{display:block;margin-top:1px;color:#93b7d9;font-size:9px}.nav-title{margin:22px 9px 8px;color:#6f94b8;font-size:9px;font-weight:900;letter-spacing:.15em;text-transform:uppercase}.nav{display:grid;gap:5px}.nav a{display:grid;grid-template-columns:29px minmax(0,1fr);align-items:center;gap:8px;min-height:40px;padding:8px 10px;border:1px solid transparent;border-radius:11px;color:#c6d8e9;text-decoration:none;font-size:11px;font-weight:760;transition:.18s ease}.nav a:hover{color:#fff;background:rgba(42,168,255,.12);transform:translateX(2px)}.nav a.active{border-color:rgba(255,255,255,.12);color:#092d56;background:linear-gradient(135deg,#fff,#eaf6ff);box-shadow:0 8px 22px rgba(0,0,0,.16)}.nav-code{width:27px;height:25px;display:grid;place-items:center;border-radius:7px;color:#7fc8ff;background:rgba(42,168,255,.12);font-size:9px;font-weight:950}.nav a.active .nav-code{color:#fff;background:linear-gradient(135deg,var(--co),var(--co-bright))}.sidebar-foot{display:grid;gap:7px;margin-top:auto}.sidebar-foot a{padding:9px 11px;border:1px solid rgba(255,255,255,.12);border-radius:9px;color:#d6e5f3;text-decoration:none;font-size:10px;font-weight:760}.sidebar-foot a:hover{border-color:#4bb8ff;background:rgba(42,168,255,.1)}.main{min-width:0}.topbar{height:72px;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:0 32px;border-bottom:1px solid rgba(207,221,234,.9);background:rgba(255,255,255,.88);box-shadow:0 5px 22px rgba(20,60,95,.04);backdrop-filter:blur(14px)}.crumb{color:var(--muted);font-size:10px}.topbar strong{color:var(--ink)}.operation-tag{display:inline-flex;align-items:center;gap:7px;margin-right:18px;padding:6px 10px;border:1px solid #cfe4f7;border-radius:999px;color:#0b579c;background:#edf8ff;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.operation-tag::before{width:7px;height:7px;border-radius:50%;background:#18a96b;box-shadow:0 0 0 4px rgba(24,169,107,.12);content:""}.user{display:flex;align-items:center;gap:10px}.avatar{width:36px;height:36px;display:grid;place-items:center;border-radius:12px;color:#fff;background:linear-gradient(145deg,var(--co-dark),var(--co-bright));box-shadow:0 7px 17px rgba(17,103,216,.22);font-size:10px;font-weight:950}.content{width:min(1320px,100%);margin:auto;padding:28px 32px 48px}.hero{position:relative;display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);overflow:hidden;border:1px solid rgba(255,255,255,.5);border-radius:22px;color:#fff;background:linear-gradient(120deg,#062852 0,#0c5cac 58%,#159ee5 100%);box-shadow:0 22px 55px rgba(8,61,111,.22)}.hero::before,.hero::after{position:absolute;border:1px solid rgba(255,255,255,.16);border-radius:50%;content:""}.hero::before{width:260px;height:260px;right:25%;top:-185px}.hero::after{width:170px;height:170px;right:-55px;bottom:-100px}.hero-copy{position:relative;z-index:1;padding:34px}.eyebrow{margin:0 0 8px;color:#9cddff;font-size:9px;font-weight:900;letter-spacing:.15em;text-transform:uppercase}.hero h1{max-width:720px;margin:0;font-size:31px;line-height:1.13;letter-spacing:-.03em}.hero p{max-width:700px;margin:11px 0 0;color:#d6edff}.quick{position:relative;z-index:1;display:grid;align-content:center;gap:9px;padding:24px;background:linear-gradient(180deg,rgba(0,27,58,.12),rgba(0,27,58,.35));backdrop-filter:blur(5px)}.quick-label{margin:0 0 2px;color:#a9ddfb;font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.quick a{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid rgba(255,255,255,.22);border-radius:11px;color:#fff;background:rgba(255,255,255,.09);text-decoration:none;font-size:11px;font-weight:850;transition:.18s ease}.quick a:hover{border-color:#fff;background:rgba(255,255,255,.17);transform:translateY(-1px)}.workspace{display:grid;grid-template-columns:1.08fr .92fr;gap:16px;margin-top:20px}.hub{overflow:hidden;border:1px solid var(--line);border-radius:18px;background:rgba(255,255,255,.94);box-shadow:0 12px 34px rgba(20,60,95,.07)}.hub.operacion{grid-row:span 2}.hub-head{position:relative;padding:21px 21px 16px;border-bottom:1px solid #edf2f7}.hub-head::before{position:absolute;inset:0 auto 0 0;width:4px;background:linear-gradient(var(--co),var(--co-bright));content:""}.hub.servicio .hub-head::before{background:linear-gradient(#7047eb,#b463f4)}.hub.cumplimiento .hub-head::before{background:linear-gradient(#08a078,#42ca9b)}.hub-label{color:var(--co);font-size:9px;font-weight:950;letter-spacing:.13em;text-transform:uppercase}.servicio .hub-label{color:#7047eb}.cumplimiento .hub-label{color:#07835f}.hub h2{margin:4px 0 2px;font-size:18px}.hub-head p{margin:0;color:var(--muted);font-size:10px}.hub-items{display:grid;padding:8px}.hub.operacion .hub-items{gap:3px}.hub.servicio .hub-items{grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}.hub.cumplimiento .hub-items{grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}.module{display:grid;grid-template-columns:44px minmax(0,1fr) auto;align-items:center;gap:11px;min-height:67px;padding:10px 11px;border:1px solid transparent;border-radius:12px;color:inherit;text-decoration:none;transition:.18s ease}.module:hover{border-color:#d9e9f6;background:#f4faff;transform:translateY(-1px)}.module-code{width:42px;height:42px;display:grid;place-items:center;border-radius:11px;color:#0f67b3;background:linear-gradient(145deg,#e5f5ff,#d8ecff);font-size:9px;font-weight:950}.servicio .module-code{color:#6b3fce;background:#f1ebff}.cumplimiento .module-code{color:#08775b;background:#e5faf3}.module strong{display:block;font-size:12px}.module small{display:block;margin-top:2px;color:var(--muted);font-size:9px}.module-arrow{color:#6ea7d4;font-size:18px}.parameters-section{margin-top:12px;padding:12px 14px;border:1px solid var(--line);border-radius:14px;background:rgba(255,255,255,.9);box-shadow:0 7px 20px rgba(20,60,95,.05)}.section-heading{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:8px}.section-heading h2{margin:0;font-size:14px}.section-heading p{margin:1px 0 0;color:var(--muted);font-size:9px}.parameters{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px}.parameter{display:flex;align-items:center;gap:7px;min-height:38px;padding:6px 8px;border:1px solid #dde8f2;border-radius:9px;color:var(--ink);background:#fff;text-decoration:none;font-size:9px;font-weight:800;transition:.18s ease}.parameter:hover{border-color:#8dc8f4;box-shadow:0 5px 14px rgba(17,103,216,.08);transform:translateY(-1px)}.parameter span{width:24px;height:24px;display:grid;place-items:center;border-radius:7px;color:#fff;background:linear-gradient(145deg,var(--co),var(--co-bright));font-size:9px;font-weight:950}@media(max-width:1050px){.workspace{grid-template-columns:1fr}.hub.operacion{grid-row:auto}.hub.operacion .hub-items{grid-template-columns:repeat(3,minmax(0,1fr))}.parameters{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:900px){.layout{grid-template-columns:80px minmax(0,1fr)}.sidebar{padding:18px 10px}.identity{justify-content:center}.identity div:last-child,.nav-title,.nav-label,.sidebar-foot span{display:none}.nav a{display:flex;justify-content:center;padding:8px}.nav-code{width:30px}.sidebar-foot a{text-align:center}.hero{grid-template-columns:1fr}.quick{grid-template-columns:repeat(3,1fr)}.quick-label{grid-column:1/-1}.parameters{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.layout{display:block}.sidebar{position:relative;width:100%;height:auto;display:block;padding:10px 12px}.identity{justify-content:flex-start;padding:0 0 10px}.identity div:last-child{display:block}.nav{grid-template-columns:repeat(5,minmax(70px,1fr));overflow-x:auto}.nav a{display:flex;min-width:70px}.nav-label{display:none}.sidebar-foot{display:none}.topbar{height:auto;padding:11px 14px}.operation-tag{display:none}.content{padding:15px 11px 30px}.hero-copy{padding:24px}.hero h1{font-size:25px}.quick{grid-template-columns:1fr}.hub.operacion .hub-items,.hub.servicio .hub-items,.hub.cumplimiento .hub-items{grid-template-columns:1fr}.parameters{grid-template-columns:1fr}.user div:last-child{display:none}}
  </style>
  <style id="sidebar-controls-fix">
    /* El listado puede crecer, pero las acciones de sesión deben permanecer visibles. */
    @media (min-width:651px) {
      .sidebar {
        overflow: hidden;
      }

      .identity,
      .nav-title,
      .sidebar-foot {
        flex: 0 0 auto;
      }

      .nav {
        min-height: 0;
        flex: 1 1 auto;
        align-content: start;
        overflow-x: hidden;
        overflow-y: auto;
        padding: 0 4px 6px 0;
        overscroll-behavior: contain;
        scrollbar-color: rgba(127, 200, 255, .58) transparent;
        scrollbar-width: thin;
      }

      .nav::-webkit-scrollbar {
        width: 5px;
      }

      .nav::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: rgba(127, 200, 255, .58);
      }

      .sidebar-foot {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, .10);
        background: linear-gradient(180deg, rgba(5, 19, 35, 0), rgba(5, 19, 35, .72) 24%);
      }
    }

    .sidebar-foot a {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .sidebar-action-code {
      width: 25px;
      height: 25px;
      display: grid;
      flex: 0 0 auto;
      place-items: center;
      border-radius: 7px;
      color: #7fc8ff;
      background: rgba(42, 168, 255, .12);
      font-size:9px;
      font-weight: 950;
    }

    @media (max-width:900px) and (min-width:651px) {
      .sidebar-foot .sidebar-action-code {
        display: grid;
      }
    }

    @media (max-width:650px) {
      .sidebar-foot {
        display: flex;
        gap: 7px;
        margin-top: 9px;
        padding-top: 9px;
        border-top: 1px solid rgba(255, 255, 255, .10);
      }

      .sidebar-foot a {
        flex: 1 1 0;
        justify-content: center;
      }
    }
  </style>
</head>
<body>
<div class="layout">
  <aside class="sidebar" id="colombia-sidebar">
    <div class="identity"><div class="flag">CO</div><div><strong>Mesa de Servicio</strong><small>Portal corporativo Colombia</small></div></div>
    <p class="nav-title">Administración nacional</p>
    <nav class="nav">
      <a class="active" href="panelAdmin.php"><span class="nav-code">IN</span><span class="nav-label">Centro de control</span></a>
      <a href="solicitudes.php"><span class="nav-code">TK</span><span class="nav-label">Tickets</span></a>
      <a href="crearUsuarios.php"><span class="nav-code">US</span><span class="nav-label">Usuarios</span></a>
      <a href="indicadores.php"><span class="nav-code">BI</span><span class="nav-label">Indicadores</span></a>
      <a href="catalogos.php"><span class="nav-code">CA</span><span class="nav-label">Catálogos</span></a>
      <a href="servicios.php"><span class="nav-code">SV</span><span class="nav-label">Servicios</span></a>
      <a href="soluciones.php"><span class="nav-code">SO</span><span class="nav-label">Soluciones</span></a>
      <a href="procesos.php"><span class="nav-code">FL</span><span class="nav-label">Flujos</span></a>
      <a href="sla.php"><span class="nav-code">SL</span><span class="nav-label">SLA</span></a>
      <a href="feriados.php"><span class="nav-code">FE</span><span class="nav-label">Festivos</span></a>
    </nav>
    <div class="sidebar-foot">
      <a href="seleccionarPais.php" title="Cambiar país de operación">
        <span class="sidebar-action-code" aria-hidden="true">CP</span>
        <span class="sidebar-action-label">Cambiar país</span>
      </a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div><span class="crumb">Mesa de Servicio / Colombia</span><br><strong>Centro de administración nacional</strong></div>
      <div class="user" data-svc-account-host>
        <span class="operation-tag">Operación disponible</span>
      </div>
    </header>

    <div class="content">
      <section class="workspace">
        <?php foreach ($gruposColombia as $grupo): ?>
          <article class="hub <?= htmlspecialchars($grupo['clase'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="hub-head"><span class="hub-label"><?= htmlspecialchars($grupo['etiqueta'], ENT_QUOTES, 'UTF-8') ?></span><h2><?= htmlspecialchars($grupo['titulo'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($grupo['descripcion'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="hub-items">
              <?php foreach ($grupo['items'] as $item): ?>
                <a class="module" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><span class="module-code"><?= htmlspecialchars($item['codigo'], ENT_QUOTES, 'UTF-8') ?></span><span><strong><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($item['texto'], ENT_QUOTES, 'UTF-8') ?></small></span><span class="module-arrow">›</span></a>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="parameters-section">
        <div class="section-heading"><div><h2>Parámetros de la operación</h2><p>Configuraciones disponibles únicamente para Colombia.</p></div></div>
        <div class="parameters">
          <?php foreach ($parametrosColombia as $parametro): ?>
            <a class="parameter" href="configuraciones.php?tipo=<?= urlencode($parametro['tipo']) ?>"><span><?= htmlspecialchars($parametro['codigo'], ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($parametro['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </main>
</div>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
