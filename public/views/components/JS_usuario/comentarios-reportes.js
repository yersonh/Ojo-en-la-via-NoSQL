console.log('comentarios-reportes.js cargado');

const comentariosOverlay = document.getElementById('comentariosOverlay');
const cerrarComentarios = document.getElementById('cerrarComentarios');
const comentariosLista = document.getElementById('comentariosLista');
const comentariosTotal = document.getElementById('comentariosTotal');
const comentarioForm = document.getElementById('comentarioForm');
const comentarioReporteId = document.getElementById('comentarioReporteId');
const comentarioTexto = document.getElementById('comentarioTexto');

let comentarioPadreActivo = null;
let botonComentariosActivo = null;

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-abrir-comentarios');

    if (!btn) {
        return;
    }

    const reporteId = btn.dataset.reporteId;

    if (!reporteId) {
        alert('No se encontró el ID del reporte.');
        return;
    }

    botonComentariosActivo = btn;
    comentarioPadreActivo = null;
    comentarioReporteId.value = reporteId;
    comentarioTexto.value = '';
    comentarioTexto.placeholder = 'Escribe un comentario...';

    comentariosOverlay.classList.add('activo');

    await cargarComentarios(reporteId);
});

cerrarComentarios.addEventListener('click', () => {
    comentariosOverlay.classList.remove('activo');
    comentarioPadreActivo = null;
    comentarioTexto.value = '';
    comentarioTexto.placeholder = 'Escribe un comentario...';
});

comentariosOverlay.addEventListener('click', (e) => {
    if (e.target === comentariosOverlay) {
        comentariosOverlay.classList.remove('activo');
        comentarioPadreActivo = null;
        comentarioTexto.value = '';
        comentarioTexto.placeholder = 'Escribe un comentario...';
    }
});

comentarioForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const reporteId = comentarioReporteId.value;
    const texto = comentarioTexto.value.trim();

    if (!reporteId) {
        alert('No se encontró el reporte.');
        return;
    }

    if (!texto) {
        return;
    }

    const formData = new FormData();
    formData.append('reporte_id', reporteId);
    formData.append('comentario', texto);

    if (comentarioPadreActivo) {
        formData.append('comentario_padre_id', comentarioPadreActivo);
    }

    try {
        const respuesta = await fetch('/views/usuario/guardar_comentario.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const textoRespuesta = await respuesta.text();
        console.log('Respuesta guardar comentario:', textoRespuesta);

        const data = JSON.parse(textoRespuesta);

        if (!data.ok) {
            alert(data.mensaje || 'No se pudo guardar el comentario.');
            return;
        }

        comentarioTexto.value = '';
        comentarioPadreActivo = null;
        comentarioTexto.placeholder = 'Escribe un comentario...';

        if (botonComentariosActivo) {
            const contador = botonComentariosActivo.querySelector('.comment-count');

            if (contador) {
                contador.textContent = data.total_comentarios;
            }
        }

        await cargarComentarios(reporteId);

    } catch (error) {
        console.error('Error al guardar comentario:', error);
        alert('Error al conectar con el servidor.');
    }
});

async function cargarComentarios(reporteId) {
    comentariosLista.innerHTML = '<p class="comentarios-vacio">Cargando comentarios...</p>';

    try {
        const respuesta = await fetch(`/views/usuario/listar_comentario.php?reporte_id=${encodeURIComponent(reporteId)}`, {
            method: 'GET',
            credentials: 'same-origin'
        });

        const textoRespuesta = await respuesta.text();
        console.log('Respuesta listar comentarios:', textoRespuesta);

        const data = JSON.parse(textoRespuesta);

        if (!data.ok) {
            comentariosLista.innerHTML = `<p class="comentarios-vacio">${escapeHTML(data.mensaje || 'No se pudieron cargar los comentarios.')}</p>`;
            return;
        }

        comentariosTotal.textContent = data.total;

        if (!data.comentarios || data.comentarios.length === 0) {
            comentariosLista.innerHTML = '<p class="comentarios-vacio">Sé el primero en comentar.</p>';
            return;
        }

        comentariosLista.innerHTML = data.comentarios
            .map((comentario) => renderComentario(comentario, 0))
            .join('');

    } catch (error) {
        console.error('Error al cargar comentarios:', error);
        comentariosLista.innerHTML = '<p class="comentarios-vacio">Error al cargar comentarios.</p>';
    }
}

