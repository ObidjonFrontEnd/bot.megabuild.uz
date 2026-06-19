<?php /** @var yii\web\View $this */ ?>
<div id="loginScreen" class="screen">
    <div class="login-box">
        <div class="logo">🏗️</div>
        <h1>Тендерный бот</h1>
        <p class="subtitle">Войдите в свой аккаунт</p>

        <div id="errorBox" class="error-box"></div>

        <div class="form-group">
            <label for="loginInput">Логин</label>
            <input type="text" id="loginInput" placeholder="Введите логин..." autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false">
        </div>
        <div class="form-group">
            <label for="passwordInput">Пароль</label>
            <input type="password" id="passwordInput" placeholder="Введите пароль..." autocomplete="current-password">
        </div>

        <button id="loginBtn" class="btn-primary" onclick="doLogin()">Войти</button>
    </div>
</div>

<div id="successScreen" class="screen hidden">
    <div class="success-icon">✅</div>
    <h3 id="welcomeName">Добро пожаловать!</h3>
    <p>Вы успешно вошли. Можете закрыть это окно.</p>
    <button class="btn-primary" onclick="window.Telegram?.WebApp?.close()">Закрыть</button>
</div>

<script>
    async function doLogin() {
        const login    = document.getElementById('loginInput').value.trim();
        const password = document.getElementById('passwordInput').value;
        if (!login || !password) { showErr('Введите логин и пароль'); return; }

        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.textContent = 'Входим...';
        hideErr();

        try {
            const initData  = tg?.initData ?? '';
            // tg_id берём из URL (бот вставляет его при создании кнопки)
            const urlParams = new URLSearchParams(window.location.search);
            const tgId      = parseInt(urlParams.get('tg_id') ?? '0') || (tg?.initDataUnsafe?.user?.id ?? 0);
            const res  = await fetch('/bot-token/link', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': '1' },
                body:    JSON.stringify({ init_data: initData, tg_id: tgId, login, password }),
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Неверный логин или пароль');

            // Сохраняем сессию в localStorage
            try {
                localStorage.setItem('tg_session', JSON.stringify({
                    token:     data.token,
                    role:      data.role,
                    user_id:   data.user_id,
                    full_name: data.full_name,
                }));
            } catch {}

            document.getElementById('welcomeName').textContent =
                'Добро пожаловать, ' + (data.full_name ?? '') + '!';
            showScreen('successScreen');
            setTimeout(() => tg?.close(), 1500);
        } catch (err) {
            showErr(err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Войти';
        }
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Enter') doLogin();
    });

    function showErr(msg) {
        const el = document.getElementById('errorBox');
        el.textContent = msg;
        el.classList.add('visible');
    }
    function hideErr() {
        document.getElementById('errorBox').classList.remove('visible');
    }

    // Страница логина всегда показывает форму.
    // Кэш localStorage здесь не читаем — если бот отправил эту кнопку, значит сессия невалидна.
</script>
