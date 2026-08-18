<?php
declare(strict_types=1);

/**
 * Envío de eventos de correo hacia un webhook privado de n8n.
 *
 * n8n se encarga de autenticarse con Microsoft 365 y enviar el mensaje. La
 * Mesa de Servicio nunca almacena la contraseña del buzón de correo.
 */

final class CorreoN8nException extends RuntimeException
{
}

/** @return array<string, mixed> */
function correoN8nConfiguracionLocal(): array
{
    static $configuracion = null;

    if (is_array($configuracion)) {
        return $configuracion;
    }

    $ruta = (string) (getenv('MESA_CONFIG_FILE') ?: '');

    if ($ruta === '') {
        $ruta = APP_ROOT . '/config/configuracion.local.php';
    }

    $configuracion = [];

    if (is_file($ruta)) {
        $leida = require $ruta;

        if (is_array($leida)) {
            $configuracion = $leida;
        }
    }

    return $configuracion;
}

function correoN8nValor(
    string $variableEntorno,
    string $claveLocal,
    string $predeterminado = ''
): string {
    $entorno = getenv($variableEntorno);

    if ($entorno !== false && trim((string) $entorno) !== '') {
        return trim((string) $entorno);
    }

    $local = correoN8nConfiguracionLocal();

    return isset($local[$claveLocal])
        ? trim((string) $local[$claveLocal])
        : $predeterminado;
}

/** @return array{webhook_url:string,webhook_secret:string} */
function correoN8nConfiguracion(): array
{
    $url = correoN8nValor('MESA_N8N_WEBHOOK_URL', 'n8n_webhook_url');
    $secreto = correoN8nValor(
        'MESA_N8N_WEBHOOK_SECRET',
        'n8n_webhook_secret'
    );
    $partes = parse_url($url);
    $host = is_array($partes)
        ? strtolower((string) ($partes['host'] ?? ''))
        : '';
    $esHttps = is_array($partes)
        && strtolower((string) ($partes['scheme'] ?? '')) === 'https';
    $esHttpLocal = is_array($partes)
        && strtolower((string) ($partes['scheme'] ?? '')) === 'http'
        && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    $esHttpDesarrollo = !seguridadEsProduccion()
        && is_array($partes)
        && strtolower((string) ($partes['scheme'] ?? '')) === 'http';

    if (
        !is_array($partes)
        || empty($partes['host'])
        || (!$esHttps && !$esHttpLocal && !$esHttpDesarrollo)
        || strlen($secreto) < 32
    ) {
        throw new CorreoN8nException(
            'La integración privada con n8n no está configurada.'
        );
    }

    return [
        'webhook_url' => $url,
        'webhook_secret' => $secreto,
    ];
}

/**
 * @return array{configuracion:array{webhook_url:string,webhook_secret:string},cuerpo:string}
 */
function correoN8nPrepararSolicitud(
    string $destinatario,
    string $asunto,
    string $contenidoHtml,
    string $evento
): array {
    $destinatario = strtolower(trim($destinatario));
    $asunto = trim($asunto);

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        throw new CorreoN8nException('El destinatario del correo no es válido.');
    }

    if ($asunto === '' || $contenidoHtml === '') {
        throw new CorreoN8nException('El correo no tiene contenido válido.');
    }

    if (preg_match('/^[a-z0-9_]{3,60}$/', $evento) !== 1) {
        throw new CorreoN8nException('El tipo de evento de correo no es válido.');
    }

    $configuracion = correoN8nConfiguracion();
    $payload = [
        'event' => $evento,
        'recipient' => $destinatario,
        'subject' => $asunto,
        'html' => $contenidoHtml,
        'request_id' => bin2hex(random_bytes(16)),
        'sent_at' => gmdate('c'),
    ];

    try {
        $cuerpo = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    } catch (JsonException $e) {
        throw new CorreoN8nException(
            'No fue posible preparar la solicitud para n8n.',
            0,
            $e
        );
    }

    return [
        'configuracion' => $configuracion,
        'cuerpo' => $cuerpo,
    ];
}