function renderComentario(comentario, nivel = 0) {
    const avatar = comentario.foto_perfil
        ? `<img src="${escapeHTML(comentario.foto_perfil)}" alt="Foto de perfil">`
        : `<span>${escapeHTML(comentario.inicial)}</span>`;

    const totalRespuestas = comentario.respuestas ? comentario.respuestas.length : 0;

    const respuestasHTML = totalRespuestas > 0
        ? `
            <button 
                type="button"
                class="btn-ver-respuestas"
                data-comentario-id="${escapeHTML(comentario.id)}"
                data-total-respuestas="${totalRespuestas}"
            >
                <span class="linea-respuestas"></span>
                Ver ${totalRespuestas} ${totalRespuestas === 1 ? 'respuesta' : 'respuestas'}
            </button>

            <div 
                class="comentario-respuestas ocultar-respuestas"
                id="respuestas-${escapeHTML(comentario.id)}"
            >
                ${comentario.respuestas.map((respuesta) => renderComentario(respuesta, nivel + 1)).join('')}
            </div>
        `
        : '';

    return `
        <div class="comentario-bloque ${nivel > 0 ? 'comentario-bloque-respuesta' : ''}">
            <div class="comentario-item">
                <div class="comentario-avatar ${nivel > 0 ? 'comentario-avatar-respuesta' : ''}">
                    ${avatar}
                </div>

                <div class="comentario-contenido">
                    <div class="comentario-autor">
                        ${escapeHTML(comentario.usuario)}
                    </div>

                    <div class="comentario-burbuja ${nivel > 0 ? 'comentario-burbuja-respuesta' : ''}">
                       <p class="comentario-texto" data-comentario-id="${escapeHTML(comentario.id)}">${escapeHTML(String(comentario.comentario ?? '').trim())}</p>
                    </div>
                         <div class="comentario-meta">
                        <span>${escapeHTML(comentario.fecha)}</span>

                        <button 
                            type="button" 
                            class="btn-responder-comentario"
                            data-comentario-id="${escapeHTML(comentario.id)}"
                            data-usuario="${escapeHTML(comentario.usuario)}"
                        >
                            Responder
                        </button>

                        <button 
                        type="button" 
                        class="btn-like-comentario"
                        data-comentario-id="${escapeHTML(comentario.id)}"
                    >
                        ♡ <span>${comentario.likes ?? 0}</span>
                    </button>

                    ${comentario.es_mio ? `
                        <button 
                            type="button" 
                            class="btn-editar-comentario"
                            data-comentario-id="${escapeHTML(comentario.id)}"
                            data-comentario-texto="${escapeHTML(comentario.comentario)}"
                        >
                            Editar
                        </button>

                        <button 
                            type="button" 
                            class="btn-eliminar-comentario"
                            data-comentario-id="${escapeHTML(comentario.id)}"
                        >
                            Eliminar
                        </button>
                    ` : ''}
                    </div>

                    ${respuestasHTML}
                </div>
            </div>
        </div>
    `;
}

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like-comentario');

    if (!btn) {
        return;
    }

    const comentarioId = btn.dataset.comentarioId;

    if (!comentarioId) {
        alert('No se encontró el comentario.');
        return;
    }

    const formData = new FormData();
    formData.append('comentario_id', comentarioId);

    try {
        const respuesta = await fetch('/views/usuario/like_comentario.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const textoRespuesta = await respuesta.text();
        console.log('Respuesta like comentario:', textoRespuesta);

        const data = JSON.parse(textoRespuesta);

        if (!data.ok) {
            alert(data.mensaje || 'No se pudo actualizar el like.');
            return;
        }

        btn.innerHTML = `${data.liked ? '♥' : '♡'} <span>${data.total_likes}</span>`;

    } catch (error) {
        console.error('Error al dar like al comentario:', error);
        alert('Error al conectar con el servidor.');
    }
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-responder-comentario');

    if (!btn) {
        return;
    }

    const comentarioId = btn.dataset.comentarioId || '';
    const usuario = btn.dataset.usuario || '';

    comentarioPadreActivo = comentarioId;

    comentarioTexto.focus();

    if (usuario) {
        comentarioTexto.value = `@${usuario} `;
        comentarioTexto.placeholder = `Respondiendo a ${usuario}`;
    }
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-ver-respuestas');

    if (!btn) {
        return;
    }

    const comentarioId = btn.dataset.comentarioId;
    const totalRespuestas = Number(btn.dataset.totalRespuestas || 0);
    const contenedor = document.getElementById(`respuestas-${comentarioId}`);

    if (!contenedor) {
        return;
    }

    const estaOculto = contenedor.classList.contains('ocultar-respuestas');

    if (estaOculto) {
        contenedor.classList.remove('ocultar-respuestas');

        btn.innerHTML = `
            <span class="linea-respuestas"></span>
            Ocultar ${totalRespuestas === 1 ? 'respuesta' : 'respuestas'}
        `;
    } else {
        contenedor.classList.add('ocultar-respuestas');

        btn.innerHTML = `
            <span class="linea-respuestas"></span>
            Ver ${totalRespuestas} ${totalRespuestas === 1 ? 'respuesta' : 'respuestas'}
        `;
    }
});

