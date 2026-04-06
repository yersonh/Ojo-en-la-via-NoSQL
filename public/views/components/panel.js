// Panel.js - Versión corregida con sistema de likes funcionando
document.addEventListener('DOMContentLoaded', function() {

    setTimeout(() => {
        try {
            const navItems = document.querySelectorAll('.nav-item');
            const bottomNav = document.querySelector('.bottom-nav');
            const mainContent = document.querySelector('.main');
            const navActivationZone = document.createElement('div');

            navActivationZone.className = 'nav-activation-zone';
            document.body.appendChild(navActivationZone);

            // Estado de la navegación
            let isNavHidden = false;
            let hideTimeout = null;
            let lastScrollTop = 0;
            let scrollDirection = 'down';

            navItems.forEach(i => i.addEventListener('click', onNavClick));
            initAutoHideNav();

            cargarFeedSuave();


            function initAutoHideNav() {
                hideTimeout = setTimeout(hideNavigation, 3000);
                navActivationZone.addEventListener('mouseenter', showNavigation);
                navActivationZone.addEventListener('touchstart', showNavigation);
                bottomNav.addEventListener('mouseenter', showNavigation);
                bottomNav.addEventListener('touchstart', showNavigation);

                bottomNav.addEventListener('mouseleave', () => {
                    if (!isUserInteracting()) {
                        hideTimeout = setTimeout(hideNavigation, 1000);
                    }
                });

                if (mainContent) {
                    mainContent.addEventListener('scroll', handleScroll);
                }

                document.addEventListener('mousemove', resetHideTimer);
                document.addEventListener('touchstart', resetHideTimer);
                document.addEventListener('click', resetHideTimer);
            }

            function handleScroll() {
                const scrollTop = mainContent.scrollTop;
                if (scrollTop > lastScrollTop) {
                    scrollDirection = 'down';
                } else {
                    scrollDirection = 'up';
                }
                lastScrollTop = scrollTop;

                if (scrollDirection === 'down' && !isNavHidden) {
                    hideNavigation();
                } else if (scrollDirection === 'up' && isNavHidden) {
                    showNavigation();
                }

                resetHideTimer();
            }

            function resetHideTimer() {
                clearTimeout(hideTimeout);
                if (!isNavHidden) {
                    hideTimeout = setTimeout(hideNavigation, 3000);
                }
            }

            function actualizarPosicionBoton() {
                const mapButton = document.querySelector('.map-floating-button');
                const bottomNav = document.querySelector('.bottom-nav');

                if (mapButton && bottomNav) {
                    if (bottomNav.classList.contains('hidden')) {
                        mapButton.style.bottom = '20px';
                    } else {
                        mapButton.style.bottom = '80px';
                    }
                }
            }

            function hideNavigation() {
                if (!isNavHidden && bottomNav) {
                    bottomNav.classList.remove('visible');
                    bottomNav.classList.add('hidden');
                    navActivationZone.classList.add('active');
                    if (mainContent) {
                        mainContent.classList.remove('with-visible-nav');
                        mainContent.classList.add('with-hidden-nav');
                    }
                    isNavHidden = true;
                    actualizarPosicionBoton();
                }
            }

            function showNavigation() {
                clearTimeout(hideTimeout);
                if (isNavHidden && bottomNav) {
                    bottomNav.classList.remove('hidden');
                    bottomNav.classList.add('visible');
                    navActivationZone.classList.remove('active');
                    if (mainContent) {
                        mainContent.classList.remove('with-hidden-nav');
                        mainContent.classList.add('with-visible-nav');
                    }
                    isNavHidden = false;
                    actualizarPosicionBoton();
                    hideTimeout = setTimeout(hideNavigation, 3000);
                }
            }

            function isUserInteracting() {
                return bottomNav.matches(':hover') || navActivationZone.matches(':hover');
            }

            async function onNavClick(e) {
                navItems.forEach(n => n.classList.remove('active'));
                e.currentTarget.classList.add('active');

                const target = e.currentTarget.getAttribute('data-target');
                document.querySelectorAll('#mainContent > div').forEach(d => {
                    d.style.display = 'none';
                });

                const targetElement = document.getElementById(target);
                if (targetElement) {
                    targetElement.style.display = 'block';

                    if (target === 'feedView') {
                        cargarFeedSuave();
                    } else if (target === 'notificationsView') {
                        cargarNotificacionesSuave();
                    } else if (target === 'profileView') {
                        cargarEstadisticasPerfil();
                    }

                    const mapButton = document.querySelector('.map-floating-button');

                    if (target === 'mapView') {
                        targetElement.style.position = 'fixed';
                        targetElement.style.top = '44px';
                        targetElement.style.left = '0';
                        targetElement.style.right = '0';
                        targetElement.style.bottom = '0';
                        targetElement.style.width = '100%';
                        targetElement.style.height = 'calc(100vh - 44px)';
                        targetElement.style.zIndex = '998';

                        if (mapButton) {
                            mapButton.style.display = 'flex';
                            mapButton.classList.add('visible');
                            mapButton.classList.remove('hidden');
                        }

                        const iframe = targetElement.querySelector('iframe');
                        if (iframe) {
                            iframe.style.width = '100%';
                            iframe.style.height = '100%';
                        }
                    } else {
                        if (mapButton) {
                            mapButton.style.display = 'none';
                            mapButton.classList.remove('visible');
                            mapButton.classList.add('hidden');
                        }
                    }
                }

                showNavigation();
            }

        } catch (error) {
            console.error('Error durante la inicialización:', error);
        }
    }, 100);
});

