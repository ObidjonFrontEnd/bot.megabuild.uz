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

<!-- ══════════════════════════════════════════════════════════════════════════
     ЭКРАН 1 — Список предложений (карточки)
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="listScreen" class="screen hidden">
    <div class="page-header">
        <h2 id="requestTitle">Предложения</h2>
        <p id="requestMeta" class="meta-text"></p>
    </div>

    <div id="quotesListContainer"></div>
    <div id="listActionMsg" class="action-msg"></div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     ЭКРАН 2 — Детали одного предложения
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="detailScreen" class="screen hidden">
    <div class="detail-top-bar" onclick="showList()">
        <span class="back-arrow">←</span>
        <span>К списку предложений</span>
    </div>

    <div class="detail-card">
        <div class="detail-header">
            <div id="detailSupplierName" class="detail-supplier-name"></div>
            <div id="detailBadge" class="detail-badge-wrap"></div>
        </div>

        <div class="detail-info-grid">
            <div class="detail-info-row">
                <span class="detail-label">Поставщик</span>
                <span id="detailSupplier" class="detail-value"></span>
            </div>
            <div class="detail-info-row">
                <span class="detail-label">ИНН</span>
                <span id="detailInn" class="detail-value"></span>
            </div>
            <div class="detail-info-row">
                <span class="detail-label">Общая сумма</span>
                <span id="detailTotal" class="detail-value detail-price"></span>
            </div>
            <div class="detail-info-row">
                <span class="detail-label">Доставка</span>
                <span id="detailDeliveryPrice" class="detail-value"></span>
            </div>
            <div class="detail-info-row">
                <span class="detail-label">Срок поставки</span>
                <span id="detailDeliveryDays" class="detail-value"></span>
            </div>
            <div class="detail-info-row">
                <span class="detail-label">Комментарий</span>
                <span id="detailComment" class="detail-value"></span>
            </div>
            <div class="detail-info-row">
                <span class="detail-label">Снабженец</span>
                <span id="detailUser" class="detail-value"></span>
            </div>
            <div class="detail-info-row">
                <span class="detail-label">Дата</span>
                <span id="detailDate" class="detail-value"></span>
            </div>
        </div>
    </div>

    <!-- Материалы предложения -->
    <div class="detail-card">
        <div class="detail-section-title">Материалы</div>
        <div id="detailMaterials"></div>
    </div>

    <!-- Кнопки действий (только для finance_manager / director) -->
    <div id="detailActions" class="detail-actions hidden"></div>

    <div id="detailActionMsg" class="action-msg"></div>
</div>

