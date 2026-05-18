/**
 * popup-reporte.js — Componente compartido
 * Incluir ANTES de mapa-reportes.js y del mapa admin.
 * Inyecta automáticamente el CSS necesario.
 */

/* Lightbox para imágenes del mapa */
(function crearLightboxMapa() {
    if (document.getElementById('popup-lightbox')) return;
    const lb = document.createElement('div');
    lb.id = 'popup-lightbox';
    lb.innerHTML = `
        <button id="popup-lightbox-close" aria-label="Cerrar">&times;</button>
        <img id="popup-lightbox-img" src="" alt="">
    `;
    const cerrar = () => lb.classList.remove('open');
    lb.addEventListener('click', e => { if (e.target === lb) cerrar(); });
    lb.querySelector('#popup-lightbox-close').addEventListener('click', cerrar);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
    document.body.appendChild(lb);
})();

function abrirLightbox(src) {
    const lb = document.getElementById('popup-lightbox');
    document.getElementById('popup-lightbox-img').src = src;
    lb.classList.add('open');
}

/* El CSS del popup y marcadores está en /views/compartido/css/popup-reporte.css */

/* ══════════════════════════════════════════════
   UTILIDADES
══════════════════════════════════════════════ */
function escaparHtml(texto) {
    if (texto === null || texto === undefined) return '';
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalizarEstado(estado) {
    return String(estado || 'pendiente')
        .toLowerCase().trim()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/\s+/g, '_');
}

function capitalizarEstado(estado) {
    return String(estado || 'pendiente').replace(/_/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

/* ══════════════════════════════════════════════
   COLORES Y EMOJIS
══════════════════════════════════════════════ */
function obtenerColorPorEstado(estado) {
    const e = normalizarEstado(estado);
    if (e === 'pendiente')                           return '#f39c12';
    if (e === 'en_revision' || e === 'revision')     return '#3498db';
    if (e === 'notificado'  || e === 'informado')    return '#9b59b6';
    if (e === 'resuelto'    || e === 'solucionado')  return '#27ae60';
    return '#f39c12';
}

function obtenerIconoPorTipo(tipo) {
    const t = String(tipo || '').toLowerCase().trim()
        .normalize('NFD').replace(/[̀-ͯ]/g, '');
    if (t === 'accidente')                          return 'fa-car-crash';
    if (t === 'hueco')                              return 'fa-circle-notch';
    if (t === 'trafico' || t === 'tráfico')         return 'fa-traffic-light';
    if (t === 'obstruccion' || t === 'obstrucción') return 'fa-road-barrier';
    if (t === 'inundacion' || t === 'inundación')   return 'fa-water';
    if (t === 'semaforo' || t === 'semáforo')       return 'fa-traffic-light';
    if (t === 'alumbrado')                          return 'fa-lightbulb';
    if (t === 'basura')                             return 'fa-trash';
    return 'fa-map-marker-alt';
}

/* ══════════════════════════════════════════════
   ICONO DEL MARCADOR
══════════════════════════════════════════════ */
function crearIconoReporte(tipo, estado) {
    const color = obtenerColorPorEstado(estado || 'pendiente');
    const icono = obtenerIconoPorTipo(tipo);
    return L.divIcon({
        className: 'icono-reporte-personalizado',
        html: `<div class="marker-circle" style="background:${color};">
                   <i class="fas ${icono}" style="font-size:15px;color:#fff;"></i>
               </div>`,
        iconSize:    [42, 42],
        iconAnchor:  [21, 21],
        popupAnchor: [0, -18]
    });
}

/* ══════════════════════════════════════════════
   POPUP
══════════════════════════════════════════════ */
function obtenerImagenReporte(reporte) {
    if (reporte.imagen)  return reporte.imagen;
    if (reporte.foto)    return reporte.foto;
    if (Array.isArray(reporte.imagenes) && reporte.imagenes.length > 0)
        return reporte.imagenes[0];
    return '';
}

function crearPopupReporte(reporte, opciones) {
    opciones = opciones || {};

    const id          = escaparHtml(reporte.id || reporte._id || '');
    const tipo        = escaparHtml(reporte.tipo || 'Incidente');
    const descripcion = escaparHtml(reporte.descripcion || 'Sin descripción');
    const autor       = escaparHtml(reporte.usuario_nombre || reporte.usuario || 'No disponible');
    const fecha       = escaparHtml(reporte.fecha || 'No disponible');
    const estadoOrig  = reporte.estado || 'pendiente';
    const estadoLabel = escaparHtml(capitalizarEstado(estadoOrig));
    const colorEstado = obtenerColorPorEstado(estadoOrig);
    const imagen      = escaparHtml(obtenerImagenReporte(reporte));
    const icono       = obtenerIconoPorTipo(tipo);

    const btnTexto = opciones.btnTexto || '<i class="fas fa-comments"></i> Ver Comentarios';
    const btnHref  = opciones.btnHref
        ? escaparHtml(opciones.btnHref)
        : `/views/usuario/alertas.php?comentarios=${id}`;

    const btnHtml = id
        ? `<a href="${btnHref}" class="popup-button">${btnTexto}</a>`
        : '';

    return `
        <div class="popup-reporte">
            <div class="popup-header">
                <span class="popup-icon"><i class="fas ${icono}"></i></span>
                <span class="popup-title">${tipo}</span>
            </div>
            <div class="popup-body">
                <div class="popup-section">
                    <div class="popup-section-title">
                        <span><i class="fas fa-image" style="margin-right:5px;color:#6b7280;"></i>Imagen</span>
                        <span class="popup-badge">${imagen ? '1 imagen' : 'Sin imagen'}</span>
                    </div>
                    <div class="popup-image-box">
                        ${imagen
                            ? `<img src="/${imagen.replace(/^\/+/, '')}" alt="Imagen del reporte" class="popup-image" onclick="abrirLightbox(this.src)">`
                            : `<div class="popup-image-empty">Sin imagen disponible</div>`}
                    </div>
                </div>

                <div class="popup-description">${descripcion}</div>

                <div class="popup-info-card">
                    <div class="popup-info-label"><i class="fas fa-user" style="margin-right:5px;color:#6b7280;"></i>Reportado por</div>
                    <div class="popup-info-value">${autor}</div>
                </div>
                <div class="popup-info-card">
                    <div class="popup-info-label"><i class="fas fa-calendar" style="margin-right:5px;color:#6b7280;"></i>Fecha</div>
                    <div class="popup-info-value">${fecha}</div>
                </div>
                <div class="popup-info-card">
                    <div class="popup-info-label"><i class="fas fa-tag" style="margin-right:5px;color:#6b7280;"></i>Estado</div>
                    <div class="popup-info-value">
                        <span class="popup-status" style="background:${colorEstado};">${estadoLabel}</span>
                    </div>
                </div>

                ${btnHtml}
            </div>
        </div>`;
}
