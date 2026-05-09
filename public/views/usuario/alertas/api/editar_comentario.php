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

try {
    if (!isset($_SESSION['usuario_id'])) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No has iniciado sesión.'
        ], 401);
    }

    $comentarioIdTexto = $_POST['comentario_id'] ?? '';
    $nuevoTexto = trim($_POST['comentario'] ?? '');

    if (!preg_match('/^[a-f\d]{24}$/i', $comentarioIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de comentario inválido.'
        ], 400);
    }

    if ($nuevoTexto === '') {
        responderJson([
            'ok' => false,
            'mensaje' => 'El comentario no puede estar vacío.'
        ], 400);
    }

    if (mb_strlen($nuevoTexto) > 500) {
        responderJson([
            'ok' => false,
            'mensaje' => 'El comentario no puede superar 500 caracteres.'
        ], 400);
    }

    $db = conectarMongoDB();

    $comentarios = $db->comentarios_reporte;

    $comentarioId = new MongoDB\BSON\ObjectId($comentarioIdTexto);
    $usuarioId = new MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id']);

    $comentario = $comentarios->findOne([
        '_id' => $comentarioId
    ]);

    if (!$comentario) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Comentario no encontrado.'
        ], 404);
    }

    if (!isset($comentario['usuario_id']) || (string) $comentario['usuario_id'] !== (string) $usuarioId) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No puedes editar comentarios de otros usuarios.'
        ], 403);
    }

    $comentarios->updateOne(
        [
            '_id' => $comentarioId,
            'usuario_id' => $usuarioId
        ],
        [
            '$set' => [
                'comentario' => $nuevoTexto,
                'editado' => true,
                'fecha_edicion' => new MongoDB\BSON\UTCDateTime()
            ]
        ]
    );

    responderJson([
        'ok' => true,
        'mensaje' => 'Comentario actualizado.'
    ]);

} catch (Throwable $e) {
    responderJson([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}
