class SSENotificacionesManager {
    constructor() {
        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        this.isConnected = false;
        this.badgeElement = document.getElementById('notificationBadge');
        this.userRole = null;
        this.manejadoresPersonalizados = [];
    }

    inicializar() {
        this.conectar();
        this.configurarEventosVisibilidad();
    }

    registrarManejador(evento, manejador) {
        this.manejadoresPersonalizados.push({ evento, manejador });
        console.log(`Manejador registrado para evento: ${evento}`);
    }

    configurarEventosVisibilidad() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isConnected) {
                console.log('Página visible - reconectando SSE');
                this.reconectar();
            }
        });
    }

    conectar() {
        try {
            if (this.eventSource) {
                this.eventSource.close();
            }

            const sseUrl = '../../controllers/sse_notificaciones.php';
            console.log('Conectando a SSE unificado:', sseUrl);

            this.eventSource = new EventSource(sseUrl, {
                withCredentials: true
            });

            this.eventSource.onopen = () => {
                console.log('Conexión SSE unificada establecida');
                this.isConnected = true;
                this.reconnectAttempts = 0;

                this.ejecutarManejadores('conexion_establecida', {});
            };

            this.eventSource.addEventListener('ping', (event) => {
                const data = JSON.parse(event.data);
                console.log('Ping SSE recibido', data);
                this.userRole = data.user_role;

                this.ejecutarManejadores('ping', data);
            });

            this.eventSource.addEventListener('notificacion', (event) => {
                const data = JSON.parse(event.data);
                console.log('Notificación usuario recibida:', data);

                if (data.type === 'nuevas_notificaciones' && data.for_user) {
                    this.manejarNuevasNotificacionesUsuario(data);
                }

                this.ejecutarManejadores('notificacion', data);
            });

            // NOTIFICACIONES PARA ADMIN
            this.eventSource.addEventListener('nuevo_reporte', (event) => {
                const data = JSON.parse(event.data);
                console.log('Notificación admin recibida:', data);
                this.manejarNuevoReporteAdmin(data);

                // Notificar a los manejadores personalizados
                this.ejecutarManejadores('nuevo_reporte', data);
            });

            this.eventSource.addEventListener('connected', (event) => {
                const data = JSON.parse(event.data);
                console.log('Conectado al servidor SSE, user_id:', data.user_id, 'rol:', data.user_role);
                this.userRole = data.user_role;

                this.ejecutarManejadores('connected', data);
            });

            this.eventSource.onerror = (error) => {
                console.error('Error en conexión SSE:', error);
                this.isConnected = false;
                this.manejarErrorConexion();

                this.ejecutarManejadores('error', { error });
            };

        } catch (error) {
            console.error('Error inicializando SSE:', error);
            this.manejarErrorConexion();

            // Notificar a los manejadores personalizados
            this.ejecutarManejadores('error_inicializacion', { error });
        }
    }

    ejecutarManejadores(evento, data) {
        this.manejadoresPersonalizados.forEach(({ evento: evt, manejador }) => {
            if (evt === evento) {
                try {
                    manejador(data);
                } catch (error) {
                    console.error(`Error en manejador personalizado para ${evento}:`, error);
                }
            }
        });
    }

    manejarNuevasNotificacionesUsuario(data) {
        this.actualizarBadge(data.total);
s
        if (!this.estaEnVistaNotificaciones()) {
            this.mostrarToastNotificacion(data.total, 'usuario');
        }

        this.reproducirSonidoNotificacion();
    }

    manejarNuevoReporteAdmin(data) {
        if (this.userRole !== 1) return;

        this.mostrarToastNotificacionAdmin(data);

        this.actualizarContadorReportesAdmin();

        this.reproducirSonidoNotificacion('admin');
    }

    actualizarBadge(cantidad) {
        if (!this.badgeElement) return;

        if (cantidad > 0) {
            this.badgeElement.textContent = cantidad > 99 ? '99+' : cantidad;
            this.badgeElement.style.display = 'flex';
            this.animarNuevaNotificacion();
        } else {
            this.badgeElement.style.display = 'none';
        }
    }

    animarNuevaNotificacion() {
        this.badgeElement.classList.add('nueva-notificacion');
        setTimeout(() => {
            this.badgeElement.classList.remove('nueva-notificacion');
        }, 3000);
    }

    mostrarToastNotificacion(cantidad, tipo = 'usuario') {
        const icon = tipo === 'usuario' ? 'fas fa-bell' : 'fas fa-exclamation-triangle';
        const mensaje = tipo === 'usuario'
            ? `Tienes ${cantidad} nueva(s) notificación(es)`
            : 'Nuevo reporte recibido';

        this.crearToast(mensaje, icon, tipo);
    }

    mostrarToastNotificacionAdmin(data) {
        const tipoIncidente = data.tipo_incidente || 'Incidente';
        const mensaje = `Nuevo reporte: ${tipoIncidente}`;

        this.crearToast(mensaje, 'fas fa-exclamation-triangle', 'admin', data);
    }

    crearToast(mensaje, icono, tipo = 'usuario', data = null) {
        const toast = document.createElement('div');
        toast.className = `notification-toast toast-${tipo}`;

        let acciones = '';
        if (tipo === 'admin' && data && data.id_reporte) {
            acciones = `<button class="toast-action" onclick="verReporteAdmin(${data.id_reporte})">Ver</button>`;
        }

        toast.innerHTML = `
            <div class="toast-content">
                <i class="${icono}"></i>
                <span>${mensaje}</span>
                ${acciones}
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, tipo === 'admin' ? 8000 : 5000);
    }

    actualizarContadorReportesAdmin() {
        // Si estás en el panel de admin, actualizar el contador de reportes pendientes
        const contadorReportes = document.getElementById('contadorReportesPendientes');
        if (contadorReportes) {
            // Incrementar contador o recargar la lista
            const current = parseInt(contadorReportes.textContent) || 0;
            contadorReportes.textContent = current + 1;
            contadorReportes.style.display = 'inline';
        }
    }

    reproducirSonidoNotificacion(tipo = 'usuario') {
        if (this.estaEnModoSilencioso()) return;

        try {
            if (tipo === 'admin') {
                this.crearSonidoAdmin();
            } else {
                this.crearSonidoUsuario();
            }
        } catch (error) {
            console.log('🔇 No se pudo reproducir sonido de notificación');
        }
    }

    crearSonidoUsuario() {
        // Sonido suave para notificaciones normales
        this.crearSonido(800, 0.3);
    }

    crearSonidoAdmin() {
        // Sonido más urgente para admin
        this.crearSonido(1200, 0.5);
        setTimeout(() => this.crearSonido(800, 0.3), 200);
    }

    crearSonido(frecuencia, volumen) {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.frequency.value = frecuencia;
        oscillator.type = 'sine';

        gainNode.gain.setValueAtTime(volumen, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    }

    estaEnVistaNotificaciones() {
        const notificationsView = document.getElementById('notificationsView');
        return notificationsView && notificationsView.style.display !== 'none';
    }

    estaEnModoSilencioso() {
        return localStorage.getItem('modo_silencioso') === 'true';
    }

    manejarErrorConexion() {
        this.reconnectAttempts++;

        if (this.reconnectAttempts <= this.maxReconnectAttempts) {
            console.log(`Reconectando SSE en ${this.reconnectDelay}ms (intento ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);

            setTimeout(() => {
                this.conectar();
            }, this.reconnectDelay);

            this.reconnectDelay *= 1.5;
        } else {
            console.error('Máximo de intentos de reconexión SSE alcanzado');
        }
    }

    reconectar() {
        this.reconnectAttempts = 0;
        this.reconnectDelay = 3000;
        this.conectar();
    }

    destruir() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
        console.log('🔚 SSE destruido');
    }
}

window.verReporteAdmin = function(id_reporte) {
    // Redirigir al reporte específico en el panel de admin
    console.log('📋 Redirigiendo al reporte:', id_reporte);
    // Implementa esta función según tu panel de admin
    window.location.href = `admin.php?action=ver_reporte&id=${id_reporte}`;
};

// Instancia global
window.SSEManager = new SSENotificacionesManager();