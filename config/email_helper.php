<?php

/**
 * Envía un correo usando la API REST de Brevo (no SMTP).
 * Usar enviarCorreoBackground() desde peticiones HTTP para no bloquear.
 */
function enviarCorreoBrevo(string $destinatario, string $asunto, string $cuerpoHtml): bool
{
    $apiKey  = $_ENV['BREVO_API_KEY']    ?? getenv('BREVO_API_KEY');
    $from    = $_ENV['SMTP_FROM']        ?? getenv('SMTP_FROM');
    $fromName= $_ENV['SMTP_FROM_NAME']   ?? getenv('SMTP_FROM_NAME') ?? 'Ojo en la Vía';

    if (!$apiKey || !$from) {
        error_log('Brevo: faltan variables de entorno BREVO_API_KEY o SMTP_FROM');
        return false;
    }

    $payload = json_encode([
        'sender'      => ['name' => $fromName, 'email' => $from],
        'to'          => [['email' => $destinatario]],
        'subject'     => $asunto,
        'htmlContent' => $cuerpoHtml,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('Brevo curl error: ' . $error);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('Brevo API error (' . $httpCode . '): ' . $response);
        return false;
    }

    return true;
}

/**
 * Lanza el envío en un proceso separado para no bloquear la petición HTTP.
 */
function enviarCorreoBackground(string $destinatario, string $asunto, string $cuerpoHtml): void
{
    $script  = __DIR__ . '/cli_correo.php';
    $payload = base64_encode(json_encode([
        'destinatario' => $destinatario,
        'asunto'       => $asunto,
        'cuerpo'       => $cuerpoHtml,
    ]));

    $phpBin = PHP_BINARY;
    $cmd    = "$phpBin $script " . escapeshellarg($payload);

    if (PHP_OS_FAMILY === 'Windows') {
        popen("start /B $cmd", 'r');
    } else {
        exec("$cmd > /dev/null 2>&1 &");
    }
}
