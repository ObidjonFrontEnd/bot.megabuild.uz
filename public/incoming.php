<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// ─── Проверка X-Secret-Token ─────────────────────────────────────────────────

$incomingSecret = $_SERVER['HTTP_X_SECRET_TOKEN'] ?? '';
$expectedSecret = $_ENV['YII2_INCOMING_SECRET'] ?? '';

if (empty($expectedSecret) || !hash_equals($expectedSecret, $incomingSecret)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ─── Парсим тело запроса ─────────────────────────────────────────────────────

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || !isset($data['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

use App\Api\ApiClient;
use App\Auth\SessionManager;
use App\Storage\Database;
use SergiX44\Nutgram\Nutgram;

set_exception_handler(function (Throwable $e) {
    error_log('[INCOMING ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(200);
});

$bot = new Nutgram($_ENV['TELEGRAM_TOKEN']);

// ─── Обработка событий ───────────────────────────────────────────────────────

switch ($data['event']) {

    case 'new-request':
        // TODO: Этап 4 — рассылка уведомления всем supplier и finance_manager
        // Шаги:
        // 1. $api->getRequest($data['request_id']) — получить детали
        // 2. $api->getRequestMaterials($data['request_id']) — материалы
        // 3. $api->getUsersByRole('supplier') — список снабженцев
        // 4. $api->getUsersByRole('finance_manager') — список фин. менеджеров
        // 5. bot->sendMessage() каждому по tg_id
        break;

    case 'new-quote':
        // TODO: Этап 4 — уведомить finance_manager если кол-во предложений >= 2
        // Условие: $data['quotes_count'] >= 2
        break;

    case 'winner-set':
        // TODO: Этап 4 — уведомить победителя (поставщика)
        // Найти сессию по user_id = $data['winner_user_id']
        // Отправить поздравление по tg_id
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown event']);
        exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
