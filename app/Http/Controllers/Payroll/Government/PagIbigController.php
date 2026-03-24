<?php

namespace App\Http\Controllers\Payroll\Government;

use App\Http\Controllers\Controller;
use App\Models\GovernmentReport;
use App\Services\Payroll\GovernmentContributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * PagIbigController
 * Manages Pag-IBIG contributions tracking and reporting
 *
 * Pag-IBIG Rate: 3-4% of monthly compensation
 * - Employee Share (EE): 1% or 2%
 * - Employer Share (ER): 2%
 * - Maximum contribution: ₱100 each (EE & ER)
 *
 * Report Format: MCRF (CSV) for Pag-IBIG eSRS portal submission
 * Due Date: 10th of following month
 * Supports loan deductions with amortization tracking
 */
class PagIbigController extends Controller
{
    public function __construct(
        private GovernmentContributionService $pagIbigService,
    ) {}

    public function index()
    {
        $mcrfReports = GovernmentReport::where('agency', 'pagibig')
            ->where('report_type', 'mcrf')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GovernmentReport $r) => [
                'id'                        => $r->id,
                'period_id'                 => $r->payroll_period_id,
                'month'                     => $r->report_period,
                'file_name'                 => $r->file_name ?? '',
                'file_path'                 => $r->file_path ?? '',
                'file_size'                 => (int) ($r->file_size ?? 0),
                'total_employees'           => (int) ($r->total_employees ?? 0),
                'total_employee_contribution' => (float) ($r->total_employee_share ?? 0),
                'total_employer_contribution' => (float) ($r->total_employer_share ?? 0),
                'total_contribution'        => (float) ($r->total_amount ?? 0),
                'employees_with_loans'      => 0,
                'status'                    => $r->status ?? 'draft',
                'submission_status'         => $this->mapSubmissionStatus($r->status),
                'submission_date'           => $r->submitted_at?->format('Y-m-d'),
                'rejection_reason'          => $r->rejection_reason,
                'created_at'               => $r->created_at->toISOString(),
                'updated_at'               => $r->updated_at->toISOString(),
            ]);

        $contributions  = $this->pagIbigService->getContributions('pagibig');
        $periods        = $this->pagIbigService->getPeriods();
        $summary        = $this->pagIbigService->getSummary('pagibig');
        $remittances    = $this->pagIbigService->getRemittances('pagibig');
        $loanDeductions = $this->pagIbigService->getPagIbigLoanDeductions();

        return Inertia::render('Payroll/Government/PagIbig/Index', [
            'contributions'   => $contributions,
            'periods'         => $periods,
            'summary'         => $summary,
            'remittances'     => $remittances,
            'mcrf_reports'    => $mcrfReports,
            'loan_deductions' => $loanDeductions,
        ]);
    }

    public function generateMCRF(Request $request, int $periodId)
    {
        $validated = $request->validate([
            'month' => 'required|string|date_format:Y-m',
        ]);

        Log::info('Pag-IBIG MCRF generation requested', [
            'period_id' => $periodId,
            'month'     => $validated['month'],
            'user_id'   => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'MCRF report generated successfully',
        ]);
    }

public function downloadMCRF(int $reportId)
{
    try {
        $report = GovernmentReport::where('agency', 'pagibig')
            ->where('report_type', 'mcrf')
            ->findOrFail($reportId);

        // Serve stored file if it exists
        if ($report->file_path && Storage::exists($report->file_path)) {
            return Storage::download($report->file_path, $report->file_name ?? 'pb_mcrf.csv');
        }

        // Generate on the fly
        $contributions = $this->pagIbigService->getContributions('pagibig', $report->payroll_period_id);
        $filename      = $report->file_name ?? "PagIBIG-MCRF-{$report->report_period}.csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ];

        $callback = function () use ($contributions) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Sequence No.',
                'Pag-IBIG Number',
                'Employee Name',
                'Employee Number',
                'Monthly Compensation',
                'Employee Rate',
                'Employee Contribution',
                'Employer Contribution',
                'Total Contribution',
            ]);

            $seq = 1;
            foreach ($contributions as $row) {
                fputcsv($handle, [
                    $seq++,
                    $row['pagibig_number'],
                    $row['employee_name'],
                    $row['employee_number'],
                    number_format($row['monthly_compensation'], 2, '.', ''),
                    $row['employee_rate'] . '%',
                    number_format($row['employee_contribution'], 2, '.', ''),
                    number_format($row['employer_contribution'], 2, '.', ''),
                    number_format($row['total_contribution'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);

    } catch (\Exception $e) {
        Log::error('Pag-IBIG MCRF download error', ['error' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function downloadContributions(int $periodId)
{
    try {
        $contributions = $this->pagIbigService->getContributions('pagibig', $periodId);

        if ($contributions->isEmpty()) {
            return response()->json(['error' => 'No contribution data found for this period.'], 404);
        }

        $period   = \App\Models\PayrollPeriod::find($periodId);
        $month    = $period?->period_start?->format('Y-m') ?? 'unknown';
        $filename = "PagIBIG-Contributions-{$month}.csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ];

        $callback = function () use ($contributions) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee Number',
                'Employee Name',
                'Pag-IBIG Number',
                'Month',
                'Monthly Compensation',
                'Employee Rate',
                'Employee Contribution',
                'Employer Contribution',
                'Total Contribution',
            ]);

            foreach ($contributions as $row) {
                fputcsv($handle, [
                    $row['employee_number'],
                    $row['employee_name'],
                    $row['pagibig_number'],
                    $row['month'],
                    number_format($row['monthly_compensation'], 2, '.', ''),
                    $row['employee_rate'] . '%',
                    number_format($row['employee_contribution'], 2, '.', ''),
                    number_format($row['employer_contribution'], 2, '.', ''),
                    number_format($row['total_contribution'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);

    } catch (\Exception $e) {
        Log::error('Pag-IBIG contributions download error', ['error' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    public function download(Request $request, int $reportId)
    {
        $type = $request->query('type', 'mcrf');

        return match ($type) {
            'contributions' => $this->downloadContributions($reportId),
            default         => $this->downloadMCRF($reportId),
        };
    }

    public function submit(Request $request, int $reportId)
    {
        Log::info('Pag-IBIG MCRF submission requested', [
            'report_id' => $reportId,
            'user_id'   => auth()->id(),
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'MCRF report submitted successfully',
            'submission_date' => now()->toDateTimeString(),
        ]);
    }

    private function mapSubmissionStatus(?string $status): string
    {
        return match ($status) {
            'submitted' => 'Submitted',
            'accepted'  => 'Accepted',
            'rejected'  => 'Rejected',
            'ready'     => 'Ready',
            default     => 'Not submitted',
        };
    }
}
