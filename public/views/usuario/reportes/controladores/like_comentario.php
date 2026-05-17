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

    if (!preg_match('/^[a-f\d]{24}$/i', $comentarioIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de comentario invalido.'
        ], 400);
    }

    $db = conectarMongoDB();
    $reportes = $db->Reportes;

    $comentarioId = new MongoDB\BSON\ObjectId($comentarioIdTexto);
    $usuarioIdTexto = (string) $_SESSION['usuario_id'];
    $usuarioId = new MongoDB\BSON\ObjectId($usuarioIdTexto);

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
    $liked = false;
    $totalLikes = 0;

    foreach ($comentarios as &$comentario) {
        if (!isset($comentario['_id']) || (string) $comentario['_id'] !== (string) $comentarioId) {
            continue;
        }

        $likes = normalizarArray($comentario['likes'] ?? []);
        $indiceLike = null;

        foreach ($likes as $indice => $like) {
            $usuarioLike = $like['usuario_id'] ?? $like['usuario_origen_id'] ?? null;

            if ($usuarioLike !== null && (string) $usuarioLike === $usuarioIdTexto) {
                $indiceLike = $indice;
                break;
            }
        }

        if ($indiceLike !== null) {
            array_splice($likes, $indiceLike, 1);
            $liked = false;
        } else {
            $likes[] = [
                'usuario_id' => $usuarioId,
                'fecha_like' => new MongoDB\BSON\UTCDateTime()
            ];
            $liked = true;
        }

        $comentario['likes'] = array_values($likes);
        $totalLikes = count($comentario['likes']);
        break;
    }

    unset($comentario);

    $reportes->updateOne(
        ['_id' => $reporte['_id']],
        ['$set' => ['comentarios' => array_values($comentarios)]]
    );

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
