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

    <!-- Уже отправленные предложения снабженца -->
    <div id="myOffersSection" class="section hidden">
        <label class="section-label">Ваши предложения</label>
        <div id="myOffersList"></div>
    </div>

    <div class="section">
        <label class="section-label" id="formLabel">Поставщик</label>
        <div class="search-wrap">
            <input type="text" id="supplierSearch"
                   placeholder="Введите название или ИНН..."
                   autocomplete="off" autocorrect="off" spellcheck="false">
            <div id="supplierDropdown" class="dropdown hidden"></div>
        </div>
        <div id="supplierSelected" class="selected-badge hidden"></div>

        <!-- Форма создания нового поставщика -->
        <div id="supplierCreateForm" class="create-form hidden">
            <div class="form-group">
                <label for="newSupplierName">Название поставщика</label>
                <input type="text" id="newSupplierName" class="field" placeholder="Название">
            </div>
            <div class="form-group">
                <label for="newSupplierInn">ИНН</label>
                <input type="text" id="newSupplierInn" class="field"
                       placeholder="ИНН" inputmode="numeric" autocomplete="off">
            </div>
            <div id="createSupplierError" class="error-box"></div>
            <div class="create-form-btns">
                <button type="button" class="btn-secondary" onclick="cancelCreateSupplier()">Отмена</button>
                <button type="button" class="btn-primary" onclick="submitCreateSupplier()">Создать</button>
            </div>
        </div>
    </div>

    <div class="section">
        <label class="section-label">Цены по материалам</label>
        <div id="materialsList"></div>
    </div>

    <div class="section total-section">
        <div class="total-row">
            <span>Итого:</span>
            <span id="totalSum" class="total-value">0 UZS</span>
        </div>
    </div>

    <div class="section">
        <div class="form-group">
            <label for="deliveryDays">Срок поставки (дней)</label>
            <input type="number" id="deliveryDays" class="field" min="0" placeholder="7">
        </div>
        <div class="form-group">
            <label for="deliveryPrice">Стоимость доставки (UZS)</label>
            <input type="number" id="deliveryPrice" class="field" min="0" placeholder="0">
        </div>
        <div class="form-group">
            <label for="offerComment">Комментарий (необязательно)</label>
            <input type="text" id="offerComment" class="field" placeholder="Примечание">
        </div>
    </div>

    <div id="submitError" class="error-box"></div>
    <button id="submitBtn" class="btn-primary btn-submit" onclick="submitOffer()">
        Отправить предложение
    </button>
</div>

<div id="successScreen" class="screen hidden">
    <div class="success-icon">✅</div>
    <h3>Предложение отправлено!</h3>
    <p id="successMsg"></p>
    <button class="btn-primary" onclick="window.Telegram?.WebApp?.close()">Закрыть</button>
</div>