document.addEventListener('DOMContentLoaded', async () => {
    const parametros = new URLSearchParams(window.location.search);
    const reporteIdDesdeUrl = parametros.get('comentarios');

    if (!reporteIdDesdeUrl || !/^[a-f\d]{24}$/i.test(reporteIdDesdeUrl)) {
        return;
    }

    const boton = document.querySelector(`.btn-abrir-comentarios[data-reporte-id="${reporteIdDesdeUrl}"]`);

    if (boton) {
        setTimeout(() => {
            boton.click();
        }, 400);

        return;
    }

    if (comentariosOverlay && comentarioReporteId && comentarioTexto) {
        botonComentariosActivo = null;
        comentarioPadreActivo = null;

        comentarioReporteId.value = reporteIdDesdeUrl;
        comentarioTexto.value = '';
        comentarioTexto.placeholder = 'Escribe un comentario...';

        comentariosOverlay.classList.add('activo');

        await cargarComentarios(reporteIdDesdeUrl);
    }
});
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-editar-comentario');

    if (!btn) {
        return;
    }

    const comentarioId = btn.dataset.comentarioId;
    const textoActual = btn.dataset.comentarioTexto || '';

    const nuevoTexto = prompt('Editar comentario:', textoActual);

    if (nuevoTexto === null) {
        return;
    }

    const textoLimpio = nuevoTexto.trim();

    if (!textoLimpio) {
        alert('El comentario no puede estar vacío.');
        return;
    }

    if (textoLimpio.length > 500) {
        alert('El comentario no puede superar 500 caracteres.');
        return;
    }

    const formData = new FormData();
    formData.append('comentario_id', comentarioId);
    formData.append('comentario', textoLimpio);

    try {
        const respuesta = await fetch('/views/usuario/editar_comentario.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const textoRespuesta = await respuesta.text();
        console.log('Respuesta editar comentario:', textoRespuesta);

        const data = JSON.parse(textoRespuesta);

        if (!data.ok) {
            alert(data.mensaje || 'No se pudo editar el comentario.');
            return;
        }

        await cargarComentarios(comentarioReporteId.value);

    } catch (error) {
        console.error('Error al editar comentario:', error);
        alert('Error al conectar con el servidor.');
    }
});
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-eliminar-comentario');

    if (!btn) {
        return;
    }

    const comentarioId = btn.dataset.comentarioId;

    const confirmar = confirm('¿Seguro que quieres eliminar este comentario?');

    if (!confirmar) {
        return;
    }

    const formData = new FormData();
    formData.append('comentario_id', comentarioId);

    try {
        const respuesta = await fetch('/views/usuario/eliminar_comentario.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const textoRespuesta = await respuesta.text();
        console.log('Respuesta eliminar comentario:', textoRespuesta);

        const data = JSON.parse(textoRespuesta);

        if (!data.ok) {
            alert(data.mensaje || 'No se pudo eliminar el comentario.');
            return;
        }

        await cargarComentarios(comentarioReporteId.value);

    } catch (error) {
        console.error('Error al eliminar comentario:', error);
        alert('Error al conectar con el servidor.');
    }
});
function escapeHTML(texto) {
    return String(texto ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}