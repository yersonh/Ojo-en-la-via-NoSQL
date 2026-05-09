document.addEventListener('DOMContentLoaded', function () {
    inicializarAccionesReportesAlertas();
});

let editarReporteMapa = null;
let editarReporteMarcador = null;

function inicializarAccionesReportesAlertas() {
    const overlay = document.getElementById('editarReporteOverlay');
    const form = document.getElementById('editarReporteForm');
    const cerrar = document.getElementById('cerrarEditarReporte');
    const cancelar = document.getElementById('cancelarEditarReporte');
    const btnUbicacionActual = document.getElementById('usarUbicacionActualEditar');

    if (!overlay || !form) {
        return;
    }

    document.addEventListener('click', async function (event) {
        const botonMenu = event.target.closest('.btn-reporte-menu');
        const botonEditar = event.target.closest('.btn-alerta-editar-reporte');
        const botonEliminar = event.target.closest('.btn-alerta-eliminar-reporte');

        if (!event.target.closest('.reporte-menu')) {
            cerrarMenusReporte();
        }

        if (botonMenu) {
            const menu = botonMenu.closest('.reporte-menu');
            const estabaAbierto = menu.classList.contains('abierto');
            cerrarMenusReporte();
            menu.classList.toggle('abierto', !estabaAbierto);
            return;
        }

        if (botonEditar) {
            const tarjeta = botonEditar.closest('.alerta-card-red');
            cerrarMenusReporte();
            abrirModalEditarReporte(tarjeta);
            return;
        }

        if (botonEliminar) {
            const tarjeta = botonEliminar.closest('.alerta-card-red');
            cerrarMenusReporte();
            await eliminarReporteAlerta(tarjeta);
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        await guardarReporteEditado(form);
    });

    [cerrar, cancelar].forEach(function (boton) {
        if (boton) {
            boton.addEventListener('click', cerrarModalEditarReporte);
        }
    });

    if (btnUbicacionActual) {
        btnUbicacionActual.addEventListener('click', usarUbicacionActualEnEdicion);
    }

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            cerrarModalEditarReporte();
        }
    });
}

function usarUbicacionActualEnEdicion() {
    const boton = document.getElementById('usarUbicacionActualEditar');

    if (!navigator.geolocation) {
        alert('Tu navegador no permite obtener la ubicacion actual.');
        return;
    }

    const textoOriginal = boton ? boton.textContent : '';

    if (boton) {
        boton.disabled = true;
        boton.textContent = 'Buscando ubicacion...';
    }

    navigator.geolocation.getCurrentPosition(
        function (posicion) {
            const lat = posicion.coords.latitude;
            const lng = posicion.coords.longitude;

            moverMarcadorEdicion(lat, lng);

            if (editarReporteMapa) {
                editarReporteMapa.setView([lat, lng], 17);
                editarReporteMapa.invalidateSize();
            }

            if (boton) {
                boton.disabled = false;
                boton.textContent = textoOriginal;
            }
        },
        function () {
            alert('No se pudo obtener tu ubicacion. Revisa los permisos del navegador.');

            if (boton) {
                boton.disabled = false;
                boton.textContent = textoOriginal;
            }
        },
        {
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 0
        }
    );
}

function cerrarMenusReporte() {
    document.querySelectorAll('.reporte-menu.abierto').forEach(function (menu) {
        menu.classList.remove('abierto');
    });
}

function abrirModalEditarReporte(tarjeta) {
    if (!tarjeta) {
        return;
    }

    const tipoSelect = document.getElementById('editarReporteTipo');
    const tipoActual = tarjeta.dataset.tipo || '';

    document.getElementById('editarReporteId').value = tarjeta.dataset.reporteId || '';
    tipoSelect.value = tipoActual;

    if (tipoActual && tipoSelect.value !== tipoActual) {
        const opcionActual = document.createElement('option');
        opcionActual.value = tipoActual;
        opcionActual.textContent = tipoActual;
        tipoSelect.appendChild(opcionActual);
        tipoSelect.value = tipoActual;
    }

    document.getElementById('editarReporteDescripcion').value = tarjeta.dataset.descripcion || '';
    actualizarUbicacionEdicion(tarjeta.dataset.latitud || '', tarjeta.dataset.longitud || '');
    prepararPreviewFoto(tarjeta.dataset.imagen || '');

    const overlay = document.getElementById('editarReporteOverlay');
    overlay.classList.add('activo');
    overlay.setAttribute('aria-hidden', 'false');

    setTimeout(function () {
        inicializarMapaEdicion(tarjeta.dataset.latitud, tarjeta.dataset.longitud);
    }, 80);
}

