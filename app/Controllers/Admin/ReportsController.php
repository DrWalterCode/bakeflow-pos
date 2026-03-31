<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\DayEndReportService;

class ReportsController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to'] ?? date('Y-m-d');
        $format = strtolower((string)($_GET['format'] ?? ''));

        $stmt = $db->prepare("
            SELECT DATE(t.created_at) AS sale_date,
                   COUNT(*) AS transactions,
                   COALESCE(SUM(t.total), 0) AS total_sales,
                   COALESCE(dc.status, 'open') AS closing_status
            FROM transactions t
            LEFT JOIN daily_closings dc ON dc.date = DATE(t.created_at)
            WHERE t.status = 'completed'
              AND DATE(t.created_at) BETWEEN ? AND ?
            GROUP BY DATE(t.created_at), COALESCE(dc.status, 'open')
            ORDER BY sale_date DESC
        ");
        $stmt->execute([$from, $to]);
        $dailySales = $stmt->fetchAll();

        if ($format === 'csv') {
            $rows = [['Date', 'Transactions', 'Total Sales', 'Status']];
            foreach ($dailySales as $row) {
                $rows[] = [
                    $row['sale_date'],
                    (int)$row['transactions'],
                    number_format((float)$row['total_sales'], 2, '.', ''),
                    ucfirst((string)$row['closing_status']),
                ];
            }
            $this->streamCsv('sales-summary-' . $from . '-to-' . $to . '.csv', $rows);
        }

        $currencySymbol = $this->currencySymbol($db);
        $isPrint = $format === 'print';

        View::render('admin.reports.index', compact('dailySales', 'from', 'to', 'currencySymbol', 'isPrint'), $isPrint ? '' : 'app');
    }

    public function daily(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();
        $service = new DayEndReportService($db);

        try {
            $date = $service->normaliseDate($_GET['date'] ?? date('Y-m-d'));
        } catch (\Throwable $e) {
            $date = date('Y-m-d');
        }

        $format = strtolower((string)($_GET['format'] ?? ''));
        $section = strtolower((string)($_GET['section'] ?? 'products'));
        $report = $service->buildReport($date, true);
        $currencySymbol = $this->currencySymbol($db);

        if ($format === 'csv') {
            $this->streamDailyCsv($report, $date, $section);
        }

        $isPrint = $format === 'print';
        $isAdmin = Auth::isAdmin();

        View::render('admin.reports.daily', compact('report', 'date', 'currencySymbol', 'isPrint', 'isAdmin'), $isPrint ? '' : 'app');
    }

    public function closeDay(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $service = new DayEndReportService();

        try {
            $date = $_POST['date'] ?? date('Y-m-d');
            $actualCash = (float)($_POST['actual_cash'] ?? 0);
            $notes = $_POST['notes'] ?? null;
            $service->closeDay($date, $actualCash, $notes, (int)(Auth::id() ?? 0));
            $this->redirect('/admin/reports/daily?date=' . urlencode($service->normaliseDate($date)), 'Day closed successfully.');
        } catch (\Throwable $e) {
            $this->redirect('/admin/reports/daily?date=' . urlencode((string)($_POST['date'] ?? date('Y-m-d'))), $e->getMessage(), 'error');
        }
    }

    public function reopenDay(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $service = new DayEndReportService();

        try {
            $date = $_POST['date'] ?? date('Y-m-d');
            $reason = $_POST['reopen_reason'] ?? null;
            $service->reopenDay($date, $reason, (int)(Auth::id() ?? 0));
            $this->redirect('/admin/reports/daily?date=' . urlencode($service->normaliseDate($date)), 'Day reopened successfully.');
        } catch (\Throwable $e) {
            $this->redirect('/admin/reports/daily?date=' . urlencode((string)($_POST['date'] ?? date('Y-m-d'))), $e->getMessage(), 'error');
        }
    }

    public function products(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to'] ?? date('Y-m-d');
        $format = strtolower((string)($_GET['format'] ?? ''));

        $stmt = $db->prepare("
            SELECT ti.product_name,
                   SUM(ti.quantity) AS total_qty,
                   SUM(ti.line_total) AS total_revenue
            FROM transaction_items ti
            INNER JOIN transactions t ON t.id = ti.transaction_id
            WHERE t.status = 'completed'
              AND DATE(t.created_at) BETWEEN ? AND ?
            GROUP BY ti.product_name
            ORDER BY total_revenue DESC, ti.product_name ASC
        ");
        $stmt->execute([$from, $to]);
        $productSales = $stmt->fetchAll();

        if ($format === 'csv') {
            $rows = [['Product', 'Qty Sold', 'Revenue']];
            foreach ($productSales as $row) {
                $rows[] = [
                    $row['product_name'],
                    (int)$row['total_qty'],
                    number_format((float)$row['total_revenue'], 2, '.', ''),
                ];
            }
            $this->streamCsv('product-sales-' . $from . '-to-' . $to . '.csv', $rows);
        }

        $currencySymbol = $this->currencySymbol($db);
        $isPrint = $format === 'print';

        View::render('admin.reports.products', compact('productSales', 'from', 'to', 'currencySymbol', 'isPrint'), $isPrint ? '' : 'app');
    }

    public function cashiers(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();

        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to'] ?? date('Y-m-d');
        $format = strtolower((string)($_GET['format'] ?? ''));

        $stmt = $db->prepare("
            SELECT u.name AS cashier_name,
                   COUNT(t.id) AS transactions,
                   COALESCE(SUM(t.total), 0) AS total_sales
            FROM transactions t
            INNER JOIN users u ON u.id = t.cashier_id
            WHERE t.status = 'completed'
              AND DATE(t.created_at) BETWEEN ? AND ?
            GROUP BY t.cashier_id, u.name
            ORDER BY total_sales DESC, u.name ASC
        ");
        $stmt->execute([$from, $to]);
        $cashierPerf = $stmt->fetchAll();

        if ($format === 'csv') {
            $rows = [['Cashier', 'Transactions', 'Total Sales']];
            foreach ($cashierPerf as $row) {
                $rows[] = [
                    $row['cashier_name'],
                    (int)$row['transactions'],
                    number_format((float)$row['total_sales'], 2, '.', ''),
                ];
            }
            $this->streamCsv('cashier-performance-' . $from . '-to-' . $to . '.csv', $rows);
        }

        $currencySymbol = $this->currencySymbol($db);
        $isPrint = $format === 'print';

        View::render('admin.reports.cashiers', compact('cashierPerf', 'from', 'to', 'currencySymbol', 'isPrint'), $isPrint ? '' : 'app');
    }

    private function currencySymbol(\PDO $db): string
    {
        $value = $db->query("SELECT currency_symbol FROM shops WHERE id = 1 LIMIT 1")->fetchColumn();
        return is_string($value) && $value !== '' ? $value : '$';
    }

    private function streamDailyCsv(array $report, string $date, string $section): void
    {
        if ($section === 'expenses') {
            $rows = [['Expense Date', 'Category', 'Description', 'Amount', 'Receipt Ref', 'Recorded By', 'Created At']];
            foreach ($report['expenses'] as $expense) {
                $rows[] = [
                    $expense['expense_date'] ?? $date,
                    $expense['category_name'] ?? '',
                    $expense['description'] ?? '',
                    number_format((float)($expense['amount'] ?? 0), 2, '.', ''),
                    $expense['receipt_ref'] ?? '',
                    $expense['recorded_by_name'] ?? '',
                    $expense['created_at'] ?? '',
                ];
            }
            $this->streamCsv('day-end-expenses-' . $date . '.csv', $rows);
        }

        if ($section === 'transactions') {
            $rows = [['Reference', 'Cashier', 'Payment Method', 'Total', 'Cash Portion', 'Created At', 'Sync Status']];
            foreach ($report['transactions'] as $transaction) {
                $rows[] = [
                    $transaction['transaction_ref'] ?? '',
                    $transaction['cashier_name'] ?? '',
                    strtoupper((string)($transaction['payment_method'] ?? '')),
                    number_format((float)($transaction['total'] ?? 0), 2, '.', ''),
                    number_format((float)($transaction['cash_portion'] ?? 0), 2, '.', ''),
                    $transaction['created_at'] ?? '',
                    $transaction['sync_status'] ?? '',
                ];
            }
            $this->streamCsv('day-end-transactions-' . $date . '.csv', $rows);
        }

        $rows = [[
            'Date',
            'Product',
            'Type',
            'Opening Stock',
            'Produced',
            'Sold',
            'Closing Stock',
            'Revenue',
        ]];

        foreach ($report['products'] as $product) {
            $rows[] = [
                $date,
                $product['product_name'],
                $product['is_cake'] ? 'cake' : 'stock',
                $product['opening_stock'] === null ? '' : (int)$product['opening_stock'],
                $product['produced_qty'] === null ? '' : (int)$product['produced_qty'],
                (int)$product['sold_qty'],
                $product['closing_stock'] === null ? '' : (int)$product['closing_stock'],
                number_format((float)$product['revenue'], 2, '.', ''),
            ];
        }

        $this->streamCsv('day-end-products-' . $date . '.csv', $rows);
    }

    private function streamCsv(string $filename, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            http_response_code(500);
            echo 'Failed to create CSV export.';
            exit;
        }

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