function correoN8nEnviar(
    string $destinatario,
    string $asunto,
    string $contenidoHtml,
    string $evento = 'correo_sistema'
): void {
    if (!function_exists('curl_init')) {
        throw new CorreoN8nException(
            'La extensión cURL de PHP no está habilitada.'
        );
    }

    $solicitud = correoN8nPrepararSolicitud(
        $destinatario,
        $asunto,
        $contenidoHtml,
        $evento
    );
    $configuracion = $solicitud['configuracion'];
    $cuerpo = $solicitud['cuerpo'];

    $curl = curl_init($configuracion['webhook_url']);

    if ($curl === false) {
        throw new CorreoN8nException('No fue posible preparar la conexión con n8n.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Mesa-Secret: ' . $configuracion['webhook_secret'],
            'X-Mesa-Event: ' . $evento,
        ],
        CURLOPT_POSTFIELDS => $cuerpo,
    ]);

    $respuesta = curl_exec($curl);
    $codigoHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($respuesta === false) {
        throw new CorreoN8nException(
            'No fue posible comunicarse con n8n: ' . $error
        );
    }

    if ($codigoHttp < 200 || $codigoHttp >= 300) {
        throw new CorreoN8nException(
            'n8n rechazó la solicitud de envío de correo.'
        );
    }
}

/**
 * Entrega el evento a n8n y cierra la conexión sin esperar a que termine el
 * flujo de correo. Se usa para notificaciones informativas de tickets; los
 * flujos críticos, como recuperación de contraseña, siguen usando la llamada
 * confirmada de correoN8nEnviar().
 */
function correoN8nEnviarSinEspera(
    string $destinatario,
    string $asunto,
    string $contenidoHtml,
    string $evento = 'correo_sistema'
): void {
    $solicitud = correoN8nPrepararSolicitud(
        $destinatario,
        $asunto,
        $contenidoHtml,
        $evento
    );
    $configuracion = $solicitud['configuracion'];
    $cuerpo = $solicitud['cuerpo'];
    $partes = parse_url($configuracion['webhook_url']);

    if (!is_array($partes) || empty($partes['host'])) {
        throw new CorreoN8nException('La dirección de n8n no es válida.');
    }

    $esHttps = strtolower((string) ($partes['scheme'] ?? '')) === 'https';
    $host = (string) $partes['host'];
    $puerto = (int) ($partes['port'] ?? ($esHttps ? 443 : 80));
    $ruta = (string) ($partes['path'] ?? '/');

    if ($ruta === '') {
        $ruta = '/';
    }

    if (isset($partes['query']) && $partes['query'] !== '') {
        $ruta .= '?' . $partes['query'];
    }

    if (preg_match('/[\r\n]/', $ruta) === 1) {
        throw new CorreoN8nException('La dirección de n8n no es válida.');
    }

    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ],
    ]);
    $destino = ($esHttps ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;
    $numeroError = 0;
    $textoError = '';
    $socket = @stream_socket_client(
        $destino,
        $numeroError,
        $textoError,
        1.0,
        STREAM_CLIENT_CONNECT,
        $contexto
    );

    if (!is_resource($socket)) {
        throw new CorreoN8nException(
            'No fue posible entregar la notificación a n8n: ' . $textoError
        );
    }

    stream_set_timeout($socket, 1);
    $puertoPredeterminado = $esHttps ? 443 : 80;
    $hostCabecera = $host . ($puerto === $puertoPredeterminado ? '' : ':' . $puerto);
    $solicitudHttp = "POST {$ruta} HTTP/1.1\r\n"
        . "Host: {$hostCabecera}\r\n"
        . "Content-Type: application/json\r\n"
        . "Accept: application/json\r\n"
        . 'X-Mesa-Secret: ' . $configuracion['webhook_secret'] . "\r\n"
        . "X-Mesa-Event: {$evento}\r\n"
        . 'Content-Length: ' . strlen($cuerpo) . "\r\n"
        . "Connection: close\r\n\r\n"
        . $cuerpo;
    $total = strlen($solicitudHttp);
    $enviado = 0;

    while ($enviado < $total) {
        $bytes = fwrite($socket, substr($solicitudHttp, $enviado));

        if ($bytes === false || $bytes === 0) {
            fclose($socket);
            throw new CorreoN8nException(
                'La conexión con n8n se cerró antes de recibir la notificación.'
            );
        }

        $enviado += $bytes;
    }

    fflush($socket);
    fclose($socket);
}
