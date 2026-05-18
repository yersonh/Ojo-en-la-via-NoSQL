<?php
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/token_entidad_helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../views/usuario/reportes/modelos/notificaciones_modelo.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$db    = conectarMongoDB();
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$exito = '';
$reporte   = null;
$tokenData = null;

if ($token === '') {
    $error = 'Enlace inválido o incompleto.';
} else {
    $tokenData = validarTokenEntidad($db, $token);
    if (!$tokenData) {
        $error = 'Este enlace no es válido, ya fue utilizado o ha expirado (validez: 7 días).';
    } else {
        $reporte = $db->Reportes->findOne(['_id' => $tokenData['reporte_id']]);
        if (!$reporte) {
            $error = 'El reporte vinculado a este enlace no existe.';
        }
    }
}

// ── POST: actualizar estado ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $reporte && $tokenData) {
    $nuevoEstado = trim($_POST['estado'] ?? '');
    $comentario  = trim($_POST['comentario'] ?? '');

    $estadosPermitidos = ['en_revision', 'resuelto'];
    if (!in_array($nuevoEstado, $estadosPermitidos, true)) {
        $error = 'Estado no válido.';
    } else {
        $fechaEstado = new UTCDateTime();
        $reporteId   = $tokenData['reporte_id'];

        $db->Reportes->updateOne(
            ['_id' => $reporteId],
            [
                '$set'  => ['estado' => $nuevoEstado, 'fecha_estado' => $fechaEstado],
                '$push' => [
                    'historial_estados' => [
                        'estado_anterior' => (string)($reporte['estado'] ?? 'notificado'),
                        'estado_nuevo'    => $nuevoEstado,
                        'entidad'         => $tokenData['entidad'],
                        'comentario'      => $comentario,
                        'fecha_estado'    => $fechaEstado,
                    ]
                ]
            ]
        );

        // Notificar al ciudadano
        $usuarioDestino = $reporte['usuario_id'] ?? $reporte['usuario_creador_id'] ?? null;
        if ($usuarioDestino) {
            $etiqueta = $nuevoEstado === 'resuelto' ? 'resuelto' : 'en revisión';
            crearNotificacionUsuario(
                $db,
                $usuarioDestino,
                null,
                'estado_reporte',
                'Reporte actualizado por entidad',
                'La entidad "' . $tokenData['entidad'] . '" cambió el estado de tu reporte a ' . $etiqueta . '.'
                . ($comentario !== '' ? ' Comentario: ' . $comentario : ''),
                $reporteId
            );
        }

        marcarTokenUsado($db, $token);

        $etiqueta = $nuevoEstado === 'resuelto' ? 'Resuelto' : 'En revisión';
        $exito = 'Estado actualizado a <strong>' . htmlspecialchars($etiqueta) . '</strong>. El ciudadano ha sido notificado. Gracias.';
        $reporte  = null; // Ocultar formulario tras éxito
    }
}

