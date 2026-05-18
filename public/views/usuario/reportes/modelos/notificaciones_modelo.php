<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function crearNotificacionUsuario($db, $usuarioDestinoId, $usuarioOrigenId, $tipo, $titulo, $mensaje, $reporteId = null, $comentarioId = null)
{
    if (!$usuarioDestinoId) {
        return;
    }

    // Suprimir auto-notificaciones solo cuando hay un origen real
    if ($usuarioOrigenId && (string) $usuarioDestinoId === (string) $usuarioOrigenId) {
        return;
    }

    $documento = [
        'usuario_destino_id' => $usuarioDestinoId instanceof ObjectId ? $usuarioDestinoId : new ObjectId((string) $usuarioDestinoId),
        'tipo' => $tipo,
        'titulo' => $titulo,
        'mensaje' => $mensaje,
        'leida' => false,
        'fecha' => new UTCDateTime()
    ];

    if ($usuarioOrigenId) {
        $documento['usuario_origen_id'] = $usuarioOrigenId instanceof ObjectId ? $usuarioOrigenId : new ObjectId((string) $usuarioOrigenId);
    }

    if ($reporteId !== null) {
        $documento['reporte_id'] = $reporteId instanceof ObjectId ? $reporteId : new ObjectId((string) $reporteId);
    }

    if ($comentarioId !== null) {
        $documento['comentario_id'] = $comentarioId instanceof ObjectId ? $comentarioId : new ObjectId((string) $comentarioId);
    }

    $db->notificaciones->insertOne($documento);
}

function obtenerNombreNotificador()
{
    $nombre = trim($_SESSION['usuario_nombre'] ?? '');

    if ($nombre !== '') {
        return $nombre;
    }

    $email = trim($_SESSION['usuario_email'] ?? '');

    if ($email !== '') {
        return $email;
    }

    return 'Alguien';
}
