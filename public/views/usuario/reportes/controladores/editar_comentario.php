<?php
session_start();

require_once __DIR__ . '/../../../../../config/conexion.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/comentario_filtro.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizarArray($valor): array
{
    if ($valor instanceof MongoDB\Model\BSONArray) {
        return $valor->getArrayCopy();
    }

    return is_array($valor) ? $valor : [];
}

try {
    if (!isset($_SESSION['usuario_id'])) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No has iniciado sesion.'
        ], 401);
    }

    $comentarioIdTexto = $_POST['comentario_id'] ?? '';
    $nuevoTexto = trim($_POST['comentario'] ?? '');

    if (!preg_match('/^[a-f\d]{24}$/i', $comentarioIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de comentario invalido.'
        ], 400);
    }

    if ($nuevoTexto === '') {
        responderJson([
            'ok' => false,
            'mensaje' => 'El comentario no puede estar vacio.'
        ], 400);
    }

    if (mb_strlen($nuevoTexto) > 500) {
        responderJson([
            'ok' => false,
            'mensaje' => 'El comentario no puede superar 500 caracteres.'
        ], 400);
    }

    if (comentarioContieneGroseria($nuevoTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Tu comentario contiene palabras no permitidas.'
        ], 400);
    }

    $db = conectarMongoDB();
    $reportes = $db->Reportes;

    $comentarioId = new MongoDB\BSON\ObjectId($comentarioIdTexto);
    $usuarioIdTexto = (string) $_SESSION['usuario_id'];

    $reporte = $reportes->findOne([
        'comentarios._id' => $comentarioId
    ]);

    if (!$reporte) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Comentario no encontrado.'
        ], 404);
    }

    $comentarios = normalizarArray($reporte['comentarios'] ?? []);
    $encontrado = false;

    foreach ($comentarios as &$comentario) {
        if (!isset($comentario['_id']) || (string) $comentario['_id'] !== (string) $comentarioId) {
            continue;
        }

        if (!isset($comentario['usuario_id']) || (string) $comentario['usuario_id'] !== $usuarioIdTexto) {
            responderJson([
                'ok' => false,
                'mensaje' => 'No puedes editar comentarios de otros usuarios.'
            ], 403);
        }

        $comentario['comentario'] = $nuevoTexto;
        $comentario['editado'] = true;
        $comentario['fecha_edicion'] = new MongoDB\BSON\UTCDateTime();
        $encontrado = true;
        break;
    }

    unset($comentario);

    if (!$encontrado) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Comentario no encontrado.'
        ], 404);
    }

    $reportes->updateOne(
        ['_id' => $reporte['_id']],
        ['$set' => ['comentarios' => array_values($comentarios)]]
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
