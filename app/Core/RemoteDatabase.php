<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class RemoteDatabase
{
    private static ?PDO $pdo = null;

    public static function connect(): ?PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = Env::get('REMOTE_DB_HOST', '');
        $port = Env::get('REMOTE_DB_PORT', '3306');
        $db   = Env::get('REMOTE_DB_DATABASE', '');
        $user = Env::get('REMOTE_DB_USERNAME', '');
        $pass = Env::get('REMOTE_DB_PASSWORD', '');

        if ($host === '' || $db === '') {
            return null;
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
        } catch (PDOException $e) {
            self::$pdo = null;
            return null;
        }

        return self::$pdo;
    }

    public static function getConnection(): ?PDO
    {
        return self::connect();
    }

    public static function isAvailable(): bool
    {
        return self::connect() !== null;
    }
}
