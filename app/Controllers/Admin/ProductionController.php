<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class ProductionController extends BaseController
{
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

        // Current stock levels
        $stockLevels = $db->query("
            SELECT p.id, p.name, p.stock_quantity, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1 AND p.is_cake = 0
            ORDER BY c.sort_order, p.sort_order, p.name
        ")->fetchAll();

        // Today's production total
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM production_entries WHERE production_date = ?");
        $stmt->execute([$today]);
        $todayProduction = (int)$stmt->fetchColumn();

        View::render('admin.production.index', compact('entries', 'products', 'stockLevels', 'todayProduction'));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $db = Database::getConnection();
        $productId      = (int)($_POST['product_id'] ?? 0);
        $quantity        = (int)($_POST['quantity'] ?? 0);
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

            // Increment stock
            $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")
               ->execute([$quantity, $productId]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $this->redirect('/admin/production', 'Failed to record production: ' . $e->getMessage(), 'error');
            return;
        }

        $this->redirect('/admin/production', 'Production entry recorded — stock updated.');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $db = Database::getConnection();
        $id = (int)$_POST['id'];

        // Fetch entry to reverse stock
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

            // Decrement stock (floor at 0)
            $db->prepare("UPDATE products SET stock_quantity = MAX(0, stock_quantity - ?) WHERE id = ?")
               ->execute([(int)$entry['quantity'], (int)$entry['product_id']]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $this->redirect('/admin/production', 'Failed to delete entry: ' . $e->getMessage(), 'error');
            return;
        }

        $this->redirect('/admin/production', 'Production entry deleted — stock adjusted.');
    }
}
