<?php $pageTitle = 'Expenses'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Expenses</h4>
    <button class="btn btn-primary btn-icon-text" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
        <i data-lucide="plus" class="icon-sm me-1"></i> Record Expense
    </button>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Today</p>
                        <h4 class="mb-0">$<?= number_format((float)($todayExpenses ?? 0), 2) ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(239,107,137,.12)">
                        <i data-lucide="receipt" style="width:22px;height:22px;color:#ef6b89"></i>
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
                        <p class="text-muted small mb-1">This Week</p>
                        <h4 class="mb-0">$<?= number_format((float)($weekExpenses ?? 0), 2) ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(114,105,239,.12)">
                        <i data-lucide="calendar" style="width:22px;height:22px;color:#7269ef"></i>
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
                        <p class="text-muted small mb-1">This Month</p>
                        <h4 class="mb-0">$<?= number_format((float)($monthExpenses ?? 0), 2) ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(247,184,75,.12)">
                        <i data-lucide="trending-up" style="width:22px;height:22px;color:#f7b84b"></i>
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
                        <p class="text-muted small mb-1">Total Records</p>
                        <h4 class="mb-0"><?= count($expenses) ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(45,189,130,.12)">
                        <i data-lucide="file-text" style="width:22px;height:22px;color:#2dbd82"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bf-table" id="expenses-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Receipt Ref</th>
                                <th>Recorded By</th>
                                <th class="no-sort no-search"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['expense_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($e['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="fw-semibold"><?= htmlspecialchars($e['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="fw-semibold">$<?= number_format((float)$e['amount'], 2) ?></td>
                                <td class="text-muted" style="font-size:0.8rem"><?= htmlspecialchars($e['receipt_ref'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($e['recorded_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="editExpense(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>)"
                                        title="Edit">
                                        <i data-lucide="edit-3" class="icon-sm"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                        onclick="deleteExpense(<?= (int)$e['id'] ?>, '<?= htmlspecialchars($e['description'], ENT_QUOTES, 'UTF-8') ?>')"
                                        title="Delete">
                                        <i data-lucide="trash-2" class="icon-sm"></i>
                                    </button>
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

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/expenses/store">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Record Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="expense_category_id" class="form-select" required>
                            <option value="">Select category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. 10kg sugar, 25kg flour" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount ($)</label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required onkeydown="return false">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Receipt / Reference (optional)</label>
                        <input type="text" name="receipt_ref" class="form-control" placeholder="Invoice or receipt number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional details"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/expenses/update">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="expense_category_id" id="edit-category" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" id="edit-description" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount ($)</label>
                            <input type="number" name="amount" id="edit-amount" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="expense_date" id="edit-date" class="form-control" max="<?= date('Y-m-d') ?>" required onkeydown="return false">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Receipt / Reference</label>
                        <input type="text" name="receipt_ref" id="edit-receipt" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="edit-notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete form -->
<form id="delete-form" method="POST" action="/admin/expenses/delete" style="display:none">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" id="delete-id">
</form>

<?php $pageScripts = <<<'JS'
<script>
function editExpense(e) {
    document.getElementById('edit-id').value          = e.id;
    document.getElementById('edit-category').value    = e.expense_category_id;
    document.getElementById('edit-description').value = e.description;
    document.getElementById('edit-amount').value      = e.amount;
    document.getElementById('edit-date').value        = e.expense_date;
    document.getElementById('edit-receipt').value     = e.receipt_ref || '';
    document.getElementById('edit-notes').value       = e.notes || '';
    new bootstrap.Modal(document.getElementById('editExpenseModal')).show();
}
function deleteExpense(id, desc) {
    bfConfirm('Delete expense: ' + desc + '?', function() {
        document.getElementById('delete-id').value = id;
        document.getElementById('delete-form').submit();
    });
}
</script>
JS; ?>
