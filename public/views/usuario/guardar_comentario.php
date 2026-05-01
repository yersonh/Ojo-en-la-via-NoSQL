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

    $reporteIdTexto = $_POST['reporte_id'] ?? '';
    $comentarioTexto = trim($_POST['comentario'] ?? '');

    if (!preg_match('/^[a-f\d]{24}$/i', $reporteIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de reporte inválido.'
        ], 400);
    }

    if ($comentarioTexto === '') {
        responderJson([
            'ok' => false,
            'mensaje' => 'El comentario no puede estar vacío.'
        ], 400);
    }

    if (mb_strlen($comentarioTexto) > 500) {
        responderJson([
            'ok' => false,
            'mensaje' => 'El comentario no puede superar 500 caracteres.'
        ], 400);
    }

    $db = conectarMongoDB();

    $comentarios = $db->comentarios_reporte;

    $reporteId = new MongoDB\BSON\ObjectId($reporteIdTexto);
    $usuarioId = new MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id']);

    $comentarios->insertOne([
        'reporte_id' => $reporteId,
        'usuario_id' => $usuarioId,
        'comentario' => $comentarioTexto,
        'fecha_comentario' => new MongoDB\BSON\UTCDateTime()
    ]);

    $totalComentarios = $comentarios->countDocuments([
        'reporte_id' => $reporteId
    ]);

    responderJson([
        'ok' => true,
        'mensaje' => 'Comentario guardado.',
        'total_comentarios' => $totalComentarios
    ]);

} catch (Throwable $e) {
    responderJson([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}