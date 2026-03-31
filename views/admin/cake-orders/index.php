<?php $pageTitle = 'Cake Orders'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Cake Orders</h4>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Pending</p>
                        <h4 class="mb-0"><?= $summary['pending'] ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(108,117,125,.12)">
                        <i data-lucide="clock" style="width:22px;height:22px;color:#6c757d"></i>
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
                        <p class="text-muted small mb-1">In Production</p>
                        <h4 class="mb-0"><?= $summary['in_production'] ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(13,110,253,.12)">
                        <i data-lucide="chef-hat" style="width:22px;height:22px;color:#0d6efd"></i>
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
                        <p class="text-muted small mb-1">Ready for Collection</p>
                        <h4 class="mb-0"><?= $summary['ready'] ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(25,135,84,.12)">
                        <i data-lucide="check-circle" style="width:22px;height:22px;color:#198754"></i>
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
                        <p class="text-muted small mb-1">Total Orders</p>
                        <h4 class="mb-0"><?= $totalOrders ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(232,99,26,.12)">
                        <i data-lucide="cake" style="width:22px;height:22px;color:#E8631A"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter buttons -->
<div class="mb-3">
    <div class="btn-group" role="group">
        <a href="/admin/cake-orders" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
        <a href="/admin/cake-orders?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">Pending</a>
        <a href="/admin/cake-orders?status=in_production" class="btn btn-sm <?= $statusFilter === 'in_production' ? 'btn-primary' : 'btn-outline-secondary' ?>">In Production</a>
        <a href="/admin/cake-orders?status=ready" class="btn btn-sm <?= $statusFilter === 'ready' ? 'btn-primary' : 'btn-outline-secondary' ?>">Ready</a>
        <a href="/admin/cake-orders?status=collected" class="btn btn-sm <?= $statusFilter === 'collected' ? 'btn-primary' : 'btn-outline-secondary' ?>">Collected</a>
    </div>
</div>

<!-- Orders table -->
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bf-table" id="cake-orders-table">
                        <thead>
                            <tr>
                                <th>Order Date</th>
                                <th>Pickup Date</th>
                                <th>Customer</th>
                                <th>Cake Details</th>
                                <th>Inscription</th>
                                <th>Price</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="no-sort no-search">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['order_date'] ?? $order['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($order['pickup_date']): ?>
                                        <strong><?= date('d M Y', strtotime($order['pickup_date'])) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['customer_name']): ?>
                                        <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($order['customer_phone']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(($order['size_name'] ?? '') . ' ' . ($order['flavour_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($order['shape'] === 'square'): ?>
                                        <br><small class="text-muted">Square</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['inscription']): ?>
                                        <em>"<?= htmlspecialchars($order['inscription'], ENT_QUOTES, 'UTF-8') ?>"</em>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    $<?= number_format((float)$order['full_price'], 2) ?>
                                    <?php if ((float)($order['additional_cost'] ?? 0) > 0): ?>
                                        <br><small class="text-muted">Extras: $<?= number_format((float)$order['additional_cost'], 2) ?></small>
                                    <?php endif; ?>
                                    <?php if ((float)$order['balance_due'] > 0): ?>
                                        <br><small class="text-warning fw-bold">Bal: $<?= number_format((float)$order['balance_due'], 2) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $payBadge = match($order['payment_status']) {
                                        'deposit' => 'bg-warning text-dark',
                                        'partial' => 'bg-info',
                                        'paid'    => 'bg-success',
                                        default   => 'bg-secondary',
                                    };
                                    $payLabel = match($order['payment_status']) {
                                        'deposit' => 'Deposit',
                                        'partial' => 'Partial',
                                        'paid'    => 'Paid',
                                        default   => $order['payment_status'],
                                    };
                                    ?>
                                    <span class="badge <?= $payBadge ?>"><?= $payLabel ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($order['order_status']) {
                                        'pending'        => 'bg-secondary',
                                        'in_production'  => 'bg-info',
                                        'ready'          => 'bg-success',
                                        'collected'      => 'bg-dark',
                                        default          => 'bg-secondary',
                                    };
                                    $statusLabel = match($order['order_status']) {
                                        'pending'        => 'Pending',
                                        'in_production'  => 'In Production',
                                        'ready'          => 'Ready',
                                        'collected'      => 'Collected',
                                        default          => $order['order_status'],
                                    };
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-nowrap">
                                        <button type="button"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="Print Slip"
                                           onclick="printCakeProductionSlip(<?= (int)$order['id'] ?>, '/admin/cake-orders/print-slip?id=<?= (int)$order['id'] ?>', this)">
                                            <i data-lucide="printer" style="width:14px;height:14px"></i>
                                        </button>

                                        <?php if ($order['order_status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-info text-white" title="Start Production"
                                                onclick="startProduction(<?= (int)$order['id'] ?>, '<?= htmlspecialchars(($order['size_name'] ?? '') . ' ' . ($order['flavour_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
                                            <i data-lucide="play" style="width:14px;height:14px"></i>
                                        </button>
                                        <?php endif; ?>

                                        <?php if ($order['order_status'] === 'in_production'): ?>
                                        <button type="button" class="btn btn-sm btn-success" title="Mark Ready"
                                                onclick="markReady(<?= (int)$order['id'] ?>, '<?= htmlspecialchars(($order['size_name'] ?? '') . ' ' . ($order['flavour_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
                                            <i data-lucide="check" style="width:14px;height:14px"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for status transitions -->
<form id="start-production-form" method="POST" action="/admin/cake-orders/start-production" style="display:none">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" id="start-production-id">
</form>
<form id="mark-ready-form" method="POST" action="/admin/cake-orders/mark-ready" style="display:none">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" id="mark-ready-id">
</form>

<?php $pageScripts = <<<'JS'
<script>
function startProduction(id, details) {
    bfConfirm('Start production for <strong>' + details + '</strong>?', function() {
        document.getElementById('start-production-id').value = id;
        document.getElementById('start-production-form').submit();
    });
}

function markReady(id, details) {
    bfConfirm('Mark <strong>' + details + '</strong> as ready for collection?', function() {
        document.getElementById('mark-ready-id').value = id;
        document.getElementById('mark-ready-form').submit();
    });
}

async function printCakeProductionSlip(id, fallbackUrl, button) {
    if (button) {
        button.disabled = true;
    }

    try {
        var response = await fetch('/api/print/cake-order-slip', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ cake_order_id: id })
        });

        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Printer did not accept the production slip.');
        }

        bfAlert('Production slip printed' + (data.printer_name ? ' on ' + data.printer_name : '') + '.');
    } catch (error) {
        console.error('Cake production slip print failed:', error);
        bfAlert('Direct print failed. Opening the browser print page instead.');
        window.location.assign(fallbackUrl);
    } finally {
        if (button) {
            button.disabled = false;
        }
    }
}
</script>
JS;
?>
