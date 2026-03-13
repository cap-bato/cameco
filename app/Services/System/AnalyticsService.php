<?php

/**
 * NOTE – USER ANALYTICS / OBSERVABILITY REPLACEMENT PLAN
 *
 * This service manually computes analytics from SecurityAuditLog records to support
 * admin dashboards in on-prem environments without an observability or analytics stack.
 * The following functionality is intentionally temporary:
 *
 *   • User login statistics
 *   • Module usage classification (regex-based and fragile)
 *   • Activity heatmap (day/hour rollups)
 *   • Crude session duration pairing via login/logout logs
 *   • Basic event frequency summaries
 *
 * Once a full analytics stack is deployed (recommended: SigNoz / Prometheus+Loki+Grafana):
 *
 * REPLACE WITH:
 *   – Aggregated metrics via OTEL instrumentation (success/failure events, module usage)
 *   – Distributed tracing for session behaviour
 *   – Structured logs stored and queried in observability backend (Loki/OpenSearch/etc.)
 *   – Dashboards and visualizations in the monitoring platform instead of Laravel
 *
 * REMOVE / REFACTOR:
 *   1) Module detection using LIKE/CASE patterns, replace with structured metadata on logs
 *   2) Session duration pairing logic (unreliable, fails on missing logout events)
 *   3) Heavy in-memory grouping + sorting after ->get() calls, move to database aggregation
 *   4) Any derived analytics that are better calculated by the analytics backend
 *
 * KEEP:
 *   – Only business-specific metrics required by HRIS (e.g., attendance-driven activity)
 *
 * This file is a transitional analytics layer and should be rewritten once proper
 * telemetry and dashboards are available.
 */

