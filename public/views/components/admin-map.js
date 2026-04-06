// admin-map.js
class AdminMapManager {
    constructor() {
        this.map = null;
        this.markers = [];
        this.isInitialized = false;
        this.initializationAttempts = 0;
        this.maxAttempts = 3;
    }

    init() {
        console.log('🗺️ Inicializando AdminMapManager...');
        this.observeTabChanges();

        // Inicializar si la pestaña dashboard está activa
        if (this.isDashboardActive()) {
            this.initializeMapWithRetry();
        }
    }

    isDashboardActive() {
        const dashboardTab = document.getElementById('dashboard');
        return dashboardTab && dashboardTab.classList.contains('active');
    }

    observeTabChanges() {
        // Observar clics en el menú de navegación
        const menuLinks = document.querySelectorAll('.sidebar-menu a');
        menuLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                // Extraer el nombre de la pestaña del atributo onclick
                const onclickAttr = e.target.getAttribute('onclick');
                if (onclickAttr) {
                    const match = onclickAttr.match(/showTab\('(\w+)'\)/);
                    if (match && match[1] === 'dashboard') {
                        console.log('📌 Navegando al dashboard, inicializando mapa...');
                        setTimeout(() => {
                            this.initializeMapWithRetry();
                        }, 300);
                    }
                }
            });
        });

        // Observar cambios en las pestañas
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' &&
                    mutation.attributeName === 'class' &&
                    mutation.target.id === 'dashboard') {

                    if (mutation.target.classList.contains('active')) {
                        console.log('📌 Dashboard activado vía observer');
                        setTimeout(() => {
                            this.initializeMapWithRetry();
                        }, 300);
                    } else {
                        // Limpiar cuando se sale del dashboard
                        this.cleanup();
                    }
                }
            });
        });

        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(tab => {
            observer.observe(tab, { attributes: true });
        });
    }

    initializeMapWithRetry() {
        // Evitar múltiples intentos simultáneos
        if (this.initializationInProgress) return;

        this.initializationInProgress = true;
        this.initializationAttempts = 0;

        const tryInitialize = () => {
            if (this.initializationAttempts >= this.maxAttempts) {
                console.error('❌ Límite de intentos alcanzado');
                this.initializationInProgress = false;
                return;
            }

            this.initializationAttempts++;
            console.log(`🔄 Intento ${this.initializationAttempts} de inicialización`);

            if (this.checkMapContainer()) {
                this.initializeMap();
                this.initializationInProgress = false;
            } else {
                setTimeout(tryInitialize, 500);
            }
        };

        tryInitialize();
    }

    checkMapContainer() {
        const mapContainer = document.getElementById('adminMap');

        if (!mapContainer) {
            console.log('Contenedor adminMap no encontrado en el DOM');
            return false;
        }

        const style = window.getComputedStyle(mapContainer);
        const isVisible = style.display !== 'none' &&
                            style.visibility !== 'hidden' &&
                            mapContainer.offsetParent !== null;

        const hasDimensions = mapContainer.offsetWidth > 0 && mapContainer.offsetHeight > 0;

        if (!isVisible || !hasDimensions) {
            console.log('Contenedor adminMap no visible o sin dimensiones');
            return false;
        }

        console.log('Contenedor adminMap listo');
        return true;
    }

    initializeMap() {
        // Evitar inicialización múltiple
        if (this.isInitialized && this.map) {
            console.log('ℹ️ Mapa ya inicializado, omitiendo...');
            return;
        }

        try {
            const mapContainer = document.getElementById('adminMap');

            if (!mapContainer) {
                throw new Error('Contenedor no encontrado');
            }

            // Limpiar mapa existente si hay alguno
            if (this.map) {
                console.log('🔄 Limpiando mapa anterior...');
                this.cleanup();
            }

            // Verificar si Leaflet ya inicializó este contenedor
            if (mapContainer._leaflet_id) {
                console.log('🔄 Contenedor ya tiene ID de Leaflet, limpiando...');
                // Limpiar cualquier instancia previa de Leaflet
                mapContainer._leaflet_id = null;
                // Limpiar eventos y elementos hijos
                while (mapContainer.firstChild) {
                    mapContainer.removeChild(mapContainer.firstChild);
                }
            }

            console.log('🗺️ Inicializando nuevo mapa...');

            // Inicializar mapa
            this.map = L.map('adminMap', {
                preferCanvas: true // Mejor rendimiento para muchos marcadores
            }).setView([4.142, -73.626], 13);

            // Capa del mapa
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.map);

            console.log('✅ Mapa administrativo inicializado correctamente');
            this.isInitialized = true;
            this.initializationAttempts = 0;

            // Cargar reportes después de un pequeño delay
            setTimeout(() => {
                this.loadReportes();
            }, 1000);

        } catch (error) {
            console.error('❌ Error al inicializar el mapa:', error);
            this.isInitialized = false;
            this.initializationInProgress = false;

            // Intentar limpiar para el próximo intento
            this.cleanup();
        }
    }

    async loadReportes() {
        if (!this.map || !this.isInitialized) {
            console.error('Mapa no disponible para cargar reportes');
            return;
        }

        try {
            console.log('📊 Cargando reportes...');
            const response = await fetch('../../controllers/reportecontrolador.php?action=listar');

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const reportes = await response.json();

            console.log(`📊 ${reportes.length} reportes cargados`);

            // Limpiar marcadores anteriores
            this.clearMarkers();

            // Agregar marcadores
            const validReportes = reportes.filter(reporte =>
                reporte.latitud && reporte.longitud &&
                !isNaN(parseFloat(reporte.latitud)) && !isNaN(parseFloat(reporte.longitud))
            );

            console.log(`📍 ${validReportes.length} reportes con coordenadas válidas`);

            validReportes.forEach(reporte => {
                try {
                    const lat = parseFloat(reporte.latitud);
                    const lng = parseFloat(reporte.longitud);

                    const marker = L.marker([lat, lng])
                        .addTo(this.map)
                        .bindPopup(this.createPopupContent(reporte));

                    this.markers.push(marker);
                } catch (markerError) {
                    console.error('Error creando marcador:', markerError);
                }
            });

            // Ajustar vista para mostrar todos los marcadores
            if (this.markers.length > 0) {
                const group = L.featureGroup(this.markers);
                this.map.fitBounds(group.getBounds().pad(0.1));
                console.log('🎯 Vista ajustada a los marcadores');
            } else {
                console.log('ℹ️ No hay marcadores para mostrar');
            }

        } catch (error) {
            console.error('❌ Error cargando reportes:', error);
        }
    }

    clearMarkers() {
        console.log(`🧹 Limpiando ${this.markers.length} marcadores...`);
        this.markers.forEach(marker => {
            if (this.map) {
                this.map.removeLayer(marker);
            }
        });
        this.markers = [];
    }

    cleanup() {
        console.log('🧹 Limpiando mapa...');

        // Limpiar marcadores
        this.clearMarkers();

        // Remover mapa
        if (this.map) {
            try {
                this.map.remove();
                this.map = null;
            } catch (error) {
                console.error('Error removiendo mapa:', error);
            }
        }

        this.isInitialized = false;

        // Limpiar contenedor
        const mapContainer = document.getElementById('adminMap');
        if (mapContainer) {
            mapContainer._leaflet_id = null;
            // No limpiar el HTML interno para mantener el loading message
        }
    }

    createPopupContent(reporte) {
        const statusColor = this.getStatusColor(reporte.estado);
        return `
            <div style="min-width: 250px; max-width: 300px;">
                <h4 style="margin: 0 0 8px 0; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                    ${reporte.tipo_incidente || 'Incidente'}
                </h4>
                <p style="margin: 0 0 8px 0; font-size: 14px; line-height: 1.4;">
                    ${reporte.descripcion || 'Sin descripción'}
                </p>
                <div style="font-size: 12px; color: #666; line-height: 1.4;">
                    <strong>Usuario:</strong> ${reporte.correo || reporte.usuario || 'Anónimo'}<br>
                    <strong>Fecha:</strong> ${new Date(reporte.fecha_reporte).toLocaleDateString()}<br>
                    <strong>Estado:</strong> <span style="color: ${statusColor}; font-weight: bold;">${reporte.estado || 'Desconocido'}</span>
                </div>
            </div>
        `;
    }

    getStatusColor(estado) {
        const colors = {
            'Pendiente': '#f39c12',
            'En Proceso': '#3498db',
            'Resuelto': '#27ae60',
            'Desconocido': '#95a5a6'
        };
        return colors[estado] || '#95a5a6';
    }

    // Método para forzar recarga
    refresh() {
        console.log('🔄 Recargando mapa...');
        if (this.map && this.isInitialized) {
            this.loadReportes();
        } else {
            this.initializeMapWithRetry();
        }
    }
}

// Inicialización segura
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 DOM cargado, iniciando AdminMapManager...');
    if (!window.adminMapManager) {
        window.adminMapManager = new AdminMapManager();
        window.adminMapManager.init();
    } else {
        console.log('ℹ️ AdminMapManager ya existe, usando instancia existente');
    }
});

// También inicializar cuando la ventana se carga completamente
window.addEventListener('load', function() {
    console.log('🏁 Ventana completamente cargada');
    if (window.adminMapManager && !window.adminMapManager.isInitialized) {
        setTimeout(() => {
            window.adminMapManager.initializeMapWithRetry();
        }, 1000);
    }
});