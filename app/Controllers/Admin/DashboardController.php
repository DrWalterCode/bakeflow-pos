<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\View;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();

        $db = Database::getConnection();

        // Today's summary
        $today = date('Y-m-d');

        $todaySales = $db->prepare("
            SELECT COUNT(*) AS count, COALESCE(SUM(total), 0) AS total
            FROM transactions
            WHERE DATE(created_at) = ? AND status = 'completed'
        ");
        $todaySales->execute([$today]);
        $todayData = $todaySales->fetch();

        $pendingSync = (int)$db->query(
            "SELECT COUNT(*) FROM transactions WHERE sync_status = 'pending'"
        )->fetchColumn();

        $productCount = (int)$db->query(
            "SELECT COUNT(*) FROM products WHERE is_active = 1"
        )->fetchColumn();

        $cashierCount = (int)$db->query(
            "SELECT COUNT(*) FROM users WHERE role = 'cashier' AND is_active = 1"
        )->fetchColumn();

        // Today's expenses
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = ?");
        $stmt->execute([$today]);
        $todayExpenses = (float)$stmt->fetchColumn();

        // Low stock count (non-cake products with stock <= 5)
        $lowStockCount = (int)$db->query(
            "SELECT COUNT(*) FROM products WHERE is_active = 1 AND is_cake = 0 AND stock_quantity <= 5"
        )->fetchColumn();

        // Today's production
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM production_entries WHERE production_date = ?");
        $stmt->execute([$today]);
        $todayProduction = (int)$stmt->fetchColumn();

        // Recent transactions (last 10)
        $recent = $db->query("
            SELECT t.*, u.name AS cashier_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.cashier_id
            ORDER BY t.created_at DESC
            LIMIT 10
        ")->fetchAll();

        // Today's expenses by category
        $todayExpensesByCategory = $db->prepare("
            SELECT ec.name, COALESCE(SUM(e.amount), 0) AS total
            FROM expenses e
            LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
            WHERE e.expense_date = ?
            GROUP BY ec.name
            ORDER BY total DESC
            LIMIT 5
        ");
        $todayExpensesByCategory->execute([$today]);
        $topExpenses = $todayExpensesByCategory->fetchAll();

        View::render('admin.dashboard', compact(
            'todayData', 'pendingSync', 'productCount', 'cashierCount', 'recent',
            'todayExpenses', 'lowStockCount', 'todayProduction', 'topExpenses'
        ));
    }
}
