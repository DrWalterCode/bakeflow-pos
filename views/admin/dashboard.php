<?php $pageTitle = 'Dashboard'; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="mb-0">Dashboard</h4>
            <a href="/pos" class="btn btn-primary btn-sm" target="_blank">
                <i data-lucide="shopping-cart" class="icon-sm me-1"></i> Open POS
            </a>
        </div>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Today's Sales</p>
                        <h4 class="mb-0">$<?= number_format((float)($todayData['total'] ?? 0), 2) ?></h4>
                        <small class="text-muted"><?= (int)($todayData['count'] ?? 0) ?> transactions</small>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(114,105,239,.12)">
                        <i data-lucide="trending-up" style="width:22px;height:22px;color:#7269ef"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Pending Sync</p>
                        <h4 class="mb-0"><?= (int)$pendingSync ?></h4>
                        <small class="text-muted">transactions</small>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:<?= $pendingSync > 0 ? 'rgba(247,184,75,.12)' : 'rgba(45,189,130,.12)' ?>">
                        <i data-lucide="cloud-upload" style="width:22px;height:22px;color:<?= $pendingSync > 0 ? '#f7b84b' : '#2dbd82' ?>"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Active Products</p>
                        <h4 class="mb-0"><?= (int)$productCount ?></h4>
                        <small class="text-muted"><a href="/admin/products">Manage</a></small>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(41,186,202,.12)">
                        <i data-lucide="cake-slice" style="width:22px;height:22px;color:#29baca"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Active Cashiers</p>
                        <h4 class="mb-0"><?= (int)$cashierCount ?></h4>
                        <small class="text-muted"><a href="/admin/users">Manage</a></small>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(239,107,137,.12)">
                        <i data-lucide="user-check" style="width:22px;height:22px;color:#ef6b89"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Expenses & Production cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Today's Expenses</p>
                        <h4 class="mb-0">$<?= number_format((float)($todayExpenses ?? 0), 2) ?></h4>
                        <small class="text-muted"><a href="/admin/expenses">View all</a></small>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(239,107,137,.12)">
                        <i data-lucide="receipt" style="width:22px;height:22px;color:#ef6b89"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Today's Production</p>
                        <h4 class="mb-0"><?= (int)($todayProduction ?? 0) ?> <small class="text-muted fw-normal">items</small></h4>
                        <small class="text-muted"><a href="/admin/production">View all</a></small>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(45,189,130,.12)">
                        <i data-lucide="factory" style="width:22px;height:22px;color:#2dbd82"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Low / Out of Stock</p>
                        <h4 class="mb-0 <?= ($lowStockCount ?? 0) > 0 ? 'text-warning' : '' ?>"><?= (int)($lowStockCount ?? 0) ?> <small class="text-muted fw-normal">items</small></h4>
                        <small class="text-muted"><a href="/admin/production">View stock</a></small>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:<?= ($lowStockCount ?? 0) > 0 ? 'rgba(247,184,75,.12)' : 'rgba(45,189,130,.12)' ?>">
                        <i data-lucide="alert-triangle" style="width:22px;height:22px;color:<?= ($lowStockCount ?? 0) > 0 ? '#f7b84b' : '#2dbd82' ?>"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Recent Transactions</h6>
                <a href="/admin/reports/daily" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Cashier</th>
                                <th>Method</th>
                                <th>Total</th>
                                <th>Sync</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No transactions today</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent as $txn): ?>
                            <tr>
                                <td class="fw-semibold text-primary" style="font-size:0.8rem">
                                    <?= htmlspecialchars($txn['transaction_ref'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><?= htmlspecialchars($txn['cashier_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($txn['payment_method']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="fw-semibold">$<?= number_format((float)$txn['total'], 2) ?></td>
                                <td>
                                    <?php if ($txn['sync_status'] === 'synced'): ?>
                                        <span class="admin-badge-synced">Synced</span>
                                    <?php elseif ($txn['sync_status'] === 'failed'): ?>
                                        <span class="admin-badge-failed">Failed</span>
                                    <?php else: ?>
                                        <span class="admin-badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted" style="font-size:0.8rem">
                                    <?= htmlspecialchars($txn['created_at'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Expenses Breakdown -->
<?php if (!empty($topExpenses)): ?>
<div class="row">
    <div class="col-md-6 grid-margin stretch-card">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Today's Expenses by Category</h6>
                <a href="/admin/expenses" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topExpenses as $exp): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($exp['name'] ?? 'Uncategorised', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end fw-semibold">$<?= number_format((float)$exp['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light">
                            <td class="fw-bold">Total</td>
                            <td class="text-end fw-bold">$<?= number_format((float)($todayExpenses ?? 0), 2) ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
