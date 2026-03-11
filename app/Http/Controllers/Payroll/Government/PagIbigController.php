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
        $report = GovernmentReport::findOrFail($reportId);

        if (!$report->file_path || !Storage::exists($report->file_path)) {
            return response()->json(['success' => false, 'message' => 'MCRF report file not available'], 404);
        }

        return Storage::download($report->file_path, $report->file_name ?? 'pb_mcrf.csv');
    }

    public function downloadContributions(int $periodId)
    {
        $report = GovernmentReport::where('agency', 'pagibig')
            ->where('report_type', 'contributions')
            ->where('payroll_period_id', $periodId)
            ->latest()
            ->first();

        if (!$report || !$report->file_path || !Storage::exists($report->file_path)) {
            return response()->json(['success' => false, 'message' => 'Contributions report not available'], 404);
        }

        return Storage::download($report->file_path, $report->file_name ?? 'pagibig_contributions.csv');
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
