<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (!isset($_SESSION['usuario_id'])) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No has iniciado sesión.'
        ], 401);
    }

    $comentarioIdTexto = $_POST['comentario_id'] ?? '';

    if (!preg_match('/^[a-f\d]{24}$/i', $comentarioIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de comentario inválido.'
        ], 400);
    }

    $db = conectarMongoDB();

    $comentariosReporte = $db->comentarios_reporte;
    $likesComentario = $db->likes_comentario;

    $comentarioId = new MongoDB\BSON\ObjectId($comentarioIdTexto);
    $usuarioId = new MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id']);

    $comentario = $comentariosReporte->findOne([
        '_id' => $comentarioId
    ]);

    if (!$comentario) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Comentario no encontrado.'
        ], 404);
    }

    $likeExistente = $likesComentario->findOne([
        'comentario_id' => $comentarioId,
        'usuario_id' => $usuarioId
    ]);

    if ($likeExistente) {
        $likesComentario->deleteOne([
            'comentario_id' => $comentarioId,
            'usuario_id' => $usuarioId
        ]);

        $liked = false;
    } else {
        $likesComentario->insertOne([
            'comentario_id' => $comentarioId,
            'usuario_id' => $usuarioId,
            'fecha_like' => new MongoDB\BSON\UTCDateTime()
        ]);

        $liked = true;
    }

    $totalLikes = $likesComentario->countDocuments([
        'comentario_id' => $comentarioId
    ]);

    responderJson([
        'ok' => true,
        'liked' => $liked,
        'total_likes' => $totalLikes
    ]);

} catch (Throwable $e) {
    responderJson([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}