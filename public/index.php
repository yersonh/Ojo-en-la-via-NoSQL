<?php
session_start();

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

$mensaje = '';
$tipo = '';

try {
    $db = conectarMongoDB();
    $usuarios = $db->usuario;
} catch (Throwable $e) {
    die("Error de conexión: " . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registro') {
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($nombre_completo === '' || $telefono === '' || $email === '' || $password === '') {
            $mensaje = 'Todos los campos son obligatorios.';
            $tipo = 'error';
        } else {
             $existeEmail = $usuarios->findOne(['email' => $email]);
            $existeTelefono = $usuarios->findOne(['telefono' => $telefono]);

            if ($existeEmail) {
                $mensaje = 'Ese correo ya está registrado.';
                $tipo = 'error';
            } elseif ($existeTelefono) {
                $mensaje = 'Ese número de teléfono ya está registrado.';
                $tipo = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $resultado = $usuarios->insertOne([
                    'nombre_completo' => $nombre_completo,
                    'telefono' => $telefono,
                    'email' => $email,
                    'password' => $hash,
                    'estado' => true,
                    'fecha_creacion' => date('Y-m-d H:i:s'),
                    'foto_perfil' => '',
                    'rol' => 'ciudadano'
                ]);

                if ($resultado->getInsertedCount() > 0) {
                    $mensaje = 'Usuario registrado correctamente.';
                    $tipo = 'ok';
                } else {
                    $mensaje = 'No se pudo registrar el usuario.';
                    $tipo = 'error';
                }
            }
        }
    }

    if ($accion === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $mensaje = 'Correo y contraseña son obligatorios.';
            $tipo = 'error';
        } else {
            $usuario = $usuarios->findOne(['email' => $email]);

            if (!$usuario) {
                $mensaje = 'Usuario no encontrado.';
                $tipo = 'error';
            } elseif (!($usuario['estado'] ?? false)) {
                $mensaje = 'Usuario inactivo.';
                $tipo = 'error';
            } elseif (!password_verify($password, $usuario['password'] ?? '')) {
                $mensaje = 'Contraseña incorrecta.';
                $tipo = 'error';
            } else {
                $nombreSesion = trim($usuario['nombre_completo'] ?? '');

                if ($nombreSesion === '') {
                    $nombreSesion = $usuario['email'] ?? 'Usuario';
                }

                $_SESSION['usuario_id'] = (string) $usuario['_id'];
                $_SESSION['usuario_nombre'] = $nombreSesion;
                $_SESSION['usuario_email'] = $usuario['email'] ?? '';
                $_SESSION['foto_perfil'] = $usuario['foto_perfil'] ?? '';
                $_SESSION['usuario_rol'] = $usuario['rol'] ?? 'ciudadano';

                if ($_SESSION['usuario_rol'] === 'admin') {
                    header('Location: views/admin/panel.php');
                    exit;
                }

                header('Location: views/usuario/inicio.php');
                exit;
            }
        }
    }

    if ($accion === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
    <title>Ojo en la vía - Iniciar sesión</title>

    <!-- Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;

            /* AQUI CAMBIAS LA IMAGEN DE FONDO */
            background: url('imagenes/login3.jpg') no-repeat center center/cover;
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
            z-index: 0;
        }

        .mensaje {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 900px;
            padding: 14px 18px;
            border-radius: 12px;
            z-index: 20;
            font-weight: bold;
            text-align: center;
        }

        .ok {
            background: rgba(220, 252, 231, 0.95);
            color: #166534;
        }

        .error {
            background: rgba(254, 226, 226, 0.95);
            color: #991b1b;
        }

        .sesion {
            position: fixed;
            top: 75px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 900px;
            padding: 16px 18px;
            border-radius: 12px;
            z-index: 20;
            background: rgba(255, 247, 237, 0.97);
            color: #9a3412;
            text-align: center;
        }

        .sesion form {
            margin-top: 10px;
        }

        .sesion button {
            background: #dc2626;
            border: none;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }

        .container {
            position: relative;
            z-index: 10;
            width: 90%;
            max-width: 1100px;
            min-height: 540px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-radius: 22px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.48);
            backdrop-filter: blur(7px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
        }

        .left-panel,
        .right-panel {
            padding: 60px 45px;
            color: white;
        }

        .left-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: rgba(255,255,255,0.03);
        }

        .left-panel h1 {
            font-size: 3rem;
            margin-bottom: 25px;
        }

        .left-panel p {
            font-size: 1.2rem;
            line-height: 1.6;
            max-width: 420px;
            margin-bottom: 30px;
        }

        .social-icons {
            display: flex;
            gap: 22px;
            font-size: 2rem;
        }

        .social-icons i {
            cursor: pointer;
            transition: 0.3s;
        }

        .social-icons i:hover {
            transform: scale(1.15);
            color: #2d8cf0;
        }

        .right-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(0, 0, 0, 0.15);
        }

        .right-panel h2 {
            font-size: 2.6rem;
            text-align: center;
            margin-bottom: 40px;
        }

        .input-box {
            position: relative;
            margin-bottom: 28px;
        }

        .input-box i {
            position: absolute;
            top: 14px;
            left: 0;
            color: #fff;
            font-size: 1rem;
        }

        .input-box input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 2px solid rgba(255,255,255,0.9);
            outline: none;
            color: white;
            font-size: 1.05rem;
            padding: 10px 10px 10px 30px;
        }

        .input-box input::placeholder {
            color: rgba(255,255,255,0.85);
        }

        .extra-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            font-size: 0.98rem;
            gap: 15px;
            flex-wrap: wrap;
        }

        .extra-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .extra-options a,
        .register-link a,
        .toggle-link a {
            color: #2d8cf0;
            text-decoration: none;
            font-weight: bold;
        }

        .extra-options a:hover,
        .register-link a:hover,
        .toggle-link a:hover {
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            background: #2d8cf0;
            border: none;
            color: white;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn:hover {
            background: #1874c9;
        }

        .register-link,
        .toggle-link {
            text-align: center;
            margin-top: 25px;
            color: white;
            font-size: 1rem;
        }

        .form-box {
            display: none;
        }

        .form-box.active {
            display: block;
        }

        @media (max-width: 900px) {
            .container {
                grid-template-columns: 1fr;
            }

            .left-panel {
                display: none;
            }

            .right-panel {
                padding: 40px 25px;
            }

            .right-panel h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<div class="overlay"></div>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje <?php echo htmlspecialchars($tipo); ?>">
        <?php echo htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="left-panel">
        <h1>¡Bienvenido!</h1>
        <p>
            Explora nuestra plataforma "Ojo en la vía", donde podrás reportar y consultar
            el estado de las calles de Villavicencio.
        </p>

        <div class="social-icons">
            <i class="fab fa-facebook"></i>
            <i class="fab fa-twitter"></i>
            <i class="fab fa-instagram"></i>
        </div>
    </div>

<div class="right-panel">
    <!-- LOGIN -->
    <div class="form-box active" id="loginBox">
        <h2>Iniciar Sesión</h2>

        <form method="POST">
            <input type="hidden" name="accion" value="login">

            <div class="input-box">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Contraseña" required>
            </div>

            <div class="extra-options">
                <label>
                    <input type="checkbox"> Recuérdame
                </label>
                <a href="forgot_password.php">¿Olvidaste tu contraseña?</a>
            </div>

            <button class="btn" type="submit">Ingresar</button>
        </form>

        <div class="register-link">
            ¿No tienes cuenta?
            <a href="#" onclick="mostrarRegistro()">Regístrate</a>
        </div>
    </div>

    <!-- REGISTRO -->
    <div class="form-box" id="registerBox">
        <h2>Crear Cuenta</h2>

        <form method="POST">
            <input type="hidden" name="accion" value="registro">

            <div class="input-box">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="nombre_completo" placeholder="Nombre completo" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-phone"></i>
                <input type="text" name="telefono" placeholder="Número telefónico" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Contraseña" required>
            </div>

            <button class="btn" type="submit">Registrarme</button>
        </form>

        <div class="toggle-link">
            ¿Ya tienes cuenta?
            <a href="#" onclick="mostrarLogin()">Inicia sesión</a>
        </div>
    </div>
</div>

<script>
    function mostrarRegistro() {
        document.getElementById('loginBox').classList.remove('active');
        document.getElementById('registerBox').classList.add('active');
    }

    function mostrarLogin() {
        document.getElementById('registerBox').classList.remove('active');
        document.getElementById('loginBox').classList.add('active');
    }
</script>

</body>
</html>
