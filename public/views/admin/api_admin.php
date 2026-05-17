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

    $db->Reportes->deleteOne(['_id' => $rid]);
    $db->notificaciones->deleteMany(['reporte_id' => $rid]);

    ok(['mensaje' => 'Reporte eliminado.']);
}

/* ═══════════════════════════════════════════════════════
   CRUD DE REGLAS DE NOTIFICACIÓN AUTOMÁTICA
═══════════════════════════════════════════════════════ */
if ($accion === 'crear_regla') {
    $tipo      = trim($_POST['tipo_incidente'] ?? '');
    $entidad   = trim($_POST['entidad']        ?? '');
    $asunto    = trim($_POST['asunto']         ?? '');
    $mensaje   = trim($_POST['mensaje']        ?? '');
    $prioridad = trim($_POST['prioridad']      ?? 'media');

    if ($tipo    === '') err('El tipo de incidente es obligatorio.');
    if ($entidad === '') err('La entidad es obligatoria.');
    if ($asunto  === '') err('El asunto es obligatorio.');
    if ($mensaje === '') err('El mensaje es obligatorio.');
    if (!in_array($prioridad, ['alta', 'media', 'baja'], true)) $prioridad = 'media';

    $existe = $db->reglas_notificacion->findOne(['tipo_incidente' => $tipo]);
    if ($existe) err('Ya existe una regla para ese tipo de incidente.');

    $resultado = $db->reglas_notificacion->insertOne([
        'tipo_incidente' => $tipo,
        'entidad'        => $entidad,
        'asunto'         => $asunto,
        'mensaje'        => $mensaje,
        'prioridad'      => $prioridad,
        'activa'         => true,
        'fecha_creacion' => new UTCDateTime(),
    ]);

    ok(['id' => (string) $resultado->getInsertedId()]);
}

if ($accion === 'actualizar_regla') {
    $rid       = validarId($_POST['regla_id']     ?? '');
    $entidad   = trim($_POST['entidad']           ?? '');
    $asunto    = trim($_POST['asunto']            ?? '');
    $mensaje   = trim($_POST['mensaje']           ?? '');
    $prioridad = trim($_POST['prioridad']         ?? 'media');

    if ($entidad === '') err('La entidad es obligatoria.');
    if ($asunto  === '') err('El asunto es obligatorio.');
    if ($mensaje === '') err('El mensaje es obligatorio.');
    if (!in_array($prioridad, ['alta', 'media', 'baja'], true)) $prioridad = 'media';

    $db->reglas_notificacion->updateOne(
        ['_id' => $rid],
        ['$set' => ['entidad' => $entidad, 'asunto' => $asunto, 'mensaje' => $mensaje, 'prioridad' => $prioridad]]
    );

    ok();
}

if ($accion === 'toggle_regla') {
    $rid    = validarId($_POST['regla_id'] ?? '');
    $activa = ($_POST['activa'] ?? '0') === '1';

    $db->reglas_notificacion->updateOne(['_id' => $rid], ['$set' => ['activa' => $activa]]);

    ok(['activa' => $activa]);
}

if ($accion === 'eliminar_regla') {
    $rid = validarId($_POST['regla_id'] ?? '');
    $db->reglas_notificacion->deleteOne(['_id' => $rid]);
    ok();
}

err('Acción no reconocida.');
