<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class DayEndReportService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function normaliseDate(?string $date): string
    {
        $value = trim((string)$date);
        if ($value === '') {
            return date('Y-m-d');
        }

        $parsed = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            throw new RuntimeException('Invalid report date.');
        }

        return $value;
    }

    public function isClosed(?string $date = null): bool
    {
        $closure = $this->getClosure($this->normaliseDate($date));
        return $closure['status'] === 'closed';
    }

    public function buildReport(?string $date = null, bool $preferSnapshot = true): array
    {
        $date = $this->normaliseDate($date);
        $closure = $this->getClosure($date);

        if ($preferSnapshot && $closure['status'] === 'closed' && trim((string)$closure['report_snapshot']) !== '') {
            $snapshot = json_decode((string)$closure['report_snapshot'], true);
            if (is_array($snapshot)) {
                $snapshot['closure'] = $this->buildClosurePayload($closure, (float)($snapshot['summary']['expected_cash'] ?? 0));
                return $snapshot;
            }
        }

        $report = $this->buildLiveReport($date);
        $report['closure'] = $this->buildClosurePayload($closure, (float)$report['summary']['expected_cash']);

        return $report;
    }

    public function closeDay(?string $date, float $actualCash, ?string $notes, int $userId): array
    {
        $date = $this->normaliseDate($date);
        if ($actualCash < 0) {
            throw new RuntimeException('Actual cash cannot be negative.');
        }

        $report = $this->buildLiveReport($date);
        $expectedCash = (float)$report['summary']['expected_cash'];
        $notes = trim((string)$notes);
        $closedAt = date('Y-m-d H:i:s');

        $closure = [
            'status'         => 'closed',
            'date'           => $date,
            'expected_cash'  => $expectedCash,
            'actual_cash'    => round($actualCash, 2),
            'difference'     => round($actualCash - $expectedCash, 2),
            'notes'          => $notes,
            'closed_at'      => $closedAt,
            'closed_by'      => $userId,
            'closed_by_name' => null,
            'reopened_at'    => null,
            'reopened_by'    => null,
            'reopened_by_name' => null,
            'reopen_reason'  => null,
        ];

        $report['closure'] = $closure;
        $snapshot = json_encode($report, JSON_UNESCAPED_UNICODE);
        if ($snapshot === false) {
            throw new RuntimeException('Failed to serialise the day-end snapshot.');
        }

        $this->db->beginTransaction();

        try {
            $existingStmt = $this->db->prepare("SELECT id FROM daily_closings WHERE date = ? LIMIT 1");
            $existingStmt->execute([$date]);
            $existing = $existingStmt->fetch();

            if ($existing) {
                $stmt = $this->db->prepare("
                    UPDATE daily_closings
                    SET cashier_id = ?,
                        expected_cash = ?,
                        actual_cash = ?,
                        notes = ?,
                        closed_at = ?,
                        status = 'closed',
                        report_snapshot = ?,
                        reopened_by = NULL,
                        reopened_at = NULL,
                        reopen_reason = NULL
                    WHERE id = ?
                ");
                $stmt->execute([
                    $userId,
                    $expectedCash,
                    $actualCash,
                    $notes !== '' ? $notes : null,
                    $closedAt,
                    $snapshot,
                    (int)$existing['id'],
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO daily_closings
                        (date, cashier_id, expected_cash, actual_cash, notes, closed_at, status, report_snapshot)
                    VALUES (?, ?, ?, ?, ?, ?, 'closed', ?)
                ");
                $stmt->execute([
                    $date,
                    $userId,
                    $expectedCash,
                    $actualCash,
                    $notes !== '' ? $notes : null,
                    $closedAt,
                    $snapshot,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->buildReport($date, true);
    }

    public function reopenDay(?string $date, ?string $reason, int $userId): array
    {
        $date = $this->normaliseDate($date);
        $reason = trim((string)$reason);

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("SELECT id, status FROM daily_closings WHERE date = ? LIMIT 1");
            $stmt->execute([$date]);
            $row = $stmt->fetch();

            if (!$row || ($row['status'] ?? '') !== 'closed') {
                throw new RuntimeException('This day is not currently closed.');
            }

            $update = $this->db->prepare("
                UPDATE daily_closings
                SET status = 'open',
                    reopened_by = ?,
                    reopened_at = ?,
                    reopen_reason = ?
                WHERE id = ?
            ");
            $update->execute([
                $userId,
                date('Y-m-d H:i:s'),
                $reason !== '' ? $reason : null,
                (int)$row['id'],
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->buildReport($date, false);
    }

    public function getClosure(?string $date = null): array
    {
        $date = $this->normaliseDate($date);

        $stmt = $this->db->prepare("
            SELECT dc.*,
                   closer.name AS closed_by_name,
                   reopener.name AS reopened_by_name
            FROM daily_closings dc
            LEFT JOIN users closer ON closer.id = dc.cashier_id
            LEFT JOIN users reopener ON reopener.id = dc.reopened_by
            WHERE dc.date = ?
            LIMIT 1
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'date'             => $date,
                'status'           => 'open',
                'cashier_id'       => null,
                'expected_cash'    => 0.0,
                'actual_cash'      => 0.0,
                'difference'       => 0.0,
                'notes'            => null,
                'closed_at'        => null,
                'closed_by_name'   => null,
                'reopened_by'      => null,
                'reopened_at'      => null,
                'reopened_by_name' => null,
                'reopen_reason'    => null,
                'report_snapshot'  => null,
            ];
        }

        $row['expected_cash'] = (float)($row['expected_cash'] ?? 0);
        $row['actual_cash'] = (float)($row['actual_cash'] ?? 0);
        $row['difference'] = (float)($row['difference'] ?? ($row['actual_cash'] - $row['expected_cash']));

        return $row;
    }

    private function buildLiveReport(string $date): array
    {
        $summaryStmt = $this->db->prepare("
            SELECT COUNT(*) AS transaction_count,
                   COALESCE(SUM(subtotal), 0) AS gross_sales,
                   COALESCE(SUM(discount), 0) AS discount_total,
                   COALESCE(SUM(total), 0) AS net_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) AS cash_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) AS card_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'mobile' THEN total ELSE 0 END), 0) AS mobile_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'split' THEN total ELSE 0 END), 0) AS split_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'split' THEN total - card_amount ELSE 0 END), 0) AS split_cash_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'split' THEN card_amount ELSE 0 END), 0) AS split_card_sales
            FROM transactions
            WHERE status = 'completed'
              AND DATE(created_at) = ?
        ");
        $summaryStmt->execute([$date]);
        $summary = $summaryStmt->fetch() ?: [];

        $expenseStmt = $this->db->prepare("
            SELECT e.id,
                   e.description,
                   e.amount,
                   e.expense_date,
                   e.receipt_ref,
                   e.notes,
                   e.created_at,
                   ec.name AS category_name,
                   u.name AS recorded_by_name
            FROM expenses e
            LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
            LEFT JOIN users u ON u.id = e.recorded_by
            WHERE e.expense_date = ?
            ORDER BY e.created_at ASC, e.id ASC
        ");
        $expenseStmt->execute([$date]);
        $expenses = $expenseStmt->fetchAll();

        $totalExpenses = 0.0;
        foreach ($expenses as &$expense) {
            $expense['id'] = (int)$expense['id'];
            $expense['amount'] = (float)$expense['amount'];
            $totalExpenses += $expense['amount'];
        }
        unset($expense);

        $transactionStmt = $this->db->prepare("
            SELECT t.*,
                   u.name AS cashier_name,
                   CASE
                       WHEN t.payment_method = 'cash' THEN t.total
                       WHEN t.payment_method = 'split' THEN t.total - t.card_amount
                       ELSE 0
                   END AS cash_portion
            FROM transactions t
            LEFT JOIN users u ON u.id = t.cashier_id
            WHERE t.status = 'completed'
              AND DATE(t.created_at) = ?
            ORDER BY t.created_at DESC, t.id DESC
        ");
        $transactionStmt->execute([$date]);
        $transactions = $transactionStmt->fetchAll();

        foreach ($transactions as &$transaction) {
            $transaction['id'] = (int)$transaction['id'];
            $transaction['subtotal'] = (float)$transaction['subtotal'];
            $transaction['discount'] = (float)$transaction['discount'];
            $transaction['total'] = (float)$transaction['total'];
            $transaction['cash_tendered'] = (float)$transaction['cash_tendered'];
            $transaction['change_given'] = (float)$transaction['change_given'];
            $transaction['card_amount'] = (float)$transaction['card_amount'];
            $transaction['cash_portion'] = (float)$transaction['cash_portion'];
        }
        unset($transaction);

        $soldToday = $this->fetchProductMovementMap("
            SELECT ti.product_id,
                   SUM(ti.quantity) AS qty,
                   SUM(ti.line_total) AS revenue
            FROM transaction_items ti
            INNER JOIN transactions t ON t.id = ti.transaction_id
            WHERE ti.product_id IS NOT NULL
              AND t.status = 'completed'
              AND DATE(t.created_at) = ?
            GROUP BY ti.product_id
        ", [$date]);

        $soldAfter = $this->fetchProductMovementMap("
            SELECT ti.product_id,
                   SUM(ti.quantity) AS qty
            FROM transaction_items ti
            INNER JOIN transactions t ON t.id = ti.transaction_id
            WHERE ti.product_id IS NOT NULL
              AND t.status = 'completed'
              AND DATE(t.created_at) > ?
            GROUP BY ti.product_id
        ", [$date]);

        $producedToday = $this->fetchProductMovementMap("
            SELECT product_id,
                   SUM(quantity) AS qty
            FROM production_entries
            WHERE production_date = ?
            GROUP BY product_id
        ", [$date]);

        $producedAfter = $this->fetchProductMovementMap("
            SELECT product_id,
                   SUM(quantity) AS qty
            FROM production_entries
            WHERE production_date > ?
            GROUP BY product_id
        ", [$date]);

        $products = $this->db->query("
            SELECT id, name, is_cake, is_active, stock_quantity
            FROM products
            ORDER BY name ASC
        ")->fetchAll();

        $productRows = [];
        foreach ($products as $product) {
            $productId = (int)$product['id'];
            $isCake = (int)$product['is_cake'] === 1;
            $soldQty = (int)($soldToday[$productId]['qty'] ?? 0);
            $revenue = (float)($soldToday[$productId]['revenue'] ?? 0);
            $producedQty = (int)($producedToday[$productId]['qty'] ?? 0);
            $soldQtyAfter = (int)($soldAfter[$productId]['qty'] ?? 0);
            $producedQtyAfter = (int)($producedAfter[$productId]['qty'] ?? 0);

            $openingStock = null;
            $closingStock = null;

            if (!$isCake) {
                $currentStock = (int)$product['stock_quantity'];
                $closingStock = $currentStock - $producedQtyAfter + $soldQtyAfter;
                $openingStock = $closingStock - $producedQty + $soldQty;
            }

            $include = false;
            if ($isCake) {
                $include = $soldQty > 0;
            } else {
                $include = (int)$product['is_active'] === 1
                    || $soldQty > 0
                    || $producedQty > 0
                    || (int)$openingStock > 0
                    || (int)$closingStock > 0;
            }

            if (!$include) {
                continue;
            }

            $productRows[] = [
                'product_id'     => $productId,
                'product_name'   => (string)$product['name'],
                'is_cake'        => $isCake,
                'opening_stock'  => $openingStock,
                'produced_qty'   => $isCake ? null : $producedQty,
                'sold_qty'       => $soldQty,
                'closing_stock'  => $closingStock,
                'revenue'        => round($revenue, 2),
            ];
        }

        $grossSales = (float)($summary['gross_sales'] ?? 0);
        $discountTotal = (float)($summary['discount_total'] ?? 0);
        $netSales = (float)($summary['net_sales'] ?? 0);
        $cashSales = (float)($summary['cash_sales'] ?? 0);
        $cardSales = (float)($summary['card_sales'] ?? 0);
        $mobileSales = (float)($summary['mobile_sales'] ?? 0);
        $splitSales = (float)($summary['split_sales'] ?? 0);
        $splitCashSales = (float)($summary['split_cash_sales'] ?? 0);
        $splitCardSales = (float)($summary['split_card_sales'] ?? 0);
        $expectedCash = round($cashSales + $splitCashSales - $totalExpenses, 2);

        return [
            'summary' => [
                'date'              => $date,
                'transaction_count' => (int)($summary['transaction_count'] ?? 0),
                'gross_sales'       => round($grossSales, 2),
                'discount_total'    => round($discountTotal, 2),
                'net_sales'         => round($netSales, 2),
                'cash_sales'        => round($cashSales, 2),
                'card_sales'        => round($cardSales, 2),
                'mobile_sales'      => round($mobileSales, 2),
                'split_sales'       => round($splitSales, 2),
                'split_cash_sales'  => round($splitCashSales, 2),
                'split_card_sales'  => round($splitCardSales, 2),
                'total_expenses'    => round($totalExpenses, 2),
                'expected_cash'     => $expectedCash,
            ],
            'products' => $productRows,
            'expenses' => $expenses,
            'transactions' => $transactions,
        ];
    }

    private function fetchProductMovementMap(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $map = [];

        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];
            $map[$productId] = [
                'qty'     => (int)($row['qty'] ?? 0),
                'revenue' => (float)($row['revenue'] ?? 0),
            ];
        }

        return $map;
    }

    private function buildClosurePayload(array $closure, float $expectedCash): array
    {
        $status = (string)($closure['status'] ?? 'open');
        $actualCash = $status === 'closed'
            ? (float)($closure['actual_cash'] ?? 0)
            : null;
        $difference = $status === 'closed'
            ? round($actualCash - $expectedCash, 2)
            : null;

        return [
            'date'               => $closure['date'] ?? date('Y-m-d'),
            'status'             => $status,
            'expected_cash'      => round($expectedCash, 2),
            'actual_cash'        => $actualCash,
            'difference'         => $difference,
            'notes'              => $closure['notes'] ?? null,
            'closed_at'          => $closure['closed_at'] ?? null,
            'closed_by'          => isset($closure['cashier_id']) ? (int)$closure['cashier_id'] : null,
            'closed_by_name'     => $closure['closed_by_name'] ?? null,
            'reopened_by'        => isset($closure['reopened_by']) ? (int)$closure['reopened_by'] : null,
            'reopened_at'        => $closure['reopened_at'] ?? null,
            'reopened_by_name'   => $closure['reopened_by_name'] ?? null,
            'reopen_reason'      => $closure['reopen_reason'] ?? null,
        ];
    }
}
