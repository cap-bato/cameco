<?php

namespace App\Http\Controllers\Payroll\PayrollProcessing;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PayrollAdjustmentController extends Controller
{
    /**
     * Display a listing of payroll adjustments
     */
    public function index(Request $request): Response
    {
        // Get available periods from database
        $availablePeriods = PayrollPeriod::orderByDesc('period_start')
            ->get(['id', 'period_number', 'period_type', 'period_start', 'period_end', 'payment_date', 'status'])
            ->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->period_number . ' (' . $p->period_start?->format('M d') . '–' . $p->period_end?->format('M d, Y') . ')',
                'period_type'  => $p->period_type,
                'start_date'   => $p->period_start?->toDateString(),
                'end_date'     => $p->period_end?->toDateString(),
                'payment_date' => $p->payment_date?->toDateString(),
                'status'       => $p->status,
            ])
            ->values()
            ->all();

        // Get available employees from database
        $availableEmployees = Employee::with(['profile:id,first_name,last_name', 'department:id,name'])
            ->orderBy('employee_number')
            ->get()
            ->map(fn($e) => [
                'id'              => $e->id,
                'name'            => ($e->profile->first_name ?? '') . ' ' . ($e->profile->last_name ?? ''),
                'employee_number' => $e->employee_number,
                'department'      => $e->department->name ?? 'N/A',
            ])
            ->values()
            ->all();

        // Build query for adjustments
        $query = PayrollAdjustment::with(['payrollPeriod:id,period_name,period_start,period_end,payment_date,status', 'employee.profile:id,first_name,last_name', 'employee.department:id,name', 'employee.position:id,title'])
            ->orderByDesc('created_at');

        // Apply filters
        if ($request->filled('period_id')) {
            $query->byPeriod($request->input('period_id'));
        }

        if ($request->filled('employee_id')) {
            $query->byEmployee($request->input('employee_id'));
        }

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('adjustment_type')) {
            $query->byType($request->input('adjustment_type'));
        }

        // Get adjustments
        $adjustments = $query->get()->map(fn($adj) => $this->transformAdjustment($adj))->values()->all();

        return Inertia::render('Payroll/PayrollProcessing/Adjustments/Index', [
            'adjustments'         => $adjustments,
            'available_periods'   => $availablePeriods,
            'available_employees' => $availableEmployees,
            'filters'             => [
                'period_id'       => $request->input('period_id') ? (int) $request->input('period_id') : null,
                'employee_id'     => $request->input('employee_id') ? (int) $request->input('employee_id') : null,
                'status'          => $request->input('status'),
                'adjustment_type' => $request->input('adjustment_type'),
            ],
        ]);
    }

    /**
     * Store a newly created adjustment
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payroll_period_id'   => 'required|integer|exists:payroll_periods,id',
            'employee_id'         => 'required|integer|exists:employees,id',
            'adjustment_type'     => 'required|in:earning,deduction,correction,backpay,refund',
            'adjustment_category' => 'required|string|max:100',
            'component'           => 'nullable|string|max:255',
            'amount'              => 'required|numeric|min:0.01|max:999999.99',
            'reason'              => 'required|string|max:200',
            'reference_number'    => 'nullable|string|max:100',
        ]);

        try {
            PayrollAdjustment::create([
                'payroll_period_id' => $validated['payroll_period_id'],
                'employee_id'       => $validated['employee_id'],
                'adjustment_type'   => $validated['adjustment_type'],
                'category'          => $validated['adjustment_category'], // map field name
                'component'         => $validated['component'] ?? null,
                'amount'            => $validated['amount'],
                'reason'            => $validated['reason'],
                'reference_number'  => $validated['reference_number'] ?? null,
                'status'            => 'pending',
                'submitted_at'      => now(),
                'created_by'        => auth()->id(),
            ]);

            return redirect()->back()->with('success', 'Payroll adjustment created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create payroll adjustment', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create adjustment: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified adjustment
     */
    public function show(int $id): Response
    {
        $adjustment = PayrollAdjustment::with(['payrollPeriod', 'employee.profile', 'approvedByUser', 'rejectedByUser'])
            ->findOrFail($id);

        return Inertia::render('Payroll/PayrollProcessing/Adjustments/Show', [
            'adjustment' => [
                'id'                  => $adjustment->id,
                'payroll_period_id'   => $adjustment->payroll_period_id,
                'employee_id'         => $adjustment->employee_id,
                'employee_name'       => $adjustment->employee && $adjustment->employee->profile ? (($adjustment->employee->profile->first_name ?? '') . ' ' . ($adjustment->employee->profile->last_name ?? '')) : 'N/A',
                'adjustment_type'     => $adjustment->adjustment_type,
                'adjustment_category' => $adjustment->category,
                'amount'              => (float) $adjustment->amount,
                'reason'              => $adjustment->reason,
                'status'              => $adjustment->status,
            ],
        ]);
    }

    /**
     * Update the specified adjustment
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'payroll_period_id'   => 'required|integer|exists:payroll_periods,id',
            'employee_id'         => 'required|integer|exists:employees,id',
            'adjustment_type'     => 'required|in:earning,deduction,correction,backpay,refund',
            'adjustment_category' => 'required|string|max:100',
            'component'           => 'nullable|string|max:255',
            'amount'              => 'required|numeric|min:0.01|max:999999.99',
            'reason'              => 'required|string|max:200',
            'reference_number'    => 'nullable|string|max:100',
        ]);

        try {
            $adjustment = PayrollAdjustment::findOrFail($id);

            if ($adjustment->status !== 'pending') {
                return redirect()->back()
                    ->with('error', 'Only pending adjustments can be edited.');
            }

            $adjustment->update([
                'payroll_period_id' => $validated['payroll_period_id'],
                'employee_id'       => $validated['employee_id'],
                'adjustment_type'   => $validated['adjustment_type'],
                'category'          => $validated['adjustment_category'], // map field name
                'component'         => $validated['component'] ?? null,
                'amount'            => $validated['amount'],
                'reason'            => $validated['reason'],
                'reference_number'  => $validated['reference_number'] ?? null,
            ]);

            return redirect()->back()->with('success', 'Payroll adjustment updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update payroll adjustment', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update adjustment: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified adjustment
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $adjustment = PayrollAdjustment::findOrFail($id);

            if (!in_array($adjustment->status, ['pending', 'rejected'])) {
                return redirect()->back()
                    ->with('error', 'Only pending or rejected adjustments can be deleted.');
            }

            $adjustment->delete();

            return redirect()->back()->with('success', 'Payroll adjustment deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete payroll adjustment', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to delete adjustment: ' . $e->getMessage());
        }
    }

    /**
     * Approve the specified adjustment
     */
    public function approve(int $id): RedirectResponse
    {
        try {
            $adjustment = PayrollAdjustment::findOrFail($id);

            if ($adjustment->status !== 'pending') {
                return redirect()->back()
                    ->with('error', 'Adjustment is not in pending status.');
            }

            $adjustment->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Payroll adjustment approved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to approve payroll adjustment', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Reject the specified adjustment
     */
    public function reject(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_notes' => 'required|string|max:500',
        ]);

        try {
            $adjustment = PayrollAdjustment::findOrFail($id);

            if ($adjustment->status !== 'pending') {
                return redirect()->back()
                    ->with('error', 'Adjustment is not in pending status.');
            }

            $adjustment->update([
                'status'           => 'rejected',
                'rejected_by'      => auth()->id(),
                'rejected_at'      => now(),
                'rejection_reason' => $validated['rejection_notes'],
                'review_notes'     => $validated['rejection_notes'],
            ]);

            return redirect()->back()->with('success', 'Payroll adjustment rejected');
        } catch (\Exception $e) {
            Log::error('Failed to reject payroll adjustment', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display adjustment history for a specific employee
     */
    public function history(Request $request, int $employeeId): Response
    {
        // Get employee
        $employee = Employee::with(['profile', 'department', 'position'])->findOrFail($employeeId);

        // Get available periods
        $availablePeriods = PayrollPeriod::orderByDesc('period_start')
            ->get(['id', 'period_number', 'period_start', 'period_end'])
            ->map(fn($p) => [
                'id'   => $p->id,
                'name' => $p->period_number . ' (' . $p->period_start?->format('M d') . '–' . $p->period_end?->format('M d, Y') . ')',
            ])
            ->values()
            ->all();

        // Build query for adjustments
        $query = PayrollAdjustment::with(['payrollPeriod', 'approvedByUser', 'rejectedByUser'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('created_at');

        // Apply filters
        if ($request->filled('period_id')) {
            $query->byPeriod($request->input('period_id'));
        }

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }

        // Get adjustments
        $adjustments = $query->get()->map(fn($adj) => $this->transformAdjustment($adj))->values()->all();

        // Calculate summary
        $allAdjustments = PayrollAdjustment::where('employee_id', $employeeId)->get();

        $summary = [
            'total_adjustments'     => $allAdjustments->count(),
            'pending_adjustments'   => $allAdjustments->where('status', 'pending')->count(),
            'approved_adjustments'  => $allAdjustments->where('status', 'approved')->count(),
            'rejected_adjustments'  => $allAdjustments->where('status', 'rejected')->count(),
            'total_pending_amount'  => $allAdjustments->where('status', 'pending')->sum('amount'),
        ];

        return Inertia::render('Payroll/PayrollProcessing/Adjustments/History', [
            'employee_id'          => $employee->id,
            'employee_name'        => ($employee->profile->first_name ?? '') . ' ' . ($employee->profile->last_name ?? ''),
            'employee_number'      => $employee->employee_number,
            'department'           => $employee->department->name ?? 'N/A',
            'position'             => $employee->position->title ?? 'N/A',
            'adjustments'          => $adjustments,
            'summary'              => $summary,
            'available_periods'    => $availablePeriods,
            'available_statuses'   => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
                ['value' => 'applied', 'label' => 'Applied'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
        ]);
    }

    /**
     * Transform PayrollAdjustment model to array for frontend
     */
    private function transformAdjustment(PayrollAdjustment $adj): array
    {
        $reviewedBy = $adj->approvedBy?->name ?? $adj->rejectedBy?->name;
        $reviewedAt = $adj->approved_at ?? $adj->rejected_at;

        return [
            'id'                  => $adj->id,
            'payroll_period_id'   => $adj->payroll_period_id,
            'payroll_period'      => [
                'id'           => $adj->payrollPeriod->id,
                'name'         => $adj->payrollPeriod->period_number . ' (' . $adj->payrollPeriod->period_start?->format('M d') . '–' . $adj->payrollPeriod->period_end?->format('M d, Y') . ')',
                'start_date'   => $adj->payrollPeriod->period_start?->toDateString(),
                'end_date'     => $adj->payrollPeriod->period_end?->toDateString(),
                'payment_date' => $adj->payrollPeriod->payment_date?->toDateString(),
                'status'       => $adj->payrollPeriod->status,
            ],
            'employee_id'         => $adj->employee_id,
            'employee_name'       => $adj->employee?->profile?->full_name
                                        ?? $adj->employee?->user?->name ?? 'Unknown',
            'employee_number'     => $adj->employee?->employee_number ?? '',
            'department'          => $adj->employee?->department?->name ?? '',
            'position'            => $adj->employee?->position?->title ?? '',
            'adjustment_type'     => $adj->adjustment_type,
            'adjustment_category' => $adj->category, // DB col 'category' → frontend 'adjustment_category'
            'amount'              => (float) $adj->amount,
            'reason'              => $adj->reason,
            'reference_number'    => $adj->reference_number,
            'status'              => $adj->status,
            'requested_by'        => $adj->createdBy?->name ?? 'System',
            'requested_at'        => ($adj->submitted_at ?? $adj->created_at)?->toIso8601String(),
            'reviewed_by'         => $reviewedBy,
            'reviewed_at'         => $reviewedAt?->toIso8601String(),
            'review_notes'        => $adj->review_notes ?? $adj->rejection_reason,
            'applied_at'          => $adj->applied_at?->toIso8601String(),
            'created_at'          => $adj->created_at->toIso8601String(),
            'updated_at'          => $adj->updated_at->toIso8601String(),
        ];
    }
}
