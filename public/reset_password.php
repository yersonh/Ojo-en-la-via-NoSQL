<?php
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

$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($token === '') {
    die('Token no válido.');
}

$usuario = $usuarios->findOne([
    'reset_password.token' => $token
]);

if (!$usuario) {
    die('Token inválido o usuario no encontrado.');
}

if (isset($usuario['reset_password']['expira'])) {
    $vence = $usuario['reset_password']['expira']->toDateTime()->getTimestamp();

    if ($vence < time()) {
        die('El enlace ya venció.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirmar = trim($_POST['confirmar'] ?? '');

    if ($password === '' || $confirmar === '') {
        $mensaje = 'Completa todos los campos.';
        $tipo = 'error';
    } elseif ($password !== $confirmar) {
        $mensaje = 'Las contraseñas no coinciden.';
        $tipo = 'error';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $usuarios->updateOne(
            ['_id' => $usuario['_id']],
            [
                '$set' => ['password' => $hash],
                '$unset' => [
                    'reset_password' => ''
                ]
            ]
        );

        $mensaje = 'Contraseña actualizada correctamente.';
        $tipo = 'ok';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
    <title>Cambiar contraseña</title>
</head>
<body>
    <h2>Nueva contraseña</h2>

    <?php if ($mensaje !== ''): ?>
        <div><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <label>Nueva contraseña</label>
        <input type="password" name="password" required>

        <label>Confirmar contraseña</label>
        <input type="password" name="confirmar" required>

        <button type="submit">Cambiar contraseña</button>
    </form>
</body>
</html>
