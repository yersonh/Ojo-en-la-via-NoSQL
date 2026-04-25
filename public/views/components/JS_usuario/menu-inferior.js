const bottomNav = document.getElementById('bottomNav');
const bottomHoverZone = document.getElementById('bottomHoverZone');
const mapElement = document.getElementById('map');

let hideTimer = null;

function mostrarMenu() {
    clearTimeout(hideTimer);

    if (bottomNav) {
        bottomNav.classList.add('visible');
    }
}

function ocultarMenu() {
    clearTimeout(hideTimer);

    hideTimer = setTimeout(() => {
        if (bottomNav) {
            bottomNav.classList.remove('visible');
        }
    }, 700);
}

if (bottomNav) {
    // Si NO hay mapa, por ejemplo en perfil.php o alertas.php,
    // dejamos el menú visible siempre.
    if (!mapElement) {
        bottomNav.classList.add('visible');
    }

    // Si SÍ hay mapa, usamos el efecto de mostrar/ocultar.
    if (bottomHoverZone && mapElement) {
        bottomHoverZone.addEventListener('mouseenter', mostrarMenu);
        bottomNav.addEventListener('mouseenter', mostrarMenu);

        bottomHoverZone.addEventListener('mouseleave', ocultarMenu);
        bottomNav.addEventListener('mouseleave', ocultarMenu);

        mapElement.addEventListener('mousemove', function (e) {
            const altoVentana = window.innerHeight;
            const distanciaAbajo = altoVentana - e.clientY;

            if (distanciaAbajo <= 50) {
                mostrarMenu();
            } else if (!bottomNav.matches(':hover')) {
                ocultarMenu();
            }
        });

        mapElement.addEventListener('mouseleave', ocultarMenu);
    }
}