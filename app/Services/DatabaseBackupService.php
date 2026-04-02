<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PDO;
use RuntimeException;

class DatabaseBackupService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function createBackup(): array
    {
        @set_time_limit(300);

        $directory = $this->resolveBackupDirectory();
        $databaseName = Env::get('DB_DATABASE', 'bakeflow_pos');
        $timestamp = date('Y-m-d_H-i-s');
        $filename = sprintf('%s_backup_%s.sql', $this->slugify($databaseName), $timestamp);
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        $bytesWritten = file_put_contents($path, $this->buildSqlDump($databaseName));
        if ($bytesWritten === false) {
            throw new RuntimeException('Unable to write the backup file.');
        }

        return [
            'path' => $path,
            'filename' => $filename,
            'directory' => $directory,
        ];
    }

    private function resolveBackupDirectory(): string
    {
        $directories = [];

        $userProfile = trim((string)getenv('USERPROFILE'));
        if ($userProfile !== '') {
            $directories[] = $userProfile . DIRECTORY_SEPARATOR . 'Documents';
        }

        $home = trim((string)getenv('HOME'));
        if ($home !== '') {
            $directories[] = $home . DIRECTORY_SEPARATOR . 'Documents';
        }

        foreach ($directories as $documentsDirectory) {
            if (!is_dir($documentsDirectory)) {
                continue;
            }

            $backupDirectory = $documentsDirectory . DIRECTORY_SEPARATOR . 'BakeFlow POS Backups';
            if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0777, true) && !is_dir($backupDirectory)) {
                continue;
            }

            if (is_writable($backupDirectory)) {
                return $backupDirectory;
            }
        }

        $fallbackDirectory = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($fallbackDirectory) && !mkdir($fallbackDirectory, 0777, true) && !is_dir($fallbackDirectory)) {
            throw new RuntimeException('Unable to create a backup directory.');
        }

        if (!is_writable($fallbackDirectory)) {
            throw new RuntimeException('No writable backup directory is available.');
        }

        return $fallbackDirectory;
    }

    private function buildSqlDump(string $databaseName): string
    {
        $lines = [
            '-- BakeFlow POS database backup',
            '-- Database: ' . $databaseName,
            '-- Generated: ' . date('Y-m-d H:i:s'),
            '',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
        ];

        foreach ($this->fetchTableNames() as $tableName) {
            $escapedTable = $this->escapeIdentifier($tableName);
            $createTable = $this->db->query('SHOW CREATE TABLE `' . $escapedTable . '`')->fetch();
            if (!is_array($createTable) || !isset($createTable['Create Table'])) {
                throw new RuntimeException('Failed to read schema for table "' . $tableName . '".');
            }

            $lines[] = '-- --------------------------------------------------------';
            $lines[] = '-- Table structure for `' . $tableName . '`';
            $lines[] = '-- --------------------------------------------------------';
            $lines[] = 'DROP TABLE IF EXISTS `' . $escapedTable . '`;';
            $lines[] = $createTable['Create Table'] . ';';
            $lines[] = '';

            $rowStatements = $this->buildInsertStatements($tableName);
            if ($rowStatements !== []) {
                $lines[] = '-- Data for table `' . $tableName . '`';
                array_push($lines, ...$rowStatements);
                $lines[] = '';
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @return list<string>
     */
    private function fetchTableNames(): array
    {
        $stmt = $this->db->query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ");

        $tables = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tableName = (string)($row['table_name'] ?? '');
            if ($tableName !== '') {
                $tables[] = $tableName;
            }
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private function buildInsertStatements(string $tableName): array
    {
        $escapedTable = $this->escapeIdentifier($tableName);
        $stmt = $this->db->query('SELECT * FROM `' . $escapedTable . '`');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map(
            fn (string $column): string => '`' . $this->escapeIdentifier($column) . '`',
            $columns
        ));

        $statements = [];
        $batch = [];
        $batchSize = 100;

        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $values[] = $value === null ? 'NULL' : $this->db->quote((string)$value);
            }
            $batch[] = '(' . implode(', ', $values) . ')';

            if (count($batch) >= $batchSize) {
                $statements[] = 'INSERT INTO `' . $escapedTable . '` (' . $columnList . ') VALUES ' . implode(",\n", $batch) . ';';
                $batch = [];
            }
        }

        if ($batch !== []) {
            $statements[] = 'INSERT INTO `' . $escapedTable . '` (' . $columnList . ') VALUES ' . implode(",\n", $batch) . ';';
        }

        return $statements;
    }

    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? 'backup';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'backup';
    }
}
