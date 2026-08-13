<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(): PDO
    {
        $dsn = getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=15432;dbname=stat_pipeline';
        $user = getenv('DB_USER') ?: 'app';
        $password = getenv('DB_PASSWORD') ?: 'app';

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}

