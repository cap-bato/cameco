<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display payroll dashboard with all widget data in single response
     */
    public function index(): Response
    {
        return Inertia::render('Payroll/Dashboard', [
            'summary' => $this->getSummaryMetrics(),
            'pendingPeriods' => $this->getPendingPeriods(),
            'recentActivities' => $this->getRecentActivities(),
            'criticalAlerts' => $this->getCriticalAlerts(),
            'complianceStatus' => $this->getComplianceStatus(),
            'quickActions' => $this->getQuickActions(),
        ]);
    }

    /**
     * Real data for summary metrics
     */
    private function getSummaryMetrics(): array
    {
        $currentPeriod = \App\Models\PayrollPeriod::orderByDesc('period_start')->first();
        $previousPeriod = \App\Models\PayrollPeriod::orderByDesc('period_start')->skip(1)->first();
        $activeEmployees = \App\Models\Employee::active()->count();
        $newHires = \App\Models\Employee::whereBetween('date_hired', [$currentPeriod?->period_start, $currentPeriod?->period_end])->count();
        $separations = \App\Models\Employee::whereNotNull('termination_date')
            ->whereBetween('termination_date', [$currentPeriod?->period_start, $currentPeriod?->period_end])->count();
        $onLeave = 0; // Implement leave logic if available
        $changeFromPreviousRaw = $activeEmployees - (\App\Models\Employee::active()->count() - $newHires + $separations);
        $changeFromPrevious = ($changeFromPreviousRaw > 0 ? '+' : ($changeFromPreviousRaw < 0 ? '-' : '')) . abs($changeFromPreviousRaw);
        $changePercentageRaw = $activeEmployees > 0 ? round(($changeFromPreviousRaw / $activeEmployees) * 100, 1) : 0;
        $changePercentage = ($changePercentageRaw > 0 ? '+' : ($changePercentageRaw < 0 ? '-' : '')) . abs($changePercentageRaw) . '%';
        $currentNet = $currentPeriod?->total_net_pay ?? 0;
        $previousNet = $previousPeriod?->total_net_pay ?? 0;
        $netDiff = $currentNet - $previousNet;
        $netPct = $previousNet > 0 ? round(($netDiff / $previousNet) * 100, 1) : 0;
        $trend = $netDiff >= 0 ? 'up' : 'down';
        $pendingPeriods = \App\Models\PayrollPeriod::whereIn('status', ['calculating', 'reviewing', 'approved'])
            ->count();
        $periodsToCalculate = \App\Models\PayrollPeriod::where('status', 'calculating')->count();
        $periodsToReview = \App\Models\PayrollPeriod::where('status', 'reviewing')->count();
        $periodsToApprove = \App\Models\PayrollPeriod::where('status', 'approved')->count();
        $adjustmentsPending = \App\Models\PayrollPeriod::where('adjustments_count', '>', 0)->count();
        $govReportsDue = \App\Models\GovernmentReport::where('status', '!=', 'paid')->count();
        return [
            'current_period' => [
                'id' => $currentPeriod?->id,
                'name' => $currentPeriod?->period_name,
                'period_type' => $currentPeriod?->period_type,
                'start_date' => $currentPeriod?->period_start ? \Carbon\Carbon::parse($currentPeriod->period_start)->toDateString() : null,
                'end_date' => $currentPeriod?->period_end ? \Carbon\Carbon::parse($currentPeriod->period_end)->toDateString() : null,
                'cutoff_date' => $currentPeriod?->timekeeping_cutoff_date ? \Carbon\Carbon::parse($currentPeriod->timekeeping_cutoff_date)->toDateString() : null,
                'pay_date' => $currentPeriod?->payment_date ? \Carbon\Carbon::parse($currentPeriod->payment_date)->toDateString() : null,
                'status' => $currentPeriod?->status,
                'status_label' => ucfirst($currentPeriod?->status ?? ''),
                'status_color' => $this->getStatusColor($currentPeriod?->status),
                'total_employees' => $currentPeriod?->total_employees,
                'progress_percentage' => $currentPeriod?->progress_percentage,
                'days_until_pay' => $currentPeriod?->getDaysUntilPayment() ?? null,
            ],
            'total_employees' => [
                'active' => $activeEmployees,
                'new_hires_this_period' => $newHires,
                'separations_this_period' => $separations,
                'on_leave' => $onLeave,
                'change_from_previous' => $changeFromPrevious,
                'change_percentage' => $changePercentage,
            ],
            'net_payroll' => [
                'current_period' => $currentNet,
                'previous_period' => $previousNet,
                'difference' => $netDiff,
                'percentage_change' => ($netPct >= 0 ? '+' : '') . $netPct . '%',
                'trend' => $trend,
                'formatted_current' => '₱' . number_format($currentNet, 2),
                'formatted_previous' => '₱' . number_format($previousNet, 2),
            ],
            'pending_actions' => [
                'total' => $pendingPeriods,
                'periods_to_calculate' => $periodsToCalculate,
                'periods_to_review' => $periodsToReview,
                'periods_to_approve' => $periodsToApprove,
                'adjustments_pending' => $adjustmentsPending,
                'government_reports_due' => $govReportsDue,
            ],
        ];
    }

    /**
     * Helper to get status color
     */
    private function getStatusColor($status): string
    {
        return match ($status) {
            'calculating' => 'blue',
            'reviewing' => 'yellow',
            'approved' => 'green',
            'finalized' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Real data for pending payroll periods
     */
    private function getPendingPeriods(): array
    {
        $periods = \App\Models\PayrollPeriod::whereIn('status', ['calculating', 'reviewing', 'approved'])
            ->orderByDesc('period_start')
            ->limit(5)
            ->get();
        return $periods->map(function ($period) {
            return [
                'id' => $period->id,
                'name' => $period->period_name,
                'period_type' => $period->period_type,
                'start_date' => $period->period_start ? \Carbon\Carbon::parse($period->period_start)->toDateString() : null,
                'end_date' => $period->period_end ? \Carbon\Carbon::parse($period->period_end)->toDateString() : null,
                'pay_date' => $period->payment_date ? \Carbon\Carbon::parse($period->payment_date)->toDateString() : null,
                'status' => $period->status,
                'status_label' => ucfirst($period->status),
                'status_color' => $this->getStatusColor($period->status),
                'total_employees' => $period->total_employees,
                'total_gross_pay' => $period->total_gross_pay,
                'total_deductions' => $period->total_deductions,
                'total_net_pay' => $period->total_net_pay,
                'formatted_net_pay' => '₱' . number_format($period->total_net_pay, 2),
                'progress_percentage' => $period->progress_percentage,
                'actions' => $this->getPeriodActions($period->status),
            ];
        })->toArray();
    }

    /**
     * Helper to get available actions for a period
     */
    private function getPeriodActions($status): array
    {
        return match ($status) {
            'calculating' => ['review', 'recalculate'],
            'reviewing' => ['approve', 'adjust'],
            'approved' => ['generate_payslips', 'generate_bank_file'],
            default => [],
        };
    }

    /**
     * Real data for recent activities (last 10 SecurityAuditLog entries for payroll)
     */
    private function getRecentActivities(): array
    {
        $logs = \App\Models\SecurityAuditLog::whereIn('event_type', [
            'payroll_calculated', 'adjustment_created', 'government_report_generated', 'payroll_approved',
            'bank_file_generated', 'payslips_generated', 'remittance_paid', 'attendance_imported',
            'component_updated', 'period_created',
        ])->orderByDesc('created_at')->limit(10)->get();
        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'type' => $log->event_type,
                'icon' => $this->getActivityIcon($log->event_type),
                'icon_color' => $this->getActivityColor($log->event_type),
                'title' => $this->getActivityTitle($log->event_type),
                'description' => $log->description,
                'user' => $log->user?->name ?? 'System',
                'timestamp' => $log->created_at?->toDateTimeString(),
                'relative_time' => $log->created_at?->diffForHumans(),
            ];
        })->toArray();
    }

    private function getActivityIcon($type): string
    {
        return match ($type) {
            'payroll_calculated' => 'calculator',
            'adjustment_created' => 'edit',
            'government_report_generated' => 'file-text',
            'payroll_approved' => 'check-circle',
            'bank_file_generated' => 'download',
            'payslips_generated' => 'file',
            'remittance_paid' => 'credit-card',
            'attendance_imported' => 'upload',
            'component_updated' => 'settings',
            'period_created' => 'plus-circle',
            default => 'info',
        };
    }

    private function getActivityColor($type): string
    {
        return match ($type) {
            'payroll_calculated' => 'blue',
            'adjustment_created' => 'yellow',
            'government_report_generated' => 'green',
            'payroll_approved' => 'green',
            'bank_file_generated' => 'purple',
            'payslips_generated' => 'blue',
            'remittance_paid' => 'green',
            'attendance_imported' => 'blue',
            'component_updated' => 'gray',
            'period_created' => 'green',
            default => 'gray',
        };
    }

    private function getActivityTitle($type): string
    {
        return match ($type) {
            'payroll_calculated' => 'Payroll Calculated',
            'adjustment_created' => 'Adjustment Created',
            'government_report_generated' => 'Government Report Generated',
            'payroll_approved' => 'Payroll Approved',
            'bank_file_generated' => 'Bank File Generated',
            'payslips_generated' => 'Payslips Generated',
            'remittance_paid' => 'Remittance Paid',
            'attendance_imported' => 'Attendance Imported',
            'component_updated' => 'Salary Component Updated',
            'period_created' => 'Payroll Period Created',
            default => 'Activity',
        };
    }

    /**
     * Real data for critical alerts (sample: overdue remittances, calculation errors, deadlines, variances, pending bank files)
     */
    private function getCriticalAlerts(): array
    {
        $alerts = [];
        // Overdue government remittances
        $overdueRemits = \App\Models\GovernmentReport::where('status', 'overdue')->get();
        foreach ($overdueRemits as $remit) {
            $alerts[] = [
                'id' => 'remit_' . $remit->id,
                'type' => 'overdue_remittance',
                'severity' => 'error',
                'icon' => 'alert-circle',
                'title' => $remit->agency . ' Remittance Overdue',
                'message' => $remit->agency . ' contributions for ' . $remit->report_period . ' are overdue.',
                'action_label' => 'Pay Now',
                'action_url' => '/payroll/government/' . strtolower($remit->agency) . '/remittances',
                'days_overdue' => now()->diffInDays($remit->due_date),
                'amount' => '₱' . number_format($remit->total_amount, 2),
                'deadline' => $remit->due_date ? \Carbon\Carbon::parse($remit->due_date)->toDateString() : null,
                'created_at' => $remit->created_at?->toDateTimeString(),
            ];
        }
        // Calculation errors (negative net pay)
        $errorCalcs = \App\Models\EmployeePayrollCalculation::where('net_pay', '<', 0)->limit(3)->get();
        foreach ($errorCalcs as $calc) {
            $alerts[] = [
                'id' => 'calc_' . $calc->id,
                'type' => 'calculation_error',
                'severity' => 'error',
                'icon' => 'x-circle',
                'title' => 'Calculation Errors Detected',
                'message' => 'Employee #' . $calc->employee_number . ' has negative net pay in ' . ($calc->payrollPeriod?->period_name ?? 'period') . '.',
                'action_label' => 'Review Errors',
                'action_url' => '/payroll/calculations/errors',
                'affected_employees' => 1,
                'period' => $calc->payrollPeriod?->period_name,
                'created_at' => $calc->created_at?->toDateTimeString(),
            ];
        }
        // Upcoming deadlines (remittances due soon) - fallback: show latest 'ready' or 'submitted' reports as 'due soon'
        $upcomingRemits = \App\Models\GovernmentReport::whereIn('status', ['ready', 'submitted'])
            ->orderByDesc('created_at')->limit(2)->get();
        foreach ($upcomingRemits as $remit) {
            $alerts[] = [
                'id' => 'upcoming_' . $remit->id,
                'type' => 'upcoming_deadline',
                'severity' => 'warning',
                'icon' => 'clock',
                'title' => $remit->agency . ' Remittance Due Soon',
                'message' => $remit->agency . ' contributions for ' . $remit->report_period . ' are due soon.',
                'action_label' => 'Prepare Payment',
                'action_url' => '/payroll/government/' . strtolower($remit->agency) . '/remittances',
                'days_until_due' => null,
                'amount' => '₱' . number_format($remit->total_amount, 2),
                'deadline' => null,
                'created_at' => $remit->created_at?->toDateTimeString(),
            ];
        }
        // Payroll variances (periods with >10% net pay change)
        $periods = \App\Models\PayrollPeriod::orderByDesc('period_start')->limit(2)->get();
        if ($periods->count() === 2) {
            $curr = $periods[0];
            $prev = $periods[1];
            if ($prev->total_net_pay > 0) {
                $pct = (($curr->total_net_pay - $prev->total_net_pay) / $prev->total_net_pay) * 100;
                if (abs($pct) > 10) {
                    $alerts[] = [
                        'id' => 'variance_' . $curr->id,
                        'type' => 'variance_alert',
                        'severity' => 'warning',
                        'icon' => 'trending-up',
                        'title' => 'Unusual Payroll Variance',
                        'message' => $curr->period_name . ' payroll is ' . round($pct, 1) . '% ' . ($pct > 0 ? 'higher' : 'lower') . ' than previous period.',
                        'action_label' => 'View Comparison',
                        'action_url' => '/payroll/periods/' . $curr->id . '/compare',
                        'variance_percentage' => ($pct > 0 ? '+' : '') . round($pct, 1) . '%',
                        'variance_amount' => '₱' . number_format($curr->total_net_pay - $prev->total_net_pay, 2),
                        'created_at' => $curr->updated_at?->toDateTimeString(),
                    ];
                }
            }
        }
        // Pending bank file generations
        $pendingBankFiles = \App\Models\PayrollPeriod::where('status', 'approved')
            ->whereDoesntHave('bankFileBatches')
            ->limit(2)->get();
        foreach ($pendingBankFiles as $period) {
            $alerts[] = [
                'id' => 'bankfile_' . $period->id,
                'type' => 'bank_file_pending',
                'severity' => 'info',
                'icon' => 'alert-triangle',
                'title' => 'Bank File Generation Pending',
                'message' => $period->period_name . ' bank file not yet generated. Generate before pay date (' . ($period->payment_date ? \Carbon\Carbon::parse($period->payment_date)->toDateString() : '') . ').',
                'action_label' => 'Generate Now',
                'action_url' => '/payroll/bank-files/generate',
                'period' => $period->period_name,
                'pay_date' => $period->payment_date ? \Carbon\Carbon::parse($period->payment_date)->toDateString() : null,
                'days_until_pay_date' => now()->diffInDays($period->payment_date, false),
                'created_at' => $period->updated_at?->toDateTimeString(),
            ];
        }
        return $alerts;
    }

    /**
     * Real data for compliance status (latest report per agency)
     */
    private function getComplianceStatus(): array
    {
        $agencies = ['SSS', 'PhilHealth', 'Pag-IBIG', 'BIR'];
        $result = [];
        foreach ($agencies as $agency) {
            $report = \App\Models\GovernmentReport::where('agency', $agency)
                ->orderByDesc('report_period')->first();
            if ($report) {
                $status = $report->status;
                $statusLabel = match ($status) {
                    'overdue' => 'Overdue',
                    'paid' => 'Paid',
                    'pending' => 'Due Soon',
                    default => ucfirst($status),
                };
                $statusColor = match ($status) {
                    'overdue' => 'red',
                    'paid' => 'green',
                    'pending' => 'yellow',
                    default => 'gray',
                };
                $days = $status === 'overdue' ? now()->diffInDays($report->due_date) : ($status === 'pending' ? now()->diffInDays($report->due_date, false) : 0);
                $result[strtolower($agency)] = [
                    'name' => $agency,
                    'full_name' => $report->report_name ?? $agency,
                    'period' => $report->report_period,
                    'due_date' => $report->due_date ? \Carbon\Carbon::parse($report->due_date)->toDateString() : null,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'status_color' => $statusColor,
                    'days_overdue' => $status === 'overdue' ? $days : 0,
                    'days_until_due' => $status === 'pending' ? $days : 0,
                    'amount' => $report->total_amount,
                    'formatted_amount' => '₱' . number_format($report->total_amount, 2),
                    'employee_share' => $report->total_employee_share,
                    'employer_share' => $report->total_employer_share,
                    'report_generated' => true,
                    'report_type' => $report->report_type,
                    'payment_reference' => $report->payment_reference ?? null,
                    'paid_date' => $report->paid_date ?? null,
                    'actions' => ['pay_now', 'view_report'],
                ];
            }
        }
        return $result;
    }

    /**
     * Real data for quick actions (static for now)
     */
    private function getQuickActions(): array
    {
        return [
            [
                'id' => 'create_period',
                'label' => 'Create Payroll Period',
                'icon' => 'plus-circle',
                'color' => 'blue',
                'url' => '/payroll/periods/create',
                'description' => 'Start a new payroll period',
            ],
            [
                'id' => 'import_attendance',
                'label' => 'Import Attendance',
                'icon' => 'upload',
                'color' => 'green',
                'url' => '/payroll/employee-payroll-info',
                'description' => 'Import timekeeping data',
            ],
            [
                'id' => 'generate_reports',
                'label' => 'Government Reports',
                'icon' => 'file-text',
                'color' => 'purple',
                'url' => '/payroll/reports/government',
                'description' => 'Generate compliance reports',
            ],
            [
                'id' => 'payroll_register',
                'label' => 'Payroll Register',
                'icon' => 'list',
                'color' => 'gray',
                'url' => '/payroll/reports/register',
                'description' => 'View detailed payroll register',
            ],
            [
                'id' => 'export_summary',
                'label' => 'Export Summary',
                'icon' => 'download',
                'color' => 'orange',
                'url' => '/payroll/reports/analytics',
                'description' => 'Export payroll summary',
            ],
        ];
    }
}
