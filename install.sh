#!/usr/bin/env bash
# ==========================================================================
#  Установщик Telegram-бота. Запускать из папки bot/:
#      bash install.sh
#  Скрипт сам спросит 3 значения, поставит зависимости и подготовит базу.
# ==========================================================================
set -e

cd "$(dirname "$0")"

echo "========================================"
echo "   Установка бота"
echo "========================================"
echo

# --- 1. Проверка PHP ---
if ! command -v php >/dev/null 2>&1; then
    echo "❌ PHP не найден. Установите PHP 8.2+ и повторите."
    echo "   Ubuntu: sudo apt install php php-cli php-sqlite3 php-curl php-mbstring"
    exit 1
fi
PHP_VER=$(php -r 'echo PHP_VERSION;')
echo "✅ PHP найден: $PHP_VER"

# --- 2. Проверка нужных расширений PHP ---
MISSING=""
for ext in pdo_sqlite curl mbstring; do
    if ! php -m | grep -qi "^$ext$"; then
        MISSING="$MISSING $ext"
    fi
done
if [ -n "$MISSING" ]; then
    echo "❌ Не хватает расширений PHP:$MISSING"
    echo "   Ubuntu: sudo apt install php-sqlite3 php-curl php-mbstring"
    exit 1
fi
echo "✅ Расширения PHP на месте (pdo_sqlite, curl, mbstring)"

# --- 3. Composer (установит локально, если в системе его нет) ---
if command -v composer >/dev/null 2>&1; then
    COMPOSER="composer"
else
    echo "ℹ️  Composer не найден — скачиваю локально..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --quiet
    rm -f composer-setup.php
    COMPOSER="php composer.phar"
    echo "✅ Composer скачан в папку проекта"
fi

# --- 4. Зависимости ---
echo
echo "📦 Ставлю зависимости (composer install)..."
$COMPOSER install --no-dev --optimize-autoloader --no-interaction
echo "✅ Зависимости установлены"

# --- 5. Файл .env ---
echo
if [ -f .env ]; then
    echo "ℹ️  Файл .env уже есть — оставляю как есть."
else
    cp .env.example .env
    echo "✅ Создан .env из шаблона. Сейчас заполним 3 значения."
    echo

    read -r -p "1) Токен бота (от @BotFather): " TG_TOKEN
    read -r -p "2) Адрес API основного сервера (напр. http://46.62.246.156/api): " API_URL
    read -r -p "3) Публичный адрес бота (напр. https://bot.domain.uz): " APP_URL

    # Подставляем значения построчно (без regex — безопасно для / : $ в URL)
    php -r '
        $f = ".env";
        $vals = ["TELEGRAM_TOKEN"=>$argv[1], "YII2_API_BASE_URL"=>$argv[2], "APP_URL"=>$argv[3]];
        $lines = explode("\n", file_get_contents($f));
        $seen = [];
        foreach ($lines as $i => $line) {
            foreach ($vals as $k => $v) {
                if (strpos($line, $k."=") === 0) {
                    $lines[$i] = $k."=".trim($v);
                    $seen[$k] = true;
                }
            }
        }
        foreach ($vals as $k => $v) {
            if (empty($seen[$k])) $lines[] = $k."=".trim($v);
        }
        file_put_contents($f, implode("\n", $lines));
    ' "$TG_TOKEN" "$API_URL" "$APP_URL"
    echo "✅ .env заполнен"
fi

# --- 6. Папки и права ---
mkdir -p data runtime/logs runtime/cache
chmod -R 775 data runtime 2>/dev/null || true
echo "✅ Папки data/ и runtime/ готовы"

# --- 7. Инициализация базы (создаст файл и таблицы) ---
echo
echo "🗄  Готовлю базу данных..."
php -d variables_order=EGPCS -r '
    require "vendor/autoload.php";
    Dotenv\Dotenv::createImmutable(__DIR__)->load();
    $p = !empty($_ENV["DB_PATH"]) ? $_ENV["DB_PATH"] : __DIR__."/data/bot.sqlite";
    $dir = dirname($p);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdo = new PDO("sqlite:".$p);
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        tg_id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL,
        full_name TEXT NOT NULL, role TEXT NOT NULL, token TEXT NOT NULL,
        created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
    echo "   База: ".$p."\n";
'
echo "✅ База готова"

echo
echo "========================================"
echo "   ГОТОВО ✅"
echo "========================================"
echo "Осталось:"
echo "  1) Настроить веб-сервер (пример: deploy/nginx.conf.example),"
echo "     корень сайта должен указывать на папку:  $(pwd)/web"
echo "  2) Зарегистрировать webhook Telegram:"
echo "       php set-webhook.php"
echo "  Подробно всё описано в INSTALL.md"
echo
