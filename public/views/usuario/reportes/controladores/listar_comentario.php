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

    $reportes = $db->Reportes;
    $usuarios = $db->usuario;

    $reporteId = new MongoDB\BSON\ObjectId($reporteIdTexto);
    $usuarioSesionId = (string) $_SESSION['usuario_id'];

    $reporte = $reportes->findOne([
        '_id' => $reporteId
    ]);

    if (!$reporte) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Reporte no encontrado.'
        ], 404);
    }

    $comentariosReporte = $reporte['comentarios'] ?? [];

    if ($comentariosReporte instanceof MongoDB\Model\BSONArray) {
        $comentariosReporte = $comentariosReporte->getArrayCopy();
    }

    $comentariosReporte = is_array($comentariosReporte) ? $comentariosReporte : [];

    usort($comentariosReporte, function ($a, $b) {
        $fechaA = $a['fecha_comentario'] ?? null;
        $fechaB = $b['fecha_comentario'] ?? null;
        $tsA = $fechaA instanceof MongoDB\BSON\UTCDateTime ? $fechaA->toDateTime()->getTimestamp() : 0;
        $tsB = $fechaB instanceof MongoDB\BSON\UTCDateTime ? $fechaB->toDateTime()->getTimestamp() : 0;
        return $tsA <=> $tsB;
    });

    $usuariosIds = [];
    foreach ($comentariosReporte as $comentario) {
        if (($comentario['usuario_id'] ?? null) instanceof MongoDB\BSON\ObjectId) {
            $usuariosIds[(string) $comentario['usuario_id']] = $comentario['usuario_id'];
        }
    }

    $usuariosPorId = [];
    if (!empty($usuariosIds)) {
        foreach ($usuarios->find(['_id' => ['$in' => array_values($usuariosIds)]]) as $usuario) {
            $usuariosPorId[(string) $usuario['_id']] = $usuario;
        }
    }

    $comentariosTemporales = [];

    foreach ($comentariosReporte as $comentario) {
        $usuarioComentarioId = isset($comentario['usuario_id'])
            ? (string) $comentario['usuario_id']
            : '';
        $usuario = $usuariosPorId[$usuarioComentarioId] ?? null;

        $nombre = obtenerNombreUsuario($usuario);
        $foto = obtenerFotoUsuario($usuario);

        $comentarioId = $comentario['_id'];

        $esMio = $usuarioSesionId === $usuarioComentarioId;

        $likesComentario = $comentario['likes'] ?? [];

        if ($likesComentario instanceof MongoDB\Model\BSONArray) {
            $likesComentario = $likesComentario->getArrayCopy();
        }

        $totalLikes = is_array($likesComentario) ? count($likesComentario) : 0;

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
