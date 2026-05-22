document.addEventListener('DOMContentLoaded', function () {
    inicializarNotificacionesPerfil();
    inicializarPreviewFotoPerfil();
});

function inicializarNotificacionesPerfil() {
    const notificaciones = document.querySelectorAll('.notificacion-item');
    const marcarTodas = document.getElementById('marcarTodasPerfil');

    notificaciones.forEach(function (notificacion) {
        notificacion.addEventListener('click', async function () {
            await marcarNotificacionComoLeida(notificacion);

            if (notificacion.dataset.url) {
                window.location.href = notificacion.dataset.url;
            }
        });
    });

    if (marcarTodas) {
        marcarTodas.addEventListener('click', async function (event) {
            event.stopPropagation();
            await marcarTodasNotificacionesComoLeidas();
        });
    }
}

async function marcarNotificacionComoLeida(notificacion) {
    if (!notificacion.classList.contains('no-leida')) {
        return;
    }

    const notificacionId = notificacion.dataset.notificacionId;

    if (notificacionId) {
        const formData = new FormData();
        formData.append('accion', 'marcar_leida');
        formData.append('notificacion_id', notificacionId);

        try {
            const respuesta = await fetch('/views/usuario/notificaciones_perfil.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await respuesta.json();

            if (!data.ok) {
                console.warn(data.mensaje || 'No se pudo marcar la notificacion como leida.');
                return;
            }
        } catch (error) {
            console.error('Error marcando notificacion:', error);
            return;
        }
    }

    notificacion.classList.remove('no-leida');
    actualizarContadorNotificaciones();
}

async function marcarTodasNotificacionesComoLeidas() {
    const formData = new FormData();
    formData.append('accion', 'marcar_todas');

    try {
        const respuesta = await fetch('/views/usuario/notificaciones_perfil.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await respuesta.json();

        if (!data.ok) {
            alert(data.mensaje || 'No se pudieron marcar las notificaciones.');
            return;
        }

        document.querySelectorAll('.notificacion-item.no-leida').forEach(function (notificacion) {
            notificacion.classList.remove('no-leida');
        });

        actualizarContadorNotificaciones();
    } catch (error) {
        console.error('Error marcando todas las notificaciones:', error);
        alert('Error al conectar con el servidor.');
    }
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

    const marcarTodas = document.getElementById('marcarTodasPerfil');

    if (marcarTodas && totalNoLeidas === 0) {
        marcarTodas.remove();
    }
}

function inicializarPreviewFotoPerfil() {
    const inputFoto = document.getElementById('foto_perfil');
    const avatar = document.querySelector('.editar-foto-preview .perfil-avatar');

    if (!inputFoto || !avatar) {
        return;
    }

    inputFoto.addEventListener('change', function () {
        const archivo = inputFoto.files && inputFoto.files[0];

        if (!archivo) {
            return;
        }

        if (!archivo.type.startsWith('image/')) {
            alert('Selecciona una imagen valida.');
            inputFoto.value = '';
            return;
        }

        const urlPreview = URL.createObjectURL(archivo);
        avatar.innerHTML = `<img src="${urlPreview}" alt="Foto de perfil">`;
    });
}
