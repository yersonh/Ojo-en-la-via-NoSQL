<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

$mensaje = '';
$tipoMensaje = '';

$nombreMostrar = trim($_SESSION['usuario_nombre'] ?? '');
if ($nombreMostrar === '') {
    $nombreMostrar = $_SESSION['usuario_email'] ?? 'Usuario';
}

try {
    $db = conectarMongoDB();
    $reportes = $db->reportes;
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
                $directorioSubidas = __DIR__ . '/../../uploads/reportes/';

                if (!is_dir($directorioSubidas)) {
                    mkdir($directorioSubidas, 0777, true);
                }

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
            $documento = [
                'usuario_id' => new \MongoDB\BSON\ObjectId($_SESSION['usuario_id']),
                'usuario_email' => $_SESSION['usuario_email'] ?? '',
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'ubicacion' => [
                    'latitud' => (float) $latitud,
                    'longitud' => (float) $longitud
                ],
                'direccion_texto' => '',
                'imagenes' => $imagenes,
                'estado' => 'activo',
                'fecha_reporte' => new \MongoDB\BSON\UTCDateTime()
            ];

            $resultado = $reportes->insertOne($documento);

            if ($resultado->getInsertedCount() > 0) {
                $mensaje = 'Reporte guardado correctamente.';
                $tipoMensaje = 'success';
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


//reportes para mostrar en el mapa 
$reportesMapa = [];

try {
    $cursor = $reportes->find([
        'estado' => 'activo'
    ]);

    foreach ($cursor as $reporte) {
    $lat = $reporte['ubicacion']['latitud'] ?? null;
    $lng = $reporte['ubicacion']['longitud'] ?? null;

    if ($lat !== null && $lng !== null) {
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

        if (is_array($imagenesReporte) && isset($imagenesReporte[0])) {
            $primeraImagen = $imagenesReporte[0];
        } elseif ($imagenesReporte instanceof \MongoDB\Model\BSONArray) {
            $imagenesArray = $imagenesReporte->getArrayCopy();
            $primeraImagen = $imagenesArray[0] ?? null;
        }

        $reportesMapa[] = [
            'id' => (string) $reporte['_id'],
            'tipo' => $reporte['tipo'] ?? 'Incidente',
            'descripcion' => $reporte['descripcion'] ?? '',
            'latitud' => (float) $lat,
            'longitud' => (float) $lng,
            'direccion_texto' => $reporte['direccion_texto'] ?? '',
            'imagen' => $primeraImagen,
            'usuario_email' => $reporte['usuario_email'] ?? 'No disponible',
            'fecha' => $fechaFormateada,
            'estado' => $reporte['estado'] ?? 'activo'
        ];
    }
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
    <title>Inicio - Mapa</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

    <style>
        .oculto {
            display: none;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: Arial, sans-serif;
        }

        body {
            background: #111;
        }

        .contenedor {
            position: relative;
            width: 100%;
            height: 100vh;
        }

        .mapa {
            width: 100%;
            height: 100vh;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        .topbar {
            position: absolute;
             top: 8px;
             left: 50%;
            transform: translateX(-50%);
            width: 80%;
            z-index: 3001;
            height: 56px;
            padding: 0 14px;
            background: linear-gradient(90deg, #0f5f96, #0b6ea9);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.18);
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar h2 {
            font-size: 18px;
            margin: 0;
        }

        .topbar a {
            text-decoration: none;
            color: white;
            background: #dc3545;
            padding: 9px 14px;
            border-radius: 10px;
            transition: 0.2s ease;
        }

        .topbar a:hover {
            background: #bb2d3b;
        }

         .panel {
            position: absolute;
            top: 80px;
            right: 16px;
            width: 360px;
            max-width: calc(100% - 32px);
            max-height: calc(100vh - 180px);
            overflow-y: auto;
            padding: 18px;
            border-radius: 26px;
            z-index: 3001;

            background: rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(22px) saturate(160%);
            -webkit-backdrop-filter: blur(22px) saturate(160%);

            border: 1px solid rgba(255, 255, 255, 0.28);
            box-shadow:
                0 8px 30px rgba(0, 0, 0, 0.16),
                inset 0 1px 0 rgba(255, 255, 255, 0.32),
                inset 0 -1px 0 rgba(255, 255, 255, 0.08);
        }

        .panel::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 26px;
            pointer-events: none;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.22) 0%,
                rgba(255, 255, 255, 0.08) 38%,
                rgba(255, 255, 255, 0.03) 100%
            );
        }

        .panel h3 {
            margin-bottom: 16px;
            color: #333;
        }

        .panel label {
            display: block;
            margin-top: 12px;
            margin-bottom: 6px;
            font-weight: bold;
            color: #333;
        }
         .panel select,
        .panel textarea,
        .panel input[type="file"] {
            width: 100%;
            padding: 12px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            font-size: 14px;
            color: #1f2937;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.22),
                0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .panel textarea {
            resize: vertical;
            min-height: 100px;
        }

      /*  .panel button {
            margin-top: 15px;
            background: #0d6efd;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }

        .panel button:hover {
            background: #0b5ed7;
        }*/

         .panel button[type="submit"] {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 16px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.88), rgba(29, 78, 216, 0.72));
            box-shadow:
                0 8px 24px rgba(37, 99, 235, 0.28),
                inset 0 1px 0 rgba(255, 255, 255, 0.22);
        }

        .panel button[type="submit"]:hover {
            transform: translateY(-1px);
        }
     /*   .info-ubicacion {
            margin-top: 14px;
            padding: 10px;
            background: #f1f3f5;
            border-radius: 10px;
            font-size: 14px;
            color: #333;
        }*/
          .info-ubicacion {
            margin-top: 14px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 16px;
            font-size: 14px;
            color: #1f2937;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                0 4px 14px rgba(0, 0, 0, 0.05);
        }
                
        .alerta {
            margin-bottom: 12px;
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
        }

        .alerta.success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .alerta.error {
            background: #f8d7da;
            color: #842029;
        }

        .bottom-nav {
            position: fixed;
            left: 50%;
            bottom: -90px;
            transform: translateX(-50%);
            width: min(460px, calc(100% - 20px));
            height: 72px;
            background: rgba(10, 10, 10, 0.96);
            backdrop-filter: blur(10px);
            box-shadow: 0 -10px 35px rgba(0,0,0,0.35);
            z-index: 4000;
            display: flex;
            align-items: center;
            justify-content: space-around;
            border-radius: 18px 18px 0 0;
            border: 1px solid rgba(255,255,255,0.08);
            transition: bottom 0.25s ease;
        }

        .bottom-nav.visible {
            bottom: 0;
        }

        .bottom-nav a {
            flex: 1;
            height: 100%;
            text-decoration: none;
            color: #a9a9a9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            gap: 4px;
            transition: color 0.2s ease, background 0.2s ease;
            border-radius: 14px;
        }

        .bottom-nav a:hover,
        .bottom-nav a.active {
            color: #2f7df6;
            background: rgba(255,255,255,0.04);
        }

        .bottom-nav .icon {
            font-size: 18px;
            line-height: 1;
        }

        .bottom-hover-zone {
            position: fixed;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 40px;
            z-index: 3001;
            background: transparent;
        }

        @media (max-width: 768px) {
            .topbar {
                top: 10px;
                left: 10px;
                right: 10px;
                padding: 10px 14px;
            }

            .topbar h2 {
                font-size: 15px;
            }

            .topbar a {
                padding: 8px 12px;
                font-size: 13px;
            }

            .panel {
                left: 10px;
                right: 10px;
                top: auto;
                bottom: 80px;
                width: auto;
                max-height: 38vh;
                padding: 14px;
            }

            .bottom-nav {
                bottom: 0;
                width: 100%;
                max-width: 100%;
                border-radius: 18px 18px 0 0;
            }

            .bottom-hover-zone {
                display: none;
            }
        }


             .leaflet-popup-content-wrapper {
            border-radius: 16px;
            padding: 0;
            overflow: hidden;
        }

        .leaflet-popup-content {
            margin: 0 !important;
            width: 280px !important;
        }

        .leaflet-popup-close-button {
            color: white !important;
            font-size: 18px !important;
            top: 10px !important;
            right: 10px !important;
        }

        .popup-reporte {
            font-family: Arial, sans-serif;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
        }

        .popup-header {
            background: linear-gradient(90deg, #1e88e5, #1565c0);
            color: white;
            padding: 14px 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .popup-title {
            font-size: 15px;
        }

        .popup-body {
            padding: 14px;
            background: #f8fafc;
        }

        .popup-section {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .popup-section-title {
            font-size: 13px;
            font-weight: bold;
            color: #374151;
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f9fafb;
        }

        .popup-badge {
            background: #e5e7eb;
            color: #4b5563;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 999px;
        }

        .popup-image-box {
            width: 100%;
            height: 140px;
            background: #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .popup-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .popup-image-empty {
            color: #6b7280;
            font-size: 13px;
        }

        .popup-description {
            font-size: 14px;
            color: #374151;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .popup-info-card {
            background: #f3f4f6;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        .popup-info-label {
            font-size: 13px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 4px;
        }

        .popup-info-value {
            font-size: 13px;
            color: #4b5563;
            word-break: break-word;
        }

        .popup-status {
            display: inline-block;
            background: #1e88e5;
            color: white;
            font-size: 12px;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 999px;
        }

        .popup-button {
            width: 100%;
            border: none;
            background: #1e88e5;
            color: white;
            font-weight: bold;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 6px;
        }

        .popup-button:hover {
            background: #1565c0;
        }
        /* estilo para los iconos que estan en el mapa de alertas */
        .icono-reporte-personalizado {
            background: transparent !important;
            border: none !important;
        }

        .marker-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #42a5f5, #1e88e5);
            border: 3px solid #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .marker-emoji {
            font-size: 18px;
            line-height: 1;
        }
        
                /* avatar en la barra superior */
             .user-avatar {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                background: #e8d5a9;
                color: #7a6123;
                font-weight: bold;
                font-size: 17px;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid rgba(255,255,255,0.9);
                overflow: hidden;
                flex-shrink: 0;
                text-transform: uppercase;
            }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

       .logout-btn {
            text-decoration: none;
            color: white;
            background: #d84d57;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .logout-btn:hover {
            background: #c53d47;
        }

        /*boton de cámara*/ 
            .foto-opciones {
            display: flex;
            gap: 14px;
            margin-top: 10px;
            margin-bottom: 12px;
        }

            .btn-foto {
            flex: 1;
            height: 56px;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
            gap: 10px;
            padding: 0 18px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 18px;
            cursor: pointer;
            text-decoration: none;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            text-align: center;
            appearance: none;
            -webkit-appearance: none;
            outline: none;
            background: linear-gradient(135deg, #5f8df7 0%, #4c78ea 55%, #3563d6 100%);
            box-shadow:
                0 8px 20px rgba(28, 75, 160, 0.35),
                inset 0 1px 0 rgba(255, 255, 255, 0.30),
                inset 0 -2px 6px rgba(0, 0, 0, 0.12);
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
        }

        .btn-foto:hover {
            transform: translateY(-2px);
            filter: brightness(1.03);
        }

        .btn-foto:active {
            transform: scale(0.98);
        }
        .btn-foto-icon {
            font-size: 18px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

       
        .nombre-archivo {
            margin-top: 6px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.70);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #4b5563;
            font-size: 14px;
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.35),
                0 4px 12px rgba(0,0,0,0.05);
            word-break: break-word;
        }
        .nombre-archivo.vacio {
            color: #6b7280;
            justify-content: center;
        }
                #nombreArchivoTexto {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #video {
            width: 100%;
            margin-top: 12px;
            border-radius: 14px;
            overflow: hidden;
        }

        
        #tomarFoto {
            width: 100%;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .foto-opciones {
                flex-direction: row;
                gap: 10px;
            }

            .btn-foto {
                height: 52px;
                font-size: 14px;
                padding: 0 12px;
            }

            .btn-foto span:last-child {
                white-space: nowrap;
            }
        }


    </style>
</head>
<body>
    <div class="bottom-hover-zone" id="bottomHoverZone"></div>

    <nav class="bottom-nav" id="bottomNav">
        <a href="#" class="active">
            <span class="icon">🔔</span>
            <span>Alertas</span>
        </a>

        <a href="#">
            <span class="icon">🗺️</span>
            <span>Mapa</span>
        </a>

        <a href="#">
            <span class="icon">👤</span>
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
            echo $inicial;
        ?>
    <?php endif; ?>
</div>
        <h2>Bienvenida, <?php echo htmlspecialchars($nombreMostrar); ?></h2>
    </div>

    <a href="../../logout.php" class="logout-btn">Cerrar sesión</a>
</div>
        <div class="mapa">
            <div id="map"></div>
        </div>

                    <div class="panel oculto" id="panelRegistro">
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
                    <textarea name="descripcion" id="descripcion" placeholder="Describe el incidente" required></textarea>
<label for="foto">Fotografía (opcional):</label>

<div class="foto-opciones">
    <label for="foto" class="btn-foto">
        <span class="btn-foto-icon">📁</span>
        <span>Subir archivo</span>
    </label>

                <button type="button" id="abrirCamara" class="btn-foto">
                    <span class="btn-foto-icon">📷</span>
                    <span>Activar cámara</span>
                </button>
            </div>

            <input type="file" name="foto" id="foto" accept="image/*" hidden>

            <div id="archivoInfo" class="nombre-archivo vacio">
                <span id="nombreArchivoTexto">Ningún archivo seleccionado</span>
                <button type="button" id="quitarArchivo" class="quitar-archivo" style="display:none;">✕</button>
            </div>

            <video id="video" autoplay playsinline style="display:none;"></video>
            <canvas id="canvas" style="display:none;"></canvas>
            <button type="button" id="tomarFoto" class="btn-foto" style="display:none;">Tomar foto</button>

                    <div class="info-ubicacion oculto" id="infoUbicacion">
                        <strong>Ubicación seleccionada:</strong><br>
                        Latitud: <span id="latitud">No seleccionada</span><br>
                        Longitud: <span id="longitud">No seleccionada</span>
                    </div>

                    <input type="hidden" name="latitud" id="latitudInput">
                    <input type="hidden" name="longitud" id="longitudInput">
                    <button type="submit">Registrar</button>
                </form>
            </div>

    </div>


    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
            <?php include __DIR__ . '/../components/mapa/map-config.php'; ?>

            <script>
                window.reportesDB = <?php echo json_encode($reportesMapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            </script>

            <script src="/views/components/JS_usuario/mapa-reportes.js"></script>
            <script src="/views/components/JS_usuario/menu-inferior.js"></script>
         <script>
    const inputFoto = document.getElementById('foto');
    const nombreArchivoTexto = document.getElementById('nombreArchivoTexto');
    const archivoInfo = document.getElementById('archivoInfo');
    const quitarArchivoBtn = document.getElementById('quitarArchivo');
    const abrirCamaraBtn = document.getElementById('abrirCamara');
    const tomarFotoBtn = document.getElementById('tomarFoto');
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');

    let stream = null;

    function actualizarVistaArchivo() {
        if (inputFoto.files && inputFoto.files.length > 0) {
            nombreArchivoTexto.textContent = inputFoto.files[0].name;
            quitarArchivoBtn.style.display = 'flex';
            archivoInfo.classList.remove('vacio');
        } else {
            nombreArchivoTexto.textContent = 'Ningún archivo seleccionado';
            quitarArchivoBtn.style.display = 'none';
            archivoInfo.classList.add('vacio');
        }
    }

    function cerrarCamara() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }

        video.srcObject = null;
        video.style.display = 'none';
        tomarFotoBtn.style.display = 'none';
    }

    if (inputFoto) {
        inputFoto.addEventListener('change', actualizarVistaArchivo);
    }

    if (quitarArchivoBtn) {
        quitarArchivoBtn.addEventListener('click', () => {
            inputFoto.value = '';
            actualizarVistaArchivo();
            cerrarCamara();
        });
    }

    if (abrirCamaraBtn) {
        abrirCamaraBtn.addEventListener('click', async () => {
            try {
                cerrarCamara();

                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: false
                });

                video.srcObject = stream;
                video.style.display = 'block';
                tomarFotoBtn.style.display = 'flex';
            } catch (error) {
                alert('No se pudo abrir la cámara.');
                console.error(error);
            }
        });
    }

    if (tomarFotoBtn) {
        tomarFotoBtn.addEventListener('click', () => {
            if (!video.videoWidth || !video.videoHeight) {
                alert('La cámara todavía no está lista.');
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) {
                    alert('No se pudo capturar la foto.');
                    return;
                }

                const archivo = new File([blob], 'foto_camara.png', { type: 'image/png' });
                const dt = new DataTransfer();
                dt.items.add(archivo);
                inputFoto.files = dt.files;

                actualizarVistaArchivo();
            }, 'image/png');

            cerrarCamara();
        });
    }

    actualizarVistaArchivo();
</script>
</body>
</html>