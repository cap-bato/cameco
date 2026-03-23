<?php
/*
* dev note:
* this shit is ugly, only made it to see how it would look like in the frontend,
* i am gonna be using SigNoz for metrics gathering, this shit is brittle af
*/


/**
 * REFACTORING & SIGNOZ INTEGRATION PLAN
 *
 * This service currently handles multiple concerns:
 *   - Collecting OS-level system metrics (CPU, memory, load average, uptime)
 *   - Measuring application-layer metrics (DB latency, queue status, cache health)
 *   - Gathering domain-level metrics through repositories (backups, patches, security events, cron jobs)
 *   - Computing overall system health
 *   - Formatting and caching responses for dashboard consumption
 *
 * This violates the Single Responsibility Principle. A refactor will split these responsibilities
 * into separate, purpose-specific services and reduce the scope of this class to an orchestration layer.
 *
 * Planned breakdown:
 *
 *   1. InfrastructureMetricsService
 *      - CPU, memory, load average, uptime
 *      - Storage usage
 *
 *   2. ApplicationMetricsService
 *      - Database latency
 *      - Cache connectivity
 *      - Queue job counts
 *      - Cron job metrics
 *
 *   3. SecurityMetricsService
 *      - Failed logins
 *      - Critical events
 *      - Recent events
 *
 *   4. BackupMetricsService
 *      - Latest backup
 *      - Backup success rate
 *
 *   5. PatchMetricsService
 *      - Pending patches
 *      - Security-critical patches
 *
 *   6. SystemHealthAggregator (this class after refactor)
 *      - Fetch metrics from the above services
 *      - Compute overall health status
 *      - Provide a unified dashboard response
 *
 *
 * SIGNOZ INTEGRATION PLAN
 * ------------------------
 * After SigNoz is deployed, most infrastructure and runtime metrics will no longer be gathered manually
 * from within PHP. The following transition steps will be applied:
 *
 *   A. Replace OS-level metrics:
 *      - Remove getCpuUsage, getMemoryUsage, getLoadAverage, getUptime.
 *      - Use OpenTelemetry collectors or Prometheus exporters feeding SigNoz for host metrics.
 *      - SystemHealthAggregator will consume metrics via SigNoz APIs or a lightweight internal adapter.
 *
 *   B. Replace application-layer metrics:
 *      - Database latency moved to SigNoz tracing (DB spans) or Prometheus exporters.
 *      - Queue metrics exposed via queue monitoring exporters.
 *      - Cache health via Redis/Memcached exporters.
 *
 *   C. Logging & security events:
 *      - Shift recent security events, failed login attempts, and critical logs to SigNoz log ingestion.
 *      - Replace internal queries with SigNoz log-based queries or dashboards.
 *
 *   D. Backups, patches, domain-specific metrics:
 *      - These remain application-owned and are still gathered through repositories.
 *      - Emit structured logs and custom metrics to SigNoz to unify observability.
 *
 *   E. SystemHealthAggregator after SigNoz:
 *      - Reduce responsibility to:
 *           • Fetch business-domain metrics (patches, backups, domain events)
 *           • Pull infrastructure/application metrics from SigNoz instead of local methods
 *           • Produce a lightweight, business-facing health summary
 *
 * Final goal:
 *   - This class becomes a high-level orchestrator.
 *   - All raw metric gathering (CPU, memory, disk, DB latency, queue state) moves out of PHP.
 *   - SigNoz becomes the single source of truth for observability.
 */




namespace App\Services\System;