function cerrarModalEditarReporte() {
    const overlay = document.getElementById('editarReporteOverlay');

    if (!overlay) {
        return;
    }

    overlay.classList.remove('activo');
    overlay.setAttribute('aria-hidden', 'true');
}

function inicializarMapaEdicion(latitudInicial, longitudInicial) {
    if (typeof L === 'undefined') {
        return;
    }

    const lat = parseFloat(latitudInicial);
    const lng = parseFloat(longitudInicial);
    const coordenadas = [
        Number.isFinite(lat) ? lat : 4.142,
        Number.isFinite(lng) ? lng : -73.626
    ];

    if (!editarReporteMapa) {
        editarReporteMapa = L.map('editarReporteMapa', {
            zoomControl: true
        }).setView(coordenadas, 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(editarReporteMapa);

        editarReporteMarcador = L.marker(coordenadas, {
            draggable: true
        }).addTo(editarReporteMapa);

        editarReporteMapa.on('click', function (event) {
            moverMarcadorEdicion(event.latlng.lat, event.latlng.lng);
        });

        editarReporteMarcador.on('dragend', function () {
            const posicion = editarReporteMarcador.getLatLng();
            actualizarUbicacionEdicion(posicion.lat, posicion.lng);
        });
    } else {
        editarReporteMapa.setView(coordenadas, 15);
        editarReporteMarcador.setLatLng(coordenadas);
    }

    editarReporteMapa.invalidateSize();
}

function moverMarcadorEdicion(latitud, longitud) {
    if (editarReporteMarcador) {
        editarReporteMarcador.setLatLng([latitud, longitud]);
    }

    actualizarUbicacionEdicion(latitud, longitud);
}

function actualizarUbicacionEdicion(latitud, longitud) {
    const lat = parseFloat(latitud);
    const lng = parseFloat(longitud);
    const latTexto = Number.isFinite(lat) ? lat.toFixed(12).replace(/0+$/, '').replace(/\.$/, '') : '';
    const lngTexto = Number.isFinite(lng) ? lng.toFixed(12).replace(/0+$/, '').replace(/\.$/, '') : '';

    document.getElementById('editarReporteLatitud').value = latTexto;
    document.getElementById('editarReporteLongitud').value = lngTexto;
    document.getElementById('editarReporteLatitudTexto').textContent = latTexto || '-';
    document.getElementById('editarReporteLongitudTexto').textContent = lngTexto || '-';
}

function prepararPreviewFoto(imagen) {
    const preview = document.getElementById('editarReporteFotoPreview');
    const input = document.getElementById('editarReporteFoto');

    if (input) {
        input.value = '';
        input.onchange = function () {
            const archivo = input.files && input.files[0];

            if (!archivo) {
                preview.src = imagen ? `/${imagen.replace(/^\/+/, '')}` : '';
                return;
            }

            preview.src = URL.createObjectURL(archivo);
        };
    }

    if (preview) {
        preview.src = imagen ? `/${imagen.replace(/^\/+/, '')}` : '';
    }
}

async function guardarReporteEditado(form) {
    const botonGuardar = form.querySelector('.btn-guardar-editar');
    const textoOriginal = botonGuardar ? botonGuardar.textContent : '';

    if (botonGuardar) {
        botonGuardar.disabled = true;
        botonGuardar.textContent = 'Guardando...';
    }

    try {
        const formData = await construirFormDataReporte(form);

        const respuesta = await fetch('/views/usuario/alertas/api/editar_reporte.php', {
            method: 'POST',
            body: formData
        });

        const data = await respuesta.json();

        if (!data.ok) {
            alert(data.mensaje || 'No se pudo editar el reporte.');
            return;
        }

        actualizarTarjetaReporte(data.reporte);
        cerrarModalEditarReporte();
    } catch (error) {
        console.error('Error al editar reporte:', error);
        alert(error.message || 'No se pudo editar el reporte.');
    } finally {
        if (botonGuardar) {
            botonGuardar.disabled = false;
            botonGuardar.textContent = textoOriginal;
        }
    }
}

async function construirFormDataReporte(form) {
    const formData = new FormData(form);
    const inputFoto = document.getElementById('editarReporteFoto');
    const archivo = inputFoto?.files?.[0];

    if (!archivo) {
        formData.delete('foto');
        return formData;
    }

    if (!archivo.type.startsWith('image/')) {
        throw new Error('La foto debe ser una imagen.');
    }

    const imagenComprimida = await comprimirImagen(archivo);
    formData.set('foto', imagenComprimida, imagenComprimida.name);

    return formData;
}

function comprimirImagen(archivo) {
    return new Promise((resolve) => {
        const img = new Image();
        const url = URL.createObjectURL(archivo);

        img.onload = function () {
            URL.revokeObjectURL(url);

            const maxDimension = 1400;
            const escala = Math.min(1, maxDimension / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');

            canvas.width = Math.max(1, Math.round(img.width * escala));
            canvas.height = Math.max(1, Math.round(img.height * escala));

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) {
                    resolve(archivo);
                    return;
                }

                const nombre = archivo.name.replace(/\.[^.]+$/, '') || 'reporte';
                resolve(new File([blob], `${nombre}.jpg`, {
                    type: 'image/jpeg',
                    lastModified: Date.now()
                }));
            }, 'image/jpeg', 0.82);
        };

        img.onerror = function () {
            URL.revokeObjectURL(url);
            resolve(archivo);
        };

        img.src = url;
    });
}

