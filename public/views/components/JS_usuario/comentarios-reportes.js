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

        comentariosLista.innerHTML = data.comentarios.map((comentario) => {
            const avatar = comentario.foto_perfil
                ? `<img src="${escapeHTML(comentario.foto_perfil)}" alt="Foto de perfil">`
                : `<span>${escapeHTML(comentario.inicial)}</span>`;

            const respuestasHTML = comentario.respuestas && comentario.respuestas.length > 0
                ? `
                    <div class="comentario-respuestas">
                        ${comentario.respuestas.map((respuesta) => {
                            const avatarRespuesta = respuesta.foto_perfil
                                ? `<img src="${escapeHTML(respuesta.foto_perfil)}" alt="Foto de perfil">`
                                : `<span>${escapeHTML(respuesta.inicial)}</span>`;

                            return `
                                <div class="comentario-item comentario-respuesta">
                                    <div class="comentario-avatar comentario-avatar-respuesta">
                                        ${avatarRespuesta}
                                    </div>

                                    <div class="comentario-contenido">
                                        <div class="comentario-burbuja comentario-burbuja-respuesta">
                                            <strong>${escapeHTML(respuesta.usuario)}</strong>
                                            <p>${escapeHTML(respuesta.comentario)}</p>
                                        </div>

                                        <div class="comentario-meta">
                                            <span>${escapeHTML(respuesta.fecha)}</span>

                                            <button 
                                                type="button" 
                                                class="btn-responder-comentario"
                                                data-comentario-id="${escapeHTML(comentario.id)}"
                                                data-usuario="${escapeHTML(respuesta.usuario)}"
                                            >
                                                Responder
                                            </button>

                                            <button 
                                                type="button" 
                                                class="btn-like-comentario"
                                                data-comentario-id="${escapeHTML(respuesta.id)}"
                                            >
                                                ♡ <span>${respuesta.likes ?? 0}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `
                : '';

            return `
                <div class="comentario-bloque">
                    <div class="comentario-item">
                        <div class="comentario-avatar">
                            ${avatar}
                        </div>

                        <div class="comentario-contenido">
                            <div class="comentario-burbuja">
                                <strong>${escapeHTML(comentario.usuario)}</strong>
                                <p>${escapeHTML(comentario.comentario)}</p>
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
                            </div>
                        </div>
                    </div>

                    ${respuestasHTML}
                </div>
            `;
        }).join('');

    } catch (error) {
        console.error('Error al cargar comentarios:', error);
        comentariosLista.innerHTML = '<p class="comentarios-vacio">Error al cargar comentarios.</p>';
    }
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
function escapeHTML(texto) {
    return String(texto ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}