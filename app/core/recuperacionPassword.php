<?php
declare(strict_types=1);

require_once APP_ROOT . '/core/correoN8n.php';

const RECUPERACION_VIGENCIA_MINUTOS = 10;
const RECUPERACION_MAX_INTENTOS_CODIGO = 5;
const RECUPERACION_MAX_SOLICITUDES_USUARIO = 3;
const RECUPERACION_MAX_SOLICITUDES_IP = 10;

function recuperacionSecreto(): string
{
    $secreto = correoN8nValor(
        'MESA_RECOVERY_PEPPER',
        'recovery_pepper'
    );

    if (strlen($secreto) < 32) {
        throw new RuntimeException(
            'El secreto de recuperación no está configurado correctamente.'
        );
    }

    return $secreto;
}

function recuperacionCodigoHash(
    int $idUsuario,
    string $codigo,
    string $secreto
): string {
    return hash_hmac(
        'sha256',
        $idUsuario . '|' . $codigo,
        $secreto
    );
}

function recuperacionIpHash(string $secreto): string
{
    return hash_hmac('sha256', seguridadIpCliente(), $secreto);
}

function recuperacionTablaDisponible(mysqli $conn): bool
{
    try {
        $resultado = $conn->query(
            "SHOW TABLES LIKE 'recuperaciones_password'"
        );

        return $resultado !== false && $resultado->num_rows === 1;
    } catch (Throwable $e) {
        error_log(
            'No fue posible verificar la recuperación de contraseña: '
            . $e->getMessage()
        );

        return false;
    }
}

function recuperacionEnmascararCorreo(string $correo): string
{
    $partes = explode('@', strtolower(trim($correo)), 2);

    if (count($partes) !== 2) {
        return 'el correo registrado';
    }

    $usuario = $partes[0];
    $dominio = $partes[1];
    $visible = function_exists('mb_substr')
        ? mb_substr($usuario, 0, min(2, mb_strlen($usuario, 'UTF-8')), 'UTF-8')
        : substr($usuario, 0, min(2, strlen($usuario)));

    return $visible . str_repeat('*', max(3, strlen($usuario) - 2)) . '@' . $dominio;
}

function recuperacionCorreoHtml(string $nombre, string $codigo): string
{
    $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $codigoSeguro = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
    $vigencia = RECUPERACION_VIGENCIA_MINUTOS;

    return <<<HTML
<!doctype html>
<html lang="es">
<body style="margin:0;background:#f3f6fb;font-family:Arial,sans-serif;color:#172033">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb;padding:28px 12px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #dce4ef;border-radius:16px;overflow:hidden">
        <tr><td style="background:#113b7a;padding:22px 28px;color:#ffffff;font-size:20px;font-weight:700">Mesa de Servicio</td></tr>
        <tr><td style="padding:30px 28px">
          <p style="margin:0 0 14px;font-size:16px">Hola, {$nombreSeguro}.</p>
          <p style="margin:0 0 22px;line-height:1.55">Recibimos una solicitud para restablecer la contraseña de su cuenta. Ingrese este código en la Mesa de Servicio:</p>
          <div style="margin:0 0 22px;padding:18px;text-align:center;background:#eef4ff;border:1px solid #c9d9f2;border-radius:12px;font-size:32px;font-weight:800;letter-spacing:8px;color:#113b7a">{$codigoSeguro}</div>
          <p style="margin:0 0 12px;line-height:1.55">El código vence en <strong>{$vigencia} minutos</strong> y solo puede utilizarse una vez.</p>
          <p style="margin:0;color:#5b677a;font-size:14px;line-height:1.5">Si no solicitó este cambio, ignore el correo. Su contraseña actual continuará funcionando.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Genera y envía un código cuando el correo pertenece a un usuario activo.
 *
 * El resultado siempre es genérico para impedir que terceros descubran qué
 * correos están registrados en la plataforma.
 */
function recuperacionSolicitarCodigo(mysqli $conn, string $correo): void
{
    if (!recuperacionTablaDisponible($conn)) {
        throw new RuntimeException(
            'La tabla de recuperación de contraseña no está instalada.'
        );
    }

    $secreto = recuperacionSecreto();
    $correo = strtolower(trim($correo));

    $stmt = $conn->prepare(
        "SELECT id_usuario, nombre
         FROM usuarios
         WHERE email = ?
           AND estado = 'activo'
         LIMIT 1"
    );
    $stmt->bind_param('s', $correo);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario) {
        // Trabajo constante mínimo para reducir diferencias de tiempo.
        hash_hmac('sha256', 'usuario-no-encontrado|' . $correo, $secreto);
        return;
    }

    $idUsuario = (int) $usuario['id_usuario'];
    $ipHash = recuperacionIpHash($secreto);

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM recuperaciones_password
         WHERE id_usuario = ?
           AND creado_en >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $solicitudesUsuario = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM recuperaciones_password
         WHERE solicitado_ip_hash = ?
           AND creado_en >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $stmt->bind_param('s', $ipHash);
    $stmt->execute();
    $solicitudesIp = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    if (
        $solicitudesUsuario >= RECUPERACION_MAX_SOLICITUDES_USUARIO
        || $solicitudesIp >= RECUPERACION_MAX_SOLICITUDES_IP
    ) {
        return;
    }

    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codigoHash = recuperacionCodigoHash($idUsuario, $codigo, $secreto);
    $expiraEn = date(
        'Y-m-d H:i:s',
        time() + (RECUPERACION_VIGENCIA_MINUTOS * 60)
    );
    $idRecuperacion = 0;

    try {
        $conn->begin_transaction();
        $conn->query(
            "DELETE FROM recuperaciones_password
             WHERE creado_en < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        $stmt = $conn->prepare(
            "UPDATE recuperaciones_password
             SET usado_en = COALESCE(usado_en, NOW())
             WHERE id_usuario = ?
               AND usado_en IS NULL"
        );
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO recuperaciones_password
                (id_usuario, codigo_hash, solicitado_ip_hash, expira_en)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isss',
            $idUsuario,
            $codigoHash,
            $ipHash,
            $expiraEn
        );
        $stmt->execute();
        $idRecuperacion = (int) $stmt->insert_id;
        $stmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignorado) {
        }

        throw $e;
    }

    try {
        correoN8nEnviar(
            $correo,
            'Código para restablecer su contraseña',
            recuperacionCorreoHtml((string) $usuario['nombre'], $codigo),
            'password_recovery'
        );
    } catch (Throwable $e) {
        try {
            $stmt = $conn->prepare(
                "UPDATE recuperaciones_password
                 SET usado_en = NOW()
                 WHERE id_recuperacion = ?"
            );
            $stmt->bind_param('i', $idRecuperacion);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $ignorado) {
        }

        error_log('No fue posible enviar el código de recuperación: ' . $e->getMessage());
    }
}

