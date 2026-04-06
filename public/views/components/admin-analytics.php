<?php

?>

<div class="section">
    <div class="section-header">
        <h3><i class="fas fa-chart-line"></i> Analytics & Estadísticas</h3>
        <div class="filters">
            <select id="rangoTiempo" class="form-control">
                <option value="7">Últimos 7 días</option>
                <option value="30" selected>Últimos 30 días</option>
                <option value="90">Últimos 3 meses</option>
                <option value="365">Último año</option>
            </select>
        </div>
    </div>

    <div class="analytics-content">
        <!-- Tarjetas de Resumen -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-reports">
                    <i class="fas fa-flag"></i>
                </div>
                <div class="stat-info">
                    <h4 id="totalReportes">0</h4>
                    <p>Total Reportes</p>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span>12%</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h4 id="reportesPendientes">0</h4>
                    <p>Pendientes</p>
                </div>
                <div class="stat-trend down">
                    <i class="fas fa-arrow-down"></i>
                    <span>5%</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon in-progress">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <div class="stat-info">
                    <h4 id="reportesProceso">0</h4>
                    <p>En Proceso</p>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span>8%</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon resolved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h4 id="reportesResueltos">0</h4>
                    <p>Resueltos</p>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span>15%</span>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h4>Reportes por Tipo</h4>
                    <select id="tipoGrafica" class="form-control">
                        <option value="bar">Barras</option>
                        <option value="pie">Pastel</option>
                        <option value="doughnut">Dona</option>
                    </select>
                </div>
                <canvas id="tipoChart"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h4>Evolución Temporal</h4>
                    <select id="agrupacionTiempo" class="form-control">
                        <option value="daily">Diario</option>
                        <option value="weekly" selected>Semanal</option>
                        <option value="monthly">Mensual</option>
                    </select>
                </div>
                <canvas id="timelineChart"></canvas>
            </div>

            <div class="chart-container full-width">
                <div class="chart-header">
                    <h4>Distribución por Estado</h4>
                    <div class="chart-legend" id="estadoLegend"></div>
                </div>
                <canvas id="estadoChart"></canvas>
            </div>

            <div class="chart-container full-width">
                <div class="chart-header">
                    <h4>Actividad de Usuarios</h4>
                    <select id="metricasUsuarios" class="form-control">
                        <option value="reportes">Reportes por Usuario</option>
                        <option value="actividad">Actividad Reciente</option>
                    </select>
                </div>
                <canvas id="usuariosChart"></canvas>
            </div>
        </div>

        <!-- Tablas de Datos -->
        <div class="data-tables">
            <div class="data-table-container">
                <h4><i class="fas fa-exclamation-circle"></i> Tipos de Incidentes Más Comunes</h4>
                <div class="table-responsive">
                    <table id="tablaTipos">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Porcentaje</th>
                                <th>Tendencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="data-table-container">
                <h4><i class="fas fa-users"></i> Usuarios Más Activos</h4>
                <div class="table-responsive">
                    <table id="tablaUsuarios">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Reportes</th>
                                <th>Última Actividad</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>