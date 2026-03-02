<?php

namespace App\Services\Payroll;

use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * AllowanceDeductionService
 *
 * Manages employee allowances and deductions including:
 * - Recurring allowances (rice, COLA, transportation, meal, etc.)
 * - Recurring deductions (insurance, union dues, canteen, etc.)
 * - Bulk assignment of allowances/deductions
 * - Total calculations for payroll integration
 */
class AllowanceDeductionService
{
    /**
     * Add allowance to employee
     *
     * @param Employee $employee
     * @param string $allowanceType Type of allowance (rice, cola, transportation, meal, housing, communication, etc.)
     * @param array $data Contains 'amount', optional 'effective_date', 'end_date', 'description'
     * @param User $creator
     * @return EmployeeAllowance
     */
    public function addAllowance(Employee $employee, string $allowanceType, array $data, User $creator): EmployeeAllowance
    {
        // Validate allowance type
        $validTypes = ['rice', 'cola', 'transportation', 'meal', 'housing', 'communication', 'laundry', 'clothing', 'other'];
        if (!in_array($allowanceType, $validTypes)) {
            throw ValidationException::withMessages([
                'allowance_type' => "Invalid allowance type. Allowed: " . implode(', ', $validTypes),
            ]);
        }

        // Validate amount
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than 0',
            ]);
        }

        // Set effective date to today if not provided
        $effectiveDate = $data['effective_date'] ?? Carbon::now()->toDateString();

        // Deactivate existing allowance of same type if exists
        EmployeeAllowance::where('employee_id', $employee->id)
            ->where('allowance_type', $allowanceType)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'end_date' => $effectiveDate,
            ]);

        // Create new allowance
        $allowance = EmployeeAllowance::create([
            'employee_id' => $employee->id,
            'allowance_type' => $allowanceType,
            'amount' => (float) $data['amount'],
            'effective_date' => $effectiveDate,
            'end_date' => $data['end_date'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        Log::info("Employee allowance added", [
            'employee_id' => $employee->id,
            'allowance_type' => $allowanceType,
            'amount' => $data['amount'],
            'effective_date' => $effectiveDate,
        ]);

        return $allowance;
    }

    /**
     * Remove allowance from employee
     *
     * @param EmployeeAllowance $allowance
     * @param User $updater
     * @return void
     */
    public function removeAllowance(EmployeeAllowance $allowance, User $updater): void
    {
        // Deactivate allowance with effective end date as today
        $allowance->update([
            'is_active' => false,
            'end_date' => Carbon::now()->toDateString(),
            'updated_by' => $updater->id,
        ]);

        Log::info("Employee allowance removed", [
            'employee_id' => $allowance->employee_id,
            'allowance_type' => $allowance->allowance_type,
        ]);
    }

    /**
     * Add deduction to employee
     *
     * @param Employee $employee
     * @param string $deductionType Type of deduction (insurance, union_dues, canteen, loan, etc.)
     * @param array $data Contains 'amount', optional 'effective_date', 'end_date', 'description'
     * @param User $creator
     * @return EmployeeDeduction
     */
    public function addDeduction(Employee $employee, string $deductionType, array $data, User $creator): EmployeeDeduction
    {
        // Validate deduction type
        $validTypes = ['insurance', 'union_dues', 'canteen', 'loan', 'uniform_fund', 'medical', 'educational', 'savings', 'cooperative', 'other'];
        if (!in_array($deductionType, $validTypes)) {
            throw ValidationException::withMessages([
                'deduction_type' => "Invalid deduction type. Allowed: " . implode(', ', $validTypes),
            ]);
        }

        // Validate amount
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than 0',
            ]);
        }

        // Set effective date to today if not provided
        $effectiveDate = $data['effective_date'] ?? Carbon::now()->toDateString();

        // Deactivate existing deduction of same type if exists
        EmployeeDeduction::where('employee_id', $employee->id)
            ->where('deduction_type', $deductionType)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'end_date' => $effectiveDate,
            ]);

        // Create new deduction
        $deduction = EmployeeDeduction::create([
            'employee_id' => $employee->id,
            'deduction_type' => $deductionType,
            'amount' => (float) $data['amount'],
            'effective_date' => $effectiveDate,
            'end_date' => $data['end_date'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        Log::info("Employee deduction added", [
            'employee_id' => $employee->id,
            'deduction_type' => $deductionType,
            'amount' => $data['amount'],
            'effective_date' => $effectiveDate,
        ]);

        return $deduction;
    }

    /**
     * Remove deduction from employee
     *
     * @param EmployeeDeduction $deduction
     * @param User $updater
     * @return void
     */
    public function removeDeduction(EmployeeDeduction $deduction, User $updater): void
    {
        // Deactivate deduction with effective end date as today
        $deduction->update([
            'is_active' => false,
            'end_date' => Carbon::now()->toDateString(),
            'updated_by' => $updater->id,
        ]);

        Log::info("Employee deduction removed", [
            'employee_id' => $deduction->employee_id,
            'deduction_type' => $deduction->deduction_type,
        ]);
    }

    /**
     * Bulk assign allowance to multiple employees
     *
     * @param string $allowanceType
     * @param array $data Contains 'amount', 'employee_ids' or filter criteria (department_id, position_id, salary_type)
     * @param User $creator
     * @return Collection of EmployeeAllowance
     */
    public function bulkAssignAllowances(string $allowanceType, array $data, User $creator): Collection
    {
        // Determine which employees to assign to
        $employeeIds = [];

        if (isset($data['employee_ids'])) {
            // Direct employee selection
            $employeeIds = (array) $data['employee_ids'];
        } elseif (isset($data['department_id']) || isset($data['position_id']) || isset($data['salary_type'])) {
            // Filter by criteria
            $query = Employee::query();

            if (isset($data['department_id'])) {
                $query->where('department_id', $data['department_id']);
            }

            if (isset($data['position_id'])) {
                $query->where('position_id', $data['position_id']);
            }

            if (isset($data['salary_type'])) {
                $query->whereHas('payrollInfo', function ($q) use ($data) {
                    $q->where('salary_type', $data['salary_type']);
                });
            }

            $employeeIds = $query->pluck('id')->toArray();
        } else {
            throw ValidationException::withMessages([
                'employees' => 'Must specify either employee_ids or filter criteria (department_id, position_id, salary_type)',
            ]);
        }

        if (empty($employeeIds)) {
            throw ValidationException::withMessages([
                'employees' => 'No employees found matching criteria',
            ]);
        }

        // Validate amount
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than 0',
            ]);
        }

        // Add allowance to each employee
        $createdAllowances = new Collection();
        $effectiveDate = $data['effective_date'] ?? Carbon::now()->toDateString();

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            if ($employee) {
                try {
                    $allowance = $this->addAllowance($employee, $allowanceType, [
                        'amount' => $data['amount'],
                        'effective_date' => $effectiveDate,
                        'end_date' => $data['end_date'] ?? null,
                        'description' => $data['description'] ?? null,
                    ], $creator);

                    $createdAllowances->push($allowance);
                } catch (\Exception $e) {
                    Log::error("Failed to assign allowance to employee {$employeeId}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info("Bulk allowance assignment completed", [
            'allowance_type' => $allowanceType,
            'count' => $createdAllowances->count(),
            'amount' => $data['amount'],
        ]);

        return $createdAllowances;
    }

    /**
     * Get all active allowances for employee
     *
     * @param Employee $employee
     * @return Collection of EmployeeAllowance
     */
    public function getActiveAllowances(Employee $employee): Collection
    {
        return EmployeeAllowance::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::now()->toDateString());
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all active deductions for employee
     *
     * @param Employee $employee
     * @return Collection of EmployeeDeduction
     */
    public function getActiveDeductions(Employee $employee): Collection
    {
        return EmployeeDeduction::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::now()->toDateString());
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all allowances for employee (including inactive)
     *
     * @param Employee $employee
     * @param bool $activeOnly
     * @return Collection
     */
    public function getEmployeeAllowances(Employee $employee, bool $activeOnly = true): Collection
    {
        $query = EmployeeAllowance::where('employee_id', $employee->id);

        if ($activeOnly) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', Carbon::now()->toDateString());
                });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get all deductions for employee (including inactive)
     *
     * @param Employee $employee
     * @param bool $activeOnly
     * @return Collection
     */
    public function getEmployeeDeductions(Employee $employee, bool $activeOnly = true): Collection
    {
        $query = EmployeeDeduction::where('employee_id', $employee->id);

        if ($activeOnly) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', Carbon::now()->toDateString());
                });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Calculate total monthly allowances for employee
     *
     * @param Employee $employee
     * @return float
     */
    public function getTotalMonthlyAllowances(Employee $employee): float
    {
        return $this->getActiveAllowances($employee)->sum('amount');
    }

    /**
     * Calculate total monthly deductions for employee
     *
     * @param Employee $employee
     * @return float
     */
    public function getTotalMonthlyDeductions(Employee $employee): float
    {
        return $this->getActiveDeductions($employee)->sum('amount');
    }

    /**
     * Get allowances by type
     *
     * @param Employee $employee
     * @param string $allowanceType
     * @return EmployeeAllowance|null
     */
    public function getEmployeeAllowanceByType(Employee $employee, string $allowanceType): ?EmployeeAllowance
    {
        return EmployeeAllowance::where('employee_id', $employee->id)
            ->where('allowance_type', $allowanceType)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::now()->toDateString());
            })
            ->first();
    }

    /**
     * Get deductions by type
     *
     * @param Employee $employee
     * @param string $deductionType
     * @return EmployeeDeduction|null
     */
    public function getEmployeeDeductionByType(Employee $employee, string $deductionType): ?EmployeeDeduction
    {
        return EmployeeDeduction::where('employee_id', $employee->id)
            ->where('deduction_type', $deductionType)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::now()->toDateString());
            })
            ->first();
    }

    /**
     * Get allowances and deductions grouped
     *
     * @param Employee $employee
     * @return array
     */
    public function getEmployeeAllowancesDeductionsGrouped(Employee $employee): array
    {
        return [
            'allowances' => $this->getActiveAllowances($employee)->groupBy('allowance_type')->toArray(),
            'deductions' => $this->getActiveDeductions($employee)->groupBy('deduction_type')->toArray(),
            'total_allowances' => $this->getTotalMonthlyAllowances($employee),
            'total_deductions' => $this->getTotalMonthlyDeductions($employee),
            'net_allowances_deductions' => $this->getTotalMonthlyAllowances($employee) - $this->getTotalMonthlyDeductions($employee),
        ];
    }
}