async function cargarEstadisticasPerfil() {

    try {
        actualizarEstadisticasUI({
            reports: '...',
            likes: '...',
            comments: '...',
            views: '...'
        });

        const resp = await fetch('../../controllers/usuario_controlador.php?action=obtener_estadisticas', {
            method: 'GET',
            credentials: 'include'
        });

        if (!resp.ok) {
            throw new Error(`Error HTTP: ${resp.status}`);
        }

        const data = await resp.json();

        if (data.success) {
            actualizarEstadisticasUI(data.estadisticas);
        } else {
            throw new Error(data.error || 'Error al cargar estadísticas');
        }

    } catch (error) {
        console.error('Error cargando estadísticas:', error);
        // Mostrar valores por defecto
        actualizarEstadisticasUI({
            reports: '0',
            likes: '0',
            comments: '0',
            views: '0'
        });
    }
}

function actualizarEstadisticasUI(estadisticas) {
    const elementos = {
        reports: document.getElementById('statReports'),
        likes: document.getElementById('statLikes'),
        comments: document.getElementById('statComments'),
        views: document.getElementById('statViews')
    };

    for (const [key, element] of Object.entries(elementos)) {
        if (element && estadisticas[key] !== undefined) {
            element.textContent = estadisticas[key];

            element.style.transform = 'scale(1.1)';
            setTimeout(() => {
                element.style.transform = 'scale(1)';
            }, 300);
        }
    }
}

async function verificarSesion() {
    try {
        const resp = await fetch('../../controllers/usuario_controlador.php?action=verificar_sesion', {
            method: 'GET',
            credentials: 'include'
        });

        const data = await resp.json();
        console.log('Estado sesión:', data);

        if (!data.sesion_activa) {
            console.warn('Sesión no activa, redirigiendo...');
            window.location.href = '../../index.php';
            return false;
        }

        return true;
    } catch (error) {
        console.error('Error verificando sesión:', error);
        return false;
    }
}

function cargarImagenSegura(elemento, url) {
    if (!elemento) return;

    if (!url || url === '' || url === 'null') {
        elemento.src = window.location.origin + '/imagenes/default-avatar.png';
        return;
    }

    if (url.startsWith('http')) {
        elemento.src = url;
    } else {
        const baseUrl = window.location.origin;
        elemento.src = baseUrl + (url.startsWith('/') ? url : '/' + url);
    }

    // Manejar errores de carga de manera más robusta
    elemento.onerror = function() {
        console.warn('Error cargando imagen:', this.src);
        // Usar imagen por defecto absoluta
        this.src = window.location.origin + '/imagenes/default-avatar.png';
        this.onerror = null; // Prevenir bucles infinitos
    };

    // Verificar si la imagen carga correctamente
    elemento.onload = function() {
        console.log('Imagen cargada correctamente:', this.src);
    };
}

