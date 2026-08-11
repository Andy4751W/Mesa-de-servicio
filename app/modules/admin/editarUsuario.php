<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/ubicacion.php';
seguridadExigirRol([1]);
$idPaisOperacion = paisExigirContexto();
$opcionesUbicacion = ubicacionListarOpciones($conn, $idPaisOperacion);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirigirEdicion($idUsuario, $mensaje)
{
    header(
        'Location: editarUsuario.php?id=' . (int) $idUsuario
        . '&msg=' . urlencode($mensaje)
    );
    exit;
}

$idUsuario = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT)
    : filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idUsuario) {
    header('Location: crearUsuarios.php?msg=usuario_no_encontrado');
    exit;
}

$mensajes = [
    'datos_incompletos' => [
        'error',
        'Complete todos los campos obligatorios.'
    ],
    'correo_invalido' => [
        'error',
        'El correo electrónico no tiene un formato válido.'
    ],
    'rol_invalido' => [
        'error',
        'El rol seleccionado no es válido.'
    ],
    'ubicacion_invalida' => [
        'error',
        'Seleccione el país, el departamento y la ciudad en el orden indicado.'
    ],
    'password_invalido' => [
        'error',
        'La contraseña debe tener mínimo 8 caracteres, una letra mayúscula y un número.'
    ],
    'solicitud_invalida' => [
        'error',
        'La solicitud no es válida. Actualice la página e inténtelo nuevamente.'
    ],
    'usuario_duplicado' => [
        'error',
        'Ya existe un usuario con la misma cédula o correo electrónico.'
    ],
    'error_operacion' => [
        'error',
        'No fue posible actualizar el usuario.'
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    seguridadExigirOrigenPost();
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        redirigirEdicion($idUsuario, 'solicitud_invalida');
    }

    $cedula = seguridadTexto($_POST['cedula'] ?? '', 30);
    $nombre = seguridadTexto($_POST['nombre'] ?? '', 160);
    $proceso = seguridadTexto($_POST['proceso'] ?? '', 120);
    $cu1 = seguridadTexto($_POST['cu1'] ?? '', 120);
    $cu3 = seguridadTexto($_POST['cu3'] ?? '', 120);
    $correo = strtolower(seguridadTexto($_POST['correo'] ?? '', 190));
    $descripcionCu1 = seguridadTexto($_POST['descripcion_cu1'] ?? '', 500);
    $empresa = seguridadTexto($_POST['empresa'] ?? '', 160);
    $passwordPlano = (string) ($_POST['password'] ?? '');
    $rol = filter_input(INPUT_POST, 'rol', FILTER_VALIDATE_INT);
    $idPais = filter_input(INPUT_POST, 'id_pais', FILTER_VALIDATE_INT) ?: 0;
    $idDepartamento = filter_input(INPUT_POST, 'id_departamento', FILTER_VALIDATE_INT) ?: 0;
    $idCiudad = filter_input(INPUT_POST, 'id_ciudad', FILTER_VALIDATE_INT) ?: 0;

    if (
        $cedula === '' ||
        $nombre === '' ||
        $proceso === '' ||
        $cu1 === '' ||
        $cu3 === '' ||
        $correo === '' ||
        $descripcionCu1 === '' ||
        $empresa === ''
    ) {
        redirigirEdicion($idUsuario, 'datos_incompletos');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        redirigirEdicion($idUsuario, 'correo_invalido');
    }

    if (!in_array($rol, [1, 2, 3], true)) {
        redirigirEdicion($idUsuario, 'rol_invalido');
    }

    if ($idUsuario === (int) $_SESSION['usuario_id'] && $rol !== 1) {
        redirigirEdicion($idUsuario, 'rol_invalido');
    }

    $ubicacion = null;

    if ($rol !== 1) {
        $ubicacion = ubicacionValidarSeleccion(
            $conn,
            $idPaisOperacion,
            $idPais,
            $idDepartamento,
            $idCiudad
        );

        if ($ubicacion === null) {
            redirigirEdicion($idUsuario, 'ubicacion_invalida');
        }
    }

    $ciudad = $rol === 1 ? 'No aplica' : $ubicacion['ciudad'];
    $idPaisPerfil = $rol === 1 ? 0 : $ubicacion['id_pais'];
    $idDepartamentoPerfil = $rol === 1 ? 0 : $ubicacion['id_departamento'];
    $idCiudadPerfil = $rol === 1 ? 0 : $ubicacion['id_ciudad'];

    if ($passwordPlano !== '' && !seguridadPasswordValida($passwordPlano)) {
        redirigirEdicion($idUsuario, 'password_invalido');
    }

    try {
        $idPaisUsuario = $rol === 1 ? null : $idPaisOperacion;
        if ($passwordPlano !== '') {
            $password = password_hash($passwordPlano, PASSWORD_DEFAULT);

            $stmtUpdate = $conn->prepare(
                "UPDATE usuarios
                 SET
                    cedula = ?,
                    nombre = ?,
                    proceso = ?,
                    cu1 = ?,
                    cu3 = ?,
                    email = ?,
                    descripcion_cu1 = ?,
                    ciudad = ?,
                    empresa = ?,
                    id_rol = ?,
                    id_pais_operacion = ?,
                    id_pais = NULLIF(?, 0),
                    id_departamento = NULLIF(?, 0),
                    id_ciudad = NULLIF(?, 0),
                    password = ?
                 WHERE id_usuario = ?
                   AND (id_rol = 1 OR id_pais_operacion = ?)"
            );
            $stmtUpdate->bind_param(
                "sssssssssiiiiisii",
                $cedula,
                $nombre,
                $proceso,
                $cu1,
                $cu3,
                $correo,
                $descripcionCu1,
                $ciudad,
                $empresa,
                $rol,
                $idPaisUsuario,
                $idPaisPerfil,
                $idDepartamentoPerfil,
                $idCiudadPerfil,
                $password,
                $idUsuario,
                $idPaisOperacion
            );
        } else {
            $stmtUpdate = $conn->prepare(
                "UPDATE usuarios
                 SET
                    cedula = ?,
                    nombre = ?,
                    proceso = ?,
                    cu1 = ?,
                    cu3 = ?,
                    email = ?,
                    descripcion_cu1 = ?,
                    ciudad = ?,
                    empresa = ?,
                    id_rol = ?,
                    id_pais_operacion = ?,
                    id_pais = NULLIF(?, 0),
                    id_departamento = NULLIF(?, 0),
                    id_ciudad = NULLIF(?, 0)
                 WHERE id_usuario = ?
                   AND (id_rol = 1 OR id_pais_operacion = ?)"
            );
            $stmtUpdate->bind_param(
                "sssssssssiiiiiii",
                $cedula,
                $nombre,
                $proceso,
                $cu1,
                $cu3,
                $correo,
                $descripcionCu1,
                $ciudad,
                $empresa,
                $rol,
                $idPaisUsuario,
                $idPaisPerfil,
                $idDepartamentoPerfil,
                $idCiudadPerfil,
                $idUsuario,
                $idPaisOperacion
            );
        }

        if (!$stmtUpdate->execute()) {
            throw new RuntimeException('No fue posible actualizar el usuario.');
        }

        $stmtUpdate->close();
        header('Location: crearUsuarios.php');
        exit;
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            redirigirEdicion($idUsuario, 'usuario_duplicado');
        }

        redirigirEdicion($idUsuario, 'error_operacion');
    } catch (Throwable $e) {
        redirigirEdicion($idUsuario, 'error_operacion');
    }
}

