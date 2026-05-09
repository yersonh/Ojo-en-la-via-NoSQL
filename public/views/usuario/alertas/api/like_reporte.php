<?php
session_start();

require_once __DIR__ . '/../../../../../config/conexion.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/notificaciones_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'No has iniciado sesión.'
    ]);
    exit;
}

try {
    $reporteIdTexto = $_POST['reporte_id'] ?? '';

    if (!preg_match('/^[a-f\d]{24}$/i', $reporteIdTexto)) {
        throw new Exception('ID de reporte inválido.');
    }

    $db = conectarMongoDB();
    $likesReportes = $db->likes_reporte;
    $reportes = $db->reportes;

    $likesReportes->createIndex(
        [
            'reporte_id' => 1,
            'usuario_id' => 1
        ],
        [
            'unique' => true
        ]
    );

    $reporteId = new \MongoDB\BSON\ObjectId($reporteIdTexto);
    $usuarioId = new \MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id']);

    $likeExistente = $likesReportes->findOne([
        'reporte_id' => $reporteId,
        'usuario_id' => $usuarioId
    ]);

    if ($likeExistente) {
        $likesReportes->deleteOne([
            'reporte_id' => $reporteId,
            'usuario_id' => $usuarioId
        ]);

        $liked = false;
    } else {
        $likesReportes->insertOne([
            'reporte_id' => $reporteId,
            'usuario_id' => $usuarioId,
            'fecha_like' => new \MongoDB\BSON\UTCDateTime()
        ]);

        $liked = true;

        $reporte = $reportes->findOne([
            '_id' => $reporteId
        ]);

        $usuarioDestino = $reporte['usuario_id'] ?? $reporte['usuario_creador_id'] ?? null;

        if ($reporte && $usuarioDestino) {
            crearNotificacionUsuario(
                $db,
                $usuarioDestino,
                $usuarioId,
                'like_reporte',
                'Nuevo like',
                obtenerNombreNotificador() . ' le dio like a tu reporte.',
                $reporteId
            );
        }
    }

    $totalLikes = $likesReportes->countDocuments([
        'reporte_id' => $reporteId
    ]);

    echo json_encode([
        'ok' => true,
        'liked' => $liked,
        'likes' => $totalLikes
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ]);
}
