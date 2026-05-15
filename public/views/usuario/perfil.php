<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/auth_helper.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

verificar_autenticacion('../../index.php');

$db = conectarMongoDB();

$usuarioId = $_SESSION['usuario_id'];

try {
    $usuarioObjectId = new ObjectId($usuarioId);
} catch (Throwable $e) {
    session_destroy();
    header('Location: ../../index.php');
    exit;
}

$usuario = $db->usuario->findOne([
    '_id' => $usuarioObjectId
]);

if (!$usuario) {
    session_destroy();
    header('Location: ../../index.php');
    exit;
}

$nombreCompleto = $usuario['nombre_completo'] ?? $_SESSION['usuario_nombre'] ?? 'Usuario';
$email = $usuario['email'] ?? $_SESSION['usuario_email'] ?? 'No disponible';
$telefono = $usuario['telefono'] ?? 'No disponible';
$fotoPerfil = $usuario['foto_perfil'] ?? '';
$primerLetra = strtoupper(substr($nombreCompleto, 0, 1));

/* ========= ESTADÍSTICAS ========= */

$totalReportes = $db->reportes->countDocuments([
    '$or' => [
        ['usuario_id' => $usuarioId],
        ['usuario_id' => $usuarioObjectId],
        ['usuario_creador_id' => $usuarioId],
        ['usuario_creador_id' => $usuarioObjectId],
    ]
]);

$totalComentarios = $db->comentarios_reporte->countDocuments([
    '$or' => [
        ['usuario_id' => $usuarioId],
        ['usuario_id' => $usuarioObjectId],
        ['usuario_origen_id' => $usuarioId],
        ['usuario_origen_id' => $usuarioObjectId],
    ]
]);

$totalLikesReportes = $db->likes_reporte->countDocuments([
    '$or' => [
        ['usuario_id' => $usuarioId],
        ['usuario_id' => $usuarioObjectId],
        ['usuario_origen_id' => $usuarioId],
        ['usuario_origen_id' => $usuarioObjectId],
    ]
]);

/* ========= NOTIFICACIONES ========= */

$notificaciones = $db->notificaciones->find(
    [
        '$or' => [
            ['usuario_destino_id' => $usuarioObjectId],
            ['usuario_destino_id' => $usuarioId],
        ]
    ],
    [
        'sort' => ['fecha' => -1],
        'limit' => 20
    ]
);

$totalNoLeidas = $db->notificaciones->countDocuments([
    '$or' => [
        ['usuario_destino_id' => $usuarioObjectId],
        ['usuario_destino_id' => $usuarioId],
    ],
    'leida' => false
]);

function obtenerIconoNotificacion($tipo)
{
    return match ($tipo) {
        'comentario' => '💬',
        'respuesta_comentario' => '↩️',
        'like_reporte' => '❤️',
        'like_comentario' => '👍',
        'estado_reporte' => '📌',
        default => '🔔'
    };
}

