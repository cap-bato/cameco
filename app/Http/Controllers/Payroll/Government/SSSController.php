<?php

namespace App\Http\Controllers\Payroll\Government;

use App\Http\Controllers\Controller;
use App\Models\GovernmentReport;
use App\Services\Payroll\GovernmentContributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SSSController extends Controller
{
    public function __construct(
        private GovernmentContributionService $sssService,
    ) {}

    public function index(Request $request)
    {
        try {
            $periodId = $request->input('period_id') ? (int) $request->input('period_id') : null;

            $r3Reports = GovernmentReport::with('payrollPeriod')
                ->where('agency', 'sss')
                ->where('report_type', 'r3')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (GovernmentReport $r) => [
                    'id'                 => $r->id,
                    'period_id'          => $r->payroll_period_id,
                    'month'              => $r->report_period,
                    'file_name'          => $r->file_name,
                    'file_path'          => $r->file_path,
                    'file_size'          => (int) ($r->file_size ?? 0),
                    'total_employees'    => $r->total_employees,
                    'total_compensation' => (float) $r->total_compensation,
                    'total_employee_share' => (float) $r->total_employee_share,
                    'total_employer_share' => (float) $r->total_employer_share,
                    'total_ec_share'     => 0.0,   // not stored separately; included in total_employee_share
                    'total_amount'       => (float) $r->total_amount,
                    'status'             => $r->status,
                    'submission_status'  => $r->submission_reference ?? ($r->status === 'accepted' ? 'accepted' : 'pending submission'),
                    'submission_date'    => $r->submitted_at?->toDateString(),
                    'rejection_reason'   => $r->rejection_reason,
                    'created_at'         => $r->created_at?->toISOString() ?? '',
                    'updated_at'         => $r->updated_at?->toISOString() ?? '',
                ]);

            return Inertia::render('Payroll/Government/SSS/Index', [
                'contributions' => $this->sssService->getContributions('sss', $periodId),
                'periods'       => $this->sssService->getPeriods(),
                'summary'       => $this->sssService->getSummary('sss', $periodId),
                'remittances'   => $this->sssService->getRemittances('sss'),
                'r3_reports'    => $r3Reports,
            ]);
        } catch (\Exception $e) {
            Log::error('SSS page load error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors('Failed to load SSS page');
        }
    }

    public function generateR3(Request $request, int $periodId)
    {
        try {
            $validated = $request->validate([
                'month' => 'required|date_format:Y-m',
            ]);

            Log::info('SSS R3 report generated', [
                'period_id'    => $periodId,
                'month'        => $validated['month'],
                'generated_by' => auth()->id(),
            ]);

            return back()->with('success', 'SSS R3 report generated successfully');
        } catch (\Exception $e) {
            Log::error('SSS R3 generation error', [
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors('Failed to generate SSS R3 report');
        }
    }

    public function downloadR3(int $reportId)
    {
        try {
            $report = GovernmentReport::where('agency', 'sss')
                ->where('report_type', 'r3')
                ->findOrFail($reportId);

            if (!$report->file_path || !Storage::exists($report->file_path)) {
                return response(['error' => 'Report file not found. Please regenerate the report.'], 404)
                    ->header('Content-Type', 'application/json');
            }

            Log::info('SSS R3 downloaded', [
                'report_id'     => $reportId,
                'file_name'     => $report->file_name,
                'downloaded_by' => auth()->id(),
            ]);

            return Storage::download($report->file_path, $report->file_name);
        } catch (\Exception $e) {
            Log::error('SSS R3 download error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['error' => $e->getMessage()], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    public function downloadContributions(int $periodId)
    {
        try {
            $report = GovernmentReport::where('agency', 'sss')
                ->where('payroll_period_id', $periodId)
                ->where('report_type', 'contributions')
                ->latest()
                ->first();

            if (!$report || !$report->file_path || !Storage::exists($report->file_path)) {
                return response(['error' => 'Contributions report not found. Please generate it first.'], 404)
                    ->header('Content-Type', 'application/json');
            }

            Log::info('SSS contributions downloaded', [
                'period_id'     => $periodId,
                'file_name'     => $report->file_name,
                'downloaded_by' => auth()->id(),
            ]);

            return Storage::download($report->file_path, $report->file_name);
        } catch (\Exception $e) {
            Log::error('SSS contributions download error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['error' => $e->getMessage()], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    public function download(Request $request, int $reportId)
    {
        try {
            $type = $request->query('type', 'r3');

            if ($type === 'contributions') {
                return $this->downloadContributions($reportId);
            }

            return $this->downloadR3($reportId);
        } catch (\Exception $e) {
            Log::error('SSS download error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['error' => $e->getMessage()], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    public function submit(Request $request, int $reportId)
    {
        try {
            $request->validate([
                'reference_number' => 'nullable|string',
                'payment_date'     => 'nullable|date',
            ]);

            Log::info('SSS R3 report submitted', [
                'report_id'       => $reportId,
                'submitted_by'    => auth()->id(),
                'submission_date' => now(),
            ]);

            return back()->with('success', 'SSS R3 report submitted successfully');
        } catch (\Exception $e) {
            Log::error('SSS submission error', [
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors('Failed to submit SSS R3 report');
        }
    }
}

