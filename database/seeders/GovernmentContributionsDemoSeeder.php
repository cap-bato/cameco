<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\EmployeeGovernmentContribution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class GovernmentContributionsDemoSeeder extends Seeder
{
    /**
     * Seed EmployeeGovernmentContribution records for all active employees and periods.
     * This ensures BIR/SSS/PhilHealth/Pag-IBIG reports always show demo data.
     */
    public function run(): void
    {
        $this->command->info('Seeding EmployeeGovernmentContribution demo data for all periods and employees...');

        $periods = PayrollPeriod::all();
        $employees = Employee::where('status', 'active')->get();
        $faker = Faker::create();

        if ($periods->isEmpty() || $employees->isEmpty()) {
            $this->command->warn('No periods or employees found. Run PayrollPeriodsSeeder and EmployeeSeeder first.');
            return;
        }

        $total = 0;
        foreach ($periods as $period) {
            foreach ($employees as $employee) {
                // Avoid duplicate records
                $exists = EmployeeGovernmentContribution::where('employee_id', $employee->id)
                    ->where('payroll_period_id', $period->id)
                    ->exists();
                if ($exists) continue;

                $gross = $faker->numberBetween(18000, 35000);
                $basic = $faker->numberBetween(15000, $gross - 2000);
                $taxable = $gross - $faker->numberBetween(2000, 5000);
                $sssEmp = round($gross * 0.045, 2);
                $sssEr  = round($gross * 0.08, 2);
                $sssEc  = 30.00;
                $sssTot = $sssEmp + $sssEr + $sssEc;
                $philEmp = round($gross * 0.0225, 2);
                $philEr  = round($gross * 0.0225, 2);
                $philTot = $philEmp + $philEr;
                $pagibigEmp = 100.00;
                $pagibigEr  = 100.00;
                $pagibigTot = $pagibigEmp + $pagibigEr;
                $withholding = round($gross * 0.08, 2);

                // Get TIN and government numbers from profile if available
                $profile = $employee->profile;
                $tin = $profile?->tin_number ?? $faker->numerify('1###########');
                $sss_number = $profile?->sss_number ?? $faker->numerify('##-#######-#');
                $philhealth_number = $profile?->philhealth_number ?? $faker->numerify('##-########-#');
                $pagibig_number = $profile?->pagibig_number ?? $faker->numerify('####-####-####');

                EmployeeGovernmentContribution::create([
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'period_start' => $period->period_start,
                    'period_end' => $period->period_end,
                    'period_month' => $period->period_month,
                    'basic_salary' => $basic,
                    'gross_compensation' => $gross,
                    'taxable_income' => $taxable,
                    'sss_number' => $sss_number,
                    'sss_employee_contribution' => $sssEmp,
                    'sss_employer_contribution' => $sssEr,
                    'sss_ec_contribution' => $sssEc,
                    'sss_total_contribution' => $sssTot,
                    'philhealth_number' => $philhealth_number,
                    'philhealth_employee_contribution' => $philEmp,
                    'philhealth_employer_contribution' => $philEr,
                    'philhealth_total_contribution' => $philTot,
                    'pagibig_number' => $pagibig_number,
                    'pagibig_employee_contribution' => $pagibigEmp,
                    'pagibig_employer_contribution' => $pagibigEr,
                    'pagibig_total_contribution' => $pagibigTot,
                    'tin' => $tin,
                    'withholding_tax' => $withholding,
                    'status' => 'processed',
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $total++;
            }
        }
        $this->command->info("✓ Seeded {$total} EmployeeGovernmentContribution records");
    }
}
