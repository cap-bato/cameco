<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\EmployeeGovernmentContribution;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;

class EmployeeGovernmentContributionTest extends TestCase
{
    use RefreshDatabase;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = $this->makePeriod('2026-01', 'A');
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

    /**
     * Creates a fresh Employee per call so the UNIQUE(employee_id, payroll_period_id)
     * constraint is never violated when multiple contributions share the same period.
     */
    private function makeContribution(array $overrides = []): EmployeeGovernmentContribution
    {
        return EmployeeGovernmentContribution::create(array_merge([
            'employee_id'        => Employee::factory()->create()->id,
            'payroll_period_id'  => $this->period->id,
            'period_month'       => '2026-01',
            'period_start'       => '2026-01-01',
            'period_end'         => '2026-01-31',
            'basic_salary'       => 0,
            'gross_compensation' => 0,
            'taxable_income'     => 0,
            'status'             => 'pending',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_belongs_to_employee(): void
    {
        $employee     = Employee::factory()->create();
        $contribution = $this->makeContribution(['employee_id' => $employee->id]);
        $this->assertTrue($contribution->employee->is($employee));
    }

    public function test_belongs_to_payroll_period(): void
    {
        $contribution = $this->makeContribution();
        $this->assertTrue($contribution->payrollPeriod->is($this->period));
    }

    public function test_belongs_to_calculated_by_user(): void
    {
        $user = User::factory()->create();

        $contribution = $this->makeContribution(['calculated_by' => $user->id]);

        $this->assertTrue($contribution->calculatedBy->is($user));
    }

    public function test_belongs_to_processed_by_user(): void
    {
        $user = User::factory()->create();

        $contribution = $this->makeContribution(['status' => 'processed', 'processed_by' => $user->id]);

        $this->assertTrue($contribution->processedBy->is($user));
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function test_scope_pending_returns_only_pending_records(): void
    {
        $this->makeContribution(['status' => 'pending']);
        $this->makeContribution(['status' => 'processed']);

        $pending = EmployeeGovernmentContribution::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    public function test_scope_processed_returns_only_processed_records(): void
    {
        $this->makeContribution(['status' => 'processed']);
        $this->makeContribution(['status' => 'remitted']);

        $processed = EmployeeGovernmentContribution::processed()->get();

        $this->assertCount(1, $processed);
        $this->assertEquals('processed', $processed->first()->status);
    }

    public function test_scope_remitted_returns_only_remitted_records(): void
    {
        $this->makeContribution(['status' => 'remitted']);
        $this->makeContribution(['status' => 'pending']);

        $remitted = EmployeeGovernmentContribution::remitted()->get();

        $this->assertCount(1, $remitted);
        $this->assertEquals('remitted', $remitted->first()->status);
    }

    public function test_scope_by_period_month_filters_correctly(): void
    {
        $this->makeContribution(['period_month' => '2026-01']);
        $this->makeContribution(['period_month' => '2026-02']);

        $results = EmployeeGovernmentContribution::byPeriodMonth('2026-01')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('2026-01', $results->first()->period_month);
    }

    public function test_scope_by_status_filters_correctly(): void
    {
        $this->makeContribution(['status' => 'processed']);
        $this->makeContribution(['status' => 'remitted']);

        $results = EmployeeGovernmentContribution::byStatus('processed')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('processed', $results->first()->status);
    }

    // -------------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------------

    public function test_decimal_casts_return_numeric_values(): void
    {
        $contribution = $this->makeContribution([
            'status'                   => 'processed',
            'sss_employee_contribution' => 363.00,
            'sss_employer_contribution' => 759.70,
            'sss_total_contribution'    => 1122.70,
        ]);

        $fresh = $contribution->fresh();
        $this->assertIsNumeric($fresh->sss_employee_contribution);
        $this->assertIsNumeric($fresh->sss_employer_contribution);
        $this->assertIsNumeric($fresh->sss_total_contribution);
    }

    public function test_boolean_casts_return_bool(): void
    {
        $contribution = $this->makeContribution([
            'is_sss_exempted'        => false,
            'is_philhealth_exempted' => false,
            'is_pagibig_exempted'    => true,
        ]);

        $fresh = $contribution->fresh();
        $this->assertFalse($fresh->is_sss_exempted);
        $this->assertFalse($fresh->is_philhealth_exempted);
        $this->assertTrue($fresh->is_pagibig_exempted);
    }
}
