<?php
declare(strict_types=1);

/**
 * Paletas permitidas para la interfaz. Las claves se guardan en la base de
 * datos; los colores siempre se resuelven desde este catálogo controlado.
 *
 * @return array<string, array<string, string>>
 */
function temasInterfazDisponibles(string $codigoPais = 'CO'): array
{
    $esPeru = strtoupper(trim($codigoPais)) === 'PE';

    return [
        'corporativo' => [
            'nombre' => 'Corporativo',
            'descripcion' => 'La identidad visual predeterminada de la operación.',
            'primary' => $esPeru ? '#c81e3a' : '#1167d8',
            'accent' => $esPeru ? '#f15b72' : '#38a7f3',
            'dark' => $esPeru ? '#171f2a' : '#072b58',
            'background' => '#f1f5f9',
            'surface' => '#ffffff',
            'soft' => $esPeru ? '#fff1f3' : '#edf6ff',
            'text' => '#263f57',
            'heading' => '#142d45',
            'muted' => '#60788d',
            'border' => '#d8e4ee',
            'sidebar_text' => '#d7e5f1',
            'sidebar_muted' => '#a9c2d9',
            'on_primary' => '#ffffff',
            'scheme' => 'light',
        ],
        'negro_blanco' => [
            'nombre' => 'Negro y blanco',
            'descripcion' => 'Fondo negro, textos claros y acciones blancas.',
            'primary' => '#f4f4f5',
            'accent' => '#a1a1aa',
            'dark' => '#000000',
            'background' => '#050505',
            'surface' => '#111111',
            'soft' => '#1c1c1f',
            'text' => '#f4f4f5',
            'heading' => '#ffffff',
            'muted' => '#b4b4bc',
            'border' => '#34343a',
            'sidebar_text' => '#f4f4f5',
            'sidebar_muted' => '#b4b4bc',
            'on_primary' => '#09090b',
            'scheme' => 'dark',
        ],
        'azul_rosado' => [
            'nombre' => 'Azul y rosado',
            'descripcion' => 'Paneles azul profundo y acciones rosadas.',
            'primary' => '#ec4899',
            'accent' => '#60a5fa',
            'dark' => '#0b1538',
            'background' => '#0d1736',
            'surface' => '#14234b',
            'soft' => '#1b2f62',
            'text' => '#f4f7ff',
            'heading' => '#ffffff',
            'muted' => '#b9c6e4',
            'border' => '#334b82',
            'sidebar_text' => '#f7f3ff',
            'sidebar_muted' => '#c8c1e3',
            'on_primary' => '#ffffff',
            'scheme' => 'dark',
        ],
        'rojo_negro' => [
            'nombre' => 'Rojo y negro',
            'descripcion' => 'Fondo negro, letras blancas y botones rojos.',
            'primary' => '#dc2626',
            'accent' => '#f87171',
            'dark' => '#000000',
            'background' => '#050505',
            'surface' => '#111111',
            'soft' => '#1c1c1c',
            'text' => '#f5f5f5',
            'heading' => '#ffffff',
            'muted' => '#c5c5c5',
            'border' => '#3a3030',
            'sidebar_text' => '#fff1f1',
            'sidebar_muted' => '#e4bcbc',
            'on_primary' => '#ffffff',
            'scheme' => 'dark',
        ],
        'verde' => [
            'nombre' => 'Verde esmeralda',
            'descripcion' => 'Paneles verde oscuro y acciones esmeralda de alto contraste.',
            'primary' => '#047857',
            'accent' => '#10b981',
            'dark' => '#021c16',
            'background' => '#032f28',
            'surface' => '#06483b',
            'soft' => '#0b5d4c',
            'text' => '#ecfdf5',
            'heading' => '#ffffff',
            'muted' => '#b7d9ce',
            'border' => '#16735e',
            'sidebar_text' => '#ecfdf5',
            'sidebar_muted' => '#b8ddd0',
            'on_primary' => '#ffffff',
            'scheme' => 'dark',
        ],
        'morado' => [
            'nombre' => 'Morado',
            'descripcion' => 'Paneles morado oscuro y acciones violetas.',
            'primary' => '#7c3aed',
            'accent' => '#a78bfa',
            'dark' => '#1d0b38',
            'background' => '#211239',
            'surface' => '#301b50',
            'soft' => '#43266d',
            'text' => '#f7f3ff',
            'heading' => '#ffffff',
            'muted' => '#d0c1e3',
            'border' => '#63448a',
            'sidebar_text' => '#f5f3ff',
            'sidebar_muted' => '#d2c7e7',
            'on_primary' => '#ffffff',
            'scheme' => 'dark',
        ],
        'noche_cian' => [
            'nombre' => 'Noche y cian',
            'descripcion' => 'Azul nocturno con acciones cian luminosas.',
            'primary' => '#0369a1',
            'accent' => '#22d3ee',
            'dark' => '#061827',
            'background' => '#071b2d',
            'surface' => '#0c2a43',
            'soft' => '#123d5c',
            'text' => '#f0f9ff',
            'heading' => '#ffffff',
            'muted' => '#b7d8ea',
            'border' => '#245677',
            'sidebar_text' => '#f0f9ff',
            'sidebar_muted' => '#b7d8ea',
            'on_primary' => '#ffffff',
            'scheme' => 'dark',
        ],
        'grafito_dorado' => [
            'nombre' => 'Grafito y dorado',
            'descripcion' => 'Grafito elegante con detalles dorados.',
            'primary' => '#b45309',
            'accent' => '#fbbf24',
            'dark' => '#09090b',
            'background' => '#111113',
            'surface' => '#1c1c1f',
            'soft' => '#2a2824',
            'text' => '#f5f5f4',
            'heading' => '#ffffff',
            'muted' => '#c9c4b8',
            'border' => '#4b4332',
            'sidebar_text' => '#fffaf0',
            'sidebar_muted' => '#d5c9aa',
            'on_primary' => '#ffffff',
            'scheme' => 'dark',
        ],
        'vino_coral' => [
            'nombre' => 'Vino y coral',
            'descripcion' => 'Paneles vino profundo con acentos coral.',
            'primary' => '#be123c',
            'accent' => '#fb7185',
            'dark' => '#310916',
            'background' => '#3b0b1d',
            'surface' => '#571329',
            'soft' => '#731c38',
            'text' => '#fff1f2',
            'heading' => '#ffffff',
            'muted' => '#fecdd3',
            'border' => '#8f2d49',
            'sidebar_text' => '#fff1f2',
            'sidebar_muted' => '#fecdd3',
            'on_primary' => '#ffffff',
            'scheme' => 'dark',
        ],
        'petroleo_turquesa' => [
            'nombre' => 'Petróleo y turquesa',
            'descripcion' => 'Verde petróleo con acciones turquesa.',
            'primary' => '#0f766e',
            'accent' => '#2dd4bf',
            'dark' => '#042f2e',
            'background' => '#052f33',
            'surface' => '#0b4546',
            'soft' => '#115e59',
            'text' => '#f0fdfa',
            'heading' => '#ffffff',
            'muted' => '#b5e3de',
            'border' => '#24736f',
            'sidebar_text' => '#f0fdfa',
            'sidebar_muted' => '#b5e3de',
            'on_primary' => '#ffffff',
            'scheme' => 'dark',
        ],
        'arena_terracota' => [
            'nombre' => 'Arena y terracota',
            'descripcion' => 'Fondos cálidos y acciones terracota.',
            'primary' => '#9a3412',
            'accent' => '#ea580c',
            'dark' => '#431407',
            'background' => '#f7efe4',
            'surface' => '#fffaf2',
            'soft' => '#f3e2cf',
            'text' => '#4a2a1b',
            'heading' => '#2f160c',
            'muted' => '#745547',
            'border' => '#dec8af',
            'sidebar_text' => '#fff7ed',
            'sidebar_muted' => '#fed7aa',
            'on_primary' => '#ffffff',
            'scheme' => 'light',
        ],
        'hielo_indigo' => [
            'nombre' => 'Hielo e índigo',
            'descripcion' => 'Superficies claras con acciones índigo.',
            'primary' => '#4338ca',
            'accent' => '#818cf8',
            'dark' => '#1e1b4b',
            'background' => '#eef2ff',
            'surface' => '#ffffff',
            'soft' => '#e0e7ff',
            'text' => '#31385d',
            'heading' => '#171b3c',
            'muted' => '#596487',
            'border' => '#c7d2fe',
            'sidebar_text' => '#eef2ff',
            'sidebar_muted' => '#c7d2fe',
            'on_primary' => '#ffffff',
            'scheme' => 'light',
        ],
    ];
}

