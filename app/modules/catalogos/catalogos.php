<?php
declare(strict_types=1);

/*
 * Administración exclusiva de catálogos.
 * Los servicios se gestionan de forma independiente en servicios.php.
 */
require_once APP_ROOT . '/security/validarSesion.php';
seguridadExigirRol([1]);
$idPaisOperacion = paisExigirContexto();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparCatalogo($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Guardar el orden de los catálogos mediante arrastrar y soltar.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['guardar_orden_catalogos'])
) {
    seguridadExigirOrigenPost();
    header('Content-Type: application/json; charset=UTF-8');

    $token = $_POST['csrf_token'] ?? '';
    $ordenRecibido = $_POST['orden'] ?? [];

    if (
        !is_string($token)
        || !hash_equals((string) $_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'mensaje' => 'La solicitud no es válida. Actualice la página.',
        ]);
        exit;
    }

    if (!is_array($ordenRecibido)) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'mensaje' => 'El orden recibido no es válido.',
        ]);
        exit;
    }

    $idsOrdenados = array_values(array_unique(array_filter(
        array_map('intval', $ordenRecibido),
        function ($id) {
            return $id > 0;
        }
    )));

    try {
        $resultadoActivos = $conn->query(
            "SELECT id_catalogo
             FROM catalogos
             WHERE estado = 'activo'
               AND id_pais_operacion = {$idPaisOperacion}"
        );
        $idsActivos = [];

        while ($fila = $resultadoActivos->fetch_assoc()) {
            $idsActivos[] = (int) $fila['id_catalogo'];
        }

        $idsValidacion = $idsOrdenados;
        sort($idsValidacion);
        sort($idsActivos);

        if ($idsValidacion !== $idsActivos) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'mensaje' => 'La lista cambió. Actualice la página.',
            ]);
            exit;
        }

        $conn->begin_transaction();
        $stmtOrden = $conn->prepare(
            "UPDATE catalogos
             SET orden = ?
             WHERE id_catalogo = ? AND id_pais_operacion = ?"
        );

        foreach ($idsOrdenados as $posicion => $idCatalogo) {
            $orden = $posicion + 1;
            $stmtOrden->bind_param("iii", $orden, $idCatalogo, $idPaisOperacion);

            if (!$stmtOrden->execute()) {
                throw new RuntimeException(
                    'No fue posible actualizar el orden.'
                );
            }
        }

        $stmtOrden->close();
        $conn->commit();

        echo json_encode([
            'ok' => true,
            'mensaje' => 'Orden guardado.',
        ]);
        exit;
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // La transacción pudo no haberse iniciado.
        }

        error_log(
            'Error al ordenar catálogos: ' . $e->getMessage()
        );
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'mensaje' => 'No fue posible guardar el orden.',
        ]);
        exit;
    }
}

$catalogos = $conn->query(
    "SELECT
        id_catalogo,
        nombre,
        descripcion,
        imagen,
        orden
     FROM catalogos
     WHERE estado = 'activo'
       AND id_pais_operacion = {$idPaisOperacion}
     ORDER BY orden ASC, nombre ASC"
);

