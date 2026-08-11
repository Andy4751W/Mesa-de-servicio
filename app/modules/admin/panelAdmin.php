<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';

if ((int) ($_SESSION['rol'] ?? 0) !== 1) {
  header('Location: login.html?error=acceso_denegado');
  exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$idPaisOperacion = paisExigirContexto();
$nombrePaisOperacion = paisContextoNombre();
$colorPaisOperacion = paisColorActual();

if (paisContextoCodigo() === 'PE') {
  require __DIR__ . '/panelAdminPeru.php';
  return;
}

if (paisContextoCodigo() === 'CO') {
  require __DIR__ . '/panelAdminColombia.php';
  return;
}

$modulosConfigurables = [
  [
    'tipo' => 'pais',
    'titulo' => 'Países',
    'descripcion' => 'Registre los países donde actúa la empresa.',
    'codigo' => 'PA',
    'color' => '#7c3aed',
    'suave' => '#f1eafe',
  ],
  [
    'tipo' => 'departamento',
    'titulo' => 'Departamentos',
    'descripcion' => 'Administre los departamentos asociados a cada país.',
    'codigo' => 'DE',
    'color' => '#6d28d9',
    'suave' => '#eee9fe',
  ],
  [
    'tipo' => 'ciudad',
    'titulo' => 'Ciudades',
    'descripcion' => 'Administre las ciudades asociadas a un departamento.',
    'codigo' => 'CI',
    'color' => '#8b5cf6',
    'suave' => '#f3efff',
  ],
  [
    'tipo' => 'prioridad',
    'titulo' => 'Prioridades',
    'descripcion' => 'Defina las prioridades disponibles en los servicios.',
    'codigo' => 'PR',
    'color' => '#db2777',
    'suave' => '#fce7f3',
  ],
  [
    'tipo' => 'urgencia',
    'titulo' => 'Urgencias',
    'descripcion' => 'Configure los grados de urgencia de atención.',
    'codigo' => 'UR',
    'color' => '#e11d48',
    'suave' => '#ffe4e9',
  ],
  [
    'tipo' => 'nivel',
    'titulo' => 'Niveles',
    'descripcion' => 'Administre los niveles de atención o escalamiento.',
    'codigo' => 'NV',
    'color' => '#0f6fec',
    'suave' => '#e8f2ff',
  ],
  [
    'tipo' => 'impacto',
    'titulo' => 'Impactos',
    'descripcion' => 'Defina el alcance que puede tener una solicitud.',
    'codigo' => 'IM',
    'color' => '#0e9f9a',
    'suave' => '#e5f8f6',
  ],
  [
    'tipo' => 'estado',
    'titulo' => 'Estados',
    'descripcion' => 'Administre los estados disponibles para el servicio.',
    'codigo' => 'ES',
    'color' => '#d97706',
    'suave' => '#fff3df',
  ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administrador | Mesa de Servicio</title>
  <style>
    :root {
      --primary: <?= htmlspecialchars($colorPaisOperacion, ENT_QUOTES, 'UTF-8') ?>;
      --primary-dark: <?= htmlspecialchars($colorPaisOperacion, ENT_QUOTES, 'UTF-8') ?>;
      --navy: #102a43;
      --text: #243b53;
      --muted: #627d98;
      --surface: #ffffff;
      --background: #f3f6fb;
      --border: #e3eaf3;
      --shadow: 0 18px 45px rgba(15, 45, 75, 0.10);
    }

    * {
      box-sizing: border-box;
    }

    html {
      min-height: 100%;
    }

    body {
      min-height: 100vh;
      margin: 0;
      color: var(--text);
      background:
        radial-gradient(circle at 8% 0%, rgba(15, 111, 236, 0.10), transparent 28%),
        radial-gradient(circle at 96% 92%, rgba(14, 165, 164, 0.09), transparent 25%),
        var(--background);
      font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
      line-height: 1.5;
    }

    a {
      color: inherit;
    }

    .country-button {
      display:inline-flex;align-items:center;gap:7px;padding:8px 11px;
      border:1px solid color-mix(in srgb,var(--primary) 28%,#dfe7f1);
      border-radius:9px;color:var(--primary);background:#fff;
      text-decoration:none;font-size:11px;font-weight:800;
    }

    .page-shell {
      width: min(1280px, calc(100% - 32px));
      margin: 0 auto;
      padding: 12px 0 14px;
    }

    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 10px;
      padding: 9px 14px;
      border: 1px solid rgba(227, 234, 243, 0.85);
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.92);
      box-shadow: 0 8px 24px rgba(15, 45, 75, 0.06);
      backdrop-filter: blur(10px);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }

    .brand-mark {
      display: grid;
      flex: 0 0 auto;
      width: 36px;
      height: 36px;
      place-items: center;
      border-radius: 10px;
      color: #fff;
      background: linear-gradient(145deg, #0f7af5, #0b4fae);
      box-shadow: 0 8px 18px rgba(15, 111, 236, 0.27);
      font-size: 13px;
      font-weight: 800;
      letter-spacing: -0.4px;
    }

    .brand-copy {
      min-width: 0;
    }

    .brand-name,
    .brand-subtitle {
      margin: 0;
    }

    .brand-name {
      color: var(--navy);
      font-size: 14px;
      font-weight: 750;
    }

    .brand-subtitle {
      color: var(--muted);
      font-size: 11px;
    }

    .role-chip {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      flex: 0 0 auto;
      padding: 6px 10px;
      border: 1px solid #dbe7f5;
      border-radius: 999px;
      color: #315779;
      background: #f7faff;
      font-size: 12px;
      font-weight: 700;
    }

    .role-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.13);
    }

    .session-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 0 0 auto;
    }

    .hero {
      position: relative;
      overflow: hidden;
      min-height: 58px;
      padding: 9px 18px;
      border-radius: 13px;
      color: #fff;
      background: linear-gradient(120deg, #0b4fae 0%, #0f76e8 58%, #1996ec 100%);
      box-shadow: var(--shadow);
    }

    .hero::before,
    .hero::after {
      position: absolute;
      border-radius: 50%;
      content: "";
      pointer-events: none;
    }

    .hero::before {
      top: -165px;
      right: -45px;
      width: 290px;
      height: 290px;
      border: 42px solid rgba(255, 255, 255, 0.07);
    }

    .hero::after {
      right: 205px;
      bottom: -215px;
      width: 265px;
      height: 265px;
      background: rgba(255, 255, 255, 0.055);
    }

    .hero-content {
      position: relative;
      z-index: 1;
      display: flex;
      width: 100%;
      max-width: none;
      align-items: center;
      gap: 12px;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin: 0;
      padding: 3px 8px;
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      font-size: 9px;
      font-weight: 750;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .hero h1 {
      margin: 0;
      flex: 0 0 auto;
      font-size: 20px;
      line-height: 1.15;
      letter-spacing: -0.35px;
    }

    .hero p {
      max-width: none;
      margin: 0;
      color: rgba(255, 255, 255, 0.84);
      font-size: 10.5px;
    }

    .content {
      margin-top: 13px;
    }

    .section-heading {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 9px;
    }

    .section-heading h2,
    .section-heading p {
      margin: 0;
    }

    .section-heading h2 {
      color: var(--navy);
      font-size: 18px;
      letter-spacing: -0.25px;
    }

    .section-heading p {
      margin-top: 1px;
      color: var(--muted);
      font-size: 11px;
    }

    .module-count {
      flex: 0 0 auto;
      padding: 5px 9px;
      border: 1px solid #dce7f3;
      border-radius: 9px;
      color: #486581;
      background: rgba(255, 255, 255, 0.78);
      font-size: 11px;
      font-weight: 700;
    }

    .menu {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .module-card {
      --accent: #0f6fec;
      --accent-soft: #e9f2ff;
      position: relative;
      display: grid;
      grid-template-columns: auto minmax(0, 1fr) auto;
      align-items: center;
      gap: 11px;
      min-height: 86px;
      padding: 11px 13px;
      overflow: hidden;
      border: 1px solid var(--border);
      border-radius: 14px;
      color: var(--text);
      background: var(--surface);
      box-shadow: 0 6px 18px rgba(31, 62, 93, 0.065);
      text-decoration: none;
      transition:
        transform 0.22s ease,
        box-shadow 0.22s ease,
        border-color 0.22s ease;
    }

    .module-card::before {
      position: absolute;
      top: 0;
      left: 0;
      width: 5px;
      height: 100%;
      background: var(--accent);
      content: "";
      transform: scaleY(0.55);
      transform-origin: center;
      transition: transform 0.22s ease;
    }

    .module-card:hover {
      transform: translateY(-2px);
      border-color: color-mix(in srgb, var(--accent) 38%, white);
      box-shadow: 0 11px 24px rgba(31, 62, 93, 0.12);
    }

    .module-card:hover::before {
      transform: scaleY(1);
    }

    .module-card:focus-visible {
      outline: 3px solid rgba(15, 111, 236, 0.28);
      outline-offset: 3px;
    }

    .module-card.users {
      --accent: #0f6fec;
      --accent-soft: #e9f2ff;
    }

    .module-card.catalogs {
      --accent: #0e9f9a;
      --accent-soft: #e5f8f6;
    }

    .module-card.services {
      --accent: #2563eb;
      --accent-soft: #e8f0ff;
    }

    .module-card.requests {
      --accent: #7c55d9;
      --accent-soft: #f1edfc;
    }

    .module-card.sla {
      --accent: #e88918;
      --accent-soft: #fff3df;
    }

    .module-card.holidays {
      --accent: #0891b2;
      --accent-soft: #e6f7fb;
    }

    .module-card.indicators {
      --accent: #0f8b72;
      --accent-soft: #e5f7f2;
    }

    .setting-code {
      font-size: 11px;
      font-weight: 850;
      letter-spacing: 0.06em;
    }

    .icon-box {
      display: grid;
      width: 42px;
      height: 42px;
      place-items: center;
      border-radius: 12px;
      color: var(--accent);
      background: var(--accent-soft);
    }

    .icon-box svg {
      width: 21px;
      height: 21px;
      stroke: currentColor;
    }

    .module-copy {
      min-width: 0;
    }

    .module-title,
    .module-description {
      margin: 0;
    }

    .module-title {
      color: var(--navy);
      font-size: 14px;
      font-weight: 760;
      line-height: 1.18;
      letter-spacing: -0.1px;
    }

    .module-description {
      display: -webkit-box;
      margin-top: 3px;
      overflow: hidden;
      color: var(--muted);
      font-size: 10.5px;
      line-height: 1.3;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
    }

    .module-arrow {
      display: grid;
      width: 28px;
      height: 28px;
      place-items: center;
      border-radius: 50%;
      color: var(--accent);
      background: var(--accent-soft);
      transition: transform 0.22s ease;
    }

    .module-card:hover .module-arrow {
      transform: translateX(4px);
    }

    .module-arrow svg {
      width: 14px;
      height: 14px;
      stroke: currentColor;
    }

    .footer {
      display: none;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      margin-top: 28px;
      padding: 0 4px;
      color: #829ab1;
      font-size: 12px;
    }

    @media (max-width: 1080px) {
      .menu {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    @media (max-width: 760px) {
      .page-shell {
        width: min(100% - 24px, 1280px);
        padding-top: 12px;
      }

      .topbar {
        border-radius: 14px;
      }

      .brand-subtitle {
        display: none;
      }

      .hero {
        min-height: 62px;
        padding: 10px 14px;
        border-radius: 13px;
      }

      .hero p {
        display: none;
      }

      .hero-content {
        flex-wrap: wrap;
        gap: 7px;
      }

      .section-heading {
        align-items: flex-start;
      }

      .module-count {
        display: none;
      }

      .menu {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .module-card {
        min-height: 94px;
        padding: 14px;
      }

      .footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
      }
    }

    @media (max-width: 520px) {
      .role-chip {
        padding: 8px;
        font-size: 0;
      }

      .session-actions {
        gap: 6px;
      }

      .module-card {
        grid-template-columns: auto minmax(0, 1fr) auto;
      }

      .menu {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 440px) {
      .module-card {
        grid-template-columns: auto minmax(0, 1fr);
      }

      .module-arrow {
        display: none;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
      }
    }
  </style>
</head>
<body>
  <main class="page-shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark" aria-hidden="true">MS</div>
        <div class="brand-copy">
          <p class="brand-name">Mesa de Servicio</p>
          <p class="brand-subtitle">Centro de administración</p>
        </div>
      </div>

      <div class="session-actions">
        <a class="country-button" href="seleccionarPais.php" title="Cambiar país de operación">
          <?= htmlspecialchars($nombrePaisOperacion, ENT_QUOTES, 'UTF-8') ?> · Cambiar país
        </a>
        <div class="role-chip" title="Sesión de administrador">
          <span class="role-dot" aria-hidden="true"></span>
          Administrador
        </div>
      </div>
    </header>

    <section class="hero" aria-labelledby="titulo-panel">
      <div class="hero-content">
        <span class="eyebrow">Operación <?= htmlspecialchars($nombrePaisOperacion, ENT_QUOTES, 'UTF-8') ?></span>
        <h1 id="titulo-panel">Panel de Administrador · <?= htmlspecialchars($nombrePaisOperacion, ENT_QUOTES, 'UTF-8') ?></h1>
        <p>
          Los cambios realizados aquí se aplican únicamente a
          <?= htmlspecialchars($nombrePaisOperacion, ENT_QUOTES, 'UTF-8') ?>.
        </p>
      </div>
    </section>

    <section class="content" aria-labelledby="titulo-modulos">
      <div class="section-heading">
        <div>
          <h2 id="titulo-modulos">Módulos de administración</h2>
          <p>Seleccione una opción para administrar <?= htmlspecialchars($nombrePaisOperacion, ENT_QUOTES, 'UTF-8') ?>.</p>
        </div>
        <span class="module-count">
          <?php echo 9 + count($modulosConfigurables); ?> módulos disponibles
        </span>
      </div>

      <nav class="menu" aria-label="Módulos administrativos">
        <a class="module-card users" href="crearUsuarios.php">
          <span class="icon-box" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M19 8v6M16 11h6"></path>
            </svg>
          </span>
          <span class="module-copy">
            <span class="module-title">Gestión de usuarios</span>
            <span class="module-description">
              Cree, edite, habilite o inhabilite las cuentas del sistema.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <a class="module-card catalogs" href="catalogos.php">
          <span class="icon-box" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"></path>
              <path d="M4 5.5v16M8 7h8M8 11h8"></path>
            </svg>
          </span>
          <span class="module-copy">
            <span class="module-title">Catálogos</span>
            <span class="module-description">
              Cree, edite, habilite y organice las clases de servicio.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <a class="module-card services" href="servicios.php">
          <span class="icon-box" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 4h14a2 2 0 0 1 2 2v3H3V6a2 2 0 0 1 2-2z"></path>
              <path d="M3 9h18v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <path d="M7 14h4M7 17h7"></path>
            </svg>
          </span>
          <span class="module-copy">
            <span class="module-title">Servicios</span>
            <span class="module-description">
              Seleccione un catálogo y administre sus servicios, SLA y parámetros.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <a
          class="module-card configurable"
          href="soluciones.php"
          style="--accent:#0f8b72;--accent-soft:#e7f8f3;"
        >
          <span class="icon-box" aria-hidden="true">
            <span class="setting-code">SO</span>
          </span>
          <span class="module-copy">
            <span class="module-title">Soluciones por servicio</span>
            <span class="module-description">
              Cree, edite o retire las soluciones predeterminadas de cada servicio.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <a class="module-card requests" href="procesos.php">
          <span class="icon-box" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="5" cy="6" r="2"></circle>
              <circle cx="19" cy="12" r="2"></circle>
              <circle cx="5" cy="18" r="2"></circle>
              <path d="M7 6h4a3 3 0 0 1 3 3v0a3 3 0 0 0 3 3M7 18h4a3 3 0 0 0 3-3v0"></path>
            </svg>
          </span>
          <span class="module-copy">
            <span class="module-title">Configuración de tickets</span>
            <span class="module-description">
              Configure tipos de ticket, áreas, gestores, SLA, orden y checklist.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <a class="module-card requests" href="solicitudes.php">
          <span class="icon-box" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 11h6M9 15h4"></path>
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <path d="M14 2v6h6"></path>
            </svg>
          </span>
          <span class="module-copy">
            <span class="module-title">Tickets</span>
            <span class="module-description">
              Consulte y gestione los tickets, su trazabilidad y conversación.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <a class="module-card indicators" href="indicadores.php">
          <span class="icon-box" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 19V9M10 19V5M16 19v-7M22 19V2"></path>
              <path d="M2 19h21"></path>
            </svg>
          </span>
          <span class="module-copy">
            <span class="module-title">Indicadores</span>
            <span class="module-description">
              Consulte métricas, SLA, calificaciones, tiempos, gestores y áreas.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <a class="module-card sla" href="sla.php">
          <span class="icon-box" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="13" r="8"></circle>
              <path d="M12 9v4l2.5 1.5M9 2h6M12 2v3"></path>
            </svg>
          </span>
          <span class="module-copy">
            <span class="module-title">Acuerdos de servicio (SLA)</span>
            <span class="module-description">
              Configure los tiempos de respuesta asignados a cada servicio.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <a class="module-card holidays" href="feriados.php">
          <span class="icon-box" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="5" width="18" height="16" rx="2"></rect>
              <path d="M16 3v4M8 3v4M3 10h18"></path>
              <path d="m9 15 2 2 4-4"></path>
            </svg>
          </span>
          <span class="module-copy">
            <span class="module-title">Feriados</span>
            <span class="module-description">
              Registre días y rangos no laborables que deben excluirse del SLA.
            </span>
          </span>
          <span class="module-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
          </span>
        </a>

        <?php foreach ($modulosConfigurables as $modulo): ?>
          <a
            class="module-card configurable"
            href="configuraciones.php?tipo=<?php echo urlencode($modulo['tipo']); ?>"
            style="--accent: <?php echo htmlspecialchars($modulo['color'], ENT_QUOTES, 'UTF-8'); ?>; --accent-soft: <?php echo htmlspecialchars($modulo['suave'], ENT_QUOTES, 'UTF-8'); ?>;"
          >
            <span class="icon-box" aria-hidden="true">
              <span class="setting-code">
                <?php echo htmlspecialchars($modulo['codigo'], ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </span>
            <span class="module-copy">
              <span class="module-title">
                <?php echo htmlspecialchars($modulo['titulo'], ENT_QUOTES, 'UTF-8'); ?>
              </span>
              <span class="module-description">
                <?php echo htmlspecialchars($modulo['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </span>
            <span class="module-arrow" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m9 18 6-6-6-6"></path>
              </svg>
            </span>
          </a>
        <?php endforeach; ?>
      </nav>
    </section>

    <footer class="footer">
      <span>Mesa de Servicio · Administración</span>
      <span>Gestión centralizada de la plataforma</span>
    </footer>
  </main>
  <script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
