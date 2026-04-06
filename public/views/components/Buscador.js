const BuscadorManager = {
    map: null,
    searchMarker: null,
    currentSearchResults: [],
    userLocation: null,
    locationMarker: null,
    accuracyCircle: null,

    inicializar(map) {
        this.map = map;
        console.log('BuscadorManager inicializado');

        this.inicializarEventos();
        this.obtenerUbicacionUsuario();
    },

    inicializarEventos() {
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');

        if (!searchInput) {
            console.error('No se encontró el elemento searchInput');
            return;
        }

        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.buscarDireccion();
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                if (searchResults) searchResults.style.display = 'none';
            }
        });

        // Búsqueda en tiempo real
        let searchTimeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            if (searchInput.value.length > 2) {
                searchTimeout = setTimeout(() => {
                    this.buscarDireccion();
                }, 800);
            } else {
                if (searchResults) searchResults.style.display = 'none';
                this.ocultarInfoContextual();
            }
        });

    },

    async buscarDireccion() {
        const searchInput = document.getElementById('searchInput');
        const query = searchInput.value.trim();

        if (!query) {
            this.mostrarAlerta('Por favor, introduce una dirección para buscar', 'error');
            return;
        }

        try {
            this.mostrarLoadingBusqueda(true);

            const resultados = await this.buscarConOpenStreetMap(query);
            this.currentSearchResults = resultados;
            this.mostrarResultadosBusqueda(resultados);

        } catch (error) {
            console.error('Error en búsqueda:', error);
            this.mostrarAlerta('Error al buscar la dirección. Inténtalo de nuevo.', 'error');
        } finally {
            this.mostrarLoadingBusqueda(false);
        }
    },

    async buscarConOpenStreetMap(query) {
        try {
            let url = `https://nominatim.openstreetmap.org/search?` +
                `format=json&` +
                `q=${encodeURIComponent(query)}&` +
                `limit=10&` +
                `countrycodes=co&` +
                `accept-language=es&` +
                `addressdetails=1`;

            if (this.userLocation) {
                url += `&viewbox=${this.getViewboxAroundUser()}`;
                url += `&bounded=1`;
                console.log('🔍 Búsqueda CONTEXTUAL activada');
            }

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }

            const data = await response.json();

            if (this.userLocation && data.length > 0) {
                return this.ordenarResultadosPorDistancia(data);
            }

            return data;

        } catch (error) {
            console.error('Error en búsqueda OSM:', error);
            throw error;
        }
    },

    mostrarResultadosBusqueda(resultados) {
        const resultsContainer = document.getElementById('searchResults');
        if (!resultsContainer) return;

        if (resultados.length === 0) {
            resultsContainer.innerHTML = '<div class="no-results">No se encontraron resultados para tu búsqueda</div>';
            resultsContainer.style.display = 'block';
            return;
        }

        let html = '';
        resultados.forEach((resultado, index) => {
            const tipoLugar = this.formatearTipoLugar(resultado.type);

            // CALCULAR DISTANCIA SI TENEMOS UBICACIÓN
            let infoDistancia = '';
            let badgeCercano = '';

            if (this.userLocation) {
                const distancia = this.calcularDistancia(
                    this.userLocation.lat, this.userLocation.lng,
                    parseFloat(resultado.lat), parseFloat(resultado.lon)
                );

                if (distancia < 1) {
                    infoDistancia = `• ${(distancia * 1000).toFixed(0)}m`;
                } else {
                    infoDistancia = `• ${distancia.toFixed(1)}km`;
                }

                // Badge para resultados cercanos
                if (index < 3 && distancia < 50) {
                    badgeCercano = `<span class="relevance-badge" style="background: #10b981;">Cercano</span>`;
                }
            }

            html += `
                <div class="search-result-item" onclick="BuscadorManager.seleccionarResultadoBusqueda(${index})">
                    <div class="result-name">
                        ${resultado.display_name}
                        ${badgeCercano}
                    </div>
                    <div class="result-address">
                        📍 ${tipoLugar} ${infoDistancia}
                    </div>
                </div>
            `;
        });

        resultsContainer.innerHTML = html;
        resultsContainer.style.display = 'block';

        if (this.userLocation) {
            this.mostrarInfoContextual();
        }
    },

    seleccionarResultadoBusqueda(index) {
        const resultsContainer = document.getElementById('searchResults');
        const selectedResult = this.currentSearchResults[index];

        if (!selectedResult) return;

        this.procesarResultadoSeleccionado(selectedResult);

        // Ocultar resultados
        if (resultsContainer) resultsContainer.style.display = 'none';
    },

    procesarResultadoSeleccionado(resultado) {
        const lat = parseFloat(resultado.lat);
        const lon = parseFloat(resultado.lon);

        if (this.searchMarker) {
            this.map.removeLayer(this.searchMarker);
        }

        this.map.flyTo([lat, lon], 16, {
            duration: 1.5,
            easeLinearity: 0.25
        });
        this.searchMarker = L.marker([lat, lon], {
            icon: L.divIcon({
                className: 'search-marker',
                html: `
                    <div style="
                        background: #4264fb;
                        border: 3px solid white;
                        border-radius: 50%;
                        width: 50px;
                        height: 50px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 22px;
                        box-shadow: 0 4px 20px rgba(66, 100, 251, 0.4);
                        animation: pulse 1.5s infinite;
                        cursor: pointer;
                    ">
                        📍
                    </div>
                `,
                iconSize: [50, 50],
                iconAnchor: [25, 50],
                popupAnchor: [0, -50]
            }),
            zIndexOffset: 2000
        }).addTo(this.map);

        // Popup informativo
        this.searchMarker.bindPopup(`
            <div style="text-align: center; padding: 10px; min-width: 280px; font-family: Arial, sans-serif;">
                <div style="font-size: 16px; font-weight: bold; color: #4264fb; margin-bottom: 8px;">
                    🔍 Dirección Encontrada
                </div>
                <div style="font-size: 13px; margin-bottom: 10px; color: #555; line-height: 1.4; max-height: 80px; overflow-y: auto;">
                    ${resultado.display_name}
                </div>
                <div style="font-family: 'Courier New', monospace; font-size: 11px; background: #f8f9fa; padding: 8px; border-radius: 4px; margin-bottom: 10px;">
                    <strong>Lat:</strong> ${lat.toFixed(6)}<br>
                    <strong>Lon:</strong> ${lon.toFixed(6)}
                </div>
                <div style="margin-top: 10px;">
                    <button onclick="BuscadorManager.usarEstaUbicacion(${lat}, ${lon})"
                            style="
                                background: #4264fb;
                                color: white;
                                border: none;
                                padding: 10px 20px;
                                border-radius: 6px;
                                cursor: pointer;
                                font-size: 12px;
                                font-weight: bold;
                            ">
                        📌 Usar esta ubicación
                    </button>
                </div>
            </div>
        `).openPopup();

        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = resultado.display_name;
    },

    usarEstaUbicacion(lat, lng) {
        if (typeof FormularioManager !== 'undefined' && FormularioManager.actualizarCoordenadas) {
            FormularioManager.actualizarCoordenadas(lat, lng);
        }

        if (this.searchMarker) {
            this.searchMarker.closePopup();
        }

    },

    async obtenerUbicacionUsuario() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocalización no soportada'));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };

                    console.log('✅ Ubicación del usuario obtenida');
                    this.actualizarEstadoUbicacion(true);
                    resolve(this.userLocation);
                },
                (error) => {
                    console.log('❌ No se pudo obtener ubicación:', error.message);
                    this.actualizarEstadoUbicacion(false);
                    reject(error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000
                }
            );
        });
    },

    actualizarEstadoUbicacion(disponible) {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        if (disponible && this.userLocation) {
            searchInput.placeholder = `🔍 Buscar cerca de tu ubicación...`;
            searchInput.style.background = '#f0f9ff';
            searchInput.style.borderColor = '#3b82f6';
        } else {
            searchInput.placeholder = `🔍 Buscar en Colombia...`;
            searchInput.style.background = '';
            searchInput.style.borderColor = '';
        }
    },

    mostrarInfoContextual() {
        const searchContainer = document.querySelector('.search-container');
        if (!searchContainer) return;

        this.ocultarInfoContextual();

        const infoDiv = document.createElement('div');
        infoDiv.id = 'contextual-info';
        infoDiv.innerHTML = `
            <div style="
                background: #f0f9ff;
                border: 1px solid #3b82f6;
                border-radius: 6px;
                padding: 8px 12px;
                margin-top: 8px;
                font-size: 12px;
                color: #1e40af;
            ">
                <strong>📍 Búsqueda contextual activada</strong><br>
                <small>Priorizando resultados cerca de tu ubicación</small>
            </div>
        `;

        searchContainer.appendChild(infoDiv);
    },

    ocultarInfoContextual() {
        const existingInfo = document.getElementById('contextual-info');
        if (existingInfo) existingInfo.remove();
    },

    getViewboxAroundUser() {
        if (!this.userLocation) return '';

        const lat = this.userLocation.lat;
        const lng = this.userLocation.lng;
        const radius = 0.18;

        const left = lng - radius;
        const right = lng + radius;
        const top = lat + radius;
        const bottom = lat - radius;

        return `${left},${top},${right},${bottom}`;
    },

    ordenarResultadosPorDistancia(resultados) {
        return resultados.sort((a, b) => {
            const distA = this.calcularDistancia(
                this.userLocation.lat, this.userLocation.lng,
                parseFloat(a.lat), parseFloat(a.lon)
            );
            const distB = this.calcularDistancia(
                this.userLocation.lat, this.userLocation.lng,
                parseFloat(b.lat), parseFloat(b.lon)
            );

            return distA - distB;
        });
    },

    calcularDistancia(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = this.toRad(lat2 - lat1);
        const dLon = this.toRad(lon2 - lon1);

        const a =
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
            Math.sin(dLon/2) * Math.sin(dLon/2);

        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    },

    toRad(grados) {
        return grados * (Math.PI / 180);
    },

    formatearTipoLugar(tipo) {
        const tipos = {
            'city': 'Ciudad', 'town': 'Pueblo', 'village': 'Aldea',
            'hamlet': 'Caserío', 'suburb': 'Barrio', 'neighborhood': 'Vecindario',
            'road': 'Calle', 'street': 'Calle', 'house': 'Casa',
            'building': 'Edificio', 'administrative': 'Área administrativa'
        };
        return tipos[tipo] || tipo;
    },

    mostrarLoadingBusqueda(mostrar) {
        const btnBuscar = document.getElementById('btnBuscar');
        if (!btnBuscar) return;

        if (mostrar) {
            btnBuscar.innerHTML = '<div class="search-loading"></div>';
            btnBuscar.disabled = true;
        } else {
            btnBuscar.innerHTML = 'Buscar';
            btnBuscar.disabled = false;
        }
    },

    mostrarAlerta(mensaje, tipo = 'success') {
        // Usar el sistema de alertas del MapaManager
        if (typeof MapaManager !== 'undefined') {
            MapaManager.mostrarAlerta(mensaje, tipo);
        } else {
            alert(mensaje);
        }
    },

    limpiarBusqueda() {
        if (this.searchMarker) {
            this.map.removeLayer(this.searchMarker);
            this.searchMarker = null;
        }

        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';

        const searchResults = document.getElementById('searchResults');
        if (searchResults) searchResults.style.display = 'none';

        this.ocultarInfoContextual();
    }
};