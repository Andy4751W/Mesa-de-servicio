<?php
declare(strict_types=1);

define('MESA_PERMITE_SIN_PAIS', true);
require_once APP_ROOT . '/security/validarSesion.php';
seguridadExigirRol([1]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $idPais = filter_input(INPUT_POST, 'id_pais_operacion', FILTER_VALIDATE_INT) ?: 0;
    $pais = paisBuscarOperacion($conn, $idPais);

    if (!$pais) {
        header('Location: seleccionarPais.php?error=pais_invalido', true, 303);
        exit;
    }

    paisGuardarContexto($pais);
    $_SESSION['ultima_interaccion'] = time();
    header('Location: panelAdmin.php', true, 303);
    exit;
}

paisLimpiarContexto();
$paises = paisOperacionesActivas($conn);
$nombre = htmlspecialchars((string) ($_SESSION['usuario'] ?? 'Administrador'), ENT_QUOTES, 'UTF-8');
$error = (string) ($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seleccionar país | Mesa de Servicio</title>
    <style>
        *{box-sizing:border-box}
        :root{color-scheme:light}
        body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;color:#243b53;background:radial-gradient(circle at 10% 5%,#e4f0ff 0,transparent 29%),radial-gradient(circle at 92% 92%,#fde9ed 0,transparent 28%),#f6f8fb;font:14px/1.5 Inter,"Segoe UI",Arial,sans-serif}
        .shell{width:min(900px,100%)}
        .top{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:16px}
        .eyebrow{margin:0 0 4px;color:#526d82;font-size:10px;font-weight:850;letter-spacing:.11em;text-transform:uppercase}
        h1{margin:0;color:#102a43;font-size:clamp(26px,3.2vw,34px);line-height:1.15;letter-spacing:-.025em}
        .intro{max-width:650px;margin:6px 0 0;color:#627d98;font-size:12px}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .country{position:relative;overflow:hidden;min-height:222px;padding:22px;border:1px solid #dfe7f1;border-radius:18px;background:rgba(255,255,255,.96);box-shadow:0 16px 38px rgba(16,42,67,.09);transition:.2s ease}
        .country:hover{border-color:color-mix(in srgb,var(--country) 25%,#dfe7f1);box-shadow:0 20px 44px rgba(16,42,67,.12);transform:translateY(-2px)}
        .country::after{position:absolute;right:-48px;bottom:-74px;width:170px;height:170px;border:28px solid color-mix(in srgb,var(--country) 11%,transparent);border-radius:50%;content:"";pointer-events:none}
        .country-head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
        .flag{width:48px;height:34px;display:grid;flex:0 0 auto;place-items:center;border-radius:8px;color:#fff;background:var(--country);box-shadow:0 7px 16px color-mix(in srgb,var(--country) 28%,transparent);font-size:11px;font-weight:950;letter-spacing:.08em}
        .country h2{margin:0;color:#102a43;font-size:22px;letter-spacing:-.02em}
        .country p{position:relative;z-index:1;min-height:42px;max-width:335px;margin:0 0 17px;color:#627d98;font-size:12px}
        .country button{position:relative;z-index:1;width:100%;min-height:42px;border:0;border-radius:10px;color:#fff;background:var(--country);box-shadow:0 8px 18px color-mix(in srgb,var(--country) 18%,transparent);font:inherit;font-size:12px;font-weight:850;cursor:pointer;transition:.18s ease}
        .country button:hover{filter:brightness(.94);transform:translateY(-1px)}
        .country button:focus-visible{outline:3px solid rgba(15,111,236,.2);outline-offset:2px}
        .alert{margin-bottom:14px;padding:10px 13px;border:1px solid #f2c9ce;border-radius:10px;color:#9f2f2f;background:#fff0f0;font-size:12px}
        .empty{padding:25px;border:1px solid #dfe7f1;border-radius:16px;background:#fff;text-align:center}
        @media(max-width:680px){body{align-items:start;padding:18px 14px}.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.country{min-height:auto}.country p{min-height:0}}
    </style>
</head>
<body>
<main class="shell">
    <header class="top">
        <div>
            <p class="eyebrow">Sesión de <?= $nombre ?></p>
            <h1>Seleccione la operación</h1>
            <p class="intro">Trabaje con la información, usuarios y configuración del país elegido.</p>
        </div>
    </header>
    <?php if ($error !== ''): ?><div class="alert">No fue posible seleccionar ese país.</div><?php endif; ?>
    <?php if (!$paises): ?>
        <div class="empty">No hay países de operación activos.</div>
    <?php else: ?>
        <section class="grid" aria-label="Países de operación">
            <?php foreach ($paises as $pais): ?>
                <?php $color = htmlspecialchars((string) $pais['color_primario'], ENT_QUOTES, 'UTF-8'); ?>
                <article class="country" style="--country:<?= $color ?>">
                    <div class="country-head">
                        <div class="flag"><?= htmlspecialchars((string) $pais['codigo'], ENT_QUOTES, 'UTF-8') ?></div>
                        <h2><?= htmlspecialchars((string) $pais['nombre'], ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                    <p>Ingresar al panel administrativo y trabajar exclusivamente con la información de <?= htmlspecialchars((string) $pais['nombre'], ENT_QUOTES, 'UTF-8') ?>.</p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(seguridadTokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id_pais_operacion" value="<?= (int) $pais['id_pais_operacion'] ?>">
                        <button type="submit">Ingresar a <?= htmlspecialchars((string) $pais['nombre'], ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
