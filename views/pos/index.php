<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BakeFlow POS</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="pos-body">

<!-- Hidden data for JS -->
<script>
window.BFPOS_CONFIG = {
    csrfToken:      '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>',
    cashierName:    '<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>',
    cashierId:      <?= (int)$user['id'] ?>,
    userRole:       '<?= htmlspecialchars((string)($user['role'] ?? 'cashier'), ENT_QUOTES, 'UTF-8') ?>',
    isAdmin:        <?= !empty($isAdmin) ? 'true' : 'false' ?>,
    terminalId:     '<?= htmlspecialchars($terminalId, ENT_QUOTES, 'UTF-8') ?>',
    currency:       '<?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?>',
    businessDate:   '<?= htmlspecialchars($businessDate, ENT_QUOTES, 'UTF-8') ?>',
    idleTimeout:    <?= $idleTimeout ?>,
    primaryColor:   '<?= htmlspecialchars($shop['primary_color'] ?? '#E8631A', ENT_QUOTES, 'UTF-8') ?>',
    shopName:       '<?= htmlspecialchars($shop['name'], ENT_QUOTES, 'UTF-8') ?>',
    receiptAutoPrint: <?= $receiptAutoPrint ? 'true' : 'false' ?>,
};
</script>

<!-- ============================================================
     TOP BAR
     ============================================================ -->
