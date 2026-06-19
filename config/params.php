<?php

return [
    'telegramToken'         => $_ENV['TELEGRAM_TOKEN'] ?? '',
    'telegramWebhookSecret' => $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '',
    'yii2ApiBaseUrl'        => rtrim($_ENV['YII2_API_BASE_URL'] ?? '', '/'),
    'yii2IncomingSecret'    => $_ENV['YII2_INCOMING_SECRET'] ?? '',
    'appUrl'                => rtrim($_ENV['APP_URL'] ?? '', '/'),
];
