<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\SyncService;

class SyncController extends BaseController
{
    public function status(): void
    {
        $this->requireAuth();
        $this->json(SyncService::status(Database::getConnection()));
    }

    public function push(): void
    {
        $this->requireAuth();

        $result = (new SyncService())->push();
        $this->json($result, (int)($result['http_status'] ?? 200));
    }

    /**
     * Receive sync payload from a remote POS terminal (API-key auth).
     * POST /api/sync/receive
     */
    public function receive(): void
    {
        $db = Database::getConnection();

        // Authenticate via API key
        $row = $db->query("SELECT value FROM settings WHERE `key` = 'sync_api_key' LIMIT 1")->fetch();
        $expectedKey = trim($row['value'] ?? '');
        $providedKey = $_SERVER['HTTP_X_SYNC_API_KEY']
            ?? $_SERVER['HTTP_X_API_KEY']
            ?? $_GET['api_key']
            ?? '';

        if ($expectedKey === '' || $providedKey !== $expectedKey) {
            $this->json(['success' => false, 'error' => 'Invalid or missing API key.'], 403);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload) || !isset($payload['tables'])) {
            $this->json(['success' => false, 'error' => 'Invalid payload.'], 400);
        }

        $receiver = new SyncService(local: $db);
        $result = $receiver->receivePayload($payload['tables'], $payload['delete_missing'] ?? []);
        $this->json($result, $result['success'] ? 200 : 500);
    }
}
