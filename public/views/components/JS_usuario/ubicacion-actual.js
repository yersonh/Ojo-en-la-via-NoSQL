
            const btnMiUbicacion = document.getElementById('btnMiUbicacion');
            const latitudTexto = document.getElementById('latitud');
            const longitudTexto = document.getElementById('longitud');
            const latitudInput = document.getElementById('latitudInput');
            const longitudInput = document.getElementById('longitudInput');
            const infoUbicacion = document.getElementById('infoUbicacion');

            let marcadorMiUbicacion = null;
            let circuloPrecision = null;

            function guardarUbicacionSeleccionada(lat, lng) {
                latitudTexto.textContent = lat.toFixed(6);
                longitudTexto.textContent = lng.toFixed(6);

                latitudInput.value = lat;
                longitudInput.value = lng;

                infoUbicacion.classList.remove('oculto');
            }

            if (btnMiUbicacion) {
                btnMiUbicacion.addEventListener('click', () => {
                    if (!navigator.geolocation) {
                        alert('Tu navegador no permite obtener la ubicación.');
                        return;
                    }

                    btnMiUbicacion.disabled = true;
                    btnMiUbicacion.textContent = 'Ubicando...';

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            const precision = position.coords.accuracy;

                            guardarUbicacionSeleccionada(lat, lng);

                            if (marcadorMiUbicacion) {
                                map.removeLayer(marcadorMiUbicacion);
                            }

                            if (circuloPrecision) {
                                map.removeLayer(circuloPrecision);
                            }

                            marcadorMiUbicacion = L.marker([lat, lng]).addTo(map)
                                .bindPopup('Estás aquí')
                                .openPopup();

                            circuloPrecision = L.circle([lat, lng], {
                                radius: precision
                            }).addTo(map);

                            map.setView([lat, lng], 17);

                            btnMiUbicacion.disabled = false;
                            btnMiUbicacion.textContent = '📍 Mi ubicación';
                        },
                        (error) => {
                            btnMiUbicacion.disabled = false;
                            btnMiUbicacion.textContent = '📍 Mi ubicación';

                            if (error.code === error.PERMISSION_DENIED) {
                                alert('Debes permitir el acceso a tu ubicación.');
                            } else if (error.code === error.POSITION_UNAVAILABLE) {
                                alert('No se pudo obtener tu ubicación actual.');
                            } else if (error.code === error.TIMEOUT) {
                                alert('La búsqueda de ubicación tardó demasiado.');
                            } else {
                                alert('Ocurrió un error al obtener tu ubicación.');
                            }

                            console.error(error);
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );
                });
            }
        