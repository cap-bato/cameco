<?php

namespace App\Http\Controllers\Payroll\Reports;

use App\Http\Controllers\Controller;
use App\Models\PayrollApprovalHistory;
use App\Models\PayrollCalculationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PayrollAuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->parseFilters($request);

        return Inertia::render('Payroll/Reports/Audit', [
            'auditLogs'     => $this->getAuditLogs($filters),
            'changeHistory' => $this->getChangeHistory($filters),
            'filters'       => [
                'action'      => $filters['action'],
                'entity_type' => $filters['entity_type'],
                'user_id'     => $filters['user_id'],
                'date_range'  => $filters['date_range'],
                'search'      => $filters['search'],
            ],
        ]);
    }

    /**
     * @param array{action: string[], entity_type: string[], user_id: int[], date_range: array{from: string, to: string}|null, search: string} $filters
     */
    private function getAuditLogs(array $filters): array
    {
        $actions     = $filters['action'];
        $entityTypes = $filters['entity_type'];
        $userIds     = $filters['user_id'];
        $dateRange   = $filters['date_range'];
        $search      = $filters['search'];

        $approvalActions = !empty($actions) ? $this->getApprovalActionsFromFilter($actions) : [];
        $calcLogTypes    = !empty($actions) ? $this->getCalcLogTypesFromFilter($actions) : [];

        $fetchApprovals = empty($entityTypes) || in_array('PayrollPeriod', $entityTypes);
        $fetchCalcLogs  = empty($entityTypes) || in_array('PayrollCalculation', $entityTypes);

        if (!empty($actions)) {
            if (empty($approvalActions)) {
                $fetchApprovals = false;
            }
            if (empty($calcLogTypes)) {
                $fetchCalcLogs = false;
            }
        }

        // --- Fetch filtered records ---
        if ($fetchApprovals) {
            $q = PayrollApprovalHistory::with('payrollPeriod')->orderByDesc('created_at')->limit(30);
            if (!empty($approvalActions)) {
                $q->whereIn('action', $approvalActions);
            }
            if (!empty($userIds)) {
                $q->whereIn('user_id', $userIds);
            }
            if ($dateRange !== null) {
                $q->where('created_at', '>=', Carbon::parse($dateRange['from'])->startOfDay())
                  ->where('created_at', '<=', Carbon::parse($dateRange['to'])->endOfDay());
            }
            if ($search !== '') {
                $s = $search;
                $q->where(function ($q2) use ($s) {
                    $q2->where('user_name', 'like', "%{$s}%")
                       ->orWhereHas('payrollPeriod', fn ($pq) => $pq->where('period_name', 'like', "%{$s}%"));
                });
            }
            $approvals = $q->get();
        } else {
            $approvals = collect();
        }

        if ($fetchCalcLogs) {
            $q = PayrollCalculationLog::with('payrollPeriod')->orderByDesc('created_at')->limit(30);
            if (!empty($calcLogTypes)) {
                $q->whereIn('log_type', $calcLogTypes);
            }
            if (!empty($userIds)) {
                $q->where('actor_type', 'user')->whereIn('actor_id', $userIds);
            }
            if ($dateRange !== null) {
                $q->where('created_at', '>=', Carbon::parse($dateRange['from'])->startOfDay())
                  ->where('created_at', '<=', Carbon::parse($dateRange['to'])->endOfDay());
            }
            if ($search !== '') {
                $s = $search;
                $q->where(function ($q2) use ($s) {
                    $q2->where('message', 'like', "%{$s}%")
                       ->orWhere('actor_name', 'like', "%{$s}%")
                       ->orWhereHas('payrollPeriod', fn ($pq) => $pq->where('period_name', 'like', "%{$s}%"));
                });
            }
            $calcLogs = $q->get();
        } else {
            $calcLogs = collect();
        }

        // --- Bulk-load user emails ---
        $approvalUserIds = $approvals->pluck('user_id')->filter()->unique();
        $calcUserIds     = $calcLogs->where('actor_type', 'user')->pluck('actor_id')->filter()->unique();
        $allUserIds      = $approvalUserIds->merge($calcUserIds)->unique();
        $userEmails      = $allUserIds->isNotEmpty()
            ? User::whereIn('id', $allUserIds)->pluck('email', 'id')
            : collect();

        // --- Build raw entries ---
        $raw = [];

        foreach ($approvals as $approval) {
            $action = $this->mapApprovalAction($approval->action);
            $ts     = $approval->created_at;
            $raw[]  = [
                '_ts'             => (string) $ts->toIso8601String(),
                'action'          => $action,
                'action_label'    => $this->getActionLabel($action),
                'action_color'    => $this->getActionColor($action),
                'entity_type'     => 'PayrollPeriod',
                'entity_id'       => $approval->payroll_period_id,
                'entity_name'     => $approval->payrollPeriod?->period_name ?? "Period #{$approval->payroll_period_id}",
                'user_id'         => $approval->user_id,
                'user_name'       => $approval->user_name,
                'user_email'      => $userEmails->get($approval->user_id, ''),
                'timestamp'       => $ts->toIso8601String(),
                'formatted_date'  => $ts->format('F j, Y'),
                'formatted_time'  => $ts->format('g:i A'),
                'relative_time'   => $this->getRelativeTime($ts),
                'changes_summary' => "Status: {$approval->status_from} → {$approval->status_to}",
                'old_values'      => ['status' => $approval->status_from],
                'new_values'      => ['status' => $approval->status_to],
                'ip_address'      => null,
                'has_changes'     => true,
            ];
        }

        foreach ($calcLogs as $log) {
            $action     = $this->mapLogTypeToAction($log->log_type);
            $actorId    = ($log->actor_type === 'user' && $log->actor_id) ? (int) $log->actor_id : 0;
            $ts         = $log->created_at;
            $metadata   = $log->metadata ?? [];
            $hasChanges = !empty($metadata['old_values']) || !empty($metadata['new_values']);

            $raw[] = [
                '_ts'             => (string) $ts->toIso8601String(),
                'action'          => $action,
                'action_label'    => $this->getActionLabel($action),
                'action_color'    => $this->getActionColor($action),
                'entity_type'     => 'PayrollCalculation',
                'entity_id'       => $log->payroll_period_id,
                'entity_name'     => $log->payrollPeriod?->period_name ?? "Period #{$log->payroll_period_id}",
                'user_id'         => $actorId,
                'user_name'       => $log->actor_name ?? 'System',
                'user_email'      => $actorId > 0 ? $userEmails->get($actorId, '') : '',
                'timestamp'       => $ts->toIso8601String(),
                'formatted_date'  => $ts->format('F j, Y'),
                'formatted_time'  => $ts->format('g:i A'),
                'relative_time'   => $this->getRelativeTime($ts),
                'changes_summary' => $log->message,
                'old_values'      => $hasChanges ? ($metadata['old_values'] ?? null) : null,
                'new_values'      => $hasChanges ? ($metadata['new_values'] ?? null) : null,
                'ip_address'      => $log->ip_address,
                'has_changes'     => $hasChanges,
            ];
        }

        // --- Sort, cap, assign sequential IDs ---
        usort($raw, fn ($a, $b) => strcmp($b['_ts'], $a['_ts']));

        $logs = [];
        foreach (array_slice($raw, 0, 50) as $i => $entry) {
            unset($entry['_ts']);
            $entry['id'] = $i + 1;
            $logs[] = $entry;
        }

        return $logs;
    }

    /**
     * @param array{action: string[], entity_type: string[], user_id: int[], date_range: array{from: string, to: string}|null, search: string} $filters
     */
    private function getChangeHistory(array $filters): array
    {
        $actions     = $filters['action'];
        $entityTypes = $filters['entity_type'];
        $userIds     = $filters['user_id'];
        $dateRange   = $filters['date_range'];
        $search      = $filters['search'];

        // All change history comes from PayrollApprovalHistory (entity_type = PayrollPeriod)
        if (!empty($entityTypes) && !in_array('PayrollPeriod', $entityTypes)) {
            return [];
        }

        $approvalActions = !empty($actions) ? $this->getApprovalActionsFromFilter($actions) : [];
        if (!empty($actions) && empty($approvalActions)) {
            return [];
        }

        $q = PayrollApprovalHistory::with('payrollPeriod')->orderByDesc('created_at')->limit(100);

        if (!empty($approvalActions)) {
            $q->whereIn('action', $approvalActions);
        }
        if (!empty($userIds)) {
            $q->whereIn('user_id', $userIds);
        }
        if ($dateRange !== null) {
            $q->where('created_at', '>=', Carbon::parse($dateRange['from'])->startOfDay())
              ->where('created_at', '<=', Carbon::parse($dateRange['to'])->endOfDay());
        }
        if ($search !== '') {
            $s = $search;
            $q->where(function ($q2) use ($s) {
                $q2->where('user_name', 'like', "%{$s}%")
                   ->orWhereHas('payrollPeriod', fn ($pq) => $pq->where('period_name', 'like', "%{$s}%"));
            });
        }

        $approvals = $q->get();

        if ($approvals->isEmpty()) {
            return [];
        }

        $changes = [];
        foreach ($approvals as $idx => $approval) {
            $ts = $approval->created_at;
            $changes[] = [
                'id'                  => $idx + 1,
                'log_id'              => $approval->id,
                'entity_type'         => 'PayrollPeriod',
                'entity_id'           => $approval->payroll_period_id,
                'field_name'          => 'status',
                'field_label'         => 'Status',
                'old_value'           => $approval->status_from,
                'new_value'           => $approval->status_to,
                'formatted_old_value' => ucwords(str_replace('_', ' ', $approval->status_from)),
                'formatted_new_value' => ucwords(str_replace('_', ' ', $approval->status_to)),
                'value_type'          => 'string',
                'user_id'             => $approval->user_id,
                'user_name'           => $approval->user_name,
                'timestamp'           => $ts->toIso8601String(),
                'formatted_timestamp' => $ts->format('F j, Y g:i A'),
            ];
        }

        return $changes;
    }

    /**
     * @return array{action: string[], entity_type: string[], user_id: int[], date_range: array{from: string, to: string}|null, search: string}
     */
    private function parseFilters(Request $request): array
    {
        /** @var string|array<string>|null $rawAction */
        $rawAction = $request->query('action');
        /** @var string|array<string>|null $rawEntityType */
        $rawEntityType = $request->query('entity_type');
        /** @var string|array<string>|null $rawUserId */
        $rawUserId = $request->query('user_id');
        /** @var string|array<string, string>|null $rawDateRange */
        $rawDateRange = $request->query('date_range');
        $search = trim((string) $request->query('search', ''));

        $actions = is_array($rawAction)
            ? array_values(array_filter($rawAction, 'is_string'))
            : ($rawAction !== null && $rawAction !== '' ? [$rawAction] : []);

        $entityTypes = is_array($rawEntityType)
            ? array_values(array_filter($rawEntityType, 'is_string'))
            : ($rawEntityType !== null && $rawEntityType !== '' ? [$rawEntityType] : []);

        $userIds = is_array($rawUserId)
            ? array_map('intval', array_values(array_filter($rawUserId)))
            : ($rawUserId !== null && $rawUserId !== '' ? [(int) $rawUserId] : []);

        $dateRange = is_array($rawDateRange)
            && isset($rawDateRange['from'], $rawDateRange['to'])
            && $rawDateRange['from'] !== ''
            && $rawDateRange['to'] !== ''
            ? ['from' => (string) $rawDateRange['from'], 'to' => (string) $rawDateRange['to']]
            : null;

        return [
            'action'      => $actions,
            'entity_type' => $entityTypes,
            'user_id'     => $userIds,
            'date_range'  => $dateRange,
            'search'      => $search,
        ];
    }

    /**
     * Map display action values back to PayrollApprovalHistory.action enum values.
     *
     * @param  string[] $actions
     * @return string[]
     */
    private function getApprovalActionsFromFilter(array $actions): array
    {
        $map = [
            'created'   => ['submit'],
            'approved'  => ['approve'],
            'rejected'  => ['reject'],
            'finalized' => ['lock', 'unlock'],
        ];

        $result = [];
        foreach ($actions as $action) {
            foreach ($map[$action] ?? [] as $v) {
                $result[] = $v;
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * Map display action values back to PayrollCalculationLog.log_type enum values.
     *
     * @param  string[] $actions
     * @return string[]
     */
    private function getCalcLogTypesFromFilter(array $actions): array
    {
        $map = [
            'calculated' => ['calculation_started', 'calculation_completed', 'recalculation', 'data_fetched'],
            'adjusted'   => ['calculation_failed', 'exception_detected', 'adjustment_applied'],
            'approved'   => ['approval'],
            'rejected'   => ['rejection'],
            'finalized'  => ['lock', 'unlock'],
        ];

        $result = [];
        foreach ($actions as $action) {
            foreach ($map[$action] ?? [] as $v) {
                $result[] = $v;
            }
        }
        return array_values(array_unique($result));
    }

    private function mapApprovalAction(string $action): string
    {
        return match ($action) {
            'submit'  => 'created',
            'approve' => 'approved',
            'reject'  => 'rejected',
            'lock'    => 'finalized',
            'unlock'  => 'finalized',
            default   => 'created',
        };
    }

    private function mapLogTypeToAction(string $logType): string
    {
        return match ($logType) {
            'calculation_started', 'calculation_completed',
            'recalculation', 'data_fetched' => 'calculated',
            'calculation_failed', 'exception_detected' => 'adjusted',
            'adjustment_applied' => 'adjusted',
            'approval' => 'approved',
            'rejection' => 'rejected',
            'lock'    => 'finalized',
            'unlock'  => 'finalized',
            default   => 'calculated',
        };
    }

    private function getActionLabel(string $action): string
    {
        return match ($action) {
            'created'    => 'Created',
            'calculated' => 'Calculated',
            'adjusted'   => 'Adjusted',
            'approved'   => 'Approved',
            'rejected'   => 'Rejected',
            'finalized'  => 'Finalized',
            default      => ucfirst($action),
        };
    }

    private function getActionColor(string $action): string
    {
        return match ($action) {
            'created'    => 'green',
            'calculated' => 'blue',
            'adjusted'   => 'yellow',
            'approved'   => 'green',
            'rejected'   => 'red',
            'finalized'  => 'purple',
            default      => 'blue',
        };
    }

    private function getRelativeTime(Carbon $timestamp): string
    {
        $now  = Carbon::now();
        $diff = $now->diffInSeconds($timestamp);

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = $now->diffInMinutes($timestamp);
            return $minutes === 1 ? '1 minute ago' : "{$minutes} minutes ago";
        } elseif ($diff < 86400) {
            $hours = $now->diffInHours($timestamp);
            return $hours === 1 ? '1 hour ago' : "{$hours} hours ago";
        } else {
            $days = $now->diffInDays($timestamp);
            return $days === 1 ? 'yesterday' : "{$days} days ago";
        }
    }
}
