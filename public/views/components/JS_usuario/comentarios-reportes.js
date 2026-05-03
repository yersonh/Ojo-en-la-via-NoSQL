console.log('comentarios-reportes.js cargado');

const comentariosOverlay = document.getElementById('comentariosOverlay');
const cerrarComentarios = document.getElementById('cerrarComentarios');
const comentariosLista = document.getElementById('comentariosLista');
const comentariosTotal = document.getElementById('comentariosTotal');
const comentarioForm = document.getElementById('comentarioForm');
const comentarioReporteId = document.getElementById('comentarioReporteId');
const comentarioTexto = document.getElementById('comentarioTexto');

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
    comentarioReporteId.value = reporteId;
    comentarioTexto.value = '';

    comentariosOverlay.classList.add('activo');

    await cargarComentarios(reporteId);
});

cerrarComentarios.addEventListener('click', () => {
    comentariosOverlay.classList.remove('activo');
});

comentariosOverlay.addEventListener('click', (e) => {
    if (e.target === comentariosOverlay) {
        comentariosOverlay.classList.remove('activo');
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

            return `
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

    const usuario = btn.dataset.usuario || '';

    comentarioTexto.focus();

    if (usuario) {
        comentarioTexto.value = `@${usuario} `;
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