<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\SyncState;
use App\Core\View;
use App\Services\DatabaseBackupService;
use App\Services\SystemResetService;

class SettingsController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $rows = $db->query("SELECT `key`, value FROM settings")->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        $shop = $db->query("SELECT * FROM shops WHERE id = 1 LIMIT 1")->fetch();
        $cakeSizes = $db->query("SELECT id, name, price_base, deposit_amount FROM cake_sizes WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

        View::render('admin.settings.index', compact('settings', 'shop', 'cakeSizes'));
    }

    public function save(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $shopName = trim($_POST['shop_name'] ?? '');
        if ($shopName === '') {
            $this->redirect('/admin/settings', 'Shop name is required.', 'error');
        }

        if (isset($_POST['tax_rate'])) {
            $taxRate = (float)$_POST['tax_rate'];
            if ($taxRate < 0 || $taxRate > 100) {
                $this->redirect('/admin/settings', 'Tax rate must be between 0 and 100.', 'error');
            }
        }

        if (isset($_POST['idle_timeout'])) {
            $idleTimeout = (int)$_POST['idle_timeout'];
            if ($idleTimeout < 60) {
                $this->redirect('/admin/settings', 'Idle timeout must be at least 60 seconds.', 'error');
            }
        }

        $shopEmail = trim($_POST['shop_email'] ?? '');
        if ($shopEmail !== '' && !filter_var($shopEmail, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('/admin/settings', 'Invalid email address.', 'error');
        }

        $shopPhone = trim((string)($_POST['shop_phone'] ?? ''));
        if ($shopPhone !== '') {
            $shopPhone = preg_replace('/\s*,\s*/', ', ', $shopPhone) ?? $shopPhone;
        }

        $db = Database::getConnection();

        $db->prepare("UPDATE shops SET name = ?, address = ?, phone = ?, email = ?, receipt_header = ?, receipt_footer = ?, primary_color = ?, currency_symbol = ? WHERE id = 1")
           ->execute([
               $shopName,
               trim($_POST['shop_address'] ?? ''),
               $shopPhone,
               $shopEmail,
               trim($_POST['receipt_header'] ?? ''),
               trim($_POST['receipt_footer'] ?? ''),
               trim($_POST['primary_color'] ?? '#E8631A'),
               trim($_POST['currency_symbol'] ?? '$'),
           ]);

        $keys = [
            'terminal_id',
            'idle_timeout',
            'sync_interval',
            'sync_remote_url',
            'tax_rate',
            'receipt_printer_name',
            'remote_db_host',
            'remote_db_port',
            'remote_db_database',
            'remote_db_username',
            'remote_db_password',
            'sync_api_key',
        ];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $db->prepare("INSERT INTO settings (`key`, value, updated_at) VALUES (?, ?, NOW())
                              ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()")
                   ->execute([$key, trim($_POST[$key])]);
            }
        }

        $checkboxSettings = [
            'receipt_auto_print' => isset($_POST['receipt_auto_print']) ? '1' : '0',
            'receipt_open_drawer' => isset($_POST['receipt_open_drawer']) ? '1' : '0',
        ];
        foreach ($checkboxSettings as $key => $value) {
            $db->prepare("INSERT INTO settings (`key`, value, updated_at) VALUES (?, ?, NOW())
                          ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()")
               ->execute([$key, $value]);
        }

        if (isset($_POST['cake_deposits']) && is_array($_POST['cake_deposits'])) {
            foreach ($_POST['cake_deposits'] as $sizeId => $depositAmt) {
                $sizeId = (int)$sizeId;
                $depositAmt = max(0.0, (float)$depositAmt);

                $priceRow = $db->prepare("SELECT price_base FROM cake_sizes WHERE id = ?");
                $priceRow->execute([$sizeId]);
                $priceRow = $priceRow->fetch();
                if ($priceRow) {
                    $depositAmt = min($depositAmt, (float)$priceRow['price_base']);
                    $db->prepare("UPDATE cake_sizes SET deposit_amount = ? WHERE id = ?")
                       ->execute([$depositAmt, $sizeId]);
                }
            }
        }

        SyncState::markDirty($db, ['shops', 'settings', 'cake_sizes']);
        $this->redirect('/admin/settings', 'Settings saved.');
    }

    public function backupDatabase(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $returnUrl = $this->resolveReturnUrl();

        try {
            $service = new DatabaseBackupService(Database::getConnection());
            $result = $service->createBackup();
        } catch (\Throwable $e) {
            $this->redirect($returnUrl, 'Database backup failed: ' . $e->getMessage(), 'error');
        }

        $message = sprintf(
            'Database backup created: %s',
            $result['path']
        );

        $this->redirect($returnUrl, $message);
    }

    public function resetSystem(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $options = [
            'reset_transactions' => isset($_POST['reset_transactions']),
            'reset_cake_orders' => isset($_POST['reset_cake_orders']),
            'reset_stock_zero' => isset($_POST['reset_stock_zero']),
            'reset_expenses' => isset($_POST['reset_expenses']),
            'reset_production_entries' => isset($_POST['reset_production_entries']),
            'reset_stock_adjustments' => isset($_POST['reset_stock_adjustments']),
        ];

        if (
            !$options['reset_transactions']
            && !$options['reset_cake_orders']
            && !$options['reset_stock_zero']
            && !$options['reset_expenses']
            && !$options['reset_production_entries']
            && !$options['reset_stock_adjustments']
        ) {
            $this->redirect('/admin/settings', 'Select at least one reset option.', 'error');
        }

        $db = Database::getConnection();
        $service = new SystemResetService();

        try {
            $summary = $service->run($db, $options, trim((string)($_POST['reset_from_date'] ?? '')));
        } catch (\Throwable $e) {
            $this->redirect('/admin/settings', 'System reset failed: ' . $e->getMessage(), 'error');
        }

        $parts = [];
        if ($options['reset_transactions']) {
            $parts[] = $summary['transactions_deleted'] . ' POS transactions';
        }
        if ($options['reset_cake_orders'] || $options['reset_transactions']) {
            $parts[] = $summary['cake_orders_deleted'] . ' cake orders';
        }
        if ($options['reset_expenses']) {
            $parts[] = $summary['expenses_deleted'] . ' expenses';
        }
        if ($options['reset_production_entries']) {
            $parts[] = $summary['production_entries_deleted'] . ' production entries';
        }
        if ($options['reset_stock_adjustments']) {
            $parts[] = $summary['stock_adjustments_deleted'] . ' stock adjustments';
        }
        if ($options['reset_stock_zero']) {
            $parts[] = $summary['stock_rows_zeroed'] . ' stock rows zeroed';
        } elseif ($options['reset_transactions'] && (int)$summary['stock_units_restocked'] > 0) {
            $parts[] = $summary['stock_units_restocked'] . ' stock units restored';
        }

        $scope = $summary['reset_from_date'] !== null
            ? ' from ' . $summary['reset_from_date'] . ' onward'
            : ' for all dates';

        $message = 'System reset completed' . $scope . ': ' . implode(', ', $parts) . '.';
        $this->redirect('/admin/settings', $message);
    }

    private function resolveReturnUrl(): string
    {
        $fallback = '/admin/settings';
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return $fallback;
        }

        $parts = parse_url($referer);
        $path = (string)($parts['path'] ?? '');
        if ($path === '' || !str_starts_with($path, '/admin')) {
            return $fallback;
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return $path . $query;
    }
}
