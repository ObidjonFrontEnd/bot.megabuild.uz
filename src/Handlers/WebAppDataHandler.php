<?php

namespace App\Handlers;

use App\Auth\SessionManager;
use SergiX44\Nutgram\Nutgram;

class WebAppDataHandler
{
    public static function handle(Nutgram $bot): void
    {
        $webAppData = $bot->message()?->web_app_data;
        if (!$webAppData) {
            return;
        }

        $data = json_decode($webAppData->data, true);

        if (!$data || !isset($data['token'], $data['role'], $data['user_id'], $data['full_name'])) {
            $bot->sendMessage('❌ Ошибка авторизации. Попробуйте снова.');
            return;
        }

        $allowedRoles = ['supplier', 'finance_manager'];
        if (!in_array($data['role'], $allowedRoles, true)) {
            $bot->sendMessage('❌ У вас нет доступа к этому боту.');
            return;
        }

        $tgId = $bot->userId();
        SessionManager::save(
            tgId:     $tgId,
            userId:   (int) $data['user_id'],
            fullName: $data['full_name'],
            role:     $data['role'],
            token:    $data['token']
        );

        $roleName = $data['role'] === 'supplier' ? 'Снабженец' : 'Финансовый менеджер';
        $bot->sendMessage(
            "✅ Вы вошли как {$data['full_name']}.\nРоль: {$roleName}",
            ['reply_markup' => StartHandler::mainMenuKeyboard()]
        );
    }
}
