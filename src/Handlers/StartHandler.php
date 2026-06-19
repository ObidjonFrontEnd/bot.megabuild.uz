<?php

namespace App\Handlers;

use App\Auth\SessionManager;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

class StartHandler
{
    public static function handle(Nutgram $bot): void
    {
        $tgId    = $bot->userId();
        $session = SessionManager::get($tgId);

        if ($session) {
            $roleName = $session['role'] === 'supplier' ? 'Снабженец' : 'Финансовый менеджер';
            $bot->sendMessage(
                "Добро пожаловать, {$session['full_name']}!\nРоль: {$roleName}",
                ['reply_markup' => self::mainMenuKeyboard()]
            );
            return;
        }

        $appUrl     = rtrim($_ENV['APP_URL'] ?? '', '/');
        $miniAppUrl = $appUrl . '/miniapp/index.html';

        // KeyboardButton с web_app — только этот тип позволяет sendData обратно в бот.
        // InlineKeyboardButton.web_app sendData не поддерживает (Telegram Bot API ограничение).
        $bot->sendMessage(
            "Добро пожаловать!\n\nНажмите кнопку «🔑 Войти» ниже, чтобы авторизоваться.",
            ['reply_markup' => ReplyKeyboardMarkup::make(
                resize_keyboard: true,
                one_time_keyboard: true
            )->addRow(
                KeyboardButton::make(
                    text: '🔑 Войти',
                    web_app: new WebAppInfo(url: $miniAppUrl)
                )
            )]
        );
    }

    public static function mainMenuKeyboard(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)
            ->addRow(KeyboardButton::make('📋 Заявки'))
            ->addRow(KeyboardButton::make('🚪 Выйти'));
    }
}
