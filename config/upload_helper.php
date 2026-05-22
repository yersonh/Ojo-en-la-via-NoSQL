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

function getProfileUploadDir(): string
{
    $dir = getenv('RAILWAY_ENVIRONMENT') !== false
        ? '/uploads/perfiles/'
        : __DIR__ . '/../public/uploads/perfiles/';

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}
