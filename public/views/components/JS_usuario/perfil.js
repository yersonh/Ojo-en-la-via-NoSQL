document.addEventListener('DOMContentLoaded', function () {
    inicializarNotificacionesPerfil();
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