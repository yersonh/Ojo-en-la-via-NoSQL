<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../usuario/reportes/modelos/notificaciones_modelo.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

header('Content-Type: application/json; charset=utf-8');

function ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function err($msg, $status = 400)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'mensaje' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function esAdmin(): bool
{
    return isset($_SESSION['usuario_id']) && ($_SESSION['usuario_rol'] ?? '') === 'admin';
}

function validarId(string $id): ObjectId
{
    if (!preg_match('/^[a-f\d]{24}$/i', $id)) {
        err('ID inválido.');
    }
    return new ObjectId($id);
}

if (!esAdmin()) {
    err('Sin permiso.', 403);
}

$accion = $_POST['accion'] ?? '';
$db     = conectarMongoDB();

/* ═══════════════════════════════════════════════════════
   TOGGLE ESTADO USUARIO  (activar / desactivar)
═══════════════════════════════════════════════════════ */
if ($accion === 'toggle_usuario') {
    $uid    = validarId($_POST['usuario_id'] ?? '');
    $estado = ($_POST['estado'] ?? '0') === '1';

    $db->usuario->updateOne(
        ['_id' => $uid],
        ['$set' => ['estado' => $estado]]
    );

    ok(['estado' => $estado]);
}

/* ═══════════════════════════════════════════════════════
   CAMBIAR ROL USUARIO
═══════════════════════════════════════════════════════ */
if ($accion === 'cambiar_rol') {
    $uid    = validarId($_POST['usuario_id'] ?? '');
    $rol    = trim($_POST['rol'] ?? '');

    if (!in_array($rol, ['ciudadano', 'admin'], true)) {
        err('Rol no válido.');
    }

    // Impedir que un admin se quite su propio rol de admin
    if ((string)$uid === (string)$_SESSION['usuario_id'] && $rol !== 'admin') {
        err('No puedes cambiar tu propio rol.');
    }

    $db->usuario->updateOne(
        ['_id' => $uid],
        ['$set' => ['rol' => $rol]]
    );

    ok(['rol' => $rol]);
}

/* ═══════════════════════════════════════════════════════
   ELIMINAR REPORTE (admin puede borrar cualquier reporte)
═══════════════════════════════════════════════════════ */
if ($accion === 'eliminar_reporte_admin') {
    $rid = validarId($_POST['reporte_id'] ?? '');

    // Recopilar IDs de comentarios para borrar sus likes
    $comentariosIds = [];
    foreach ($db->comentarios_reporte->find(['reporte_id' => $rid]) as $c) {
        if (isset($c['_id'])) $comentariosIds[] = $c['_id'];
    }

    $db->Reportes->deleteOne(['_id' => $rid]);
    $db->comentarios_reporte->deleteMany(['reporte_id' => $rid]);
    $db->notificaciones->deleteMany(['reporte_id' => $rid]);

    if (!empty($comentariosIds)) {
        $db->likes_comentario->deleteMany(['comentario_id' => ['$in' => $comentariosIds]]);
    }

    ok(['mensaje' => 'Reporte eliminado.']);
}

/* ═══════════════════════════════════════════════════════
   GUARDAR NOTIFICACIÓN A ENTIDAD
═══════════════════════════════════════════════════════ */
if ($accion === 'guardar_notificacion_entidad') {
    $entidad   = trim($_POST['entidad']    ?? '');
    $prioridad = trim($_POST['prioridad']  ?? 'media');
    $asunto    = trim($_POST['asunto']     ?? '');
    $mensaje   = trim($_POST['mensaje']    ?? '');
    $repIdTxt  = trim($_POST['reporte_id'] ?? '');

    if ($entidad === '')  err('Selecciona una entidad.');
    if ($asunto  === '')  err('El asunto es obligatorio.');
    if ($mensaje === '')  err('El mensaje no puede estar vacío.');
    if (mb_strlen($mensaje) > 1000) err('El mensaje supera los 1000 caracteres.');
    if (!in_array($prioridad, ['alta', 'media', 'baja'], true)) $prioridad = 'media';

    $doc = [
        'entidad'      => $entidad,
        'prioridad'    => $prioridad,
        'asunto'       => $asunto,
        'mensaje'      => $mensaje,
        'estado_notif' => 'enviada',
        'admin_id'     => new ObjectId((string)$_SESSION['usuario_id']),
        'admin_nombre' => $_SESSION['usuario_nombre'] ?? 'Admin',
        'fecha'        => new UTCDateTime(),
    ];

    // Vincular reporte si se seleccionó
    if ($repIdTxt !== '' && preg_match('/^[a-f\d]{24}$/i', $repIdTxt)) {
        $doc['reporte_id'] = new ObjectId($repIdTxt);
        $reporteVinculado = $db->Reportes->findOne(['_id' => $doc['reporte_id']]);

        // Actualizar estado del reporte a 'notificado'
        $fechaEstado = new UTCDateTime();

        $db->Reportes->updateOne(
            ['_id' => $doc['reporte_id']],
            [
                '$set' => ['estado' => 'notificado', 'fecha_estado' => $fechaEstado],
                '$push' => [
                    'historial_estados' => [
                        'estado_anterior' => $reporteVinculado['estado'] ?? null,
                        'estado_nuevo' => 'notificado',
                        'admin_id' => new ObjectId((string)$_SESSION['usuario_id']),
                        'fecha_estado' => $fechaEstado
                    ]
                ]
            ]
        );
    }

    $db->notificaciones_entidades->insertOne($doc);

    ok([
        'notificacion' => [
            'entidad'   => $entidad,
            'prioridad' => $prioridad,
            'asunto'    => $asunto,
            'mensaje'   => $mensaje,
            'admin'     => $doc['admin_nombre'],
        ]
    ]);
}

err('Acción no reconocida.');
