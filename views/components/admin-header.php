<div class="header">
    <h1>Panel de Administración</h1>
    <div class="header-right">
        <!-- Notificaciones -->
        <?php include 'admin-notificaciones.php'; ?>

        <!-- Información del usuario -->
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['nombres'], 0, 1)); ?>
            </div>
            <div>
                <strong><?php echo htmlspecialchars($_SESSION['nombres']); ?></strong>
                <div style="font-size: 0.8rem; color: #666;">Administrador</div>
            </div>
        </div>
    </div>
</div>