<?php

$params = require __DIR__ . '/params.php';
// DB_PATH можно не задавать (или оставить пустым) — тогда база ляжет внутрь проекта.
$dbPath = !empty($_ENV['DB_PATH']) ? $_ENV['DB_PATH'] : dirname(__DIR__) . '/data/bot.sqlite';

return [
    'id'       => 'tg-bot',
    'basePath' => dirname(__DIR__),
    'aliases'  => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'db' => [
            'class'   => 'yii\db\Connection',
            'dsn'     => 'sqlite:' . $dbPath,
            'charset' => 'utf8',
        ],
        'request' => [
            'enableCookieValidation' => false,
            'enableCsrfValidation'   => false,
        ],
        'response' => [
            'class' => 'yii\web\Response',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'traceLevel'    => YII_DEBUG ? 3 : 0,
            'flushInterval' => 1,
            'targets' => [
                [
                    'class'          => 'yii\log\FileTarget',
                    'levels'         => ['error', 'warning'],
                    'logFile'        => '@app/runtime/logs/app.log',
                    'exportInterval' => 1,
                ],
            ],
        ],
        'errorHandler' => [
            'class' => 'yii\web\ErrorHandler',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'rules' => [
                ''                => 'site/index',
                'webhook'         => 'webhook/index',
                'incoming'        => 'incoming/index',
                'bot-token/link'    => 'bot-token/link',
                'bot-token/logout'  => 'bot-token/logout',
                'bot-token'         => 'bot-token/index',
                'mini-app/login'  => 'mini-app/login',
                'mini-app/offer'  => 'mini-app/offer',
                'mini-app/compare' => 'mini-app/compare',
            ],
        ],
        'bot' => [
            'class' => 'app\components\TelegramBot',
        ],
        'apiClient' => [
            'class' => 'app\components\ApiClient',
        ],
    ],
    'params' => $params,
];