function actualizarTarjetaReporte(reporte) {
    const tarjeta = document.querySelector(`.alerta-card-red[data-reporte-id="${reporte.id}"]`);

    if (!tarjeta) {
        return;
    }

    tarjeta.dataset.tipo = reporte.tipo;
    tarjeta.dataset.descripcion = reporte.descripcion;
    tarjeta.dataset.latitud = reporte.latitud;
    tarjeta.dataset.longitud = reporte.longitud;
    tarjeta.dataset.imagen = reporte.imagen || tarjeta.dataset.imagen || '';

    const tipo = tarjeta.querySelector('.alerta-tipo-texto');
    const descripcion = tarjeta.querySelector('.alerta-descripcion');
    const latitud = tarjeta.querySelector('.alerta-latitud');
    const longitud = tarjeta.querySelector('.alerta-longitud');
    const linkMapa = tarjeta.querySelector('.alerta-link-mapa');
    let imagen = tarjeta.querySelector('.alerta-foto-reporte');

    if (tipo) {
        tipo.textContent = reporte.tipo;
    }

    if (descripcion) {
        descripcion.textContent = reporte.descripcion || 'Sin descripcion';
    }

    if (latitud) {
        latitud.textContent = reporte.latitud;
    }

    if (longitud) {
        longitud.textContent = reporte.longitud;
    }

    if (linkMapa) {
        linkMapa.href = `inicio.php?reporte=${encodeURIComponent(reporte.id)}&lat=${encodeURIComponent(reporte.latitud)}&lng=${encodeURIComponent(reporte.longitud)}`;
    }

    if (reporte.imagen) {
        if (!imagen) {
            imagen = document.createElement('img');
            imagen.className = 'alerta-foto-reporte';
            imagen.alt = 'Foto del reporte';
            descripcion.insertAdjacentElement('afterend', imagen);
        }

        imagen.src = `/${String(reporte.imagen).replace(/^\/+/, '')}`;
    }
}

async function eliminarReporteAlerta(tarjeta) {
    const reporteId = tarjeta?.dataset.reporteId;

    if (!reporteId) {
        alert('No se encontro el reporte.');
        return;
    }

    if (!confirm('Seguro que quieres eliminar este reporte?')) {
        return;
    }

    const formData = new FormData();
    formData.append('reporte_id', reporteId);

    try {
        const respuesta = await fetch('/views/usuario/alertas/api/eliminar_reporte.php', {
            method: 'POST',
            body: formData
        });

        const data = await respuesta.json();

        if (!data.ok) {
            alert(data.mensaje || 'No se pudo eliminar el reporte.');
            return;
        }

        tarjeta.remove();
    } catch (error) {
        console.error('Error al eliminar reporte:', error);
        alert('No se pudo eliminar el reporte.');
    }
}
