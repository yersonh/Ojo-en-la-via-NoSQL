// components/mapa-offline.js
const MapaOfflineManager = {
    tileCache: null,
    areaVillavicencio: {
        north: 4.3, south: 4.0,  // Límites de Villavicencio
        east: -73.4, west: -73.8
    },
    isInitialized: false,

    async inicializar() {
        if (this.isInitialized) return;

        try {
            await this.inicializarCache();
            this.isInitialized = true;
            console.log('🗺️ Mapa offline inicializado');
        } catch (error) {
            console.error('❌ Error inicializando mapa offline:', error);
        }
    },

    async inicializarCache() {
        if ('caches' in window) {
            try {
                this.tileCache = await caches.open('map-tiles-villavicencio-v1');
                console.log('💾 Cache de tiles inicializado');
            } catch (error) {
                console.log('⚠️ Cache no disponible:', error);
                this.tileCache = null;
            }
        } else {
            console.log('⚠️ Cache API no soportada en este navegador');
            this.tileCache = null;
        }
    },

    // Obtener tile offline o cargar online
    async obtenerTile(url) {
        if (!this.tileCache) return url;

        try {
            // Verificar si está en cache
            const cached = await this.tileCache.match(url);
            if (cached) {
                console.log('💾 Tile servido desde cache:', url);
                return URL.createObjectURL(await cached.blob());
            }

            // Si no está, cargar y cachear
            const response = await fetch(url);
            if (response.ok) {
                await this.tileCache.put(url, response.clone());
                console.log('🌐 Tile cargado y cacheado:', url);
                return url;
            }
        } catch (error) {
            console.log('❌ Error cargando tile, usando offline:', url);
        }

        return this.generarTileOffline();
    },

    // Generar tile básico para offline
    generarTileOffline() {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 256;
        const ctx = canvas.getContext('2d');

        // Fondo gradiente suave
        const gradient = ctx.createLinearGradient(0, 0, 256, 256);
        gradient.addColorStop(0, '#f8f9fa');
        gradient.addColorStop(1, '#e9ecef');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, 256, 256);

        // Grid sutil
        ctx.strokeStyle = '#dee2e6';
        ctx.lineWidth = 0.5;

        // Líneas principales cada 32px
        for (let i = 0; i <= 256; i += 32) {
            ctx.beginPath();
            ctx.moveTo(i, 0);
            ctx.lineTo(i, 256);
            ctx.stroke();

            ctx.beginPath();
            ctx.moveTo(0, i);
            ctx.lineTo(256, i);
            ctx.stroke();
        }

        // Líneas secundarias cada 8px (más sutiles)
        ctx.strokeStyle = '#f1f3f5';
        ctx.lineWidth = 0.25;
        for (let i = 0; i <= 256; i += 8) {
            if (i % 32 !== 0) { // No dibujar donde ya hay líneas principales
                ctx.beginPath();
                ctx.moveTo(i, 0);
                ctx.lineTo(i, 256);
                ctx.stroke();

                ctx.beginPath();
                ctx.moveTo(0, i);
                ctx.lineTo(256, i);
                ctx.stroke();
            }
        }

        // Texto "Villavicencio Offline"
        ctx.fillStyle = '#6c757d';
        ctx.font = 'bold 14px Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('Villavicencio', 128, 120);

        ctx.fillStyle = '#adb5bd';
        ctx.font = '10px Arial, sans-serif';
        ctx.fillText('Modo Offline', 128, 140);

        // Marca de agua sutil
        ctx.fillStyle = 'rgba(108, 117, 125, 0.1)';
        ctx.font = '8px Arial, sans-serif';
        ctx.fillText('🗺️ Ojo en la Vía', 128, 240);

        return canvas.toDataURL();
    },

    async precargarTilesEsenciales() {
        if (!this.tileCache || !navigator.onLine) {
            console.log('⏸Precarga omitida - Sin cache o sin conexión');
            return;
        }

        const zooms = [12, 13, 14, 15, 16]; // Zooms más usados en Villavicencio
        console.log('Precargando tiles esenciales de Villavicencio...');

        let totalTiles = 0;
        let tilesCargados = 0;

        for (const z of zooms) {
            const tiles = this.calcularTilesVillavicencio(z);
            totalTiles += tiles.length;

            const batchSize = 5;
            for (let i = 0; i < tiles.length; i += batchSize) {
                const batch = tiles.slice(i, i + batchSize);
                await Promise.allSettled(
                    batch.map(tile => this.cacheTile(tile.x, tile.y, tile.z))
                );
                tilesCargados += batch.length;

                const progreso = Math.round((tilesCargados / totalTiles) * 100);
                if (progreso % 20 === 0) {
                    console.log(`📦 Precarga: ${progreso}% (${tilesCargados}/${totalTiles} tiles)`);
                }

                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }

        console.log(`Precarga completada: ${tilesCargados} tiles cacheados`);
    },

    calcularTilesVillavicencio(zoom) {
        const tiles = [];
        const bounds = this.areaVillavicencio;

        const topLeft = this.latLngToTile(bounds.north, bounds.west, zoom);
        const bottomRight = this.latLngToTile(bounds.south, bounds.east, zoom);

        const startX = Math.max(0, topLeft.x - 2);
        const endX = bottomRight.x + 2;
        const startY = Math.max(0, topLeft.y - 2);
        const endY = bottomRight.y + 2;

        for (let x = startX; x <= endX; x++) {
            for (let y = startY; y <= endY; y++) {
                // Verificar que las coordenadas son válidas para este zoom
                const maxTile = Math.pow(2, zoom) - 1;
                if (x >= 0 && x <= maxTile && y >= 0 && y <= maxTile) {
                    tiles.push({ x, y, z: zoom });
                }
            }
        }

        console.log(`Zoom ${zoom}: ${tiles.length} tiles para Villavicencio`);
        return tiles;
    },

    latLngToTile(lat, lng, zoom) {
        const latRad = lat * Math.PI / 180;
        const n = Math.pow(2, zoom);
        const x = Math.floor((lng + 180) / 360 * n);
        const y = Math.floor((1 - Math.asinh(Math.tan(latRad)) / Math.PI) / 2 * n);
        return { x, y };
    },

    async cacheTile(x, y, z) {
        const tileUrl = `https://tile.openstreetmap.org/${z}/${x}/${y}.png`;

        try {
            // Verificar si ya está cacheado
            const existing = await this.tileCache.match(tileUrl);
            if (existing) {
                return 'already_cached';
            }

            // Intentar cargar y cachear
            const response = await fetch(tileUrl);
            if (response.ok) {
                await this.tileCache.put(tileUrl, response);
                return 'cached';
            }
        } catch (error) {
        }

        return 'error';
    },

    async obtenerEstadísticasCache() {
        if (!this.tileCache) {
            return { total: 0, tamaño: '0 KB' };
        }

        try {
            const keys = await this.tileCache.keys();
            let tamañoTotal = 0;

            for (const request of keys) {
                const response = await this.tileCache.match(request);
                if (response) {
                    const blob = await response.blob();
                    tamañoTotal += blob.size;
                }
            }

            const tamañoFormateado = this.formatearTamaño(tamañoTotal);
            return {
                total: keys.length,
                tamaño: tamañoFormateado
            };
        } catch (error) {
            console.error('Error obteniendo estadísticas:', error);
            return { total: 0, tamaño: '0 KB' };
        }
    },

    formatearTamaño(bytes) {
        const unidades = ['B', 'KB', 'MB', 'GB'];
        let tamaño = bytes;
        let unidadIndex = 0;

        while (tamaño >= 1024 && unidadIndex < unidades.length - 1) {
            tamaño /= 1024;
            unidadIndex++;
        }

        return `${tamaño.toFixed(1)} ${unidades[unidadIndex]}`;
    },

    async limpiarCache() {
        if (this.tileCache) {
            const keys = await this.tileCache.keys();
            for (const request of keys) {
                await this.tileCache.delete(request);
            }
            console.log('🗑️ Cache de tiles limpiado');
        }
    },

    async estaTileCacheado(x, y, z) {
        if (!this.tileCache) return false;

        const tileUrl = `https://tile.openstreetmap.org/${z}/${x}/${y}.png`;
        const cached = await this.tileCache.match(tileUrl);
        return !!cached;
    }
};

if (typeof window !== 'undefined') {
    window.addEventListener('online', () => {
        console.log('Conexión restaurada - Verificando precarga de tiles...');
        // Precargar tiles cuando se recupera la conexión
        setTimeout(() => {
            MapaOfflineManager.precargarTilesEsenciales();
        }, 2000);
    });

    window.addEventListener('offline', () => {
        console.log('Sin conexión - Usando tiles cacheados');
    });
}

MapaOfflineManager.debug = {
    async mostrarEstadísticas() {
        const stats = await MapaOfflineManager.obtenerEstadísticasCache();
        console.log('📊 Estadísticas Cache Tiles:', stats);
        return stats;
    },

    async verTile(x, y, z) {
        const cacheado = await MapaOfflineManager.estaTileCacheado(x, y, z);
        console.log(`Tile ${z}/${x}/${y}:`, cacheado ? '✅ Cacheado' : '❌ No cacheado');
        return cacheado;
    },

    async forzarPrecarga() {
        console.log('Forzando precarga de tiles...');
        await MapaOfflineManager.precargarTilesEsenciales();
    }
};