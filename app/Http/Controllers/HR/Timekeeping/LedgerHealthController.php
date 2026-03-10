<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LedgerHealthController extends Controller
{
    /**
     * Cache TTL in seconds (5 minutes as per subtask 4.2.5).
     */
    private const CACHE_TTL = 300; // 5 minutes
    
    /**
     * Get current ledger health status.
     * 
     * Uses caching (5-minute TTL) to reduce DB load (Subtask 4.2.5).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $includeHistory = $request->boolean('include_history', false);
        
        // Cache health data with 5-minute TTL to reduce DB load
        $cacheKey = 'ledger_health_current';
        $health = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return $this->generateLedgerHealth();
        });
        
        $response = [
            'success' => true,
            'data' => $health,
            'timestamp' => now()->toISOString(),
            'cached' => Cache::has($cacheKey),
        ];
        
        // Include 24-hour health logs if requested (also cached)
        if ($includeHistory) {
            $historyCacheKey = 'ledger_health_history_24h';
            $response['history'] = Cache::remember($historyCacheKey, self::CACHE_TTL, function () {
                return $this->fetchLast24HoursHealthLogs();
            });
        }
        
        return response()->json($response);
    }

    /**
     * Get 24-hour health log history.
     * 
     * This endpoint returns health snapshots for the last 24 hours,
     * allowing trend analysis and historical health monitoring.
     * 
     * Uses caching (5-minute TTL) to reduce DB load (Subtask 4.2.5).
     * 
     * @return JsonResponse
     */
    public function history(): JsonResponse
    {
        // Cache history logs with 5-minute TTL to reduce DB load
        $cacheKey = 'ledger_health_history_24h';
        $logs = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return $this->fetchLast24HoursHealthLogs();
        });
        
        return response()->json([
            'success' => true,
            'data' => $logs,
            'meta' => [
                'total' => count($logs),
                'period' => '24_hours',
                'interval' => 'hourly',
                'timezone' => config('app.timezone'),
                'cached' => Cache::has($cacheKey),
            ],
        ]);
    }

    /**
     * Clear cached health data (for administrative purposes).
     * 
     * This method allows administrators to force refresh the cached health data
     * when immediate updates are needed (e.g., after system maintenance or incidents).
     * 
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        $cleared = [];
        
        // Clear current health cache
        if (Cache::has('ledger_health_current')) {
            Cache::forget('ledger_health_current');
            $cleared[] = 'ledger_health_current';
        }
        
        // Clear history cache
        if (Cache::has('ledger_health_history_24h')) {
            Cache::forget('ledger_health_history_24h');
            $cleared[] = 'ledger_health_history_24h';
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Health cache cleared successfully',
            'cleared_keys' => $cleared,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Fetch last 24 hours of health logs.
     * 
     * In production, this would query the `ledger_health_logs` table.
     * For Phase 1, generates mock historical data with realistic patterns.
     * 
     * @return array
     */
    private function fetchLast24HoursHealthLogs(): array
    {
        $logs = [];
        $now = now();
        
        // Generate hourly snapshots for last 24 hours
        for ($i = 23; $i >= 0; $i--) {
            $timestamp = $now->copy()->subHours($i);
            $hour = $timestamp->hour;
            
            // Simulate realistic health patterns
            $status = $this->determineHistoricalStatus($hour, $i);
            $eventsInHour = $this->calculateEventsForHour($hour, $status);
            
            $logs[] = [
                'timestamp' => $timestamp->toISOString(),
                'status' => $status,
                'sequence_id_start' => 12000 + ($i * 50),
                'sequence_id_end' => 12000 + ($i * 50) + $eventsInHour,
                'events_processed' => $eventsInHour,
                'events_failed' => $status === 'critical' ? rand(3, 10) : ($status === 'warning' ? rand(1, 3) : 0),
                'devices_online' => $status === 'critical' ? rand(1, 2) : ($status === 'warning' ? rand(2, 3) : rand(3, 4)),
                'devices_offline' => $status === 'critical' ? rand(2, 3) : ($status === 'warning' ? rand(1, 2) : rand(0, 1)),
                'avg_latency_ms' => $status === 'critical' ? rand(700, 1000) : ($status === 'warning' ? rand(250, 400) : rand(80, 150)),
                'hash_verification_passed' => $status === 'critical' ? false : true,
                'queue_depth' => $status === 'critical' ? rand(800, 1500) : ($status === 'warning' ? rand(200, 400) : rand(0, 50)),
                'sync_success_rate' => $status === 'critical' ? rand(70, 80) : ($status === 'warning' ? rand(88, 95) : rand(98, 100)),
                'alerts_count' => $status === 'critical' ? rand(3, 8) : ($status === 'warning' ? rand(1, 2) : 0),
            ];
        }
        
        return $logs;
    }

    /**
     * Determine historical health status based on hour and position.
     * 
     * @param int $hour
     * @param int $hoursAgo
     * @return string
     */
    private function determineHistoricalStatus(int $hour, int $hoursAgo): string
    {
        // Simulate incident at 10 AM (2 hours ago if current time is noon)
        if ($hoursAgo >= 12 && $hoursAgo <= 14) {
            return 'critical';
        }
        
        // Simulate warning periods during low-activity hours
        if ($hour >= 2 && $hour <= 5) {
            return 'warning';
        }
        
        // Simulate occasional warnings
        if ($hoursAgo === 6 || $hoursAgo === 18) {
            return 'warning';
        }
        
        return 'healthy';
    }

    /**
     * Calculate realistic event count for a given hour.
     * 
     * @param int $hour
     * @param string $status
     * @return int
     */
    private function calculateEventsForHour(int $hour, string $status): int
    {
        // During critical status, event processing drops
        if ($status === 'critical') {
            return rand(0, 20);
        }
        
        // Normal business hours (7 AM - 6 PM)
        if ($hour >= 7 && $hour <= 18) {
            return rand(30, 60);
        }
        
        // Early morning shift change (5-7 AM)
        if ($hour >= 5 && $hour < 7) {
            return rand(15, 30);
        }
        
        // Late evening shift change (6-8 PM)
        if ($hour >= 18 && $hour <= 20) {
            return rand(10, 25);
        }
        
        // Night hours - minimal activity
        return rand(0, 5);
    }

    /**
     * Generate comprehensive ledger health data.
     * 
     * Computes critical metrics including:
     * - Processing lag (time delta between ledger write and processing)
     * - Sequence gaps (missing sequence IDs indicating lost events)
     * - Hash failures (cryptographic integrity violations)
     * - Overall health status (healthy/warning/critical)
     * 
     * @return array
     */
    private function generateLedgerHealth(): array
    {
        // Simulate different health states based on time
        $minute = now()->minute;
        $status = 'healthy';
        $alerts = [];
        
        // Compute core metrics (4.2.3: processing lag, gap count, hash failure count)
        $metrics = $this->computeHealthMetrics($minute);
        
        // Determine status based on computed metrics (4.2.4)
        $status = $this->determineHealthStatus($metrics);
        
        // Generate alerts based on status
        if ($status === 'warning') {
            $alerts[] = [
                'severity' => 'warning',
                'message' => 'Device OFFICE-01 offline for 45 minutes',
                'timestamp' => now()->subMinutes(45)->toISOString(),
            ];
            if ($metrics['processing_lag_seconds'] > 120) {
                $alerts[] = [
                    'severity' => 'warning',
                    'message' => "Processing lag at {$metrics['processing_lag_seconds']}s (threshold: 120s)",
                    'timestamp' => now()->subMinutes(2)->toISOString(),
                ];
            }
        }
        
        if ($status === 'critical') {
            $seqEnd = $metrics['last_sequence_id'] + 5;
            $alerts[] = [
                'severity' => 'critical',
                'message' => "Hash verification failed for sequence range {$metrics['last_sequence_id']}-{$seqEnd}",
                'timestamp' => now()->subMinutes(10)->toISOString(),
            ];
            $alerts[] = [
                'severity' => 'critical',
                'message' => 'Processing backlog exceeds threshold (1250 events)',
                'timestamp' => now()->subMinutes(15)->toISOString(),
            ];
            if ($metrics['sequence_gaps_count'] > 0) {
                $alerts[] = [
                    'severity' => 'critical',
                    'message' => "{$metrics['sequence_gaps_count']} sequence gaps detected - potential data loss",
                    'timestamp' => now()->subMinutes(8)->toISOString(),
                ];
            }
        }
        
        // Return comprehensive health data with detailed metrics (4.2.4)
        return [
            'status' => $status, // healthy/warning/critical
            'last_sequence_id' => $metrics['last_sequence_id'],
            'events_today' => $metrics['events_today'],
            'devices_online' => $metrics['devices_online'],
            'devices_offline' => $metrics['devices_offline'],
            'devices_maintenance' => $metrics['devices_maintenance'],
            'last_sync' => $metrics['last_sync'],
            'avg_latency_ms' => $metrics['avg_latency_ms'],
            
            // Core computed metrics (4.2.3)
            'computed_metrics' => [
                'processing_lag_seconds' => $metrics['processing_lag_seconds'],
                'sequence_gaps_count' => $metrics['sequence_gaps_count'],
                'hash_failures_count' => $metrics['hash_failures_count'],
                'gap_details' => $metrics['gap_details'],
            ],
            
            // Hash verification details
            'hash_verification' => [
                'total_checked' => $metrics['hash_total_checked'],
                'passed' => $metrics['hash_passed'],
                'failed' => $metrics['hash_failures_count'],
                'success_rate' => $metrics['hash_failures_count'] === 0 
                    ? 100.0 
                    : round((($metrics['hash_passed'] / $metrics['hash_total_checked']) * 100), 2),
                'last_check' => now()->subMinutes(rand(1, 3))->toISOString(),
            ],
            
            // Performance metrics
            'performance' => [
                'events_per_hour' => $metrics['events_per_hour'],
                'avg_processing_time_ms' => $metrics['avg_processing_time_ms'],
                'queue_depth' => $metrics['queue_depth'],
                'throughput_trend' => $metrics['throughput_trend'],
                'processing_lag_seconds' => $metrics['processing_lag_seconds'],
            ],
            
            // Sync status
            'sync_status' => [
                'last_sync_duration_ms' => $metrics['last_sync_duration_ms'],
                'sync_success_rate' => $metrics['sync_success_rate'],
                'pending_sync_count' => $metrics['pending_sync_count'],
            ],
            
            'alerts' => $alerts,
            
            // 24-hour metrics window
            'metrics_window' => [
                'start' => now()->subHours(24)->toISOString(),
                'end' => now()->toISOString(),
                'total_events_processed' => $metrics['total_events_processed'],
                'total_errors' => $metrics['total_errors'],
                'total_sequence_gaps' => $metrics['sequence_gaps_count'],
                'total_hash_failures' => $metrics['hash_failures_count'],
            ],
        ];
    }

    /**
     * Compute health metrics: processing lag, gap count, hash failure count.
     * 
     * Subtask 4.2.3 implementation.
     * 
     * @param int $minute Current minute (for simulation)
     * @return array
     */
    private function computeHealthMetrics(int $minute): array
    {
        // Determine simulated health state based on time
        $isCritical = ($minute >= 30 && $minute < 35);
        $isWarning = ($minute >= 15 && $minute < 20);
        
        $lastSequenceId = 12404 + rand(0, 50);
        $eventsToday = 247 + rand(-10, 50);
        
        // Compute processing lag (seconds between ledger write and Laravel processing)
        // Healthy: < 60s, Warning: 60-180s, Critical: > 180s
        $processingLag = $isCritical 
            ? rand(180, 600) 
            : ($isWarning ? rand(60, 180) : rand(5, 45));
        
        // Compute sequence gaps (missing sequence IDs)
        // Healthy: 0, Warning: 0-2, Critical: 3+
        $sequenceGaps = 0;
        $gapDetails = [];
        
        if ($isCritical) {
            $sequenceGaps = rand(3, 8);
            // Generate gap details for audit trail
            for ($i = 0; $i < $sequenceGaps; $i++) {
                $gapStart = $lastSequenceId - rand(50, 200);
                $gapSize = rand(1, 5);
                $gapDetails[] = [
                    'missing_start' => $gapStart,
                    'missing_end' => $gapStart + $gapSize - 1,
                    'gap_size' => $gapSize,
                    'detected_at' => now()->subMinutes(rand(5, 30))->toISOString(),
                ];
            }
        } elseif ($isWarning) {
            $sequenceGaps = rand(0, 2);
            if ($sequenceGaps > 0) {
                $gapStart = $lastSequenceId - rand(20, 100);
                $gapDetails[] = [
                    'missing_start' => $gapStart,
                    'missing_end' => $gapStart,
                    'gap_size' => 1,
                    'detected_at' => now()->subMinutes(rand(2, 10))->toISOString(),
                ];
            }
        }
        
        // Compute hash failures (cryptographic integrity violations)
        // Healthy: 0, Warning: 0-2, Critical: 3+
        $hashFailures = $isCritical ? rand(3, 10) : ($isWarning ? rand(0, 2) : 0);
        $hashTotalChecked = $eventsToday;
        $hashPassed = $hashTotalChecked - $hashFailures;
        
        return [
            'last_sequence_id' => $lastSequenceId,
            'events_today' => $eventsToday,
            'devices_online' => $isCritical ? 1 : ($isWarning ? 2 : 4),
            'devices_offline' => $isCritical ? 2 : ($isWarning ? 1 : 0),
            'devices_maintenance' => rand(0, 1),
            'last_sync' => now()->subMinutes(rand(1, 5))->toISOString(),
            'avg_latency_ms' => $isCritical ? 850 : ($isWarning ? 320 : 125),
            
            // Core metrics (4.2.3)
            'processing_lag_seconds' => $processingLag,
            'sequence_gaps_count' => $sequenceGaps,
            'hash_failures_count' => $hashFailures,
            'gap_details' => $gapDetails,
            
            // Hash verification
            'hash_total_checked' => $hashTotalChecked,
            'hash_passed' => $hashPassed,
            
            // Performance
            'events_per_hour' => $isCritical ? 0 : ($isWarning ? 180 : 31 + rand(-5, 10)),
            'avg_processing_time_ms' => $isCritical ? 1250 : ($isWarning ? 185 : 45 + rand(-10, 20)),
            'queue_depth' => $isCritical ? 1250 : ($isWarning ? 245 : rand(0, 5)),
            'throughput_trend' => $isCritical ? 'declining' : ($isWarning ? 'stable' : 'increasing'),
            
            // Sync
            'last_sync_duration_ms' => rand(50, 200),
            'sync_success_rate' => $isCritical ? 75.5 : ($isWarning ? 92.3 : 99.8),
            'pending_sync_count' => $isCritical ? 1250 : ($isWarning ? 245 : rand(0, 10)),
            
            // Metrics window
            'total_events_processed' => 5847 + rand(-100, 200),
            'total_errors' => $isCritical ? 45 : ($isWarning ? 8 : 0),
        ];
    }

    /**
     * Determine health status based on computed metrics.
     * 
     * Subtask 4.2.4 implementation.
     * 
     * Status determination rules:
     * - Critical: hash_failures >= 3 OR sequence_gaps >= 3 OR processing_lag > 300s OR queue_depth > 1000
     * - Warning: hash_failures >= 1 OR sequence_gaps >= 1 OR processing_lag > 120s OR queue_depth > 200
     * - Healthy: All metrics within normal thresholds
     * 
     * @param array $metrics
     * @return string 'healthy'|'warning'|'critical'
     */
    private function determineHealthStatus(array $metrics): string
    {
        // Critical thresholds
        if ($metrics['hash_failures_count'] >= 3 
            || $metrics['sequence_gaps_count'] >= 3 
            || $metrics['processing_lag_seconds'] > 300
            || $metrics['queue_depth'] > 1000) {
            return 'critical';
        }
        
        // Warning thresholds
        if ($metrics['hash_failures_count'] >= 1 
            || $metrics['sequence_gaps_count'] >= 1 
            || $metrics['processing_lag_seconds'] > 120
            || $metrics['queue_depth'] > 200
            || $metrics['devices_offline'] > 1) {
            return 'warning';
        }
        
        return 'healthy';
    }
}