<script>
    const REQUEST_ID = <?= (int)$requestId ?>;

    let authToken          = null;
    let currentUserId      = null;
    let currentTgId        = null;
    let materials          = [];
    let selectedSupplierId = null;
    let selectedSupplier   = null;   // полный объект поставщика
    let searchTimer        = null;

    async function init() {
        try {
            if (!REQUEST_ID) throw new Error('Не указан request_id');

            const sess = await getSession();
            if (sess.role !== 'supplier') throw new Error('Доступ только для снабженцев');
            authToken     = sess.token;
            currentUserId = sess.user_id;
            // tg_id нужен для уведомления в чат
            currentTgId   = tg?.initDataUnsafe?.user?.id ?? null;

            const req = await api.get(`/request/get-one?request_id=${REQUEST_ID}`, authToken);
            document.getElementById('requestTitle').textContent =
                'Заявка ' + (req.request_no ?? '#' + REQUEST_ID);
            document.getElementById('requestMeta').textContent =
                (req.project?.name ?? '—') + ' · Срок: ' + fmtDate(req.need_date);

            const matRes  = await api.get(`/request-materials/index?request_id=${REQUEST_ID}&size=100`, authToken);
            materials = matRes.items ?? matRes.data?.items ?? [];
            if (!materials.length) throw new Error('В заявке нет материалов');

            renderMaterials();
            await loadMyOffers();
            showScreen('mainScreen');
        } catch (err) {
            showFatalError(err.message);
        }
    }

    function renderMaterials() {
        const container = document.getElementById('materialsList');
        container.innerHTML = '';
        materials.forEach((mat, idx) => {
            const name = esc(mat.resource?.name ?? '—');
            const note = mat.note ? ` (${esc(mat.note)})` : '';
            const qty  = mat.quantity ?? '?';
            const unit = esc(mat.unit?.name ?? '');
            const div  = document.createElement('div');
            div.className = 'material-row';
            div.innerHTML = `
                <div class="material-name">${name}${note}</div>
                <div class="material-qty">Кол-во: ${qty} ${unit}</div>
                <div class="price-row">
                    <input type="number" id="price_${idx}" class="price-input"
                           min="0" placeholder="Цена за ${unit || 'ед.'}"
                           oninput="updateTotal()">
                    <span class="price-currency">UZS</span>
                </div>
                <div class="material-total" id="matTotal_${idx}">Сумма: —</div>`;
            container.appendChild(div);
        });
    }

    // ── Уже отправленные предложения снабженца ────────────────────────────────
    async function loadMyOffers() {
        const section = document.getElementById('myOffersSection');
        const list    = document.getElementById('myOffersList');
        try {
            const res = await api.get(`/request-quotes/index?request_id=${REQUEST_ID}&size=100`, authToken);
            const all = res.items ?? res.data?.items ?? [];
            // Только свои предложения (по user.id)
            const mine = all.filter(q => (q.user?.id ?? null) === currentUserId);

            if (!mine.length) { section.classList.add('hidden'); return; }

            list.innerHTML = '';
            mine.forEach(q => {
                const supplierName = esc(q.supplier?.name ?? '—');
                const inn          = q.supplier?.inn ? ` · ИНН: ${esc(q.supplier.inn)}` : '';
                const totalPrice   = fmtMoney(q.total_price ?? 0);
                const created      = q.created_at ? esc(q.created_at) : '';

                let status = '';
                if (q.is_winner) {
                    status = '<span class="my-offer-status status-winner">🏆 Победитель</span>';
                } else if (q.is_lowest_rejected) {
                    status = '<span class="my-offer-status status-rejected">✗ Отклонено</span>';
                } else {
                    status = '<span class="my-offer-status status-pending">⏳ На рассмотрении</span>';
                }

                const card = document.createElement('div');
                card.className = 'my-offer-card' + (q.is_winner ? ' is-winner' : '');
                card.innerHTML = `
                    <div class="my-offer-head">
                        <span class="my-offer-supplier">${supplierName}</span>
                        <span class="my-offer-price">${totalPrice} UZS</span>
                    </div>
                    <div class="my-offer-meta">${inn ? inn.replace(/^ · /, '') : ''}${created ? ' · ' + created : ''}</div>
                    <div>${status}</div>`;
                list.appendChild(card);
            });

            section.classList.remove('hidden');
        } catch {
            section.classList.add('hidden');
        }
    }

    function updateTotal() {
        let total = 0;
        materials.forEach((mat, idx) => {
            const v     = parseFloat(document.getElementById(`price_${idx}`)?.value) || 0;
            const qty   = parseFloat(mat.quantity) || 0;
            const line  = v * qty;
            total      += line;
            const el    = document.getElementById(`matTotal_${idx}`);
            if (el) el.textContent = line > 0 ? 'Сумма: ' + fmtMoney(line) + ' UZS' : 'Сумма: —';
        });
        document.getElementById('totalSum').textContent = fmtMoney(total) + ' UZS';
    }

    // ── Поставщик ────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const inp = document.getElementById('supplierSearch');
        inp.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 2) { hideDropdown(); return; }
            searchTimer = setTimeout(() => searchSuppliers(q), 350);
        });
        inp.addEventListener('blur', () => setTimeout(hideDropdown, 200));
    });

    async function searchSuppliers(query) {
        try {
            const res   = await api.get(`/supplier/index?search=${encodeURIComponent(query)}&size=10`, authToken);
            const items = res.items ?? res.data?.items ?? [];
            renderDropdown(items, query);
        } catch { hideDropdown(); }
    }

    function renderDropdown(items, query) {
        const dd = document.getElementById('supplierDropdown');
        dd.innerHTML = '';
        if (!items.length) {
            const el = document.createElement('div');
            el.className = 'dd-item dd-empty';
            el.innerHTML = 'Не найдено — <b>создать нового?</b>';
            el.addEventListener('click', createSupplier);
            dd.appendChild(el);
        } else {
            items.forEach(s => {
                const el = document.createElement('div');
                el.className = 'dd-item';
                el.textContent = s.name + (s.inn ? ` (ИНН: ${s.inn})` : '');
                el.addEventListener('click', () => selectSupplier(s));
                dd.appendChild(el);
            });
            const cr = document.createElement('div');
            cr.className = 'dd-item dd-create';
            cr.textContent = '+ Создать нового поставщика';
            cr.addEventListener('click', createSupplier);
            dd.appendChild(cr);
        }
        dd.classList.remove('hidden');
    }

    function hideDropdown() {
        document.getElementById('supplierDropdown').classList.add('hidden');
    }

    function selectSupplier(s) {
        selectedSupplierId = s.id;
        selectedSupplier   = s;
        document.getElementById('supplierSearch').value = s.name;
        const badge = document.getElementById('supplierSelected');
        badge.textContent = '✓ ' + s.name + (s.inn ? ' (ИНН: ' + s.inn + ')' : '');
        badge.classList.remove('hidden');
        document.getElementById('supplierCreateForm').classList.add('hidden');
        hideDropdown();
    }

    // Показываем форму создания (название предзаполнено из поиска)
    function createSupplier() {
        hideDropdown();
        const typed = document.getElementById('supplierSearch').value.trim();
        document.getElementById('newSupplierName').value = typed;
        document.getElementById('newSupplierInn').value  = '';
        hideCreateError();
        document.getElementById('supplierCreateForm').classList.remove('hidden');
        document.getElementById('newSupplierInn').focus();
    }

    function cancelCreateSupplier() {
        document.getElementById('supplierCreateForm').classList.add('hidden');
        hideCreateError();
    }

    async function submitCreateSupplier() {
        hideCreateError();
        const name = document.getElementById('newSupplierName').value.trim();
        const inn  = document.getElementById('newSupplierInn').value.trim();
        if (!name) { showCreateError('Укажите название поставщика'); return; }
        if (!inn)  { showCreateError('Укажите ИНН'); return; }

        try {
            const data = await api.post('/supplier/create', { name, inn }, authToken);
            if (data.id) {
                cancelCreateSupplier();
                selectSupplier(data);
            } else {
                showCreateError('Не удалось создать поставщика');
            }
        } catch (err) {
            showCreateError(err.message);
        }
    }

    // ── Уведомление в Telegram после отправки предложения ────────────────────
    async function sendTgNotification(requestNo, sentMaterials, deliveryDays, comment, totalPrice) {
        if (!currentTgId) return; // нет tg_id — пропускаем
        try {
            await fetch('/mini-app/notify-offer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tg_id:         currentTgId,
                    request_no:    requestNo,
                    supplier_name: selectedSupplier?.name ?? document.getElementById('supplierSearch').value.trim(),
                    total_price:   totalPrice,
                    currency:      'UZS',
                    delivery_days: deliveryDays || null,
                    comment:       comment || null,
                    materials:     sentMaterials,
                }),
            });
        } catch (e) {
            // Уведомление некритично — не блокируем основной флоу
            console.warn('notify-offer failed:', e);
        }
    }

    function showCreateError(msg) {
        const el = document.getElementById('createSupplierError');
        el.textContent = msg;
        el.classList.add('visible');
    }
    function hideCreateError() {
        document.getElementById('createSupplierError').classList.remove('visible');
    }

    // ── Отправка ─────────────────────────────────────────────────────────────
    async function submitOffer() {
        hideSubmitError();
        if (!selectedSupplierId) { showSubmitError('Выберите или создайте поставщика'); return; }

        const prices = materials.map((_, idx) => {
            const v = parseFloat(document.getElementById(`price_${idx}`)?.value);
            return (isNaN(v) || v <= 0) ? null : v;
        });
        if (prices.includes(null)) { showSubmitError('Укажите цену для каждого материала'); return; }

        const deliveryDays  = parseInt(document.getElementById('deliveryDays').value)   || 0;
        const deliveryPrice = parseFloat(document.getElementById('deliveryPrice').value) || 0;
        const comment       = document.getElementById('offerComment').value.trim();

        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Отправляем...';

        let sent = 0;
        const errors       = [];
        const sentMaterials = [];   // для уведомления
        let   totalSum      = 0;

        for (let idx = 0; idx < materials.length; idx++) {
            const mat        = materials[idx];
            const unitPrice  = prices[idx];
            const qty        = parseFloat(mat.quantity) || 1;
            const totalPrice = unitPrice * qty;
            try {
                await api.post(`/request-quotes/create?request_id=${REQUEST_ID}`, {
                    supplier_id:    selectedSupplierId,
                    total_price:    totalPrice,
                    currency:       'UZS',
                    delivery_days:  deliveryDays,
                    delivery_price: deliveryPrice,
                    comment:        comment || null,
                    material_id:    mat.id,
                    unit_price:     unitPrice,
                    unit_id:        mat.unit?.id ?? null,
                }, authToken);
                sent++;
                totalSum += totalPrice;
                sentMaterials.push({
                    name:       mat.resource?.name ?? '—',
                    qty:        qty,
                    unit:       mat.unit?.name ?? '',
                    unit_price: unitPrice,
                    total:      totalPrice,
                });
            } catch (err) {
                errors.push(`${mat.resource?.name ?? 'материал'}: ${err.message}`);
            }
        }

        btn.disabled = false;
        btn.textContent = 'Отправить предложение';

        if (errors.length && !sent) { showSubmitError('Ошибки: ' + errors.join('; ')); return; }

        // Получаем номер заявки для уведомления
        let requestNo = '#' + REQUEST_ID;
        try {
            const req = await api.get(`/request/get-one?request_id=${REQUEST_ID}`, authToken);
            requestNo = req.request_no ?? requestNo;
        } catch {}

        // Отправляем уведомление в Telegram (некритично, не блокирует UI)
        await sendTgNotification(requestNo, sentMaterials, deliveryDays, comment, totalSum);

        let msg = `Отправлено ${sent} из ${materials.length} котировок.`;
        if (errors.length) msg += '\nЧастичные ошибки: ' + errors.join('; ');
        document.getElementById('successMsg').textContent = msg;
        showScreen('successScreen');
        setTimeout(() => tg?.close(), 3000);
    }

    function showSubmitError(msg) {
        const el = document.getElementById('submitError');
        el.textContent = msg;
        el.classList.add('visible');
    }
    function hideSubmitError() {
        document.getElementById('submitError').classList.remove('visible');
    }

    init();
</script>
