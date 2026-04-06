const NotificacionesManager = {
    inicializado: false,
    intervaloActualizacion: null,
    ultimaVerificacion: null,
    sseManager: null,

    inicializar() {
        if (this.inicializado) {
            console.log('NotificacionesManager ya estaba inicializado');
            return;
        }

        this.configurarEventos();
        this.iniciarSSE();
        this.inicializado = true;
    },

    iniciarSSE() {
        // Verificar si existe el SSE Manager global
        if (window.SSEManager && typeof window.SSEManager.inicializar === 'function') {
            this.sseManager = window.SSEManager;

            // Configurar nuestros manejadores personalizados en el SSE Manager
            this.configurarManejadoresSSE();

            // Inicializar SSE
            this.sseManager.inicializar();

        } else {
            console.warn(' SSE Manager no disponible, usando polling como fallback');
            this.iniciarPollingFallback();
        }
    },

    configurarManejadoresSSE() {
        // Sobrescribir el manejador de notificaciones de usuario
        const originalManejarNotificaciones = this.sseManager.manejarNuevasNotificacionesUsuario;

        this.sseManager.manejarNuevasNotificacionesUsuario = (data) => {
            console.log('Notificación SSE recibida en NotificacionesManager:', data);

            // Ejecutar el manejador original si existe
            if (originalManejarNotificaciones) {
                originalManejarNotificaciones.call(this.sseManager, data);
            }

            // Nuestro manejo personalizado
            this.manejarNotificacionSSE(data);
        };

        // Sobrescribir el manejador de toast
        const originalMostrarToast = this.sseManager.mostrarToastNotificacion;

        this.sseManager.mostrarToastNotificacion = (cantidad, tipo = 'usuario') => {
            // Usar nuestro toast personalizado
            this.mostrarNotificacionPush(`Tienes ${cantidad} nueva(s) notificación(es)`);
        };
    },

    manejarNotificacionSSE(data) {
        // Actualizar contador inmediatamente
        this.actualizarContadorNotificaciones();

        // Mostrar notificación push
        if (!this.estaEnVistaNotificaciones()) {
            this.mostrarNotificacionPush('Tienes nuevas notificaciones');
        }

        // Si YA estamos en la vista de notificaciones, actualizar
        if (this.estaEnVistaNotificaciones()) {
            this.cargarTodasNotificaciones();
        }
    },

    iniciarPollingFallback() {
        // Cargar contador inicial
        this.actualizarContadorNotificaciones();
        // Actualizar cada 30 segundos
        this.intervaloActualizacion = setInterval(() => {
            this.actualizarContadorNotificaciones();
        }, 30000);
    },

    configurarEventos() {
        // Evento para cargar notificaciones cuando se abre la vista
        const notificationIcon = document.querySelector('.nav-item[data-target="notificationsView"]');
        if (notificationIcon) {
            notificationIcon.addEventListener('click', (e) => {
                // Cuando se hace clic en el icono, cargar las notificaciones después de un breve delay
                setTimeout(() => {
                    if (this.estaEnVistaNotificaciones()) {
                        this.cargarTodasNotificaciones();
                    }
                }, 100);
            });
        }

        // Reconectar SSE cuando la página se vuelve visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.sseManager && !this.sseManager.isConnected) {
                console.log('Página visible - reconectando SSE');
                this.sseManager.reconectar();
            }
        });
    },

    async actualizarContadorNotificaciones() {
        try {
            const resp = await fetch('../../controllers/notificacion_controlador.php?action=contar_no_leidas', {
                method: 'GET',
                credentials: 'include'
            });

            if (!resp.ok) {
                throw new Error(`Error HTTP: ${resp.status}`);
            }

            const data = await resp.json();
            this.actualizarBadgeUI(data.total_no_leidas || 0);

            return data.total_no_leidas || 0;

        } catch (error) {
            console.error('Error actualizando contador:', error);
            this.actualizarBadgeUI(0);
            return 0;
        }
    },

    actualizarBadgeUI(totalNoLeidas) {
        const badge = document.getElementById('notificationBadge');

        if (badge) {
            if (totalNoLeidas > 0) {
                badge.textContent = totalNoLeidas > 99 ? '99+' : totalNoLeidas;
                badge.style.display = 'flex';
                this.animarNuevaNotificacion();
            } else {
                badge.style.display = 'none';
            }
        }
    },

    animarNuevaNotificacion() {
        const badge = document.getElementById('notificationBadge');
        if (!badge) return;

        badge.classList.add('nueva-notificacion');
        setTimeout(() => {
            badge.classList.remove('nueva-notificacion');
        }, 3000);
    },

    async obtenerNuevasNotificaciones() {
        try {
            const params = new URLSearchParams();
            if (this.ultimaVerificacion) {
                params.append('ultima_verificacion', Math.floor(this.ultimaVerificacion / 1000));
            }

            const resp = await fetch(`../../controllers/notificacion_controlador.php?action=obtener_nuevas&${params}`, {
                credentials: 'include'
            });

            if (!resp.ok) throw new Error(`Error HTTP: ${resp.status}`);

            const data = await resp.json();

            if (data.success && data.notificaciones.length > 0) {
                this.ultimaVerificacion = Date.now();
            }

            return data.notificaciones || [];

        } catch (error) {
            console.error('Error obteniendo nuevas notificaciones:', error);
            return [];
        }
    },

    async cargarTodasNotificaciones() {
        try {
            const notificationsView = document.getElementById('notificationsView');
            if (!notificationsView) return;

            notificationsView.innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Cargando notificaciones...</p>
                </div>
            `;

            const resp = await fetch('../../controllers/notificacion_controlador.php?action=listar', {
                credentials: 'include'
            });

            if (!resp.ok) throw new Error(`Error HTTP: ${resp.status}`);

            const notificaciones = await resp.json();

            this.mostrarNotificacionesEnVista(notificaciones, notificationsView);
            this.actualizarContadorNotificaciones();

        } catch (error) {
            console.error('Error cargando notificaciones:', error);
            const notificationsView = document.getElementById('notificationsView');
            if (notificationsView) {
                notificationsView.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error al cargar notificaciones</p>
                    </div>
                `;
            }
        }
    },

    mostrarNotificacionPush(mensaje, tipo = 'info') {

        if (document.hidden || this.estaEnVistaNotificaciones()) return;

        const toast = document.createElement('div');
        toast.className = `profile-notification profile-notification-${tipo}`;
        toast.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-bell"></i>
                <span>${this.escapeHtml(mensaje)}</span>
            </div>
        `;

        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        }, 100);

        setTimeout(() => {
            toast.style.transform = 'translateX(400px)';
            toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);

        if (!this.estaEnModoSilencioso()) {
            this.reproducirSonidoNotificacion();
        }
    },

    reproducirSonidoNotificacion() {
        try {
            // Crear un sonido simple usando Web Audio API
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (error) {
            console.log('No se pudo reproducir sonido de notificación');
        }
    },

    estaEnVistaNotificaciones() {
        const notificationsView = document.getElementById('notificationsView');
        return notificationsView && notificationsView.style.display !== 'none';
    },

    estaEnModoSilencioso() {
        return localStorage.getItem('modo_silencioso') === 'true';
    },

    mostrarNotificacionesEnVista(notificaciones, container) {
        if (!container) return;

        if (!Array.isArray(notificaciones) || notificaciones.length === 0) {
            container.innerHTML = `
                <div class="no-notifications">
                    <i class="fas fa-bell-slash" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                    <p>No tienes notificaciones</p>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="notifications-header">
                <h3>Tus Notificaciones</h3>
                <button class="btn-mark-all-read" onclick="NotificacionesManager.marcarTodasComoLeidas()">
                    <i class="fas fa-check-double"></i> Marcar todas como leídas
                </button>
            </div>
            <div class="notifications-list">
                ${notificaciones.map(notif => `
                    <div class="notification-card ${notif.leida ? 'leida' : 'no-leida'}"
                         data-id="${notif.id_notificacion}">
                        <div class="notification-card-header">
                            <div class="notification-type">
                                ${this.obtenerIconoTipo(notif.tipo)}
                                <span>${this.obtenerTextoTipo(notif.tipo)}</span>
                            </div>
                            <div class="notification-date">
                                ${this.formatearFechaCompleta(notif.fecha)}
                            </div>
                        </div>
                        <div class="notification-card-body">
                            <div class="notification-message">${this.escapeHtml(notif.mensaje)}</div>
                            ${notif.origen_nombres ? `
                                <div class="notification-from">
                                    <i class="fas fa-user"></i>
                                    De: ${this.escapeHtml(notif.origen_nombres)}
                                </div>
                            ` : ''}
                        </div>
                        <div class="notification-card-actions">
                            ${notif.id_reporte ? `
                                <button class="btn-small btn-primary"
                                        onclick="NotificacionesManager.verNotificacion(${notif.id_notificacion}, ${notif.id_reporte})">
                                    <i class="fas fa-eye"></i> Ver Reporte
                                </button>
                            ` : ''}
                            ${!notif.leida ? `
                                <button class="btn-small btn-secondary"
                                        onclick="NotificacionesManager.marcarComoLeida(${notif.id_notificacion}, this)">
                                    <i class="fas fa-check"></i> Marcar como leída
                                </button>
                            ` : `
                                <span class="badge-leida">Leída</span>
                            `}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    async marcarComoLeida(id_notificacion, elementoBtn = null) {
        try {
            const formData = new FormData();
            formData.append('id_notificacion', id_notificacion);

            const resp = await fetch('../../controllers/notificacion_controlador.php?action=marcar_leida', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await resp.json();

            if (data.success) {
                if (elementoBtn) {
                    const notificationItem = elementoBtn.closest('.notification-card');
                    if (notificationItem) {
                        notificationItem.classList.remove('no-leida');
                        notificationItem.classList.add('leida');

                        if (elementoBtn.classList.contains('btn-small')) {
                            elementoBtn.outerHTML = '<span class="badge-leida">Leída</span>';
                        }
                    }
                }

                // Actualizar contador
                this.actualizarContadorNotificaciones();
                this.mostrarMensajeExito('Notificación marcada como leída');
            } else {
                throw new Error(data.error || 'Error al marcar como leída');
            }
        } catch (error) {
            console.error('Error marcando notificación como leída:', error);
            alert('Error al marcar como leída: ' + error.message);
        }
    },

    async marcarTodasComoLeidas() {
        try {
            const resp = await fetch('../../controllers/notificacion_controlador.php?action=marcar_todas_leidas', {
                method: 'POST',
                credentials: 'include'
            });

            const data = await resp.json();

            if (data.success) {
                document.querySelectorAll('.notification-card').forEach(item => {
                    item.classList.remove('no-leida');
                    item.classList.add('leida');

                    const btn = item.querySelector('.btn-small');
                    if (btn) {
                        btn.outerHTML = '<span class="badge-leida">Leída</span>';
                    }
                });

                // Actualizar contador
                this.actualizarContadorNotificaciones();
                this.mostrarMensajeExito('Todas las notificaciones marcadas como leídas');
            } else {
                throw new Error(data.error || 'Error al marcar todas como leídas');
            }
        } catch (error) {
            console.error('Error marcando todas como leídas:', error);
            alert('Error al marcar todas como leídas: ' + error.message);
        }
    },

    verNotificacion(id_notificacion, id_reporte) {
        // marcar como leída si no lo está
        this.marcarComoLeida(id_notificacion);

        // Navegar al reporte usando el sistema de comentarios
        if (typeof ComentariosManager !== 'undefined' && ComentariosManager.abrirComentarios) {
            ComentariosManager.abrirComentarios(id_reporte);
        } else {
            console.warn('Sistema de comentarios no disponible');
            alert('Función de comentarios no disponible');
        }
    },

    verTodasNotificaciones() {
        // Navegar a la vista de notificaciones
        const navItem = document.querySelector('.nav-item[data-target="notificationsView"]');
        if (navItem) {
            navItem.click();
        }
    },

    obtenerIconoTipo(tipo) {
        const iconos = {
            'like': '👍',
            'comentario': '💬',
            'nuevo_reporte_usuario': '📢',
            'sistema': '🔔',
            'admin': '⚡'
        };
        return iconos[tipo] || '🔔';
    },

    obtenerTextoTipo(tipo) {
        const textos = {
            'like': 'Like',
            'comentario': 'Comentario',
            'nuevo_reporte_usuario': 'Nuevo Reporte',
            'sistema': 'Sistema',
            'admin': 'Administración'
        };
        return textos[tipo] || 'Notificación';
    },

    formatearTiempoRelativo(fechaString) {
        const fecha = new Date(fechaString);
        const ahora = new Date();
        const diffMs = ahora - fecha;
        const diffSeg = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSeg / 60);
        const diffHoras = Math.floor(diffMin / 60);
        const diffDias = Math.floor(diffHoras / 24);

        if (diffSeg < 60) return 'ahora';
        if (diffMin < 60) return `hace ${diffMin} min`;
        if (diffHoras < 24) return `hace ${diffHoras} h`;
        if (diffDias < 7) return `hace ${diffDias} d`;

        return fecha.toLocaleDateString('es-ES');
    },

    formatearFechaCompleta(fechaString) {
        const fecha = new Date(fechaString);
        return fecha.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    mostrarMensajeExito(mensaje) {
        console.log('' + mensaje);
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    destruir() {
        if (this.intervaloActualizacion) {
            clearInterval(this.intervaloActualizacion);
        }

        if (this.sseManager) {
            this.sseManager.destruir();
        }

        this.inicializado = false;
        console.log('NotificacionesManager destruido');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    // Esperar un poco para asegurar que SSE Manager esté disponible
    setTimeout(() => {
        NotificacionesManager.inicializar();
    }, 100);
});

document.addEventListener('DOMContentLoaded', function() {
    // Sobrescribir la función de carga de notificaciones en panel.js
    if (typeof window.cargarNotificacionesSuave === 'function') {
        const originalCargarNotificaciones = window.cargarNotificacionesSuave;
        window.cargarNotificacionesSuave = function() {
            NotificacionesManager.cargarTodasNotificaciones();
        };
    }
});

window.NotificacionesManager = NotificacionesManager;