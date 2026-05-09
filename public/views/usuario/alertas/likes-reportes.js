console.log('likes-reportes.js cargado');

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like-reporte');

    if (!btn) {
        return;
    }

    e.preventDefault();

    console.log('Botón like presionado');

    const reporteId = btn.dataset.reporteId;

    console.log('ID del reporte:', reporteId);

    if (!reporteId) {
        alert('No se encontró el ID del reporte.');
        return;
    }

    const formData = new FormData();
    formData.append('reporte_id', reporteId);

    try {
        const respuesta = await fetch('/views/usuario/alertas/api/like_reporte.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const texto = await respuesta.text();

        console.log('Status HTTP:', respuesta.status);
        console.log('Respuesta completa del PHP:', texto);

        let data;

        try {
            data = JSON.parse(texto);
        } catch (errorJson) {
            console.error('El PHP no devolvió JSON válido.');
            console.error('Respuesta recibida:', texto);
            alert('El servidor devolvió una respuesta inválida. Revisa la consola.');
            return;
        }

        if (!respuesta.ok) {
            alert(data.mensaje || 'Error del servidor.');
            return;
        }

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
        console.error('Error real de conexión:', error);
        alert('Error al conectar con el servidor.');
    }
});
