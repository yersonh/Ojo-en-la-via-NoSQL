document.addEventListener('DOMContentLoaded', function () {
    inicializarNotificacionesPerfil();
    inicializarReportesPerfil();
});

function inicializarNotificacionesPerfil() {
    const notificaciones = document.querySelectorAll('.notificacion-item');

    notificaciones.forEach(function (notificacion) {
        notificacion.addEventListener('click', function () {
            marcarNotificacionComoLeidaVisual(notificacion);
        });
    });
}

function marcarNotificacionComoLeidaVisual(notificacion) {
    if (!notificacion.classList.contains('no-leida')) {
        return;
    }

    notificacion.classList.remove('no-leida');
    actualizarContadorNotificaciones();
}

function actualizarContadorNotificaciones() {
    const totalNoLeidas = document.querySelectorAll('.notificacion-item.no-leida').length;

    const contadorTexto = document.getElementById('contadorNotificaciones');
    const numeroNoLeidas = document.getElementById('numeroNoLeidas');

    if (numeroNoLeidas) {
        numeroNoLeidas.textContent = totalNoLeidas;
    }

    if (contadorTexto) {
        if (totalNoLeidas > 0) {
            contadorTexto.textContent = `${totalNoLeidas} sin leer`;
        } else {
            contadorTexto.remove();
        }
    }
}

function inicializarReportesPerfil() {
    const lista = document.getElementById('misReportesLista');
    const modal = document.getElementById('editarReporteModal');
    const form = document.getElementById('editarReporteForm');
    const cerrar = document.getElementById('cerrarEditarReporte');
    const cancelar = document.getElementById('cancelarEditarReporte');

    if (!lista || !modal || !form) {
        return;
    }

    lista.addEventListener('click', async function (event) {
        const item = event.target.closest('.mi-reporte-item');

        if (!item) {
            return;
        }

        if (event.target.closest('.btn-editar-reporte')) {
            abrirModalEditarReporte(item);
            return;
        }

        if (event.target.closest('.btn-eliminar-reporte')) {
            await eliminarReporte(item);
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        await guardarReporteEditado(form);
    });

    [cerrar, cancelar].forEach(function (boton) {
        if (boton) {
            boton.addEventListener('click', cerrarModalEditarReporte);
        }
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            cerrarModalEditarReporte();
        }
    });
}

function abrirModalEditarReporte(item) {
    const tipoSelect = document.getElementById('editarReporteTipo');
    const tipoActual = item.dataset.tipo || '';

    document.getElementById('editarReporteId').value = item.dataset.reporteId || '';
    tipoSelect.value = tipoActual;

    if (tipoActual && tipoSelect.value !== tipoActual) {
        const opcionActual = document.createElement('option');
        opcionActual.value = tipoActual;
        opcionActual.textContent = tipoActual;
        tipoSelect.appendChild(opcionActual);
        tipoSelect.value = tipoActual;
    }

    document.getElementById('editarReporteDescripcion').value = item.dataset.descripcion || '';
    document.getElementById('editarReporteLatitud').value = item.dataset.latitud || '';
    document.getElementById('editarReporteLongitud').value = item.dataset.longitud || '';

    const modal = document.getElementById('editarReporteModal');
    modal.classList.add('activo');
    modal.setAttribute('aria-hidden', 'false');
}

function cerrarModalEditarReporte() {
    const modal = document.getElementById('editarReporteModal');

    if (!modal) {
        return;
    }

    modal.classList.remove('activo');
    modal.setAttribute('aria-hidden', 'true');
}

async function guardarReporteEditado(form) {
    const botonGuardar = form.querySelector('.btn-guardar-reporte');
    const textoOriginal = botonGuardar ? botonGuardar.textContent : '';

    if (botonGuardar) {
        botonGuardar.disabled = true;
        botonGuardar.textContent = 'Guardando...';
    }

    try {
        const respuesta = await fetch('/views/usuario/editar_reporte.php', {
            method: 'POST',
            body: new FormData(form)
        });

        const data = await respuesta.json();

        if (!data.ok) {
            alert(data.mensaje || 'No se pudo editar el reporte.');
            return;
        }

        actualizarReporteEnPantalla(data.reporte);
        cerrarModalEditarReporte();
    } catch (error) {
        console.error('Error al editar reporte:', error);
        alert('No se pudo editar el reporte.');
    } finally {
        if (botonGuardar) {
            botonGuardar.disabled = false;
            botonGuardar.textContent = textoOriginal;
        }
    }
}

function actualizarReporteEnPantalla(reporte) {
    const item = document.querySelector(`.mi-reporte-item[data-reporte-id="${reporte.id}"]`);

    if (!item) {
        return;
    }

    item.dataset.tipo = reporte.tipo;
    item.dataset.descripcion = reporte.descripcion;
    item.dataset.latitud = reporte.latitud;
    item.dataset.longitud = reporte.longitud;

    const titulo = item.querySelector('.mi-reporte-top strong');
    const descripcion = item.querySelector('.mi-reporte-contenido p');
    const meta = item.querySelector('.mi-reporte-meta');

    if (titulo) {
        titulo.textContent = reporte.tipo;
    }

    if (descripcion) {
        descripcion.textContent = reporte.descripcion || 'Sin descripcion';
    }

    if (meta) {
        const fecha = meta.querySelector('span:first-child')?.textContent || '';
        meta.innerHTML = `
            <span>${escapeHtml(fecha)}</span>
            <span>${escapeHtml(String(reporte.latitud))}, ${escapeHtml(String(reporte.longitud))}</span>
        `;
    }
}

async function eliminarReporte(item) {
    const reporteId = item.dataset.reporteId;

    if (!reporteId) {
        alert('No se encontro el reporte.');
        return;
    }

    if (!confirm('Seguro que quieres eliminar este reporte?')) {
        return;
    }

    const formData = new FormData();
    formData.append('reporte_id', reporteId);

    try {
        const respuesta = await fetch('/views/usuario/eliminar_reporte.php', {
            method: 'POST',
            body: formData
        });

        const data = await respuesta.json();

        if (!data.ok) {
            alert(data.mensaje || 'No se pudo eliminar el reporte.');
            return;
        }

        item.remove();
        actualizarTotalReportesPerfil();

        if (!document.querySelector('.mi-reporte-item')) {
            const lista = document.getElementById('misReportesLista');
            lista.innerHTML = '<div class="notificaciones-vacio">Todavia no has creado reportes.</div>';
        }
    } catch (error) {
        console.error('Error al eliminar reporte:', error);
        alert('No se pudo eliminar el reporte.');
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function actualizarTotalReportesPerfil() {
    const contador = document.getElementById('contadorMisReportes');
    const estadistica = document.getElementById('totalReportesPerfil');
    const totalActual = estadistica ? parseInt(estadistica.textContent, 10) : parseInt(contador?.textContent || '0', 10);
    const total = Number.isFinite(totalActual) && totalActual > 0 ? totalActual - 1 : 0;

    if (contador) {
        contador.textContent = `${total} total`;
    }

    if (estadistica) {
        estadistica.textContent = total;
    }
}
