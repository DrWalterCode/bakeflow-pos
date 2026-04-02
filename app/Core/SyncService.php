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
        'stock_adjustments',
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
        'stock_adjustments',
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
        'stock_adjustments' => ['id', 'product_id', 'adjustment_type', 'quantity', 'reason_code', 'reason_label', 'notes', 'previous_quantity', 'new_quantity', 'adjusted_by', 'created_at'],
        'daily_closings' => ['id', 'date', 'cashier_id', 'expected_cash', 'actual_cash', 'notes', 'closed_at'],
    ];

    private const DELETE_MISSING_TABLES = [
        'shops',
        'categories',
        'cake_sizes',
        'cake_flavours',
        'expense_categories',
        'specials',
        'products',
        'transactions',
        'transaction_items',
        'cake_orders',
        'expenses',
        'production_entries',
        'stock_adjustments',
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
        $pendingRecords = 0;

        foreach (self::TRACKED_TABLES as $table) {
            $state = $states[$table] ?? null;
            $count = self::pendingCountForTable($local, $table, $state);

            if ($count > 0) {
                $pendingTables[$table] = $count;
                $dirtyTables[] = $table;
                $pendingRecords += $count;
            }
        }

        // Badge shows dirty table count (not total records, which inflates for snapshot tables)
        $pending = count($dirtyTables);

        // Check settings table first, fall back to .env
        $remoteHost = '';
        $remotePort = 3306;
        $syncUrl = '';
        try {
            $cfgRows = $local->query("SELECT `key`, value FROM settings WHERE `key` IN ('remote_db_host','remote_db_port','sync_remote_url')")->fetchAll();
            foreach ($cfgRows as $r) {
                if ($r['key'] === 'remote_db_host') { $remoteHost = trim($r['value'] ?? ''); }
                if ($r['key'] === 'remote_db_port') { $remotePort = (int)($r['value'] ?: 3306); }
                if ($r['key'] === 'sync_remote_url') { $syncUrl = trim($r['value'] ?? ''); }
            }
        } catch (\Throwable $e) {}
        if ($remoteHost === '') {
            $remoteHost = trim(Env::get('REMOTE_DB_HOST', ''));
            $remotePort = (int)Env::get('REMOTE_DB_PORT', '3306');
        }
        $online = false;
        $configured = false;
        $message = 'Sync not configured';

        // Check direct MySQL connectivity
        if ($remoteHost !== '') {
            $configured = true;
            $socket = @fsockopen($remoteHost, $remotePort, $errno, $errstr, 2);
            if (is_resource($socket)) {
                fclose($socket);
                $online = true;
            }
        }

        // Check HTTP sync URL connectivity (fallback)
        if (!$online && $syncUrl !== '') {
            $configured = true;
            $parsed = parse_url($syncUrl);
            $host = $parsed['host'] ?? '';
            $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
            if ($host !== '') {
                $socket = @fsockopen(($port === 443 ? 'ssl://' : '') . $host, $port, $errno, $errstr, 3);
                if (is_resource($socket)) {
                    fclose($socket);
                    $online = true;
                }
            }
        }

        if ($configured) {
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

        if (!$configured) {
            $status = 'red';
        } elseif (!$online) {
            $status = 'red';
        } elseif ($dirtyTables !== []) {
            $status = 'orange';
            $message = count($dirtyTables) === 1 ? '1 to sync' : count($dirtyTables) . ' to sync';
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
            // Fall back to HTTP push if a remote URL is configured
            $remoteUrl = $this->getSetting($local, 'sync_remote_url');
            if ($remoteUrl !== '') {
                return $this->pushViaHttp($local, $remoteUrl);
            }

            $message = 'Remote database unavailable and no sync URL configured.';
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

        $foreignKeysDisabled = false;

        try {
            $this->remote->exec('SET FOREIGN_KEY_CHECKS = 0');
            $foreignKeysDisabled = true;

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
                    $recordsDeleted += $transactionsResult['deleted'] ?? 0;
                }
            }

            if ($errors === []) {
                foreach (['cake_orders', 'expenses', 'production_entries', 'stock_adjustments', 'daily_closings'] as $table) {
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
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        } finally {
            if ($foreignKeysDisabled) {
                try {
                    $this->remote->exec('SET FOREIGN_KEY_CHECKS = 1');
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
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

        $needsSync = ($state['last_synced_at'] ?? null) === null
            || (int)($state['is_dirty'] ?? 0) === 1
            || $pendingCount > 0;

        if (!$needsSync) {
            return [
                'table' => 'transactions',
                'status' => 'skipped',
                'records' => 0,
                'related_records' => 0,
                'deleted' => 0,
            ];
        }

        $transactions = $local->query(
            "SELECT " . $this->columnList('transactions') . " FROM transactions ORDER BY created_at ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $transactionIds = array_map(static fn (array $row): int => (int)$row['id'], $transactions);

        $items = $transactionIds === []
            ? []
            : $this->fetchRowsByIds($local, 'transaction_items', self::TABLE_COLUMNS['transaction_items'], 'transaction_id', $transactionIds);
        $itemIds = array_map(static fn (array $row): int => (int)$row['id'], $items);

        foreach ($transactions as &$transaction) {
            $transaction['sync_status'] = 'synced';
        }
        unset($transaction);

        try {
            $this->remote->beginTransaction();
            $this->bulkUpsert($this->remote, 'transactions', self::TABLE_COLUMNS['transactions'], $transactions);
            $this->bulkUpsert($this->remote, 'transaction_items', self::TABLE_COLUMNS['transaction_items'], $items);
            $deletedItems = $this->deleteMissingRows($this->remote, 'transaction_items', $itemIds);
            $deletedTransactions = $this->deleteMissingRows($this->remote, 'transactions', $transactionIds);
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
                'deleted' => $deletedItems + $deletedTransactions,
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

    // ------------------------------------------------------------------
    // HTTP-based sync (when direct MySQL is firewalled)
    // ------------------------------------------------------------------

    private function pushViaHttp(PDO $local, string $url): array
    {
        $apiKey = $this->getSetting($local, 'sync_api_key');

        // Build payload: collect dirty tables
        $tables = [];
        $deleteMissing = [];
        $totalRecords = 0;
        $tablesSynced = 0;

        // Phase 1: snapshot tables
        foreach (self::SNAPSHOT_ORDER as $table) {
            $state = SyncState::getState($local, $table);
            $needsSync = ($state['last_synced_at'] ?? null) === null || (int)($state['is_dirty'] ?? 0) === 1;
            if (!$needsSync) {
                continue;
            }

            $columns = self::TABLE_COLUMNS[$table] ?? [];
            $rows = $this->fetchAllRows($local, $table, $columns);
            $tables[$table] = ['columns' => $columns, 'rows' => $rows];
            $totalRecords += count($rows);

            if (in_array($table, self::DELETE_MISSING_TABLES, true)) {
                $deleteMissing[$table] = array_map(static fn(array $r): int => (int)$r['id'], $rows);
            }
        }

        // Phase 2: transactions
        $state = SyncState::getState($local, 'transactions');
        $pendingCount = (int)$local->query("SELECT COUNT(*) FROM transactions WHERE sync_status IN ('pending', 'failed')")->fetchColumn();
        $needsTransactionSync = ($state['last_synced_at'] ?? null) === null
            || (int)($state['is_dirty'] ?? 0) === 1
            || $pendingCount > 0;

        if ($needsTransactionSync) {
            $transactions = $local->query(
                "SELECT " . $this->columnList('transactions') . " FROM transactions ORDER BY id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            $transactionIds = array_map(static fn(array $r): int => (int)$r['id'], $transactions);

            // Mark as synced in the payload
            foreach ($transactions as &$t) {
                $t['sync_status'] = 'synced';
            }
            unset($t);

            $tables['transactions'] = ['columns' => self::TABLE_COLUMNS['transactions'], 'rows' => $transactions];
            $totalRecords += count($transactions);

            // Include related transaction_items
            $items = $transactionIds === [] ? []
                : $this->fetchRowsByIds($local, 'transaction_items', self::TABLE_COLUMNS['transaction_items'], 'transaction_id', $transactionIds);
            $tables['transaction_items'] = ['columns' => self::TABLE_COLUMNS['transaction_items'], 'rows' => $items];
            $totalRecords += count($items);
            $deleteMissing['transactions'] = $transactionIds;
            $deleteMissing['transaction_items'] = array_map(static fn(array $r): int => (int)$r['id'], $items);
        }

        if ($tables === []) {
            $this->logRun($local, 'success', 0, null);
            return [
                'success' => true, 'http_status' => 200,
                'tables_synced' => 0, 'records_synced' => 0,
                'records_deleted' => 0, 'records_failed' => 0,
                'results' => [], 'errors' => [],
                'message' => 'Nothing to sync.',
            ];
        }

        // Send HTTP POST
        $payload = json_encode(['tables' => $tables, 'delete_missing' => $deleteMissing], JSON_UNESCAPED_UNICODE);
        $receiveUrl = rtrim($url, '/');
        if (!str_ends_with($receiveUrl, '/receive')) {
            $receiveUrl .= '/receive';
        }

        $ch = curl_init($receiveUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Sync-API-Key: ' . $apiKey,
            ],
        ]);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode < 200 || $httpCode >= 300) {
            $message = $curlError ?: "Remote returned HTTP {$httpCode}";
            if ($body) {
                $decoded = json_decode($body, true);
                if (isset($decoded['error'])) {
                    $message = $decoded['error'];
                }
            }
            $this->logRun($local, 'failed', 0, $message);
            return [
                'success' => false, 'http_status' => 502,
                'tables_synced' => 0, 'records_synced' => 0,
                'records_deleted' => 0, 'records_failed' => 0,
                'results' => [], 'errors' => [$message],
                'message' => $message,
            ];
        }

        $response = json_decode($body, true) ?? [];

        // Mark local state as synced
        foreach (array_keys($tables) as $table) {
            if ($table === 'transaction_items') {
                continue;
            }
            SyncState::markSynced($local, $table, count($tables[$table]['rows']));
        }

        // Update transaction sync_status locally
        if (isset($tables['transactions'])) {
            $transactionIds = array_map(static fn(array $r): int => (int)$r['id'], $tables['transactions']['rows']);
            if ($transactionIds !== []) {
                $ph = implode(', ', array_fill(0, count($transactionIds), '?'));
                $local->prepare("UPDATE transactions SET sync_status = 'synced' WHERE id IN ({$ph})")->execute($transactionIds);
            }
        }

        $synced = $response['records_synced'] ?? $totalRecords;
        $this->logRun($local, 'success', $synced, null);

        return [
            'success' => true,
            'http_status' => 200,
            'tables_synced' => count($tables),
            'records_synced' => $synced,
            'records_deleted' => $response['records_deleted'] ?? 0,
            'records_failed' => 0,
            'results' => $response['results'] ?? [],
            'errors' => [],
            'message' => 'Sync completed via HTTP.',
        ];
    }

    /**
     * Receive a sync payload (server-side) and upsert into local DB.
     */
    public function receivePayload(array $tables, array $deleteMissing): array
    {
        $db = $this->local ?? Database::getConnection();
        $recordsSynced = 0;
        $recordsDeleted = 0;
        $results = [];

        // Snapshot tables: safe to truncate before inserting (full data sent)
        $snapshotTables = ['shops', 'users', 'categories', 'cake_sizes', 'cake_flavours',
            'expense_categories', 'specials', 'settings', 'products',
            'cake_orders', 'expenses', 'production_entries', 'stock_adjustments', 'daily_closings'];

        try {
            $db->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tables as $table => $data) {
                $columns = $data['columns'] ?? [];
                $rows = $data['rows'] ?? [];

                if ($columns === [] || !isset(self::TABLE_COLUMNS[$table]) && $table !== 'transaction_items') {
                    continue;
                }

                $db->beginTransaction();

                // For snapshot tables, delete all rows first to avoid unique key conflicts
                if (in_array($table, $snapshotTables, true) && $rows !== []) {
                    $db->exec('DELETE FROM ' . self::quoteIdentifier($table));
                }

                $this->bulkUpsert($db, $table, $columns, $rows);

                $deleted = 0;
                if (isset($deleteMissing[$table])) {
                    $localIds = $deleteMissing[$table];
                    $deleted = $this->deleteMissingRows($db, $table, $localIds);
                }

                $db->commit();
                $recordsSynced += count($rows);
                $recordsDeleted += $deleted;

                $results[] = ['table' => $table, 'status' => 'synced', 'records' => count($rows), 'deleted' => $deleted];
            }

            $db->exec('SET FOREIGN_KEY_CHECKS = 1');

            return [
                'success' => true,
                'records_synced' => $recordsSynced,
                'records_deleted' => $recordsDeleted,
                'results' => $results,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'records_synced' => $recordsSynced,
                'records_deleted' => $recordsDeleted,
                'results' => $results,
            ];
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function getSetting(PDO $db, string $key): string
    {
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        return trim($stmt->fetchColumn() ?: '');
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
