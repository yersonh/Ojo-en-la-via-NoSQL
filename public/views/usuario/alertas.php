<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

try {
    $db = conectarMongoDB();
    $reportes = $db->reportes;

    $cursor = $reportes->find(
        ['estado' => 'activo'],
        ['sort' => ['fecha_reporte' => -1]]
    );
} catch (Throwable $e) {
    $cursor = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas</title>

    <link rel="stylesheet" href="/views/components/Css_usuario/inicio-mapa.css">
</head>
<body>

    <div class="bottom-hover-zone" id="bottomHoverZone"></div>

    <nav class="bottom-nav" id="bottomNav">
        <a href="alertas.php" class="active">
            <span class="icon">🔔</span>
            <span>Alertas</span>
        </a>

        <a href="inicio.php">
            <span class="icon">🗺️</span>
            <span>Mapa</span>
        </a>

        <a href="perfil.php">
            <span class="icon">👤</span>
            <span>Perfil</span>
        </a>
    </nav>

    <main class="pagina-simple">
        <h2>Alertas recientes</h2>

        <div class="lista-alertas-pagina">
            <?php foreach ($cursor as $reporte): ?>
                <?php
                    $fechaFormateada = 'Fecha no disponible';

                    if (
                        !empty($reporte['fecha_reporte']) &&
                        $reporte['fecha_reporte'] instanceof \MongoDB\BSON\UTCDateTime
                    ) {
                        $fechaFormateada = $reporte['fecha_reporte']
                            ->toDateTime()
                            ->setTimezone(new DateTimeZone('America/Bogota'))
                            ->format('d/m/Y, H:i');
                    }
                ?>

                <article class="alerta-card">
                    <h3><?php echo htmlspecialchars($reporte['tipo'] ?? 'Incidente'); ?></h3>

                    <p>
                        <?php echo htmlspecialchars($reporte['descripcion'] ?? 'Sin descripción'); ?>
                    </p>

                    <small>
                        <?php echo htmlspecialchars($fechaFormateada); ?>
                    </small>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

    <script src="/views/components/JS_usuario/menu-inferior.js"></script>
</body>
</html>