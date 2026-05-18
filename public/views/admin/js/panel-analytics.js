/* ════════════════════════════════════════════════════════
   ANALÍTICAS
   Depende de: ANALYTICS_RAW (data inline), tooltipBase, PALETTE (panel-ui.js)
════════════════════════════════════════════════════════ */
let chartTendenciaInst = null;
let chartZonaInst      = null;

/* ── Helpers de fecha (siempre en America/Bogota para coincidir con los datos) ── */
function isoDate(d) {
    return d.toLocaleDateString('en-CA', { timeZone: 'America/Bogota' });
}
function isoToday() {
    return isoDate(new Date());
}
function isoOffsetDays(n) {
    const d = new Date();
    d.setDate(d.getDate() - n + 1);
    return isoDate(d);
}

/* ── Estado del filtro activo (fuente de verdad única) ── */
const _filtro = { tipo: '', desde: '', hasta: '' };

function leerFiltro() {
    _filtro.tipo  = document.getElementById('analytics-tipo')?.value  || '';
    _filtro.desde = document.getElementById('analytics-fecha-desde')?.value || '';
    _filtro.hasta = document.getElementById('analytics-fecha-hasta')?.value || '';
}

function analyticsFiltered() {
    leerFiltro();
    let data = ANALYTICS_RAW;
    if (_filtro.tipo)  data = data.filter(r => r.tipo  === _filtro.tipo);
    if (_filtro.desde) data = data.filter(r => r.fecha >= _filtro.desde);
    if (_filtro.hasta) data = data.filter(r => r.fecha <= _filtro.hasta);
    return data;
}

/* ── Preset buttons ── */
function initPresets() {
    document.querySelectorAll('.analytics-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const days = parseInt(btn.dataset.days);
            const elDesde = document.getElementById('analytics-fecha-desde');
            const elHasta = document.getElementById('analytics-fecha-hasta');

            if (days === 0) {
                elDesde.value = '';
                elHasta.value = '';
            } else {
                elDesde.value = isoOffsetDays(days);
                elHasta.value = isoToday();
            }

            highlightPreset(btn);
            renderAnalytics();
        });
    });
}

function highlightPreset(active) {
    document.querySelectorAll('.analytics-preset').forEach(b => {
        b.style.background = '#f1f5f9';
        b.style.color      = '#475569';
    });
    if (active) {
        active.style.background = '#eaf3fb';
        active.style.color      = '#2471a3';
    }
}

function onFechaChange() {
    highlightPreset(null);
    renderAnalytics();
}

/* ── Badge de conteo en cada sección ── */
function updateBadges(n) {
    const txt = n + ' reporte' + (n !== 1 ? 's' : '');
    ['badge-tendencia', 'badge-tasa', 'badge-heatmap', 'badge-zona',
     'analytics-count'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = txt;
    });
}

/* ════════════════════════════════════════════════════════
   TENDENCIA
════════════════════════════════════════════════════════ */
function renderTendencia(data) {
    const { desde, hasta } = _filtro;

    const days = [];
    if (desde && hasta) {
        let cur = new Date(desde + 'T12:00:00');   // mediodía evita saltos de timezone
        const end = new Date(hasta + 'T12:00:00');
        while (cur <= end && days.length < 60) {
            const key = isoDate(cur);
            days.push({
                key,
                label: cur.toLocaleDateString('es-CO', { timeZone: 'America/Bogota', month: 'short', day: 'numeric' })
            });
            cur.setDate(cur.getDate() + 1);
        }
        const label = document.getElementById('tendencia-label');
        if (label) label.textContent = `${desde} → ${hasta}`;
    } else {
        for (let i = 6; i >= 0; i--) {
            const d = new Date();
            d.setDate(d.getDate() - i);
            const key = isoDate(d);
            days.push({
                key,
                label: d.toLocaleDateString('es-CO', { timeZone: 'America/Bogota', weekday: 'short', day: 'numeric' })
            });
        }
        const label = document.getElementById('tendencia-label');
        if (label) label.textContent = 'últimos 7 días';
    }

    const counts = days.map(d => data.filter(r => r.fecha === d.key).length);

    if (chartTendenciaInst) { chartTendenciaInst.destroy(); chartTendenciaInst = null; }

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
                pointRadius: days.length > 20 ? 2 : 5,
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
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: '#64748b', font: { size: 11 }, maxTicksLimit: 14, maxRotation: days.length > 14 ? 45 : 0 }
                },
                y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { precision: 0, color: '#64748b', font: { size: 11 } } }
            }
        }
    });
}

/* ════════════════════════════════════════════════════════
   TASA DE RESOLUCIÓN
════════════════════════════════════════════════════════ */
function renderTasa(data) {
    const total = data.length;
    const est   = { pendiente: 0, en_revision: 0, notificado: 0, resuelto: 0 };
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
            <div style="font-size:.78rem;color:#64748b;margin-top:2px;">${total} reportes en el período</div>
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

/* ════════════════════════════════════════════════════════
   MAPA DE CALOR
════════════════════════════════════════════════════════ */
function renderHeatmap(data) {
    const dias  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const bloqs = ['0–3','4–7','8–11','12–15','16–19','20–23'];

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

/* ════════════════════════════════════════════════════════
   ZONA / BARRIO
════════════════════════════════════════════════════════ */
function renderZona(data) {
    const topN   = parseInt(document.getElementById('zona-topn')?.value || '10');
    const counts = {};
    data.forEach(r => { if (r.barrio) counts[r.barrio] = (counts[r.barrio] || 0) + 1; });
    const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, topN);

    const wrap = document.getElementById('zona-wrap');

    // Destruir chart ANTES de reemplazar el canvas
    if (chartZonaInst) { chartZonaInst.destroy(); chartZonaInst = null; }

    if (!sorted.length) {
        wrap.innerHTML = `<div class="empty-state" style="margin-top:40px;">
            <i class="fas fa-map-pin"></i>
            <p>Sin datos de zona en el período seleccionado.</p>
        </div>`;
        return;
    }

    wrap.innerHTML = '<canvas id="chartZona"></canvas>';
    wrap.style.height = Math.max(220, sorted.length * 38) + 'px';

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

/* ════════════════════════════════════════════════════════
   RENDER PRINCIPAL
════════════════════════════════════════════════════════ */
function renderAnalytics() {
    // Leer filtro una sola vez → almacenar en _filtro
    const data = analyticsFiltered();
    updateBadges(data.length);

    // Cada función usa _filtro directamente (no depende del parámetro)
    try { renderTendencia(data); } catch(e) { console.error('Tendencia:', e); }
    try { renderTasa(data);      } catch(e) { console.error('Tasa:', e); }
    try { renderHeatmap(data);   } catch(e) { console.error('Heatmap:', e); }
    try { renderZona(data);      } catch(e) { console.error('Zona:', e); }
}

/* ── Init: preset "7 días" activo por defecto ── */
document.addEventListener('DOMContentLoaded', () => {
    initPresets();
    const preset7 = document.querySelector('.analytics-preset[data-days="7"]');
    if (preset7) {
        document.getElementById('analytics-fecha-desde').value = isoOffsetDays(7);
        document.getElementById('analytics-fecha-hasta').value = isoToday();
        highlightPreset(preset7);
    }
});
