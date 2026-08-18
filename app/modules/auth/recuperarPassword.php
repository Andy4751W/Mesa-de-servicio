<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/seguridad.php';

seguridadAplicarCabeceras(true);
seguridadIniciarSesion();

require_once APP_ROOT . '/config/conexion.php';
require_once APP_ROOT . '/core/recuperacionPassword.php';

function escaparRecuperacion(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function redirigirRecuperacion(string $paso, string $estado = ''): never
{
    $paso = in_array($paso, ['correo', 'codigo'], true) ? $paso : 'correo';
    $url = 'recuperarPassword.php?paso=' . rawurlencode($paso);

    if ($estado !== '') {
        $url .= '&estado=' . rawurlencode($estado);
    }

    header('Location: ' . $url, true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    seguridadExigirOrigenPost();
    seguridadExigirCsrfPost();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'reiniciar') {
        unset(
            $_SESSION['recuperacion_email'],
            $_SESSION['recuperacion_solicitada_en']
        );
        redirigirRecuperacion('correo');
    }

    if ($accion === 'solicitar') {
        $correo = strtolower(seguridadTexto($_POST['email'] ?? '', 190));

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            redirigirRecuperacion('correo', 'correo_invalido');
        }

        try {
            recuperacionSolicitarCodigo($conn, $correo);
        } catch (Throwable $e) {
            error_log(
                'No fue posible iniciar la recuperación de contraseña: '
                . $e->getMessage()
            );
            redirigirRecuperacion('correo', 'servicio_no_disponible');
        }

        $_SESSION['recuperacion_email'] = $correo;
        $_SESSION['recuperacion_solicitada_en'] = time();
        redirigirRecuperacion('codigo', 'codigo_enviado');
    }

    if ($accion === 'cambiar') {
        $correo = strtolower(trim((string) (
            $_SESSION['recuperacion_email'] ?? ''
        )));
        $codigoEntrada = (string) ($_POST['codigo'] ?? '');
        $codigo = strlen($codigoEntrada) <= 32
            ? preg_replace('/\D+/', '', $codigoEntrada)
            : '';
        $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
        $passwordConfirmacion = (string) (
            $_POST['password_confirmacion'] ?? ''
        );

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            redirigirRecuperacion('correo', 'sesion_vencida');
        }

        if (!is_string($codigo) || strlen($codigo) !== 6) {
            redirigirRecuperacion('codigo', 'codigo_invalido');
        }

        if (!seguridadPasswordValida($passwordNueva)) {
            redirigirRecuperacion('codigo', 'password_invalido');
        }

        if (!hash_equals($passwordNueva, $passwordConfirmacion)) {
            redirigirRecuperacion('codigo', 'password_no_coincide');
        }

        try {
            $resultado = recuperacionCambiarPassword(
                $conn,
                $correo,
                $codigo,
                $passwordNueva
            );
        } catch (Throwable $e) {
            error_log(
                'No fue posible completar la recuperación de contraseña: '
                . $e->getMessage()
            );
            redirigirRecuperacion('codigo', 'servicio_no_disponible');
        }

        if ($resultado === 'password_repetido') {
            redirigirRecuperacion('codigo', 'password_repetido');
        }

        if ($resultado !== 'ok') {
            redirigirRecuperacion('codigo', 'codigo_invalido');
        }

        unset(
            $_SESSION['recuperacion_email'],
            $_SESSION['recuperacion_solicitada_en']
        );
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: login.html?recuperacion=ok', true, 303);
        exit;
    }

    redirigirRecuperacion('correo', 'solicitud_invalida');
}

$paso = (string) ($_GET['paso'] ?? 'correo');
$correoSesion = strtolower(trim((string) (
    $_SESSION['recuperacion_email'] ?? ''
)));

if ($paso === 'codigo' && !filter_var($correoSesion, FILTER_VALIDATE_EMAIL)) {
    $paso = 'correo';
}

