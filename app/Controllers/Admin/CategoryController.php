<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\View;

class CategoryController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();
        $categories = $db->query(
            "SELECT * FROM categories ORDER BY sort_order, name"
        )->fetchAll();
        View::render('admin.categories.index', compact('categories'));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->redirect('/admin/categories', 'Category name is required.', 'error');
        }

        $db = Database::getConnection();

        $dupCheck = $db->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND is_active = 1");
        $dupCheck->execute([$name]);
        if ($dupCheck->fetch()) {
            $this->redirect('/admin/categories', 'A category with this name already exists.', 'error');
        }

        $db->prepare("INSERT INTO categories (name, color, sort_order, is_active) VALUES (?, ?, ?, ?)")
           ->execute([$name, trim($_POST['color'] ?? '#6c757d'), (int)($_POST['sort_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0]);
        $this->redirect('/admin/categories', 'Category added.');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->redirect('/admin/categories', 'Category name is required.', 'error');
        }

        $db = Database::getConnection();

        $dupCheck = $db->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND is_active = 1 AND id != ?");
        $dupCheck->execute([$name, $id]);
        if ($dupCheck->fetch()) {
            $this->redirect('/admin/categories', 'A category with this name already exists.', 'error');
        }

        $db->prepare("UPDATE categories SET name = ?, color = ?, sort_order = ?, is_active = ? WHERE id = ?")
           ->execute([$name, trim($_POST['color'] ?? '#6c757d'), (int)($_POST['sort_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0, $id]);
        $this->redirect('/admin/categories', 'Category updated.');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $db = Database::getConnection();
        $db->prepare("UPDATE categories SET is_active = 0 WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        $this->redirect('/admin/categories', 'Category deactivated.');
    }
}
