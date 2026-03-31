<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

class SyncState
{
    public static function ensureSchema(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS sync_state (
                table_name        VARCHAR(100) PRIMARY KEY,
                is_dirty          TINYINT(1)   NOT NULL DEFAULT 1,
                last_synced_at    DATETIME     NULL,
                last_attempted_at DATETIME     NULL,
                last_synced_count INT          NOT NULL DEFAULT 0,
                last_error        TEXT         NULL,
                updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function ensureRows(PDO $db, array $tables): void
    {
        $tables = array_values(array_unique(array_filter($tables, static fn ($table): bool => is_string($table) && $table !== '')));
        if ($tables === []) {
            return;
        }

        self::ensureSchema($db);

        $placeholders = implode(', ', array_fill(0, count($tables), '(?, 1, NOW())'));
        $stmt = $db->prepare(
            "INSERT IGNORE INTO sync_state (table_name, is_dirty, updated_at) VALUES {$placeholders}"
        );

        $params = [];
        foreach ($tables as $table) {
            $params[] = $table;
        }

        $stmt->execute($params);
    }

    public static function markDirty(PDO $db, string|array $tables): void
    {
        $tables = is_array($tables) ? $tables : [$tables];
        self::ensureRows($db, $tables);

        $placeholders = implode(', ', array_fill(0, count($tables), '?'));
        $stmt = $db->prepare("
            UPDATE sync_state
            SET is_dirty = 1,
                updated_at = NOW()
            WHERE table_name IN ({$placeholders})
        ");
        $stmt->execute(array_values($tables));
    }

    public static function markSynced(PDO $db, string $table, int $count): void
    {
        self::ensureRows($db, [$table]);

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

    public static function markFailed(PDO $db, string $table, string $error): void
    {
        self::ensureRows($db, [$table]);

        $stmt = $db->prepare("
            UPDATE sync_state
            SET is_dirty = 1,
                last_attempted_at = NOW(),
                last_error = ?,
                updated_at = NOW()
            WHERE table_name = ?
        ");
        $stmt->execute([$error, $table]);
    }

    public static function getStates(PDO $db, array $tables): array
    {
        self::ensureRows($db, $tables);

        $placeholders = implode(', ', array_fill(0, count($tables), '?'));
        $stmt = $db->prepare("SELECT * FROM sync_state WHERE table_name IN ({$placeholders})");
        $stmt->execute(array_values($tables));

        $states = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $states[$row['table_name']] = $row;
        }

        return $states;
    }

    public static function getState(PDO $db, string $table): array
    {
        $states = self::getStates($db, [$table]);
        return $states[$table] ?? [
            'table_name' => $table,
            'is_dirty' => 1,
            'last_synced_at' => null,
            'last_attempted_at' => null,
            'last_synced_count' => 0,
            'last_error' => null,
            'updated_at' => null,
        ];
    }
}
