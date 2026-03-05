<?php

namespace App\Http\Controllers\Payroll\EmployeePayroll;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Services\Payroll\SalaryComponentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Salary Component Controller
 * 
 * Manages salary components for payroll calculations
 * Supports CRUD operations with validation and filtering
 */
class SalaryComponentController extends Controller
{
    public function __construct(
        private SalaryComponentService $componentService
    ) {}

    /**
     * Get available component types
     */
    private function getAvailableComponentTypes()
    {
        return [
            ['value' => 'earning', 'label' => 'Earning'],
            ['value' => 'deduction', 'label' => 'Deduction'],
            ['value' => 'benefit', 'label' => 'Benefit'],
            ['value' => 'tax', 'label' => 'Tax'],
            ['value' => 'contribution', 'label' => 'Contribution'],
            ['value' => 'loan', 'label' => 'Loan'],
            ['value' => 'allowance', 'label' => 'Allowance'],
        ];
    }

    /**
     * Get available categories
     */
    private function getAvailableCategories()
    {
        return [
            ['value' => 'regular', 'label' => 'Regular'],
            ['value' => 'overtime', 'label' => 'Overtime'],
            ['value' => 'holiday', 'label' => 'Holiday'],
            ['value' => 'leave', 'label' => 'Leave'],
            ['value' => 'allowance', 'label' => 'Allowance'],
            ['value' => 'deduction', 'label' => 'Deduction'],
            ['value' => 'tax', 'label' => 'Tax'],
            ['value' => 'contribution', 'label' => 'Contribution'],
            ['value' => 'loan', 'label' => 'Loan'],
            ['value' => 'adjustment', 'label' => 'Adjustment'],
        ];
    }

    /**
     * Get available calculation methods
     */
    private function getAvailableCalculationMethods()
    {
        return [
            ['value' => 'fixed_amount', 'label' => 'Fixed Amount'],
            ['value' => 'percentage_of_basic', 'label' => 'Percentage of Basic'],
            ['value' => 'percentage_of_gross', 'label' => 'Percentage of Gross'],
            ['value' => 'per_hour', 'label' => 'Per Hour'],
            ['value' => 'per_day', 'label' => 'Per Day'],
            ['value' => 'per_unit', 'label' => 'Per Unit'],
            ['value' => 'percentage_of_component', 'label' => 'Percentage of Component'],
        ];
    }

    /**
     * Display a listing of salary components
     */
    public function index(Request $request)
    {
        $query = SalaryComponent::query()
            ->with(['referenceComponent', 'createdBy', 'updatedBy'])
            ->where('is_active', true);

        // Apply search filter
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Apply component type filter
        if ($request->has('component_type') && $request->input('component_type')) {
            $query->where('component_type', $request->input('component_type'));
        }

        // Apply category filter
        if ($request->has('category') && $request->input('category')) {
            $query->where('category', $request->input('category'));
        }

        // Apply status filter
        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $components = $query->orderBy('display_order')->paginate(50);

        // Get reference components for dropdown
        $referenceComponents = SalaryComponent::where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->toArray();

        return Inertia::render('Payroll/EmployeePayroll/Components/Index', [
            'components' => $components,
            'filters' => $request->only(['search', 'component_type', 'category', 'status']),
            'available_component_types' => $this->getAvailableComponentTypes(),
            'available_categories' => $this->getAvailableCategories(),
            'available_calculation_methods' => $this->getAvailableCalculationMethods(),
            'reference_components' => $referenceComponents,
        ]);
    }

    /**
     * Show the form for creating a new salary component
     */
    public function create()
    {
        // Get reference components for dropdown
        $referenceComponents = SalaryComponent::where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->toArray();

        return Inertia::render('Payroll/EmployeePayroll/Components/Create', [
            'available_component_types' => $this->getAvailableComponentTypes(),
            'available_categories' => $this->getAvailableCategories(),
            'available_calculation_methods' => $this->getAvailableCalculationMethods(),
            'reference_components' => $referenceComponents,
        ]);
    }

