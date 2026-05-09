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

function normalizarEstado($estado)
{
    $estado = strtolower(trim($estado));

    if ($estado === 'pendiente') {
        return 'pendiente';
    }

    if ($estado === 'en revisión' || $estado === 'en_revision') {
        return 'en_revision';
    }

    if ($estado === 'notificado') {
        return 'notificado';
    }

    if ($estado === 'resuelto') {
        return 'resuelto';
    }

    return 'pendiente';
}


try {
    $db = conectarMongoDB();

    $reportes = $db->reportes;
    $likesReportes = $db->likes_reporte;
    $comentariosReporte = $db->comentarios_reporte;

     $comentariosAutoIdTexto = $_GET['comentarios'] ?? '';
    $filtroReportes = [];

    if ($comentariosAutoIdTexto !== '' && preg_match('/^[a-f\d]{24}$/i', $comentariosAutoIdTexto)) {
        $filtroReportes['_id'] = new \MongoDB\BSON\ObjectId($comentariosAutoIdTexto);
    }
    $pipeline = [];

if (!empty($filtroReportes)) {
    $pipeline[] = [
        '$match' => $filtroReportes
    ];
}

$pipeline[] = [
    '$lookup' => [
        'from' => 'usuario',
        'localField' => 'usuario_id',
        'foreignField' => '_id',
        'as' => 'usuario'
    ]
];

$pipeline[] = [
    '$sort' => [
        'fecha_reporte' => -1
    ]
];

$cursor = $reportes->aggregate($pipeline);

} catch (Throwable $e) {
    $cursor = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
    <title>Alertas</title>

    <link rel="stylesheet" href="/views/components/Css_usuario/alertas.css">
    <link rel="stylesheet" href="/views/components/Css_usuario/comentarios-reportes.css">
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
                    $usuarioReporte = $reporte['usuario'][0] ?? null;

                    $nombreUsuario = 'Usuario sin nombre';

                    if ($usuarioReporte && !empty($usuarioReporte['nombre_completo'])) {
                        $nombreUsuario = $usuarioReporte['nombre_completo'];
                    } elseif ($usuarioReporte && !empty($usuarioReporte['nombre'])) {
                        $nombreUsuario = $usuarioReporte['nombre'];
                    } elseif ($usuarioReporte && !empty($usuarioReporte['nombre_usuario'])) {
                        $nombreUsuario = $usuarioReporte['nombre_usuario'];
                    }

                    $tipo = $reporte['tipo']
                        ?? $reporte['tipo_incidente']
                        ?? 'Incidente';

                    $descripcion = $reporte['descripcion'] ?? 'Sin descripción';
                  $fotoReporte = null;

                $imagenesReporte = $reporte['imagenes'] ?? [];

                if ($imagenesReporte instanceof \MongoDB\Model\BSONArray) {
                    $imagenesReporte = $imagenesReporte->getArrayCopy();
                }

                if (is_array($imagenesReporte) && !empty($imagenesReporte[0])) {
                    $fotoReporte = (string) $imagenesReporte[0];
                }
                    $estado = normalizarEstado($reporte['estado'] ?? 'pendiente');

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

                    $inicial = mb_strtoupper(mb_substr($nombreUsuario, 0, 1));

                    $reporteIdObj = $reporte['_id'];
                    $usuarioIdActual = new \MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id']);

                    $totalLikes = $likesReportes->countDocuments([
                        'reporte_id' => $reporteIdObj
                    ]);

                    $yaDioLike = $likesReportes->countDocuments([
                        'reporte_id' => $reporteIdObj,
                        'usuario_id' => $usuarioIdActual
                    ]) > 0;

                 $totalComentariosReporte = $comentariosReporte->countDocuments([
                    'reporte_id' => $reporteIdObj,
                    'comentario_padre_id' => null
                ]);
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

           
              <?php if (!empty($fotoReporte)): ?>
                <img 
                    src="/<?php echo htmlspecialchars(ltrim($fotoReporte, '/')); ?>" 
                    alt="Foto del reporte"
                    class="alerta-foto-reporte"
                >
            <?php endif; ?>

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
                        <button 
                            type="button"
                            class="btn-like-reporte <?php echo $yaDioLike ? 'liked' : ''; ?>"
                            data-reporte-id="<?php echo htmlspecialchars((string) $reporte['_id']); ?>"
                        >
                            ❤️ <span class="like-count"><?php echo $totalLikes; ?></span>
                        </button>

                        <button 
                        type="button" 
                        class="btn-abrir-comentarios"
                        data-reporte-id="<?php echo htmlspecialchars((string) $reporte['_id']); ?>"
                    >
                       💬 Comentarios (<span class="comment-count"><?php echo $totalComentariosReporte; ?></span>)
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
    <div class="comentarios-overlay" id="comentariosOverlay">
    <div class="comentarios-modal">
        <div class="comentarios-header">
            <h3><span id="comentariosTotal">0</span> comentarios</h3>
            <button type="button" id="cerrarComentarios" class="cerrar-comentarios">×</button>
        </div>

        <div class="comentarios-lista" id="comentariosLista">
            <p class="comentarios-vacio">Cargando comentarios...</p>
        </div>

        <form class="comentario-form" id="comentarioForm">
            <input type="hidden" id="comentarioReporteId" name="reporte_id">

            <div class="comentario-input-wrap">
                <input 
                    type="text" 
                    id="comentarioTexto" 
                    name="comentario" 
                    placeholder="Agregar comentario..." 
                    maxlength="500"
                    autocomplete="off"
                >

                <button type="submit">Enviar</button>
            </div>
        </form>
    </div>
</div>
 <script src="/views/components/JS_usuario/menu-inferior.js"></script>
<script src="/views/components/JS_usuario/likes-reportes.js"></script>
<script src="/views/components/JS_usuario/comentarios-reportes.js"></script>

</body>
</html>
