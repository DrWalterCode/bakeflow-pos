<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\SyncState;
use App\Core\View;

class ExpenseController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $expenses = $db->query("
            SELECT e.*, ec.name AS category_name, u.name AS recorded_by_name
            FROM expenses e
            LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
            LEFT JOIN users u ON u.id = e.recorded_by
            ORDER BY e.expense_date DESC, e.created_at DESC
        ")->fetchAll();

        $categories = $db->query(
            "SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name"
        )->fetchAll();

        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = ?");
        $stmt->execute([$today]);
        $todayExpenses = (float)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date >= ?");
        $stmt->execute([$weekStart]);
        $weekExpenses = (float)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date >= ?");
        $stmt->execute([$monthStart]);
        $monthExpenses = (float)$stmt->fetchColumn();

        View::render('admin.expenses.index', compact(
            'expenses', 'categories', 'todayExpenses', 'weekExpenses', 'monthExpenses'
        ));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $description = trim($_POST['description'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $expenseDate = $_POST['expense_date'] ?? '';
        $categoryId  = (int)($_POST['expense_category_id'] ?? 0);

        if ($description === '') {
            $this->redirect('/admin/expenses', 'Description is required.', 'error');
        }
        if ($amount <= 0) {
            $this->redirect('/admin/expenses', 'Amount must be greater than zero.', 'error');
        }
        if ($expenseDate === '') {
            $expenseDate = date('Y-m-d');
        }
        if ($expenseDate > date('Y-m-d')) {
            $this->redirect('/admin/expenses', 'Expense date cannot be in the future.', 'error');
        }

        $db = Database::getConnection();

        $catCheck = $db->prepare("SELECT id FROM expense_categories WHERE id = ? AND is_active = 1");
        $catCheck->execute([$categoryId]);
        if (!$catCheck->fetch()) {
            $this->redirect('/admin/expenses', 'Invalid expense category.', 'error');
        }

        $db->prepare("
            INSERT INTO expenses (expense_category_id, description, amount, expense_date, recorded_by, receipt_ref, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $categoryId,
            $description,
            $amount,
            $expenseDate,
            Auth::id(),
            trim($_POST['receipt_ref'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
        ]);

        SyncState::markDirty($db, 'expenses');
        $this->redirect('/admin/expenses', 'Expense recorded successfully.');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id          = (int)($_POST['id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $expenseDate = $_POST['expense_date'] ?? '';
        $categoryId  = (int)($_POST['expense_category_id'] ?? 0);

        if ($description === '') {
            $this->redirect('/admin/expenses', 'Description is required.', 'error');
        }
        if ($amount <= 0) {
            $this->redirect('/admin/expenses', 'Amount must be greater than zero.', 'error');
        }
        if ($expenseDate === '') {
            $expenseDate = date('Y-m-d');
        }
        if ($expenseDate > date('Y-m-d')) {
            $this->redirect('/admin/expenses', 'Expense date cannot be in the future.', 'error');
        }

        $db = Database::getConnection();

        $exists = $db->prepare("SELECT id FROM expenses WHERE id = ?");
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            $this->redirect('/admin/expenses', 'Expense not found.', 'error');
        }

        $db->prepare("
            UPDATE expenses
            SET expense_category_id = ?, description = ?, amount = ?,
                expense_date = ?, receipt_ref = ?, notes = ?
            WHERE id = ?
        ")->execute([
            $categoryId,
            $description,
            $amount,
            $expenseDate,
            trim($_POST['receipt_ref'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
            $id,
        ]);

        SyncState::markDirty($db, 'expenses');
        $this->redirect('/admin/expenses', 'Expense updated successfully.');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $db = Database::getConnection();
        $db->prepare("DELETE FROM expenses WHERE id = ?")->execute([(int)$_POST['id']]);

        SyncState::markDirty($db, 'expenses');
        $this->redirect('/admin/expenses', 'Expense deleted.');
    }
}
