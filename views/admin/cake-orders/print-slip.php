<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Slip — Order #<?= (int)$order['id'] ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #000;
            background: #fff;
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
        }
        .slip-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .slip-header h1 {
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .shop-name { font-size: 13px; color: #555; }
        .slip-ref {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .slip-section {
            border-bottom: 1px dashed #999;
            padding: 10px 0;
        }
        .slip-section:last-child { border-bottom: none; }
        .slip-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .slip-value {
            font-size: 16px;
            font-weight: 700;
        }
        .slip-value-lg {
            font-size: 22px;
            font-weight: 800;
        }
        .slip-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        .slip-col { flex: 1; }
        .inscription-box {
            border: 2px solid #000;
            padding: 10px 14px;
            margin: 6px 0;
            font-size: 18px;
            font-weight: 700;
            font-style: italic;
            text-align: center;
        }
        .notes-box {
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
        }
        .payment-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-deposit { background: #fff3cd; color: #856404; }
        .badge-paid { background: #d1e7dd; color: #0f5132; }
        .pickup-highlight {
            background: #fff3cd;
            padding: 8px 12px;
            border-radius: 6px;
            text-align: center;
        }
        .btn-print {
            display: block;
            width: 100%;
            padding: 10px;
            background: #E8631A;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 16px;
        }
        .btn-print:hover { opacity: 0.85; }

        @media print {
            .btn-print { display: none; }
            body { padding: 0; max-width: 100%; }
            .slip-header { border-bottom-width: 1px; }
        }
    </style>
</head>
<body>

<div class="slip-header">
    <h1>Production Slip</h1>
    <div class="shop-name"><?= htmlspecialchars($shop['name'] ?? 'BakeFlow Bakery', ENT_QUOTES, 'UTF-8') ?></div>
    <div class="slip-ref">Ref: <?= htmlspecialchars($order['transaction_ref'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> | Order #<?= (int)$order['id'] ?></div>
</div>

<?php if ($order['pickup_date']): ?>
<div class="slip-section">
    <div class="pickup-highlight">
        <div class="slip-label">Pickup Date</div>
        <div class="slip-value-lg"><?= date('l, d F Y', strtotime($order['pickup_date'])) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="slip-section">
    <div class="slip-row">
        <div class="slip-col">
            <div class="slip-label">Customer</div>
            <div class="slip-value"><?= htmlspecialchars($order['customer_name'] ?? 'Walk-in', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php if ($order['customer_phone']): ?>
        <div class="slip-col">
            <div class="slip-label">Phone</div>
            <div class="slip-value"><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="slip-section">
    <div class="slip-row">
        <div class="slip-col">
            <div class="slip-label">Flavour</div>
            <div class="slip-value"><?= htmlspecialchars($order['flavour_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="slip-col">
            <div class="slip-label">Size</div>
            <div class="slip-value"><?= htmlspecialchars($order['size_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
    <div style="margin-top: 8px;">
        <div class="slip-label">Shape</div>
        <div class="slip-value"><?= ucfirst($order['shape'] ?? 'round') ?></div>
    </div>
</div>

<?php if ($order['inscription']): ?>
<div class="slip-section">
    <div class="slip-label">Inscription</div>
    <div class="inscription-box"><?= htmlspecialchars($order['inscription'], ENT_QUOTES, 'UTF-8') ?></div>
</div>
<?php endif; ?>

<?php if ($order['notes']): ?>
<div class="slip-section">
    <div class="slip-label">Special Notes</div>
    <div class="notes-box"><?= htmlspecialchars($order['notes'], ENT_QUOTES, 'UTF-8') ?></div>
</div>
<?php endif; ?>

<div class="slip-section">
    <div class="slip-row">
        <div class="slip-col">
            <div class="slip-label">Price</div>
            <div class="slip-value">$<?= number_format((float)$order['full_price'], 2) ?></div>
        </div>
        <div class="slip-col">
            <div class="slip-label">Payment</div>
            <div class="slip-value">
                <?php if ($order['payment_status'] === 'paid'): ?>
                    <span class="payment-badge badge-paid">PAID IN FULL</span>
                <?php else: ?>
                    <span class="payment-badge badge-deposit">DEPOSIT: $<?= number_format((float)$order['amount_paid'], 2) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="slip-section" style="text-align: center; font-size: 11px; color: #999;">
    Order placed: <?= htmlspecialchars($order['order_date'] ?? $order['created_at'], ENT_QUOTES, 'UTF-8') ?>
    | Printed: <?= date('d M Y H:i') ?>
</div>

<button class="btn-print" onclick="window.print()">Print Slip</button>

</body>
</html>
