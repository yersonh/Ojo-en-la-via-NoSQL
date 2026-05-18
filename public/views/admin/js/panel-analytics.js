/* ════════════════════════════════════════════════════════
   ANALÍTICAS
   Depende de: ANALYTICS_RAW (data inline), tooltipBase, PALETTE (panel-ui.js)
════════════════════════════════════════════════════════ */
let chartTendenciaInst = null;
let chartZonaInst      = null;

function analyticsFiltered() {
    const tipo = document.getElementById('analytics-tipo')?.value || '';
    const data = tipo ? ANALYTICS_RAW.filter(r => r.tipo === tipo) : ANALYTICS_RAW;
    const el   = document.getElementById('analytics-count');
    if (el) el.textContent = data.length + ' reporte' + (data.length !== 1 ? 's' : '');
    return data;
}

function renderTendencia(data) {
    const days = [];
    for (let i = 6; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        days.push({
            key:   d.toISOString().split('T')[0],
            label: d.toLocaleDateString('es-CO', { weekday: 'short', day: 'numeric' })
        });
    }
    const counts = days.map(d => data.filter(r => r.fecha === d.key).length);

    if (chartTendenciaInst) chartTendenciaInst.destroy();

    const ctx  = document.getElementById('chartTendencia').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 210);
    grad.addColorStop(0, 'rgba(16,185,129,0.35)');
    grad.addColorStop(1, 'rgba(16,185,129,0.0)');

    chartTendenciaInst = new Chart(ctx, {
        type: 'line',
        data: {
            labels: days.map(d => d.label),
            datasets: [{
                data: counts,
                borderColor: '#10b981',
                backgroundColor: grad,
                borderWidth: 2.5,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8,
                fill: true,
                tension: 0.4,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 700, easing: 'easeInOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipBase,
                    callbacks: { label: i => ` ${i.parsed.y} reporte${i.parsed.y !== 1 ? 's' : ''}` }
                }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { precision: 0, color: '#64748b', font: { size: 11 } } }
            }
        }
    });
}

function renderTasa(data) {
    const total  = data.length;
    const est    = { pendiente: 0, en_revision: 0, notificado: 0, resuelto: 0 };
    data.forEach(r => { if (est[r.estado] !== undefined) est[r.estado]++; });
    const pct = total ? Math.round(est.resuelto / total * 100) : 0;

    const items = [
        { label: 'Resuelto',    count: est.resuelto,    color: '#10b981' },
        { label: 'Notificado',  count: est.notificado,  color: '#8b5cf6' },
        { label: 'En revisión', count: est.en_revision, color: '#0ea5e9' },
        { label: 'Pendiente',   count: est.pendiente,   color: '#f59e0b' },
    ];

    document.getElementById('tasa-container').innerHTML = `
        <div style="text-align:center;margin-bottom:18px;">
            <div style="font-size:3.2rem;font-weight:700;color:#10b981;line-height:1;">${pct}%</div>
            <div style="font-size:.8rem;color:#94a3b8;margin-top:4px;">tasa de resolución</div>
            <div style="font-size:.78rem;color:#64748b;margin-top:2px;">${total} reportes totales</div>
        </div>
        ${items.map(it => {
            const p = total ? (it.count / total * 100).toFixed(1) : 0;
            return `<div style="margin-bottom:11px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:.81rem;color:#475569;">${it.label}</span>
                    <span style="font-size:.81rem;font-weight:600;color:${it.color};">${it.count} <span style="color:#94a3b8;font-weight:400;">(${p}%)</span></span>
                </div>
                <div style="background:#f1f5f9;border-radius:4px;height:6px;overflow:hidden;">
                    <div style="width:${p}%;height:100%;background:${it.color};border-radius:4px;transition:width .8s ease;"></div>
                </div>
            </div>`;
        }).join('')}`;
}

