<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeePayrollInfo;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeePayrollInfoSeeder extends Seeder
{

    private const SALARY_PRESETS = [
        ['basic_salary' => 35000, 'daily_rate' => 1346.15, 'hourly_rate' => 168.27],
        ['basic_salary' => 28000, 'daily_rate' => 1076.92, 'hourly_rate' => 134.62],
        ['basic_salary' => 22000, 'daily_rate' => 846.15, 'hourly_rate' => 105.77],
        ['basic_salary' => 18000, 'daily_rate' => 692.31, 'hourly_rate' => 86.54],
        ['basic_salary' => 15000, 'daily_rate' => 576.92, 'hourly_rate' => 72.12],
    ];

    public function run(): void
    {
        // Truncate payroll info table to avoid duplicates and ensure only current employees are included
        if (\Schema::hasTable('employee_payroll_infos')) {
            \DB::table('employee_payroll_infos')->truncate();
        } else {
            $this->command->warn('Table employee_payroll_infos does not exist. Skipping truncate.');
        }

        $this->command->info('Seeding EmployeePayrollInfo...');

        $creator = User::where('email', 'payroll@cameco.com')->first()
            ?? User::where('email', 'superadmin@cameco.com')->first()
            ?? User::first();

        if (!$creator) {
            $this->command->error('No user found - run RolesAndPermissionsSeeder first.');
            return;
        }

        $employees = Employee::where('status', 'active')->get();
        if ($employees->isEmpty()) {
            $this->command->error('No active employees found - run EmployeeSeeder first.');
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($employees as $index => $employee) {
            $exists = EmployeePayrollInfo::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->whereNull('end_date')
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $preset = self::SALARY_PRESETS[$index % count(self::SALARY_PRESETS)];

            EmployeePayrollInfo::create([
                'employee_id' => $employee->id,
                'salary_type' => 'monthly',
                'basic_salary' => $preset['basic_salary'],
                'daily_rate' => $preset['daily_rate'],
                'hourly_rate' => $preset['hourly_rate'],
                'payment_method' => 'cash',
                'tax_status' => 'S',
                'rdo_code' => '055',
                'withholding_tax_exemption' => 0,
                'is_tax_exempt' => false,
                'is_substituted_filing' => false,
                'sss_number' => sprintf('33-%07d-%d', $employee->id * 1000 + $index, mt_rand(0, 9)),
                'philhealth_number' => sprintf('%012d', $employee->id * 100000 + $index),
                'pagibig_number' => sprintf('%012d', $employee->id * 200000 + $index),
                'tin_number' => sprintf('%09d-%03d', $employee->id * 1000000, mt_rand(0, 999)),
                'sss_bracket' => $this->sss($preset['basic_salary']),
                'is_sss_voluntary' => false,
                'philhealth_is_indigent' => false,
                'pagibig_employee_rate' => 2.00,
                'bank_name' => null,
                'bank_code' => null,
                'bank_account_number' => null,
                'bank_account_name' => null,
                'is_entitled_to_rice' => true,
                'is_entitled_to_uniform' => false,
                'is_entitled_to_laundry' => false,
                'is_entitled_to_medical' => true,
                'effective_date' => '2026-01-01',
                'end_date' => null,
                'is_active' => true,
                'created_by' => $creator->id,
            ]);

            $created++;
        }

        $this->command->info("  -> Created: {$created}, Skipped: {$skipped}");
    }

    private function sss(float $salary): string
    {
        $brackets = [
            3250, 3750, 4250, 4750, 5250, 5750, 6250, 6750, 7250, 7750, 8250, 8750,
            9250, 9750, 10250, 10750, 11250, 11750, 12250, 12750, 13250, 13750, 14250,
            14750, 15250, 15750, 16250, 16750, 17250, 17750, 18250, 18750, 19250, 19750,
        ];

        foreach ($brackets as $i => $cap) {
            if ($salary <= $cap) {
                return (string) ($i + 1);
            }
        }

        return '35';
    }
}
