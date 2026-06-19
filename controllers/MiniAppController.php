<?php

namespace app\controllers;

use yii\web\Controller;

class MiniAppController extends Controller
{
    public $layout = '@app/views/layouts/mini-app';

    public function actionLogin(): string
    {
        $this->view->title = 'Вход — Тендерный бот';
        return $this->render('login');
    }

    public function actionOffer(): string
    {
        $requestId = (int) \Yii::$app->request->get('request_id', 0);
        $this->view->title = 'Добавить предложение';
        return $this->render('offer', ['requestId' => $requestId]);
    }

    public function actionCompare(): string
    {
        $requestId = (int) \Yii::$app->request->get('request_id', 0);
        $this->view->title = 'Сравнение предложений';
        return $this->render('compare', ['requestId' => $requestId]);
    }

    /**
     * POST /mini-app/notify-winner
     * Отправляет снабженцу (победителю) Telegram-сообщение о том,
     * что его предложение выбрано победителем.
     *
     * Body JSON: {
     *   winner_user_id: int,
     *   winner_name   : string,
     *   request_no    : string,
     *   supplier_name : string,
     *   total_price   : float,
     *   currency      : string,
     *   materials     : [{ name, qty, unit, unit_price, total }]
     * }
     */
    public function actionNotifyWinner(): array
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        \Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        \Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type');

        if (\Yii::$app->request->method === 'OPTIONS') {
            \Yii::$app->response->statusCode = 204;
            return [];
        }

        $body         = json_decode(\Yii::$app->request->rawBody, true) ?? [];
        $winnerUserId = (int) ($body['winner_user_id'] ?? 0);

        if ($winnerUserId === 0) {
            \Yii::$app->response->statusCode = 400;
            return ['success' => false, 'message' => 'winner_user_id обязателен'];
        }

        // Ищем сессию победителя по user_id → получаем его tg_id
        $session = \app\models\Session::findOne(['user_id' => $winnerUserId]);
        if (!$session || !$session->tg_id) {
            // Победитель ещё не привязал Telegram — молча игнорируем
            return ['success' => true, 'message' => 'Победитель не привязан к Telegram'];
        }

        $tgId        = (int) $session->tg_id;
        $winnerName  = $body['winner_name']  ?? ($session->full_name ?? '');
        $requestNo   = $body['request_no']   ?? '—';
        $supplierName = $body['supplier_name'] ?? '—';
        $totalPrice  = (float) ($body['total_price'] ?? 0);
        $currency    = $body['currency']     ?? 'UZS';
        $materials   = $body['materials']    ?? [];

        // Формируем сообщение победителю
        $fmtTotal = number_format($totalPrice, 0, '.', ' ');

        $text  = "🏆 <b>Ваше предложение выбрано победителем!</b>\n\n";
        $text .= "📋 Заявка: <b>{$requestNo}</b>\n";
        $text .= "🏢 Поставщик: <b>" . htmlspecialchars($supplierName) . "</b>\n";
        if ($winnerName) {
            $text .= "👤 Снабженец: <b>" . htmlspecialchars($winnerName) . "</b>\n";
        }

        if (!empty($materials)) {
            $text .= "\n<b>Товары к закупке:</b>\n";
            foreach ($materials as $m) {
                $mName  = htmlspecialchars($m['name']  ?? '—');
                $mQty   = $m['qty']   ?? null;
                $mUnit  = htmlspecialchars($m['unit']  ?? '');
                $mPrice = isset($m['unit_price']) ? number_format((float)$m['unit_price'], 0, '.', ' ') : null;
                $mTotal = number_format((float)($m['total'] ?? 0), 0, '.', ' ');

                if ($mQty !== null && $mPrice !== null) {
                    $text .= "• {$mName} — {$mQty} {$mUnit} × {$mPrice} = <b>{$mTotal} {$currency}</b>\n";
                } else {
                    $text .= "• {$mName} — <b>{$mTotal} {$currency}</b>\n";
                }
            }
        }

