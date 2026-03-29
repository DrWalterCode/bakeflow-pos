<?php $pageTitle = 'Daily Report'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Daily Report — <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></h4>
    <a href="/admin/reports" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

<div class="row g-3 mb-4">
    <?php $cols = [['label'=>'Transactions','val'=>(int)$daySummary['count'],'fmt'=>false],['label'=>'Total Sales','val'=>(float)$daySummary['total'],'fmt'=>true],['label'=>'Cash','val'=>(float)$daySummary['cash_total'],'fmt'=>true],['label'=>'Card','val'=>(float)$daySummary['card_total'],'fmt'=>true]]; ?>
    <?php foreach ($cols as $col): ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted small mb-1"><?= $col['label'] ?></p>
                <h4 class="mb-0"><?= $col['fmt'] ? '$' . number_format($col['val'], 2) : $col['val'] ?></h4>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 bf-table" id="daily-report-table">
                <thead><tr><th>Ref</th><th>Cashier</th><th>Method</th><th>Total</th><th>Sync</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td class="fw-semibold text-primary" style="font-size:0.8rem"><?= htmlspecialchars($t['transaction_ref'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['cashier_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge bg-secondary"><?= strtoupper(htmlspecialchars($t['payment_method'], ENT_QUOTES, 'UTF-8')) ?></span></td>
                    <td class="fw-semibold">$<?= number_format((float)$t['total'], 2) ?></td>
                    <td><span class="admin-badge-<?= htmlspecialchars($t['sync_status'], ENT_QUOTES, 'UTF-8') ?>"><?= ucfirst(htmlspecialchars($t['sync_status'], ENT_QUOTES, 'UTF-8')) ?></span></td>
                    <td class="text-muted" style="font-size:0.8rem"><?= htmlspecialchars($t['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
