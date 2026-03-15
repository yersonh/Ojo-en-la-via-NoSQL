<?php
$autoridades = $adminControlador->obtenerAutoridades();
?>

<div id="alertaModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-bell"></i> Enviar Alerta a Autoridad</h3>
            <button class="close-modal">&times;</button>
        </div>

        <div class="modal-body">
            <div class="form-group">
                <label for="autoridadDestino">Seleccionar Autoridad:</label>
                <select id="autoridadDestino" class="form-control">
                    <option value="">-- Seleccione una autoridad --</option>
                    <?php foreach($autoridades as $autoridad): ?>
                    <option value="<?php echo $autoridad['id']; ?>"
                            data-descripcion="<?php echo $autoridad['descripcion']; ?>">
                        <?php echo $autoridad['nombre']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted" id="descripcionAutoridad"></small>
            </div>

            <div class="form-group">
                <label for="emailPersonalizado">Email (opcional):</label>
                <input type="email" id="emailPersonalizado" class="form-control"
                       placeholder="Usar email diferente al predeterminado">
                <small class="text-muted">Dejar vacío para usar el email predeterminado de la autoridad</small>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Información del Reporte:</strong>
                <div id="infoReporte"></div>
            </div>

            <div class="historial-alertas" id="historialAlertas" style="display: none;">
                <h4><i class="fas fa-history"></i> Alertas Anteriores</h4>
                <div id="listaHistorial"></div>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn btn-secondary" id="cancelarAlerta">Cancelar</button>
            <button class="btn btn-primary" id="enviarAlerta">
                <i class="fas fa-paper-plane"></i> Enviar Alerta
            </button>
        </div>
    </div>
</div>