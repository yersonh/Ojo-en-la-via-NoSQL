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

        $usuarioDestino = $reporte['usuario_id'] ?? $reporte['usuario_creador_id'] ?? null;
        if ($usuarioDestino) {
            $etiquetaNotif = $nuevoEstado === 'resuelto' ? 'resuelto' : 'en revisión';
            crearNotificacionUsuario(
                $db,
                $usuarioDestino,
                null,
                'estado_reporte',
                'Reporte actualizado por entidad',
                'La entidad "' . $tokenData['entidad'] . '" cambió el estado de tu reporte a ' . $etiquetaNotif . '.'
                . ($comentario !== '' ? ' Comentario: ' . $comentario : ''),
                $reporteId
            );
        }

        if ($nuevoEstado === 'resuelto') {
            marcarTokenUsado($db, $token);
        }

        $etiqueta = $nuevoEstado === 'resuelto' ? 'Resuelto' : 'En revisión';
        $exito = 'Estado actualizado a <strong>' . htmlspecialchars($etiqueta) . '</strong>. El ciudadano ha sido notificado.';

        // Recargar reporte para mostrar estado actualizado
        $reporte = $db->Reportes->findOne(['_id' => $reporteId]);
    }
}

// ── Helpers de presentación ───────────────────────────────────────────────
$estadoLabels = [
    'pendiente'   => ['label' => 'Pendiente',   'clase' => 'badge-pendiente'],
    'en_revision' => ['label' => 'En revisión', 'clase' => 'badge-en_revision'],
    'notificado'  => ['label' => 'Notificado',  'clase' => 'badge-notificado'],
    'resuelto'    => ['label' => 'Resuelto',    'clase' => 'badge-resuelto'],
];
$estadoActual = (string)($reporte['estado'] ?? 'pendiente');
$estadoInfo   = $estadoLabels[$estadoActual] ?? ['label' => ucfirst($estadoActual), 'clase' => ''];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Actualizar reporte – Ojo en la Via</title>
<link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;color:#2c3e50;min-height:100vh;display:flex;flex-direction:column;}

