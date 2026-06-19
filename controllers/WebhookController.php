<?php

namespace app\controllers;

use yii\web\Controller;
use yii\web\Response;

class WebhookController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionIndex(): Response
    {
        \Yii::$app->response->format = Response::FORMAT_RAW;

        if (\Yii::$app->request->method !== 'POST') {
            return \Yii::$app->response;
        }

        $rawInput = \Yii::$app->request->rawBody;
        if (empty($rawInput)) {
            return \Yii::$app->response;
        }

        // Проверка X-Telegram-Bot-Api-Secret-Token
        $secret = \Yii::$app->params['telegramWebhookSecret'] ?? '';
        if ($secret !== '') {
            $incoming = \Yii::$app->request->headers->get('X-Telegram-Bot-Api-Secret-Token', '');
            if (!hash_equals($secret, $incoming)) {
                \Yii::$app->response->statusCode = 403;
                return \Yii::$app->response;
            }
        }

        try {
            /** @var \app\components\TelegramBot $bot */
            \Yii::$app->get('bot')->handleWebhook();
        } catch (\Throwable $e) {
            \Yii::error('[WEBHOOK] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        return \Yii::$app->response;
    }
}
