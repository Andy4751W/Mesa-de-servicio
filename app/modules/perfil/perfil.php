<?php
declare(strict_types=1);

// El perfil del administrador es global y debe abrir desde el selector,
// incluso antes de elegir Colombia o Perú.
if (!defined('MESA_PERMITE_SIN_PAIS')) {
    define('MESA_PERMITE_SIN_PAIS', true);
}

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/perfil.php';
require_once APP_ROOT . '/core/temasInterfaz.php';

seguridadExigirRol([1, 2, 3]);
$rolSesion = (int) ($_SESSION['rol'] ?? 0);
$idPaisOperacion = $rolSesion === 1
    ? paisContextoId()
    : paisExigirContexto();
$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
$codigoPaisTema = paisContextoCodigo() ?: 'CO';

function escaparPerfil(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function perfilRedirigir(string $tipo, string $texto): never
{
    $_SESSION['perfil_mensaje'] = ['tipo' => $tipo, 'texto' => $texto];
    $destino = (string) ($_GET['modal'] ?? '') === '1'
        ? 'perfil.php?modal=1'
        : 'perfil.php';
    header('Location: ' . $destino, true, 303);
    exit;
}

$stmt = $conn->prepare(
    "SELECT
        u.cedula,
        u.nombre,
        u.email,
        u.proceso,
        u.empresa,
        u.password,
        u.id_rol,
        CASE WHEN u.id_rol = 1 THEN 'Todos los países' ELSE COALESCE(pais.nombre, 'Sin asignar') END AS pais,
        CASE WHEN u.id_rol = 1 THEN 'No aplica' ELSE COALESCE(departamento.nombre, 'Sin asignar') END AS departamento,
        CASE WHEN u.id_rol = 1 THEN 'No aplica' ELSE COALESCE(ciudad.nombre, NULLIF(u.ciudad, ''), 'Sin asignar') END AS ciudad
     FROM usuarios AS u
     LEFT JOIN configuraciones_servicio AS pais
        ON pais.id_opcion = u.id_pais AND pais.tipo = 'pais'
     LEFT JOIN configuraciones_servicio AS departamento
        ON departamento.id_opcion = u.id_departamento AND departamento.tipo = 'departamento'
     LEFT JOIN configuraciones_servicio AS ciudad
        ON ciudad.id_opcion = u.id_ciudad AND ciudad.tipo = 'ciudad'
     WHERE u.id_usuario = ?
       AND (u.id_rol = 1 OR (u.id_pais_operacion = ? AND u.id_rol IN (2, 3)))
       AND u.estado = 'activo'
     LIMIT 1"
);
$stmt->bind_param('ii', $idUsuario, $idPaisOperacion);
$stmt->execute();
$usuarioPerfil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuarioPerfil) {
    http_response_code(403);
    exit('No fue posible acceder al perfil solicitado.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    try {
        if ($accion === 'actualizar_foto') {
            perfilGuardarImagen($idUsuario, $_FILES['foto_perfil'] ?? []);
            perfilRedirigir('ok', 'La imagen de perfil se actualizó correctamente.');
        }

        if ($accion === 'cambiar_password') {
            $passwordActual = (string) ($_POST['password_actual'] ?? '');
            $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
            $passwordConfirmacion = (string) ($_POST['password_confirmacion'] ?? '');

            if (
                $passwordActual === ''
                || strlen($passwordActual) > 1024
                || !password_verify($passwordActual, (string) $usuarioPerfil['password'])
            ) {
                throw new DomainException('La contraseña actual no es correcta.');
            }

            if (!seguridadPasswordValida($passwordNueva)) {
                throw new DomainException('La nueva contraseña debe tener mínimo 8 caracteres, una mayúscula y un número.');
            }

            if (!hash_equals($passwordNueva, $passwordConfirmacion)) {
                throw new DomainException('La nueva contraseña y su confirmación no coinciden.');
            }

            if (password_verify($passwordNueva, (string) $usuarioPerfil['password'])) {
                throw new DomainException('La nueva contraseña debe ser diferente de la actual.');
            }

            $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
            $actualizar = $conn->prepare(
                "UPDATE usuarios
                 SET password = ?
                 WHERE id_usuario = ?
                   AND (id_rol = 1 OR (id_pais_operacion = ? AND id_rol IN (2, 3)))"
            );
            $actualizar->bind_param('sii', $hash, $idUsuario, $idPaisOperacion);
            $actualizar->execute();
            $actualizado = $actualizar->affected_rows === 1;
            $actualizar->close();

            if (!$actualizado) {
                throw new RuntimeException('No fue posible actualizar la contraseña.');
            }

            session_regenerate_id(true);
            $_SESSION['ultima_rotacion'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            perfilRedirigir('ok', 'La contraseña se cambió correctamente.');
        }

        throw new DomainException('La acción solicitada no está disponible.');
    } catch (DomainException $e) {
        perfilRedirigir('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Error al administrar el perfil: ' . $e->getMessage());
        perfilRedirigir('error', 'No fue posible guardar el cambio. Inténtelo nuevamente.');
    }
}

$mensaje = $_SESSION['perfil_mensaje'] ?? null;
unset($_SESSION['perfil_mensaje']);
$temaActual = temaInterfazUsuario(
    $conn,
    $idUsuario,
    $codigoPaisTema
);
$paletaPerfil = temaInterfazResolver($temaActual, $codigoPaisTema);
$rolNombre = match ((int) $usuarioPerfil['id_rol']) {
    1 => 'Administrador',
    2 => 'Gestor',
    default => 'Solicitante',
};
$nombreContextoPerfil = $rolSesion === 1 && $idPaisOperacion < 1
    ? 'Todos los países'
    : paisContextoNombre();
$color = $paletaPerfil['primary'];
$colorOscuro = $paletaPerfil['dark'];
$versionImagen = perfilImagenActual($idUsuario)['modificado'] ?? time();
$esModalPerfil = (string) ($_GET['modal'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar perfil | Mesa de Servicio</title>
    <style>
        :root{--primary:<?= escaparPerfil($color) ?>;--primary-dark:<?= escaparPerfil($colorOscuro) ?>;--ink:#14273b;--text:#425d76;--muted:#6e8295;--line:#dce6ef;--soft:#f3f7fb;--danger:#b42318;--ok:#087443}*{box-sizing:border-box}body{margin:0;color:var(--text);background:radial-gradient(circle at 50% 0,color-mix(in srgb,var(--primary) 10%,#fff),transparent 36%),#eef3f7;font:13px/1.45 Inter,"Segoe UI",Arial,sans-serif}.profile-page{min-height:100vh;display:grid;place-items:center;padding:24px 14px}.profile-card{width:min(760px,100%);overflow:hidden;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 24px 70px rgba(19,48,74,.13)}.profile-head{position:relative;padding:26px 24px 20px;color:#fff;text-align:center;background:linear-gradient(135deg,var(--primary-dark),var(--primary))}.profile-head::after{position:absolute;inset:auto -50px -90px auto;width:180px;height:180px;border:1px solid rgba(255,255,255,.18);border-radius:50%;content:""}.profile-head h1{margin:0;font-size:22px}.profile-head p{margin:4px 0 0;color:rgba(255,255,255,.8);font-size:11px}.profile-body{padding:0 24px 24px}.photo-form{position:relative;z-index:2;display:grid;justify-items:center;margin-top:-4px;text-align:center}.avatar-shell{width:112px;height:112px;margin-top:-56px;padding:4px;border-radius:50%;background:#fff;box-shadow:0 12px 32px rgba(16,42,67,.2)}.avatar-shell img{width:100%;height:100%;display:block;border-radius:50%;object-fit:cover;background:var(--soft)}.photo-name{margin:10px 0 0;color:var(--ink);font-size:16px;font-weight:850}.photo-role{margin:2px 0 10px;color:var(--muted);font-size:10px}.photo-actions{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap}.file-label,.button{min-height:36px;display:inline-flex;align-items:center;justify-content:center;padding:8px 13px;border:1px solid #cbdbea;border-radius:9px;cursor:pointer;text-decoration:none;font:800 11px/1 Inter,"Segoe UI",Arial,sans-serif}.file-label{color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,#fff)}.button{border-color:var(--primary);color:#fff;background:var(--primary)}.button:hover,.file-label:hover{transform:translateY(-1px)}.photo-help{margin:8px 0 0;color:var(--muted);font-size:9px}.readonly-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:20px;padding:12px;border:1px solid var(--line);border-radius:13px;background:var(--soft)}.readonly-item{min-width:0;padding:7px 9px}.readonly-item span{display:block;color:var(--muted);font-size:9px;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.readonly-item strong{display:block;overflow:hidden;margin-top:3px;color:var(--ink);font-size:11px;text-overflow:ellipsis;white-space:nowrap}.security{margin-top:16px;padding-top:17px;border-top:1px solid var(--line)}.section-title{display:flex;align-items:flex-start;gap:10px;margin-bottom:13px}.section-icon{width:34px;height:34px;display:grid;flex:0 0 auto;place-items:center;border-radius:10px;color:var(--primary);background:color-mix(in srgb,var(--primary) 9%,#fff);font-size:15px}.section-title h2{margin:0;color:var(--ink);font-size:16px}.section-title p{margin:2px 0 0;color:var(--muted);font-size:10px}.password-grid{display:grid;grid-template-columns:1fr 1fr;gap:11px}.field{display:grid;gap:5px}.field.current{grid-column:1/-1}.field label{color:var(--ink);font-size:10px;font-weight:850}.field input{width:100%;min-height:40px;padding:9px 11px;border:1px solid #cad9e7;border-radius:9px;color:var(--ink);background:#fff;outline:none}.field input:focus{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 13%,transparent)}.rule{grid-column:1/-1;margin:0;color:var(--muted);font-size:9px}.form-actions{grid-column:1/-1;display:flex;justify-content:flex-end;margin-top:2px}.alert{margin:14px 0 0;padding:10px 12px;border:1px solid;border-radius:10px;font-size:11px;font-weight:750}.alert.ok{border-color:#b8e2cb;color:var(--ok);background:#eefaf3}.alert.error{border-color:#efc3c0;color:var(--danger);background:#fff3f2}.profile-modal-view{background:#eef3f7}.profile-modal-view .profile-page{min-height:0;place-items:start center;padding:12px}.profile-modal-view .profile-card{border:0;border-radius:13px;box-shadow:none}.profile-modal-view .profile-head{display:none}.profile-modal-view .profile-body{padding:12px 18px 18px}.profile-modal-view .photo-form{margin-top:0}.profile-modal-view .avatar-shell{width:88px;height:88px;margin-top:0;box-shadow:0 8px 24px rgba(16,42,67,.16)}.profile-modal-view .photo-name{margin-top:7px}.profile-modal-view .readonly-grid{margin-top:13px}@media(max-width:620px){.profile-page{padding:9px}.profile-head{padding:22px 15px 66px}.profile-body{padding:0 14px 18px}.readonly-grid,.password-grid{grid-template-columns:1fr}.field.current{grid-column:auto}.readonly-item strong{white-space:normal}.form-actions{grid-column:auto}.form-actions .button{width:100%}.profile-modal-view .profile-page{padding:7px}.profile-modal-view .profile-body{padding:10px 12px 14px}}
        :root{
            --theme-accent:<?= escaparPerfil($paletaPerfil['accent']) ?>;
            --theme-bg:<?= escaparPerfil($paletaPerfil['background']) ?>;
            --theme-surface:<?= escaparPerfil($paletaPerfil['surface']) ?>;
            --theme-soft:<?= escaparPerfil($paletaPerfil['soft']) ?>;
            --theme-on-primary:<?= escaparPerfil($paletaPerfil['on_primary']) ?>;
            --soft:var(--theme-soft);
            --ink:<?= escaparPerfil($paletaPerfil['heading']) ?>;
            --text:<?= escaparPerfil($paletaPerfil['text']) ?>;
            --muted:<?= escaparPerfil($paletaPerfil['muted']) ?>;
            --line:<?= escaparPerfil($paletaPerfil['border']) ?>;
        }
        html{color-scheme:<?= escaparPerfil($paletaPerfil['scheme']) ?>;background:var(--theme-bg)}
        body,.profile-modal-view{background:radial-gradient(circle at 50% 0,color-mix(in srgb,var(--primary) 10%,var(--theme-surface)),transparent 36%),var(--theme-bg)}
        .profile-card,.profile-modal-view .profile-card{background:var(--theme-surface)}
        .profile-head{background:linear-gradient(135deg,var(--primary-dark),color-mix(in srgb,var(--primary) 46%,var(--primary-dark)) 68%,color-mix(in srgb,var(--theme-accent) 50%,var(--primary-dark)))}
        .avatar-shell{background:var(--theme-surface)}
        .avatar-shell img,.readonly-grid{background:var(--theme-soft)}
        .file-label{border-color:var(--line);color:var(--primary);background:var(--theme-soft)}
        .button{color:var(--theme-on-primary);background:var(--primary)}
        .field input{border-color:var(--line);color:var(--text);background:color-mix(in srgb,var(--theme-surface) 70%,var(--theme-soft))}
        .field input::placeholder{color:var(--muted)}
    </style>
</head>
<body class="<?= $esModalPerfil ? 'profile-modal-view' : '' ?>">
<main class="profile-page">
    <section class="profile-card" aria-labelledby="titulo-perfil">
        <header class="profile-head">
            <h1 id="titulo-perfil">Administrar perfil</h1>
            <p>Su información personal es de solo lectura.</p>
        </header>

        <div class="profile-body">
            <form class="photo-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= escaparPerfil($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="accion" value="actualizar_foto">
                <div class="avatar-shell">
                    <img id="profile-preview" src="imagenPerfil.php?v=<?= (int) $versionImagen ?>" alt="Imagen de perfil de <?= escaparPerfil($usuarioPerfil['nombre']) ?>">
                </div>
                <p class="photo-name"><?= escaparPerfil($usuarioPerfil['nombre']) ?></p>
                <p class="photo-role"><?= escaparPerfil($rolNombre) ?> · <?= escaparPerfil($nombreContextoPerfil) ?></p>
                <div class="photo-actions">
                    <label class="file-label" for="foto_perfil">Elegir imagen</label>
                    <input id="foto_perfil" type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp" hidden required>
                    <button class="button" type="submit">Guardar imagen</button>
                </div>
                <p class="photo-help">Formatos JPG, PNG o WebP · máximo 3 MB.</p>
            </form>

            <?php if (is_array($mensaje)): ?>
                <div class="alert <?= escaparPerfil($mensaje['tipo'] ?? 'error') ?>" role="status">
                    <?= escaparPerfil($mensaje['texto'] ?? '') ?>
                </div>
            <?php endif; ?>

            <section class="readonly-grid" aria-label="Información del usuario">
                <div class="readonly-item"><span>Documento</span><strong><?= escaparPerfil($usuarioPerfil['cedula']) ?></strong></div>
                <div class="readonly-item"><span>Correo</span><strong title="<?= escaparPerfil($usuarioPerfil['email']) ?>"><?= escaparPerfil($usuarioPerfil['email']) ?></strong></div>
                <div class="readonly-item"><span>Proceso</span><strong><?= escaparPerfil($usuarioPerfil['proceso']) ?></strong></div>
                <div class="readonly-item"><span>Empresa</span><strong><?= escaparPerfil($usuarioPerfil['empresa']) ?></strong></div>
                <div class="readonly-item"><span>País</span><strong><?= escaparPerfil($usuarioPerfil['pais']) ?></strong></div>
                <div class="readonly-item"><span>Departamento</span><strong><?= escaparPerfil($usuarioPerfil['departamento']) ?></strong></div>
                <div class="readonly-item"><span>Ciudad</span><strong><?= escaparPerfil($usuarioPerfil['ciudad']) ?></strong></div>
                <div class="readonly-item"><span>Rol</span><strong><?= escaparPerfil($rolNombre) ?></strong></div>
            </section>

            <section class="security" aria-labelledby="titulo-password">
                <div class="section-title">
                    <span class="section-icon" aria-hidden="true">◆</span>
                    <div><h2 id="titulo-password">Cambiar contraseña</h2><p>Confirme primero su contraseña actual.</p></div>
                </div>
                <form class="password-grid" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escaparPerfil($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="cambiar_password">
                    <div class="field current">
                        <label for="password_actual">Contraseña actual</label>
                        <input id="password_actual" type="password" name="password_actual" maxlength="1024" autocomplete="current-password" required>
                    </div>
                    <div class="field">
                        <label for="password_nueva">Nueva contraseña</label>
                        <input id="password_nueva" type="password" name="password_nueva" minlength="8" maxlength="128" pattern="(?=.*[A-ZÁÉÍÓÚÑÜ])(?=.*[0-9]).{8,128}" autocomplete="new-password" required>
                    </div>
                    <div class="field">
                        <label for="password_confirmacion">Confirmar contraseña</label>
                        <input id="password_confirmacion" type="password" name="password_confirmacion" minlength="8" maxlength="128" autocomplete="new-password" required>
                    </div>
                    <p class="rule">Mínimo 8 caracteres, al menos una letra mayúscula y un número.</p>
                    <div class="form-actions"><button class="button" type="submit">Actualizar contraseña</button></div>
                </form>
            </section>
        </div>
    </section>
</main>
<script>
(function(){
    var input=document.getElementById('foto_perfil');
    var preview=document.getElementById('profile-preview');
    if(input&&preview){
        input.addEventListener('change',function(){
            var file=input.files&&input.files[0];
            if(!file)return;
            var url=URL.createObjectURL(file);
            preview.src=url;
            preview.onload=function(){URL.revokeObjectURL(url)};
        });
    }
    if(window.parent!==window){
        ['pointerdown','keydown','scroll','touchstart'].forEach(function(eventName){
            window.addEventListener(eventName,function(){
                window.parent.postMessage({type:'mesa-profile-activity'},window.location.origin);
            },{passive:true});
        });
    }
}());
</script>
<?php if (is_array($mensaje) && ($mensaje['tipo'] ?? '') === 'ok'): ?>
<script>
if(window.parent!==window){window.parent.postMessage({tipo:'mesa-profile-updated'},window.location.origin)}
</script>
<?php endif; ?>
</body>
</html>
