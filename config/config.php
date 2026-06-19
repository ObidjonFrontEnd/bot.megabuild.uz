<?php

return [
    'telegram_token'          => $_ENV['TELEGRAM_TOKEN'] ?? '',
    'telegram_webhook_secret' => $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '',
    'yii2_api_base_url'       => rtrim($_ENV['YII2_API_BASE_URL'] ?? '', '/'),
    'yii2_incoming_secret'    => $_ENV['YII2_INCOMING_SECRET'] ?? '',
    'db_path'                 => !empty($_ENV['DB_PATH']) ? $_ENV['DB_PATH'] : __DIR__ . '/../data/bot.sqlite',
    'app_url'                 => rtrim($_ENV['APP_URL'] ?? '', '/'),
];