function aplicarCargaSeguraAvatares() {
    document.querySelectorAll('.post-header .avatar').forEach(avatar => {
        cargarImagenSegura(avatar, avatar.src);
    });
}

// Cargar feed de forma segura
async function cargarFeedSuave() {
    console.log('Cargando feed de reportes...');

    const feedView = document.getElementById('feedView');
    if (!feedView) {
        console.error('feedView no encontrado');
        return;
    }

    const postsContainer = document.getElementById('postsContainer');
    const loadingPosts = document.getElementById('loadingPosts');
    const noPosts = document.getElementById('noPosts');

    function mostrarElemento(elemento, mostrar) {
        if (elemento && elemento.style) {
            elemento.style.display = mostrar ? 'block' : 'none';
        }
    }

    if (postsContainer && loadingPosts && noPosts) {
        postsContainer.innerHTML = '';
        mostrarElemento(loadingPosts, true);
        mostrarElemento(noPosts, false);
    } else {
        feedView.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Cargando reportes...</p></div>';
    }

    try {
        const resp = await fetch('../../controllers/reportecontrolador.php?action=listar', {
            credentials: 'include'
        });

        // MANEJO SEGURO DE ERRORES - SIN REDIRECCIONES
        if (!resp.ok) {
            if (resp.status === 401) {
                console.warn('Posible problema de sesión al cargar feed');
                // No redirigir, solo mostrar error
                throw new Error('Problema de autenticación');
            }
            throw new Error(`Error HTTP: ${resp.status}`);
        }

        const data = await resp.json();

        if (!Array.isArray(data)) {
            throw new Error('Respuesta inválida del servidor');
        }

        if (data.length === 0) {
            if (postsContainer && loadingPosts && noPosts) {
                mostrarElemento(loadingPosts, false);
                mostrarElemento(noPosts, true);
            } else {
                feedView.innerHTML = '<p style="text-align:center; color:var(--text-muted); padding: 40px;">No hay reportes disponibles.</p>';
            }
            return;
        }

        if (postsContainer && loadingPosts && noPosts) {
            mostrarElemento(loadingPosts, false);
            postsContainer.innerHTML = '';

            data.forEach(reporte => {
                try {
                    const postElement = crearPostElement(reporte);
                    if (postElement) {
                        postsContainer.appendChild(postElement);
                    }
                } catch (error) {
                    console.error('Error creando elemento de post:', error);
                }
            });

            aplicarCargaSeguraAvatares();
        } else {
            feedView.innerHTML = '';
            data.forEach(post => {
                try {
                    const postElement = crearPostElement(post);
                    if (postElement) {
                        feedView.appendChild(postElement);
                    }
                } catch (error) {
                    console.error('Error creando post fallback:', error);
                }
            });
        }

        console.log(`Feed cargado: ${data.length} reportes`);

    } catch (err) {
        console.error('Error cargando feed:', err);

        // MOSTRAR ERROR SIN REDIRIGIR
        if (postsContainer && loadingPosts && noPosts) {
            mostrarElemento(loadingPosts, false);
            mostrarElemento(noPosts, true);
            noPosts.innerHTML = '<p style="text-align:center; color:var(--warning); padding: 20px;">Problema al cargar reportes. Intenta recargar la página.</p>';
        } else {
            feedView.innerHTML = '<p style="text-align:center; color:var(--warning); padding: 20px;">Problema al cargar reportes.</p>';
        }
    }
}

