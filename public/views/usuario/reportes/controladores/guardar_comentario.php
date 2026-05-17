<?php
session_start();

require_once __DIR__ . '/../../../../../config/conexion.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../modelos/notificaciones_modelo.php';

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
    $comentarioPadreTexto = $_POST['comentario_padre_id'] ?? '';

    if (!preg_match('/^[a-f\d]{24}$/i', $reporteIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de reporte inválido.'
        ], 400);
    }

    $comentarioPadreId = null;

    if ($comentarioPadreTexto !== '') {
        if (!preg_match('/^[a-f\d]{24}$/i', $comentarioPadreTexto)) {
            responderJson([
                'ok' => false,
                'mensaje' => 'ID de comentario padre inválido.'
            ], 400);
        }

        $comentarioPadreId = new MongoDB\BSON\ObjectId($comentarioPadreTexto);
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

    $reportes = $db->Reportes;

    $reporteId = new MongoDB\BSON\ObjectId($reporteIdTexto);
    $usuarioId = new MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id']);

    $reporte = $reportes->findOne([
        '_id' => $reporteId
    ]);

    if (!$reporte) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Reporte no encontrado.'
        ], 404);
    }

    if ($comentarioPadreId !== null) {
        $comentariosActuales = $reporte['comentarios'] ?? [];

        if ($comentariosActuales instanceof MongoDB\Model\BSONArray) {
            $comentariosActuales = $comentariosActuales->getArrayCopy();
        }

        $padreExiste = false;
        foreach ($comentariosActuales as $comentarioActual) {
            if (isset($comentarioActual['_id']) && (string) $comentarioActual['_id'] === (string) $comentarioPadreId) {
                $padreExiste = true;
                break;
            }
        }

        if (!$padreExiste) {
            responderJson([
                'ok' => false,
                'mensaje' => 'Comentario padre no encontrado.'
            ], 404);
        }
    }

    $comentarioId = new MongoDB\BSON\ObjectId();
    $comentarioNuevo = [
        '_id' => $comentarioId,
        'usuario_id' => $usuarioId,
        'comentario' => $comentarioTexto,
        'comentario_padre_id' => $comentarioPadreId,
        'fecha_comentario' => new MongoDB\BSON\UTCDateTime(),
        'likes' => [],
        'eliminado' => false,
        'editado' => false
    ];

    $reportes->updateOne(
        ['_id' => $reporteId],
        ['$push' => ['comentarios' => $comentarioNuevo]]
    );

    $nombreOrigen = obtenerNombreNotificador();
    $usuarioRespuestaNotificado = null;

    if ($comentarioPadreId !== null) {
        $comentarioPadre = null;
        $comentariosActuales = $reporte['comentarios'] ?? [];

        if ($comentariosActuales instanceof MongoDB\Model\BSONArray) {
            $comentariosActuales = $comentariosActuales->getArrayCopy();
        }

        foreach ($comentariosActuales as $comentarioActual) {
            if (isset($comentarioActual['_id']) && (string) $comentarioActual['_id'] === (string) $comentarioPadreId) {
                $comentarioPadre = $comentarioActual;
                break;
            }
        }

        if ($comentarioPadre && isset($comentarioPadre['usuario_id'])) {
            $usuarioRespuestaNotificado = (string) $comentarioPadre['usuario_id'];

            crearNotificacionUsuario(
                $db,
                $comentarioPadre['usuario_id'],
                $usuarioId,
                'respuesta_comentario',
                'Nueva respuesta',
                $nombreOrigen . ' respondió: "' . mb_substr($comentarioTexto, 0, 80) . '"',
                $reporteId,
                $comentarioId
            );
        }
    }

    $usuarioDestinoReporte = $reporte['usuario_id'] ?? $reporte['usuario_creador_id'] ?? null;

    if (
        $reporte &&
        $usuarioDestinoReporte &&
        (string) $usuarioDestinoReporte !== (string) $usuarioRespuestaNotificado
    ) {
        crearNotificacionUsuario(
            $db,
            $usuarioDestinoReporte,
            $usuarioId,
            'comentario',
            'Nuevo comentario',
            $nombreOrigen . ' comentó: "' . mb_substr($comentarioTexto, 0, 80) . '"',
            $reporteId,
            $comentarioId
        );
    }

    $reporteActualizado = $reportes->findOne(['_id' => $reporteId]);
    $comentariosActualizados = $reporteActualizado['comentarios'] ?? [];

    if ($comentariosActualizados instanceof MongoDB\Model\BSONArray) {
        $comentariosActualizados = $comentariosActualizados->getArrayCopy();
    }

    $totalComentarios = 0;
    foreach (is_array($comentariosActualizados) ? $comentariosActualizados : [] as $comentarioActual) {
        if (($comentarioActual['comentario_padre_id'] ?? null) === null) {
            $totalComentarios++;
        }
    }

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
