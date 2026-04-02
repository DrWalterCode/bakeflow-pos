<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\SyncState;
use App\Core\View;

class ProductionController extends BaseController
{
    private const STOCK_ADJUSTMENT_REASONS = [
        'increase' => [
            'production_count_correction' => 'Production count correction',
            'stock_recount_gain' => 'Stock recount gain',
            'returned_to_stock' => 'Returned to stock',
            'cancelled_sale_reversal' => 'Cancelled sale reversal',
            'customer_return_restocked' => 'Customer return restocked',
            'supplier_replacement' => 'Supplier replacement received',
            'packaging_rework_recovered' => 'Recovered after packaging rework',
            'miscount_correction_increase' => 'Miscount correction',
            'transfer_in' => 'Transfer in from another branch',
            'opening_balance' => 'Opening balance / initial stock',
            'other_increase' => 'Other increase',
        ],
        'decrease' => [
            'stale_product' => 'Product went stale',
            'expired_product' => 'Product expired',
            'broken_product' => 'Product broken',
            'damaged_product' => 'Damaged during handling',
            'burnt_or_failed_batch' => 'Burnt or failed batch',
            'undercooked_or_quality_failed' => 'Undercooked / quality failed',
            'contamination' => 'Contamination / unsafe to sell',
            'stock_recount_loss' => 'Stock recount loss',
            'miscount_correction_decrease' => 'Miscount correction',
            'spillage_or_drop' => 'Spillage / dropped item',
            'packaging_damage' => 'Packaging damage',
            'display_waste' => 'Display waste',
            'sample_or_tasting' => 'Sample / tasting',
            'staff_consumption' => 'Staff consumption',
            'complimentary_or_donation' => 'Complimentary / donation',
            'customer_return_waste' => 'Customer return not resellable',
            'theft_or_missing' => 'Theft / missing stock',
            'transfer_out' => 'Transfer out to another branch',
            'quality_recall' => 'Quality recall / contamination',
            'other_decrease' => 'Other decrease',
        ],
    ];

