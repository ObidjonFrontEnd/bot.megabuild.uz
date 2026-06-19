<?php

defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');

require __DIR__ . '/../vendor/autoload.php';

// Load .env before Yii boots so params.php can read $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

$app = new yii\web\Application($config);

// Ensure sessions table exists on every boot (idempotent)
$app->db->createCommand('
    CREATE TABLE IF NOT EXISTS sessions (
        tg_id      INTEGER PRIMARY KEY,
        user_id    INTEGER NOT NULL,
        full_name  TEXT    NOT NULL,
        role       TEXT    NOT NULL,
        token      TEXT    NOT NULL,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )
')->execute();

$app->run();
