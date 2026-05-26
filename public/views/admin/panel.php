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

// ── UTILERÍA PARA SEGURIDAD DE DATOS ───────────────────────────────────────
$safeString = function($val, $default = '') {
    if ($val === null) return $default;
    if (is_scalar($val)) return (string)$val;
    if (is_object($val) && method_exists($val, '__toString')) return (string)$val;
    if ($val instanceof \MongoDB\BSON\UTCDateTime) {
        return $val->toDateTime()->setTimezone(new DateTimeZone('America/Bogota'))->format('d/m/Y H:i');
    }
    return $default;
};

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
    $tiposData[] = ['tipo' => $safeString($t['_id'] ?? 'Sin tipo'), 'total' => (int)$t['total']];
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

// ── DATOS ANALÍTICAS ───────────────────────────────────────────────────────
$analyticsRaw = [];
foreach ($reportes->aggregate([
    ['$addFields' => ['ts' => ['$toDate' => '$_id']]],
    ['$addFields' => [
        'hora'     => ['$hour'      => ['date' => '$ts', 'timezone' => 'America/Bogota']],
        'diaSem'   => ['$dayOfWeek' => ['date' => '$ts', 'timezone' => 'America/Bogota']],
        'fechaStr' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$ts', 'timezone' => 'America/Bogota']],
    ]],
    ['$project' => [
        'tipo'   => ['$ifNull' => ['$tipo', '$tipo_incidente']],
        'estado' => 1,
        'hora'   => 1,
        'diaSem' => 1,
        'fechaStr' => 1,
        'barrio' => ['$trim' => ['input' => ['$arrayElemAt' => [
            ['$split' => [['$ifNull' => ['$direccion_texto', '']], ',']], 2
        ]]]],
    ]],
]) as $r) {
    $barrio = trim($safeString($r['barrio'] ?? ''));
    if ($barrio !== '' && !preg_match('/^\d/', $barrio)) {
        $barrioFinal = $barrio;
    } else {
        $barrioFinal = '';
    }
    $analyticsRaw[] = [
        'tipo'  => $safeString($r['tipo']    ?? 'Sin tipo'),
        'estado'=> $safeString($r['estado']  ?? 'pendiente'),
        'hora'  => (int)($r['hora']       ?? 0),
        'dia'   => (int)($r['diaSem']     ?? 1),
        'fecha' => $safeString($r['fechaStr']?? ''),
        'barrio'=> $barrioFinal,
    ];
}

// ── LISTA DE REPORTES (tabla) ──────────────────────────────────────────────
$usuariosMap = [];
foreach ($db->usuario->find([], ['projection' => ['_id' => 1, 'nombre_completo' => 1, 'email' => 1]]) as $u) {
    $usuariosMap[(string)$u['_id']] = $safeString($u['nombre_completo'] ?? $u['email'] ?? 'N/A');
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
    $ubicacion = $r['ubicacion'] ?? [];
    $lat = (string)($ubicacion['lat']      ?? $ubicacion['latitud']  ?? $r['latitud']  ?? '');
    $lng = (string)($ubicacion['lng']      ?? $ubicacion['longitud'] ?? $r['longitud'] ?? '');
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
        'tipo'          => $safeString($r['tipo'] ?? $r['tipo_incidente'] ?? 'Sin tipo'),
        'descripcion'   => $safeString($r['descripcion'] ?? ''),
        'estado'        => $safeString($r['estado'] ?? 'pendiente'),
        'fecha'         => $fecha,
        'usuario'       => $autor,
        'usuario_nombre'=> $autor,
        'lat'           => $lat,
        'lng'           => $lng,
        'imagen'        => $foto,
        'foto'          => $foto,
    ];
}

