<?php

namespace App\Storage;

use PDO;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dbPath = !empty($_ENV['DB_PATH']) ? $_ENV['DB_PATH'] : __DIR__ . '/../../data/bot.sqlite';
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            self::$instance = new PDO('sqlite:' . $dbPath);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::migrate(self::$instance);
        }
        return self::$instance;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sessions (
                tg_id       INTEGER PRIMARY KEY,
                user_id     INTEGER NOT NULL,
                full_name   TEXT    NOT NULL,
                role        TEXT    NOT NULL,
                token       TEXT    NOT NULL,
                created_at  INTEGER NOT NULL,
                updated_at  INTEGER NOT NULL
            )
        ");
    }
}
