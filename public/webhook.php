<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Принимаем только POST с непустым телом
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    exit;
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    http_response_code(200);
    exit;
}

// Проверка X-Telegram-Bot-Api-Secret-Token (если задан в .env)
$secret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
if ($secret !== '') {
    $incoming = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($secret, $incoming)) {
        http_response_code(403);
        exit;
    }
}

use App\Handlers\LogoutHandler;
use App\Handlers\RequestDetailHandler;
use App\Handlers\RequestsMenuHandler;
use App\Handlers\StartHandler;
use App\Handlers\WebAppDataHandler;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;

// Telegram ожидает 200 даже при ошибках — не выводим HTML-стэктрейс
set_exception_handler(function (Throwable $e) {
    error_log('[BOT ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(200);
});

$bot = new Nutgram($_ENV['TELEGRAM_TOKEN']);

// Nutgram 3.x: режим задаётся через setRunningMode(), не через run()
$bot->setRunningMode(Webhook::class);

// /start — авторизация или приветствие
$bot->onCommand('start', StartHandler::handle(...));

// web_app_data — данные после логина в Mini App
$bot->onMessage(function (Nutgram $bot) {
    if ($bot->message()?->web_app_data !== null) {
        WebAppDataHandler::handle($bot);
    }
});

// Постоянная кнопка меню "📋 Заявки"
$bot->onText('📋 Заявки', RequestsMenuHandler::showMenu(...));

// ── Callback-кнопки: список заявок ──────────────────────────────────────────

// req:menu — вернуться к меню фильтров
$bot->onCallbackQueryData('req:menu', function (Nutgram $bot) {
    $bot->answerCallbackQuery();
    RequestsMenuHandler::showMenu($bot);
});

// req:filter:{type}:{page} — список заявок по фильтру
$bot->onCallbackQueryData('req:filter:{type}:{page}', RequestsMenuHandler::handleFilter(...));

// req:detail:{requestId}:{filterType}:{filterPage} — детали заявки
$bot->onCallbackQueryData('req:detail:{requestId}:{filterType}:{filterPage}', RequestDetailHandler::handle(...));

// noop — заглушка для кнопок без действия
$bot->onCallbackQueryData('noop', fn (Nutgram $bot) => $bot->answerCallbackQuery());

// Кнопка и команда выхода
$bot->onText('🚪 Выйти', LogoutHandler::handle(...));
$bot->onCommand('logout', LogoutHandler::handle(...));

$bot->run();
