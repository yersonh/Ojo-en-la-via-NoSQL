<?php
/**
 * Router para PHP built-in server.
 * Intercepta /uploads/* y sirve los archivos desde el volumen de Railway (/uploads).
 * Para todo lo demás devuelve false → el servidor sirve desde public/ normalmente.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (str_starts_with($path, '/uploads/')) {
    $file = $path; // /uploads/reportes/reporte_xxx.jpg

    if (is_file($file)) {
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($file);
        return true;
    }

    // Fallback: dejar que public/ lo intente (desarrollo local)
    return false;
}

// Todo lo demás lo maneja el servidor desde public/
return false;
