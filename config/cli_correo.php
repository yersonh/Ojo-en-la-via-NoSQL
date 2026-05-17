<?php
/**
 * Script CLI ejecutado en background para enviar correos sin bloquear la petición HTTP.
 * Uso: php cli_correo.php <base64_json>
 */
require_once __DIR__ . '/email_helper.php';

$payload = json_decode(base64_decode($argv[1] ?? ''), true);

if (!$payload || empty($payload['destinatario'])) {
    exit(1);
}

enviarCorreoBrevo(
    $payload['destinatario'],
    $payload['asunto']  ?? '(sin asunto)',
    $payload['cuerpo']  ?? ''
);
