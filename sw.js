const CACHE_NAME = 'ojo-en-la-via-v3-' + new Date().toISOString().split('T')[0];
const API_ENDPOINTS = ['/controllers/', '/api/', 'reportecontrolador', 'usuario_controlador'];


self.addEventListener('install', (event) => {
    console.log('🔧 SW Profesional instalado - Versión 3.0');
    self.skipWaiting(); // Tomar control inmediato

    // Precargar SOLO página offline y assets críticos
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                const criticalAssets = [
                    '/offline.html',
                    '/imagenes/fiveicon.png',
                    '/styles/mapa.css',
                    '/styles/formulario.css'
                ].filter(url => url); // Filtrar URLs válidas

                console.log('Precargando assets críticos:', criticalAssets);
                return cache.addAll(criticalAssets)
                    .catch(error => {
                        console.log('Algunos assets críticos fallaron:', error);
                    });
            })
    );
});

self.addEventListener('activate', (event) => {
    console.log('SW Profesional activado - Limpiando caches antiguos');

    event.waitUntil(
        Promise.all([
            self.clients.claim(), // Tomar control de todas las pestañas

            // Limpiar TODOS los caches antiguos
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== CACHE_NAME) {
                            console.log('Eliminando cache antiguo:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
        ]).then(() => {
            console.log('SW completamente activado y limpio');
        })
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // 1. Ignorar métodos que no sean GET
    if (request.method !== 'GET') {
        return;
    }

    // 2. Ignorar esquemas no soportados
    if (request.url.startsWith('chrome-extension:') ||
        request.url.startsWith('moz-extension:') ||
        request.url.includes('safari-extension')) {
        return;
    }

    // 3. Ignorar recursos de terceros (solo mismo origin)
    if (!request.url.startsWith(self.location.origin)) {
        return;
    }

    // 4. IGNORAR COMPLETAMENTE ARCHIVOS JS - EVITAR CACHE DE CÓDIGO
    if (request.url.match(/\.js(\?.*)?$/) ||
        request.destination === 'script') {
        return; // Network only - siempre la versión más reciente
    }

    // 5. IGNORAR APIS Y ENDPOINTS DINÁMICOS
    if (isApiRequest(request)) {
        return; // Network only - datos siempre frescos
    }

    // ESTRATEGIA: NETWORK-FIRST PARA TODO LO DEMÁS
    event.respondWith(handleNetworkFirst(request));
});

// FUNCIÓN PARA IDENTIFICAR REQUEST DE API
function isApiRequest(request) {
    const url = request.url.toLowerCase();
    return API_ENDPOINTS.some(endpoint => url.includes(endpoint)) ||
        request.headers.get('Accept')?.includes('application/json') ||
        url.includes('?action=') ||
        url.includes('/controllers/') ||
        url.includes('/api/');
}

// ESTRATEGIA NETWORK-FIRST (SIEMPRE VERSIÓN MÁS RECIENTE)
async function handleNetworkFirst(request) {
    try {
        // 1. INTENTAR NETWORK PRIMERO
        console.log('Network-First para:', request.url);
        const networkResponse = await fetch(request);

        // 2. VERIFICAR SI LA RESPUESTA ES VÁLIDA
        if (networkResponse && networkResponse.status === 200) {
            // 3. ACTUALIZAR CACHE EN SEGUNDO PLANO (SOLO PARA ASSETS NO-JS)
            if (shouldCache(request)) {
                cacheResponse(request, networkResponse.clone());
            }
            return networkResponse;
        }
        throw new Error('Respuesta de red no válida');

    } catch (error) {
        console.log('Network falló, intentando cache:', request.url, error.message);

        // 4. FALLBACK AL CACHE
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            console.log('Sirviendo desde cache:', request.url);
            return cachedResponse;
        }

        // 5. FALLBACK A PÁGINA OFFLINE PARA HTML
        if (request.destination === 'document' ||
            request.headers.get('Accept')?.includes('text/html')) {
            return getOfflinePage();
        }

        // 6. PARA RECURSOS ESTÁTICOS, RESPONDER CON ERROR CONTROLADO
        return new Response('', {
            status: 408,
            statusText: 'Offline',
            headers: { 'Content-Type': getContentType(request.url) }
        });
    }
}

function shouldCache(request) {
    const url = request.url.toLowerCase();

    // CACHEAR SOLO:
    // - CSS
    // - Imágenes
    // - Fuentes
    // - Páginas HTML (pero con Network-First)
    return url.match(/\.(css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/) ||
           request.destination === 'style' ||
           request.destination === 'image' ||
           request.destination === 'font';
}

async function cacheResponse(request, response) {
    try {
        const cache = await caches.open(CACHE_NAME);
        await cache.put(request, response);
    } catch (error) {
        console.log('Error actualizando cache:', error);
    }
}

async function getOfflinePage() {
    try {
        const cache = await caches.open(CACHE_NAME);
        const offlinePage = await cache.match('/offline.html');
        if (offlinePage) {
            return offlinePage;
        }
    } catch (error) {
        console.log('No se pudo obtener página offline del cache');
    }

    return new Response(`
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Modo Offline - Ojo en la Vía</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                }
                .offline-container {
                    text-align: center;
                    background: rgba(255,255,255,0.1);
                    backdrop-filter: blur(10px);
                    padding: 3rem;
                    border-radius: 20px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
                    border: 1px solid rgba(255,255,255,0.2);
                    max-width: 500px;
                    margin: 1rem;
                }
                .offline-icon {
                    font-size: 4rem;
                    margin-bottom: 1.5rem;
                    animation: pulse 2s infinite;
                }
                h1 {
                    font-size: 2rem;
                    margin-bottom: 1rem;
                    font-weight: 300;
                }
                p {
                    font-size: 1.1rem;
                    margin-bottom: 2rem;
                    opacity: 0.9;
                    line-height: 1.6;
                }
                .retry-btn {
                    background: rgba(255,255,255,0.2);
                    border: 2px solid rgba(255,255,255,0.3);
                    color: white;
                    padding: 12px 30px;
                    border-radius: 50px;
                    font-size: 1rem;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    backdrop-filter: blur(10px);
                }
                .retry-btn:hover {
                    background: rgba(255,255,255,0.3);
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                }
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                }
            </style>
        </head>
        <body>
            <div class="offline-container">
                <div class="offline-icon">📶</div>
                <h1>Sin conexión a internet</h1>
                <p>La aplicación <strong>Ojo en la Vía</strong> requiere conexión para funcionar.</p>
                <p>Verifica tu conexión e intenta nuevamente.</p>
                <button class="retry-btn" onclick="location.reload()">Reintentar conexión</button>
            </div>
        </body>
        </html>
    `, {
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'Cache-Control': 'no-cache'
        }
    });
}

// 🛠️ FUNCIÓN AUXILIAR PARA CONTENT TYPE
function getContentType(url) {
    const types = {
        '.css': 'text/css',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
        '.jpeg': 'image/jpeg',
        '.gif': 'image/gif',
        '.svg': 'image/svg+xml',
        '.ico': 'image/x-icon',
        '.woff': 'font/woff',
        '.woff2': 'font/woff2',
        '.ttf': 'font/ttf'
    };

    for (const [ext, type] of Object.entries(types)) {
        if (url.endsWith(ext)) return type;
    }

    return 'text/plain';
}

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// NOTIFICACIONES PUSH (PARA FUTURAS NOTIFICACIONES)
self.addEventListener('push', (event) => {
    if (event.data) {
        const data = event.data.json();
        const options = {
            body: data.body || 'Nueva actualización disponible',
            icon: '/imagenes/fiveicon.png',
            badge: '/imagenes/fiveicon.png',
            vibrate: [100, 50, 100],
            data: { url: data.url || '/' }
        };

        event.waitUntil(
            self.registration.showNotification(data.title || 'Ojo en la Vía', options)
        );
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(windowClients => {
            for (let client of windowClients) {
                if (client.url === event.notification.data.url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(event.notification.data.url);
            }
        })
    );
});