    /**
     * Store a newly created salary component in storage
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:salary_components,name',
            'code' => 'required|string|max:50|unique:salary_components,code',
            'component_type' => 'required|in:earning,deduction,benefit,tax,contribution,loan,allowance',
            'category' => 'required|in:regular,overtime,holiday,leave,allowance,deduction,tax,contribution,loan,adjustment',
            'calculation_method' => 'required|in:fixed_amount,percentage_of_basic,percentage_of_gross,per_hour,per_day,per_unit,percentage_of_component',
            'default_amount' => 'nullable|numeric|min:0',
            'default_percentage' => 'nullable|numeric|min:0|max:1000',
            'reference_component_id' => 'nullable|exists:salary_components,id',
            'ot_multiplier' => 'nullable|numeric|min:0',
            'is_premium_pay' => 'boolean',
            'is_taxable' => 'boolean',
            'is_deminimis' => 'boolean',
            'deminimis_limit_monthly' => 'nullable|numeric|min:0',
            'deminimis_limit_annual' => 'nullable|numeric|min:0',
            'is_13th_month' => 'boolean',
            'is_other_benefits' => 'boolean',
            'affects_sss' => 'boolean',
            'affects_philhealth' => 'boolean',
            'affects_pagibig' => 'boolean',
            'affects_gross_compensation' => 'boolean',
            'display_order' => 'integer|min:0',
            'is_displayed_on_payslip' => 'boolean',
        ]);

        try {
            $component = $this->componentService->createComponent(
                $validated,
                auth()->user()
            );

            return redirect()
                ->route('payroll.salary-components.show', $component->id)
                ->with('success', 'Salary component created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified salary component
     */
    public function show(SalaryComponent $salaryComponent)
    {
        $salaryComponent->load(['referenceComponent', 'createdBy', 'updatedBy']);

        return Inertia::render('Payroll/EmployeePayroll/Components/Show', [
            'component' => $salaryComponent,
        ]);
    }

    /**
     * Show the form for editing the specified salary component
     */
    public function edit(SalaryComponent $salaryComponent)
    {
        // Prevent editing of system components
        if ($salaryComponent->is_system_component) {
            abort(403, 'System components cannot be edited.');
        }

        $salaryComponent->load('referenceComponent');

        // Get reference components for dropdown
        $referenceComponents = SalaryComponent::where('is_active', true)
            ->where('id', '!=', $salaryComponent->id)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->toArray();

        return Inertia::render('Payroll/EmployeePayroll/Components/Edit', [
            'component' => $salaryComponent,
            'available_component_types' => $this->getAvailableComponentTypes(),
            'available_categories' => $this->getAvailableCategories(),
            'available_calculation_methods' => $this->getAvailableCalculationMethods(),
            'reference_components' => $referenceComponents,
        ]);
    }

    /**
     * Update the specified salary component in storage
     */
    public function update(Request $request, SalaryComponent $salaryComponent)
    {
        // Prevent editing of system components
        if ($salaryComponent->is_system_component) {
            abort(403, 'System components cannot be edited.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:salary_components,name,' . $salaryComponent->id,
            'code' => 'required|string|max:50|unique:salary_components,code,' . $salaryComponent->id,
            'component_type' => 'required|in:earning,deduction,benefit,tax,contribution,loan,allowance',
            'category' => 'required|in:regular,overtime,holiday,leave,allowance,deduction,tax,contribution,loan,adjustment',
            'calculation_method' => 'required|in:fixed_amount,percentage_of_basic,percentage_of_gross,per_hour,per_day,per_unit,percentage_of_component',
            'default_amount' => 'nullable|numeric|min:0',
            'default_percentage' => 'nullable|numeric|min:0|max:1000',
            'reference_component_id' => 'nullable|exists:salary_components,id',
            'ot_multiplier' => 'nullable|numeric|min:0',
            'is_premium_pay' => 'boolean',
            'is_taxable' => 'boolean',
            'is_deminimis' => 'boolean',
            'deminimis_limit_monthly' => 'nullable|numeric|min:0',
            'deminimis_limit_annual' => 'nullable|numeric|min:0',
            'is_13th_month' => 'boolean',
            'is_other_benefits' => 'boolean',
            'affects_sss' => 'boolean',
            'affects_philhealth' => 'boolean',
            'affects_pagibig' => 'boolean',
            'affects_gross_compensation' => 'boolean',
            'display_order' => 'integer|min:0',
            'is_displayed_on_payslip' => 'boolean',
            'is_active' => 'boolean',
        ]);

        try {
            $component = $this->componentService->updateComponent(
                $salaryComponent,
                $validated,
                auth()->user()
            );

            return redirect()
                ->route('payroll.salary-components.show', $component->id)
                ->with('success', 'Salary component updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified salary component from storage
     */
    public function destroy(SalaryComponent $salaryComponent)
    {
        // Prevent deletion of system components
        if ($salaryComponent->is_system_component) {
            abort(403, 'System components cannot be deleted.');
        }

        try {
            $this->componentService->deleteComponent($salaryComponent, auth()->user());

            return redirect()
                ->route('payroll.salary-components.index')
                ->with('success', 'Salary component deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
