<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    /**
     * Display paginated audit logs with filters.
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'user_id' => 'nullable|integer|exists:users,id',
            'module' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = Activity::query()
            ->with(['causer:id,name,email', 'subject'])
            ->orderBy('created_at', 'desc');

        // Apply date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
        }

        // Apply user filter (causer_id)
        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        // Apply module filter (subject_type)
        if ($request->filled('module')) {
            $module = $request->module;
            
            // Map frontend module names to model class names
            $moduleMap = [
                'Company' => 'App\\Models\\SystemSetting',
                'Department' => 'App\\Models\\Department',
                'Position' => 'App\\Models\\Position',
                'LeavePolicy' => 'App\\Models\\LeavePolicy',
                'Holiday' => 'App\\Models\\SystemSetting', // Holidays stored in SystemSettings
                'PayrollRules' => 'App\\Models\\SystemSetting',
                'SystemConfig' => 'App\\Models\\SystemSetting',
                'ApprovalWorkflow' => 'App\\Models\\SystemSetting',
                'BusinessRules' => 'App\\Models\\SystemSetting',
            ];

            if (isset($moduleMap[$module])) {
                $query->where('subject_type', $moduleMap[$module]);
                
                // Additional filtering for SystemSettings-based modules by log_name
                if ($moduleMap[$module] === 'App\\Models\\SystemSetting') {
                    $logNameMap = [
                        'Company' => 'company_configuration',
                        'Holiday' => 'business_rules_configuration',
                        'PayrollRules' => 'payroll_configuration',
                        'SystemConfig' => 'system_configuration',
                        'ApprovalWorkflow' => 'workflow_configuration',
                        'BusinessRules' => 'business_rules_configuration',
                    ];
                    
                    if (isset($logNameMap[$module])) {
                        $query->where('log_name', $logNameMap[$module]);
                    }
                }
            }
        }

        // Paginate results (50 per page)
        $logs = $query->paginate(50);

        // Transform the logs for frontend
        $logs->getCollection()->transform(function ($activity) {
            return $this->transformActivity($activity);
        });

        // Get list of users who have made changes (for filter dropdown)
        $users = Activity::query()
            ->whereNotNull('causer_id')
            ->with('causer:id,name,email')
            ->select('causer_id')
            ->distinct()
            ->get()
            ->pluck('causer')
            ->filter()
            ->unique('id')
            ->values()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });

        return Inertia::render('Admin/SystemConfig/Index', [
            'auditLogs' => $logs,
            'availableUsers' => $users,
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'user_id' => $request->user_id,
                'module' => $request->module,
            ],
        ]);
    }

    /**
     * Export audit logs to CSV.
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'user_id' => 'nullable|integer|exists:users,id',
            'module' => 'nullable|string',
        ]);

        $query = Activity::query()
            ->with(['causer:id,name,email', 'subject'])
            ->orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to));
        }

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        if ($request->filled('module')) {
            $module = $request->module;
            
            $moduleMap = [
                'Company' => 'App\\Models\\SystemSetting',
                'Department' => 'App\\Models\\Department',
                'Position' => 'App\\Models\\Position',
                'LeavePolicy' => 'App\\Models\\LeavePolicy',
                'Holiday' => 'App\\Models\\SystemSetting',
                'PayrollRules' => 'App\\Models\\SystemSetting',
                'SystemConfig' => 'App\\Models\\SystemSetting',
                'ApprovalWorkflow' => 'App\\Models\\SystemSetting',
                'BusinessRules' => 'App\\Models\\SystemSetting',
            ];

            if (isset($moduleMap[$module])) {
                $query->where('subject_type', $moduleMap[$module]);
                
                if ($moduleMap[$module] === 'App\\Models\\SystemSetting') {
                    $logNameMap = [
                        'Company' => 'company_configuration',
                        'Holiday' => 'business_rules_configuration',
                        'PayrollRules' => 'payroll_configuration',
                        'SystemConfig' => 'system_configuration',
                        'ApprovalWorkflow' => 'workflow_configuration',
                        'BusinessRules' => 'business_rules_configuration',
                    ];
                    
                    if (isset($logNameMap[$module])) {
                        $query->where('log_name', $logNameMap[$module]);
                    }
                }
            }
        }

        // Get all matching logs (no pagination for export)
        $logs = $query->get();

        // Generate CSV
        $filename = 'audit-logs-' . now()->format('Y-m-d-His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'Timestamp',
                'User',
                'User Email',
                'Action',
                'Module',
                'Subject ID',
                'Old Values',
                'New Values',
                'Changes Summary',
            ]);

            // CSV Rows
            foreach ($logs as $activity) {
                $transformed = $this->transformActivity($activity);
                
                fputcsv($file, [
                    $transformed['timestamp'],
                    $transformed['user_name'],
                    $transformed['user_email'],
                    $transformed['action'],
                    $transformed['module'],
                    $transformed['subject_id'],
                    json_encode($transformed['old_values']),
                    json_encode($transformed['new_values']),
                    $transformed['changes_summary'],
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Transform activity model to frontend format.
     * 
     * @param Activity|\stdClass $activity
     * @return array
     */
    private function transformActivity($activity): array
    {
        $properties = $activity->properties ?? collect();
        $oldValues = $properties->get('old', []);
        $newValues = $properties->get('attributes', []);

        // Generate human-readable changes summary
        $changesSummary = $this->generateChangesSummary($oldValues, $newValues);

        return [
            'id' => $activity->id,
            'timestamp' => $activity->created_at->format('Y-m-d H:i:s'),
            'relative_time' => $activity->created_at->diffForHumans(),
            'user_name' => $activity->causer?->name ?? 'System',
            'user_email' => $activity->causer?->email ?? 'N/A',
            'action' => $activity->description,
            'log_name' => $activity->log_name,
            'module' => $this->getModuleName($activity->subject_type, $activity->log_name),
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changes_summary' => $changesSummary,
        ];
    }

    /**
     * Get human-readable module name from subject type and log name.
     * 
     * @param string|null $subjectType
     * @param string|null $logName
     * @return string
     */
    private function getModuleName(?string $subjectType, ?string $logName): string
    {
        if (!$subjectType) {
            return 'System';
        }

        $className = class_basename($subjectType);

        // For SystemSetting, use log_name to determine module
        if ($className === 'SystemSetting' && $logName) {
            $logNameMap = [
                'company_configuration' => 'Company Setup',
                'business_rules_configuration' => 'Business Rules',
                'payroll_configuration' => 'Payroll Rules',
                'system_configuration' => 'System Configuration',
                'system_integration' => 'System Integration',
                'workflow_configuration' => 'Approval Workflows',
                'leave_policy_configuration' => 'Leave Policies',
                'leave_approval_configuration' => 'Leave Approval Rules',
            ];

            return $logNameMap[$logName] ?? $className;
        }

        // Direct model mapping
        $moduleMap = [
            'Department' => 'Department',
            'Position' => 'Position',
            'LeavePolicy' => 'Leave Policy',
            'User' => 'User Management',
        ];

        return $moduleMap[$className] ?? $className;
    }

    /**
     * Generate human-readable changes summary.
     * 
     * @param array $oldValues
     * @param array $newValues
     * @return string
     */
    private function generateChangesSummary(array $oldValues, array $newValues): string
    {
        if (empty($oldValues) && empty($newValues)) {
            return 'No changes recorded';
        }

        if (empty($oldValues)) {
            return 'New record created with ' . count($newValues) . ' field(s)';
        }

        $changes = [];
        $changedFields = array_keys(array_diff_assoc($newValues, $oldValues));

        foreach ($changedFields as $field) {
            $oldValue = $oldValues[$field] ?? 'null';
            $newValue = $newValues[$field] ?? 'null';

            // Format values for readability
            $oldValue = is_bool($oldValue) ? ($oldValue ? 'true' : 'false') : $oldValue;
            $newValue = is_bool($newValue) ? ($newValue ? 'true' : 'false') : $newValue;

            // Truncate long values
            if (is_string($oldValue) && strlen($oldValue) > 50) {
                $oldValue = substr($oldValue, 0, 47) . '...';
            }
            if (is_string($newValue) && strlen($newValue) > 50) {
                $newValue = substr($newValue, 0, 47) . '...';
            }

            $changes[] = "{$field}: {$oldValue} → {$newValue}";
        }

        return implode('; ', array_slice($changes, 0, 5)) . (count($changes) > 5 ? '...' : '');
    }
}
