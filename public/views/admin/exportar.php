<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../config/auth_helper.php';

verificar_autenticacion('../../../index.php');

if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acceso denegado.');
}

$tipo = $_GET['tipo'] ?? '';
if (!in_array($tipo, ['reportes', 'usuarios', 'analiticas'], true)) {
    http_response_code(400);
    exit('Tipo de exportación no válido.');
}

$db = conectarMongoDB();

/* ════════════════════════════════════════════════════════
   REPORTES  →  CSV
════════════════════════════════════════════════════════ */
if ($tipo === 'reportes') {
    $filename = 'reportes_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF"; // BOM para Excel

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Tipo', 'Descripcion', 'Estado', 'Fecha', 'Hora', 'Usuario', 'Direccion', 'Latitud', 'Longitud']);

    $cursor = $db->Reportes->aggregate([
        ['$lookup' => ['from' => 'usuario', 'localField' => 'usuario_id', 'foreignField' => '_id', 'as' => 'u']],
        ['$sort'   => ['fecha_reporte' => -1]]
    ]);

    foreach ($cursor as $r) {
        $u = null;
        if (!empty($r['u'])) { foreach ($r['u'] as $x) { $u = $x; break; } }
        $nombre = 'No disponible';
        if ($u) $nombre = (string)($u['nombre_completo'] ?? $u['nombre'] ?? $u['nombre_usuario'] ?? 'No disponible');

        $fecha = '';
        $hora  = '';
        if (!empty($r['fecha_reporte']) && $r['fecha_reporte'] instanceof \MongoDB\BSON\UTCDateTime) {
            $dt    = $r['fecha_reporte']->toDateTime()->setTimezone(new DateTimeZone('America/Bogota'));
            $fecha = $dt->format('d/m/Y');
            $hora  = $dt->format('H:i');
        }

        $lat = $r['ubicacion']['latitud']  ?? $r['ubicacion']['lat']  ?? $r['latitud']  ?? '';
        $lng = $r['ubicacion']['longitud'] ?? $r['ubicacion']['lng']  ?? $r['longitud'] ?? '';

        fputcsv($out, [
            (string)$r['_id'],
            $r['tipo'] ?? $r['tipo_incidente'] ?? '',
            $r['descripcion'] ?? '',
            $r['estado'] ?? '',
            $fecha,
            $hora,
            $nombre,
            $r['direccion_texto'] ?? '',
            $lat !== '' ? number_format((float)$lat, 7, '.', '') : '',
            $lng !== '' ? number_format((float)$lng, 7, '.', '') : '',
        ]);
    }
    fclose($out);
    exit;
}

/* ════════════════════════════════════════════════════════
   USUARIOS  →  CSV
════════════════════════════════════════════════════════ */
if ($tipo === 'usuarios') {
    $filename = 'usuarios_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Nombre', 'Email', 'Telefono', 'Rol', 'Estado', 'Fecha de Creacion', 'Hora']);

    foreach ($db->usuario->find([], ['sort' => ['fecha_creacion' => -1]]) as $u) {
        $fecha = '';
        $hora  = '';
        if (!empty($u['fecha_creacion']) && $u['fecha_creacion'] instanceof \MongoDB\BSON\UTCDateTime) {
            $dt    = $u['fecha_creacion']->toDateTime()->setTimezone(new DateTimeZone('America/Bogota'));
            $fecha = $dt->format('d/m/Y');
            $hora  = $dt->format('H:i');
        }

        fputcsv($out, [
            (string)$u['_id'],
            $u['nombre_completo'] ?? $u['nombre'] ?? '',
            $u['email'] ?? '',
            $u['telefono'] ?? '',
            $u['rol'] ?? '',
            isset($u['estado']) ? ($u['estado'] ? 'Activo' : 'Inactivo') : '',
            $fecha,
            $hora,
        ]);
    }
    fclose($out);
    exit;
}

/* ════════════════════════════════════════════════════════
   ANALÍTICAS  →  HTML con gráficas
════════════════════════════════════════════════════════ */

