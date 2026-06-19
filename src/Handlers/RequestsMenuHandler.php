<?php

namespace App\Handlers;

use App\Api\ApiClient;
use App\Auth\SessionManager;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class RequestsMenuHandler
{
    private const PAGE_SIZE = 10;

    // Показать главное меню "Заявки" с 4 фильтрами
    public static function showMenu(Nutgram $bot): void
    {
        $tgId   = $bot->userId();
        $session = SessionManager::get($tgId);
        if (!$session) {
            $bot->sendMessage('❌ Вы не авторизованы. Нажмите /start');
            return;
        }

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('🆕 Новые',              callback_data: 'req:filter:new:1'))
            ->addRow(InlineKeyboardButton::make('💬 Есть предложения',   callback_data: 'req:filter:with_quotes:1'))
            ->addRow(InlineKeyboardButton::make('🏆 Объявлен победитель', callback_data: 'req:filter:winner:1'))
            ->addRow(InlineKeyboardButton::make('✅ Обработан',          callback_data: 'req:filter:tracked:1'));

        $bot->sendMessage('📋 Заявки — выберите раздел:', ['reply_markup' => $keyboard]);
    }

    // Обработчик фильтра: req:filter:{type}:{page}
    public static function handleFilter(Nutgram $bot): void
    {
        $tgId   = $bot->userId();
        $session = SessionManager::get($tgId);
        if (!$session) {
            $bot->answerCallbackQuery(['text' => 'Сессия истекла. Нажмите /start']);
            return;
        }

        $data = $bot->callbackQuery()?->data ?? '';
        // Формат: req:filter:{type}:{page}
        $parts = explode(':', $data);
        if (count($parts) < 4) {
            $bot->answerCallbackQuery();
            return;
        }
        $filterType = $parts[2];
        $page       = max(1, (int) $parts[3]);

        $api = new ApiClient($session['token']);

        $items      = [];
        $totalCount = 0;
        $error      = null;

        switch ($filterType) {
            case 'new':
                // Новые = нет ни одной котировки
                [$items, $totalCount, $error] = self::fetchFiltered($api, $page, 'new');
                break;

            case 'with_quotes':
                [$items, $totalCount, $error] = self::fetchFiltered($api, $page, 'with_quotes');
                break;

            case 'winner':
                [$items, $totalCount, $error] = self::fetchFiltered($api, $page, 'winner');
                break;

            case 'tracked':
                [$items, $totalCount, $error] = self::fetchFiltered($api, $page, 'tracked');
                break;

            default:
                $bot->answerCallbackQuery(['text' => 'Неизвестный фильтр']);
                return;
        }

        $bot->answerCallbackQuery();

        if ($error) {
            $bot->editMessageText('❌ Ошибка загрузки заявок: ' . $error);
            return;
        }

        if (empty($items)) {
            $filterLabels = [
                'new'         => '🆕 Новые',
                'with_quotes' => '💬 Есть предложения',
                'winner'      => '🏆 Объявлен победитель',
                'tracked'     => '✅ Обработан',
            ];
            $label = $filterLabels[$filterType] ?? $filterType;
            $bot->editMessageText("📋 {$label}\n\nЗаявок не найдено.");
            return;
        }

        $text     = self::buildListText($filterType, $items, $page, $totalCount);
        $keyboard = self::buildListKeyboard($filterType, $items, $page, $totalCount);

        $bot->editMessageText($text, ['reply_markup' => $keyboard, 'parse_mode' => 'HTML']);
    }

    // Получить список заявок с фильтрацией на стороне бота
    private static function fetchFiltered(ApiClient $api, int $page, string $filterType): array
    {
        // Для фильтров требующих доп. данных нам нужно загрузить все заявки постранично
        // и фильтровать. Чтобы не делать сотни запросов — загружаем большой размер страницы
        // и фильтруем до нужной "страницы бота".
        $perLoad = 50;
        $apiPage = 1;
        $matched = [];
        $scanned = 0;

        // Для простых фильтров (new/winner/tracked) фильтруем локально
        // Продолжаем загружать страницы API пока не наберём достаточно или не кончится
        do {
            $result = $api->getRequests(['size' => $perLoad, 'page' => $apiPage]);

            if (isset($result['error']) || !isset($result['items'])) {
                return [[], 0, $result['message'] ?? 'API недоступен'];
            }

            $apiItems   = $result['items'] ?? [];
            $apiTotal   = $result['pagination']['totalCount'] ?? count($apiItems);
            $apiPages   = $result['pagination']['pageCount'] ?? 1;

            foreach ($apiItems as $req) {
                $pass = self::passesFilter($api, $req, $filterType);
                if ($pass) {
                    $matched[] = $req;
                }
            }

            $scanned += count($apiItems);
            $apiPage++;
        } while (count($apiItems) === $perLoad && $apiPage <= $apiPages && count($matched) < $page * self::PAGE_SIZE + self::PAGE_SIZE);

        $totalMatched = count($matched);
        $offset       = ($page - 1) * self::PAGE_SIZE;
        $pageItems    = array_slice($matched, $offset, self::PAGE_SIZE);

        return [$pageItems, $totalMatched, null];
    }

    // Проверить, проходит ли заявка через фильтр
    private static function passesFilter(ApiClient $api, array $req, string $filterType): bool
    {
        $requestId = $req['id'];

        switch ($filterType) {
            case 'new':
                // Нет ни одной котировки
                $quotes = $api->getQuotes($requestId, ['size' => 1]);
                $count  = $quotes['pagination']['totalCount'] ?? count($quotes['items'] ?? []);
                return $count === 0;

            case 'with_quotes':
                // Есть котировки, нет победителя
                $quotes   = $api->getQuotes($requestId, ['size' => 1]);
                $count    = $quotes['pagination']['totalCount'] ?? count($quotes['items'] ?? []);
                $winners  = $api->getQuotes($requestId, ['is_winner' => 1, 'size' => 1]);
                $winCount = $winners['pagination']['totalCount'] ?? count($winners['items'] ?? []);
                return $count > 0 && $winCount === 0;

            case 'winner':
                // Есть победитель
                $winners  = $api->getQuotes($requestId, ['is_winner' => 1, 'size' => 1]);
                $winCount = $winners['pagination']['totalCount'] ?? count($winners['items'] ?? []);
                return $winCount > 0;

            case 'tracked':
                // Есть запись в request-tracking
                $tracking = $api->getTracking($requestId);
                $items    = $tracking['items'] ?? $tracking;
                return is_array($items) && count($items) > 0;
        }

        return false;
    }

    // Собрать текст списка
    private static function buildListText(string $filterType, array $items, int $page, int $total): string
    {
        $filterLabels = [
            'new'         => '🆕 Новые заявки',
            'with_quotes' => '💬 Заявки с предложениями',
            'winner'      => '🏆 Объявлен победитель',
            'tracked'     => '✅ Обработанные заявки',
        ];

        $label  = $filterLabels[$filterType] ?? 'Заявки';
        $pages  = (int) ceil($total / self::PAGE_SIZE);
        $header = "<b>{$label}</b>  (стр. {$page}/{$pages}, всего {$total})\n\n";

        $lines = [];
        foreach ($items as $req) {
            $no          = $req['request_no'] ?? "#{$req['id']}";
            $project     = $req['project']['name'] ?? '—';
            $responsible = $req['responsible_user']['full_name'] ?? ($req['user']['full_name'] ?? '—');
            $needDate    = isset($req['need_date']) ? date('d.m.Y', strtotime($req['need_date'])) : '—';

            $lines[] = "📋 <b>{$no}</b>\n"
                     . "   Проект: {$project}\n"
                     . "   Ответственный: {$responsible}\n"
                     . "   Срок: {$needDate}";
        }

        return $header . implode("\n\n", $lines);
    }

    // Кнопки списка: по заявке + пагинация + назад
    private static function buildListKeyboard(string $filterType, array $items, int $page, int $total): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($items as $req) {
            $no    = $req['request_no'] ?? "#{$req['id']}";
            $id    = $req['id'];
            $keyboard->addRow(
                InlineKeyboardButton::make("📋 {$no}", callback_data: "req:detail:{$id}:{$filterType}:{$page}")
            );
        }

        // Пагинация
        $pages = (int) ceil($total / self::PAGE_SIZE);
        $navRow = [];
        if ($page > 1) {
            $navRow[] = InlineKeyboardButton::make('◀ Назад', callback_data: "req:filter:{$filterType}:" . ($page - 1));
        }
        if ($page < $pages) {
            $navRow[] = InlineKeyboardButton::make('Вперёд ▶', callback_data: "req:filter:{$filterType}:" . ($page + 1));
        }
        if (!empty($navRow)) {
            $keyboard->addRow(...$navRow);
        }

        // Кнопка назад к меню фильтров
        $keyboard->addRow(
            InlineKeyboardButton::make('🔙 К разделам', callback_data: 'req:menu')
        );

        return $keyboard;
    }
}
