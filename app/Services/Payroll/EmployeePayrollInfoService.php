<?php

namespace App\Services\Payroll;

use App\Models\EmployeePayrollInfo;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * EmployeePayrollInfoService
 * 
 * Manages employee payroll information including salary setup, government numbers,
 * tax configuration, bank details, and automatic calculation of derived rates.
 * 
 * Key Responsibilities:
 * - Create and update employee payroll information
 * - Validate government number formats (SSS, PhilHealth, Pag-IBIG, TIN)
 * - Auto-calculate daily_rate and hourly_rate from basic_salary
 * - Auto-detect SSS bracket based on salary
 * - Maintain payroll information history with effective dating
 * - Track salary changes with proper history recording
 */
class EmployeePayrollInfoService
{
    /**
     * Create new employee payroll information
     * 
     * @param array $data Payroll information data
     * @param User $creator User creating the record
     * @return EmployeePayrollInfo
     * @throws ValidationException If validation fails
     */
    public function createPayrollInfo(array $data, User $creator): EmployeePayrollInfo
    {
        // Validate government numbers
        $this->validateGovernmentNumbers($data);

        // Auto-calculate derived rates
        $data = $this->calculateDerivedRates($data);

        // Auto-detect SSS bracket
        if (!isset($data['sss_bracket']) && isset($data['basic_salary'])) {
            $data['sss_bracket'] = $this->autoDetectSSSBracket($data['basic_salary']);
        }

        // Set effective date to today if not provided
        if (!isset($data['effective_date'])) {
            $data['effective_date'] = Carbon::now()->toDateString();
        }

        // Deactivate existing payroll info for this employee
        if (isset($data['employee_id'])) {
            EmployeePayrollInfo::where('employee_id', $data['employee_id'])
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date' => Carbon::now()->toDateString(),
                ]);
        }

        // Create new payroll info
        $payrollInfo = EmployeePayrollInfo::create([
            ...$data,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        Log::info("Employee payroll info created", [
            'employee_id' => $payrollInfo->employee_id,
            'salary_type' => $payrollInfo->salary_type,
            'basic_salary' => $payrollInfo->basic_salary,
            'created_by' => $creator->id,
        ]);

        return $payrollInfo;
    }

    /**
     * Update employee payroll information (with history tracking)
     * 
     * Creates new record if salary changes to maintain history.
     * Updates current record for non-salary field changes.
     * 
     * @param EmployeePayrollInfo $payrollInfo Current payroll info
     * @param array $data Updated data
     * @param User $updater User making the update
     * @return EmployeePayrollInfo Updated or new payroll info
     * @throws \Exception If database transaction fails
     */
    public function updatePayrollInfo(EmployeePayrollInfo $payrollInfo, array $data, User $updater): EmployeePayrollInfo
    {
        DB::beginTransaction();
        try {
            // Validate government numbers
            $this->validateGovernmentNumbers($data);

            // Auto-calculate derived rates
            $data = $this->calculateDerivedRates($data);

            // Auto-detect SSS bracket if basic_salary changed
            if (isset($data['basic_salary']) && $data['basic_salary'] != $payrollInfo->basic_salary) {
                $data['sss_bracket'] = $this->autoDetectSSSBracket($data['basic_salary']);
            }

            // If salary changed, create new record with history
            if ($this->isSalaryChange($payrollInfo, $data)) {
                // End current payroll info
                $payrollInfo->update([
                    'is_active' => false,
                    'end_date' => Carbon::now()->toDateString(),
                    'updated_by' => $updater->id,
                ]);

                // Create new payroll info record
                $newPayrollInfo = EmployeePayrollInfo::create([
                    'employee_id' => $payrollInfo->employee_id,
                    ...$data,
                    'effective_date' => $data['effective_date'] ?? Carbon::now()->toDateString(),
                    'is_active' => true,
                    'created_by' => $updater->id,
                ]);

                DB::commit();

                Log::info("Employee payroll info updated with history", [
                    'employee_id' => $payrollInfo->employee_id,
                    'old_salary' => $payrollInfo->basic_salary,
                    'new_salary' => $data['basic_salary'] ?? $payrollInfo->basic_salary,
                    'updated_by' => $updater->id,
                ]);

                return $newPayrollInfo;
            } else {
                // Just update non-salary fields
                $payrollInfo->update([
                    ...$data,
                    'updated_by' => $updater->id,
                ]);

                DB::commit();

                Log::info("Employee payroll info updated", [
                    'employee_id' => $payrollInfo->employee_id,
                    'updated_by' => $updater->id,
                ]);

                return $payrollInfo;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update employee payroll info", [
                'employee_id' => $payrollInfo->employee_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get active payroll info for employee
     * 
     * Returns the currently active payroll information record.
     * 
     * @param Employee $employee
     * @return EmployeePayrollInfo|null
     */
    public function getActivePayrollInfo(Employee $employee): ?EmployeePayrollInfo
    {
        return EmployeePayrollInfo::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereNull('end_date')
            ->first();
    }

    /**
     * Get payroll history for employee
     * 
     * Returns all payroll information records (active and inactive) in reverse chronological order.
     * 
     * @param Employee $employee
     * @return array
     */
    public function getPayrollHistory(Employee $employee): array
    {
        $history = EmployeePayrollInfo::where('employee_id', $employee->id)
            ->orderBy('effective_date', 'desc')
            ->get();

        return $history->map(function ($record) {
            return [
                'id' => $record->id,
                'effective_date' => $record->effective_date,
                'end_date' => $record->end_date,
                'salary_type' => $record->salary_type,
                'basic_salary' => (float) $record->basic_salary,
                'daily_rate' => (float) $record->daily_rate,
                'hourly_rate' => (float) $record->hourly_rate,
                'is_active' => $record->is_active,
                'created_at' => $record->created_at,
            ];
        })->toArray();
    }

    /**
     * Validate government number formats
     * 
     * Validates SSS, PhilHealth, Pag-IBIG, and TIN number formats.
     * Throws ValidationException if any numbers are invalid.
     * 
     * @param array $data
     * @throws ValidationException
     */
    private function validateGovernmentNumbers(array $data): void
    {
        $errors = [];

        if (isset($data['sss_number']) && !empty($data['sss_number'])) {
            if (!EmployeePayrollInfo::validateGovernmentNumber('sss', $data['sss_number'])) {
                $errors['sss_number'] = 'Invalid SSS number format. Expected: 00-1234567-8';
            }
        }

        if (isset($data['philhealth_number']) && !empty($data['philhealth_number'])) {
            if (!EmployeePayrollInfo::validateGovernmentNumber('philhealth', $data['philhealth_number'])) {
                $errors['philhealth_number'] = 'Invalid PhilHealth number format. Expected: 12 digits';
            }
        }

        if (isset($data['pagibig_number']) && !empty($data['pagibig_number'])) {
            if (!EmployeePayrollInfo::validateGovernmentNumber('pagibig', $data['pagibig_number'])) {
                $errors['pagibig_number'] = 'Invalid Pag-IBIG number format. Expected: 1234-5678-9012';
            }
        }

        if (isset($data['tin_number']) && !empty($data['tin_number'])) {
            if (!EmployeePayrollInfo::validateGovernmentNumber('tin', $data['tin_number'])) {
                $errors['tin_number'] = 'Invalid TIN format. Expected: 123-456-789-000';
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Calculate derived rates (daily_rate, hourly_rate)
     * 
     * Auto-calculates daily_rate from basic_salary (÷22 working days).
     * Auto-calculates hourly_rate from daily_rate (÷8 hours).
     * 
     * @param array $data
     * @return array Updated data with calculated rates
     */
    private function calculateDerivedRates(array $data): array
    {
        // Calculate daily_rate from basic_salary if not provided
        if (isset($data['salary_type']) && $data['salary_type'] === 'monthly' && isset($data['basic_salary'])) {
            if (!isset($data['daily_rate'])) {
                $data['daily_rate'] = round($data['basic_salary'] / 22, 2); // 22 working days per month
            }
        }

        // Calculate hourly_rate from daily_rate if not provided
        if (isset($data['daily_rate']) && !isset($data['hourly_rate'])) {
            $data['hourly_rate'] = round($data['daily_rate'] / 8, 2); // 8 hours per day
        }

        return $data;
    }

    /**
     * Auto-detect SSS bracket based on monthly salary
     * 
     * Uses BIR SSS salary brackets for the current year.
     * @todo Replace with lookup from government_contribution_rates table once implemented
     * 
     * @param float $salary Monthly salary
     * @return string SSS bracket code
     */
    private function autoDetectSSSBracket(float $salary): string
    {
        // 2024-2025 SSS salary brackets
        if ($salary < 4250) return 'E1';
        if ($salary < 8000) return 'E2';
        if ($salary < 16000) return 'E3';
        if ($salary < 30000) return 'E4';
        if ($salary < 40000) return 'E5';
        return 'E6'; // ₱40,000 and above
    }

    /**
     * Check if data contains salary changes (requires history)
     * 
     * Salary changes trigger creation of new payroll info record.
     * Non-salary changes just update the current record.
     * 
     * @param EmployeePayrollInfo $current
     * @param array $data
     * @return bool True if salary-related fields changed
     */
    private function isSalaryChange(EmployeePayrollInfo $current, array $data): bool
    {
        $salaryFields = ['basic_salary', 'daily_rate', 'hourly_rate', 'salary_type'];

        foreach ($salaryFields as $field) {
            if (isset($data[$field]) && $data[$field] != $current->{$field}) {
                return true;
            }
        }

        return false;
    }
}
