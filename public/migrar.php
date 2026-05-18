<?php
/**
 * Script de migración único — abrir en el navegador en Railway:
 *   https://ojo-en-la-via-nosql-production.up.railway.app/migrar.php?token=MIGRAR2026
 *
 * Después de ejecutarlo correctamente, eliminar este archivo.
 */

if (($_GET['token'] ?? '') !== 'MIGRAR2026') {
    http_response_code(403);
    exit('Acceso denegado.');
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;

$db = conectarMongoDB();

$log = [];

function log_msg(string $msg) {
    global $log;
    $log[] = $msg;
    flush();
}

/* ═══════════════════════════════════════════════════════════
   1. notificaciones  →  usuario.notificaciones[]
═══════════════════════════════════════════════════════════ */
$totalNotif      = $db->notificaciones->countDocuments([]);
$migradas        = 0;
$sinDestinatario = 0;
$porUsuario      = [];

foreach ($db->notificaciones->find([]) as $n) {
    $raw = $n['usuario_destino_id'] ?? null;
    if (!$raw) { $sinDestinatario++; continue; }

    $uid = (string) $raw;

    $doc = ['_id' => $n['_id'] ?? new ObjectId()];
    foreach (['tipo','titulo','mensaje','leida','fecha','reporte_id','comentario_id','usuario_origen_id'] as $c) {
        if (isset($n[$c])) $doc[$c] = $n[$c];
    }
    $porUsuario[$uid][] = $doc;
}

foreach ($porUsuario as $uid => $docs) {
    try { $oid = new ObjectId($uid); $f = ['$or' => [['_id' => $oid], ['_id' => $uid]]]; }
    catch (Throwable) { $f = ['_id' => $uid]; }

    $r = $db->usuario->updateOne($f, ['$push' => ['notificaciones' => ['$each' => $docs]]]);
    if ($r->getMatchedCount() > 0) { $migradas += count($docs); }
    else { log_msg("⚠ Usuario no encontrado: $uid (" . count($docs) . " notif. descartadas)"); }
}

log_msg("✓ notificaciones: $totalNotif leídas → $migradas embebidas en usuario" . ($sinDestinatario ? " ($sinDestinatario sin destinatario)" : ''));

/* ═══════════════════════════════════════════════════════════
   2. notificaciones_entidades  →  Reportes.notificaciones_entidades[]
═══════════════════════════════════════════════════════════ */
$totalEnt   = $db->notificaciones_entidades->countDocuments([]);
$migEnt     = 0;
$sinReporte = 0;
$porReporte = [];

foreach ($db->notificaciones_entidades->find([]) as $n) {
    $raw = $n['reporte_id'] ?? null;
    if (!$raw) { $sinReporte++; continue; }

    $rid = (string) $raw;
    $doc = ['_id' => $n['_id'] ?? new ObjectId()];
    foreach (['entidad','prioridad','asunto','mensaje','estado_notif','admin_id','admin_nombre','fecha'] as $c) {
        if (isset($n[$c])) $doc[$c] = $n[$c];
    }
    $porReporte[$rid][] = $doc;
}

foreach ($porReporte as $rid => $docs) {
    try { $oid = new ObjectId($rid); $f = ['_id' => $oid]; }
    catch (Throwable) { $f = ['_id' => $rid]; }

    $r = $db->Reportes->updateOne($f, ['$push' => ['notificaciones_entidades' => ['$each' => $docs]]]);
    if ($r->getMatchedCount() > 0) { $migEnt += count($docs); }
    else { log_msg("⚠ Reporte no encontrado: $rid (" . count($docs) . " notif. descartadas)"); }
}

log_msg("✓ notificaciones_entidades: $totalEnt leídas → $migEnt embebidas en Reportes" . ($sinReporte ? " ($sinReporte sin reporte)" : ''));

/* ═══════════════════════════════════════════════════════════
   3. Eliminar colecciones antiguas
═══════════════════════════════════════════════════════════ */
$db->notificaciones->drop();
log_msg("✓ Colección 'notificaciones' eliminada");

$db->notificaciones_entidades->drop();
log_msg("✓ Colección 'notificaciones_entidades' eliminada");

$ok = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Migración — Ojo en la Vía</title>
<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .box { background: #1e293b; border-radius: 16px; padding: 36px 40px; max-width: 560px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,.4); }
    h2 { color: #10b981; margin: 0 0 24px; font-size: 1.3rem; }
    .line { padding: 9px 14px; border-radius: 8px; margin-bottom: 8px; font-size: .92rem; background: #0f172a; border-left: 4px solid #10b981; }
    .line.warn { border-color: #f59e0b; }
    .footer { margin-top: 24px; padding: 14px; background: #dc2626; border-radius: 10px; font-size: .85rem; color: #fff; text-align: center; }
</style>
</head>
<body>
<div class="box">
    <h2>Migración completada</h2>
    <?php foreach ($log as $l): ?>
        <div class="line <?= str_starts_with($l, '⚠') ? 'warn' : '' ?>"><?= htmlspecialchars($l) ?></div>
    <?php endforeach; ?>
    <div class="footer">
        Elimina el archivo <strong>public/migrar.php</strong> del servidor ahora que la migración terminó.
    </div>
</div>
</body>
</html>
