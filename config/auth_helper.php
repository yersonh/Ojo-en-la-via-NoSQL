<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Verifica que haya sesión activa. Si no la hay, intenta restaurarla desde
 * la cookie remember_token. Si tampoco hay cookie válida, redirige al login.
 */
function verificar_autenticacion(string $redirect = '/index.php'): void
{
    if (isset($_SESSION['usuario_id'])) return;

    $token = $_COOKIE['remember_token'] ?? '';
    if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        header('Location: ' . $redirect);
        exit;
    }

    require_once __DIR__ . '/conexion.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    try {
        $db       = conectarMongoDB();
        $tokenDoc = $db->tokens_sesion->findOne(['token' => $token]);

        if (!$tokenDoc || $tokenDoc['expira']->toDateTime() < new DateTime()) {
            if ($tokenDoc) {
                $db->tokens_sesion->deleteOne(['token' => $token]);
            }
            _limpiar_cookie_token();
            header('Location: ' . $redirect);
            exit;
        }

        $usuario = $db->usuario->findOne(['_id' => $tokenDoc['usuario_id']]);

        if (!$usuario || !($usuario['estado'] ?? false)) {
            $db->tokens_sesion->deleteOne(['token' => $token]);
            _limpiar_cookie_token();
            header('Location: ' . $redirect);
            exit;
        }

        $_SESSION['usuario_id']     = (string) $usuario['_id'];
        $_SESSION['usuario_nombre'] = trim($usuario['nombre_completo'] ?? '') ?: ($usuario['email'] ?? 'Usuario');
        $_SESSION['usuario_email']  = $usuario['email'] ?? '';
        $_SESSION['foto_perfil']    = $usuario['foto_perfil'] ?? '';
        $_SESSION['usuario_rol']    = $usuario['rol'] ?? 'ciudadano';

    } catch (Throwable $e) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Genera un token de 30 días, lo guarda en MongoDB y establece la cookie.
 */
function guardar_token_recordar(\MongoDB\Database $db, string $usuario_id): void
{
    $token  = bin2hex(random_bytes(32));
    $expira = new DateTime('+30 days');

    $db->tokens_sesion->insertOne([
        'usuario_id' => new ObjectId($usuario_id),
        'token'      => $token,
        'expira'     => new UTCDateTime($expira->getTimestamp() * 1000),
        'creado'     => new UTCDateTime(),
    ]);

    setcookie('remember_token', $token, [
        'expires'  => $expira->getTimestamp(),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Elimina el token del DB y borra la cookie.
 */
function eliminar_token_recordar(\MongoDB\Database $db): void
{
    $token = $_COOKIE['remember_token'] ?? '';
    if ($token) {
        try {
            $db->tokens_sesion->deleteOne(['token' => $token]);
        } catch (Throwable $e) {
        }
        _limpiar_cookie_token();
    }
}

function _limpiar_cookie_token(): void
{
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
