<?php

namespace app\handlers;

use app\components\ApiClient;
use app\components\Keyboard;
use app\components\TelegramBot;
use app\models\Session;

class RequestDetailHandler
{
    public static function show(
        TelegramBot $bot,
        int $chatId,
        int $userId,
        int $messageId,
        int $requestId,
        string $filterType,
        int $filterPage
    ): void {
        $session = Session::findByTgId($userId);
        if (!$session) {
            return;
        }

        /** @var ApiClient $api */
        $api = \Yii::$app->get('apiClient')->withToken($session->token);

        // Заявка
        $req = $api->getRequest($requestId);
        if (empty($req) || isset($req['error'])) {
            $bot->editMessageText($chatId, $messageId, '❌ Заявка не найдена.');
            return;
        }

        // Материалы
        $matResult = $api->getRequestMaterials($requestId);
        $materials = $matResult['items'] ?? [];

        // Все предложения (нужно знать кол-во групп и победителя)
        $quotesResult = $api->getQuotes($requestId, ['size' => 100]);
        $allQuotes    = $quotesResult['items'] ?? [];

        // Считаем уникальные предложения по группе supplier_id + user_id
        // (бот создаёт одну request_quote на материал, но это одно предложение)
        $uniqueGroups  = [];
        $winnerTotal   = 0.0;
        $winner        = null;

        foreach ($allQuotes as $q) {
            $supplierId = $q['supplier']['id'] ?? 0;
            $userId     = $q['user']['id']     ?? 0;
            $key        = "{$supplierId}_{$userId}";

            $uniqueGroups[$key] = true;

            if (!empty($q['is_winner'])) {
                // Суммируем все победные записи одного поставщика
                $winnerTotal += (float)($q['total_price'] ?? 0);
                if ($winner === null) {
                    $winner = $q; // берём первую для имён
                }
            }
        }

        // Победитель: пересчитываем total как сумму всех его котировок
        if ($winner !== null) {
            $winner['_total_sum'] = $winnerTotal;
        }

        $quotesCount = count($uniqueGroups);

        $bot->editMessageText(
            $chatId,
            $messageId,
            self::buildText($req, $materials, $quotesCount, $winner),
            [
                'parse_mode'   => 'HTML',
                'reply_markup' => self::buildKeyboard($requestId, $filterType, $filterPage, $session->role, $quotesCount),
            ]
        );
    }

    // ─── Форматирование ───────────────────────────────────────────────────────

    private static function buildText(array $req, array $materials, int $quotesCount, ?array $winner): string
    {
        $no          = $req['request_no'] ?? "#{$req['id']}";
        $project     = $req['project']['name'] ?? '—';
        $responsible = $req['responsible_user']['full_name'] ?? ($req['user']['full_name'] ?? '—');
        $needDate    = isset($req['need_date']) ? date('d.m.Y', strtotime($req['need_date'])) : '—';
        $title       = $req['title'] ?? '';

        $text = "<b>📋 Заявка {$no}</b>\n";
        if ($title) {
            $text .= "{$title}\n";
        }
        $text .= "Проект: {$project}\n"
               . "Ответственный: {$responsible}\n"
               . "Срок: {$needDate}\n"
               . "Предложений: {$quotesCount}\n";

        if ($winner !== null) {
            $supplierName = $winner['supplier']['name'] ?? '—';
            // _total_sum — сумма всех котировок победителя (один поставщик, много материалов)
            $totalPrice   = number_format((float)($winner['_total_sum'] ?? $winner['total_price'] ?? 0), 0, '.', ' ');
            $senderName   = $winner['user']['full_name'] ?? '—';
            $text .= "\n🏆 <b>Победитель:</b> {$supplierName}\n"
                   . "   Сумма: {$totalPrice} UZS\n"
                   . "   Предложил: {$senderName}\n";
        }

        if (!empty($materials)) {
            $text .= "\n<b>Товары:</b>\n";
            foreach ($materials as $mat) {
                $name     = $mat['resource']['name'] ?? '—';
                $quantity = $mat['quantity'] ?? '?';
                $unit     = $mat['unit']['name'] ?? '';
                $note     = trim($mat['note'] ?? '');

                if ($note !== '') {
                    $text .= "• {$name} ({$note}) — {$quantity} {$unit}\n";
                } else {
                    $text .= "• {$name} — {$quantity} {$unit}\n";
                }
            }
        } else {
            $text .= "\nТовары не указаны.\n";
        }

        return $text;
    }

    private static function buildKeyboard(int $requestId, string $filterType, int $filterPage, string $role, int $quotesCount): array
    {
        $appUrl = rtrim(\Yii::$app->params['appUrl'], '/');
        $rows   = [];

        if ($role === 'supplier') {
            $offerUrl = $appUrl . "/mini-app/offer?request_id={$requestId}";
            $rows[]   = [Keyboard::webApp('📝 Добавить предложение', $offerUrl)];
        }

        // Кнопка "Предложения" — для всех ролей, если есть хотя бы одно предложение
        if ($quotesCount > 0) {
            $quotesUrl = $appUrl . "/mini-app/quotes?request_id={$requestId}";
            $rows[]    = [Keyboard::webApp('📋 Предложения (' . $quotesCount . ')', $quotesUrl)];
        }

        $rows[] = [Keyboard::cb('🔙 К списку', "req:filter:{$filterType}:{$filterPage}")];

        return Keyboard::inline($rows);
    }
}
