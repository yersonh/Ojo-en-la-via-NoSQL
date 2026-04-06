<?php

require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\Database;

function conectarMongoDB(): Database
{
    try {
        $uri = "mongodb://mongo:nkXnGyANfrwZcmTcqpZvFgCoEtBgHAUI@nozomi.proxy.rlwy.net:23279/ojoenlavia?authSource=admin";

        $dbName = ltrim(parse_url($uri, PHP_URL_PATH) ?? '', '/');

        if (!$dbName) {
            die("Error: no se pudo obtener el nombre de la base desde la URI.");
        }

        $client = new Client($uri);

        return $client->selectDatabase($dbName);
    } catch (\Throwable $e) {
        die("Error al conectar con MongoDB: " . $e->getMessage());
    }
}