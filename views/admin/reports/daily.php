<?php
$pageTitle = 'Day-End Report';
$summary = $report['summary'] ?? [];
$products = $report['products'] ?? [];
$expenses = $report['expenses'] ?? [];
$transactions = $report['transactions'] ?? [];
$closure = $report['closure'] ?? [];
$status = $closure['status'] ?? 'open';
$printQuery = http_build_query(['date' => $date, 'format' => 'print']);
$productsCsvQuery = http_build_query(['date' => $date, 'format' => 'csv', 'section' => 'products']);
$expensesCsvQuery = http_build_query(['date' => $date, 'format' => 'csv', 'section' => 'expenses']);
$transactionsCsvQuery = http_build_query(['date' => $date, 'format' => 'csv', 'section' => 'transactions']);
?>
<?php if (!empty($isPrint)): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day-End Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
        h1, h2 { margin-bottom: 8px; }
        .report-meta { margin-bottom: 16px; color: #666; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .summary-card { border: 1px solid #ddd; padding: 12px; border-radius: 8px; }
        .summary-card small { color: #666; display: block; margin-bottom: 6px; }
        .section { margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0">Day-End Report - <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></h4>
        <?php if (!empty($isPrint)): ?>
        <div class="report-meta">Status: <?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?></div>
        <?php endif; ?>
    </div>
    <?php if (empty($isPrint)): ?>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/reports" class="btn btn-outline-secondary btn-sm">Back</a>
        <a href="/admin/reports/daily?<?= htmlspecialchars($printQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm" target="_blank">Print / Save PDF</a>
        <a href="/admin/reports/daily?<?= htmlspecialchars($productsCsvQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary btn-sm">Products CSV</a>
        <a href="/admin/reports/daily?<?= htmlspecialchars($expensesCsvQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary btn-sm">Expenses CSV</a>
        <a href="/admin/reports/daily?<?= htmlspecialchars($transactionsCsvQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary btn-sm">Transactions CSV</a>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($isPrint)): ?>
<form method="GET" class="row g-3 mb-4">
    <div class="col-auto">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" onkeydown="return false">
    </div>
    <div class="col-auto align-self-end">
        <button type="submit" class="btn btn-outline-primary">Load Report</button>
    </div>
</form>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label' => 'Transactions', 'value' => (int)($summary['transaction_count'] ?? 0), 'money' => false],
        ['label' => 'Gross Sales', 'value' => (float)($summary['gross_sales'] ?? 0), 'money' => true],
        ['label' => 'Discounts', 'value' => (float)($summary['discount_total'] ?? 0), 'money' => true],
        ['label' => 'Net Sales', 'value' => (float)($summary['net_sales'] ?? 0), 'money' => true],
        ['label' => 'Expenses', 'value' => (float)($summary['total_expenses'] ?? 0), 'money' => true],
        ['label' => 'Expected Cash In Hand', 'value' => (float)($summary['expected_cash'] ?? 0), 'money' => true],
    ];
    ?>
    <?php foreach ($cards as $card): ?>
    <div class="col-sm-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <p class="text-muted small mb-1"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></p>
                <h4 class="mb-0">
                    <?= $card['money']
                        ? htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') . number_format((float)$card['value'], 2)
                        : (int)$card['value'] ?>
                </h4>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="card-title mb-1">Payment Mix</h5>
                        <p class="text-muted small mb-0">How the day&apos;s sales were collected.</p>
                    </div>
                    <span class="badge <?= $status === 'closed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?>
                    </span>
                </div>
                <div class="row g-3">
                    <div class="col-6"><small class="text-muted d-block">Cash</small><strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($summary['cash_sales'] ?? 0), 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Card</small><strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($summary['card_sales'] ?? 0), 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Mobile</small><strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($summary['mobile_sales'] ?? 0), 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Split Total</small><strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($summary['split_sales'] ?? 0), 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Split Cash Portion</small><strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($summary['split_cash_sales'] ?? 0), 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Split Card Portion</small><strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($summary['split_card_sales'] ?? 0), 2) ?></strong></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Closeout</h5>
                <div class="row g-3 mb-3">
                    <div class="col-6"><small class="text-muted d-block">Expected Cash</small><strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($closure['expected_cash'] ?? $summary['expected_cash'] ?? 0), 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Actual Cash</small><strong><?= $closure['actual_cash'] === null ? 'Not closed' : htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') . number_format((float)$closure['actual_cash'], 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Variance</small><strong><?= $closure['difference'] === null ? 'Not closed' : htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') . number_format((float)$closure['difference'], 2) ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Closed By</small><strong><?= htmlspecialchars($closure['closed_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Closed At</small><strong><?= htmlspecialchars($closure['closed_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="col-6"><small class="text-muted d-block">Reopened At</small><strong><?= htmlspecialchars($closure['reopened_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>
                <?php if (!empty($closure['notes'])): ?>
                <p class="mb-2"><small class="text-muted d-block">Notes</small><?= nl2br(htmlspecialchars((string)$closure['notes'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <?php if (!empty($closure['reopen_reason'])): ?>
                <p class="mb-2"><small class="text-muted d-block">Reopen Reason</small><?= nl2br(htmlspecialchars((string)$closure['reopen_reason'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>

                <?php if (empty($isPrint) && !empty($isAdmin)): ?>
                    <?php if ($status === 'closed'): ?>
                    <form method="POST" action="/admin/reports/daily/reopen" class="mt-3">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label class="form-label">Reopen Reason</label>
                            <textarea name="reopen_reason" class="form-control" rows="2" placeholder="Optional note for the audit trail"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Reopen this day and allow new transactions again?');">Reopen Day</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="/admin/reports/daily/close" class="mt-3">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label class="form-label">Actual Cash Counted</label>
                            <input type="number" name="actual_cash" class="form-control" min="0" step="0.01" required value="<?= htmlspecialchars(number_format((float)($summary['expected_cash'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional closeout notes"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Close this day and lock new transactions?');">Close Day</button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <div>
                <h5 class="mb-1">Product Movement</h5>
                <p class="text-muted small mb-0">Opening stock, production, sales, and closing stock for the day.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 <?= empty($isPrint) ? 'bf-table' : '' ?>" id="day-end-products-table">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Opening</th>
                    <th>Produced</th>
                    <th>Sold</th>
                    <th>Closing</th>
                    <th>Revenue</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= !empty($product['is_cake']) ? 'bg-info' : 'bg-light text-dark' ?>"><?= !empty($product['is_cake']) ? 'Cake' : 'Stock' ?></span></td>
                    <td><?= $product['opening_stock'] === null ? '—' : (int)$product['opening_stock'] ?></td>
                    <td><?= $product['produced_qty'] === null ? '—' : (int)$product['produced_qty'] ?></td>
                    <td><?= (int)$product['sold_qty'] ?></td>
                    <td><?= $product['closing_stock'] === null ? '—' : (int)$product['closing_stock'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)$product['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <div>
                <h5 class="mb-1">Expenses</h5>
                <p class="text-muted small mb-0">Every expense recorded for the day.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 <?= empty($isPrint) ? 'bf-table' : '' ?>" id="day-end-expenses-table">
                <thead>
                <tr>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Receipt Ref</th>
                    <th>Recorded By</th>
                    <th>Time</th>
                    <th>Amount</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?= htmlspecialchars($expense['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($expense['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($expense['receipt_ref'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($expense['recorded_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($expense['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($expense['amount'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <div>
                <h5 class="mb-1">Transactions</h5>
                <p class="text-muted small mb-0">Detailed sales list for the selected day.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 <?= empty($isPrint) ? 'bf-table' : '' ?>" id="day-end-transactions-table">
                <thead>
                <tr>
                    <th>Ref</th>
                    <th>Cashier</th>
                    <th>Method</th>
                    <th>Total</th>
                    <th>Cash Portion</th>
                    <th>Sync</th>
                    <th>Time</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td class="fw-semibold text-primary" style="font-size:0.8rem"><?= htmlspecialchars($transaction['transaction_ref'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($transaction['cashier_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge bg-secondary"><?= strtoupper(htmlspecialchars($transaction['payment_method'], ENT_QUOTES, 'UTF-8')) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)$transaction['total'], 2) ?></td>
                    <td><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)($transaction['cash_portion'] ?? 0), 2) ?></td>
                    <td><span class="admin-badge-<?= htmlspecialchars($transaction['sync_status'], ENT_QUOTES, 'UTF-8') ?>"><?= ucfirst(htmlspecialchars($transaction['sync_status'], ENT_QUOTES, 'UTF-8')) ?></span></td>
                    <td class="text-muted" style="font-size:0.8rem"><?= htmlspecialchars($transaction['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($isPrint)): ?>
</body>
</html>
<?php endif; ?>
