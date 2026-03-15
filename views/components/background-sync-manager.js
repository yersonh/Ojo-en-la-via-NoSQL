// BackgroundSyncManager.js - VERSIÓN CORREGIDA
class BackgroundSyncManager {
    constructor() {
        this.initialized = false;
        this.syncInProgress = false;
        this.useServiceWorker = false;
        this.serviceWorkerRegistration = null;
    }

    async initialize() {
        if (this.initialized) return;

        console.log('Inicializando Background Sync Manager...');

        try {
            // 1. Verificar soporte
            this.useServiceWorker = await this.checkServiceWorkerSupport();

            if (this.useServiceWorker) {
                await this.registerServiceWorker();
            } else {
                console.log('Background Sync no disponible, usando métodos tradicionales');
            }

            // 2. Configurar event listeners
            this.setupEventListeners();

            // 3. Sincronizar pendientes al iniciar
            await this.sincronizarPendientesAlInicio();

            this.initialized = true;
            console.log('Background Sync Manager inicializado');

        } catch (error) {
            console.error('Error inicializando Background Sync Manager:', error);
        }
    }

    async checkServiceWorkerSupport() {
        return 'serviceWorker' in navigator && 'SyncManager' in window;
    }

    async registerServiceWorker() {
        try {
            this.serviceWorkerRegistration = await navigator.serviceWorker.register('/sw.js');
            console.log('Service Worker registrado:', this.serviceWorkerRegistration.scope);

            // Configurar escucha de mensajes
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.handleServiceWorkerMessage(event);
            });

            // Configurar Background Sync si está disponible
            if (this.serviceWorkerRegistration.sync) {
                this.setupBackgroundSync();
            }

            return this.serviceWorkerRegistration;
        } catch (error) {
            console.log('Service Worker no disponible:', error.message);
            this.useServiceWorker = false;
            throw error;
        }
    }

    setupBackgroundSync() {
        // Registrar sync cuando recuperamos conexión
        window.addEventListener('online', async () => {
            if (this.useServiceWorker && this.serviceWorkerRegistration) {
                try {
                    await this.serviceWorkerRegistration.sync.register('sincronizar-reportes');
                    console.log('Background Sync registrado');
                } catch (error) {
                    console.log('⚠️ No se pudo registrar Background Sync:', error);
                    // Fallback a sincronización tradicional
                    this.sincronizarSilenciosamente();
                }
            }
        });
    }

    setupEventListeners() {
        // Sincronización tradicional cuando se recupera conexión
        window.addEventListener('online', () => {
            if (!this.useServiceWorker) {
                console.log('Conexión recuperada - Sincronizando (tradicional)...');
                this.sincronizarSilenciosamente();
            }
        });

        // Sincronizar cuando la página se vuelve visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && navigator.onLine) {
                console.log('Página visible - Verificando sincronización...');
                this.sincronizarSilenciosamente();
            }
        });

        // Sincronizar periódicamente cada 2 minutos (solo si no hay Service Worker)
        if (!this.useServiceWorker) {
            setInterval(() => {
                if (navigator.onLine && !this.syncInProgress) {
                    console.log('Sincronización periódica...');
                    this.sincronizarSilenciosamente();
                }
            }, 2 * 60 * 1000);
        }
    }

    async sincronizarPendientesAlInicio() {
        // Esperar un poco antes de sincronizar al inicio
        setTimeout(async () => {
            if (navigator.onLine) {
                const pendientes = JSON.parse(localStorage.getItem('reportes_pendientes') || '[]');
                if (pendientes.length > 0) {
                    console.log(`${pendientes.length} reportes pendientes al inicio`);
                    await this.sincronizarSilenciosamente();
                }
            }
        }, 3000);
    }

async sincronizarSilenciosamente() {
    if (this.syncInProgress || !navigator.onLine) {
        return;
    }

    const pendientes = JSON.parse(localStorage.getItem('reportes_pendientes') || '[]');
    if (pendientes.length === 0) {
        console.log('ℹNo hay reportes pendientes para sincronizar');
        return;
    }

    this.syncInProgress = true;
    console.log(`Iniciando sincronización de ${pendientes.length} reportes...`);

    try {
        await OfflineManager.sincronizarReportesPendientes();
        console.log('Sincronización silenciosa completada');
    } catch (error) {
        console.error('Error en sincronización silenciosa:', error);
    } finally {
        this.syncInProgress = false;
    }
}

    async sincronizarManual() {
        if (this.syncInProgress) {
            this.mostrarMensaje('Sincronización ya en progreso...', 'info');
            return;
        }

        if (!navigator.onLine) {
            this.mostrarMensaje('No hay conexión a internet', 'error');
            return;
        }

        this.mostrarMensaje('Sincronizando reportes pendientes...', 'info');
        this.syncInProgress = true;

        try {
            await OfflineManager.sincronizarReportesPendientes();
            this.mostrarMensaje('Sincronización completada', 'success');
        } catch (error) {
            console.error('Error en sincronización manual:', error);
            this.mostrarMensaje('Error al sincronizar reportes', 'error');
        } finally {
            this.syncInProgress = false;
        }
    }

    handleServiceWorkerMessage(event) {
        const { type, message, timestamp } = event.data;
        console.log(`📨 Mensaje del SW [${type}]:`, message);

        switch (type) {
            case 'BACKGROUND_SYNC_TRIGGERED':
                console.log('🔄 Background Sync activado desde Service Worker');
                this.sincronizarSilenciosamente();
                break;

            default:
                console.log('📨 Mensaje no manejado:', type);
        }
    }

    mostrarMensaje(mensaje, tipo = 'info') {
        if (window.formularioSistema && window.formularioSistema.showAlert) {
            window.formularioSistema.showAlert(mensaje, tipo);
        } else {
            // Fallback
            console.log(`💬 ${tipo}: ${mensaje}`);
        }
    }

    getStatus() {
        return {
            initialized: this.initialized,
            syncInProgress: this.syncInProgress,
            useServiceWorker: this.useServiceWorker,
            hasPending: JSON.parse(localStorage.getItem('reportes_pendientes') || '[]').length > 0,
            online: navigator.onLine
        };
    }

    async forceSync() {
        console.log('🚀 Forzando sincronización...');
        await this.sincronizarManual();
    }
}

const backgroundSyncManager = new BackgroundSyncManager();

// Inicialización automática mejorada
document.addEventListener('DOMContentLoaded', async () => {
    try {
        await backgroundSyncManager.initialize();

        window.backgroundSyncManager = backgroundSyncManager;
        console.log('🎯 BackgroundSyncManager listo');
    } catch (error) {
        console.error('❌ Error inicializando BackgroundSyncManager:', error);
    }
});

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready.then(() => {
        if (!backgroundSyncManager.initialized) {
            backgroundSyncManager.initialize();
        }
    });
}