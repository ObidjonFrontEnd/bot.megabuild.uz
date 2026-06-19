<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($this->title ?? 'Тендерный бот') ?></title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        // Применяем тему Telegram до отрисовки (убираем мигание)
        (function () {
            const tg = window.Telegram?.WebApp;
            if (!tg) return;
            tg.ready();
            tg.expand();
            const p = tg.themeParams || {};
            const r = document.documentElement;
            if (p.bg_color)           r.style.setProperty('--tg-bg',       p.bg_color);
            if (p.text_color)         r.style.setProperty('--tg-text',     p.text_color);
            if (p.hint_color)         r.style.setProperty('--tg-hint',     p.hint_color);
            if (p.link_color)         r.style.setProperty('--tg-link',     p.link_color);
            if (p.button_color)       r.style.setProperty('--tg-btn',      p.button_color);
            if (p.button_text_color)  r.style.setProperty('--tg-btn-text', p.button_text_color);
            if (p.secondary_bg_color) r.style.setProperty('--tg-sec-bg',   p.secondary_bg_color);
        })();
    </script>
    <style>
        :root {
            --tg-bg:       #ffffff;
            --tg-text:     #000000;
            --tg-hint:     #999999;
            --tg-link:     #2481cc;
            --tg-btn:      #2481cc;
            --tg-btn-text: #ffffff;
            --tg-sec-bg:   #f1f1f1;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--tg-bg);
            color: var(--tg-text);
            min-height: 100vh;
        }

        /* ── Экраны ── */
        .screen { width: 100%; }
        .screen.hidden { display: none !important; }

        #loadingScreen {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            min-height: 60vh; gap: 16px;
            color: var(--tg-hint);
        }
        .spinner {
            width: 36px; height: 36px;
            border: 3px solid var(--tg-sec-bg);
            border-top-color: var(--tg-btn);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        #errorScreen, #successScreen {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            min-height: 60vh; padding: 32px 20px;
            gap: 12px; text-align: center;
        }
        .error-icon, .success-icon { font-size: 48px; }
        #errorScreen p, #successScreen p {
            color: var(--tg-hint); font-size: 14px;
        }
        #successScreen h3 { font-size: 20px; font-weight: 700; }

        /* ── Кнопки ── */
        .btn-primary {
            width: 100%; padding: 14px;
            background: var(--tg-btn); color: var(--tg-btn-text);
            border: none; border-radius: 10px;
            font-size: 16px; font-weight: 600;
            cursor: pointer; transition: opacity .15s;
        }
        .btn-primary:active { opacity: .85; }
        .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
        .btn-secondary {
            width: 100%; padding: 14px;
            background: transparent; color: var(--tg-hint);
            border: 1.5px solid var(--tg-hint); border-radius: 10px;
            font-size: 16px; font-weight: 600;
            cursor: pointer; transition: opacity .15s;
        }
        .btn-secondary:active { opacity: .75; }

        /* ── Логин ── */
        #loginScreen {
            display: flex; align-items: center;
            justify-content: center; min-height: 100vh;
        }
        .login-box {
            width: 100%; max-width: 400px;
            padding: 32px 20px;
        }
        .logo { font-size: 52px; text-align: center; margin-bottom: 12px; }
        h1 { text-align: center; font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .subtitle {
            text-align: center; color: var(--tg-hint);
            font-size: 14px; margin-bottom: 32px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 12px; font-weight: 600;
            color: var(--tg-hint); text-transform: uppercase;
            letter-spacing: .5px; margin-bottom: 6px;
        }
        .form-group input, input.field {
            width: 100%; padding: 13px 14px;
            border: 1.5px solid #e0e0e0; border-radius: 10px;
            font-size: 16px; background: var(--tg-sec-bg);
            color: var(--tg-text); outline: none;
            -webkit-appearance: none; transition: border-color .15s;
        }
        .form-group input:focus, input.field:focus {
            border-color: var(--tg-btn);
            background: var(--tg-bg);
        }
        .error-box {
            color: #c0392b; font-size: 13px; text-align: center;
            padding: 10px 14px; background: #fdecea;
            border-radius: 8px; margin-bottom: 14px; display: none;
        }
        .error-box.visible { display: block; }

        /* ── App страницы ── */
        .page-header {
            padding: 16px 16px 8px;
            border-bottom: 1px solid var(--tg-sec-bg);
            margin-bottom: 8px;
        }
        .page-header h2 { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
        .meta-text { font-size: 13px; color: var(--tg-hint); }

        .section {
            padding: 12px 16px;
            border-bottom: 1px solid var(--tg-sec-bg);
        }
        .section-label {
            display: block; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .6px;
            color: var(--tg-hint); margin-bottom: 8px;
        }

        /* ── Поставщик ── */
        .search-wrap { position: relative; }
        .search-wrap input {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid #e0e0e0; border-radius: 10px;
            font-size: 15px; background: var(--tg-sec-bg);
            color: var(--tg-text); outline: none; -webkit-appearance: none;
        }
        .search-wrap input:focus {
            border-color: var(--tg-btn); background: var(--tg-bg);
        }
        .dropdown {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0;
            background: var(--tg-bg); border: 1px solid #e0e0e0;
            border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,.12);
            z-index: 100; max-height: 220px; overflow-y: auto;
        }
        .dropdown.hidden { display: none; }
        .dd-item {
            padding: 10px 14px; font-size: 14px; cursor: pointer;
            border-bottom: 1px solid var(--tg-sec-bg);
        }
        .dd-item:last-child { border-bottom: none; }
        .dd-item:active { background: var(--tg-sec-bg); }
        .dd-create { color: var(--tg-link); font-weight: 600; }
        .dd-empty { color: var(--tg-hint); }
        .selected-badge {
            display: inline-block; margin-top: 8px;
            padding: 4px 10px; background: #e8f5e9; color: #2e7d32;
            border-radius: 20px; font-size: 13px; font-weight: 600;
        }

        /* ── Список своих предложений ── */
        .my-offer-card {
            background: var(--tg-sec-bg); border-radius: 10px;
            padding: 12px; margin-bottom: 8px;
        }
        .my-offer-card:last-child { margin-bottom: 0; }
        .my-offer-card.is-winner { border: 1.5px solid #4caf50; }
        .my-offer-head {
            display: flex; justify-content: space-between;
            align-items: center; gap: 8px; margin-bottom: 4px;
        }
        .my-offer-supplier { font-weight: 600; font-size: 15px; }
        .my-offer-price { font-weight: 700; font-size: 15px; color: var(--tg-btn); white-space: nowrap; }
        .my-offer-meta { font-size: 12px; color: var(--tg-hint); margin-bottom: 8px; }
        .my-offer-status { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .status-winner { background: #4caf50; color: #fff; }
        .status-rejected { background: #f44336; color: #fff; }
        .status-pending { background: #ffe0b2; color: #e65100; }

        /* ── Форма создания поставщика ── */
        .create-form {
            margin-top: 12px; padding: 14px;
            background: var(--tg-sec-bg); border-radius: 12px;
        }
        .create-form.hidden { display: none; }
        .create-form-btns { display: flex; gap: 8px; }
        .create-form-btns .btn-primary,
        .create-form-btns .btn-secondary { flex: 1; padding: 11px; }

        /* ── Материалы ── */
        .material-row {
            background: var(--tg-sec-bg); border-radius: 10px;
            padding: 12px; margin-bottom: 8px;
        }
        .material-row:last-child { margin-bottom: 0; }
        .material-name { font-weight: 600; font-size: 15px; margin-bottom: 2px; }
        .material-qty { font-size: 13px; color: var(--tg-hint); margin-bottom: 8px; }
        .price-row { display: flex; align-items: center; gap: 8px; }
        .price-input {
            flex: 1; padding: 8px 12px;
            border: 1.5px solid #e0e0e0; border-radius: 8px;
            font-size: 15px; background: var(--tg-bg);
            color: var(--tg-text); outline: none; -webkit-appearance: none;
        }
        .price-input:focus { border-color: var(--tg-btn); }
        .price-currency { font-size: 13px; color: var(--tg-hint); white-space: nowrap; }
        .material-total { margin-top: 6px; font-size: 13px; color: var(--tg-link); font-weight: 500; }

        .total-section { background: var(--tg-sec-bg); }
        .total-row { display: flex; justify-content: space-between; align-items: center; font-size: 16px; }
        .total-value { font-weight: 700; color: var(--tg-btn); font-size: 18px; }

        .btn-submit { margin: 16px; width: calc(100% - 32px); }

        /* ── Таблица сравнения ── */
        .compare-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 0 0 8px; }
        .compare-table { border-collapse: collapse; width: 100%; min-width: 360px; font-size: 13px; }
        .compare-table th {
            background: var(--tg-sec-bg); padding: 8px 10px; text-align: left;
            font-weight: 700; font-size: 12px; color: var(--tg-hint);
            text-transform: uppercase; letter-spacing: .4px;
            border-bottom: 1px solid #e0e0e0; white-space: nowrap;
        }
        .compare-table td {
            padding: 10px; border-bottom: 1px solid var(--tg-sec-bg); vertical-align: top;
        }
        .compare-table tr:last-child td { border-bottom: none; }
        .quote-row.is-winner { background: #f0fff4; }
        .quote-row.is-winner td:first-child { border-left: 3px solid #4caf50; }
        .quote-row.is-rejected { opacity: .6; }
        .badge {
            display: inline-block; padding: 2px 7px;
            border-radius: 10px; font-size: 11px; font-weight: 700;
        }
        .badge-winner { background: #4caf50; color: #fff; }
        .badge-rejected { background: #f44336; color: #fff; }
        .supplier-name { font-weight: 600; font-size: 14px; }
        .supplier-meta { font-size: 12px; color: var(--tg-hint); margin-top: 2px; }
        .price-big { font-weight: 700; font-size: 15px; }
        .price-sub { font-size: 12px; color: var(--tg-hint); }
        .action-btns { display: flex; flex-direction: column; gap: 4px; }
        .btn-sm {
            padding: 5px 10px; border-radius: 7px; border: none;
            font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;
        }
        .btn-win { background: var(--tg-btn); color: var(--tg-btn-text); }
        .btn-win:disabled { opacity: .4; cursor: not-allowed; }
        .btn-rej { background: #fdecea; color: #c0392b; }
        .btn-unset { background: var(--tg-sec-bg); color: var(--tg-text); }
        .no-quotes { text-align: center; padding: 40px 20px; color: var(--tg-hint); }

        .action-msg {
            margin: 0 16px 16px; padding: 10px 14px;
            border-radius: 8px; font-size: 13px;
            text-align: center; display: none;
        }
        .action-msg.visible { display: block; }
    </style>
    <script>
        // ── Утилиты (доступны до загрузки body) ─────────────────────────────────
        const tg = window.Telegram?.WebApp;

        function showScreen(id) {
            document.querySelectorAll('.screen').forEach(s => s.classList.add('hidden'));
            document.getElementById(id)?.classList.remove('hidden');
        }
        function showFatalError(msg) {
            const el = document.getElementById('errorMsg');
            if (el) el.textContent = msg;
            showScreen('errorScreen');
        }
        function fmtMoney(n) {
            return Math.round(n).toLocaleString('ru-RU');
        }
        function fmtDate(s) {
            return s ? new Date(s).toLocaleDateString('ru-RU') : '—';
        }
        function esc(s) {
            return String(s)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // Все запросы через nginx-прокси /api/ → Yii2 основного сервера
        async function apiFetch(method, path, body, token) {
            const headers = {
                'Content-Type': 'application/json',
                'ngrok-skip-browser-warning': '1', // отключает HTML-предупреждение ngrok
            };
            if (token) headers['Authorization'] = 'Bearer ' + token;
            const opts = { method, headers };
            if (body) opts.body = JSON.stringify(body);
            const res = await fetch('/api' + path, opts);
            let data = {};
            try { data = await res.json(); } catch {}
            if (res.status === 401) {
                clearSession();
                throw new Error('Сессия истекла. Войдите снова через бот.');
            }
            if (!res.ok) {
                const msg = data.message
                    || (data.errors ? Object.values(data.errors).flat().join(', ') : null)
                    || 'Ошибка ' + res.status;
                throw new Error(msg);
            }
            return data.data ?? data;
        }
        const api = {
            get:  (path, token)       => apiFetch('GET',  path, null, token),
            post: (path, body, token) => apiFetch('POST', path, body, token),
            put:  (path, body, token) => apiFetch('PUT',  path, body, token),
        };

        async function getSession() {
            // Сначала проверяем localStorage (после логина через Mini App)
            try {
                const cached = localStorage.getItem('tg_session');
                if (cached) {
                    const sess = JSON.parse(cached);
                    if (sess && sess.token) return { success: true, ...sess };
                }
            } catch {}

            // Fallback: запросить у бота по Telegram initData
            const initData = tg?.initData ?? '';
            if (!initData) throw new Error('Сессия не найдена. Войдите через кнопку «🔑 Войти» в боте.');
            const res  = await fetch('/bot-token?' + new URLSearchParams({ init_data: initData }), {
                headers: { 'ngrok-skip-browser-warning': '1' },
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Ошибка авторизации');
            // Кэшируем, чтобы не запрашивать каждый раз
            try { localStorage.setItem('tg_session', JSON.stringify(data)); } catch {}
            return data;
        }

        function clearSession() {
            try { localStorage.removeItem('tg_session'); } catch {}
        }
    </script>
</head>
<body>
<?= $content ?>
</body>
</html>
