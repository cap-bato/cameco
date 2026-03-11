<?php

namespace App\Http\Controllers\Payroll\Reports;

use App\Http\Controllers\Controller;
use App\Models\GovernmentReport;
use App\Models\GovernmentRemittance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PayrollGovernmentReportsController extends Controller
{
    public function index(Request $request)
    {
        $reportsSummary = [
            'total_reports_generated' => GovernmentReport::count(),
            'total_reports_submitted'  => GovernmentReport::where('status', 'submitted')->count(),
            'reports_pending_submission' => GovernmentReport::whereIn('status', ['draft', 'ready'])->count(),
            'total_contributions' => (float) (GovernmentRemittance::sum('total_amount') ?? 0),
            'next_deadline' => GovernmentRemittance::whereIn('status', ['pending', 'ready'])
                ->where('due_date', '>=', now())
                ->orderBy('due_date')
                ->value('due_date'),
            'overdue_reports' => GovernmentRemittance::where(function ($q) {
                $q->where('status', 'overdue')
                  ->orWhere(fn ($q2) => $q2->where('is_late', true)->whereNotIn('status', ['paid', 'submitted']));
            })->count(),
        ];

        $sssReports = GovernmentReport::byAgency('sss')
            ->with(['payrollPeriod', 'governmentRemittance'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($r) => $this->mapSSSReportCard($r))
            ->values()
            ->all();

        $philhealthReports = GovernmentReport::byAgency('philhealth')
            ->with(['payrollPeriod', 'governmentRemittance'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($r) => $this->mapPhilHealthReportCard($r))
            ->values()
            ->all();

        $pagibigReports = GovernmentReport::byAgency('pagibig')
            ->with(['payrollPeriod', 'governmentRemittance'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($r) => $this->mapPagIbigReportCard($r))
            ->values()
            ->all();

        $birReports = GovernmentReport::byAgency('bir')
            ->with(['payrollPeriod', 'governmentRemittance'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($r) => $this->mapBIRReportCard($r))
            ->values()
            ->all();

        $upcomingDeadlines = GovernmentRemittance::where('due_date', '>=', now())
            ->whereNotIn('status', ['paid'])
            ->with('payrollPeriod')
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn ($rem) => $this->mapDeadline($rem))
            ->values()
            ->all();

        $complianceStatus = $this->buildComplianceStatus();

        return Inertia::render('Payroll/Reports/Government/Index', [
            'reports_summary'     => $reportsSummary,
            'sss_reports'         => $sssReports,
            'philhealth_reports'  => $philhealthReports,
            'pagibig_reports'     => $pagibigReports,
            'bir_reports'         => $birReports,
            'upcoming_deadlines'  => $upcomingDeadlines,
            'compliance_status'   => $complianceStatus,
        ]);
    }


    private function mapSSSReportCard($r): array
    {
        $period = $r->payrollPeriod;
        $remittance = $r->governmentRemittance;
        $dueDate = $remittance?->due_date?->toDateString()
            ?? ($period ? Carbon::parse($period->end_date)->addDays(10)->toDateString() : now()->addDays(10)->toDateString());
        $isOverdue = Carbon::parse($dueDate)->isPast() && ! in_array($r->status, ['submitted', 'accepted']);

        return [
            'id'               => $r->id,
            'report_type'      => $this->mapSSSReportType($r->report_type ?? 'r3'),
            'period_id'        => $r->payroll_period_id ?? 0,
            'period_name'      => $period?->name ?? $r->report_period ?? 'N/A',
            'month'            => $period ? Carbon::parse($period->start_date)->format('F') : 'N/A',
            'year'             => $period ? (int) Carbon::parse($period->start_date)->format('Y') : now()->year,
            'total_employees'  => (int) ($r->total_employees ?? 0),
            'total_compensation' => (float) ($r->total_compensation ?? 0),
            'employee_share'   => (float) ($r->total_employee_share ?? 0),
            'employer_share'   => (float) ($r->total_employer_share ?? 0),
            'ec_share'         => (float) ($remittance?->ec_share ?? 0),
            'total_contribution' => (float) ($r->total_amount ?? 0),
            'status'           => $r->status ?? 'draft',
            'status_label'     => $this->mapStatusLabel($r->status ?? 'draft'),
            'status_color'     => $this->mapStatusColor($r->status ?? 'draft'),
            'submission_date'  => $r->submitted_at?->toDateString(),
            'due_date'         => $dueDate,
            'is_overdue'       => $isOverdue,
            'file_name'        => $r->file_name,
            'file_path'        => $r->file_path,
            'action_required'  => in_array($r->status, ['draft', 'ready']),
            'error_message'    => $r->rejection_reason,
        ];
    }

    private function mapPhilHealthReportCard($r): array
    {
        $period = $r->payrollPeriod;
        $remittance = $r->governmentRemittance;
        $dueDate = $remittance?->due_date?->toDateString()
            ?? ($period ? Carbon::parse($period->end_date)->addDays(15)->toDateString() : now()->addDays(15)->toDateString());
        $isOverdue = Carbon::parse($dueDate)->isPast() && ! in_array($r->status, ['submitted', 'accepted']);

        return [
            'id'               => $r->id,
            'report_type'      => strtoupper($r->report_type ?? 'RF1'),
            'period_id'        => $r->payroll_period_id ?? 0,
            'period_name'      => $period?->name ?? $r->report_period ?? 'N/A',
            'month'            => $period ? Carbon::parse($period->start_date)->format('F') : 'N/A',
            'year'             => $period ? (int) Carbon::parse($period->start_date)->format('Y') : now()->year,
            'total_employees'  => (int) ($r->total_employees ?? 0),
            'total_compensation' => (float) ($r->total_compensation ?? 0),
            'employee_share'   => (float) ($r->total_employee_share ?? 0),
            'employer_share'   => (float) ($r->total_employer_share ?? 0),
            'total_contribution' => (float) ($r->total_amount ?? 0),
            'status'           => $r->status ?? 'draft',
            'status_label'     => $this->mapStatusLabel($r->status ?? 'draft'),
            'status_color'     => $this->mapStatusColor($r->status ?? 'draft'),
            'submission_date'  => $r->submitted_at?->toDateString(),
            'due_date'         => $dueDate,
            'is_overdue'       => $isOverdue,
            'file_name'        => $r->file_name,
            'file_path'        => $r->file_path,
            'action_required'  => in_array($r->status, ['draft', 'ready']),
            'error_message'    => $r->rejection_reason,
        ];
    }

    private function mapPagIbigReportCard($r): array
    {
        $period = $r->payrollPeriod;
        $remittance = $r->governmentRemittance;
        $dueDate = $remittance?->due_date?->toDateString()
            ?? ($period ? Carbon::parse($period->end_date)->addDays(10)->toDateString() : now()->addDays(10)->toDateString());
        $isOverdue = Carbon::parse($dueDate)->isPast() && ! in_array($r->status, ['submitted', 'accepted']);

        return [
            'id'               => $r->id,
            'report_type'      => strtoupper($r->report_type ?? 'MCRF'),
            'period_id'        => $r->payroll_period_id ?? 0,
            'period_name'      => $period?->name ?? $r->report_period ?? 'N/A',
            'month'            => $period ? Carbon::parse($period->start_date)->format('F') : 'N/A',
            'year'             => $period ? (int) Carbon::parse($period->start_date)->format('Y') : now()->year,
            'total_employees'  => (int) ($r->total_employees ?? 0),
            'total_compensation' => (float) ($r->total_compensation ?? 0),
            'employee_share'   => (float) ($r->total_employee_share ?? 0),
            'employer_share'   => (float) ($r->total_employer_share ?? 0),
            'total_contribution' => (float) ($r->total_amount ?? 0),
            'status'           => $r->status ?? 'draft',
            'status_label'     => $this->mapStatusLabel($r->status ?? 'draft'),
            'status_color'     => $this->mapStatusColor($r->status ?? 'draft'),
            'submission_date'  => $r->submitted_at?->toDateString(),
            'due_date'         => $dueDate,
            'is_overdue'       => $isOverdue,
            'file_name'        => $r->file_name,
            'file_path'        => $r->file_path,
            'action_required'  => in_array($r->status, ['draft', 'ready']),
            'error_message'    => $r->rejection_reason,
        ];
    }

    private function mapBIRReportCard($r): array
    {
        $period = $r->payrollPeriod;
        $remittance = $r->governmentRemittance;
        $dueDate = $remittance?->due_date?->toDateString()
            ?? ($period ? Carbon::parse($period->end_date)->addDays(20)->toDateString() : now()->addDays(20)->toDateString());
        $isOverdue = Carbon::parse($dueDate)->isPast() && ! in_array($r->status, ['submitted', 'accepted']);

        return [
            'id'               => $r->id,
            'report_type'      => $this->mapBIRReportType($r->report_type ?? '1601c'),
            'period_id'        => $r->payroll_period_id ?? 0,
            'period_name'      => $period?->name ?? $r->report_period ?? 'N/A',
            'month'            => $period ? Carbon::parse($period->start_date)->format('F') : 'N/A',
            'year'             => $period ? (int) Carbon::parse($period->start_date)->format('Y') : now()->year,
            'total_employees'  => (int) ($r->total_employees ?? 0),
            'total_compensation' => (float) ($r->total_compensation ?? 0),
            'total_tax_withheld' => (float) ($r->total_tax_withheld ?? 0),
            'status'           => $r->status ?? 'draft',
            'status_label'     => $this->mapStatusLabel($r->status ?? 'draft'),
            'status_color'     => $this->mapStatusColor($r->status ?? 'draft'),
            'submission_date'  => $r->submitted_at?->toDateString(),
            'due_date'         => $dueDate,
            'is_overdue'       => $isOverdue,
            'file_name'        => $r->file_name,
            'file_path'        => $r->file_path,
            'action_required'  => in_array($r->status, ['draft', 'ready']),
            'error_message'    => $r->rejection_reason,
        ];
    }

    private function mapDeadline($rem): array
    {
        $dueDate = $rem->due_date?->toDateString() ?? now()->toDateString();
        $daysUntilDue = (int) abs(now()->diffInDays(Carbon::parse($dueDate), false));
        $isOverdue = Carbon::parse($dueDate)->isPast();

        $agencyMap = [
            'sss'        => ['label' => 'Social Security System',                    'report_type' => 'SSS R3',        'key' => 'SSS',       'url' => '/payroll/government/sss'],
            'philhealth' => ['label' => 'Philippine Health Insurance Corporation',   'report_type' => 'PhilHealth RF1', 'key' => 'PhilHealth', 'url' => '/payroll/government/philhealth'],
            'pagibig'    => ['label' => 'Pag-IBIG Fund',                             'report_type' => 'Pag-IBIG MCRF', 'key' => 'Pag-IBIG',   'url' => '/payroll/government/pagibig'],
            'bir'        => ['label' => 'Bureau of Internal Revenue',                'report_type' => 'BIR 1601C',     'key' => 'BIR',        'url' => '/payroll/government/bir'],
        ];

        $agency = strtolower($rem->agency ?? '');
        $info = $agencyMap[$agency] ?? [
            'label' => $rem->agency ?? 'Unknown',
            'report_type' => $rem->remittance_type ?? 'Report',
            'key' => strtoupper($agency),
            'url' => '/payroll/government',
        ];

        return [
            'id'                  => $rem->id,
            'report_type'         => $info['report_type'],
            'agency'              => $info['key'],
            'agency_label'        => $info['label'],
            'due_date'            => $dueDate,
            'days_until_due'      => $daysUntilDue,
            'is_overdue'          => $isOverdue,
            'related_period_id'   => $rem->payroll_period_id ?? 0,
            'related_period_name' => $rem->payrollPeriod?->name ?? ($rem->remittance_month ?? 'N/A'),
            'action_url'          => $info['url'],
        ];
    }

    private function buildComplianceStatus(): array
    {
        $agencyConfig = [
            'sss'        => 'Social Security System',
            'philhealth' => 'Philippine Health Insurance Corporation',
            'pagibig'    => 'Pag-IBIG Fund',
            'bir'        => 'Bureau of Internal Revenue',
        ];

        $agencyDetails = [];
        $totalRequired  = 0;
        $totalSubmitted = 0;
        $latestSubmission = null;
        $earliestDue = null;

        foreach ($agencyConfig as $agency => $agencyName) {
            $required  = GovernmentReport::byAgency($agency)->count();
            $submitted = GovernmentReport::byAgency($agency)->where('status', 'submitted')->count();
            $pct = $required > 0 ? (int) round(($submitted / $required) * 100) : 0;

            $lastSubmittedAt = GovernmentReport::byAgency($agency)
                ->where('status', 'submitted')
                ->max('submitted_at');

            $nextDueDate = GovernmentRemittance::where('agency', $agency)
                ->whereIn('status', ['pending', 'ready'])
                ->where('due_date', '>=', now())
                ->min('due_date');

            if ($lastSubmittedAt && (! $latestSubmission || $lastSubmittedAt > $latestSubmission)) {
                $latestSubmission = $lastSubmittedAt;
            }
            if ($nextDueDate && (! $earliestDue || $nextDueDate < $earliestDue)) {
                $earliestDue = $nextDueDate;
            }

            $complianceStatus = match (true) {
                $pct >= 90 => 'compliant',
                $pct >= 50 => 'at_risk',
                default    => 'non_compliant',
            };

            $agencyDetails[$agency] = [
                'agency'                  => $agencyName,
                'total_reports_required'  => $required,
                'total_reports_submitted' => $submitted,
                'submission_percentage'   => $pct,
                'compliance_status'       => $complianceStatus,
                'compliance_status_label' => match ($complianceStatus) {
                    'compliant'      => 'Compliant',
                    'at_risk'        => 'At Risk',
                    default          => 'Non-Compliant',
                },
                'last_submission_date' => $lastSubmittedAt ? Carbon::parse($lastSubmittedAt)->toDateString() : null,
                'next_due_date'        => $nextDueDate ? Carbon::parse($nextDueDate)->toDateString() : null,
            ];

            $totalRequired  += $required;
            $totalSubmitted += $submitted;
        }

        $overallPct = $totalRequired > 0 ? (int) round(($totalSubmitted / $totalRequired) * 100) : 0;
        $overallStatus = match (true) {
            $overallPct >= 90 => 'on_track',
            $overallPct >= 50 => 'at_risk',
            default           => 'non_compliant',
        };

        return [
            'total_required_reports'  => $totalRequired,
            'total_submitted_reports' => $totalSubmitted,
            'submission_percentage'   => $overallPct,
            'submission_status'       => $overallStatus,
            'submission_status_label' => match ($overallStatus) {
                'on_track' => 'On Track',
                'at_risk'  => 'At Risk',
                default    => 'Non-Compliant',
            },
            'last_submission_date' => $latestSubmission ? Carbon::parse($latestSubmission)->toDateString() : null,
            'next_due_date'        => $earliestDue ? Carbon::parse($earliestDue)->toDateString() : null,
            'agencies'             => $agencyDetails,
        ];
    }

    private function mapSSSReportType(string $type): string
    {
        return match (strtolower($type)) {
            'monthly' => 'Monthly',
            default   => 'R3',
        };
    }

    private function mapBIRReportType(string $type): string
    {
        return match (strtolower($type)) {
            'alphalist' => 'Alphalist',
            '2316'      => '2316',
            default     => strtoupper($type),
        };
    }

    private function mapStatusLabel(string $status): string
    {
        return match ($status) {
            'draft'     => 'Draft',
            'ready'     => 'Ready',
            'submitted' => 'Submitted',
            'accepted'  => 'Accepted',
            'rejected'  => 'Rejected',
            default     => ucfirst($status),
        };
    }

    private function mapStatusColor(string $status): string
    {
        return match ($status) {
            'draft'              => 'blue',
            'ready'              => 'yellow',
            'submitted',
            'accepted'           => 'green',
            'rejected'           => 'red',
            default              => 'blue',
        };
    }
}

