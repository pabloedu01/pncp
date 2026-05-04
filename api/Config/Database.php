<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static $connection = null;

    public static function getConnection()
    {
        if (self::$connection === null) {
            $credsFile = __DIR__ . '/../../.credentials.php';
            if (file_exists($credsFile)) {
                $creds = require $credsFile;
                $host = getenv('DB_HOST') ?: ($creds['DB_HOST'] ?? 'db');
                $db_name = getenv('DB_NAME') ?: ($creds['DB_NAME'] ?? 'pncp');
                $username = getenv('DB_USER') ?: ($creds['DB_USER'] ?? 'postgres');
                $password = getenv('DB_PASS') ?: ($creds['DB_PASS'] ?? 'password');
            } else {
                $host = getenv('DB_HOST') ?: 'db';
                $db_name = getenv('DB_NAME') ?: 'pncp';
                $username = getenv('DB_USER') ?: 'postgres';
                $password = getenv('DB_PASS') ?: 'password';
            }

            try {
                self::$connection = new PDO("pgsql:host=" . $host . ";dbname=" . $db_name, $username, $password);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $exception) {
                http_response_code(500);
                echo json_encode(["error" => "Erro de conexão com o banco de dados."]);
                exit;
            }
        }
        return self::$connection;
    }
}
