<?php
declare(strict_types=1);

// La apariencia del administrador también debe estar disponible antes de
// seleccionar una operación; para gestores y solicitantes se conserva el país.
if (!defined('MESA_PERMITE_SIN_PAIS')) {
    define('MESA_PERMITE_SIN_PAIS', true);
}

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/temasInterfaz.php';

seguridadExigirRol([1, 2, 3]);

$rolSesion = (int) ($_SESSION['rol'] ?? 0);
$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$idPaisOperacion = $rolSesion === 1
    ? paisContextoId()
    : paisExigirContexto();
$codigoPaisTema = paisContextoCodigo() ?: 'CO';
$esModalApariencia = (string) ($_GET['modal'] ?? '') === '1';

function escaparApariencia(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function aparienciaRedirigir(string $tipo, string $texto): never
{
    $_SESSION['apariencia_mensaje'] = [
        'tipo' => $tipo,
        'texto' => $texto,
    ];

    $destino = (string) ($_GET['modal'] ?? '') === '1'
        ? 'apariencia.php?modal=1&embed=1'
        : 'apariencia.php';

    header('Location: ' . $destino, true, 303);
    exit;
}

// Confirma que la preferencia solo se consulte y actualice para la cuenta
// activa. La regla es idéntica para administrador, gestor y solicitante.
$stmt = $conn->prepare(
    "SELECT id_usuario
     FROM usuarios
     WHERE id_usuario = ?
       AND (id_rol = 1 OR (id_pais_operacion = ? AND id_rol IN (2, 3)))
       AND estado = 'activo'
     LIMIT 1"
);
$stmt->bind_param('ii', $idUsuario, $idPaisOperacion);
$stmt->execute();
$usuarioApariencia = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuarioApariencia) {
    http_response_code(403);
    exit('No fue posible acceder a la apariencia de esta cuenta.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if ((string) ($_POST['accion'] ?? '') !== 'cambiar_tema') {
            throw new DomainException('La acción solicitada no está disponible.');
        }

        $temaGuardado = temaInterfazGuardarUsuario(
            $conn,
            $idUsuario,
            (string) ($_POST['tema'] ?? ''),
            $codigoPaisTema
        );

        $_SESSION['apariencia_tema_actualizado'] = $temaGuardado;
        aparienciaRedirigir(
            'ok',
            'La apariencia se guardó únicamente para su cuenta.'
        );
    } catch (DomainException $e) {
        aparienciaRedirigir('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Error al cambiar la apariencia: ' . $e->getMessage());
        aparienciaRedirigir(
            'error',
            'No fue posible guardar la apariencia. Inténtelo nuevamente.'
        );
    }
}

$mensaje = $_SESSION['apariencia_mensaje'] ?? null;
unset($_SESSION['apariencia_mensaje']);

$temaActualizado = (string) (
    $_SESSION['apariencia_tema_actualizado'] ?? ''
);
unset($_SESSION['apariencia_tema_actualizado']);