// ── Construir los mismos datos que panel.php genera ──
$analyticsRaw = [];
foreach ($db->Reportes->aggregate([
    ['$addFields' => ['ts' => ['$toDate' => '$_id']]],
    ['$addFields' => [
        'hora'     => ['$hour'      => ['date' => '$ts', 'timezone' => 'America/Bogota']],
        'diaSem'   => ['$dayOfWeek' => ['date' => '$ts', 'timezone' => 'America/Bogota']],
        'fechaStr' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$ts', 'timezone' => 'America/Bogota']],
    ]],
    ['$project' => [
        'tipo'    => ['$ifNull' => ['$tipo', '$tipo_incidente']],
        'estado'  => 1, 'hora' => 1, 'diaSem' => 1, 'fechaStr' => 1,
        'barrio'  => ['$trim' => ['input' => ['$arrayElemAt' => [
            ['$split' => [['$ifNull' => ['$direccion_texto', '']], ',']], 2
        ]]]],
    ]],
]) as $r) {
    $barrio = trim((string)($r['barrio'] ?? ''));
    $analyticsRaw[] = [
        'tipo'  => (string)($r['tipo']     ?? 'Sin tipo'),
        'estado'=> (string)($r['estado']   ?? 'pendiente'),
        'hora'  => (int)($r['hora']        ?? 0),
        'dia'   => (int)($r['diaSem']      ?? 1),
        'fecha' => (string)($r['fechaStr'] ?? ''),
        'barrio'=> (!preg_match('/^\d/', $barrio) && $barrio !== '') ? $barrio : '',
    ];
}

// ── Resumen por estado ──
$totEst = ['pendiente' => 0, 'en_revision' => 0, 'notificado' => 0, 'resuelto' => 0];
foreach ($analyticsRaw as $r) {
    if (isset($totEst[$r['estado']])) $totEst[$r['estado']]++;
}
$total = count($analyticsRaw);

