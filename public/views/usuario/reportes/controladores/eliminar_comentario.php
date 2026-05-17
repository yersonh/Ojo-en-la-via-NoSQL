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

function obtenerIdsDescendientes(array $comentarios, string $comentarioId): array
{
    $ids = [$comentarioId];
    $pendientes = [$comentarioId];

    while (!empty($pendientes)) {
        $padreActual = array_shift($pendientes);

        foreach ($comentarios as $comentario) {
            $padreId = $comentario['comentario_padre_id'] ?? null;

            if ($padreId !== null && (string) $padreId === $padreActual && isset($comentario['_id'])) {
                $hijoId = (string) $comentario['_id'];
                $ids[] = $hijoId;
                $pendientes[] = $hijoId;
            }
        }
    }

    return $ids;
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
    $comentarioBase = null;

    foreach ($comentarios as $comentario) {
        if (isset($comentario['_id']) && (string) $comentario['_id'] === $comentarioIdTexto) {
            $comentarioBase = $comentario;
            break;
        }
    }

    if (!$comentarioBase) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Comentario no encontrado.'
        ], 404);
    }

    if (!isset($comentarioBase['usuario_id']) || (string) $comentarioBase['usuario_id'] !== $usuarioIdTexto) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No puedes eliminar comentarios de otros usuarios.'
        ], 403);
    }

    $idsAEliminar = obtenerIdsDescendientes($comentarios, $comentarioIdTexto);
    $comentariosFiltrados = array_values(array_filter($comentarios, function ($comentario) use ($idsAEliminar) {
        return !isset($comentario['_id']) || !in_array((string) $comentario['_id'], $idsAEliminar, true);
    }));

    $reportes->updateOne(
        ['_id' => $reporte['_id']],
        ['$set' => ['comentarios' => $comentariosFiltrados]]
    );

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
