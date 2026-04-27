document.addEventListener('DOMContentLoaded', () => {
    const botonesLike = document.querySelectorAll('.btn-like-reporte');

    botonesLike.forEach((btn) => {
        btn.addEventListener('click', async () => {
            const reporteId = btn.dataset.reporteId;

            const formData = new FormData();
            formData.append('reporte_id', reporteId);

            try {
                const respuesta = await fetch('/views/components/usuario/like_reporte.php', {
                    method: 'POST',
                    body: formData
                });

                const texto = await respuesta.text();
                console.log('Respuesta del servidor:', texto);

                const data = JSON.parse(texto);

                if (!data.ok) {
                    alert(data.mensaje || 'No se pudo procesar el like.');
                    return;
                }

                btn.classList.toggle('liked', data.liked);

                const contador = btn.querySelector('.like-count');

                if (contador) {
                    contador.textContent = data.likes;
                }

            } catch (error) {
                console.error('Error real del like:', error);
                alert('Error al conectar con el servidor.');
            }
        });
    });
});