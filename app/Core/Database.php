<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host   = Env::get('DB_HOST', '127.0.0.1');
        $port   = Env::get('DB_PORT', '3306');
        $dbName = Env::get('DB_DATABASE', 'bakeflow_pos');
        $user   = Env::get('DB_USERNAME', 'root');
        $pass   = Env::get('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }

        // Check if database has been initialised (shops table has data)
        $check = self::$pdo->query("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = '{$dbName}' AND table_name = 'shops'")->fetch();
        $isNew = ((int)($check['cnt'] ?? 0) === 0);

        if ($isNew) {
            self::initialise();
        }

        // Run pending migrations on every connect.
        // For fresh databases, $isNew tells migrate() to just mark all
        // migrations as applied because the base MySQL schema is already current.
        self::migrate($isNew);

        return self::$pdo;
    }

    public static function getConnection(): PDO
    {
        return self::connect();
    }

    private static function initialise(): void
    {
        $schemaFile = APP_ROOT . '/database/schema_mysql.sql';
        $seedFile   = APP_ROOT . '/database/seed_mysql.sql';

        if (file_exists($schemaFile)) {
            self::executeMultiStatement(file_get_contents($schemaFile));
        }
        if (file_exists($seedFile)) {
            self::executeMultiStatement(file_get_contents($seedFile));
        }
    }

    /**
     * Execute a SQL string containing multiple statements.
     */
    private static function executeMultiStatement(string $sql): void
    {
        $statements = preg_split('/;\s*$/m', $sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                self::$pdo->exec($stmt);
            }
        }
    }

    /**
     * Run all pending migration files from database/migrations/.
     *
     * Each file is a plain SQL script named like:
     *   0001_create_expense_tables.sql
     *   0002_add_stock_quantity.sql
     *
     * The migrations table tracks which files have already been applied,
     * so each migration runs exactly once — even on repeated server starts.
     *
     * @param bool $freshDb  True when the base MySQL schema just ran (fresh install).
     *                       In that case we skip executing the migration SQL and
     *                       just mark the files as applied.
     */
    private static function migrate(bool $freshDb = false): void
    {
        $migrationsDir = APP_ROOT . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            return;
        }

        // Ensure the migrations tracking table exists
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                filename   VARCHAR(255) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Collect already-applied migrations
        $applied = [];
        $rows = self::$pdo->query("SELECT filename FROM migrations")->fetchAll();
        foreach ($rows as $row) {
            $applied[$row['filename']] = true;
        }

        // Discover and sort migration files
        $files = glob($migrationsDir . '/*.sql');
        if (!$files) {
            return;
        }
        sort($files); // alphabetical = chronological with 0001_ prefix

        foreach ($files as $file) {
            $filename = basename($file);
            if (isset($applied[$filename])) {
                continue; // already applied
            }

            // On fresh databases the base schema already has everything,
            // so we just record the migration without executing it.
            if (!$freshDb) {
                $sql = file_get_contents($file);
                if ($sql === false || trim($sql) === '') {
                    continue;
                }

                // Split on semicolons and run each statement individually.
                // This way an expected error (e.g. ALTER TABLE ADD COLUMN
                // on a column that already exists) doesn't block the rest.
                $statements = preg_split('/;\s*$/m', $sql);
                foreach ($statements as $stmt_sql) {
                    $stmt_sql = trim($stmt_sql);
                    if ($stmt_sql === '') {
                        continue;
                    }
                    try {
                        self::$pdo->exec($stmt_sql);
                    } catch (PDOException $e) {
                        // Tolerate duplicate-column ALTER TABLE retries.
                        if (stripos($e->getMessage(), 'duplicate column') !== false
                            || stripos($e->getMessage(), 'Duplicate column name') !== false) {
                            continue;
                        }
                        throw $e; // re-throw anything unexpected
                    }
                }
            }

            // Record it so it never runs again
            $stmt = self::$pdo->prepare("INSERT INTO migrations (filename) VALUES (?)");
            $stmt->execute([$filename]);
        }
    }
}
