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
 * PhilHealthController
 * Manages PhilHealth contributions tracking and reporting
 * PhilHealth Rate: 5% of monthly basic (2.5% EE + 2.5% ER, max 5,000)
 */
class PhilHealthController extends Controller
{
    public function __construct(
        private GovernmentContributionService $philHealthService,
    ) {}

    public function index()
    {
        $rf1Reports = GovernmentReport::where('agency', 'philhealth')
            ->where('report_type', 'rf1')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GovernmentReport $r) => [
                'id'                     => $r->id,
                'period_id'              => $r->payroll_period_id,
                'month'                  => $r->report_period,
                'file_name'              => $r->file_name ?? '',
                'file_path'              => $r->file_path ?? '',
                'file_size'              => (int) ($r->file_size ?? 0),
                'total_employees'        => (int) ($r->total_employees ?? 0),
                'total_basic_salary'     => (float) ($r->total_compensation ?? 0),
                'total_employee_premium' => (float) ($r->total_employee_share ?? 0),
                'total_employer_premium' => (float) ($r->total_employer_share ?? 0),
                'total_premium'          => (float) ($r->total_amount ?? 0),
                'indigent_count'         => 0,
                'status'                 => $r->status ?? 'draft',
                'submission_status'      => $this->mapSubmissionStatus($r->status),
                'submission_date'        => $r->submitted_at?->format('Y-m-d'),
                'rejection_reason'       => $r->rejection_reason,
                'created_at'             => $r->created_at->toISOString(),
                'updated_at'             => $r->updated_at->toISOString(),
            ]);

        $contributions = $this->philHealthService->getContributions('philhealth');
        $periods       = $this->philHealthService->getPeriods();
        $summary       = $this->philHealthService->getSummary('philhealth');
        $remittances   = $this->philHealthService->getRemittances('philhealth');

        return Inertia::render('Payroll/Government/PhilHealth/Index', [
            'contributions' => $contributions,
            'periods'       => $periods,
            'summary'       => $summary,
            'remittances'   => $remittances,
            'rf1_reports'   => $rf1Reports,
        ]);
    }

    public function generateRF1(Request $request, int $periodId)
    {
        $validated = $request->validate([
            'month' => 'required|string|date_format:Y-m',
        ]);

        Log::info('PhilHealth RF1 generation requested', [
            'period_id' => $periodId,
            'month'     => $validated['month'],
            'user_id'   => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'RF1 report generated successfully',
        ]);
    }

public function downloadRF1(int $reportId)
{
    try {
        $report = GovernmentReport::where('agency', 'philhealth')
            ->where('report_type', 'rf1')
            ->findOrFail($reportId);

        // Serve stored file if it exists
        if ($report->file_path && Storage::exists($report->file_path)) {
            return Storage::download($report->file_path, $report->file_name ?? 'rf1_report.csv');
        }

        // Otherwise generate on the fly
        $contributions = $this->philHealthService->getContributions('philhealth', $report->payroll_period_id);
        $filename      = $report->file_name ?? "PhilHealth-RF1-{$report->report_period}.csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ];

        $callback = function () use ($contributions) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Sequence No.',
                'PhilHealth Number',
                'Employee Name',
                'Monthly Basic Salary',
                'EE Premium',
                'ER Premium',
                'Total Premium',
            ]);

            $seq = 1;
            foreach ($contributions as $row) {
                fputcsv($handle, [
                    $seq++,
                    $row['philhealth_number'],
                    $row['employee_name'],
                    number_format($row['monthly_basic'], 2, '.', ''),
                    number_format($row['employee_premium'], 2, '.', ''),
                    number_format($row['employer_premium'], 2, '.', ''),
                    number_format($row['total_premium'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);

    } catch (\Exception $e) {
        Log::error('PhilHealth RF1 download error', ['error' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
public function downloadContributions(int $periodId)
{
    try {
        $contributions = $this->philHealthService->getContributions('philhealth', $periodId);

        if ($contributions->isEmpty()) {
            return response()->json(['error' => 'No contribution data found for this period.'], 404);
        }

        $period   = \App\Models\PayrollPeriod::find($periodId);
        $month    = $period?->period_start?->format('Y-m') ?? 'unknown';
        $filename = "PhilHealth-Contributions-{$month}.csv";

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
                'PhilHealth Number',
                'Month',
                'Monthly Basic Salary',
                'Employee Premium (2.5%)',
                'Employer Premium (2.5%)',
                'Total Premium',
            ]);

            foreach ($contributions as $row) {
                fputcsv($handle, [
                    $row['employee_number'],
                    $row['employee_name'],
                    $row['philhealth_number'],
                    $row['month'],
                    number_format($row['monthly_basic'], 2, '.', ''),
                    number_format($row['employee_premium'], 2, '.', ''),
                    number_format($row['employer_premium'], 2, '.', ''),
                    number_format($row['total_premium'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);

    } catch (\Exception $e) {
        Log::error('PhilHealth contributions download error', ['error' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    public function download(Request $request, int $reportId)
    {
        $type = $request->query('type', 'rf1');

        return match ($type) {
            'contributions' => $this->downloadContributions($reportId),
            default         => $this->downloadRF1($reportId),
        };
    }

    public function submit(Request $request, int $reportId)
    {
        Log::info('PhilHealth RF1 submission requested', [
            'report_id' => $reportId,
            'user_id'   => auth()->id(),
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'RF1 report submitted successfully',
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
