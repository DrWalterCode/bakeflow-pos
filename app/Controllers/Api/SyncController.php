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
}
