<?php

require_once __DIR__ . '/email_helper.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Busca una regla activa para el tipo de incidente y, si existe,
 * inserta en notificaciones_entidades y envía el correo a la entidad.
 */
function dispararNotificacionEntidad(\MongoDB\Database $db, array $reporte, ObjectId $reporteId): void
{
    $tipo = (string) ($reporte['tipo'] ?? $reporte['tipo_incidente'] ?? '');
    if ($tipo === '') return;

    $regla = $db->reglas_notificacion->findOne(['tipo_incidente' => $tipo, 'activa' => true]);
    if (!$regla) return;

    $direccion = (string) ($reporte['direccion_texto'] ?? '');
    if ($direccion === '') {
        $lat       = $reporte['ubicacion']['latitud']  ?? $reporte['latitud']  ?? '';
        $lng       = $reporte['ubicacion']['longitud'] ?? $reporte['longitud'] ?? '';
        $direccion = "Lat: $lat, Lng: $lng";
    }

    $vars   = ['{tipo}', '{direccion}', '{reporte_id}'];
    $valores = [$tipo, $direccion, (string) $reporteId];

    $asunto  = str_replace($vars, $valores, (string) ($regla['asunto']  ?? 'Nuevo reporte: {tipo}'));
    $mensaje = str_replace($vars, $valores, (string) ($regla['mensaje'] ?? 'Se reportó {tipo} en {direccion}.'));

    $fechaNotif = new UTCDateTime();

    $db->notificaciones_entidades->insertOne([
        'reporte_id'   => $reporteId,
        'entidad'      => (string) ($regla['entidad']   ?? ''),
        'prioridad'    => (string) ($regla['prioridad'] ?? 'media'),
        'asunto'       => $asunto,
        'mensaje'      => $mensaje,
        'estado_notif' => 'enviada',
        'admin_id'     => null,
        'admin_nombre' => 'Sistema automático',
        'fecha'        => $fechaNotif,
    ]);

    // Actualizar estado del reporte a 'notificado' con historial
    $db->Reportes->updateOne(
        ['_id' => $reporteId],
        [
            '$set'  => ['estado' => 'notificado', 'fecha_estado' => $fechaNotif],
            '$push' => [
                'historial_estados' => [
                    'estado_anterior' => 'pendiente',
                    'estado_nuevo'    => 'notificado',
                    'usuario_id'      => null,
                    'fecha_estado'    => $fechaNotif,
                ]
            ]
        ]
    );

    $cuerpo = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;'>
            <h2 style='color:#2c3e50;border-bottom:2px solid #27ae60;padding-bottom:8px;'>
                Nueva notificación — Ojo en la Vía
            </h2>
            <table style='width:100%;border-collapse:collapse;margin-bottom:16px;'>
                <tr><td style='padding:6px 0;color:#7f8c8d;width:140px;'>Entidad</td>
                    <td style='padding:6px 0;font-weight:600;'>" . htmlspecialchars((string)($regla['entidad'] ?? '')) . "</td></tr>
                <tr><td style='padding:6px 0;color:#7f8c8d;'>Tipo de incidente</td>
                    <td style='padding:6px 0;font-weight:600;'>" . htmlspecialchars($tipo) . "</td></tr>
                <tr><td style='padding:6px 0;color:#7f8c8d;'>Ubicación</td>
                    <td style='padding:6px 0;'>" . htmlspecialchars($direccion) . "</td></tr>
                <tr><td style='padding:6px 0;color:#7f8c8d;'>Prioridad</td>
                    <td style='padding:6px 0;'>" . htmlspecialchars((string)($regla['prioridad'] ?? 'media')) . "</td></tr>
                <tr><td style='padding:6px 0;color:#7f8c8d;'>ID reporte</td>
                    <td style='padding:6px 0;font-size:.85em;color:#888;'>" . htmlspecialchars((string)$reporteId) . "</td></tr>
            </table>
            <div style='background:#f8f9fa;border-left:4px solid #27ae60;padding:12px 16px;margin-bottom:16px;'>
                <strong>Asunto:</strong> " . htmlspecialchars($asunto) . "<br><br>
                " . nl2br(htmlspecialchars($mensaje)) . "
            </div>
            <p style='color:#bdc3c7;font-size:.8em;'>Generado automáticamente por Ojo en la Vía.</p>
        </div>
    ";

    enviarCorreoBackground('laurenoviedo68@gmail.com', $asunto, $cuerpo);
}
