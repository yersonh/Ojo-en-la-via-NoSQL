<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

if (($_SESSION['usuario_rol'] ?? 'ciudadano') !== 'admin') {
    header('Location: ../usuario/inicio.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
    <title>Panel Administrador</title>
</head>
<body>
    <h1>Panel Administrador</h1>
    <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador'); ?>.</p>
</body>
</html>
