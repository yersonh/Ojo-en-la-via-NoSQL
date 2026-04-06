<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


$mensaje = '';
$tipo = '';

try {
    $db = conectarMongoDB();
    $usuarios = $db->usuario;
} catch (Throwable $e) {
    die("Error de conexión: " . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $mensaje = 'Debes escribir tu correo.';
        $tipo = 'error';
    } else {
        $usuario = $usuarios->findOne(['email' => $email]);

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $expira = time() + 3600;

            $usuarios->updateOne(
                ['email' => $email],
                ['$set' => [
                    'reset_token' => $token,
                    'reset_token_expira' => $expira
                ]]
            );

            $enlace = "http://127.0.0.1:8000/reset_password.php?token=" . urlencode($token);
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp-relay.brevo.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'a5bd10001@smtp-brevo.com';
                $mail->Password = 'bskHMweYhvyAq3B';
                $mail->Port = 587;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

                $mail->setFrom('ojoenlavia1@gmail.com', 'Ojo en la vía');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = 'Recuperar contraseña';
                $mail->Body = "
                    <h2>Recuperación de contraseña</h2>
                    <p>Recibimos una solicitud para cambiar tu contraseña.</p>
                    <p>Haz clic en el siguiente enlace:</p>
                    <p><a href='$enlace'>$enlace</a></p>
                    <p>Este enlace vence en 1 hora.</p>
                ";

                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];

                $mail->send();
                $mensaje = 'Se envió un enlace a tu correo.';
                $tipo = 'ok';

            } catch (Exception $e) {
                $mensaje = 'No se pudo enviar el correo: ' . $mail->ErrorInfo;
                $tipo = 'error';
            }
        } else {
            $mensaje = 'No existe una cuenta con ese correo.';
            $tipo = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña</title>
</head>
<body>
    <h2>Recuperar contraseña</h2>

    <?php if ($mensaje !== ''): ?>
        <div><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Correo electrónico</label>
        <input type="email" name="email" required>
        <button type="submit">Enviar enlace</button>
    </form>

    <p><a href="index.php">Volver al inicio de sesión</a></p>
</body>
</html>