namespace App\Services\System;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(
        protected DatabaseCompatibilityService $dbCompat
    ) {}


    /**
     * Get user login statistics
     */
    public function getUserLoginStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to = $to ?? now();

        $loginStats = SecurityAuditLog::where('event_type', 'user_login')
            ->whereBetween('created_at', [$from, $to])
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(function ($logs) {
                $firstLog = $logs->first();
                $user = $firstLog?->user;

                return [
                    'user_id' => $user?->id ?? $firstLog?->user_id,
                    'user_name' => $user?->name ?? 'Unknown User',
                    'email' => $user?->email,
                    'login_count' => $logs->count(),
                    'last_login' => $logs->max('created_at'),
                    'first_login' => $logs->min('created_at'),
                ];
            })
            ->sortByDesc('login_count')
            ->values()
            ->toArray();

        return $loginStats;
    }

    /**
     * Get most used modules based on audit logs
     */
    public function getMostUsedModules(?Carbon $from = null, ?Carbon $to = null, int $limit = 10): array
    {
        $from = $from ?? now()->subMonths(1);
        $to = $to ?? now();

        // Extract module from event_type or details
        $moduleUsage = SecurityAuditLog::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('COUNT(*) as access_count'))
            ->selectRaw("
                CASE 
                    WHEN event_type LIKE '%user%' THEN 'User Management'
                    WHEN event_type LIKE '%role%' THEN 'Roles & Permissions'
                    WHEN event_type LIKE '%security%' OR event_type LIKE '%policy%' THEN 'Security Policies'
                    WHEN event_type LIKE '%ip%' THEN 'IP Rules'
                    WHEN event_type LIKE '%department%' THEN 'Departments'
                    WHEN event_type LIKE '%position%' THEN 'Positions'
                    WHEN event_type LIKE '%health%' OR event_type LIKE '%backup%' THEN 'System Health'
                    WHEN event_type LIKE '%patch%' THEN 'Patch Management'
                    WHEN event_type LIKE '%cron%' THEN 'Cron Jobs'
                    ELSE 'Other'
                END as module
            ")
            ->groupBy('module')
            ->orderByDesc('access_count')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'module' => $row->module,
                    'access_count' => $row->access_count,
                    'percentage' => 0, // Will calculate after
                ];
            })
            ->toArray();

        // Calculate percentages
        $total = array_sum(array_column($moduleUsage, 'access_count'));
        foreach ($moduleUsage as &$item) {
            $item['percentage'] = $total > 0 ? round(($item['access_count'] / $total) * 100, 2) : 0;
        }

        return $moduleUsage;
    }

    /**
     * Get user activity heatmap (day of week and hour)
     */
    public function getUserActivityHeatmap(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to = $to ?? now();

        // Use database-agnostic functions for day of week and hour extraction
        $activities = SecurityAuditLog::whereBetween('created_at', [$from, $to])
            ->selectRaw(DatabaseCompatibilityService::extractDayOfWeek('created_at') . ' as day_of_week')
            ->selectRaw(DatabaseCompatibilityService::extractHour('created_at') . ' as hour')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('day_of_week', 'hour')
            ->get();

        // Initialize heatmap (7 days x 24 hours)
        $heatmap = [];
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $heatmap[] = [
                    'day' => $days[$day],
                    'day_num' => $day,
                    'hour' => $hour,
                    'count' => 0,
                ];
            }
        }

        // Fill in actual data
        foreach ($activities as $activity) {
            $day = (int)$activity->day_of_week;
            $hour = (int)$activity->hour;
            $key = $day * 24 + $hour;
            if (isset($heatmap[$key])) {
                $heatmap[$key]['count'] = $activity->count;
            }
        }

        return $heatmap;
    }

    /**
     * Get session duration statistics
     */
    public function getSessionDurationStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to = $to ?? now();

        // Get login and logout pairs
        $logins = SecurityAuditLog::where('event_type', 'user_login')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $logouts = SecurityAuditLog::where('event_type', 'user_logout')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $sessionDurations = [];
        $totalDuration = 0;
        $sessionCount = 0;

        foreach ($logins as $login) {
            // Find corresponding logout
            $logout = $logouts
                ->where('user_id', $login->user_id)
                ->where('created_at', '>', $login->created_at)
                ->first();

            if ($logout) {
                $duration = $logout->created_at->diffInMinutes($login->created_at);
                $sessionDurations[] = [
                    'user_id' => $login->user_id,
                    'user_name' => $login->user?->name ?? 'Unknown',
                    'login_at' => $login->created_at,
                    'logout_at' => $logout->created_at,
                    'duration_minutes' => $duration,
                ];
                $totalDuration += $duration;
                $sessionCount++;
            }
        }

        $avgDuration = $sessionCount > 0 ? round($totalDuration / $sessionCount, 2) : 0;
        $maxDuration = collect($sessionDurations)->max('duration_minutes') ?? 0;
        $minDuration = collect($sessionDurations)->min('duration_minutes') ?? 0;

        return [
            'total_sessions' => $sessionCount,
            'average_duration_minutes' => $avgDuration,
            'max_duration_minutes' => $maxDuration,
            'min_duration_minutes' => $minDuration,
            'total_duration_hours' => round($totalDuration / 60, 2),
            'sessions' => collect($sessionDurations)->sortByDesc('duration_minutes')->take(20)->values()->toArray(),
        ];
    }

    /**
     * Get activity by action type
     */
    public function getActivityByActionType(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to = $to ?? now();

        return SecurityAuditLog::whereBetween('created_at', [$from, $to])
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->get()
            ->map(function ($row) {
                return [
                    'action' => $row->event_type,
                    'count' => $row->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get user activity summary
     */
    public function getUserActivitySummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to = $to ?? now();

        $totalUsers = User::count();
        $activeAccounts = User::where('is_active', true)->count();
        $totalEvents = SecurityAuditLog::whereBetween('created_at', [$from, $to])->count();
        $uniqueUsers = SecurityAuditLog::whereBetween('created_at', [$from, $to])
            ->distinct('user_id')
            ->count('user_id');
        $loginAttempts = SecurityAuditLog::where('event_type', 'user_login')
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $failedLogins = SecurityAuditLog::where('event_type', 'failed_login_attempt')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'total_users' => $totalUsers,
            'active_accounts' => $activeAccounts,
            'total_events' => $totalEvents,
            'unique_users' => $uniqueUsers,
            'successful_logins' => $loginAttempts,
            'failed_login_attempts' => $failedLogins,
            'success_rate' => $loginAttempts + $failedLogins > 0 
                ? round(($loginAttempts / ($loginAttempts + $failedLogins)) * 100, 2) 
                : 0,
            'period_start' => $from->format('Y-m-d'),
            'period_end' => $to->format('Y-m-d'),
        ];
    }
}
