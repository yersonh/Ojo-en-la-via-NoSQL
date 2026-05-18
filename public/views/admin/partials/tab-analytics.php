<!-- ════════════════ TAB: ANALÍTICAS ════════════════ -->
<div id="tab-analytics" class="tab-content">

    <!-- Filtros globales -->
    <div class="section-card" style="margin-bottom:18px;">
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <span style="font-weight:600;color:#475569;font-size:.9rem;flex-shrink:0;">
                <i class="fas fa-filter" style="margin-right:6px;color:#94a3b8;"></i>Filtros
            </span>

            <!-- Tipo -->
            <select id="analytics-tipo" class="form-control" style="width:200px;" onchange="renderAnalytics()">
                <option value="">Todos los tipos</option>
                <?php foreach ($tiposData as $t): ?>
                <option value="<?= htmlspecialchars($t['tipo']) ?>"><?= htmlspecialchars($t['tipo']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Separador -->
            <div style="width:1px;height:28px;background:#e2e8f0;flex-shrink:0;"></div>

            <!-- Rangos rápidos -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;" id="analytics-presets">
                <button class="btn btn-sm analytics-preset" data-days="1"  style="background:#f1f5f9;color:#475569;">Hoy</button>
                <button class="btn btn-sm analytics-preset" data-days="7"  style="background:#eaf3fb;color:#2471a3;">7 días</button>
                <button class="btn btn-sm analytics-preset" data-days="30" style="background:#f1f5f9;color:#475569;">30 días</button>
                <button class="btn btn-sm analytics-preset" data-days="90" style="background:#f1f5f9;color:#475569;">90 días</button>
                <button class="btn btn-sm analytics-preset" data-days="0"  style="background:#f1f5f9;color:#475569;">Todo</button>
            </div>

            <!-- Separador -->
            <div style="width:1px;height:28px;background:#e2e8f0;flex-shrink:0;"></div>

            <!-- Fechas personalizadas -->
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <label style="font-size:.82rem;color:#64748b;white-space:nowrap;">Desde</label>
                <input type="date" id="analytics-fecha-desde" class="form-control" style="width:150px;" onchange="onFechaChange()">
                <label style="font-size:.82rem;color:#64748b;white-space:nowrap;">Hasta</label>
                <input type="date" id="analytics-fecha-hasta" class="form-control" style="width:150px;" onchange="onFechaChange()">
            </div>

            <span id="analytics-count" style="font-size:.82rem;color:#94a3b8;margin-left:auto;"></span>
        </div>
    </div>

    <!-- Fila 1: Tendencia + Tasa -->
    <div style="display:grid;grid-template-columns:3fr 2fr;gap:18px;margin-bottom:18px;">
        <div class="section-card">
            <div class="section-card-header">
                <h4><i class="fas fa-chart-line"></i> Tendencia <span id="tendencia-label">últimos 7 días</span></h4>
            </div>
            <div style="height:230px;"><canvas id="chartTendencia"></canvas></div>
        </div>
        <div class="section-card">
            <div class="section-card-header">
                <h4><i class="fas fa-check-double"></i> Tasa de resolución</h4>
            </div>
            <div id="tasa-container" style="padding:4px 0;"></div>
        </div>
    </div>

    <!-- Fila 2: Mapa de calor -->
    <div class="section-card" style="margin-bottom:18px;">
        <div class="section-card-header">
            <h4><i class="fas fa-th"></i> Actividad por día y hora</h4>
            <span style="font-size:.78rem;color:#94a3b8;">Hora Colombia (Bogotá)</span>
        </div>
        <div id="heatmap-container" style="overflow-x:auto;padding:8px 0;"></div>
        <div style="display:flex;align-items:center;gap:6px;margin-top:12px;justify-content:flex-end;">
            <span style="font-size:.75rem;color:#94a3b8;">Menos</span>
            <?php foreach ([0.08, 0.3, 0.55, 0.75, 0.93] as $a): ?>
            <div style="width:18px;height:18px;border-radius:4px;background:rgba(79,110,247,<?= $a ?>);"></div>
            <?php endforeach; ?>
            <span style="font-size:.75rem;color:#94a3b8;">Más</span>
        </div>
    </div>

    <!-- Fila 3: Zona/barrio -->
    <div class="section-card">
        <div class="section-card-header">
            <h4><i class="fas fa-map-pin"></i> Reportes por zona</h4>
            <select id="zona-topn" class="form-control" style="width:140px;" onchange="renderAnalytics()">
                <option value="5">Top 5 zonas</option>
                <option value="10" selected>Top 10 zonas</option>
                <option value="15">Top 15 zonas</option>
            </select>
        </div>
        <div id="zona-wrap" style="height:320px;"><canvas id="chartZona"></canvas></div>
    </div>

</div><!-- /tab-analytics -->
