/**
 * popup-reporte.js — Componente compartido
 * Incluir ANTES de mapa-reportes.js y del mapa admin.
 * Inyecta automáticamente el CSS necesario.
 */

(function inyectarEstilosPopup() {
    if (document.getElementById('popup-reporte-styles')) return;
    const s = document.createElement('style');
    s.id = 'popup-reporte-styles';
    s.textContent = `
        /* ── Leaflet wrapper ── */
        .popup-reporte-wrapper .leaflet-popup-content-wrapper {
            border-radius: 16px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
        }
        .popup-reporte-wrapper .leaflet-popup-content {
            margin: 0 !important;
            width: 290px !important;
        }
        .popup-reporte-wrapper .leaflet-popup-close-button {
            color: #fff !important;
            font-size: 18px !important;
            top: 10px !important;
            right: 10px !important;
        }

        /* ── Estructura del popup ── */
        .popup-reporte {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
        }
        .popup-header {
            background: linear-gradient(90deg, #1e88e5, #1565c0);
            color: #fff;
            padding: 14px 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .popup-icon { font-size: 20px; line-height: 1; }
        .popup-title { font-size: 15px; }
        .popup-body {
            padding: 14px;
            background: #f8fafc;
        }
        .popup-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .popup-section-title {
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f9fafb;
        }
        .popup-badge {
            background: #e5e7eb;
            color: #4b5563;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 999px;
        }
        .popup-image-box {
            width: 100%;
            height: 140px;
            background: #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .popup-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .popup-image-empty {
            color: #9ca3af;
            font-size: 13px;
        }
        .popup-description {
            font-size: 13px;
            color: #374151;
            margin-bottom: 10px;
            line-height: 1.45;
        }
        .popup-info-card {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 9px 12px;
            margin-bottom: 8px;
        }
        .popup-info-label {
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 3px;
        }
        .popup-info-value {
            font-size: 13px;
            color: #4b5563;
            word-break: break-word;
        }
        .popup-status {
            display: inline-block;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
        }
        .popup-button {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1e88e5;
            color: #fff !important;
            font-weight: 700;
            font-size: 13px;
            padding: 11px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            margin-top: 6px;
            text-decoration: none !important;
            box-sizing: border-box;
            transition: background .2s;
        }
        .popup-button:hover { background: #1565c0; color: #fff !important; }

        /* ── Marcadores ── */
        .icono-reporte-personalizado {
            background: transparent !important;
            border: none !important;
        }
        .marker-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,.28);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .marker-emoji {
            font-size: 18px;
            line-height: 1;
        }
    `;
    document.head.appendChild(s);
})();

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
                            ? `<img src="/${imagen.replace(/^\/+/, '')}" alt="Imagen del reporte" class="popup-image">`
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
