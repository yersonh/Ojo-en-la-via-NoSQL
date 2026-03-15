const ComentariosManager = {
    inicializado: false,
    enviando: false,

    inicializar() {
        if (this.inicializado) {
            console.log('⚠️ ComentariosManager ya estaba inicializado');
            return;
        }

        this.configurarEventos();
        this.inicializado = true;
    },

    configurarEventos() {
        const formComentario = document.getElementById('formComentario');
        if (formComentario) {
            formComentario.removeEventListener('submit', this.manejadorSubmit);

            this.manejadorSubmit = (e) => this.agregarComentario(e);
            formComentario.addEventListener('submit', this.manejadorSubmit);
        }

        const textoComentario = document.getElementById('textoComentario');
        if (textoComentario) {
            textoComentario.addEventListener('input', (e) => this.actualizarContadorCaracteres(e));
        }

        const modalOverlay = document.getElementById('comentariosSection');
        if (modalOverlay) {
            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    this.cerrarComentarios();
                }
            });
        }
    },

    actualizarContadorCaracteres(e) {
        const target = e.target;
        const contador = document.getElementById('comentarioChars');

        if (contador && target) {
            contador.textContent = target.value.length;

            // Cambiar color si se acerca al límite
            if (target.value.length > 450) {
                contador.style.color = '#e74c3c';
            } else if (target.value.length > 400) {
                contador.style.color = '#f39c12';
            } else {
                contador.style.color = '#6c757d';
            }
        }
    },

    async cargarComentarios(id_reporte) {
        try {
            const comentariosList = document.getElementById('comentariosList');
            if (!comentariosList) {
                console.error('Elemento comentariosList no encontrado');
                return;
            }

            comentariosList.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Cargando comentarios...</p></div>';

            const resp = await fetch(`../../controllers/reportecontrolador.php?action=listar_comentarios&id_reporte=${id_reporte}`);

            if (!resp.ok) {
                throw new Error(`Error HTTP: ${resp.status}`);
            }

            const comentarios = await resp.json();

            comentariosList.innerHTML = '';

            if (!Array.isArray(comentarios) || comentarios.length === 0) {
                comentariosList.innerHTML = '<p style="text-align: center; color: #6c757d; padding: 20px;">No hay comentarios aún. Sé el primero en comentar.</p>';
                return;
            }

            comentarios.forEach(comentario => {
                const fecha = this.formatearFecha(comentario.fecha_comentario);

                const comentarioHTML = `
                    <div class="comentario-item">
                        <div class="comentario-header">
                            <span class="comentario-usuario">${this.escapeHtml(comentario.nombres || '')} ${this.escapeHtml(comentario.apellidos || '')}</span>
                            <span class="comentario-fecha">${fecha}</span>
                        </div>
                        <div class="comentario-texto">${this.escapeHtml(comentario.comentario || '')}</div>
                    </div>
                `;
                comentariosList.innerHTML += comentarioHTML;
            });

        } catch (error) {
            console.error('Error al cargar comentarios:', error);
            const comentariosList = document.getElementById('comentariosList');
            if (comentariosList) {
                comentariosList.innerHTML = '<p style="text-align: center; color: #e74c3c; padding: 20px;">Error al cargar comentarios</p>';
            }
        }
    },

    async agregarComentario(e) {
        e.preventDefault();

        if (this.enviando) {
            console.log('Ya se está enviando un comentario');
            return;
        }

        this.enviando = true;

        const formData = new FormData(e.target);
        const btnComentario = document.getElementById('btnComentario');
        const textoComentario = document.getElementById('textoComentario');
        const id_reporte = formData.get('id_reporte');
        const id_usuario = window.usuarioId;

        if (!btnComentario || !textoComentario) {
            console.error('Elementos del formulario no encontrados');
            this.enviando = false;
            return;
        }

        formData.append('id_usuario', id_usuario);

        btnComentario.disabled = true;
        btnComentario.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publicando...';

        try {
            const resp = await fetch('../../controllers/reportecontrolador.php?action=agregar_comentario', {
                method: 'POST',
                body: formData
            });

            const result = await resp.json();

            if (result.success) {
                textoComentario.value = '';

                this.actualizarContadorCaracteres({ target: textoComentario });

                await this.cargarComentarios(id_reporte);

                console.log('Comentario agregado correctamente');

                await this.notificarComentario(id_reporte, id_usuario);

                if (typeof window.actualizarContadorComentarios === 'function') {
                    window.actualizarContadorComentarios(id_reporte);
                }

            } else {
                console.error('Error al agregar comentario:', result.mensaje || result.error);
                alert('Error al agregar comentario: ' + (result.mensaje || result.error));
            }
        } catch (error) {
            console.error('Error de conexión:', error);
            alert('Error de conexión. Intenta nuevamente.');
        } finally {
            btnComentario.disabled = false;
            btnComentario.innerHTML = '<i class="fas fa-paper-plane"></i> Publicar Comentario';
            this.enviando = false;
        }
    },

    async notificarComentario(id_reporte, id_usuario_origen) {
        try {
            const resp = await fetch(`../../controllers/reportecontrolador.php?action=obtener_propietario&id_reporte=${id_reporte}`);
            const data = await resp.json();

            if (data.success && data.id_usuario_destino && data.id_usuario_destino !== id_usuario_origen) {
                const formData = new FormData();
                formData.append('id_reporte', id_reporte);
                formData.append('id_usuario_origen', id_usuario_origen);
                formData.append('id_usuario_destino', data.id_usuario_destino);

                await fetch('../../controllers/notificacion_controlador.php?action=notificar_comentario', {
                    method: 'POST',
                    body: formData
                });

                console.log('Notificación de comentario enviada');
            }
        } catch (error) {
            console.error('Error notificando comentario:', error);
        }
    },

    abrirComentarios(id_reporte) {
        const comentarioIdReporte = document.getElementById('comentarioIdReporte');
        const comentarioIdUsuario = document.getElementById('comentarioIdUsuario');
        const comentariosSection = document.getElementById('comentariosSection');

        if (!comentarioIdReporte || !comentariosSection) {
            console.error('Elementos necesarios para comentarios no encontrados');
            return;
        }

        // Establecer valores
        comentarioIdReporte.value = id_reporte;
        if (comentarioIdUsuario) {
            comentarioIdUsuario.value = window.usuarioId || '';
        }

        comentariosSection.style.display = 'flex';

        this.cargarComentarios(id_reporte);

        setTimeout(() => {
            const textoComentario = document.getElementById('textoComentario');
            if (textoComentario) {
                textoComentario.focus();

                // 🆕 INICIALIZAR CONTADOR
                this.actualizarContadorCaracteres({ target: textoComentario });
            }
        }, 300);
    },

    cerrarComentarios() {
        const comentariosSection = document.getElementById('comentariosSection');
        if (comentariosSection) {
            comentariosSection.style.display = 'none';
        }

        // Limpiar formulario
        const textoComentario = document.getElementById('textoComentario');
        if (textoComentario) {
            textoComentario.value = '';
            this.actualizarContadorCaracteres({ target: textoComentario });
        }
    },

    formatearFecha(fechaString) {
        try {
            const fecha = new Date(fechaString);

            // Verificar si la fecha es válida
            if (isNaN(fecha.getTime())) {
                return 'Fecha no disponible';
            }

            // Formatear en español (ya incluye ajuste de zona horaria)
            return fecha.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

        } catch (e) {
            console.error('Error formateando fecha:', e);
            return 'Fecha no disponible';
        }
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

window.actualizarContadorComentarios = async function(id_reporte) {
    try {
        const resp = await fetch(`../../controllers/reportecontrolador.php?action=contar_comentarios&id_reporte=${id_reporte}`);
        const data = await resp.json();

        // Buscar todos los posts con este id_reporte y actualizar contador
        const posts = document.querySelectorAll(`[data-post-id="${id_reporte}"]`);
        posts.forEach(post => {
            const commentBtn = post.querySelector('.comment-btn');
            if (commentBtn && data.total_comentarios !== undefined) {
                const commentText = commentBtn.querySelector('span');
                if (commentText) {
                    commentText.textContent = `Comentarios (${data.total_comentarios})`;
                }
            }
        });

        console.log(`✅ Contador de comentarios actualizado: ${data.total_comentarios}`);
    } catch (error) {
        console.error('Error actualizando contador de comentarios:', error);
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    ComentariosManager.inicializar();
    console.log('ComentariosManager listo para inicializar...');
});

setTimeout(() => {
    if (!ComentariosManager.inicializado) {
        console.log('🔄 Inicializando ComentariosManager con timeout...');
        ComentariosManager.inicializar();
    }
}, 1000);