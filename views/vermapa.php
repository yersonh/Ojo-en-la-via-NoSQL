<?php
// Iniciar sesión si es necesario
session_start();

// Configuración base
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
           "://$_SERVER[HTTP_HOST]";
$esProduccion = strpos($_SERVER['HTTP_HOST'], 'railway.app') !== false || 
                strpos($_SERVER['HTTP_HOST'], 'ojo-en-la-via') !== false;

// Aquí iría la lógica para obtener datos de la BD cuando exista
// Por ahora inicializamos como array vacío
$tipos = []; 
$usuario_id = $_SESSION['usuario_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ojo en la Vía - Reportes</title>
    <link rel="shortcut icon" href="/imagenes/fiveicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
</head>
<body>
    <!-- Botón móvil para alternar panel -->
    <button class="mobile-toggle" id="panelToggle">📋 Formulario</button>

    <!-- Contenedor principal -->
    <div class="app-container">
        <!-- Mapa -->
        <div id="map"></div>

        <!-- Panel de formulario -->
        <div id="panel">
            <h2>Registrar Reporte</h2>
            <div class="search-container">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Buscar dirección en Colombia..." autocomplete="off">
                    <button type="button" id="btnBuscar" class="btn-buscar">
                        Buscar
                    </button>
                </div>
                <div id="searchResults" class="search-results"></div>
            </div>

            <div id="alertSuccess" class="alert alert-success"></div>
            <div id="alertError" class="alert alert-error"></div>

            <form id="formReporte" enctype="multipart/form-data" method="POST" action="/api/reportes.php">
                <label for="tipo">Tipo de incidente:</label>
                <select id="tipo" name="id_tipo_incidente" required>
                    <option value="">Seleccione un tipo...</option>
                    <?php if (!empty($tipos)): ?>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= htmlspecialchars($t['id_tipo_incidente']) ?>">
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="3" required></textarea>

                <!-- SECCIÓN DE IMAGEN MEJORADA CON CÁMARA -->
                <div class="campo-imagen">
                    <label for="foto">📸 Fotografía (opcional):</label>

                    <div class="opciones-imagen">
                        <button type="button" id="btnTomarFoto" class="btn-camara">
                            📸 Tomar Foto
                        </button>
                        <button type="button" id="btnSeleccionarArchivo" class="btn-archivo">
                            📁 Seleccionar Archivo
                        </button>
                    </div>

                    <input type="file" id="foto" name="imagen[]" accept="image/*" multiple style="display: none;">

                    <div class="preview">
                        <img id="previewImg" src="" alt="Vista previa" style="display: none;">
                        <div id="sinImagen" class="sin-imagen">
                            📷 No hay imagen seleccionada
                        </div>
                    </div>

                    <video id="videoCamara" autoplay playsinline style="display: none; width: 100%; border-radius: 8px;"></video>

                    <div id="controlesCamara" class="controles-camara" style="display: none;">
                        <button type="button" id="btnCapturar" class="btn-capturar">
                            Capturar Foto
                        </button>
                        <button type="button" id="btnCancelarCamara" class="btn-cancelar">
                            Cancelar
                        </button>
                    </div>

                    <canvas id="canvasCaptura" style="display: none;"></canvas>
                </div>

                <label>🗺️ Seleccione ubicación en el mapa:</label>

                <div class="coordenadas">
                    Latitud: <span id="latDisplay">No seleccionada</span><br>
                    Longitud: <span id="lngDisplay">No seleccionada</span>
                </div>

                <input type="hidden" id="latitud" name="latitud">
                <input type="hidden" id="longitud" name="longitud">
                <?php if ($usuario_id): ?>
                    <input type="hidden" id="id_usuario" name="id_usuario" value="<?php echo $usuario_id; ?>">
                <?php endif; ?>

                <div class="loading" id="loading">
                    <div class="spinner"></div> Procesando...
                </div>

                <button type="submit" id="submitBtn">Registrar Reporte</button>
            </form>

            <!-- Sección de Comentarios -->
            <div id="comentariosSection" class="comentarios-section" style="display: none;">
                <h3>💬 Comentarios del Reporte</h3>

                <div class="comentarios-list" id="comentariosList"></div>

                <form id="formComentario" class="form-comentario">
                    <input type="hidden" id="comentarioIdReporte" name="id_reporte">
                    <?php if ($usuario_id): ?>
                        <input type="hidden" name="id_usuario" value="<?php echo $usuario_id; ?>">
                    <?php endif; ?>

                    <textarea
                        id="textoComentario"
                        name="comentario"
                        placeholder="Agrega un comentario..."
                        required
                    ></textarea>

                    <button type="submit" id="btnComentario">💬 Comentar</button>
                </form>
            </div>
        </div>
    </div>

<!-- Scripts externos -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

<!-- Scripts del servidor (existirán en el futuro) -->
<script src="/components/ConnectionManager.js"></script>
<script src="/components/Buscador.js"></script>
<script src="/components/comentarios.js"></script>

<!-- Configuración inicial -->
<script>
window.__INITIAL_CONFIG__ = {
    baseUrl: '<?php echo $baseUrl; ?>',
    esProduccion: <?php echo $esProduccion ? 'true' : 'false'; ?>,
    usuarioId: <?php echo $usuario_id ?: 'null'; ?>
};
</script>

<!-- Módulos ES6 (existirán en el futuro) -->
<script type="module">
    // Verificar que los módulos existen antes de importar
    const loadModule = async (path) => {
        try {
            return await import(path);
        } catch (error) {
            console.warn(`Módulo no encontrado: ${path} - Se creará próximamente`);
            return null;
        }
    };

    (async function() {
        try {
            console.log('Inicializando aplicación con soporte offline...');

            // Cargar módulos dinámicamente
            const mapaModule = await loadModule('/components/mapa/index.js');
            const formularioModule = await loadModule('/components/formulario/index.js');

            if (mapaModule) {
                window.mapaSistema = mapaModule.mapaSistema;
                await window.mapaSistema.inicializar();
                console.log('Sistema de mapas inicializado');
            }

            if (formularioModule) {
                window.formularioSistema = formularioModule.formularioSistema;
                window.FormularioManager = window.formularioSistema;
                await window.formularioSistema.initialize();
                console.log('Sistema de formularios inicializado');
            }

            // Inicializar otros módulos con verificación
            if (typeof window.ComentariosManager !== 'undefined' && window.ComentariosManager.inicializar) {
                window.ComentariosManager.inicializar();
                console.log('ComentariosManager inicializado');
            }

            if (typeof window.BuscadorManager !== 'undefined' && window.BuscadorManager.inicializar && window.mapaSistema) {
                window.BuscadorManager.inicializar(window.mapaSistema.getMap());
                console.log('BuscadorManager inicializado');
            }

            if (window.connectionManager && window.formularioSistema) {
                window.connectionManager.addListener((online) => {
                    window.formularioSistema.handleConnectionChange(online);
                });
            }

            console.log('✅ Aplicación completamente inicializada con soporte offline');

        } catch (error) {
            console.error('Error al inicializar la aplicación:', error);

            const alertError = document.getElementById('alertError');
            if (alertError) {
                alertError.textContent = 'Error al cargar la aplicación. Por favor, recarga la página.';
                alertError.style.display = 'block';
            }
        }
    })();
</script>

<script>
(function() {
    const config = window.__INITIAL_CONFIG__ || {};
    const esProduccion = config.esProduccion;

    function corregirImagenesSoloProduccion() {
        if (!esProduccion) {
            console.log('Modo desarrollo: imágenes sin cambios');
            return;
        }

        console.log('Corrigiendo imágenes a HTTPS en producción...');

        document.querySelectorAll('img').forEach(img => {
            const srcOriginal = img.src;
            if (srcOriginal && srcOriginal.startsWith('http://')) {
                img.src = srcOriginal.replace('http://', 'https://');
                console.log('✅ Imagen corregida en producción:', srcOriginal, '→', img.src);
            }
        });

        if (esProduccion) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1) {
                            if (node.tagName === 'IMG' && node.src && node.src.startsWith('http://')) {
                                node.src = node.src.replace('http://', 'https://');
                            } else if (node.querySelectorAll) {
                                node.querySelectorAll('img').forEach(img => {
                                    if (img.src && img.src.startsWith('http://')) {
                                        img.src = img.src.replace('http://', 'https://');
                                    }
                                });
                            }
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(corregirImagenesSoloProduccion, 1000);
    });

    if (esProduccion && window.mapaSistema) {
        const originalRecargarReportes = window.mapaSistema.recargarReportes;
        if (originalRecargarReportes) {
            window.mapaSistema.recargarReportes = async function() {
                await originalRecargarReportes.call(this);
                setTimeout(corregirImagenesSoloProduccion, 500);
            };
        }
    }
})();
</script>

