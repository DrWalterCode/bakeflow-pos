<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class SyncService
{
    private const TRACKED_TABLES = [
        'shops',
        'users',
        'categories',
        'cake_sizes',
        'cake_flavours',
        'expense_categories',
        'specials',
        'settings',
        'products',
        'transactions',
        'cake_orders',
        'expenses',
        'production_entries',
        'daily_closings',
    ];

    private const SNAPSHOT_ORDER = [
        'shops',
        'users',
        'categories',
        'cake_sizes',
        'cake_flavours',
        'expense_categories',
        'specials',
        'settings',
        'products',
        'cake_orders',
        'expenses',
        'production_entries',
        'daily_closings',
    ];

    private const TABLE_COLUMNS = [
        'shops' => ['id', 'name', 'logo_path', 'address', 'phone', 'email', 'receipt_header', 'receipt_footer', 'primary_color', 'currency_symbol', 'tax_rate', 'created_at'],
        'users' => ['id', 'name', 'username', 'password_hash', 'pin_hash', 'role', 'is_active', 'pin_fail_count', 'pin_locked_until', 'last_login_at', 'created_at'],
        'categories' => ['id', 'name', 'color', 'sort_order', 'is_active', 'created_at'],
        'cake_sizes' => ['id', 'name', 'label', 'price_base', 'deposit_amount', 'is_active', 'sort_order'],
        'cake_flavours' => ['id', 'name', 'is_active', 'sort_order'],
        'expense_categories' => ['id', 'name', 'description', 'is_active', 'created_at'],
        'specials' => ['id', 'name', 'description', 'discount_type', 'discount_value', 'applies_to', 'target_id', 'start_date', 'end_date', 'is_active', 'created_at'],
        'settings' => ['id', 'key', 'value', 'updated_at'],
        'products' => ['id', 'category_id', 'name', 'description', 'price', 'barcode', 'is_active', 'is_cake', 'stock_quantity', 'is_quick_item', 'quick_item_order', 'sort_order', 'created_at'],
        'transactions' => ['id', 'transaction_ref', 'cashier_id', 'subtotal', 'discount', 'total', 'payment_method', 'cash_tendered', 'change_given', 'card_amount', 'reference_number', 'status', 'terminal_id', 'sync_status', 'notes', 'created_at'],
        'transaction_items' => ['id', 'transaction_id', 'product_id', 'product_name', 'unit_price', 'quantity', 'line_total'],
        'cake_orders' => ['id', 'transaction_item_id', 'flavour_id', 'size_id', 'shape', 'inscription', 'pickup_date', 'notes', 'additional_cost', 'full_price', 'deposit_amount', 'amount_paid', 'balance_due', 'payment_status', 'order_status', 'balance_transaction_id', 'customer_name', 'customer_phone', 'created_at'],
        'expenses' => ['id', 'expense_category_id', 'description', 'amount', 'expense_date', 'recorded_by', 'receipt_ref', 'notes', 'created_at'],
        'production_entries' => ['id', 'product_id', 'quantity', 'produced_by', 'batch_ref', 'notes', 'production_date', 'created_at'],
        'daily_closings' => ['id', 'date', 'cashier_id', 'expected_cash', 'actual_cash', 'notes', 'closed_at'],
    ];

    private const DELETE_MISSING_TABLES = [
        'expenses',
        'production_entries',
        'daily_closings',
    ];

    public function __construct(
        private readonly ?PDO $local = null,
        private ?PDO $remote = null
    ) {
    }

    public static function trackedTables(): array
    {
        return self::TRACKED_TABLES;
    }

    public static function status(PDO $local): array
    {
        SyncState::ensureRows($local, self::TRACKED_TABLES);
        $states = SyncState::getStates($local, self::TRACKED_TABLES);

        $pendingTables = [];
        $dirtyTables = [];
        $pending = 0;

        foreach (self::TRACKED_TABLES as $table) {
            $state = $states[$table] ?? null;
            $count = self::pendingCountForTable($local, $table, $state);

            if ($count > 0) {
                $pendingTables[$table] = $count;
                $dirtyTables[] = $table;
                $pending += $count;
            }
        }

        $remoteHost = trim(Env::get('REMOTE_DB_HOST', ''));
        $remotePort = (int)Env::get('REMOTE_DB_PORT', '3306');
        $online = false;
        $message = 'Sync not configured';

        if ($remoteHost !== '') {
            $socket = @fsockopen($remoteHost, $remotePort, $errno, $errstr, 2);
            if (is_resource($socket)) {
                fclose($socket);
                $online = true;
            }
            $message = $online ? 'Synced' : 'Offline';
        }

        $lastSuccess = $local->query("
            SELECT synced_at
            FROM sync_log
            WHERE direction = 'push' AND status = 'success'
            ORDER BY id DESC
            LIMIT 1
        ")->fetchColumn() ?: null;

        $lastError = $local->query("
            SELECT error_msg
            FROM sync_log
            WHERE direction = 'push' AND status = 'failed'
            ORDER BY id DESC
            LIMIT 1
        ")->fetchColumn() ?: null;

        if ($remoteHost === '') {
            $status = 'red';
        } elseif (!$online) {
            $status = 'red';
        } elseif ($dirtyTables !== []) {
            $status = 'orange';
            $message = count($dirtyTables) === 1 ? '1 pending table' : count($dirtyTables) . ' pending tables';
        } else {
            $status = 'green';
        }

        return [
            'status' => $status,
            'pending' => $pending,
            'pending_tables' => $pendingTables,
            'dirty_tables' => $dirtyTables,
            'last_successful_sync' => $lastSuccess,
            'last_error' => $lastError,
            'message' => $message,
        ];
    }

    public function push(): array
    {
        $local = $this->local ?? Database::getConnection();
        SyncState::ensureRows($local, self::TRACKED_TABLES);

        $this->remote = $this->remote ?? RemoteDatabase::getConnection();
        if (!$this->remote) {
            $message = 'Remote database unavailable.';
            $this->logRun($local, 'failed', 0, $message);

            return [
                'success' => false,
                'http_status' => 503,
                'tables_synced' => 0,
                'records_synced' => 0,
                'records_deleted' => 0,
                'records_failed' => 0,
                'results' => [],
                'errors' => [$message],
                'message' => $message,
            ];
        }

        $results = [];
        $errors = [];
        $tablesSynced = 0;
        $recordsSynced = 0;
        $recordsDeleted = 0;
        $recordsFailed = 0;

        foreach (['shops', 'users', 'categories', 'cake_sizes', 'cake_flavours', 'expense_categories', 'specials', 'settings', 'products'] as $table) {

            $result = $this->syncSnapshotTable($local, $table);
            $results[] = $result;

            if ($result['status'] === 'failed') {
                $errors[] = $result['error'];
                $recordsFailed += $result['records'];
                break;
            }

            if ($result['status'] === 'synced') {
                $tablesSynced++;
                $recordsSynced += $result['records'];
                $recordsDeleted += $result['deleted'] ?? 0;
            }
        }

        if ($errors === []) {
            $transactionsResult = $this->syncTransactions($local);
            $results[] = $transactionsResult;

            if ($transactionsResult['status'] === 'failed') {
                $errors[] = $transactionsResult['error'];
                $recordsFailed += $transactionsResult['records'];
            } elseif ($transactionsResult['status'] === 'synced') {
                $tablesSynced++;
                $recordsSynced += $transactionsResult['records'] + ($transactionsResult['related_records'] ?? 0);
            }
        }

        if ($errors === []) {
            foreach (['cake_orders', 'expenses', 'production_entries', 'daily_closings'] as $table) {
                $result = $this->syncSnapshotTable($local, $table);
                $results[] = $result;

                if ($result['status'] === 'failed') {
                    $errors[] = $result['error'];
                    $recordsFailed += $result['records'];
                    break;
                }

                if ($result['status'] === 'synced') {
                    $tablesSynced++;
                    $recordsSynced += $result['records'];
                    $recordsDeleted += $result['deleted'] ?? 0;
                }
            }
        }

        $success = $errors === [];
        $message = $success
            ? 'Sync completed successfully.'
            : 'Sync failed: ' . implode('; ', $errors);

        $this->logRun(
            $local,
            $success ? 'success' : 'failed',
            $recordsSynced,
            $success ? null : $message
        );

        return [
            'success' => $success,
            'http_status' => $success ? 200 : 500,
            'tables_synced' => $tablesSynced,
            'records_synced' => $recordsSynced,
            'records_deleted' => $recordsDeleted,
            'records_failed' => $recordsFailed,
            'results' => $results,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    private static function pendingCountForTable(PDO $local, string $table, ?array $state): int
    {
        if ($table === 'transactions') {
            $pending = (int)$local->query("
                SELECT COUNT(*)
                FROM transactions
                WHERE sync_status IN ('pending', 'failed')
            ")->fetchColumn();

            if (($state['last_synced_at'] ?? null) === null) {
                $pending = (int)$local->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
            }

            if ((int)($state['is_dirty'] ?? 0) === 1 && $pending === 0) {
                $pending = (int)$local->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
            }

            return $pending;
        }

        if (($state['last_synced_at'] ?? null) === null || (int)($state['is_dirty'] ?? 0) === 1) {
            $count = (int)$local->query('SELECT COUNT(*) FROM ' . self::quoteIdentifier($table))->fetchColumn();
            return max($count, 1);
        }

        return 0;
    }

    private function syncSnapshotTable(PDO $local, string $table): array
    {
        $state = SyncState::getState($local, $table);
        $needsSync = ($state['last_synced_at'] ?? null) === null || (int)($state['is_dirty'] ?? 0) === 1;

        if (!$needsSync) {
            return [
                'table' => $table,
                'status' => 'skipped',
                'records' => 0,
                'deleted' => 0,
            ];
        }

        $columns = self::TABLE_COLUMNS[$table] ?? [];
        $rows = $this->fetchAllRows($local, $table, $columns);

        try {
            $this->remote->beginTransaction();
            $this->bulkUpsert($this->remote, $table, $columns, $rows);

            $deleted = 0;
            if (in_array($table, self::DELETE_MISSING_TABLES, true)) {
                $deleted = $this->deleteMissingRows($this->remote, $table, array_map(static fn (array $row): int => (int)$row['id'], $rows));
            }

            $this->remote->commit();
            SyncState::markSynced($local, $table, count($rows));

            return [
                'table' => $table,
                'status' => 'synced',
                'records' => count($rows),
                'deleted' => $deleted,
            ];
        } catch (Throwable $e) {
            if ($this->remote->inTransaction()) {
                $this->remote->rollBack();
            }
            SyncState::markFailed($local, $table, $e->getMessage());

            return [
                'table' => $table,
                'status' => 'failed',
                'records' => count($rows),
                'deleted' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function syncTransactions(PDO $local): array
    {
        $state = SyncState::getState($local, 'transactions');
        $pendingCount = (int)$local->query("
            SELECT COUNT(*)
            FROM transactions
            WHERE sync_status IN ('pending', 'failed')
        ")->fetchColumn();

        $fullSync = ($state['last_synced_at'] ?? null) === null
            || ((int)($state['is_dirty'] ?? 0) === 1 && $pendingCount === 0);

        if (!$fullSync && $pendingCount === 0) {
            return [
                'table' => 'transactions',
                'status' => 'skipped',
                'records' => 0,
                'related_records' => 0,
                'deleted' => 0,
            ];
        }

        $transactionSql = $fullSync
            ? "SELECT " . $this->columnList('transactions') . " FROM transactions ORDER BY created_at ASC, id ASC"
            : "SELECT " . $this->columnList('transactions') . " FROM transactions WHERE sync_status IN ('pending', 'failed') ORDER BY created_at ASC, id ASC";

        $transactions = $local->query($transactionSql)->fetchAll(PDO::FETCH_ASSOC);
        $transactionIds = array_map(static fn (array $row): int => (int)$row['id'], $transactions);

        $items = $transactionIds === []
            ? []
            : $this->fetchRowsByIds($local, 'transaction_items', self::TABLE_COLUMNS['transaction_items'], 'transaction_id', $transactionIds);

        foreach ($transactions as &$transaction) {
            $transaction['sync_status'] = 'synced';
        }
        unset($transaction);

        try {
            $this->remote->beginTransaction();
            $this->bulkUpsert($this->remote, 'transactions', self::TABLE_COLUMNS['transactions'], $transactions);
            $this->bulkUpsert($this->remote, 'transaction_items', self::TABLE_COLUMNS['transaction_items'], $items);
            $this->remote->commit();

            if ($transactionIds !== []) {
                $placeholders = implode(', ', array_fill(0, count($transactionIds), '?'));
                $stmt = $local->prepare("
                    UPDATE transactions
                    SET sync_status = 'synced'
                    WHERE id IN ({$placeholders})
                ");
                $stmt->execute($transactionIds);
            }

            SyncState::markSynced($local, 'transactions', count($transactions));

            return [
                'table' => 'transactions',
                'status' => 'synced',
                'records' => count($transactions),
                'related_records' => count($items),
                'deleted' => 0,
            ];
        } catch (Throwable $e) {
            if ($this->remote->inTransaction()) {
                $this->remote->rollBack();
            }
            SyncState::markFailed($local, 'transactions', $e->getMessage());

            return [
                'table' => 'transactions',
                'status' => 'failed',
                'records' => count($transactions),
                'related_records' => count($items),
                'deleted' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function fetchAllRows(PDO $db, string $table, array $columns): array
    {
        $orderBy = $table === 'settings' ? 'id ASC' : 'id ASC';
        $stmt = $db->query(
            'SELECT ' . $this->columnList($table) . ' FROM ' . self::quoteIdentifier($table) . ' ORDER BY ' . $orderBy
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchRowsByIds(PDO $db, string $table, array $columns, string $foreignKey, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            'SELECT ' . $this->columnList($table)
            . ' FROM ' . self::quoteIdentifier($table)
            . ' WHERE ' . self::quoteIdentifier($foreignKey) . " IN ({$placeholders}) ORDER BY id ASC"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function bulkUpsert(PDO $db, string $table, array $columns, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $quotedColumns = array_map([self::class, 'quoteIdentifier'], $columns);
        $updateColumns = array_filter($columns, static fn (string $column): bool => $column !== 'id');
        $updateSql = implode(', ', array_map(
            static fn (string $column): string => self::quoteIdentifier($column) . ' = VALUES(' . self::quoteIdentifier($column) . ')',
            $updateColumns
        ));

        foreach (array_chunk($rows, 100) as $chunk) {
            $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $placeholders = implode(', ', array_fill(0, count($chunk), $rowPlaceholder));
            $sql = 'INSERT INTO ' . self::quoteIdentifier($table)
                . ' (' . implode(', ', $quotedColumns) . ') VALUES ' . $placeholders
                . ' ON DUPLICATE KEY UPDATE ' . $updateSql;

            $params = [];
            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $params[] = $row[$column] ?? null;
                }
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
    }

    private function deleteMissingRows(PDO $db, string $table, array $localIds): int
    {
        if ($localIds === []) {
            return (int)$db->exec('DELETE FROM ' . self::quoteIdentifier($table));
        }

        $remoteIds = $db->query('SELECT id FROM ' . self::quoteIdentifier($table))->fetchAll(PDO::FETCH_COLUMN);
        $remoteIds = array_map('intval', $remoteIds);
        $missingIds = array_values(array_diff($remoteIds, $localIds));

        if ($missingIds === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($missingIds), '?'));
        $stmt = $db->prepare('DELETE FROM ' . self::quoteIdentifier($table) . " WHERE id IN ({$placeholders})");
        $stmt->execute($missingIds);

        return $stmt->rowCount();
    }

    private function logRun(PDO $local, string $status, int $recordsCount, ?string $error): void
    {
        $stmt = $local->prepare("
            INSERT INTO sync_log (direction, status, records_count, error_msg)
            VALUES ('push', ?, ?, ?)
        ");
        $stmt->execute([$status, $recordsCount, $error]);
    }

    private function columnList(string $table): string
    {
        $columns = self::TABLE_COLUMNS[$table] ?? [];
        return implode(', ', array_map([self::class, 'quoteIdentifier'], $columns));
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
