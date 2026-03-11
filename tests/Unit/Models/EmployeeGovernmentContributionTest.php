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

    private Employee $employee;
    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employee = Employee::factory()->create();
        $this->period   = PayrollPeriod::create([
            'period_name'  => 'January 2026',
            'period_month' => '2026-01',
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
            'status'       => 'finalized',
        ]);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_belongs_to_employee(): void
    {
        $contribution = EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'pending',
        ]);

        $this->assertTrue($contribution->employee->is($this->employee));
    }

    public function test_belongs_to_payroll_period(): void
    {
        $contribution = EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'pending',
        ]);

        $this->assertTrue($contribution->payrollPeriod->is($this->period));
    }

    public function test_belongs_to_calculated_by_user(): void
    {
        $user = User::factory()->create();

        $contribution = EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'pending',
            'calculated_by'     => $user->id,
        ]);

        $this->assertTrue($contribution->calculatedBy->is($user));
    }

    public function test_belongs_to_processed_by_user(): void
    {
        $user = User::factory()->create();

        $contribution = EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'processed',
            'processed_by'      => $user->id,
        ]);

        $this->assertTrue($contribution->processedBy->is($user));
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function test_scope_pending_returns_only_pending_records(): void
    {
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'pending',
        ]);
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'processed',
        ]);

        $pending = EmployeeGovernmentContribution::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    public function test_scope_processed_returns_only_processed_records(): void
    {
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'processed',
        ]);
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'remitted',
        ]);

        $processed = EmployeeGovernmentContribution::processed()->get();

        $this->assertCount(1, $processed);
        $this->assertEquals('processed', $processed->first()->status);
    }

    public function test_scope_remitted_returns_only_remitted_records(): void
    {
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'remitted',
        ]);
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'pending',
        ]);

        $remitted = EmployeeGovernmentContribution::remitted()->get();

        $this->assertCount(1, $remitted);
        $this->assertEquals('remitted', $remitted->first()->status);
    }

    public function test_scope_by_period_month_filters_correctly(): void
    {
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'pending',
        ]);
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-02',
            'status'            => 'pending',
        ]);

        $results = EmployeeGovernmentContribution::byPeriodMonth('2026-01')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('2026-01', $results->first()->period_month);
    }

    public function test_scope_by_status_filters_correctly(): void
    {
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'processed',
        ]);
        EmployeeGovernmentContribution::create([
            'employee_id'       => $this->employee->id,
            'payroll_period_id' => $this->period->id,
            'period_month'      => '2026-01',
            'status'            => 'remitted',
        ]);

        $results = EmployeeGovernmentContribution::byStatus('processed')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('processed', $results->first()->status);
    }

    // -------------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------------

    public function test_decimal_casts_return_numeric_values(): void
    {
        $contribution = EmployeeGovernmentContribution::create([
            'employee_id'              => $this->employee->id,
            'payroll_period_id'        => $this->period->id,
            'period_month'             => '2026-01',
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
        $contribution = EmployeeGovernmentContribution::create([
            'employee_id'         => $this->employee->id,
            'payroll_period_id'   => $this->period->id,
            'period_month'        => '2026-01',
            'status'              => 'pending',
            'is_sss_exempted'     => false,
            'is_philhealth_exempted' => false,
            'is_pagibig_exempted' => true,
        ]);

        $fresh = $contribution->fresh();
        $this->assertFalse($fresh->is_sss_exempted);
        $this->assertFalse($fresh->is_philhealth_exempted);
        $this->assertTrue($fresh->is_pagibig_exempted);
    }
}