/**
 * @return 'ok'|'codigo_invalido'|'password_repetido'
 */
function recuperacionCambiarPassword(
    mysqli $conn,
    string $correo,
    string $codigo,
    string $passwordNueva
): string {
    if (!recuperacionTablaDisponible($conn)) {
        throw new RuntimeException(
            'La tabla de recuperación de contraseña no está instalada.'
        );
    }

    $secreto = recuperacionSecreto();
    $correo = strtolower(trim($correo));
    $stmt = $conn->prepare(
        "SELECT id_usuario, password
         FROM usuarios
         WHERE email = ?
           AND estado = 'activo'
         LIMIT 1"
    );
    $stmt->bind_param('s', $correo);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario) {
        hash_equals(
            hash_hmac('sha256', 'codigo-invalido', $secreto),
            hash_hmac('sha256', $codigo, $secreto)
        );
        return 'codigo_invalido';
    }

    $idUsuario = (int) $usuario['id_usuario'];

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare(
            "SELECT id_recuperacion, codigo_hash, intentos
             FROM recuperaciones_password
             WHERE id_usuario = ?
               AND usado_en IS NULL
               AND expira_en >= NOW()
               AND intentos < ?
             ORDER BY id_recuperacion DESC
             LIMIT 1
             FOR UPDATE"
        );
        $maxIntentos = RECUPERACION_MAX_INTENTOS_CODIGO;
        $stmt->bind_param('ii', $idUsuario, $maxIntentos);
        $stmt->execute();
        $recuperacion = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$recuperacion) {
            $conn->commit();
            return 'codigo_invalido';
        }

        $codigoEsperado = recuperacionCodigoHash($idUsuario, $codigo, $secreto);

        if (!hash_equals((string) $recuperacion['codigo_hash'], $codigoEsperado)) {
            $intentos = (int) $recuperacion['intentos'] + 1;
            $idRecuperacion = (int) $recuperacion['id_recuperacion'];
            $stmt = $conn->prepare(
                "UPDATE recuperaciones_password
                 SET intentos = ?,
                     usado_en = CASE WHEN ? >= ? THEN NOW() ELSE usado_en END
                 WHERE id_recuperacion = ?"
            );
            $stmt->bind_param(
                'iiii',
                $intentos,
                $intentos,
                $maxIntentos,
                $idRecuperacion
            );
            $stmt->execute();
            $stmt->close();
            $conn->commit();

            return 'codigo_invalido';
        }

        if (password_verify($passwordNueva, (string) $usuario['password'])) {
            $conn->commit();
            return 'password_repetido';
        }

        $hashPassword = password_hash($passwordNueva, PASSWORD_DEFAULT);

        if (!is_string($hashPassword) || $hashPassword === '') {
            throw new RuntimeException('No fue posible proteger la nueva contraseña.');
        }

        $stmt = $conn->prepare(
            "UPDATE usuarios
             SET password = ?
             WHERE id_usuario = ?
               AND estado = 'activo'"
        );
        $stmt->bind_param('si', $hashPassword, $idUsuario);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('No fue posible actualizar la contraseña.');
        }
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE recuperaciones_password
             SET usado_en = COALESCE(usado_en, NOW())
             WHERE id_usuario = ?"
        );
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $stmt->close();

        // Permite iniciar sesión desde el mismo equipo después del cambio.
        $claveLogin = hash(
            'sha256',
            $correo . '|' . seguridadIpCliente()
        );
        $stmt = $conn->prepare(
            'DELETE FROM seguridad_intentos_login WHERE clave = ?'
        );
        $stmt->bind_param('s', $claveLogin);
        $stmt->execute();
        $stmt->close();
        $conn->commit();

        return 'ok';
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignorado) {
        }

        throw $e;
    }
}
