<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * SalaryComponentSeeder
 * 
 * Seeds all system salary components into the salary_components table.
 * These are base components used for payroll calculations across all employees.
 * 
 * System components:
 * - Cannot be deleted (protected)
 * - Are marked as is_system_component = true
 * - Have predefined calculation methods and tax treatment
 * - Are ordered by display_order for proper payslip sequencing
 * 
 * Component Categories:
 * 1. Earnings - Regular (Basic Salary, Other Allowances, Rate Differences)
 * 2. Earnings - Overtime (1.25x, 1.30x, 2.00x, 2.60x multipliers)
 * 3. Earnings - Holiday & Special Pay (Regular, Double, Special Holiday, Night Premium)
 * 4. Contributions - Government (SSS, PhilHealth, Pag-IBIG)
 * 5. Deductions - Withholding Tax
 * 6. Deductions - Loans
 * 7. Allowances - De Minimis/Tax-Exempt (Rice, Clothing, Laundry, Medical)
 * 8. Benefits - 13th Month Pay
 */
class SalaryComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a system user for audit trail
        $systemUser = User::firstOrCreate(
            ['email' => 'system@payroll.local'],
            [
                'name' => 'System',
                'username' => 'system',
                'password' => bcrypt('system-password'),
                'email_verified_at' => now(),
            ]
        );
        $systemComponents = [
            // ============================================
            // CATEGORY 1: EARNINGS - REGULAR
            // ============================================
            [
                'name' => 'Basic Salary',
                'code' => 'BASIC',
                'component_type' => 'earning',
                'category' => 'regular',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => true,
                'is_deminimis' => false,
                'is_13th_month' => false,
                'is_other_benefits' => false,
                'affects_sss' => true,
                'affects_philhealth' => true,
                'affects_pagibig' => true,
                'affects_gross_compensation' => true,
                'display_order' => 1,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Other Allowance',
                'code' => 'ALLOWANCE_OTHER',
                'component_type' => 'earning',
                'category' => 'regular',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => true,
                'affects_sss' => true,
                'affects_philhealth' => true,
                'affects_pagibig' => true,
                'affects_gross_compensation' => true,
                'display_order' => 2,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Rate Difference',
                'code' => 'ALLOWANCE_DIFF_RATE',
                'component_type' => 'earning',
                'category' => 'regular',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => true,
                'affects_sss' => true,
                'affects_philhealth' => true,
                'affects_pagibig' => true,
                'affects_gross_compensation' => true,
                'display_order' => 3,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],

            // ============================================
            // CATEGORY 2: EARNINGS - OVERTIME
            // ============================================
            [
                'name' => 'Overtime Regular',
                'code' => 'OT_REG',
                'component_type' => 'earning',
                'category' => 'overtime',
                'calculation_method' => 'per_hour',
                'ot_multiplier' => 1.25,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => true,
                'display_order' => 5,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Overtime Holiday',
                'code' => 'OT_HOLIDAY',
                'component_type' => 'earning',
                'category' => 'overtime',
                'calculation_method' => 'per_hour',
                'ot_multiplier' => 1.30,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => true,
                'display_order' => 6,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Overtime Double',
                'code' => 'OT_DOUBLE',
                'component_type' => 'earning',
                'category' => 'overtime',
                'calculation_method' => 'per_hour',
                'ot_multiplier' => 2.00,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => true,
                'display_order' => 7,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Overtime Triple',
                'code' => 'OT_TRIPLE',
                'component_type' => 'earning',
                'category' => 'overtime',
                'calculation_method' => 'per_hour',
                'ot_multiplier' => 2.60,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => true,
                'display_order' => 8,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],

            // ============================================
            // CATEGORY 3: EARNINGS - HOLIDAY & SPECIAL PAY
            // ============================================
            [
                'name' => 'Regular Holiday Pay',
                'code' => 'HOLIDAY_REG',
                'component_type' => 'earning',
                'category' => 'holiday',
                'calculation_method' => 'per_day',
                'ot_multiplier' => 1.00,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'affects_sss' => true,
                'affects_philhealth' => true,
                'affects_pagibig' => true,
                'affects_gross_compensation' => true,
                'display_order' => 10,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Double Holiday Pay',
                'code' => 'HOLIDAY_DOUBLE',
                'component_type' => 'earning',
                'category' => 'holiday',
                'calculation_method' => 'per_day',
                'ot_multiplier' => 2.00,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'affects_sss' => true,
                'affects_philhealth' => true,
                'affects_pagibig' => true,
                'affects_gross_compensation' => true,
                'display_order' => 11,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Special Holiday (If Worked)',
                'code' => 'HOLIDAY_SPECIAL_WORK',
                'component_type' => 'earning',
                'category' => 'holiday',
                'calculation_method' => 'per_day',
                'ot_multiplier' => 2.00,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'affects_sss' => true,
                'affects_philhealth' => true,
                'affects_pagibig' => true,
                'affects_gross_compensation' => true,
                'display_order' => 12,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Night Shift Premium',
                'code' => 'PREMIUM_NIGHT',
                'component_type' => 'earning',
                'category' => 'holiday',
                'calculation_method' => 'percentage_of_basic',
                'default_percentage' => 10.00,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => true,
                'display_order' => 15,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],

            // ============================================
            // CATEGORY 4: CONTRIBUTIONS - GOVERNMENT
            // ============================================
            [
                'name' => 'SSS Contribution',
                'code' => 'SSS',
                'component_type' => 'contribution',
                'category' => 'contribution',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => false,
                'is_deminimis' => false,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 20,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'PhilHealth Contribution',
                'code' => 'PHILHEALTH',
                'component_type' => 'contribution',
                'category' => 'contribution',
                'calculation_method' => 'percentage_of_basic',
                'default_percentage' => 2.50,
                'is_taxable' => false,
                'is_deminimis' => false,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 21,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Pag-IBIG Contribution',
                'code' => 'PAGIBIG',
                'component_type' => 'contribution',
                'category' => 'contribution',
                'calculation_method' => 'percentage_of_basic',
                'default_percentage' => 1.00,
                'is_taxable' => false,
                'is_deminimis' => false,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 22,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],

            // ============================================
            // CATEGORY 5: DEDUCTIONS - WITHHOLDING TAX
            // ============================================
            [
                'name' => 'Withholding Tax',
                'code' => 'TAX',
                'component_type' => 'tax',
                'category' => 'tax',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => false,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 25,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],

            // ============================================
            // CATEGORY 6: DEDUCTIONS - LOANS
            // ============================================
            [
                'name' => 'Loan Deduction',
                'code' => 'LOAN_DEDUCTION',
                'component_type' => 'deduction',
                'category' => 'loan',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => false,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 26,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],

            // ============================================
            // CATEGORY 7: ALLOWANCES - DE MINIMIS / TAX-EXEMPT
            // ============================================
            [
                'name' => 'Rice Subsidy',
                'code' => 'RICE',
                'component_type' => 'allowance',
                'category' => 'allowance',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => false,
                'is_deminimis' => true,
                'deminimis_limit_monthly' => 2000.00,
                'deminimis_limit_annual' => 24000.00,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 30,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Clothing/Uniform Allowance',
                'code' => 'CLOTHING',
                'component_type' => 'allowance',
                'category' => 'allowance',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => false,
                'is_deminimis' => true,
                'deminimis_limit_monthly' => 1000.00,
                'deminimis_limit_annual' => 5000.00,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 31,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Laundry Allowance',
                'code' => 'LAUNDRY',
                'component_type' => 'allowance',
                'category' => 'allowance',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => false,
                'is_deminimis' => true,
                'deminimis_limit_monthly' => 300.00,
                'deminimis_limit_annual' => 3600.00,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 32,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
            [
                'name' => 'Medical/Health Allowance',
                'code' => 'MEDICAL',
                'component_type' => 'allowance',
                'category' => 'allowance',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => false,
                'is_deminimis' => true,
                'deminimis_limit_monthly' => 1000.00,
                'deminimis_limit_annual' => 5000.00,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => false,
                'display_order' => 33,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],

            // ============================================
            // CATEGORY 8: BENEFITS - 13TH MONTH PAY
            // ============================================
            [
                'name' => '13th Month Pay',
                'code' => '13TH_MONTH',
                'component_type' => 'benefit',
                'category' => 'adjustment',
                'calculation_method' => 'percentage_of_basic',
                'default_percentage' => 100.00,
                'is_taxable' => true,
                'is_13th_month' => true,
                'is_other_benefits' => false,
                'affects_sss' => false,
                'affects_philhealth' => false,
                'affects_pagibig' => false,
                'affects_gross_compensation' => true,
                'display_order' => 40,
                'is_displayed_on_payslip' => true,
                'is_active' => true,
                'is_system_component' => true,
            ],
        ];

        // Seed or update each component
        foreach ($systemComponents as $component) {
            // Add created_by for audit trail
            $component['created_by'] = $systemUser->id;
            
            SalaryComponent::updateOrCreate(
                ['code' => $component['code']], // Match by unique code
                $component // Update/create with all attributes
            );
        }

        $this->command->info('✅ SalaryComponentSeeder completed successfully!');
        $this->command->info(sprintf('📊 Total components seeded: %d', count($systemComponents)));
    }
}