.topbar{background:#1a2332;padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 8px rgba(0,0,0,.18);}
.topbar-brand{display:flex;align-items:center;gap:10px;color:#fff;font-size:1rem;font-weight:700;}
.topbar-brand i{color:#3498db;font-size:1.15rem;}
.topbar-sub{font-size:.75rem;color:rgba(255,255,255,.45);font-weight:400;margin-top:1px;}

.page-wrap{flex:1;display:flex;align-items:flex-start;justify-content:center;padding:36px 16px 48px;}
.container{width:100%;max-width:580px;}

.section-card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 6px rgba(0,0,0,.07);margin-bottom:18px;}
.section-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #f0f2f5;}
.section-card-header h4{font-size:.98rem;font-weight:600;display:flex;align-items:center;gap:8px;color:#2c3e50;}
.section-card-header h4 i{color:#3498db;}

.alert{border-radius:8px;padding:13px 16px;margin-bottom:18px;font-size:.88rem;display:flex;align-items:flex-start;gap:10px;}
.alert i{margin-top:1px;flex-shrink:0;}
.alert-error{background:#fdedec;color:#c0392b;border-left:4px solid #e74c3c;}
.alert-success{background:#eafaf1;color:#1e8449;border-left:4px solid #27ae60;}

.field{margin-bottom:14px;}
.field label{display:block;font-size:.78rem;font-weight:600;color:#7f8c8d;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;}
.field-value{font-size:.9rem;color:#2c3e50;background:#f8f9fa;border-radius:8px;padding:10px 13px;border:1px solid #eaecef;line-height:1.5;}
.fields-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:600;}
.badge-pendiente{background:#fef9e7;color:#d68910;}
.badge-en_revision{background:#eaf3fb;color:#2471a3;}
.badge-notificado{background:#f5eef8;color:#7d3c98;}
.badge-resuelto{background:#eafaf1;color:#1e8449;}

.form-label{display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:#2c3e50;}
.form-control{width:100%;padding:9px 12px;border:1.5px solid #dde1e4;border-radius:8px;font-size:.88rem;color:#2c3e50;outline:none;transition:.2s;background:#fff;font-family:inherit;}
.form-control:focus{border-color:#3498db;box-shadow:0 0 0 3px rgba(52,152,219,.12);}
textarea.form-control{resize:vertical;min-height:90px;}
.form-hint{font-size:.78rem;color:#7f8c8d;margin-top:4px;}

.btn-group{display:flex;gap:10px;margin-top:20px;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 20px;border:none;border-radius:8px;font-size:.88rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none;flex:1;}
.btn-primary{background:#3498db;color:#fff;}
.btn-primary:hover{background:#2176ae;}
.btn-success{background:#27ae60;color:#fff;}
.btn-success:hover{background:#1e8449;}

.page-footer{text-align:center;padding:18px;font-size:.75rem;color:#aab0b8;border-top:1px solid #e8eaed;background:#fff;}

.divider{border:none;border-top:1px solid #f0f2f5;margin:18px 0;}

@media(max-width:480px){
    .fields-grid{grid-template-columns:1fr;}
    .btn-group{flex-direction:column;}
    .topbar{padding:0 16px;}
}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">
        <i class="fas fa-eye"></i>
        <div>
            Ojo en la Via
            <div class="topbar-sub">Portal de entidades</div>
        </div>
    </div>
</div>

<div class="page-wrap">
    <div class="container">

        <?php if ($error): ?>
        <div class="section-card">
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <p style="font-size:.85rem;color:#7f8c8d;text-align:center;">
                Si cree que esto es un error, comuníquese con el administrador.
            </p>
        </div>

        <?php else: ?>

        <?php if ($exito): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= $exito ?></span>
        </div>
        <?php endif; ?>

        <?php if ($reporte): ?>

        <!-- Detalles del reporte -->
        <div class="section-card">
            <div class="section-card-header">
                <h4><i class="fas fa-file-alt"></i> Detalle del reporte</h4>
                <span class="badge <?= $estadoInfo['clase'] ?>"><?= htmlspecialchars($estadoInfo['label']) ?></span>
            </div>

            <div class="fields-grid">
                <div class="field">
                    <label>Tipo de incidente</label>
                    <div class="field-value">
                        <?= htmlspecialchars((string)($reporte['tipo'] ?? $reporte['tipo_incidente'] ?? 'Sin tipo')) ?>
                    </div>
                </div>
                <div class="field">
                    <label>Entidad asignada</label>
                    <div class="field-value">
                        <?= htmlspecialchars($tokenData['entidad']) ?>
                    </div>
                </div>
            </div>

            <div class="field">
                <label>Ubicacion</label>
                <div class="field-value">
                    <?= htmlspecialchars((string)($reporte['direccion_texto'] ?? 'No especificada')) ?>
                </div>
            </div>

            <div class="field">
                <label>Descripcion</label>
                <div class="field-value">
                    <?= nl2br(htmlspecialchars((string)($reporte['descripcion'] ?? 'Sin descripción'))) ?>
                </div>
            </div>
        </div>

        <!-- Formulario de actualización -->
        <?php if ($estadoActual !== 'resuelto'): ?>
        <div class="section-card">
            <div class="section-card-header">
                <h4><i class="fas fa-edit"></i> Actualizar estado</h4>
            </div>

            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <label class="form-label">Comentario <span style="font-weight:400;color:#aab0b8;">(opcional)</span></label>
                <textarea name="comentario" class="form-control" rows="3" maxlength="500"
                          placeholder="Ej: Se inicio revisión del sector, estimado de resolución 48h..."></textarea>
                <div class="form-hint">El comentario se incluye en la notificación al ciudadano.</div>

                <div class="btn-group">
                    <button type="submit" name="estado" value="en_revision" class="btn btn-primary">
                        <i class="fas fa-tools"></i> Marcar en revisión
                    </button>
                    <button type="submit" name="estado" value="resuelto" class="btn btn-success">
                        <i class="fas fa-check-double"></i> Marcar como resuelto
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="section-card" style="text-align:center;padding:30px;">
            <i class="fas fa-check-circle" style="font-size:2.2rem;color:#27ae60;margin-bottom:10px;display:block;"></i>
            <p style="font-size:.95rem;font-weight:600;color:#2c3e50;">Este reporte ya fue marcado como resuelto.</p>
            <p style="font-size:.83rem;color:#7f8c8d;margin-top:6px;">No se requiere ninguna acción adicional.</p>
        </div>
        <?php endif; ?>

        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<div class="page-footer">
    Ojo en la Via &middot; Villavicencio, Colombia &middot; Enlace de un solo uso, válido 7 días
</div>

</body>
</html>
