<div class="dashboard-content">
    <div class="dashboard-cards">
        <div class="card reportes">
            <i class="fas fa-map-marker-alt" style="color: #3498db;"></i>
            <h3><?php echo $estadisticas['total_reportes']; ?></h3>
            <p>Total Reportes</p>
        </div>
        <div class="card usuarios">
            <i class="fas fa-users" style="color: #27ae60;"></i>
            <h3><?php echo $estadisticas['total_usuarios']; ?></h3>
            <p>Usuarios Registrados</p>
        </div>
        <div class="card pendientes">
            <i class="fas fa-clock" style="color: #f39c12;"></i>
            <h3>
                <?php
                    $pendientes = array_filter($estadisticas['reportes_por_estado'], function($item) {
                        return $item['estado'] === 'Pendiente';
                    });
                    echo count($pendientes) > 0 ? current($pendientes)['cantidad'] : 0;
                ?>
            </h3>
            <p>Reportes Pendientes</p>
        </div>
        <div class="card resueltos">
            <i class="fas fa-check-circle" style="color: #27ae60;"></i>
            <h3>
                <?php
                    $resueltos = array_filter($estadisticas['reportes_por_estado'], function($item) {
                        return $item['estado'] === 'Resuelto';
                    });
                    echo count($resueltos) > 0 ? current($resueltos)['cantidad'] : 0;
                ?>
            </h3>
            <p>Reportes Resueltos</p>
        </div>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="section">
        <div class="section-header">
            <h3><i class="fas fa-chart-line"></i> Estadísticas Rápidas</h3>
        </div>
        <div class="section-content">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tipo de Incidente</th>
                            <th>Cantidad</th>
                            <th>Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($estadisticas['tipos_comunes'] as $tipo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tipo['nombre']); ?></td>
                            <td><?php echo $tipo['cantidad']; ?></td>
                            <td>
                                <?php
                                    if ($estadisticas['total_reportes'] > 0) {
                                        echo round(($tipo['cantidad'] / $estadisticas['total_reportes']) * 100, 1) . '%';
                                    } else {
                                        echo '0%';
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mapa de Reportes -->
<div class="section">
    <div class="section-header">
        <h3><i class="fas fa-map-marked-alt"></i> Mapa de Reportes</h3>
        <!-- CAMBIA onclick por un event listener -->
        <button id="refreshMapBtn" class="btn btn-sm btn-primary">
            <i class="fas fa-sync-alt"></i> Actualizar
        </button>
    </div>
    <div class="section-content">
        <div id="adminMap" style="height: 500px; border-radius: 8px; background: #f8f9fa;">
            <div style="display: flex; justify-content: center; align-items: center; height: 100%; color: #666;">
                <div style="text-align: center;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>Cargando mapa...</p>
                </div>
            </div>
        </div>
    </div>
</div>
        <!-- Espacio adicional para asegurar scroll -->
        <div style="height: 100px; background: transparent;"></div>
    </div>