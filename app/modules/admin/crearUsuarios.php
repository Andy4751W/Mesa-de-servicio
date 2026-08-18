<?php
declare(strict_types=1);

require_once APP_ROOT . '/security/validarSesion.php';
require_once APP_ROOT . '/core/ubicacion.php';
seguridadExigirRol([1]);
$idPaisOperacion = paisExigirContexto();
$nombrePaisOperacion = paisContextoNombre();
$codigoPaisOperacion = paisContextoCodigo();
$opcionesUbicacion = ubicacionListarOpciones($conn, $idPaisOperacion);

function escaparUsuario(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function valorCsvUsuario(mixed $valor): string
{
    $texto = (string) $valor;

    return preg_match('/^[=+\-@]/', $texto) === 1 ? "'" . $texto : $texto;
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
    'password_invalido' => ['error', 'La contraseña debe tener mínimo 8 caracteres, una letra mayúscula y un número.'],
    'rol_invalido' => ['error', 'Seleccione un rol válido.'],
    'ubicacion_invalida' => ['error', 'Seleccione el país, el departamento y la ciudad en el orden indicado.'],
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
        $empresa = seguridadTexto($_POST['empresa'] ?? '', 160);
        $passwordPlano = (string) ($_POST['password'] ?? '');
        $rol = filter_input(INPUT_POST, 'id_rol', FILTER_VALIDATE_INT) ?: 0;
        $idPais = filter_input(INPUT_POST, 'id_pais', FILTER_VALIDATE_INT) ?: 0;
        $idDepartamento = filter_input(INPUT_POST, 'id_departamento', FILTER_VALIDATE_INT) ?: 0;
        $idCiudad = filter_input(INPUT_POST, 'id_ciudad', FILTER_VALIDATE_INT) ?: 0;

        if (
            $cedula === ''
            || $nombre === ''
            || $proceso === ''
            || $cu1 === ''
            || $cu3 === ''
            || $email === ''
            || $descripcion === ''
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
                volverUsuarios('ubicacion_invalida');
            }
        }

        $ciudad = $rol === 1 ? 'No aplica' : $ubicacion['ciudad'];
        $idPaisPerfil = $rol === 1 ? 0 : $ubicacion['id_pais'];
        $idDepartamentoPerfil = $rol === 1 ? 0 : $ubicacion['id_departamento'];
        $idCiudadPerfil = $rol === 1 ? 0 : $ubicacion['id_ciudad'];

        if (!seguridadPasswordValida($passwordPlano)) {
            volverUsuarios('password_invalido');
        }

        try {
            $password = password_hash($passwordPlano, PASSWORD_DEFAULT);
            $idPaisUsuario = $rol === 1 ? null : $idPaisOperacion;
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
                        id_pais_operacion,
                        id_pais,
                        id_departamento,
                        id_ciudad,
                        estado
                    )
                 VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), 'activo'
                 )"
            );
            $stmt->bind_param(
                'ssssssssssiiiii',
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
                $rol,
                $idPaisUsuario,
                $idPaisPerfil,
                $idDepartamentoPerfil,
                $idCiudadPerfil
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
                "UPDATE usuarios
                 SET estado = ?
                 WHERE id_usuario = ?
                   AND (id_rol = 1 OR id_pais_operacion = ?)"
            );
            $stmt->bind_param('sii', $estado, $idUsuario, $idPaisOperacion);
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
        u.cu1,
        u.descripcion_cu1,
        u.cu3,
        CASE WHEN u.id_rol = 1 THEN 'No aplica' ELSE COALESCE(ciudad.nombre, NULLIF(u.ciudad, ''), 'Sin asignar') END AS ciudad,
        CASE WHEN u.id_rol = 1 THEN 'No aplica' ELSE COALESCE(departamento.nombre, 'Sin asignar') END AS departamento,
        CASE WHEN u.id_rol = 1 THEN 'Todos los países' ELSE COALESCE(pais.nombre, 'Sin asignar') END AS pais,
        u.empresa,
        u.id_rol,
        u.id_pais_operacion,
        u.estado,
        COALESCE(r.nombre_rol, 'Sin rol') AS rol,
        CASE WHEN u.id_rol = 1 THEN 'Todos los países' ELSE po.nombre END AS pais_operacion
     FROM usuarios AS u
     LEFT JOIN roles AS r ON r.id_rol = u.id_rol
     LEFT JOIN paises_operacion AS po
        ON po.id_pais_operacion = u.id_pais_operacion
     LEFT JOIN configuraciones_servicio AS pais
        ON pais.id_opcion = u.id_pais AND pais.tipo = 'pais'
     LEFT JOIN configuraciones_servicio AS departamento
        ON departamento.id_opcion = u.id_departamento AND departamento.tipo = 'departamento'
     LEFT JOIN configuraciones_servicio AS ciudad
        ON ciudad.id_opcion = u.id_ciudad AND ciudad.tipo = 'ciudad'
     WHERE u.id_rol = 1 OR u.id_pais_operacion = {$idPaisOperacion}
     ORDER BY u.estado = 'activo' DESC, u.nombre, u.id_usuario"
);