$rawJson = json_encode($analyticsRaw, JSON_UNESCAPED_UNICODE);
$fecha = date('d/m/Y H:i', strtotime('+0 hours'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Analíticas — Ojo en la Vía <?= date('d/m/Y') ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; color: #1e293b; }
    .page { max-width: 1100px; margin: 0 auto; padding: 32px 20px 60px; }

    /* ── Topbar ── */
    .topbar { background: #1a2332; color: #fff; border-radius: 16px; padding: 22px 28px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; }
    .topbar h1 { font-size: 1.35rem; font-weight: 700; }
    .topbar .meta { font-size: .82rem; color: #94a3b8; margin-top: 4px; }
    .btn-print { background: #1e88e5; color: #fff; border: none; border-radius: 9px; padding: 9px 18px; font-size: .85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 7px; }
    .btn-print:hover { background: #1565c0; }

    /* ── Cards ── */
    .card { background: #fff; border-radius: 14px; padding: 22px 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
    .card-title { font-size: .95rem; font-weight: 700; color: #1e293b; margin-bottom: 18px; display: flex; align-items: center; gap: 9px; }
    .card-title i { color: #6b7280; }

    /* ── KPIs ── */
    .kpis { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 20px; }
    .kpi { background: #fff; border-radius: 12px; padding: 18px 16px; text-align: center; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
    .kpi .num { font-size: 2.4rem; font-weight: 700; line-height: 1; }
    .kpi .lbl { font-size: .78rem; color: #64748b; margin-top: 4px; }

    /* ── Tasa ── */
    .bar-row { margin-bottom: 12px; }
    .bar-row .label-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: .82rem; }
    .bar-bg { background: #f1f5f9; border-radius: 4px; height: 7px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 4px; }

    /* ── Heatmap ── */
    #heatmap-export table { border-collapse: separate; border-spacing: 5px; width: 100%; }
    #heatmap-export th { font-size: .72rem; color: #94a3b8; font-weight: 500; padding-bottom: 4px; text-align: center; }
    #heatmap-export td.dia { font-size: .78rem; color: #64748b; font-weight: 500; white-space: nowrap; padding-right: 6px; }

    /* ── Tabla ── */
    .data-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .data-table th { background: #f8fafc; color: #475569; font-weight: 600; padding: 10px 12px; text-align: left; border-bottom: 2px solid #e5e7eb; }
    .data-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; color: #374151; }
    .data-table tr:hover td { background: #fafbfc; }
    .badge { display: inline-block; font-size: .72rem; font-weight: 700; padding: 2px 9px; border-radius: 999px; color: #fff; }

    /* ── 2 columnas ── */
    .row2 { display: grid; grid-template-columns: 3fr 2fr; gap: 18px; margin-bottom: 20px; }
    canvas { display: block; }

    @media print {
        body { background: #fff; }
        .btn-print { display: none !important; }
        .page { padding: 0; }
        .topbar { border-radius: 0; }
    }
    @media (max-width: 700px) {
        .kpis { grid-template-columns: repeat(2,1fr); }
        .row2 { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>
<div class="page">

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <h1><i class="fas fa-chart-bar" style="margin-right:10px;"></i>Reporte de Analíticas</h1>
            <div class="meta">Ojo en la Vía &nbsp;·&nbsp; Generado el <?= date('d/m/Y \a \l\a\s H:i', time()) ?> &nbsp;·&nbsp; <?= $total ?> reportes totales</div>
        </div>
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir / PDF
        </button>
    </div>

    <!-- KPIs -->
    <div class="kpis">
        <div class="kpi">
            <div class="num" style="color:#1e88e5;"><?= $total ?></div>
            <div class="lbl">Total reportes</div>
        </div>
        <div class="kpi">
            <div class="num" style="color:#10b981;"><?= $totEst['resuelto'] ?></div>
            <div class="lbl">Resueltos</div>
        </div>
        <div class="kpi">
            <div class="num" style="color:#8b5cf6;"><?= $totEst['notificado'] ?></div>
            <div class="lbl">Notificados</div>
        </div>
        <div class="kpi">
            <div class="num" style="color:#f59e0b;"><?= $totEst['pendiente'] ?></div>
            <div class="lbl">Pendientes</div>
        </div>
    </div>

    <!-- Tendencia + Tasa -->
    <div class="row2">
        <div class="card">
            <div class="card-title"><i class="fas fa-chart-line"></i> Tendencia (últimos 30 días)</div>
            <div style="height:230px;"><canvas id="chartTendencia"></canvas></div>
        </div>
        <div class="card">
            <div class="card-title"><i class="fas fa-check-double"></i> Tasa de resolución</div>
            <div style="text-align:center;margin-bottom:20px;">
                <div style="font-size:3rem;font-weight:700;color:#10b981;line-height:1;">
                    <?= $total ? round($totEst['resuelto'] / $total * 100) : 0 ?>%
                </div>
                <div style="font-size:.8rem;color:#94a3b8;margin-top:4px;">tasa de resolución</div>
            </div>
            <?php
            $items = [
                ['Resuelto',    $totEst['resuelto'],    '#10b981'],
                ['Notificado',  $totEst['notificado'],  '#8b5cf6'],
                ['En revisión', $totEst['en_revision'], '#0ea5e9'],
                ['Pendiente',   $totEst['pendiente'],   '#f59e0b'],
            ];
            foreach ($items as [$lbl, $cnt, $col]):
                $pct = $total ? round($cnt / $total * 100, 1) : 0;
            ?>
            <div class="bar-row">
                <div class="label-row">
                    <span><?= $lbl ?></span>
                    <span style="color:<?= $col ?>;font-weight:600;"><?= $cnt ?> <span style="color:#94a3b8;font-weight:400;">(<?= $pct ?>%)</span></span>
                </div>
                <div class="bar-bg"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Heatmap -->
    <div class="card">
        <div class="card-title"><i class="fas fa-th"></i> Actividad por día y hora (Hora Colombia)</div>
        <div id="heatmap-export" style="overflow-x:auto;"></div>
        <div style="display:flex;align-items:center;gap:6px;margin-top:14px;justify-content:flex-end;">
            <span style="font-size:.75rem;color:#94a3b8;">Menos</span>
            <?php foreach ([0.08, 0.3, 0.55, 0.75, 0.93] as $a): ?>
            <div style="width:18px;height:18px;border-radius:4px;background:rgba(79,110,247,<?= $a ?>);"></div>
            <?php endforeach; ?>
            <span style="font-size:.75rem;color:#94a3b8;">Más</span>
        </div>
    </div>

    <!-- Zonas -->
    <div class="card">
        <div class="card-title"><i class="fas fa-map-pin"></i> Top 10 zonas con más reportes</div>
        <div style="height:340px;"><canvas id="chartZona"></canvas></div>
    </div>

    <!-- Tabla de datos -->
    <div class="card">
        <div class="card-title"><i class="fas fa-table"></i> Detalle de reportes</div>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Tipo</th><th>Estado</th><th>Fecha</th><th>Hora</th><th>Día</th><th>Zona / Barrio</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $colores = ['pendiente' => '#f59e0b', 'en_revision' => '#0ea5e9', 'notificado' => '#8b5cf6', 'resuelto' => '#10b981'];
                $dias    = [1 => 'Dom', 2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb'];
                foreach ($analyticsRaw as $i => $r):
                    $col = $colores[$r['estado']] ?? '#94a3b8';
                    $dia = $dias[$r['dia']] ?? '?';
                    $estado = ucfirst(str_replace('_', ' ', $r['estado']));
                ?>
                <tr>
                    <td style="color:#94a3b8;"><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($r['tipo']) ?></td>
                    <td><span class="badge" style="background:<?= $col ?>;"><?= htmlspecialchars($estado) ?></span></td>
                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                    <td><?= str_pad($r['hora'], 2, '0', STR_PAD_LEFT) ?>:00</td>
                    <td><?= $dia ?></td>
                    <td><?= htmlspecialchars($r['barrio'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

</div><!-- /page -->

<script>
const DATA = <?= $rawJson ?>;

/* ── Helpers ── */
function isoDate(d) { return d.toLocaleDateString('en-CA', { timeZone: 'America/Bogota' }); }

/* ── Tendencia (30 días) ── */
(function() {
    const days = [];
    for (let i = 29; i >= 0; i--) {
        const d = new Date(); d.setDate(d.getDate() - i);
        days.push({ key: isoDate(d), label: d.toLocaleDateString('es-CO', { timeZone: 'America/Bogota', month: 'short', day: 'numeric' }) });
    }
    const counts = days.map(d => DATA.filter(r => r.fecha === d.key).length);
    const ctx  = document.getElementById('chartTendencia').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 210);
    grad.addColorStop(0, 'rgba(16,185,129,0.35)');
    grad.addColorStop(1, 'rgba(16,185,129,0.0)');
    new Chart(ctx, {
        type: 'line',
        data: { labels: days.map(d => d.label), datasets: [{ data: counts, borderColor: '#10b981', backgroundColor: grad, borderWidth: 2.5, pointBackgroundColor: '#10b981', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 3, fill: true, tension: 0.4 }] },
        options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, border: { display: false }, ticks: { color: '#64748b', font: { size: 10 }, maxTicksLimit: 10, maxRotation: 45 } }, y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { precision: 0, color: '#64748b', font: { size: 10 } } } } }
    });
})();

/* ── Heatmap ── */
(function() {
    const dias  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const bloqs = ['0–3','4–7','8–11','12–15','16–19','20–23'];
    const grid  = Array.from({ length: 7 }, () => Array(6).fill(0));
    DATA.forEach(r => {
        const d = (r.dia - 1 + 7) % 7;
        const b = Math.min(5, Math.floor(r.hora / 4));
        grid[d][b]++;
    });
    const maxVal = Math.max(1, ...grid.flat());
    let html = `<table><thead><tr><th style="width:40px;"></th>${bloqs.map(b => `<th>${b}h</th>`).join('')}</tr></thead><tbody>`;
    dias.forEach((dia, di) => {
        html += `<tr><td class="dia">${dia}</td>`;
        bloqs.forEach((_, bi) => {
            const val = grid[di][bi];
            const alpha = (0.07 + (val / maxVal) * 0.88).toFixed(2);
            const light = val / maxVal < 0.5;
            html += `<td style="background:rgba(79,110,247,${alpha});border-radius:7px;padding:11px 6px;text-align:center;font-size:.73rem;font-weight:600;color:${light ? '#475569' : '#fff'};">${val || ''}</td>`;
        });
        html += `</tr>`;
    });
    html += `</tbody></table>`;
    document.getElementById('heatmap-export').innerHTML = html;
})();

/* ── Zonas ── */
(function() {
    const counts = {};
    DATA.forEach(r => { if (r.barrio) counts[r.barrio] = (counts[r.barrio] || 0) + 1; });
    const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 10);
    if (!sorted.length) { document.getElementById('chartZona').closest('.card').style.display = 'none'; return; }
    document.getElementById('chartZona').closest('div').style.height = Math.max(220, sorted.length * 38) + 'px';
    new Chart(document.getElementById('chartZona'), {
        type: 'bar',
        data: { labels: sorted.map(([z]) => z.length > 28 ? z.slice(0, 26) + '…' : z), datasets: [{ data: sorted.map(([, c]) => c), backgroundColor: 'rgba(139,92,246,0.72)', hoverBackgroundColor: 'rgba(139,92,246,1)', borderRadius: 7, borderWidth: 0 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { precision: 0, color: '#64748b', font: { size: 11 } } }, y: { grid: { display: false }, border: { display: false }, ticks: { color: '#475569', font: { size: 11 } } } } }
    });
})();
</script>
</body>
</html>
<?php exit; ?>
