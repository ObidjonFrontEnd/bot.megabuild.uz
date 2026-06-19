<?php

namespace app\handlers;

use app\components\Keyboard;
use app\components\TelegramBot;
use app\models\Session;

class LogoutHandler
{
    public static function handle(TelegramBot $bot, int $chatId, int $userId): void
    {
        $session = Session::findByTgId($userId);

        if (!$session) {
            $bot->sendMessage($chatId, 'Вы не авторизованы.', [
                'reply_markup' => Keyboard::remove(),
            ]);
            return;
        }

        // Инвалидируем токен на сервере
        try {
            /** @var \app\components\ApiClient $api */
            \Yii::$app->get('apiClient')->withToken($session->token)->post('auth/logout');
        } catch (\Throwable) {
            // Если сервер недоступен — всё равно удаляем локальную сессию
        }

        Session::deleteByTgId($userId);

        $bot->sendMessage(
            $chatId,
            "👋 Вы вышли из системы.\n\nНажмите /start, чтобы войти снова.",
            ['reply_markup' => Keyboard::remove()]
        );
    }
}