$temas = temasInterfazDisponibles($codigoPaisTema);
$temaActual = temaInterfazUsuario($conn, $idUsuario, $codigoPaisTema);
$paleta = temaInterfazResolver($temaActual, $codigoPaisTema);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar apariencia | Mesa de Servicio</title>
    <style>
        :root{
            --primary:<?= escaparApariencia($paleta['primary']) ?>;
            --dark:<?= escaparApariencia($paleta['dark']) ?>;
            --accent:<?= escaparApariencia($paleta['accent']) ?>;
            --page:<?= escaparApariencia($paleta['background']) ?>;
            --surface:<?= escaparApariencia($paleta['surface']) ?>;
            --soft:<?= escaparApariencia($paleta['soft']) ?>;
            --text:<?= escaparApariencia($paleta['text']) ?>;
            --heading:<?= escaparApariencia($paleta['heading']) ?>;
            --muted:<?= escaparApariencia($paleta['muted']) ?>;
            --line:<?= escaparApariencia($paleta['border']) ?>;
            --on-primary:<?= escaparApariencia($paleta['on_primary']) ?>;
        }
        *{box-sizing:border-box}
        html{color-scheme:<?= escaparApariencia($paleta['scheme']) ?>;background:var(--page)}
        body{min-width:0;margin:0;color:var(--text);background:radial-gradient(circle at 50% 0,color-mix(in srgb,var(--primary) 9%,var(--surface)),transparent 38%),var(--page);font:14px/1.5 Inter,"Segoe UI",Arial,sans-serif}
        button,input{font:inherit}
        .appearance-page{min-height:100vh;padding:22px}
        .appearance-shell{width:min(1080px,100%);margin:0 auto}
        .appearance-intro{display:flex;align-items:flex-start;gap:13px;padding:17px 18px;border:1px solid var(--line);border-radius:15px;color:var(--text);background:color-mix(in srgb,var(--surface) 88%,var(--soft));box-shadow:0 8px 24px color-mix(in srgb,var(--dark) 12%,transparent)}
        .appearance-intro-icon{width:42px;height:42px;display:grid;flex:0 0 auto;place-items:center;border-radius:12px;color:var(--on-primary);background:linear-gradient(145deg,var(--primary),color-mix(in srgb,var(--primary) 64%,var(--accent)));font-size:20px;font-weight:900}
        .appearance-intro h1{margin:0;color:var(--heading);font-size:21px;line-height:1.2}
        .appearance-intro p{max-width:760px;margin:5px 0 0;color:var(--muted);font-size:13px;line-height:1.55}
        .theme-form{display:grid;gap:17px;margin-top:17px}
        .theme-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .theme-choice{position:relative;display:block;min-width:0;cursor:pointer}
        .theme-choice input{position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none}
        .theme-choice-body{min-height:154px;display:grid;align-content:start;gap:12px;padding:14px;border:1px solid var(--line);border-radius:15px;background:var(--surface);box-shadow:0 6px 18px color-mix(in srgb,var(--dark) 9%,transparent);transition:border-color .16s ease,box-shadow .16s ease,transform .16s ease}
        .theme-choice:hover .theme-choice-body{transform:translateY(-2px);border-color:color-mix(in srgb,var(--primary) 58%,var(--line));box-shadow:0 12px 28px color-mix(in srgb,var(--dark) 15%,transparent)}
        .theme-choice input:focus-visible+.theme-choice-body{outline:3px solid color-mix(in srgb,var(--primary) 28%,transparent);outline-offset:3px}
        .theme-choice input:checked+.theme-choice-body{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 7%,var(--surface));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 20%,transparent),0 12px 28px color-mix(in srgb,var(--dark) 12%,transparent)}
        .theme-preview{height:61px;display:grid;grid-template-columns:1.15fr .82fr .65fr;overflow:hidden;border:1px solid rgba(0,0,0,.16);border-radius:11px;background:var(--preview-bg)}
        .theme-preview span:nth-child(1){background:var(--preview-dark)}
        .theme-preview span:nth-child(2){background:var(--preview-primary)}
        .theme-preview span:nth-child(3){background:var(--preview-accent)}
        .theme-choice-copy{min-width:0}
        .theme-choice-copy strong,.theme-choice-copy small{display:block}
        .theme-choice-copy strong{color:var(--heading);font-size:14px;line-height:1.25}
        .theme-choice-copy small{margin-top:5px;color:var(--muted);font-size:12px;font-weight:600;line-height:1.45}
        .theme-selected{position:absolute;top:22px;right:22px;width:25px;height:25px;display:none;place-items:center;border:2px solid #fff;border-radius:50%;color:#fff;background:var(--primary);box-shadow:0 3px 10px rgba(0,0,0,.22);font-size:14px;font-weight:950}
        .theme-choice input:checked~.theme-selected{display:grid}
        .theme-actions{position:sticky;bottom:0;z-index:3;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:15px 17px;border:1px solid var(--line);border-radius:15px;background:color-mix(in srgb,var(--surface) 94%,transparent);box-shadow:0 -8px 28px color-mix(in srgb,var(--dark) 14%,transparent);backdrop-filter:blur(10px)}
        .theme-note{margin:0;color:var(--muted);font-size:12px;font-weight:650;line-height:1.45}
        .theme-save{min-height:43px;flex:0 0 auto;padding:10px 18px;border:1px solid var(--primary);border-radius:11px;color:var(--on-primary);background:linear-gradient(145deg,var(--primary),color-mix(in srgb,var(--primary) 72%,var(--accent)));box-shadow:0 8px 20px color-mix(in srgb,var(--primary) 24%,transparent);cursor:pointer;font-weight:850}
        .theme-save:hover{transform:translateY(-1px);filter:brightness(1.04)}
        .alert{margin-top:15px;padding:11px 13px;border:1px solid;border-radius:11px;font-size:13px;font-weight:750}
        .alert.ok{border-color:#55b987;color:#075f3b;background:#eaf9f1}
        .alert.error{border-color:#e8a9a4;color:#982017;background:#fff1f0}
        .appearance-modal-view .appearance-page{padding:16px}
        .appearance-modal-view .appearance-intro h1{display:none}
        .appearance-modal-view .appearance-intro p{margin:0}
        @media(max-width:820px){.theme-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:560px){.appearance-page,.appearance-modal-view .appearance-page{padding:11px}.appearance-intro{padding:13px}.appearance-intro-icon{width:38px;height:38px}.appearance-intro p{font-size:12px}.theme-grid{grid-template-columns:1fr;gap:11px}.theme-choice-body{min-height:140px}.theme-actions{align-items:stretch;flex-direction:column}.theme-save{width:100%}}
    </style>
</head>
<body class="<?= $esModalApariencia ? 'appearance-modal-view' : '' ?>">
<main class="appearance-page">
    <section class="appearance-shell" aria-labelledby="titulo-apariencia">
        <header class="appearance-intro">
            <span class="appearance-intro-icon" aria-hidden="true">◐</span>
            <div>
                <h1 id="titulo-apariencia">Cambiar apariencia</h1>
                <p>Elija la combinación que prefiera. Se aplicará en todos sus módulos y será visible únicamente en su cuenta.</p>
            </div>
        </header>

        <?php if (is_array($mensaje)): ?>
            <div class="alert <?= escaparApariencia($mensaje['tipo'] ?? 'error') ?>" role="status">
                <?= escaparApariencia($mensaje['texto'] ?? '') ?>
            </div>
        <?php endif; ?>

        <form class="theme-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= escaparApariencia(seguridadTokenCsrf()) ?>">
            <input type="hidden" name="accion" value="cambiar_tema">
            <div class="theme-grid" role="radiogroup" aria-label="Combinaciones de color disponibles">
                <?php foreach ($temas as $claveTema => $tema): ?>
                    <?php $seleccionado = $claveTema === $temaActual; ?>
                    <label class="theme-choice">
                        <input
                            type="radio"
                            name="tema"
                            value="<?= escaparApariencia($claveTema) ?>"
                            data-theme-choice
                            data-primary="<?= escaparApariencia($tema['primary']) ?>"
                            data-dark="<?= escaparApariencia($tema['dark']) ?>"
                            data-accent="<?= escaparApariencia($tema['accent']) ?>"
                            data-background="<?= escaparApariencia($tema['background']) ?>"
                            data-surface="<?= escaparApariencia($tema['surface']) ?>"
                            data-soft="<?= escaparApariencia($tema['soft']) ?>"
                            data-text="<?= escaparApariencia($tema['text']) ?>"
                            data-heading="<?= escaparApariencia($tema['heading']) ?>"
                            data-muted="<?= escaparApariencia($tema['muted']) ?>"
                            data-border="<?= escaparApariencia($tema['border']) ?>"
                            data-on-primary="<?= escaparApariencia($tema['on_primary']) ?>"
                            data-scheme="<?= escaparApariencia($tema['scheme']) ?>"
                            <?= $seleccionado ? 'checked' : '' ?>
                        >
                        <span class="theme-choice-body">
                            <span
                                class="theme-preview"
                                style="--preview-dark:<?= escaparApariencia($tema['dark']) ?>;--preview-primary:<?= escaparApariencia($tema['primary']) ?>;--preview-accent:<?= escaparApariencia($tema['accent']) ?>;--preview-bg:<?= escaparApariencia($tema['background']) ?>"
                                aria-hidden="true"
                            ><span></span><span></span><span></span></span>
                            <span class="theme-choice-copy">
                                <strong><?= escaparApariencia($tema['nombre']) ?></strong>
                                <small><?= escaparApariencia($tema['descripcion']) ?></small>
                            </span>
                        </span>
                        <span class="theme-selected" aria-hidden="true">✓</span>
                    </label>
                <?php endforeach; ?>
            </div>
            <footer class="theme-actions">
                <p class="theme-note">La configuración es privada: no cambia la apariencia de otros usuarios.</p>
                <button class="theme-save" type="submit">Guardar apariencia</button>
            </footer>
        </form>
    </section>
</main>
<script>
(function(){
    'use strict';
    document.querySelectorAll('[data-theme-choice]').forEach(function(option){
        option.addEventListener('change',function(){
            if(!option.checked)return;
            var root=document.documentElement;
            root.style.setProperty('--primary',option.dataset.primary||'');
            root.style.setProperty('--dark',option.dataset.dark||'');
            root.style.setProperty('--accent',option.dataset.accent||'');
            root.style.setProperty('--page',option.dataset.background||'');
            root.style.setProperty('--surface',option.dataset.surface||'');
            root.style.setProperty('--soft',option.dataset.soft||'');
            root.style.setProperty('--text',option.dataset.text||'');
            root.style.setProperty('--heading',option.dataset.heading||'');
            root.style.setProperty('--muted',option.dataset.muted||'');
            root.style.setProperty('--line',option.dataset.border||'');
            root.style.setProperty('--on-primary',option.dataset.onPrimary||'');
            root.style.colorScheme=option.dataset.scheme||'light';
        });
    });

    if(window.parent!==window){
        ['pointerdown','keydown','scroll','touchstart'].forEach(function(eventName){
            window.addEventListener(eventName,function(){
                window.parent.postMessage({type:'mesa-profile-activity'},window.location.origin);
            },{passive:true});
        });
    }
}());
</script>
<?php if ($temaActualizado !== ''): ?>
<script>
if(window.parent!==window){window.parent.postMessage({tipo:'mesa-theme-updated',tema:<?= json_encode($temaActualizado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>},window.location.origin)}
</script>
<?php endif; ?>
</body>
</html>
