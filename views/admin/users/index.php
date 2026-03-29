<?php $pageTitle = 'Users'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0">Users</h4>
    <button class="btn btn-primary btn-icon-text" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i data-lucide="plus" class="icon-sm me-1"></i> Add User
    </button>
</div>

<div class="alert alert-info">
    <strong>Default credentials:</strong> Admin login: <code>admin</code> / <code>admin</code> &nbsp;|&nbsp;
    Cashier PIN: <code>1234</code>. Change these immediately.
</div>

<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 bf-table" id="users-table">
                        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th><th class="no-sort no-search"></th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $u['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>"><?= ucfirst($u['role']) ?></span></td>
                                <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                                <td class="text-muted" style="font-size:0.8rem"><?= $u['last_login_at'] ? htmlspecialchars($u['last_login_at'], ENT_QUOTES, 'UTF-8') : 'Never' ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i data-lucide="edit-3" class="icon-sm"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>')">
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="/admin/users/store">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-header"><h5 class="modal-title">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-select"><option value="cashier">Cashier</option><option value="admin">Admin</option></select></div>
                <div class="mb-3"><label class="form-label">PIN (cashiers, 4–6 digits)</label><input type="password" name="pin" class="form-control" minlength="4" maxlength="6" pattern="\d{4,6}" placeholder="e.g. 1234" title="PIN must be 4 to 6 digits"></div>
                <div class="mb-3"><label class="form-label">Password (admin users, min 6 chars)</label><input type="password" name="password" class="form-control" minlength="6" placeholder="Leave blank for cashiers"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add User</button></div>
        </form>
    </div></div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="/admin/users/update">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="eu-id">
            <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" id="eu-name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Role</label><select name="role" id="eu-role" class="form-select"><option value="cashier">Cashier</option><option value="admin">Admin</option></select></div>
                <div class="mb-3 form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="eu-active"><label class="form-check-label">Active</label></div>
                <div class="mb-3"><label class="form-label">New PIN (leave blank to keep current)</label><input type="password" name="pin" class="form-control" minlength="4" maxlength="6" pattern="\d{4,6}" title="PIN must be 4 to 6 digits"></div>
                <div class="mb-3"><label class="form-label">New Password (leave blank to keep current)</label><input type="password" name="password" class="form-control" minlength="6"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>

<form id="del-user-form" method="POST" action="/admin/users/delete" style="display:none">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" id="del-user-id">
</form>

<?php $pageScripts = <<<'JS'
<script>
function editUser(u) {
    document.getElementById('eu-id').value       = u.id;
    document.getElementById('eu-name').value     = u.name;
    document.getElementById('eu-role').value     = u.role;
    document.getElementById('eu-active').checked = u.is_active == 1;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
function deleteUser(id, name) {
    bfConfirm('Deactivate user: ' + name + '?', function() {
        document.getElementById('del-user-id').value = id;
        document.getElementById('del-user-form').submit();
    });
}
</script>
JS; ?>
