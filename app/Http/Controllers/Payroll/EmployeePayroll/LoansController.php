<?php

namespace App\Http\Controllers\Payroll\EmployeePayroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Department;
use App\Services\Payroll\LoanManagementService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoansController extends Controller
{
    public function __construct(
        private LoanManagementService $loanManagementService,
    ) {}

    /**
     * Display a listing of employee loans
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $search = $request->input('search');
        $employeeId = $request->input('employee_id');
        $departmentId = $request->input('department_id');
        $loanType = $request->input('loan_type');
        $status = $request->input('status');

        $loansQuery = EmployeeLoan::with(['employee.user', 'employee.department', 'createdBy']);

        if ($search) {
            $loansQuery->whereHas('employee.user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%");
            })
            ->orWhereHas('employee', function ($q) use ($search) {
                $q->where('employee_number', 'like', "%$search%");
            });
        }

        if ($employeeId) {
            $loansQuery->where('employee_id', $employeeId);
        }

        if ($departmentId) {
            $loansQuery->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }

        if ($loanType) {
            $loansQuery->where('loan_type', $loanType);
        }

        if ($status) {
            $loansQuery->where('status', $status);
        }

        $loans = $loansQuery->get()->map(function ($loan) {
            $remainingBalance = $loan->principal_amount - ($loan->installments_paid * $loan->monthly_amortization);
            
            return [
                'id' => $loan->id,
                'employee_id' => $loan->employee_id,
                'employee_name' => $loan->employee->user->full_name,
                'employee_number' => $loan->employee->employee_number,
                'department_id' => $loan->employee->department_id,
                'department_name' => $loan->employee->department->name ?? 'N/A',
                'loan_type' => $loan->loan_type,
                'loan_type_label' => $this->getLoanTypeLabel($loan->loan_type),
                'loan_type_color' => $this->getLoanTypeColor($loan->loan_type),
                'loan_number' => $loan->loan_number,
                'principal_amount' => (float)$loan->principal_amount,
                'interest_rate' => (float)$loan->interest_rate,
                'total_amount' => (float)$loan->principal_amount + ((float)$loan->principal_amount * ((float)$loan->interest_rate / 100)),
                'monthly_amortization' => (float)$loan->monthly_amortization,
                'number_of_installments' => $loan->number_of_installments,
                'installments_paid' => $loan->installments_paid,
                'remaining_balance' => (float)max(0, $remainingBalance),
                'loan_date' => $loan->loan_date,
                'start_date' => $loan->start_date,
                'maturity_date' => $loan->maturity_date,
                'status' => $loan->status,
                'status_label' => ucfirst(str_replace('_', ' ', $loan->status)),
                'status_color' => $this->getStatusColor($loan->status),
                'is_active' => $loan->status === 'active',
                'approved_by' => $loan->approvedBy->name ?? 'N/A',
                'approved_at' => $loan->approved_at,
                'created_by' => $loan->createdBy->name ?? 'System',
                'created_at' => $loan->created_at,
                'updated_by' => $loan->updated_by,
                'updated_at' => $loan->updated_at,
            ];
        });

        $employees = Employee::with('user', 'department')
            ->where('status', 'active')
            ->get(['id', 'employee_number', 'department_id', 'user_id'])
            ->map(fn($emp) => [
                'id' => $emp->id,
                'name' => $emp->user->full_name,
                'employee_number' => $emp->employee_number,
                'department' => $emp->department->name ?? 'N/A',
            ]);

        $departments = Department::where('status', 'active')->get(['id', 'name'])->values();

        return Inertia::render('Payroll/EmployeePayroll/Loans/Index', [
            'loans' => $loans,
            'employees' => $employees,
            'departments' => $departments,
            'filters' => [
                'search' => $search ?? '',
                'employee_id' => $employeeId ?? null,
                'department_id' => $departmentId ?? null,
                'loan_type' => $loanType ?? null,
                'status' => $status ?? null,
            ],
            'loanTypes' => [
                ['id' => 'sss', 'name' => 'SSS Loan'],
                ['id' => 'pagibig', 'name' => 'Pag-IBIG Loan'],
                ['id' => 'company', 'name' => 'Company Loan'],
            ],
            'statuses' => [
                ['id' => 'active', 'name' => 'Active'],
                ['id' => 'completed', 'name' => 'Completed'],
                ['id' => 'cancelled', 'name' => 'Cancelled'],
                ['id' => 'restructured', 'name' => 'Restructured'],
            ],
        ]);
    }

    /**
     * Show a single loan details
     */
    public function show(int $id)
    {
        $this->authorize('view', Employee::class);

        try {
            $loan = EmployeeLoan::with(['employee.user', 'employee.department', 'createdBy', 'approvedBy'])
                ->findOrFail($id);

            $details = $this->loanManagementService->getLoanDetails($loan);
            $history = $this->loanManagementService->getLoanDeductionHistory($loan);

            $remainingBalance = $loan->principal_amount - ($loan->installments_paid * $loan->monthly_amortization);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $loan->id,
                    'employee_id' => $loan->employee_id,
                    'employee_name' => $loan->employee->user->full_name,
                    'employee_number' => $loan->employee->employee_number,
                    'department' => $loan->employee->department->name,
                    'loan_type' => $loan->loan_type,
                    'loan_number' => $loan->loan_number,
                    'principal_amount' => (float)$loan->principal_amount,
                    'interest_rate' => (float)$loan->interest_rate,
                    'monthly_amortization' => (float)$loan->monthly_amortization,
                    'number_of_installments' => $loan->number_of_installments,
                    'installments_paid' => $loan->installments_paid,
                    'remaining_balance' => (float)max(0, $remainingBalance),
                    'loan_date' => $loan->loan_date,
                    'start_date' => $loan->start_date,
                    'maturity_date' => $loan->maturity_date,
                    'status' => $loan->status,
                    'details' => $details,
                    'deduction_history' => $history,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Store a newly created loan
     */
    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'loan_type' => 'required|in:sss,pagibig,company',
            'principal_amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'number_of_installments' => 'required|integer|min:1',
            'monthly_amortization' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'approved_by' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $employee = Employee::findOrFail($validated['employee_id']);
            
            $loanData = [
                'loan_type' => $validated['loan_type'],
                'principal_amount' => $validated['principal_amount'],
                'interest_rate' => $validated['interest_rate'] ?? 0,
                'number_of_installments' => $validated['number_of_installments'],
                'monthly_amortization' => $validated['monthly_amortization'],
                'start_date' => $validated['start_date'],
            ];

            $loan = $this->loanManagementService->createLoan($employee, $loanData, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Loan created successfully',
                'data' => [
                    'id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'status' => $loan->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update loan details
     */
    public function update(Request $request, int $id)
    {
        $this->authorize('update', Employee::class);

        $validated = $request->validate([
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'monthly_amortization' => 'nullable|numeric|min:0',
        ]);

        try {
            $loan = EmployeeLoan::findOrFail($id);
            $loan->update($validated);

            return response()->json(['success' => true, 'message' => 'Loan updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Process early payment for a loan
     */
    public function earlyPayment(Request $request, int $id)
    {
        $this->authorize('update', Employee::class);

        $validated = $request->validate([
            'payment_amount' => 'required|numeric|min:0',
        ]);

        try {
            $loan = EmployeeLoan::findOrFail($id);
            $this->loanManagementService->makeEarlyPayment($loan, $validated['payment_amount'], auth()->user());

            return response()->json(['success' => true, 'message' => 'Early payment processed successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a loan
     */
    public function cancel(int $id)
    {
        $this->authorize('delete', Employee::class);

        try {
            $loan = EmployeeLoan::findOrFail($id);
            $loan->update(['status' => 'cancelled']);

            return response()->json(['success' => true, 'message' => 'Loan cancelled successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get loan type label
     */
    private function getLoanTypeLabel(string $type): string
    {
        return match ($type) {
            'sss' => 'SSS Loan',
            'pagibig' => 'Pag-IBIG Loan',
            'company' => 'Company Loan',
            default => $type,
        };
    }

    /**
     * Get loan type color for badge
     */
    private function getLoanTypeColor(string $type): string
    {
        return match ($type) {
            'sss' => 'bg-indigo-100 text-indigo-800',
            'pagibig' => 'bg-purple-100 text-purple-800',
            'company' => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get status color for badge
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'active' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'restructured' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
