# Установка бота на сервер (без Docker)

Инструкция для установки «с нуля». Подходит даже без опыта программирования —
нужно лишь по очереди выполнить команды.

---

## Что должно быть на сервере

- **PHP 8.2 или новее** с расширениями: `pdo_sqlite`, `curl`, `mbstring`.
- **Веб-сервер**: Nginx (рекомендуется) или Apache.
- **Git** — чтобы скачать проект.
- Домен с HTTPS для бота (Telegram требует https для webhook и Mini App).

Установить PHP на Ubuntu/Debian:
```bash
sudo apt update
sudo apt install -y php php-cli php-fpm php-sqlite3 php-curl php-mbstring git unzip
```

---

## Шаг 1. Скачать проект

```bash
cd /var/www              # или любая папка, где будет жить бот
git clone <АДРЕС_ВАШЕГО_РЕПОЗИТОРИЯ> tg
cd tg/bot
```

## Шаг 2. Запустить установщик

Одна команда сделает всё: поставит зависимости, спросит 3 настройки,
создаст базу и подготовит папки.

```bash
bash install.sh
```

Установщик спросит три значения:

1. **Токен бота** — берётся у [@BotFather](https://t.me/BotFather).
2. **Адрес API основного сервера (Yii2)** — например `http://46.62.246.156/api`
   (без слэша в конце).
3. **Публичный адрес бота** — например `https://bot.ваш-домен.uz`.

> Если что-то ввели неправильно — просто откройте файл `.env` и поправьте вручную.

## Шаг 3. Настроить веб-сервер

Корень сайта должен указывать на папку **`bot/web`** (именно `web`, не корень проекта).

### Вариант A — Nginx (рекомендуется)

1. Откройте готовый пример: `deploy/nginx.conf.example`.
2. Замените в нём `bot.ваш-домен.uz`, путь `/путь/к/bot` и адрес API на свои.
3. Скопируйте и включите:
   ```bash
   sudo cp deploy/nginx.conf.example /etc/nginx/sites-available/bot.conf
   sudo ln -s /etc/nginx/sites-available/bot.conf /etc/nginx/sites-enabled/
   sudo nginx -t && sudo systemctl reload nginx
   ```
4. Выдайте бесплатный HTTPS-сертификат:
   ```bash
   sudo apt install -y certbot python3-certbot-nginx
   sudo certbot --nginx -d bot.ваш-домен.uz
   ```

### Вариант B — Apache

Укажите `DocumentRoot` на папку `bot/web` — там уже лежит `.htaccess`
с нужными правилами (требуется включённый `mod_rewrite`).
Для проксирования `/api/` на Yii2 настройте `mod_proxy` либо укажите в Mini App
прямой адрес API основного сервера.

## Шаг 4. Зарегистрировать webhook Telegram

После того как сайт открывается по https:
```bash
php set-webhook.php
```
Должно вывести: `✅ Webhook зарегистрирован`.

## Готово 🎉

Откройте бота в Telegram и отправьте `/start`.

---

## Обновление до новой версии

```bash
cd /var/www/tg
git pull
cd bot
composer install --no-dev --optimize-autoloader
```
Файл `.env` и база `data/bot.sqlite` при обновлении **не трогаются**.

---

## Если что-то не работает

| Симптом | Причина и решение |
|---|---|
| `Database Exception (#14)` | Папка `data/` недоступна для записи. Выполните: `chmod -R 775 data runtime` и убедитесь, что владелец — пользователь веб-сервера (`www-data`). |
| Бот не отвечает на `/start` | Webhook не зарегистрирован или домен без https. Запустите `php set-webhook.php` и проверьте, что сайт открывается по `https://`. |
| Mini App не грузит данные | Неверный `YII2_API_BASE_URL` в `.env` или не настроен блок `location /api/` в Nginx. |
| `PHP не найден` / нет расширений | Доустановите пакеты из раздела «Что должно быть на сервере». |
| Ошибки в логах | Смотрите `runtime/logs/app.log` и логи Nginx в `/var/log/nginx/`. |

---

## Важно про безопасность

- Файл `.env` содержит токен бота и секреты — он **не попадает в git**
  (добавлен в `.gitignore`). Никому его не передавайте.
- Если токен бота уже «засветился» в репозитории — отзовите его у @BotFather
  командой `/revoke` и впишите новый в `.env`.