        $text .= "\n💰 <b>Итого: {$fmtTotal} {$currency}</b>\n";
        $text .= "\n✅ Приступайте к закупке!";

        try {
            /** @var \app\components\TelegramBot $bot */
            \Yii::$app->get('bot')->sendMessage($tgId, $text, ['parse_mode' => 'HTML']);
            return ['success' => true];
        } catch (\Throwable $e) {
            \Yii::error('[notify-winner] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Ошибка отправки уведомления'];
        }
    }

    public function actionQuotes(): string
    {
        $requestId = (int) \Yii::$app->request->get('request_id', 0);
        $this->view->title = 'Предложения';
        return $this->render('quotes', ['requestId' => $requestId]);
    }

    /**
     * POST /mini-app/notify-offer
     * Отправляет снабженцу в Telegram подтверждение о его предложении.
     *
     * Body JSON: {
     *   tg_id        : int,
     *   request_no   : string,
     *   supplier_name: string,
     *   total_price  : float,
     *   currency     : string,
     *   delivery_days: int|null,
     *   comment      : string|null,
     *   materials    : [{ name, qty, unit, unit_price, total }]
     * }
     */
    public function actionNotifyOffer(): array
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        \Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        \Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type');

        if (\Yii::$app->request->method === 'OPTIONS') {
            \Yii::$app->response->statusCode = 204;
            return [];
        }

        $body  = json_decode(\Yii::$app->request->rawBody, true) ?? [];
        $tgId  = (int) ($body['tg_id'] ?? 0);

        if ($tgId === 0) {
            \Yii::$app->response->statusCode = 400;
            return ['success' => false, 'message' => 'tg_id обязателен'];
        }

        // Проверяем что сессия существует (безопасность)
        $session = \app\models\Session::findByTgId($tgId);
        if (!$session) {
            \Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => 'Сессия не найдена'];
        }

        $requestNo    = $body['request_no']    ?? '—';
        $supplierName = $body['supplier_name'] ?? '—';
        $totalPrice   = (float) ($body['total_price'] ?? 0);
        $currency     = $body['currency']      ?? 'UZS';
        $deliveryDays = isset($body['delivery_days']) && $body['delivery_days'] > 0
                        ? (int) $body['delivery_days'] . ' дн.'
                        : '—';
        $comment      = trim($body['comment'] ?? '');
        $materials    = $body['materials']     ?? [];

        // Формируем сообщение
        $text  = "✅ <b>Предложение отправлено!</b>\n\n";
        $text .= "📋 Заявка: <b>{$requestNo}</b>\n";
        $text .= "🏢 Поставщик: <b>" . htmlspecialchars($supplierName) . "</b>\n";

        if (!empty($materials)) {
            $text .= "\n<b>Материалы:</b>\n";
            foreach ($materials as $m) {
                $mName  = htmlspecialchars($m['name']  ?? '—');
                $mQty   = $m['qty']        ?? '?';
                $mUnit  = htmlspecialchars($m['unit']  ?? '');
                $mUprc  = number_format((float)($m['unit_price'] ?? 0), 0, '.', ' ');
                $mTotal = number_format((float)($m['total']      ?? 0), 0, '.', ' ');
                $text  .= "• {$mName} — {$mQty} {$mUnit} × {$mUprc} = <b>{$mTotal} {$currency}</b>\n";
            }
        }

        $fmtTotal = number_format($totalPrice, 0, '.', ' ');
        $text .= "\n💰 <b>Итого: {$fmtTotal} {$currency}</b>\n";
        $text .= "🚚 Срок поставки: {$deliveryDays}\n";

        if ($comment !== '') {
            $text .= "💬 Комментарий: " . htmlspecialchars($comment) . "\n";
        }

        try {
            /** @var \app\components\TelegramBot $bot */
            \Yii::$app->get('bot')->sendMessage($tgId, $text, ['parse_mode' => 'HTML']);
            return ['success' => true];
        } catch (\Throwable $e) {
            \Yii::error('[notify-offer] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Ошибка отправки уведомления'];
        }
    }
}
