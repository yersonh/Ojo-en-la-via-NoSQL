/* ════════════════════════════════════════════════════════
   MAPA ADMIN
   Depende de: REPORTES_DATA (data inline), showToast (panel-ui.js)
               obtenerEmojiPorTipo, crearIconoReporte, crearPopupReporte (popup-reporte.js)
════════════════════════════════════════════════════════ */
let mapaInst    = null;
let mapaMarkers = [];

function initMapa() {
    if (mapaInst) { mapaInst.invalidateSize(); return; }

    setTimeout(() => {
        const cont = document.getElementById('adminMap');
        if (!cont) return;
        if (cont._leaflet_id) { cont._leaflet_id = null; cont.innerHTML = ''; }

        mapaInst = L.map('adminMap', { preferCanvas: true }).setView([4.142, -73.626], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(mapaInst);

        cargarMarcadores(REPORTES_DATA);
    }, 200);
}

function cargarMarcadores(data) {
    mapaMarkers.forEach(m => m.remove());
    mapaMarkers = [];

    const filtro = document.getElementById('mapa-filtro-estado')?.value || '';

    data.filter(r =>
        r.lat && r.lng &&
        !isNaN(parseFloat(r.lat)) && !isNaN(parseFloat(r.lng)) &&
        (filtro === '' || r.estado === filtro)
    ).forEach(r => {
        const emoji  = obtenerEmojiPorTipo(r.tipo);
        const icon   = crearIconoReporte(emoji, r.estado);
        const popup  = crearPopupReporte(r, {
            btnTexto: '💬 Ver Comentarios',
            btnHref:  `/views/usuario/alertas.php?comentarios=${r.id}`
        });

        const m = L.marker([parseFloat(r.lat), parseFloat(r.lng)], { icon })
            .bindPopup(popup, { maxWidth: 320, className: 'popup-reporte-wrapper' });
        m.addTo(mapaInst);
        mapaMarkers.push(m);
    });

    if (mapaMarkers.length > 0) {
        const group = L.featureGroup(mapaMarkers);
        mapaInst.fitBounds(group.getBounds().pad(0.12));
    }
}

function filtrarMapa()  { if (mapaInst) cargarMarcadores(REPORTES_DATA); }
function recargarMapa() { if (mapaInst) { cargarMarcadores(REPORTES_DATA); showToast('Mapa actualizado', 'success'); } }
