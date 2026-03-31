<?php
$pageTitle = 'Cashier Performance';
$printQuery = http_build_query(['from' => $from, 'to' => $to, 'format' => 'print']);
$csvQuery = http_build_query(['from' => $from, 'to' => $to, 'format' => 'csv']);
?>
<?php if (!empty($isPrint)): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Performance</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
        h1 { margin-bottom: 8px; }
        .report-meta { margin-bottom: 16px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0">Cashier Performance</h4>
        <?php if (!empty($isPrint)): ?>
        <div class="report-meta">Period: <?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
    <?php if (empty($isPrint)): ?>
    <div class="d-flex gap-2">
        <a href="/admin/reports/cashiers?<?= htmlspecialchars($csvQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary">Download CSV</a>
        <a href="/admin/reports/cashiers?<?= htmlspecialchars($printQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary" target="_blank">Print / Save PDF</a>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($isPrint)): ?>
<form method="GET" class="row g-3 mb-4">
    <div class="col-auto"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>" onkeydown="return false"></div>
    <div class="col-auto"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>" onkeydown="return false"></div>
    <div class="col-auto align-self-end"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
</form>
<?php endif; ?>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0 <?= empty($isPrint) ? 'bf-table' : '' ?>" id="cashier-perf-table">
        <thead><tr><th>Cashier</th><th>Transactions</th><th>Total Sales</th></tr></thead>
        <tbody>
        <?php foreach ($cashierPerf as $row): ?>
        <tr>
            <td class="fw-semibold"><?= htmlspecialchars($row['cashier_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$row['transactions'] ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float)$row['total_sales'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>

<?php if (!empty($isPrint)): ?>
</body>
</html>
<?php endif; ?>
