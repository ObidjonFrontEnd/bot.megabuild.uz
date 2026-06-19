#!/usr/bin/env php
<?php

/**
 * Скрипт регистрации Telegram webhook.
 * Использование: php set-webhook.php https://your-domain.com/webhook
 */

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$token = $_ENV['TELEGRAM_TOKEN'] ?? '';
$secret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';

if (empty($token)) {
    die("TELEGRAM_TOKEN не задан в .env\n");
}

$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$url = $argv[1] ?? ($appUrl ? $appUrl . '/webhook' : null);

if (!$url) {
    die("Использование: php set-webhook.php https://your-domain.com/webhook\n");
}

$params = [
    'url'             => $url,
    'allowed_updates' => ['message', 'callback_query'],
];
if ($secret) {
    $params['secret_token'] = $secret;
}

$apiUrl  = "https://api.telegram.org/bot{$token}/setWebhook";
$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode($params),
    ],
]);
$response = file_get_contents($apiUrl, false, $context);
$result   = json_decode($response, true);

if ($result['ok'] ?? false) {
    echo "✅ Webhook зарегистрирован: {$url}\n";
} else {
    echo "❌ Ошибка: " . ($result['description'] ?? 'Неизвестная ошибка') . "\n";
}
