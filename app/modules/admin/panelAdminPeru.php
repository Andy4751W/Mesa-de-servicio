<?php
declare(strict_types=1);

$nombreAdministrador = htmlspecialchars(
    (string) ($_SESSION['usuario'] ?? 'Administrador'),
    ENT_QUOTES,
    'UTF-8'
);

$parametrosPeru = [
    ['tipo' => 'pais', 'codigo' => 'PA', 'nombre' => 'Países y cobertura'],
    ['tipo' => 'departamento', 'codigo' => 'DE', 'nombre' => 'Departamentos'],
    ['tipo' => 'ciudad', 'codigo' => 'CI', 'nombre' => 'Ciudades'],
    ['tipo' => 'prioridad', 'codigo' => 'PR', 'nombre' => 'Prioridades'],
    ['tipo' => 'urgencia', 'codigo' => 'UR', 'nombre' => 'Urgencias'],
    ['tipo' => 'nivel', 'codigo' => 'NV', 'nombre' => 'Niveles'],
    ['tipo' => 'impacto', 'codigo' => 'IM', 'nombre' => 'Impactos'],
    ['tipo' => 'estado', 'codigo' => 'ES', 'nombre' => 'Estados'],
];

$modulosPeru = [
    [
        'grupo' => 'Operación',
        'descripcion' => 'Gestione las personas, servicios y casos de Perú.',
        'items' => [
            ['url' => 'crearUsuarios.php', 'codigo' => 'US', 'titulo' => 'Usuarios', 'texto' => 'Gestores y solicitantes registrados en Perú.'],
            ['url' => 'solicitudes.php', 'codigo' => 'TK', 'titulo' => 'Tickets', 'texto' => 'Casos, derivaciones, conversaciones y trazabilidad.'],
            ['url' => 'indicadores.php', 'codigo' => 'BI', 'titulo' => 'Indicadores', 'texto' => 'Resultados, cumplimiento del SLA y desempeño.'],
        ],
    ],
    [
        'grupo' => 'Diseño del servicio',
        'descripcion' => 'Configure la oferta y los flujos de atención peruanos.',
        'items' => [
            ['url' => 'catalogos.php', 'codigo' => 'CA', 'titulo' => 'Catálogos', 'texto' => 'Áreas y categorías visibles para la operación.'],
            ['url' => 'servicios.php', 'codigo' => 'SV', 'titulo' => 'Servicios', 'texto' => 'Servicios, parámetros y tiempos asociados.'],
            ['url' => 'configuraciones.php?tipo=prioridad', 'codigo' => 'PR', 'titulo' => 'Prioridades', 'texto' => 'Niveles de atención asignables a los servicios.'],
            ['url' => 'soluciones.php', 'codigo' => 'SO', 'titulo' => 'Soluciones', 'texto' => 'Respuestas predeterminadas por servicio.'],
            ['url' => 'procesos.php', 'codigo' => 'FL', 'titulo' => 'Flujos', 'texto' => 'Etapas, gestores, derivaciones y checklist.'],
        ],
    ],
    [
        'grupo' => 'Tiempo y calendario',
        'descripcion' => 'Controle las reglas laborales que aplican únicamente en Perú.',
        'items' => [
            ['url' => 'sla.php', 'codigo' => 'SL', 'titulo' => 'Acuerdos SLA', 'texto' => 'Tiempos de respuesta para los servicios.'],
            ['url' => 'feriados.php', 'codigo' => 'FE', 'titulo' => 'Feriados', 'texto' => 'Días y periodos no laborables de Perú.'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administración Perú | Mesa de Servicio</title>
  <style>
    :root{--peru:#c81e3a;--peru-dark:#8f1027;--ink:#182433;--muted:#526d82;--line:#e5e9ef;--surface:#fff;--soft:#f7f8fa;--sidebar:#171d27}*{box-sizing:border-box}html,body{min-height:100%}body{margin:0;color:var(--ink);background:#f3f4f6;font:14px/1.5 Inter,"Segoe UI",Arial,sans-serif}.layout{min-height:100vh;display:grid;grid-template-columns:260px minmax(0,1fr)}.sidebar{position:sticky;top:0;height:100vh;display:flex;flex-direction:column;padding:24px 18px;color:#fff;background:linear-gradient(180deg,#1b222d,#111720)}.identity{display:flex;align-items:center;gap:12px;padding:0 7px 25px;border-bottom:1px solid rgba(255,255,255,.1)}.flag{width:43px;height:43px;display:grid;place-items:center;border-radius:11px;background:var(--peru);font-weight:900;letter-spacing:.08em}.identity strong{display:block;font-size:15px}.identity small{display:block;margin-top:1px;color:#aeb8c5;font-size:10px}.nav-title{margin:23px 9px 8px;color:#7f8c9c;font-size:9px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.nav{display:grid;gap:5px}.nav a{display:flex;align-items:center;gap:10px;padding:10px 11px;border-radius:9px;color:#c9d1db;text-decoration:none;font-size:12px;font-weight:720}.nav a:hover,.nav a.active{color:#fff;background:rgba(200,30,58,.22)}.nav-code{width:25px;color:#ff9aac;font-size:9px;font-weight:900}.sidebar-foot{display:grid;gap:7px;margin-top:auto}.sidebar-foot a{padding:9px 11px;border:1px solid rgba(255,255,255,.12);border-radius:9px;color:#d5dbe3;text-decoration:none;font-size:11px;font-weight:750}.main{min-width:0}.topbar{height:72px;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:0 32px;border-bottom:1px solid var(--line);background:#fff}.crumb{color:var(--muted);font-size:11px}.topbar strong{color:var(--ink)}.user{display:flex;align-items:center;gap:10px}.avatar{width:34px;height:34px;display:grid;place-items:center;border-radius:50%;color:#fff;background:var(--peru);font-size:11px;font-weight:900}.content{width:min(1260px,100%);margin:auto;padding:28px 32px 46px}.section{margin-top:25px}.content>.section:first-child{margin-top:0}.section-heading{display:flex;align-items:end;justify-content:space-between;gap:16px;margin-bottom:11px}.section h2{margin:0;font-size:18px}.section-heading p{margin:3px 0 0;color:var(--muted);font-size:11px}.module-list{overflow:hidden;border:1px solid var(--line);border-radius:14px;background:#fff}.module-row{display:grid;grid-template-columns:46px minmax(0,1fr) auto;align-items:center;gap:13px;min-height:72px;padding:12px 16px;border-bottom:1px solid #edf0f4;color:inherit;text-decoration:none}.module-row:last-child{border-bottom:0}.module-row:hover{background:#fff8f9}.module-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:10px;color:var(--peru);background:#fff0f2;font-size:10px;font-weight:900}.module-row strong{display:block}.module-row small{display:block;margin-top:2px;color:var(--muted);font-size:11px}.arrow{color:var(--peru);font-size:18px}.parameters-section{margin-top:14px}.parameters-section .section-heading{margin-bottom:8px}.parameters-section h2{font-size:14px}.parameters-section .section-heading p{margin-top:1px;font-size:9px}.parameters{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px}.parameter{display:flex;align-items:center;gap:7px;min-height:38px;padding:6px 8px;border:1px solid var(--line);border-radius:9px;color:var(--ink);background:#fff;text-decoration:none;font-size:9px;font-weight:760}.parameter span{width:24px;height:24px;display:grid;place-items:center;border-radius:7px;color:var(--peru);background:#fff0f2;font-size:9px;font-weight:900}@media(max-width:1050px) and (min-width:901px){.parameters{grid-template-columns:repeat(3,1fr)}}@media(max-width:900px) and (min-width:621px){.parameters{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.layout{display:block}.sidebar{position:relative;width:100%;height:auto;display:block;padding:12px}.identity{justify-content:flex-start;padding:0 0 11px}.identity div:last-child{display:block}.nav{grid-template-columns:repeat(4,1fr)}.nav a{padding:8px}.sidebar-foot{display:none}.topbar{height:auto;padding:12px 16px}.content{padding:16px 12px 32px}.parameters{grid-template-columns:1fr}.user div:last-child{display:none}}
    .flag{display:block;overflow:hidden;border:1px solid rgba(255,255,255,.3);background:linear-gradient(90deg,#d9102f 0 33.333%,#fff 33.333% 66.666%,#d9102f 66.666% 100%);box-shadow:0 9px 22px rgba(0,0,0,.22)}
  </style>
  <style id="sidebar-controls-fix">
    /* El listado puede crecer, pero las acciones de sesión deben permanecer visibles. */
    @media (min-width:621px) {
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
        scrollbar-color: rgba(255, 154, 172, .58) transparent;
        scrollbar-width: thin;
      }

      .nav::-webkit-scrollbar {
        width: 5px;
      }

      .nav::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: rgba(255, 154, 172, .58);
      }

      .sidebar-foot {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, .10);
        background: linear-gradient(180deg, rgba(17, 23, 32, 0), rgba(17, 23, 32, .76) 24%);
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
      color: #ff9aac;
      background: rgba(200, 30, 58, .20);
      font-size:9px;
      font-weight: 900;
    }

    @media (max-width:620px) {
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
  <aside class="sidebar" id="peru-sidebar">
    <div class="identity"><div class="flag" role="img" aria-label="Bandera de Perú"></div><div><strong>Mesa de Servicio</strong><small>Portal corporativo Perú</small></div></div>
    <p class="nav-title">Administración</p>
    <nav class="nav">
      <a class="active" href="panelAdmin.php"><span class="nav-code">IN</span><span>Inicio</span></a>
      <a href="solicitudes.php"><span class="nav-code">TK</span><span>Tickets</span></a>
      <a href="crearUsuarios.php"><span class="nav-code">US</span><span>Usuarios</span></a>
      <a href="indicadores.php"><span class="nav-code">BI</span><span>Indicadores</span></a>
      <a href="catalogos.php"><span class="nav-code">CA</span><span>Catálogos</span></a>
      <a href="servicios.php"><span class="nav-code">SV</span><span>Servicios</span></a>
      <a href="soluciones.php"><span class="nav-code">SO</span><span>Soluciones</span></a>
      <a href="procesos.php"><span class="nav-code">FL</span><span>Flujos</span></a>
      <a href="sla.php"><span class="nav-code">SL</span><span>SLA y calendario</span></a>
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
      <div><span class="crumb">Mesa de Servicio / Perú</span><br><strong>Centro de control operativo</strong></div>
      <div class="user" data-svc-account-host></div>
    </header>
    <div class="content">
      <?php foreach ($modulosPeru as $grupo): ?>
        <section class="section">
          <div class="section-heading"><div><h2><?= htmlspecialchars($grupo['grupo'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($grupo['descripcion'], ENT_QUOTES, 'UTF-8') ?></p></div></div>
          <div class="module-list">
            <?php foreach ($grupo['items'] as $item): ?>
              <a class="module-row" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><span class="module-icon"><?= htmlspecialchars($item['codigo'], ENT_QUOTES, 'UTF-8') ?></span><span><strong><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($item['texto'], ENT_QUOTES, 'UTF-8') ?></small></span><span class="arrow">›</span></a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>

      <section class="section parameters-section">
        <div class="section-heading"><div><h2>Parámetros operativos</h2><p>Opciones disponibles exclusivamente para los servicios de Perú.</p></div></div>
        <div class="parameters">
          <?php foreach ($parametrosPeru as $parametro): ?>
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