$stmtUsuario = $conn->prepare(
    "SELECT
        id_usuario,
        cedula,
        nombre,
        proceso,
        cu1,
        cu3,
        email,
        descripcion_cu1,
        ciudad,
        id_pais,
        id_departamento,
        id_ciudad,
        empresa,
        id_rol
     FROM usuarios
     WHERE id_usuario = ?
       AND (id_rol = 1 OR id_pais_operacion = ?)"
);
$stmtUsuario->bind_param("ii", $idUsuario, $idPaisOperacion);
$stmtUsuario->execute();
$resultadoUsuario = $stmtUsuario->get_result();
$usuario = $resultadoUsuario->fetch_assoc();
$stmtUsuario->close();

if (!$usuario) {
    header('Location: crearUsuarios.php?msg=usuario_no_encontrado');
    exit;
}

$mensajeActual = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar usuario | Mesa de Servicio</title>
    <style>
        :root {
            --primary: #1f5f99;
            --primary-dark: #163f65;
            --navy: #132f4c;
            --text: #263b50;
            --muted: #64788b;
            --surface: #ffffff;
            --background: #f3f6fb;
            --border: #dfe7f1;
            --danger: #a72836;
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
                radial-gradient(circle at 7% 0%, rgba(31, 95, 153, 0.10), transparent 27%),
                radial-gradient(circle at 95% 100%, rgba(72, 101, 129, 0.08), transparent 24%),
                var(--background);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.45;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        .page-shell {
            width: min(980px, calc(100% - 40px));
            margin: 0 auto;
            padding: 28px 0 40px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
            padding: 13px 17px;
            border: 1px solid rgba(223, 231, 241, 0.9);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 8px 24px rgba(15, 45, 75, 0.06);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .brand-mark {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: 11px;
            color: #fff;
            background: linear-gradient(145deg, #2b73ad, #163f65);
            box-shadow: 0 8px 18px rgba(22, 63, 101, 0.24);
            font-size: 14px;
            font-weight: 800;
        }

        .brand-name,
        .brand-subtitle {
            margin: 0;
        }

        .brand-name {
            color: var(--navy);
            font-size: 15px;
            font-weight: 750;
        }

        .brand-subtitle {
            color: var(--muted);
            font-size: 11px;
        }

        .btn-volver {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 13px;
            border: 1px solid #dce6f1;
            border-radius: 10px;
            color: #315779;
            background: #f7faff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition:
                color 0.18s ease,
                background 0.18s ease;
        }

        .btn-volver:hover {
            color: var(--primary-dark);
            background: #edf5ff;
        }

        .form-card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 21px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .form-header {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            min-height: 165px;
            padding: 32px 36px;
            overflow: hidden;
            color: #fff;
            background: linear-gradient(120deg, #132f4c 0%, #1f5f99 64%, #2b76aa 100%);
        }

        .form-header::after {
            position: absolute;
            top: -105px;
            right: -55px;
            width: 260px;
            height: 260px;
            border: 40px solid rgba(255, 255, 255, 0.07);
            border-radius: 50%;
            content: "";
        }

        .form-header-copy,
        .user-id {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 9px;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .form-header h1,
        .form-header p {
            margin: 0;
        }

        .form-header h1 {
            font-size: clamp(27px, 4vw, 38px);
            line-height: 1.12;
            letter-spacing: -0.8px;
        }

        .form-header p {
            margin-top: 9px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 13px;
        }

        .user-id {
            flex: 0 0 auto;
            padding: 9px 12px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.12);
            font-size: 12px;
            font-weight: 700;
        }

        .form-body {
            padding: 28px 32px 30px;
        }

        .alerta {
            margin-bottom: 20px;
            padding: 12px 15px;
            border: 1px solid #f1c3c8;
            border-radius: 11px;
            color: #922b36;
            background: #fdecee;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
        }

        .section-title {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .section-title h2,
        .section-title p {
            margin: 0;
        }

        .section-title h2 {
            color: var(--navy);
            font-size: 17px;
        }

        .section-title p {
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px 19px;
        }

        .campo {
            min-width: 0;
        }

        .campo.completo {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #334e68;
            font-size: 12px;
            font-weight: 750;
        }

        input,
        select,
        textarea {
            width: 100%;
            margin: 0;
            padding: 10px 11px;
            border: 1px solid #cfdbe8;
            border-radius: 9px;
            color: var(--text);
            outline: none;
            background: #fff;
            font-size: 13px;
            transition:
                border-color 0.18s ease,
                box-shadow 0.18s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(31, 95, 153, 0.13);
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .password-help {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 41px;
            padding: 10px 17px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
            transition:
                transform 0.18s ease,
                box-shadow 0.18s ease,
                background 0.18s ease;
        }

        .btn-cancelar {
            border: 1px solid #d4dfe9;
            color: #486581;
            background: #fff;
        }

        .btn-cancelar:hover {
            background: #f4f7fa;
        }

        .btn-guardar {
            border: 1px solid var(--primary);
            color: #fff;
            background: var(--primary);
            box-shadow: 0 8px 18px rgba(31, 95, 153, 0.22);
        }

        .btn-guardar:hover {
            transform: translateY(-1px);
            background: var(--primary-dark);
            box-shadow: 0 11px 22px rgba(31, 95, 153, 0.27);
        }

        @media (max-width: 720px) {
            .page-shell {
                width: min(100% - 24px, 980px);
                padding-top: 12px;
            }

            .brand-subtitle {
                display: none;
            }

            .form-header {
                align-items: flex-start;
                flex-direction: column;
                min-height: auto;
                padding: 28px 24px;
            }

            .form-body {
                padding: 23px 20px 25px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .campo.completo {
                grid-column: auto;
            }
        }

        @media (max-width: 440px) {
            .brand-name {
                font-size: 13px;
            }

            .btn-volver {
                padding: 8px 10px;
                font-size: 12px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">MS</div>
                <div>
                    <p class="brand-name">Mesa de Servicio</p>
                    <p class="brand-subtitle">Administración de usuarios</p>
                </div>
            </div>

            <a href="crearUsuarios.php" class="btn-volver">
                <span aria-hidden="true">←</span>
                Volver a usuarios
            </a>
        </header>

        <section class="form-card" aria-labelledby="titulo-edicion">
            <header class="form-header">
                <div class="form-header-copy">
                    <span class="eyebrow">Administración</span>
                    <h1 id="titulo-edicion">Editar usuario</h1>
                    <p>Actualice los datos y permisos de acceso de la cuenta.</p>
                </div>

                <span class="user-id">
                    ID <?php echo (int) $usuario['id_usuario']; ?>
                </span>
            </header>

            <div class="form-body">
                <?php if (isset($mensajes[$mensajeActual])): ?>
                    <div class="alerta">
                        <?php echo escapar($mensajes[$mensajeActual][1]); ?>
                    </div>
                <?php endif; ?>

                <div class="section-title">
                    <h2>Información del usuario</h2>
                    <p>Los campos marcados son obligatorios.</p>
                </div>

                <form method="POST" action="editarUsuario.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escapar($_SESSION['csrf_token']); ?>"
                    >
                    <input
                        type="hidden"
                        name="id_usuario"
                        value="<?php echo (int) $usuario['id_usuario']; ?>"
                    >

                    <div class="form-grid">
                        <div class="campo">
                            <label for="cedula">Cédula</label>
                            <input
                                id="cedula"
                                type="text"
                                name="cedula"
                                maxlength="30"
                                value="<?php echo escapar($usuario['cedula']); ?>"
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="nombre">Nombre completo</label>
                            <input
                                id="nombre"
                                type="text"
                                name="nombre"
                                maxlength="150"
                                value="<?php echo escapar($usuario['nombre']); ?>"
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="proceso">Proceso</label>
                            <input
                                id="proceso"
                                type="text"
                                name="proceso"
                                maxlength="150"
                                value="<?php echo escapar($usuario['proceso']); ?>"
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="cu1">Cu1</label>
                            <input
                                id="cu1"
                                type="text"
                                name="cu1"
                                maxlength="150"
                                value="<?php echo escapar($usuario['cu1']); ?>"
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="cu3">Cu3</label>
                            <input
                                id="cu3"
                                type="text"
                                name="cu3"
                                maxlength="150"
                                value="<?php echo escapar($usuario['cu3']); ?>"
                                required
                            >
                        </div>

                        <div class="campo completo">
                            <label for="descripcion_cu1">Descripción del Cu1</label>
                            <textarea
                                id="descripcion_cu1"
                                name="descripcion_cu1"
                                required
                            ><?php echo escapar($usuario['descripcion_cu1']); ?></textarea>
                        </div>

                        <div class="campo">
                            <label for="correo">Correo electrónico</label>
                            <input
                                id="correo"
                                type="email"
                                name="correo"
                                maxlength="190"
                                value="<?php echo escapar($usuario['email']); ?>"
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="empresa">Empresa</label>
                            <input
                                id="empresa"
                                type="text"
                                name="empresa"
                                maxlength="150"
                                value="<?php echo escapar($usuario['empresa']); ?>"
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="rol">Rol</label>
                            <select id="rol" name="rol" required>
                                <option
                                    value="1"
                                    <?php echo (int) $usuario['id_rol'] === 1 ? 'selected' : ''; ?>
                                >Administrador</option>
                                <option
                                    value="2"
                                    <?php echo (int) $usuario['id_rol'] === 2 ? 'selected' : ''; ?>
                                >Gestor</option>
                                <option
                                    value="3"
                                    <?php echo (int) $usuario['id_rol'] === 3 ? 'selected' : ''; ?>
                                >Solicitante</option>
                            </select>
                        </div>

                        <div class="campo ubicacion-campo">
                            <label for="id_pais">País</label>
                            <select id="id_pais" name="id_pais" required>
                                <option value="">Seleccione un país</option>
                                <?php foreach ($opcionesUbicacion['paises'] as $opcion): ?>
                                    <option value="<?= (int) $opcion['id_opcion'] ?>" <?= (int) ($usuario['id_pais'] ?? 0) === (int) $opcion['id_opcion'] ? 'selected' : '' ?>><?= escapar($opcion['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo ubicacion-campo">
                            <label for="id_departamento">Departamento</label>
                            <select id="id_departamento" name="id_departamento" required>
                                <option value="">Seleccione un departamento</option>
                                <?php foreach ($opcionesUbicacion['departamentos'] as $opcion): ?>
                                    <option value="<?= (int) $opcion['id_opcion'] ?>" data-padre="<?= (int) $opcion['id_padre'] ?>" <?= (int) ($usuario['id_departamento'] ?? 0) === (int) $opcion['id_opcion'] ? 'selected' : '' ?>><?= escapar($opcion['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo ubicacion-campo">
                            <label for="id_ciudad">Ciudad</label>
                            <select id="id_ciudad" name="id_ciudad" required>
                                <option value="">Seleccione una ciudad</option>
                                <?php foreach ($opcionesUbicacion['ciudades'] as $opcion): ?>
                                    <option value="<?= (int) $opcion['id_opcion'] ?>" data-padre="<?= (int) $opcion['id_padre'] ?>" <?= (int) ($usuario['id_ciudad'] ?? 0) === (int) $opcion['id_opcion'] ? 'selected' : '' ?>><?= escapar($opcion['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo">
                            <label for="password">Nueva contraseña</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                minlength="8"
                                maxlength="128"
                                pattern="(?=.*[A-ZÁÉÍÓÚÜÑ])(?=.*[0-9]).{8,128}"
                                title="Use mínimo 8 caracteres, una letra mayúscula y un número."
                                autocomplete="new-password"
                                placeholder="Dejar vacío para conservarla"
                            >
                            <small class="password-help">
                                Solo diligencie este campo si desea cambiarla. Debe tener mínimo 8 caracteres, una letra mayúscula y un número.
                            </small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="crearUsuarios.php" class="btn btn-cancelar">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-guardar">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
    <script>
    (() => {
        const role = document.getElementById('rol');
        const country = document.getElementById('id_pais');
        const department = document.getElementById('id_departamento');
        const city = document.getElementById('id_ciudad');

        const filterChild = (parent, child) => {
            const parentId = parent.value;
            let selectedValid = child.value === '';
            Array.from(child.options).forEach((option) => {
                if (option.value === '') return;
                const matches = parentId !== '' && option.dataset.padre === parentId;
                option.hidden = !matches;
                option.disabled = !matches;
                if (matches && option.selected) selectedValid = true;
            });
            if (!selectedValid) child.value = '';
            child.disabled = parentId === '' || role.value === '1';
        };

        const updateLocation = () => {
            const globalAdmin = role.value === '1';
            [country, department, city].forEach((control) => {
                control.required = !globalAdmin;
            });
            country.disabled = globalAdmin;
            if (globalAdmin) {
                country.value = '';
                department.value = '';
                city.value = '';
                department.disabled = true;
                city.disabled = true;
                return;
            }
            filterChild(country, department);
            filterChild(department, city);
        };

        country.addEventListener('change', () => {
            department.value = '';
            city.value = '';
            updateLocation();
        });
        department.addEventListener('change', () => {
            city.value = '';
            updateLocation();
        });
        role.addEventListener('change', updateLocation);
        updateLocation();
    })();
    </script>
    <script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
