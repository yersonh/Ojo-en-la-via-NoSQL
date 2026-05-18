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

$filename = 'exportacion_' . $tipo . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

if ($tipo === 'reportes') {
    fputcsv($out, ['ID', 'Tipo', 'Descripción', 'Estado', 'Fecha', 'Usuario', 'Dirección', 'Latitud', 'Longitud']);

    $cursor = $db->Reportes->aggregate([
        [
            '$lookup' => [
                'from' => 'usuario',
                'localField' => 'usuario_id',
                'foreignField' => '_id',
                'as' => 'usuario'
            ]
        ],
        ['$sort' => ['fecha_reporte' => -1]]
    ]);

    foreach ($cursor as $r) {
        $usuario = null;
        if (!empty($r['usuario'])) {
            foreach ($r['usuario'] as $u) { $usuario = $u; break; }
        }
        $nombre = 'No disponible';
        if ($usuario) {
            $nombre = (string)($usuario['nombre_completo'] ?? $usuario['nombre'] ?? $usuario['nombre_usuario'] ?? 'No disponible');
        }

        $fecha = '';
        if (!empty($r['fecha_reporte']) && $r['fecha_reporte'] instanceof \MongoDB\BSON\UTCDateTime) {
            $fecha = $r['fecha_reporte']->toDateTime()
                ->setTimezone(new DateTimeZone('America/Bogota'))
                ->format('d/m/Y H:i');
        }

        $lat = $r['ubicacion']['latitud'] ?? $r['ubicacion']['lat'] ?? $r['latitud'] ?? '';
        $lng = $r['ubicacion']['longitud'] ?? $r['ubicacion']['lng'] ?? $r['longitud'] ?? '';

        fputcsv($out, [
            (string)$r['_id'],
            $r['tipo'] ?? $r['tipo_incidente'] ?? '',
            $r['descripcion'] ?? '',
            $r['estado'] ?? '',
            $fecha,
            $nombre,
            $r['direccion_texto'] ?? '',
            $lat,
            $lng,
        ]);
    }

} elseif ($tipo === 'usuarios') {
    fputcsv($out, ['ID', 'Nombre', 'Email', 'Teléfono', 'Rol', 'Estado', 'Fecha de Creación']);

    $cursor = $db->usuario->find([], ['sort' => ['fecha_creacion' => -1]]);

    foreach ($cursor as $u) {
        $fecha = '';
        if (!empty($u['fecha_creacion']) && $u['fecha_creacion'] instanceof \MongoDB\BSON\UTCDateTime) {
            $fecha = $u['fecha_creacion']->toDateTime()
                ->setTimezone(new DateTimeZone('America/Bogota'))
                ->format('d/m/Y H:i');
        }

        $estado = '';
        if (isset($u['estado'])) {
            $estado = $u['estado'] ? 'Activo' : 'Inactivo';
        }

        fputcsv($out, [
            (string)$u['_id'],
            $u['nombre_completo'] ?? $u['nombre'] ?? '',
            $u['email'] ?? '',
            $u['telefono'] ?? '',
            $u['rol'] ?? '',
            $estado,
            $fecha,
        ]);
    }

} elseif ($tipo === 'analiticas') {
    fputcsv($out, ['ID Reporte', 'Tipo', 'Estado', 'Fecha', 'Hora', 'Día de la semana', 'Dirección']);

    $cursor = $db->Reportes->find([], ['sort' => ['fecha_reporte' => -1]]);

    $dias = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes',
             'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];

    foreach ($cursor as $r) {
        $fecha = '';
        $hora = '';
        $dia = '';

        if (!empty($r['fecha_reporte']) && $r['fecha_reporte'] instanceof \MongoDB\BSON\UTCDateTime) {
            $dt = $r['fecha_reporte']->toDateTime()->setTimezone(new DateTimeZone('America/Bogota'));
            $fecha = $dt->format('d/m/Y');
            $hora  = $dt->format('H:i');
            $dia   = $dias[$dt->format('l')] ?? $dt->format('l');
        }

        fputcsv($out, [
            (string)$r['_id'],
            $r['tipo'] ?? $r['tipo_incidente'] ?? '',
            $r['estado'] ?? '',
            $fecha,
            $hora,
            $dia,
            $r['direccion_texto'] ?? '',
        ]);
    }
}

fclose($out);
exit;
