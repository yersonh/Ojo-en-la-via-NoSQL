const map = crearMapa('map');
let marcador = null;
const marcadoresReportes = new Map();

/* ========= ESTADOS Y COLORES ========= */

function normalizarEstado(estado) {
    return String(estado || 'pendiente')
        .toLowerCase()
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '_');
}

function obtenerColorPorEstado(estado) {
    const estadoNormalizado = normalizarEstado(estado);

    if (estadoNormalizado === 'pendiente') {
        return '#e53935'; // rojo
    }

    if (
        estadoNormalizado === 'en_revision' ||
        estadoNormalizado === 'revisado' ||
        estadoNormalizado === 'revision'
    ) {
        return '#f39c12'; // naranja
    }

    if (
        estadoNormalizado === 'notificado' ||
        estadoNormalizado === 'informado'
    ) {
        return '#f1c40f'; // amarillo
    }

    if (
        estadoNormalizado === 'resuelto' ||
        estadoNormalizado === 'solucionado'
    ) {
        return '#2ecc71'; // verde
    }

    return '#e53935'; // rojo por defecto
}

/* ========= ICONOS DEL MAPA ========= */

function crearIconoReporte(emoji = '🚨', estado = 'pendiente') {
    const color = obtenerColorPorEstado(estado);

    return L.divIcon({
        className: 'icono-reporte-personalizado',
        html: `
            <div class="marker-circle" style="background:${color};">
                <span class="marker-emoji">${emoji}</span>
            </div>
        `,
        iconSize: [42, 42],
        iconAnchor: [21, 21],
        popupAnchor: [0, -18]
    });
}

function obtenerEmojiPorTipo(tipo) {
    const tipoNormalizado = String(tipo || '').toLowerCase().trim();

    switch (tipoNormalizado) {
        case 'accidente':
            return '🚨';

        case 'hueco':
            return '🕳️';

        case 'tráfico':
        case 'trafico':
            return '🚗';

        case 'obstrucción':
        case 'obstruccion':
            return '🚧';

        case 'inundación':
        case 'inundacion':
            return '🌊';

        default:
            return '📍';
    }
}

/* ========= CREAR REPORTE AL HACER CLICK ========= */

map.on('click', function (e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;

    if (marcador) {
        map.removeLayer(marcador);
    }

    marcador = L.marker([lat, lng], {
        icon: crearIconoReporte('📍', 'pendiente')
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

/* ========= UTILIDADES ========= */

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
    const estadoTexto = String(estado || 'pendiente').replace(/_/g, ' ');
    return estadoTexto.charAt(0).toUpperCase() + estadoTexto.slice(1);
}

/* ========= POPUP DEL REPORTE ========= */

function obtenerImagenReporte(reporte) {
    if (reporte.imagen) {
        return reporte.imagen;
    }

    if (Array.isArray(reporte.imagenes) && reporte.imagenes.length > 0) {
        return reporte.imagenes[0];
    }

    return '';
}

function crearPopupReporte(reporte) {
    const reporteId = escaparHtml(reporte.id || reporte._id || '');
    const tipo = escaparHtml(reporte.tipo || 'Incidente');
    const descripcion = escaparHtml(reporte.descripcion || 'Sin descripción');
    const usuarioNombre = escaparHtml(reporte.usuario_nombre || 'No disponible');
    const fecha = escaparHtml(reporte.fecha || 'No disponible');
    const estadoOriginal = reporte.estado || 'pendiente';
    const estado = escaparHtml(capitalizarEstado(estadoOriginal));

    const imagenOriginal = obtenerImagenReporte(reporte);
    const imagen = escaparHtml(imagenOriginal);
    const cantidadImagenes = imagen ? '1 imagen' : '0 imágenes';
    const colorEstado = obtenerColorPorEstado(estadoOriginal);

    return `
        <div class="popup-reporte">
            <div class="popup-header">
                <span class="popup-icon">${obtenerEmojiPorTipo(tipo)}</span>
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
                                ? `<img src="/${imagen.replace(/^\/+/, '')}" alt="Imagen del reporte" class="popup-image">`
                                : `<div class="popup-image-empty">Sin imagen</div>`
                        }
                    </div>
                </div>

                <div class="popup-description">${descripcion}</div>

                <div class="popup-info-card">
                    <div class="popup-info-label">👤 Reportado por:</div>
                    <div class="popup-info-value">${usuarioNombre}</div>
                </div>

                <div class="popup-info-card">
                    <div class="popup-info-label">📅 Fecha:</div>
                    <div class="popup-info-value">${fecha}</div>
                </div>

                <div class="popup-info-card">
                    <div class="popup-info-label">📌 Estado:</div>
                    <div 
                        class="popup-status" 
                        style="background:${colorEstado}; color:#ffffff;"
                    >
                        ${estado}
                    </div>
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

/* ========= PINTAR REPORTES EXISTENTES ========= */

if (Array.isArray(window.reportesDB)) {
    window.reportesDB.forEach(reporte => {
        if (
            typeof reporte.latitud !== 'number' ||
            typeof reporte.longitud !== 'number'
        ) {
            return;
        }

        const emoji = obtenerEmojiPorTipo(reporte.tipo);

        const marker = L.marker([reporte.latitud, reporte.longitud], {
            icon: crearIconoReporte(emoji, reporte.estado)
        })
            .addTo(map)
            .bindPopup(crearPopupReporte(reporte), {
                maxWidth: 320,
                className: 'popup-reporte-wrapper'
            });

        const reporteId = String(reporte.id || reporte._id || '');

        if (reporteId) {
            marcadoresReportes.set(reporteId, {
                marker,
                reporte
            });
        }
    });
}

/* ========= ABRIR REPORTE DESDE ALERTAS ========= */

const parametrosMapa = new URLSearchParams(window.location.search);
const reporteDestino = parametrosMapa.get('reporte');
const latDestino = parseFloat(parametrosMapa.get('lat'));
const lngDestino = parseFloat(parametrosMapa.get('lng'));

if (reporteDestino && marcadoresReportes.has(reporteDestino)) {
    const destino = marcadoresReportes.get(reporteDestino);

    map.setView([destino.reporte.latitud, destino.reporte.longitud], 17);

    setTimeout(() => {
        destino.marker.openPopup();
    }, 350);
} else if (Number.isFinite(latDestino) && Number.isFinite(lngDestino)) {
    map.setView([latDestino, lngDestino], 17);
}
/* ========= CERRAR PANEL DE REGISTRO ========= */

const cerrarPanel = document.getElementById('cerrarPanel');
const panelRegistro = document.getElementById('panelRegistro');

if (cerrarPanel && panelRegistro) {
    cerrarPanel.addEventListener('click', function () {
        panelRegistro.classList.add('oculto');

        if (marcador) {
            map.removeLayer(marcador);
            marcador = null;
        }
    });
}
