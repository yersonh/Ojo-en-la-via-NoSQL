<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../usuario/reportes/modelos/notificaciones_modelo.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? 'ciudadano') !== 'admin') {
        responderJson([
            'ok' => false,
            'mensaje' => 'No tienes permiso para actualizar reportes.'
        ], 403);
    }

    $reporteIdTexto = $_POST['reporte_id'] ?? '';
    $estado = trim($_POST['estado'] ?? '');

    if (!preg_match('/^[a-f\d]{24}$/i', $reporteIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de reporte invalido.'
        ], 400);
    }

    if ($estado === '') {
        responderJson([
            'ok' => false,
            'mensaje' => 'Selecciona un estado.'
        ], 400);
    }

    $db = conectarMongoDB();
    $reportes = $db->Reportes;
    $reporteId = new MongoDB\BSON\ObjectId($reporteIdTexto);
    $adminId = new MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id']);

    $reporte = $reportes->findOne([
        '_id' => $reporteId
    ]);

    if (!$reporte) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Reporte no encontrado.'
        ], 404);
    }

    $fechaEstado = new MongoDB\BSON\UTCDateTime();

    $reportes->updateOne(
        ['_id' => $reporteId],
        [
            '$set' => [
                'estado' => $estado,
                'fecha_estado' => $fechaEstado
            ],
            '$push' => [
                'historial_estados' => [
                    'estado_anterior' => $reporte['estado'] ?? null,
                    'estado_nuevo' => $estado,
                    'admin_id' => $adminId,
                    'fecha_estado' => $fechaEstado
                ]
            ]
        ]
    );

    $usuarioDestino = $reporte['usuario_id'] ?? $reporte['usuario_creador_id'] ?? null;

    if ($usuarioDestino) {
        crearNotificacionUsuario(
            $db,
            $usuarioDestino,
            $adminId,
            'estado_reporte',
            'Estado actualizado',
            'El estado de tu reporte cambió a ' . $estado . '.',
            $reporteId
        );
    }

    responderJson([
        'ok' => true,
        'mensaje' => 'Estado actualizado.',
        'estado' => $estado
    ]);
} catch (Throwable $e) {
    responderJson([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}
