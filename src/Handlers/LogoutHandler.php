<?php

namespace App\Handlers;

use App\Api\ApiClient;
use App\Auth\SessionManager;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

class LogoutHandler
{
    public static function handle(Nutgram $bot): void
    {
        $tgId   = $bot->userId();
        $session = SessionManager::get($tgId);

        if (!$session) {
            $bot->sendMessage(
                'Вы не авторизованы.',
                ['reply_markup' => new ReplyKeyboardRemove(remove_keyboard: true)]
            );
            return;
        }

        // Инвалидируем токен на сервере
        try {
            $api = new ApiClient($session['token']);
            $api->post('auth/logout');
        } catch (\Throwable) {
            // Если сервер недоступен — всё равно удаляем локальную сессию
        }

        SessionManager::delete($tgId);

        $bot->sendMessage(
            "👋 Вы вышли из системы.\n\nНажмите /start, чтобы войти снова.",
            ['reply_markup' => new ReplyKeyboardRemove(remove_keyboard: true)]
        );
    }
}
