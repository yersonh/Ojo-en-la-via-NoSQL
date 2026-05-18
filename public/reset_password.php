<?php
session_start();

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $db       = conectarMongoDB();
    $usuarios = $db->usuario;
} catch (Throwable $e) {
    $_SESSION['rp_error'] = 'Error de conexión. Intenta más tarde.';
    header('Location: reset_password.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? $_SESSION['rp_token'] ?? '');

// ── Estado de error pre-renderizado ──────────────────────────────────────────
$errorPagina = '';

if ($token === '') {
    $errorPagina = 'El enlace de recuperación no es válido. Solicita uno nuevo.';
} else {
    $usuario = $usuarios->findOne(['reset_password.token' => $token]);

    if (!$usuario) {
        $errorPagina = 'El enlace ya fue usado o no es válido. Solicita uno nuevo.';
    } elseif (
        isset($usuario['reset_password']['expira']) &&
        $usuario['reset_password']['expira']->toDateTime()->getTimestamp() < time()
    ) {
        $errorPagina = 'El enlace ha caducado (válido 1 hora). Solicita uno nuevo.';
    }
}

// ── PRG: procesar POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorPagina === '') {
    $password  = $_POST['password']  ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if ($password === '' || $confirmar === '') {
        $_SESSION['rp_msg']   = 'Completa todos los campos.';
        $_SESSION['rp_tipo']  = 'error';
        $_SESSION['rp_token'] = $token;
    } elseif (strlen($password) < 8) {
        $_SESSION['rp_msg']   = 'La contraseña debe tener al menos 8 caracteres.';
        $_SESSION['rp_tipo']  = 'error';
        $_SESSION['rp_token'] = $token;
    } elseif ($password !== $confirmar) {
        $_SESSION['rp_msg']   = 'Las contraseñas no coinciden.';
        $_SESSION['rp_tipo']  = 'error';
        $_SESSION['rp_token'] = $token;
    } else {
        $usuarios->updateOne(
            ['_id' => $usuario['_id']],
            [
                '$set'   => ['password' => password_hash($password, PASSWORD_DEFAULT)],
                '$unset' => ['reset_password' => ''],
            ]
        );
        unset($_SESSION['rp_token']);
        $_SESSION['rp_msg']  = 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.';
        $_SESSION['rp_tipo'] = 'ok';
    }

    header('Location: reset_password.php' . ($errorPagina === '' && ($_SESSION['rp_tipo'] ?? '') !== 'ok' ? '?token=' . urlencode($token) : ''));
    exit;
}

// ── Leer mensajes flash ───────────────────────────────────────────────────────
$mensaje = $_SESSION['rp_msg']  ?? '';
$tipo    = $_SESSION['rp_tipo'] ?? '';
unset($_SESSION['rp_msg'], $_SESSION['rp_tipo']);

// Si el flash es "ok" ya no necesitamos el token en la URL
if ($tipo === 'ok') {
    $token = '';
}

// Recuperar token desde sesión si viene de PRG con error
if ($token === '' && !empty($_SESSION['rp_token'])) {
    $token = $_SESSION['rp_token'];
    unset($_SESSION['rp_token']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
    <title>Nueva contraseña — Ojo en la Vía</title>
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
        .input-box .toggle-pw {
            position: absolute;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            color: rgba(255,255,255,.5);
            font-size: .9rem;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        .input-box .toggle-pw:hover { color: rgba(255,255,255,.85); }
        .input-box input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 2px solid rgba(255,255,255,.6);
            outline: none;
            color: #fff;
            font-size: 1rem;
            padding: 10px 28px 10px 28px;
            transition: border-color .2s;
        }
        .input-box input::placeholder { color: rgba(255,255,255,.45); }
        .input-box input:focus { border-bottom-color: #1e88e5; }

        .strength-bar {
            height: 4px;
            border-radius: 2px;
            margin-top: 6px;
            background: rgba(255,255,255,.15);
            overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: width .3s, background .3s;
        }
        .strength-label {
            font-size: .75rem;
            margin-top: 4px;
            color: rgba(255,255,255,.5);
            min-height: 1em;
        }

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
        <div class="icon-wrap">
            <i class="fas <?= ($errorPagina !== '' || $tipo === 'ok') ? ($tipo === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation') : 'fa-key' ?>"></i>
        </div>
        <h1>Nueva contraseña</h1>
        <p>Ojo en la Vía</p>
    </div>

    <?php if ($errorPagina !== ''): ?>
        <div class="alert error">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($errorPagina) ?></span>
        </div>
        <a href="forgot_password.php" class="btn-submit" style="text-decoration:none;margin-bottom:0;">
            <i class="fas fa-paper-plane"></i> Solicitar nuevo enlace
        </a>

    <?php elseif ($tipo === 'ok'): ?>
        <div class="alert ok">
            <i class="fas fa-circle-check"></i>
            <span><?= htmlspecialchars($mensaje) ?></span>
        </div>
        <a href="index.php" class="btn-submit" style="text-decoration:none;margin-bottom:0;">
            <i class="fas fa-right-to-bracket"></i> Iniciar sesión
        </a>

    <?php else: ?>
        <?php if ($mensaje !== ''): ?>
            <div class="alert <?= htmlspecialchars($tipo) ?>">
                <i class="fas <?= $tipo === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <span><?= htmlspecialchars($mensaje) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="input-box">
                <i class="fas fa-lock ico"></i>
                <input type="password" name="password" id="pw" placeholder="Nueva contraseña" required autocomplete="new-password">
                <button type="button" class="toggle-pw" onclick="toggleVer('pw','eye1')">
                    <i class="fas fa-eye" id="eye1"></i>
                </button>
            </div>
            <div class="strength-bar"><div class="strength-bar-fill" id="strengthFill"></div></div>
            <div class="strength-label" id="strengthLabel"></div>

            <div class="input-box" style="margin-top:18px;">
                <i class="fas fa-lock-open ico"></i>
                <input type="password" name="confirmar" id="pw2" placeholder="Confirmar contraseña" required autocomplete="new-password">
                <button type="button" class="toggle-pw" onclick="toggleVer('pw2','eye2')">
                    <i class="fas fa-eye" id="eye2"></i>
                </button>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-floppy-disk"></i> Guardar contraseña
            </button>
        </form>
    <?php endif; ?>

    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
    </a>
</div>

<script>
function toggleVer(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

const pw    = document.getElementById('pw');
const fill  = document.getElementById('strengthFill');
const label = document.getElementById('strengthLabel');

if (pw) {
    pw.addEventListener('input', function () {
        const v = this.value;
        let score = 0;
        if (v.length >= 8)                     score++;
        if (v.length >= 12)                    score++;
        if (/[A-Z]/.test(v))                   score++;
        if (/[0-9]/.test(v))                   score++;
        if (/[^A-Za-z0-9]/.test(v))            score++;

        const pct    = ['0%','25%','50%','75%','100%'][score] || '0%';
        const colors = ['','#ef4444','#f59e0b','#3b82f6','#10b981','#10b981'];
        const labels = ['','Muy débil','Débil','Aceptable','Fuerte','Muy fuerte'];

        fill.style.width      = pct;
        fill.style.background = colors[score] || '#ef4444';
        label.textContent     = v.length ? labels[score] : '';
        label.style.color     = colors[score] || 'rgba(255,255,255,.5)';
    });
}
</script>
</body>
</html>
