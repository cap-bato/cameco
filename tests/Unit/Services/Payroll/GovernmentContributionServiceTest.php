<?php

namespace Tests\Unit\Services\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Payroll\GovernmentContributionService;
use App\Models\EmployeeGovernmentContribution;
use App\Models\GovernmentRemittance;
use App\Models\PayrollPeriod;
use App\Models\Employee;

class GovernmentContributionServiceTest extends TestCase
{
    use RefreshDatabase;

    private GovernmentContributionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GovernmentContributionService::class);
    }

    private function makePeriod(string $month, string $suffix = 'A'): PayrollPeriod
    {
        [$year, $m] = explode('-', $month);
        $start = "{$year}-{$m}-01";
        $end   = date('Y-m-t', strtotime($start));
        return PayrollPeriod::create([
            'period_number'           => "{$month}-{$suffix}",
            'period_name'             => date('F Y', strtotime($start)) . " – Period {$suffix}",
            'period_start'            => $start,
            'period_end'              => $end,
            'payment_date'            => date('Y-m-d', strtotime($end . ' +5 days')),
            'period_month'            => $month,
            'period_year'             => (int) $year,
            'timekeeping_cutoff_date' => $end,
            'leave_cutoff_date'       => $end,
            'adjustment_deadline'     => date('Y-m-d', strtotime($end . ' +2 days')),
        ]);
    }

    // =========================================================================
    // getPeriods()
    // =========================================================================

    public function test_get_periods_returns_empty_when_no_periods_exist(): void
    {
        $result = $this->service->getPeriods();
        $this->assertCount(0, $result);
    }

    public function test_get_periods_returns_correct_shape(): void
    {
        $this->makePeriod('2026-01');
        $this->makePeriod('2026-02', 'A');

        $result = $this->service->getPeriods();

        $this->assertCount(2, $result);
        $first = $result->first();
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('month', $first);
        $this->assertArrayHasKey('start_date', $first);
        $this->assertArrayHasKey('end_date', $first);
        $this->assertArrayHasKey('status', $first);
    }

    public function test_get_periods_ordered_newest_first(): void
    {
        $this->makePeriod('2026-01');
        $this->makePeriod('2026-03', 'A');

        $result = $this->service->getPeriods();

        $this->assertEquals('2026-03-01', $result->first()['start_date']);
    }

    // =========================================================================
    // getContributions()
    // =========================================================================

    public function test_get_contributions_returns_empty_when_no_periods_exist(): void
    {
        $result = $this->service->getContributions('sss', null);
        $this->assertCount(0, $result);
    }

    public function test_get_contributions_returns_empty_collection_for_explicit_null_period_with_no_data(): void
    {
        // No PayrollPeriod exists, so resolveLatestPeriodId returns null
        $result = $this->service->getContributions('philhealth', null);
        $this->assertCount(0, $result);
    }

    public function test_get_contributions_sss_maps_correct_fields(): void
    {
        $period   = $this->makePeriod('2026-01');
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'              => $employee->id,
            'payroll_period_id'        => $period->id,
            'period_month'             => '2026-01',
            'period_start'             => '2026-01-01',
            'period_end'               => '2026-01-31',
            'basic_salary'             => 0,
            'taxable_income'           => 0,
            'status'                   => 'processed',
            'gross_compensation'       => 20000.00,
            'sss_employee_contribution' => 363.00,
            'sss_employer_contribution' => 759.70,
            'sss_ec_contribution'       => 10.00,
            'sss_total_contribution'    => 1122.70,
        ]);

        $result = $this->service->getContributions('sss', $period->id);

        $this->assertCount(1, $result);
        $row = $result->first();

        $this->assertArrayHasKey('employee_contribution', $row);
        $this->assertArrayHasKey('employer_contribution', $row);
        $this->assertArrayHasKey('ec_contribution', $row);
        $this->assertArrayHasKey('total_contribution', $row);
        $this->assertArrayHasKey('monthly_compensation', $row);
        $this->assertArrayHasKey('is_processed', $row);
        $this->assertEquals(363.00, $row['employee_contribution']);
        $this->assertEquals(759.70, $row['employer_contribution']);
        $this->assertEquals(10.00, $row['ec_contribution']);
        $this->assertEquals(1122.70, $row['total_contribution']);
    }

    public function test_get_contributions_philhealth_maps_correct_fields(): void
    {
        $period   = $this->makePeriod('2026-01');
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'                   => $employee->id,
            'payroll_period_id'             => $period->id,
            'period_month'                  => '2026-01',
            'period_start'                  => '2026-01-01',
            'period_end'                    => '2026-01-31',
            'gross_compensation'            => 0,
            'taxable_income'                => 0,
            'status'                        => 'processed',
            'basic_salary'                  => 20000.00,
            'philhealth_employee_contribution' => 450.00,
            'philhealth_employer_contribution' => 450.00,
            'philhealth_total_contribution'    => 900.00,
        ]);

        $result = $this->service->getContributions('philhealth', $period->id);

        $this->assertCount(1, $result);
        $row = $result->first();

        $this->assertArrayHasKey('employee_premium', $row);
        $this->assertArrayHasKey('employer_premium', $row);
        $this->assertArrayHasKey('total_premium', $row);
        $this->assertArrayHasKey('monthly_basic', $row);
        $this->assertEquals(450.00, $row['employee_premium']);
        $this->assertEquals(900.00, $row['total_premium']);
    }

    public function test_get_contributions_pagibig_maps_correct_fields(): void
    {
        $period   = $this->makePeriod('2026-01');
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'                => $employee->id,
            'payroll_period_id'          => $period->id,
            'period_month'               => '2026-01',
            'period_start'               => '2026-01-01',
            'period_end'                 => '2026-01-31',
            'basic_salary'               => 0,
            'gross_compensation'         => 0,
            'taxable_income'             => 0,
            'status'                     => 'processed',
            'pagibig_compensation_base'  => 5000.00,
            'pagibig_employee_contribution' => 100.00,
            'pagibig_employer_contribution' => 100.00,
            'pagibig_total_contribution'    => 200.00,
        ]);

        $result = $this->service->getContributions('pagibig', $period->id);

        $this->assertCount(1, $result);
        $row = $result->first();

        $this->assertArrayHasKey('employee_contribution', $row);
        $this->assertArrayHasKey('employer_contribution', $row);
        $this->assertArrayHasKey('total_contribution', $row);
        $this->assertArrayHasKey('employee_rate', $row);
        $this->assertEquals(100.00, $row['employee_contribution']);
        $this->assertEquals(200.00, $row['total_contribution']);
    }

    public function test_get_contributions_bir_maps_correct_fields(): void
    {
        $period   = $this->makePeriod('2026-01');
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'       => $employee->id,
            'payroll_period_id' => $period->id,
            'period_month'      => '2026-01',
            'period_start'      => '2026-01-01',
            'period_end'        => '2026-01-31',
            'basic_salary'      => 0,
            'status'            => 'processed',
            'gross_compensation' => 20000.00,
            'taxable_income'     => 18000.00,
            'withholding_tax'    => 350.00,
        ]);

        $result = $this->service->getContributions('bir', $period->id);

        $this->assertCount(1, $result);
        $row = $result->first();

        $this->assertArrayHasKey('withholding_tax', $row);
        $this->assertArrayHasKey('taxable_income', $row);
        $this->assertArrayHasKey('gross_compensation', $row);
        $this->assertEquals(350.00, $row['withholding_tax']);
    }

    public function test_get_contributions_returns_empty_for_unknown_agency(): void
    {
        $period = $this->makePeriod('2026-01');

        $result = $this->service->getContributions('unknown_agency', $period->id);
        $this->assertCount(0, $result);
    }

    // =========================================================================
    // getSummary()
    // =========================================================================

    public function test_get_summary_returns_sss_structure_when_no_data(): void
    {
        $summary = $this->service->getSummary('sss', null);

        $this->assertArrayHasKey('total_employees', $summary);
        $this->assertArrayHasKey('total_monthly_compensation', $summary);
        $this->assertArrayHasKey('total_employee_contribution', $summary);
        $this->assertArrayHasKey('total_employer_contribution', $summary);
        $this->assertArrayHasKey('total_ec_contribution', $summary);
        $this->assertArrayHasKey('total_contribution', $summary);
        $this->assertArrayHasKey('last_remittance_date', $summary);
        $this->assertArrayHasKey('next_due_date', $summary);
        $this->assertArrayHasKey('pending_remittances', $summary);
        $this->assertEquals(0, $summary['total_employees']);
    }

    public function test_get_summary_returns_philhealth_structure_when_no_data(): void
    {
        $summary = $this->service->getSummary('philhealth', null);

        $this->assertArrayHasKey('total_employees', $summary);
        $this->assertArrayHasKey('total_monthly_basic', $summary);
        $this->assertArrayHasKey('total_employee_premium', $summary);
        $this->assertArrayHasKey('total_employer_premium', $summary);
        $this->assertArrayHasKey('total_premium', $summary);
        $this->assertArrayHasKey('indigent_members', $summary);
        $this->assertEquals(0, $summary['total_employees']);
    }

    public function test_get_summary_returns_pagibig_structure_when_no_data(): void
    {
        $summary = $this->service->getSummary('pagibig', null);

        $this->assertArrayHasKey('total_employees', $summary);
        $this->assertArrayHasKey('total_monthly_compensation', $summary);
        $this->assertArrayHasKey('total_employee_contribution', $summary);
        $this->assertArrayHasKey('total_employer_contribution', $summary);
        $this->assertArrayHasKey('total_contribution', $summary);
        $this->assertArrayHasKey('total_loan_deductions', $summary);
    }

    public function test_get_summary_returns_bir_structure_when_no_data(): void
    {
        $summary = $this->service->getSummary('bir', null);

        $this->assertArrayHasKey('total_employees', $summary);
        $this->assertArrayHasKey('total_gross_compensation', $summary);
        $this->assertArrayHasKey('total_withholding_tax', $summary);
        $this->assertArrayHasKey('next_deadline', $summary);
        $this->assertEquals(0, $summary['total_employees']);
    }

    public function test_get_summary_sss_aggregates_contributions_correctly(): void
    {
        $period   = $this->makePeriod('2026-01');
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'              => $employee->id,
            'payroll_period_id'        => $period->id,
            'period_month'             => '2026-01',
            'period_start'             => '2026-01-01',
            'period_end'               => '2026-01-31',
            'basic_salary'             => 0,
            'taxable_income'           => 0,
            'status'                   => 'processed',
            'gross_compensation'       => 20000.00,
            'sss_employee_contribution' => 363.00,
            'sss_employer_contribution' => 759.70,
            'sss_ec_contribution'       => 10.00,
            'sss_total_contribution'    => 1132.70,
        ]);

        $summary = $this->service->getSummary('sss', $period->id);

        $this->assertEquals(1, $summary['total_employees']);
        $this->assertEquals(20000.00, $summary['total_monthly_compensation']);
        $this->assertEquals(363.00, $summary['total_employee_contribution']);
        $this->assertEquals(759.70, $summary['total_employer_contribution']);
        $this->assertEquals(10.00, $summary['total_ec_contribution']);
        $this->assertEquals(1132.70, $summary['total_contribution']);
    }

    // =========================================================================
    // getRemittances()
    // =========================================================================

    public function test_get_remittances_returns_empty_when_none_exist(): void
    {
        $result = $this->service->getRemittances('sss');
        $this->assertCount(0, $result);
    }

    public function test_get_remittances_returns_correct_shape(): void
    {
        $period = $this->makePeriod('2026-01');

        GovernmentRemittance::create([
            'payroll_period_id' => $period->id,
            'agency'            => 'sss',
            'remittance_type'   => 'monthly',
            'status'            => 'paid',
            'remittance_month'  => '2026-01',
            'period_start'      => '2026-01-01',
            'period_end'        => '2026-01-31',
            'due_date'          => '2026-02-15',
            'total_amount'      => 5000.00,
            'employee_share'    => 2000.00,
            'employer_share'    => 3000.00,
            'is_late'           => false,
        ]);

        $result = $this->service->getRemittances('sss');

        $this->assertCount(1, $result);
        $row = $result->first();

        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('period_id', $row);
        $this->assertArrayHasKey('month', $row);
        $this->assertArrayHasKey('remittance_amount', $row);
        $this->assertArrayHasKey('due_date', $row);
        $this->assertArrayHasKey('payment_date', $row);
        $this->assertArrayHasKey('payment_reference', $row);
        $this->assertArrayHasKey('status', $row);
        $this->assertArrayHasKey('has_penalty', $row);
        $this->assertArrayHasKey('penalty_amount', $row);
        $this->assertArrayHasKey('contributions', $row);
        $this->assertArrayHasKey('employee_share', $row['contributions']);
        $this->assertArrayHasKey('employer_share', $row['contributions']);
        $this->assertArrayHasKey('ec_share', $row['contributions']);

        $this->assertEquals(5000.00, $row['remittance_amount']);
        $this->assertEquals('paid', $row['status']);
    }

    public function test_get_remittances_filters_by_agency(): void
    {
        $period = $this->makePeriod('2026-01');

        GovernmentRemittance::create([
            'payroll_period_id' => $period->id,
            'agency'            => 'sss',
            'remittance_type'   => 'monthly',
            'status'            => 'paid',
            'remittance_month'  => '2026-01',
            'period_start'      => '2026-01-01',
            'period_end'        => '2026-01-31',
            'due_date'          => '2026-02-15',
            'total_amount'      => 5000.00,
            'employee_share'    => 2000.00,
            'employer_share'    => 3000.00,
            'is_late'           => false,
        ]);
        GovernmentRemittance::create([
            'payroll_period_id' => $period->id,
            'agency'            => 'philhealth',
            'remittance_type'   => 'monthly',
            'status'            => 'paid',
            'remittance_month'  => '2026-01',
            'period_start'      => '2026-01-01',
            'period_end'        => '2026-01-31',
            'due_date'          => '2026-02-15',
            'total_amount'      => 3000.00,
            'employee_share'    => 1500.00,
            'employer_share'    => 1500.00,
            'is_late'           => false,
        ]);

        $sss      = $this->service->getRemittances('sss');
        $philHealth = $this->service->getRemittances('philhealth');

        $this->assertCount(1, $sss);
        $this->assertCount(1, $philHealth);
    }

    public function test_get_remittances_respects_limit(): void
    {
        $period = $this->makePeriod('2026-01');

        foreach (range(1, 5) as $i) {
            GovernmentRemittance::create([
                'payroll_period_id' => $period->id,
                'agency'            => 'sss',
                'remittance_type'   => 'monthly',
                'status'            => 'paid',
                'remittance_month'  => "2025-0{$i}",
                'period_start'      => "2025-0{$i}-01",
                'period_end'        => "2025-0{$i}-28",
                'due_date'          => "2025-0{$i}-15",
                'total_amount'      => 1000.00,
                'employee_share'    => 500.00,
                'employer_share'    => 500.00,
                'is_late'           => false,
            ]);
        }

        $result = $this->service->getRemittances('sss', 3);
        $this->assertCount(3, $result);
    }
}
