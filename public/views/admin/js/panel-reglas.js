/* ════════════════════════════════════════════════════════
   REGLAS DE NOTIFICACIÓN AUTOMÁTICA
   Depende de: showToast (panel-ui.js)
════════════════════════════════════════════════════════ */

function reglaHTML(reg) {
    const bg          = reg.activa ? '#fff' : '#f8f9fa';
    const prioBadge   = `<span class="badge badge-${reg.prioridad}" style="margin-left:6px;">${reg.prioridad.charAt(0).toUpperCase()+reg.prioridad.slice(1)}</span>`;
    const inactivaBadge = reg.activa ? '' : `<span class="badge" style="background:#95a5a6;color:#fff;margin-left:4px;">Inactiva</span>`;
    const toggleColor = reg.activa ? '#f39c12' : '#27ae60';
    const toggleIcon  = reg.activa ? 'pause' : 'play';
    const toggleTitle = reg.activa ? 'Desactivar' : 'Activar';

    const div = document.createElement('div');
    div.className = 'regla-item';
    div.id = 'regla-' + reg.id;
    div.style.cssText = `border:1px solid #e8ecef;border-radius:8px;padding:12px 14px;margin-bottom:10px;background:${bg};`;
    div.dataset.reg = JSON.stringify(reg);
    div.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div style="flex:1;">
                <div style="font-weight:600;font-size:.9rem;margin-bottom:2px;">
                    ${reg.tipo_incidente}${prioBadge}${inactivaBadge}
                </div>
                <div style="font-size:.82rem;color:#7f8c8d;">→ ${reg.entidad}</div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0;">
                <button class="btn btn-secondary btn-sm btn-editar-regla" title="Editar"><i class="fas fa-pen"></i></button>
                <button class="btn btn-sm btn-toggle-regla" style="background:${toggleColor};color:#fff;" title="${toggleTitle}"><i class="fas fa-${toggleIcon}"></i></button>
                <button class="btn btn-sm btn-eliminar-regla" style="background:#e74c3c;color:#fff;" title="Eliminar"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;

    div.querySelector('.btn-editar-regla').addEventListener('click', () => editarRegla(reg));
    div.querySelector('.btn-toggle-regla').addEventListener('click', () => toggleRegla(reg.id, !reg.activa));
    div.querySelector('.btn-eliminar-regla').addEventListener('click', () => eliminarRegla(reg.id));

    return div;
}

function abrirModalRegla() {
    document.getElementById('modal-regla-titulo').textContent = 'Nueva regla';
    document.getElementById('regla-edit-id').value   = '';
    document.getElementById('regla-tipo').value      = '';
    document.getElementById('regla-tipo').disabled   = false;
    document.getElementById('regla-entidad').value   = '';
    document.getElementById('regla-prioridad').value = 'media';
    document.getElementById('regla-asunto').value    = '';
    document.getElementById('regla-mensaje').value   = '';
    document.getElementById('modal-regla').style.display = 'flex';
}

function editarRegla(reg) {
    document.getElementById('modal-regla-titulo').textContent = 'Editar regla';
    document.getElementById('regla-edit-id').value   = reg.id;
    document.getElementById('regla-tipo').value      = reg.tipo_incidente;
    document.getElementById('regla-tipo').disabled   = true;
    document.getElementById('regla-entidad').value   = reg.entidad;
    document.getElementById('regla-prioridad').value = reg.prioridad;
    document.getElementById('regla-asunto').value    = reg.asunto;
    document.getElementById('regla-mensaje').value   = reg.mensaje;
    document.getElementById('modal-regla').style.display = 'flex';
}

function cerrarModalRegla() {
    document.getElementById('modal-regla').style.display = 'none';
}

async function guardarRegla() {
    const id      = document.getElementById('regla-edit-id').value;
    const tipo    = document.getElementById('regla-tipo').value.trim();
    const entidad = document.getElementById('regla-entidad').value.trim();
    const asunto  = document.getElementById('regla-asunto').value.trim();
    const mensaje = document.getElementById('regla-mensaje').value.trim();
    const prio    = document.getElementById('regla-prioridad').value;

    if (!tipo || !entidad || !asunto || !mensaje) {
        showToast('Completa todos los campos', 'warning');
        return;
    }

    const fd = new FormData();
    fd.append('prioridad', prio);
    fd.append('entidad',   entidad);
    fd.append('asunto',    asunto);
    fd.append('mensaje',   mensaje);

    if (id) {
        fd.append('accion',   'actualizar_regla');
        fd.append('regla_id', id);
    } else {
        fd.append('accion',         'crear_regla');
        fd.append('tipo_incidente', tipo);
    }

    try {
        const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) { showToast(data.mensaje || 'Error al guardar', 'error'); return; }

        const reg = { id: id || data.id, tipo_incidente: tipo, entidad, asunto, mensaje, prioridad: prio, activa: true };

        const nuevoEl = reglaHTML(reg);
        if (id) {
            const existing = document.getElementById('regla-' + id);
            if (existing) existing.replaceWith(nuevoEl);
        } else {
            const lista = document.getElementById('reglas-lista');
            const empty = lista.querySelector('#reglas-empty');
            if (empty) empty.remove();
            lista.prepend(nuevoEl);
        }

        showToast(id ? 'Regla actualizada' : 'Regla creada', 'success');
        cerrarModalRegla();
    } catch {
        showToast('Error de conexión', 'error');
    }
}

async function toggleRegla(id, activar) {
    const fd = new FormData();
    fd.append('accion',   'toggle_regla');
    fd.append('regla_id', id);
    fd.append('activa',   activar ? '1' : '0');

    try {
        const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) { showToast(data.mensaje || 'Error', 'error'); return; }

        const item = document.getElementById('regla-' + id);
        if (item) {
            const reg = JSON.parse(item.dataset.reg);
            reg.activa = activar;
            item.replaceWith(reglaHTML(reg));
        }
        showToast(activar ? 'Regla activada' : 'Regla desactivada', 'success');
    } catch {
        showToast('Error de conexión', 'error');
    }
}

async function eliminarRegla(id) {
    if (!confirm('¿Eliminar esta regla? Las notificaciones ya enviadas no se borrarán.')) return;

    const fd = new FormData();
    fd.append('accion',   'eliminar_regla');
    fd.append('regla_id', id);

    try {
        const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('regla-' + id)?.remove();
            showToast('Regla eliminada', 'success');
        } else {
            showToast(data.mensaje || 'Error', 'error');
        }
    } catch {
        showToast('Error de conexión', 'error');
    }
}

document.getElementById('modal-regla')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalRegla();
});

// Conectar botones de los cards renderizados por PHP
document.querySelectorAll('.regla-item').forEach(item => {
    const reg = JSON.parse(item.dataset.reg);
    item.querySelector('.btn-editar-regla')?.addEventListener('click', () => editarRegla(reg));
    item.querySelector('.btn-toggle-regla')?.addEventListener('click', () => toggleRegla(reg.id, !reg.activa));
    item.querySelector('.btn-eliminar-regla')?.addEventListener('click', () => eliminarRegla(reg.id));
});
