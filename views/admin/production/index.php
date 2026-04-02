<?php $pageTitle = 'Production & Stock'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Production & Stock</h4>
    <button class="btn btn-primary btn-icon-text" data-bs-toggle="modal" data-bs-target="#addProductionModal">
        <i data-lucide="plus" class="icon-sm me-1"></i> Record Production
    </button>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Today's Production</p>
                        <h4 class="mb-0"><?= (int)$todayProduction ?> <small class="text-muted fw-normal">items</small></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(45,189,130,.12)">
                        <i data-lucide="factory" style="width:22px;height:22px;color:#2dbd82"></i>
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
                        <p class="text-muted small mb-1">Products Tracked</p>
                        <h4 class="mb-0"><?= count($stockLevels) ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(41,186,202,.12)">
                        <i data-lucide="package" style="width:22px;height:22px;color:#29baca"></i>
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
                        <p class="text-muted small mb-1">Low Stock</p>
                        <?php $lowStock = array_filter($stockLevels, fn($p) => (int)$p['stock_quantity'] <= 5 && (int)$p['stock_quantity'] > 0); ?>
                        <h4 class="mb-0"><?= count($lowStock) ?> <small class="text-muted fw-normal">items</small></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(247,184,75,.12)">
                        <i data-lucide="alert-triangle" style="width:22px;height:22px;color:#f7b84b"></i>
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
                        <p class="text-muted small mb-1">Out of Stock</p>
                        <?php $outOfStock = array_filter($stockLevels, fn($p) => (int)$p['stock_quantity'] === 0); ?>
                        <h4 class="mb-0 <?= count($outOfStock) > 0 ? 'text-danger' : '' ?>"><?= count($outOfStock) ?></h4>
                    </div>
                    <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:<?= count($outOfStock) > 0 ? 'rgba(239,107,137,.12)' : 'rgba(45,189,130,.12)' ?>">
                        <i data-lucide="package-x" style="width:22px;height:22px;color:<?= count($outOfStock) > 0 ? '#ef6b89' : '#2dbd82' ?>"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Current Stock Levels -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h6 class="mb-0">Current Stock Levels</h6>
                    <small class="text-muted">Adjust stock per product with a required reason and quantity.</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bf-table" id="stock-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>In Stock</th>
                                <th>Status</th>
                                <th class="no-sort no-search">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stockLevels as $p): ?>
                            <?php $qty = (int)$p['stock_quantity']; ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['category_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="fw-semibold"><?= $qty ?></td>
                                <td>
                                    <?php if ($qty === 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($qty <= 5): ?>
                                        <span class="badge bg-warning text-dark">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#adjustStockModal"
                                        data-product-id="<?= (int)$p['id'] ?>"
                                        data-product-name="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-current-stock="<?= $qty ?>"
                                        title="Adjust stock"
                                    >
                                        <i data-lucide="pencil" class="icon-sm me-1"></i>
                                        Adjust
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

<!-- Stock Adjustment History -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Recent Stock Adjustments</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bf-table" id="stock-adjustments-table" data-order-col="0" data-order-dir="desc">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Change</th>
                                <th>Reason</th>
                                <th>Previous</th>
                                <th>New</th>
                                <th>Adjusted By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stockAdjustments as $adjustment): ?>
                            <?php
                                $isIncrease = ($adjustment['adjustment_type'] ?? '') === 'increase';
                                $signedQuantity = ($isIncrease ? '+' : '-') . (int)$adjustment['quantity'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$adjustment['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($adjustment['product_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($adjustment['category_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge <?= $isIncrease ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $signedQuantity ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($adjustment['reason_label'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int)($adjustment['previous_quantity'] ?? 0) ?></td>
                                <td><?= (int)($adjustment['new_quantity'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($adjustment['adjusted_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-muted" style="font-size:0.8rem;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                    title="<?= htmlspecialchars($adjustment['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($adjustment['notes'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
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

<!-- Production History -->
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Production History</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bf-table" id="production-table" data-order-col="0" data-order-dir="desc">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Batch Ref</th>
                                <th>Produced By</th>
                                <th>Notes</th>
                                <th class="no-sort no-search"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['production_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($e['product_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($e['category_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="fw-semibold"><?= (int)$e['quantity'] ?></td>
                                <td class="text-muted" style="font-size:0.8rem"><?= htmlspecialchars($e['batch_ref'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($e['produced_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-muted" style="font-size:0.8rem;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                    title="<?= htmlspecialchars($e['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($e['notes'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger"
                                        onclick="deleteEntry(<?= (int)$e['id'] ?>, '<?= htmlspecialchars($e['product_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>', <?= (int)$e['quantity'] ?>)"
                                        title="Delete (reverses stock)">
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

<!-- Add Production Modal -->
<div class="modal fade" id="addProductionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/production/store">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Record Production</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>">
                                    <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                                    (<?= htmlspecialchars($p['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                                    - Stock: <?= (int)$p['stock_quantity'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity Produced</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Production Date</label>
                            <input type="date" name="production_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required onkeydown="return false">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batch Reference (optional)</label>
                        <input type="text" name="batch_ref" class="form-control" placeholder="e.g. BATCH-001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Morning batch, extra large muffins"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Production</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/production/adjust" id="adjust-stock-form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="product_id" id="adjust-product-id">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="adjust-product-name" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" class="form-control" id="adjust-current-stock" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Adjustment Type</label>
                            <select class="form-select" name="adjustment_type" id="adjustment-type" required>
                                <option value="decrease">Decrease stock</option>
                                <option value="increase">Increase stock</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="adjustment_quantity" id="adjustment-quantity" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <select class="form-select" name="reason_code" id="adjustment-reason" required></select>
                        <div class="form-text">Reasons update automatically based on whether stock is increasing or decreasing.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Details</label>
                        <textarea class="form-control" name="notes" id="adjustment-notes" rows="3" placeholder="Add context for the adjustment. Required if you choose Other."></textarea>
                    </div>
                    <div class="rounded-3 border px-3 py-2 bg-light">
                        <div class="small text-muted mb-1">Adjustment Preview</div>
                        <div class="fw-semibold" id="adjustment-preview">Current stock will update here.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="adjust-stock-submit">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete form -->
<form id="delete-form" method="POST" action="/admin/production/delete" style="display:none">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" id="delete-id">
</form>

<?php
$stockAdjustmentReasonsJson = json_encode(
    $stockAdjustmentReasons,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$pageScripts = <<<JS
<script>
var stockAdjustmentReasons = {$stockAdjustmentReasonsJson};

function deleteEntry(id, product, qty) {
    bfConfirm('Delete production entry for ' + qty + 'x ' + product + '? This will reverse the stock addition.', function() {
        document.getElementById('delete-id').value = id;
        document.getElementById('delete-form').submit();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('adjustStockModal');
    if (!modal) {
        return;
    }

    var productIdInput = document.getElementById('adjust-product-id');
    var productNameInput = document.getElementById('adjust-product-name');
    var currentStockInput = document.getElementById('adjust-current-stock');
    var typeSelect = document.getElementById('adjustment-type');
    var quantityInput = document.getElementById('adjustment-quantity');
    var reasonSelect = document.getElementById('adjustment-reason');
    var notesInput = document.getElementById('adjustment-notes');
    var preview = document.getElementById('adjustment-preview');
    var submitButton = document.getElementById('adjust-stock-submit');

    function renderReasonOptions(selectedType) {
        var reasons = stockAdjustmentReasons[selectedType] || {};
        var options = ['<option value="">Select reason...</option>'];
        Object.keys(reasons).forEach(function(code) {
            options.push('<option value="' + code + '">' + reasons[code] + '</option>');
        });
        reasonSelect.innerHTML = options.join('');
        updateNotesRequirement();
    }

    function updateNotesRequirement() {
        var requiresNotes = reasonSelect.value.indexOf('other_') === 0;
        notesInput.required = requiresNotes;
        notesInput.placeholder = requiresNotes
            ? 'Add details for the Other reason.'
            : 'Add context for the adjustment. Required if you choose Other.';
    }

    function updatePreview() {
        var currentStock = parseInt(currentStockInput.value || '0', 10);
        var quantity = parseInt(quantityInput.value || '0', 10);
        var type = typeSelect.value;

        if (isNaN(quantity) || quantity < 1) {
            preview.textContent = 'Enter a quantity of at least 1.';
            preview.className = 'fw-semibold text-danger';
            submitButton.disabled = true;
            return;
        }

        var newStock = type === 'increase' ? currentStock + quantity : currentStock - quantity;
        if (newStock < 0) {
            preview.textContent = 'This reduction would take stock below zero.';
            preview.className = 'fw-semibold text-danger';
            submitButton.disabled = true;
            return;
        }

        var signedQuantity = (type === 'increase' ? '+' : '-') + quantity;
        preview.textContent = 'Current ' + currentStock + ' -> New ' + newStock + ' (' + signedQuantity + ')';
        preview.className = 'fw-semibold ' + (type === 'increase' ? 'text-success' : 'text-danger');
        submitButton.disabled = false;
    }

    modal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        if (!button) {
            return;
        }

        var currentStock = parseInt(button.getAttribute('data-current-stock') || '0', 10);
        var defaultType = currentStock === 0 ? 'increase' : 'decrease';

        productIdInput.value = button.getAttribute('data-product-id') || '';
        productNameInput.value = button.getAttribute('data-product-name') || '';
        currentStockInput.value = currentStock;
        typeSelect.value = defaultType;
        quantityInput.value = '1';
        notesInput.value = '';

        renderReasonOptions(defaultType);
        updatePreview();
    });

    typeSelect.addEventListener('change', function() {
        renderReasonOptions(this.value);
        updatePreview();
    });

    quantityInput.addEventListener('input', updatePreview);
    reasonSelect.addEventListener('change', updateNotesRequirement);
});
</script>
JS;
?>
