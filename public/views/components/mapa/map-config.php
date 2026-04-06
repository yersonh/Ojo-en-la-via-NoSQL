<script>
    const MAP_CONFIG = {
        center: [4.142, -73.6266],
        zoom: 13,
        tileUrl: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '&copy; OpenStreetMap contributors'
    };

    function crearMapa(idContenedor) {
        const map = L.map(idContenedor).setView(MAP_CONFIG.center, MAP_CONFIG.zoom);

        L.tileLayer(MAP_CONFIG.tileUrl, {
            attribution: MAP_CONFIG.attribution
        }).addTo(map);

        return map;
    }
</script>