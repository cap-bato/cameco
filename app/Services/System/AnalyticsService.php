<?php

/**
 * NOTE – USER ANALYTICS / OBSERVABILITY REPLACEMENT PLAN
 * (see original file header for full replacement plan)
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
        $to   = $to   ?? now();

        return SecurityAuditLog::where('event_type', 'user_login')
            ->whereBetween('created_at', [$from, $to])
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(function ($logs) {
                $firstLog = $logs->first();
                $user     = $firstLog?->user;

                return [
                    'user_id'     => $user?->id ?? $firstLog?->user_id,
                    'user_name'   => $user?->name ?? 'Unknown User',
                    'email'       => $user?->email,
                    'login_count' => $logs->count(),
                    'last_login'  => $logs->max('created_at'),
                    'first_login' => $logs->min('created_at'),
                ];
            })
            ->sortByDesc('login_count')
            ->values()
            ->toArray();
    }

    /**
     * Get most used modules based on audit logs
     */
    public function getMostUsedModules(?Carbon $from = null, ?Carbon $to = null, int $limit = 10): array
    {
        $from = $from ?? now()->subMonths(1);
        $to   = $to   ?? now();

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
            ->map(fn($row) => [
                'module'       => $row->module,
                'access_count' => (int) $row->access_count,
                'percentage'   => 0,
            ])
            ->toArray();

        $total = array_sum(array_column($moduleUsage, 'access_count'));
        foreach ($moduleUsage as &$item) {
            $item['percentage'] = $total > 0
                ? round(($item['access_count'] / $total) * 100, 2)
                : 0;
        }

        return $moduleUsage;
    }

    /**
     * Get user activity heatmap (day of week and hour)
     */
    public function getUserActivityHeatmap(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to   = $to   ?? now();

        $activities = SecurityAuditLog::whereBetween('created_at', [$from, $to])
            ->selectRaw(DatabaseCompatibilityService::extractDayOfWeek('created_at') . ' as day_of_week')
            ->selectRaw(DatabaseCompatibilityService::extractHour('created_at') . ' as hour')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('day_of_week', 'hour')
            ->get();

        $days    = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $heatmap = [];

        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $heatmap[] = [
                    'day'     => $days[$day],
                    'day_num' => $day,
                    'hour'    => $hour,
                    'count'   => 0,
                ];
            }
        }

        foreach ($activities as $activity) {
            $day = (int) $activity->day_of_week;
            $hour = (int) $activity->hour;
            $key  = $day * 24 + $hour;
            if (isset($heatmap[$key])) {
                $heatmap[$key]['count'] = $activity->count;
            }
        }

        return $heatmap;
    }

    /**
     * Get session duration statistics.
     *
     * FIX: Carbon::diffInMinutes($other) computes $this->timestamp - $other->timestamp.
     * The original code called $logout->diffInMinutes($login) which gave a NEGATIVE value
     * because it computed login_time - logout_time.
     *
     * Correct call: $login->created_at->diffInMinutes($logout->created_at)
     *               i.e.  logout_time - login_time  → always positive.
     *
     * Also use (int) cast + abs() as a double safety net, and round aggregates to 2dp.
     */
    public function getSessionDurationStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to   = $to   ?? now();

        $logins  = SecurityAuditLog::where('event_type', 'user_login')
            ->whereBetween('created_at', [$from, $to])
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $logouts = SecurityAuditLog::where('event_type', 'user_logout')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $sessionDurations = [];
        $totalDuration    = 0;

        foreach ($logins as $login) {
            // Find the FIRST logout for this user that happened AFTER this login
            $logout = $logouts
                ->where('user_id', $login->user_id)
                ->filter(fn($l) => $l->created_at->gt($login->created_at))
                ->sortBy('created_at')
                ->first();

            if (!$logout) {
                continue;
            }

            // ✅ FIXED: $login->diffInMinutes($logout) = logout - login = positive value
            $duration = (int) abs($login->created_at->diffInMinutes($logout->created_at));

            $sessionDurations[] = [
                'user_id'          => $login->user_id,
                'user_name'        => $login->user?->name ?? 'Unknown',
                'login_at'         => $login->created_at,
                'logout_at'        => $logout->created_at,
                'duration_minutes' => $duration,
            ];

            $totalDuration += $duration;
        }

        $sessionCount = count($sessionDurations);
        $avgDuration  = $sessionCount > 0 ? round($totalDuration / $sessionCount, 1) : 0;
        $maxDuration  = $sessionCount > 0 ? (int) collect($sessionDurations)->max('duration_minutes') : 0;
        $minDuration  = $sessionCount > 0 ? (int) collect($sessionDurations)->min('duration_minutes') : 0;

        return [
            'total_sessions'           => $sessionCount,
            'average_duration_minutes' => $avgDuration,
            'max_duration_minutes'     => $maxDuration,
            'min_duration_minutes'     => $minDuration,
            'total_duration_hours'     => round($totalDuration / 60, 1),
            'sessions'                 => collect($sessionDurations)
                ->sortByDesc('duration_minutes')
                ->take(20)
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Get activity by action type
     */
    public function getActivityByActionType(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to   = $to   ?? now();

        return SecurityAuditLog::whereBetween('created_at', [$from, $to])
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'action' => $row->event_type,
                'count'  => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Get user activity summary
     */
    public function getUserActivitySummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subMonths(1);
        $to   = $to   ?? now();

        $totalUsers     = User::count();
        $activeAccounts = User::where('is_active', true)->count();
        $totalEvents    = SecurityAuditLog::whereBetween('created_at', [$from, $to])->count();
        $uniqueUsers    = SecurityAuditLog::whereBetween('created_at', [$from, $to])
            ->distinct('user_id')
            ->count('user_id');
        $successfulLogins = SecurityAuditLog::where('event_type', 'user_login')
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $failedLogins = SecurityAuditLog::where('event_type', 'failed_login_attempt')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $total = $successfulLogins + $failedLogins;

        return [
            'total_users'            => $totalUsers,
            'active_accounts'        => $activeAccounts,
            'total_events'           => $totalEvents,
            'unique_users'           => $uniqueUsers,
            'successful_logins'      => $successfulLogins,
            'failed_login_attempts'  => $failedLogins,
            'success_rate'           => $total > 0
                ? round(($successfulLogins / $total) * 100, 2)
                : 0,
            'period_start'           => $from->format('Y-m-d'),
            'period_end'             => $to->format('Y-m-d'),
        ];
    }
}