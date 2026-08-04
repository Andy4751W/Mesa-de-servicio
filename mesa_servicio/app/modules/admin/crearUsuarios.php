<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
seguridadExigirRol([1]);

function escaparUsuario(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function volverUsuarios(string $mensaje): never
{
    header(
        'Location: crearUsuarios.php?msg=' . rawurlencode($mensaje),
        true,
        303
    );
    exit;
}

$mensajes = [
    'creado' => ['ok', 'El usuario fue creado correctamente.'],
    'actualizado' => ['ok', 'El estado del usuario fue actualizado.'],
    'datos_incompletos' => ['error', 'Complete todos los campos obligatorios.'],
    'correo_invalido' => ['error', 'El correo electrónico no es válido.'],
    'password_invalido' => ['error', 'La contraseña debe tener entre 12 y 128 caracteres.'],
    'rol_invalido' => ['error', 'Seleccione un rol válido.'],
    'duplicado' => ['error', 'La cédula o el correo ya pertenecen a otro usuario.'],
    'autoproteccion' => ['error', 'No puede inhabilitar su propia cuenta.'],
    'solicitud_invalida' => ['error', 'La solicitud no es válida. Actualice la página.'],
    'error' => ['error', 'No fue posible completar la operación.'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    seguridadExigirOrigenPost();
    seguridadExigirCsrfPost();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        $cedula = seguridadTexto($_POST['cedula'] ?? '', 30);
        $nombre = seguridadTexto($_POST['nombre'] ?? '', 160);
        $proceso = seguridadTexto($_POST['proceso'] ?? '', 120);
        $cu1 = seguridadTexto($_POST['cu1'] ?? '', 120);
        $cu3 = seguridadTexto($_POST['cu3'] ?? '', 120);
        $email = strtolower(seguridadTexto($_POST['email'] ?? '', 190));
        $descripcion = seguridadTexto($_POST['descripcion_cu1'] ?? '', 500);
        $ciudad = seguridadTexto($_POST['ciudad'] ?? '', 120);
        $empresa = seguridadTexto($_POST['empresa'] ?? '', 160);
        $passwordPlano = (string) ($_POST['password'] ?? '');
        $rol = filter_input(INPUT_POST, 'id_rol', FILTER_VALIDATE_INT) ?: 0;

        if (
            $cedula === ''
            || $nombre === ''
            || $proceso === ''
            || $cu1 === ''
            || $cu3 === ''
            || $email === ''
            || $descripcion === ''
            || $ciudad === ''
            || $empresa === ''
        ) {
            volverUsuarios('datos_incompletos');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            volverUsuarios('correo_invalido');
        }

        if (!in_array($rol, [1, 2, 3], true)) {
            volverUsuarios('rol_invalido');
        }

        if (!seguridadPasswordValida($passwordPlano)) {
            volverUsuarios('password_invalido');
        }

        try {
            $password = password_hash($passwordPlano, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO usuarios
                    (
                        cedula,
                        nombre,
                        proceso,
                        cu1,
                        cu3,
                        email,
                        descripcion_cu1,
                        ciudad,
                        empresa,
                        password,
                        id_rol,
                        estado
                    )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')"
            );
            $stmt->bind_param(
                'ssssssssssi',
                $cedula,
                $nombre,
                $proceso,
                $cu1,
                $cu3,
                $email,
                $descripcion,
                $ciudad,
                $empresa,
                $password,
                $rol
            );
            $stmt->execute();
            $stmt->close();
            volverUsuarios('creado');
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                volverUsuarios('duplicado');
            }

            error_log('No fue posible crear el usuario: ' . $e->getMessage());
            volverUsuarios('error');
        } catch (Throwable $e) {
            error_log('No fue posible crear el usuario: ' . $e->getMessage());
            volverUsuarios('error');
        }
    }

    if ($accion === 'estado') {
        $idUsuario = filter_input(
            INPUT_POST,
            'id_usuario',
            FILTER_VALIDATE_INT
        ) ?: 0;
        $estado = (string) ($_POST['estado'] ?? '');

        if ($idUsuario < 1 || !in_array($estado, ['activo', 'inhabilitado'], true)) {
            volverUsuarios('solicitud_invalida');
        }

        if (
            $idUsuario === (int) $_SESSION['usuario_id']
            && $estado !== 'activo'
        ) {
            volverUsuarios('autoproteccion');
        }

        try {
            $stmt = $conn->prepare(
                'UPDATE usuarios SET estado = ? WHERE id_usuario = ?'
            );
            $stmt->bind_param('si', $estado, $idUsuario);
            $stmt->execute();
            $stmt->close();
            volverUsuarios('actualizado');
        } catch (Throwable $e) {
            error_log('No fue posible actualizar el usuario: ' . $e->getMessage());
            volverUsuarios('error');
        }
    }

    volverUsuarios('solicitud_invalida');
}

