<?php
session_start();

require_once __DIR__ . '/../../../../../config/conexion.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function filtroPropietarioReporteEliminar(MongoDB\BSON\ObjectId $reporteId, $usuarioIdTexto, MongoDB\BSON\ObjectId $usuarioId)
{
    return [
        '_id' => $reporteId,
        '$or' => [
            ['usuario_id' => $usuarioIdTexto],
            ['usuario_id' => $usuarioId],
            ['usuario_creador_id' => $usuarioIdTexto],
            ['usuario_creador_id' => $usuarioId],
        ]
    ];
}

try {
    if (!isset($_SESSION['usuario_id'])) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No has iniciado sesion.'
        ], 401);
    }

    $reporteIdTexto = $_POST['reporte_id'] ?? '';

    if (!preg_match('/^[a-f\d]{24}$/i', $reporteIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de reporte invalido.'
        ], 400);
    }

    $db = conectarMongoDB();

    $reportes = $db->Reportes;

    $reporteId = new MongoDB\BSON\ObjectId($reporteIdTexto);
    $usuarioIdTexto = (string) $_SESSION['usuario_id'];
    $usuarioId = new MongoDB\BSON\ObjectId($usuarioIdTexto);
    $filtro = filtroPropietarioReporteEliminar($reporteId, $usuarioIdTexto, $usuarioId);

    $reporte = $reportes->findOne($filtro);

    if (!$reporte) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Reporte no encontrado o no tienes permiso para eliminarlo.'
        ], 404);
    }

    $reportes->deleteOne($filtro);

    responderJson([
        'ok' => true,
        'mensaje' => 'Reporte eliminado.'
    ]);
} catch (Throwable $e) {
    responderJson([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}
