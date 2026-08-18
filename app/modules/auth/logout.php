<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/seguridad.php';

seguridadAplicarCabeceras(true);
seguridadIniciarSesion();
seguridadCerrarSesion();

$motivo = (string) ($_GET['motivo'] ?? '');
$motivosPermitidos = ['inactividad', 'tiempo_maximo', 'sesion_expirada'];
$destino = in_array($motivo, $motivosPermitidos, true)
    ? 'login.html?error=' . rawurlencode($motivo)
    : 'login.html?sesion=cerrada';

/*
 * La redirección PHP es el mecanismo principal. El contenido HTML,
 * JavaScript y meta refresh funcionan como respaldo si el servidor
 * ya había enviado contenido antes de ejecutar este archivo.
 */
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ' . $destino, true, 302);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="1;url=<?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>">
    <title>Cerrando sesión | Mesa de Servicio</title>
    <style>
        * { box-sizing: border-box; }
        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            place-items: center;
            padding: 24px;
            color: #243b53;
            background:
                radial-gradient(
                    circle at 8% 0%,
                    rgba(15, 111, 236, 0.12),
                    transparent 28%
                ),
                #f3f6fb;
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
        }
        .logout-card {
            width: min(420px, 100%);
            padding: 34px;
            border: 1px solid #e3eaf3;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(15, 45, 75, 0.12);
            text-align: center;
        }
        .icon {
            display: grid;
            width: 58px;
            height: 58px;
            margin: 0 auto 18px;
            place-items: center;
            border-radius: 17px;
            color: #fff;
            background: linear-gradient(145deg, #0f7af5, #0b4fae);
            box-shadow: 0 10px 22px rgba(15, 111, 236, 0.25);
            font-weight: 800;
        }
        h1 {
            margin: 0;
            color: #102a43;
            font-size: 24px;
        }
        p {
            margin: 9px 0 22px;
            color: #526d82;
            font-size: 14px;
        }
        a {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 10px 17px;
            border-radius: 10px;
            color: #fff;
            background: #0f6fec;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }
    </style>
    <script>
        window.location.replace(
            <?= json_encode($destino, JSON_UNESCAPED_SLASHES) ?>
        );
    </script>
</head>
<body>
    <main class="logout-card">
        <div class="icon" aria-hidden="true">MS</div>
        <h1>Sesión cerrada</h1>
        <p>Está siendo redirigido a la página de inicio de sesión.</p>
        <a href="<?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>">
            Ir al inicio de sesión
        </a>
    </main>
</body>
</html>
