<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

$nombreMostrar = trim($_SESSION['usuario_nombre'] ?? '');

if ($nombreMostrar === '') {
    $nombreMostrar = $_SESSION['usuario_email'] ?? 'Usuario';
}

$inicial = 'U';

if (!empty($nombreMostrar)) {
    $inicial = strtoupper(substr(trim($nombreMostrar), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil</title>

    <link rel="stylesheet" href="/views/components/Css_usuario/inicio-mapa.css">
</head>
<body>

    <div class="bottom-hover-zone" id="bottomHoverZone"></div>

    <nav class="bottom-nav" id="bottomNav">
        <a href="alertas.php">
            <span class="icon">🔔</span>
            <span>Alertas</span>
        </a>

        <a href="inicio.php">
            <span class="icon">🗺️</span>
            <span>Mapa</span>
        </a>

        <a href="perfil.php" class="active">
            <span class="icon">👤</span>
            <span>Perfil</span>
        </a>
    </nav>

    <main class="pagina-simple">
        <section class="perfil-contenedor">
            <div class="perfil-avatar-grande">
                <?php echo htmlspecialchars($inicial); ?>
            </div>

            <h2><?php echo htmlspecialchars($nombreMostrar); ?></h2>

            <p class="perfil-correo">
                <?php echo htmlspecialchars($_SESSION['usuario_email'] ?? 'Correo no disponible'); ?>
            </p>

            <a href="../../logout.php" class="btn-cerrar-sesion">
                Cerrar sesión
            </a>
        </section>
    </main>

    <script src="/views/components/JS_usuario/menu-inferior.js"></script>
</body>
</html>