<header id="pos-topbar">
    <div class="topbar-left">
        <span id="shop-name"><?= htmlspecialchars($shop['name'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="topbar-center">
        <span id="pos-clock"></span>
    </div>
    <div class="topbar-right">
        <span id="cashier-label"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
        <div id="sync-badge" class="sync-badge sync-pending" title="Sync status">
            <span id="sync-dot"></span>
            <span id="sync-label">Syncing…</span>
        </div>
        <button class="topbar-btn" id="menu-btn" type="button" title="Menu" aria-label="Open quick access menu" aria-controls="pos-menu" aria-expanded="false" onclick="POS.toggleMenu()">
            <i data-lucide="menu"></i>
        </button>
    </div>
</header>

<div id="day-closed-banner" class="hidden" style="background:#fff4d6;border-bottom:1px solid rgba(0,0,0,0.08);padding:10px 18px;color:#7a5a00;font-size:0.92rem;">
    Day closed.
</div>

<!-- Slide-out menu -->
<div id="pos-menu" class="pos-menu hidden" role="dialog" aria-modal="true" aria-labelledby="pos-menu-title" aria-hidden="true">
    <div class="pos-menu-panel">
        <div class="menu-header">
            <div>
                <div class="menu-eyebrow">BakeFlow POS</div>
                <h2 id="pos-menu-title">Quick Access</h2>
                <p class="menu-subtitle">Navigation and session controls</p>
            </div>
            <button class="menu-close" type="button" aria-label="Close quick access menu" onclick="POS.toggleMenu()">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="menu-meta">
            <div class="menu-meta-chip">
                <i data-lucide="user-round"></i>
                <span><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="menu-meta-chip">
                <i data-lucide="monitor-smartphone"></i>
                <span><?= htmlspecialchars($terminalId, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <nav class="menu-nav" aria-label="POS quick links">
            <div class="menu-section-label">Workspace</div>

            <a href="/admin" class="menu-item">
                <span class="menu-item-icon">
                    <i data-lucide="layout-dashboard"></i>
                </span>
                <span class="menu-item-copy">
                    <span class="menu-item-title">Admin Panel</span>
                    <span class="menu-item-note">Reports, products, settings</span>
                </span>
            </a>

            <button class="menu-item" type="button" onclick="POS.openCakePickups()">
                <span class="menu-item-icon">
                    <i data-lucide="cake-slice"></i>
                </span>
                <span class="menu-item-copy">
                    <span class="menu-item-title">Cake Pickups</span>
                    <span class="menu-item-note">Outstanding collection orders</span>
                </span>
            </button>

            <button class="menu-item" type="button" onclick="POS.openEndDay()">
                <span class="menu-item-icon">
                    <i data-lucide="clipboard-list"></i>
                </span>
                <span class="menu-item-copy">
                    <span class="menu-item-title">End of Day</span>
                    <span class="menu-item-note">Close shift and review totals</span>
                </span>
            </button>
        </nav>

        <div class="menu-footer">
            <div class="menu-session-card">
                <div class="menu-section-label">Session</div>
                <p>Signed in as <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> on <?= htmlspecialchars($terminalId, ENT_QUOTES, 'UTF-8') ?>.</p>
            </div>

            <a href="/logout" class="menu-item menu-item-danger">
                <span class="menu-item-icon">
                    <i data-lucide="log-out"></i>
                </span>
                <span class="menu-item-copy">
                    <span class="menu-item-title">Logout</span>
                    <span class="menu-item-note">End this cashier session</span>
                </span>
            </a>
        </div>
    </div>
</div>
<div id="menu-overlay" class="overlay hidden" onclick="POS.toggleMenu()"></div>

<!-- ============================================================
     MAIN LAYOUT
     ============================================================ -->
<main id="pos-main">

    <!-- ============================
         PRODUCT PANEL (left 70%)
         ============================ -->
    <section id="product-panel">

        <div class="product-toolbar">
            <div class="product-toolbar-copy">
                <div class="panel-eyebrow">Fast Sale</div>
                <h1>Build the order quickly</h1>
                <p>Choose a category or search the product name, then review the cart before taking payment.</p>
            </div>
            <label class="product-search" for="product-search">
                <span>Find product</span>
                <input type="search" id="product-search" placeholder="Search pies, cupcakes, muffins..." autocomplete="off" oninput="POS.filterProducts(this.value)">
            </label>
        </div>

        <!-- Category tabs -->
        <nav id="category-tabs" role="tablist" aria-label="Product categories">
            <!-- Populated by JS -->
        </nav>

        <div class="panel-quickbar">
            <span id="product-results-count">Loading products...</span>
            <div class="panel-shortcuts">
                <span>F1-F8 switch categories</span>
                <span>Enter opens checkout</span>
            </div>
        </div>

        <!-- Product grid -->
        <div id="product-grid" role="list" aria-label="Products">
            <!-- Populated by JS -->
        </div>

    </section>

    <!-- ============================
         CART PANEL (right 30%)
         ============================ -->
    <aside id="cart-panel">
        <div class="cart-header">
            <div class="cart-header-copy">
                <span>Current Order</span>
                <p id="cart-header-note">Tap a product to start the order.</p>
            </div>
            <div class="cart-header-actions">
                <span id="cart-count-badge" class="cart-count-badge">0 items</span>
                <button class="btn-clear-cart" onclick="POS.clearCart()" title="Clear cart">&#10005; Clear</button>
            </div>
        </div>

        <div id="cart-items" role="list" aria-label="Cart items">
            <div class="cart-empty" id="cart-empty">
                <span>&#127855;</span>
                <p>Cart is empty</p>
                <p class="cart-empty-hint">Tap products to add</p>
            </div>
        </div>

        <div id="cart-totals">
            <div class="totals-row">
                <span>Subtotal</span>
                <span id="total-subtotal">$0.00</span>
            </div>
            <div class="totals-row discount-row hidden" id="discount-row">
                <span>Discount</span>
                <span id="total-discount" class="text-danger">-$0.00</span>
            </div>
            <div class="totals-row totals-grand">
                <span>TOTAL</span>
                <span id="total-grand">$0.00</span>
            </div>
        </div>

        <button id="btn-pay" class="btn-pay" onclick="POS.openPayment()" disabled>
            <span class="btn-pay-copy">
                <span class="btn-pay-label">Take Payment</span>
                <span class="btn-pay-note">Review total and charge the customer</span>
            </span>
            <span id="pay-amount">$0.00</span>
        </button>
    </aside>

</main>

<!-- ============================================================
     PAYMENT MODAL
     ============================================================ -->
<div id="payment-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-label="Payment">
    <div class="modal-card modal-payment">
        <div class="modal-header">
            <div>
                <h2>Take Payment</h2>
                <p class="modal-subtitle">Choose how the customer is paying, confirm the amount, then finish the sale.</p>
            </div>
            <button class="modal-close" onclick="POS.closePayment()" aria-label="Close">&times;</button>
        </div>

        <div class="pay-order-summary">
            <div class="pay-summary-stat">
                <i data-lucide="shopping-bag" class="pay-summary-icon"></i>
                <span>Items</span>
                <strong id="pay-item-count">0 items</strong>
            </div>
            <div class="pay-summary-stat">
                <i data-lucide="wallet-cards" class="pay-summary-icon"></i>
                <span>Method</span>
                <strong id="pay-method-label">Cash</strong>
            </div>
            <div class="pay-summary-stat">
                <i data-lucide="keyboard" class="pay-summary-icon"></i>
                <span>Tip</span>
                <strong id="pay-method-tip">Enter confirms cash</strong>
            </div>
        </div>

        <!-- Payment method tabs -->
        <div class="pay-tabs">
            <button class="pay-tab active" data-method="cash" onclick="POS.selectPayMethod('cash')">
                <i data-lucide="banknote" class="pay-tab-icon"></i>
                <span>Cash</span>
            </button>
            <button class="pay-tab" data-method="card" onclick="POS.selectPayMethod('card')">
                <i data-lucide="credit-card" class="pay-tab-icon"></i>
                <span>Card</span>
            </button>
            <button class="pay-tab" data-method="mobile" onclick="POS.selectPayMethod('mobile')">
                <i data-lucide="smartphone" class="pay-tab-icon"></i>
                <span>Mobile</span>
            </button>
            <button class="pay-tab" data-method="split" onclick="POS.selectPayMethod('split')">
                <i data-lucide="split" class="pay-tab-icon"></i>
                <span>Split</span>
            </button>
        </div>

        <div class="pay-total-due">
            <span>Total Due</span>
            <strong id="pay-due">$0.00</strong>
        </div>

        <!-- CASH panel -->
        <div id="pay-cash" class="pay-panel active">
            <div class="pay-section">
                <div class="pay-section-head">
                    <span class="pay-step">Step 1</span>
                    <h3>Select or enter the cash received</h3>
                </div>
                <div id="cash-tender-buttons" class="tender-quick"></div>
            </div>

            <div class="pay-section">
                <div class="tender-input-wrap">
                    <label>Cash Tendered</label>
                    <input type="number" id="cash-tendered" min="0" step="0.01" placeholder="0.00"
                           oninput="POS.calcChange()" onkeydown="POS.payKeydown(event)">
                    <p class="field-help">Type the amount received if it is not in the quick buttons above.</p>
                </div>
            </div>

            <div class="change-display">
                <div class="change-copy">
                    <span>Change</span>
                    <small id="cash-status-note">Enter the amount received to continue.</small>
                </div>
                <strong id="change-amount" class="change-ok">$0.00</strong>
            </div>
        </div>

        <!-- CARD panel -->
        <div id="pay-card" class="pay-panel hidden">
            <div class="pay-callout">
                <div class="pay-step">Step 1</div>
                <strong id="card-total">$0.00</strong>
                <p>Ask the customer to tap, insert, or swipe their card on the terminal.</p>
            </div>
            <div class="tender-input-wrap">
                <label>Reference / Approval Code (optional)</label>
                <input type="text" id="card-reference" placeholder="e.g. AUTH123456">
                <p class="field-help">You can leave this blank if the terminal does not give a reference.</p>
            </div>
        </div>

        <!-- MOBILE MONEY panel -->
        <div id="pay-mobile" class="pay-panel hidden">
            <div class="pay-callout">
                <div class="pay-step">Step 1</div>
                <strong id="mobile-total">$0.00</strong>
                <p>Confirm the transfer on the customer phone, then record the reference if provided.</p>
            </div>
            <div class="tender-input-wrap">
                <label>Transaction Reference (optional)</label>
                <input type="text" id="mobile-reference" placeholder="e.g. TXN9876543">
                <p class="field-help">Use the reference from the payment confirmation message when available.</p>
            </div>
        </div>

        <!-- SPLIT panel -->
        <div id="pay-split" class="pay-panel hidden">
            <div class="pay-callout pay-callout-compact">
                <div class="pay-step">Step 1</div>
                <p>Enter the cash part first. The remaining balance will auto-fill on the second field.</p>
            </div>
            <div class="split-row">
                <div class="tender-input-wrap">
                    <label>Cash Amount</label>
                    <input type="number" id="split-cash" min="0" step="0.01" placeholder="0.00"
                           oninput="POS.calcSplit()">
                </div>
                <div class="tender-input-wrap">
                    <label>Card Amount</label>
                    <input type="number" id="split-card" min="0" step="0.01" placeholder="0.00"
                           oninput="POS.calcSplitCard()">
                </div>
            </div>
            <div class="change-display">
                <div class="change-copy">
                    <span>Change from Cash</span>
                    <small>The card or mobile amount must cover the remaining balance.</small>
                </div>
                <strong id="split-change">$0.00</strong>
            </div>
        </div>

        <button id="btn-confirm-pay" class="btn-confirm-pay" onclick="POS.confirmPayment()" disabled>
            Confirm Payment
        </button>
    </div>
</div>

<!-- ============================================================
     RECEIPT MODAL
     ============================================================ -->
<div id="receipt-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-label="Receipt">
    <div class="modal-card modal-receipt">
        <div class="modal-header">
            <h2>Receipt</h2>
            <button class="modal-close" onclick="POS.closeReceipt()" aria-label="Close">&times;</button>
        </div>

        <div id="receipt-print-area">
            <!-- Populated by JS after sale -->
        </div>
        <div id="receipt-print-status" style="padding: 0 20px 10px; font-size: 0.8rem; color: var(--text-muted);"></div>

        <div class="receipt-actions">
            <button class="btn-print" onclick="POS.printReceipt()">&#128424; Print</button>
            <button class="btn-new-sale" onclick="POS.newSale()">New Sale</button>
        </div>
    </div>
</div>

<!-- ============================================================
     CAKE ORDER MODAL
     ============================================================ -->
<div id="cake-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-label="Cake Order">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Cake Order Details</h2>
            <button class="modal-close" onclick="POS.closeCakeModal()" aria-label="Close">&times;</button>
        </div>

        <div class="cake-form">
            <div class="cake-row">
                <div class="form-field">
                    <label>Flavour</label>
                    <select id="cake-flavour">
                        <option value="">Select flavour…</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Size</label>
                    <select id="cake-size" onchange="POS.updateCakePrice()">
                        <option value="">Select size…</option>
                    </select>
                </div>
            </div>
            <div class="cake-row">
                <div class="form-field">
                    <label>Shape</label>
                    <div class="shape-btns">
                        <button class="shape-btn active" data-shape="round" onclick="POS.selectShape(this)">&#9711; Round</button>
                        <button class="shape-btn" data-shape="square" onclick="POS.selectShape(this)">&#9633; Square (+$5)</button>
                    </div>
                </div>
                <div class="form-field">
                    <label>Calculated Price</label>
                    <div id="cake-price-display" class="cake-price-display">$0.00</div>
                </div>
            </div>
            <div class="form-field">
                <label>Additional Cost (optional)</label>
                <input type="number" id="cake-extra-cost" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 5.00" oninput="POS.updateCakePrice()">
            </div>
            <div class="form-field">
                <label>Inscription (optional)</label>
                <input type="text" id="cake-inscription" placeholder="e.g. Happy Birthday Sarah!" maxlength="60">
            </div>
            <div class="form-field">
                <label>Pickup Date</label>
                <input type="date" id="cake-pickup" min="<?= date('Y-m-d') ?>" onkeydown="return false">
            </div>
            <div class="form-field">
                <label>Notes (optional)</label>
                <input type="text" id="cake-notes" placeholder="Any special instructions…" maxlength="120">
            </div>
            <div class="cake-row">
                <div class="form-field" style="grid-column: span 2;">
                    <label>Payment Option</label>
                    <div class="shape-btns">
                        <button type="button" class="shape-btn active" data-pay="full" onclick="POS.selectCakePayment(this)">&#10003; Pay Full Amount</button>
                        <button type="button" class="shape-btn" data-pay="deposit" onclick="POS.selectCakePayment(this)">&#9201; Pay Deposit Only</button>
                    </div>
                </div>
            </div>
            <div id="cake-deposit-info" class="cake-row hidden">
                <div class="form-field">
                    <label>Deposit Amount</label>
                    <div id="cake-deposit-display" class="cake-price-display">$0.00</div>
                </div>
                <div class="form-field">
                    <label>Balance Due at Pickup</label>
                    <div id="cake-balance-display" class="cake-price-display cake-balance-amount">$0.00</div>
                </div>
            </div>
            <div id="cake-customer-fields">
                <div class="cake-row">
                    <div class="form-field">
                        <label>Customer Name (recommended)</label>
                        <input type="text" id="cake-customer-name" placeholder="e.g. Sarah M." maxlength="60">
                    </div>
                    <div class="form-field">
                        <label>Customer Phone (optional)</label>
                        <input type="tel" id="cake-customer-phone" placeholder="+263 7X XXX XXXX" maxlength="16" pattern="\+263\s?7[0-9]{1}\s?[0-9]{3}\s?[0-9]{4}" title="Zimbabwean number: +263 7X XXX XXXX" oninput="this.value = this.value.replace(/[^0-9+\s]/g, '')" onfocus="if(!this.value) this.value='+263 '">
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-cancel" onclick="POS.closeCakeModal()">Cancel</button>
            <button class="btn-add-cake" onclick="POS.addCakeToCart()" id="btn-add-cake" disabled>Add to Cart</button>
        </div>
    </div>
</div>

<!-- ============================================================
     BARCODE hidden input (always focusable via scanner)
     ============================================================ -->
<!-- ============================================================
     CONFIRM / ALERT MODAL
     ============================================================ -->
<div id="bf-dialog" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-label="Dialog">
    <div class="modal-card modal-dialog-sm">
        <div class="modal-header">
            <h2 id="bf-dialog-title">Confirm</h2>
            <button class="modal-close" onclick="POS._dialogCancel()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 16px 20px;">
            <p id="bf-dialog-message" style="margin:0; line-height:1.5;"></p>
        </div>
        <div class="modal-footer" id="bf-dialog-footer">
            <button class="btn-cancel" id="bf-dialog-cancel" onclick="POS._dialogCancel()">Cancel</button>
            <button class="btn-add-cake" id="bf-dialog-ok" onclick="POS._dialogOk()">OK</button>
        </div>
    </div>
</div>

<!-- ============================================================
     CAKE PICKUPS MODAL
     ============================================================ -->
<div id="cake-pickups-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-label="Cake Pickups">
    <div class="modal-card" style="max-width: 720px;">
        <div class="modal-header">
            <h2>Pending Cake Orders</h2>
            <button class="modal-close" onclick="POS.closeCakePickups()" aria-label="Close">&times;</button>
        </div>
        <div id="cake-pickups-content" style="padding: 16px 20px; max-height: 60vh; overflow-y: auto;">
            <div class="grid-loading">Loading...</div>
        </div>
    </div>
</div>

<!-- ============================================================
     BALANCE PAYMENT MODAL
     ============================================================ -->
<div id="balance-payment-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-label="Balance Payment">
    <div class="modal-card modal-dialog-sm">
        <div class="modal-header">
            <h2>Collect Balance</h2>
            <button class="modal-close" onclick="POS.closeBalancePayment()" aria-label="Close">&times;</button>
        </div>
        <div style="padding: 16px 20px;">
            <div id="balance-order-summary" style="margin-bottom: 16px;"></div>
            <div class="form-field" style="margin-bottom: 12px;">
                <label>Balance Due</label>
                <div id="balance-due-display" class="cake-price-display" style="font-size: 1.4rem;"></div>
            </div>
            <div class="form-field" style="margin-bottom: 12px;">
                <label>Payment Method</label>
                <div class="shape-btns">
                    <button type="button" class="shape-btn active" data-balpay="cash" onclick="POS.selectBalancePayMethod(this)">Cash</button>
                    <button type="button" class="shape-btn" data-balpay="card" onclick="POS.selectBalancePayMethod(this)">Card</button>
                    <button type="button" class="shape-btn" data-balpay="mobile" onclick="POS.selectBalancePayMethod(this)">Mobile</button>
                </div>
            </div>
            <div id="balance-cash-fields">
                <div class="form-field" style="margin-bottom: 12px;">
                    <label>Cash Tendered</label>
                    <input type="number" id="balance-cash-tendered" step="0.01" min="0" placeholder="0.00">
                </div>
            </div>
            <div id="balance-ref-fields" class="hidden">
                <div class="form-field" style="margin-bottom: 12px;">
                    <label>Reference Number</label>
                    <input type="text" id="balance-reference" placeholder="Transaction reference" maxlength="50">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="POS.closeBalancePayment()">Cancel</button>
            <button class="btn-add-cake" id="btn-confirm-balance" onclick="POS.confirmBalancePayment()">Confirm Payment</button>
        </div>
    </div>
</div>

<!-- ============================================================
     END OF DAY MODAL
     ============================================================ -->
<div id="endday-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-label="End of Day">
    <div class="modal-card" style="max-width: 980px;">
        <div class="modal-header">
            <div>
                <h2>End of Day</h2>
                <p class="modal-subtitle">Review today&apos;s sales, expenses, stock movement, and close the business date.</p>
            </div>
            <button class="modal-close" onclick="POS.closeEndDay()" aria-label="Close">&times;</button>
        </div>
        <div id="endday-content" style="padding: 16px 20px; max-height: 70vh; overflow-y: auto;">
            <div class="grid-loading">Loading report...</div>
        </div>
        <div class="modal-footer" style="justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <div id="endday-status-note" style="font-size: 0.85rem; color: var(--text-muted);"></div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button class="btn-cancel" onclick="POS.closeEndDay()">Close</button>
                <button class="btn-print" onclick="POS.downloadEndDay('pdf')">Download PDF</button>
                <button class="btn-print" onclick="POS.downloadEndDay('excel')">Download Excel</button>
                <button class="btn-cancel hidden" id="btn-reopen-endday" onclick="POS.reopenEndDay()">Reopen Day</button>
                <button class="btn-add-cake hidden" id="btn-close-endday" onclick="POS.finalizeEndDay()">Close Day</button>
            </div>
        </div>
    </div>
</div>

<input type="text" id="barcode-input" class="barcode-input" autocomplete="off"
       aria-hidden="true" tabindex="-1" placeholder="Scan barcode…">

<script src="/assets/vendors/core/core.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
<script src="/assets/js/pos.js?v=<?= time() ?>"></script>
</body>
</html>