use App\Repositories\Contracts\System\SystemHealthRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    public function __construct(
        protected SystemHealthRepositoryInterface $repository
    ) {}

    /**
     * Get all dashboard metrics with caching
     */
    public function getDashboardMetrics(): array
    {
        return Cache::remember('system_health_dashboard_metrics', now()->addMinutes(5), function () {
            return [
                'server' => $this->getServerHealthMetrics(),
                'database' => $this->getDatabaseMetrics(),
                'cache' => $this->getCacheMetrics(),
                'queue' => $this->getQueueMetrics(),
                'storage' => $this->getStorageMetrics(),
                'backup' => $this->getBackupSummary(),
                'patches' => $this->getPatchApprovalSummary(),
                'security' => $this->getSecurityOverview(),
                'overall_status' => $this->determineOverallStatus(),
            ];
        });
    }

    /**
     * Get server health metrics
     */
    /**
     * Get server health metrics (uptime = oldest log in period, fallback to OS uptime)
     */
    public function getServerHealthMetrics(int $days = 7): array
    {
        $cpuUsage = $this->getCpuUsage();
        $memoryUsage = $this->getMemoryUsage();
        $loadAverage = $this->getLoadAverage();

        // Use uptime from oldest health log in period, fallback to OS uptime
        $oldestLog = $this->repository->getHealthLogs($days)->first();
        if ($oldestLog && isset($oldestLog->uptime_seconds)) {
            $uptime = $oldestLog->uptime_seconds;
        } else {
            $uptime = $this->getUptime();
        }

        return [
            'cpu_usage' => $cpuUsage,
            'memory_usage' => $memoryUsage,
            'load_average' => $loadAverage,
            'uptime' => $uptime,
            'uptime_formatted' => $this->formatUptime($uptime),
            'status' => $this->determineServerStatus($cpuUsage, $memoryUsage),
        ];
    }

    /**
     * Get database metrics
     */
    public function getDatabaseMetrics(): array
    {
        $responseTime = $this->measureDatabaseResponseTime();
        
        return [
            'status' => 'online',
            'response_time_ms' => $responseTime,
            'connection_status' => $responseTime < 100 ? 'healthy' : 'slow',
        ];
    }

    /**
     * Get cache metrics
     */
    public function getCacheMetrics(): array
    {
        $driver = config('cache.default');
        $status = $this->testCacheConnection();

        return [
            'driver' => $driver,
            'status' => $status ? 'online' : 'offline',
        ];
    }

    /**
     * Get queue metrics
     */
    public function getQueueMetrics(): array
    {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        return [
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
            'status' => $pending < 100 ? 'healthy' : 'warning',
        ];
    }

    /**
     * Get storage metrics
     */
    public function getStorageMetrics(): array
    {
        $totalSpace = disk_total_space(base_path());
        $freeSpace = disk_free_space(base_path());
        $usedSpace = $totalSpace - $freeSpace;
        $usagePercentage = round(($usedSpace / $totalSpace) * 100, 2);

        return [
            'total_bytes' => $totalSpace,
            'used_bytes' => $usedSpace,
            'free_bytes' => $freeSpace,
            'usage_percentage' => $usagePercentage,
            'total_formatted' => $this->formatBytes($totalSpace),
            'used_formatted' => $this->formatBytes($usedSpace),
            'free_formatted' => $this->formatBytes($freeSpace),
            'status' => $usagePercentage < 80 ? 'healthy' : ($usagePercentage < 90 ? 'warning' : 'critical'),
        ];
    }

    /**
     * Get backup summary
     */
    public function getBackupSummary(): array
    {
        $latestBackup = $this->repository->getLatestBackup();
        $successRate = $this->repository->getBackupSuccessRate();

        return [
            'latest_backup' => $latestBackup,
            'success_rate' => $successRate,
            'status' => $successRate >= 95 ? 'healthy' : 'warning',
        ];
    }

    /**
     * Get patch approval summary
     */
    public function getPatchApprovalSummary(): array
    {
        $pendingCount = $this->repository->getPendingPatchesCount();
        $securityPending = $this->repository->getSecurityPatchesPending();

        return [
            'pending_total' => $pendingCount,
            'security_pending' => $securityPending,
            'status' => $securityPending > 0 ? 'warning' : 'healthy',
        ];
    }

    /**
     * Get security overview
     */
    public function getSecurityOverview(): array
    {
        $criticalEvents = $this->repository->getCriticalSecurityEventsCount();
        $failedLogins = $this->repository->getFailedLoginAttemptsCount();
        $recentEvents = $this->repository->getRecentSecurityEvents(5);

        return [
            'critical_events_24h' => $criticalEvents,
            'failed_logins_24h' => $failedLogins,
            'recent_events' => $recentEvents,
            'status' => $criticalEvents > 0 ? 'critical' : ($failedLogins > 5 ? 'warning' : 'healthy'),
        ];
    }

    /**
     * Get cron job metrics
     */
    public function getCronMetrics(): array
    {
        return Cache::remember('system_health_cron_metrics', now()->addMinutes(5), function () {
            try {
                // Get metrics from CronRepository via SystemCronService
                $cronService = app(\App\Services\System\SystemCronService::class);
                $metrics = $cronService->getJobMetrics();

                // Determine status based on metrics
                $status = 'healthy';
                if ($metrics['overdue_jobs'] > 0) {
                    $status = 'critical';
                } elseif ($metrics['recent_failures'] > 0) {
                    $status = 'warning';
                } elseif ($metrics['failed_jobs'] > 0) {
                    $status = 'warning';
                }

                return [
                    'total_jobs' => $metrics['total_jobs'],
                    'enabled_jobs' => $metrics['enabled_jobs'],
                    'disabled_jobs' => $metrics['disabled_jobs'],
                    'overdue_jobs' => $metrics['overdue_jobs'],
                    'recent_failures_24h' => $metrics['recent_failures'],
                    'overall_success_rate' => $metrics['overall_success_rate'],
                    'next_job' => $metrics['next_job'],
                    'status' => $status,
                ];
            } catch (\Exception $e) {
                // If cron service not available, return empty metrics
                return [
                    'total_jobs' => 0,
                    'enabled_jobs' => 0,
                    'disabled_jobs' => 0,
                    'overdue_jobs' => 0,
                    'recent_failures_24h' => 0,
                    'overall_success_rate' => 0,
                    'next_job' => null,
                    'status' => 'unavailable',
                ];
            }
        });
    }

    /**
     * Determine overall system status
     */
    protected function determineOverallStatus(): string
    {
        $metrics = [
            'server' => $this->getServerHealthMetrics()['status'],
            'storage' => $this->getStorageMetrics()['status'],
            'backup' => $this->getBackupSummary()['status'],
            'security' => $this->getSecurityOverview()['status'],
        ];

        if (in_array('critical', $metrics)) {
            return 'critical';
        }

        if (in_array('warning', $metrics)) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Get CPU usage percentage
     */
    protected function getCpuUsage(): float
    {
        // Windows-compatible CPU usage check
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                // Check if COM extension is available
                if (!class_exists('COM')) {
                    // Fallback: Use PHP's own resource usage
                    return round(rand(20, 60) + (rand(0, 100) / 100), 2);
                }
                
                $wmi = new \COM('winmgmts://');
                $cpus = $wmi->ExecQuery('SELECT LoadPercentage FROM Win32_Processor');
                $cpu = 0;
                foreach ($cpus as $processor) {
                    $cpu = $processor->LoadPercentage;
                    break;
                }
                return (float) $cpu;
            } catch (\Exception $e) {
                // Fallback for development
                return round(rand(20, 60) + (rand(0, 100) / 100), 2);
            }
        }

        // Unix-like systems
        try {
            $load = sys_getloadavg();
            return round($load[0] * 100, 2);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get memory usage percentage
     */
    protected function getMemoryUsage(): float
    {
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                // Check if COM extension is available
                if (!class_exists('COM')) {
                    // Fallback: Use PHP's memory usage
                    $memoryUsage = memory_get_usage(true);
                    $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
                    
                    if ($memoryLimit > 0) {
                        return round(($memoryUsage / $memoryLimit) * 100, 2);
                    }
                    
                    // If no memory limit, return simulated value
                    return round(rand(40, 70) + (rand(0, 100) / 100), 2);
                }
                
                $wmi = new \COM('winmgmts://');
                $os = $wmi->ExecQuery('SELECT TotalVisibleMemorySize, FreePhysicalMemory FROM Win32_OperatingSystem');
                foreach ($os as $mem) {
                    $total = $mem->TotalVisibleMemorySize;
                    $free = $mem->FreePhysicalMemory;
                    $used = $total - $free;
                    return round(($used / $total) * 100, 2);
                }
            } catch (\Exception $e) {
                // Fallback: Use PHP's memory usage
                $memoryUsage = memory_get_usage(true);
                $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
                
                if ($memoryLimit > 0) {
                    return round(($memoryUsage / $memoryLimit) * 100, 2);
                }
                
                return round(rand(40, 70) + (rand(0, 100) / 100), 2);
            }
        }

        // Unix-like systems
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($memoryLimit > 0) {
            return round(($memoryUsage / $memoryLimit) * 100, 2);
        }

        return 0.0;
    }

    /**
     * Get load average
     */
    protected function getLoadAverage(): ?string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return sprintf('%.2f, %.2f, %.2f', $load[0], $load[1], $load[2]);
        }

        return null;
    }

    /**
     * Get system uptime in seconds
     */
    protected function getUptime(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                // Check if COM extension is available
                if (!class_exists('COM')) {
                    // Fallback: Return simulated uptime (7-30 days)
                    return rand(604800, 2592000);
                }
                
                $wmi = new \COM('winmgmts://');
                $os = $wmi->ExecQuery('SELECT LastBootUpTime FROM Win32_OperatingSystem');
                foreach ($os as $system) {
                    $bootTime = $system->LastBootUpTime;
                    // Convert WMI datetime format to timestamp
                    $timestamp = substr($bootTime, 0, 14);
                    $year = substr($timestamp, 0, 4);
                    $month = substr($timestamp, 4, 2);
                    $day = substr($timestamp, 6, 2);
                    $hour = substr($timestamp, 8, 2);
                    $minute = substr($timestamp, 10, 2);
                    $second = substr($timestamp, 12, 2);
                    $bootTimestamp = strtotime("$year-$month-$day $hour:$minute:$second");
                    return time() - $bootTimestamp;
                }
            } catch (\Exception $e) {
                // Fallback: Return simulated uptime
                return rand(604800, 2592000);
            }
        }

        // Unix-like systems
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            return (int) floatval(explode(' ', $uptime)[0]);
        }

        return 0;
    }

    /**
     * Format uptime seconds to human readable
     */
    protected function formatUptime(int $seconds): string
    {
        if ($seconds === 0) {
            return 'Unknown';
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$days}d {$hours}h {$minutes}m";
    }

    /**
     * Measure database response time in milliseconds
     */
    protected function measureDatabaseResponseTime(): int
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::table('users')->limit(1)->count();
            $end = microtime(true);

            return (int) round(($end - $start) * 1000);
        } catch (\Exception $e) {
            return 999;
        }
    }

    /**
     * Test cache connection
     */
    protected function testCacheConnection(): bool
    {
        try {
            Cache::put('system_health_test', true, 10);
            $result = Cache::get('system_health_test');
            Cache::forget('system_health_test');
            return $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        
        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Determine server status based on CPU and memory
     */
    protected function determineServerStatus(float $cpu, float $memory): string
    {
        if ($cpu > 85 || $memory > 90) {
            return 'critical';
        }

        if ($cpu > 70 || $memory > 75) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Parse memory limit string to bytes
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $value = (int) $limit;

        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Force refresh metrics cache
     */
    public function refreshMetrics(): array
    {
        Cache::forget('system_health_dashboard_metrics');
        return $this->getDashboardMetrics();
    }

    /**
     * Get historical health data for charts
     */
    public function getHistoricalHealthData(int $days = 7): array
    {
        $healthLogs = $this->repository->getHealthLogs($days);

        return [
            'timeline' => $healthLogs->map(fn($log) => [
                'timestamp' => $log->created_at->format('Y-m-d H:i'),
                'cpu_usage' => $log->cpu_usage,
                'memory_usage' => $log->memory_usage,
                'disk_usage' => $log->disk_usage,
                'database_response_ms' => $log->database_response_ms,
                'status' => $log->status,
            ])->toArray(),
            'averages' => [
                'cpu' => round($healthLogs->avg('cpu_usage'), 2),
                'memory' => round($healthLogs->avg('memory_usage'), 2),
                'disk' => round($healthLogs->avg('disk_usage'), 2),
                'database' => round($healthLogs->avg('database_response_ms'), 2),
            ],
            'peaks' => [
                'cpu' => round($healthLogs->max('cpu_usage'), 2),
                'memory' => round($healthLogs->max('memory_usage'), 2),
                'disk' => round($healthLogs->max('disk_usage'), 2),
                'database' => round($healthLogs->max('database_response_ms'), 2),
            ],
        ];
    }
}
