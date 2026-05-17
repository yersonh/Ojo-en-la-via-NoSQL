<?php
session_start();

require_once __DIR__ . '/../../../config/auth_helper.php';

verificar_autenticacion('../../index.php');

if (($_SESSION['usuario_rol'] ?? 'ciudadano') !== 'admin') {
    header('Location: ../usuario/inicio.php');
    exit;
}

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use MongoDB\BSON\UTCDateTime;

$db = conectarMongoDB();
$reportes = $db->Reportes;

// ── ESTADÍSTICAS GLOBALES ──────────────────────────────────────────────────
$totalReportes  = $reportes->countDocuments([]);
$totalUsuarios  = $db->usuario->countDocuments([]);
$pendientes     = $reportes->countDocuments(['estado' => 'pendiente']);
$resueltos      = $reportes->countDocuments(['estado' => 'resuelto']);
$enRevision     = $reportes->countDocuments(['estado' => 'en_revision']);
$notificados    = $reportes->countDocuments(['estado' => 'notificado']);

// ── TIPOS DE INCIDENTE (doughnut chart) ───────────────────────────────────
$tiposData = [];
foreach ($reportes->aggregate([
    ['$group' => ['_id' => '$tipo', 'total' => ['$sum' => 1]]],
    ['$sort'  => ['total' => -1]],
    ['$limit' => 7]
]) as $t) {
    $tiposData[] = ['tipo' => (string)($t['_id'] ?? 'Sin tipo'), 'total' => (int)$t['total']];
}

// ── REPORTES POR MES (últimos 6 meses – bar chart) ─────────────────────────
$mesesNombres = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$mesesData = [];
foreach ($reportes->aggregate([
    ['$addFields' => ['ts' => ['$toDate' => '$_id']]],
    ['$group' => ['_id' => ['y' => ['$year' => '$ts'], 'm' => ['$month' => '$ts']], 'total' => ['$sum' => 1]]],
    ['$sort'  => ['_id.y' => -1, '_id.m' => -1]],
    ['$limit' => 6]
]) as $m) {
    $mes = (int)$m['_id']['m'];
    $mesesData[] = ['mes' => $mesesNombres[$mes - 1] . ' ' . $m['_id']['y'], 'total' => (int)$m['total']];
}
$mesesData = array_reverse($mesesData);

// ── LISTA DE REPORTES (tabla) ──────────────────────────────────────────────
// Pre-cargamos todos los usuarios en un mapa para resolver autor sin depender del tipo de usuario_id
$usuariosMap = [];
foreach ($db->usuario->find([], ['projection' => ['_id' => 1, 'nombre_completo' => 1, 'email' => 1]]) as $u) {
    $usuariosMap[(string)$u['_id']] = (string)($u['nombre_completo'] ?? $u['email'] ?? 'N/A');
}

$reportesList = [];
foreach ($reportes->find([], ['sort' => ['_id' => -1], 'limit' => 400]) as $r) {
    $uidStr = (string)($r['usuario_id'] ?? $r['usuario_creador_id'] ?? '');
    $autor  = $usuariosMap[$uidStr] ?? 'N/A';
    $fecha = 'N/A';
    if (isset($r['fecha_reporte'])) {
        if ($r['fecha_reporte'] instanceof UTCDateTime) {
            $fecha = $r['fecha_reporte']->toDateTime()
                ->setTimezone(new DateTimeZone('America/Bogota'))
                ->format('d/m/Y H:i');
        } else {
            $fecha = (string)$r['fecha_reporte'];
        }
    }
    // Coordenadas: pueden estar en ubicacion.lat/lng, ubicacion.latitud/longitud, o raíz
    $ubicacion = $r['ubicacion'] ?? [];
    $lat = (string)($ubicacion['lat']      ?? $ubicacion['latitud']  ?? $r['latitud']  ?? '');
    $lng = (string)($ubicacion['lng']      ?? $ubicacion['longitud'] ?? $r['longitud'] ?? '');
    // Imagen: puede ser array imagenes[] o campo foto
    $foto = '';
    $imagenes = $r['imagenes'] ?? [];
    if ($imagenes instanceof \MongoDB\Model\BSONArray) {
        $imagenes = $imagenes->getArrayCopy();
    }

    if (!empty($imagenes) && is_array($imagenes)) {
        $foto = (string)($imagenes[0] ?? '');
    } elseif (!empty($r['foto'])) {
        $foto = (string)$r['foto'];
    }

    $reportesList[] = [
        'id'            => (string)$r['_id'],
        'tipo'          => (string)($r['tipo'] ?? $r['tipo_incidente'] ?? 'Sin tipo'),
        'descripcion'   => (string)($r['descripcion'] ?? ''),
        'estado'        => (string)($r['estado'] ?? 'pendiente'),
        'fecha'         => $fecha,
        'usuario'       => $autor,
        'usuario_nombre'=> $autor,   // alias que usa crearPopupReporte
        'lat'           => $lat,
        'lng'           => $lng,
        'imagen'        => $foto,    // alias que usa crearPopupReporte
        'foto'          => $foto,
    ];
}

// ── LISTA DE USUARIOS ──────────────────────────────────────────────────────
$usuariosList = [];
foreach ($db->usuario->find([], ['sort' => ['_id' => -1], 'limit' => 300]) as $u) {
    $usuariosList[] = [
        'id'       => (string)$u['_id'],
        'nombre'   => (string)($u['nombre_completo'] ?? 'N/A'),
        'email'    => (string)($u['email'] ?? 'N/A'),
        'telefono' => (string)($u['telefono'] ?? 'N/A'),
        'rol'      => (string)($u['rol'] ?? 'ciudadano'),
        'estado'   => (bool)($u['estado'] ?? true),
        'fecha'    => (string)($u['fecha_creacion'] ?? 'N/A'),
    ];
}

