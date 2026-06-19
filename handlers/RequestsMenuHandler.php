<?php

namespace app\handlers;

use app\components\ApiClient;
use app\components\Keyboard;
use app\components\TelegramBot;
use app\models\Session;

class RequestsMenuHandler
{
    private const PAGE_SIZE = 10;

    // Тексты кнопок → тип фильтра
    private const FILTER_MAP = [
        '🆕 Новые'       => 'new',
        '💬 Предложения' => 'with_quotes',
        '🏆 Победитель'  => 'winner',
        '✅ Обработан'   => 'tracked',
    ];

    // ─── Вход в раздел "Заявки" ───────────────────────────────────────────────

    /**
     * Вызывается при нажатии "📋 Заявки":
     * — переключает клавиатуру на фильтры
     * — сразу показывает список "Новые"
     */
    public static function showMenu(TelegramBot $bot, int $chatId, int $userId): void
    {
        $session = Session::findByTgId($userId);
        if (!$session) {
            $bot->sendMessage($chatId, '❌ Вы не авторизованы. Нажмите /start');
            return;
        }

        // Устанавливаем постоянную клавиатуру раздела
        $bot->sendMessage($chatId, '📋 Заявки', [
            'reply_markup' => Keyboard::requestsMenu(),
        ]);

        // Сразу загружаем список "Новые"
        self::sendList($bot, $chatId, $userId, 'new', 1);
    }

    // ─── Кнопка фильтра (reply-клавиатура) ────────────────────────────────────

    public static function handleFilterButton(TelegramBot $bot, int $chatId, int $userId, string $text): void
    {
        $filterType = self::FILTER_MAP[$text] ?? null;
        if ($filterType === null) {
            return;
        }
        self::sendList($bot, $chatId, $userId, $filterType, 1);
    }

    // ─── Пагинация (inline-callback) ──────────────────────────────────────────

    /**
     * Callback: req:filter:{type}:{page}
     * Редактирует сообщение со списком (inline-пагинация).
     */
    public static function showList(
        TelegramBot $bot,
        int $chatId,
        int $userId,
        int $messageId,
        string $filterType,
        int $page
    ): void {
        $session = Session::findByTgId($userId);
        if (!$session) {
            return;
        }

        /** @var ApiClient $api */
        $api = \Yii::$app->get('apiClient')->withToken($session->token);

        [$items, $hasMore, $error] = self::fetchFiltered($api, $filterType, $page);

        if ($error) {
            $bot->editMessageText($chatId, $messageId, '❌ Ошибка загрузки: ' . $error);
            return;
        }

        if (empty($items)) {
            $bot->editMessageText(
                $chatId,
                $messageId,
                self::filterLabel($filterType) . "\n\nЗаявок нет.",
                ['reply_markup' => Keyboard::inline([[Keyboard::cb('🔄 Обновить', "req:filter:{$filterType}:1")]])]
            );
            return;
        }

        $bot->editMessageText($chatId, $messageId, self::buildListText($filterType, $page), [
            'parse_mode'   => 'HTML',
            'reply_markup' => self::buildListKeyboard($filterType, $items, $page, $hasMore),
        ]);
    }

    // ─── Внутренний метод: отправить новое сообщение со списком ──────────────

    private static function sendList(TelegramBot $bot, int $chatId, int $userId, string $filterType, int $page): void
    {
        $session = Session::findByTgId($userId);
        if (!$session) {
            return;
        }

        /** @var ApiClient $api */
        $api = \Yii::$app->get('apiClient')->withToken($session->token);

        [$items, $hasMore, $error] = self::fetchFiltered($api, $filterType, $page);

        if ($error) {
            $bot->sendMessage($chatId, '❌ Ошибка загрузки: ' . $error);
            return;
        }

        if (empty($items)) {
            $bot->sendMessage($chatId, self::filterLabel($filterType) . "\n\nЗаявок нет.");
            return;
        }

        $bot->sendMessage(
            $chatId,
            self::buildListText($filterType, $page),
            [
                'parse_mode'   => 'HTML',
                'reply_markup' => self::buildListKeyboard($filterType, $items, $page, $hasMore),
            ]
        );
    }

    // ─── Загрузка + фильтрация ────────────────────────────────────────────────

    /**
     * Загружает заявки постранично из API, фильтрует по типу.
     * Загружает ровно столько API-страниц, сколько нужно для заполнения
     * виртуальной страницы $targetPage, плюс одну дополнительную для определения
     * наличия следующей страницы.
     *
     * Возвращает [$pageItems, $total, $error]
     * Если $total > $targetPage * PAGE_SIZE → есть ещё страницы.
     */
    private static function fetchFiltered(ApiClient $api, string $filterType, int $targetPage): array
    {
        $perLoad  = 50;
        $apiPage  = 1;
        $matched  = [];
        $need     = $targetPage * self::PAGE_SIZE + 1; // +1 чтобы знать, есть ли следующая страница

        do {
            $result = $api->getRequests(['size' => $perLoad, 'page' => $apiPage]);

            if (isset($result['error']) || !isset($result['items'])) {
                $errMsg = $result['message'] ?? $result['error'] ?? 'API недоступен';
                if (empty($matched)) {
                    return [[], 0, $errMsg];
                }
                break;
            }

            $apiItems = $result['items'];
            $pagesApi = (int) ($result['pagination']['pageCount'] ?? 1);

            foreach ($apiItems as $req) {
                $enriched = self::enrichIfPasses($api, $req, $filterType);
                if ($enriched !== null) {
                    $matched[] = $enriched;
                    if (count($matched) >= $need) {
                        break; // Достаточно для определения наличия след. страницы
                    }
                }
            }

            if (count($matched) >= $need) {
                break;
            }

            $apiPage++;
        } while ($apiPage <= $pagesApi && count($apiItems) > 0);

        $hasMore   = count($matched) > $targetPage * self::PAGE_SIZE;
        $offset    = ($targetPage - 1) * self::PAGE_SIZE;
        $pageItems = array_slice($matched, $offset, self::PAGE_SIZE);

        return [$pageItems, $hasMore, null];
    }

