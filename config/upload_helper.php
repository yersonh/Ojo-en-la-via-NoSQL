<?php
function getUploadDir(): string
{
    $dir = getenv('RAILWAY_ENVIRONMENT') !== false
        ? '/uploads/reportes/'
        : __DIR__ . '/../public/uploads/reportes/';

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}
