<div class="section">
    <div class="section-header">
        <h3><i class="fas fa-map-marker-alt"></i> Gestión de Reportes</h3>
        <div>
            <select id="filtroEstadoReportes" class="form-control" style="display: inline-block; width: auto;">
                <option value="">Todos los estados</option>
                <option value="Pendiente">Pendiente</option>
                <option value="En Proceso">En Proceso</option>
                <option value="Resuelto">Resuelto</option>
                <option value="Notificado">Notificado</option>
            </select>
        </div>
    </div>
    <div class="section-content">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reportes) > 0): ?>
                        <?php foreach($reportes as $reporte): ?>
                        <tr>
                            <td><?php echo $reporte['id_reporte']; ?></td>
                            <td><?php echo htmlspecialchars($reporte['tipo_incidente']); ?></td>
                            <td title="<?php echo htmlspecialchars($reporte['descripcion']); ?>">
                                <?php echo substr($reporte['descripcion'], 0, 50) . (strlen($reporte['descripcion']) > 50 ? '...' : ''); ?>
                            </td>
                            <td><?php echo htmlspecialchars($reporte['correo']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_reporte'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $reporte['estado'])); ?>">
                                    <?php echo $reporte['estado']; ?>
                                </span>
                            </td>
                            <td class="acciones-cell">
                                <div class="acciones-container">
                                    <select class="cambiar-estado" data-id="<?php echo $reporte['id_reporte']; ?>">
                                        <option value="Pendiente" <?php echo $reporte['estado'] == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="En Proceso" <?php echo $reporte['estado'] == 'En Proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                        <option value="Resuelto" <?php echo $reporte['estado'] == 'Resuelto' ? 'selected' : ''; ?>>Resuelto</option>
                                        <option value="Notificado" <?php echo $reporte['estado'] == 'Notificado' ? 'selected' : ''; ?>>Notificado</option>
                                    </select>

                                    <!-- Botón de alerta centrado -->
                                    <div class="btn-alerta-container">
                                        <button class="btn btn-warning btn-sm btn-alerta"
                                                data-id="<?php echo $reporte['id_reporte']; ?>"
                                                title="Enviar alerta a autoridad">
                                            <i class="fas fa-bell"></i>
                                        </button>
                                    </div>

                                    <button class="btn btn-danger btn-sm eliminar-reporte" data-id="<?php echo $reporte['id_reporte']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: #666;">
                                <i class="fas fa-inbox"></i> No hay reportes registrados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>