<?php
/**
 * Migración única: mueve datos de las colecciones independientes
 * `notificaciones` y `notificaciones_entidades` hacia arrays embebidos
 * en `usuario` y `Reportes` respectivamente, luego elimina las colecciones.
 *
 * Uso: php scripts/migrar_notificaciones.php
 */

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;

$db = conectarMongoDB();

echo "=== Migración de notificaciones ===\n\n";

/* ═══════════════════════════════════════════════════════════
   1. notificaciones  →  usuario.notificaciones[]
═══════════════════════════════════════════════════════════ */
echo "▶ Leyendo colección 'notificaciones'...\n";

$totalNotif   = $db->notificaciones->countDocuments([]);
$migradas     = 0;
$sinDestinatario = 0;

// Agrupar por usuario_destino_id para hacer un solo $push por usuario
$porUsuario = [];

foreach ($db->notificaciones->find([]) as $n) {
    $raw = $n['usuario_destino_id'] ?? null;
    if (!$raw) { $sinDestinatario++; continue; }

    // Normalizar a string para agrupar
    $uid = $raw instanceof ObjectId ? (string) $raw : (string) $raw;

    // Construir el subdocumento embebido (sin usuario_destino_id)
    $doc = ['_id' => $n['_id'] ?? new ObjectId()];

    foreach (['tipo','titulo','mensaje','leida','fecha','reporte_id','comentario_id','usuario_origen_id'] as $campo) {
        if (isset($n[$campo])) $doc[$campo] = $n[$campo];
    }

    $porUsuario[$uid][] = $doc;
}

echo "  Encontradas: $totalNotif | Agrupadas por usuario: " . count($porUsuario) . "\n";

foreach ($porUsuario as $uid => $docs) {
    // Intentar como ObjectId primero, luego como string
    try {
        $oid = new ObjectId($uid);
        $filtro = ['$or' => [['_id' => $oid], ['_id' => $uid]]];
    } catch (Throwable) {
        $filtro = ['_id' => $uid];
    }

    $resultado = $db->usuario->updateOne(
        $filtro,
        ['$push' => ['notificaciones' => ['$each' => $docs]]]
    );

    if ($resultado->getMatchedCount() > 0) {
        $migradas += count($docs);
    } else {
        echo "  ⚠ Usuario no encontrado: $uid (" . count($docs) . " notif descartadas)\n";
    }
}

echo "  Migradas: $migradas | Sin destinatario: $sinDestinatario\n\n";

/* ═══════════════════════════════════════════════════════════
   2. notificaciones_entidades  →  Reportes.notificaciones_entidades[]
═══════════════════════════════════════════════════════════ */
echo "▶ Leyendo colección 'notificaciones_entidades'...\n";

$totalEnt   = $db->notificaciones_entidades->countDocuments([]);
$migEnt     = 0;
$sinReporte = 0;

$porReporte = [];

foreach ($db->notificaciones_entidades->find([]) as $n) {
    $raw = $n['reporte_id'] ?? null;
    if (!$raw) { $sinReporte++; continue; }

    $rid = $raw instanceof ObjectId ? (string) $raw : (string) $raw;

    $doc = ['_id' => $n['_id'] ?? new ObjectId()];

    foreach (['entidad','prioridad','asunto','mensaje','estado_notif','admin_id','admin_nombre','fecha'] as $campo) {
        if (isset($n[$campo])) $doc[$campo] = $n[$campo];
    }

    $porReporte[$rid][] = $doc;
}

echo "  Encontradas: $totalEnt | Agrupadas por reporte: " . count($porReporte) . "\n";

foreach ($porReporte as $rid => $docs) {
    try {
        $oid = new ObjectId($rid);
        $filtro = ['_id' => $oid];
    } catch (Throwable) {
        $filtro = ['_id' => $rid];
    }

    $resultado = $db->Reportes->updateOne(
        $filtro,
        ['$push' => ['notificaciones_entidades' => ['$each' => $docs]]]
    );

    if ($resultado->getMatchedCount() > 0) {
        $migEnt += count($docs);
    } else {
        echo "  ⚠ Reporte no encontrado: $rid (" . count($docs) . " notif descartadas)\n";
    }
}

echo "  Migradas: $migEnt | Sin reporte: $sinReporte\n\n";

/* ═══════════════════════════════════════════════════════════
   3. Eliminar las colecciones vacías
═══════════════════════════════════════════════════════════ */
echo "▶ Eliminando colecciones antiguas...\n";

$db->notificaciones->drop();
echo "  ✓ Colección 'notificaciones' eliminada\n";

$db->notificaciones_entidades->drop();
echo "  ✓ Colección 'notificaciones_entidades' eliminada\n";

echo "\n=== Migración completada ===\n";
echo "  notificaciones embebidas en usuario:  $migradas\n";
echo "  notif. entidades embebidas en Reportes: $migEnt\n";
