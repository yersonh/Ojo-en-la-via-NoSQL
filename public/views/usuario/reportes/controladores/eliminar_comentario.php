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

function obtenerIdsDescendientes($comentarios, MongoDB\BSON\ObjectId $comentarioId)
{
    $ids = [$comentarioId];
    $pendientes = [$comentarioId];

    while (!empty($pendientes)) {
        $padreActual = array_shift($pendientes);

        $hijos = $comentarios->find([
            'comentario_padre_id' => $padreActual
        ]);

        foreach ($hijos as $hijo) {
            if (!isset($hijo['_id'])) {
                continue;
            }

            $hijoId = $hijo['_id'];
            $ids[] = $hijoId;
            $pendientes[] = $hijoId;
        }
    }

    return $ids;
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

    $comentarios = $db->comentarios_reporte;
    $likesComentario = $db->likes_comentario;

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
            'mensaje' => 'No puedes eliminar comentarios de otros usuarios.'
        ], 403);
    }

    $idsAEliminar = obtenerIdsDescendientes($comentarios, $comentarioId);

    $comentarios->deleteMany([
        '_id' => [
            '$in' => $idsAEliminar
        ]
    ]);

    $likesComentario->deleteMany([
        'comentario_id' => [
            '$in' => $idsAEliminar
        ]
    ]);

    responderJson([
        'ok' => true,
        'mensaje' => 'Comentario eliminado.',
        'comentarios_eliminados' => count($idsAEliminar)
    ]);

} catch (Throwable $e) {
    responderJson([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}
