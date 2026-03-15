<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Ojo en la Vía</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <link rel="stylesheet" href="styles/mapa.css">
    <link rel="stylesheet" href="styles/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'components/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'components/admin-header.php'; ?>

            <!-- Contenido de las pestañas -->
            <div id="dashboard" class="tab-content active">
                <?php include 'components/admin-dashboard.php'; ?>
            </div>

            <div id="reportes" class="tab-content">
                <?php include 'components/admin-reportes.php'; ?>
            </div>

            <div id="usuarios" class="tab-content">
                <?php include 'components/admin-usuarios.php'; ?>
            </div>

            <div id="analytics" class="tab-content">
                <?php include 'components/admin-analytics.php'; ?>
            </div>

            <div id="configuracion" class="tab-content">
                <?php include 'components/admin-configuracion.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="components/admin-analytics.js"></script>
    <script src="components/admin.js"></script>
    <script src="components/admin-configuracion.js"></script>
    <script src="components/admin-notificaciones.js"></script>

    <?php include 'components/admin-alertas-modal.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Admin panel cargado correctamente');
        if (typeof AdminManager !== 'undefined' && AdminManager.inicializar) {
            AdminManager.inicializar();
        }
        if (typeof AdminAnalytics !== 'undefined' && AdminAnalytics.inicializar) {
            AdminAnalytics.inicializar();
        }
        if (typeof AdminConfiguracion !== 'undefined' && AdminConfiguracion.inicializar) {
            AdminConfiguracion.inicializar();
        }
        if (typeof AdminNotificaciones !== 'undefined' && AdminNotificaciones.inicializar) {
            AdminNotificaciones.inicializar();
        }
    });
    </script>
</body>
</html>