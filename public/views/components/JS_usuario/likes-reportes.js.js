
console.log('likes-reportes.js cargado');

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like-reporte');

    if (!btn) {
        return;
    }

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
        const respuesta = await fetch('/views/components/usuario/like_reporte.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const texto = await respuesta.text();

        console.log('Respuesta del PHP:', texto);

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
        console.error('Error real:', error);
        alert('Error al conectar con el servidor.');
    }
});