function formatearFechaNotificacion($fecha)
{
    if ($fecha instanceof UTCDateTime) {
        return $fecha->toDateTime()->format('d/m/Y H:i');
    }

    if (is_string($fecha)) {
        return $fecha;
    }

    return '';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil - Ojo en la Vía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">

    <link rel="stylesheet" href="/views/usuario/perfil/css/perfil.css">
</head>

<body>
    <main class="perfil-page">
        <header class="perfil-header">
            <div class="perfil-avatar">
                <?php if (!empty($fotoPerfil)): ?>
                    <img 
                        src="/<?php echo htmlspecialchars(ltrim((string) $fotoPerfil, '/')); ?>" 
                        alt="Foto de perfil"
                    >
                <?php else: ?>
                    <?php echo htmlspecialchars($primerLetra); ?>
                <?php endif; ?>
            </div>

            <div class="perfil-header-info">
                <h1><?php echo htmlspecialchars($nombreCompleto); ?></h1>

                <div class="perfil-header-meta">
                    <span>✉️ <?php echo htmlspecialchars($email); ?></span>
                    <span>📞 <?php echo htmlspecialchars($telefono); ?></span>
                </div>
            </div>
        </header>

        <section class="perfil-layout">
            <div class="perfil-main">
                <article class="perfil-card">
                    <h3>👤 Información Personal</h3>

                    <div class="info-item">
                        <div class="info-icon">👤</div>
                        <div>
                            <span class="info-label">Nombre completo</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($nombreCompleto); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">✉️</div>
                        <div>
                            <span class="info-label">Correo electrónico</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($email); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">📞</div>
                        <div>
                            <span class="info-label">Teléfono</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($telefono); ?>
                            </span>
                        </div>
                    </div>
                </article>

                <article class="perfil-card notificaciones-card">
                    <div class="perfil-card-title-row">
                        <h3>🔔 Notificaciones</h3>

                        <?php if ($totalNoLeidas > 0): ?>
                            <span class="notificaciones-count" id="contadorNotificaciones">
                                <?php echo (int) $totalNoLeidas; ?> sin leer
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="notificaciones-lista">
                        <?php
                        $hayNotificaciones = false;

                        foreach ($notificaciones as $notificacion):
                            $hayNotificaciones = true;

                            $notificacionId = isset($notificacion['_id']) ? (string) $notificacion['_id'] : '';
                            $tipoNotificacion = $notificacion['tipo'] ?? 'general';
                            $tituloNotificacion = $notificacion['titulo'] ?? 'Notificación';
                            $mensajeNotificacion = $notificacion['mensaje'] ?? 'Tienes una nueva notificación.';
                            $leida = $notificacion['leida'] ?? false;
                            $fechaNotificacion = formatearFechaNotificacion($notificacion['fecha'] ?? null);
                            $reporteNotificacionId = isset($notificacion['reporte_id']) ? (string) $notificacion['reporte_id'] : '';
                            $comentarioNotificacionId = isset($notificacion['comentario_id']) ? (string) $notificacion['comentario_id'] : '';
                            $urlNotificacion = '';

                            if ($reporteNotificacionId !== '' && $comentarioNotificacionId !== '') {
                                $urlNotificacion = 'alertas.php?comentarios=' . urlencode($reporteNotificacionId) . '&comentario=' . urlencode($comentarioNotificacionId);
                            } elseif ($reporteNotificacionId !== '') {
                                $urlNotificacion = 'alertas.php?reporte=' . urlencode($reporteNotificacionId);
                            }
                        ?>
                            <div 
                                class="notificacion-item <?php echo empty($leida) ? 'no-leida' : ''; ?>"
                                data-notificacion-id="<?php echo htmlspecialchars($notificacionId); ?>"
                                data-url="<?php echo htmlspecialchars($urlNotificacion); ?>"
                            >
                                <div class="notificacion-icono">
                                    <?php echo obtenerIconoNotificacion($tipoNotificacion); ?>
                                </div>

                                <div class="notificacion-contenido">
                                    <strong>
                                        <?php echo htmlspecialchars($tituloNotificacion); ?>
                                    </strong>

                                    <p>
                                        <?php echo htmlspecialchars($mensajeNotificacion); ?>
                                    </p>

                                    <?php if (!empty($fechaNotificacion)): ?>
                                        <span>
                                            <?php echo htmlspecialchars($fechaNotificacion); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$hayNotificaciones): ?>
                            <div class="notificaciones-vacio">
                                No tienes notificaciones todavía.
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            </div>

            <aside class="perfil-side">
                <article class="perfil-card">
                    <h3>📊 Estadísticas</h3>

                    <div class="estadisticas-grid">
                        <div class="estadistica-box">
                            <div class="estadistica-icon">🚩</div>
                            <span class="estadistica-numero" id="totalReportesPerfil">
                                <?php echo (int) $totalReportes; ?>
                            </span>
                            <span class="estadistica-label">Reportes</span>
                        </div>

                        <div class="estadistica-box">
                            <div class="estadistica-icon">❤️</div>
                            <span class="estadistica-numero">
                                <?php echo (int) $totalLikesReportes; ?>
                            </span>
                            <span class="estadistica-label">Likes</span>
                        </div>

                        <div class="estadistica-box">
                            <div class="estadistica-icon">💬</div>
                            <span class="estadistica-numero">
                                <?php echo (int) $totalComentarios; ?>
                            </span>
                            <span class="estadistica-label">Comentarios</span>
                        </div>

                        <div class="estadistica-box">
                            <div class="estadistica-icon">🔔</div>
                            <span class="estadistica-numero" id="numeroNoLeidas">
                                <?php echo (int) $totalNoLeidas; ?>
                            </span>
                            <span class="estadistica-label">Sin leer</span>
                        </div>
                    </div>
                </article>

                <article class="perfil-card">
                    <a href="editar_perfil.php" class="perfil-btn">
                        <span class="perfil-btn-icon">✏️</span>

                        <span>
                            Editar Perfil
                            <small>Actualiza tu información personal</small>
                        </span>
                    </a>

                    <form action="../../index.php" method="POST">
                        <input type="hidden" name="accion" value="logout">

                        <button type="submit" class="perfil-btn logout">
                            <span class="perfil-btn-icon">🚪</span>

                            <span>
                                Cerrar Sesión
                                <small>Salir de tu cuenta</small>
                            </span>
                        </button>
                    </form>
                </article>
            </aside>
        </section>
    </main>
<div class="bottom-hover-zone" id="bottomHoverZone"></div>

<nav class="bottom-nav" id="bottomNav">
    <a href="alertas.php">
        <span class="icon">🔔</span>
        <span>Alertas</span>
    </a>

    <a href="inicio.php">
        <span class="icon">🗺️</span>
        <span>Mapa</span>
    </a>

    <a href="perfil.php" class="active">
        <span class="icon">👤</span>
        <span>Perfil</span>
    </a>
</nav>

<script src="/views/usuario/compartido/js/menu-inferior.js"></script>
<script src="/views/usuario/perfil/js/perfil.js"></script>
</body>
</html>
