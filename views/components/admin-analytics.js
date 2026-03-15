class AnalyticsManager {
    constructor() {
        this.charts = {};
        this.currentRange = 30;
        this.currentAgrupacion = 'weekly';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadAnalyticsData();
    }

    setupEventListeners() {
        document.getElementById('rangoTiempo').addEventListener('change', (e) => {
            this.currentRange = parseInt(e.target.value);
            this.loadAnalyticsData();
        });

        document.getElementById('tipoGrafica').addEventListener('change', (e) => {
            this.updateChartType('tipoChart', e.target.value);
        });

        document.getElementById('agrupacionTiempo').addEventListener('change', (e) => {
            this.currentAgrupacion = e.target.value;
            this.loadTimelineData();
        });

        document.getElementById('metricasUsuarios').addEventListener('change', (e) => {
            this.updateUsersChart(e.target.value);
        });
    }

    async loadAnalyticsData() {
        try {
            this.showLoadingState(true);

            // Cargar todos los datos en paralelo
            const [statsData, tiposData, estadosData, timelineData, usuariosData] = await Promise.all([
                this.fetchData('estadisticas_generales'),
                this.fetchData('reportes_por_tipo'),
                this.fetchData('distribucion_estado'),
                this.fetchData('evolucion_temporal', { agrupacion: this.currentAgrupacion }),
                this.fetchData('usuarios_activos')
            ]);

            this.updateStatsCards(statsData);
            this.createCharts({
                tipos: tiposData,
                estados: estadosData,
                timeline: timelineData,
                usuarios: usuariosData
            });
            this.updateDataTables({
                tipos: tiposData,
                usuarios: usuariosData
            });

        } catch (error) {
            console.error('Error cargando datos de analytics:', error);
            this.showError('Error al cargar los datos de analytics');
        } finally {
            this.showLoadingState(false);
        }
    }

    async fetchData(action, params = {}) {
        const urlParams = new URLSearchParams({
            action: action,
            dias: this.currentRange,
            ...params
        });

        const response = await fetch(`../../controllers/analyticscontrolador.php?${urlParams}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        return data;
    }

    updateStatsCards(data) {
        const stats = data.stats || {};
        const tendencias = data.tendencias || {};

        document.getElementById('totalReportes').textContent = stats.total_reportes?.toLocaleString() || '0';
        document.getElementById('reportesPendientes').textContent = stats.reportes_pendientes?.toLocaleString() || '0';
        document.getElementById('reportesProceso').textContent = stats.reportes_proceso?.toLocaleString() || '0';
        document.getElementById('reportesResueltos').textContent = stats.reportes_resueltos?.toLocaleString() || '0';

        // Actualizar tendencias
        this.updateTrend('totalReportes', tendencias.total_reportes);
    }

    updateTrend(statId, tendencia) {
        const statCard = document.getElementById(statId).closest('.stat-card');
        const trendElement = statCard.querySelector('.stat-trend');

        if (tendencia && tendencia.porcentaje > 0) {
            trendElement.className = `stat-trend ${tendencia.direccion}`;
            trendElement.innerHTML = `
                <i class="fas fa-arrow-${tendencia.direccion}"></i>
                <span>${tendencia.porcentaje}%</span>
            `;
        } else {
            trendElement.style.display = 'none';
        }
    }

    createCharts(data) {
        this.createTipoChart(data.tipos);
        this.createTimelineChart(data.timeline);
        this.createEstadoChart(data.estados);
        this.createUsersChart(data.usuarios);
    }

    createTipoChart(tiposData) {
        const ctx = document.getElementById('tipoChart').getContext('2d');

        if (this.charts.tipoChart) {
            this.charts.tipoChart.destroy();
        }

        const labels = tiposData.map(item => item.tipo);
        const values = tiposData.map(item => parseInt(item.cantidad));

        const colors = this.generateColors(labels.length);

        this.charts.tipoChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Reportes por Tipo',
                    data: values,
                    backgroundColor: colors,
                    borderColor: colors.map(color => this.adjustBrightness(color, -20)),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw} reportes`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    createTimelineChart(timelineData) {
        const ctx = document.getElementById('timelineChart').getContext('2d');

        if (this.charts.timelineChart) {
            this.charts.timelineChart.destroy();
        }

        const labels = timelineData.map(item => item.periodo);
        const totales = timelineData.map(item => parseInt(item.total));
        const resueltos = timelineData.map(item => parseInt(item.resueltos));

        this.charts.timelineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Reportes Totales',
                        data: totales,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Reportes Resueltos',
                        data: resueltos,
                        borderColor: '#4cc9f0',
                        backgroundColor: 'rgba(76, 201, 240, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    createEstadoChart(estadosData) {
        const ctx = document.getElementById('estadoChart').getContext('2d');

        if (this.charts.estadoChart) {
            this.charts.estadoChart.destroy();
        }

        const labels = estadosData.map(item => item.estado);
        const values = estadosData.map(item => parseInt(item.cantidad));
        const total = values.reduce((sum, value) => sum + value, 0);

        const colors = {
            'Pendiente': '#f72585',
            'En Proceso': '#4361ee',
            'Resuelto': '#4cc9f0',
            'Verificado': '#3a0ca3'
        };

        this.charts.estadoChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(label => colors[label] || '#6c757d'),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Actualizar leyenda
        this.updateEstadoLegend(labels, values, total, colors);
    }

    createUsersChart(usuariosData) {
        const ctx = document.getElementById('usuariosChart').getContext('2d');

        if (this.charts.usuariosChart) {
            this.charts.usuariosChart.destroy();
        }

        const labels = usuariosData.map(item => item.nombre || item.correo);
        const values = usuariosData.map(item => parseInt(item.total_reportes));

        this.charts.usuariosChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Reportes por Usuario',
                    data: values,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: '#4361ee',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    updateEstadoLegend(labels, values, total, colors) {
        const legendContainer = document.getElementById('estadoLegend');
        legendContainer.innerHTML = '';

        labels.forEach((label, index) => {
            const value = values[index];
            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;

            const legendItem = document.createElement('div');
            legendItem.className = 'legend-item';
            legendItem.innerHTML = `
                <span class="legend-color" style="background-color: ${colors[label]}"></span>
                <span>${label}: ${value} (${percentage}%)</span>
            `;

            legendContainer.appendChild(legendItem);
        });
    }

    updateDataTables(data) {
        this.updateTiposTable(data.tipos);
        this.updateUsuariosTable(data.usuarios);
    }

    updateTiposTable(tiposData) {
        const tbody = document.querySelector('#tablaTipos tbody');
        tbody.innerHTML = '';

        const total = tiposData.reduce((sum, item) => sum + parseInt(item.cantidad), 0);

        tiposData.forEach((item, index) => {
            const value = parseInt(item.cantidad);
            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.tipo}</td>
                <td>${value}</td>
                <td>${percentage}%</td>
                <td>
                    <span class="stat-trend up">
                        <i class="fas fa-chart-line"></i>
                        <span>--</span>
                    </span>
                </td>
            `;

            tbody.appendChild(row);
        });
    }

updateUsuariosTable(usuariosData) {
    const tbody = document.querySelector('#tablaUsuarios tbody');
    tbody.innerHTML = '';

    if (!usuariosData || usuariosData.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td colspan="4" style="text-align: center; padding: 20px; color: #666;">
                <i class="fas fa-users"></i> No hay usuarios con reportes en el período seleccionado
            </td>
        `;
        tbody.appendChild(row);
        return;
    }

    usuariosData.forEach(usuario => {
        const statusClass = usuario.estado === 'Activo' ? 'badge-resuelto' : 'badge-pendiente';

        // Formatear fecha de última actividad
        let fechaActividad = 'Nunca';
        if (usuario.ultima_actividad && usuario.ultima_actividad !== 'Nunca') {
            try {
                const fecha = new Date(usuario.ultima_actividad);
                if (!isNaN(fecha.getTime())) {
                    fechaActividad = fecha.toLocaleDateString();
                }
            } catch (e) {
                fechaActividad = usuario.ultima_actividad;
            }
        }

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${usuario.nombre || usuario.correo}</td>
            <td>${usuario.total_reportes || 0}</td>
            <td>${fechaActividad}</td>
            <td><span class="badge ${statusClass}">${usuario.estado}</span></td>
        `;

        tbody.appendChild(row);
    });
}

    async loadTimelineData() {
        try {
            const timelineData = await this.fetchData('evolucion_temporal', {
                agrupacion: this.currentAgrupacion
            });
            this.createTimelineChart(timelineData);
        } catch (error) {
            console.error('Error cargando datos de timeline:', error);
        }
    }

    // Métodos auxiliares (mantener los mismos del código anterior)
    generateColors(count) {
        const colors = [];
        const hueStep = 360 / count;

        for (let i = 0; i < count; i++) {
            const hue = i * hueStep;
            colors.push(`hsl(${hue}, 70%, 65%)`);
        }

        return colors;
    }

    adjustBrightness(color, percent) {
        const num = parseInt(color.slice(1), 16);
        const amt = Math.round(2.55 * percent);
        const R = (num >> 16) + amt;
        const G = (num >> 8 & 0x00FF) + amt;
        const B = (num & 0x0000FF) + amt;

        return `#${(0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1)}`;
    }

    showLoadingState(show) {
        const elements = document.querySelectorAll('.stat-card, .chart-container, .data-table-container');
        elements.forEach(el => {
            if (show) {
                el.classList.add('loading');
            } else {
                el.classList.remove('loading');
            }
        });
    }

    showError(message) {
        alert(message);
    }

    updateChartType(chartId, type) {
        if (this.charts[chartId]) {
            this.charts[chartId].config.type = type;
            this.charts[chartId].update();
        }
    }

    updateUsersChart(metric) {
        console.log('Actualizando gráfica de usuarios con métrica:', metric);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const analyticsTab = document.getElementById('analytics');
    if (analyticsTab && analyticsTab.classList.contains('active')) {
        window.analyticsManager = new AnalyticsManager();
    }
});

document.addEventListener('click', function(e) {
    if (e.target.matches('.sidebar-menu a[data-tab="analytics"]')) {
        setTimeout(() => {
            if (!window.analyticsManager) {
                window.analyticsManager = new AnalyticsManager();
            } else {
                window.analyticsManager.loadAnalyticsData();
            }
        }, 100);
    }
});