const botonesPanel = document.querySelectorAll('.bottom-nav a');
const panelAlertas = document.getElementById('panelAlertas');
const panelPerfil = document.getElementById('panelPerfil');
const listaAlertas = document.getElementById('listaAlertas');

function ocultarPaneles() {
    panelAlertas?.classList.remove('panel-activo');
    panelPerfil?.classList.remove('panel-activo');
}

function activarBoton(botonActivo) {
    botonesPanel.forEach(boton => boton.classList.remove('active'));
    botonActivo.classList.add('active');
}

function cargarAlertas() {
    if (!listaAlertas) return;

    const reportes = window.reportesDB || [];

    if (reportes.length === 0) {
        listaAlertas.innerHTML = '<p class="texto-vacio">No hay alertas registradas todavía.</p>';
        return;
    }

    listaAlertas.innerHTML = reportes.map(reporte => `
        <div class="alerta-item">
            <strong>${reporte.tipo || 'Incidente'}</strong>
            <p>${reporte.descripcion || 'Sin descripción'}</p>
            <small>${reporte.fecha || 'Fecha no disponible'}</small>
        </div>
    `).join('');
}

botonesPanel.forEach(boton => {
    boton.addEventListener('click', (e) => {
        e.preventDefault();

        const panel = boton.dataset.panel;

        activarBoton(boton);
        ocultarPaneles();

        if (panel === 'alertas') {
            panelAlertas?.classList.add('panel-activo');
            cargarAlertas();
        }

        if (panel === 'mapa') {
            ocultarPaneles();

            setTimeout(() => {
                if (typeof map !== 'undefined') {
                    map.invalidateSize();
                }
            }, 200);
        }

        if (panel === 'perfil') {
            panelPerfil?.classList.add('panel-activo');
        }
    });
});

cargarAlertas();