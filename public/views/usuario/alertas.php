<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

function formatearFecha($fecha)
{
    if ($fecha instanceof \MongoDB\BSON\UTCDateTime) {
        return $fecha
            ->toDateTime()
            ->setTimezone(new DateTimeZone('America/Bogota'))
            ->format('d/m/Y');
    }

    return 'Fecha no disponible';
}

function tiempoTranscurrido($fecha)
{
    if (!$fecha instanceof \MongoDB\BSON\UTCDateTime) {
        return '';
    }

    $fechaReporte = $fecha->toDateTime()->setTimezone(new DateTimeZone('America/Bogota'));
    $ahora = new DateTime('now', new DateTimeZone('America/Bogota'));

    $diferencia = $ahora->getTimestamp() - $fechaReporte->getTimestamp();

    if ($diferencia < 60) {
        return 'Ahora';
    }

    if ($diferencia < 3600) {
        return floor($diferencia / 60) . ' min';
    }

    if ($diferencia < 86400) {
        return floor($diferencia / 3600) . ' h';
    }

    return floor($diferencia / 86400) . ' d';
}

function textoEstado($estado)
{
    switch ($estado) {
        case 'pendiente':
            return 'PENDIENTE';

        case 'en_revision':
            return 'EN REVISIÓN';

        case 'notificado':
            return 'NOTIFICADO';

        case 'resuelto':
            return 'RESUELTO';

        default:
            return strtoupper($estado ?: 'PENDIENTE');
    }
}

function obtenerNombreUsuario($reporte, $usuarios)
{
    if (empty($reporte['usuario_id'])) {
        return 'Usuario sin nombre';
    }

    try {
        $usuarioId = $reporte['usuario_id'];

        if ($usuarioId instanceof \MongoDB\BSON\ObjectId) {
            $usuario = $usuarios->findOne([
                '_id' => $usuarioId
            ]);
        } else {
            $usuarioIdTexto = (string) $usuarioId;

            if (!preg_match('/^[a-f\d]{24}$/i', $usuarioIdTexto)) {
                return 'Usuario sin nombre';
            }

            $usuario = $usuarios->findOne([
                '_id' => new \MongoDB\BSON\ObjectId($usuarioIdTexto)
            ]);
        }

        if (!$usuario) {
            return 'Usuario sin nombre';
        }

        return $usuario['nombre_completo'] ?? 'Usuario sin nombre';

    } catch (Throwable $e) {
        return 'Usuario sin nombre';
    }
}
try {
    $db = conectarMongoDB();

    $reportes = $db->reportes;
    $usuarios = $db->usuarios;

    $cursor = $reportes->find(
        [],
        ['sort' => ['fecha_reporte' => -1]]
    );

} catch (Throwable $e) {
    $cursor = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas</title>

    <link rel="stylesheet" href="/views/components/Css_usuario/inicio-mapa.css">
    <link rel="stylesheet" href="/views/components/Css_usuario/alertas.css">
</head>
<body class="body-alertas">

    <div class="bottom-hover-zone" id="bottomHoverZone"></div>

    <nav class="bottom-nav" id="bottomNav">
        <a href="alertas.php" class="active">
            <span class="icon">🔔</span>
            <span>Alertas</span>
        </a>

        <a href="inicio.php">
            <span class="icon">🗺️</span>
            <span>Mapa</span>
        </a>

        <a href="perfil.php">
            <span class="icon">👤</span>
            <span>Perfil</span>
        </a>
    </nav>

    <main class="pagina-alertas">
        <h2 class="titulo-alertas">Alertas recientes</h2>

        <section class="lista-alertas-pagina">
            <?php foreach ($cursor as $reporte): ?>
                <?php
                    $nombreUsuario = obtenerNombreUsuario($reporte, $usuarios);

                    $tipo = $reporte['tipo']
                        ?? $reporte['tipo_incidente']
                        ?? 'Incidente';

                    $descripcion = $reporte['descripcion'] ?? 'Sin descripción';

                    $estado = $reporte['estado'] ?? 'pendiente';

                    $latitud = $reporte['latitud']
                        ?? $reporte['ubicacion']['lat']
                        ?? $reporte['ubicacion']['latitud']
                        ?? null;

                    $longitud = $reporte['longitud']
                        ?? $reporte['ubicacion']['lng']
                        ?? $reporte['ubicacion']['longitud']
                        ?? null;

                    $fecha = $reporte['fecha_reporte'] ?? null;

                    $fechaFormateada = formatearFecha($fecha);
                    $tiempo = tiempoTranscurrido($fecha);

                    $comentarios = isset($reporte['comentarios']) && is_countable($reporte['comentarios'])
                        ? count($reporte['comentarios'])
                        : 0;

                    $likes = isset($reporte['likes']) && is_countable($reporte['likes'])
                        ? count($reporte['likes'])
                        : 0;

                    $inicial = mb_strtoupper(mb_substr($nombreUsuario, 0, 1));
                ?>

                <article class="alerta-card-red">
                    <div class="alerta-header">
                        <div class="alerta-avatar">
                            <?php echo htmlspecialchars($inicial); ?>
                        </div>

                        <div class="alerta-info">
                            <div class="alerta-linea-superior">
                                <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong>
                            </div>

                            <div class="alerta-meta">
                                <span>📍 Ubicación en mapa</span>

                                <?php if (!empty($tiempo)): ?>
                                    <span>⏱ <?php echo htmlspecialchars($tiempo); ?></span>
                                <?php endif; ?>

                                <span class="chip-tipo">
                                    <?php echo htmlspecialchars($tipo); ?>
                                </span>
                            </div>

                            <span class="chip-estado estado-<?php echo htmlspecialchars($estado); ?>">
                                <?php echo htmlspecialchars(textoEstado($estado)); ?>
                            </span>
                        </div>
                    </div>

                    <p class="alerta-descripcion">
                        <?php echo htmlspecialchars($descripcion); ?>
                    </p>

                    <div class="alerta-detalles">
                        <?php if ($latitud !== null && $longitud !== null): ?>
                            <div>
                                🛣️ Coordenadas:
                                <?php echo htmlspecialchars($latitud); ?>,
                                <?php echo htmlspecialchars($longitud); ?>
                            </div>
                        <?php else: ?>
                            <div>🛣️ Coordenadas no disponibles</div>
                        <?php endif; ?>

                        <div>🗓️ <?php echo htmlspecialchars($fechaFormateada); ?></div>
                    </div>

                    <div class="alerta-acciones">
                        <button type="button">❤️ <?php echo $likes; ?></button>

                        <button type="button">
                            💬 Comentarios (<?php echo $comentarios; ?>)
                        </button>

                        <?php if ($latitud !== null && $longitud !== null): ?>
                            <a href="inicio.php?lat=<?php echo urlencode($latitud); ?>&lng=<?php echo urlencode($longitud); ?>">
                                📍 Ver en Mapa
                            </a>
                        <?php else: ?>
                            <a href="inicio.php">📍 Ver en Mapa</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <script src="/views/components/JS_usuario/menu-inferior.js"></script>
</body>
</html>