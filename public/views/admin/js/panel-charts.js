/* ════════════════════════════════════════════════════════
   CHARTS DEL DASHBOARD (Chart.js)
   Depende de: PALETTE, tooltipBase (panel-ui.js)
               CHART_TIPOS, CHART_MESES, ESTADOS_DATA (data inline)
════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {

    // ── Doughnut: tipos de incidente ──────────────────────────
    new Chart(document.getElementById('chartTipos'), {
        type: 'doughnut',
        data: {
            labels: CHART_TIPOS.map(t => t.tipo),
            datasets: [{
                data: CHART_TIPOS.map(t => t.total),
                backgroundColor: PALETTE,
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverOffset: 12,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            animation: { duration: 900, easing: 'easeInOutQuart', animateScale: true },
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: { size: 11, family: 'system-ui' },
                        boxWidth: 12, boxHeight: 12,
                        padding: 14, color: '#475569',
                    }
                },
                tooltip: {
                    ...tooltipBase,
                    callbacks: {
                        label(ctx) {
                            const sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = sum ? ((ctx.parsed / sum) * 100).toFixed(1) : 0;
                            return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });

    // ── Bar: reportes por mes ─────────────────────────────────
    const ctxMeses = document.getElementById('chartMeses').getContext('2d');
    const grad = ctxMeses.createLinearGradient(0, 0, 0, 260);
    grad.addColorStop(0, 'rgba(79,110,247,0.85)');
    grad.addColorStop(1, 'rgba(79,110,247,0.08)');

    new Chart(ctxMeses, {
        type: 'bar',
        data: {
            labels: CHART_MESES.map(m => m.mes),
            datasets: [{
                label: 'Reportes',
                data: CHART_MESES.map(m => m.total),
                backgroundColor: grad,
                borderColor: '#4f6ef7',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: 'rgba(79,110,247,0.95)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 900,
                easing: 'easeInOutQuart',
                delay: ctx => ctx.dataIndex * 60,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipBase,
                    callbacks: {
                        label: item => ` ${item.parsed.y} reporte${item.parsed.y !== 1 ? 's' : ''}`,
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: '#64748b', font: { size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { precision: 0, color: '#64748b', font: { size: 11 } },
                }
            }
        }
    });

    // ── Horizontal bar: estados ───────────────────────────────
    new Chart(document.getElementById('chartEstados'), {
        type: 'bar',
        data: {
            labels: ['Pendiente', 'En Revisión', 'Notificado', 'Resuelto'],
            datasets: [{
                label: 'Cantidad',
                data: [ESTADOS_DATA.pendiente, ESTADOS_DATA.en_revision, ESTADOS_DATA.notificado, ESTADOS_DATA.resuelto],
                backgroundColor: [
                    'rgba(245,158,11,0.75)',
                    'rgba(14,165,233,0.75)',
                    'rgba(139,92,246,0.75)',
                    'rgba(16,185,129,0.75)',
                ],
                hoverBackgroundColor: [
                    'rgba(245,158,11,1)',
                    'rgba(14,165,233,1)',
                    'rgba(139,92,246,1)',
                    'rgba(16,185,129,1)',
                ],
                borderRadius: 8,
                borderWidth: 0,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 900,
                easing: 'easeInOutQuart',
                delay: ctx => ctx.dataIndex * 80,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipBase,
                    callbacks: {
                        label: item => ` ${item.parsed.x} reporte${item.parsed.x !== 1 ? 's' : ''}`,
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { precision: 0, color: '#64748b', font: { size: 11 } },
                },
                y: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: '#475569', font: { size: 12, weight: '500' } },
                }
            }
        }
    });
});
