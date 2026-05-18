<?php
session_start();

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email_helper.php';

use MongoDB\BSON\UTCDateTime;

$db       = conectarMongoDB();
$usuarios = $db->usuario;

// PRG: redirigir con estado en sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['fp_msg']  = 'Escribe un correo electrónico válido.';
        $_SESSION['fp_tipo'] = 'error';
    } else {
        $usuario = $usuarios->findOne(['email' => $email]);

        if ($usuario && ($usuario['estado'] ?? false)) {
            $token  = bin2hex(random_bytes(32));
            $expira = new DateTime('+1 hour');

            $usuarios->updateOne(
                ['email' => $email],
                ['$set' => [
                    'reset_password' => [
                        'token'  => $token,
                        'expira' => new UTCDateTime($expira->getTimestamp() * 1000),
                        'creado' => new UTCDateTime(),
                    ]
                ]]
            );

            $base   = rtrim(getenv('APP_URL') ?: 'https://ojo-en-la-via-nosql-production.up.railway.app', '/');
            $enlace = $base . '/reset_password.php?token=' . urlencode($token);

            $cuerpo = "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;'>
                <div style='background:#1a2332;padding:28px 32px;border-radius:14px 14px 0 0;text-align:center;'>
                    <h2 style='color:#fff;margin:0;font-size:1.4rem;'>Recuperar contraseña</h2>
                    <p style='color:rgba(255,255,255,.55);margin:6px 0 0;font-size:.88rem;'>Ojo en la Vía</p>
                </div>
                <div style='background:#f8fafc;padding:28px 32px;'>
                    <p style='color:#374151;font-size:.95rem;margin:0 0 18px;'>
                        Recibimos una solicitud para restablecer la contraseña de tu cuenta.<br>
                        Si no fuiste tú, puedes ignorar este mensaje.
                    </p>
                    <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:20px;'>
                        <tr>
                            <td align='center' style='background:#1e88e5;border-radius:10px;padding:16px 24px;'>
                                <a href='" . htmlspecialchars($enlace) . "'
                                   style='display:inline-block;color:#fff;text-decoration:none;font-weight:700;font-size:1rem;'>
                                    Restablecer contraseña
                                </a>
                            </td>
                        </tr>
                    </table>
                    <p style='color:#9ca3af;font-size:.8rem;text-align:center;margin:0;'>
                        Este enlace vence en <strong>1 hora</strong> y es de un solo uso.
                    </p>
                </div>
            </div>";

            $ok = enviarCorreoBrevo($email, 'Restablecer contraseña — Ojo en la Vía', $cuerpo);

            if ($ok) {
                $_SESSION['fp_msg']  = 'Te enviamos un enlace a ' . htmlspecialchars($email) . '. Revisa tu bandeja (y spam).';
                $_SESSION['fp_tipo'] = 'ok';
            } else {
                $_SESSION['fp_msg']  = 'No se pudo enviar el correo. Intenta más tarde.';
                $_SESSION['fp_tipo'] = 'error';
            }
        } else {
            // Mismo mensaje por seguridad (no revelar si el email existe)
            $_SESSION['fp_msg']  = 'Si ese correo está registrado y activo, recibirás un enlace en breve.';
            $_SESSION['fp_tipo'] = 'ok';
        }
    }

    header('Location: forgot_password.php');
    exit;
}

$mensaje = $_SESSION['fp_msg']  ?? '';
$tipo    = $_SESSION['fp_tipo'] ?? '';
unset($_SESSION['fp_msg'], $_SESSION['fp_tipo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
    <title>Recuperar contraseña — Ojo en la Vía</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: url('imagenes/login3.jpg') no-repeat center center / cover;
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 0;
        }

        .card {
            position: relative;
            z-index: 10;
            width: 90%;
            max-width: 440px;
            background: rgba(0,0,0,.52);
            backdrop-filter: blur(10px);
            border-radius: 22px;
            padding: 48px 42px;
            box-shadow: 0 12px 40px rgba(0,0,0,.4);
            color: #fff;
        }

        .brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .brand .icon-wrap {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 1.6rem;
        }
        .brand h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 6px; }
        .brand p  { font-size: .88rem; color: rgba(255,255,255,.6); }

        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            font-size: .88rem;
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert.ok    { background: rgba(16,185,129,.18); border: 1px solid rgba(16,185,129,.4); color: #6ee7b7; }
        .alert.error { background: rgba(239,68,68,.18);  border: 1px solid rgba(239,68,68,.4);  color: #fca5a5; }

        .input-box {
            position: relative;
            margin-bottom: 24px;
        }
        .input-box i.ico {
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            color: rgba(255,255,255,.7);
            font-size: .95rem;
            pointer-events: none;
        }
        .input-box input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 2px solid rgba(255,255,255,.6);
            outline: none;
            color: #fff;
            font-size: 1rem;
            padding: 10px 10px 10px 28px;
            transition: border-color .2s;
        }
        .input-box input::placeholder { color: rgba(255,255,255,.45); }
        .input-box input:focus { border-bottom-color: #1e88e5; }

        .btn-submit {
            width: 100%;
            background: #1e88e5;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover { background: #1565c0; }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,.6);
            font-size: .88rem;
            text-decoration: none;
            transition: color .2s;
        }
        .back-link:hover { color: #fff; }
        .back-link i { margin-right: 5px; }
    </style>
</head>
<body>
<div class="overlay"></div>

<div class="card">
    <div class="brand">
        <div class="icon-wrap"><i class="fas fa-lock-open"></i></div>
        <h1>Recuperar contraseña</h1>
        <p>Escribe tu correo y te enviaremos un enlace para restablecerla.</p>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert <?= htmlspecialchars($tipo) ?>">
            <i class="fas <?= $tipo === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <span><?= htmlspecialchars($mensaje) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($tipo !== 'ok'): ?>
    <form method="POST" novalidate>
        <div class="input-box">
            <i class="fas fa-envelope ico"></i>
            <input type="email" name="email" placeholder="Correo electrónico" required autocomplete="email">
        </div>
        <button type="submit" class="btn-submit">
            <i class="fas fa-paper-plane"></i> Enviar enlace
        </button>
    </form>
    <?php endif; ?>

    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
    </a>
</div>
</body>
</html>
