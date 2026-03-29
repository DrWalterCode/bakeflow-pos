<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\View;

class ReportsController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? date('Y-m-d');

        // Daily totals
        $stmt = $db->prepare("
            SELECT DATE(created_at) AS sale_date,
                   COUNT(*) AS transactions,
                   SUM(total) AS total_sales
            FROM transactions
            WHERE status = 'completed'
              AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY sale_date DESC
        ");
        $stmt->execute([$from, $to]);
        $dailySales = $stmt->fetchAll();

        View::render('admin.reports.index', compact('dailySales', 'from', 'to'));
    }

    public function daily(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $date = $_GET['date'] ?? date('Y-m-d');

        $stmt = $db->prepare("
            SELECT t.*, u.name AS cashier_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.cashier_id
            WHERE DATE(t.created_at) = ? AND t.status = 'completed'
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$date]);
        $transactions = $stmt->fetchAll();

        $summary = $db->prepare("
            SELECT COUNT(*) AS count, COALESCE(SUM(total),0) AS total,
                   COALESCE(SUM(CASE WHEN payment_method='cash'   THEN total ELSE 0 END), 0) AS cash_total,
                   COALESCE(SUM(CASE WHEN payment_method='card'   THEN total ELSE 0 END), 0) AS card_total,
                   COALESCE(SUM(CASE WHEN payment_method='mobile' THEN total ELSE 0 END), 0) AS mobile_total,
                   COALESCE(SUM(CASE WHEN payment_method='split'  THEN total ELSE 0 END), 0) AS split_total
            FROM transactions
            WHERE DATE(created_at) = ? AND status = 'completed'
        ");
        $summary->execute([$date]);
        $daySummary = $summary->fetch();

        View::render('admin.reports.daily', compact('transactions', 'daySummary', 'date'));
    }

    public function products(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? date('Y-m-d');

        $stmt = $db->prepare("
            SELECT ti.product_name,
                   SUM(ti.quantity) AS total_qty,
                   SUM(ti.line_total) AS total_revenue
            FROM transaction_items ti
            INNER JOIN transactions t ON t.id = ti.transaction_id
            WHERE t.status = 'completed'
              AND DATE(t.created_at) BETWEEN ? AND ?
            GROUP BY ti.product_name
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$from, $to]);
        $productSales = $stmt->fetchAll();

        View::render('admin.reports.products', compact('productSales', 'from', 'to'));
    }

    public function cashiers(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? date('Y-m-d');

        $stmt = $db->prepare("
            SELECT u.name AS cashier_name,
                   COUNT(t.id) AS transactions,
                   COALESCE(SUM(t.total), 0) AS total_sales
            FROM transactions t
            INNER JOIN users u ON u.id = t.cashier_id
            WHERE t.status = 'completed'
              AND DATE(t.created_at) BETWEEN ? AND ?
            GROUP BY t.cashier_id, u.name
            ORDER BY total_sales DESC
        ");
        $stmt->execute([$from, $to]);
        $cashierPerf = $stmt->fetchAll();

        View::render('admin.reports.cashiers', compact('cashierPerf', 'from', 'to'));
    }
}
