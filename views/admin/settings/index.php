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
                <div class="card-header"><h6 class="mb-0">Sync Settings</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Sync Interval (seconds)</label>
                            <select name="sync_interval" class="form-select">
                                <?php foreach ([60=>'1 min', 300=>'5 min', 900=>'15 min', 1800=>'30 min', 0=>'Manual only'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($settings['sync_interval'] ?? '300') == $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Remote Server URL (for sync)</label>
                            <input type="url" name="sync_remote_url" class="form-control" value="<?= htmlspecialchars($settings['sync_remote_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://yourserver.com/api/sync">
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Configure remote MySQL credentials in your <code>.env</code> file.</small>
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
