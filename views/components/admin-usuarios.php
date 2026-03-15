<div class="section">
    <div class="section-header">
        <h3><i class="fas fa-users"></i> Gestión de Usuarios</h3>
        <div class="header-actions">
            <button onclick="abrirModalExportar()" class="btn btn-sm btn-success">
                <i class="fas fa-download"></i> Exportar
            </button>
        </div>
    </div>
    <div class="section-content">
        <!-- Filtros -->
        <div class="filters" style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center;">
            <div class="filter-group">
                <label for="filtroEstadoUsuarios">Filtrar por estado:</label>
                <select id="filtroEstadoUsuarios" class="form-control" style="width: 150px;">
                    <option value="">Todos</option>
                    <option value="Activo">Activo</option>
                    <option value="Inactivo">Inactivo</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroRol">Filtrar por rol:</label>
                <select id="filtroRol" class="form-control" style="width: 150px;">
                    <option value="">Todos</option>
                    <option value="Admin">Administrador</option>
                    <option value="Usuario">Usuario</option>
                </select>
            </div>
            <button onclick="resetFilters()" class="btn btn-sm btn-secondary">
                <i class="fas fa-refresh"></i> Limpiar
            </button>
        </div>

        <!-- Tabla de usuarios -->
        <div class="table-responsive">
            <table id="tablaUsuarios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Reportes</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($usuarios) > 0): ?>
                        <?php foreach($usuarios as $usuario): ?>
                        <tr data-estado="<?php echo htmlspecialchars($usuario['estado']); ?>"
                            data-rol="<?php echo htmlspecialchars($usuario['rol']); ?>">
                            <td><?php echo $usuario['id_usuario']; ?></td>
                            <td>
                                <div class="user-info">
                                    <strong><?php echo htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <i class="fas fa-envelope" style="color: #666; margin-right: 5px;"></i>
                                <?php echo htmlspecialchars($usuario['correo']); ?>
                            </td>
                            <td>
                                <?php if(!empty($usuario['telefono'])): ?>
                                    <i class="fas fa-phone" style="color: #666; margin-right: 5px;"></i>
                                    <?php echo htmlspecialchars($usuario['telefono']); ?>
                                <?php else: ?>
                                    <span style="color: #999;">No registrado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $usuario['rol'] == 'Admin' ? 'primary' : 'secondary'; ?>">
                                    <i class="fas fa-<?php echo $usuario['rol'] == 'Admin' ? 'crown' : 'user'; ?>"></i>
                                    <?php echo htmlspecialchars($usuario['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $usuario['estado'] == 'Activo' ? 'activo' : 'inactivo'; ?>">
                                    <i class="fas fa-<?php echo $usuario['estado'] == 'Activo' ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo htmlspecialchars($usuario['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="report-count <?php echo $usuario['total_reportes'] > 0 ? 'has-reports' : ''; ?>">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo $usuario['total_reportes']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if($usuario['id_usuario'] != $_SESSION['usuario_id']): ?>
                                <div class="action-buttons">
                                    <select class="cambiar-estado-usuario form-control-sm"
                                            data-id="<?php echo $usuario['id_usuario']; ?>"
                                            style="min-width: 120px;">
                                        <option value="1" <?php echo $usuario['id_estado'] == 1 ? 'selected' : ''; ?>>
                                            🟢 Activar
                                        </option>
                                        <option value="2" <?php echo $usuario['id_estado'] == 2 ? 'selected' : ''; ?>>
                                            🔴 Desactivar
                                        </option>
                                    </select>

                                    <button class="btn btn-sm btn-info view-user"
                                            data-id="<?php echo $usuario['id_usuario']; ?>"
                                            title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="current-user-badge">
                                    <i class="fas fa-user-check"></i> Tu cuenta
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-users fa-2x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                                <h4>No hay usuarios registrados</h4>
                                <p>Los usuarios aparecerán aquí cuando se registren en el sistema.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Estadísticas -->
        <div class="user-stats" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <div style="display: flex; justify-content: space-around; text-align: center;">
                <div>
                    <h4 style="margin: 0; color: #2c3e50;"><?php echo count($usuarios); ?></h4>
                    <small style="color: #666;">Total Usuarios</small>
                </div>
                <div>
                    <h4 style="margin: 0; color: #27ae60;">
                        <?php echo count(array_filter($usuarios, fn($u) => $u['estado'] === 'Activo')); ?>
                    </h4>
                    <small style="color: #666;">Usuarios Activos</small>
                </div>
                <div>
                    <h4 style="margin: 0; color: #e74c3c;">
                        <?php echo count(array_filter($usuarios, fn($u) => $u['estado'] === 'Inactivo')); ?>
                    </h4>
                    <small style="color: #666;">Usuarios Inactivos</small>
                </div>
                <div>
                    <h4 style="margin: 0; color: #3498db;">
                        <?php echo array_sum(array_column($usuarios, 'total_reportes')); ?>
                    </h4>
                    <small style="color: #666;">Total Reportes</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Exportación -->
<div id="modalExportar" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border-radius: 10px; width: 400px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h3 style="margin: 0; color: #2c3e50;">
                <i class="fas fa-download"></i> Exportar Usuarios
            </h3>
            <span class="close" onclick="cerrarModalExportar()" style="cursor: pointer; font-size: 1.5rem; color: #666;">&times;</span>
        </div>

        <div class="modal-body">
            <div class="form-group">
                <label for="formatoExportacion"><strong>Formato de exportación:</strong></label>
                <select id="formatoExportacion" class="form-control" style="margin-bottom: 15px;">
                    <option value="csv">CSV (Excel compatible)</option>
                    <option value="excel">Excel (XLSX)</option>
                    <option value="json">JSON</option>
                </select>
            </div>

            <div class="form-group">
                <label for="filtroExportacion"><strong>Filtrar por:</strong></label>
                <select id="filtroExportacion" class="form-control" style="margin-bottom: 15px;">
                    <option value="todos">Todos los usuarios</option>
                    <option value="activos">Solo usuarios activos</option>
                    <option value="inactivos">Solo usuarios inactivos</option>
                    <option value="admin">Solo administradores</option>
                    <option value="usuario">Solo usuarios normales</option>
                </select>
            </div>

            <div class="form-group">
                <label><strong>Campos a incluir:</strong></label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="camposExportar" value="id" checked> ID
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="camposExportar" value="nombre" checked> Nombre
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="camposExportar" value="correo" checked> Correo
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="camposExportar" value="telefono" checked> Teléfono
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="camposExportar" value="rol" checked> Rol
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="camposExportar" value="estado" checked> Estado
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="camposExportar" value="reportes" checked> Reportes
                    </label>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
            <button onclick="cerrarModalExportar()" class="btn btn-secondary">Cancelar</button>
            <button onclick="exportarUsuarios()" class="btn btn-success">
                <i class="fas fa-download"></i> Exportar
            </button>
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
}

.modal.show {
    display: block;
}

/* Animación del modal */
.modal-content {
    animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-50px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Estilos para checkboxes */
input[type="checkbox"] {
    width: 16px;
    height: 16px;
}
</style>

<script>
// Funciones del modal
function abrirModalExportar() {
    document.getElementById('modalExportar').classList.add('show');
    document.getElementById('modalExportar').style.display = 'block';
}

function cerrarModalExportar() {
    document.getElementById('modalExportar').classList.remove('show');
    document.getElementById('modalExportar').style.display = 'none';
}

// Cerrar modal al hacer click fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalExportar');
    if (event.target === modal) {
        cerrarModalExportar();
    }
}

// Función principal de exportación
function exportarUsuarios() {
    const formato = document.getElementById('formatoExportacion').value;
    const filtro = document.getElementById('filtroExportacion').value;

    // Obtener campos seleccionados
    const camposSeleccionados = [];
    document.querySelectorAll('input[name="camposExportar"]:checked').forEach(checkbox => {
        camposSeleccionados.push(checkbox.value);
    });

    if (camposSeleccionados.length === 0) {
        alert('Por favor selecciona al menos un campo para exportar.');
        return;
    }

    console.log('Exportando usuarios:', { formato, filtro, campos: camposSeleccionados });

    // Mostrar loading
    const exportBtn = document.querySelector('.modal-footer .btn-success');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exportando...';
    exportBtn.disabled = true;

    // Preparar datos para exportación
    const datosExportacion = {
        formato: formato,
        filtro: filtro,
        campos: camposSeleccionados
    };

    // Llamar a la función de exportación
    realizarExportacion(datosExportacion).finally(() => {
        // Restaurar botón
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        cerrarModalExportar();
    });
}

// Función para realizar la exportación
async function realizarExportacion(datos) {
    try {
        const response = await fetch('../../controllers/exportar_controlador.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'exportar_usuarios',
                datos: datos
            })
        });

        if (!response.ok) {
            throw new Error('Error en la exportación');
        }

        // Obtener el blob de la respuesta
        const blob = await response.blob();

        // Crear URL para descargar el archivo
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;

        // Nombre del archivo según el formato
        const fecha = new Date().toISOString().split('T')[0];
        let extension = '';
        let mimeType = '';

        switch(datos.formato) {
            case 'csv':
                extension = 'csv';
                mimeType = 'text/csv';
                break;
            case 'excel':
                extension = 'xlsx';
                mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'json':
                extension = 'json';
                mimeType = 'application/json';
                break;
        }

        a.download = `usuarios_${fecha}.${extension}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Mostrar mensaje de éxito
        mostrarMensajeExportacion('✅ Exportación completada correctamente', 'success');

    } catch (error) {
        console.error('Error en exportación:', error);
        mostrarMensajeExportacion('❌ Error en la exportación: ' + error.message, 'error');
    }
}

// Función para mostrar mensajes de exportación
function mostrarMensajeExportacion(mensaje, tipo) {
    const mensajeDiv = document.createElement('div');
    mensajeDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: bold;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        ${tipo === 'success' ? 'background: #27ae60;' : 'background: #e74c3c;'}
    `;

    mensajeDiv.innerHTML = `
        <i class="fas ${tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${mensaje}
    `;

    document.body.appendChild(mensajeDiv);

    setTimeout(() => {
        mensajeDiv.remove();
    }, 5000);
}

