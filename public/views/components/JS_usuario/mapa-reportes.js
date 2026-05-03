const map = crearMapa('map');
let marcador = null;

function crearIconoReporte(emoji = '🚨') {
    return L.divIcon({
        className: 'icono-reporte-personalizado',
        html: `
            <div class="marker-circle">
                <span class="marker-emoji">${emoji}</span>
            </div>
        `,
        iconSize: [42, 42],
        iconAnchor: [21, 21],
        popupAnchor: [0, -18]
    });
}

function obtenerEmojiPorTipo(tipo) {
    switch ((tipo || '').toLowerCase()) {
        case 'accidente':
            return '🚨';
        case 'hueco':
            return '🕳️';
        case 'tráfico':
            return '🚗';
        case 'obstrucción':
            return '🚧';
        default:
            return '📍';
    }
}

map.on('click', function (e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;

    if (marcador) {
        map.removeLayer(marcador);
    }

    marcador = L.marker([lat, lng], {
        icon: crearIconoReporte('📍')
    }).addTo(map);

    const panelRegistro = document.getElementById('panelRegistro');
    const infoUbicacion = document.getElementById('infoUbicacion');
    const latSpan = document.getElementById('latitud');
    const lngSpan = document.getElementById('longitud');
    const latInput = document.getElementById('latitudInput');
    const lngInput = document.getElementById('longitudInput');

    if (panelRegistro) {
        panelRegistro.classList.remove('oculto');
    }

    if (infoUbicacion) {
        infoUbicacion.classList.remove('oculto');
    }

    if (latSpan) latSpan.textContent = lat.toFixed(6);
    if (lngSpan) lngSpan.textContent = lng.toFixed(6);
    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;
});

function escaparHtml(texto) {
    if (texto === null || texto === undefined) return '';
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function capitalizarEstado(estado) {
    if (!estado) return 'Activo';
    return estado.charAt(0).toUpperCase() + estado.slice(1);
}
function crearPopupReporte(reporte) {
    const reporteId = escaparHtml(reporte.id || reporte._id || '');
    const tipo = escaparHtml(reporte.tipo || 'Incidente');
    const descripcion = escaparHtml(reporte.descripcion || 'Sin descripción');
    const usuarioEmail = escaparHtml(reporte.usuario_email || 'No disponible');
    const fecha = escaparHtml(reporte.fecha || 'No disponible');
    const estado = escaparHtml(capitalizarEstado(reporte.estado || 'activo'));
    const imagen = reporte.imagen ? `/${reporte.imagen}` : '';
    const cantidadImagenes = reporte.imagen ? '1 imagen' : '0 imágenes';

    return `
        <div class="popup-reporte">
            <div class="popup-header">
                <span class="popup-icon">🚨</span>
                <span class="popup-title">${tipo}</span>
            </div>

            <div class="popup-body">
                <div class="popup-section">
                    <div class="popup-section-title">
                        <span>📷 Imágenes del Reporte</span>
                        <span class="popup-badge">${cantidadImagenes}</span>
                    </div>

                    <div class="popup-image-box">
                        ${
                            imagen
                                ? `<img src="${imagen}" alt="Imagen del reporte" class="popup-image">`
                                : `<div class="popup-image-empty">Sin imagen</div>`
                        }
                    </div>
                </div>

                <div class="popup-description">${descripcion}</div>

                <div class="popup-info-card">
                    <div class="popup-info-label">👤 Reportado por:</div>
                    <div class="popup-info-value">${usuarioEmail}</div>
                </div>

                <div class="popup-info-card">
                    <div class="popup-info-label">📅 Fecha:</div>
                    <div class="popup-info-value">${fecha}</div>
                </div>

                <div class="popup-info-card">
                    <div class="popup-info-label">📌 Estado:</div>
                    <div class="popup-status">${estado}</div>
                </div>

                <a 
                    href="/views/usuario/alertas.php?comentarios=${reporteId}"
                    class="popup-button"
                >
                    💬 Ver Comentarios
                </a>
            </div>
        </div>
    `;
}

if (Array.isArray(window.reportesDB)) {
    window.reportesDB.forEach(reporte => {
        if (
            typeof reporte.latitud !== 'number' ||
            typeof reporte.longitud !== 'number'
        ) {
            return;
        }

        const emoji = obtenerEmojiPorTipo(reporte.tipo);

        L.marker([reporte.latitud, reporte.longitud], {
            icon: crearIconoReporte(emoji)
        })
            .addTo(map)
            .bindPopup(crearPopupReporte(reporte), {
                maxWidth: 320,
                className: 'popup-reporte-wrapper'
            });
    });
}