/* ════════════════════════════════════════════════════════
   TABLAS: filtros, cambio de estado, CRUD usuario/reporte
   Depende de: openModal, showToast (panel-ui.js)
════════════════════════════════════════════════════════ */

function filtrarTabla(tableId, countId) {
    const tabla = document.getElementById(tableId);
    if (!tabla) return;
    const rows = tabla.querySelectorAll('tbody tr[data-id]');
    let visible = 0;

    const buscarR   = (document.getElementById('buscar-reporte')?.value || '').toLowerCase();
    const estadoR   = (document.getElementById('filtro-estado-reporte')?.value || '').toLowerCase();
    const tipoR     = (document.getElementById('filtro-tipo-reporte')?.value || '').toLowerCase();

    const buscarU   = (document.getElementById('buscar-usuario')?.value || '').toLowerCase();
    const rolU      = (document.getElementById('filtro-rol')?.value || '').toLowerCase();
    const estadoU   = (document.getElementById('filtro-estado-usuario')?.value || '');

    rows.forEach(row => {
        let show = true;
        const texto  = (row.dataset.texto  || '').toLowerCase();
        const estado = (row.dataset.estado || '').toLowerCase();
        const tipo   = (row.dataset.tipo   || '').toLowerCase();
        const rol    = (row.dataset.rol    || '').toLowerCase();

        if (tableId === 'tabla-reportes') {
            if (buscarR && !texto.includes(buscarR)) show = false;
            if (estadoR && estado !== estadoR) show = false;
            if (tipoR   && tipo !== tipoR)     show = false;
        } else {
            if (buscarU && !texto.includes(buscarU))    show = false;
            if (rolU    && rol !== rolU)                show = false;
            if (estadoU !== '' && estado !== estadoU)   show = false;
        }

        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const counter = document.getElementById(countId);
    if (counter) counter.textContent = visible;
}

document.getElementById('buscar-reporte')?.addEventListener('input',    () => filtrarTabla('tabla-reportes','count-reportes'));
document.getElementById('filtro-estado-reporte')?.addEventListener('change', () => filtrarTabla('tabla-reportes','count-reportes'));
document.getElementById('filtro-tipo-reporte')?.addEventListener('change',   () => filtrarTabla('tabla-reportes','count-reportes'));

document.getElementById('buscar-usuario')?.addEventListener('input',    () => filtrarTabla('tabla-usuarios','count-usuarios'));
document.getElementById('filtro-rol')?.addEventListener('change',       () => filtrarTabla('tabla-usuarios','count-usuarios'));
document.getElementById('filtro-estado-usuario')?.addEventListener('change', () => filtrarTabla('tabla-usuarios','count-usuarios'));

/* ════════════════════════════════════════════════════════
   CAMBIAR ESTADO DE REPORTE
════════════════════════════════════════════════════════ */
document.querySelectorAll('.select-estado-reporte').forEach(sel => {
    sel.addEventListener('change', async function() {
        const id     = this.dataset.id;
        const estado = this.value;
        const row    = this.closest('tr');
        try {
            const fd = new FormData();
            fd.append('reporte_id', id);
            fd.append('estado', estado);
            const res = await fetch('actualizar_estado_reporte.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                row.dataset.estado = estado;
                const badge = row.querySelector('.badge');
                if (badge) {
                    badge.className = 'badge badge-' + estado;
                    badge.textContent = estado.replace('_',' ').replace(/\b\w/g, c => c.toUpperCase());
                }
                showToast('Estado actualizado', 'success');
            } else {
                showToast(data.mensaje || 'Error al actualizar', 'error');
            }
        } catch(e) {
            showToast('Error de conexión', 'error');
        }
    });
});

/* ════════════════════════════════════════════════════════
   ELIMINAR REPORTE (admin)
════════════════════════════════════════════════════════ */
document.querySelectorAll('.eliminar-reporte').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        openModal('Eliminar reporte', '¿Seguro que deseas eliminar este reporte? Se eliminarán también sus comentarios y likes.', 'btn-danger', async () => {
            const fd = new FormData();
            fd.append('reporte_id', id);
            fd.append('accion', 'eliminar_reporte_admin');
            try {
                const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    btn.closest('tr').remove();
                    showToast('Reporte eliminado', 'success');
                } else {
                    showToast(data.mensaje || 'Error', 'error');
                }
            } catch(e) {
                showToast('Error de conexión', 'error');
            }
        });
    });
});

