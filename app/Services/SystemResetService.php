<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SyncState;
use InvalidArgumentException;
use PDO;
use Throwable;

class SystemResetService
{
    /**
     * @param array{
     *     reset_transactions?: bool,
     *     reset_stock_zero?: bool,
     *     reset_expenses?: bool
     * } $options
     * @return array<string, mixed>
     */
    public function run(PDO $db, array $options, ?string $resetFromDate): array
    {
        $resetTransactions = (bool)($options['reset_transactions'] ?? false);
        $resetStockZero = (bool)($options['reset_stock_zero'] ?? false);
        $resetExpenses = (bool)($options['reset_expenses'] ?? false);

        if (!$resetTransactions && !$resetStockZero && !$resetExpenses) {
            throw new InvalidArgumentException('Select at least one reset option.');
        }

        $normalizedDate = $this->normalizeDate($resetFromDate);
        $summary = [
            'reset_from_date' => $normalizedDate,
            'transactions_deleted' => 0,
            'transaction_items_deleted' => 0,
            'cake_orders_deleted' => 0,
            'daily_closings_deleted' => 0,
            'sync_log_deleted' => 0,
            'stock_rows_restocked' => 0,
            'stock_units_restocked' => 0,
            'stock_rows_zeroed' => 0,
            'expenses_deleted' => 0,
        ];

        SyncState::ensureSchema($db);

        try {
            $db->beginTransaction();

            if ($resetTransactions) {
                $summary = array_merge($summary, $this->resetTransactions($db, $normalizedDate));
            }

            if ($resetExpenses) {
                $summary['expenses_deleted'] = $this->resetExpenses($db, $normalizedDate);
            }

            if ($resetStockZero) {
                $summary['stock_rows_zeroed'] = $this->zeroStock($db);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        $dirtyTables = [];
        if ($resetTransactions) {
            $dirtyTables = array_merge($dirtyTables, ['transactions', 'cake_orders', 'daily_closings', 'products']);
        }
        if ($resetExpenses) {
            $dirtyTables[] = 'expenses';
        }
        if ($resetStockZero) {
            $dirtyTables[] = 'products';
        }

        if ($dirtyTables !== []) {
            SyncState::markDirty($db, array_values(array_unique($dirtyTables)));
        }

        return $summary;
    }

    private function normalizeDate(?string $date): ?string
    {
        $date = trim((string)$date);
        if ($date === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($dt === false || (($errors['warning_count'] ?? 0) > 0) || (($errors['error_count'] ?? 0) > 0) || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Reset date must be a valid date in YYYY-MM-DD format.');
        }

        return $dt->format('Y-m-d');
    }

    /**
     * @return array<string, int>
     */
    private function resetTransactions(PDO $db, ?string $resetFromDate): array
    {
        $summary = [
            'transactions_deleted' => 0,
            'transaction_items_deleted' => 0,
            'cake_orders_deleted' => 0,
            'daily_closings_deleted' => 0,
            'sync_log_deleted' => 0,
            'stock_rows_restocked' => 0,
            'stock_units_restocked' => 0,
        ];

        $txnParams = [];
        $txnWhere = '';
        if ($resetFromDate !== null) {
            $txnWhere = ' WHERE created_at >= ?';
            $txnParams[] = $resetFromDate . ' 00:00:00';
        }

        $cakeOrderSql = '
            FROM cake_orders co
            LEFT JOIN transaction_items order_items ON order_items.id = co.transaction_item_id
            LEFT JOIN transactions order_txn ON order_txn.id = order_items.transaction_id
            LEFT JOIN transactions balance_txn ON balance_txn.id = co.balance_transaction_id
        ';
        $cakeOrderParams = [];
        $cakeOrderWhere = '';
        if ($resetFromDate !== null) {
            $cakeOrderWhere = ' WHERE order_txn.created_at >= ? OR balance_txn.created_at >= ? OR co.created_at >= ?';
            $cakeOrderParams = [
                $resetFromDate . ' 00:00:00',
                $resetFromDate . ' 00:00:00',
                $resetFromDate . ' 00:00:00',
            ];
        }

        $countStmt = $db->prepare('SELECT COUNT(*) FROM transactions' . $txnWhere);
        $countStmt->execute($txnParams);
        $summary['transactions_deleted'] = (int)$countStmt->fetchColumn();

        $countStmt = $db->prepare('
            SELECT COUNT(*)
            FROM transaction_items ti
            INNER JOIN transactions t ON t.id = ti.transaction_id' . $txnWhere
        );
        $countStmt->execute($txnParams);
        $summary['transaction_items_deleted'] = (int)$countStmt->fetchColumn();

        $countStmt = $db->prepare('SELECT COUNT(DISTINCT co.id) ' . $cakeOrderSql . $cakeOrderWhere);
        $countStmt->execute($cakeOrderParams);
        $summary['cake_orders_deleted'] = (int)$countStmt->fetchColumn();

        $closingWhere = '';
        $closingParams = [];
        if ($resetFromDate !== null) {
            $closingWhere = ' WHERE `date` >= ?';
            $closingParams[] = $resetFromDate;
        }
        $countStmt = $db->prepare('SELECT COUNT(*) FROM daily_closings' . $closingWhere);
        $countStmt->execute($closingParams);
        $summary['daily_closings_deleted'] = (int)$countStmt->fetchColumn();

        $syncLogWhere = '';
        $syncLogParams = [];
        if ($resetFromDate !== null) {
            $syncLogWhere = ' WHERE synced_at >= ?';
            $syncLogParams[] = $resetFromDate . ' 00:00:00';
        }
        $countStmt = $db->prepare('SELECT COUNT(*) FROM sync_log' . $syncLogWhere);
        $countStmt->execute($syncLogParams);
        $summary['sync_log_deleted'] = (int)$countStmt->fetchColumn();

        $restockStmt = $db->prepare('
            SELECT ti.product_id, SUM(ti.quantity) AS qty
            FROM transaction_items ti
            INNER JOIN transactions t ON t.id = ti.transaction_id
            INNER JOIN products p ON p.id = ti.product_id
' . ($resetFromDate !== null ? ' WHERE t.created_at >= ? AND p.is_cake = 0' : ' WHERE p.is_cake = 0') . '
            GROUP BY ti.product_id
        ');
        $restockStmt->execute($txnParams);
        $restockRows = $restockStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($restockRows as $row) {
            $qty = (int)($row['qty'] ?? 0);
            $productId = (int)($row['product_id'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $db->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?')
               ->execute([$qty, $productId]);

            $summary['stock_rows_restocked']++;
            $summary['stock_units_restocked'] += $qty;
        }

        if ($summary['cake_orders_deleted'] > 0) {
            $deleteStmt = $db->prepare('DELETE co ' . $cakeOrderSql . $cakeOrderWhere);
            $deleteStmt->execute($cakeOrderParams);
        }

        $deleteStmt = $db->prepare('DELETE FROM transactions' . $txnWhere);
        $deleteStmt->execute($txnParams);

        $deleteStmt = $db->prepare('DELETE FROM daily_closings' . $closingWhere);
        $deleteStmt->execute($closingParams);

        $deleteStmt = $db->prepare('DELETE FROM sync_log' . $syncLogWhere);
        $deleteStmt->execute($syncLogParams);

        return $summary;
    }

    private function resetExpenses(PDO $db, ?string $resetFromDate): int
    {
        $where = '';
        $params = [];
        if ($resetFromDate !== null) {
            $where = ' WHERE expense_date >= ?';
            $params[] = $resetFromDate;
        }

        $countStmt = $db->prepare('SELECT COUNT(*) FROM expenses' . $where);
        $countStmt->execute($params);
        $count = (int)$countStmt->fetchColumn();

        $deleteStmt = $db->prepare('DELETE FROM expenses' . $where);
        $deleteStmt->execute($params);

        return $count;
    }

    private function zeroStock(PDO $db): int
    {
        $stmt = $db->prepare('UPDATE products SET stock_quantity = 0 WHERE stock_quantity <> 0');
        $stmt->execute();

        return $stmt->rowCount();
    }
}
