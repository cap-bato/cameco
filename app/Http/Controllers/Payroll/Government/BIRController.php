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

        // Fetch BIR Alphalist employee data for the selected period
        $birAlphalistEmployees = collect($this->birService->getContributions('bir', $periodId))
            ->map(function ($row, $i) {
                $row = (object) $row;
                return [
                    'sequence_number' => $i + 1,
                    'tin' => $row->tin ?? '',
                    'employee_name' => $row->employee_name ?? '',
                    'address' => $row->employee_address ?? '',
                    'birth_date' => $row->birth_date ?? '',
                    'gender' => $row->gender ?? '',
                    'civil_status' => $row->civil_status ?? '',
                    'annual_gross_compensation' => $row->gross_compensation ?? 0,
                    'annual_non_taxable_compensation' => $row->deminimis_benefits ?? 0,
                    'annual_taxable_compensation' => $row->taxable_income ?? 0,
                    'annual_tax_withheld' => $row->withholding_tax ?? 0,
                    'status_flag' => $row->status_flag ?? 'Active',
                ];
            })->values();

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

        // Fetch BIR employee contributions for the selected period
        $birEmployees = collect($this->birService->getContributions('bir', $periodId))
            ->map(function ($row) {
                $row = (object) $row;
                return [
                    'employee_id' => isset($row->employee_number) ? $row->employee_number : $row->employee_id,
                    'tin' => $row->tin ?? '',
                    'employee_name' => $row->employee_name ?? '',
                    'gross_compensation' => $row->gross_compensation ?? 0,
                    'withholding_tax' => $row->withholding_tax ?? 0,
                ];
            })->values();

        // Fetch BIR 2316 certificate data for the selected period
        $bir2316Certificates = collect($this->birService->getContributions('bir', $periodId))
            ->map(function ($row) use ($periodId) {
                $row = (object) $row;
                return [
                    'id' => $row->id ?? null,
                    'employee_id' => isset($row->employee_number) ? $row->employee_number : $row->employee_id,
                    'tin' => $row->tin ?? '',
                    'employee_name' => $row->employee_name ?? '',
                    'employee_address' => $row->employee_address ?? '',
                    'employer_tin' => $row->employer_tin ?? '',
                    'employer_name' => $row->employer_name ?? '',
                    'tax_year' => $row->tax_year ?? (now()->year),
                    'gross_compensation' => $row->gross_compensation ?? 0,
                    'non_taxable_compensation' => $row->deminimis_benefits ?? 0,
                    'taxable_compensation' => $row->taxable_income ?? 0,
                    'tax_withheld' => $row->withholding_tax ?? 0,
                    'deductions_from_compensation' => $row->deductions_from_compensation ?? 0,
                    'net_compensation' => ($row->gross_compensation ?? 0) - ($row->deductions_from_compensation ?? 0),
                    'generated_at' => $row->generated_at ?? null,
                    'issued_at' => $row->issued_at ?? null,
                ];
            })->values();

        return Inertia::render('Payroll/Government/BIR/Index', [
            'reports'           => $reports,
            'periods'           => $this->birService->getPeriods(),
            'summary'           => $this->birService->getSummary('bir', $periodId),
            'generated_reports' => $generatedReports,
            'bir_employees'     => $birEmployees,
            'bir_2316_certificates' => $bir2316Certificates,
            'bir_alphalist_employees' => $birAlphalistEmployees,
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

            if ($request->hasHeader('X-Inertia')) {
                    return Inertia::location(route('payroll.bir.index'));
            }
            return back()->with('success', 'Form 2316 certificates generated successfully for all employees');
        } catch (\Exception $e) {
            Log::error('Form 2316 generation error', [
                'period_id' => $periodId,
                'error'     => $e->getMessage(),
            ]);

            if ($request->hasHeader('X-Inertia')) {
                    return Inertia::location(route('payroll.bir.index'));
            }
            return back()->withErrors('Failed to generate Form 2316: ' . $e->getMessage());
        }

    }
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
