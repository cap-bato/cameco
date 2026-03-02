<?php

namespace App\Services\Payroll;

use App\Models\SalaryComponent;
use App\Models\EmployeeSalaryComponent;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * SalaryComponentService
 * 
 * Manages salary components and employee component assignments.
 * 
 * Salary components are reusable building blocks for payroll calculations:
 * - System Components: Basic Salary, OT, SSS, PhilHealth, Pag-IBIG, Tax, etc. (protected)
 * - Custom Components: Office-specific components created by admin (can be deleted)
 * 
 * Key Responsibilities:
 * - Create and manage salary components
 * - Assign components to employees with custom amounts
 * - Retrieve components by type and category
 * - Protect system components from deletion
 * - Track component assignments with effective dating
 */
class SalaryComponentService
{
    /**
     * Create new salary component
     * 
     * System components (is_system_component=true) cannot be created manually.
     * 
     * @param array $data Component data
     * @param User $creator User creating the component
     * @return SalaryComponent
     * @throws ValidationException If validation fails
     */
    public function createComponent(array $data, User $creator): SalaryComponent
    {
        // Prevent creation of system components outside of seeder
        if (isset($data['is_system_component']) && $data['is_system_component']) {
            throw ValidationException::withMessages([
                'is_system_component' => 'System components cannot be created manually. Use DatabaseSeeder instead.',
            ]);
        }

        // Validate unique code
        if (SalaryComponent::where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Component code already exists. Use unique codes.',
            ]);
        }

        // Create component
        $component = SalaryComponent::create([
            ...$data,
            'is_system_component' => false,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        Log::info("Salary component created", [
            'component_id' => $component->id,
            'code' => $component->code,
            'name' => $component->name,
            'created_by' => $creator->id,
        ]);

        return $component;
    }

    /**
     * Update salary component
     * 
     * System components cannot be updated (is_system_component=true).
     * 
     * @param SalaryComponent $component
     * @param array $data Updated data
     * @param User $updater User making the update
     * @return SalaryComponent
     * @throws ValidationException If component is system component
     */
    public function updateComponent(SalaryComponent $component, array $data, User $updater): SalaryComponent
    {
        // Prevent updating system components
        if ($component->is_system_component) {
            throw ValidationException::withMessages([
                'system_component' => 'System components cannot be updated. Create custom components instead.',
            ]);
        }

        // Validate unique code (if changed)
        if (isset($data['code']) && $data['code'] != $component->code) {
            if (SalaryComponent::where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'Component code already exists. Use unique codes.',
                ]);
            }
        }

        $component->update([
            ...$data,
            'updated_by' => $updater->id,
        ]);

        Log::info("Salary component updated", [
            'component_id' => $component->id,
            'code' => $component->code,
            'updated_by' => $updater->id,
        ]);

        return $component;
    }

    /**
     * Delete salary component
     * 
     * System components cannot be deleted.
     * Only custom components can be deleted.
     * 
     * @param SalaryComponent $component
     * @throws ValidationException If component is system component or in use
     */
    public function deleteComponent(SalaryComponent $component): void
    {
        // Prevent deleting system components
        if ($component->is_system_component) {
            throw ValidationException::withMessages([
                'system_component' => 'System components cannot be deleted.',
            ]);
        }

        // Check if component is assigned to any employee
        $assignmentCount = EmployeeSalaryComponent::where('salary_component_id', $component->id)
            ->count();

        if ($assignmentCount > 0) {
            throw ValidationException::withMessages([
                'in_use' => "Cannot delete component that is assigned to {$assignmentCount} employee(s). Remove assignments first.",
            ]);
        }

        $component->delete();

        Log::info("Salary component deleted", [
            'component_id' => $component->id,
            'code' => $component->code,
        ]);
    }

    /**
     * Assign component to employee with custom amount
     * 
     * Creates or updates EmployeeSalaryComponent record.
     * Handles effective dating for salary changes.
     * 
     * @param Employee $employee
     * @param SalaryComponent $component
     * @param array $data Assignment data (amount, effective_date, etc.)
     * @param User $creator User creating the assignment
     * @return EmployeeSalaryComponent
     */
    public function assignComponentToEmployee(
        Employee $employee,
        SalaryComponent $component,
        array $data,
        User $creator
    ): EmployeeSalaryComponent {
        $data['effective_date'] = $data['effective_date'] ?? Carbon::now()->toDateString();

        // Check if assignment already exists
        $existing = EmployeeSalaryComponent::where('employee_id', $employee->id)
            ->where('salary_component_id', $component->id)
            ->where('effective_date', $data['effective_date'])
            ->first();

        if ($existing) {
            // Update existing assignment
            $existing->update([
                ...$data,
                'updated_by' => $creator->id,
            ]);

            Log::info("Employee salary component updated", [
                'employee_id' => $employee->id,
                'component_id' => $component->id,
                'amount' => $data['amount'] ?? $existing->amount,
            ]);

            return $existing;
        }

        // Create new assignment
        $assignment = EmployeeSalaryComponent::create([
            'employee_id' => $employee->id,
            'salary_component_id' => $component->id,
            ...$data,
            'created_by' => $creator->id,
        ]);

        Log::info("Employee salary component assigned", [
            'employee_id' => $employee->id,
            'component_id' => $component->id,
            'amount' => $assignment->amount,
        ]);

        return $assignment;
    }

    /**
     * Remove component assignment from employee
     * 
     * Soft-deletes the assignment.
     * 
     * @param EmployeeSalaryComponent $assignment
     * @return void
     */
    public function removeComponentFromEmployee(EmployeeSalaryComponent $assignment): void
    {
        $assignment->delete();

        Log::info("Employee salary component removed", [
            'employee_id' => $assignment->employee_id,
            'component_id' => $assignment->salary_component_id,
        ]);
    }

    /**
     * Get all components by type
     * 
     * @param string $componentType earning|deduction|benefit|tax|contribution|loan|allowance
     * @param bool $activeOnly Only return active components
     * @return Collection
     */
    public function getComponentsByType(string $componentType, bool $activeOnly = true): Collection
    {
        $query = SalaryComponent::where('component_type', $componentType);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * Get all components by category
     * 
     * @param string $category regular|overtime|holiday|allowance|deduction|tax|contribution|etc.
     * @param bool $activeOnly Only return active components
     * @return Collection
     */
    public function getComponentsByCategory(string $category, bool $activeOnly = true): Collection
    {
        $query = SalaryComponent::where('category', $category);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * Get all assigned components for employee
     * 
     * Returns currently active component assignments.
     * 
     * @param Employee $employee
     * @param bool $activeOnly Only return active assignments
     * @return Collection
     */
    public function getEmployeeComponents(Employee $employee, bool $activeOnly = true): Collection
    {
        $query = EmployeeSalaryComponent::where('employee_id', $employee->id)
            ->with('salaryComponent')
            ->orderBy('created_at', 'desc');

        if ($activeOnly) {
            $query->where(function ($q) {
                $q->where('is_active', true)
                  ->whereNull('deleted_at');
            });
        }

        return $query->get();
    }

    /**
     * Get all system components
     * 
     * @return Collection
     */
    public function getSystemComponents(): Collection
    {
        return SalaryComponent::where('is_system_component', true)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get components by type with employee-specific assignments
     * 
     * Enriches component data with employee assignment amounts.
     * 
     * @param Employee $employee
     * @param string $componentType
     * @return Collection
     */
    public function getEmployeeComponentsByType(Employee $employee, string $componentType): Collection
    {
        $components = $this->getComponentsByType($componentType);
        $assignments = $this->getEmployeeComponents($employee)
            ->keyBy('salary_component_id');

        return $components->map(function ($component) use ($assignments) {
            $assignment = $assignments->get($component->id);

            return [
                'id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'display_order' => $component->display_order,
                'assigned' => !is_null($assignment),
                'amount' => $assignment?->amount,
                'frequency' => $assignment?->frequency,
                'effective_date' => $assignment?->effective_date,
            ];
        });
    }

    /**
     * Get all components grouped by type
     * 
     * @param bool $activeOnly Only return active components
     * @return array
     */
    public function getComponentsGroupedByType(bool $activeOnly = true): array
    {
        $query = SalaryComponent::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('display_order')
            ->get()
            ->groupBy('component_type')
            ->map(function ($group) {
                return $group->map(function ($component) {
                    return [
                        'id' => $component->id,
                        'code' => $component->code,
                        'name' => $component->name,
                        'category' => $component->category,
                        'is_system' => $component->is_system_component,
                    ];
                });
            })
            ->toArray();
    }
}
