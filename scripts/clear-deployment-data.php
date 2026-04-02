<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/app/Core/Env.php';
require_once APP_ROOT . '/app/Core/Database.php';
require_once APP_ROOT . '/app/Core/SyncState.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\SyncState;

if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    echo "This script can only be run from the command line." . PHP_EOL;
    exit(1);
}

Env::load(APP_ROOT . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Africa/Harare'));

$db = Database::getConnection();

/**
 * @return int
 */
function countRows(PDO $db, string $table): int
{
    return (int)$db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

/**
 * Mark a table as locally clean after deployment prep so the new POS starts from a neutral sync state.
 */
function markTableClean(PDO $db, string $table, int $count): void
{
    SyncState::ensureRows($db, [$table]);

    $stmt = $db->prepare("
        UPDATE sync_state
        SET is_dirty = 0,
            last_synced_at = NOW(),
            last_attempted_at = NOW(),
            last_synced_count = ?,
            last_error = NULL,
            updated_at = NOW()
        WHERE table_name = ?
    ");
    $stmt->execute([$count, $table]);
}

$summary = [
    'cake_orders_deleted' => countRows($db, 'cake_orders'),
    'transactions_deleted' => countRows($db, 'transactions'),
    'transaction_items_deleted' => countRows($db, 'transaction_items'),
    'expenses_deleted' => countRows($db, 'expenses'),
    'production_entries_deleted' => countRows($db, 'production_entries'),
    'daily_closings_deleted' => countRows($db, 'daily_closings'),
    'sync_log_deleted' => countRows($db, 'sync_log'),
];

try {
    $db->beginTransaction();

    $db->exec('DELETE FROM `cake_orders`');
    $db->exec('DELETE FROM `transactions`');
    $db->exec('DELETE FROM `expenses`');
    $db->exec('DELETE FROM `production_entries`');
    $db->exec('DELETE FROM `daily_closings`');
    $db->exec('DELETE FROM `sync_log`');

    $productStockStmt = $db->prepare('UPDATE `products` SET `stock_quantity` = 0 WHERE `stock_quantity` <> 0');
    $productStockStmt->execute();
    $summary['products_stock_zeroed'] = $productStockStmt->rowCount();

    $userStateStmt = $db->prepare('
        UPDATE `users`
        SET `pin_fail_count` = 0,
            `pin_locked_until` = NULL,
            `last_login_at` = NULL
        WHERE `pin_fail_count` <> 0
           OR `pin_locked_until` IS NOT NULL
           OR `last_login_at` IS NOT NULL
    ');
    $userStateStmt->execute();
    $summary['users_state_reset'] = $userStateStmt->rowCount();

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR, 'Deployment cleanup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

foreach ([
    'transaction_items',
    'transactions',
    'cake_orders',
    'expenses',
    'production_entries',
    'daily_closings',
    'sync_log',
] as $table) {
    $db->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
}

markTableClean($db, 'users', countRows($db, 'users'));
markTableClean($db, 'products', countRows($db, 'products'));
markTableClean($db, 'transactions', 0);
markTableClean($db, 'cake_orders', 0);
markTableClean($db, 'expenses', 0);
markTableClean($db, 'production_entries', 0);
markTableClean($db, 'daily_closings', 0);

echo json_encode([
    'success' => true,
    'message' => 'Deployment data cleared successfully.',
    'summary' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