// Versiones seguras de otras funciones
async function cargarNotificacionesSuave() {
    const notificationsView = document.getElementById('notificationsView');
    if (!notificationsView) return;

    notificationsView.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Cargando notificaciones...</p></div>';

    try {
        const resp = await fetch('../../controllers/notificacion_controlador.php?action=listar', {
            credentials: 'include'
        });

        if (!resp.ok) {
            if (resp.status === 401) {
                notificationsView.innerHTML = '<div class="notification">Problema de sesión al cargar notificaciones</div>';
                return;
            }
            throw new Error(`Error HTTP: ${resp.status}`);
        }

        const data = await resp.json();

        if (!Array.isArray(data) || data.length === 0) {
            notificationsView.innerHTML = `
                <div class="no-notifications">
                    <i class="fas fa-bell-slash" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                    <p>No tienes notificaciones</p>
                </div>
            `;
            return;
        }

        notificationsView.innerHTML = '';
        data.forEach(n => {
            const div = document.createElement('div');
            div.className = `notification ${n.leida ? 'leida' : 'no-leida'}`;

            // Icono según el tipo de notificación
            let icono = '🔔';
            if (n.tipo === 'like') icono = '👍';
            else if (n.tipo === 'comentario') icono = '💬';
            else if (n.tipo === 'nuevo_reporte_usuario') icono = '📢';

            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 10px;">
                    <div style="flex: 1;">
                        <div style="font-size: 20px; margin-bottom: 5px;">${icono}</div>
                        <strong>${escapeHtml(n.origen_nombres || 'Sistema')}</strong>
                        <div style="font-size:13px; color:#666; margin: 5px 0;">${escapeHtml(n.mensaje || n.tipo)}</div>
                        <div style="font-size:12px; color:#999;">${new Date(n.fecha).toLocaleString('es-ES')}</div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        ${n.id_reporte ? `<button class="btn-small" onclick="verNotificacion(${n.id_notificacion}, ${n.id_reporte})">Ver</button>` : ''}
                        <button class="btn-small ${n.leida ? 'btn-secondary' : 'btn-primary'}" onclick="marcarLeida(${n.id_notificacion}, this)">
                            ${n.leida ? 'Leída' : 'Marcar leída'}
                        </button>
                    </div>
                </div>
            `;
            notificationsView.appendChild(div);
        });

        // Botón para marcar todas como leídas
        const markAllBtn = document.createElement('button');
        markAllBtn.className = 'btn btn-primary';
        markAllBtn.style.margin = '15px auto';
        markAllBtn.style.display = 'block';
        markAllBtn.innerHTML = '<i class="fas fa-check-double"></i> Marcar todas como leídas';
        markAllBtn.onclick = marcarTodasLeidas;
        notificationsView.appendChild(markAllBtn);

    } catch (err) {
        console.error(err);
        notificationsView.innerHTML = '<div class="notification error">Error al cargar notificaciones</div>';
    }
}

async function marcarTodasLeidas() {
    try {
        const resp = await fetch('../../controllers/notificacion_controlador.php?action=marcar_todas_leidas', {
            method: 'POST',
            credentials: 'include'
        });

        const result = await resp.json();
        if (result.success) {
            // Recargar notificaciones
            cargarNotificacionesSuave();
        }
    } catch (error) {
        console.error('Error marcando todas como leídas:', error);
    }
}

// FUNCIÓN PARA OBTENER ID DE USUARIO
async function obtenerUsuarioId() {
    if (window.usuarioId) {
        return window.usuarioId;
    }

    try {
        const resp = await fetch('../../controllers/usuario_controlador.php?action=obtener_id', {
            credentials: 'include'
        });
        const data = await resp.json();
        if (data.success && data.id_usuario) {
            window.usuarioId = data.id_usuario;
            return data.id_usuario;
        }
    } catch (error) {
        console.error('Error obteniendo ID de usuario:', error);
    }

    return 0;
}

window.toggleLike = async function(id_reporte, btn) {
    try {
        const id_usuario = await obtenerUsuarioId();
        const formData = new FormData();
        formData.append('id_reporte', id_reporte);
        formData.append('id_usuario', id_usuario);

        const resp = await fetch('../../controllers/reportecontrolador.php?action=toggle_like', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const r = await resp.json();

        if (r.success) {
            const likeCount = btn.querySelector('.like-count');
            let currentCount = parseInt(likeCount.textContent) || 0;

            if (r.action === 'liked') {
                btn.innerHTML = '<i class="fas fa-heart"></i> <span class="like-count">' + (currentCount + 1) + '</span>';
                btn.style.color = 'var(--danger)';
                likeCount.textContent = currentCount + 1;
            } else {
                btn.innerHTML = '<i class="far fa-heart"></i> <span class="like-count">' + (currentCount - 1) + '</span>';
                btn.style.color = '';
                likeCount.textContent = Math.max(0, currentCount - 1);
            }

            const postElement = btn.closest('.post');
            if (postElement) {
                await cargarLikesPost(id_reporte, postElement);
            }
        } else {
            alert('Error al procesar like');
        }
    } catch (err) {
        console.error(err);
        alert('Error al conectar con el servidor');
    }
}

function crearPostElement(reporte) {
    try {
        let avatarUrl = '/imagenes/default-avatar.png';

        if (reporte.foto_perfil &&
            reporte.foto_perfil !== '' &&
            reporte.foto_perfil !== 'null' &&
            reporte.foto_perfil !== null) {

            if (reporte.foto_perfil.startsWith('/')) {
                avatarUrl = window.location.origin + reporte.foto_perfil;
            } else if (reporte.foto_perfil.startsWith('http')) {
                avatarUrl = reporte.foto_perfil;
            } else {
                avatarUrl = window.location.origin + '/' + reporte.foto_perfil;
            }
        }

        const nombreUsuario = (reporte.nombres && reporte.apellidos)
            ? `${reporte.nombres} ${reporte.apellidos}`.trim()
            : (reporte.usuario ? reporte.usuario.split('@')[0] : 'Usuario');

        const timeText = tiempoRelativo(new Date(reporte.fecha_reporte));
        const descripcionCorta = reporte.descripcion && reporte.descripcion.length > 150 ?
            reporte.descripcion.substring(0, 150) + '...' : reporte.descripcion;

        const div = document.createElement('div');
        div.className = 'post';
        div.setAttribute('data-post-id', reporte.id_reporte);

        div.innerHTML = `
            <div class="post-header">
                <img src="${avatarUrl}" class="avatar" alt="${nombreUsuario}"
                    onerror="this.src='${window.location.origin}/imagenes/default-avatar.png'">
                <div class="user-info">
                    <div class="user-name">${escapeHtml(nombreUsuario)}</div>
                    <div class="post-meta">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Ubicación en mapa</span>
                        <i class="fas fa-clock"></i>
                        <span>${timeText}</span>
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="post-incident-type">${escapeHtml(reporte.tipo_incidente || 'Tipo no especificado')}</span>
                    </div>
                    <div class="post-status">
                        <span class="status-badge ${(reporte.estado || 'pendiente').toLowerCase().replace(' ', '-')}">${reporte.estado || 'pendiente'}</span>
                    </div>
                </div>
            </div>

            ${reporte.imagenes && reporte.imagenes.length > 0 ? `
                <div class="post-images">
                    ${crearEstructuraImagenesSimple(reporte.imagenes)}
                </div>
            ` : ''}

            <div class="post-desc">${escapeHtml(reporte.descripcion || 'Sin descripción')}</div>

            <div class="post-additional-info">
                <div class="info-item">
                    <i class="fas fa-road"></i>
                    <span class="street-info">Coordenadas: ${formatearCoordenada(reporte.latitud)}, ${formatearCoordenada(reporte.longitud)}</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-day"></i>
                    <span class="report-date">${formatearFecha(reporte.fecha_reporte)}</span>
                </div>
            </div>

            <div class="post-actions">
                <button class="btn-small like-btn" data-report-id="${reporte.id_reporte}">
                    <i class="far fa-heart"></i>
                    <span class="like-count">0</span>
                </button>
                <button class="btn-small comment-btn" data-report-id="${reporte.id_reporte}">
                    <i class="fas fa-comment"></i>
                    <span>Comentarios (0)</span>
                </button>
                <button class="btn-small view-map-btn">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Ver en Mapa</span>
                </button>
            </div>
        `;

        const likeBtn = div.querySelector('.like-btn');
        const commentBtn = div.querySelector('.comment-btn');
        const viewMapBtn = div.querySelector('.view-map-btn');
        const streetInfo = div.querySelector('.street-info');

        if (likeBtn) {
            likeBtn.addEventListener('click', () => toggleLike(reporte.id_reporte, likeBtn));
        }
        if (commentBtn) {
            commentBtn.addEventListener('click', () => {
                if (typeof ComentariosManager !== 'undefined' && ComentariosManager.abrirComentarios) {
                    ComentariosManager.abrirComentarios(reporte.id_reporte);
                } else {
                    alert('Función de comentarios no disponible');
                }
            });
        }
        if (viewMapBtn) viewMapBtn.addEventListener('click', () => navegarAlMapa(reporte));
        if (streetInfo) {
            streetInfo.style.cursor = 'pointer';
            streetInfo.title = 'Haz clic para ver en el mapa';
            streetInfo.addEventListener('click', () => navegarAlMapa(reporte));
        }

        setTimeout(() => {
            cargarLikesPost(reporte.id_reporte, div);
            cargarComentariosPost(reporte.id_reporte, div);
            verificarLikeUsuario(reporte.id_reporte, div);
        }, 100);

        return div;

    } catch (error) {
        console.error('Error creando post:', error);
        return null;
    }
}

async function cargarLikesPost(id_reporte, postElement) {
    try {
        const resp = await fetch(`../../controllers/reportecontrolador.php?action=contar_likes&id_reporte=${id_reporte}`, {
            credentials: 'include'
        });
        const data = await resp.json();

        const likeCount = postElement.querySelector('.like-count');
        if (likeCount && data.total_likes !== undefined) {
            likeCount.textContent = data.total_likes;
        }
    } catch (error) {
        console.error('Error cargando likes:', error);
    }
}

async function cargarComentariosPost(id_reporte, postElement) {
    try {
        const resp = await fetch(`../../controllers/reportecontrolador.php?action=contar_comentarios&id_reporte=${id_reporte}`, {
            credentials: 'include'
        });
        const data = await resp.json();

        const commentBtn = postElement.querySelector('.comment-btn');
        if (commentBtn && data.total_comentarios !== undefined) {
            const commentText = commentBtn.querySelector('span');
            if (commentText) {
                commentText.textContent = `Comentarios (${data.total_comentarios})`;
            }
        }
    } catch (error) {
        console.error('Error cargando comentarios:', error);
    }
}

async function verificarLikeUsuario(id_reporte, postElement) {
    try {
        const id_usuario = await obtenerUsuarioId();
        const formData = new FormData();
        formData.append('id_reporte', id_reporte);
        formData.append('id_usuario', id_usuario);

        const resp = await fetch('../../controllers/reportecontrolador.php?action=verificar_like', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const data = await resp.json();

        const likeBtn = postElement.querySelector('.like-btn');
        if (likeBtn && data.liked) {
            likeBtn.innerHTML = '<i class="fas fa-heart"></i> <span class="like-count">' + (likeBtn.querySelector('.like-count')?.textContent || '0') + '</span>';
            likeBtn.style.color = 'var(--danger)';
        }
    } catch (error) {
        console.error('Error verificando like:', error);
    }
}

function crearEstructuraImagenesSimple(imagenes) {
    if (!imagenes || imagenes.length === 0) return '';

    const imagenesSeguras = imagenes.map(img => {
        return img.replace(/'/g, "&#39;").replace(/"/g, "&#34;");
    });

    if (imagenesSeguras.length === 1) {
        return `<img src="${imagenesSeguras[0]}" alt="Imagen del reporte" class="post-image" onclick="ampliarImagen('${imagenesSeguras[0]}')">`;
    } else if (imagenesSeguras.length === 2) {
        return `
            <div class="images-grid two-images">
                ${imagenesSeguras.map(img =>
                    `<img src="${img}" alt="Imagen del reporte" class="post-image" onclick="ampliarImagen('${img}')">`
                ).join('')}
            </div>
        `;
    } else if (imagenesSeguras.length === 3) {
        return `
            <div class="images-grid three-images">
                ${imagenesSeguras.map(img =>
                    `<img src="${img}" alt="Imagen del reporte" class="post-image" onclick="ampliarImagen('${img}')">`
                ).join('')}
            </div>
        `;
    } else {
        const totalImagenes = imagenesSeguras.length;
        const imagenesMostradas = imagenesSeguras.slice(0, 4);
        const imagenesExtra = totalImagenes - 4;

        return `
            <div class="images-grid four-images ${imagenesExtra > 0 ? 'has-more-images' : ''}">
                ${imagenesMostradas.map(img =>
                    `<img src="${img}" alt="Imagen del reporte" class="post-image" onclick="ampliarImagen('${img}')">`
                ).join('')}
                ${imagenesExtra > 0 ? `
                    <div class="image-count-overlay">+${imagenesExtra}</div>
                ` : ''}
            </div>
        `;
    }
}

function navegarAlMapa(reporte) {
    const mapNavItem = document.querySelector('.nav-item[data-target="mapView"]');
    if (mapNavItem) {
        mapNavItem.click();

        setTimeout(() => {
            enviarCoordenadasAlMapa(reporte);
        }, 1000);
    }
}

function enviarCoordenadasAlMapa(reporte) {
    const mapIframe = document.querySelector('#mapView iframe');
    if (mapIframe && mapIframe.contentWindow) {
        try {
            const lat = typeof reporte.latitud === 'string' ? parseFloat(reporte.latitud) : reporte.latitud;
            const lng = typeof reporte.longitud === 'string' ? parseFloat(reporte.longitud) : reporte.longitud;

            console.log('📍 Enviando al mapa:', { reportId: reporte.id_reporte, lat, lng });

            const message = {
                type: 'SHOW_REPORT',
                coordinates: {
                    lat: lat,
                    lng: lng
                },
                reportId: reporte.id_reporte,
                reportData: {
                    tipo_incidente: reporte.tipo_incidente,
                    descripcion: reporte.descripcion,
                    estado: reporte.estado,
                    usuario: reporte.usuario,
                    fecha_reporte: reporte.fecha_reporte
                }
            };

            mapIframe.contentWindow.postMessage(message, '*');

        } catch (error) {
            console.error('Error enviando coordenadas al mapa:', error);
        }
    }
}

function abrirMapaCompleto() {
    const url = window.mapUrl || '<?php echo $mapUrl; ?>';
    const ventanaMapa = window.open(url, 'MapaOjoEnLaVia',
        'width=1200,height=800,scrollbars=yes,resizable=yes');

    if (ventanaMapa) {
        ventanaMapa.focus();
    } else {
        alert('Por favor permite las ventanas emergentes para esta función');
    }
}

function tiempoRelativo(date) {
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return diff + 's';
    if (diff < 3600) return Math.floor(diff/60) + 'm';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
    });
}

function formatearCoordenada(coord) {
    if (coord === null || coord === undefined) {
        return 'No disponible';
    }

    const num = typeof coord === 'string' ? parseFloat(coord) : coord;

    if (isNaN(num)) {
        return 'Inválida';
    }

    return num.toFixed(6);
}

function formatearFecha(fechaString) {
    try {
        const fecha = new Date(fechaString);
        return fecha.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch (e) {
        return 'Fecha no disponible';
    }
}

window.marcarLeida = async function(id, btn) {
    try {
        const form = new FormData();
        form.append('id_notificacion', id);
        const resp = await fetch('../../controllers/notificacion_controlador.php?action=marcar_leida', {
            method: 'POST',
            body: form,
            credentials: 'include'
        });
        const r = await resp.json();
        if (r.success) {
            btn.textContent = 'Leída';
            btn.disabled = true;
        }
    } catch (e) {
        console.error(e);
        alert('Error al marcar como leída');
    }
}

window.verNotificacion = function(id_notificacion, id_reporte) {
    if (id_reporte && id_reporte !== 'null') {
        if (typeof ComentariosManager !== 'undefined' && ComentariosManager.abrirComentarios) {
            ComentariosManager.abrirComentarios(id_reporte);
        } else {
            alert('Función de comentarios no disponible');
        }
    } else {
        alert('No hay información de reporte asociada');
    }
}

window.compartirReporte = function(reporte) {
    const texto = `Reporte de ${reporte.tipo_incidente}: ${reporte.descripcion}`;

    if (navigator.share) {
        navigator.share({
            title: 'Ojo en la Vía - Reporte',
            text: texto,
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(texto).then(() => {
            alert('Reporte copiado al portapapeles');
        });
    }
}

window.ampliarImagen = function(src) {
    const modal = document.createElement('div');
    modal.className = 'image-modal';
    modal.innerHTML = `
        <img src="${src}" alt="Imagen ampliada">
        <button class="image-modal-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    modal.onclick = (e) => {
        if (e.target === modal) modal.remove();
    };
    document.body.appendChild(modal);
}

window.navegarAlMapa = navegarAlMapa;

// Función para cerrar sesión (solo cuando el usuario lo solicita)
function cerrarSesion() {
    if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
        window.location.href = '../../logout.php';
    }
}

// Mejorar la experiencia en móviles
document.addEventListener('touchstart', function() {}, { passive: true });