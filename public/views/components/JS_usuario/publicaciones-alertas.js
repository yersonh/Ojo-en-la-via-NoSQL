document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-like-reporte').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const reporteId = btn.dataset.reporteId;

            const formData = new FormData();
            formData.append('reporte_id', reporteId);

            try {
                const respuesta = await fetch('/views/components/usuario/like_reporte.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await respuesta.json();

                if (!data.ok) {
                    alert(data.mensaje || 'No se pudo procesar el like.');
                    return;
                }

                btn.classList.toggle('liked', data.liked);
                btn.querySelector('.like-count').textContent = data.likes;

            } catch (error) {
                alert('Error al conectar con el servidor.');
            }
        });
    });
});