if ($resultadoUsuarios !== false) {
    $usuarios = $resultadoUsuarios->fetch_all(MYSQLI_ASSOC);
}

if (($_GET['export'] ?? '') === 'csv') {
    $nombreArchivo = sprintf(
        'usuarios_%s_%s.csv',
        strtolower($codigoPaisOperacion),
        date('Y-m-d')
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $salida = fopen('php://output', 'wb');

    if ($salida === false) {
        http_response_code(500);
        exit('No fue posible generar la descarga.');
    }

    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv(
        $salida,
        [
            'ID',
            'Cédula',
            'Nombre completo',
            'Correo',
            'Rol',
            'Proceso',
            'CU1',
            'Descripción CU1',
            'CU3',
            'Empresa',
            'País',
            'Departamento',
            'Ciudad',
            'Operación',
            'Estado',
        ],
        ';',
        '"',
        '\\'
    );

    foreach ($usuarios as $usuario) {
        fputcsv(
            $salida,
            array_map(
                'valorCsvUsuario',
                [
                    $usuario['id_usuario'],
                    $usuario['cedula'],
                    $usuario['nombre'],
                    $usuario['email'],
                    $usuario['rol'],
                    $usuario['proceso'],
                    $usuario['cu1'],
                    $usuario['descripcion_cu1'],
                    $usuario['cu3'],
                    $usuario['empresa'],
                    $usuario['pais'],
                    $usuario['departamento'],
                    $usuario['ciudad'],
                    $usuario['pais_operacion'],
                    $usuario['estado'],
                ]
            ),
            ';',
            '"',
            '\\'
        );
    }

    fclose($salida);
    exit;
}

$mensajeActual = (string) ($_GET['msg'] ?? '');
$mensaje = $mensajes[$mensajeActual] ?? null;
$abrirModalPorError = in_array(
    $mensajeActual,
    [
        'datos_incompletos',
        'correo_invalido',
        'password_invalido',
        'rol_invalido',
        'ubicacion_invalida',
        'duplicado',
        'error',
    ],
    true
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios | Mesa de Servicio</title>
    <style>
        :root {
            --primary: <?= $codigoPaisOperacion === 'PE' ? '#c81e3a' : '#0f6fec' ?>;
            --primary-dark: <?= $codigoPaisOperacion === 'PE' ? '#8f1027' : '#0b4fae' ?>;
            --primary-soft: <?= $codigoPaisOperacion === 'PE' ? '#fff0f2' : '#eaf2fd' ?>;
            --primary-rgb: <?= $codigoPaisOperacion === 'PE' ? '200,30,58' : '15,111,236' ?>;
            --navy: #102a43;
            --text: #243b53;
            --muted: #526d82;
            --border: #dfe7f1;
            --bg: #f3f6fb;
            --danger: #b42318;
            --success: #087443;
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body {
            margin: 0;
            color: var(--text);
            background: var(--bg);
            font: 12px/1.45 Inter, "Segoe UI", Arial, sans-serif;
        }
        button, input, select, textarea { font: inherit; }
        body.modal-open { overflow: hidden; }
        .shell { width: min(1260px, calc(100% - 24px)); margin: 12px auto 30px; }
        .top {
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 10px;
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 7px 22px rgba(16,42,67,.05);
        }
        .top h1 { margin: 0; color: var(--navy); font-size: 18px; line-height: 1.2; }
        .top p { margin: 2px 0 0; color: var(--muted); font-size: 10px; }
        .top-actions, .row-actions, .modal-actions { display: flex; align-items: center; gap: 7px; }
        .button {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 11px;
            border: 1px solid transparent;
            border-radius: 8px;
            color: #fff;
            background: var(--primary);
            text-decoration: none;
            font-weight: 800;
            cursor: pointer;
        }
        .button:hover { background: var(--primary-dark); }
        .button.soft { border-color: var(--border); color: #315b7d; background: #f8fbff; }
        .button.soft:hover { color: var(--primary); background: var(--primary-soft); }
        .button.secondary { color: var(--primary-dark); background: var(--primary-soft); }
        .button.danger { color: var(--danger); background: #fff0f1; }
        .button.mini { min-height: 29px; padding: 5px 8px; font-size: 10px; }
        .card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 8px 26px rgba(16,42,67,.055);
        }
        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
        }
        .card-head h2 { margin: 0; color: var(--navy); font-size: 15px; }
        .card-head p { margin: 2px 0 0; color: var(--muted); font-size: 9px; }
        .result-count {
            flex: 0 0 auto;
            padding: 4px 8px;
            border-radius: 999px;
            color: var(--primary-dark);
            background: var(--primary-soft);
            font-size: 9px;
            font-weight: 850;
        }
        .filters {
            display: grid;
            grid-template-columns: minmax(260px,1fr) 170px 150px auto;
            gap: 8px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            background: #fbfdff;
        }
        .control { position: relative; }
        .control input, .control select {
            width: 100%;
            min-height: 36px;
            padding: 7px 10px;
            outline: none;
            border: 1px solid #ccd9e6;
            border-radius: 8px;
            color: var(--text);
            background: #fff;
        }
        .control input:focus, .control select:focus,
        .field input:focus, .field select:focus, .field textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb),.11);
        }
        .table-wrap { overflow: auto; }
        table { width: 100%; min-width: 960px; border-collapse: collapse; }
        th, td { padding: 9px 10px; border-bottom: 1px solid #e8eef5; text-align: left; vertical-align: middle; }
        th { color: #526d82; background: #f7f9fc; font-size: 9px; letter-spacing: .035em; text-transform: uppercase; }
        tbody tr:hover { background: #fbfdff; }
        tbody tr:last-child td { border-bottom: 0; }
        td strong { color: var(--navy); font-size: 11px; }
        td small { color: var(--muted); font-size: 9px; }
        .badge { display: inline-flex; padding: 3px 7px; border-radius: 999px; font-size: 9px; font-weight: 800; text-transform: capitalize; }
        .badge.active { color: var(--success); background: #eaf8ef; }
        .badge.off { color: var(--danger); background: #fff0f1; }
        .row-actions { flex-wrap: wrap; }
        .row-actions form { margin: 0; }
        .empty-filter { padding: 24px; color: var(--muted); text-align: center; }
        .alert { margin-bottom: 10px; padding: 9px 12px; border-radius: 9px; font-weight: 700; }
        .alert.ok { color: var(--success); background: #eaf8ef; }
        .alert.error { color: var(--danger); background: #fff0f1; }
        .modal {
            position: fixed;
            /* Debe quedar por encima de la barra corporativa compartida. */
            z-index: 2147483500;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 12px;
            background: rgba(8,25,43,.58);
            opacity: 0;
            transition: opacity .16s ease;
        }
        .modal.open { opacity: 1; }
        .modal-card {
            width: min(760px,100%);
            max-height: calc(100vh - 24px);
            overflow: hidden;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(0,0,0,.28);
            transform: translateY(8px) scale(.985);
            transition: transform .16s ease;
        }
        .modal.open .modal-card { transform: translateY(0) scale(1); }
        .modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
        }
        .modal-head h2 { margin: 0; color: var(--navy); font-size: 16px; }
        .modal-head p { margin: 2px 0 0; color: var(--muted); font-size: 9px; }
        .modal-close {
            width: 31px;
            height: 31px;
            border: 0;
            border-radius: 8px;
            color: #526d82;
            background: #eef3f8;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }
        .modal-body { max-height: calc(100vh - 92px); overflow-y: auto; padding: 13px 15px 15px; }
        .grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 9px; }
        .field { display: grid; gap: 4px; }
        .field.wide { grid-column: span 2; }
        .field.full { grid-column: 1/-1; }
        .field label { color: #315b7d; font-size: 10px; font-weight: 800; }
        .field input, .field select, .field textarea {
            width: 100%;
            min-height: 36px;
            padding: 7px 9px;
            outline: none;
            border: 1px solid #cdd9e7;
            border-radius: 8px;
            color: var(--text);
            background: #fff;
        }
        .field textarea { min-height: 56px; resize: vertical; }
        .modal-actions { justify-content: flex-end; margin-top: 11px; }
        @media (max-width: 850px) {
            .filters { grid-template-columns: 1fr 1fr; }
            .filters .search-control { grid-column: 1/-1; }
        }
        @media (max-width: 650px) {
            .shell { width: calc(100% - 14px); margin-top: 7px; }
            .top, .card-head { align-items: flex-start; flex-direction: column; }
            .top-actions { width: 100%; flex-wrap: wrap; }
            .top-actions .button { flex: 1; }
            .filters, .grid { grid-template-columns: 1fr; }
            .filters .search-control, .field.wide, .field.full { grid-column: auto; }
            .filters .button { width: 100%; }
        }
    </style>
</head>
<body>
<main class="shell">
    <header class="top">
        <div>
            <h1>Administración de usuarios · <?= escaparUsuario($nombrePaisOperacion) ?></h1>
            <p>Gestione los agentes vinculados a esta operación.</p>
        </div>
        <div class="top-actions">
            <a class="button soft" href="panelAdmin.php">← Volver al panel</a>
            <a class="button soft" href="crearUsuarios.php?export=csv">↓ Descargar base</a>
            <button class="button" id="openUserModal" type="button">＋ Crear usuario</button>
        </div>
    </header>

    <?php if ($mensaje): ?>
        <div class="alert <?= escaparUsuario($mensaje[0]) ?>"><?= escaparUsuario($mensaje[1]) ?></div>
    <?php endif; ?>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Usuarios registrados</h2>
                <p>Busque por nombre, cédula, correo, proceso, empresa o ciudad.</p>
            </div>
            <span class="result-count" id="resultCount"><?= count($usuarios) === 1 ? '1 usuario' : count($usuarios) . ' usuarios' ?></span>
        </div>
        <div class="filters" aria-label="Filtros de usuarios">
            <div class="control search-control">
                <input id="userSearch" type="search" placeholder="Buscar agente..." autocomplete="off">
            </div>
            <div class="control">
                <select id="roleFilter" aria-label="Filtrar por rol">
                    <option value="">Todos los roles</option>
                    <option value="1">Administrador</option>
                    <option value="2">Gestor</option>
                    <option value="3">Solicitante</option>
                </select>
            </div>
            <div class="control">
                <select id="statusFilter" aria-label="Filtrar por estado">
                    <option value="">Todos los estados</option>
                    <option value="activo">Activos</option>
                    <option value="inhabilitado">Inhabilitados</option>
                </select>
            </div>
            <button class="button soft" id="clearFilters" type="button">Limpiar filtros</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Usuario</th><th>Cédula</th><th>Proceso</th><th>Empresa</th><th>Ubicación</th><th>Rol</th><th>Operación</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody id="usersTableBody">
                <?php foreach ($usuarios as $usuario): ?>
                    <tr
                        class="user-row"
                        data-role="<?= (int) $usuario['id_rol'] ?>"
                        data-status="<?= escaparUsuario($usuario['estado']) ?>"
                        data-search="<?= escaparUsuario(implode(' ', [
                            $usuario['nombre'],
                            $usuario['email'],
                            $usuario['cedula'],
                            $usuario['proceso'],
                            $usuario['empresa'],
                            $usuario['pais'],
                            $usuario['departamento'],
                            $usuario['ciudad'],
                            $usuario['rol'],
                        ])) ?>"
                    >
                        <td><strong><?= escaparUsuario($usuario['nombre']) ?></strong><br><small><?= escaparUsuario($usuario['email']) ?></small></td>
                        <td><?= escaparUsuario($usuario['cedula']) ?></td>
                        <td><?= escaparUsuario($usuario['proceso']) ?></td>
                        <td><?= escaparUsuario($usuario['empresa']) ?></td>
                        <td><strong><?= escaparUsuario($usuario['pais']) ?></strong><br><small><?= escaparUsuario($usuario['departamento']) ?> · <?= escaparUsuario($usuario['ciudad']) ?></small></td>
                        <td><?= escaparUsuario($usuario['rol']) ?></td>
                        <td><?= escaparUsuario($usuario['pais_operacion']) ?></td>
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
                    <tr id="noFilterResults" hidden>
                        <td class="empty-filter" colspan="9">No hay usuarios que coincidan con los filtros.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
<div
    class="modal"
    id="userModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="userModalTitle"
    hidden
>
    <div class="modal-card">
        <div class="modal-head">
            <div>
                <h2 id="userModalTitle">Crear usuario · <?= escaparUsuario($nombrePaisOperacion) ?></h2>
                <p>El usuario quedará vinculado a la operación seleccionada.</p>
            </div>
            <button class="modal-close" type="button" data-close-modal aria-label="Cerrar">×</button>
        </div>
        <div class="modal-body">
            <form id="createUserForm" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= escaparUsuario(seguridadTokenCsrf()) ?>">
                <input type="hidden" name="accion" value="crear">
                <div class="grid">
                    <div class="field"><label for="cedula">Cédula</label><input id="cedula" name="cedula" maxlength="30" required></div>
                    <div class="field wide"><label for="nombre">Nombre completo</label><input id="nombre" name="nombre" maxlength="160" required></div>
                    <div class="field"><label for="proceso">Proceso</label><input id="proceso" name="proceso" maxlength="120" required></div>
                    <div class="field"><label for="cu1">CU1</label><input id="cu1" name="cu1" maxlength="120" required></div>
                    <div class="field"><label for="cu3">CU3</label><input id="cu3" name="cu3" maxlength="120" required></div>
                    <div class="field wide"><label for="email">Correo</label><input id="email" type="email" name="email" maxlength="190" required></div>
                    <div class="field wide"><label for="empresa">Empresa</label><input id="empresa" name="empresa" maxlength="160" required></div>
                    <div class="field"><label for="id_rol">Rol</label><select id="id_rol" name="id_rol" required><option value="3">Solicitante</option><option value="2">Gestor</option><option value="1">Administrador</option></select></div>
                    <div class="field location-field"><label for="id_pais">País</label><select id="id_pais" name="id_pais" required><option value="">Seleccione un país</option><?php foreach ($opcionesUbicacion['paises'] as $opcion): ?><option value="<?= (int) $opcion['id_opcion'] ?>"><?= escaparUsuario($opcion['nombre']) ?></option><?php endforeach; ?></select></div>
                    <div class="field location-field"><label for="id_departamento">Departamento</label><select id="id_departamento" name="id_departamento" required disabled><option value="">Seleccione un departamento</option><?php foreach ($opcionesUbicacion['departamentos'] as $opcion): ?><option value="<?= (int) $opcion['id_opcion'] ?>" data-padre="<?= (int) $opcion['id_padre'] ?>"><?= escaparUsuario($opcion['nombre']) ?></option><?php endforeach; ?></select></div>
                    <div class="field location-field"><label for="id_ciudad">Ciudad</label><select id="id_ciudad" name="id_ciudad" required disabled><option value="">Seleccione una ciudad</option><?php foreach ($opcionesUbicacion['ciudades'] as $opcion): ?><option value="<?= (int) $opcion['id_opcion'] ?>" data-padre="<?= (int) $opcion['id_padre'] ?>"><?= escaparUsuario($opcion['nombre']) ?></option><?php endforeach; ?></select></div>
                    <div class="field full"><label for="descripcion_cu1">Descripción CU1</label><textarea id="descripcion_cu1" name="descripcion_cu1" maxlength="500" required></textarea></div>
                    <div class="field full">
                        <label for="password">Contraseña inicial</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            minlength="8"
                            maxlength="128"
                            pattern="(?=.*[A-ZÁÉÍÓÚÜÑ])(?=.*[0-9]).{8,128}"
                            title="Use mínimo 8 caracteres, una letra mayúscula y un número."
                            autocomplete="new-password"
                            required
                        >
                        <small>Mínimo 8 caracteres, una letra mayúscula y un número.</small>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="button soft" type="button" data-close-modal>Cancelar</button>
                    <button class="button" type="submit">Crear usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    'use strict';

    const modal = document.getElementById('userModal');
    const openButton = document.getElementById('openUserModal');
    const firstInput = document.getElementById('cedula');
    const searchInput = document.getElementById('userSearch');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const clearFilters = document.getElementById('clearFilters');
    const resultCount = document.getElementById('resultCount');
    const noResults = document.getElementById('noFilterResults');
    const rows = Array.from(document.querySelectorAll('.user-row'));
    const userRole = document.getElementById('id_rol');
    const country = document.getElementById('id_pais');
    const department = document.getElementById('id_departamento');
    const city = document.getElementById('id_ciudad');
    let closeTimer = 0;

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const openModal = () => {
        window.clearTimeout(closeTimer);
        modal.hidden = false;
        document.body.classList.add('modal-open');
        requestAnimationFrame(() => modal.classList.add('open'));
        window.setTimeout(() => firstInput.focus(), 90);
    };

    const closeModal = () => {
        modal.classList.remove('open');
        document.body.classList.remove('modal-open');
        closeTimer = window.setTimeout(() => { modal.hidden = true; }, 170);
    };

    const filterUsers = () => {
        const term = normalize(searchInput.value);
        const role = roleFilter.value;
        const status = statusFilter.value;
        let visible = 0;

        rows.forEach((row) => {
            const matchesText = term === '' || normalize(row.dataset.search).includes(term);
            const matchesRole = role === '' || row.dataset.role === role;
            const matchesStatus = status === '' || row.dataset.status === status;
            const show = matchesText && matchesRole && matchesStatus;
            row.hidden = !show;
            if (show) visible += 1;
        });

        noResults.hidden = visible !== 0;
        resultCount.textContent = `${visible} ${visible === 1 ? 'usuario' : 'usuarios'}`;
    };

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
        child.disabled = parentId === '' || userRole.value === '1';
    };

    const updateLocation = () => {
        const globalAdmin = userRole.value === '1';
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
    userRole.addEventListener('change', updateLocation);
    updateLocation();

    openButton.addEventListener('click', openModal);
    modal.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
    [searchInput, roleFilter, statusFilter].forEach((control) => {
        control.addEventListener('input', filterUsers);
        control.addEventListener('change', filterUsers);
    });
    clearFilters.addEventListener('click', () => {
        searchInput.value = '';
        roleFilter.value = '';
        statusFilter.value = '';
        filterUsers();
        searchInput.focus();
    });
    filterUsers();

    <?php if ($abrirModalPorError): ?>
    openModal();
    <?php endif; ?>
})();
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