$totalCatalogos = $catalogos ? $catalogos->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogos | Mesa de Servicio</title>
    <style>
        :root {
            --primary: #0e9f9a;
            --primary-dark: #08736f;
            --blue: #0f6fec;
            --navy: #102a43;
            --text: #243b53;
            --muted: #627d98;
            --surface: #ffffff;
            --background: #f3f6fb;
            --border: #dfe8f3;
            --success: #087443;
            --danger: #b42318;
            --shadow: 0 18px 46px rgba(16, 42, 67, 0.10);
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
                radial-gradient(
                    circle at 8% 0%,
                    rgba(15, 111, 236, 0.10),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 96% 92%,
                    rgba(14, 159, 154, 0.10),
                    transparent 25%
                ),
                var(--background);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.5;
        }

        button,
        a {
            font: inherit;
        }

        .page-shell {
            width: min(1180px, calc(100% - 40px));
            margin: 0 auto;
            padding: 28px 0 38px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
            padding: 14px 18px;
            border: 1px solid rgba(223, 232, 243, 0.92);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 8px 24px rgba(15, 45, 75, 0.06);
            backdrop-filter: blur(10px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            display: grid;
            flex: 0 0 auto;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(145deg, #12aaa5, #08736f);
            box-shadow: 0 8px 18px rgba(14, 159, 154, 0.25);
            font-size: 15px;
            font-weight: 850;
        }

        .brand p {
            margin: 0;
        }

        .brand-name {
            color: var(--navy);
            font-size: 16px;
            font-weight: 780;
        }

        .brand-subtitle {
            color: var(--muted);
            font-size: 12px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 14px;
            border: 1px solid transparent;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 750;
            transition:
                transform 0.18s ease,
                box-shadow 0.18s ease,
                background 0.18s ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button.secondary {
            border-color: #dce6f1;
            color: #486581;
            background: #f8fafc;
        }

        .button.primary {
            color: #fff;
            background: var(--primary);
            box-shadow: 0 7px 17px rgba(14, 159, 154, 0.22);
        }

        .hero {
            position: relative;
            overflow: hidden;
            min-height: 58px;
            padding: 9px 14px;
            border-radius: 12px;
            color: #fff;
            background:
                linear-gradient(
                    120deg,
                    #08736f 0%,
                    #0e9f9a 58%,
                    #18b9b2 100%
                );
            box-shadow: 0 8px 20px rgba(15, 45, 75, 0.10);
        }

        .hero::before,
        .hero::after {
            position: absolute;
            border-radius: 50%;
            content: "";
            pointer-events: none;
        }

        .hero::before {
            top: -45px;
            right: -18px;
            width: 112px;
            height: 112px;
            border: 16px solid rgba(255, 255, 255, 0.07);
        }

        .hero::after {
            right: 84px;
            bottom: -67px;
            width: 98px;
            height: 98px;
            background: rgba(255, 255, 255, 0.055);
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 760px;
        }

        .eyebrow {
            display: none;
        }

        .hero h1 {
            margin: 0;
            font-size: 17px;
            line-height: 1.18;
            letter-spacing: -0.2px;
        }

        .hero p {
            max-width: 720px;
            margin: 2px 0 0;
            color: rgba(255, 255, 255, 0.86);
            overflow: hidden;
            font-size: 11px;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .content {
            margin-top: 14px;
        }

        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 17px;
        }

        .section-heading h2,
        .section-heading p {
            margin: 0;
        }

        .section-heading h2 {
            color: var(--navy);
            font-size: 22px;
            letter-spacing: -0.4px;
        }

        .section-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
        }

        .total-chip {
            flex: 0 0 auto;
            padding: 7px 11px;
            border: 1px solid #dce7f3;
            border-radius: 9px;
            color: #486581;
            background: rgba(255, 255, 255, 0.84);
            font-size: 12px;
            font-weight: 750;
        }

        .catalogos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));
            gap: 15px;
            transition: opacity 0.16s ease;
        }

        .catalogos.guardando {
            opacity: 0.68;
            pointer-events: none;
        }

        .catalogo {
            position: relative;
            display: flex;
            min-width: 0;
            min-height: 145px;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 20px 15px;
            border: 1px solid var(--border);
            border-radius: 17px;
            color: var(--text);
            background: var(--surface);
            box-shadow: 0 9px 24px rgba(31, 62, 93, 0.07);
            text-align: center;
            cursor: grab;
            user-select: none;
            transition:
                transform 0.18s ease,
                opacity 0.16s ease,
                border-color 0.18s ease,
                box-shadow 0.18s ease;
        }

        .catalogo::before {
            position: absolute;
            top: 13px;
            right: 13px;
            color: #9bb0c4;
            content: "⋮⋮";
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -4px;
        }

        .catalogo:hover {
            transform: translateY(-3px);
            border-color: #a8d7d4;
            box-shadow: 0 15px 30px rgba(31, 62, 93, 0.12);
        }

        .catalogo:active {
            cursor: grabbing;
        }

        .catalogo.arrastrando {
            opacity: 0.34;
            transform: scale(0.96);
        }

        .catalogo img {
            width: 52px;
            height: 52px;
            margin-bottom: 12px;
            padding: 5px;
            border: 1px solid #dce6f0;
            border-radius: 12px;
            background: #f7faff;
            object-fit: contain;
        }

        .catalogo strong {
            overflow-wrap: anywhere;
            color: var(--navy);
            font-size: 14px;
        }

        .catalogo small {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
        }

        .order-status {
            display: inline-flex;
            min-height: 20px;
            align-items: center;
            margin-left: 6px;
            font-size: 12px;
            font-weight: 750;
        }

        .order-status.success {
            color: var(--success);
        }

        .order-status.error {
            color: var(--danger);
        }

        .empty {
            padding: 34px 22px;
            border: 1px dashed #cbd8e5;
            border-radius: 16px;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.74);
            text-align: center;
        }

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-top: 28px;
            padding: 0 4px;
            color: #829ab1;
            font-size: 12px;
        }

        @media (max-width: 720px) {
            .page-shell {
                width: min(100% - 24px, 1180px);
                padding-top: 12px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .top-actions {
                justify-content: flex-start;
                width: 100%;
            }

            .hero {
                min-height: 58px;
                padding: 9px 14px;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .total-chip {
                display: none;
            }

            .catalogos {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .footer {
                align-items: flex-start;
                flex-direction: column;
                gap: 4px;
            }
        }

        @media (max-width: 430px) {
            .catalogos {
                grid-template-columns: 1fr;
            }

            .button {
                flex: 1 1 140px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
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
            <div>
                <p class="brand-name">Mesa de Servicio</p>
                <p class="brand-subtitle">Administración de catálogos</p>
            </div>
        </div>

        <nav class="top-actions" aria-label="Acciones de catálogos">
            <a class="button secondary" href="panelAdmin.php">
                ← Volver al panel
            </a>
            <a class="button primary" href="editarcatalogos.php#listado">
                Administrar catálogos
            </a>
        </nav>
    </header>

    <section class="hero" aria-labelledby="titulo-catalogos">
        <div class="hero-content">
            <span class="eyebrow">Módulo administrativo</span>
            <h1 id="titulo-catalogos">Catálogos</h1>
            <p>
                Organice las clases de servicio. Puede arrastrarlas para definir
                el orden en que aparecerán dentro de la plataforma.
            </p>
        </div>
    </section>

    <section class="content" aria-labelledby="titulo-listado">
        <div class="section-heading">
            <div>
                <h2 id="titulo-listado">Catálogos disponibles</h2>
                <p>
                    Arrastre una tarjeta para cambiar su posición.
                    <span
                        id="estadoOrden"
                        class="order-status"
                        aria-live="polite"
                    ></span>
                </p>
            </div>
            <span class="total-chip">
                <?php echo (int) $totalCatalogos; ?>
                <?php echo $totalCatalogos === 1 ? 'catálogo' : 'catálogos'; ?>
            </span>
        </div>

        <?php if ($catalogos && $totalCatalogos > 0): ?>
            <div
                id="listaCatalogos"
                class="catalogos"
                role="list"
                data-csrf="<?php echo escaparCatalogo($_SESSION['csrf_token']); ?>"
            >
                <?php while ($catalogo = $catalogos->fetch_assoc()): ?>
                    <article
                        class="catalogo"
                        role="listitem"
                        draggable="true"
                        data-id="<?php echo (int) $catalogo['id_catalogo']; ?>"
                        title="<?php echo escaparCatalogo($catalogo['descripcion']); ?>"
                    >
                        <img
                            src="<?php echo escaparCatalogo(
                                seguridadUrlImagenCatalogo(
                                    (int) $catalogo['id_catalogo'],
                                    $catalogo['imagen']
                                )
                            ); ?>"
                            alt="Icono de <?php echo escaparCatalogo($catalogo['nombre']); ?>"
                        >
                        <strong>
                            <?php echo escaparCatalogo($catalogo['nombre']); ?>
                        </strong>
                        <small>
                            Posición <?php echo (int) $catalogo['orden']; ?>
                        </small>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                No hay catálogos activos. Use “Administrar catálogos” para crear
                o habilitar uno.
            </div>
        <?php endif; ?>
    </section>

    <footer class="footer">
        <span>Mesa de Servicio · Catálogos</span>
        <span>Ordenamiento y administración de clases de servicio</span>
    </footer>
</main>

<script>
    const listaCatalogos = document.getElementById('listaCatalogos');
    const estadoOrden = document.getElementById('estadoOrden');
    let catalogoArrastrado = null;
    let ordenModificado = false;

    if (listaCatalogos) {
        listaCatalogos.querySelectorAll('.catalogo').forEach(function (catalogo) {
            catalogo.addEventListener('dragstart', function (event) {
                catalogoArrastrado = catalogo;
                ordenModificado = false;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData(
                    'text/plain',
                    catalogo.dataset.id
                );

                window.setTimeout(function () {
                    catalogo.classList.add('arrastrando');
                }, 0);
            });

            catalogo.addEventListener('dragover', function (event) {
                event.preventDefault();

                if (
                    !catalogoArrastrado
                    || catalogoArrastrado === catalogo
                ) {
                    return;
                }

                const rectangulo = catalogo.getBoundingClientRect();
                const insertarDespues =
                    event.clientX > rectangulo.left + rectangulo.width / 2;

                listaCatalogos.insertBefore(
                    catalogoArrastrado,
                    insertarDespues ? catalogo.nextSibling : catalogo
                );
                ordenModificado = true;
            });

            catalogo.addEventListener('drop', function (event) {
                event.preventDefault();
            });

            catalogo.addEventListener('dragend', function () {
                catalogo.classList.remove('arrastrando');

                if (ordenModificado) {
                    guardarOrdenCatalogos();
                }

                catalogoArrastrado = null;
            });
        });
    }

    async function guardarOrdenCatalogos() {
        const datos = new URLSearchParams();
        datos.append('guardar_orden_catalogos', '1');
        datos.append('csrf_token', listaCatalogos.dataset.csrf);

        listaCatalogos.querySelectorAll('.catalogo').forEach(function (
            catalogo,
            indice
        ) {
            datos.append('orden[]', catalogo.dataset.id);

            const posicion = catalogo.querySelector('small');
            if (posicion) {
                posicion.textContent = 'Posición ' + (indice + 1);
            }
        });

        listaCatalogos.classList.add('guardando');
        estadoOrden.className = 'order-status';
        estadoOrden.textContent = 'Guardando...';

        try {
            const respuesta = await fetch('catalogos.php', {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: datos.toString()
            });
            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.ok) {
                throw new Error(
                    resultado.mensaje
                    || 'No fue posible guardar el orden.'
                );
            }

            estadoOrden.className = 'order-status success';
            estadoOrden.textContent = '✓ Orden guardado';
        } catch (error) {
            estadoOrden.className = 'order-status error';
            estadoOrden.textContent = '✕ ' + error.message;
        } finally {
            listaCatalogos.classList.remove('guardando');
        }
    }
</script>
<script src="assets/js/controlSesion.js" defer></script>
</body>
</html>