// ── Helpers de presentación ───────────────────────────────────────────────
$estadoLabels = [
    'pendiente'   => ['label' => 'Pendiente',   'color' => '#f39c12'],
    'en_revision' => ['label' => 'En revisión', 'color' => '#3498db'],
    'notificado'  => ['label' => 'Notificado',  'color' => '#9b59b6'],
    'resuelto'    => ['label' => 'Resuelto',    'color' => '#27ae60'],
];
$estadoActual = (string)($reporte['estado'] ?? 'pendiente');
$estadoInfo   = $estadoLabels[$estadoActual] ?? ['label' => ucfirst($estadoActual), 'color' => '#7f8c8d'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Actualizar reporte – Ojo en la Vía</title>
<link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #1a2a6c 0%, #2471a3 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
}
.card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,.18);
    width: 100%;
    max-width: 560px;
    overflow: hidden;
}
.card-header {
    background: linear-gradient(90deg, #1a2a6c, #2471a3);
    color: #fff;
    padding: 22px 28px;
}
.card-header h1 { font-size: 1.2rem; font-weight: 700; margin-bottom: 2px; }
.card-header p  { font-size: .85rem; opacity: .8; }
.card-body { padding: 26px 28px; }
.alert {
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 20px;
    font-size: .9rem;
}
.alert-error   { background: #fef2f2; color: #b91c1c; border-left: 4px solid #ef4444; }
.alert-success { background: #f0fdf4; color: #166534; border-left: 4px solid #22c55e; }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: .82rem; color: #64748b; margin-bottom: 5px; font-weight: 500; }
.field-value {
    font-size: .95rem;
    color: #1e293b;
    background: #f8fafc;
    border-radius: 6px;
    padding: 9px 12px;
    border: 1px solid #e2e8f0;
}
.badge-estado {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: .8rem;
    font-weight: 600;
    color: #fff;
}
.divider { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }
.form-label { display: block; font-size: .85rem; font-weight: 600; color: #374151; margin-bottom: 8px; }
.form-hint  { font-size: .78rem; color: #94a3b8; margin-top: 4px; }
textarea {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 9px 12px;
    font-size: .9rem;
    font-family: inherit;
    resize: vertical;
    color: #1e293b;
}
textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
.btn-group { display: flex; gap: 12px; margin-top: 20px; }
.btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: .95rem;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .15s, transform .1s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn:hover   { opacity: .88; transform: translateY(-1px); }
.btn:active  { transform: translateY(0); }
.btn-revision { background: #3498db; color: #fff; }
.btn-resuelto { background: #27ae60; color: #fff; }
.footer { text-align: center; padding: 14px; font-size: .75rem; color: #94a3b8; border-top: 1px solid #f1f5f9; }
</style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1><i class="fas fa-eye" style="margin-right:8px;"></i>Ojo en la Vía</h1>
        <p>Portal de actualización de reportes para entidades</p>
    </div>

    <div class="card-body">

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i><?= htmlspecialchars($error) ?>
        </div>

        <?php elseif ($exito): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="margin-right:8px;"></i><?= $exito ?>
        </div>
        <p style="font-size:.88rem;color:#64748b;text-align:center;margin-top:12px;">
            Puede cerrar esta ventana.
        </p>

        <?php elseif ($reporte): ?>

        <p style="font-size:.9rem;color:#475569;margin-bottom:18px;">
            <strong><?= htmlspecialchars($tokenData['entidad']) ?></strong>, a continuación encontrará
            los detalles del reporte asignado a su entidad. Por favor actualice el estado según
            corresponda.
        </p>

        <!-- Detalles del reporte -->
        <div class="field">
            <label>Tipo de incidente</label>
            <div class="field-value">
                <?= htmlspecialchars((string)($reporte['tipo'] ?? $reporte['tipo_incidente'] ?? 'Sin tipo')) ?>
            </div>
        </div>
        <div class="field">
            <label>Descripción</label>
            <div class="field-value">
                <?= nl2br(htmlspecialchars((string)($reporte['descripcion'] ?? 'Sin descripción'))) ?>
            </div>
        </div>
        <div class="field">
            <label>Ubicación</label>
            <div class="field-value">
                <?= htmlspecialchars((string)($reporte['direccion_texto'] ?? 'No especificada')) ?>
            </div>
        </div>
        <div class="field">
            <label>Estado actual</label>
            <div>
                <span class="badge-estado" style="background:<?= $estadoInfo['color'] ?>;">
                    <?= htmlspecialchars($estadoInfo['label']) ?>
                </span>
            </div>
        </div>

        <hr class="divider">

        <!-- Formulario de actualización -->
        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <label class="form-label">Comentario (opcional)</label>
            <textarea name="comentario" rows="3" maxlength="500"
                      placeholder="Ej: Se inició revisión del sector, se estima resolución en 48h…"></textarea>
            <div class="form-hint">El comentario se mostrará al ciudadano en su notificación.</div>

            <div class="btn-group">
                <button type="submit" name="estado" value="en_revision" class="btn btn-revision">
                    <i class="fas fa-tools"></i> En revisión
                </button>
                <button type="submit" name="estado" value="resuelto" class="btn btn-resuelto">
                    <i class="fas fa-check-double"></i> Resuelto
                </button>
            </div>
        </form>

        <?php endif; ?>

    </div>

    <div class="footer">
        Ojo en la Vía · Villavicencio, Colombia · Enlace de un solo uso, válido 7 días
    </div>
</div>
</body>
</html>
