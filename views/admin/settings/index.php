<?php $pageTitle = 'Settings'; ?>

<div class="mb-4"><h4 class="mb-0">Settings</h4></div>

<form method="POST" action="/admin/settings/save">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="row g-4">
        <!-- Shop Branding -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">Shop Branding</h6></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Shop Name</label><input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($shop['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div class="mb-3"><label class="form-label">Address</label><input type="text" name="shop_address" class="form-control" value="<?= htmlspecialchars($shop['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="shop_phone" class="form-control" inputmode="tel" placeholder="+263 77 226 4471, +263 77 332 4050" value="<?= htmlspecialchars($shop['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <small class="text-muted">Use commas to save more than one phone number.</small>
                    </div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="shop_email" class="form-control" value="<?= htmlspecialchars($shop['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="row">
                        <div class="col">
                            <label class="form-label">Primary Colour</label>
                            <input type="color" name="primary_color" class="form-control form-control-color" value="<?= htmlspecialchars($shop['primary_color'] ?? '#E8631A', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col">
                            <label class="form-label">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($shop['currency_symbol'] ?? '$', ENT_QUOTES, 'UTF-8') ?>" maxlength="3">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt & Terminal -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">Receipt & Terminal</h6></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Receipt Header Message</label><input type="text" name="receipt_header" class="form-control" value="<?= htmlspecialchars($shop['receipt_header'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="mb-3">
                        <label class="form-label">Receipt Footer / Feedback Message</label>
                        <textarea name="receipt_footer" class="form-control" rows="3"><?= htmlspecialchars($shop['receipt_footer'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small class="text-muted">This prints at the bottom of the receipt. Use it for your thank-you note or a feedback message that points customers to WhatsApp or email.</small>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="receipt_auto_print" name="receipt_auto_print" <?= (int)($settings['receipt_auto_print'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="receipt_auto_print">Auto-print receipt after payment</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="receipt_printer_name">Windows Printer Queue Name</label>
                        <input
                            type="text"
                            id="receipt_printer_name"
                            name="receipt_printer_name"
                            class="form-control"
                            value="<?= htmlspecialchars($settings['receipt_printer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Leave blank to use the Windows default printer"
                        >
                        <small class="text-muted">Use the exact Windows printer name for this terminal, for example <code>XP-90</code>. Leave blank to print to the default Windows printer.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="receipt_open_drawer" name="receipt_open_drawer" <?= ($settings['receipt_open_drawer'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="receipt_open_drawer">Open cash drawer on cash and split receipts</label>
                    </div>
                    <div class="alert alert-light border mb-3" role="alert">
                        <strong>Receipt printing:</strong> BakeFlow sends receipts straight to the Windows printer on this terminal. If a queue name is saved above, BakeFlow uses that queue. If it is blank, BakeFlow uses the default Windows printer. Browser print is only used if the direct print helper fails.
                    </div>
                    <div class="mb-3"><label class="form-label">Terminal ID</label><input type="text" name="terminal_id" class="form-control" value="<?= htmlspecialchars($settings['terminal_id'] ?? 'TXN001', ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="mb-3"><label class="form-label">Auto-Logout (idle seconds)</label><input type="number" name="idle_timeout" class="form-control" value="<?= (int)($settings['idle_timeout'] ?? 600) ?>" min="60"></div>
                </div>
            </div>
        </div>

        <!-- Sync Settings -->
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Remote Sync</h6></div>
                <div class="card-body">
                    <p class="text-muted mb-3">Connect this terminal to a remote MySQL database so that sales, products and other data are pushed automatically. Leave the host blank to disable sync.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Remote DB Host</label>
                            <input type="text" name="remote_db_host" class="form-control" value="<?= htmlspecialchars($settings['remote_db_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. mysql.example.com">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Port</label>
                            <input type="number" name="remote_db_port" class="form-control" value="<?= htmlspecialchars($settings['remote_db_port'] ?? '3306', ENT_QUOTES, 'UTF-8') ?>" min="1" max="65535">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="remote_db_database" class="form-control" value="<?= htmlspecialchars($settings['remote_db_database'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" name="remote_db_username" class="form-control" value="<?= htmlspecialchars($settings['remote_db_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="remote_db_password" class="form-control" value="<?= htmlspecialchars($settings['remote_db_password'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sync API Key</label>
                            <input type="text" name="sync_api_key" class="form-control" value="<?= htmlspecialchars($settings['sync_api_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Optional">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Push Interval</label>
                            <select name="sync_interval" class="form-select">
                                <?php foreach ([
                                    60    => 'Every 1 minute',
                                    300   => 'Every 5 minutes',
                                    900   => 'Every 15 minutes',
                                    1800  => 'Every 30 minutes',
                                    3600  => 'Every 1 hour',
                                    7200  => 'Every 2 hours',
                                    21600 => 'Every 6 hours',
                                    43200 => 'Every 12 hours',
                                    0     => 'Manual only',
                                ] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($settings['sync_interval'] ?? '300') == $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">How often this terminal pushes data to the remote server.</small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Remote Server URL</label>
                            <input type="url" name="sync_remote_url" class="form-control" value="<?= htmlspecialchars($settings['sync_remote_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://system.example.com/api/sync">
                            <small class="text-muted">The URL of the remote BakeFlow instance. Sync pushes data here via HTTPS. If a direct MySQL connection is also configured, it is tried first.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Cake Deposit Amounts -->
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Cake Deposit Amounts</h6></div>
                <div class="card-body">
                    <p class="text-muted mb-3">Set the deposit amount required for each cake size. Customers pay the deposit at order time and the balance at pickup.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Base Price</th>
                                    <th>Deposit Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cakeSizes as $size): ?>
                                <tr>
                                    <td><?= htmlspecialchars($size['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($shop['currency_symbol'] ?? '$', ENT_QUOTES, 'UTF-8') ?><?= number_format((float)$size['price_base'], 2) ?></td>
                                    <td>
                                        <input type="number" name="cake_deposits[<?= (int)$size['id'] ?>]"
                                               class="form-control" step="0.01" min="0"
                                               max="<?= (float)$size['price_base'] ?>"
                                               value="<?= number_format((float)$size['deposit_amount'], 2, '.', '') ?>">
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

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
</form>

<form method="POST" action="/admin/settings/reset" id="systemResetForm" class="mt-4">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="card border-danger">
        <div class="card-header bg-danger-subtle">
            <h6 class="mb-0 text-danger">System Reset Tools</h6>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Use this to keep products, categories, users and settings, while removing selected operational data.
                If you enter a date, BakeFlow keeps records before that date and resets records on and after it.
            </p>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="checkbox" value="1" id="reset_transactions" name="reset_transactions">
                        <label class="form-check-label fw-semibold" for="reset_transactions">Remove POS transactions</label>
                        <div class="small text-muted mt-2">
                            Removes POS transactions, line items, linked cake orders, daily closings and sync log entries in the selected date range.
                            Sold stock is added back automatically unless you also zero stock below.
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="checkbox" value="1" id="reset_cake_orders" name="reset_cake_orders">
                        <label class="form-check-label fw-semibold" for="reset_cake_orders">Remove cake orders</label>
                        <div class="small text-muted mt-2">
                            Deletes cake order records in the selected date range, including orders that were not removed through the POS transaction reset.
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="checkbox" value="1" id="reset_expenses" name="reset_expenses">
                        <label class="form-check-label fw-semibold" for="reset_expenses">Remove expenses</label>
                        <div class="small text-muted mt-2">
                            Deletes expense records in the selected date range. Expense categories remain in place.
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-lg-4">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="checkbox" value="1" id="reset_production_entries" name="reset_production_entries">
                        <label class="form-check-label fw-semibold" for="reset_production_entries">Remove production history</label>
                        <div class="small text-muted mt-2">
                            Deletes production entry history in the selected date range. Current stock balances are not changed by this option.
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="checkbox" value="1" id="reset_stock_adjustments" name="reset_stock_adjustments">
                        <label class="form-check-label fw-semibold" for="reset_stock_adjustments">Remove stock adjustments</label>
                        <div class="small text-muted mt-2">
                            Deletes stock adjustment history in the selected date range. Current stock balances are not changed by this option.
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="checkbox" value="1" id="reset_stock_zero" name="reset_stock_zero">
                        <label class="form-check-label fw-semibold" for="reset_stock_zero">Reset stock to 0</label>
                        <div class="small text-muted mt-2">
                            Sets every product stock balance to zero immediately. This applies to current stock balances, not a historical stock date.
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <label class="form-label" for="reset_from_date">Reset From Date</label>
                    <input type="date" class="form-control" id="reset_from_date" name="reset_from_date" max="<?= date('Y-m-d') ?>">
                    <small class="text-muted">Leave blank to reset all matching records.</small>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="reset_reason">Operator Note</label>
                    <input type="text" class="form-control" id="reset_reason" value="This action is destructive. Take a database backup before using it." readonly>
                    <small class="text-muted">This tool does not remove products, categories, users, settings, cake sizes or other master data.</small>
                </div>
            </div>

            <div class="alert alert-warning d-flex align-items-start gap-2 mt-4 mb-0" role="alert">
                <i data-lucide="triangle-alert" class="icon-sm mt-1"></i>
                <div>
                    <strong>Backup first.</strong> This reset cannot be undone from the admin panel.
                    Make a MySQL backup before running it on a live shop database.
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-danger">Run Reset</button>
        </div>
    </div>
</form>

<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0">Current Operational Data Counts</h6>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            Refresh this page after running a reset to confirm exactly what still exists in the live database.
        </p>
        <div class="row g-3">
            <?php foreach ([
                'transactions' => 'POS Transactions',
                'transaction_items' => 'Transaction Items',
                'cake_orders' => 'Cake Orders',
                'expenses' => 'Expenses',
                'production_entries' => 'Production Entries',
                'stock_adjustments' => 'Stock Adjustments',
                'daily_closings' => 'Daily Closings',
                'sync_log' => 'Sync Log',
            ] as $key => $label): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="small text-muted text-uppercase"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="fs-4 fw-semibold mt-1"><?= (int)($operationalCounts[$key] ?? 0) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('systemResetForm');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function(event) {
        var options = [
            document.getElementById('reset_transactions'),
            document.getElementById('reset_cake_orders'),
            document.getElementById('reset_stock_zero'),
            document.getElementById('reset_expenses'),
            document.getElementById('reset_production_entries'),
            document.getElementById('reset_stock_adjustments')
        ].filter(function(input) { return input && input.checked; });

        if (options.length === 0) {
            event.preventDefault();
            bfAlert('Select at least one reset option.');
            return;
        }

        event.preventDefault();

        var labels = options.map(function(input) {
            var label = form.querySelector('label[for="' + input.id + '"]');
            return label ? label.textContent.trim() : input.name;
        });
        var resetDate = document.getElementById('reset_from_date').value;
        var scope = resetDate ? (' from ' + resetDate + ' onward') : ' for all dates';

        bfConfirm(
            'Run the following reset' + scope + ': ' + labels.join(', ') + '? Make sure you already have a backup.',
            function() { form.submit(); }
        );
    });
});
</script>
