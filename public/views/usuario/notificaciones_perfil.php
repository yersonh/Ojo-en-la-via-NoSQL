<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/auth_helper.php';

use MongoDB\BSON\ObjectId;

header('Content-Type: application/json; charset=utf-8');

function responderNotificacionJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    verificar_autenticacion('../../index.php');

    if (empty($_SESSION['usuario_id'])) {
        responderNotificacionJson(['ok' => false, 'mensaje' => 'No has iniciado sesion.'], 401);
    }

    $accion = $_POST['accion'] ?? '';
    $usuarioId = new ObjectId((string) $_SESSION['usuario_id']);
    $db = conectarMongoDB();

    if ($accion === 'marcar_leida') {
        $notificacionIdTexto = $_POST['notificacion_id'] ?? '';

        if (!preg_match('/^[a-f\d]{24}$/i', $notificacionIdTexto)) {
            responderNotificacionJson(['ok' => false, 'mensaje' => 'ID de notificacion invalido.'], 400);
        }

        $notificacionId = new ObjectId($notificacionIdTexto);

        $db->usuario->updateOne(
            ['_id' => $usuarioId, 'notificaciones._id' => $notificacionId],
            ['$set' => ['notificaciones.$.leida' => true]]
        );

        responderNotificacionJson(['ok' => true]);
    }

    if ($accion === 'marcar_todas') {
        $db->usuario->updateOne(
            ['_id' => $usuarioId],
            ['$set' => ['notificaciones.$[notif].leida' => true]],
            ['arrayFilters' => [['notif.leida' => false]]]
        );

        responderNotificacionJson(['ok' => true]);
    }

    responderNotificacionJson(['ok' => false, 'mensaje' => 'Accion no valida.'], 400);
} catch (Throwable $e) {
    responderNotificacionJson(['ok' => false, 'mensaje' => $e->getMessage()], 500);
}
