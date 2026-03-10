<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PayrollConfiguration;
use Carbon\Carbon;

class PayrollConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        // Deduction Timing Configuration
        PayrollConfiguration::updateOrCreate(
            ['config_key' => 'deduction_timing'],
            [
                'config_value' => [
                    'sss' => [
                        'timing' => 'monthly_only',  // per_cutoff | monthly_only | split_monthly
                        'apply_on_period' => 2, // 1 = 1st cutoff, 2 = 2nd cutoff, null = both
                        'description' => 'SSS contribution - once per month on 2nd cutoff',
                    ],
                    'philhealth' => [
                        'timing' => 'monthly_only',
                        'apply_on_period' => 2,
                        'description' => 'PhilHealth premium - once per month on 2nd cutoff',
                    ],
                    'pagibig' => [
                        'timing' => 'monthly_only',
                        'apply_on_period' => 2,
                        'description' => 'Pag-IBIG contribution - once per month on 2nd cutoff',
                    ],
                    'withholding_tax' => [
                        'timing' => 'monthly_only',
                        'apply_on_period' => 2,
                        'description' => 'Withholding tax - once per month on 2nd cutoff',
                    ],
                    'loans' => [
                        'timing' => 'monthly_only',
                        'apply_on_period' => 2,
                        'description' => 'Loan installments - once per month on 2nd cutoff',
                    ],
                ],
                'description' => 'Deduction timing configuration for semi-monthly payroll',
                'effective_from' => Carbon::now(),
                'is_active' => true,
            ]
        );

        // Alternative configuration examples (commented out)
        
        // Example 1: All deductions per cutoff
        /*
        PayrollConfiguration::create([
            'config_key' => 'deduction_timing_per_cutoff',
            'config_value' => [
                'sss' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
                'philhealth' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
                'pagibig' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
                'withholding_tax' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
                'loans' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
            ],
            'description' => 'All deductions applied every cutoff (for testing)',
            'effective_from' => Carbon::now(),
            'is_active' => false,
        ]);
        */

        // Example 2: Split monthly deductions
        /*
        PayrollConfiguration::create([
            'config_key' => 'deduction_timing_split',
            'config_value' => [
                'sss' => ['timing' => 'split_monthly', 'apply_on_period' => null],
                'philhealth' => ['timing' => 'split_monthly', 'apply_on_period' => null],
                'pagibig' => ['timing' => 'split_monthly', 'apply_on_period' => null],
                'withholding_tax' => ['timing' => 'split_monthly', 'apply_on_period' => null],
                'loans' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            ],
            'description' => 'Government contributions split 50/50 across cutoffs',
            'effective_from' => Carbon::now(),
            'is_active' => false,
        ]);
        */

        $this->command->info('Payroll configuration seeded successfully!');
        $this->command->info('- Deduction timing: monthly_only (2nd cutoff)');
    }
}
