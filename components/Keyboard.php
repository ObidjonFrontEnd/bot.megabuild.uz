<?php

namespace app\components;

/**
 * Строит массивы клавиатур для Telegram Bot API.
 * Передавать в sendMessage как 'reply_markup' => Keyboard::...
 */
class Keyboard
{
    // ─── Reply-клавиатуры (постоянные внизу экрана) ──────────────────────────

    public static function reply(array $rows, bool $resize = true, bool $oneTime = false): array
    {
        $result = [
            'keyboard'        => $rows,
            'resize_keyboard' => $resize,
        ];
        if ($oneTime) {
            $result['one_time_keyboard'] = true;
        }
        return $result;
    }

    public static function remove(): array
    {
        return ['remove_keyboard' => true];
    }

    /** Обычная текстовая кнопка reply-клавиатуры */
    public static function btn(string $text): array
    {
        return ['text' => $text];
    }

    /** Кнопка с открытием Mini App (reply-клавиатура).
     *  Только этот тип позволяет sendData обратно в бот. */
    public static function webAppBtn(string $text, string $url): array
    {
        return ['text' => $text, 'web_app' => ['url' => $url]];
    }

    // ─── Inline-клавиатуры (кнопки под сообщением) ───────────────────────────

    public static function inline(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    /** Кнопка с callback_data (max 64 байта) */
    public static function cb(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    /** Кнопка с URL */
    public static function url(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    /** Кнопка открывающая Mini App в inline-клавиатуре */
    public static function webApp(string $text, string $url): array
    {
        return ['text' => $text, 'web_app' => ['url' => $url]];
    }

    // ─── Главное меню бота ───────────────────────────────────────────────────

    public static function mainMenu(): array
    {
        return self::reply([
            [self::btn('📋 Заявки')],
            [self::btn('🚪 Выйти')],
        ]);
    }

    /**
     * Клавиатура раздела "Заявки" — постоянная нижняя.
     * Ряд 1: 3 кнопки фильтра | Ряд 2: 1 фильтр + Назад
     */
    public static function requestsMenu(): array
    {
        return self::reply([
            [
                self::btn('🆕 Новые'),
                self::btn('💬 Предложения'),
                self::btn('🏆 Победитель'),
            ],
            [
                self::btn('✅ Обработан'),
                self::btn('🔙 Назад'),
            ],
        ]);
    }
}
