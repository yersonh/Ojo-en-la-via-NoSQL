<?php
session_start();

require_once __DIR__ . '/../../../../../config/conexion.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../modelos/notificaciones_modelo.php';

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
    $reportes = $db->Reportes;

    $reporteId = new \MongoDB\BSON\ObjectId($reporteIdTexto);
    $usuarioIdTexto = (string) $_SESSION['usuario_id'];
    $usuarioId = new \MongoDB\BSON\ObjectId($usuarioIdTexto);

    $reporte = $reportes->findOne([
        '_id' => $reporteId
    ]);

    if (!$reporte) {
        throw new Exception('Reporte no encontrado.');
    }

    $likeExistente = $reportes->findOne([
        '_id' => $reporteId,
        '$or' => [
            ['likes.usuario_id' => $usuarioId],
            ['likes.usuario_id' => $usuarioIdTexto],
            ['likes.usuario_origen_id' => $usuarioId],
            ['likes.usuario_origen_id' => $usuarioIdTexto]
        ]
    ]);

    if ($likeExistente) {
        $reportes->updateOne(
            ['_id' => $reporteId],
            [
                '$pull' => [
                    'likes' => [
                        '$or' => [
                            ['usuario_id' => $usuarioId],
                            ['usuario_id' => $usuarioIdTexto],
                            ['usuario_origen_id' => $usuarioId],
                            ['usuario_origen_id' => $usuarioIdTexto]
                        ]
                    ]
                ]
            ]
        );

        $liked = false;
    } else {
        $resultadoLike = $reportes->updateOne(
            [
                '_id' => $reporteId,
                '$nor' => [
                    ['likes.usuario_id' => $usuarioId],
                    ['likes.usuario_id' => $usuarioIdTexto],
                    ['likes.usuario_origen_id' => $usuarioId],
                    ['likes.usuario_origen_id' => $usuarioIdTexto]
                ]
            ],
            [
                '$push' => [
                    'likes' => [
                        'usuario_id' => $usuarioId,
                        'fecha_like' => new \MongoDB\BSON\UTCDateTime()
                    ]
                ]
            ]
        );

        $liked = true;

        $usuarioDestino = $reporte['usuario_id'] ?? $reporte['usuario_creador_id'] ?? null;

        if ($resultadoLike->getModifiedCount() > 0 && $usuarioDestino) {
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

    $reporteActualizado = $reportes->findOne(['_id' => $reporteId]);
    $likesActuales = $reporteActualizado['likes'] ?? [];

    if ($likesActuales instanceof \MongoDB\Model\BSONArray) {
        $likesActuales = $likesActuales->getArrayCopy();
    }

    $totalLikes = is_array($likesActuales) ? count($likesActuales) : 0;

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
