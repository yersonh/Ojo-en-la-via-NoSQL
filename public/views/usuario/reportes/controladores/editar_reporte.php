<?php
session_start();

require_once __DIR__ . '/../../../../../config/conexion.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function filtroPropietarioReporte(MongoDB\BSON\ObjectId $reporteId, $usuarioIdTexto, MongoDB\BSON\ObjectId $usuarioId)
{
    return [
        '_id' => $reporteId,
        '$or' => [
            ['usuario_id' => $usuarioIdTexto],
            ['usuario_id' => $usuarioId],
            ['usuario_creador_id' => $usuarioIdTexto],
            ['usuario_creador_id' => $usuarioId],
        ]
    ];
}

function mensajeErrorSubida($codigo)
{
    return match ($codigo) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'La foto es demasiado pesada. Intenta con una imagen mas liviana.',
        UPLOAD_ERR_PARTIAL => 'La foto se cargo incompleta. Intenta nuevamente.',
        UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal para subir imagenes.',
        UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar la foto temporalmente.',
        UPLOAD_ERR_EXTENSION => 'Una extension del servidor bloqueo la carga de la foto.',
        default => 'No se pudo cargar la foto del reporte. Codigo: ' . (int) $codigo
    };
}

function guardarFotoReporteEditada()
{
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        responderJson([
            'ok' => false,
            'mensaje' => mensajeErrorSubida($_FILES['foto']['error'])
        ], 400);
    }

    $nombreOriginal = $_FILES['foto']['name'] ?? '';
    $tmp = $_FILES['foto']['tmp_name'] ?? '';
    $tamano = (int) ($_FILES['foto']['size'] ?? 0);
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

    if ($tamano > 5 * 1024 * 1024) {
        responderJson([
            'ok' => false,
            'mensaje' => 'La foto no puede pesar mas de 5 MB.'
        ], 400);
    }

    if (!in_array($extension, $extPermitidas, true)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'La imagen debe ser jpg, jpeg, png o webp.'
        ], 400);
    }

    $directorioSubidas = __DIR__ . '/../../../../uploads/reportes/';

    if (!is_dir($directorioSubidas)) {
        mkdir($directorioSubidas, 0777, true);
    }

    $nombreArchivo = uniqid('reporte_editado_', true) . '.' . $extension;
    $rutaFinal = $directorioSubidas . $nombreArchivo;

    if (!move_uploaded_file($tmp, $rutaFinal)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No se pudo guardar la nueva foto.'
        ], 500);
    }

    return 'uploads/reportes/' . $nombreArchivo;
}

try {
    if (!isset($_SESSION['usuario_id'])) {
        responderJson([
            'ok' => false,
            'mensaje' => 'No has iniciado sesion.'
        ], 401);
    }

    $reporteIdTexto = $_POST['reporte_id'] ?? '';
    $tipo = trim($_POST['tipo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $latitudTexto = trim($_POST['latitud'] ?? '');
    $longitudTexto = trim($_POST['longitud'] ?? '');

    if (!preg_match('/^[a-f\d]{24}$/i', $reporteIdTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'ID de reporte invalido.'
        ], 400);
    }

    if ($tipo === '' || $descripcion === '' || $latitudTexto === '' || $longitudTexto === '') {
        responderJson([
            'ok' => false,
            'mensaje' => 'Completa el tipo, la descripcion y la ubicacion.'
        ], 400);
    }

    if (mb_strlen($descripcion) > 800) {
        responderJson([
            'ok' => false,
            'mensaje' => 'La descripcion no puede superar 800 caracteres.'
        ], 400);
    }

    if (!is_numeric($latitudTexto) || !is_numeric($longitudTexto)) {
        responderJson([
            'ok' => false,
            'mensaje' => 'La ubicacion debe tener coordenadas validas.'
        ], 400);
    }

    $latitud = (float) $latitudTexto;
    $longitud = (float) $longitudTexto;

    if ($latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Las coordenadas estan fuera del rango permitido.'
        ], 400);
    }

    $db = conectarMongoDB();
    $reportes = $db->Reportes;

    $reporteId = new MongoDB\BSON\ObjectId($reporteIdTexto);
    $usuarioIdTexto = (string) $_SESSION['usuario_id'];
    $usuarioId = new MongoDB\BSON\ObjectId($usuarioIdTexto);
    $filtro = filtroPropietarioReporte($reporteId, $usuarioIdTexto, $usuarioId);

    $reporte = $reportes->findOne($filtro);

    if (!$reporte) {
        responderJson([
            'ok' => false,
            'mensaje' => 'Reporte no encontrado o no tienes permiso para editarlo.'
        ], 404);
    }

    $nuevaImagen = guardarFotoReporteEditada();

    $camposActualizar = [
        'tipo' => $tipo,
        'descripcion' => $descripcion,
        'ubicacion' => [
            'latitud' => $latitud,
            'longitud' => $longitud
        ],
        'latitud' => $latitud,
        'longitud' => $longitud,
        'editado' => true,
        'fecha_edicion' => new MongoDB\BSON\UTCDateTime()
    ];

    if ($nuevaImagen !== null) {
        $camposActualizar['imagenes'] = [$nuevaImagen];
    }

    $reportes->updateOne(
        $filtro,
        [
            '$set' => $camposActualizar
        ]
    );

    responderJson([
        'ok' => true,
        'mensaje' => 'Reporte actualizado.',
        'reporte' => [
            'id' => $reporteIdTexto,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'imagen' => $nuevaImagen
        ]
    ]);
} catch (Throwable $e) {
    responderJson([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}
