<?php

namespace app\controllers;

use yii\web\Controller;

class SiteController extends Controller
{
    public function actionIndex(): string
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return json_encode(['status' => 'ok', 'service' => 'tg-bot']);
    }
}
