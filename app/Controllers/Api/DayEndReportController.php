<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Services\DayEndReportService;

class DayEndReportController extends BaseController
{
    public function show(): void
    {
        $this->requireAuth();

        try {
            $service = new DayEndReportService();
            $date = $_GET['date'] ?? date('Y-m-d');
            $report = $service->buildReport($date, true);

            $this->json([
                'success' => true,
                'report'  => $report,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }

    public function close(): void
    {
        $this->requireAdmin();
        $this->verifyJsonCsrf();

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];

        try {
            $service = new DayEndReportService();
            $report = $service->closeDay(
                $input['date'] ?? date('Y-m-d'),
                (float)($input['actual_cash'] ?? 0),
                isset($input['notes']) ? (string)$input['notes'] : null,
                (int)(Auth::id() ?? 0)
            );

            $this->json([
                'success' => true,
                'status'  => 'closed',
                'message' => 'Day closed successfully.',
                'closure' => $report['closure'],
                'report'  => $report,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }

    public function reopen(): void
    {
        $this->requireAdmin();
        $this->verifyJsonCsrf();

        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];

        try {
            $service = new DayEndReportService();
            $report = $service->reopenDay(
                $input['date'] ?? date('Y-m-d'),
                isset($input['reason']) ? (string)$input['reason'] : null,
                (int)(Auth::id() ?? 0)
            );

            $this->json([
                'success' => true,
                'status'  => 'open',
                'message' => 'Day reopened successfully.',
                'closure' => $report['closure'],
                'report'  => $report,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
}