// ── LISTA DE USUARIOS ──────────────────────────────────────────────────────
$usuariosList = [];
foreach ($db->usuario->find([], ['sort' => ['_id' => -1], 'limit' => 300]) as $u) {
    $usuariosList[] = [
        'id'       => (string)$u['_id'],
        'nombre'   => $safeString($u['nombre_completo'] ?? 'N/A'),
        'email'    => $safeString($u['email'] ?? 'N/A'),
        'telefono' => $safeString($u['telefono'] ?? 'N/A'),
        'rol'      => $safeString($u['rol'] ?? 'ciudadano'),
        'estado'   => (bool)($u['estado'] ?? true),
        'fecha'    => $safeString($u['fecha_creacion'] ?? 'N/A'),
    ];
}

// ── REGLAS DE NOTIFICACIÓN AUTOMÁTICA ─────────────────────────────────────
$reglasList = [];
foreach ($db->reglas_notificacion->find([], ['sort' => ['fecha_creacion' => -1]]) as $r) {
    $reglasList[] = [
        'id'             => (string)$r['_id'],
        'tipo_incidente' => $safeString($r['tipo_incidente'] ?? ''),
        'entidad'        => $safeString($r['entidad']        ?? ''),
        'asunto'         => $safeString($r['asunto']         ?? ''),
        'mensaje'        => $safeString($r['mensaje']        ?? ''),
        'prioridad'      => $safeString($r['prioridad']      ?? 'media'),
        'activa'         => (bool)($r['activa']           ?? true),
    ];
}

// ── HISTORIAL DE NOTIFICACIONES A ENTIDADES (embebidas en Reportes) ────────
$notifEntList = [];
foreach ($db->Reportes->aggregate([
    ['$match'   => ['notificaciones_entidades.0' => ['$exists' => true]]],
    ['$unwind'  => '$notificaciones_entidades'],
    ['$sort'    => ['notificaciones_entidades.fecha' => -1]],
    ['$limit'   => 100],
    ['$project' => ['reporte_id' => '$_id', 'ne' => '$notificaciones_entidades']],
]) as $r) {
    $ne    = $r['ne'];
    $fecha = 'N/A';
    if (isset($ne['fecha']) && $ne['fecha'] instanceof UTCDateTime) {
        $fecha = $ne['fecha']->toDateTime()
            ->setTimezone(new DateTimeZone('America/Bogota'))
            ->format('d/m/Y H:i');
    }
    $notifEntList[] = [
        'id'        => isset($ne['_id'])    ? (string) $ne['_id']       : '',
        'entidad'   => $safeString($ne['entidad']      ?? ''),
        'prioridad' => $safeString($ne['prioridad']    ?? 'media'),
        'asunto'    => $safeString($ne['asunto']       ?? ''),
        'mensaje'   => $safeString($ne['mensaje']      ?? ''),
        'reporte_id'=> isset($r['reporte_id']) ? (string) $r['reporte_id'] : null,
        'fecha'     => $fecha,
        'origen'    => $safeString($ne['admin_nombre'] ?? 'Sistema automático'),
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
<link rel="stylesheet" href="/views/compartido/css/popup-reporte.css">
<link rel="stylesheet" href="css/panel.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/views/compartido/js/popup-reporte.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        <a onclick="showTab('analytics')">
            <i class="fas fa-chart-line"></i><span>Analíticas</span>
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
        <?php include __DIR__ . '/partials/tab-dashboard.php'; ?>
        <?php include __DIR__ . '/partials/tab-reportes.php'; ?>
        <?php include __DIR__ . '/partials/tab-usuarios.php'; ?>
        <?php include __DIR__ . '/partials/tab-notificar.php'; ?>
        <?php include __DIR__ . '/partials/tab-analytics.php'; ?>
        <?php include __DIR__ . '/partials/tab-mapa.php'; ?>
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
const ANALYTICS_RAW = <?= json_encode($analyticsRaw, JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- ════════════════════ JS MODULES ════════════════════ -->
<script src="js/panel-ui.js"></script>
<script src="js/panel-charts.js"></script>
<script src="js/panel-tables.js"></script>
<script src="js/panel-reglas.js"></script>
<script src="js/panel-analytics.js"></script>
<script src="js/panel-mapa.js"></script>

<?php $logoutUrl = '../../index.php'; include __DIR__ . '/../components/modal-logout.php'; ?>
</body>
</html>
