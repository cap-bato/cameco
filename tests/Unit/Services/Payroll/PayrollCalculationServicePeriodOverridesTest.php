<?php

namespace Tests\Unit\Services\Payroll;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollConfiguration;
use App\Models\EmployeePayrollInfo;
use App\Models\GovernmentContributionRate;
use App\Services\Payroll\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayrollCalculationServicePeriodOverridesTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCalculationService $service;
    private User $creator;
    private Employee $employee;
    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(PayrollCalculationService::class);
        $this->creator = User::factory()->create();
        
        // Create a test employee with payroll info
        $this->employee = Employee::factory()->create(['employment_type' => 'Regular']);
        
        // Create government rate records
        $this->createGovernmentRates();

        // Create payroll info
        EmployeePayrollInfo::create([
            'employee_id'       => $this->employee->id,
            'salary_type'       => 'monthly',
            'basic_salary'      => 30000,
            'daily_rate'        => 1153.85,
            'hourly_rate'       => 144.23,
            'payment_method'    => 'bank_transfer',
            'tax_status'        => 'Z',
            'sss_number'        => '01-2345678-9',
            'philhealth_number' => '123456789012',
            'pagibig_number'    => '1234-5678-9012',
            'is_active'         => true,
            'effective_date'    => '2020-01-01',
            'created_by'        => $this->creator->id,
        ]);
        
        // Create a test payroll period
        $this->period = PayrollPeriod::create([
            'period_number' => '001',
            'period_name' => 'Test Period',
            'period_type' => 'regular',
            'period_start' => '2025-03-01',
            'period_end' => '2025-03-15',
            'payment_date' => '2025-03-20',
            'period_month' => '2025-03',
            'period_year' => 2025,
            'timekeeping_cutoff_date' => '2025-03-15',
            'leave_cutoff_date' => '2025-03-15',
            'adjustment_deadline' => '2025-03-15',
            'status' => 'draft',
            'created_by' => $this->creator->id,
        ]);
    }

    private function createGovernmentRates(): void
    {
        // SSS: single bracket covering all salary levels
        GovernmentContributionRate::create([
            'agency'          => 'sss',
            'rate_type'       => 'bracket',
            'compensation_min'=> 0,
            'compensation_max'=> null,
            'employee_amount' => 1350.00,
            'employer_amount' => 2850.00,
            'ec_amount'       => 10.00,
            'total_amount'    => 4210.00,
            'is_active'       => true,
            'effective_from'  => '2020-01-01',
        ]);

        // PhilHealth: premium rate
        GovernmentContributionRate::create([
            'agency'              => 'philhealth',
            'rate_type'           => 'premium_rate',
            'employee_rate'       => 2.50,
            'employer_rate'       => 2.50,
            'minimum_contribution'=> 400.00,
            'maximum_contribution'=> 3200.00,
            'is_active'           => true,
            'effective_from'      => '2020-01-01',
        ]);

        // Pag-IBIG: contribution rate
        GovernmentContributionRate::create([
            'agency'              => 'pagibig',
            'rate_type'           => 'contribution_rate',
            'compensation_min'    => 0,
            'compensation_max'    => null,
            'employee_rate'       => 2.00,
            'employer_rate'       => 2.00,
            'contribution_ceiling'=> 100.00,
            'is_active'           => true,
            'effective_from'      => '2020-01-01',
        ]);
    }

    public function test_period_override_takes_precedence_over_global_config(): void
    {
        // Set global config to per_cutoff for SSS
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'per_cutoff'],
            'philhealth' => ['timing' => 'per_cutoff'],
            'pagibig' => ['timing' => 'per_cutoff'],
            'withholding_tax' => ['timing' => 'per_cutoff'],
            'loans' => ['timing' => 'per_cutoff'],
        ]);

        // Set period override to monthly_only on 1st cutoff for SSS
        $this->period->calculation_config = [
            'deduction_timing' => [
                'sss' => ['timing' => 'monthly_only', 'apply_on_period' => 1],
            ],
        ];
        $this->period->save();

        // Test that we can calculate without errors (period override is applied)
        $result = $this->service->calculateEmployee(
            $this->employee,
            $this->period
        );

        // Result should be successful
        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result->gross_pay);
        
        // SSS should be deducted on 1st cutoff (period 1) instead of every cutoff
        $this->assertGreaterThan(0, $result->sss_contribution);
    }

    public function test_period_override_for_split_monthly_timing(): void
    {
        // Set period override to split_monthly for SSS and loans
        $this->period->calculation_config = [
            'deduction_timing' => [
                'sss' => ['timing' => 'split_monthly'],
                'loans' => ['timing' => 'split_monthly'],
            ],
        ];
        $this->period->save();

        // Create a second half period to test split_monthly
        $period2 = PayrollPeriod::create([
            'period_number' => '002',
            'period_name' => 'Test Period 2nd Half',
            'period_type' => 'regular',
            'period_start' => '2025-03-16',
            'period_end' => '2025-03-31',
            'payment_date' => '2025-04-05',
            'period_month' => '2025-03',
            'period_year' => 2025,
            'timekeeping_cutoff_date' => '2025-03-31',
            'leave_cutoff_date' => '2025-03-31',
            'adjustment_deadline' => '2025-03-31',
            'status' => 'draft',
            'created_by' => $this->creator->id,
            'calculation_config' => [
                'deduction_timing' => [
                    'sss' => ['timing' => 'split_monthly'],
                    'loans' => ['timing' => 'split_monthly'],
                ],
            ],
        ]);

        // Calculate for both periods
        $result1 = $this->service->calculateEmployee(
            $this->employee,
            $this->period
        );

        $result2 = $this->service->calculateEmployee(
            $this->employee,
            $period2
        );

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        
        // Both should have deductions due to split_monthly override
        $this->assertGreaterThan(0, $result1->sss_contribution);
        $this->assertGreaterThan(0, $result2->sss_contribution);
        
        // Each should be approximately half (within rounding)
        $sss1 = $result1->sss_contribution;
        $sss2 = $result2->sss_contribution;
        $total = $sss1 + $sss2;
        
        // Both halves should be close to the full amount
        $this->assertGreaterThan($total * 0.45, $sss1);
        $this->assertLessThan($total * 0.55, $sss1);
    }

    public function test_period_with_null_override_uses_global_config(): void
    {
        // Set global config
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'philhealth' => ['timing' => 'per_cutoff'],
            'pagibig' => ['timing' => 'per_cutoff'],
            'withholding_tax' => ['timing' => 'per_cutoff'],
            'loans' => ['timing' => 'per_cutoff'],
        ]);

        // Period has no overrides (null calculation_config)
        $this->period->calculation_config = null;
        $this->period->save();

        // Calculate for 1st cutoff (should NOT apply SSS due to global monthly_only on 2nd cutoff)
        $result = $this->service->calculateEmployee(
            $this->employee,
            $this->period
        );

        $this->assertNotNull($result);
        // SSS should be 0 on first cutoff due to global config
        $this->assertEquals(0, $result->sss_contribution);
    }

    public function test_partial_period_overrides_mixed_with_global(): void
    {
        // Set global config for all deductions
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'philhealth' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'pagibig' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'withholding_tax' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'loans' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        ]);

        // Override only SSS and PhilHealth for this period
        $this->period->calculation_config = [
            'deduction_timing' => [
                'sss' => ['timing' => 'per_cutoff'],
                'philhealth' => ['timing' => 'split_monthly'],
            ],
        ];
        $this->period->save();

        // Calculate
        $result = $this->service->calculateEmployee(
            $this->employee,
            $this->period
        );

        $this->assertNotNull($result);
        
        // SSS should apply (per_cutoff override)
        $this->assertGreaterThan(0, $result->sss_contribution);
        
        // PhilHealth should apply (split_monthly override)
        $this->assertGreaterThan(0, $result->philhealth_contribution);
        
        // Others should follow global (monthly_only on 2nd) so should be 0 on 1st cutoff
        $this->assertEquals(0, $result->pagibig_contribution);
        $this->assertEquals(0, $result->withholding_tax);
    }
}
