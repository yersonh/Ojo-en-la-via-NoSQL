<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/auth_helper.php';

verificar_autenticacion('../../index.php');

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

function reportePerteneceUsuario($reporte, \MongoDB\BSON\ObjectId $usuarioIdActual)
{
    $usuarioIdTexto = (string) $usuarioIdActual;
    $posiblesCampos = [
        $reporte['usuario_id'] ?? null,
        $reporte['usuario_creador_id'] ?? null,
    ];

    foreach ($posiblesCampos as $campo) {
        if ($campo !== null && (string) $campo === $usuarioIdTexto) {
            return true;
        }
    }

    return false;
}

function normalizarArrayBson($valor): array
{
    if ($valor instanceof \MongoDB\Model\BSONArray) {
        return $valor->getArrayCopy();
    }

    return is_array($valor) ? $valor : [];
}

function usuarioDioLikeReporte($likes, \MongoDB\BSON\ObjectId $usuarioIdActual): bool
{
    foreach (normalizarArrayBson($likes) as $like) {
        $usuarioLike = $like['usuario_id'] ?? $like['usuario_origen_id'] ?? null;

        if ($usuarioLike !== null && (string) $usuarioLike === (string) $usuarioIdActual) {
            return true;
        }
    }

    return false;
}


try {
    $db = conectarMongoDB();

    $reportes = $db->Reportes;

    $comentariosAutoIdTexto = $_GET['comentarios'] ?? '';
    $reporteAutoIdTexto = $_GET['reporte'] ?? '';
    $filtroReportes = [];

    if ($comentariosAutoIdTexto !== '' && preg_match('/^[a-f\d]{24}$/i', $comentariosAutoIdTexto)) {
        $filtroReportes['_id'] = new \MongoDB\BSON\ObjectId($comentariosAutoIdTexto);
    } elseif ($reporteAutoIdTexto !== '' && preg_match('/^[a-f\d]{24}$/i', $reporteAutoIdTexto)) {
        $filtroReportes['_id'] = new \MongoDB\BSON\ObjectId($reporteAutoIdTexto);
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <link rel="stylesheet" href="/views/usuario/reportes/css/alertas.css">
    <link rel="stylesheet" href="/views/usuario/reportes/css/comentarios-reportes.css">
</head>
<body class="body-alertas">

    <div class="bottom-hover-zone" id="bottomHoverZone"></div>

    <nav class="bottom-nav" id="bottomNav">
        <a href="alertas.php" class="active">
            <span class="icon"><i class="fas fa-bell"></i></span>
            <span>Alertas</span>
        </a>

        <a href="inicio.php">
            <span class="icon"><i class="fas fa-map"></i></span>
            <span>Mapa</span>
        </a>

        <a href="perfil.php">
            <span class="icon"><i class="fas fa-user"></i></span>
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

                    $direccion = trim((string)($reporte['direccion_texto'] ?? ''));

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

                    $likesReporte = normalizarArrayBson($reporte['likes'] ?? []);
                    $totalLikes = count($likesReporte);
                    $yaDioLike = usuarioDioLikeReporte($likesReporte, $usuarioIdActual);

                    $comentariosReporte = normalizarArrayBson($reporte['comentarios'] ?? []);
                    $totalComentariosReporte = 0;

                    foreach ($comentariosReporte as $comentarioReporte) {
                        if (($comentarioReporte['comentario_padre_id'] ?? null) === null) {
                            $totalComentariosReporte++;
                        }
                    }

                    $esReportePropio = reportePerteneceUsuario($reporte, $usuarioIdActual);
                ?>

                <article
                    class="alerta-card-red"
                    data-reporte-id="<?php echo htmlspecialchars((string) $reporte['_id']); ?>"
                    data-tipo="<?php echo htmlspecialchars((string) $tipo); ?>"
                    data-descripcion="<?php echo htmlspecialchars((string) $descripcion); ?>"
                    data-latitud="<?php echo htmlspecialchars((string) ($latitud ?? '')); ?>"
                    data-longitud="<?php echo htmlspecialchars((string) ($longitud ?? '')); ?>"
                    data-imagen="<?php echo htmlspecialchars((string) ($fotoReporte ?? '')); ?>"
                >
                    <div class="alerta-header">
                        <div class="alerta-avatar">
                            <?php echo htmlspecialchars($inicial); ?>
                        </div>

                        <div class="alerta-info">
                            <div class="alerta-linea-superior">
                                <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong>
                            </div>

                            <div class="alerta-meta">
                                <span><i class="fas fa-map-marker-alt"></i> Ubicación en mapa</span>

                                <?php if (!empty($tiempo)): ?>
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($tiempo); ?></span>
                                <?php endif; ?>

                                <span class="chip-tipo alerta-tipo-texto">
                                    <?php echo htmlspecialchars($tipo); ?>
                                </span>
                            </div>

                            <span class="chip-estado estado-<?php echo htmlspecialchars($estado); ?>">
                                <?php echo htmlspecialchars(textoEstado($estado)); ?>
                            </span>
                        </div>

                        <?php if ($esReportePropio): ?>
                            <div class="reporte-menu">
                                <button type="button" class="btn-reporte-menu" aria-label="Opciones del reporte">
                                    ...
                                </button>

                                <div class="reporte-menu-opciones">
                                    <button type="button" class="btn-alerta-editar-reporte">Editar</button>
                                    <button type="button" class="btn-alerta-eliminar-reporte">Eliminar</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>


                      <p class="alerta-descripcion">
                        <?php echo htmlspecialchars($descripcion); ?>
                    </p>                                  

           
              <?php if (!empty($fotoReporte)): ?>
                <img
                    src="/<?php echo htmlspecialchars(ltrim($fotoReporte, '/')); ?>"
                    alt="Foto del reporte"
                    class="alerta-foto-reporte img-lightbox"
                    style="cursor:zoom-in;"
                >
            <?php endif; ?>

                    <div class="alerta-detalles">
                        <div>
                            <i class="fas fa-map-marker-alt" style="margin-right:5px;color:#7f8c8d;"></i>
                            <?php echo htmlspecialchars($direccion !== '' ? $direccion : 'Ubicación no disponible'); ?>
                        </div>
                        <div>
                            <i class="fas fa-calendar" style="margin-right:5px;color:#7f8c8d;"></i>
                            <?php echo htmlspecialchars($fechaFormateada); ?>
                        </div>
                    </div>
                    
                    <div class="alerta-acciones">
                        <button 
                            type="button"
                            class="btn-like-reporte <?php echo $yaDioLike ? 'liked' : ''; ?>"
                            data-reporte-id="<?php echo htmlspecialchars((string) $reporte['_id']); ?>"
                        >
                            <i class="fas fa-heart"></i> <span class="like-count"><?php echo $totalLikes; ?></span>
                        </button>

                        <button 
                        type="button" 
                        class="btn-abrir-comentarios"
                        data-reporte-id="<?php echo htmlspecialchars((string) $reporte['_id']); ?>"
                    >
                       <i class="fas fa-comments"></i> Comentarios (<span class="comment-count"><?php echo $totalComentariosReporte; ?></span>)
                    </button>

                        <?php if ($latitud !== null && $longitud !== null): ?>
                            <a class="alerta-link-mapa" href="inicio.php?reporte=<?php echo urlencode((string) $reporte['_id']); ?>&lat=<?php echo urlencode($latitud); ?>&lng=<?php echo urlencode($longitud); ?>">
                                <i class="fas fa-map-marker-alt"></i> Ver en Mapa
                            </a>
                        <?php else: ?>
                            <a class="alerta-link-mapa" href="inicio.php"><i class="fas fa-map-marker-alt"></i> Ver en Mapa</a>
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
<div class="editar-reporte-overlay" id="editarReporteOverlay" aria-hidden="true">
    <div class="editar-reporte-modal">
        <div class="editar-reporte-header">
            <h3>Editar reporte</h3>
            <button type="button" id="cerrarEditarReporte" class="cerrar-editar-reporte">×</button>
        </div>

        <form id="editarReporteForm" class="editar-reporte-form">
            <input type="hidden" name="reporte_id" id="editarReporteId">

            <label for="editarReporteTipo">Tipo de incidente</label>
            <select name="tipo" id="editarReporteTipo" required>
                <option value="">Seleccione un tipo</option>
                <option value="Accidente">Accidente</option>
                <option value="Hueco">Hueco</option>
                <option value="Trafico">Trafico</option>
                <option value="Obstruccion">Obstruccion</option>
            </select>

            <label for="editarReporteDescripcion">Descripcion</label>
            <textarea name="descripcion" id="editarReporteDescripcion" maxlength="800" required></textarea>

            <div class="editar-reporte-ubicacion-head">
                <label>Ubicacion</label>
                <button type="button" id="usarUbicacionActualEditar" class="btn-ubicacion-actual-editar">
                    <span>📍</span>
                    Usar mi ubicacion
                </button>
            </div>
            <div class="editar-reporte-mapa" id="editarReporteMapa"></div>
            <p class="editar-reporte-ayuda">
                Haz clic en el mapa para mover el punto del reporte.
            </p>

            <div class="editar-reporte-coordenadas">
                <span>Latitud: <strong id="editarReporteLatitudTexto">-</strong></span>
                <span>Longitud: <strong id="editarReporteLongitudTexto">-</strong></span>
            </div>

            <input type="hidden" name="latitud" id="editarReporteLatitud" required>
            <input type="hidden" name="longitud" id="editarReporteLongitud" required>

            <label for="editarReporteFoto">Foto del reporte</label>
            <div class="editar-reporte-foto">
                <img id="editarReporteFotoPreview" alt="Foto actual del reporte">
                <div>
                    <input type="file" name="foto" id="editarReporteFoto" accept="image/*">
                    <small>Si seleccionas una imagen nueva, reemplaza la foto actual.</small>
                </div>
            </div>

            <div class="editar-reporte-acciones">
                <button type="button" id="cancelarEditarReporte" class="btn-cancelar-editar">Cancelar</button>
                <button type="submit" class="btn-guardar-editar">Guardar</button>
            </div>
        </form>
    </div>
</div>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="/views/usuario/compartido/js/menu-inferior.js"></script>
<script src="/views/usuario/reportes/js/likes-reportes.js"></script>
<script src="/views/usuario/reportes/js/alertas-reportes.js"></script>
<script src="/views/usuario/reportes/js/comentarios-reportes.js"></script>

<!-- Lightbox -->
<div id="lightbox-overlay" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.92);align-items:center;justify-content:center;">
    <button onclick="document.getElementById('lightbox-overlay').style.display='none'"
        style="position:fixed;top:18px;right:22px;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;"
        aria-label="Cerrar">&times;</button>
    <img id="lightbox-img" src="" alt="" style="max-width:94vw;max-height:92vh;border-radius:10px;box-shadow:0 8px 40px rgba(0,0,0,.6);object-fit:contain;">
</div>
<script>
(function() {
    const lb = document.getElementById('lightbox-overlay');
    const cerrar = () => lb.style.display = 'none';

    document.querySelectorAll('.img-lightbox').forEach(img => {
        img.style.cursor = 'pointer';
        img.addEventListener('click', () => {
            document.getElementById('lightbox-img').src = img.src;
            lb.style.display = 'flex';
        });
    });

    lb.addEventListener('click', e => { if (e.target === lb) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
})();
</script>

</body>
</html>
