<?php

namespace app\controllers;

use app\models\Session;
use yii\web\Controller;
use yii\web\Response;

class IncomingController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionIndex(): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        if (\Yii::$app->request->method !== 'POST') {
            \Yii::$app->response->statusCode = 405;
            return ['error' => 'Method not allowed'];
        }

        // Проверка X-Secret-Token (из Yii2 основного сервера)
        $secret   = \Yii::$app->params['yii2IncomingSecret'] ?? '';
        $incoming = \Yii::$app->request->headers->get('X-Secret-Token', '');
        if ($secret !== '' && !hash_equals($secret, $incoming)) {
            \Yii::$app->response->statusCode = 403;
            return ['error' => 'Forbidden'];
        }

        $event = json_decode(\Yii::$app->request->rawBody, true) ?? [];
        $type  = $event['event'] ?? '';

        try {
            $this->handleEvent($type, $event);
        } catch (\Throwable $e) {
            \Yii::error('[INCOMING] ' . $e->getMessage());
        }

        return ['ok' => true];
    }

    private function handleEvent(string $type, array $event): void
    {
        switch ($type) {
            case 'new-request':
                $this->handleNewRequest($event);
                break;
            case 'new-quote':
                // TODO: уведомить finance_manager о новом предложении
                break;
            case 'winner-set':
                // TODO: уведомить всех участников заявки о победителе
                break;
        }
    }

    // ─── new-request: рассылка всем supplier и supply_manager ────────────────

    private function handleNewRequest(array $event): void
    {
        $requestId = (int) ($event['request_id'] ?? 0);
        if ($requestId === 0) {
            \Yii::warning('[INCOMING] new-request: отсутствует request_id');
            return;
        }

        // Берём токен любого авторизованного пользователя с широкими правами чтения.
        // Предпочтение: finance_manager > director > supply_manager > supplier.
        $token = $this->getServiceToken();
        if ($token === null) {
            \Yii::warning('[INCOMING] new-request: нет ни одного залогиненного пользователя для вызова API');
            return;
        }

        /** @var \app\components\ApiClient $api */
        $api = \Yii::$app->get('apiClient')->withToken($token);

        // Загружаем данные заявки
        $req = $api->getRequest($requestId);
        if (empty($req) || isset($req['error'])) {
            \Yii::warning("[INCOMING] new-request: не удалось загрузить заявку #{$requestId}: "
                . ($req['error'] ?? 'пустой ответ'));
            return;
        }

        // Загружаем материалы
        $matResult = $api->getRequestMaterials($requestId);
        $materials = $matResult['items'] ?? [];

        // Формируем текст уведомления
        $text = $this->buildNewRequestMessage($req, $materials);

        // Inline-кнопка для перехода в Mini App (форма добавления предложения)
        $appUrl    = rtrim(\Yii::$app->params['appUrl'], '/');
        $offerUrl  = "{$appUrl}/mini-app/offer?request_id={$requestId}";
        $keyboard  = [
            'inline_keyboard' => [[
                ['text' => '📝 Открыть заявку', 'web_app' => ['url' => $offerUrl]],
            ]],
        ];

        // Находим всех supplier и supply_manager из локальных сессий
        $sessions = Session::find()
            ->where(['role' => ['supplier', 'supply_manager']])
            ->andWhere(['>', 'tg_id', 0])
            ->all();

        if (empty($sessions)) {
            \Yii::info("[INCOMING] new-request #{$requestId}: нет зарегистрированных поставщиков");
            return;
        }

        /** @var \app\components\TelegramBot $bot */
        $bot = \Yii::$app->get('bot');
        $sent = 0;

        foreach ($sessions as $session) {
            try {
                $bot->sendMessage((int) $session->tg_id, $text, [
                    'parse_mode'   => 'HTML',
                    'reply_markup' => $keyboard,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                \Yii::warning("[INCOMING] new-request: ошибка отправки tg_id={$session->tg_id}: "
                    . $e->getMessage());
            }
        }

        \Yii::info("[INCOMING] new-request #{$requestId}: отправлено {$sent} из " . count($sessions) . " получателей");
    }

    /**
     * Возвращает токен пользователя с наиболее широкими правами на чтение.
     * Используется для системных запросов к API (загрузка заявки, материалов).
     * Приоритет: finance_manager → director → supply_manager → supplier.
     */
    private function getServiceToken(): ?string
    {
        // Пробуем роли в порядке приоритета прав на чтение
        $rolePriority = ['finance_manager', 'director', 'supply_manager', 'supplier'];

        foreach ($rolePriority as $role) {
            $session = Session::findOne(['role' => $role]);
            if ($session && !empty($session->token)) {
                return $session->token;
            }
        }

        return null;
    }

    /**
     * Формирует текст Telegram-уведомления о новой заявке.
     */
    private function buildNewRequestMessage(array $req, array $materials): string
    {
        $no       = htmlspecialchars($req['request_no'] ?? "#{$req['id']}");
        $project  = htmlspecialchars($req['project']['name'] ?? '—');
        $creator  = htmlspecialchars($req['user']['full_name'] ?? '—');
        $needDate = isset($req['need_date'])
            ? date('d.m.Y', strtotime($req['need_date']))
            : '—';
        $title    = trim($req['title'] ?? '');

        $text  = "📋 <b>Новая заявка {$no}</b>\n";
        if ($title !== '') {
            $text .= htmlspecialchars($title) . "\n";
        }
        $text .= "Проект: {$project}\n";
        $text .= "От: {$creator}\n";
        $text .= "Срок: {$needDate}\n";

        if (!empty($materials)) {
            $text .= "\n<b>Что нужно:</b>\n";
            foreach ($materials as $mat) {
                $name = htmlspecialchars($mat['resource']['name'] ?? '—');
                $qty  = $mat['quantity'] ?? '?';
                $unit = htmlspecialchars($mat['unit']['name'] ?? '');
                $note = trim($mat['note'] ?? '');

                if ($note !== '') {
                    $text .= "• {$name} (" . htmlspecialchars($note) . ") — {$qty} {$unit}\n";
                } else {
                    $text .= "• {$name} — {$qty} {$unit}\n";
                }
            }
        }

        return $text;
    }
}
