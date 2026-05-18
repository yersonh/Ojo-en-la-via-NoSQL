<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function generarTokenEntidad(\MongoDB\Database $db, ObjectId $reporteId, string $entidad): string
{
    $token = bin2hex(random_bytes(32)); // 64 hex chars

    $db->Reportes->updateOne(
        ['_id' => $reporteId],
        ['$set' => [
            'token_entidad' => [
                'token'            => $token,
                'entidad'          => $entidad,
                'usado'            => false,
                'fecha_creacion'   => new UTCDateTime(),
                'fecha_expiracion' => new UTCDateTime((time() + 7 * 86400) * 1000),
            ]
        ]]
    );

    return $token;
}

function validarTokenEntidad(\MongoDB\Database $db, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;

    $reporte = $db->Reportes->findOne([
        'token_entidad.token' => $token,
        'token_entidad.usado' => false,
    ]);
    if (!$reporte) return null;

    $te     = $reporte['token_entidad'];
    $expiry = $te['fecha_expiracion']->toDateTime()->getTimestamp();
    if ($expiry < time()) return null;

    return [
        'token'      => $token,
        'reporte_id' => $reporte['_id'],
        'entidad'    => (string) ($te['entidad'] ?? ''),
    ];
}

function marcarTokenUsado(\MongoDB\Database $db, string $token): void
{
    $db->Reportes->updateOne(
        ['token_entidad.token' => $token],
        ['$set' => [
            'token_entidad.usado'     => true,
            'token_entidad.fecha_uso' => new UTCDateTime(),
        ]]
    );
}
