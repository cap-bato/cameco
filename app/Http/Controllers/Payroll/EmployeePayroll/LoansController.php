<?php

namespace App\Http\Controllers\Payroll\EmployeePayroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Department;
use App\Services\Payroll\LoanManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $this->authorize('viewAny', EmployeeLoan::class);
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
                'employee_name' => $loan->employee?->user?->full_name ?? 'N/A',
                'employee_number' => $loan->employee?->employee_number ?? 'N/A',
                'department_id' => $loan->employee?->department_id,
                'department_name' => $loan->employee?->department?->name ?? 'N/A',
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
                'approved_by' => $loan->approvedBy?->name ?? 'N/A',
                'approved_at' => $loan->approved_at,
                'created_by' => $loan->createdBy?->name ?? 'System',
                'created_at' => $loan->created_at,
                'updated_by' => $loan->updated_by,
                'updated_at' => $loan->updated_at,
            ];
        });

        $employees = Employee::with('user', 'department')
            ->where('status', 'active')
            ->get(['id', 'employee_number', 'department_id', 'user_id'])
            ->filter(fn($emp) => $emp->user !== null)
            ->map(fn($emp) => [
                'id' => $emp->id,
                'name' => $emp->user?->full_name ?? $emp->employee_number,
                'employee_number' => $emp->employee_number,
                'department' => $emp->department?->name ?? 'N/A',
            ]);

        $departments = Department::where('status', 'active')->get(['id', 'name'])->values();

        return Inertia::render('Payroll/EmployeePayroll/Loans/Index', [
            'loans' => $loans->values()->toArray(),
            'employees' => $employees->values()->toArray(),
            'departments' => $departments->toArray(),
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
        try {
            $loan = EmployeeLoan::findOrFail($id);
            $loan->update(['status' => 'cancelled']);

            return response()->json(['success' => true, 'message' => 'Loan cancelled successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete/Destroy a loan
     */
    public function destroy(int $id)
    {
        try {
            $loan = EmployeeLoan::with('employee')->findOrFail($id);

            // Check if loan is active - only allow deletion of non-active loans
            if ($loan->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete active loan. Please cancel first.',
                ], 422);
            }

            DB::transaction(function () use ($loan) {
                // Delete associated deductions
                $loan->loanDeductions()->delete();

                // Soft delete the loan
                $loan->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Loan deleted successfully',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to delete loan', ['loan_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'An error occurred while deleting the loan. Please contact support if the issue persists.'], 500);
        }
    }

    /**
     * Get loan payment/deduction history
     */
    public function getPayments(int $id)
    {
        try {
            $loan = EmployeeLoan::with(['loanDeductions' => fn($q) => $q->orderBy('installment_number', 'asc')])
                ->findOrFail($id);

            $payments = $loan->loanDeductions->map(function ($deduction) {
                return [
                    'id' => $deduction->id,
                    'installment_number' => $deduction->installment_number,
                    'due_date' => $deduction->due_date,
                    'principal_deduction' => (float)$deduction->principal_deduction,
                    'interest_deduction' => (float)$deduction->interest_deduction,
                    'total_deduction' => (float)$deduction->total_deduction,
                    'penalty_amount' => (float)$deduction->penalty_amount,
                    'amount_deducted' => (float)$deduction->amount_deducted,
                    'amount_paid' => (float)$deduction->amount_paid,
                    'balance_after_payment' => (float)$deduction->balance_after_payment,
                    'status' => $deduction->status,
                    'is_paid' => $deduction->status === 'paid' || $deduction->amount_paid > 0,
                    'paid_date' => $deduction->paid_date,
                    'deducted_at' => $deduction->deducted_at,
                    'reference_number' => $deduction->reference_number,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'loan_type' => $loan->loan_type,
                    'total_installments' => $loan->number_of_installments,
                    'installments_paid' => $loan->installments_paid,
                    'principal_amount' => (float)$loan->principal_amount,
                    'total_amount' => (float)($loan->total_loan_amount ?? ($loan->principal_amount + ($loan->principal_amount * ($loan->interest_rate / 100)))),
                    'monthly_amortization' => (float)$loan->installment_amount,
                    'remaining_balance' => (float)($loan->remaining_balance ?? max(0, (float)($loan->principal_amount - ($loan->installments_paid * $loan->installment_amount)))),
                    'payments' => $payments,
                ],
                'message' => 'Payment history retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve loan payment history', ['loan_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'An error occurred while retrieving payment history. Please contact support if the issue persists.'], 500);
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
