<?php

namespace App\Http\Controllers\Payroll\EmployeePayroll;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayrollInfo;
use App\Models\Employee;
use App\Models\Department;
use App\Services\Payroll\EmployeePayrollInfoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeePayrollInfoController extends Controller
{
    public function __construct(
        private EmployeePayrollInfoService $payrollInfoService
    ) {}
    /**
     * Display a listing of employee payroll information.
     */
    public function index(Request $request)
    {
        $query = EmployeePayrollInfo::query()
            ->with(['employee.department', 'employee.position', 'createdBy', 'updatedBy'])
            ->where('is_active', true);

        // Apply search filter
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        // Apply salary type filter
        if ($request->has('salary_type') && $request->input('salary_type')) {
            $query->where('salary_type', $request->input('salary_type'));
        }

        // Apply payment method filter
        if ($request->has('payment_method') && $request->input('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        // Apply tax status filter
        if ($request->has('tax_status') && $request->input('tax_status')) {
            $query->where('tax_status', $request->input('tax_status'));
        }

        // Apply status filter
        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $employees = $query->paginate(50);

        // Transform the data for the frontend
        $employees->transform(function ($payrollInfo) {
            return [
                'id' => $payrollInfo->id,
                'employee_id' => $payrollInfo->employee_id,
                'employee_name' => $payrollInfo->employee?->full_name ?? 'N/A',
                'employee_number' => $payrollInfo->employee?->employee_number ?? 'N/A',
                'department' => $payrollInfo->employee?->department?->name ?? 'N/A',
                'position' => $payrollInfo->employee?->position?->name ?? 'N/A',
                'salary_type' => $payrollInfo->salary_type,
                'salary_type_label' => $this->getSalaryTypeLabel($payrollInfo->salary_type),
                'basic_salary' => $payrollInfo->basic_salary,
                'daily_rate' => $payrollInfo->daily_rate,
                'hourly_rate' => $payrollInfo->hourly_rate,
                'payment_method' => $payrollInfo->payment_method,
                'payment_method_label' => $this->getPaymentMethodLabel($payrollInfo->payment_method),
                'tax_status' => $payrollInfo->tax_status,
                'tax_status_label' => $this->getTaxStatusLabel($payrollInfo->tax_status),
                'rdo_code' => $payrollInfo->rdo_code,
                'withholding_tax_exemption' => $payrollInfo->withholding_tax_exemption,
                'is_tax_exempt' => $payrollInfo->is_tax_exempt,
                'is_substituted_filing' => $payrollInfo->is_substituted_filing,
                'sss_number' => $payrollInfo->sss_number,
                'philhealth_number' => $payrollInfo->philhealth_number,
                'pagibig_number' => $payrollInfo->pagibig_number,
                'tin_number' => $payrollInfo->tin_number,
                'sss_bracket' => $payrollInfo->sss_bracket,
                'is_sss_voluntary' => $payrollInfo->is_sss_voluntary,
                'philhealth_is_indigent' => $payrollInfo->philhealth_is_indigent,
                'pagibig_employee_rate' => $payrollInfo->pagibig_employee_rate,
                'bank_name' => $payrollInfo->bank_name,
                'bank_code' => $payrollInfo->bank_code,
                'bank_account_number' => $payrollInfo->bank_account_number,
                'bank_account_name' => $payrollInfo->bank_account_name,
                'is_entitled_to_rice' => $payrollInfo->is_entitled_to_rice,
                'is_entitled_to_uniform' => $payrollInfo->is_entitled_to_uniform,
                'is_entitled_to_laundry' => $payrollInfo->is_entitled_to_laundry,
                'is_entitled_to_medical' => $payrollInfo->is_entitled_to_medical,
                'is_active' => $payrollInfo->is_active,
                'status_label' => $payrollInfo->is_active ? 'Active' : 'Inactive',
                'effective_date' => $payrollInfo->effective_date,
                'end_date' => $payrollInfo->end_date,
                'created_at' => $payrollInfo->created_at,
                'updated_at' => $payrollInfo->updated_at,
            ];
        });

        // Get available options
        $departments = Department::where('is_active', true)->get(['id', 'name'])->toArray();

        return Inertia::render('Payroll/EmployeePayroll/Info/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'salary_type', 'payment_method', 'tax_status', 'status']),
            'available_salary_types' => $this->getSalaryTypes(),
            'available_payment_methods' => $this->getPaymentMethods(),
            'available_tax_statuses' => $this->getTaxStatuses(),
            'available_departments' => $departments,
        ]);
    }

    /**
     * Show the form for creating a new employee payroll info.
     */
    public function create()
    {
        $employees = Employee::where('is_active', true)
            ->doesntHave('payrollInfo')
            ->with('department', 'position')
            ->get(['id', 'employee_number', 'first_name', 'last_name', 'department_id'])
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'employee_number' => $emp->employee_number,
                    'full_name' => $emp->full_name,
                    'department' => $emp->department?->name,
                ];
            });

        return Inertia::render('Payroll/EmployeePayroll/Info/Create', [
            'available_employees' => $employees,
            'available_salary_types' => $this->getSalaryTypes(),
            'available_payment_methods' => $this->getPaymentMethods(),
            'available_tax_statuses' => $this->getTaxStatuses(),
        ]);
    }

    /**
     * Store a newly created employee payroll info in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id|unique:employee_payroll_info,employee_id,NULL,id,is_active,1',
            'salary_type' => 'required|in:monthly,daily,hourly,contractual,project_based',
            'basic_salary' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:bank_transfer,cash,check',
            'tax_status' => 'required|in:Z,S,ME,S1,ME1,S2,ME2,S3,ME3,S4,ME4',
            'rdo_code' => 'nullable|string|max:10',
            'withholding_tax_exemption' => 'nullable|numeric|min:0',
            'is_tax_exempt' => 'boolean',
            'is_substituted_filing' => 'boolean',
            'sss_number' => 'nullable|string|max:20',
            'philhealth_number' => 'nullable|string|max:20',
            'pagibig_number' => 'nullable|string|max:20',
            'tin_number' => 'nullable|string|max:20',
            'sss_bracket' => 'nullable|string|max:5',
            'is_sss_voluntary' => 'boolean',
            'philhealth_is_indigent' => 'boolean',
            'pagibig_employee_rate' => 'nullable|numeric|in:1.00,2.00',
            'bank_name' => 'nullable|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'bank_account_number' => 'nullable|string|max:20',
            'bank_account_name' => 'nullable|string|max:100',
            'is_entitled_to_rice' => 'boolean',
            'is_entitled_to_uniform' => 'boolean',
            'is_entitled_to_laundry' => 'boolean',
            'is_entitled_to_medical' => 'boolean',
            'effective_date' => 'required|date',
        ]);

        try {
            $payrollInfo = $this->payrollInfoService->createPayrollInfo(
                $validated,
                auth()->user()
            );

            return redirect()
                ->route('payroll.employee-payroll-info.show', $payrollInfo->id)
                ->with('success', 'Employee payroll information created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified employee payroll info.
     */
    public function show(EmployeePayrollInfo $payrollInfo)
    {
        $payrollInfo->load(['employee.department', 'employee.position', 'createdBy', 'updatedBy']);

        return Inertia::render('Payroll/EmployeePayroll/Info/Show', [
            'employee' => [
                'id' => $payrollInfo->id,
                'employee_id' => $payrollInfo->employee_id,
                'employee_name' => $payrollInfo->employee?->full_name ?? 'N/A',
                'employee_number' => $payrollInfo->employee?->employee_number ?? 'N/A',
                'department' => $payrollInfo->employee?->department?->name ?? 'N/A',
                'position' => $payrollInfo->employee?->position?->name ?? 'N/A',
                'salary_type' => $payrollInfo->salary_type,
                'salary_type_label' => $this->getSalaryTypeLabel($payrollInfo->salary_type),
                'basic_salary' => $payrollInfo->basic_salary,
                'daily_rate' => $payrollInfo->daily_rate,
                'hourly_rate' => $payrollInfo->hourly_rate,
                'payment_method' => $payrollInfo->payment_method,
                'payment_method_label' => $this->getPaymentMethodLabel($payrollInfo->payment_method),
                'tax_status' => $payrollInfo->tax_status,
                'tax_status_label' => $this->getTaxStatusLabel($payrollInfo->tax_status),
                'rdo_code' => $payrollInfo->rdo_code,
                'withholding_tax_exemption' => $payrollInfo->withholding_tax_exemption,
                'is_tax_exempt' => $payrollInfo->is_tax_exempt,
                'is_substituted_filing' => $payrollInfo->is_substituted_filing,
                'sss_number' => $payrollInfo->sss_number,
                'philhealth_number' => $payrollInfo->philhealth_number,
                'pagibig_number' => $payrollInfo->pagibig_number,
                'tin_number' => $payrollInfo->tin_number,
                'sss_bracket' => $payrollInfo->sss_bracket,
                'is_sss_voluntary' => $payrollInfo->is_sss_voluntary,
                'philhealth_is_indigent' => $payrollInfo->philhealth_is_indigent,
                'pagibig_employee_rate' => $payrollInfo->pagibig_employee_rate,
                'bank_name' => $payrollInfo->bank_name,
                'bank_code' => $payrollInfo->bank_code,
                'bank_account_number' => $payrollInfo->bank_account_number,
                'bank_account_name' => $payrollInfo->bank_account_name,
                'is_entitled_to_rice' => $payrollInfo->is_entitled_to_rice,
                'is_entitled_to_uniform' => $payrollInfo->is_entitled_to_uniform,
                'is_entitled_to_laundry' => $payrollInfo->is_entitled_to_laundry,
                'is_entitled_to_medical' => $payrollInfo->is_entitled_to_medical,
                'is_active' => $payrollInfo->is_active,
                'status_label' => $payrollInfo->is_active ? 'Active' : 'Inactive',
                'effective_date' => $payrollInfo->effective_date,
                'end_date' => $payrollInfo->end_date,
                'created_at' => $payrollInfo->created_at,
                'updated_at' => $payrollInfo->updated_at,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified employee payroll info.
     */
    public function edit(EmployeePayrollInfo $payrollInfo)
    {
        $payrollInfo->load(['employee.department', 'employee.position']);

        return Inertia::render('Payroll/EmployeePayroll/Info/Edit', [
            'employee' => [
                'id' => $payrollInfo->id,
                'employee_id' => $payrollInfo->employee_id,
                'employee_name' => $payrollInfo->employee?->full_name ?? 'N/A',
                'employee_number' => $payrollInfo->employee?->employee_number ?? 'N/A',
                'salary_type' => $payrollInfo->salary_type,
                'basic_salary' => $payrollInfo->basic_salary,
                'daily_rate' => $payrollInfo->daily_rate,
                'hourly_rate' => $payrollInfo->hourly_rate,
                'payment_method' => $payrollInfo->payment_method,
                'tax_status' => $payrollInfo->tax_status,
                'rdo_code' => $payrollInfo->rdo_code,
                'withholding_tax_exemption' => $payrollInfo->withholding_tax_exemption,
                'is_tax_exempt' => $payrollInfo->is_tax_exempt,
                'is_substituted_filing' => $payrollInfo->is_substituted_filing,
                'sss_number' => $payrollInfo->sss_number,
                'philhealth_number' => $payrollInfo->philhealth_number,
                'pagibig_number' => $payrollInfo->pagibig_number,
                'tin_number' => $payrollInfo->tin_number,
                'sss_bracket' => $payrollInfo->sss_bracket,
                'is_sss_voluntary' => $payrollInfo->is_sss_voluntary,
                'philhealth_is_indigent' => $payrollInfo->philhealth_is_indigent,
                'pagibig_employee_rate' => $payrollInfo->pagibig_employee_rate,
                'bank_name' => $payrollInfo->bank_name,
                'bank_code' => $payrollInfo->bank_code,
                'bank_account_number' => $payrollInfo->bank_account_number,
                'bank_account_name' => $payrollInfo->bank_account_name,
                'is_entitled_to_rice' => $payrollInfo->is_entitled_to_rice,
                'is_entitled_to_uniform' => $payrollInfo->is_entitled_to_uniform,
                'is_entitled_to_laundry' => $payrollInfo->is_entitled_to_laundry,
                'is_entitled_to_medical' => $payrollInfo->is_entitled_to_medical,
                'effective_date' => $payrollInfo->effective_date,
            ],
            'available_salary_types' => $this->getSalaryTypes(),
            'available_payment_methods' => $this->getPaymentMethods(),
            'available_tax_statuses' => $this->getTaxStatuses(),
        ]);
    }

    /**
     * Update the specified employee payroll info in storage.
     */
    public function update(Request $request, EmployeePayrollInfo $payrollInfo)
    {
        $validated = $request->validate([
            'salary_type' => 'required|in:monthly,daily,hourly,contractual,project_based',
            'basic_salary' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:bank_transfer,cash,check',
            'tax_status' => 'required|in:Z,S,ME,S1,ME1,S2,ME2,S3,ME3,S4,ME4',
            'rdo_code' => 'nullable|string|max:10',
            'withholding_tax_exemption' => 'nullable|numeric|min:0',
            'is_tax_exempt' => 'boolean',
            'is_substituted_filing' => 'boolean',
            'sss_number' => 'nullable|string|max:20',
            'philhealth_number' => 'nullable|string|max:20',
            'pagibig_number' => 'nullable|string|max:20',
            'tin_number' => 'nullable|string|max:20',
            'sss_bracket' => 'nullable|string|max:5',
            'is_sss_voluntary' => 'boolean',
            'philhealth_is_indigent' => 'boolean',
            'pagibig_employee_rate' => 'nullable|numeric|in:1.00,2.00',
            'bank_name' => 'nullable|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'bank_account_number' => 'nullable|string|max:20',
            'bank_account_name' => 'nullable|string|max:100',
            'is_entitled_to_rice' => 'boolean',
            'is_entitled_to_uniform' => 'boolean',
            'is_entitled_to_laundry' => 'boolean',
            'is_entitled_to_medical' => 'boolean',
        ]);

        try {
            $payrollInfo = $this->payrollInfoService->updatePayrollInfo(
                $payrollInfo,
                $validated,
                auth()->user()
            );

            return redirect()
                ->route('payroll.employee-payroll-info.show', $payrollInfo->id)
                ->with('success', 'Employee payroll information updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified employee payroll info from storage.
     */
    public function destroy(EmployeePayrollInfo $payrollInfo)
    {
        try {
            $payrollInfo->delete();

            return redirect()
                ->route('payroll.employee-payroll-info.index')
                ->with('success', 'Employee payroll information deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get available salary types
     */
    private function getSalaryTypes()
    {
        return [
            ['value' => 'monthly', 'label' => 'Monthly'],
            ['value' => 'daily', 'label' => 'Daily'],
            ['value' => 'hourly', 'label' => 'Hourly'],
            ['value' => 'contractual', 'label' => 'Contractual'],
            ['value' => 'project_based', 'label' => 'Project-Based'],
        ];
    }

    /**
     * Get available payment methods
     */
    private function getPaymentMethods()
    {
        return [
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'check', 'label' => 'Check'],
        ];
    }

    /**
     * Get available tax statuses
     */
    private function getTaxStatuses()
    {
        return [
            ['value' => 'Z', 'label' => 'Zero/Exempt (Z)'],
            ['value' => 'S', 'label' => 'Single (S)'],
            ['value' => 'ME', 'label' => 'Married Employee (ME)'],
            ['value' => 'S1', 'label' => 'Single w/ 1 Dependent (S1)'],
            ['value' => 'ME1', 'label' => 'Married w/ 1 Dependent (ME1)'],
            ['value' => 'S2', 'label' => 'Single w/ 2 Dependents (S2)'],
            ['value' => 'ME2', 'label' => 'Married w/ 2 Dependents (ME2)'],
            ['value' => 'S3', 'label' => 'Single w/ 3 Dependents (S3)'],
            ['value' => 'ME3', 'label' => 'Married w/ 3 Dependents (ME3)'],
            ['value' => 'S4', 'label' => 'Single w/ 4+ Dependents (S4)'],
            ['value' => 'ME4', 'label' => 'Married w/ 4+ Dependents (ME4)'],
        ];
    }

    /**
     * Get salary type label
     */
    private function getSalaryTypeLabel($value)
    {
        $types = collect($this->getSalaryTypes())->keyBy('value');
        return $types->get($value)?->label ?? $value;
    }

    /**
     * Get payment method label
     */
    private function getPaymentMethodLabel($value)
    {
        $methods = collect($this->getPaymentMethods())->keyBy('value');
        return $methods->get($value)?->label ?? $value;
    }

    /**
     * Get tax status label
     */
    private function getTaxStatusLabel($value)
    {
        $statuses = collect($this->getTaxStatuses())->keyBy('value');
        return $statuses->get($value)?->label ?? $value;
    }
}
