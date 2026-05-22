document.addEventListener('DOMContentLoaded', function () {
    inicializarNotificacionesPerfil();
    inicializarPreviewFotoPerfil();
});

function inicializarNotificacionesPerfil() {
    const notificaciones = document.querySelectorAll('.notificacion-item');

    notificaciones.forEach(function (notificacion) {
        notificacion.addEventListener('click', function () {
            marcarNotificacionComoLeidaVisual(notificacion);

            if (notificacion.dataset.url) {
                window.location.href = notificacion.dataset.url;
            }
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
