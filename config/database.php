<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;

class Database
{
    private static $client = null;

    public static function connect()
    {
        if (self::$client === null) {

            // Obtener la URL de conexión desde Railway
            $uri = getenv('MONGO_URL');

            if (!$uri) {
                throw new Exception("No se encontró la variable de entorno MONGO_URL");
            }

            try {

                self::$client = new Client($uri);

            } catch (Exception $e) {

                die("Error conectando a MongoDB: " . $e->getMessage());

            }
        }

        return self::$client;
    }

    public static function getDatabase($dbName = "ojo_en_la_via")
    {
        $client = self::connect();
        return $client->$dbName;
    }
}