<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\SyncState;
use App\Core\View;
use PDO;

class ProductController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $products = $db->query("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            ORDER BY c.sort_order, p.sort_order, p.name
        ")->fetchAll();

        $categories = $db->query(
            "SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name"
        )->fetchAll();

        View::render('admin.products.index', compact('products', 'categories'));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $isQuickItem = isset($_POST['is_quick_item']) ? 1 : 0;
        $quickItemOrder = max(0, (int)($_POST['quick_item_order'] ?? 0));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') {
            $this->redirect('/admin/products', 'Product name is required.', 'error');
        }
        if ($price < 0) {
            $this->redirect('/admin/products', 'Price cannot be negative.', 'error');
        }

        $db = Database::getConnection();

        $catCheck = $db->prepare("SELECT id FROM categories WHERE id = ? AND is_active = 1");
        $catCheck->execute([$categoryId]);
        if (!$catCheck->fetch()) {
            $this->redirect('/admin/products', 'Invalid category selected.', 'error');
        }

        $dupCheck = $db->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND is_active = 1");
        $dupCheck->execute([$name]);
        if ($dupCheck->fetch()) {
            $this->redirect('/admin/products', 'A product with this name already exists.', 'error');
        }

        $db->beginTransaction();

        try {
            $quickItemOrder = $this->prepareQuickItemOrder($db, $isQuickItem === 1, $quickItemOrder);

            $db->prepare("
                INSERT INTO products (category_id, name, description, price, barcode, is_active, is_cake, is_quick_item, quick_item_order, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $categoryId,
                $name,
                trim($_POST['description'] ?? ''),
                $price,
                trim($_POST['barcode'] ?? '') ?: null,
                isset($_POST['is_active']) ? 1 : 0,
                isset($_POST['is_cake']) ? 1 : 0,
                $isQuickItem,
                $quickItemOrder,
                $sortOrder,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        SyncState::markDirty($db, 'products');
        $this->redirect('/admin/products', 'Product added successfully.');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $isQuickItem = isset($_POST['is_quick_item']) ? 1 : 0;
        $quickItemOrder = max(0, (int)($_POST['quick_item_order'] ?? 0));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') {
            $this->redirect('/admin/products', 'Product name is required.', 'error');
        }
        if ($price < 0) {
            $this->redirect('/admin/products', 'Price cannot be negative.', 'error');
        }

        $db = Database::getConnection();

        $dupCheck = $db->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND is_active = 1 AND id != ?");
        $dupCheck->execute([$name, $id]);
        if ($dupCheck->fetch()) {
            $this->redirect('/admin/products', 'A product with this name already exists.', 'error');
        }

        $db->beginTransaction();

        try {
            $quickItemOrder = $this->prepareQuickItemOrder($db, $isQuickItem === 1, $quickItemOrder, $id);

            $db->prepare("
                UPDATE products
                SET category_id = ?, name = ?, description = ?, price = ?,
                    barcode = ?, is_active = ?, is_cake = ?, is_quick_item = ?, quick_item_order = ?, sort_order = ?
                WHERE id = ?
            ")->execute([
                $categoryId,
                $name,
                trim($_POST['description'] ?? ''),
                $price,
                trim($_POST['barcode'] ?? '') ?: null,
                isset($_POST['is_active']) ? 1 : 0,
                isset($_POST['is_cake']) ? 1 : 0,
                $isQuickItem,
                $quickItemOrder,
                $sortOrder,
                $id,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        SyncState::markDirty($db, 'products');
        $this->redirect('/admin/products', 'Product updated successfully.');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $db = Database::getConnection();
        $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?")
           ->execute([(int)$_POST['id']]);

        SyncState::markDirty($db, 'products');
        $this->redirect('/admin/products', 'Product deactivated.');
    }

    private function prepareQuickItemOrder(PDO $db, bool $isQuickItem, int $requestedOrder, ?int $excludeId = null): int
    {
        if (!$isQuickItem) {
            return 0;
        }

        if ($excludeId !== null) {
            $currentStmt = $db->prepare('SELECT is_quick_item, quick_item_order FROM products WHERE id = ?');
            $currentStmt->execute([$excludeId]);
            $current = $currentStmt->fetch();

            if ($current && (int)$current['is_quick_item'] === 1 && (int)$current['quick_item_order'] === $requestedOrder && $requestedOrder > 0) {
                return $requestedOrder;
            }
        }

        if ($requestedOrder <= 0) {
            $maxStmt = $db->prepare(
                'SELECT COALESCE(MAX(quick_item_order), 0) FROM products WHERE is_quick_item = 1'
                . ($excludeId !== null ? ' AND id != ?' : '')
            );

            $params = [];
            if ($excludeId !== null) {
                $params[] = $excludeId;
            }

            $maxStmt->execute($params);

            return ((int)$maxStmt->fetchColumn()) + 1;
        }

        $sql = '
            UPDATE products
            SET quick_item_order = quick_item_order + 1
            WHERE is_quick_item = 1
              AND quick_item_order >= ?
        ';
        $params = [$requestedOrder];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $db->prepare($sql)->execute($params);

        return $requestedOrder;
    }
}
