<?php

namespace app\components;

use app\components\Keyboard;
use app\handlers\LogoutHandler;
use app\handlers\RequestDetailHandler;
use app\handlers\RequestsMenuHandler;
use app\handlers\StartHandler;
use app\handlers\WebAppDataHandler;
use GuzzleHttp\Client;
use yii\base\Component;

class TelegramBot extends Component
{
    private string $apiBase;
    private Client $http;

    public function init(): void
    {
        parent::init();
        $token        = \Yii::$app->params['telegramToken'];
        $this->apiBase = "https://api.telegram.org/bot{$token}/";
        $this->http    = new Client(['timeout' => 15]);
    }

    // ─── Telegram Bot API ────────────────────────────────────────────────────

    /** Низкоуровневый вызов любого метода Telegram API */
    public function api(string $method, array $params = []): array
    {
        try {
            $resp   = $this->http->post($this->apiBase . $method, ['json' => $params]);
            $result = json_decode($resp->getBody()->getContents(), true) ?? [];
            if (empty($result['ok'])) {
                \Yii::error("[TG] {$method} failed: " . json_encode($result));
            }
            return $result;
        } catch (\Throwable $e) {
            \Yii::error("[TG] {$method} exception: " . $e->getMessage());
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    public function sendMessage(int $chatId, string $text, array $opts = []): array
    {
        return $this->api('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
        ], $opts));
    }

    public function editMessageText(int $chatId, int $messageId, string $text, array $opts = []): array
    {
        return $this->api('editMessageText', array_merge([
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
        ], $opts));
    }

    public function answerCallbackQuery(string $cbqId, string $text = '', bool $showAlert = false): array
    {
        $params = ['callback_query_id' => $cbqId];
        if ($text !== '') {
            $params['text'] = $text;
        }
        if ($showAlert) {
            $params['show_alert'] = true;
        }
        return $this->api('answerCallbackQuery', $params);
    }

    public function deleteMessage(int $chatId, int $messageId): array
    {
        return $this->api('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    // ─── Dispatch ─────────────────────────────────────────────────────────────

    public function handleWebhook(): void
    {
        $raw    = \Yii::$app->request->rawBody;
        $update = json_decode($raw, true);
        if (!is_array($update)) {
            return;
        }
        $this->dispatch($update);
    }

    private function dispatch(array $update): void
    {
        if (isset($update['message'])) {
            $this->dispatchMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->dispatchCallbackQuery($update['callback_query']);
        }
    }

    // ─── Сообщения ────────────────────────────────────────────────────────────

    private function dispatchMessage(array $msg): void
    {
        $chatId = $msg['chat']['id'];
        $userId = $msg['from']['id'];
        $text   = $msg['text'] ?? '';

        // web_app_data — результат логина из Mini App
        if (isset($msg['web_app_data'])) {
            WebAppDataHandler::handle($this, $chatId, $userId, $msg);
            return;
        }

        // /start
        if (str_starts_with($text, '/start')) {
            StartHandler::handle($this, $chatId, $userId);
            return;
        }

        // Главная кнопка меню → открыть раздел заявок (сразу показывает "Новые")
        if ($text === '📋 Заявки') {
            RequestsMenuHandler::showMenu($this, $chatId, $userId);
            return;
        }

        // Кнопки фильтров раздела заявок
        if (in_array($text, ['🆕 Новые', '💬 Предложения', '🏆 Победитель', '✅ Обработан'], true)) {
            RequestsMenuHandler::handleFilterButton($this, $chatId, $userId, $text);
            return;
        }

        // Назад из раздела заявок → главное меню
        if ($text === '🔙 Назад') {
            $this->sendMessage($chatId, 'Главное меню', ['reply_markup' => Keyboard::mainMenu()]);
            return;
        }

        // Выход
        if ($text === '🚪 Выйти' || $text === '/logout') {
            LogoutHandler::handle($this, $chatId, $userId);
            return;
        }
    }

    // ─── Callback-кнопки ─────────────────────────────────────────────────────

    private function dispatchCallbackQuery(array $cbq): void
    {
        $cbqId     = $cbq['id'];
        $chatId    = $cbq['message']['chat']['id'];
        $userId    = $cbq['from']['id'];
        $messageId = $cbq['message']['message_id'];
        $data      = $cbq['data'] ?? '';

        error_log("[CBQ] data={$data} chatId={$chatId} msgId={$messageId}");
        \Yii::warning("[CBQ] data={$data} chatId={$chatId} msgId={$messageId}");

        // req:menu — вернуться к меню фильтров
        if ($data === 'req:menu') {
            $this->answerCallbackQuery($cbqId);
            RequestsMenuHandler::showMenuEdit($this, $chatId, $userId, $messageId);
            return;
        }

        // req:filter:{type}:{page}
        if (preg_match('/^req:filter:([a-z_]+):(\d+)$/', $data, $m)) {
            $this->answerCallbackQuery($cbqId);
            RequestsMenuHandler::showList($this, $chatId, $userId, $messageId, $m[1], (int) $m[2]);
            return;
        }

        // req:detail:{requestId}:{filterType}:{filterPage}
        if (preg_match('/^req:detail:(\d+):([a-z_]+):(\d+)$/', $data, $m)) {
            $this->answerCallbackQuery($cbqId);
            RequestDetailHandler::show($this, $chatId, $userId, $messageId, (int) $m[1], $m[2], (int) $m[3]);
            return;
        }

        // noop — кнопка-заглушка
        if ($data === 'noop') {
            $this->answerCallbackQuery($cbqId);
            return;
        }

        // Неизвестный callback
        $this->answerCallbackQuery($cbqId);
    }
}
