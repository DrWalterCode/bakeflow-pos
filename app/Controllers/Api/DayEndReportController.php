<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Database;
use App\Lib\PdfWriter;
use App\Services\DayEndReportService;

class DayEndReportController extends BaseController
{
    public function show(): void
    {
        $this->requireAuth();

        try {
            $service = new DayEndReportService();
            $date = $_GET['date'] ?? date('Y-m-d');
            $report = $service->buildReport($date, true);

            $this->json([
                'success' => true,
                'report'  => $report,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }

    public function close(): void
    {
        $this->requireAdmin();
        $this->verifyJsonCsrf();

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];

        try {
            $service = new DayEndReportService();
            $report = $service->closeDay(
                $input['date'] ?? date('Y-m-d'),
                (float)($input['actual_cash'] ?? 0),
                isset($input['notes']) ? (string)$input['notes'] : null,
                (int)(Auth::id() ?? 0)
            );

            $this->json([
                'success' => true,
                'status'  => 'closed',
                'message' => 'Day closed successfully.',
                'closure' => $report['closure'],
                'report'  => $report,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }

    public function reopen(): void
    {
        $this->requireAdmin();
        $this->verifyJsonCsrf();

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];

        try {
            $service = new DayEndReportService();
            $report = $service->reopenDay(
                $input['date'] ?? date('Y-m-d'),
                isset($input['reason']) ? (string)$input['reason'] : null,
                (int)(Auth::id() ?? 0)
            );

            $this->json([
                'success' => true,
                'status'  => 'open',
                'message' => 'Day reopened successfully.',
                'closure' => $report['closure'],
                'report'  => $report,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }

    public function download(): void
    {
        $this->requireAuth();

        $format = $_GET['format'] ?? 'pdf';
        $date   = $_GET['date'] ?? date('Y-m-d');

        try {
            $service = new DayEndReportService();
            $report  = $service->buildReport($date, true);

            $db   = Database::getConnection();
            $shop = $db->query("SELECT * FROM shops WHERE id = 1 LIMIT 1")->fetch();
            $shopName = $shop['name'] ?? 'BakeFlow';
            $currency = $shop['currency_symbol'] ?? '$';

            if ($format === 'excel') {
                $content  = $this->generateExcel($report, $shopName, $currency, $date);
                $ext      = 'xls';
                $mime     = 'application/vnd.ms-excel';
            } else {
                $content  = $this->generatePdf($report, $shopName, $currency, $date);
                $ext      = 'pdf';
                $mime     = 'application/pdf';
            }

            $this->saveToDocuments($content, $shopName, $date, $ext);

            $filename = sprintf('DayEnd_%s_%s.%s', $date, date('His'), $ext);
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }

    /* ── PDF generation ─────────────────────────────────────── */

    private function generatePdf(array $report, string $shopName, string $currency, string $date): string
    {
        $summary      = $report['summary'] ?? [];
        $products     = $report['products'] ?? [];
        $expenses     = $report['expenses'] ?? [];
        $transactions = $report['transactions'] ?? [];
        $closure      = $report['closure'] ?? [];
        $closed       = ($closure['status'] ?? '') === 'closed';

        $fmt = fn(float $v): string => $currency . number_format($v, 2);

        $pdf = new PdfWriter();
        $pdf->setFont(8);

        // Header
        $pdf->setFont(9)->write(strtoupper($shopName), false);
        $pdf->setFont(16)->write('Day-End Report', true);
        $pdf->setFont(10)->write($date);
        $pdf->spacer(4);
        $pdf->setFont(9)->write('Status: ' . ($closed ? 'CLOSED' : 'OPEN'), true);
        $pdf->spacer(6)->hr()->spacer(4);

        // Summary grid
        $pdf->setFont(9);
        $pdf->kvGrid([
            ['label' => 'Transactions', 'value' => (string)($summary['transaction_count'] ?? 0)],
            ['label' => 'Net Sales',    'value' => $fmt((float)($summary['net_sales'] ?? 0))],
            ['label' => 'Expenses',     'value' => $fmt((float)($summary['total_expenses'] ?? 0))],
            ['label' => 'Expected Cash','value' => $fmt((float)($summary['expected_cash'] ?? 0))],
        ]);

        // Payment breakdown
        $pdf->kvGrid([
            ['label' => 'Cash Sales',   'value' => $fmt((float)($summary['cash_sales'] ?? 0))],
            ['label' => 'Card Sales',   'value' => $fmt((float)($summary['card_sales'] ?? 0))],
            ['label' => 'Mobile Sales', 'value' => $fmt((float)($summary['mobile_sales'] ?? 0))],
            ['label' => 'Split Cash',   'value' => $fmt((float)($summary['split_cash_sales'] ?? 0))],
        ]);

        $pdf->hr()->spacer(4);

        // Product Movement table
        $pdf->setFont(11)->write('Product Movement', true)->spacer(4);
        $pdf->setFont(8);
        $prodWidths = [160, 40, 50, 55, 40, 50, 60];  // ~455 total
        $prodAligns = ['left', 'left', 'left', 'left', 'left', 'left', 'right'];

        $pdf->tableRow(['Product', 'Type', 'Opening', 'Produced', 'Sold', 'Closing', 'Revenue'], $prodWidths, true, $prodAligns);
        if (empty($products)) {
            $pdf->tableRow(['No product movement recorded.', '', '', '', '', '', ''], $prodWidths);
        } else {
            foreach ($products as $p) {
                $pdf->tableRow([
                    $p['product_name'] ?? '',
                    ($p['is_cake'] ?? false) ? 'Cake' : 'Stock',
                    $p['opening_stock'] === null ? '' : (string)$p['opening_stock'],
                    $p['produced_qty'] === null ? '' : (string)$p['produced_qty'],
                    (string)($p['sold_qty'] ?? 0),
                    $p['closing_stock'] === null ? '' : (string)$p['closing_stock'],
                    $fmt((float)($p['revenue'] ?? 0)),
                ], $prodWidths, false, $prodAligns);
            }
        }

        $pdf->spacer(10)->hr()->spacer(4);

        // Expenses table
        $pdf->setFont(11)->write('Expenses', true)->spacer(4);
        $pdf->setFont(8);
        $expWidths = [100, 160, 80, 70];
        $expAligns = ['left', 'left', 'left', 'right'];

        $pdf->tableRow(['Category', 'Description', 'User', 'Amount'], $expWidths, true, $expAligns);
        if (empty($expenses)) {
            $pdf->tableRow(['No expenses recorded.', '', '', ''], $expWidths);
        } else {
            foreach ($expenses as $e) {
                $pdf->tableRow([
                    $e['category_name'] ?? '',
                    $e['description'] ?? '',
                    $e['recorded_by_name'] ?? '',
                    $fmt((float)($e['amount'] ?? 0)),
                ], $expWidths, false, $expAligns);
            }
        }

        $pdf->spacer(10)->hr()->spacer(4);

        // Transactions table
        $pdf->setFont(11)->write('Transactions', true)->spacer(4);
        $pdf->setFont(8);
        $txnWidths = [120, 80, 100, 70];
        $txnAligns = ['left', 'left', 'left', 'right'];

        $pdf->tableRow(['Ref', 'Method', 'Cashier', 'Total'], $txnWidths, true, $txnAligns);
        if (empty($transactions)) {
            $pdf->tableRow(['No transactions recorded.', '', '', ''], $txnWidths);
        } else {
            foreach ($transactions as $t) {
                $pdf->tableRow([
                    $t['transaction_ref'] ?? '',
                    $t['payment_method'] ?? '',
                    $t['cashier_name'] ?? '',
                    $fmt((float)($t['total'] ?? 0)),
                ], $txnWidths, false, $txnAligns);
            }
        }

        // Closure info
        if ($closed) {
            $pdf->spacer(10)->hr()->spacer(4);
            $pdf->setFont(9);
            $pdf->write('Actual Cash: ' . $fmt((float)($closure['actual_cash'] ?? 0)), true);
            $pdf->write('Difference: ' . $fmt((float)($closure['difference'] ?? 0)));
            if (!empty($closure['notes'])) {
                $pdf->write('Notes: ' . $closure['notes']);
            }
            $closedBy = $closure['closed_by_name'] ?? '';
            $closedAt = $closure['closed_at'] ?? '';
            $pdf->write("Closed by {$closedBy} at {$closedAt}");
        }

        $pdf->spacer(12);
        $pdf->setFont(7)->write('Generated ' . date('Y-m-d H:i:s'));

        return $pdf->build();
    }

    /* ── Excel (HTML table) generation ──────────────────────── */

    private function generateExcel(array $report, string $shopName, string $currency, string $date): string
    {
        $summary      = $report['summary'] ?? [];
        $products     = $report['products'] ?? [];
        $expenses     = $report['expenses'] ?? [];
        $transactions = $report['transactions'] ?? [];
        $closure      = $report['closure'] ?? [];
        $closed       = ($closure['status'] ?? '') === 'closed';

        $fmt = fn(float $v): string => $currency . number_format($v, 2);
        $esc = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
    h2 { margin: 0 0 4px; }
    h3 { margin: 16px 0 4px; }
    table { border-collapse: collapse; margin-bottom: 12px; }
    th, td { border: 1px solid #ccc; padding: 4px 8px; vertical-align: top; }
    th { background: #f0f0f0; font-weight: bold; }
    .r { text-align: right; }
    .label { color: #666; }
</style>
</head>
<body>';

        $html .= '<h2>' . $esc($shopName) . ' - Day-End Report</h2>';
        $html .= '<p>' . $esc($date) . ' &mdash; ' . ($closed ? 'CLOSED' : 'OPEN') . '</p>';

        // Summary
        $html .= '<h3>Summary</h3>';
        $html .= '<table>';
        $html .= '<tr><td class="label">Transactions</td><td>' . (int)($summary['transaction_count'] ?? 0) . '</td></tr>';
        $html .= '<tr><td class="label">Net Sales</td><td class="r">' . $fmt((float)($summary['net_sales'] ?? 0)) . '</td></tr>';
        $html .= '<tr><td class="label">Expenses</td><td class="r">' . $fmt((float)($summary['total_expenses'] ?? 0)) . '</td></tr>';
        $html .= '<tr><td class="label">Expected Cash</td><td class="r">' . $fmt((float)($summary['expected_cash'] ?? 0)) . '</td></tr>';
        $html .= '<tr><td class="label">Cash Sales</td><td class="r">' . $fmt((float)($summary['cash_sales'] ?? 0)) . '</td></tr>';
        $html .= '<tr><td class="label">Card Sales</td><td class="r">' . $fmt((float)($summary['card_sales'] ?? 0)) . '</td></tr>';
        $html .= '<tr><td class="label">Mobile Sales</td><td class="r">' . $fmt((float)($summary['mobile_sales'] ?? 0)) . '</td></tr>';
        $html .= '<tr><td class="label">Split Cash</td><td class="r">' . $fmt((float)($summary['split_cash_sales'] ?? 0)) . '</td></tr>';
        $html .= '</table>';

        // Product Movement
        $html .= '<h3>Product Movement</h3>';
        $html .= '<table><thead><tr><th>Product</th><th>Type</th><th>Opening</th><th>Produced</th><th>Sold</th><th>Closing</th><th>Revenue</th></tr></thead><tbody>';
        if (empty($products)) {
            $html .= '<tr><td colspan="7">No product movement recorded.</td></tr>';
        } else {
            foreach ($products as $p) {
                $html .= '<tr>';
                $html .= '<td>' . $esc($p['product_name'] ?? '') . '</td>';
                $html .= '<td>' . (($p['is_cake'] ?? false) ? 'Cake' : 'Stock') . '</td>';
                $html .= '<td>' . ($p['opening_stock'] === null ? '' : (int)$p['opening_stock']) . '</td>';
                $html .= '<td>' . ($p['produced_qty'] === null ? '' : (int)$p['produced_qty']) . '</td>';
                $html .= '<td>' . (int)($p['sold_qty'] ?? 0) . '</td>';
                $html .= '<td>' . ($p['closing_stock'] === null ? '' : (int)$p['closing_stock']) . '</td>';
                $html .= '<td class="r">' . $fmt((float)($p['revenue'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';

        // Expenses
        $html .= '<h3>Expenses</h3>';
        $html .= '<table><thead><tr><th>Category</th><th>Description</th><th>User</th><th>Amount</th></tr></thead><tbody>';
        if (empty($expenses)) {
            $html .= '<tr><td colspan="4">No expenses recorded.</td></tr>';
        } else {
            foreach ($expenses as $e) {
                $html .= '<tr>';
                $html .= '<td>' . $esc($e['category_name'] ?? '') . '</td>';
                $html .= '<td>' . $esc($e['description'] ?? '') . '</td>';
                $html .= '<td>' . $esc($e['recorded_by_name'] ?? '') . '</td>';
                $html .= '<td class="r">' . $fmt((float)($e['amount'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';

        // Transactions
        $html .= '<h3>Transactions</h3>';
        $html .= '<table><thead><tr><th>Ref</th><th>Method</th><th>Cashier</th><th>Total</th></tr></thead><tbody>';
        if (empty($transactions)) {
            $html .= '<tr><td colspan="4">No transactions recorded.</td></tr>';
        } else {
            foreach ($transactions as $t) {
                $html .= '<tr>';
                $html .= '<td>' . $esc($t['transaction_ref'] ?? '') . '</td>';
                $html .= '<td>' . $esc($t['payment_method'] ?? '') . '</td>';
                $html .= '<td>' . $esc($t['cashier_name'] ?? '') . '</td>';
                $html .= '<td class="r">' . $fmt((float)($t['total'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';

        // Closure info
        if ($closed) {
            $html .= '<h3>Closure</h3>';
            $html .= '<table>';
            $html .= '<tr><td class="label">Actual Cash</td><td class="r">' . $fmt((float)($closure['actual_cash'] ?? 0)) . '</td></tr>';
            $html .= '<tr><td class="label">Difference</td><td class="r">' . $fmt((float)($closure['difference'] ?? 0)) . '</td></tr>';
            if (!empty($closure['notes'])) {
                $html .= '<tr><td class="label">Notes</td><td>' . $esc($closure['notes']) . '</td></tr>';
            }
            $html .= '<tr><td class="label">Closed by</td><td>' . $esc($closure['closed_by_name'] ?? '') . ' at ' . $esc($closure['closed_at'] ?? '') . '</td></tr>';
            $html .= '</table>';
        }

        $html .= '<p style="color:#999;font-size:9pt;">Generated ' . date('Y-m-d H:i:s') . '</p>';
        $html .= '</body></html>';

        return $html;
    }

    /* ── Save file to Documents folder ──────────────────────── */

    private function saveToDocuments(string $content, string $shopName, string $date, string $ext): void
    {
        $userProfile = getenv('USERPROFILE');
        if (!$userProfile) {
            $userProfile = $_SERVER['USERPROFILE'] ?? '';
        }
        if ($userProfile === '') {
            return;
        }

        $documentsPath = $userProfile . DIRECTORY_SEPARATOR . 'Documents';
        if (!is_dir($documentsPath)) {
            return;
        }

        $cleanName = (string)preg_replace('/[<>:"\/\\\\|?*]/', '', $shopName);
        if ($cleanName === '') {
            $cleanName = 'BakeFlow';
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        $monthFolder = $dt ? $dt->format('F Y') : date('F Y');

        $folder = $documentsPath . DIRECTORY_SEPARATOR . $cleanName . DIRECTORY_SEPARATOR . $monthFolder;
        if (!is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }

        $filename = sprintf('DayEnd_%s_%s.%s', $date, date('His'), $ext);
        @file_put_contents($folder . DIRECTORY_SEPARATOR . $filename, $content);
    }
}