$usuarios = [];
$resultadoUsuarios = $conn->query(
    "SELECT
        u.id_usuario,
        u.cedula,
        u.nombre,
        u.email,
        u.proceso,
        u.ciudad,
        u.empresa,
        u.id_rol,
        u.estado,
        COALESCE(r.nombre_rol, 'Sin rol') AS rol
     FROM usuarios AS u
     LEFT JOIN roles AS r ON r.id_rol = u.id_rol
     ORDER BY u.estado = 'activo' DESC, u.nombre, u.id_usuario"
);

if ($resultadoUsuarios !== false) {
    $usuarios = $resultadoUsuarios->fetch_all(MYSQLI_ASSOC);
}

$mensajeActual = (string) ($_GET['msg'] ?? '');
$mensaje = $mensajes[$mensajeActual] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios | Mesa de Servicio</title>
    <style>
        :root{--primary:#0f6fec;--navy:#102a43;--text:#243b53;--muted:#627d98;--border:#dfe7f1;--bg:#f3f6fb;--danger:#b42318;--success:#087443}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.45 Inter,"Segoe UI",Arial,sans-serif}.shell{width:min(1220px,calc(100% - 32px));margin:24px auto}.top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px}.top h1{margin:0;color:var(--navy);font-size:25px}.back,.button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 14px;border:0;border-radius:9px;background:var(--primary);color:#fff;text-decoration:none;font-weight:750;cursor:pointer}.card{margin-bottom:16px;padding:20px;border:1px solid var(--border);border-radius:15px;background:#fff;box-shadow:0 8px 28px rgba(16,42,67,.06)}.alert{margin-bottom:16px;padding:11px 14px;border-radius:9px;font-weight:650}.alert.ok{color:var(--success);background:#eaf8ef}.alert.error{color:var(--danger);background:#fff0f1}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}.field{display:grid;gap:5px}.field.wide{grid-column:span 2}.field.full{grid-column:1/-1}label{color:#315b7d;font-size:12px;font-weight:750}input,select,textarea{width:100%;min-height:40px;padding:9px 11px;border:1px solid #cdd9e7;border-radius:8px;background:#fff;color:var(--text);font:inherit}textarea{min-height:74px;resize:vertical}.actions{display:flex;justify-content:flex-end;margin-top:14px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:900px}th,td{padding:10px;border-bottom:1px solid #e8eef5;text-align:left;vertical-align:middle}th{color:#526d82;font-size:11px;text-transform:uppercase}.badge{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:750}.badge.active{color:var(--success);background:#eaf8ef}.badge.off{color:var(--danger);background:#fff0f1}.row-actions{display:flex;gap:7px}.row-actions form{margin:0}.mini{min-height:32px;padding:6px 9px;font-size:12px}.secondary{background:#eaf2fd;color:#0b4fae}.danger{background:#fff0f1;color:var(--danger)}@media(max-width:800px){.grid{grid-template-columns:1fr}.field.wide{grid-column:auto}.shell{width:min(100% - 20px,1220px)}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<main class="shell">
    <header class="top">
        <div><h1>Administración de usuarios</h1><span>Creación y control de acceso a la Mesa de Servicio.</span></div>
        <a class="back" href="panelAdmin.php">Volver al panel</a>
    </header>

    <?php if ($mensaje): ?>
        <div class="alert <?= escaparUsuario($mensaje[0]) ?>"><?= escaparUsuario($mensaje[1]) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Crear usuario</h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= escaparUsuario(seguridadTokenCsrf()) ?>">
            <input type="hidden" name="accion" value="crear">
            <div class="grid">
                <div class="field"><label for="cedula">Cédula</label><input id="cedula" name="cedula" maxlength="30" required></div>
                <div class="field wide"><label for="nombre">Nombre completo</label><input id="nombre" name="nombre" maxlength="160" required></div>
                <div class="field"><label for="proceso">Proceso</label><input id="proceso" name="proceso" maxlength="120" required></div>
                <div class="field"><label for="cu1">CU1</label><input id="cu1" name="cu1" maxlength="120" required></div>
                <div class="field"><label for="cu3">CU3</label><input id="cu3" name="cu3" maxlength="120" required></div>
                <div class="field"><label for="email">Correo</label><input id="email" type="email" name="email" maxlength="190" required></div>
                <div class="field"><label for="ciudad">Ciudad</label><input id="ciudad" name="ciudad" maxlength="120" required></div>
                <div class="field"><label for="empresa">Empresa</label><input id="empresa" name="empresa" maxlength="160" required></div>
                <div class="field"><label for="id_rol">Rol</label><select id="id_rol" name="id_rol" required><option value="3">Solicitante</option><option value="2">Gestor</option><option value="1">Administrador</option></select></div>
                <div class="field full"><label for="descripcion_cu1">Descripción CU1</label><textarea id="descripcion_cu1" name="descripcion_cu1" maxlength="500" required></textarea></div>
                <div class="field full"><label for="password">Contraseña inicial (mínimo 12 caracteres)</label><input id="password" type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" required></div>
            </div>
            <div class="actions"><button class="button" type="submit">Crear usuario</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Usuarios registrados</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Usuario</th><th>Cédula</th><th>Proceso</th><th>Empresa / ciudad</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><strong><?= escaparUsuario($usuario['nombre']) ?></strong><br><small><?= escaparUsuario($usuario['email']) ?></small></td>
                        <td><?= escaparUsuario($usuario['cedula']) ?></td>
                        <td><?= escaparUsuario($usuario['proceso']) ?></td>
                        <td><?= escaparUsuario($usuario['empresa']) ?><br><small><?= escaparUsuario($usuario['ciudad']) ?></small></td>
                        <td><?= escaparUsuario($usuario['rol']) ?></td>
                        <td><span class="badge <?= $usuario['estado'] === 'activo' ? 'active' : 'off' ?>"><?= escaparUsuario($usuario['estado']) ?></span></td>
                        <td>
                            <div class="row-actions">
                                <a class="button mini secondary" href="editarUsuario.php?id=<?= (int) $usuario['id_usuario'] ?>">Editar</a>
                                <?php if ((int) $usuario['id_usuario'] !== (int) $_SESSION['usuario_id']): ?>
                                    <form method="post" onsubmit="return confirm('¿Confirma el cambio de estado de este usuario?')">
                                        <input type="hidden" name="csrf_token" value="<?= escaparUsuario(seguridadTokenCsrf()) ?>">
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="id_usuario" value="<?= (int) $usuario['id_usuario'] ?>">
                                        <input type="hidden" name="estado" value="<?= $usuario['estado'] === 'activo' ? 'inhabilitado' : 'activo' ?>">
                                        <button class="button mini <?= $usuario['estado'] === 'activo' ? 'danger' : '' ?>" type="submit"><?= $usuario['estado'] === 'activo' ? 'Inhabilitar' : 'Activar' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
