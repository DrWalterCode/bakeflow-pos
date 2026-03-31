<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\View;

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

        // Save shop info
        $db->prepare("UPDATE shops SET name = ?, address = ?, phone = ?, email = ?, receipt_header = ?, receipt_footer = ?, primary_color = ?, currency_symbol = ? WHERE id = 1")
           ->execute([
               $shopName,
               trim($_POST['shop_address']    ?? ''),
               $shopPhone,
               $shopEmail,
               trim($_POST['receipt_header']  ?? ''),
               trim($_POST['receipt_footer']  ?? ''),
               trim($_POST['primary_color']   ?? '#E8631A'),
               trim($_POST['currency_symbol'] ?? '$'),
           ]);

        // Save settings
        $keys = [
            'terminal_id',
            'idle_timeout',
            'sync_interval',
            'sync_remote_url',
            'tax_rate',
            'receipt_printer_name',
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

        // Save cake deposit amounts
        if (isset($_POST['cake_deposits']) && is_array($_POST['cake_deposits'])) {
            foreach ($_POST['cake_deposits'] as $sizeId => $depositAmt) {
                $sizeId    = (int)$sizeId;
                $depositAmt = max(0.0, (float)$depositAmt);

                // Ensure deposit does not exceed the base price
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

        $this->redirect('/admin/settings', 'Settings saved.');
    }
}
