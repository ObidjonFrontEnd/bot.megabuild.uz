<?php /** @var yii\web\View $this */ /** @var int $requestId */ ?>
<div id="loadingScreen" class="screen">
    <div class="spinner"></div>
    <p>Загрузка...</p>
</div>

<div id="errorScreen" class="screen hidden">
    <div class="error-icon">⚠️</div>
    <p id="errorMsg"></p>
    <button class="btn-primary" onclick="window.Telegram?.WebApp?.close()">Закрыть</button>
</div>

<div id="mainScreen" class="screen hidden">
    <div class="page-header">
        <h2 id="requestTitle">Заявка</h2>
        <p id="requestMeta" class="meta-text"></p>
    </div>
    <div id="quotesContainer"></div>
    <div id="actionMsg" class="action-msg"></div>
</div>

<script>
    const REQUEST_ID = <?= (int)$requestId ?>;

    let authToken = null;
    let userRole  = null;
    let quotes    = [];

    async function init() {
        try {
            if (!REQUEST_ID) throw new Error('Не указан request_id');

            const sess = await getSession();
            authToken = sess.token;
            userRole  = sess.role;

            const req = await api.get(`/request/get-one?request_id=${REQUEST_ID}`, authToken);
            document.getElementById('requestTitle').textContent =
                'Заявка ' + (req.request_no ?? '#' + REQUEST_ID);
            document.getElementById('requestMeta').textContent =
                (req.project?.name ?? '—') + ' · Срок: ' + fmtDate(req.need_date);

            await loadQuotes();
            showScreen('mainScreen');
        } catch (err) {
            showFatalError(err.message);
        }
    }

    async function loadQuotes() {
        const res = await api.get(`/request-quotes/index?request_id=${REQUEST_ID}&size=100`, authToken);
        quotes    = res.items ?? res.data?.items ?? [];
        renderQuotes();
    }

    function renderQuotes() {
        const container = document.getElementById('quotesContainer');
        if (!quotes.length) {
            container.innerHTML = '<div class="no-quotes">Предложений пока нет</div>';
            return;
        }

        const isManager = userRole === 'finance_manager';
        let rows = '';
        quotes.forEach(q => {
            const cls          = 'quote-row' + (q.is_winner ? ' is-winner' : '') + (q.is_lowest_rejected ? ' is-rejected' : '');
            const supplierName = esc(q.supplier?.name ?? '—');
            const inn          = q.supplier?.inn ? `<div class="supplier-meta">ИНН: ${esc(q.supplier.inn)}</div>` : '';
            const totalPrice   = fmtMoney(q.total_price ?? 0);
            const deliveryDays = q.delivery_days ? `${q.delivery_days} дн.` : '—';
            const deliveryPriceStr = q.delivery_price ? fmtMoney(q.delivery_price) + ' UZS' : 'бесплатно';
            const senderName   = esc(q.user?.full_name ?? '—');
            const comment      = q.comment ? `<div class="price-sub">${esc(q.comment)}</div>` : '';

            let badge = '';
            if (q.is_winner)          badge = '<span class="badge badge-winner">🏆 Победитель</span> ';
            else if (q.is_lowest_rejected) badge = '<span class="badge badge-rejected">✗ Откл.</span> ';

            let actions = '';
            if (isManager) {
                if (q.is_winner) {
                    actions = `<div class="action-btns">
                        <button class="btn-sm btn-unset" onclick="unsetWinner(${q.id})">Снять победителя</button>
                    </div>`;
                } else if (q.is_lowest_rejected) {
                    actions = `<div class="action-btns">
                        <button class="btn-sm btn-unset" onclick="unsetRejected(${q.id})">Снять отклонение</button>
                    </div>`;
                } else {
                    actions = `<div class="action-btns">
                        <button class="btn-sm btn-win" onclick="setWinner(${q.id})">🏆 Победитель</button>
                        <button class="btn-sm btn-rej" onclick="setRejected(${q.id})">✗ Откл.</button>
                    </div>`;
                }
            }

            rows += `<tr class="${cls}" data-id="${q.id}">
                <td>${badge}<div class="supplier-name">${supplierName}</div>${inn}</td>
                <td><div class="price-big">${totalPrice} UZS</div>${comment}</td>
                <td><div>${deliveryDays}</div><div class="price-sub">${deliveryPriceStr}</div></td>
                <td><div class="supplier-meta">${senderName}</div></td>
                ${isManager ? `<td>${actions}</td>` : ''}
            </tr>`;
        });

        container.innerHTML = `
            <div class="section compare-wrap">
                <table class="compare-table">
                    <thead><tr>
                        <th>Поставщик</th><th>Сумма</th><th>Доставка</th><th>Снабженец</th>
                        ${isManager ? '<th>Действие</th>' : ''}
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }

    async function setWinner(quoteId) {
        if (!confirm('Назначить победителем?')) return;
        await doAction(`/request-quotes/set-winner?id=${quoteId}`, 'POST', null, '🏆 Победитель назначен!');
    }
    async function unsetWinner(quoteId) {
        if (!confirm('Снять статус победителя?')) return;
        await doAction(`/request-quotes/unset-winner?id=${quoteId}`, 'POST', null, 'Статус победителя снят.');
    }
    async function setRejected(quoteId) {
        const reason = prompt('Причина отклонения:');
        if (reason === null) return;
        await doAction(`/request-quotes/set-lowest-rejected?id=${quoteId}`, 'POST',
            { reason: reason || 'Не указана' }, 'Предложение отклонено.');
    }
    async function unsetRejected(quoteId) {
        await doAction(`/request-quotes/unset-lowest-rejected?id=${quoteId}`, 'POST', null, 'Отклонение снято.');
    }

    async function doAction(path, method, body, successMsg) {
        try {
            if (method === 'POST') await api.post(path, body ?? {}, authToken);
            showActionMsg(successMsg, false);
            await loadQuotes();
        } catch (err) {
            showActionMsg(err.message, true);
        }
    }

    function showActionMsg(text, isError) {
        const el = document.getElementById('actionMsg');
        el.textContent = text;
        el.style.background = isError ? '#fdecea' : '#e8f5e9';
        el.style.color      = isError ? '#c0392b' : '#2e7d32';
        el.classList.add('visible');
        setTimeout(() => el.classList.remove('visible'), 4000);
    }

    init();
</script>
