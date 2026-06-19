<?php

namespace app\handlers;

use app\components\Keyboard;
use app\components\TelegramBot;
use app\models\Session;

class WebAppDataHandler
{
    public static function handle(TelegramBot $bot, int $chatId, int $userId, array $msg): void
    {
        $raw  = $msg['web_app_data']['data'] ?? '';
        $data = json_decode($raw, true);

        if (!$data || !isset($data['token'], $data['role'], $data['user_id'], $data['full_name'])) {
            $bot->sendMessage($chatId, '❌ Ошибка авторизации. Попробуйте снова.');
            return;
        }

        if (!in_array($data['role'], ['supplier', 'finance_manager'], true)) {
            $bot->sendMessage($chatId, '❌ У вас нет доступа к этому боту.');
            return;
        }

        Session::saveSession(
            tgId:     $userId,
            userId:   (int) $data['user_id'],
            fullName: $data['full_name'],
            role:     $data['role'],
            token:    $data['token']
        );

        // Привязать tg_id на основном сервере
        try {
            \Yii::$app->get('apiClient')->setTg($userId, $data['token']);
        } catch (\Throwable $e) {
            \Yii::error('[WebAppData] set-tg failed: ' . $e->getMessage());
        }

        $roleName = $data['role'] === 'supplier' ? 'Снабженец' : 'Финансовый менеджер';
        $bot->sendMessage(
            $chatId,
            "✅ Вы вошли как {$data['full_name']}.\nРоль: {$roleName}",
            ['reply_markup' => Keyboard::mainMenu()]
        );
    }
}
