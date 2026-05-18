<!-- ════════════════ TAB: DASHBOARD ════════════════ -->
<div id="tab-dashboard" class="tab-content active">

    <!-- STAT CARDS -->
    <div class="cards-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-flag"></i></div>
            <div class="stat-info">
                <h3><?= $totalReportes ?></h3>
                <p>Total Reportes</p>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3><?= $totalUsuarios ?></h3>
                <p>Usuarios Registrados</p>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3><?= $pendientes ?></h3>
                <p>Pendientes</p>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-search"></i></div>
            <div class="stat-info">
                <h3><?= $enRevision ?></h3>
                <p>En Revisión</p>
            </div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon"><i class="fas fa-bell"></i></div>
            <div class="stat-info">
                <h3><?= $notificados ?></h3>
                <p>Notificados</p>
            </div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3><?= $resueltos ?></h3>
                <p>Resueltos</p>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="chart-card">
            <h4><i class="fas fa-chart-donut"></i> Tipos de Incidente</h4>
            <div class="chart-wrap"><canvas id="chartTipos"></canvas></div>
        </div>
        <div class="chart-card">
            <h4><i class="fas fa-chart-bar"></i> Reportes por Mes</h4>
            <div class="chart-wrap"><canvas id="chartMeses"></canvas></div>
        </div>
    </div>

    <!-- ESTADOS -->
    <div class="section-card">
        <div class="section-card-header">
            <h4><i class="fas fa-layer-group"></i> Distribución por Estado</h4>
        </div>
        <div class="chart-wrap" style="height:180px;"><canvas id="chartEstados"></canvas></div>
    </div>

</div><!-- /tab-dashboard -->
