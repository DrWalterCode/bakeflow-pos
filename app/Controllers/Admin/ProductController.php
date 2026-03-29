<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\View;

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

        $name       = trim($_POST['name'] ?? '');
        $price      = (float)($_POST['price'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);

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

        $db->prepare("
            INSERT INTO products (category_id, name, description, price, barcode, is_active, is_cake, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $categoryId,
            $name,
            trim($_POST['description'] ?? ''),
            $price,
            trim($_POST['barcode'] ?? '') ?: null,
            isset($_POST['is_active']) ? 1 : 0,
            isset($_POST['is_cake'])   ? 1 : 0,
            (int)($_POST['sort_order'] ?? 0),
        ]);

        $this->redirect('/admin/products', 'Product added successfully.');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id         = (int)($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $price      = (float)($_POST['price'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);

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

        $db->prepare("
            UPDATE products
            SET category_id = ?, name = ?, description = ?, price = ?,
                barcode = ?, is_active = ?, is_cake = ?, sort_order = ?
            WHERE id = ?
        ")->execute([
            $categoryId,
            $name,
            trim($_POST['description'] ?? ''),
            $price,
            trim($_POST['barcode'] ?? '') ?: null,
            isset($_POST['is_active']) ? 1 : 0,
            isset($_POST['is_cake'])   ? 1 : 0,
            (int)($_POST['sort_order'] ?? 0),
            $id,
        ]);

        $this->redirect('/admin/products', 'Product updated successfully.');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $db = Database::getConnection();
        $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?")
           ->execute([(int)$_POST['id']]);

        $this->redirect('/admin/products', 'Product deactivated.');
    }
}
