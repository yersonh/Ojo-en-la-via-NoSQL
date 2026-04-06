<?php
$totalNotificaciones = $adminControlador->contarNotificacionesNoLeidas($idUsuarioActual);
$notificaciones = $adminControlador->obtenerNotificacionesNoLeidas($idUsuarioActual, 5);
?>

<div class="notificaciones-wrapper">
    <div class="notificacion-icon" id="notificacionIcon">
        <i class="fas fa-bell"></i>
        <?php if ($totalNotificaciones > 0): ?>
            <span class="notificacion-badge"><?php echo $totalNotificaciones; ?></span>
        <?php endif; ?>
    </div>

    <div class="notificaciones-panel" id="notificacionesPanel">
        <div class="notificaciones-header">
            <h4>Notificaciones</h4>
            <div class="notificaciones-actions">
                <?php if ($totalNotificaciones > 0): ?>
                    <span class="notificacion-count"><?php echo $totalNotificaciones; ?> sin leer</span>
                    <button class="btn-marcar-todas" id="marcarTodasLeidas" title="Marcar todas como leídas">
                        <i class="fas fa-check-double"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="notificaciones-list">
            <?php if (count($notificaciones) > 0): ?>
                <?php foreach($notificaciones as $notif): ?>
                <div class="notificacion-item <?php echo !$notif['leida'] ? 'no-leida' : ''; ?>"
                     data-id="<?php echo $notif['id_notificacion']; ?>">
                    <div class="notificacion-icono">
                        <?php
                        $icono = 'fa-bell';
                        $color = 'text-primary';

                        switch($notif['tipo']) {
                            case 'nuevo_reporte':
                                $icono = 'fa-exclamation-circle';
                                $color = 'text-warning';
                                break;
                            case 'alerta_autoridad':
                                $icono = 'fa-paper-plane';
                                $color = 'text-info';
                                break;
                            case 'like':
                                $icono = 'fa-heart';
                                $color = 'text-danger';
                                break;
                            case 'comentario':
                                $icono = 'fa-comment';
                                $color = 'text-success';
                                break;
                        }
                        ?>
                        <i class="fas <?php echo $icono; ?> <?php echo $color; ?>"></i>
                    </div>
                    <div class="notificacion-contenido">
                        <div class="notificacion-mensaje"><?php echo $notif['mensaje']; ?></div>
                        <div class="notificacion-meta">
                            <?php if ($notif['nombre_origen']): ?>
                                <span class="notificacion-origen">De: <?php echo $notif['nombre_origen']; ?></span>
                            <?php endif; ?>
                            <span class="notificacion-fecha">
                                <?php echo date('d/m/Y H:i', strtotime($notif['fecha'])); ?>
                            </span>
                        </div>
                        <div class="notificacion-acciones">
                            <?php if ($notif['id_reporte']): ?>
                                <a href="#" class="btn-ver-reporte"
                                    data-tab="reportes"
                                    data-reporte-id="<?php echo $notif['id_reporte']; ?>">
                                    <i class="fas fa-eye"></i> Ver Reporte
                                </a>
                            <?php endif; ?>
                            <?php if (!$notif['leida']): ?>
                                <button class="btn-marcar-leida">
                                    <i class="fas fa-check"></i> Marcar leída
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notificacion-vacia">
                    <i class="fas fa-check-circle"></i>
                    <p>No hay notificaciones nuevas</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="notificaciones-footer">
            <a href="#" data-tab="reportes">Ver todas las notificaciones</a>
        </div>
    </div>
</div>