    public function index(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $entries = $db->query("
            SELECT pe.*, p.name AS product_name, c.name AS category_name, u.name AS produced_by_name
            FROM production_entries pe
            LEFT JOIN products p ON p.id = pe.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN users u ON u.id = pe.produced_by
            ORDER BY pe.production_date DESC, pe.created_at DESC
        ")->fetchAll();

        $products = $db->query(
            "SELECT p.id, p.name, p.stock_quantity, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.is_cake = 0
             ORDER BY c.sort_order, p.sort_order, p.name"
        )->fetchAll();

        $stockLevels = $db->query("
            SELECT p.id, p.name, p.stock_quantity, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1 AND p.is_cake = 0
            ORDER BY c.sort_order, p.sort_order, p.name
        ")->fetchAll();

        $stockAdjustments = $db->query("
            SELECT sa.*, p.name AS product_name, c.name AS category_name, u.name AS adjusted_by_name
            FROM stock_adjustments sa
            INNER JOIN products p ON p.id = sa.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN users u ON u.id = sa.adjusted_by
            ORDER BY sa.created_at DESC, sa.id DESC
            LIMIT 100
        ")->fetchAll();

        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM production_entries WHERE production_date = ?");
        $stmt->execute([$today]);
        $todayProduction = (int)$stmt->fetchColumn();

        $stockAdjustmentReasons = self::STOCK_ADJUSTMENT_REASONS;

        View::render('admin.production.index', compact(
            'entries',
            'products',
            'stockLevels',
            'stockAdjustments',
            'stockAdjustmentReasons',
            'todayProduction'
        ));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $db = Database::getConnection();
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $productionDate = $_POST['production_date'] ?? '';

        if ($quantity < 1) {
            $this->redirect('/admin/production', 'Quantity must be at least 1.', 'error');
        }
        if ($productionDate === '') {
            $productionDate = date('Y-m-d');
        }
        if ($productionDate > date('Y-m-d')) {
            $this->redirect('/admin/production', 'Production date cannot be in the future.', 'error');
        }

        $prodCheck = $db->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
        $prodCheck->execute([$productId]);
        if (!$prodCheck->fetch()) {
            $this->redirect('/admin/production', 'Invalid product selected.', 'error');
        }

        $db->beginTransaction();
        try {
            $db->prepare("
                INSERT INTO production_entries (product_id, quantity, produced_by, batch_ref, notes, production_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $productId,
                $quantity,
                Auth::id(),
                trim($_POST['batch_ref'] ?? '') ?: null,
                trim($_POST['notes'] ?? '') ?: null,
                $productionDate,
            ]);

            $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")
               ->execute([$quantity, $productId]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $this->redirect('/admin/production', 'Failed to record production: ' . $e->getMessage(), 'error');
            return;
        }

        SyncState::markDirty($db, ['production_entries', 'products']);
        $this->redirect('/admin/production', 'Production entry recorded - stock updated.');
    }

    public function adjustStock(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $db = Database::getConnection();
        $productId = (int)($_POST['product_id'] ?? 0);
        $adjustmentType = trim((string)($_POST['adjustment_type'] ?? ''));
        $quantity = (int)($_POST['adjustment_quantity'] ?? 0);
        $reasonCode = trim((string)($_POST['reason_code'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!isset(self::STOCK_ADJUSTMENT_REASONS[$adjustmentType])) {
            $this->redirect('/admin/production', 'Select whether the stock adjustment is an increase or decrease.', 'error');
        }

        if ($quantity < 1) {
            $this->redirect('/admin/production', 'Adjustment quantity must be at least 1.', 'error');
        }

        $reasonLabel = self::STOCK_ADJUSTMENT_REASONS[$adjustmentType][$reasonCode] ?? null;
        if ($reasonLabel === null) {
            $this->redirect('/admin/production', 'Select a valid stock adjustment reason.', 'error');
        }

        if (str_starts_with($reasonCode, 'other_') && $notes === '') {
            $this->redirect('/admin/production', 'Add details when selecting Other as the stock adjustment reason.', 'error');
        }

        $stmt = $db->prepare("
            SELECT id, name, stock_quantity
            FROM products
            WHERE id = ? AND is_active = 1 AND is_cake = 0
            LIMIT 1
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $this->redirect('/admin/production', 'Invalid product selected for stock adjustment.', 'error');
        }

        $previousQuantity = (int)$product['stock_quantity'];
        $newQuantity = $adjustmentType === 'increase'
            ? $previousQuantity + $quantity
            : $previousQuantity - $quantity;

        if ($newQuantity < 0) {
            $this->redirect('/admin/production', 'Stock adjustment cannot reduce stock below zero.', 'error');
        }

        $db->beginTransaction();
        try {
            $db->prepare("
                INSERT INTO stock_adjustments (
                    product_id,
                    adjustment_type,
                    quantity,
                    reason_code,
                    reason_label,
                    notes,
                    previous_quantity,
                    new_quantity,
                    adjusted_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $productId,
                $adjustmentType,
                $quantity,
                $reasonCode,
                $reasonLabel,
                $notes !== '' ? $notes : null,
                $previousQuantity,
                $newQuantity,
                Auth::id(),
            ]);

            $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")
               ->execute([$newQuantity, $productId]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $this->redirect('/admin/production', 'Failed to adjust stock: ' . $e->getMessage(), 'error');
            return;
        }

        SyncState::markDirty($db, ['products', 'stock_adjustments']);

        $verb = $adjustmentType === 'increase' ? 'increased' : 'reduced';
        $this->redirect(
            '/admin/production',
            sprintf('Stock %s by %d for %s.', $verb, $quantity, (string)$product['name'])
        );
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $db = Database::getConnection();
        $id = (int)$_POST['id'];

        $stmt = $db->prepare("SELECT product_id, quantity FROM production_entries WHERE id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch();

        if (!$entry) {
            $this->redirect('/admin/production', 'Production entry not found.', 'error');
            return;
        }

        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM production_entries WHERE id = ?")->execute([$id]);
            $db->prepare("UPDATE products SET stock_quantity = MAX(0, stock_quantity - ?) WHERE id = ?")
               ->execute([(int)$entry['quantity'], (int)$entry['product_id']]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $this->redirect('/admin/production', 'Failed to delete entry: ' . $e->getMessage(), 'error');
            return;
        }

        SyncState::markDirty($db, ['production_entries', 'products']);
        $this->redirect('/admin/production', 'Production entry deleted - stock adjusted.');
    }
}
