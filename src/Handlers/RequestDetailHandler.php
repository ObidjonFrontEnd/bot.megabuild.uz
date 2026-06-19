<?php

namespace App\Handlers;

use App\Api\ApiClient;
use App\Auth\SessionManager;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

class RequestDetailHandler
{
    // Callback: req:detail:{requestId}:{filterType}:{filterPage}
    public static function handle(Nutgram $bot): void
    {
        $tgId   = $bot->userId();
        $session = SessionManager::get($tgId);
        if (!$session) {
            $bot->answerCallbackQuery(['text' => 'Сессия истекла. Нажмите /start']);
            return;
        }

        $data = $bot->callbackQuery()?->data ?? '';
        // req:detail:{requestId}:{filterType}:{filterPage}
        $parts = explode(':', $data);
        if (count($parts) < 5) {
            $bot->answerCallbackQuery();
            return;
        }
        $requestId  = (int) $parts[2];
        $filterType = $parts[3];
        $filterPage = (int) $parts[4];

        $bot->answerCallbackQuery();

        $api = new ApiClient($session['token']);

        // Загружаем заявку
        $req = $api->getRequest($requestId);
        if (empty($req) || isset($req['error'])) {
            $bot->editMessageText('❌ Заявка не найдена.');
            return;
        }

        // Загружаем материалы
        $materialsResult = $api->getRequestMaterials($requestId);
        $materials       = $materialsResult['items'] ?? [];

        // Кол-во предложений
        $quotesResult = $api->getQuotes($requestId, ['size' => 1]);
        $quotesCount  = $quotesResult['pagination']['totalCount'] ?? count($quotesResult['items'] ?? []);

        $text     = self::buildDetailText($req, $materials, $quotesCount);
        $keyboard = self::buildDetailKeyboard($requestId, $filterType, $filterPage, $session['role'], $quotesCount);

        $bot->editMessageText($text, ['reply_markup' => $keyboard, 'parse_mode' => 'HTML']);
    }

    // Сборка текста детали заявки
    private static function buildDetailText(array $req, array $materials, int $quotesCount): string
    {
        $no          = $req['request_no'] ?? "#{$req['id']}";
        $project     = $req['project']['name'] ?? '—';
        $responsible = $req['responsible_user']['full_name'] ?? ($req['user']['full_name'] ?? '—');
        $needDate    = isset($req['need_date']) ? date('d.m.Y', strtotime($req['need_date'])) : '—';

        $text = "<b>📋 Заявка {$no}</b>\n"
              . "Проект: {$project}\n"
              . "Ответственный: {$responsible}\n"
              . "Срок: {$needDate}\n"
              . "Предложений: {$quotesCount}\n";

        if (!empty($materials)) {
            $text .= "\n<b>Товары:</b>\n";
            foreach ($materials as $mat) {
                $resourceName = $mat['resource']['name'] ?? '—';
                $quantity     = $mat['quantity'] ?? '?';
                $unit         = $mat['unit']['name'] ?? '';
                $note         = $mat['note'] ?? '';

                if ($note !== '' && $note !== null) {
                    $text .= "• {$resourceName} ({$note}) — {$quantity} {$unit}\n";
                } else {
                    $text .= "• {$resourceName} — {$quantity} {$unit}\n";
                }
            }
        } else {
            $text .= "\nТовары не указаны.\n";
        }

        return $text;
    }

    // Кнопки в зависимости от роли
    private static function buildDetailKeyboard(int $requestId, string $filterType, int $filterPage, string $role, int $quotesCount): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if ($role === 'supplier') {
            $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
            $offerUrl = $appUrl . "/miniapp/offer.html?request_id={$requestId}";
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    '📝 Добавить предложение',
                    web_app: new WebAppInfo(url: $offerUrl)
                )
            );
        }

        if ($role === 'finance_manager') {
            if ($quotesCount > 0) {
                // Вариант А — Mini App
                $appUrl     = rtrim($_ENV['APP_URL'] ?? '', '/');
                $compareUrl = $appUrl . "/miniapp/compare.html?request_id={$requestId}";
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        '📊 Смотреть предложения (таблица)',
                        web_app: new WebAppInfo(url: $compareUrl)
                    )
                );
                // Вариант Б — в чате
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        '📋 Смотреть предложения (в чате)',
                        callback_data: "quotes:list:{$requestId}:1"
                    )
                );
            } else {
                $keyboard->addRow(
                    InlineKeyboardButton::make('💬 Предложений пока нет', callback_data: 'noop')
                );
            }
        }

        // Tracking — доступен после выбора победителя (для обеих ролей)
        $keyboard->addRow(
            InlineKeyboardButton::make('📊 Tracking', callback_data: "tracking:list:{$requestId}")
        );

        // Кнопка назад к списку
        $keyboard->addRow(
            InlineKeyboardButton::make('🔙 К списку', callback_data: "req:filter:{$filterType}:{$filterPage}")
        );

        return $keyboard;
    }
}