function renderHeatmap(data) {
    const dias   = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const bloqs  = ['0–3','4–7','8–11','12–15','16–19','20–23'];

    const grid = Array.from({ length: 7 }, () => Array(6).fill(0));
    data.forEach(r => {
        const d = (r.dia - 1 + 7) % 7;
        const b = Math.min(5, Math.floor(r.hora / 4));
        grid[d][b]++;
    });
    const maxVal = Math.max(1, ...grid.flat());

    let html = `<table style="border-collapse:separate;border-spacing:5px;width:100%;">
        <thead><tr>
            <th style="width:36px;"></th>
            ${bloqs.map(b => `<th style="text-align:center;font-size:.72rem;color:#94a3b8;font-weight:500;padding-bottom:4px;">${b}h</th>`).join('')}
        </tr></thead><tbody>`;

    dias.forEach((dia, di) => {
        html += `<tr><td style="font-size:.78rem;color:#64748b;font-weight:500;padding-right:6px;white-space:nowrap;">${dia}</td>`;
        bloqs.forEach((_, bi) => {
            const val = grid[di][bi];
            const alpha = (0.07 + (val / maxVal) * 0.88).toFixed(2);
            const light = val / maxVal < 0.5;
            html += `<td title="${dia} ${bloqs[bi]}h — ${val} reporte${val !== 1 ? 's' : ''}"
                style="background:rgba(79,110,247,${alpha});border-radius:7px;padding:11px 6px;
                       text-align:center;font-size:.73rem;font-weight:600;
                       color:${light ? '#475569' : '#fff'};cursor:default;
                       transition:transform .12s,box-shadow .12s;"
                onmouseenter="this.style.transform='scale(1.18)';this.style.boxShadow='0 4px 12px rgba(79,110,247,.35)'"
                onmouseleave="this.style.transform='';this.style.boxShadow=''">${val || ''}</td>`;
        });
        html += `</tr>`;
    });

    html += `</tbody></table>`;
    document.getElementById('heatmap-container').innerHTML = html;
}

function renderZona(data) {
    const topN   = parseInt(document.getElementById('zona-topn')?.value || '10');
    const counts = {};
    data.forEach(r => { if (r.barrio) counts[r.barrio] = (counts[r.barrio] || 0) + 1; });
    const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, topN);

    const wrap = document.getElementById('zona-wrap');

    if (!sorted.length) {
        wrap.innerHTML = `<div class="empty-state" style="margin-top:40px;">
            <i class="fas fa-map-pin"></i>
            <p>Sin datos de zona. Los reportes necesitan dirección capturada para mostrar esta gráfica.</p>
        </div>`;
        return;
    }

    wrap.innerHTML = '<canvas id="chartZona"></canvas>';
    wrap.style.height = Math.max(220, sorted.length * 38) + 'px';

    if (chartZonaInst) { chartZonaInst.destroy(); chartZonaInst = null; }

    chartZonaInst = new Chart(document.getElementById('chartZona'), {
        type: 'bar',
        data: {
            labels: sorted.map(([z]) => z.length > 28 ? z.slice(0, 26) + '…' : z),
            datasets: [{
                data: sorted.map(([, c]) => c),
                backgroundColor: 'rgba(139,92,246,0.72)',
                hoverBackgroundColor: 'rgba(139,92,246,1)',
                borderRadius: 7,
                borderWidth: 0,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 700, easing: 'easeInOutQuart', delay: ctx => ctx.dataIndex * 40 },
            plugins: {
                legend: { display: false },
                tooltip: { ...tooltipBase, callbacks: { label: i => ` ${i.parsed.x} reporte${i.parsed.x !== 1 ? 's' : ''}` } }
            },
            scales: {
                x: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { precision: 0, color: '#64748b', font: { size: 11 } } },
                y: { grid: { display: false }, border: { display: false }, ticks: { color: '#475569', font: { size: 11 } } }
            }
        }
    });
}

function renderAnalytics() {
    const data = analyticsFiltered();
    renderTendencia(data);
    renderTasa(data);
    renderHeatmap(data);
    renderZona(data);
}
