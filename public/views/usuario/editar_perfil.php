<?php
session_start();

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/auth_helper.php';
require_once __DIR__ . '/../../../config/upload_helper.php';

use MongoDB\BSON\ObjectId;

verificar_autenticacion('../../index.php');

$db = conectarMongoDB();
$mensaje = '';
$tipoMensaje = '';

try {
    $usuarioId = new ObjectId((string) $_SESSION['usuario_id']);
} catch (Throwable $e) {
    session_destroy();
    header('Location: ../../index.php');
    exit;
}

$usuario = $db->usuario->findOne(['_id' => $usuarioId]);

if (!$usuario) {
    session_destroy();
    header('Location: ../../index.php');
    exit;
}

$nombreCompleto = (string) ($usuario['nombre_completo'] ?? $_SESSION['usuario_nombre'] ?? '');
$telefono = (string) ($usuario['telefono'] ?? '');
$email = (string) ($usuario['email'] ?? $_SESSION['usuario_email'] ?? '');
$fotoPerfil = (string) ($usuario['foto_perfil'] ?? '');
$fotoPerfilSrc = $fotoPerfil !== '' && !preg_match('/^https?:\/\//i', $fotoPerfil)
    ? '/' . ltrim($fotoPerfil, '/')
    : $fotoPerfil;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $fotoNueva = $fotoPerfil;

    if ($nombreCompleto === '') {
        $mensaje = 'El nombre no puede estar vacio.';
        $tipoMensaje = 'error';
    } elseif (mb_strlen($nombreCompleto) > 90) {
        $mensaje = 'El nombre no puede superar 90 caracteres.';
        $tipoMensaje = 'error';
    } elseif (mb_strlen($telefono) > 30) {
        $mensaje = 'El telefono no puede superar 30 caracteres.';
        $tipoMensaje = 'error';
    } else {
        try {
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('No se pudo subir la foto de perfil.');
                }

                if ((int) $_FILES['foto_perfil']['size'] > 2 * 1024 * 1024) {
                    throw new Exception('La foto de perfil no puede superar 2 MB.');
                }

                $nombreOriginal = (string) $_FILES['foto_perfil']['name'];
                $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
                $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($extension, $extPermitidas, true)) {
                    throw new Exception('La foto debe ser jpg, jpeg, png o webp.');
                }

                $infoImagen = @getimagesize($_FILES['foto_perfil']['tmp_name']);
                if ($infoImagen === false) {
                    throw new Exception('El archivo seleccionado no es una imagen valida.');
                }

                $directorio = getProfileUploadDir();
                $nombreArchivo = uniqid('perfil_', true) . '.' . $extension;
                $rutaFinal = $directorio . $nombreArchivo;

                if (!move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $rutaFinal)) {
                    throw new Exception('No se pudo guardar la foto de perfil.');
                }

                $fotoNueva = '/uploads/perfiles/' . $nombreArchivo;
            }

            $db->usuario->updateOne(
                ['_id' => $usuarioId],
                [
                    '$set' => [
                        'nombre_completo' => $nombreCompleto,
                        'telefono' => $telefono,
                        'foto_perfil' => $fotoNueva
                    ]
                ]
            );

            $_SESSION['usuario_nombre'] = $nombreCompleto;
            $_SESSION['foto_perfil'] = $fotoNueva;

            header('Location: perfil.php?perfil=actualizado');
            exit;
        } catch (Throwable $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = 'error';
        }
    }
}

$inicial = strtoupper(substr(trim($nombreCompleto) !== '' ? $nombreCompleto : 'Usuario', 0, 1));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Perfil - Ojo en la Via</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/imagenes/fiveicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/views/usuario/perfil/css/perfil.css">
</head>
<body>
    <main class="perfil-page editar-perfil-page">
        <section class="perfil-card editar-perfil-card">
            <a href="perfil.php" class="volver-perfil">
                <i class="fas fa-arrow-left"></i>
                Volver
            </a>

            <h1>Editar Perfil</h1>

            <?php if ($mensaje !== ''): ?>
                <div class="perfil-alerta <?php echo htmlspecialchars($tipoMensaje); ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="editar-perfil-form">
                <div class="editar-foto-preview">
                    <div class="perfil-avatar">
                        <?php if ($fotoPerfilSrc !== ''): ?>
                            <img src="<?php echo htmlspecialchars($fotoPerfilSrc); ?>" alt="Foto de perfil">
                        <?php else: ?>
                            <?php echo htmlspecialchars($inicial); ?>
                        <?php endif; ?>
                    </div>

                    <label class="foto-input-label" for="foto_perfil">
                        <i class="fas fa-camera"></i>
                        Cambiar foto
                    </label>

                    <input type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg,image/png,image/webp">
                </div>

                <label>
                    Nombre completo
                    <input type="text" name="nombre_completo" maxlength="90" required value="<?php echo htmlspecialchars($nombreCompleto); ?>">
                </label>

                <label>
                    Correo electronico
                    <input type="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                </label>

                <label>
                    Telefono
                    <input type="text" name="telefono" maxlength="30" value="<?php echo htmlspecialchars($telefono); ?>">
                </label>

                <button type="submit" class="perfil-btn editar-submit">
                    <span class="perfil-btn-icon"><i class="fas fa-save"></i></span>
                    <span>Guardar cambios</span>
                </button>
            </form>
        </section>
    </main>

    <script src="/views/usuario/perfil/js/perfil.js"></script>
</body>
</html>
