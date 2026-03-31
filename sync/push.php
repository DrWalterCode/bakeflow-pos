<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\SyncService;

$result = (new SyncService())->push();

if (PHP_SAPI === 'cli') {
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($result['success'] ? 0 : 1);
}

http_response_code($result['http_status'] ?? 200);
header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