// Exportación rápida (sin modal)
function exportarUsuariosRapido() {
    const datos = {
        formato: 'csv',
        filtro: 'todos',
        campos: ['id', 'nombre', 'correo', 'telefono', 'rol', 'estado', 'reportes']
    };

    realizarExportacion(datos);
}

// Filtros de la tabla
function aplicarFiltros() {
    const filtroEstado = document.getElementById('filtroEstado').value;
    const filtroRol = document.getElementById('filtroRol').value;
    const filas = document.querySelectorAll('#tablaUsuarios tbody tr');

    filas.forEach(fila => {
        if (fila.cells.length > 1) {
            const estadoFila = fila.getAttribute('data-estado');
            const rolFila = fila.getAttribute('data-rol');

            const coincideEstado = !filtroEstado || estadoFila === filtroEstado;
            const coincideRol = !filtroRol || rolFila === filtroRol;

            fila.style.display = (coincideEstado && coincideRol) ? '' : 'none';
        }
    });
}

function resetFilters() {
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroRol').value = '';
    aplicarFiltros();
}

// Event listeners para filtros
document.addEventListener('DOMContentLoaded', function() {
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroRol = document.getElementById('filtroRol');

    if (filtroEstado) filtroEstado.addEventListener('change', aplicarFiltros);
    if (filtroRol) filtroRol.addEventListener('change', aplicarFiltros);
});

// Ver detalles de usuario
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('view-user') || e.target.closest('.view-user')) {
        const userId = e.target.closest('.view-user').getAttribute('data-id');
        verDetallesUsuario(userId);
    }
});

function verDetallesUsuario(userId) {
    alert(`Ver detalles del usuario ID: ${userId}\n\nEsta funcionalidad está en desarrollo.`);
}
</script>