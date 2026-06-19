<?php
/**
 * GET /bot-token?init_data=<urlencoded Telegram initData>
 *
 * Верифицирует initData через HMAC-SHA256, ищет сессию в SQLite и возвращает
 * { token, role, user_id, full_name } — чтобы Mini App могла обращаться к API.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jsonError(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── 1. Получить и проверить initData ─────────────────────────────────────────

$rawInitData = $_GET['init_data'] ?? '';
if ($rawInitData === '') {
    jsonError(400, 'init_data обязателен');
}

$botToken = $_ENV['TELEGRAM_TOKEN'] ?? '';
if ($botToken === '') {
    jsonError(500, 'TELEGRAM_TOKEN не задан');
}

// Верифицировать согласно https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
parse_str($rawInitData, $fields);
$receivedHash = $fields['hash'] ?? '';
unset($fields['hash']);

// Отсортировать по ключу, собрать data_check_string
ksort($fields);
$dataCheckString = implode("\n", array_map(
    fn ($k, $v) => "$k=$v",
    array_keys($fields),
    array_values($fields)
));

$secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
$expectedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

if (!hash_equals($expectedHash, $receivedHash)) {
    jsonError(403, 'Неверная подпись initData');
}

// Проверить срок (не старше 24 часов)
$authDate = (int) ($fields['auth_date'] ?? 0);
if ($authDate === 0 || time() - $authDate > 86400) {
    jsonError(403, 'initData устарела');
}

// ── 2. Извлечь tg_id из user-поля initData ───────────────────────────────────

$userJson = $fields['user'] ?? '';
if ($userJson === '') {
    jsonError(400, 'Поле user отсутствует в initData');
}

$tgUser = json_decode($userJson, true);
$tgId   = (int) ($tgUser['id'] ?? 0);
if ($tgId === 0) {
    jsonError(400, 'Не удалось получить Telegram ID из initData');
}

// ── 3. Найти сессию в SQLite ──────────────────────────────────────────────────

use App\Storage\Database;

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("SELECT token, role, user_id, full_name FROM sessions WHERE tg_id = :tg_id");
    $stmt->execute(['tg_id' => $tgId]);
    $session = $stmt->fetch();
} catch (\Throwable $e) {
    jsonError(500, 'Ошибка базы данных: ' . $e->getMessage());
}

if (!$session) {
    jsonError(401, 'Сессия не найдена. Войдите через бот командой /start.');
}

// ── 4. Вернуть данные ─────────────────────────────────────────────────────────

echo json_encode([
    'success'   => true,
    'token'     => $session['token'],
    'role'      => $session['role'],
    'user_id'   => (int) $session['user_id'],
    'full_name' => $session['full_name'],
]);