/* ════════════════════════════════════════════════════════
   TOGGLE USUARIO (activar / desactivar)
════════════════════════════════════════════════════════ */
document.querySelectorAll('.toggle-usuario').forEach(btn => {
    btn.addEventListener('click', function() {
        const id     = this.dataset.id;
        const activo = this.dataset.estado === '1';
        const accion = activo ? 'desactivar' : 'activar';
        openModal(`${accion.charAt(0).toUpperCase()+accion.slice(1)} usuario`,
            `¿Deseas ${accion} este usuario?`,
            activo ? 'btn-danger' : 'btn-success',
            async () => {
                const fd = new FormData();
                fd.append('accion', 'toggle_usuario');
                fd.append('usuario_id', id);
                fd.append('estado', activo ? '0' : '1');
                try {
                    const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.ok) {
                        const nuevoEstado = !activo;
                        this.dataset.estado = nuevoEstado ? '1' : '0';
                        this.title = nuevoEstado ? 'Desactivar' : 'Activar';
                        this.querySelector('i').className = 'fas fa-' + (nuevoEstado ? 'ban' : 'check');
                        const badge = document.getElementById('estado-badge-' + id);
                        if (badge) {
                            badge.className = 'badge badge-' + (nuevoEstado ? 'activo' : 'inactivo');
                            badge.textContent = nuevoEstado ? 'Activo' : 'Inactivo';
                        }
                        const row = this.closest('tr');
                        if (row) row.dataset.estado = nuevoEstado ? '1' : '0';
                        showToast('Usuario ' + (nuevoEstado ? 'activado' : 'desactivado'), 'success');
                    } else {
                        showToast(data.mensaje || 'Error', 'error');
                    }
                } catch(e) {
                    showToast('Error de conexión', 'error');
                }
            }
        );
    });
});

/* ════════════════════════════════════════════════════════
   CAMBIAR ROL DE USUARIO
════════════════════════════════════════════════════════ */
document.querySelectorAll('.cambiar-rol').forEach(btn => {
    btn.addEventListener('click', function() {
        const id      = this.dataset.id;
        const rolAct  = this.dataset.rol;
        const nuevoRol = rolAct === 'admin' ? 'ciudadano' : 'admin';
        openModal('Cambiar rol',
            `¿Cambiar este usuario de <strong>${rolAct}</strong> a <strong>${nuevoRol}</strong>?`,
            'btn-warning',
            async () => {
                const fd = new FormData();
                fd.append('accion', 'cambiar_rol');
                fd.append('usuario_id', id);
                fd.append('rol', nuevoRol);
                try {
                    const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.ok) {
                        this.dataset.rol = nuevoRol;
                        const row  = this.closest('tr');
                        if (row) {
                            row.dataset.rol = nuevoRol;
                            const badge = row.querySelector('.badge-admin, .badge-ciudadano');
                            if (badge) {
                                badge.className = 'badge badge-' + nuevoRol;
                                badge.textContent = nuevoRol.charAt(0).toUpperCase() + nuevoRol.slice(1);
                            }
                        }
                        showToast('Rol actualizado a ' + nuevoRol, 'success');
                    } else {
                        showToast(data.mensaje || 'Error', 'error');
                    }
                } catch(e) {
                    showToast('Error de conexión', 'error');
                }
            }
        );
    });
});
