/**
 * mapa-reportes.js
 * Depende de popup-reporte.js (debe cargarse antes).
 */

const map = crearMapa('map');
let marcador = null;
const marcadoresReportes = new Map();

/* ── Construye dirección corta desde respuesta de Nominatim ── */
function construirDireccion(address) {
    const via    = address.road || address.pedestrian || address.path || '';
    const numero = address.house_number || '';
    const barrio = address.neighbourhood || address.suburb || address.quarter || '';
    const ciudad = address.city || address.town || address.village || 'Villavicencio';

    const partes = [via, numero, barrio, ciudad].filter(Boolean);
    return partes.join(', ');
}

/* ── Click en el mapa para crear reporte ── */
map.on('click', async function (e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;

    if (marcador) map.removeLayer(marcador);

    marcador = L.marker([lat, lng], {
        icon: crearIconoReporte('📍', 'pendiente')
    }).addTo(map);

    const panelRegistro  = document.getElementById('panelRegistro');
    const infoUbicacion  = document.getElementById('infoUbicacion');
    const latSpan        = document.getElementById('latitud');
    const lngSpan        = document.getElementById('longitud');
    const latInput       = document.getElementById('latitudInput');
    const lngInput       = document.getElementById('longitudInput');
    const direccionInput = document.getElementById('direccionInput');

    if (panelRegistro) panelRegistro.classList.remove('oculto');
    if (infoUbicacion) infoUbicacion.classList.remove('oculto');
    if (latSpan)  latSpan.textContent  = lat.toFixed(6);
    if (lngSpan)  lngSpan.textContent  = lng.toFixed(6);
    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;

    // Geocodificación inversa en segundo plano
    if (direccionInput) {
        direccionInput.value = '';
        try {
            const url  = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=es`;
            const resp = await fetch(url, { headers: { 'Accept-Language': 'es' } });
            const data = await resp.json();
            direccionInput.value = data.address ? construirDireccion(data.address) : (data.display_name || '');
        } catch (_) {}
    }
});

/* ── Pintar reportes existentes ── */
if (Array.isArray(window.reportesDB)) {
    window.reportesDB.forEach(reporte => {
        if (typeof reporte.latitud !== 'number' || typeof reporte.longitud !== 'number') return;

        const emoji  = obtenerEmojiPorTipo(reporte.tipo);
        const marker = L.marker([reporte.latitud, reporte.longitud], {
            icon: crearIconoReporte(emoji, reporte.estado)
        })
            .addTo(map)
            .bindPopup(crearPopupReporte(reporte), {
                maxWidth: 320,
                className: 'popup-reporte-wrapper'
            });

        const reporteId = String(reporte.id || reporte._id || '');
        if (reporteId) marcadoresReportes.set(reporteId, { marker, reporte });
    });
}

/* ── Abrir reporte desde URL ── */
const params       = new URLSearchParams(window.location.search);
const reporteDest  = params.get('reporte');
const latDest      = parseFloat(params.get('lat'));
const lngDest      = parseFloat(params.get('lng'));

if (reporteDest && marcadoresReportes.has(reporteDest)) {
    const destino = marcadoresReportes.get(reporteDest);
    map.setView([destino.reporte.latitud, destino.reporte.longitud], 17);
    setTimeout(() => destino.marker.openPopup(), 350);
} else if (Number.isFinite(latDest) && Number.isFinite(lngDest)) {
    map.setView([latDest, lngDest], 17);
}

/* ── Cerrar panel de registro ── */
const cerrarPanel  = document.getElementById('cerrarPanel');
const panelRegistro = document.getElementById('panelRegistro');

if (cerrarPanel && panelRegistro) {
    cerrarPanel.addEventListener('click', function () {
        panelRegistro.classList.add('oculto');
        if (marcador) { map.removeLayer(marcador); marcador = null; }
    });
}