<style>
    /* ── Список предложений ─────────────────────────────────────────────── */
    .quotes-list { padding: 8px 16px 16px; }

    .quote-card {
        background: var(--tg-sec-bg);
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: transform .1s;
        position: relative;
        overflow: hidden;
    }
    .quote-card:active { transform: scale(.98); }
    .quote-card.is-winner { border-left: 4px solid #4caf50; }
    .quote-card.is-rejected { opacity: .65; border-left: 4px solid #f44336; }

    .quote-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 6px;
    }
    .quote-card-supplier {
        font-weight: 700;
        font-size: 15px;
        flex: 1;
        line-height: 1.3;
    }
    .quote-card-price {
        font-weight: 800;
        font-size: 16px;
        color: var(--tg-btn);
        white-space: nowrap;
    }

    .quote-card-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        color: var(--tg-hint);
        margin-top: 4px;
    }
    .quote-card-row .q-label { }
    .quote-card-row .q-value { font-weight: 500; color: var(--tg-text); }

    .quote-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid rgba(0,0,0,.06);
    }
    .quote-card-user {
        font-size: 12px;
        color: var(--tg-hint);
    }
    .quote-card-date {
        font-size: 12px;
        color: var(--tg-hint);
    }

    .q-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .q-badge-winner { background: #4caf50; color: #fff; }
    .q-badge-rejected { background: #f44336; color: #fff; }
    .q-badge-pending { background: #ffe0b2; color: #e65100; }

    .no-quotes-msg {
        text-align: center;
        padding: 48px 20px;
        color: var(--tg-hint);
        font-size: 15px;
    }

    .q-arrow {
        font-size: 18px;
        color: var(--tg-hint);
        margin-left: 4px;
    }

    /* ── Детали предложения ──────────────────────────────────────────────── */
    .detail-top-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        font-size: 15px;
        font-weight: 600;
        color: var(--tg-link);
        cursor: pointer;
        border-bottom: 1px solid var(--tg-sec-bg);
    }
    .detail-top-bar:active { opacity: .7; }
    .back-arrow { font-size: 20px; }

    .detail-card {
        margin: 12px 16px;
        background: var(--tg-sec-bg);
        border-radius: 12px;
        overflow: hidden;
    }

    .detail-header {
        padding: 14px 14px 0;
    }
    .detail-supplier-name {
        font-size: 18px;
        font-weight: 800;
        margin-bottom: 6px;
    }
    .detail-badge-wrap { margin-bottom: 4px; }

    .detail-info-grid {
        padding: 4px 0 8px;
    }
    .detail-info-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 7px 14px;
        border-bottom: 1px solid rgba(0,0,0,.04);
        gap: 12px;
    }
    .detail-info-row:last-child { border-bottom: none; }
    .detail-label {
        font-size: 13px;
        color: var(--tg-hint);
        flex-shrink: 0;
        min-width: 100px;
    }
    .detail-value {
        font-size: 14px;
        font-weight: 500;
        text-align: right;
        word-break: break-word;
    }
    .detail-price {
        font-size: 16px;
        font-weight: 800;
        color: var(--tg-btn);
    }

    .detail-section-title {
        padding: 12px 14px 8px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: var(--tg-hint);
    }

    /* Материалы */
    .mat-item {
        padding: 10px 14px;
        border-bottom: 1px solid rgba(0,0,0,.04);
    }
    .mat-item:last-child { border-bottom: none; }
    .mat-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 2px;
    }
    .mat-qty {
        font-size: 12px;
        color: var(--tg-hint);
        margin-bottom: 4px;
    }
    .mat-price-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
    }
    .mat-unit-price { color: var(--tg-hint); }
    .mat-total-price { font-weight: 700; color: var(--tg-btn); }

    .mat-status {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 8px;
        font-size: 10px;
        font-weight: 700;
        margin-top: 4px;
    }
    .mat-status-winner { background: #4caf50; color: #fff; }
    .mat-status-pending { background: #ffe0b2; color: #e65100; }

    .mat-no-data {
        padding: 20px 14px;
        text-align: center;
        color: var(--tg-hint);
        font-size: 13px;
    }

    /* Кнопки действий */
    .detail-actions {
        padding: 0 16px 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .detail-actions.hidden { display: none; }

    .btn-action {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: opacity .15s;
    }
    .btn-action:active { opacity: .85; }
    .btn-action:disabled { opacity: .5; cursor: not-allowed; }

    .btn-winner { background: #4caf50; color: #fff; }
    .btn-reject { background: #fdecea; color: #c0392b; }
    .btn-unset { background: var(--tg-sec-bg); color: var(--tg-text); border: 1.5px solid #e0e0e0; }

    .winner-notice {
        text-align: center;
        padding: 12px;
        background: #e8f5e9;
        color: #2e7d32;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
    }
</style>

<script>
    const REQUEST_ID = <?= (int)$requestId ?>;

    let authToken        = null;
    let userRole         = null;
    let quotes           = [];
    let requestMaterials = [];   // материалы заявки с quantity

    // ── Инициализация ───────────────────────────────────────────────────────
    async function init() {
        try {
            if (!REQUEST_ID) throw new Error('Не указан request_id');

            const sess = await getSession();
            authToken = sess.token;
            userRole  = sess.role;

            // Заявка — для заголовка
            const req = await api.get(`/request/get-one?request_id=${REQUEST_ID}`, authToken);
            document.getElementById('requestTitle').textContent =
                'Предложения — ' + (req.request_no ?? '#' + REQUEST_ID);
            document.getElementById('requestMeta').textContent =
                (req.project?.name ?? '—') + ' · Срок: ' + fmtDate(req.need_date);

            // Загружаем материалы заявки (нужны quantity для расчёта итога)
            const matRes = await api.get(
                `/request-materials/index?request_id=${REQUEST_ID}&size=100`, authToken);
            requestMaterials = matRes.items ?? matRes.data?.items ?? [];

            await loadQuotes();
            showScreen('listScreen');
        } catch (err) {
            showFatalError(err.message);
        }
    }

    // ── Загрузка предложений ────────────────────────────────────────────────
    async function loadQuotes() {
        const res = await api.get(`/request-quotes/index?request_id=${REQUEST_ID}&size=100`, authToken);
        quotes = res.items ?? res.data?.items ?? [];
        renderList();
    }

    /**
     * Группируем котировки по supplier_id + user_id.
     * Один поставщик, один снабженец = одна группа (одно предложение).
     * Возвращает массив групп: { key, supplierId, supplierName, supplierInn,
     *   userId, userName, totalPrice, deliveryDays, deliveryPrice, comment,
     *   createdAt, isWinner, isRejected, ids[] }
     */
    function groupQuotes(rawQuotes) {
        const map = new Map();
        rawQuotes.forEach(q => {
            const supplierId = q.supplier?.id ?? 0;
            const userId     = q.user?.id     ?? 0;
            const key        = `${supplierId}_${userId}`;

            if (!map.has(key)) {
                map.set(key, {
                    key,
                    supplierId,
                    supplierName: q.supplier?.name ?? '—',
                    supplierInn:  q.supplier?.inn  ?? null,
                    userId,
                    userName:     q.user?.full_name ?? '—',
                    totalPrice:   0,
                    deliveryDays:  q.delivery_days  ?? null,
                    deliveryPrice: q.delivery_price ?? null,
                    comment:       q.comment        ?? null,
                    createdAt:     q.created_at     ?? '',
                    isWinner:   false,
                    isRejected: false,
                    ids: [],          // все quote.id этой группы
                    rawQuotes: [],    // сырые объекты для деталей
                });
            }

            const g = map.get(key);
            g.totalPrice += parseFloat(q.total_price ?? 0);
            g.ids.push(q.id);
            g.rawQuotes.push(q);

            // Группа — победитель если хотя бы одна запись is_winner
            if (q.is_winner)          g.isWinner   = true;
            if (q.is_lowest_rejected) g.isRejected = true;
        });
        // Победители первые
        return [...map.values()].sort((a, b) => b.isWinner - a.isWinner);
    }

    let groups = [];   // сгруппированные предложения

    // ── ЭКРАН 1: Список предложений ─────────────────────────────────────────
    function renderList() {
        const container = document.getElementById('quotesListContainer');
        groups = groupQuotes(quotes);

        if (!groups.length) {
            container.innerHTML = '<div class="no-quotes-msg">Предложений пока нет</div>';
            return;
        }

        let html = '<div class="quotes-list">';
        groups.forEach((g, idx) => {
            const supplierName = esc(g.supplierName);
            const totalPrice   = fmtMoney(g.totalPrice);
            const deliveryDays = g.deliveryDays  ? g.deliveryDays  + ' дн.'       : '—';
            const deliveryPrc  = g.deliveryPrice ? fmtMoney(g.deliveryPrice) + ' UZS' : 'бесплатно';
            const senderName   = esc(g.userName);

            let cardCls = 'quote-card';
            let badge   = '';
            if (g.isWinner) {
                cardCls += ' is-winner';
                badge    = '<span class="q-badge q-badge-winner">🏆 Победитель</span>';
            } else if (g.isRejected) {
                cardCls += ' is-rejected';
                badge    = '<span class="q-badge q-badge-rejected">✗ Отклонено</span>';
            }

            const matCount = g.ids.length;
            const matLabel = matCount > 1 ? `${matCount} материалов` : '1 материал';

            html += `
                <div class="${cardCls}" onclick="showDetail(${idx})">
                    ${badge}
                    <div class="quote-card-head">
                        <div class="quote-card-supplier">${supplierName}</div>
                        <div class="quote-card-price">${totalPrice} UZS</div>
                    </div>
                    <div class="quote-card-row">
                        <span class="q-label">Материалов</span>
                        <span class="q-value">${matLabel}</span>
                    </div>
                    <div class="quote-card-row">
                        <span class="q-label">Доставка</span>
                        <span class="q-value">${deliveryDays} · ${deliveryPrc}</span>
                    </div>
                    <div class="quote-card-footer">
                        <span class="quote-card-user">👤 ${senderName}</span>
                        <span class="quote-card-date">${esc(g.createdAt)}</span>
                    </div>
                </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // ── ЭКРАН 2: Детали группы предложений ─────────────────────────────────
    let currentGroup = null;

    async function showDetail(idx) {
        currentGroup = groups[idx];
        if (!currentGroup) return;

        const g = currentGroup;

        // Основные данные
        document.getElementById('detailSupplierName').textContent = g.supplierName;
        document.getElementById('detailSupplier').textContent     = g.supplierName;
        document.getElementById('detailInn').textContent          = g.supplierInn || '(не указан)';
        document.getElementById('detailTotal').textContent        = fmtMoney(g.totalPrice) + ' UZS';
        document.getElementById('detailDeliveryPrice').textContent =
            g.deliveryPrice ? fmtMoney(g.deliveryPrice) + ' UZS' : 'бесплатно';
        document.getElementById('detailDeliveryDays').textContent =
            g.deliveryDays ? g.deliveryDays + ' дн.' : '(не задано)';
        document.getElementById('detailComment').textContent = g.comment || '(не задано)';
        document.getElementById('detailUser').textContent    = g.userName;
        document.getElementById('detailDate').textContent    = g.createdAt;

        // Бейдж статуса
        let badgeHtml = '';
        if (g.isWinner) {
            badgeHtml = '<span class="q-badge q-badge-winner">🏆 Победитель</span>';
        } else if (g.isRejected) {
            badgeHtml = '<span class="q-badge q-badge-rejected">✗ Отклонено</span>';
        } else {
            badgeHtml = '<span class="q-badge q-badge-pending">⏳ На рассмотрении</span>';
        }
        document.getElementById('detailBadge').innerHTML = badgeHtml;

        // Загружаем материалы всех котировок группы
        await loadGroupMaterials(g);

        // Кнопки действий
        renderDetailActions(g);

        showScreen('detailScreen');
        window.scrollTo(0, 0);
    }

    /**
     * Для каждой rawQuote в группе подтягиваем unit_price через view,
     * а quantity берём из requestMaterials (заявки), матчим по resource.id.
     *
     * Наш бот создаёт одну quote на материал, поэтому:
     *   rawQuotes[i]  ↔  requestMaterials[i]  (матч по порядку/resource.id)
     */
    async function loadGroupMaterials(g) {
        const container = document.getElementById('detailMaterials');
        container.innerHTML = '<div class="mat-no-data">Загрузка...</div>';

        try {
            // Параллельно загружаем view для каждой котировки группы
            const views = await Promise.all(
                g.ids.map(qId =>
                    api.get(`/request-quotes/view?request_quotes_id=${qId}`, authToken)
                       .catch(() => null)
                )
            );

            // Собираем строки: по одной на rawQuote
            const rows = g.rawQuotes.map((raw, i) => {
                const viewRes    = views[i];
                const viewMats   = viewRes?.materials ?? viewRes?.data?.materials ?? [];
                const viewMat    = viewMats[0] ?? {};   // одна материальная котировка на quote

                // unit_price — из view (точнее), или из raw (если нет)
                const unitPrice  = parseFloat(viewMat.unit_price ?? raw.unit_price ?? 0);

                // Ищем quantity в requestMaterials — матч по resource.id
                const resourceId = viewMat.resource?.id ?? raw.resource?.id ?? null;
                let reqMat       = resourceId
                    ? requestMaterials.find(m => m.resource?.id === resourceId)
                    : requestMaterials[i] ?? null;   // fallback: по индексу

                const quantity  = parseFloat(reqMat?.quantity ?? 0) || null;
                const unit      = esc(reqMat?.unit?.name ?? viewMat.unit?.name ?? raw.unit?.name ?? '');
                const matName   = esc(reqMat?.resource?.name ?? viewMat.resource?.name ?? raw.resource?.name ?? '—');

                // Итог: если есть qty и unitPrice — считаем, иначе берём total_price из raw
                const total = (quantity && unitPrice)
                    ? unitPrice * quantity
                    : parseFloat(raw.total_price ?? 0);

                const isWinner = raw.is_winner || viewMat.is_winner;

                return { matName, quantity, unit, unitPrice, total, isWinner };
            });

            if (!rows.length) {
                container.innerHTML = '<div class="mat-no-data">Нет данных о материалах</div>';
                return;
            }

            let html = '';
            rows.forEach(r => {
                const winBadge = r.isWinner
                    ? '<span class="mat-status mat-status-winner">✓ Победитель</span>' : '';

                const qtyLine = r.quantity
                    ? `<div class="mat-qty">${r.quantity} ${r.unit}</div>`
                    : '';

                const calcLine = (r.quantity && r.unitPrice)
                    ? `<span class="mat-unit-price">${r.quantity} × ${fmtMoney(r.unitPrice)} UZS</span>`
                    : (r.unitPrice ? `<span class="mat-unit-price">${fmtMoney(r.unitPrice)} / ${r.unit || 'ед.'}</span>` : '');

                html += `
                    <div class="mat-item">
                        <div class="mat-name">${r.matName}</div>
                        ${qtyLine}
                        <div class="mat-price-row">
                            ${calcLine}
                            <span class="mat-total-price">${fmtMoney(r.total)} UZS</span>
                        </div>
                        ${winBadge ? `<div style="margin-top:4px">${winBadge}</div>` : ''}
                    </div>`;
            });

            container.innerHTML = html;
        } catch (err) {
            container.innerHTML = `<div class="mat-no-data">Ошибка загрузки: ${esc(err.message)}</div>`;
        }
    }

    function renderDetailActions(g) {
        const container = document.getElementById('detailActions');
        const canManage = (userRole === 'finance_manager' || userRole === 'director');

        if (!canManage) {
            container.classList.add('hidden');
            return;
        }

        // Проверяем: есть ли уже победитель среди ВСЕХ групп?
        const hasWinnerAlready = groups.some(gr => gr.isWinner);

        const firstId = g.ids[0];
        let html = '';

        if (g.isWinner) {
            html += '<div class="winner-notice">✅ Это предложение объявлено победителем.</div>';
            html += `<button class="btn-action btn-unset" onclick="unsetWinner(${firstId})">Изменить победителя</button>`;
        } else if (g.isRejected) {
            html += `<button class="btn-action btn-unset" onclick="unsetRejected(${firstId})">Снять отклонение</button>`;
        } else {
            // Кнопка "Объявить победителем" — только если победитель ещё не объявлен
            if (!hasWinnerAlready) {
                html += `<button class="btn-action btn-winner" onclick="setWinner(${JSON.stringify(g.ids)})">🏆 Объявить победителем</button>`;
            }
            // "Отклонить" доступна всегда (кроме уже победившего)
            html += `<button class="btn-action btn-reject" onclick="setRejected(${firstId})">❌ Отклонить</button>`;
        }

        if (!html) {
            container.classList.add('hidden');
            return;
        }

        container.innerHTML = html;
        container.classList.remove('hidden');
    }

    // ── Навигация ───────────────────────────────────────────────────────────
    function showList() {
        showScreen('listScreen');
        window.scrollTo(0, 0);
    }

    // ── Действия (победитель / отклонение) ──────────────────────────────────

    // ids — может быть числом (один ID) или массивом
    async function setWinner(ids) {
        if (!confirm('Назначить это предложение победителем?')) return;

        if (!Array.isArray(ids)) ids = [ids];

        // Сохраняем группу до перезагрузки
        const winnerGroup = currentGroup;

        let lastErr = null;
        for (const id of ids) {
            try {
                await api.post(`/request-quotes/set-winner?id=${id}`, {}, authToken);
            } catch (e) {
                lastErr = e;
            }
        }

        if (lastErr && ids.length === 1) {
            showActionMessage(lastErr.message, true);
            return;
        }

        showActionMessage('🏆 Победитель назначен!', false);
        await reloadAndRefreshDetail();

        // Отправляем уведомление победителю в Telegram
        if (winnerGroup) {
            await sendWinnerNotification(winnerGroup);
        }
    }

    async function unsetWinner(quoteId) {
        if (!confirm('Снять статус победителя?')) return;
        try {
            await api.post(`/request-quotes/unset-winner?id=${quoteId}`, {}, authToken);
            showActionMessage('Статус победителя снят.', false);
            await reloadAndRefreshDetail();
        } catch (e) {
            showActionMessage(e.message, true);
        }
    }

    async function setRejected(quoteId) {
        const reason = prompt('Причина отклонения:');
        if (reason === null) return;
        try {
            await api.post(`/request-quotes/set-lowest-rejected?id=${quoteId}`,
                { reason: reason || 'Не указана' }, authToken);
            showActionMessage('Предложение отклонено.', false);
            await reloadAndRefreshDetail();
        } catch (e) {
            showActionMessage(e.message, true);
        }
    }

    async function unsetRejected(quoteId) {
        if (!confirm('Снять статус отклонения?')) return;
        try {
            await api.post(`/request-quotes/unset-lowest-rejected?id=${quoteId}`, {}, authToken);
            showActionMessage('Отклонение снято.', false);
            await reloadAndRefreshDetail();
        } catch (e) {
            showActionMessage(e.message, true);
        }
    }

    // ── Уведомление победителю в Telegram ──────────────────────────────────
    async function sendWinnerNotification(g) {
        try {
            // Получаем номер заявки
            let requestNo = '#' + REQUEST_ID;
            try {
                const req = await api.get(`/request/get-one?request_id=${REQUEST_ID}`, authToken);
                requestNo = req.request_no ?? requestNo;
            } catch {}

            // Собираем материалы: матчим rawQuotes с requestMaterials по resource.id
            const materials = g.rawQuotes.map((raw, i) => {
                const resourceId = raw.resource?.id ?? null;
                const reqMat = resourceId
                    ? requestMaterials.find(m => m.resource?.id === resourceId)
                    : requestMaterials[i] ?? null;

                return {
                    name:       reqMat?.resource?.name ?? raw.resource?.name ?? '—',
                    qty:        reqMat?.quantity        ?? null,
                    unit:       reqMat?.unit?.name      ?? '',
                    unit_price: raw.unit_price          ?? null,
                    total:      parseFloat(raw.total_price ?? 0),
                };
            });

            await fetch('/mini-app/notify-winner', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    winner_user_id: g.userId,
                    winner_name:    g.userName,
                    request_no:     requestNo,
                    supplier_name:  g.supplierName,
                    total_price:    g.totalPrice,
                    currency:       'UZS',
                    materials,
                }),
            });
        } catch (e) {
            console.warn('notify-winner failed:', e);
        }
    }

    async function reloadAndRefreshDetail() {
        await loadQuotes();   // обновляем данные и перерисовываем список

        // Если открыт детальный экран — обновляем его
        if (currentGroup) {
            const key     = currentGroup.key;
            const updated = groups.find(g => g.key === key);
            if (updated) {
                await showDetail(groups.indexOf(updated));
            } else {
                showList();
            }
        }
    }

    function showActionMessage(text, isError) {
        // Показываем сообщение на обоих экранах
        ['listActionMsg', 'detailActionMsg'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = text;
            el.style.background = isError ? '#fdecea' : '#e8f5e9';
            el.style.color      = isError ? '#c0392b' : '#2e7d32';
            el.classList.add('visible');
            setTimeout(() => el.classList.remove('visible'), 4000);
        });
    }

    init();
</script>
