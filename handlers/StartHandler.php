<?php

namespace app\handlers;

use app\components\Keyboard;
use app\components\TelegramBot;
use app\models\Session;

class StartHandler
{
    public static function handle(TelegramBot $bot, int $chatId, int $userId): void
    {
        $session = Session::findByTgId($userId);

        if ($session) {
            $roleName = $session->role === 'supplier' ? 'Снабженец' : 'Финансовый менеджер';
            $bot->sendMessage(
                $chatId,
                "Добро пожаловать, {$session->full_name}!\nРоль: {$roleName}",
                ['reply_markup' => Keyboard::mainMenu()]
            );
            return;
        }

        $appUrl     = rtrim(\Yii::$app->params['appUrl'], '/');
        $miniAppUrl = $appUrl . '/mini-app/login?tg_id=' . $chatId;

        $bot->sendMessage(
            $chatId,
            "Добро пожаловать!\n\nНажмите кнопку «🔑 Войти» для авторизации.",
            [
                'reply_markup' => Keyboard::reply([
                    [Keyboard::webAppBtn('🔑 Войти', $miniAppUrl)],
                ], resize: true, oneTime: true),
            ]
        );
    }
}
