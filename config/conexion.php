<?php

require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\Database;

function conectarMongoDB(): Database
{
    try {
        $uri = getenv('MONGODB_URI') ?: "mongodb://mongo:FgYclOQSoaqkbHkLcTXwaODbNefKSqdK@zephyr.proxy.rlwy.net:32810/ojoenlavia?authSource=admin";

        // Extraer el nombre de la base de datos de la URI
        $dbName = ltrim(parse_url($uri, PHP_URL_PATH) ?? '', '/');

        if (!$dbName) {
            // Fallback si no está en la URI (común en algunas configuraciones)
            $dbName = 'ojoenlavia';
        }

        $client = new Client($uri);

        return $client->selectDatabase($dbName);
    } catch (\Throwable $e) {
        die("Error al conectar con MongoDB: " . $e->getMessage());
    }
}