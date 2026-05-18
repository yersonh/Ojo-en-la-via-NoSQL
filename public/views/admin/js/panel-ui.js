/* ════════════════════════════════════════════════════════
   NAVEGACIÓN POR TABS
════════════════════════════════════════════════════════ */
const tabTitles = {
    dashboard: 'Dashboard',
    reportes:  'Gestión de Reportes',
    usuarios:  'Gestión de Usuarios',
    analytics: 'Analíticas',
    notificar: 'Notificar Entidad',
    mapa:      'Mapa de Reportes'
};

function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));

    document.getElementById('tab-' + name).classList.add('active');
    document.querySelectorAll('.sidebar-menu a')[Object.keys(tabTitles).indexOf(name)].classList.add('active');
    document.getElementById('topbar-title').textContent = tabTitles[name];

    if (name === 'mapa')      initMapa();
    if (name === 'analytics') renderAnalytics();
}

/* ════════════════════════════════════════════════════════
   PALETA Y TOOLTIP BASE (usados por charts y analytics)
════════════════════════════════════════════════════════ */
const PALETTE = ['#4f6ef7','#0ea5e9','#10b981','#f59e0b','#8b5cf6','#f43f5e','#f97316','#06b6d4'];

const tooltipBase = {
    backgroundColor: '#1e293b',
    padding: 10,
    cornerRadius: 8,
    titleFont: { size: 12 },
    bodyFont:  { size: 12 },
};

// Plugin: total en el centro del doughnut
Chart.register({
    id: 'centerText',
    afterDraw(chart) {
        if (chart.config.type !== 'doughnut') return;
        const { ctx, chartArea: { top, bottom, left, right } } = chart;
        const cx    = (left + right) / 2;
        const cy    = (top  + bottom) / 2;
        const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
        ctx.save();
        ctx.font         = 'bold 26px system-ui, sans-serif';
        ctx.fillStyle    = '#1e293b';
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(total, cx, cy - 9);
        ctx.font      = '11px system-ui, sans-serif';
        ctx.fillStyle = '#94a3b8';
        ctx.fillText('total', cx, cy + 13);
        ctx.restore();
    }
});

/* ════════════════════════════════════════════════════════
   MODAL
════════════════════════════════════════════════════════ */
let modalCallback = null;

function openModal(title, body, btnClass, callback) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML    = body;
    const btn = document.getElementById('modal-confirm-btn');
    btn.className  = 'btn ' + btnClass;
    modalCallback  = callback;
    document.getElementById('modal-confirm').classList.add('show');
}
function closeModal() { document.getElementById('modal-confirm').classList.remove('show'); modalCallback = null; }
document.getElementById('modal-confirm-btn')?.addEventListener('click', () => { closeModal(); modalCallback?.(); });
document.getElementById('modal-confirm')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

/* ════════════════════════════════════════════════════════
   TOAST
════════════════════════════════════════════════════════ */
let toastTimer;
function showToast(msg, type = '') {
    clearTimeout(toastTimer);
    const t = document.getElementById('toast');
    t.textContent  = msg;
    t.className    = 'toast ' + type + ' show';
    toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}
