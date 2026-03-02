<?php

namespace App\Http\Controllers\Payroll\EmployeePayroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\SalaryComponent;
use App\Models\Department;
use App\Services\Payroll\AllowanceDeductionService;
use App\Services\Payroll\SalaryComponentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AllowancesDeductionsController extends Controller
{
    public function __construct(
        private AllowanceDeductionService $allowanceDeductionService,
        private SalaryComponentService $salaryComponentService,
    ) {}

    /**
     * Display a listing of employee allowances and deductions.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $search = $request->input('search');
        $departmentId = $request->input('department_id');
        $status = $request->input('status', 'active');
        $componentType = $request->input('component_type');

        $employeeQuery = Employee::with([
            'payrollInfo',
            'allowances' => fn($q) => $q->where('is_active', true),
            'deductions' => fn($q) => $q->where('is_active', true),
            'user',
            'department',
        ])->where('status', 'active');

        if ($search) {
            $employeeQuery->where(function ($q) use ($search) {
                $q->whereHas('user', fn($subQ) => 
                    $subQ->where('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%")
                )
                ->orWhere('employee_number', 'like', "%$search%");
            });
        }

        if ($departmentId) {
            $employeeQuery->where('department_id', $departmentId);
        }

        $employees = $employeeQuery->get();

        $employeeData = $employees->map(function ($employee) use ($componentType) {
            $allowances = $employee->allowances->map(fn($a) => [
                'id' => $a->id,
                'employee_id' => $a->employee_id,
                'salary_component_id' => $a->salary_component_id,
                'component_name' => $a->salaryComponent->name ?? 'N/A',
                'component_code' => $a->salaryComponent->code ?? 'N/A',
                'component_type' => 'allowance',
                'amount' => $a->amount,
                'percentage' => $a->percentage,
                'units' => $a->units,
                'frequency' => $a->frequency,
                'effective_date' => $a->effective_date,
                'end_date' => $a->end_date,
                'is_active' => $a->is_active,
                'is_prorated' => $a->is_prorated,
                'requires_attendance' => $a->requires_attendance,
            ]);

            $deductions = $employee->deductions->map(fn($d) => [
                'id' => $d->id,
                'employee_id' => $d->employee_id,
                'salary_component_id' => $d->salary_component_id,
                'component_name' => $d->salaryComponent->name ?? 'N/A',
                'component_code' => $d->salaryComponent->code ?? 'N/A',
                'component_type' => 'deduction',
                'amount' => $d->amount,
                'percentage' => $d->percentage,
                'units' => $d->units,
                'frequency' => $d->frequency,
                'effective_date' => $d->effective_date,
                'end_date' => $d->end_date,
                'is_active' => $d->is_active,
                'is_prorated' => $d->is_prorated,
                'requires_attendance' => $d->requires_attendance,
            ]);

            $components = collect($allowances)->concat($deductions);
            if ($componentType) {
                $components = $components->filter(fn($c) => $c['component_type'] === $componentType);
            }

            $totalAllowances = $allowances->sum('amount') ?? 0;
            $totalDeductions = $deductions->sum('amount') ?? 0;

            return [
                'id' => $employee->id,
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'first_name' => $employee->user->first_name,
                'last_name' => $employee->user->last_name,
                'department' => $employee->department->name ?? 'N/A',
                'department_id' => $employee->department_id,
                'position' => $employee->position->name ?? 'N/A',
                'components' => $components->values()->all(),
                'total_allowances' => $totalAllowances,
                'total_deductions' => $totalDeductions,
            ];
        });

        if ($status === 'active') {
            $employeeData = $employeeData->filter(fn($e) => count($e['components']) > 0);
        }

        $components = $this->salaryComponentService->getComponentsGroupedByType(true);
        $componentsList = collect([]);
        foreach ($components as $type => $typeComponents) {
            foreach ($typeComponents as $component) {
                $componentsList->push([
                    'id' => $component->id,
                    'code' => $component->code,
                    'name' => $component->name,
                    'component_type' => $component->component_type,
                    'category' => $component->category,
                    'default_amount' => $component->default_amount,
                ]);
            }
        }

        $departments = Department::where('status', 'active')->get(['id', 'code', 'name'])->values();

        $componentTypes = [
            ['id' => 'allowance', 'name' => 'Allowance'],
            ['id' => 'deduction', 'name' => 'Deduction'],
            ['id' => 'tax', 'name' => 'Tax'],
            ['id' => 'contribution', 'name' => 'Contribution'],
        ];

        return Inertia::render('Payroll/EmployeePayroll/AllowancesDeductions/Index', [
            'employeeComponents' => $employeeData->values(),
            'components' => $componentsList->values(),
            'departments' => $departments,
            'componentTypes' => $componentTypes,
            'filters' => [
                'search' => $search ?? '',
                'department_id' => $departmentId ?? null,
                'status' => $status,
                'component_type' => $componentType ?? null,
            ],
        ]);
    }

    /**
     * Display the bulk assign page.
     */
    public function bulkAssignPage()
    {
        $this->authorize('create', Employee::class);

        $employees = Employee::with('user', 'department', 'position')
            ->where('status', 'active')
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'first_name' => $emp->user->first_name,
                'last_name' => $emp->user->last_name,
                'department' => $emp->department->name ?? 'N/A',
                'position' => $emp->position->name ?? 'N/A',
            ]);

        $components = $this->salaryComponentService->getComponentsGroupedByType(true);
        $componentsList = collect([]);
        foreach ($components as $type => $typeComponents) {
            foreach ($typeComponents as $component) {
                $componentsList->push([
                    'id' => $component->id,
                    'code' => $component->code,
                    'name' => $component->name,
                    'component_type' => $component->component_type,
                    'category' => $component->category,
                    'default_amount' => $component->default_amount,
                ]);
            }
        }

        return Inertia::render('Payroll/EmployeePayroll/AllowancesDeductions/BulkAssign', [
            'employees' => $employees,
            'components' => $componentsList->values(),
        ]);
    }

    /**
     * Store a newly assigned component.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'salary_component_id' => 'required|integer|exists:salary_components,id',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'units' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:per_payroll,monthly,quarterly,semi_annual,annually,one_time',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
            'is_prorated' => 'boolean',
            'requires_attendance' => 'boolean',
        ]);

        try {
            $employee = Employee::findOrFail($validated['employee_id']);
            $component = SalaryComponent::findOrFail($validated['salary_component_id']);

            if ($component->component_type === 'allowance') {
                $assignment = $this->allowanceDeductionService->addAllowance(
                    $employee,
                    $component->code,
                    $validated,
                    auth()->user()
                );
            } else {
                $assignment = $this->allowanceDeductionService->addDeduction(
                    $employee,
                    $component->code,
                    $validated,
                    auth()->user()
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Component assignment created successfully',
                'data' => [
                    'id' => $assignment->id,
                    'employee_id' => $assignment->employee_id,
                    'salary_component_id' => $assignment->salary_component_id,
                    'amount' => $assignment->amount,
                    'percentage' => $assignment->percentage,
                    'frequency' => $assignment->frequency,
                    'effective_date' => $assignment->effective_date,
                    'end_date' => $assignment->end_date,
                    'is_active' => $assignment->is_active,
                    'created_at' => $assignment->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assignment: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a component assignment.
     */
    public function update(Request $request, int $id)
    {
        $this->authorize('update', Employee::class);

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'units' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:per_payroll,monthly,quarterly,semi_annual,annually,one_time',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
            'is_prorated' => 'boolean',
            'requires_attendance' => 'boolean',
        ]);

        try {
            $allowance = EmployeeAllowance::find($id);
            if ($allowance) {
                $allowance->update($validated);
                return response()->json(['success' => true, 'message' => 'Allowance updated successfully']);
            }

            $deduction = EmployeeDeduction::find($id);
            if ($deduction) {
                $deduction->update($validated);
                return response()->json(['success' => true, 'message' => 'Deduction updated successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a component assignment.
     */
    public function destroy(int $id)
    {
        $this->authorize('delete', Employee::class);

        try {
            $allowance = EmployeeAllowance::find($id);
            if ($allowance) {
                $this->allowanceDeductionService->removeAllowance($allowance, auth()->user());
                return response()->json(['success' => true, 'message' => 'Allowance deleted successfully']);
            }

            $deduction = EmployeeDeduction::find($id);
            if ($deduction) {
                $this->allowanceDeductionService->removeDeduction($deduction, auth()->user());
                return response()->json(['success' => true, 'message' => 'Deduction deleted successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get component history for an employee.
     */
    public function history(Request $request, int $employeeId)
    {
        $this->authorize('viewAny', Employee::class);

        $componentId = $request->input('component_id');

        try {
            Employee::findOrFail($employeeId);

            $allowanceHistory = EmployeeAllowance::where('employee_id', $employeeId)
                ->with('salaryComponent')
                ->when($componentId, fn($q) => $q->where('salary_component_id', $componentId))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'component_name' => $a->salaryComponent->name,
                    'amount' => $a->amount,
                    'frequency' => $a->frequency,
                    'effective_date' => $a->effective_date,
                    'end_date' => $a->end_date,
                    'status' => $a->is_active ? 'active' : 'inactive',
                    'changed_at' => $a->updated_at ?? $a->created_at,
                    'type' => 'allowance',
                ]);

            $deductionHistory = EmployeeDeduction::where('employee_id', $employeeId)
                ->with('salaryComponent')
                ->when($componentId, fn($q) => $q->where('salary_component_id', $componentId))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($d) => [
                    'id' => $d->id,
                    'component_name' => $d->salaryComponent->name,
                    'amount' => $d->amount,
                    'frequency' => $d->frequency,
                    'effective_date' => $d->effective_date,
                    'end_date' => $d->end_date,
                    'status' => $d->is_active ? 'active' : 'inactive',
                    'changed_at' => $d->updated_at ?? $d->created_at,
                    'type' => 'deduction',
                ]);

            $history = $allowanceHistory->concat($deductionHistory)
                ->sortByDesc('changed_at')
                ->values();

            return response()->json(['success' => true, 'data' => $history]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Bulk assign components to multiple employees.
     */
    public function bulkAssign(Request $request)
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'salary_component_id' => 'required|integer|exists:salary_components,id',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'frequency' => 'required|in:per_payroll,monthly,quarterly,semi_annual,annually,one_time',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
        ]);

        try {
            $component = SalaryComponent::findOrFail($validated['salary_component_id']);
            $assignmentData = [
                'salary_component_id' => $validated['salary_component_id'],
                'amount' => $validated['amount'] ?? null,
                'percentage' => $validated['percentage'] ?? null,
                'frequency' => $validated['frequency'],
                'effective_date' => $validated['effective_date'],
                'end_date' => $validated['end_date'] ?? null,
                'is_active' => true,
            ];

            $assignedCount = 0;
            $errors = [];

            foreach ($validated['employee_ids'] as $employeeId) {
                try {
                    $employee = Employee::findOrFail($employeeId);
                    
                    if ($component->component_type === 'allowance') {
                        $this->allowanceDeductionService->addAllowance(
                            $employee,
                            $component->code,
                            $assignmentData,
                            auth()->user()
                        );
                    } else {
                        $this->allowanceDeductionService->addDeduction(
                            $employee,
                            $component->code,
                            $assignmentData,
                            auth()->user()
                        );
                    }
                    $assignedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Employee $employeeId: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => sprintf('%d components assigned successfully', $assignedCount),
                'assigned_count' => $assignedCount,
                'errors' => $errors,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
