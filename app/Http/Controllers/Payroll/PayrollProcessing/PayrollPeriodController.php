<?php

namespace App\Http\Controllers\Payroll\PayrollProcessing;

use App\Http\Controllers\Controller;
use App\Jobs\Payroll\CalculatePayrollJob;
use App\Models\PayrollCalculationLog;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class PayrollPeriodController extends Controller
{
    /**
     * Display a listing of payroll periods with filtering
     */
    public function index(Request $request)
    {
        $query = PayrollPeriod::with(['approvedBy:id,name', 'lockedBy:id,name'])
            ->orderByDesc('period_start');

        // Search filter
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // Status filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $dbStatuses = $this->frontendStatusToDb($request->input('status'));
            if (is_array($dbStatuses)) {
                $query->whereIn('status', $dbStatuses);
            } else {
                $query->byStatus($dbStatuses);
            }
        }

        // Year filter
        if ($request->filled('year') && $request->input('year') !== 'all') {
            $query->byYear((int) $request->input('year'));
        }

        $periods = $query->get()->map(fn($p) => $this->transformPeriod($p));

        return Inertia::render('Payroll/PayrollProcessing/Periods/Index', [
            'periods' => $periods->values(),
            'filters' => [
                'search'      => $request->input('search'),
                'status'      => $request->input('status'),
                'period_type' => $request->input('period_type'),
                'year'        => $request->input('year', date('Y')),
            ],
        ]);
    }

    /**
     * Store a newly created payroll period
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'period_type' => 'required|in:weekly,bi_weekly,semi_monthly,monthly',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
            'cutoff_date' => 'required|date',
            'pay_date'    => 'required|date|after:end_date',
            // Deduction timing overrides (optional)
            'deduction_timing' => 'nullable|array',
            'deduction_timing.sss.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.sss.apply_on_period' => 'nullable|in:1,2',
            'deduction_timing.philhealth.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.philhealth.apply_on_period' => 'nullable|in:1,2',
            'deduction_timing.pagibig.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.pagibig.apply_on_period' => 'nullable|in:1,2',
            'deduction_timing.withholding_tax.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.withholding_tax.apply_on_period' => 'nullable|in:1,2',
            'deduction_timing.loans.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.loans.apply_on_period' => 'nullable|in:1,2',
        ]);

        try {
            $start = Carbon::parse($validated['start_date']);

            // Prepare calculation_config with deduction_timing overrides
            $calculationConfig = [];
            if (!empty($validated['deduction_timing'])) {
                // Only store overrides where timing is explicitly set (not null)
                $overrides = array_filter(
                    $validated['deduction_timing'],
                    fn($v) => is_array($v) && !empty($v['timing'])
                );
                if (!empty($overrides)) {
                    $calculationConfig['deduction_timing'] = $overrides;
                }
            }

            PayrollPeriod::create([
                'period_number'           => $this->generatePeriodNumber($validated['start_date']),
                'period_name'             => $validated['name'],
                'period_type'             => 'regular',
                'period_start'            => $validated['start_date'],
                'period_end'              => $validated['end_date'],
                'payment_date'            => $validated['pay_date'],
                'period_month'            => $start->format('Y-m'),
                'period_year'             => $start->year,
                'timekeeping_cutoff_date' => $validated['cutoff_date'],
                'leave_cutoff_date'       => $validated['cutoff_date'],
                'adjustment_deadline'     => $validated['cutoff_date'],
                'status'                  => 'draft',
                'created_by'              => auth()->id(),
                'calculation_config'      => !empty($calculationConfig) ? $calculationConfig : null,
            ]);

            return redirect()->route('payroll.periods.index')
                ->with('success', "Payroll period '{$validated['name']}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create payroll period', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create payroll period. Please try again.');
        }
    }

    /**
     * Display the specified payroll period
     */
    public function show($id)
    {
        $period = PayrollPeriod::findOrFail($id);
        // Redirect to index for now — detail page not yet built
        return redirect()->route('payroll.periods.index');
    }

    /**
     * Show the form for editing the specified payroll period
     */
    public function edit($id)
    {
        $period = PayrollPeriod::findOrFail($id);

        if ($period->status !== 'draft') {
            return redirect()->route('payroll.periods.index')
                ->with('error', 'Only draft periods can be edited.');
        }

        return redirect()->route('payroll.periods.index');
    }

    /**
     * Update the specified payroll period
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'period_type' => 'required|in:weekly,bi_weekly,semi_monthly,monthly',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
            'cutoff_date' => 'required|date',
            'pay_date'    => 'required|date|after:end_date',
            // Deduction timing overrides (optional)
            'deduction_timing' => 'nullable|array',
            'deduction_timing.sss.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.sss.apply_on_period' => 'nullable|in:1,2',
            'deduction_timing.philhealth.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.philhealth.apply_on_period' => 'nullable|in:1,2',
            'deduction_timing.pagibig.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.pagibig.apply_on_period' => 'nullable|in:1,2',
            'deduction_timing.withholding_tax.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.withholding_tax.apply_on_period' => 'nullable|in:1,2',
            'deduction_timing.loans.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
            'deduction_timing.loans.apply_on_period' => 'nullable|in:1,2',
        ]);

        try {
            $period = PayrollPeriod::findOrFail($id);

            if ($period->status !== 'draft') {
                return redirect()->back()
                    ->with('error', 'Only draft periods can be edited.');
            }

            $start = Carbon::parse($validated['start_date']);

            // Prepare calculation_config with deduction_timing overrides
            $calculationConfig = [];
            if (!empty($validated['deduction_timing'])) {
                // Only store overrides where timing is explicitly set (not null)
                $overrides = array_filter(
                    $validated['deduction_timing'],
                    fn($v) => is_array($v) && !empty($v['timing'])
                );
                if (!empty($overrides)) {
                    $calculationConfig['deduction_timing'] = $overrides;
                }
            }

            $period->update([
                'period_name'             => $validated['name'],
                'period_start'            => $validated['start_date'],
                'period_end'              => $validated['end_date'],
                'payment_date'            => $validated['pay_date'],
                'period_month'            => $start->format('Y-m'),
                'period_year'             => $start->year,
                'timekeeping_cutoff_date' => $validated['cutoff_date'],
                'leave_cutoff_date'       => $validated['cutoff_date'],
                'adjustment_deadline'     => $validated['cutoff_date'],
                'calculation_config'      => !empty($calculationConfig) ? $calculationConfig : null,
            ]);

            return redirect()->route('payroll.periods.index')
                ->with('success', "Payroll period '{$validated['name']}' updated successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to update payroll period', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update payroll period. Please try again.');
        }
    }

    /**
     * Delete the specified payroll period
     */
    public function destroy($id)
    {
        try {
            $period = PayrollPeriod::findOrFail($id);

            if (!in_array($period->status, ['draft', 'cancelled'])) {
                return redirect()->back()
                    ->with('error', 'Only draft or cancelled periods can be deleted.');
            }

            $period->delete();

            return redirect()->route('payroll.periods.index')
                ->with('success', 'Payroll period deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete payroll period', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to delete payroll period. Please try again.');
        }
    }

    /**
     * Calculate payroll for the specified period
     */
    public function calculate(Request $request, $id)
    {
        try {
            $period = PayrollPeriod::findOrFail($id);

            if (!in_array($period->status, ['draft', 'active', 'calculated'])) {
                return redirect()->back()
                    ->with('error', 'Period must be draft, active, or calculated to calculate.');
            }

            // Use model method to mark as calculating
            $period->markAsCalculating();

            // Dispatch calculation job
            CalculatePayrollJob::dispatch($period, auth()->id());

            // Log the calculation start
            PayrollCalculationLog::create([
                'payroll_period_id' => $period->id,
                'log_type'          => 'calculation_started',
                'severity'          => 'info',
                'message'           => "Payroll calculation started for period: {$period->period_name}",
                'actor_type'        => 'user',
                'actor_id'          => auth()->id(),
                'actor_name'        => auth()->user()->name,
                'created_at'        => now(),
            ]);

            return redirect()->back()
                ->with('success', 'Payroll calculation started. This may take a few moments.');
        } catch (\Exception $e) {
            Log::error('Failed to start payroll calculation', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to start calculation: ' . $e->getMessage());
        }
    }

    /**
     * Submit period for review
     */
    public function submitForReview(Request $request, $id)
    {
        try {
            $period = PayrollPeriod::findOrFail($id);

            if ($period->status !== 'calculated') {
                return redirect()->back()
                    ->with('error', 'Only calculated periods can be submitted for review.');
            }

            // Use model method to submit for review
            $period->submitForReview();

            return redirect()->back()
                ->with('success', 'Payroll submitted for review successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to submit period for review', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Approve payroll for the specified period
     */
    public function approve(Request $request, $id)
    {
        try {
            $period = PayrollPeriod::findOrFail($id);

            if (!in_array($period->status, ['under_review', 'pending_approval'])) {
                return redirect()->back()
                    ->with('error', 'Only periods under review can be approved.');
            }

            // Use model method to approve
            $period->approve(auth()->id());

            return redirect()->back()
                ->with('success', 'Payroll period approved successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to approve payroll period', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Finalize payroll for the specified period
     */
    public function finalize(Request $request, $id)
    {
        try {
            $period = PayrollPeriod::findOrFail($id);

            if ($period->status !== 'approved') {
                return redirect()->back()
                    ->with('error', 'Only approved periods can be finalized.');
            }

            // Use model method to finalize
            $period->finalize(auth()->id());

            return redirect()->back()
                ->with('success', 'Payroll period finalized successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to finalize payroll period', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Map a PayrollPeriod model to the shape expected by the frontend.
     */
    private function transformPeriod(PayrollPeriod $p): array
    {
        return [
            'id'                 => $p->id,
            'name'               => $p->period_name,
            'period_type'        => $this->inferFrequency($p->period_start, $p->period_end),
            'start_date'         => $p->period_start?->toDateString(),
            'end_date'           => $p->period_end?->toDateString(),
            'cutoff_date'        => $p->timekeeping_cutoff_date?->toDateString(),
            'pay_date'           => $p->payment_date?->toDateString(),
            'status'             => $this->dbStatusToFrontend($p->status),
            'total_employees'    => $p->total_employees ?? 0,
            'progress_percentage'=> (float) ($p->progress_percentage ?? 0),
            'total_gross_pay'    => (float) ($p->total_gross_pay ?? 0),
            'total_deductions'   => (float) ($p->total_deductions ?? 0),
            'total_net_pay'      => (float) ($p->total_net_pay ?? 0),
            'total_employer_cost'=> (float) ($p->total_government_contributions ?? 0),
            'processed_at'       => $p->calculation_completed_at?->toISOString(),
            'approved_by'        => $p->approvedBy?->name,
            'approved_at'        => $p->approved_at?->toISOString(),
            'finalized_by'       => $p->lockedBy?->name,
            'finalized_at'       => $p->finalized_at?->toISOString() ?? $p->locked_at?->toISOString(),
            'created_at'         => $p->created_at?->toISOString(),
            'updated_at'         => $p->updated_at?->toISOString(),
        ];
    }

    /**
     * Infer pay frequency from date range (used as frontend period_type).
     */
    private function inferFrequency($start, $end): string
    {
        if (!$start || !$end) {
            return 'semi_monthly';
        }

        $days = Carbon::parse($start)->diffInDays(Carbon::parse($end));

        return match(true) {
            $days <= 7  => 'weekly',
            $days <= 10 => 'bi_weekly',
            $days <= 17 => 'semi_monthly',
            default     => 'monthly',
        };
    }

    /**
     * Map a DB status value to what the frontend PayrollPeriod type expects.
     */
    private function dbStatusToFrontend(string $status): string
    {
        return match($status) {
            'draft', 'active'                    => 'draft',
            'calculating'                         => 'calculating',
            'calculated'                          => 'calculated',
            'under_review', 'pending_approval'   => 'reviewing',
            'approved', 'finalized'               => 'approved',
            'processing_payment', 'completed'    => 'paid',
            'cancelled'                           => 'closed',
            default                               => 'draft',
        };
    }

    /**
     * Map a frontend status filter value to one or more DB status values.
     *
     * @return string|string[]
     */
    private function frontendStatusToDb(string $status): array
    {
        return match($status) {
            'draft'      => ['draft', 'active'],
            'calculating'=> ['calculating'],
            'calculated' => ['calculated'],
            'reviewing'  => ['under_review', 'pending_approval'],
            'approved'   => ['approved', 'finalized'],
            'paid'       => ['processing_payment', 'completed'],
            'closed'     => ['cancelled'],
            default      => [],
        };
    }

    /**
     * Generate a unique period number from a start date.
     * Format: YYYY-MM-DD (padded with microseconds when duplicate).
     */
    private function generatePeriodNumber(string $startDate): string
    {
        $base = Carbon::parse($startDate)->format('Y-m-d');
        $number = $base;
        $suffix = 1;

        while (PayrollPeriod::where('period_number', $number)->exists()) {
            $number = $base . '-' . $suffix++;
        }

        return $number;
    }
}
