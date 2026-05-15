const bottomNav = document.getElementById('bottomNav');
const bottomHoverZone = document.getElementById('bottomHoverZone');

let hideTimer = null;

function esMovilOTactil() {
    return window.innerWidth <= 768 || 'ontouchstart' in window;
}

function mostrarMenu() {
    clearTimeout(hideTimer);

    if (bottomNav) {
        bottomNav.classList.add('visible');
    }
}

function ocultarMenu() {
    if (esMovilOTactil()) {
        mostrarMenu();
        return;
    }

    clearTimeout(hideTimer);

    hideTimer = setTimeout(() => {
        if (bottomNav && !bottomNav.matches(':hover')) {
            bottomNav.classList.remove('visible');
        }
    }, 500);
}

if (bottomNav) {
    if (esMovilOTactil()) {
        bottomNav.classList.add('visible');
    } else {
        bottomNav.classList.remove('visible');
    }

    bottomNav.addEventListener('mouseenter', mostrarMenu);
    bottomNav.addEventListener('mouseleave', ocultarMenu);
}

if (bottomHoverZone) {
    bottomHoverZone.addEventListener('mouseenter', mostrarMenu);
    bottomHoverZone.addEventListener('mousemove', mostrarMenu);
    bottomHoverZone.addEventListener('mouseleave', ocultarMenu);
}

document.addEventListener('mousemove', function (e) {
    if (esMovilOTactil()) {
        mostrarMenu();
        return;
    }

    const distanciaAbajo = window.innerHeight - e.clientY;

    if (distanciaAbajo <= 80) {
        mostrarMenu();
    } else {
        ocultarMenu();
    }
});

window.addEventListener('resize', function () {
    if (esMovilOTactil()) {
        mostrarMenu();
    } else if (bottomNav) {
        bottomNav.classList.remove('visible');
    }
});