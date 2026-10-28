/*
 * Glamora · Punto de venta
 *
 * El carrito vive en el navegador, pero los totales, descuentos de membresía,
 * sesiones incluidas (membresía o paquete), combos, canje de puntos y saldo a
 * favor los calcula el servidor (endpoint `quote`, el mismo CheckoutService
 * que usa el cobro). Así lo que ve la cajera y lo que se registra nunca difieren.
 */
(function () {
    'use strict';

    const C = window.POS_CONFIG;
    if (!C) return;

    const $ = (id) => document.getElementById(id);
    const KIND_ICON = { service: 'bi-scissors', product: 'bi-box', membership: 'bi-award', combo: 'bi-collection', package: 'bi-ticket-perforated' };
    const KIND_LABEL = { product: 'Producto', membership: 'Membresía', combo: 'Combo', package: 'Paquete' };
    // Se venden de a uno por venta (se activan una sola vez para el cliente).
    const SINGLE = ['membership', 'package'];
    // Tienen tope de stock: productos y combos con productos.
    const limited = (it) => it.stock !== null && it.stock !== undefined;

    // ------------------------------------------------------------------
    //  Estado
    // ------------------------------------------------------------------
    const state = {
        lines: [],              // { key, kind, id, name, price, qty, discount, personal_id, stock, showDisc }
        client: null,           // resumen del servidor (membresía y puntos)
        clientName: '',
        principal: C.defaultPrincipal ? String(C.defaultPrincipal) : '',
        payment: null,
        cashSession: '',
        branch: '',
        notes: '',
        globalDiscount: 0,
        showGlobal: false,
        coupon: '',             // código de cupón escrito por la cajera
        redeem: 0,
        credit: 0,
        onCredit: false,        // venta a crédito: se cobra solo el anticipo
        downPayment: 0,
        dueDays: C.creditDays || 30,
        tip: 0,
        received: '',
        filter: 'all',
        search: '',
        version: 0,             // cambia con cada modificación del carrito
        quote: null,
        quoteVersion: -1,
        quoteError: null,
        submitting: false,
    };

    const byKey = {};
    C.catalog.forEach((it) => { it.key = it.kind + ':' + it.id; byKey[it.key] = it; });

    // ------------------------------------------------------------------
    //  Utilidades
    // ------------------------------------------------------------------
    const fmt = (n) => (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (n) => C.currency + ' ' + fmt(n);
    const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
    const isTyping = (el) => el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);

    function api(url, opts = {}) {
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': C.csrf, 'X-Requested-With': 'XMLHttpRequest' };
        const init = { method: opts.method || 'GET', headers, signal: opts.signal };
        if (opts.json) {
            headers['Content-Type'] = 'application/json';
            init.method = opts.method || 'POST';
            init.body = JSON.stringify(opts.json);
        }
        return fetch(url, init).then((r) => r.json().catch(() => ({})).then((data) => {
            if (!r.ok) {
                const first = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(first || data.message || 'Error ' + r.status);
            }
            return data;
        }));
    }

    const quoteFresh = () => state.quote && state.quoteVersion === state.version;

    // ------------------------------------------------------------------
    //  Catálogo
    // ------------------------------------------------------------------
    function chips() {
        const list = [{ id: 'all', label: 'Todos', count: C.catalog.length }];
        C.categories.forEach((c) => {
            const n = C.catalog.filter((it) => it.kind === 'service' && it.category_id === c.id).length;
            if (n) list.push({ id: 'cat:' + c.id, label: c.name, count: n });
        });
        const loose = C.catalog.filter((it) => it.kind === 'service' && !it.category_id).length;
        if (loose && list.length > 1) list.push({ id: 'cat:none', label: 'Otros servicios', count: loose });
        [['combo', 'Combos'], ['package', 'Paquetes'], ['product', 'Productos'], ['membership', 'Membresías']].forEach(([kind, label]) => {
            const n = C.catalog.filter((it) => it.kind === kind).length;
            if (n) list.push({ id: kind, label, count: n });
        });
        return list;
    }

    function renderChips() {
        $('pos-chips').innerHTML = chips().map((c) =>
            `<button type="button" class="pos-chip ${state.filter === c.id ? 'active' : ''}" data-chip="${c.id}">${esc(c.label)}<span class="count">${c.count}</span></button>`
        ).join('');
    }

    function visibleItems() {
        const words = norm(state.search).split(/\s+/).filter(Boolean);
        return C.catalog.filter((it) => {
            const f = state.filter;
            if (['product', 'membership', 'combo', 'package'].includes(f)) { if (it.kind !== f) return false; }
            else if (f === 'cat:none') { if (it.kind !== 'service' || it.category_id) return false; }
            else if (f.startsWith('cat:')) { if (it.kind !== 'service' || String(it.category_id) !== f.slice(4)) return false; }
            const name = norm(it.name);
            return words.every((w) => name.includes(w));
        });
    }

    const qtyInCart = (key) => state.lines.filter((l) => l.key === key).reduce((s, l) => s + l.qty, 0);

    function cardMeta(it) {
        if (it.kind === 'service') return it.duration ? it.duration + ' min' : 'Servicio';
        if (it.kind === 'product') return it.stock > 0 ? 'Stock ' + (+it.stock.toFixed(2)) : 'Sin stock';
        if (it.kind === 'combo') return esc(it.detail) + (limited(it) && it.stock <= 0 ? '<br>Sin stock' : '');
        return esc(it.duration) + (it.detail ? '<br>' + esc(it.detail) : '');
    }

    const listPrice = (it) => it.list && it.list > it.price + 0.005 ? `<span class="pos-card-list">${money(it.list)}</span>` : '';

    function renderCatalog() {
        const items = visibleItems();
        $('pos-items').innerHTML = items.map((it) => {
            const inCart = qtyInCart(it.key);
            const soldOut = limited(it) && inCart >= it.stock;
            return `<button type="button" class="pos-card kind-${it.kind}" data-key="${it.key}" ${soldOut ? 'disabled' : ''}
                        title="${esc(it.name)}">
                <span class="pos-card-icon"><i class="bi ${KIND_ICON[it.kind]}"></i>${KIND_LABEL[it.kind] && it.kind !== 'product' ? ' ' + KIND_LABEL[it.kind] : ''}</span>
                <span class="pos-card-name">${esc(it.name)}</span>
                <span class="pos-card-meta">${cardMeta(it)}</span>
                <span class="pos-card-price">${money(it.price)}${listPrice(it)}</span>
                ${inCart ? `<span class="pos-card-qty">×${+inCart.toFixed(2)}</span>` : ''}
            </button>`;
        }).join('');
        $('pos-items-empty').classList.toggle('d-none', items.length > 0);
    }

    /**
     * Actualiza en su lugar la cantidad en carrito y el "agotado" de cada
     * tarjeta, sin reconstruirlas: así no se pierde el foco de teclado ni parpadea.
     */
    function updateCatalogBadges() {
        document.querySelectorAll('#pos-items .pos-card').forEach((card) => {
            const it = byKey[card.dataset.key];
            const inCart = qtyInCart(it.key);
            card.disabled = limited(it) && inCart >= it.stock;

            let badge = card.querySelector('.pos-card-qty');
            if (inCart && !badge) {
                badge = document.createElement('span');
                badge.className = 'pos-card-qty';
                card.appendChild(badge);
            }
            if (badge) {
                if (inCart) badge.textContent = '×' + (+inCart.toFixed(2));
                else badge.remove();
            }
        });
    }

    function addItem(key) {
        const it = byKey[key];
        if (!it) return;

        const existing = state.lines.find((l) => l.key === key);
        if (existing) {
            if (SINGLE.includes(it.kind)) { flash(key); return; } // una por venta
            if (limited(it) && existing.qty + 1 > it.stock) { flash(key); return; }
            existing.qty += 1;
        } else {
            if (limited(it) && it.stock <= 0) return;
            state.lines.push({
                key, kind: it.kind, id: it.id, name: it.name, price: it.price,
                qty: 1, discount: 0, personal_id: '', stock: it.stock, showDisc: false,
            });
        }

        renderLines();
        updateCatalogBadges();
        flash(key);
        changed();
    }

    function flash(key) {
        const el = document.querySelector(`.pos-card[data-key="${key}"]`);
        if (!el) return;
        el.classList.remove('flash');
        void el.offsetWidth; // reinicia la animación
        el.classList.add('flash');
    }

    // ------------------------------------------------------------------
    //  Líneas
    // ------------------------------------------------------------------
    const specialistOptions = (selected) =>
        '<option value="">— Principal —</option>' +
        C.specialists.map((s) => `<option value="${s.id}" ${String(selected) === String(s.id) ? 'selected' : ''}>${esc(s.name)}</option>`).join('');

    function renderLines() {
        $('pos-lines').innerHTML = state.lines.map((l, i) => {
            // Membresía y paquete: uno por venta y sin profesional (no comisionan al venderse).
            const isMember = SINGLE.includes(l.kind);
            const maxed = limited(l) && l.qty >= l.stock;
            return `<div class="pos-line" data-i="${i}">
                <div>
                    <div class="pos-line-name">${esc(l.name)}
                        ${KIND_LABEL[l.kind] ? `<span class="pos-badge ${l.kind}">${KIND_LABEL[l.kind]}</span>` : ''}
                        <span class="pos-badge benefit d-none" data-benefit></span>
                    </div>
                    <div class="pos-line-meta">
                        ${isMember ? '' : `<select class="form-select" data-field="personal_id" title="Atendido por">${specialistOptions(l.personal_id)}</select>`}
                        <input type="number" class="form-control price no-uppercase" data-field="price" min="0" step="0.5" value="${l.price}" title="Precio unitario">
                        ${l.showDisc || l.discount > 0
                            ? `<input type="number" class="form-control disc no-uppercase" data-field="discount" min="0" step="0.5" value="${l.discount || ''}" placeholder="Desc." title="Descuento de la línea">`
                            : '<button type="button" class="pos-link" data-act="disc">+ desc.</button>'}
                    </div>
                </div>
                <div>${isMember ? '<span class="small text-muted">1</span>' : `<div class="pos-stepper">
                    <button type="button" data-act="minus" aria-label="Quitar uno">−</button>
                    <span>${+l.qty.toFixed(2)}</span>
                    <button type="button" data-act="plus" ${maxed ? 'disabled' : ''} aria-label="Agregar uno">+</button>
                </div>`}</div>
                <div class="pos-line-amount">
                    <span class="was d-none" data-was></span>
                    <span class="now" data-now></span>
                    <button type="button" class="pos-line-remove" data-act="remove" title="Quitar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>`;
        }).join('');

        $('pos-lines-empty').classList.toggle('d-none', state.lines.length > 0);
        $('pos-clear').classList.toggle('d-none', state.lines.length === 0);
        updateLineAmounts();
    }

    /** Montos e insignias por línea: del servidor si está al día, si no un cálculo simple. */
    function updateLineAmounts() {
        const fresh = quoteFresh();
        document.querySelectorAll('#pos-lines .pos-line').forEach((row) => {
            const i = +row.dataset.i;
            const l = state.lines[i];
            if (!l) return;
            const q = fresh ? state.quote.lines[i] : null;
            const gross = l.qty * l.price;
            const total = q ? q.total : Math.max(0, gross - l.discount);

            row.querySelector('[data-now]').textContent = money(total);
            const was = row.querySelector('[data-was]');
            was.textContent = money(gross);
            was.classList.toggle('d-none', Math.abs(gross - total) < 0.005);

            const badge = row.querySelector('[data-benefit]');
            badge.textContent = q && q.badge ? q.badge : '';
            badge.classList.toggle('d-none', !(q && q.badge));
        });
    }

    function onLinesClick(e) {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const i = +btn.closest('.pos-line').dataset.i;
        const l = state.lines[i];

        switch (btn.dataset.act) {
            case 'plus': if (!(limited(l) && l.qty + 1 > l.stock)) l.qty += 1; break;
            case 'minus': l.qty -= 1; if (l.qty <= 0) state.lines.splice(i, 1); break;
            case 'remove': state.lines.splice(i, 1); break;
            case 'disc': l.showDisc = true; break;
        }

        renderLines();
        updateCatalogBadges();
        if (btn.dataset.act === 'disc') {
            document.querySelector(`.pos-line[data-i="${i}"] [data-field="discount"]`)?.focus();
            return;
        }
        changed();
    }

    function onLinesInput(e) {
        const field = e.target.dataset.field;
        if (!field) return;
        const l = state.lines[+e.target.closest('.pos-line').dataset.i];
        if (field === 'personal_id') l.personal_id = e.target.value;
        if (field === 'price') l.price = Math.max(0, num(e.target.value));
        if (field === 'discount') l.discount = Math.max(0, num(e.target.value));
        changed();
    }

    // ------------------------------------------------------------------
    //  Cliente
    // ------------------------------------------------------------------
    let clientResults = [];
    let clientActive = -1;

    const searchClients = debounce((term) => {
        api(C.routes.clients + '?q=' + encodeURIComponent(term))
            .then((list) => { clientResults = list; clientActive = list.length ? 0 : -1; renderClientResults(term); })
            .catch(() => {});
    }, 200);

    function renderClientResults(term) {
        const box = $('pos-client-results');
        if (!term) { box.classList.add('d-none'); return; }
        box.innerHTML = clientResults.length
            ? clientResults.map((c, i) => `<button type="button" class="${i === clientActive ? 'active' : ''}" data-client="${c.id}">
                    <div class="fw-semibold">${esc(c.name)}</div>
                    <div class="muted">${[c.phone, c.document].filter(Boolean).map(esc).join(' · ') || 'Sin teléfono'}</div>
                </button>`).join('')
            : `<div class="p-2 small text-muted">Sin coincidencias.${C.canCreateClient ? ' Usa <strong>Nuevo</strong> para registrarlo.' : ''}</div>`;
        box.classList.remove('d-none');
    }

    function selectClient(id) {
        $('pos-client-results').classList.add('d-none');
        return api(C.routes.client + '/' + id).then(setClient).catch((err) => alertInline(err.message));
    }

    function setClient(data) {
        state.client = data;
        state.redeem = 0;
        state.credit = 0;
        $('pos-client-search').value = '';
        renderClient();
        changed();
    }

    function clearClient() {
        state.client = null;
        state.redeem = 0;
        state.credit = 0;
        renderClient();
        changed();
        $('pos-client-search').focus();
    }

    function renderClient() {
        const c = state.client;
        $('pos-client-pick').classList.toggle('d-none', !!c);
        $('pos-client-card').classList.toggle('d-none', !c);
        if (!c) { renderPoints(); renderCredit(); return; }

        const m = c.membership;
        const packages = (c.packages || []).map((p) => `<div class="pos-pkg"><i class="bi bi-ticket-perforated"></i> <strong>${esc(p.name)}</strong> · ${esc(p.expiry)}
                <div>${p.items.filter((s) => s.remaining > 0).map((s) => `${esc(s.service)}: <strong>${s.remaining}</strong>/${s.included}`).join(' · ')}</div></div>`).join('');
        const sessions = m && m.sessions.length
            ? '<div class="mt-1">' + m.sessions.map((s) => `${esc(s.service)}: <strong>${s.remaining}</strong>/${s.included}`).join(' · ') + '</div>'
            : '';

        $('pos-client-card').innerHTML = `<div class="pos-client-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-semibold">${esc(c.name)}</div>
                    <div class="small text-muted">${[c.phone, c.document].filter(Boolean).map(esc).join(' · ') || 'Sin teléfono'}</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-act="change-client">Cambiar</button>
            </div>
            ${m ? `<div class="pos-member"><i class="bi bi-award"></i> <strong>${esc(m.plan)}</strong> · vence ${esc(m.ends_on)}
                    ${m.discount > 0 ? ` · ${m.discount} % en ${esc(m.scope.toLowerCase())}` : ''}${sessions}</div>` : ''}
            ${packages}
            ${c.credit > 0 ? `<div class="pos-credit-chip"><i class="bi bi-wallet2"></i> Saldo a favor: <strong>${money(c.credit)}</strong></div>` : ''}
            ${accountChip(c.account)}
        </div>`;

        renderPoints();
        renderCredit();
    }

    /** Cuenta corriente del cliente: lo que debe y lo que se le puede fiar. */
    function accountChip(a) {
        if (!a || (!a.enabled && a.debt <= 0)) return '';
        const cls = a.overdue ? 'text-danger' : 'text-muted';
        return `<div class="pos-account ${cls}"><i class="bi bi-journal-text"></i> ${a.debt > 0 ? `Debe <strong>${money(a.debt)}</strong>` : 'Sin deuda'}`
            + (a.enabled ? ` · puede fiar hasta ${money(a.available)}` : ' · sin crédito habilitado')
            + (a.overdue ? ' · <strong>tiene cuentas vencidas</strong>' : '') + '</div>';
    }

    // ------------------------------------------------------------------
    //  Venta a crédito
    // ------------------------------------------------------------------
    function renderCreditSale() {
        const box = $('pos-credit-sale');
        if (!box) return;
        box.classList.toggle('d-none', !state.onCredit);
        if (!state.onCredit) return;

        const due = new Date();
        due.setDate(due.getDate() + (parseInt(state.dueDays, 10) || 0));
        const q = quoteFresh() ? state.quote : null;
        $('pos-credit-info').innerHTML = q
            ? `Queda a crédito <strong>${money(q.pending)}</strong> · vence el ${due.toLocaleDateString('es')}`
            : `Vence el ${due.toLocaleDateString('es')}`;
    }

    // ------------------------------------------------------------------
    //  Saldo a favor
    // ------------------------------------------------------------------
    function renderCredit() {
        const box = $('pos-credit');
        const balance = state.client ? Number(state.client.credit) || 0 : 0;
        box.classList.toggle('d-none', balance <= 0);
        if (balance <= 0) return;

        $('credit-info').innerHTML = `Disponible <strong>${money(balance)}</strong>`;
        const input = $('pos-credit-input');
        if (document.activeElement !== input) input.value = state.credit || '';
    }

    /** Lo máximo de saldo que se puede usar: el saldo o el total, lo menor. */
    function creditMax() {
        const balance = state.client ? Number(state.client.credit) || 0 : 0;
        const total = quoteFresh() ? state.quote.total : naiveTotals().total;
        return Math.max(0, Math.min(balance, total));
    }

    // ------------------------------------------------------------------
    //  Puntos
    // ------------------------------------------------------------------
    function renderPoints() {
        const box = $('pos-points');
        const p = state.client && state.client.points;
        if (!p || !p.enabled) { box.classList.add('d-none'); return; }
        box.classList.remove('d-none');

        if (!box.dataset.ready) {
            box.innerHTML = `<div class="d-flex justify-content-between align-items-center">
                    <span class="pos-block-title mb-0"><i class="bi bi-stars"></i> Puntos</span>
                    <span class="small" id="pts-info"></span>
                </div>
                <div class="d-flex gap-2 mt-2 align-items-center d-none" id="pts-redeem">
                    <input type="number" id="pos-redeem" class="form-control form-control-sm text-end no-uppercase" min="0" step="1" placeholder="Puntos a canjear">
                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="pts-max">Usar máximo</button>
                </div>
                <div class="form-text" id="pts-hint"></div>`;
            box.dataset.ready = '1';
        }

        $('pts-info').innerHTML = `<strong>${p.balance.toLocaleString('en-US')}</strong> pts <span class="text-muted">≈ ${money(p.value)}</span>`;
        const canRedeem = p.max_redeemable > 0;
        $('pts-redeem').classList.toggle('d-none', !canRedeem);
        const input = $('pos-redeem');
        if (document.activeElement !== input) input.value = state.redeem || '';
        input.max = p.max_redeemable;

        const earn = quoteFresh() ? state.quote.points_to_earn : 0;
        $('pts-hint').textContent = (canRedeem
            ? `Mínimo ${p.min_redeem} · hasta ${p.max_redeemable} en esta venta. `
            : p.balance > 0 ? `Se canjea desde ${p.min_redeem} puntos. ` : '')
            + (earn > 0 ? `Con esta compra gana ${earn} pts.` : '');
    }

    // ------------------------------------------------------------------
    //  Totales, pago y caja
    // ------------------------------------------------------------------
    function naiveTotals() {
        const subtotal = state.lines.reduce((s, l) => s + l.qty * l.price, 0);
        const disc = state.lines.reduce((s, l) => s + l.discount, 0) + state.globalDiscount;
        const total = Math.max(0, subtotal - disc);
        return { subtotal, line_discounts: disc - state.globalDiscount, global_discount: state.globalDiscount,
                 membership_discount: 0, package_discount: 0, loyalty_discount: 0, redeem_points: 0, tax: 0, total,
                 tip: state.tip, credit_applied: 0, charge: total + state.tip,
                 on_credit: state.onCredit, down_payment: state.downPayment, pending: 0,
                 charge_now: state.onCredit ? state.downPayment : total + state.tip };
    }

    function renderTotals() {
        const fresh = quoteFresh();
        const t = fresh ? state.quote : naiveTotals();
        const row = (label, value, cls = '') => `<div class="row-t ${cls}"><span>${label}</span><span>${value}</span></div>`;
        const discounts = t.line_discounts + t.global_discount;

        let html = row('Subtotal', money(t.subtotal));
        if (discounts > 0) html += row('Descuentos', '− ' + money(discounts));
        if (t.membership_discount > 0) html += row('<i class="bi bi-award"></i> Membresía', '− ' + money(t.membership_discount), 'benefit');
        if (t.package_discount > 0) html += row('<i class="bi bi-ticket-perforated"></i> Sesiones de paquete', '− ' + money(t.package_discount), 'benefit');
        if (t.promotion_discount > 0) html += row('<i class="bi bi-percent"></i> Promociones', '− ' + money(t.promotion_discount), 'benefit');
        if (t.coupon_discount > 0 && t.coupon) html += row(`<i class="bi bi-ticket"></i> Cupón ${esc(t.coupon.code)}`, '− ' + money(t.coupon_discount), 'benefit');
        if (t.loyalty_discount > 0) html += row(`<i class="bi bi-stars"></i> Puntos (${t.redeem_points})`, '− ' + money(t.loyalty_discount), 'benefit');
        if (C.taxRate > 0 && t.tax > 0) html += row(`${esc(C.taxLabel)} ${C.taxRate} %${C.taxIncluded ? ' incl.' : ''}`, money(t.tax));
        html += row('Total', money(t.total), 'total');
        if (t.tip > 0) html += row('<i class="bi bi-heart"></i> Propina', money(t.tip));
        if (t.credit_applied > 0) html += row('<i class="bi bi-wallet2"></i> Saldo a favor', '− ' + money(t.credit_applied), 'benefit');
        if (t.tip > 0 || t.credit_applied > 0) html += row('A cobrar', money(t.charge), 'charge');
        if (t.on_credit) {
            html += row('<i class="bi bi-journal-text"></i> Anticipo (se cobra ahora)', money(t.charge_now), 'charge');
            html += row('Queda a crédito', money(t.pending || 0));
        }

        $('pos-totals-rows').innerHTML = html;
        $('pos-totals').classList.toggle('loading', !fresh && state.lines.length > 0);
        // Lo que se cobra ahora (a crédito, solo el anticipo).
        const now = t.charge_now ?? t.charge;
        $('pos-mobile-total').textContent = money(now);
        renderCash(now);
    }

    function renderPay() {
        $('pos-pay').innerHTML = C.paymentMethods.map((m) =>
            `<button type="button" data-pay="${esc(m.code)}" class="${state.payment === m.code ? 'active' : ''}">
                <i class="bi ${esc(m.icon)}"></i><span>${esc(m.name)}</span>
            </button>`).join('');
        const method = C.paymentMethods.find((m) => m.code === state.payment);
        $('pos-cash').classList.toggle('d-none', !(method && method.cash));
    }

    function renderCash(charge) {
        const quick = [charge];
        [10, 20, 50, 100, 200].forEach((step) => {
            const v = Math.ceil(charge / step) * step;
            if (v > charge && !quick.includes(v) && quick.length < 5) quick.push(v);
        });
        $('pos-quick').innerHTML = charge > 0
            ? quick.map((v, i) => `<button type="button" data-received="${v}">${i === 0 ? 'Exacto' : fmt(v)}</button>`).join('')
            : '';

        const out = $('pos-change');
        if (state.received === '' || charge <= 0) { out.innerHTML = ''; return; }
        const diff = num(state.received) - charge;
        out.innerHTML = diff >= 0
            ? `<span class="text-success">Cambio: ${money(diff)}</span>`
            : `<span class="text-danger">Falta: ${money(-diff)}</span>`;
    }

    function renderCashSession() {
        const box = $('pos-cash-session');
        if (!C.openSessions.length) {
            box.innerHTML = `<div class="small ${C.requireCash ? 'text-danger' : 'text-muted'}">
                <i class="bi bi-safe"></i> No hay cajas abiertas${C.requireCash ? ' y la empresa exige registrar la venta en caja' : ''}.
                <a href="${C.routes.cashOpen}">Abrir caja</a></div>`;
            return;
        }
        if (C.openSessions.length === 1 && state.cashSession === '') state.cashSession = String(C.openSessions[0].id);

        box.innerHTML = `<label class="pos-block-title" for="pos-session"><i class="bi bi-safe"></i> Caja</label>
            <select id="pos-session" class="form-select form-select-sm">
                ${C.requireCash ? '' : '<option value="">No registrar en caja</option>'}
                ${C.openSessions.map((s) => `<option value="${s.id}" ${state.cashSession === String(s.id) ? 'selected' : ''}>${esc(s.name)}</option>`).join('')}
            </select>`;
    }

    // ------------------------------------------------------------------
    //  Validación y cobro
    // ------------------------------------------------------------------
    function problems() {
        const list = [];
        if (state.quoteError) list.push(state.quoteError);
        if (quoteFresh()) list.push(...state.quote.errors);
        if (state.lines.length && !state.payment) list.push('Elige la forma de pago.');
        if (state.lines.length && C.requireCash && !state.cashSession) list.push('Selecciona una caja abierta para registrar la venta.');
        return [...new Set(list)];
    }

    function renderCheckout() {
        const errs = problems();
        const box = $('pos-errors');
        box.innerHTML = errs.map((e) => `<li><i class="bi bi-exclamation-circle"></i> ${esc(e)}</li>`).join('');
        box.classList.toggle('d-none', errs.length === 0);

        const ready = state.lines.length > 0 && quoteFresh() && errs.length === 0 && !state.submitting;
        $('pos-submit').disabled = !ready;
        // Una cotización no necesita forma de pago ni caja: basta con que el carrito sea válido.
        if ($('pos-save-quote')) {
            const quoteErrs = quoteFresh() ? state.quote.errors.filter((e) => !/saldo a favor|canje|puntos|propina|elegir el cliente/i.test(e)) : [];
            $('pos-save-quote').disabled = !(state.lines.length > 0 && quoteFresh() && quoteErrs.length === 0 && !state.submitting);
        }
        $('pos-submit-label').textContent = state.submitting ? 'Cobrando…'
            : !state.lines.length ? 'Agrega algo para cobrar'
            : state.onCredit ? 'Registrar a crédito · cobra ' + money(quoteFresh() ? (state.quote.charge_now ?? state.downPayment) : state.downPayment)
            : 'Cobrar ' + money(quoteFresh() ? (state.quote.charge_now ?? state.quote.charge) : naiveTotals().charge);
    }

    function payload() {
        return {
            client_id: state.client ? state.client.id : null,
            client_name: state.client ? null : (state.clientName || null),
            personal_id: state.principal || null,
            payment_method: state.payment,
            cash_session_id: state.cashSession || null,
            branch_id: state.branch || null,
            global_discount: state.globalDiscount,
            redeem_points: state.redeem || 0,
            coupon_code: state.coupon || null,
            credit_amount: state.credit || 0,
            on_credit: state.onCredit ? 1 : 0,
            down_payment: state.onCredit ? state.downPayment : null,
            due_days: state.onCredit ? state.dueDays : null,
            tip: state.tip,
            notes: state.notes || null,
            quote_id: C.quoteId || null,
            items: state.lines.map((l) => ({
                service_id: l.kind === 'service' ? l.id : null,
                product_id: l.kind === 'product' ? l.id : null,
                membership_plan_id: l.kind === 'membership' ? l.id : null,
                combo_id: l.kind === 'combo' ? l.id : null,
                service_package_id: l.kind === 'package' ? l.id : null,
                description: l.kind === 'membership' ? 'Membresía ' + l.name : l.kind === 'package' ? 'Paquete ' + l.name : l.name,
                quantity: l.qty,
                unit_price: l.price,
                discount: l.discount,
                personal_id: l.personal_id || null,
            })),
        };
    }

    /** Vuelca el carrito en campos ocultos para el POST normal del formulario. */
    function fillHidden() {
        const data = payload();
        const fields = [];
        const add = (name, value) => {
            if (value === null || value === undefined || value === '') return;
            fields.push(`<input type="hidden" name="${name}" value="${esc(value)}">`);
        };
        Object.keys(data).forEach((k) => { if (k !== 'items') add(k, data[k]); });
        data.items.forEach((it, i) => Object.keys(it).forEach((k) => add(`items[${i}][${k}]`, it[k])));
        $('pos-hidden').innerHTML = fields.join('');
    }

    function onSubmit(e) {
        if ($('pos-submit').disabled) { e.preventDefault(); return; }
        fillHidden();
        state.submitting = true;
        renderCheckout();
    }

    /** Guarda el carrito como cotización y abre su documento. */
    function saveQuote() {
        const btn = $('pos-save-quote');
        if (!btn || btn.disabled) return;
        const data = payload();
        data.redeem_points = 0;
        data.credit_amount = 0;
        data.tip = 0;
        data.on_credit = 0;
        state.submitting = true;
        renderCheckout();
        api(C.routes.quoteStore, { json: data })
            .then((res) => { window.location.href = res.url; })
            .catch((err) => { state.submitting = false; alertInline(err.message); });
    }

    // ------------------------------------------------------------------
    //  Cálculo en el servidor
    // ------------------------------------------------------------------
    let controller = null;

    const fetchQuote = debounce(() => {
        const version = state.version;
        if (controller) controller.abort();
        controller = new AbortController();

        api(C.routes.quote, { json: payload(), signal: controller.signal })
            .then((q) => {
                if (version !== state.version) return; // llegó tarde: hay un cálculo más nuevo en camino
                state.quote = q;
                state.quoteVersion = version;
                state.quoteError = null;
                if (q.client && state.client) { state.client = q.client; renderClient(); }
                refresh();
            })
            .catch((err) => {
                if (err.name === 'AbortError') return;
                state.quoteError = err.message;
                refresh();
            });
    }, 250);

    function changed() {
        state.version += 1;
        refresh();
        fetchQuote();
    }

    function refresh() {
        updateLineAmounts();
        renderTotals();
        renderPoints();
        renderCredit();
        renderCreditSale();
        renderCheckout();
    }

    function alertInline(message) {
        state.quoteError = message;
        renderCheckout();
    }

    // ------------------------------------------------------------------
    //  Eventos
    // ------------------------------------------------------------------
    function bind() {
        // Catálogo
        $('pos-search').addEventListener('input', (e) => { state.search = e.target.value; renderCatalog(); });
        $('pos-search').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const first = document.querySelector('#pos-items .pos-card:not(:disabled)');
                if (first) addItem(first.dataset.key);
            } else if (e.key === 'Escape') {
                e.target.value = ''; state.search = ''; renderCatalog();
            }
        });
        $('pos-chips').addEventListener('click', (e) => {
            const chip = e.target.closest('[data-chip]');
            if (!chip) return;
            state.filter = chip.dataset.chip;
            renderChips(); renderCatalog();
        });
        $('pos-items').addEventListener('click', (e) => {
            const card = e.target.closest('.pos-card');
            if (card && !card.disabled) addItem(card.dataset.key);
        });

        // Líneas
        $('pos-lines').addEventListener('click', onLinesClick);
        $('pos-lines').addEventListener('input', onLinesInput);
        $('pos-lines').addEventListener('change', onLinesInput);
        $('pos-clear').addEventListener('click', () => {
            if (!confirm('¿Vaciar la venta?')) return;
            state.lines = []; renderLines(); updateCatalogBadges(); changed();
        });

        // Cliente
        const search = $('pos-client-search');
        search.addEventListener('input', () => {
            const term = search.value.trim();
            if (term.length < 2) { $('pos-client-results').classList.add('d-none'); return; }
            searchClients(term);
        });
        search.addEventListener('keydown', (e) => {
            if (!clientResults.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                clientActive = (clientActive + (e.key === 'ArrowDown' ? 1 : -1) + clientResults.length) % clientResults.length;
                renderClientResults(search.value.trim());
            } else if (e.key === 'Enter' && clientActive >= 0) {
                e.preventDefault();
                selectClient(clientResults[clientActive].id);
            } else if (e.key === 'Escape') {
                $('pos-client-results').classList.add('d-none');
            }
        });
        $('pos-client-results').addEventListener('click', (e) => {
            const b = e.target.closest('[data-client]');
            if (b) selectClient(b.dataset.client);
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#pos-client-pick')) $('pos-client-results').classList.add('d-none');
        });
        $('pos-client-card').addEventListener('click', (e) => { if (e.target.closest('[data-act="change-client"]')) clearClient(); });
        $('pos-client-name').addEventListener('input', (e) => { state.clientName = e.target.value; });

        // Alta rápida de cliente
        const modalEl = $('pos-client-modal');
        if (modalEl && $('pos-client-new')) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            $('pos-client-new').addEventListener('click', () => {
                $('nc-name').value = search.value.trim() || $('pos-client-name').value.trim();
                $('nc-error').classList.add('d-none');
                modal.show();
            });
            modalEl.addEventListener('shown.bs.modal', () => $('nc-name').focus());
            $('pos-client-form').addEventListener('submit', (e) => {
                e.preventDefault();
                $('nc-save').disabled = true;
                api(C.routes.storeClient, { json: { full_name: $('nc-name').value, phone: $('nc-phone').value, id_number: $('nc-doc').value } })
                    .then((client) => { modal.hide(); ['nc-name', 'nc-phone', 'nc-doc'].forEach((id) => { $(id).value = ''; }); setClient(client); })
                    .catch((err) => { $('nc-error').textContent = err.message; $('nc-error').classList.remove('d-none'); })
                    .finally(() => { $('nc-save').disabled = false; });
            });
        }

        // Profesional principal, propina, descuento global, puntos
        $('pos-principal').addEventListener('change', (e) => { state.principal = e.target.value; changed(); });
        $('pos-tip').addEventListener('input', (e) => { state.tip = Math.max(0, num(e.target.value)); changed(); });
        $('pos-totals').addEventListener('click', (e) => {
            if (!e.target.closest('[data-act="global"]')) return;
            state.showGlobal = true;
            $('pos-gd').classList.remove('d-none');
            e.target.closest('[data-act="global"]').classList.add('d-none');
            $('pos-gd-input').focus();
        });
        $('pos-totals').addEventListener('input', (e) => {
            if (e.target.id === 'pos-coupon-input') {
                e.target.value = e.target.value.toUpperCase().replace(/\s+/g, '');
                state.coupon = e.target.value;
                changed();
                return;
            }
            if (e.target.id !== 'pos-gd-input') return;
            state.globalDiscount = Math.max(0, num(e.target.value));
            changed();
        });
        $('pos-totals').addEventListener('click', (e) => {
            if (!e.target.closest('[data-act="coupon"]')) return;
            $('pos-coupon').classList.remove('d-none');
            e.target.closest('[data-act="coupon"]').classList.add('d-none');
            $('pos-coupon-input').focus();
        });
        $('pos-points').addEventListener('input', (e) => {
            if (e.target.id !== 'pos-redeem') return;
            state.redeem = Math.max(0, Math.floor(num(e.target.value)));
            changed();
        });
        $('pos-points').addEventListener('click', (e) => {
            if (e.target.id !== 'pts-max') return;
            state.redeem = state.client.points.max_redeemable;
            $('pos-redeem').value = state.redeem;
            changed();
        });

        // Saldo a favor
        $('pos-credit-input').addEventListener('input', (e) => {
            state.credit = Math.max(0, num(e.target.value));
            changed();
        });
        $('credit-max').addEventListener('click', () => {
            state.credit = Math.round(creditMax() * 100) / 100;
            $('pos-credit-input').value = state.credit;
            changed();
        });

        // Cotización
        $('pos-save-quote')?.addEventListener('click', saveQuote);

        // Venta a crédito
        $('pos-on-credit')?.addEventListener('change', (e) => { state.onCredit = e.target.checked; changed(); });
        $('pos-down')?.addEventListener('input', (e) => { state.downPayment = Math.max(0, num(e.target.value)); changed(); });
        $('pos-due')?.addEventListener('input', (e) => { state.dueDays = Math.max(1, parseInt(e.target.value, 10) || 1); changed(); });

        // Pago
        $('pos-pay').addEventListener('click', (e) => {
            const b = e.target.closest('[data-pay]');
            if (!b) return;
            state.payment = b.dataset.pay;
            renderPay();
            renderCheckout();
            const method = C.paymentMethods.find((m) => m.code === state.payment);
            if (method && method.cash) $('pos-received').focus();
        });
        $('pos-received').addEventListener('input', (e) => { state.received = e.target.value; renderTotals(); });
        $('pos-quick').addEventListener('click', (e) => {
            const b = e.target.closest('[data-received]');
            if (!b) return;
            state.received = b.dataset.received;
            $('pos-received').value = state.received;
            renderTotals();
        });

        // Caja y más opciones
        $('pos-cash-session').addEventListener('change', (e) => {
            if (e.target.id === 'pos-session') { state.cashSession = e.target.value; renderCheckout(); }
        });
        $('pos-branch')?.addEventListener('change', (e) => { state.branch = e.target.value; });
        $('pos-notes').addEventListener('input', (e) => { state.notes = e.target.value; });

        // Cobro y atajos
        $('pos-form').addEventListener('submit', onSubmit);
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && !isTyping(document.activeElement)) {
                e.preventDefault();
                $('pos-search').focus();
            } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                if (!$('pos-submit').disabled) $('pos-form').requestSubmit();
            }
        });
    }

    // ------------------------------------------------------------------
    //  Arranque
    // ------------------------------------------------------------------
    function restoreOld() {
        const o = C.old;
        if (!o) return;

        (o.items || []).forEach((it) => {
            const key = it.membership_plan_id ? 'membership:' + it.membership_plan_id
                : it.service_package_id ? 'package:' + it.service_package_id
                : it.combo_id ? 'combo:' + it.combo_id
                : it.product_id ? 'product:' + it.product_id
                : it.service_id ? 'service:' + it.service_id : null;
            const cat = byKey[key];
            if (!cat) return;
            state.lines.push({
                key, kind: cat.kind, id: cat.id, name: cat.name, price: num(it.unit_price),
                qty: num(it.quantity) || 1, discount: num(it.discount), personal_id: it.personal_id || '',
                stock: cat.stock, showDisc: num(it.discount) > 0,
            });
        });

        state.principal = o.personal_id || state.principal;
        state.payment = o.payment_method || null;
        state.cashSession = o.cash_session_id || '';
        state.branch = o.branch_id || '';
        state.globalDiscount = num(o.global_discount);
        state.coupon = o.coupon_code || '';
        state.showGlobal = state.globalDiscount > 0;
        state.redeem = parseInt(o.redeem_points, 10) || 0;
        state.credit = num(o.credit_amount);
        state.onCredit = o.on_credit === '1' || o.on_credit === 1 || o.on_credit === true;
        state.downPayment = num(o.down_payment);
        if (o.due_days) state.dueDays = parseInt(o.due_days, 10) || state.dueDays;
        if ($('pos-on-credit')) {
            $('pos-on-credit').checked = state.onCredit;
            $('pos-down').value = state.downPayment;
            $('pos-due').value = state.dueDays;
        }
        state.tip = num(o.tip);
        state.notes = o.notes || '';
        state.clientName = o.client_name || '';

        $('pos-tip').value = state.tip;
        $('pos-notes').value = state.notes;
        $('pos-client-name').value = state.clientName;
        if ($('pos-branch')) $('pos-branch').value = state.branch;

        if (o.client_id) {
            const redeem = state.redeem;
            const credit = state.credit;
            selectClient(o.client_id).then(() => { state.redeem = redeem; state.credit = credit; changed(); });
        }
    }

    function init() {
        // Estructura fija de totales: el descuento global no se redibuja al escribir.
        $('pos-totals').innerHTML = `<div id="pos-totals-rows"></div>
            <button type="button" class="pos-link mt-1" data-act="global">+ Descuento global</button>
            ${C.sellsCoupons ? '<button type="button" class="pos-link mt-1 ms-3" data-act="coupon">+ Cupón</button>' : ''}
            <div class="d-flex justify-content-between align-items-center mt-1 d-none" id="pos-coupon">
                <span class="small text-muted"><i class="bi bi-ticket"></i> Cupón</span>
                <input type="text" id="pos-coupon-input" class="form-control form-control-sm text-end fw-semibold" style="width: 140px; letter-spacing: .04em;" maxlength="40" placeholder="CÓDIGO">
            </div>
            <div class="d-flex justify-content-between align-items-center mt-1 d-none" id="pos-gd">
                <span class="small text-muted">Descuento global</span>
                <div class="input-group input-group-sm" style="width: 140px;">
                    <span class="input-group-text">${esc(C.currency)}</span>
                    <input type="number" id="pos-gd-input" class="form-control text-end no-uppercase" min="0" step="0.5">
                </div>
            </div>`;

        // Forma de pago por defecto: la marcada como predeterminada.
        const def = C.paymentMethods.find((m) => m.default) || C.paymentMethods[0];
        state.payment = def ? def.code : null;

        restoreOld();

        $('pos-principal').value = state.principal;
        if (state.coupon && $('pos-coupon')) {
            $('pos-coupon').classList.remove('d-none');
            document.querySelector('[data-act="coupon"]')?.classList.add('d-none');
            $('pos-coupon-input').value = state.coupon;
        }
        if (state.showGlobal) {
            $('pos-gd').classList.remove('d-none');
            document.querySelector('[data-act="global"]').classList.add('d-none');
            $('pos-gd-input').value = state.globalDiscount;
        }

        bind();
        renderChips();
        renderCatalog();
        renderLines();
        renderClient();
        renderPay();
        renderCashSession();
        changed();
    }

    init();
})();
