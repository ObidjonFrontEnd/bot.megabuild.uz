<?php

namespace app\controllers;

use app\models\Session;
use yii\web\Controller;
use yii\web\Response;

/**
 * GET /bot-token?init_data=<urlencoded Telegram initData>
 *
 * Верифицирует initData через HMAC-SHA256, ищет сессию по tg_id и возвращает
 * { success, token, role, user_id, full_name } для Mini App.
 */
class BotTokenController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * POST /bot-token/link  { init_data, code }
     * Привязка tg_id по одноразовому коду с основного сервера.
     */
    public function actionLink(): array
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        \Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        \Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type');

        if (\Yii::$app->request->method === 'OPTIONS') {
            \Yii::$app->response->statusCode = 204;
            return [];
        }

        $body     = json_decode(\Yii::$app->request->rawBody, true) ?? [];
        $login    = trim($body['login'] ?? '');
        $password = $body['password'] ?? '';

        if ($login === '' || $password === '') {
            \Yii::$app->response->statusCode = 400;
            return ['success' => false, 'message' => 'Логин и пароль обязательны'];
        }

        // Аутентификация на основном сервере
        try {
            /** @var \app\components\ApiClient $apiClient */
            $apiClient = \Yii::$app->get('apiClient');
            $result    = $apiClient->login($login, $password);
        } catch (\Throwable $e) {
            \Yii::$app->response->statusCode = 502;
            return ['success' => false, 'message' => 'Ошибка связи с сервером'];
        }

        $token = $result['token'] ?? '';
        if ($token === '') {
            \Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => $result['message'] ?? 'Неверный логин или пароль'];
        }

        $user     = $result['user'] ?? [];
        $userId   = (int) ($user['id'] ?? 0);
        $fullName = $user['full_name'] ?? '';
        $roles    = $user['roles'] ?? [];
        $role     = in_array('finance_manager', $roles, true) ? 'finance_manager' : 'supplier';

        // Извлечь tg_id из initData (хэш не проверяем — уже прошли логин/пароль)
        $tgId        = (int) ($body['tg_id'] ?? 0);
        $rawInitData = trim($body['init_data'] ?? '');
        if ($tgId === 0 && $rawInitData !== '') {
            parse_str($rawInitData, $initFields);
            $tgUser = json_decode($initFields['user'] ?? '', true);
            $tgId   = (int) ($tgUser['id'] ?? 0);
        }

        // Привязать tg_id к аккаунту на основном сервере (если есть)
        if ($tgId !== 0) {
            try {
                $apiClient->setTg($tgId, $token);
            } catch (\Throwable $e) {
                \Yii::error('[LINK] set-tg failed: ' . $e->getMessage());
            }
        }

        // Сохранить сессию локально
        \app\models\Session::saveSession($tgId, $userId, $fullName, $role, $token);

        // Обновить клавиатуру в чате
        if ($tgId !== 0) {
            $roleName = $role === 'finance_manager' ? 'Финансовый менеджер' : 'Снабженец';
            \Yii::$app->get('bot')->sendMessage(
                $tgId,
                "✅ Вы вошли как {$fullName} ({$roleName})",
                ['reply_markup' => \app\components\Keyboard::mainMenu()]
            );
        }

        return [
            'success'   => true,
            'token'     => $token,
            'role'      => $role,
            'user_id'   => $userId,
            'full_name' => $fullName,
        ];
    }

    public function actionLogout(): array
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        \Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');

        if (\Yii::$app->request->method === 'OPTIONS') {
            \Yii::$app->response->statusCode = 204;
            return [];
        }

        $body  = json_decode(\Yii::$app->request->rawBody, true) ?? [];
        $token = trim($body['token'] ?? '');

        if ($token !== '') {
            \app\models\Session::deleteAll(['token' => $token]);
        }

        return ['success' => true];
    }

    public function actionIndex(): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        // CORS — Mini App открывается в Telegram и делает fetch к боту
        \Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        \Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type');

        if (\Yii::$app->request->method === 'OPTIONS') {
            \Yii::$app->response->statusCode = 204;
            return [];
        }

        $rawInitData = \Yii::$app->request->get('init_data', '');
        if ($rawInitData === '') {
            \Yii::$app->response->statusCode = 400;
            return ['success' => false, 'message' => 'init_data обязателен'];
        }

        // ── Верификация Telegram initData ─────────────────────────────────────
        $botToken = \Yii::$app->params['telegramToken'] ?? '';
        if ($botToken === '') {
            \Yii::$app->response->statusCode = 500;
            return ['success' => false, 'message' => 'TELEGRAM_TOKEN не задан'];
        }

        parse_str($rawInitData, $fields);
        $receivedHash = $fields['hash'] ?? '';
        unset($fields['hash']);

        ksort($fields);
        $dataCheckString = implode("\n", array_map(
            fn ($k, $v) => "$k=$v",
            array_keys($fields),
            array_values($fields)
        ));

        $secretKey    = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expectedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

        if (!hash_equals($expectedHash, $receivedHash)) {
            \Yii::$app->response->statusCode = 403;
            return ['success' => false, 'message' => 'Неверная подпись initData'];
        }

        $authDate = (int) ($fields['auth_date'] ?? 0);
        if ($authDate === 0 || time() - $authDate > 86400) {
            \Yii::$app->response->statusCode = 403;
            return ['success' => false, 'message' => 'initData устарела'];
        }

        // ── Извлечь tg_id из поля user ────────────────────────────────────────
        $userJson = $fields['user'] ?? '';
        if ($userJson === '') {
            \Yii::$app->response->statusCode = 400;
            return ['success' => false, 'message' => 'Поле user отсутствует в initData'];
        }

        $tgUser = json_decode($userJson, true);
        $tgId   = (int) ($tgUser['id'] ?? 0);
        if ($tgId === 0) {
            \Yii::$app->response->statusCode = 400;
            return ['success' => false, 'message' => 'Не удалось получить Telegram ID'];
        }

        // ── Найти сессию ─────────────────────────────────────────────────────
        $session = Session::findByTgId($tgId);
        if (!$session) {
            \Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => 'Сессия не найдена. Войдите через бот командой /start.'];
        }

        return [
            'success'   => true,
            'token'     => $session->token,
            'role'      => $session->role,
            'user_id'   => (int) $session->user_id,
            'full_name' => $session->full_name,
        ];
    }
}