function temaInterfazNormalizar(string $tema, string $codigoPais = 'CO'): string
{
    $tema = strtolower(trim($tema));

    return array_key_exists($tema, temasInterfazDisponibles($codigoPais))
        ? $tema
        : 'corporativo';
}

function temaInterfazRgb(string $hexadecimal): string
{
    $hexadecimal = ltrim(trim($hexadecimal), '#');

    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hexadecimal)) {
        return '17,103,216';
    }

    return implode(',', [
        (string) hexdec(substr($hexadecimal, 0, 2)),
        (string) hexdec(substr($hexadecimal, 2, 2)),
        (string) hexdec(substr($hexadecimal, 4, 2)),
    ]);
}

/** @return array<string, string> */
function temaInterfazResolver(string $tema, string $codigoPais = 'CO'): array
{
    $temas = temasInterfazDisponibles($codigoPais);

    return $temas[temaInterfazNormalizar($tema, $codigoPais)];
}

function temaInterfazUsuario(
    mysqli $conn,
    int $idUsuario,
    string $codigoPais = 'CO'
): string {
    if ($idUsuario < 1) {
        return 'corporativo';
    }

    try {
        $stmt = $conn->prepare(
            "SELECT tema
             FROM usuario_preferencias_interfaz
             WHERE id_usuario = ?
             LIMIT 1"
        );
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return temaInterfazNormalizar(
            (string) ($fila['tema'] ?? 'corporativo'),
            $codigoPais
        );
    } catch (Throwable $e) {
        error_log(
            'No fue posible cargar el tema de interfaz del usuario: '
            . $e->getMessage()
        );

        return 'corporativo';
    }
}

function temaInterfazGuardarUsuario(
    mysqli $conn,
    int $idUsuario,
    string $tema,
    string $codigoPais = 'CO'
): string {
    if ($idUsuario < 1) {
        throw new DomainException('No fue posible identificar al usuario.');
    }

    $temaNormalizado = temaInterfazNormalizar($tema, $codigoPais);

    if ($temaNormalizado !== strtolower(trim($tema))) {
        throw new DomainException('El tema seleccionado no está disponible.');
    }

    $stmt = $conn->prepare(
        "INSERT INTO usuario_preferencias_interfaz
            (id_usuario, tema)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE
            tema = VALUES(tema),
            actualizado_en = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param('is', $idUsuario, $temaNormalizado);
    $stmt->execute();
    $stmt->close();

    return $temaNormalizado;
}
