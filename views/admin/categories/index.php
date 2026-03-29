<?php $pageTitle = 'Categories'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Categories</h4>
    <button class="btn btn-primary btn-icon-text" data-bs-toggle="modal" data-bs-target="#addCatModal">
        <i data-lucide="plus" class="icon-sm me-1"></i> Add Category
    </button>
</div>

<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bf-table" id="categories-table">
                        <thead><tr><th>Name</th><th>Colour</th><th>Sort</th><th>Status</th><th class="no-sort no-search"></th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:<?= htmlspecialchars($cat['color'], ENT_QUOTES, 'UTF-8') ?>;margin-right:8px"></span>
                                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><?= htmlspecialchars($cat['color'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int)$cat['sort_order'] ?></td>
                                <td><?= $cat['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="editCat(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i data-lucide="edit-3" class="icon-sm"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                        onclick="deleteCat(<?= (int)$cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>')">
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

<!-- Add Modal -->
<div class="modal fade" id="addCatModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="/admin/categories/store">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-header"><h5 class="modal-title">Add Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Colour</label><input type="color" name="color" class="form-control form-control-color" value="#E8631A"></div>
                <div class="mb-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" checked><label class="form-check-label">Active</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
        </form>
    </div></div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCatModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="/admin/categories/update">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="ec-id">
            <div class="modal-header"><h5 class="modal-title">Edit Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" id="ec-name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Colour</label><input type="color" name="color" id="ec-color" class="form-control form-control-color"></div>
                <div class="mb-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="ec-sort" class="form-control"></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="ec-active"><label class="form-check-label">Active</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>

<form id="del-cat-form" method="POST" action="/admin/categories/delete" style="display:none">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" id="del-cat-id">
</form>

<?php $pageScripts = <<<'JS'
<script>
function editCat(c) {
    document.getElementById('ec-id').value    = c.id;
    document.getElementById('ec-name').value  = c.name;
    document.getElementById('ec-color').value = c.color;
    document.getElementById('ec-sort').value  = c.sort_order;
    document.getElementById('ec-active').checked = c.is_active == 1;
    new bootstrap.Modal(document.getElementById('editCatModal')).show();
}
function deleteCat(id, name) {
    bfConfirm('Deactivate category: ' + name + '?', function() {
        document.getElementById('del-cat-id').value = id;
        document.getElementById('del-cat-form').submit();
    });
}
</script>
JS; ?>
