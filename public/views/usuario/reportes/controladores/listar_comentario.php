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

function tiempoComentario($fecha)
{
    if (!$fecha instanceof MongoDB\BSON\UTCDateTime) {
        return '';
    }

    $fechaComentario = $fecha->toDateTime();
    $fechaComentario->setTimezone(new DateTimeZone('America/Bogota'));

    $ahora = new DateTime('now', new DateTimeZone('America/Bogota'));

    $diferencia = $ahora->getTimestamp() - $fechaComentario->getTimestamp();

    if ($diferencia < 60) {
        return 'Ahora';
    }

    if ($diferencia < 3600) {
        return floor($diferencia / 60) . ' min';
    }

    if ($diferencia < 86400) {
        return floor($diferencia / 3600) . ' h';
    }

    if ($diferencia < 604800) {
        return floor($diferencia / 86400) . ' día(s)';
    }

    return $fechaComentario->format('d/m/Y');
}

function obtenerNombreUsuario($usuario)
{
    if (!$usuario) {
        return 'Usuario';
    }

    if (!empty($usuario['nombre_completo'])) {
        return (string) $usuario['nombre_completo'];
    }

    if (!empty($usuario['nombre'])) {
        return (string) $usuario['nombre'];
    }

    if (!empty($usuario['nombre_usuario'])) {
        return (string) $usuario['nombre_usuario'];
    }

    if (!empty($usuario['email'])) {
        return (string) $usuario['email'];
    }

    return 'Usuario';
}

function obtenerFotoUsuario($usuario)
{
    if (!$usuario) {
        return '';
    }

    if (!empty($usuario['foto_perfil'])) {
        return (string) $usuario['foto_perfil'];
    }

    if (!empty($usuario['foto'])) {
        return (string) $usuario['foto'];
    }

    if (!empty($usuario['avatar'])) {
        return (string) $usuario['avatar'];
    }

    return '';
}

try {
    if (!isset($_SESSION['usuario_id'])) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No has iniciado sesión.'
        ], 401);
    }

    $reporteIdTexto = $_GET['reporte_id'] ?? '';

    if (!preg_match('/^[a-f\d]{24}$/i', $reporteIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de reporte inválido.'
        ], 400);
    }

    $db = conectarMongoDB();

    $comentarios = $db->comentarios_reporte;
    $likesComentario = $db->likes_comentario;

    $reporteId = new MongoDB\BSON\ObjectId($reporteIdTexto);
    $usuarioSesionId = (string) $_SESSION['usuario_id'];

    $cursor = $comentarios->aggregate([
        [
            '$match' => [
                'reporte_id' => $reporteId
            ]
        ],
        [
            '$lookup' => [
                'from' => 'usuario',
                'localField' => 'usuario_id',
                'foreignField' => '_id',
                'as' => 'usuario'
            ]
        ],
        [
            '$sort' => [
                'fecha_comentario' => 1
            ]
        ]
    ]);

    $comentariosTemporales = [];

    foreach ($cursor as $comentario) {
        $usuario = null;

        if (!empty($comentario['usuario'])) {
            foreach ($comentario['usuario'] as $usuarioEncontrado) {
                $usuario = $usuarioEncontrado;
                break;
            }
        }

        $nombre = obtenerNombreUsuario($usuario);
        $foto = obtenerFotoUsuario($usuario);

        $comentarioId = $comentario['_id'];

        $usuarioComentarioId = isset($comentario['usuario_id'])
            ? (string) $comentario['usuario_id']
            : '';

        $esMio = $usuarioSesionId === $usuarioComentarioId;

        $totalLikes = $likesComentario->countDocuments([
            'comentario_id' => $comentarioId
        ]);

        $comentarioPadreId = null;

        if (
            isset($comentario['comentario_padre_id']) &&
            $comentario['comentario_padre_id'] instanceof MongoDB\BSON\ObjectId
        ) {
            $comentarioPadreId = (string) $comentario['comentario_padre_id'];
        }

        $comentariosTemporales[] = [
            'id' => (string) $comentarioId,
            'usuario' => $nombre,
            'inicial' => strtoupper(substr($nombre, 0, 1)),
            'foto_perfil' => $foto,
            'comentario' => (string) ($comentario['comentario'] ?? ''),
            'fecha' => tiempoComentario($comentario['fecha_comentario'] ?? null),
            'likes' => $totalLikes,
            'comentario_padre_id' => $comentarioPadreId,
            'respuestas' => [],
            'es_mio' => $esMio,
            'eliminado' => !empty($comentario['eliminado']),
            'editado' => !empty($comentario['editado'])
        ];
    }

    $comentariosPorId = [];
    $lista = [];

    foreach ($comentariosTemporales as $comentario) {
        $comentario['respuestas'] = [];
        $comentariosPorId[$comentario['id']] = $comentario;
    }

    foreach ($comentariosPorId as $id => &$comentario) {
        $padreId = $comentario['comentario_padre_id'];

        if ($padreId !== null && isset($comentariosPorId[$padreId])) {
            $comentariosPorId[$padreId]['respuestas'][] = &$comentario;
        } else {
            $lista[] = &$comentario;
        }
    }

    unset($comentario);

    responderJson([
        'ok' => true,
        'total' => count($lista),
        'comentarios' => $lista
    ]);

} catch (Throwable $e) {
    responderJson([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}
