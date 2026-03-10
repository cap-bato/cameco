<?php

namespace Tests\Unit\Services\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Employee;
use App\Models\EmployeePayrollInfo;
use App\Models\GovernmentContributionRate;
use App\Models\PayrollConfiguration;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\AllowanceDeductionService;
use App\Services\Payroll\EmployeePayrollInfoService;
use App\Services\Payroll\LoanManagementService;
use App\Services\Payroll\PayrollCalculationService;
use App\Services\Payroll\SalaryComponentService;
use Carbon\Carbon;

/**
 * Unit tests for configurable deduction timing logic in PayrollCalculationService.
 *
 * Covers three timing modes for SSS, PhilHealth, Pag-IBIG, Withholding Tax, and Loans:
 *   - monthly_only   : deduction applied only on a specific cutoff (1 or 2)
 *   - per_cutoff     : deduction applied on every cutoff
 *   - split_monthly  : deduction applied every cutoff at half the monthly amount
 */
class PayrollCalculationServiceDeductionTimingTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;
    private EmployeePayrollInfo $payrollInfo;
    private PayrollCalculationService $service;

    // Known loan amount returned by the mocked LoanManagementService
    private const MOCK_LOAN_AMOUNT = 500.00;

    // Known SSS employee amount (from seeded bracket)
    private const SSS_EMPLOYEE_AMOUNT = 1350.00;

    protected function setUp(): void
    {
        parent::setUp();

        $creator = User::factory()->create();

        $this->employee = Employee::factory()->create(['employment_type' => 'Regular']);

        $this->seedGovernmentRates();

        $this->payrollInfo = EmployeePayrollInfo::create([
            'employee_id'       => $this->employee->id,
            'salary_type'       => 'monthly',
            'basic_salary'      => 30000,
            'daily_rate'        => 1153.85,
            'hourly_rate'       => 144.23,
            'payment_method'    => 'bank_transfer',
            'tax_status'        => 'Z', // Z = zero withholding tax, keeps tax assertions simple
            'sss_number'        => '01-2345678-9',
            'philhealth_number' => '123456789012',
            'pagibig_number'    => '1234-5678-9012',
            'is_active'         => true,
            'effective_date'    => '2020-01-01',
            'created_by'        => $creator->id,
        ]);

        $this->service = $this->buildService(loanAmount: self::MOCK_LOAN_AMOUNT);
    }

    // =========================================================================
    // Scenario 1: monthly_only — current default behavior
    // =========================================================================

    public function test_monthly_only_deductions_are_zero_on_first_cutoff(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'sss'             => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'philhealth'      => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'pagibig'         => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'withholding_tax' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'loans'           => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        ]);

        $period = $this->createPeriod('2026-03-01', '2026-03-15');

        $calc = $this->service->calculateEmployee($this->employee, $period);

        $this->assertEquals(0.0, $calc->sss_contribution,        'SSS should be 0 on 1st cutoff with monthly_only');
        $this->assertEquals(0.0, $calc->philhealth_contribution, 'PhilHealth should be 0 on 1st cutoff with monthly_only');
        $this->assertEquals(0.0, $calc->pagibig_contribution,    'Pag-IBIG should be 0 on 1st cutoff with monthly_only');
        $this->assertEquals(0.0, $calc->withholding_tax,         'WHT should be 0 on 1st cutoff with monthly_only');
        $this->assertEquals(0.0, $calc->total_loan_deductions,   'Loans should be 0 on 1st cutoff with monthly_only');
    }

    public function test_monthly_only_deductions_are_applied_on_second_cutoff(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'sss'             => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'philhealth'      => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'pagibig'         => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'withholding_tax' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'loans'           => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        ]);

        $period = $this->createPeriod('2026-03-16', '2026-03-31');

        $calc = $this->service->calculateEmployee($this->employee, $period);

        $this->assertEquals(self::SSS_EMPLOYEE_AMOUNT, (float) $calc->sss_contribution,
            'SSS should equal full bracket amount on 2nd cutoff with monthly_only');
        $this->assertGreaterThan(0, $calc->philhealth_contribution, 'PhilHealth should be > 0 on 2nd cutoff');
        $this->assertGreaterThan(0, $calc->pagibig_contribution,    'Pag-IBIG should be > 0 on 2nd cutoff');
        $this->assertEquals(self::MOCK_LOAN_AMOUNT, (float) $calc->total_loan_deductions,
            'Loans should equal full mock amount on 2nd cutoff with monthly_only');
    }

    public function test_monthly_only_on_first_period_applies_on_first_cutoff_only(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'monthly_only', 'apply_on_period' => 1],
        ]);

        $period1 = $this->createPeriod('2026-03-01', '2026-03-15');
        $period2 = $this->createPeriod('2026-03-16', '2026-03-31');

        $calc1 = $this->service->calculateEmployee($this->employee, $period1);
        $calc2 = $this->service->calculateEmployee($this->employee, $period2);

        $this->assertEquals(self::SSS_EMPLOYEE_AMOUNT, (float) $calc1->sss_contribution,
            'SSS should be applied on 1st cutoff when apply_on_period=1');
        $this->assertEquals(0.0, (float) $calc2->sss_contribution,
            'SSS should be 0 on 2nd cutoff when apply_on_period=1');
    }

    // =========================================================================
    // Scenario 2: per_cutoff — deduction every period
    // =========================================================================

    public function test_per_cutoff_deductions_apply_on_first_cutoff(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'sss'        => ['timing' => 'per_cutoff'],
            'philhealth' => ['timing' => 'per_cutoff'],
            'pagibig'    => ['timing' => 'per_cutoff'],
            'loans'      => ['timing' => 'per_cutoff'],
        ]);

        $period = $this->createPeriod('2026-03-01', '2026-03-15');

        $calc = $this->service->calculateEmployee($this->employee, $period);

        $this->assertEquals(self::SSS_EMPLOYEE_AMOUNT, (float) $calc->sss_contribution,
            'SSS should be applied in full on 1st cutoff with per_cutoff');
        $this->assertGreaterThan(0, $calc->philhealth_contribution,
            'PhilHealth should be applied on 1st cutoff with per_cutoff');
        $this->assertGreaterThan(0, $calc->pagibig_contribution,
            'Pag-IBIG should be applied on 1st cutoff with per_cutoff');
        $this->assertEquals(self::MOCK_LOAN_AMOUNT, (float) $calc->total_loan_deductions,
            'Loans should be applied on 1st cutoff with per_cutoff');
    }

    public function test_per_cutoff_deductions_apply_on_second_cutoff(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'sss'    => ['timing' => 'per_cutoff'],
            'loans'  => ['timing' => 'per_cutoff'],
        ]);

        $period = $this->createPeriod('2026-03-16', '2026-03-31');

        $calc = $this->service->calculateEmployee($this->employee, $period);

        $this->assertEquals(self::SSS_EMPLOYEE_AMOUNT, (float) $calc->sss_contribution,
            'SSS should be applied in full on 2nd cutoff with per_cutoff');
        $this->assertEquals(self::MOCK_LOAN_AMOUNT, (float) $calc->total_loan_deductions,
            'Loans should be applied on 2nd cutoff with per_cutoff');
    }

    // =========================================================================
    // Scenario 3: split_monthly — half each cutoff
    // =========================================================================

    public function test_split_monthly_sss_is_half_on_each_cutoff(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'split_monthly'],
        ]);

        $period1 = $this->createPeriod('2026-03-01', '2026-03-15');
        $period2 = $this->createPeriod('2026-03-16', '2026-03-31');

        $calc1 = $this->service->calculateEmployee($this->employee, $period1);
        $calc2 = $this->service->calculateEmployee($this->employee, $period2);

        $expectedHalf = self::SSS_EMPLOYEE_AMOUNT * 0.5;

        $this->assertEquals($expectedHalf, (float) $calc1->sss_contribution,
            'SSS should be half the monthly amount on 1st cutoff with split_monthly');
        $this->assertEquals($expectedHalf, (float) $calc2->sss_contribution,
            'SSS should be half the monthly amount on 2nd cutoff with split_monthly');
    }

    public function test_split_monthly_sss_totals_full_monthly_amount(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'split_monthly'],
        ]);

        $period1 = $this->createPeriod('2026-03-01', '2026-03-15');
        $period2 = $this->createPeriod('2026-03-16', '2026-03-31');

        $calc1 = $this->service->calculateEmployee($this->employee, $period1);
        $calc2 = $this->service->calculateEmployee($this->employee, $period2);

        $total = (float) $calc1->sss_contribution + (float) $calc2->sss_contribution;

        $this->assertEquals(self::SSS_EMPLOYEE_AMOUNT, $total,
            'Split-monthly SSS contributions across both cutoffs should equal the full monthly bracket amount');
    }

    public function test_split_monthly_loans_is_half_each_cutoff(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'loans' => ['timing' => 'split_monthly'],
        ]);

        $period1 = $this->createPeriod('2026-03-01', '2026-03-15');
        $period2 = $this->createPeriod('2026-03-16', '2026-03-31');

        $calc1 = $this->service->calculateEmployee($this->employee, $period1);
        $calc2 = $this->service->calculateEmployee($this->employee, $period2);

        $expectedHalf = self::MOCK_LOAN_AMOUNT * 0.5;

        $this->assertEquals($expectedHalf, (float) $calc1->total_loan_deductions,
            'Loan should be half on 1st cutoff with split_monthly');
        $this->assertEquals($expectedHalf, (float) $calc2->total_loan_deductions,
            'Loan should be half on 2nd cutoff with split_monthly');
    }

    // =========================================================================
    // Scenario 4: Default fallback (no PayrollConfiguration in DB)
    // =========================================================================

    public function test_default_fallback_behaves_like_monthly_only_on_second_cutoff(): void
    {
        // No PayrollConfiguration record — service falls back to hardcoded defaults
        $this->assertDatabaseCount('payroll_configurations', 0);

        $period1 = $this->createPeriod('2026-03-01', '2026-03-15');
        $period2 = $this->createPeriod('2026-03-16', '2026-03-31');

        $calc1 = $this->service->calculateEmployee($this->employee, $period1);
        $calc2 = $this->service->calculateEmployee($this->employee, $period2);

        $this->assertEquals(0.0, (float) $calc1->sss_contribution,
            'Default: SSS should be 0 on 1st cutoff');
        $this->assertEquals(self::SSS_EMPLOYEE_AMOUNT, (float) $calc2->sss_contribution,
            'Default: SSS should be full amount on 2nd cutoff');
    }

    // =========================================================================
    // Scenario 5: Mixed configuration per deduction type
    // =========================================================================

    public function test_mixed_config_each_deduction_respects_own_timing(): void
    {
        PayrollConfiguration::set('deduction_timing', [
            'sss'        => ['timing' => 'split_monthly'],            // half each cutoff
            'philhealth' => ['timing' => 'monthly_only', 'apply_on_period' => 2], // 2nd only
            'loans'      => ['timing' => 'per_cutoff'],               // every cutoff
        ]);

        $period1 = $this->createPeriod('2026-03-01', '2026-03-15');

        $calc1 = $this->service->calculateEmployee($this->employee, $period1);

        // SSS: split_monthly → half amount on 1st cutoff
        $this->assertEquals(self::SSS_EMPLOYEE_AMOUNT * 0.5, (float) $calc1->sss_contribution,
            'SSS split_monthly: half amount on 1st cutoff');

        // PhilHealth: monthly_only period 2 → zero on 1st cutoff
        $this->assertEquals(0.0, (float) $calc1->philhealth_contribution,
            'PhilHealth monthly_only: zero on 1st cutoff');

        // Loans: per_cutoff → full amount on 1st cutoff
        $this->assertEquals(self::MOCK_LOAN_AMOUNT, (float) $calc1->total_loan_deductions,
            'Loans per_cutoff: full amount on 1st cutoff');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a PayrollCalculationService with mocked sub-services.
     * The mocks return known, controlled values so tests only exercise timing logic.
     */
    private function buildService(float $loanAmount = 0.0): PayrollCalculationService
    {
        $payrollInfoService = $this->createMock(EmployeePayrollInfoService::class);
        $payrollInfoService->method('getActivePayrollInfo')->willReturn($this->payrollInfo);

        $componentService = $this->createMock(SalaryComponentService::class);
        $componentService->method('getEmployeeComponents')->willReturn(collect());

        $allowanceService = $this->createMock(AllowanceDeductionService::class);
        $allowanceService->method('getActiveAllowances')->willReturn(collect());
        $allowanceService->method('getActiveDeductions')->willReturn(collect());

        $loanService = $this->createMock(LoanManagementService::class);
        $loanService->method('processLoanDeduction')->willReturn($loanAmount);

        return new PayrollCalculationService(
            $payrollInfoService,
            $componentService,
            $allowanceService,
            $loanService,
        );
    }

    /**
     * Seed minimal government contribution rate records so that SSS, PhilHealth,
     * and Pag-IBIG calculations return non-zero values without requiring a full seeder run.
     */
    private function seedGovernmentRates(): void
    {
        // SSS: single bracket covering all salary levels
        GovernmentContributionRate::create([
            'agency'          => 'sss',
            'rate_type'       => 'bracket',
            'compensation_min'=> 0,
            'compensation_max'=> null,
            'employee_amount' => self::SSS_EMPLOYEE_AMOUNT,
            'employer_amount' => 2850.00,
            'ec_amount'       => 10.00,
            'total_amount'    => 4210.00,
            'is_active'       => true,
            'effective_from'  => '2020-01-01',
        ]);

        // PhilHealth: premium rate (5% total, 2.5% employee, min ₱400 / max ₱3200 total)
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

        // Pag-IBIG: contribution rate for all salary levels, ₱100 ceiling
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

    /**
     * Create a PayrollPeriod with the minimum required fields.
     */
    private function createPeriod(string $startDate, string $endDate): PayrollPeriod
    {
        static $counter = 0;
        $counter++;

        $start = Carbon::parse($startDate);

        return PayrollPeriod::create([
            'period_number'            => 'TEST-' . $counter . '-' . $startDate,
            'period_name'              => 'Test Period ' . $startDate,
            'period_start'             => $startDate,
            'period_end'               => $endDate,
            'payment_date'             => $endDate,
            'period_month'             => $start->format('Y-m'),
            'period_year'              => $start->year,
            'period_type'              => 'regular',
            'timekeeping_cutoff_date'  => $endDate,
            'leave_cutoff_date'        => $endDate,
            'adjustment_deadline'      => $endDate,
            'status'                   => 'draft',
        ]);
    }
}
