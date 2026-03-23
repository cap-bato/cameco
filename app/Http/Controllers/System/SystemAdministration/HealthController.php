<?php

namespace App\Http\Controllers\System\SystemAdministration;

use App\Http\Controllers\Controller;
use App\Services\System\SystemHealthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HealthController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService
    ) {}

    /**
     * Display detailed server health monitoring page
     */
    public function index(Request $request): Response
    {
        $days = $request->input('days', 7); // Default to 7 days

        $data = [
            'currentMetrics' => $this->healthService->getServerHealthMetrics($days),
            'databaseMetrics' => $this->healthService->getDatabaseMetrics(),
            'cacheMetrics' => $this->healthService->getCacheMetrics(),
            'queueMetrics' => $this->healthService->getQueueMetrics(),
            'storageMetrics' => $this->healthService->getStorageMetrics(),
            'historicalData' => $this->healthService->getHistoricalHealthData($days),
            'selectedDays' => $days,
        ];

        return Inertia::render('System/Health', $data);
    }

    /**
     * Refresh health metrics (force cache clear)
     */
    public function refresh(): \Illuminate\Http\JsonResponse
    {
        cache()->forget('system_health_dashboard_metrics');
        
        return response()->json([
            'success' => true,
            'message' => 'Health metrics refreshed successfully',
            'data' => $this->healthService->getDashboardMetrics(),
        ]);
    }
}