$estados = [
    'correo_invalido' => ['error', 'Ingrese un correo electrónico válido.'],
    'servicio_no_disponible' => [
        'error',
        'El servicio de recuperación no está disponible temporalmente. Intente más tarde.'
    ],
    'sesion_vencida' => [
        'error',
        'La solicitud venció. Ingrese nuevamente su correo electrónico.'
    ],
    'solicitud_invalida' => [
        'error',
        'La solicitud no es válida. Actualice la página e inténtelo nuevamente.'
    ],
    'codigo_enviado' => [
        'ok',
        'Si el correo pertenece a una cuenta activa, recibirá un código en los próximos minutos.'
    ],
    'codigo_invalido' => [
        'error',
        'El código es incorrecto, venció o alcanzó el límite de intentos. Puede solicitar uno nuevo.'
    ],
    'password_invalido' => [
        'error',
        'La nueva contraseña debe tener mínimo 8 caracteres, una mayúscula y un número.'
    ],
    'password_no_coincide' => [
        'error',
        'La nueva contraseña y su confirmación no coinciden.'
    ],
    'password_repetido' => [
        'error',
        'La nueva contraseña debe ser diferente de la contraseña actual.'
    ],
];
$estado = (string) ($_GET['estado'] ?? '');
$mensaje = $estados[$estado] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar contraseña | Mesa de Servicio</title>
  <style>
    :root {
      color-scheme: light;
      --navy: #123d78;
      --blue: #2563a9;
      --ink: #162238;
      --muted: #637086;
      --line: #d8e1ec;
      --soft: #eef4fb;
      --danger: #b42318;
      --success: #137a4b;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 16px;
      background: linear-gradient(145deg, #edf3fa 0%, #f8fafc 54%, #e6edf6 100%);
      color: var(--ink);
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .card {
      width: min(100%, 470px);
      overflow: hidden;
      background: #fff;
      border: 1px solid rgba(164, 180, 201, .6);
      border-radius: 18px;
      box-shadow: 0 24px 60px rgba(23, 49, 83, .15);
    }
    .brand {
      padding: 15px 28px;
      background: linear-gradient(135deg, #0e3266, var(--navy));
      color: #fff;
    }
    .brand strong { display: block; font-size: 21px; letter-spacing: -.02em; }
    .brand span { display: block; margin-top: 4px; color: #cfe0f7; font-size: 14px; }
    .content { padding: 21px 28px 20px; }
    h1 { margin: 0 0 7px; font-size: 27px; line-height: 1.15; letter-spacing: -.035em; }
    .intro { margin: 0 0 14px; color: var(--muted); line-height: 1.45; }
    .alert {
      margin: 0 0 14px;
      padding: 10px 14px;
      border: 1px solid;
      border-radius: 12px;
      font-size: 14px;
      line-height: 1.45;
    }
    .alert.error { color: var(--danger); background: #fff2f0; border-color: #f3c2bd; }
    .alert.ok { color: var(--success); background: #edf9f3; border-color: #bce4ce; }
    .field { margin-bottom: 13px; }
    label { display: block; margin-bottom: 5px; font-size: 14px; font-weight: 700; }
    input {
      width: 100%;
      min-height: 42px;
      border: 1px solid var(--line);
      border-radius: 11px;
      padding: 8px 12px;
      color: var(--ink);
      background: #fff;
      font: inherit;
      outline: none;
      transition: border-color .18s ease, box-shadow .18s ease;
    }
    input:focus { border-color: var(--blue); box-shadow: 0 0 0 4px rgba(37, 99, 169, .12); }
    input.code { text-align: center; font-size: 24px; font-weight: 800; letter-spacing: 8px; }
    .hint { margin: 5px 0 0; color: var(--muted); font-size: 12px; line-height: 1.4; }
    .button {
      width: 100%;
      min-height: 43px;
      border: 0;
      border-radius: 11px;
      padding: 9px 18px;
      background: linear-gradient(135deg, var(--navy), var(--blue));
      color: #fff;
      font: inherit;
      font-weight: 800;
      cursor: pointer;
    }
    .button:hover { filter: brightness(1.06); }
    .button:disabled { cursor: wait; opacity: .7; }
    .secondary {
      width: auto;
      min-height: auto;
      padding: 0;
      border: 0;
      background: transparent;
      color: var(--blue);
      font: inherit;
      font-weight: 700;
      cursor: pointer;
    }
    .actions { display: flex; justify-content: center; margin-top: 14px; }
    .back { display: inline-flex; margin-top: 16px; color: var(--navy); font-size: 14px; font-weight: 750; text-decoration: none; }
    .back:hover { text-decoration: underline; }
    .masked { margin: 0 0 14px; padding: 9px 13px; border-radius: 10px; background: var(--soft); color: var(--navy); font-size: 14px; }
    @media (max-width: 520px) {
      body { padding: 10px; background: #fff; }
      .card { border-radius: 16px; box-shadow: none; }
      .content, .brand { padding-left: 22px; padding-right: 22px; }
    }
  </style>
</head>
<body>
  <main class="card">
    <header class="brand">
      <strong>Mesa de Servicio</strong>
      <span>Recuperación segura de acceso</span>
    </header>
    <section class="content">
      <?php if ($paso === 'codigo'): ?>
        <h1>Ingrese el código</h1>
        <p class="intro">Revise su correo y establezca una nueva contraseña.</p>
        <p class="masked">Código enviado a <?= escaparRecuperacion(recuperacionEnmascararCorreo($correoSesion)) ?></p>
      <?php else: ?>
        <h1>Recuperar contraseña</h1>
        <p class="intro">Ingrese el correo registrado en la plataforma. Si la cuenta está activa, enviaremos un código temporal.</p>
      <?php endif; ?>

      <?php if (is_array($mensaje)): ?>
        <div class="alert <?= escaparRecuperacion($mensaje[0]) ?>" role="alert">
          <?= escaparRecuperacion($mensaje[1]) ?>
        </div>
      <?php endif; ?>

      <?php if ($paso === 'codigo'): ?>
        <form method="post" autocomplete="off" data-submit-form>
          <input type="hidden" name="csrf_token" value="<?= escaparRecuperacion(seguridadTokenCsrf()) ?>">
          <input type="hidden" name="accion" value="cambiar">
          <div class="field">
            <label for="codigo">Código de 6 dígitos</label>
            <input class="code" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
            <p class="hint">Vence en <?= RECUPERACION_VIGENCIA_MINUTOS ?> minutos y admite máximo <?= RECUPERACION_MAX_INTENTOS_CODIGO ?> intentos.</p>
          </div>
          <div class="field">
            <label for="password_nueva">Nueva contraseña</label>
            <input id="password_nueva" type="password" name="password_nueva" autocomplete="new-password" minlength="8" maxlength="128" required>
            <p class="hint">Mínimo 8 caracteres, una mayúscula y un número.</p>
          </div>
          <div class="field">
            <label for="password_confirmacion">Confirmar contraseña</label>
            <input id="password_confirmacion" type="password" name="password_confirmacion" autocomplete="new-password" minlength="8" maxlength="128" required>
          </div>
          <button class="button" type="submit">Cambiar contraseña</button>
        </form>
        <form class="actions" method="post">
          <input type="hidden" name="csrf_token" value="<?= escaparRecuperacion(seguridadTokenCsrf()) ?>">
          <input type="hidden" name="accion" value="reiniciar">
          <button class="secondary" type="submit">Solicitar un código nuevo</button>
        </form>
      <?php else: ?>
        <form method="post" autocomplete="on" data-submit-form>
          <input type="hidden" name="csrf_token" value="<?= escaparRecuperacion(seguridadTokenCsrf()) ?>">
          <input type="hidden" name="accion" value="solicitar">
          <div class="field">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" maxlength="190" autocomplete="email" autocapitalize="none" spellcheck="false" placeholder="nombre@empresa.com" required autofocus>
          </div>
          <button class="button" type="submit">Enviar código</button>
        </form>
      <?php endif; ?>

      <a class="back" href="login.html">← Volver al inicio de sesión</a>
    </section>
  </main>
  <script>
    document.querySelectorAll('[data-submit-form]').forEach(function (form) {
      form.addEventListener('submit', function () {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
          button.disabled = true;
          button.textContent = 'Procesando...';
        }
      });
    });
  </script>
</body>
</html>
