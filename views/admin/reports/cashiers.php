<?php $pageTitle = 'Cashier Performance'; ?>
<div class="d-flex align-items-center justify-content-between mb-4"><h4 class="mb-0">Cashier Performance</h4></div>
<form method="GET" class="row g-3 mb-4">
    <div class="col-auto"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>" onkeydown="return false"></div>
    <div class="col-auto"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>" onkeydown="return false"></div>
    <div class="col-auto align-self-end"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
</form>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0 bf-table" id="cashier-perf-table">
        <thead><tr><th>Cashier</th><th>Transactions</th><th>Total Sales</th></tr></thead>
        <tbody>
        <?php foreach ($cashierPerf as $row): ?>
        <tr>
            <td class="fw-semibold"><?= htmlspecialchars($row['cashier_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$row['transactions'] ?></td>
            <td class="fw-semibold">$<?= number_format((float)$row['total_sales'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
