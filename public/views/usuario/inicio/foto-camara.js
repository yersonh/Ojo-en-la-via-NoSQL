
        const inputFoto = document.getElementById('foto');
        const nombreArchivoTexto = document.getElementById('nombreArchivoTexto');
        const archivoInfo = document.getElementById('archivoInfo');
        const quitarArchivoBtn = document.getElementById('quitarArchivo');
        const abrirCamaraBtn = document.getElementById('abrirCamara');
        const tomarFotoBtn = document.getElementById('tomarFoto');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');

        let stream = null;

        function actualizarVistaArchivo() {
            if (inputFoto.files && inputFoto.files.length > 0) {
                nombreArchivoTexto.textContent = inputFoto.files[0].name;
                quitarArchivoBtn.style.display = 'flex';
                archivoInfo.classList.remove('vacio');
            } else {
                nombreArchivoTexto.textContent = 'Ningún archivo seleccionado';
                quitarArchivoBtn.style.display = 'none';
                archivoInfo.classList.add('vacio');
            }
        }

        function cerrarCamara() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }

            video.srcObject = null;
            video.style.display = 'none';
            tomarFotoBtn.style.display = 'none';
        }

        if (inputFoto) {
            inputFoto.addEventListener('change', actualizarVistaArchivo);
        }

        if (quitarArchivoBtn) {
            quitarArchivoBtn.addEventListener('click', () => {
                inputFoto.value = '';
                actualizarVistaArchivo();
                cerrarCamara();
            });
        }

        if (abrirCamaraBtn) {
            abrirCamaraBtn.addEventListener('click', async () => {
                try {
                    cerrarCamara();

                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false
                    });

                    video.srcObject = stream;
                    video.style.display = 'block';
                    tomarFotoBtn.style.display = 'flex';
                } catch (error) {
                    alert('No se pudo abrir la cámara.');
                    console.error(error);
                }
            });
        }

        if (tomarFotoBtn) {
            tomarFotoBtn.addEventListener('click', () => {
                if (!video.videoWidth || !video.videoHeight) {
                    alert('La cámara todavía no está lista.');
                    return;
                }

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                canvas.toBlob((blob) => {
                    if (!blob) {
                        alert('No se pudo capturar la foto.');
                        return;
                    }

                    const archivo = new File([blob], 'foto_camara.png', { type: 'image/png' });
                    const dt = new DataTransfer();
                    dt.items.add(archivo);
                    inputFoto.files = dt.files;

                    actualizarVistaArchivo();
                }, 'image/png');

                cerrarCamara();
            });
        }

        actualizarVistaArchivo();
  