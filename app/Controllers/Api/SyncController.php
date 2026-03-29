<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Env;

class SyncController extends BaseController
{
    public function status(): void
    {
        $this->requireAuth();

        $db = Database::getConnection();

        // Count pending transactions
        $pending = (int)$db->query(
            "SELECT COUNT(*) FROM transactions WHERE sync_status = 'pending'"
        )->fetchColumn();

        // Check internet / remote connectivity
        $remoteHost = Env::get('REMOTE_DB_HOST', '');
        $online = false;

        if ($remoteHost !== '') {
            $online = @fsockopen($remoteHost, 443, $errno, $errstr, 2) !== false;
        } else {
            // No remote configured — check general internet
            $online = @fsockopen('8.8.8.8', 53, $errno, $errstr, 2) !== false;
        }

        if (!$online) {
            $this->json(['status' => 'red', 'pending' => $pending, 'message' => 'Offline']);
        } elseif ($pending > 0) {
            $this->json(['status' => 'orange', 'pending' => $pending, 'message' => "{$pending} pending"]);
        } else {
            $this->json(['status' => 'green', 'pending' => 0, 'message' => 'Synced']);
        }
    }

    public function push(): void
    {
        $this->requireAuth();

        if (!class_exists('\App\Core\RemoteDatabase')) {
            $this->jsonError('Sync not configured.', 503);
        }

        $db = Database::getConnection();

        // Get unsynced transactions
        $stmt = $db->query("
            SELECT t.*, u.name AS cashier_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.cashier_id
            WHERE t.sync_status = 'pending'
            ORDER BY t.created_at ASC
            LIMIT 100
        ");
        $transactions = $stmt->fetchAll();

        if (empty($transactions)) {
            $this->json(['success' => true, 'synced' => 0, 'message' => 'Nothing to sync.']);
        }

        // Try remote
        $remote = \App\Core\RemoteDatabase::getConnection();

        if (!$remote) {
            // Log failure
            $db->prepare("INSERT INTO sync_log (direction, status, records_count, error_msg) VALUES ('push','failed',0,'Remote unavailable')")
               ->execute();
            $this->jsonError('Remote database unavailable.', 503);
        }

        $synced  = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($transactions as $txn) {
            try {
                // Push transaction and items to remote
                // This is a simplified push — a full implementation would use the remote schema
                $stmt = $remote->prepare("
                    INSERT IGNORE INTO transactions
                        (transaction_ref, cashier_name, subtotal, discount, total,
                         payment_method, cash_tendered, change_given, card_amount,
                         reference_number, terminal_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $txn['transaction_ref'],
                    $txn['cashier_name'],
                    $txn['subtotal'],
                    $txn['discount'],
                    $txn['total'],
                    $txn['payment_method'],
                    $txn['cash_tendered'],
                    $txn['change_given'],
                    $txn['card_amount'],
                    $txn['reference_number'],
                    $txn['terminal_id'],
                    $txn['created_at'],
                ]);

                // Mark as synced locally
                $db->prepare("UPDATE transactions SET sync_status = 'synced' WHERE id = ?")
                   ->execute([$txn['id']]);
                $synced++;

            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        // Log sync result
        $status = $failed === 0 ? 'success' : ($synced > 0 ? 'success' : 'failed');
        $db->prepare("INSERT INTO sync_log (direction, status, records_count, error_msg) VALUES ('push', ?, ?, ?)")
           ->execute([$status, $synced, $failed > 0 ? implode('; ', array_unique($errors)) : null]);

        $this->json([
            'success' => $failed === 0,
            'synced'  => $synced,
            'failed'  => $failed,
            'message' => "Synced {$synced} transactions." . ($failed > 0 ? " {$failed} failed." : ''),
        ]);
    }
}
