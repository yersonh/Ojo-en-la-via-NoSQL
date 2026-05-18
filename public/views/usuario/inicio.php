<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/auth_helper.php';
require_once __DIR__ . '/../../../config/notificacion_entidad_auto.php';
require_once __DIR__ . '/../../../config/upload_helper.php';

verificar_autenticacion('../../index.php');

$mensaje = '';
$tipoMensaje = '';

if (($_GET['reporte'] ?? '') === 'ok') {
    $mensaje = 'Reporte guardado correctamente.';
    $tipoMensaje = 'success';
}

$nombreMostrar = trim($_SESSION['usuario_nombre'] ?? '');

if ($nombreMostrar === '') {
    $nombreMostrar = $_SESSION['usuario_email'] ?? 'Usuario';
}

try {
    $db = conectarMongoDB();

    $reportes = $db->Reportes;
    $usuarios = $db->usuario;

    if (!empty($_SESSION['usuario_id'])) {
        try {
            $usuarioActual = $usuarios->findOne([
                '_id' => new \MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id'])
            ]);

            if ($usuarioActual && !empty($usuarioActual['nombre_completo'])) {
                $nombreMostrar = (string) $usuarioActual['nombre_completo'];
                $_SESSION['usuario_nombre'] = $nombreMostrar;
            } elseif ($usuarioActual && !empty($usuarioActual['nombre'])) {
                $nombreMostrar = (string) $usuarioActual['nombre'];
                $_SESSION['usuario_nombre'] = $nombreMostrar;
            } elseif ($usuarioActual && !empty($usuarioActual['nombre_usuario'])) {
                $nombreMostrar = (string) $usuarioActual['nombre_usuario'];
                $_SESSION['usuario_nombre'] = $nombreMostrar;
            }
        } catch (Throwable $e) {
            // Mantiene el nombre que ya venga en sesión.
        }
    }

} catch (Throwable $e) {
    die("Error de conexión: " . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = trim($_POST['tipo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $latitud = trim($_POST['latitud'] ?? '');
    $longitud = trim($_POST['longitud'] ?? '');

    if ($tipo === '' || $descripcion === '' || $latitud === '' || $longitud === '') {
        $mensaje = 'Debes completar el tipo, la descripción y seleccionar una ubicación en el mapa.';
        $tipoMensaje = 'error';
    } else {
        try {
            $imagenes = [];

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $directorioSubidas = getUploadDir();

                $nombreOriginal = $_FILES['foto']['name'];
                $tmp = $_FILES['foto']['tmp_name'];
                $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

                $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($extension, $extPermitidas, true)) {
                    throw new Exception('La imagen debe ser jpg, jpeg, png o webp.');
                }

                $nombreArchivo = uniqid('reporte_', true) . '.' . $extension;
                $rutaFinal = $directorioSubidas . $nombreArchivo;

                if (!move_uploaded_file($tmp, $rutaFinal)) {
                    throw new Exception('No se pudo guardar la imagen.');
                }

                $imagenes[] = 'uploads/reportes/' . $nombreArchivo;
            }

            $usuarioReporteId = new \MongoDB\BSON\ObjectId((string) $_SESSION['usuario_id']);
            $fechaReporte = new \MongoDB\BSON\UTCDateTime();

            $direccionTexto = trim($_POST['direccion_texto'] ?? '');

            $documento = [
                'usuario_id' => $usuarioReporteId,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'ubicacion' => [
                    'latitud' => (float) $latitud,
                    'longitud' => (float) $longitud
                ],
                'direccion_texto' => $direccionTexto,
                'imagenes' => $imagenes,
                'estado' => 'pendiente',
                'fecha_reporte' => $fechaReporte,
                'fecha_estado' => $fechaReporte,
                'likes' => [],
                'comentarios' => [],
                'historial_estados' => [
                    [
                        'estado_anterior' => null,
                        'estado_nuevo' => 'pendiente',
                        'usuario_id' => $usuarioReporteId,
                        'fecha_estado' => $fechaReporte
                    ]
                ]
            ];

            $resultado = $reportes->insertOne($documento);

            if ($resultado->getInsertedCount() > 0) {
                dispararNotificacionEntidad($db, $documento, $resultado->getInsertedId());
                header('Location: inicio.php?reporte=ok');
                exit;
            } else {
                $mensaje = 'No se pudo guardar el reporte.';
                $tipoMensaje = 'error';
            }

        } catch (Throwable $e) {
            $mensaje = 'Error al guardar el reporte: ' . htmlspecialchars($e->getMessage());
            $tipoMensaje = 'error';
        }
    }
}

$reportesMapa = [];

try {
    $cursor = $reportes->aggregate([
        [
            '$match' => [
                'estado' => [
                    '$in' => [
                        'pendiente',
                        'Pendiente',
                        'en_revision',
                        'En revisión',
                        'notificado',
                        'Notificado',
                        'resuelto',
                        'Resuelto'
                    ]
                ]
            ]
        ],
        [
            '$lookup' => [
                'from' => 'usuario',
                'localField' => 'usuario_id',
                'foreignField' => '_id',
                'as' => 'usuario'
            ]
        ],
        [
            '$sort' => [
                'fecha_reporte' => -1
            ]
        ]
    ]);

    foreach ($cursor as $reporte) {
        $lat = $reporte['ubicacion']['latitud']
            ?? $reporte['ubicacion']['lat']
            ?? $reporte['latitud']
            ?? null;

        $lng = $reporte['ubicacion']['longitud']
            ?? $reporte['ubicacion']['lng']
            ?? $reporte['longitud']
            ?? null;

        if ($lat === null || $lng === null) {
            continue;
        }

        $fechaFormateada = '';

        if (
            !empty($reporte['fecha_reporte']) &&
            $reporte['fecha_reporte'] instanceof \MongoDB\BSON\UTCDateTime
        ) {
            $fechaFormateada = $reporte['fecha_reporte']
                ->toDateTime()
                ->setTimezone(new DateTimeZone('America/Bogota'))
                ->format('d/m/Y, H:i');
        }

        $imagenesReporte = $reporte['imagenes'] ?? [];
        $primeraImagen = null;

        if ($imagenesReporte instanceof \MongoDB\Model\BSONArray) {
            $imagenesReporte = $imagenesReporte->getArrayCopy();
        }

        if (is_array($imagenesReporte) && isset($imagenesReporte[0])) {
            $primeraImagen = (string) $imagenesReporte[0];
        }

        $usuarioReporte = null;

        if (!empty($reporte['usuario'])) {
            foreach ($reporte['usuario'] as $usuarioEncontrado) {
                $usuarioReporte = $usuarioEncontrado;
                break;
            }
        }

        $nombreReportante = 'No disponible';

        if ($usuarioReporte && !empty($usuarioReporte['nombre_completo'])) {
            $nombreReportante = (string) $usuarioReporte['nombre_completo'];
        } elseif ($usuarioReporte && !empty($usuarioReporte['nombre'])) {
            $nombreReportante = (string) $usuarioReporte['nombre'];
        } elseif ($usuarioReporte && !empty($usuarioReporte['nombre_usuario'])) {
            $nombreReportante = (string) $usuarioReporte['nombre_usuario'];
        }

        $reportesMapa[] = [
            'id' => (string) $reporte['_id'],
            'tipo' => $reporte['tipo'] ?? 'Incidente',
            'descripcion' => $reporte['descripcion'] ?? '',
            'latitud' => (float) $lat,
            'longitud' => (float) $lng,
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'direccion_texto' => $reporte['direccion_texto'] ?? '',
            'imagen' => $primeraImagen,
            'fecha' => $fechaFormateada,
            'estado' => $reporte['estado'] ?? 'pendiente',
            'usuario_nombre' => $nombreReportante
        ];
    }

    

} catch (Throwable $e) {
    $reportesMapa = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
    <title>Inicio - Mapa</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="stylesheet" href="/views/compartido/css/popup-reporte.css">
    <link rel="stylesheet" href="/views/usuario/inicio/css/inicio-mapa.css">
</head>

<body>
    <div class="bottom-hover-zone" id="bottomHoverZone"></div>

    <nav class="bottom-nav" id="bottomNav">
        <a href="alertas.php">
            <span class="icon"><i class="fas fa-bell"></i></span>
            <span>Alertas</span>
        </a>

        <a href="inicio.php" class="active">
            <span class="icon"><i class="fas fa-map"></i></span>
            <span>Mapa</span>
        </a>

        <a href="perfil.php">
            <span class="icon"><i class="fas fa-user"></i></span>
            <span>Perfil</span>
        </a>
    </nav>

    <div class="contenedor">
        <div class="topbar">
            <div class="topbar-left">
                <div class="user-avatar">
                    <?php if (!empty($_SESSION['foto_perfil'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['foto_perfil']); ?>" alt="Foto de perfil">
                    <?php else: ?>
                        <?php
                            $inicial = 'U';

                            if (!empty($nombreMostrar)) {
                                $inicial = strtoupper(substr(trim($nombreMostrar), 0, 1));
                            }

                            echo htmlspecialchars($inicial);
                        ?>
                    <?php endif; ?>
                </div>

                <h2>Bienvenida, <?php echo htmlspecialchars($nombreMostrar); ?></h2>
            </div>

        </div>

        <div class="mapa">
            <div id="map"></div>

            <button type="button" id="btnMiUbicacion" class="btn-mi-ubicacion">
                📍 Mi ubicación
            </button>
        </div>

        <div class="panel oculto" id="panelRegistro">
        <button type="button" class="cerrar-panel" id="cerrarPanel">×</button>

        <h3>Registrar incidente</h3>

            <?php if ($mensaje !== ''): ?>
                <div class="alerta <?php echo htmlspecialchars($tipoMensaje); ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <label for="tipo">Tipo de incidente:</label>

                <select name="tipo" id="tipo" required>
                    <option value="">Seleccione un tipo</option>
                    <option value="Accidente">Accidente</option>
                    <option value="Hueco">Hueco</option>
                    <option value="Tráfico">Tráfico</option>
                    <option value="Obstrucción">Obstrucción</option>
                </select>

                <label for="descripcion">Descripción:</label>

                <textarea 
                    name="descripcion" 
                    id="descripcion" 
                    placeholder="Describe el incidente" 
                    required
                ></textarea>

                <label for="foto">Fotografía (opcional):</label>

                <div class="foto-opciones">
                    <label for="foto" class="btn-foto">
                        <span>Subir archivo</span>
                    </label>

                    <button type="button" id="abrirCamara" class="btn-foto">
                        <span>Activar cámara</span>
                    </button>
                </div>

                <input type="file" name="foto" id="foto" accept="image/*" hidden>

                <div id="archivoInfo" class="nombre-archivo vacio">
                    <span id="nombreArchivoTexto">Ningún archivo seleccionado</span>
                    <button 
                        type="button" 
                        id="quitarArchivo" 
                        class="quitar-archivo" 
                        style="display:none;"
                    >
                        ✕
                    </button>
                </div>

                <video id="video" autoplay playsinline style="display:none;"></video>
                <canvas id="canvas" style="display:none;"></canvas>

                <button 
                    type="button" 
                    id="tomarFoto" 
                    class="btn-foto" 
                    style="display:none;"
                >
                    Tomar foto
                </button>

                <div class="info-ubicacion oculto" id="infoUbicacion">
                    <strong>Ubicación seleccionada:</strong><br>
                    Latitud: <span id="latitud">No seleccionada</span><br>
                    Longitud: <span id="longitud">No seleccionada</span>
                </div>

                <input type="hidden" name="latitud" id="latitudInput">
                <input type="hidden" name="longitud" id="longitudInput">
                <input type="hidden" name="direccion_texto" id="direccionInput">

                <button type="submit">Registrar</button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <?php include __DIR__ . '/inicio/parciales/map-config.php'; ?>

    <script>
        window.reportesDB = <?php echo json_encode($reportesMapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        console.log('Reportes enviados al mapa:', window.reportesDB);
    </script>

    <script src="/views/compartido/js/popup-reporte.js"></script>
    <script src="/views/usuario/inicio/js/mapa-reportes.js"></script>
    <script src="/views/usuario/compartido/js/menu-inferior.js"></script>
    <script src="/views/usuario/inicio/js/ubicacion-actual.js"></script>
    <script src="/views/usuario/inicio/js/foto-camara.js"></script>
</body>
</html>