<script>
class SWManager {
    static async init() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.ready;
                console.log('Monitoreando actualizaciones del SW...');

                setInterval(() => {
                    registration.update();
                }, 5 * 60 * 1000);

                registration.addEventListener('updatefound', () => {
                    console.log('Nueva versión del Service Worker disponible');
                    const newWorker = registration.installing;

                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed') {
                                this.showUpdateNotification();
                            }
                        });
                    }
                });

            } catch (error) {
                console.log('No se pudo monitorear actualizaciones:', error);
            }
        }
    }

    static showUpdateNotification() {
        const notification = document.createElement('div');
        notification.innerHTML = `
            <div style="
                position: fixed;
                top: 10px;
                right: 10px;
                background: #3b82f6;
                color: white;
                padding: 12px 16px;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 10000;
                font-family: Arial;
                font-size: 14px;
                max-width: 300px;
            ">
                <strong>🔄 Actualización disponible</strong>
                <p style="margin: 5px 0; font-size: 12px;">La aplicación se ha actualizado</p>
                <button onclick="location.reload()" style="
                    background: white;
                    color: #3b82f6;
                    border: none;
                    padding: 5px 10px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    margin-right: 5px;
                ">Actualizar</button>
                <button onclick="this.parentElement.remove()" style="
                    background: transparent;
                    color: white;
                    border: 1px solid white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                ">Cerrar</button>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 30000);
    }
}

// Exponer funciones de utilidad solo en desarrollo
if (!window.__INITIAL_CONFIG__?.esProduccion) {
    window.forceSWUpdate = async function() {
        if ('serviceWorker' in navigator) {
            console.log('Forzando actualización del Service Worker...');
            const registrations = await navigator.serviceWorker.getRegistrations();

            for (let registration of registrations) {
                await registration.unregister();
                console.log('SW eliminado:', registration.scope);
            }

            console.log('Todos los SW eliminados. Recargando...');
            if (window.caches) {
                const cacheNames = await window.caches.keys();
                await Promise.all(cacheNames.map(name => window.caches.delete(name)));
            }

            setTimeout(() => {
                location.reload(true);
            }, 1000);
        } else {
            console.log('Service Worker no soportado');
        }
    };

    window.hardReload = function() {
        console.log('Recarga forzada sin cache...');
        location.reload(true);
    };

    window.debugMapa = function() {
        console.log('🔍 Estado del mapa:', {
            mapaSistema: window.mapaSistema,
            leaflet: typeof L,
            map: window.map
        });
    };
}

document.addEventListener('DOMContentLoaded', () => {
    SWManager.init();
});
</script>

<script>
// Funciones de mensajería entre componentes
window.addEventListener('message', function(event) {
    // Verificar origen si es necesario
    const message = event.data;
    console.log('📨 Mensaje recibido en mapa:', message);

    if (message && message.type === 'SHOW_REPORT') {
        mostrarReporteEnMapaDesdePanel(message);
    }
});

function mostrarReporteEnMapaDesdePanel(message) {
    console.log('Activando reporte en mapa:', message);

    if (!message.coordinates || !message.coordinates.lat || !message.coordinates.lng) {
        console.error('Coordenadas inválidas');
        return;
    }

    const lat = message.coordinates.lat;
    const lng = message.coordinates.lng;
    const reportId = message.reportId;
    const reportData = message.reportData || {};

    console.log(`Objetivo: Reporte ${reportId} en [${lat}, ${lng}]`);

    let map = null;
    if (typeof window.mapaSistema !== 'undefined' && window.mapaSistema.getMap) {
        map = window.mapaSistema.getMap();
    } else if (typeof L !== 'undefined' && window.map) {
        map = window.map;
    }

    if (!map) {
        console.error('No hay mapa disponible');
        crearMarcadorTemporal(lat, lng, reportData, null);
        return;
    }

    resaltarMarcadorReporte(reportId, lat, lng, reportData, map);
}

function resaltarMarcadorReporte(reportId, lat, lng, reportData, map) {
    console.log('BUSCANDO REPORTE EN SISTEMA:', reportId);

    map.setView([lat, lng], 16);
    console.log('Mapa centrado en:', lat, lng);

    let marcadorEncontrado = null;
    let clusterContenedor = null;

    // Buscar en markerManager si existe
    if (window.mapaSistema && window.mapaSistema.markerManager) {
        const markerManager = window.mapaSistema.markerManager;

        if (markerManager.markers && Array.isArray(markerManager.markers)) {
            for (let item of markerManager.markers) {
                if (item.data && item.data.id_reporte == reportId) {
                    console.log('Marcador encontrado en MarkerManager:', item);
                    marcadorEncontrado = item.marker;
                    break;
                }
            }
        }

        if (!marcadorEncontrado && markerManager.markerCluster) {
            const layers = markerManager.markerCluster.getLayers();
            for (let layer of layers) {
                if (layer.options && layer.options.reportId == reportId) {
                    console.log('Marcador encontrado en markerCluster:', layer);
                    marcadorEncontrado = layer;
                    clusterContenedor = markerManager.markerCluster;
                    break;
                }
            }
        }
    }

    // Buscar en todas las capas si no se encontró
    if (!marcadorEncontrado) {
        console.log('Buscando en todas las capas del mapa...');
        const targetLatLng = L.latLng(lat, lng);

        map.eachLayer((layer) => {
            if (marcadorEncontrado) return;

            if (layer instanceof L.Marker) {
                if (layer.options && layer.options.reportId == reportId) {
                    console.log('Marcador encontrado por reportId:', layer);
                    marcadorEncontrado = layer;
                    return;
                }

                const layerLatLng = layer.getLatLng();
                if (layerLatLng) {
                    const distance = targetLatLng.distanceTo(layerLatLng);
                    if (distance < 2) {
                        console.log('Marcador encontrado por coordenadas exactas:', layer);
                        marcadorEncontrado = layer;
                        return;
                    }
                }
            }

            if (layer instanceof L.MarkerClusterGroup) {
                console.log('Examinando cluster group...');
                const layersEnCluster = layer.getLayers();

                for (let clusterLayer of layersEnCluster) {
                    if (clusterLayer.options && clusterLayer.options.reportId == reportId) {
                        console.log('Marcador encontrado en cluster group:', clusterLayer);
                        marcadorEncontrado = clusterLayer;
                        clusterContenedor = layer;
                        return;
                    }
                }
            }
        });
    }

    if (marcadorEncontrado) {
        if (clusterContenedor) {
            console.log('📂 Expandiendo cluster...');
            clusterContenedor.zoomToShowLayer(marcadorEncontrado, function() {
                console.log('Cluster expandido, activando marcador...');
                setTimeout(() => {
                    activarMarcadorConEfectos(marcadorEncontrado, map, reportData);
                }, 800);
            });
        } else {
            setTimeout(() => {
                activarMarcadorConEfectos(marcadorEncontrado, map, reportData);
            }, 300);
        }
    } else {
        console.log('Marcador no encontrado en el sistema');
        crearMarcadorTemporal(lat, lng, reportData, map);
    }
}

function activarMarcadorConEfectos(marker, map, reportData) {
    console.log('ACTIVANDO MARCADOR CON EFECTOS:', marker);

    const markerLatLng = marker.getLatLng();
    if (!markerLatLng) {
        console.error('No se pudieron obtener coordenadas del marcador');
        return;
    }

    map.setView(markerLatLng, 18);

    if (marker.setZIndexOffset) {
        marker.setZIndexOffset(10000);
    }

    const element = marker.getElement();
    if (element) {
        element.style.animation = '';
        element.style.transition = 'all 0.5s ease';
        element.style.animation = 'pulseHighlight 2s infinite, bounceMarker 1s 3';
        element.style.boxShadow = '0 0 0 8px rgba(255, 215, 0, 0.4), 0 0 20px 10px rgba(255, 165, 0, 0.6)';
        element.style.zIndex = '10000';
        element.style.transform = 'scale(1.2)';

        setTimeout(() => {
            element.style.animation = '';
            element.style.boxShadow = '';
            element.style.zIndex = '';
            element.style.transform = '';
            if (marker.setZIndexOffset) {
                marker.setZIndexOffset(0);
            }
        }, 5000);
    }

    setTimeout(() => {
        if (marker.openPopup) {
            marker.openPopup();
            console.log('✅ Popup abierto forzadamente');

            setTimeout(() => {
                const popup = marker.getPopup();
                if (popup && popup.getElement) {
                    const popupElement = popup.getElement();
                    if (popupElement) {
                        popupElement.style.animation = 'slideInPopup 0.5s ease';
                        popupElement.style.zIndex = '10001';
                    }
                }
            }, 100);
        } else if (marker.bindPopup) {
            const popupContent = `
                <div class="highlighted-popup">
                    <h4>${reportData?.tipo_incidente || 'Reporte'}</h4>
                    <p><strong>Estado:</strong> ${reportData?.estado || 'No especificado'}</p>
                    <p><strong>Descripción:</strong> ${reportData?.descripcion || 'Sin descripción'}</p>
                    <p><em>📍 Navegado desde el feed</em></p>
                </div>
            `;
            marker.bindPopup(popupContent).openPopup();
        }
    }, 1000);
}

function crearMarcadorTemporal(lat, lng, reportData, map) {
    if (!map) {
        console.log('No hay mapa disponible para marcador temporal');
        return;
    }

    console.log('📍 Creando marcador temporal...');

    const marker = L.marker([lat, lng], {
        icon: L.divIcon({
            className: 'temporary-marker-highlight',
            html: `
                <div style="position: relative;">
                    <div style="font-size: 40px; filter: drop-shadow(0 0 10px gold);">📍</div>
                    <div style="position: absolute; top: 0; left: 0; width: 40px; height: 40px; border: 3px solid gold; border-radius: 50%; animation: pulseRing 2s infinite;"></div>
                </div>
            `,
            iconSize: [40, 40],
            iconAnchor: [20, 40]
        }),
        zIndexOffset: 20000
    }).addTo(map);

    const popupContent = `
        <div style="background: linear-gradient(135deg, #f093fb, #f5576c); color: white; padding: 15px; border-radius: 10px; max-width: 250px;">
            <h4 style="margin: 0 0 8px 0;">${reportData?.tipo_incidente || 'Reporte'}</h4>
            <p style="margin: 4px 0;"><strong>Estado:</strong> ${reportData?.estado || 'No especificado'}</p>
            <p style="margin: 4px 0;"><strong>Descripción:</strong> ${reportData?.descripcion || 'Sin descripción'}</p>
            <p style="margin: 4px 0;"><strong>Usuario:</strong> ${reportData?.usuario || 'Anónimo'}</p>
            <p style="margin: 8px 0 0 0;"><em>⚠️ Marcador temporal</em></p>
        </div>
    `;

    marker.bindPopup(popupContent).openPopup();
    map.setView([lat, lng], 16);

    setTimeout(() => {
        if (map && marker) {
            map.removeLayer(marker);
            console.log('🗑️ Marcador temporal eliminado');
        }
    }, 10000);
}

// Funciones expuestas globalmente
window.mostrarReporteEnMapa = mostrarReporteEnMapaDesdePanel;
window.centrarMapaEnCoordenadas = function(lat, lng) {
    if (typeof window.mapaSistema !== 'undefined' && window.mapaSistema.getMap) {
        window.mapaSistema.getMap().setView([lat, lng], 16);
    } else if (typeof L !== 'undefined' && window.map) {
        window.map.setView([lat, lng], 16);
    }
};
</script>

<style>
/* Estilos generales */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes pulseRing {
    0% { transform: scale(0.8); opacity: 1; }
    100% { transform: scale(1.5); opacity: 0; }
}

@keyframes pulseHighlight {
    0% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.7); }
    70% { box-shadow: 0 0 0 20px rgba(255, 215, 0, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0); }
}

@keyframes bounceMarker {
    0%, 20%, 50%, 80%, 100% { transform: scale(1.2) translateY(0); }
    40% { transform: scale(1.3) translateY(-10px); }
    60% { transform: scale(1.25) translateY(-5px); }
}

@keyframes slideInPopup {
    0% { opacity: 0; transform: translateY(10px) scale(0.95); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}

/* Estilos del layout */
.app-container {
    display: flex;
    height: 100vh;
    width: 100vw;
    position: relative;
}

#map {
    flex: 1;
    height: 100%;
    z-index: 1;
}

#panel {
    width: 400px;
    background: white;
    padding: 20px;
    overflow-y: auto;
    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
    z-index: 2;
    position: relative;
}

.mobile-toggle {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    padding: 12px 20px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 30px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    cursor: pointer;
    font-size: 16px;
}

@media (max-width: 768px) {
    .app-container {
        flex-direction: column;
    }
    
    #map {
        height: 60vh;
        width: 100%;
    }
    
    #panel {
        width: 100%;
        height: 40vh;
    }
    
    .mobile-toggle {
        display: block;
    }
}

/* Estilos del formulario */
.search-container {
    margin-bottom: 20px;
    position: relative;
}

.search-box {
    display: flex;
    gap: 10px;
}

#searchInput {
    flex: 1;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
}

.btn-buscar {
    padding: 10px 20px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
}

.btn-buscar:hover {
    background: #2563eb;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.search-results div {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #e0e0e0;
}

.search-results div:hover {
    background: #f0f0f0;
}

.alert {
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: none;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

label {
    display: block;
    margin: 10px 0 5px;
    font-weight: bold;
    color: #333;
}

select, textarea {
    width: 100%;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 10px;
}

select:focus, textarea:focus, #searchInput:focus {
    outline: none;
    border-color: #3b82f6;
}

/* Estilos para imagen */
.campo-imagen {
    margin-bottom: 15px;
}

.opciones-imagen {
    display: flex;
    gap: 10px;
    margin: 10px 0;
}

.btn-camara, .btn-archivo, .btn-capturar, .btn-cancelar {
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-camara {
    background: #10b981;
    color: white;
}

.btn-archivo {
    background: #6b7280;
    color: white;
}

.btn-capturar {
    background: #3b82f6;
    color: white;
}

.btn-cancelar {
    background: #ef4444;
    color: white;
}

.preview {
    margin: 10px 0;
    padding: 10px;
    border: 2px dashed #e0e0e0;
    border-radius: 8px;
    text-align: center;
    min-height: 100px;
}

#previewImg {
    max-width: 100%;
    max-height: 200px;
    border-radius: 8px;
}

.sin-imagen {
    color: #999;
    font-size: 14px;
    padding: 20px;
}

.controles-camara {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

#videoCamara {
    margin-top: 10px;
}

.coordenadas {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 8px;
    margin: 10px 0;
    font-size: 14px;
}

#submitBtn, #btnComentario {
    width: 100%;
    padding: 12px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 15px;
}

#submitBtn:hover, #btnComentario:hover {
    background: #059669;
}

#submitBtn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.loading {
    display: none;
    text-align: center;
    padding: 10px;
    color: #666;
}

.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3b82f6;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    margin: 0 auto 5px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Sección de comentarios */
.comentarios-section {
    margin-top: 30px;
    border-top: 2px solid #e0e0e0;
    padding-top: 20px;
}

.comentarios-list {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 15px;
}

.comentario-item {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.comentario-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 12px;
    color: #666;
}

.comentario-usuario {
    font-weight: bold;
    color: #333;
}

.comentario-texto {
    font-size: 14px;
    line-height: 1.4;
}

.form-comentario {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

#textoComentario {
    width: 100%;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    resize: vertical;
    min-height: 80px;
}

/* Marcadores temporales */
.temporary-marker-highlight {
    z-index: 10000 !important;
}

.temporary-marker-highlight .pulse-container {
    position: relative;
    width: 40px;
    height: 40px;
}

.highlighted-popup {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 15px;
    border-radius: 10px;
    max-width: 280px;
}

.highlighted-popup h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.highlighted-popup p {
    margin: 4px 0;
    font-size: 12px;
    line-height: 1.3;
}
</style>

</body>
</html>