// ── NOTIFICACIONES A ENTIDADES ─────────────────────────────────────────────
$notifEntList = [];
foreach ($db->notificaciones_entidades->find([], ['sort' => ['fecha' => -1], 'limit' => 100]) as $n) {
    $fecha = 'N/A';
    if (isset($n['fecha']) && $n['fecha'] instanceof UTCDateTime) {
        $fecha = $n['fecha']->toDateTime()
            ->setTimezone(new DateTimeZone('America/Bogota'))
            ->format('d/m/Y H:i');
    }
    $notifEntList[] = [
        'id'           => (string)$n['_id'],
        'entidad'      => (string)($n['entidad'] ?? ''),
        'prioridad'    => (string)($n['prioridad'] ?? 'media'),
        'asunto'       => (string)($n['asunto'] ?? ''),
        'mensaje'      => (string)($n['mensaje'] ?? ''),
        'estado_notif' => (string)($n['estado_notif'] ?? 'enviada'),
        'reporte_id'   => isset($n['reporte_id']) ? (string)$n['reporte_id'] : null,
        'fecha'        => $fecha,
        'admin'        => (string)($n['admin_nombre'] ?? 'Admin'),
    ];
}

$adminNombre  = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador');
$adminInicial = strtoupper(substr(strip_tags($adminNombre), 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
<title>Panel Administrativo – Ojo en la Vía</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/views/compartido/js/popup-reporte.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ── RESET & BASE ── */
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;color:#2c3e50;display:flex;min-height:100vh;}

/* ── SIDEBAR ── */
.sidebar{width:250px;min-width:250px;background:#1a2332;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;transition:.3s;}
.sidebar-brand{padding:22px 20px;border-bottom:1px solid rgba(255,255,255,.08);}
.sidebar-brand h2{color:#fff;font-size:1.1rem;font-weight:700;line-height:1.3;}
.sidebar-brand span{color:#3498db;display:block;font-size:.78rem;font-weight:400;margin-top:2px;}
.sidebar-menu{padding:12px 0;flex:1;overflow-y:auto;}
.sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:rgba(255,255,255,.7);text-decoration:none;font-size:.9rem;transition:.2s;border-left:3px solid transparent;cursor:pointer;}
.sidebar-menu a:hover,.sidebar-menu a.active{color:#fff;background:rgba(52,152,219,.15);border-left-color:#3498db;}
.sidebar-menu a i{width:18px;text-align:center;}
.sidebar-menu .menu-section{padding:16px 20px 6px;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.3);}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
.sidebar-footer a{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.6);text-decoration:none;font-size:.88rem;transition:.2s;}
.sidebar-footer a:hover{color:#e74c3c;}
.sidebar-logout-btn{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.6);background:transparent;border:none;font-size:.88rem;cursor:pointer;width:100%;padding:0;transition:.2s;}
.sidebar-logout-btn:hover{color:#e74c3c;}

/* ── MAIN LAYOUT ── */
.main-wrap{margin-left:250px;flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{background:#fff;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 4px rgba(0,0,0,.08);position:sticky;top:0;z-index:50;}
.topbar-title{font-size:1.1rem;font-weight:600;color:#2c3e50;}
.topbar-right{display:flex;align-items:center;gap:16px;}
.admin-chip{display:flex;align-items:center;gap:10px;}
.admin-avatar{width:36px;height:36px;border-radius:50%;background:#3498db;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:.95rem;}
.admin-name{font-size:.88rem;font-weight:600;color:#2c3e50;}
.admin-role{font-size:.75rem;color:#7f8c8d;}

/* ── CONTENT AREA ── */
.content{padding:26px 28px;flex:1;}
.tab-content{display:none;}
.tab-content.active{display:block;}

/* ── STAT CARDS ── */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:26px;}
.stat-card{background:#fff;border-radius:12px;padding:20px;display:flex;align-items:center;gap:16px;box-shadow:0 1px 6px rgba(0,0,0,.06);border-left:4px solid #3498db;}
.stat-card.green{border-left-color:#27ae60;}
.stat-card.orange{border-left-color:#f39c12;}
.stat-card.red{border-left-color:#e74c3c;}
.stat-card.purple{border-left-color:#9b59b6;}
.stat-card.teal{border-left-color:#1abc9c;}
.stat-icon{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;background:#eaf3fb;color:#3498db;}
.stat-card.green .stat-icon{background:#eafaf1;color:#27ae60;}
.stat-card.orange .stat-icon{background:#fef9e7;color:#f39c12;}
.stat-card.red .stat-icon{background:#fdedec;color:#e74c3c;}
.stat-card.purple .stat-icon{background:#f5eef8;color:#9b59b6;}
.stat-card.teal .stat-icon{background:#e8f8f5;color:#1abc9c;}
.stat-info h3{font-size:1.7rem;font-weight:700;line-height:1;}
.stat-info p{font-size:.8rem;color:#7f8c8d;margin-top:3px;}

/* ── CHARTS ── */
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:26px;}
.chart-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 6px rgba(0,0,0,.06);}
.chart-card h4{font-size:.95rem;font-weight:600;margin-bottom:16px;color:#2c3e50;display:flex;align-items:center;gap:8px;}
.chart-card h4 i{color:#3498db;}
.chart-wrap{position:relative;height:240px;}

/* ── SECTION CARD ── */
.section-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 6px rgba(0,0,0,.06);margin-bottom:22px;}
.section-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;}
.section-card-header h4{font-size:1rem;font-weight:600;display:flex;align-items:center;gap:8px;}
.section-card-header h4 i{color:#3498db;}
.section-filters{display:flex;gap:10px;flex-wrap:wrap;}

/* ── TABLE ── */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.88rem;}
thead th{background:#f8f9fa;padding:10px 14px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #eee;white-space:nowrap;}
tbody td{padding:11px 14px;border-bottom:1px solid #f0f0f0;vertical-align:middle;}
tbody tr:hover{background:#fafafa;}
.text-truncate{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

/* ── BADGES ── */
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:600;}
.badge-pendiente{background:#fef9e7;color:#d68910;}
.badge-en_revision{background:#eaf3fb;color:#2471a3;}
.badge-notificado{background:#f5eef8;color:#7d3c98;}
.badge-resuelto{background:#eafaf1;color:#1e8449;}
.badge-admin{background:#fdedec;color:#c0392b;}
.badge-ciudadano{background:#eafaf1;color:#1e8449;}
.badge-activo{background:#eafaf1;color:#1e8449;}
.badge-inactivo{background:#f2f3f4;color:#7f8c8d;}
.badge-alta{background:#fdedec;color:#c0392b;}
.badge-media{background:#fef9e7;color:#d68910;}
.badge-baja{background:#eafaf1;color:#1e8449;}
.badge-enviada{background:#eaf3fb;color:#2471a3;}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:none;border-radius:8px;font-size:.83rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none;}
.btn-primary{background:#3498db;color:#fff;}
.btn-primary:hover{background:#2176ae;}
.btn-success{background:#27ae60;color:#fff;}
.btn-success:hover{background:#1e8449;}
.btn-danger{background:#e74c3c;color:#fff;}
.btn-danger:hover{background:#c0392b;}
.btn-warning{background:#f39c12;color:#fff;}
.btn-warning:hover{background:#d68910;}
.btn-secondary{background:#ecf0f1;color:#2c3e50;}
.btn-secondary:hover{background:#dde1e4;}
.btn-sm{padding:5px 10px;font-size:.78rem;}
.btn-icon{width:32px;height:32px;padding:0;justify-content:center;border-radius:8px;}

/* ── FORM ── */
.form-group{margin-bottom:16px;}
.form-label{display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:#2c3e50;}
.form-control{width:100%;padding:9px 12px;border:1.5px solid #dde1e4;border-radius:8px;font-size:.88rem;color:#2c3e50;outline:none;transition:.2s;background:#fff;}
.form-control:focus{border-color:#3498db;box-shadow:0 0 0 3px rgba(52,152,219,.12);}
textarea.form-control{resize:vertical;min-height:100px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-hint{font-size:.78rem;color:#7f8c8d;margin-top:4px;}

/* ── ENTITY CARDS ── */
.entity-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin-bottom:18px;}
.entity-card{border:2px solid #dde1e4;border-radius:10px;padding:14px;cursor:pointer;transition:.2s;text-align:center;}
.entity-card:hover{border-color:#3498db;background:#f0f7ff;}
.entity-card.selected{border-color:#3498db;background:#eaf3fb;}
.entity-card i{font-size:1.6rem;margin-bottom:6px;color:#3498db;}
.entity-card p{font-size:.78rem;font-weight:600;color:#2c3e50;line-height:1.3;}

/* ── TIMELINE ── */
.timeline{position:relative;padding-left:24px;}
.timeline::before{content:'';position:absolute;left:8px;top:0;bottom:0;width:2px;background:#e0e0e0;}
.timeline-item{position:relative;margin-bottom:20px;}
.timeline-dot{position:absolute;left:-20px;top:4px;width:14px;height:14px;border-radius:50%;background:#3498db;border:2px solid #fff;box-shadow:0 0 0 2px #3498db;}
.timeline-dot.alta{background:#e74c3c;box-shadow:0 0 0 2px #e74c3c;}
.timeline-dot.media{background:#f39c12;box-shadow:0 0 0 2px #f39c12;}
.timeline-dot.baja{background:#27ae60;box-shadow:0 0 0 2px #27ae60;}
.timeline-content{background:#f8f9fa;border-radius:10px;padding:12px 14px;}
.timeline-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:6px;}
.timeline-title{font-weight:600;font-size:.9rem;}
.timeline-meta{font-size:.78rem;color:#7f8c8d;}
.timeline-body{font-size:.85rem;color:#555;line-height:1.5;}

/* ── MAP ── */
#adminMap{height:540px;border-radius:10px;z-index:1;}

/* ── MODAL ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:none;align-items:center;justify-content:center;}
.modal-overlay.show{display:flex;}
.modal{background:#fff;border-radius:14px;padding:28px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);}
.modal h3{margin-bottom:12px;font-size:1.1rem;}
.modal p{color:#555;margin-bottom:20px;line-height:1.5;font-size:.9rem;}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;}

/* ── SEARCH ── */
.search-box{position:relative;}
.search-box input{padding-left:36px;}
.search-box i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#aaa;font-size:.88rem;}

/* ── TOAST ── */
.toast{position:fixed;bottom:24px;right:24px;background:#2c3e50;color:#fff;padding:13px 20px;border-radius:10px;font-size:.88rem;z-index:9999;opacity:0;transform:translateY(10px);transition:.3s;pointer-events:none;max-width:320px;}
.toast.show{opacity:1;transform:translateY(0);}
.toast.success{background:#27ae60;}
.toast.error{background:#e74c3c;}
.toast.warning{background:#f39c12;}

/* ── EMPTY STATE ── */
.empty-state{text-align:center;padding:40px;color:#aaa;}
.empty-state i{font-size:2.5rem;margin-bottom:10px;display:block;}

/* ── MAP POPUP ── */
.leaflet-popup-content{min-width:200px;}

/* ── RESPONSIVE ── */
@media(max-width:900px){
    .sidebar{width:60px;min-width:60px;}
    .sidebar-brand h2,.sidebar-brand span,.sidebar-menu a span,.sidebar-menu .menu-section,.sidebar-footer a span{display:none;}
    .main-wrap{margin-left:60px;}
    .charts-grid{grid-template-columns:1fr;}
    .form-row{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- ═══════════════════════════ SIDEBAR ═══════════════════════════ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <h2><i class="fas fa-eye" style="color:#3498db;margin-right:8px;"></i>Ojo en la Vía</h2>
        <span>Panel Administrativo</span>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-section">Principal</div>
        <a class="active" onclick="showTab('dashboard')">
            <i class="fas fa-chart-pie"></i><span>Dashboard</span>
        </a>
        <a onclick="showTab('reportes')">
            <i class="fas fa-map-marker-alt"></i><span>Reportes</span>
        </a>
        <a onclick="showTab('usuarios')">
            <i class="fas fa-users"></i><span>Usuarios</span>
        </a>

        <div class="menu-section">Acciones</div>
        <a onclick="showTab('notificar')">
            <i class="fas fa-paper-plane"></i><span>Notificar Entidad</span>
        </a>
        <a onclick="showTab('mapa')">
            <i class="fas fa-map-marked-alt"></i><span>Mapa de Reportes</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <button class="sidebar-logout-btn" onclick="abrirModalLogout()">
            <i class="fas fa-sign-out-alt"></i><span>Cerrar sesión</span>
        </button>
    </div>
</aside>

<!-- ═══════════════════════════ MAIN ═══════════════════════════ -->
<div class="main-wrap">

    <!-- TOPBAR -->
    <div class="topbar">
        <span class="topbar-title" id="topbar-title">Dashboard</span>
        <div class="topbar-right">
            <div class="admin-chip">
                <div class="admin-avatar"><?= $adminInicial ?></div>
                <div>
                    <div class="admin-name"><?= $adminNombre ?></div>
                    <div class="admin-role">Administrador</div>
                </div>
            </div>
        </div>
    </div>

    <div class="content">

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

        <!-- ════════════════ TAB: REPORTES ════════════════ -->
        <div id="tab-reportes" class="tab-content">
            <div class="section-card">
                <div class="section-card-header">
                    <h4><i class="fas fa-map-marker-alt"></i> Gestión de Reportes
                        <span style="background:#eaf3fb;color:#2471a3;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;margin-left:8px;" id="count-reportes"><?= count($reportesList) ?></span>
                    </h4>
                    <div class="section-filters">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="buscar-reporte" placeholder="Buscar reporte..." style="width:200px;">
                        </div>
                        <select class="form-control" id="filtro-estado-reporte" style="width:160px;">
                            <option value="">Todos los estados</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="en_revision">En Revisión</option>
                            <option value="notificado">Notificado</option>
                            <option value="resuelto">Resuelto</option>
                        </select>
                        <select class="form-control" id="filtro-tipo-reporte" style="width:150px;">
                            <option value="">Todos los tipos</option>
                            <?php
                            $tiposUnicos = array_unique(array_column($reportesList, 'tipo'));
                            sort($tiposUnicos);
                            foreach ($tiposUnicos as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="tabla-reportes">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Usuario</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Cambiar Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reportesList as $i => $r): ?>
                            <tr data-id="<?= $r['id'] ?>"
                                data-estado="<?= htmlspecialchars($r['estado']) ?>"
                                data-tipo="<?= htmlspecialchars($r['tipo']) ?>"
                                data-texto="<?= htmlspecialchars(strtolower($r['tipo'].' '.$r['descripcion'].' '.$r['usuario'])) ?>">
                                <td style="color:#aaa;font-size:.8rem;"><?= $i+1 ?></td>
                                <td><span style="font-weight:600;"><?= htmlspecialchars($r['tipo']) ?></span></td>
                                <td>
                                    <span class="text-truncate" style="display:block;max-width:200px;" title="<?= htmlspecialchars($r['descripcion']) ?>">
                                        <?= htmlspecialchars(mb_substr($r['descripcion'], 0, 60)) ?><?= mb_strlen($r['descripcion']) > 60 ? '…' : '' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($r['usuario']) ?></td>
                                <td style="white-space:nowrap;"><?= htmlspecialchars($r['fecha']) ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($r['estado']) ?>">
                                        <?= ucfirst(str_replace('_',' ', $r['estado'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <select class="form-control select-estado-reporte" style="font-size:.8rem;padding:4px 8px;width:130px;" data-id="<?= $r['id'] ?>">
                                        <option value="pendiente"   <?= $r['estado']==='pendiente'   ?'selected':'' ?>>Pendiente</option>
                                        <option value="en_revision" <?= $r['estado']==='en_revision' ?'selected':'' ?>>En Revisión</option>
                                        <option value="notificado"  <?= $r['estado']==='notificado'  ?'selected':'' ?>>Notificado</option>
                                        <option value="resuelto"    <?= $r['estado']==='resuelto'    ?'selected':'' ?>>Resuelto</option>
                                    </select>
                                </td>
                                <td>
                                    <button class="btn btn-danger btn-sm btn-icon eliminar-reporte" data-id="<?= $r['id'] ?>" title="Eliminar reporte">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportesList)): ?>
                            <tr><td colspan="8"><div class="empty-state"><i class="fas fa-inbox"></i>No hay reportes</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- /tab-reportes -->

        <!-- ════════════════ TAB: USUARIOS ════════════════ -->
        <div id="tab-usuarios" class="tab-content">
            <div class="section-card">
                <div class="section-card-header">
                    <h4><i class="fas fa-users"></i> Gestión de Usuarios
                        <span style="background:#eaf3fb;color:#2471a3;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;margin-left:8px;" id="count-usuarios"><?= count($usuariosList) ?></span>
                    </h4>
                    <div class="section-filters">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="buscar-usuario" placeholder="Buscar usuario..." style="width:200px;">
                        </div>
                        <select class="form-control" id="filtro-rol" style="width:140px;">
                            <option value="">Todos los roles</option>
                            <option value="ciudadano">Ciudadano</option>
                            <option value="admin">Admin</option>
                        </select>
                        <select class="form-control" id="filtro-estado-usuario" style="width:140px;">
                            <option value="">Todos los estados</option>
                            <option value="1">Activos</option>
                            <option value="0">Inactivos</option>
                        </select>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="tabla-usuarios">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usuariosList as $i => $u): ?>
                            <tr data-id="<?= $u['id'] ?>"
                                data-rol="<?= htmlspecialchars($u['rol']) ?>"
                                data-estado="<?= $u['estado'] ? '1' : '0' ?>"
                                data-texto="<?= htmlspecialchars(strtolower($u['nombre'].' '.$u['email'].' '.$u['telefono'])) ?>">
                                <td style="color:#aaa;font-size:.8rem;"><?= $i+1 ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div style="width:32px;height:32px;border-radius:50%;background:#3498db;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;">
                                            <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
                                        </div>
                                        <span style="font-weight:600;"><?= htmlspecialchars($u['nombre']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['telefono']) ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($u['rol']) ?>">
                                        <?= ucfirst($u['rol']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $u['estado'] ? 'activo' : 'inactivo' ?>" id="estado-badge-<?= $u['id'] ?>">
                                        <?= $u['estado'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td style="white-space:nowrap;font-size:.82rem;color:#777;"><?= htmlspecialchars($u['fecha']) ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <button class="btn btn-secondary btn-sm btn-icon toggle-usuario"
                                            data-id="<?= $u['id'] ?>"
                                            data-estado="<?= $u['estado'] ? '1' : '0' ?>"
                                            title="<?= $u['estado'] ? 'Desactivar' : 'Activar' ?>">
                                            <i class="fas fa-<?= $u['estado'] ? 'ban' : 'check' ?>"></i>
                                        </button>
                                        <button class="btn btn-warning btn-sm btn-icon cambiar-rol"
                                            data-id="<?= $u['id'] ?>"
                                            data-rol="<?= htmlspecialchars($u['rol']) ?>"
                                            title="Cambiar rol">
                                            <i class="fas fa-user-cog"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($usuariosList)): ?>
                            <tr><td colspan="8"><div class="empty-state"><i class="fas fa-user-slash"></i>No hay usuarios</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- /tab-usuarios -->

        <!-- ════════════════ TAB: NOTIFICAR ENTIDAD ════════════════ -->
        <div id="tab-notificar" class="tab-content">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;">

                <!-- FORMULARIO -->
                <div class="section-card">
                    <div class="section-card-header">
                        <h4><i class="fas fa-paper-plane"></i> Redactar Notificación</h4>
                    </div>

                    <p style="font-size:.85rem;color:#7f8c8d;margin-bottom:16px;">
                        Selecciona la entidad responsable y redacta la notificación oficial del reporte ciudadano.
                    </p>

                    <!-- ENTIDADES -->
                    <div class="form-group">
                        <label class="form-label">Entidad Destinataria</label>
                        <div class="entity-grid" id="entity-grid">
                            <?php
                            $entidades = [
                                ['id'=>'alcaldia',       'nombre'=>'Alcaldía de Villavicencio',        'icon'=>'fa-building'],
                                ['id'=>'secretaria',     'nombre'=>'Sec. de Infraestructura',           'icon'=>'fa-hard-hat'],
                                ['id'=>'invias',         'nombre'=>'INVIAS Regional Llanos',            'icon'=>'fa-road'],
                                ['id'=>'policia',        'nombre'=>'Policía Nacional',                  'icon'=>'fa-shield-alt'],
                                ['id'=>'transito',       'nombre'=>'Tránsito y Transporte',             'icon'=>'fa-traffic-light'],
                                ['id'=>'epu',            'nombre'=>'Empresas Públicas Utilities',       'icon'=>'fa-wrench'],
                            ];
                            foreach ($entidades as $ent): ?>
                            <div class="entity-card" data-entity="<?= $ent['id'] ?>" data-label="<?= htmlspecialchars($ent['nombre']) ?>" onclick="selectEntity(this)">
                                <i class="fas <?= $ent['icon'] ?>"></i>
                                <p><?= htmlspecialchars($ent['nombre']) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="entidad-sel" value="">
                        <input type="hidden" id="entidad-label" value="">
                    </div>

                    <form id="form-notificar" onsubmit="enviarNotificacion(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Prioridad</label>
                                <select class="form-control" id="notif-prioridad" required>
                                    <option value="alta">Alta</option>
                                    <option value="media" selected>Media</option>
                                    <option value="baja">Baja</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Reporte Asociado (opcional)</label>
                                <select class="form-control" id="notif-reporte">
                                    <option value="">— Sin reporte específico —</option>
                                    <?php foreach ($reportesList as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars(mb_substr($r['tipo'].' – '.$r['descripcion'], 0, 60)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Asunto</label>
                            <input type="text" class="form-control" id="notif-asunto" placeholder="Ej: Reporte urgente de hueco en vía principal" maxlength="120" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mensaje</label>
                            <textarea class="form-control" id="notif-mensaje" rows="5" placeholder="Detalle de la notificación oficial a la entidad..." maxlength="1000" required></textarea>
                            <div class="form-hint"><span id="notif-chars">0</span>/1000 caracteres</div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-paper-plane"></i> Enviar Notificación
                        </button>
                    </form>
                </div>

                <!-- LOG DE NOTIFICACIONES -->
                <div class="section-card">
                    <div class="section-card-header">
                        <h4><i class="fas fa-history"></i> Historial de Notificaciones</h4>
                        <span style="background:#eaf3fb;color:#2471a3;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;" id="count-notifs"><?= count($notifEntList) ?></span>
                    </div>

                    <div id="notif-timeline" class="timeline" style="max-height:600px;overflow-y:auto;">
                        <?php if (empty($notifEntList)): ?>
                        <div class="empty-state">
                            <i class="fas fa-paper-plane"></i>
                            <p>Aún no se han enviado notificaciones a entidades.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($notifEntList as $n): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot <?= htmlspecialchars($n['prioridad']) ?>"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-title"><?= htmlspecialchars($n['entidad']) ?></span>
                                    <div style="display:flex;gap:6px;align-items:center;">
                                        <span class="badge badge-<?= htmlspecialchars($n['prioridad']) ?>"><?= ucfirst($n['prioridad']) ?></span>
                                        <span class="badge badge-enviada"><i class="fas fa-check"></i> <?= ucfirst($n['estado_notif']) ?></span>
                                    </div>
                                </div>
                                <div style="font-weight:600;font-size:.85rem;margin-bottom:4px;"><?= htmlspecialchars($n['asunto']) ?></div>
                                <div class="timeline-body"><?= nl2br(htmlspecialchars(mb_substr($n['mensaje'], 0, 200))) ?><?= mb_strlen($n['mensaje'])>200 ? '…' : '' ?></div>
                                <div class="timeline-meta" style="margin-top:6px;">
                                    <i class="fas fa-user-shield"></i> <?= htmlspecialchars($n['admin']) ?>
                                    &nbsp;·&nbsp;
                                    <i class="fas fa-clock"></i> <?= htmlspecialchars($n['fecha']) ?>
                                    <?php if ($n['reporte_id']): ?>
                                    &nbsp;·&nbsp;<i class="fas fa-link"></i> Reporte vinculado
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div><!-- /tab-notificar -->

        <!-- ════════════════ TAB: MAPA ════════════════ -->
        <div id="tab-mapa" class="tab-content">
            <div class="section-card">
                <div class="section-card-header">
                    <h4><i class="fas fa-map-marked-alt"></i> Mapa de Reportes en Vivo</h4>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <select class="form-control" id="mapa-filtro-estado" style="width:160px;" onchange="filtrarMapa()">
                            <option value="">Todos los estados</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="en_revision">En Revisión</option>
                            <option value="notificado">Notificado</option>
                            <option value="resuelto">Resuelto</option>
                        </select>
                        <button class="btn btn-primary btn-sm" onclick="recargarMapa()">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                    </div>
                </div>
                <!-- Leyenda -->
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
                    <?php
                    $leyenda = [
                        'pendiente'   => ['color'=>'#f39c12','label'=>'Pendiente'],
                        'en_revision' => ['color'=>'#3498db','label'=>'En Revisión'],
                        'notificado'  => ['color'=>'#9b59b6','label'=>'Notificado'],
                        'resuelto'    => ['color'=>'#27ae60','label'=>'Resuelto'],
                    ];
                    foreach ($leyenda as $leg): ?>
                    <div style="display:flex;align-items:center;gap:6px;font-size:.82rem;">
                        <span style="width:12px;height:12px;border-radius:50%;background:<?= $leg['color'] ?>;display:inline-block;"></span>
                        <?= $leg['label'] ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="adminMap"></div>
            </div>
        </div><!-- /tab-mapa -->

    </div><!-- /content -->
</div><!-- /main-wrap -->

<!-- ════════════════════ MODAL CONFIRMACIÓN ════════════════════ -->
<div class="modal-overlay" id="modal-confirm">
    <div class="modal">
        <h3 id="modal-title">¿Confirmar acción?</h3>
        <p id="modal-body">Esta acción no se puede deshacer.</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-danger" id="modal-confirm-btn">Confirmar</button>
        </div>
    </div>
</div>

<!-- ════════════════════ TOAST ════════════════════ -->
<div class="toast" id="toast"></div>

<!-- ════════════════════ DATA para JS ════════════════════ -->
<script>
const REPORTES_DATA = <?= json_encode($reportesList, JSON_UNESCAPED_UNICODE) ?>;
const CHART_TIPOS   = <?= json_encode($tiposData, JSON_UNESCAPED_UNICODE) ?>;
const CHART_MESES   = <?= json_encode($mesesData, JSON_UNESCAPED_UNICODE) ?>;
const ESTADOS_DATA  = {
    pendiente: <?= $pendientes ?>,
    en_revision: <?= $enRevision ?>,
    notificado: <?= $notificados ?>,
    resuelto: <?= $resueltos ?>
};
</script>

<script>
/* ════════════════════════════════════════════════════════
   NAVEGACIÓN POR TABS
════════════════════════════════════════════════════════ */
const tabTitles = {
    dashboard: 'Dashboard',
    reportes:  'Gestión de Reportes',
    usuarios:  'Gestión de Usuarios',
    notificar: 'Notificar Entidad',
    mapa:      'Mapa de Reportes'
};

function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));

    document.getElementById('tab-' + name).classList.add('active');
    document.querySelectorAll('.sidebar-menu a')[Object.keys(tabTitles).indexOf(name)].classList.add('active');
    document.getElementById('topbar-title').textContent = tabTitles[name];

    if (name === 'mapa') initMapa();
}

/* ════════════════════════════════════════════════════════
   CHARTS (Chart.js)
════════════════════════════════════════════════════════ */
const PALETTE = ['#3498db','#27ae60','#e74c3c','#f39c12','#9b59b6','#1abc9c','#e67e22','#2980b9'];

document.addEventListener('DOMContentLoaded', () => {
    // Doughnut – tipos de incidente
    new Chart(document.getElementById('chartTipos'), {
        type: 'doughnut',
        data: {
            labels: CHART_TIPOS.map(t => t.tipo),
            datasets: [{
                data: CHART_TIPOS.map(t => t.total),
                backgroundColor: PALETTE,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { font: { size: 11 }, boxWidth: 14 } } }
        }
    });

    // Bar – reportes por mes
    new Chart(document.getElementById('chartMeses'), {
        type: 'bar',
        data: {
            labels: CHART_MESES.map(m => m.mes),
            datasets: [{
                label: 'Reportes',
                data: CHART_MESES.map(m => m.total),
                backgroundColor: 'rgba(52,152,219,.75)',
                borderColor: '#3498db',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // Horizontal bar – estados
    new Chart(document.getElementById('chartEstados'), {
        type: 'bar',
        data: {
            labels: ['Pendiente', 'En Revisión', 'Notificado', 'Resuelto'],
            datasets: [{
                label: 'Cantidad',
                data: [ESTADOS_DATA.pendiente, ESTADOS_DATA.en_revision, ESTADOS_DATA.notificado, ESTADOS_DATA.resuelto],
                backgroundColor: ['#f39c12','#3498db','#9b59b6','#27ae60'],
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
});

/* ════════════════════════════════════════════════════════
   TABLA REPORTES – filtros
════════════════════════════════════════════════════════ */
function filtrarTabla(tableId, countId) {
    const tabla = document.getElementById(tableId);
    if (!tabla) return;
    const rows = tabla.querySelectorAll('tbody tr[data-id]');
    let visible = 0;

    const buscarR   = (document.getElementById('buscar-reporte')?.value || '').toLowerCase();
    const estadoR   = (document.getElementById('filtro-estado-reporte')?.value || '').toLowerCase();
    const tipoR     = (document.getElementById('filtro-tipo-reporte')?.value || '').toLowerCase();

    const buscarU   = (document.getElementById('buscar-usuario')?.value || '').toLowerCase();
    const rolU      = (document.getElementById('filtro-rol')?.value || '').toLowerCase();
    const estadoU   = (document.getElementById('filtro-estado-usuario')?.value || '');

    rows.forEach(row => {
        let show = true;
        const texto  = (row.dataset.texto  || '').toLowerCase();
        const estado = (row.dataset.estado || '').toLowerCase();
        const tipo   = (row.dataset.tipo   || '').toLowerCase();
        const rol    = (row.dataset.rol    || '').toLowerCase();

        if (tableId === 'tabla-reportes') {
            if (buscarR && !texto.includes(buscarR)) show = false;
            if (estadoR && estado !== estadoR) show = false;
            if (tipoR   && tipo !== tipoR)     show = false;
        } else {
            if (buscarU && !texto.includes(buscarU))    show = false;
            if (rolU    && rol !== rolU)                show = false;
            if (estadoU !== '' && estado !== estadoU)   show = false;
        }

        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const counter = document.getElementById(countId);
    if (counter) counter.textContent = visible;
}

document.getElementById('buscar-reporte')?.addEventListener('input',    () => filtrarTabla('tabla-reportes','count-reportes'));
document.getElementById('filtro-estado-reporte')?.addEventListener('change', () => filtrarTabla('tabla-reportes','count-reportes'));
document.getElementById('filtro-tipo-reporte')?.addEventListener('change',   () => filtrarTabla('tabla-reportes','count-reportes'));

document.getElementById('buscar-usuario')?.addEventListener('input',    () => filtrarTabla('tabla-usuarios','count-usuarios'));
document.getElementById('filtro-rol')?.addEventListener('change',       () => filtrarTabla('tabla-usuarios','count-usuarios'));
document.getElementById('filtro-estado-usuario')?.addEventListener('change', () => filtrarTabla('tabla-usuarios','count-usuarios'));

/* ════════════════════════════════════════════════════════
   CAMBIAR ESTADO DE REPORTE
════════════════════════════════════════════════════════ */
document.querySelectorAll('.select-estado-reporte').forEach(sel => {
    sel.addEventListener('change', async function() {
        const id     = this.dataset.id;
        const estado = this.value;
        const row    = this.closest('tr');
        try {
            const fd = new FormData();
            fd.append('reporte_id', id);
            fd.append('estado', estado);
            const res = await fetch('actualizar_estado_reporte.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                row.dataset.estado = estado;
                const badge = row.querySelector('.badge');
                if (badge) {
                    badge.className = 'badge badge-' + estado;
                    badge.textContent = estado.replace('_',' ').replace(/\b\w/g, c => c.toUpperCase());
                }
                showToast('Estado actualizado', 'success');
            } else {
                showToast(data.mensaje || 'Error al actualizar', 'error');
            }
        } catch(e) {
            showToast('Error de conexión', 'error');
        }
    });
});

/* ════════════════════════════════════════════════════════
   ELIMINAR REPORTE (admin)
════════════════════════════════════════════════════════ */
document.querySelectorAll('.eliminar-reporte').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        openModal('Eliminar reporte', '¿Seguro que deseas eliminar este reporte? Se eliminarán también sus comentarios y likes.', 'btn-danger', async () => {
            const fd = new FormData();
            fd.append('reporte_id', id);
            fd.append('accion', 'eliminar_reporte_admin');
            try {
                const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    btn.closest('tr').remove();
                    showToast('Reporte eliminado', 'success');
                } else {
                    showToast(data.mensaje || 'Error', 'error');
                }
            } catch(e) {
                showToast('Error de conexión', 'error');
            }
        });
    });
});

/* ════════════════════════════════════════════════════════
   TOGGLE USUARIO (activar / desactivar)
════════════════════════════════════════════════════════ */
document.querySelectorAll('.toggle-usuario').forEach(btn => {
    btn.addEventListener('click', function() {
        const id     = this.dataset.id;
        const activo = this.dataset.estado === '1';
        const accion = activo ? 'desactivar' : 'activar';
        openModal(`${accion.charAt(0).toUpperCase()+accion.slice(1)} usuario`,
            `¿Deseas ${accion} este usuario?`,
            activo ? 'btn-danger' : 'btn-success',
            async () => {
                const fd = new FormData();
                fd.append('accion', 'toggle_usuario');
                fd.append('usuario_id', id);
                fd.append('estado', activo ? '0' : '1');
                try {
                    const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.ok) {
                        const nuevoEstado = !activo;
                        this.dataset.estado = nuevoEstado ? '1' : '0';
                        this.title = nuevoEstado ? 'Desactivar' : 'Activar';
                        this.querySelector('i').className = 'fas fa-' + (nuevoEstado ? 'ban' : 'check');
                        const badge = document.getElementById('estado-badge-' + id);
                        if (badge) {
                            badge.className = 'badge badge-' + (nuevoEstado ? 'activo' : 'inactivo');
                            badge.textContent = nuevoEstado ? 'Activo' : 'Inactivo';
                        }
                        const row = this.closest('tr');
                        if (row) row.dataset.estado = nuevoEstado ? '1' : '0';
                        showToast('Usuario ' + (nuevoEstado ? 'activado' : 'desactivado'), 'success');
                    } else {
                        showToast(data.mensaje || 'Error', 'error');
                    }
                } catch(e) {
                    showToast('Error de conexión', 'error');
                }
            }
        );
    });
});

/* ════════════════════════════════════════════════════════
   CAMBIAR ROL DE USUARIO
════════════════════════════════════════════════════════ */
document.querySelectorAll('.cambiar-rol').forEach(btn => {
    btn.addEventListener('click', function() {
        const id      = this.dataset.id;
        const rolAct  = this.dataset.rol;
        const nuevoRol = rolAct === 'admin' ? 'ciudadano' : 'admin';
        openModal('Cambiar rol',
            `¿Cambiar este usuario de <strong>${rolAct}</strong> a <strong>${nuevoRol}</strong>?`,
            'btn-warning',
            async () => {
                const fd = new FormData();
                fd.append('accion', 'cambiar_rol');
                fd.append('usuario_id', id);
                fd.append('rol', nuevoRol);
                try {
                    const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.ok) {
                        this.dataset.rol = nuevoRol;
                        const row  = this.closest('tr');
                        if (row) {
                            row.dataset.rol = nuevoRol;
                            const badge = row.querySelector('.badge-admin, .badge-ciudadano');
                            if (badge) {
                                badge.className = 'badge badge-' + nuevoRol;
                                badge.textContent = nuevoRol.charAt(0).toUpperCase() + nuevoRol.slice(1);
                            }
                        }
                        showToast('Rol actualizado a ' + nuevoRol, 'success');
                    } else {
                        showToast(data.mensaje || 'Error', 'error');
                    }
                } catch(e) {
                    showToast('Error de conexión', 'error');
                }
            }
        );
    });
});

/* ════════════════════════════════════════════════════════
   NOTIFICAR ENTIDAD
════════════════════════════════════════════════════════ */
function selectEntity(card) {
    document.querySelectorAll('.entity-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('entidad-sel').value   = card.dataset.entity;
    document.getElementById('entidad-label').value = card.dataset.label;
}

document.getElementById('notif-mensaje')?.addEventListener('input', function() {
    document.getElementById('notif-chars').textContent = this.value.length;
});

async function enviarNotificacion(e) {
    e.preventDefault();
    const entidad = document.getElementById('entidad-sel').value;
    if (!entidad) { showToast('Selecciona una entidad destinataria', 'warning'); return; }

    const fd = new FormData();
    fd.append('accion',        'guardar_notificacion_entidad');
    fd.append('entidad',       document.getElementById('entidad-label').value);
    fd.append('prioridad',     document.getElementById('notif-prioridad').value);
    fd.append('reporte_id',    document.getElementById('notif-reporte').value);
    fd.append('asunto',        document.getElementById('notif-asunto').value);
    fd.append('mensaje',       document.getElementById('notif-mensaje').value);

    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando…';

    try {
        const res  = await fetch('api_admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            showToast('Notificación enviada correctamente', 'success');
            agregarNotifTimeline(data.notificacion);
            document.getElementById('form-notificar').reset();
            document.getElementById('notif-chars').textContent = '0';
            document.querySelectorAll('.entity-card').forEach(c => c.classList.remove('selected'));
            document.getElementById('entidad-sel').value   = '';
            document.getElementById('entidad-label').value = '';
            const count = document.getElementById('count-notifs');
            if (count) count.textContent = parseInt(count.textContent || '0') + 1;
        } else {
            showToast(data.mensaje || 'Error al enviar', 'error');
        }
    } catch(err) {
        showToast('Error de conexión', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Notificación';
    }
}

function agregarNotifTimeline(n) {
    const tl    = document.getElementById('notif-timeline');
    const empty = tl.querySelector('.empty-state');
    if (empty) empty.remove();

    const prioColors = { alta:'#e74c3c', media:'#f39c12', baja:'#27ae60' };

    const item = document.createElement('div');
    item.className = 'timeline-item';
    item.innerHTML = `
        <div class="timeline-dot ${n.prioridad}"></div>
        <div class="timeline-content">
            <div class="timeline-header">
                <span class="timeline-title">${n.entidad}</span>
                <div style="display:flex;gap:6px;">
                    <span class="badge badge-${n.prioridad}">${n.prioridad.charAt(0).toUpperCase()+n.prioridad.slice(1)}</span>
                    <span class="badge badge-enviada"><i class="fas fa-check"></i> Enviada</span>
                </div>
            </div>
            <div style="font-weight:600;font-size:.85rem;margin-bottom:4px;">${n.asunto}</div>
            <div class="timeline-body">${n.mensaje.substring(0,200)}${n.mensaje.length>200?'…':''}</div>
            <div class="timeline-meta" style="margin-top:6px;">
                <i class="fas fa-user-shield"></i> ${n.admin}
                &nbsp;·&nbsp;
                <i class="fas fa-clock"></i> Ahora
            </div>
        </div>`;
    tl.insertBefore(item, tl.firstChild);
}

/* ════════════════════════════════════════════════════════
   MAPA ADMIN — usa el componente compartido popup-reporte.js
════════════════════════════════════════════════════════ */
let mapaInst    = null;
let mapaMarkers = [];

function initMapa() {
    if (mapaInst) { mapaInst.invalidateSize(); return; }

    setTimeout(() => {
        const cont = document.getElementById('adminMap');
        if (!cont) return;
        if (cont._leaflet_id) { cont._leaflet_id = null; cont.innerHTML = ''; }

        mapaInst = L.map('adminMap', { preferCanvas: true }).setView([4.142, -73.626], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(mapaInst);

        cargarMarcadores(REPORTES_DATA);
    }, 200);
}

function cargarMarcadores(data) {
    mapaMarkers.forEach(m => m.remove());
    mapaMarkers = [];

    const filtro = document.getElementById('mapa-filtro-estado')?.value || '';

    data.filter(r =>
        r.lat && r.lng &&
        !isNaN(parseFloat(r.lat)) && !isNaN(parseFloat(r.lng)) &&
        (filtro === '' || r.estado === filtro)
    ).forEach(r => {
        const emoji  = obtenerEmojiPorTipo(r.tipo);
        const icon   = crearIconoReporte(emoji, r.estado);
        const popup  = crearPopupReporte(r, {
            btnTexto: '💬 Ver Comentarios',
            btnHref:  `/views/usuario/alertas.php?comentarios=${r.id}`
        });

        const m = L.marker([parseFloat(r.lat), parseFloat(r.lng)], { icon })
            .bindPopup(popup, { maxWidth: 320, className: 'popup-reporte-wrapper' });
        m.addTo(mapaInst);
        mapaMarkers.push(m);
    });

    if (mapaMarkers.length > 0) {
        const group = L.featureGroup(mapaMarkers);
        mapaInst.fitBounds(group.getBounds().pad(0.12));
    }
}

function filtrarMapa()  { if (mapaInst) cargarMarcadores(REPORTES_DATA); }
function recargarMapa() { if (mapaInst) { cargarMarcadores(REPORTES_DATA); showToast('Mapa actualizado', 'success'); } }

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
</script>

<?php $logoutUrl = '../../index.php'; include __DIR__ . '/../components/modal-logout.php'; ?>
</body>
</html>
