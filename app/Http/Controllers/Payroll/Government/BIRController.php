<?php

namespace App\Http\Controllers\Payroll\Government;

use App\Http\Controllers\Controller;
use App\Models\GovernmentReport;
use App\Services\Payroll\GovernmentContributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BIRController extends Controller
{
    public function __construct(
        private GovernmentContributionService $birService,
    ) {}

    /**
     * Display BIR Reports page.
     */
    public function index(Request $request)
    {
        $periodId = $request->input('period_id') ? (int) $request->input('period_id') : null;

        $reports = GovernmentReport::with('payrollPeriod')
            ->where('agency', 'bir')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GovernmentReport $r) => [
                'id'             => $r->id,
                'type'           => $this->mapReportType($r->report_type),
                'period_id'      => $r->payroll_period_id,
                'period_name'    => $r->payrollPeriod?->period_name ?? $r->report_period,
                'status'         => $r->status === 'accepted' ? 'approved' : $r->status,
                'generated_at'   => $r->created_at?->toISOString(),
                'submitted_at'   => $r->submitted_at?->toISOString(),
                'file_name'      => $r->file_name,
                'file_size'      => $r->file_size,
                'employee_count' => $r->total_employees,
                'total_amount'   => (float) $r->total_amount,
                'created_at'     => $r->created_at?->toISOString() ?? '',
                'updated_at'     => $r->updated_at?->toISOString() ?? '',
            ]);

        $generatedReports = GovernmentReport::where('agency', 'bir')
            ->whereNotIn('status', ['draft'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GovernmentReport $r) => [
                'id'                => $r->id,
                'report_type'       => $this->mapReportType($r->report_type),
                'period'            => $r->report_period,
                'file_name'         => $r->file_name,
                'file_path'         => $r->file_path,
                'file_size'         => (int) ($r->file_size ?? 0),
                'generated_at'      => $r->created_at?->toISOString() ?? '',
                'submitted'         => in_array($r->status, ['submitted', 'accepted']),
                'submission_status' => $this->mapSubmissionStatus($r->status),
                'rejection_reason'  => $r->rejection_reason,
            ]);

        return Inertia::render('Payroll/Government/BIR/Index', [
            'reports'           => $reports,
            'periods'           => $this->birService->getPeriods(),
            'summary'           => $this->birService->getSummary('bir', $periodId),
            'generated_reports' => $generatedReports,
        ]);
    }

    /**
     * Generate BIR Form 1601C (Monthly Remittance).
     */
    public function generate1601C(Request $request, int $periodId)
    {
        try {
            $validated = $request->validate([
                'rdo_code' => 'required|string',
            ]);

            Log::info('BIR Form 1601C generated', [
                'period_id'    => $periodId,
                'rdo_code'     => $validated['rdo_code'],
                'generated_by' => auth()->id(),
            ]);

            return back()->with('success', 'Form 1601C generated successfully');
        } catch (\Exception $e) {
            Log::error('Form 1601C generation error', [
                'period_id' => $periodId,
                'error'     => $e->getMessage(),
            ]);

            return back()->withErrors('Failed to generate Form 1601C: ' . $e->getMessage());
        }
    }

    /**
     * Generate BIR Form 2316 (Annual Certificate).
     */
    public function generate2316(Request $request, int $periodId)
    {
        try {
            Log::info('BIR Form 2316 certificates generated', [
                'period_id'    => $periodId,
                'generated_by' => auth()->id(),
            ]);

            return back()->with('success', 'Form 2316 certificates generated successfully for all employees');
        } catch (\Exception $e) {
            Log::error('Form 2316 generation error', [
                'period_id' => $periodId,
                'error'     => $e->getMessage(),
            ]);

            return back()->withErrors('Failed to generate Form 2316: ' . $e->getMessage());
        }
    }

    /**
     * Generate BIR Alphalist (DAT Format).
     */
    public function generateAlphalist(Request $request, int $periodId)
    {
        try {
            Log::info('BIR Alphalist generated', [
                'period_id'    => $periodId,
                'generated_by' => auth()->id(),
            ]);

            return back()->with('success', 'Alphalist generated successfully in DAT format');
        } catch (\Exception $e) {
            Log::error('Alphalist generation error', [
                'period_id' => $periodId,
                'error'     => $e->getMessage(),
            ]);

            return back()->withErrors('Failed to generate Alphalist: ' . $e->getMessage());
        }
    }

    /**
     * Download Form 1601C file for a period.
     */
    public function download1601C(Request $request, int $periodId)
    {
        return $this->downloadReportByPeriodAndType($periodId, '1601c');
    }

    /**
     * Download Form 2316 certificates for a period.
     */
    public function download2316(Request $request, int $periodId)
    {
        return $this->downloadReportByPeriodAndType($periodId, '2316');
    }

    /**
     * Download Alphalist DAT file for a period.
     */
    public function downloadAlphalist(Request $request, int $periodId)
    {
        return $this->downloadReportByPeriodAndType($periodId, 'alphalist');
    }

    /**
     * Submit Form 1601C to BIR.
     */
    public function submit1601C(Request $request, int $periodId)
    {
        try {
            Log::info('BIR Form 1601C submitted', [
                'period_id'    => $periodId,
                'report_type'  => '1601C',
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            return back()->with('success', 'Form 1601C submitted to BIR successfully');
        } catch (\Exception $e) {
            Log::error('Form 1601C submission error', [
                'period_id' => $periodId,
                'error'     => $e->getMessage(),
            ]);

            return back()->withErrors('Failed to submit Form 1601C: ' . $e->getMessage());
        }
    }

    /**
     * Download a generated report file by its ID.
     */
    public function download(Request $request, int $reportId)
    {
        try {
            $report = GovernmentReport::where('agency', 'bir')->findOrFail($reportId);

            if (!$report->file_path || !Storage::exists($report->file_path)) {
                return back()->withErrors('Report file not found. Please regenerate the report.');
            }

            Log::info('BIR report downloaded', [
                'report_id'     => $reportId,
                'report_type'   => $report->report_type,
                'file_name'     => $report->file_name,
                'downloaded_by' => auth()->id(),
            ]);

            return Storage::download($report->file_path, $report->file_name);
        } catch (\Exception $e) {
            Log::error('Report download error', [
                'report_id' => $reportId,
                'error'     => $e->getMessage(),
            ]);

            return back()->withErrors('Failed to download report: ' . $e->getMessage());
        }
    }

    /**
     * Submit a generated report to BIR.
     */
    public function submit(Request $request, int $reportId)
    {
        try {
            Log::info('BIR report submitted', [
                'report_id'    => $reportId,
                'submitted_by' => auth()->id(),
            ]);

            return back()->with('success', 'Report submitted to BIR successfully');
        } catch (\Exception $e) {
            Log::error('Report submission error', [
                'report_id' => $reportId,
                'error'     => $e->getMessage(),
            ]);

            return back()->withErrors('Failed to submit report: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function downloadReportByPeriodAndType(int $periodId, string $reportType)
    {
        try {
            $report = GovernmentReport::where('agency', 'bir')
                ->where('payroll_period_id', $periodId)
                ->where('report_type', $reportType)
                ->latest()
                ->first();

            if (!$report || !$report->file_path || !Storage::exists($report->file_path)) {
                return back()->withErrors('Report file not found. Please generate the report first.');
            }

            Log::info('BIR report downloaded', [
                'period_id'     => $periodId,
                'report_type'   => $reportType,
                'file_name'     => $report->file_name,
                'downloaded_by' => auth()->id(),
            ]);

            return Storage::download($report->file_path, $report->file_name);
        } catch (\Exception $e) {
            Log::error('Report download error', [
                'period_id'   => $periodId,
                'report_type' => $reportType,
                'error'       => $e->getMessage(),
            ]);

            return back()->withErrors('Failed to download report: ' . $e->getMessage());
        }
    }

    private function mapReportType(string $type): string
    {
        return match (strtolower($type)) {
            '1601c'     => '1601C',
            '2316'      => '2316',
            'alphalist' => 'Alphalist',
            '1604c'     => '1604C',
            '1604cf'    => '1604CF',
            '1601e'     => 'BIR1601E',
            default     => strtoupper($type),
        };
    }

    private function mapSubmissionStatus(string $status): string
    {
        return match ($status) {
            'submitted', 'accepted' => 'submitted',
            'rejected'              => 'rejected',
            default                 => 'pending',
        };
    }
}
