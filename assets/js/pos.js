/**
 * BakeFlow POS — Main POS Logic
 * Pure vanilla JS, no frameworks, no jQuery.
 */

const POS = window.POS = (function () {

    // ── State ──────────────────────────────────────────────────
    let products        = [];   // all active products from API
    let categories      = [];   // all active categories
    let cart            = [];   // [{id, name, price, qty, line_total, is_cake, cake_data}]
    let currentCatId    = null;
    let payMethod       = 'cash';
    let pendingCakeProduct = null; // product waiting for cake details
    let cakePaymentChoice  = 'full'; // 'full' or 'deposit'
    let lastTransactionId = null;
    let lastReceiptData   = null;
    let pendingBalanceOrder = null; // cake order awaiting balance collection
    let balancePayMethod   = 'cash';
    let productSearchTerm  = '';
    const cfg = window.BFPOS_CONFIG || {};
    const currency = cfg.currency || '$';
    const RECEIPT_PAPER_WIDTH_MM = 72;
    const RECEIPT_CONTENT_WIDTH_MM = 70;
    const RECEIPT_PRINT_PADDING_MM = 2;

    // ── Dialog helpers (replace native confirm/alert) ──────────
    let _dialogResolve = null;

    function _showDialog(message, title, isConfirm) {
        document.getElementById('bf-dialog-title').textContent = title || (isConfirm ? 'Confirm' : 'Notice');
        document.getElementById('bf-dialog-message').textContent = message;
        var cancelBtn = document.getElementById('bf-dialog-cancel');
        cancelBtn.style.display = isConfirm ? '' : 'none';
        document.getElementById('bf-dialog').classList.remove('hidden');
    }

    function _posConfirm(message, callback) {
        _showDialog(message, 'Confirm', true);
        _dialogResolve = callback;
    }

    function _posAlert(message) {
        _showDialog(message, 'Notice', false);
        _dialogResolve = null;
    }

    function _dialogOk() {
        document.getElementById('bf-dialog').classList.add('hidden');
        if (_dialogResolve) { _dialogResolve(); _dialogResolve = null; }
    }

    function _dialogCancel() {
        document.getElementById('bf-dialog').classList.add('hidden');
        _dialogResolve = null;
    }

    // ── Formatting ─────────────────────────────────────────────
    function fmt(amount) {
        return currency + parseFloat(amount || 0).toFixed(2);
    }

    function fmtDate(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function fmtReceiptTimestamp(value) {
        if (!value) return '';

        const normalized = String(value).includes('T')
            ? String(value)
            : String(value).replace(' ', 'T');
        const date = new Date(normalized);

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        const pad = num => String(num).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    // ── Clock ──────────────────────────────────────────────────
    function startClock() {
        function tick() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            const el = document.getElementById('pos-clock');
            if (el) el.textContent = `${h}:${m}:${s}`;
        }
        tick();
        setInterval(tick, 1000);
    }

    // ── Product Icons — keyword-matched per product for relevant visuals ──
    const PRODUCT_ICONS = [
        // Pies
        { kw: ['pie'],                                      bg: 'rgba(183,28,28,.10)', color: '#b71c1c', svg: '<path d="M3 17h18v2H3v-2zm1.8-5.6c0-3.5 3.2-6.4 7.2-6.4s7.2 2.9 7.2 6.4H4.8zM3 13h18v2H3v-2z"/>' },
        { kw: ['bacon','cheese stick'],                     bg: 'rgba(211,84,0,.10)',   color: '#d35400', svg: '<path d="M2 19h20v2H2v-2zm1-1h18c0-2-2-4-4-5 0-1 1-2 1-4 0-3.5-2.5-6-6-6S6 5.5 6 9c0 2 1 3 1 4-2 1-4 3-4 5z"/>' },
        // Cupcakes
        { kw: ['cupcake'],                                  bg: 'rgba(232,99,26,.10)',  color: '#E8631A', svg: '<path d="M12 2C9 2 6.5 4.1 6 7H5c-1.7 0-3 1.3-3 3 0 1.1.6 2 1.5 2.5V21c0 .6.4 1 1 1h15c.6 0 1-.4 1-1v-8.5c.9-.5 1.5-1.4 1.5-2.5 0-1.7-1.3-3-3-3h-1c-.5-2.9-3-5-6-5zm-5 12h10v7H7v-7zm5-4c-1.3 0-2.5-.5-3.5-1.3.6-.4 1-1 1-1.7 0-.3-.1-.6-.2-.9.8-.7 1.7-1.1 2.7-1.1s1.9.4 2.7 1.1c-.1.3-.2.6-.2.9 0 .7.4 1.3 1 1.7-1 .8-2.2 1.3-3.5 1.3z"/>' },
        // Muffins
        { kw: ['muffin'],                                   bg: 'rgba(240,165,0,.10)',  color: '#F0A500', svg: '<path d="M18 9c0-1.3-.5-2.4-1.3-3.3C16 4.1 14.2 3 12 3S8 4.1 7.3 5.7C6.5 6.6 6 7.7 6 9c-1.7 0-3 1.3-3 3h18c0-1.7-1.3-3-3-3zM5 13l1.5 9h11L19 13H5z"/>' },
        // Cookies
        { kw: ['oatmeal'],                                  bg: 'rgba(160,128,80,.10)', color: '#a08050', svg: '<circle cx="8.5" cy="10" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="15" cy="11" r="1"/><circle cx="11" cy="14" r="1"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>' },
        { kw: ['choc chip'],                                bg: 'rgba(93,64,55,.10)',   color: '#5d4037', svg: '<circle cx="9" cy="9" r="1.2"/><circle cx="14" cy="8" r="1.2"/><circle cx="11" cy="13" r="1.2"/><circle cx="15.5" cy="12.5" r="1.2"/><circle cx="8" cy="15" r="1"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>' },
        { kw: ['snickerdoodle'],                            bg: 'rgba(188,170,130,.10)',color: '#bc9a5a', svg: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/><path d="M8 10c1-.8 2.5-.8 3.5 0s2.5.8 3.5 0M8 13.5c1-.8 2.5-.8 3.5 0s2.5.8 3.5 0"/>' },
        // Cake Slices / Brownies
        { kw: ['cake slice'],                               bg: 'rgba(192,57,43,.10)',  color: '#c0392b', svg: '<path d="M12 4L4 20h16L12 4zm0 4l5 10H7l5-10z"/>' },
        { kw: ['brownie','fudge'],                          bg: 'rgba(62,39,35,.10)',   color: '#3e2723', svg: '<path d="M3 5h18v14H3V5zm2 2v10h14V7H5zm3 3h2v2H8v-2zm4 0h2v2h-2v-2zm4 0h2v2h-2v-2z"/>' },
        { kw: ['cherry','almond'],                          bg: 'rgba(198,40,40,.10)',  color: '#c62828', svg: '<path d="M12 2c-1 0-2 .5-2.7 1.3C8.5 2.5 7.4 2 6.2 2.1 4 2.3 2.2 4.2 2 6.4c-.1 1.4.5 2.7 1.5 3.6H3v2h8.5c-.3.7-.5 1.4-.5 2.2 0 3 2.5 5.5 5.5 5.5S22 17.2 22 14.2c0-2.4-1.6-4.5-3.8-5.2.4-.6.8-1.3.8-2 0-1.7-1.3-3-3-3-.8 0-1.5.3-2 .8-.5-.5-1.2-.8-2-.8z"/>' },
        // Pastries
        { kw: ['donut','doughnut'],                         bg: 'rgba(255,152,0,.10)',  color: '#ff9800', svg: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm0-14c-4.42 0-8 3.58-8 8 0 .7.1 1.37.27 2.02C5.1 10.62 8.24 8 12 8s6.9 2.62 7.73 6.02c.17-.65.27-1.32.27-2.02 0-4.42-3.58-8-8-8z"/>' },
        { kw: ['samosa'],                                   bg: 'rgba(245,183,60,.10)', color: '#d4a017', svg: '<path d="M12 3L2 21h20L12 3zm0 4l6.5 12h-13L12 7z"/>' },
        { kw: ['croissant'],                                bg: 'rgba(205,160,80,.10)', color: '#cda050', svg: '<path d="M4.5 11C2 11 2 14 4 15l6 4h4l6-4c2-1 2-4-.5-4-.8 0-1.6.3-2 .8C17 10 15 9 12 9s-5 1-5.5 2.8c-.4-.5-1.2-.8-2-.8zM12 6c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>' },
        { kw: ['cinnamon roll','cinnamon'],                 bg: 'rgba(165,105,50,.10)', color: '#a56932', svg: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.4 0 2.7.4 3.8 1.1C14.4 7 13.3 7.5 12 7.5S9.6 7 8.2 6.1C9.3 5.4 10.6 5 12 5zm0 5c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>' },
        { kw: ['tray bake'],                                bg: 'rgba(120,85,72,.10)',  color: '#795548', svg: '<path d="M2 15h20v2c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2v-2zm2-6h16c1.1 0 2 .9 2 2v2H2v-2c0-1.1.9-2 2-2zm1-3h14v2H5V6z"/>' },
        // Custom Cake
        { kw: ['custom cake','cake'],                       bg: 'rgba(41,128,185,.10)', color: '#2980B9', svg: '<path d="M12 6c1.11 0 2-.9 2-2 0-.38-.1-.73-.29-1.03L12 0l-1.71 2.97c-.19.3-.29.65-.29 1.03 0 1.1.9 2 2 2zm4.6 9.99l-1.07-1.07-1.08 1.07c-1.3 1.3-3.58 1.31-4.89 0l-1.07-1.07-1.09 1.07C6.75 16.64 5.88 17 4.96 17c-.73 0-1.4-.23-1.96-.61V21c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-4.61c-.56.38-1.23.61-1.96.61-.92 0-1.79-.36-2.44-1.01zM18 9h-5V7h-2v2H6c-1.66 0-3 1.34-3 3v1.54c0 1.08.88 1.96 1.96 1.96.52 0 1.02-.2 1.38-.57l2.14-2.13 2.13 2.13c.74.74 2.03.74 2.77 0l2.14-2.13 2.13 2.13c.37.37.86.57 1.38.57 1.08 0 1.96-.88 1.96-1.96V12c.01-1.66-1.33-3-2.99-3z"/>' },
    ];
    const DEFAULT_ICON = { bg: 'rgba(150,120,100,.10)', color: '#967864', svg: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>' };

    function getProductIcon(product) {
        const name = product.name.toLowerCase();
        const match = PRODUCT_ICONS.find(ic => ic.kw.some(k => name.includes(k)));
        const icon = match || DEFAULT_ICON;
        return `<span class="prod-icon" style="background:${icon.bg}"><svg viewBox="0 0 24 24" fill="${icon.color}" xmlns="http://www.w3.org/2000/svg">${icon.svg}</svg></span>`;
    }

    // ── Product Loading ─────────────────────────────────────────
    const CACHE_KEY = 'bfpos_products_cache_v2';
    const CACHE_TS  = 'bfpos_products_ts_v2';
    const CACHE_TTL = (cfg.cacheTtl || 300) * 1000; // ms

    async function loadProducts(forceRefresh = false) {
        try {
            const cached = localStorage.getItem(CACHE_KEY);
            const ts     = parseInt(localStorage.getItem(CACHE_TS) || '0', 10);

            if (!forceRefresh && cached && (Date.now() - ts) < CACHE_TTL) {
                const data = JSON.parse(cached);
                products   = data.products   || [];
                categories = data.categories || [];
                renderCategories();
                return;
            }
        } catch (e) { /* ignore */ }

        const grid = document.getElementById('product-grid');
        grid.innerHTML = '<div class="grid-loading">Loading products…</div>';

        try {
            const res  = await fetch('/api/products');
            const data = await res.json();
            products   = data.products   || [];
            categories = data.categories || [];

            localStorage.setItem(CACHE_KEY, JSON.stringify(data));
            localStorage.setItem(CACHE_TS,  String(Date.now()));

            renderCategories();
        } catch (err) {
            grid.innerHTML = '<div class="grid-empty">Failed to load products.<br>Check connection.</div>';
        }
    }

    // ── Category Rendering ──────────────────────────────────────
    function renderCategories() {
        const nav = document.getElementById('category-tabs');
        nav.innerHTML = '';

        // "Quick Items" tab first — admin-configured quick-access products
        const allBtn = document.createElement('button');
        allBtn.className = 'cat-tab active';
        allBtn.textContent = '⚡ Quick Items';
        allBtn.setAttribute('role', 'tab');
        allBtn.setAttribute('data-cat', 'quick');
        allBtn.style.setProperty('--tab-color', '#E8631A');
        allBtn.onclick = () => selectCategory('quick');
        nav.appendChild(allBtn);

        categories.forEach((cat, idx) => {
            const btn = document.createElement('button');
            btn.className = 'cat-tab';
            btn.textContent = cat.name;
            btn.setAttribute('role', 'tab');
            btn.setAttribute('data-cat', cat.id);
            btn.style.setProperty('--tab-color', cat.color || '#6c757d');
            btn.onclick = () => selectCategory(cat.id);
            if (idx < 7) {
                btn.title = `F${idx + 2} shortcut`;
            }
            nav.appendChild(btn);
        });

        // Select first tab
        selectCategory('quick');
    }

    function selectCategory(catId) {
        currentCatId = catId;
        document.querySelectorAll('.cat-tab').forEach(btn => {
            const isActive = String(btn.getAttribute('data-cat')) === String(catId);
            btn.classList.toggle('active', isActive);
        });
        renderProducts(catId);
    }

    function filterProducts(term) {
        productSearchTerm = String(term || '').trim().toLowerCase();
        renderProducts(currentCatId || 'quick');
    }

    // ── Product Rendering ───────────────────────────────────────
    function renderProducts(catId) {
        const grid = document.getElementById('product-grid');
        const search = productSearchTerm;
        grid.innerHTML = '';

        let filtered;
        if (search) {
            filtered = products.filter(p =>
                String(p.name || '').toLowerCase().includes(search) ||
                String(p.barcode || '').toLowerCase().includes(search)
            );
            filtered = filtered.filter(p => !p.is_cake || p.price > 0);
        } else if (catId === 'quick') {
            filtered = products
                .filter(p => p.is_quick_item && !p.is_cake)
                .sort((a, b) => {
                    const quickOrder = (a.quick_item_order || 0) - (b.quick_item_order || 0);
                    if (quickOrder !== 0) {
                        return quickOrder;
                    }

                    const sortOrder = (a.sort_order || 0) - (b.sort_order || 0);
                    if (sortOrder !== 0) {
                        return sortOrder;
                    }

                    return String(a.name || '').localeCompare(String(b.name || ''));
                });
            if (filtered.length === 0) {
                // Fallback for databases where quick-access items are not configured yet.
                filtered = products.filter(p => p.price > 0).slice(0, 10);
            }
        } else {
            filtered = products.filter(p => String(p.category_id) === String(catId));
        }

        updateProductResults(filtered.length, search);

        if (filtered.length === 0) {
            grid.innerHTML = `<div class="grid-empty">${search ? 'No products match this search.' : 'No products in this category.'}</div>`;
            return;
        }

        filtered.forEach(product => {
            const btn = document.createElement('button');
            btn.className = 'product-btn' +
                            (product.is_cake ? ' is-cake' : '') +
                            (product.price === 0 ? ' no-price' : '');
            btn.setAttribute('role', 'listitem');
            btn.setAttribute('aria-label', product.name);

            btn.innerHTML = `
                ${product.is_cake ? '<span class="prod-cake-badge">custom</span>' : ''}
                ${getProductIcon(product)}
                <span class="prod-name">${escHtml(product.name)}</span>
                <span class="prod-price">${product.price > 0 ? fmt(product.price) : 'Custom'}</span>
            `;

            btn.onclick = () => addToCart(product);
            grid.appendChild(btn);
        });
    }

    function updateProductResults(count, search) {
        const label = document.getElementById('product-results-count');
        if (!label) return;

        if (search) {
            label.textContent = count === 1 ? '1 product found' : `${count} products found`;
            return;
        }

        label.textContent = count === 1 ? '1 product ready to add' : `${count} products ready to add`;
    }

    // ── Cart Operations ─────────────────────────────────────────
    function addToCart(product) {
        if (product.is_cake) {
            openCakeModal(product);
            return;
        }

        if (Number(product.price || 0) <= 0) {
            _posAlert(`Set a price for "${product.name}" in Admin > Products before selling it.`);
            return;
        }

        const existing = cart.findIndex(i => i.id === product.id && !i.cake_data);
        if (existing >= 0) {
            cart[existing].qty++;
            cart[existing].line_total = round2(cart[existing].qty * cart[existing].price);
        } else {
            cart.push({
                id:         product.id,
                name:       product.name,
                price:      product.price,
                qty:        1,
                line_total: product.price,
                is_cake:    false,
                cake_data:  null,
            });
        }
        renderCart();
    }

    function removeFromCart(index) {
        cart.splice(index, 1);
        renderCart();
    }

    function updateQty(index, delta) {
        cart[index].qty += delta;
        if (cart[index].qty <= 0) {
            cart.splice(index, 1);
        } else {
            cart[index].line_total = round2(cart[index].qty * cart[index].price);
        }
        renderCart();
    }

    function clearCart() {
        if (cart.length === 0) return;
        _posConfirm('Clear entire cart?', function() {
            cart = [];
            renderCart();
        });
    }

    function calcTotal() {
        const subtotal = round2(cart.reduce((sum, i) => sum + i.line_total, 0));
        return { subtotal, discount: 0, total: subtotal };
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    // ── Cart Rendering ──────────────────────────────────────────
    function renderCart() {
        const container = document.getElementById('cart-items');
        const empty     = document.getElementById('cart-empty');
        const totals    = calcTotal();
        const itemCount = cart.reduce((sum, item) => sum + item.qty, 0);

        if (cart.length === 0) {
            container.innerHTML = '';
            container.appendChild(empty || createEmptyEl());
            document.getElementById('cart-empty').classList.remove('hidden');
            document.getElementById('total-subtotal').textContent = fmt(0);
            document.getElementById('total-grand').textContent    = fmt(0);
            document.getElementById('pay-amount').textContent     = fmt(0);
            const btn = document.getElementById('btn-pay');
            btn.disabled = true;
            updateCartMeta(0, false);
            return;
        }

        container.innerHTML = '';

        cart.forEach((item, idx) => {
            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <div class="cart-item-info">
                    <div class="cart-item-name">${escHtml(item.name)}</div>
                    ${item.cake_data ? `<div class="cart-item-sub">${cakeSummary(item.cake_data)}</div>` : ''}
                </div>
                <div class="cart-item-qty">
                    <button class="qty-btn" onclick="POS.updateQty(${idx}, -1)" aria-label="Decrease">−</button>
                    <span class="qty-num">${item.qty}</span>
                    <button class="qty-btn" onclick="POS.updateQty(${idx}, 1)"  aria-label="Increase">+</button>
                </div>
                <span class="cart-item-total">${fmt(item.line_total)}</span>
                <button class="cart-item-remove" onclick="POS.removeFromCart(${idx})" aria-label="Remove">✕</button>
            `;
            container.appendChild(div);
        });

        document.getElementById('total-subtotal').textContent = fmt(totals.subtotal);
        document.getElementById('total-grand').textContent    = fmt(totals.total);
        document.getElementById('pay-amount').textContent     = fmt(totals.total);
        document.getElementById('btn-pay').disabled = false;
        updateCartMeta(itemCount, true);
    }

    function updateCartMeta(itemCount, hasItems) {
        const badge = document.getElementById('cart-count-badge');
        const note = document.getElementById('cart-header-note');

        if (badge) {
            badge.textContent = itemCount === 1 ? '1 item' : `${itemCount} items`;
        }

        if (note) {
            note.textContent = hasItems
                ? 'Check quantity, then tap Take Payment.'
                : 'Tap a product to start the order.';
        }
    }

    function createEmptyEl() {
        const d = document.createElement('div');
        d.id = 'cart-empty';
        d.className = 'cart-empty';
        d.innerHTML = '<span>🥐</span><p>Cart is empty</p><p class="cart-empty-hint">Tap products to add</p>';
        return d;
    }

    function cakeSummary(cake) {
        const parts = [];
        if (cake.size_name)   parts.push(cake.size_name);
        if (cake.flavour_name) parts.push(cake.flavour_name);
        if (cake.shape === 'square') parts.push('Square');
        if ((cake.additional_cost || 0) > 0) parts.push(`Extras: ${fmt(cake.additional_cost)}`);
        if (cake.inscription) parts.push(`"${cake.inscription}"`);
        if (cake.pickup_date) parts.push(`Pickup: ${fmtDate(cake.pickup_date)}`);
        if (cake.payment_choice === 'deposit') parts.push(`Deposit: ${fmt(cake.deposit_amount)}`);
        return parts.join(' · ');
    }

    // ── Payment ─────────────────────────────────────────────────
    function getCakeAdditionalCost() {
        const value = parseFloat(document.getElementById('cake-extra-cost')?.value || '0');
        if (!Number.isFinite(value) || value < 0) return 0;
        return round2(value);
    }

    function openPayment() {
        if (cart.length === 0) return;
        const totals = calcTotal();
        const itemCount = cart.reduce((sum, item) => sum + item.qty, 0);
        document.getElementById('pay-due').textContent     = fmt(totals.total);
        document.getElementById('card-total').textContent  = fmt(totals.total);
        document.getElementById('mobile-total').textContent = fmt(totals.total);
        document.getElementById('pay-item-count').textContent = itemCount === 1 ? '1 item' : `${itemCount} items`;
        document.getElementById('cash-tendered').value     = '';
        document.getElementById('change-amount').textContent = fmt(0);
        document.getElementById('change-amount').className   = 'change-ok';
        document.getElementById('cash-status-note').textContent = 'Enter the amount received to continue.';
        document.getElementById('split-cash').value  = '';
        document.getElementById('split-card').value  = '';
        document.getElementById('split-change').textContent = fmt(0);
        document.getElementById('card-reference').value   = '';
        document.getElementById('mobile-reference').value = '';
        document.getElementById('btn-confirm-pay').disabled = true;
        renderTenderButtons(totals.total);

        selectPayMethod('cash');
        document.getElementById('payment-modal').classList.remove('hidden');
        setTimeout(() => document.getElementById('cash-tendered').focus(), 100);
    }

    function closePayment() {
        document.getElementById('payment-modal').classList.add('hidden');
    }

    function selectPayMethod(method) {
        payMethod = method;
        document.querySelectorAll('.pay-tab').forEach(t => {
            t.classList.toggle('active', t.getAttribute('data-method') === method);
        });
        document.querySelectorAll('.pay-panel').forEach(p => {
            p.classList.toggle('hidden', !p.id.endsWith(method));
        });
        updatePaymentMethodMeta(method);

        if (method === 'cash') {
            document.getElementById('btn-confirm-pay').disabled = true;
            setTimeout(() => document.getElementById('cash-tendered').focus(), 50);
        } else if (method === 'card') {
            document.getElementById('btn-confirm-pay').disabled = false;
            setTimeout(() => document.getElementById('card-reference').focus(), 50);
        } else if (method === 'mobile') {
            document.getElementById('btn-confirm-pay').disabled = false;
            setTimeout(() => document.getElementById('mobile-reference').focus(), 50);
        } else {
            document.getElementById('btn-confirm-pay').disabled = true;
            setTimeout(() => document.getElementById('split-cash').focus(), 50);
        }
    }

    function quickTender(amount) {
        let val = parseFloat(amount || '0');
        if (!Number.isFinite(val)) {
            val = calcTotal().total;
        }
        document.getElementById('cash-tendered').value = val.toFixed(2);
        calcChange();
    }

    function calcChange() {
        const totals   = calcTotal();
        const tendered = parseFloat(document.getElementById('cash-tendered').value || '0');
        const change   = round2(tendered - totals.total);
        const el       = document.getElementById('change-amount');
        const note     = document.getElementById('cash-status-note');

        if (tendered <= 0 || tendered < totals.total) {
            el.textContent = '−' + fmt(Math.abs(change));
            el.className   = 'change-warn';
            if (note) {
                note.textContent = tendered > 0
                    ? `Need ${fmt(Math.abs(change))} more before confirming.`
                    : 'Enter the amount received to continue.';
            }
            document.getElementById('btn-confirm-pay').disabled = true;
        } else {
            el.textContent = fmt(change);
            el.className   = 'change-ok';
            if (note) {
                note.textContent = change > 0
                    ? 'Ready to confirm and give change.'
                    : 'Exact amount received. Ready to confirm.';
            }
            document.getElementById('btn-confirm-pay').disabled = false;
        }
    }

    function renderTenderButtons(total) {
        const wrap = document.getElementById('cash-tender-buttons');
        if (!wrap) return;

        const options = getTenderOptions(total);
        wrap.innerHTML = options.map(amount => {
            const isExact = Math.abs(amount - total) < 0.001;
            const cls = isExact ? 'tender-btn tender-exact' : 'tender-btn';
            const label = isExact ? `Exact ${fmt(amount)}` : fmt(amount);
            return `<button class="${cls}" type="button" onclick="POS.quickTender(${amount.toFixed(2)})">${label}</button>`;
        }).join('');
    }

    function getTenderOptions(total) {
        const options = [
            total,
            Math.ceil(total),
            Math.ceil(total / 5) * 5,
            Math.ceil(total / 10) * 10,
            20,
            50,
            100,
        ];

        if (total > 100) {
            options.push(Math.ceil(total / 50) * 50);
        }

        return [...new Set(options.map(n => round2(Math.max(total, n))))].sort((a, b) => a - b).slice(0, 6);
    }

    function updatePaymentMethodMeta(method) {
        const label = document.getElementById('pay-method-label');
        const tip = document.getElementById('pay-method-tip');
        const button = document.getElementById('btn-confirm-pay');

        const map = {
            cash:   { label: 'Cash',   tip: 'Enter confirms cash',            button: 'Complete Cash Sale' },
            card:   { label: 'Card',   tip: 'Confirm after terminal approval', button: 'Confirm Card Payment' },
            mobile: { label: 'Mobile', tip: 'Record reference if provided',    button: 'Confirm Mobile Payment' },
            split:  { label: 'Split',  tip: 'Cash first, balance auto-fills',  button: 'Confirm Split Payment' },
        };

        const meta = map[method] || map.cash;
        if (label) label.textContent = meta.label;
        if (tip) tip.textContent = meta.tip;
        if (button) button.textContent = meta.button;
    }

    function calcSplit() {
        const totals    = calcTotal();
        const cashAmt   = parseFloat(document.getElementById('split-cash').value || '0');
        const remainder = round2(totals.total - cashAmt);
        document.getElementById('split-card').value = remainder > 0 ? remainder.toFixed(2) : '0.00';
        const change = round2(cashAmt - (cashAmt > totals.total ? totals.total : cashAmt));
        document.getElementById('split-change').textContent = fmt(Math.max(0, cashAmt - totals.total));
        document.getElementById('btn-confirm-pay').disabled =
            (cashAmt + parseFloat(document.getElementById('split-card').value || '0')) < totals.total;
    }

    function calcSplitCard() {
        const totals  = calcTotal();
        const cardAmt = parseFloat(document.getElementById('split-card').value || '0');
        const cashAmt = round2(totals.total - cardAmt);
        document.getElementById('split-cash').value = cashAmt > 0 ? cashAmt.toFixed(2) : '0.00';
        document.getElementById('btn-confirm-pay').disabled =
            (cashAmt + cardAmt) < totals.total;
    }

    function payKeydown(e) {
        if (e.key === 'Enter') confirmPayment();
    }

    async function confirmPayment() {
        const totals = calcTotal();
        let cash_tendered = 0;
        let card_amount   = 0;
        let reference     = '';

        if (payMethod === 'cash') {
            cash_tendered = parseFloat(document.getElementById('cash-tendered').value || '0');
            if (cash_tendered < totals.total) return;
        } else if (payMethod === 'card') {
            card_amount = totals.total;
            reference   = document.getElementById('card-reference').value.trim();
        } else if (payMethod === 'mobile') {
            card_amount = totals.total;
            reference   = document.getElementById('mobile-reference').value.trim();
        } else if (payMethod === 'split') {
            cash_tendered = parseFloat(document.getElementById('split-cash').value || '0');
            card_amount   = parseFloat(document.getElementById('split-card').value || '0');
            if (round2(cash_tendered + card_amount) < totals.total) return;
        }

        const btn = document.getElementById('btn-confirm-pay');
        btn.disabled   = true;
        btn.textContent = 'Processing…';

        const payload = {
            items: cart.map(item => ({
                product_id: item.id,
                qty:        item.qty,
                unit_price: item.price,
                cake_data:  item.cake_data || null,
            })),
            payment_method: payMethod,
            cash_tendered,
            card_amount,
            reference_number: reference,
            discount: 0,
        };

        try {
            const res  = await fetch('/api/sale', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': cfg.csrfToken || '',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.error || 'Sale failed');

            lastTransactionId = data.transaction_id;
            closePayment();
            await openReceipt(data.transaction_id, data.receipt);
            pollSyncStatus();

        } catch (err) {
            _posAlert('Error: ' + err.message);
        } finally {
            btn.disabled   = false;
            updatePaymentMethodMeta(payMethod);
        }
    }

    // ── Receipt ─────────────────────────────────────────────────
    async function openReceipt(transactionId, inlineReceipt) {
        let receipt = inlineReceipt;

        if (!receipt) {
            try {
                const res = await fetch('/api/receipt/' + transactionId);
                receipt = await res.json();
            } catch (e) {
                console.error('Could not load receipt', e);
                newSale();
                return;
            }
        }

        lastReceiptData = receipt;
        renderReceiptHtml(receipt);
        document.getElementById('receipt-modal').classList.remove('hidden');
        refreshReceiptPrinterStatus();

        if (cfg.receiptAutoPrint) {
            setTimeout(() => { printReceipt(); }, 150);
        }
    }

    function renderReceiptHtml(r) {
        const area = document.getElementById('receipt-print-area');
        const change = round2((r.cash_tendered || 0) - r.total);
        const createdAt = fmtReceiptTimestamp(r.created_at);

        let itemsHtml = '';
        (r.items || []).forEach(item => {
            itemsHtml += `
                <div class="receipt-row">
                    <span>${escHtml(item.qty)}x ${escHtml(item.product_name)}</span>
                    <span>${fmt(item.line_total)}</span>
                </div>
            `;
            if (item.cake) {
                const ck = item.cake;
                if (ck.size_name || ck.flavour_name)
                    itemsHtml += `<div class="receipt-row-indent"><span>${escHtml([ck.size_name, ck.flavour_name].filter(Boolean).join(', '))}</span><span></span></div>`;
                if (ck.shape === 'square')
                    itemsHtml += `<div class="receipt-row-indent"><span>Square shape</span><span></span></div>`;
                if ((ck.additional_cost || 0) > 0)
                    itemsHtml += `<div class="receipt-row-indent"><span>Additional cost</span><span>${fmt(ck.additional_cost)}</span></div>`;
                if (ck.inscription)
                    itemsHtml += `<div class="receipt-row-indent"><span>"${escHtml(ck.inscription)}"</span><span></span></div>`;
                if (ck.pickup_date)
                    itemsHtml += `<div class="receipt-row-indent"><span>Pickup: ${fmtDate(ck.pickup_date)}</span><span></span></div>`;
                if (ck.customer_name)
                    itemsHtml += `<div class="receipt-row-indent"><span>Customer: ${escHtml(ck.customer_name)}</span><span></span></div>`;
                if (ck.payment_status === 'deposit') {
                    itemsHtml += `<div class="receipt-row-indent receipt-deposit"><span>Cake Price</span><span>${fmt(ck.full_price)}</span></div>`;
                    itemsHtml += `<div class="receipt-row-indent receipt-deposit"><span>Deposit Paid</span><span>${fmt(ck.deposit_paid)}</span></div>`;
                    itemsHtml += `<div class="receipt-row-indent receipt-balance"><span>Balance Due</span><span>${fmt(ck.balance_due)}</span></div>`;
                } else if (ck.payment_status === 'paid') {
                    itemsHtml += `<div class="receipt-row-indent receipt-paid-full"><span>PAID IN FULL</span><span></span></div>`;
                }
            }
        });

        area.innerHTML = `
            <div class="receipt-shop-name">${escHtml(r.shop_name || cfg.shopName)}</div>
            <div class="receipt-shop-info">
                ${r.shop_address ? escHtml(r.shop_address) + '<br>' : ''}
                ${r.shop_phone   ? escHtml(r.shop_phone) + '<br>'  : ''}
                ${r.shop_email   ? escHtml(r.shop_email)           : ''}
            </div>
            ${r.receipt_header ? `<div class="receipt-footer-text">${escHtmlWithBreaks(r.receipt_header)}</div>` : ''}
            <hr class="receipt-divider">
            <div class="receipt-row"><span>Receipt #</span><span>${escHtml(r.transaction_ref)}</span></div>
            <div class="receipt-row"><span>Date</span><span>${escHtml(createdAt)}</span></div>
            <div class="receipt-row"><span>Cashier</span><span>${escHtml(r.cashier_name || cfg.cashierName)}</span></div>
            <hr class="receipt-divider">
            ${itemsHtml}
            <hr class="receipt-divider">
            <div class="receipt-total-block">
                <div class="receipt-row"><span>Subtotal</span><span>${fmt(r.subtotal)}</span></div>
                ${r.discount > 0 ? `<div class="receipt-row"><span>Discount</span><span>-${fmt(r.discount)}</span></div>` : ''}
                <div class="receipt-row receipt-grand-total"><span>TOTAL</span><span>${fmt(r.total)}</span></div>
            </div>
            <hr class="receipt-divider">
            ${r.payment_method === 'cash' || r.payment_method === 'split' ? `
                <div class="receipt-row"><span>Cash Tendered</span><span>${fmt(r.cash_tendered)}</span></div>
                <div class="receipt-row"><span>Change</span><span>${fmt(Math.max(0, change))}</span></div>
            ` : ''}
            ${r.payment_method === 'card' ? `<div class="receipt-row"><span>Card Payment</span><span>${fmt(r.total)}</span></div>` : ''}
            ${r.payment_method === 'mobile' ? `<div class="receipt-row"><span>Mobile Payment</span><span>${fmt(r.total)}</span></div>` : ''}
            ${r.payment_method === 'split' && (r.card_amount || 0) > 0 ? `<div class="receipt-row"><span>Card Portion</span><span>${fmt(r.card_amount)}</span></div>` : ''}
            ${r.reference_number ? `<div class="receipt-row"><span>Reference</span><span>${escHtml(r.reference_number)}</span></div>` : ''}
            <hr class="receipt-divider">
            <div class="receipt-footer-text">${escHtmlWithBreaks(r.receipt_footer || 'Thank you!')}</div>
        `;
    }

    function closeReceipt() {
        document.getElementById('receipt-modal').classList.add('hidden');
    }

    function setReceiptStatus(message, tone) {
        const el = document.getElementById('receipt-print-status');
        if (!el) return;

        const palette = {
            muted: 'var(--text-muted)',
            success: '#2d8653',
            warning: '#b06d00',
            error: '#c0392b',
        };

        el.textContent = message || '';
        el.style.color = palette[tone] || palette.muted;
    }
    function pxToMm(px) {
        return (px * 25.4) / 96;
    }

    function buildBrowserReceiptPrintStyles(pageHeightMm) {
        return `
            @page {
                size: ${RECEIPT_PAPER_WIDTH_MM}mm ${pageHeightMm.toFixed(2)}mm;
                margin: 0;
            }

            html,
            body {
                margin: 0;
                padding: 0;
                width: ${RECEIPT_PAPER_WIDTH_MM}mm;
                background: #fff;
                overflow: hidden;
            }

            body {
                font-family: "Courier New", Courier, monospace;
                font-size: 11px;
                line-height: 1.35;
                color: #000;
            }

            #receipt-print-area {
                box-sizing: border-box;
                width: ${RECEIPT_CONTENT_WIDTH_MM}mm;
                max-width: ${RECEIPT_CONTENT_WIDTH_MM}mm;
                padding: ${RECEIPT_PRINT_PADDING_MM}mm;
                margin: 0;
                color: #000;
                background: #fff;
            }

            .receipt-shop-name {
                font-size: 14px;
                font-weight: 700;
                text-align: center;
                margin-bottom: 1mm;
            }

            .receipt-shop-info,
            .receipt-footer-text {
                text-align: center;
                font-size: 10px;
                color: #000;
            }

            .receipt-divider {
                border: none;
                border-top: 1px dashed #000;
                margin: 1.5mm 0;
            }

            .receipt-row,
            .receipt-row-indent {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 2mm;
            }

            .receipt-row-indent {
                padding-left: 4mm;
                font-size: 10px;
                color: #000;
            }

            .receipt-row > span:first-child,
            .receipt-row-indent > span:first-child {
                flex: 1 1 auto;
                min-width: 0;
                white-space: normal;
                overflow-wrap: anywhere;
            }

            .receipt-row > span:last-child,
            .receipt-row-indent > span:last-child {
                flex: 0 0 auto;
                margin-left: 2mm;
                text-align: right;
                white-space: nowrap;
                font-weight: 700;
            }

            .receipt-total-block {
                padding: 0.5mm 0;
            }

            .receipt-grand-total,
            .receipt-balance,
            .receipt-paid-full {
                font-weight: 700;
            }
        `;
    }

    function measureBrowserReceiptHeightMm() {
        const area = document.getElementById('receipt-print-area');
        if (!area) {
            return 120;
        }

        const sandbox = document.createElement('div');
        sandbox.style.position = 'fixed';
        sandbox.style.left = '-10000px';
        sandbox.style.top = '0';
        sandbox.style.width = `${RECEIPT_PAPER_WIDTH_MM}mm`;
        sandbox.style.visibility = 'hidden';
        sandbox.style.pointerEvents = 'none';
        sandbox.innerHTML = `<style>${buildBrowserReceiptPrintStyles(200)}</style>${area.outerHTML}`;
        document.body.appendChild(sandbox);

        const receipt = sandbox.querySelector('#receipt-print-area');
        const heightPx = receipt ? receipt.scrollHeight : area.scrollHeight;
        sandbox.remove();

        return Math.max(45, pxToMm(heightPx) + 4);
    }

    function printReceiptWithBrowser() {
        const area = document.getElementById('receipt-print-area');
        if (!area) {
            window.print();
            return;
        }

        const pageHeightMm = measureBrowserReceiptHeightMm();
        const title = escHtml(lastReceiptData && lastReceiptData.transaction_ref ? lastReceiptData.transaction_ref : 'Receipt');
        const html = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title}</title>
    <style>${buildBrowserReceiptPrintStyles(pageHeightMm)}</style>
</head>
<body>${area.outerHTML}</body>
</html>`;

        const frame = document.createElement('iframe');
        frame.style.position = 'fixed';
        frame.style.right = '0';
        frame.style.bottom = '0';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.style.opacity = '0';
        document.body.appendChild(frame);

        const cleanup = () => {
            setTimeout(() => {
                frame.remove();
            }, 200);
        };

        frame.onload = function() {
            const printWindow = frame.contentWindow;
            if (!printWindow) {
                cleanup();
                window.print();
                return;
            }

            printWindow.onafterprint = cleanup;
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
            }, 120);
        };

        const doc = frame.contentWindow ? frame.contentWindow.document : null;
        if (!doc) {
            cleanup();
            window.print();
            return;
        }

        doc.open();
        doc.write(html);
        doc.close();
    }

    function refreshReceiptPrinterStatus() {
        setReceiptStatus('Receipt prints directly to the configured Windows receipt printer on this terminal. Browser print is used only as a fallback.', 'muted');
    }

    async function printReceiptDirectly() {
        const transactionId = Number((lastReceiptData && lastReceiptData.transaction_id) || lastTransactionId || 0);
        if (!transactionId) {
            throw new Error('Transaction ID is missing for this receipt.');
        }

        const res = await fetch('/api/print/receipt', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': cfg.csrfToken || '',
            },
            body: JSON.stringify({
                transaction_id: transactionId,
            }),
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
            throw new Error(data.error || 'Receipt printer did not accept the job.');
        }

        return data;
    }

    async function printReceipt() {
        if (!lastReceiptData) {
            _posAlert('No receipt is available to print yet.');
            return;
        }

        setReceiptStatus('Sending receipt to printer...', 'muted');

        try {
            const result = await printReceiptDirectly();
            const printerName = result && result.printer_name ? result.printer_name : 'default printer';
            setReceiptStatus(`Printed on ${printerName}.`, 'success');
        } catch (error) {
            setReceiptStatus('Direct print failed. Opening browser print window...', 'warning');
            setTimeout(() => {
                printReceiptWithBrowser();
            }, 120);
        }
    }

    function newSale() {
        closeReceipt();
        lastReceiptData = null;
        setReceiptStatus('', 'muted');
        cart = [];
        renderCart();
    }

    // ── Cake Modal ──────────────────────────────────────────────
    function openCakeModal(product) {
        pendingCakeProduct = product;

        // Populate selects from cached data
        const flavSelect = document.getElementById('cake-flavour');
        const sizeSelect = document.getElementById('cake-size');

        flavSelect.innerHTML = '<option value="">Select flavour…</option>';
        sizeSelect.innerHTML = '<option value="">Select size…</option>';

        // Find cake flavours and sizes from products data — fetched via API
        fetch('/api/products?include=cake_options').then(r => r.json()).then(data => {
            (data.cake_flavours || []).forEach(f => {
                flavSelect.innerHTML += `<option value="${f.id}">${escHtml(f.name)}</option>`;
            });
            (data.cake_sizes || []).forEach(s => {
                sizeSelect.innerHTML += `<option value="${s.id}" data-price="${s.price_base}" data-deposit="${s.deposit_amount}">${escHtml(s.name)} (${fmt(s.price_base)})</option>`;
            });
        }).catch(() => {
            // Fallback static data
            ['Chocolate','Black Forest','Vanilla','Red Velvet','Marble','Lemon Poppy Seed','Orange','Banana','Strawberry','German Chocolate'].forEach((f, i) => {
                flavSelect.innerHTML += `<option value="${i+1}">${f}</option>`;
            });
            [{id:1,name:'Small',p:20,d:10},{id:2,name:'Medium',p:25,d:12},{id:3,name:'Large',p:30,d:15},{id:4,name:'XL',p:40,d:20},{id:5,name:'Double Layer',p:50,d:25},{id:6,name:'16-inch',p:65,d:30}].forEach(s => {
                sizeSelect.innerHTML += `<option value="${s.id}" data-price="${s.p}" data-deposit="${s.d}">${s.name} (${fmt(s.p)})</option>`;
            });
        });

        document.getElementById('cake-inscription').value = '';
        document.getElementById('cake-pickup').value      = '';
        document.getElementById('cake-notes').value       = '';
        document.getElementById('cake-extra-cost').value  = '';
        document.getElementById('cake-price-display').textContent = fmt(0);
        document.getElementById('cake-deposit-display').textContent = fmt(0);
        document.getElementById('cake-balance-display').textContent = fmt(0);
        document.getElementById('btn-add-cake').disabled  = true;
        document.querySelectorAll('.shape-btn[data-shape]').forEach(b => b.classList.toggle('active', b.getAttribute('data-shape') === 'round'));

        // Reset payment choice to full
        cakePaymentChoice = 'full';
        document.querySelectorAll('.shape-btn[data-pay]').forEach(b => b.classList.toggle('active', b.getAttribute('data-pay') === 'full'));
        document.getElementById('cake-deposit-info').classList.add('hidden');
        document.getElementById('cake-customer-name').value  = '';
        document.getElementById('cake-customer-phone').value = '+263 ';

        document.getElementById('cake-modal').classList.remove('hidden');
    }

    function closeCakeModal() {
        pendingCakeProduct = null;
        document.getElementById('cake-modal').classList.add('hidden');
    }

    function updateCakePrice() {
        const sizeEl  = document.getElementById('cake-size');
        const shapeEl = document.querySelector('.shape-btn[data-shape].active');
        const sel     = sizeEl.options[sizeEl.selectedIndex];
        const base    = parseFloat(sel ? (sel.getAttribute('data-price') || 0) : 0);
        const shapeExtra = (shapeEl && shapeEl.getAttribute('data-shape') === 'square') ? 5 : 0;
        const additionalCost = getCakeAdditionalCost();
        const total   = round2(base + shapeExtra + additionalCost);
        const deposit = parseFloat(sel ? (sel.getAttribute('data-deposit') || 0) : 0);
        const balance = round2(total - deposit);
        document.getElementById('cake-price-display').textContent = fmt(total);
        document.getElementById('cake-deposit-display').textContent = fmt(deposit);
        document.getElementById('cake-balance-display').textContent = fmt(balance);
        document.getElementById('btn-add-cake').disabled = !sizeEl.value || !document.getElementById('cake-flavour').value;
    }

    function selectShape(btn) {
        document.querySelectorAll('.shape-btn[data-shape]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        updateCakePrice();
    }

    function selectCakePayment(btn) {
        document.querySelectorAll('.shape-btn[data-pay]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        cakePaymentChoice = btn.getAttribute('data-pay');
        const isDeposit = cakePaymentChoice === 'deposit';
        document.getElementById('cake-deposit-info').classList.toggle('hidden', !isDeposit);
    }

    function addCakeToCart() {
        if (!pendingCakeProduct) return;

        const flavEl   = document.getElementById('cake-flavour');
        const sizeEl   = document.getElementById('cake-size');
        const shapeEl  = document.querySelector('.shape-btn[data-shape].active');
        const flavId   = parseInt(flavEl.value || '0', 10);
        const sizeId   = parseInt(sizeEl.value || '0', 10);

        if (!flavId || !sizeId) {
            _posAlert('Please select a flavour and size.');
            return;
        }

        const sel      = sizeEl.options[sizeEl.selectedIndex];
        const base     = parseFloat(sel.getAttribute('data-price') || 0);
        const deposit  = parseFloat(sel.getAttribute('data-deposit') || 0);
        const rawAdditionalCost = parseFloat(document.getElementById('cake-extra-cost').value || '0');
        if (Number.isFinite(rawAdditionalCost) && rawAdditionalCost < 0) {
            _posAlert('Additional cost cannot be negative.');
            return;
        }
        const shapeExtra = (shapeEl && shapeEl.getAttribute('data-shape') === 'square') ? 5 : 0;
        const additionalCost = getCakeAdditionalCost();
        const fullPrice = round2(base + shapeExtra + additionalCost);
        const shape    = shapeEl ? shapeEl.getAttribute('data-shape') : 'round';
        const isDeposit = cakePaymentChoice === 'deposit';
        const payAmount = isDeposit ? deposit : fullPrice;

        const phoneVal = document.getElementById('cake-customer-phone').value.trim();
        if (phoneVal && phoneVal !== '+263' && phoneVal !== '+263 ') {
            const zimPhoneRe = /^\+263\s?7[0-9]{1}\s?[0-9]{3}\s?[0-9]{4}$/;
            if (!zimPhoneRe.test(phoneVal)) {
                _posAlert('Please enter a valid Zimbabwean phone number: +263 7X XXX XXXX');
                return;
            }
        }

        const cake_data = {
            flavour_id:     flavId,
            flavour_name:   flavEl.options[flavEl.selectedIndex].text,
            size_id:        sizeId,
            size_name:      sel.text.split(' (')[0],
            shape,
            inscription:    document.getElementById('cake-inscription').value.trim(),
            pickup_date:    document.getElementById('cake-pickup').value,
            notes:          document.getElementById('cake-notes').value.trim(),
            additional_cost: additionalCost,
            payment_choice: cakePaymentChoice,
            deposit_amount: deposit,
            full_price:     fullPrice,
            customer_name:  document.getElementById('cake-customer-name').value.trim(),
            customer_phone: document.getElementById('cake-customer-phone').value.trim(),
        };

        cart.push({
            id:         pendingCakeProduct.id,
            name:       pendingCakeProduct.name,
            price:      fullPrice,
            qty:        1,
            line_total: payAmount,
            is_cake:    true,
            cake_data,
        });

        closeCakeModal();
        renderCart();
    }

    // ── Sync Status ─────────────────────────────────────────────
    function pollSyncStatus() {
        fetch('/api/sync/status')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('sync-badge');
                const label = document.getElementById('sync-label');
                badge.className = 'sync-badge sync-' + (data.status || 'pending');
                if (data.status === 'green')  label.textContent = 'Synced';
                else if (data.status === 'orange') label.textContent = `${data.pending} pending`;
                else if (data.status === 'red')    label.textContent = 'Offline';
                else label.textContent = '…';
            })
            .catch(() => {
                document.getElementById('sync-badge').className = 'sync-badge sync-red';
                document.getElementById('sync-label').textContent = 'Offline';
            });
    }

    // ── Menu ────────────────────────────────────────────────────
    function toggleMenu() {
        const menu    = document.getElementById('pos-menu');
        const overlay = document.getElementById('menu-overlay');
        const button  = document.getElementById('menu-btn');
        const hidden  = menu.classList.toggle('hidden');
        overlay.classList.toggle('hidden', hidden);

        if (button) {
            button.setAttribute('aria-expanded', String(!hidden));
        }

        menu.setAttribute('aria-hidden', String(hidden));
    }

    // ── End of Day stub ─────────────────────────────────────────
    function openEndDay() {
        _posAlert('End of Day closing will be available in a future update.');
        toggleMenu();
    }

    // ── Cake Pickups ────────────────────────────────────────────
    async function openCakePickups() {
        toggleMenu();
        const modal   = document.getElementById('cake-pickups-modal');
        const content = document.getElementById('cake-pickups-content');
        content.innerHTML = '<div class="grid-loading">Loading orders...</div>';
        modal.classList.remove('hidden');

        try {
            const res  = await fetch('/api/cake-orders/pending');
            const data = await res.json();
            if (!data.success || !data.orders || data.orders.length === 0) {
                content.innerHTML = '<div class="grid-empty">No pending cake orders.</div>';
                return;
            }

            const statusBadges = {
                'pending':       '<span class="pickup-status pickup-status-pending">Pending</span>',
                'in_production': '<span class="pickup-status pickup-status-production">In Production</span>',
                'ready':         '<span class="pickup-status pickup-status-ready">Ready</span>',
            };

            let html = '<div class="pickup-list">';
            data.orders.forEach(o => {
                const detailParts = [o.size_name, o.flavour_name].filter(Boolean);
                if (o.shape === 'square') detailParts.push('Square');
                if ((o.additional_cost || 0) > 0) detailParts.push(`Extras ${fmt(o.additional_cost)}`);
                const details = detailParts.join(', ');
                const pickupStr = o.pickup_date ? fmtDate(o.pickup_date) : 'No date set';
                const badge = statusBadges[o.order_status] || '';
                const isReady = o.order_status === 'ready';
                const hasBalance = o.balance_due > 0;

                let actionHtml = '';
                if (isReady && hasBalance) {
                    actionHtml = `<button class="btn-collect-balance" onclick="POS.startBalancePayment(${o.id}, ${o.balance_due}, '${escHtml(details)}', '${escHtml(o.customer_name || '')}')">Collect Balance</button>`;
                } else if (isReady && !hasBalance) {
                    actionHtml = `<button class="btn-collect-balance" onclick="POS.markCakeCollected(${o.id})">Mark Collected</button>`;
                }

                html += `
                    <div class="pickup-item">
                        <div class="pickup-info">
                            <div class="pickup-date">${escHtml(pickupStr)} ${badge}</div>
                            <div class="pickup-detail">${escHtml(details)}</div>
                            ${o.customer_name ? `<div class="pickup-detail">${escHtml(o.customer_name)}${o.customer_phone ? ' — ' + escHtml(o.customer_phone) : ''}</div>` : ''}
                            <div class="pickup-detail">Ref: ${escHtml(o.transaction_ref || '')}</div>
                        </div>
                        ${hasBalance ? `<span class="pickup-balance">${fmt(o.balance_due)}</span>` : ''}
                        ${actionHtml}
                    </div>
                `;
            });
            html += '</div>';
            content.innerHTML = html;
        } catch (err) {
            content.innerHTML = '<div class="grid-empty">Failed to load orders. Check connection.</div>';
        }
    }

    function closeCakePickups() {
        document.getElementById('cake-pickups-modal').classList.add('hidden');
    }

    // ── Balance Payment ─────────────────────────────────────────
    function startBalancePayment(cakeOrderId, balanceDue, details, customerName) {
        closeCakePickups();
        pendingBalanceOrder = { id: cakeOrderId, balance: balanceDue };
        balancePayMethod = 'cash';

        document.getElementById('balance-due-display').textContent = fmt(balanceDue);
        document.getElementById('balance-order-summary').innerHTML =
            `<div style="font-size:0.85rem; color: var(--text-muted);">` +
            `${escHtml(details)}${customerName ? ' — ' + escHtml(customerName) : ''}` +
            `</div>`;

        document.querySelectorAll('.shape-btn[data-balpay]').forEach(b => b.classList.toggle('active', b.getAttribute('data-balpay') === 'cash'));
        document.getElementById('balance-cash-fields').classList.remove('hidden');
        document.getElementById('balance-ref-fields').classList.add('hidden');
        document.getElementById('balance-cash-tendered').value = '';
        document.getElementById('balance-reference').value     = '';
        document.getElementById('btn-confirm-balance').disabled = false;
        document.getElementById('btn-confirm-balance').textContent = 'Confirm Payment';

        document.getElementById('balance-payment-modal').classList.remove('hidden');
    }

    function selectBalancePayMethod(btn) {
        document.querySelectorAll('.shape-btn[data-balpay]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        balancePayMethod = btn.getAttribute('data-balpay');
        const isCash = balancePayMethod === 'cash';
        document.getElementById('balance-cash-fields').classList.toggle('hidden', !isCash);
        document.getElementById('balance-ref-fields').classList.toggle('hidden', isCash);
    }

    function closeBalancePayment() {
        document.getElementById('balance-payment-modal').classList.add('hidden');
        pendingBalanceOrder = null;
    }

    async function markCakeCollected(cakeOrderId) {
        _posConfirm('Mark this cake order as collected?', async function() {
            try {
                const res = await fetch('/api/cake-orders/' + cakeOrderId + '/mark-collected', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': cfg.csrfToken || '',
                    },
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Failed');
                closeCakePickups();
                _posAlert('Cake order marked as collected.');
            } catch (err) {
                _posAlert('Error: ' + err.message);
            }
        });
    }

    async function confirmBalancePayment() {
        if (!pendingBalanceOrder) return;

        const bal = pendingBalanceOrder.balance;
        let cash_tendered = 0;
        let card_amount   = 0;
        let reference     = '';

        if (balancePayMethod === 'cash') {
            cash_tendered = parseFloat(document.getElementById('balance-cash-tendered').value || '0');
            if (cash_tendered < bal) {
                _posAlert('Cash tendered (' + fmt(cash_tendered) + ') is less than balance due (' + fmt(bal) + ').');
                return;
            }
        } else {
            card_amount = bal;
            reference   = document.getElementById('balance-reference').value.trim();
        }

        const btn = document.getElementById('btn-confirm-balance');
        btn.disabled    = true;
        btn.textContent = 'Processing...';

        try {
            const res = await fetch('/api/cake-orders/' + pendingBalanceOrder.id + '/collect-balance', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': cfg.csrfToken || '',
                },
                body: JSON.stringify({
                    payment_method:   balancePayMethod,
                    cash_tendered:    cash_tendered,
                    card_amount:      card_amount,
                    reference_number: reference,
                }),
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Payment failed');

            closeBalancePayment();
            await openReceipt(data.transaction_id, data.receipt);
            pollSyncStatus();
        } catch (err) {
            _posAlert('Error: ' + err.message);
        } finally {
            btn.disabled    = false;
            btn.textContent = 'Confirm Payment';
        }
    }

    // ── Keyboard Shortcuts ───────────────────────────────────────
    function handleKey(e) {
        // Don't intercept if focused on an input
        const tag = document.activeElement ? document.activeElement.tagName : '';
        if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return;

        const tabs = document.querySelectorAll('.cat-tab');
        if (e.key === '/') {
            e.preventDefault();
            document.getElementById('product-search')?.focus();
            return;
        }

        // F1 = Quick Items, F2–F8 = categories
        if (e.key === 'F1') { e.preventDefault(); tabs[0]?.click(); return; }
        if (e.key === 'F2' && tabs[1]) { e.preventDefault(); tabs[1].click(); return; }
        if (e.key === 'F3' && tabs[2]) { e.preventDefault(); tabs[2].click(); return; }
        if (e.key === 'F4' && tabs[3]) { e.preventDefault(); tabs[3].click(); return; }
        if (e.key === 'F5' && tabs[4]) { e.preventDefault(); tabs[4].click(); return; }
        if (e.key === 'F6' && tabs[5]) { e.preventDefault(); tabs[5].click(); return; }
        if (e.key === 'F7' && tabs[6]) { e.preventDefault(); tabs[6].click(); return; }
        if (e.key === 'F8' && tabs[7]) { e.preventDefault(); tabs[7].click(); return; }

        // Enter = pay (if cart has items, modal not open)
        if (e.key === 'Enter') {
            const modal = document.getElementById('payment-modal');
            if (!modal.classList.contains('hidden')) return;
            if (cart.length > 0) openPayment();
            return;
        }

        // Escape = close active modal
        if (e.key === 'Escape') {
            if (!document.getElementById('cake-modal').classList.contains('hidden'))           { closeCakeModal();       return; }
            if (!document.getElementById('payment-modal').classList.contains('hidden'))        { closePayment();         return; }
            if (!document.getElementById('receipt-modal').classList.contains('hidden'))        { closeReceipt();         return; }
            if (!document.getElementById('cake-pickups-modal').classList.contains('hidden'))   { closeCakePickups();     return; }
            if (!document.getElementById('balance-payment-modal').classList.contains('hidden')){ closeBalancePayment();  return; }
            if (!document.getElementById('pos-menu').classList.contains('hidden'))             { toggleMenu();           return; }
        }
    }

    // ── Barcode Scanner ──────────────────────────────────────────
    let barcodeBuffer = '';
    let barcodeTimer  = null;

    function initBarcodeScanner() {
        document.addEventListener('keypress', function (e) {
            // Skip if focused on a real input/select
            const tag = document.activeElement ? document.activeElement.tagName : '';
            if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return;

            if (e.key === 'Enter') {
                if (barcodeBuffer.length > 2) {
                    processBarcode(barcodeBuffer);
                }
                barcodeBuffer = '';
                clearTimeout(barcodeTimer);
            } else {
                barcodeBuffer += e.key;
                clearTimeout(barcodeTimer);
                barcodeTimer = setTimeout(() => { barcodeBuffer = ''; }, 200);
            }
        });
    }

    function processBarcode(code) {
        const product = products.find(p => p.barcode === code);
        if (product) {
            addToCart(product);
        } else {
            console.warn('Product not found for barcode:', code);
        }
    }

    // ── Idle Timeout & Heartbeat ─────────────────────────────────
    let idleTimer = null;
    const IDLE_MS = (cfg.idleTimeout || 600) * 1000;

    function resetIdle() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => {
            window.location.href = '/login';
        }, IDLE_MS);
    }

    function startHeartbeat() {
        setInterval(() => {
            fetch('/api/auth/heartbeat', {
                method:  'POST',
                headers: { 'X-CSRF-Token': cfg.csrfToken || '' },
            })
            .then(r => r.json())
            .then(d => { if (!d.authenticated) window.location.href = '/login'; })
            .catch(() => {});
        }, 60000); // every minute
    }

    // ── Helpers ─────────────────────────────────────────────────
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escHtmlWithBreaks(str) {
        return escHtml(str).replace(/\r?\n/g, '<br>');
    }

    // ── Init ────────────────────────────────────────────────────
    function init() {
        startClock();
        loadProducts();
        pollSyncStatus();

        // Apply shop primary colour
        if (cfg.primaryColor) {
            document.documentElement.style.setProperty('--primary', cfg.primaryColor);
        }

        // Poll sync every 30s
        setInterval(pollSyncStatus, 30000);

        // Idle timeout
        ['click','keydown','touchstart','mousemove'].forEach(ev => {
            document.addEventListener(ev, resetIdle, { passive: true });
        });
        resetIdle();

        // Heartbeat
        startHeartbeat();

        // Keyboard shortcuts
        document.addEventListener('keydown', handleKey);

        // Barcode scanner
        initBarcodeScanner();

        // Refresh catalogue when returning to the POS so admin price changes show up quickly.
        window.addEventListener('focus', () => loadProducts(true));
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                loadProducts(true);
            }
        });

    }

    // Public API
    return {
        init,
        addToCart,
        removeFromCart,
        updateQty,
        clearCart,
        openPayment,
        closePayment,
        selectPayMethod,
        filterProducts,
        quickTender,
        calcChange,
        calcSplit,
        calcSplitCard,
        payKeydown,
        confirmPayment,
        openReceipt,
        closeReceipt,
        printReceipt,
        newSale,
        openCakeModal,
        closeCakeModal,
        updateCakePrice,
        selectShape,
        selectCakePayment,
        addCakeToCart,
        openCakePickups,
        closeCakePickups,
        startBalancePayment,
        selectBalancePayMethod,
        closeBalancePayment,
        confirmBalancePayment,
        markCakeCollected,
        pollSyncStatus,
        toggleMenu,
        openEndDay,
        handleKey,
        _dialogOk,
        _dialogCancel,
    };

})();

// Kick off
document.addEventListener('DOMContentLoaded', () => POS.init());
