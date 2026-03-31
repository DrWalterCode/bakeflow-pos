<?php $pageTitle = 'Products'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Products</h4>
    <button class="btn btn-primary btn-icon-text" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i data-lucide="plus" class="icon-sm me-1"></i> Add Product
    </button>
</div>

<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bf-table" id="products-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Quick Access</th>
                                <th>Cake?</th>
                                <th>Status</th>
                                <th class="no-sort no-search"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $p['price'] > 0 ? '$' . number_format((float)$p['price'], 2) : '<span class="text-muted">TBD</span>' ?></td>
                                <td>
                                    <?php if ((int)($p['is_quick_item'] ?? 0) === 1): ?>
                                        <span class="badge bg-warning text-dark">Yes<?= (int)($p['quick_item_order'] ?? 0) > 0 ? ' #' . (int)$p['quick_item_order'] : '' ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $p['is_cake'] ? '<span class="badge bg-info">Yes</span>' : '—' ?></td>
                                <td><?= $p['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="editProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)"
                                        title="Edit">
                                        <i data-lucide="edit-3" class="icon-sm"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                        onclick="deleteProduct(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>')"
                                        title="Deactivate">
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/products/store">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price ($)</label>
                        <input type="number" name="price" class="form-control" min="0" step="0.01" value="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Barcode (optional)</label>
                        <input type="text" name="barcode" class="form-control" placeholder="Leave blank if none">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_quick_item" id="addQuickItem">
                                <label class="form-check-label" for="addQuickItem">Show in Quick Access</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quick Access Order</label>
                            <input type="number" name="quick_item_order" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="addActive" checked>
                                <label class="form-check-label" for="addActive">Active</label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_cake" id="addIsCake">
                                <label class="form-check-label" for="addIsCake">Is Cake (custom order)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/products/update">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="edit-category" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="edit-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price ($)</label>
                        <input type="number" name="price" id="edit-price" class="form-control" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="edit-barcode" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="edit-sort" class="form-control" value="0">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_quick_item" id="edit-quick-item">
                                <label class="form-check-label" for="edit-quick-item">Show in Quick Access</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quick Access Order</label>
                            <input type="number" name="quick_item_order" id="edit-quick-order" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit-active">
                                <label class="form-check-label" for="edit-active">Active</label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_cake" id="edit-cake">
                                <label class="form-check-label" for="edit-cake">Is Cake</label>
                            </div>
                        </div>
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
<form id="delete-form" method="POST" action="/admin/products/delete" style="display:none">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" id="delete-id">
</form>

<?php $pageScripts = <<<'JS'
<script>
function editProduct(p) {
    document.getElementById('edit-id').value       = p.id;
    document.getElementById('edit-category').value = p.category_id;
    document.getElementById('edit-name').value     = p.name;
    document.getElementById('edit-price').value    = p.price;
    document.getElementById('edit-barcode').value  = p.barcode || '';
    document.getElementById('edit-sort').value     = p.sort_order;
    document.getElementById('edit-quick-item').checked = p.is_quick_item == 1;
    document.getElementById('edit-quick-order').value  = p.quick_item_order || 0;
    document.getElementById('edit-active').checked = p.is_active == 1;
    document.getElementById('edit-cake').checked   = p.is_cake == 1;
    new bootstrap.Modal(document.getElementById('editProductModal')).show();
}
function deleteProduct(id, name) {
    bfConfirm('Deactivate product: ' + name + '?', function() {
        document.getElementById('delete-id').value = id;
        document.getElementById('delete-form').submit();
    });
}
</script>
JS; ?>
