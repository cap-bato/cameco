<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreAdvanceRequest;
use App\Http\Requests\Payroll\ApproveAdvanceRequest;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Services\Payroll\AdvanceManagementService;
use App\Services\Payroll\AdvanceDeductionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class AdvancesController extends Controller
{
    public function __construct(
        private AdvanceManagementService $advanceService,
        private AdvanceDeductionService $deductionService
    ) {}

    /**
     * Display a listing of cash advances
     */
    public function index(Request $request)
    {
        try {
            $query = CashAdvance::with(['employee', 'department', 'approvedBy', 'createdBy', 'advanceDeductions'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('employee_number', 'like', "%{$search}%");
                });
            }

            if ($request->has('status') && !empty($request->status)) {
                $status = $request->status;
                if (in_array($status, ['pending', 'approved', 'rejected'])) {
                    $query->where('approval_status', $status);
                } elseif (in_array($status, ['active', 'completed', 'cancelled'])) {
                    $query->where('deduction_status', $status);
                }
            }

            if ($request->has('department') && !empty($request->department)) {
                $query->where('department_id', $request->department);
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->where('requested_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->where('requested_date', '<=', $request->date_to);
            }

            $advances = $query->paginate(20)->withQueryString();

            // Transform for frontend
            $advancesData = $advances->through(function ($advance) {
                return [
                    'id' => $advance->id,
                    'advance_number' => $advance->advance_number,
                    'employee_id' => $advance->employee_id,
                    'employee_name' => $advance->employee?->full_name ?? 'N/A',
                    'employee_number' => $advance->employee?->employee_number ?? 'N/A',
                    'department_id' => $advance->department_id,
                    'department_name' => $advance->department?->name ?? 'N/A',
                    'advance_type' => $advance->advance_type,
                    'amount_requested' => (float) $advance->amount_requested,
                    'amount_approved' => (float) ($advance->amount_approved ?? 0),
                    'approval_status' => $advance->approval_status,
                    'approval_status_label' => ucfirst(str_replace('_', ' ', $advance->approval_status)),
                    'approval_status_color' => $this->getStatusColor($advance->approval_status),
                    'approved_by' => $advance->approvedBy?->name ?? null,
                    'approved_at' => $advance->approved_at?->toDateTimeString(),
                    'approval_notes' => $advance->approval_notes,
                    'deduction_status' => $advance->deduction_status,
                    'deduction_status_label' => ucfirst(str_replace('_', ' ', $advance->deduction_status)),
                    'remaining_balance' => (float) ($advance->remaining_balance ?? 0),
                    'deduction_schedule' => $advance->deduction_schedule,
                    'number_of_installments' => $advance->number_of_installments ?? 0,
                    'installments_completed' => $advance->installments_completed ?? 0,
                    'requested_date' => $advance->requested_date?->toDateString(),
                    'purpose' => $advance->purpose,
                    'priority_level' => $advance->priority_level,
                    'created_at' => $advance->created_at?->toDateTimeString(),
                    'created_by' => $advance->createdBy?->name ?? 'N/A',
                ];
            });

            $employees = Employee::select('id', 'full_name as name', 'employee_number', 'department_id')
                ->with('department:id,name')
                ->where('employment_status', 'active')
                ->get()
                ->map(function ($emp) {
                    return [
                        'id' => $emp->id,
                        'name' => $emp->name,
                        'employee_number' => $emp->employee_number,
                        'department' => $emp->department?->name ?? 'N/A',
                    ];
                });

            return Inertia::render('Payroll/Advances/Index', [
                'advances' => $advancesData,
                'filters' => $request->only(['search', 'status', 'department', 'date_from', 'date_to']),
                'employees' => $employees,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading advances', ['error' => $e->getMessage()]);
            return Inertia::render('Payroll/Advances/Index', [
                'advances' => [],
                'filters' => [],
                'employees' => [],
                'error' => 'Failed to load advances. Please try again.',
            ]);
        }
    }

    /**
     * Show the form for creating a new cash advance request
     */
    public function create()
    {
        try {
            $employees = Employee::select('id', 'full_name as name', 'employee_number', 'department_id')
                ->with('department:id,name')
                ->where('employment_status', 'active')
                ->get()
                ->map(function ($emp) {
                    return [
                        'id' => $emp->id,
                        'name' => $emp->name,
                        'employee_number' => $emp->employee_number,
                        'department' => $emp->department?->name ?? 'N/A',
                    ];
                });

            return response()->json([
                'employees' => $employees,
                'advance_types' => [
                    ['value' => 'cash_advance', 'label' => 'Cash Advance'],
                    ['value' => 'medical_advance', 'label' => 'Medical Advance'],
                    ['value' => 'travel_advance', 'label' => 'Travel Advance'],
                    ['value' => 'equipment_advance', 'label' => 'Equipment Advance'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading create form data', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load form data'], 500);
        }
    }

    /**
     * Store a newly created cash advance in storage
     */
    public function store(StoreAdvanceRequest $request)
    {
        $validated = $request->validated();

        try {
            $advance = $this->advanceService->createAdvanceRequest($validated, $request->user());

            return redirect()
                ->route('payroll.advances.index')
                ->with('success', "Advance request {$advance->advance_number} created successfully");
        } catch (\Exception $e) {
            Log::error('Error creating advance', ['error' => $e->getMessage()]);
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Approve a pending cash advance
     */
    /**
     * Approve a pending cash advance
     */
    public function approve(ApproveAdvanceRequest $request, int $id)
    {
        try {
            $advance = CashAdvance::findOrFail($id);

            if ($advance->approval_status !== 'pending') {
                return redirect()
                    ->back()
                    ->withErrors(['error' => 'Only pending advances can be approved.']);
            }

            $validated = $request->validated();

            $this->advanceService->approveAdvance($advance, $validated, $request->user());

            return redirect()
                ->route('payroll.advances.index')
                ->with('success', "Advance {$advance->advance_number} approved successfully");
        } catch (\Exception $e) {
            Log::error('Error approving advance', ['error' => $e->getMessage(), 'advance_id' => $id]);
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reject a pending cash advance
     */
    public function reject(Request $request, int $id)
    {
        try {
            $advance = CashAdvance::findOrFail($id);

            if ($advance->approval_status !== 'pending') {
                return redirect()
                    ->back()
                    ->withErrors(['error' => 'Only pending advances can be rejected.']);
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string|min:10|max:500',
            ]);

            $this->advanceService->rejectAdvance($advance, $validated['rejection_reason'], $request->user());

            return redirect()
                ->route('payroll.advances.index')
                ->with('success', "Advance {$advance->advance_number} rejected");
        } catch (\Exception $e) {
            Log::error('Error rejecting advance', ['error' => $e->getMessage(), 'advance_id' => $id]);
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel an active advance
     */
    public function cancel(Request $request, int $id)
    {
        try {
            $advance = CashAdvance::findOrFail($id);

            $validated = $request->validate([
                'cancellation_reason' => 'required|string|min:10|max:500',
            ]);

            $this->advanceService->cancelAdvance($advance, $validated['cancellation_reason'], $request->user());

            return redirect()
                ->route('payroll.advances.index')
                ->with('success', "Advance {$advance->advance_number} cancelled");
        } catch (\Exception $e) {
            Log::error('Error cancelling advance', ['error' => $e->getMessage(), 'advance_id' => $id]);
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get status color for UI badges
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'yellow',
            'approved' => 'blue',
            'rejected' => 'red',
            'active' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
