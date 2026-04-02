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

$disableOtherAdmins = in_array('--disable-other-admins', $argv, true);

$users = [
    [
        'name' => 'Taona Mapisa',
        'username' => 'taona',
        'role' => 'cashier',
        'pin' => '1234',
        'password' => null,
    ],
    [
        'name' => 'Trymore Mawokomayi',
        'username' => 'trymore',
        'role' => 'cashier',
        'pin' => '1234',
        'password' => null,
    ],
    [
        'name' => 'Mellisa Shikova',
        'username' => 'mellisa',
        'role' => 'cashier',
        'pin' => '1234',
        'password' => null,
    ],
    [
        'name' => 'Precious Mazuru',
        'username' => 'precious',
        'role' => 'cashier',
        'pin' => '1234',
        'password' => null,
    ],
    [
        'name' => 'Amos Mapisa',
        'username' => 'amos',
        'role' => 'cashier',
        'pin' => '1234',
        'password' => null,
    ],
    [
        'name' => 'Tawanda Sireti',
        'username' => 'tawanda',
        'role' => 'admin',
        'pin' => null,
        'password' => 'admin123',
    ],
    [
        'name' => 'Lynette Mahlaba',
        'username' => 'lynette',
        'role' => 'admin',
        'pin' => null,
        'password' => 'admin123',
    ],
];

$db = Database::getConnection();

$desiredCashierUsernames = array_values(array_map(
    static fn (array $user): string => $user['username'],
    array_filter($users, static fn (array $user): bool => $user['role'] === 'cashier')
));

$desiredAdminUsernames = array_values(array_map(
    static fn (array $user): string => $user['username'],
    array_filter($users, static fn (array $user): bool => $user['role'] === 'admin')
));

$summary = [
    'deactivated_cashiers' => 0,
    'deactivated_admins' => 0,
    'created' => [],
    'updated' => [],
    'credentials' => [
        'cashier_pin' => '1234',
        'admin_password' => 'admin123',
    ],
];

/**
 * @param list<string> $values
 */
function makeInClause(array $values): string
{
    return implode(', ', array_fill(0, count($values), '?'));
}

SyncState::ensureSchema($db);

try {
    $db->beginTransaction();

    if ($desiredCashierUsernames !== []) {
        $stmt = $db->prepare(
            'UPDATE users
             SET is_active = 0, pin_fail_count = 0, pin_locked_until = NULL
             WHERE role = ? AND username NOT IN (' . makeInClause($desiredCashierUsernames) . ')'
        );
        $stmt->execute(array_merge(['cashier'], $desiredCashierUsernames));
    } else {
        $stmt = $db->prepare(
            'UPDATE users
             SET is_active = 0, pin_fail_count = 0, pin_locked_until = NULL
             WHERE role = ?'
        );
        $stmt->execute(['cashier']);
    }
    $summary['deactivated_cashiers'] = $stmt->rowCount();

    if ($disableOtherAdmins) {
        if ($desiredAdminUsernames !== []) {
            $stmt = $db->prepare(
                'UPDATE users
                 SET is_active = 0, pin_fail_count = 0, pin_locked_until = NULL
                 WHERE role = ? AND username NOT IN (' . makeInClause($desiredAdminUsernames) . ')'
            );
            $stmt->execute(array_merge(['admin'], $desiredAdminUsernames));
        } else {
            $stmt = $db->prepare(
                'UPDATE users
                 SET is_active = 0, pin_fail_count = 0, pin_locked_until = NULL
                 WHERE role = ?'
            );
            $stmt->execute(['admin']);
        }
        $summary['deactivated_admins'] = $stmt->rowCount();
    }

    $findStmt = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $insertStmt = $db->prepare(
        'INSERT INTO users (name, username, password_hash, pin_hash, role, is_active, pin_fail_count, pin_locked_until)
         VALUES (?, ?, ?, ?, ?, 1, 0, NULL)'
    );
    $updateStmt = $db->prepare(
        'UPDATE users
         SET name = ?,
             password_hash = ?,
             pin_hash = ?,
             role = ?,
             is_active = 1,
             pin_fail_count = 0,
             pin_locked_until = NULL
         WHERE id = ?'
    );

    foreach ($users as $user) {
        $passwordHash = $user['password'] !== null
            ? password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])
            : null;
        $pinHash = $user['pin'] !== null
            ? password_hash($user['pin'], PASSWORD_BCRYPT, ['cost' => 12])
            : null;

        $findStmt->execute([$user['username']]);
        $existingId = $findStmt->fetchColumn();

        if ($existingId === false) {
            $insertStmt->execute([
                $user['name'],
                $user['username'],
                $passwordHash,
                $pinHash,
                $user['role'],
            ]);
            $summary['created'][] = $user['username'];
            continue;
        }

        $updateStmt->execute([
            $user['name'],
            $passwordHash,
            $pinHash,
            $user['role'],
            (int)$existingId,
        ]);
        $summary['updated'][] = $user['username'];
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR, 'User reset failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

SyncState::markDirty($db, 'users');

echo json_encode([
    'success' => true,
    'disable_other_admins' => $disableOtherAdmins,
    'message' => 'POS users were reset successfully.',
    'summary' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