    /**
     * Проверяет фильтр и возвращает заявку с добавленным полем _quotes_count,
     * либо null если не проходит фильтр.
     */
    private static function enrichIfPasses(ApiClient $api, array $req, string $filterType): ?array
    {
        $id = $req['id'];

        switch ($filterType) {
            case 'new':
                [$count, $winCount, $apiOk] = self::fetchQuotesCounts($api, $id);
                // Если API недоступен (403/ошибка) — пропускаем заявку в фильтр "новые" только если точно 0
                // При ошибке API показываем заявку (хуже не будет — для "новых" критично)
                if (!$apiOk) {
                    $req['_quotes_count'] = '?';
                    return $req;
                }
                if ($count !== 0) return null;
                $req['_quotes_count'] = 0;
                return $req;

            case 'with_quotes':
                [$count, $winCount, $apiOk] = self::fetchQuotesCounts($api, $id);
                if (!$apiOk) {
                    // Нет доступа к котировкам — показываем заявку, чтобы пользователь мог зайти и посмотреть
                    $req['_quotes_count'] = '?';
                    return $req;
                }
                if ($count === 0 || $winCount > 0) return null;
                $req['_quotes_count'] = $count;
                return $req;

            case 'winner':
                // Есть winner И заявка ещё не закрыта
                if (($req['status'] ?? '') === 'close') return null;
                [$count, $winCount, $apiOk] = self::fetchQuotesCounts($api, $id);
                if (!$apiOk) {
                    $req['_quotes_count'] = '?';
                    return $req;
                }
                if ($winCount === 0) return null;
                $req['_quotes_count'] = $count;
                return $req;

            case 'tracked':
                // Обработан = статус заявки "close"
                if (($req['status'] ?? '') !== 'close') return null;
                [$count, , ] = self::fetchQuotesCounts($api, $id);
                $req['_quotes_count'] = $count;
                return $req;
        }

        return null;
    }

    /**
     * Получает кол-во уникальных предложений (групп supplier+user) и победителей.
     * Возвращает [$count, $winCount, $apiOk].
     * $apiOk = false если API вернул ошибку (403, 500, сеть).
     *
     * Бот создаёт одну request_quote на каждый материал → нужно считать
     * уникальные пары supplier_id+user_id, а не сырые строки.
     */
    private static function fetchQuotesCounts(ApiClient $api, int $requestId): array
    {
        $quotes = $api->getQuotes($requestId, ['size' => 100]);

        // Ошибка API (403, timeout, etc.)
        if (isset($quotes['error']) || isset($quotes['message'])) {
            \Yii::warning("[Filter] getQuotes error for request #{$requestId}: "
                . ($quotes['error'] ?? $quotes['message'] ?? 'unknown'));
            return [0, 0, false];
        }

        $items        = $quotes['items'] ?? [];
        $uniqueGroups = [];
        $winGroups    = [];

        foreach ($items as $q) {
            $supplierId = $q['supplier']['id'] ?? 0;
            $userId     = $q['user']['id']     ?? 0;
            $key        = "{$supplierId}_{$userId}";

            $uniqueGroups[$key] = true;

            if (!empty($q['is_winner'])) {
                $winGroups[$key] = true;
            }
        }

        $count    = count($uniqueGroups);
        $winCount = count($winGroups);

        return [$count, $winCount, true];
    }

    // ─── Форматирование ───────────────────────────────────────────────────────

    private static function buildListText(string $filterType, int $page): string
    {
        return '<b>' . self::filterLabel($filterType) . '</b>  ' . "(стр. {$page})";
    }

    private static function buildListKeyboard(string $filterType, array $items, int $page, bool $hasMore): array
    {
        $rows = [];

        foreach ($items as $req) {
            $no          = $req['request_no'] ?? "#{$req['id']}";
            $responsible = $req['responsible_user']['full_name'] ?? ($req['user']['full_name'] ?? '—');
            $needDate    = isset($req['need_date']) ? date('d.m.Y', strtotime($req['need_date'])) : '—';
            $quotesCount = $req['_quotes_count'] ?? 0;
            $id          = $req['id'];

            $countStr = ($quotesCount === '?' || $quotesCount === 0) ? '💬' : "{$quotesCount}💬";
            $label  = "📋 {$no}  {$responsible}  {$needDate}  【{$countStr}】";
            $rows[] = [Keyboard::cb($label, "req:detail:{$id}:{$filterType}:{$page}")];
        }

        // Пагинация: [◀]  [стр. X]  [▶]
        $nav   = [];
        $nav[] = $page > 1
            ? Keyboard::cb('◀', "req:filter:{$filterType}:" . ($page - 1))
            : Keyboard::cb('·', 'noop');

        $nav[] = Keyboard::cb("стр. {$page}", 'noop');

        $nav[] = $hasMore
            ? Keyboard::cb('▶', "req:filter:{$filterType}:" . ($page + 1))
            : Keyboard::cb('·', 'noop');

        $rows[] = $nav;

        return Keyboard::inline($rows);
    }

    public static function filterLabel(string $filterType): string
    {
        return match($filterType) {
            'new'         => '🆕 Новые заявки',
            'with_quotes' => '💬 Заявки с предложениями',
            'winner'      => '🏆 Объявлен победитель',
            'tracked'     => '✅ Обработанные заявки',
            default       => '📋 Заявки',
        };
    }
}
