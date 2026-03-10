<?php

namespace App\Http\Controllers\Payroll\PayrollProcessing;

use App\Http\Controllers\Controller;
use App\Jobs\Payroll\CalculatePayrollJob;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PayrollCalculationController extends Controller
{
    /**
     * Display a listing of payroll calculations (one per payroll period).
     */
    public function index(Request $request): Response
    {
        $periodId        = $request->input('period_id');
        $status          = $request->input('status');
        $calculationType = $request->input('calculation_type');

        $periodsQuery = PayrollPeriod::with(['approvedBy:id,name', 'lockedBy:id,name'])
            ->orderByDesc('period_start');

        if ($periodId) {
            $periodsQuery->where('id', $periodId);
        }

        if ($status) {
            $dbStatuses = $this->calcStatusToDbStatuses($status);
            if (!empty($dbStatuses)) {
                $periodsQuery->whereIn('status', $dbStatuses);
            }
        }

        if ($calculationType) {
            $dbType = $this->calcTypeToDbType($calculationType);
            if ($dbType) {
                $periodsQuery->where('period_type', $dbType);
            }
        }

        $periods = $periodsQuery->get();

        $periodIds = $periods->pluck('id')->all();
        $empCounts = EmployeePayrollCalculation::select('payroll_period_id',
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN calculation_status IN ('calculated','adjusted','approved','locked') THEN 1 ELSE 0 END) as processed"),
                DB::raw("SUM(CASE WHEN calculation_status = 'exception' THEN 1 ELSE 0 END) as failed")
            )
            ->whereIn('payroll_period_id', $periodIds)
            ->groupBy('payroll_period_id')
            ->get()
            ->keyBy('payroll_period_id');

        $calculations = $periods->map(fn ($p) => $this->transformToCalculation($p, $empCounts->get($p->id)));

        $availablePeriods = PayrollPeriod::orderByDesc('period_start')
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'name'       => $p->period_name,
                'start_date' => $p->period_start?->toDateString(),
                'end_date'   => $p->period_end?->toDateString(),
                'status'     => $this->dbStatusToCalcStatus($p->status),
            ]);

        return Inertia::render('Payroll/PayrollProcessing/Calculations/Index', [
            'calculations'      => $calculations->values(),
            'available_periods' => $availablePeriods->values(),
            'filters'           => [
                'period_id'        => $periodId ? (int) $periodId : null,
                'status'           => $status,
                'calculation_type' => $calculationType,
            ],
        ]);
    }

    /**
     * Start a payroll calculation for the given period.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'payroll_period_id' => 'required|integer|exists:payroll_periods,id',
            'calculation_type'  => 'required|in:regular,adjustment,final,re-calculation',
        ]);

        try {
            $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);

            // Use model method for status transition
            $period->markAsCalculating();

            CalculatePayrollJob::dispatch($period, auth()->id());

            return redirect()
                ->route('payroll.calculations.index')
                ->with('success', 'Payroll calculation started successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to start payroll calculation', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to start calculation: ' . $e->getMessage());
        }
    }

    /**
     * Show per-employee calculation details for a period.
     */
    public function show(int $id): Response
    {
        $period = PayrollPeriod::with(['approvedBy:id,name', 'lockedBy:id,name'])->findOrFail($id);

        $empCounts = EmployeePayrollCalculation::select('payroll_period_id',
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN calculation_status IN ('calculated','adjusted','approved','locked') THEN 1 ELSE 0 END) as processed"),
                DB::raw("SUM(CASE WHEN calculation_status = 'exception' THEN 1 ELSE 0 END) as failed")
            )
            ->where('payroll_period_id', $id)
            ->groupBy('payroll_period_id')
            ->first();

        $calculation = $this->transformToCalculation($period, $empCounts);

        $employeeCalculations = EmployeePayrollCalculation::where('payroll_period_id', $id)
            ->orderBy('employee_name')
            ->get()
            ->map(fn ($e) => [
                'id'              => $e->id,
                'employee_id'     => $e->employee_id,
                'employee_name'   => $e->employee_name,
                'employee_number' => $e->employee_number,
                'department'      => $e->department,
                'position'        => $e->position,
                'calculation_id'  => $e->payroll_period_id,
                'status'          => $this->empStatusToFrontend($e->calculation_status),
                'basic_pay'       => (float) $e->basic_pay,
                'earnings'        => [
                    'overtime'   => (float) $e->total_overtime_pay,
                    'allowances' => (float) $e->total_allowances,
                    'bonuses'    => (float) $e->total_bonuses,
                ],
                'deductions'      => [
                    'sss'       => (float) $e->sss_contribution,
                    'philhealth'=> (float) $e->philhealth_contribution,
                    'pagibig'   => (float) $e->pagibig_contribution,
                    'tax'       => (float) $e->withholding_tax,
                    'loans'     => (float) $e->total_loan_deductions,
                ],
                'gross_pay'        => (float) $e->gross_pay,
                'total_deductions' => (float) $e->total_deductions,
                'net_pay'          => (float) ($e->final_net_pay ?? $e->net_pay),
                'error_message'    => $e->has_exceptions
                    ? implode(', ', $e->exception_flags ?? [])
                    : null,
                'calculated_at'    => $e->calculated_at?->toISOString(),
            ]);

        return Inertia::render('Payroll/PayrollProcessing/Calculations/Show', [
            'calculation'           => $calculation,
            'employee_calculations' => $employeeCalculations->values(),
        ]);
    }

    /**
     * Recalculate: reset period back to calculating state.
     */
    public function recalculate(int $id): \Illuminate\Http\RedirectResponse
    {
        try {
            $period = PayrollPeriod::findOrFail($id);

            // Use model method for status transition
            $period->markAsCalculating();
            
            // Clear completion timestamp for recalculation
            $period->update(['calculation_completed_at' => null]);

            CalculatePayrollJob::dispatch($period, auth()->id());

            return redirect()
                ->route('payroll.calculations.index')
                ->with('success', 'Payroll recalculation started successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to recalculate payroll', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to start recalculation: ' . $e->getMessage());
        }
    }

    /**
     * Approve a completed/calculated period.
     */
    public function approve(int $id): \Illuminate\Http\RedirectResponse
    {
        try {
            $period = PayrollPeriod::findOrFail($id);

            // Use model method for approval
            $period->approve(auth()->id());

            return redirect()
                ->route('payroll.calculations.index')
                ->with('success', 'Payroll calculation approved successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to approve payroll calculation', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to approve: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a pending/processing calculation.
     */
    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        try {
            $period = PayrollPeriod::findOrFail($id);

            if (!in_array($period->status, ['draft', 'active', 'calculating'])) {
                return redirect()->back()
                    ->with('error', 'Only pending or in-progress calculations can be cancelled.');
            }

            $period->update(['status' => 'cancelled']);

            return redirect()
                ->route('payroll.calculations.index')
                ->with('success', 'Payroll calculation cancelled successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to cancel payroll calculation', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to cancel. Please try again.');
        }
    }

    /**
     * Get real-time batch progress for a payroll period.
     * Returns batch-level progress metrics if batch_id exists, otherwise returns period-level progress.
     *
     * @param PayrollPeriod $period
     * @return JsonResponse
     */
    public function batchStatus(PayrollPeriod $period): JsonResponse
    {
        try {
            // If no batch ID, return period-level progress
            if (!$period->calculation_batch_id) {
                return response()->json([
                    'progress'           => (float) ($period->progress_percentage ?? 0),
                    'total_jobs'         => null,
                    'pending_jobs'       => null,
                    'failed_jobs'        => null,
                    'finished'           => null,
                    'cancelled'          => null,
                    'batch_found'        => false,
                ]);
            }

            // Find the batch by ID
            $batch = Bus::findBatch($period->calculation_batch_id);
            if (!$batch) {
                // Batch not found or expired; return period-level progress
                return response()->json([
                    'progress'           => (float) ($period->progress_percentage ?? 0),
                    'total_jobs'         => null,
                    'pending_jobs'       => null,
                    'failed_jobs'        => null,
                    'finished'           => null,
                    'cancelled'          => null,
                    'batch_found'        => false,
                ]);
            }

            // Return batch-level progress metrics
            return response()->json([
                'progress'           => $batch->progress(),       // 0–100
                'total_jobs'         => $batch->totalJobs,
                'pending_jobs'       => $batch->pendingJobs,
                'failed_jobs'        => $batch->failedJobs,
                'finished'           => $batch->finished(),
                'cancelled'          => $batch->cancelled(),
                'batch_found'        => true,
                'batch_id'           => $period->calculation_batch_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve batch status', [
                'period_id' => $period->id,
                'batch_id' => $period->calculation_batch_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'progress'           => (float) ($period->progress_percentage ?? 0),
                'total_jobs'         => null,
                'pending_jobs'       => null,
                'failed_jobs'        => null,
                'finished'           => null,
                'cancelled'          => null,
                'batch_found'        => false,
                'error'              => 'Failed to retrieve batch status',
            ], 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================


    private function transformToCalculation(PayrollPeriod $p, mixed $counts = null): array
    {
        $total     = $p->total_employees ?? (int) ($counts?->total ?? 0);
        $processed = (int) ($counts?->processed ?? 0);
        $failed    = (int) ($counts?->failed ?? 0);
        
        // Use database progress_percentage value from UpdatePayrollProgress listener
        $progress  = (float) ($p->progress_percentage ?? 0);

        $calcStatus = $this->dbStatusToCalcStatus($p->status);

        if (in_array($calcStatus, ['completed', 'cancelled'])) {
            $progress = 100;
        }

        return [
            'id'                  => $p->id,
            'payroll_period_id'   => $p->id,
            'payroll_period'      => [
                'id'         => $p->id,
                'name'       => $p->period_name,
                'start_date' => $p->period_start?->toDateString(),
                'end_date'   => $p->period_end?->toDateString(),
                'status'     => $calcStatus,
            ],
            'calculation_type'    => $this->dbTypeToCalcType($p->period_type),
            'status'              => $calcStatus,
            'total_employees'     => $total,
            'processed_employees' => $processed,
            'failed_employees'    => $failed,
            'progress_percentage' => $progress,
            'total_gross_pay'     => (float) ($p->total_gross_pay ?? 0),
            'total_deductions'    => (float) ($p->total_deductions ?? 0),
            'total_net_pay'       => (float) ($p->total_net_pay ?? 0),
            'calculation_date'    => ($p->calculation_completed_at ?? $p->updated_at)?->toISOString(),
            'started_at'          => $p->updated_at?->toISOString(),
            'completed_at'        => $p->calculation_completed_at?->toISOString(),
            'error_message'       => null,
            'calculated_by'       => $p->approvedBy?->name,
            'created_at'          => $p->created_at?->toISOString(),
            'updated_at'          => $p->updated_at?->toISOString(),
        ];
    }

    private function dbStatusToCalcStatus(string $status): string
    {
        return match ($status) {
            'draft', 'active'                                                    => 'pending',
            'calculating'                                                         => 'processing',
            'calculated', 'under_review', 'pending_approval', 'approved',
            'finalized', 'processing_payment', 'completed'                       => 'completed',
            'cancelled'                                                           => 'cancelled',
            default                                                               => 'pending',
        };
    }

    private function calcStatusToDbStatuses(string $status): array
    {
        return match ($status) {
            'pending'    => ['draft', 'active'],
            'processing' => ['calculating'],
            'completed'  => ['calculated', 'under_review', 'pending_approval', 'approved', 'finalized', 'processing_payment', 'completed'],
            'cancelled'  => ['cancelled'],
            default      => [],
        };
    }

    private function dbTypeToCalcType(string $type): string
    {
        return match ($type) {
            'adjustment'                           => 'adjustment',
            '13th_month', 'final_pay',
            'mid_year_bonus'                       => 'final',
            default                                => 'regular',
        };
    }

    private function calcTypeToDbType(string $type): ?string
    {
        return match ($type) {
            'regular'    => 'regular',
            'adjustment' => 'adjustment',
            'final'      => 'final_pay',
            default      => null,
        };
    }

    private function empStatusToFrontend(string $status): string
    {
        return match ($status) {
            'pending', 'calculating' => 'pending',
            'calculated'             => 'calculated',
            'exception'              => 'failed',
            'adjusted'               => 'adjusted',
            'approved', 'locked'     => 'calculated',
            default                  => 'pending',
        };
    }
}
