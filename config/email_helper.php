<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarCorreoBrevo(string $destinatario, string $asunto, string $cuerpoHtml): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'a5bd10001@smtp-brevo.com';
        $mail->Password   = 'bskHMweYhvyAq3B';
        $mail->Port       = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom('ojoenlavia1@gmail.com', 'Ojo en la Vía');
        $mail->addAddress($destinatario);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHtml;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Error correo Brevo: ' . $mail->ErrorInfo);
        return false;
    }
}
