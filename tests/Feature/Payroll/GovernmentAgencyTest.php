<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\EmployeeGovernmentContribution;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

class GovernmentAgencyTest extends TestCase
{
    use RefreshDatabase;

    private User $payrollOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'Payroll Officer', 'guard_name' => 'web']);

        $this->payrollOfficer = User::factory()->create();
        $this->payrollOfficer->assignRole($role);
    }

    // =========================================================================
    // GET /payroll/government/bir
    // =========================================================================

    public function test_bir_page_returns_200(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/bir');

        $response->assertStatus(200);
    }

    public function test_bir_page_returns_data_from_employee_government_contributions(): void
    {
        $period   = $this->createPayrollPeriod();
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'       => $employee->id,
            'payroll_period_id' => $period->id,
            'period_start'      => '2026-01-01',
            'period_end'        => '2026-01-31',
            'period_month'      => '2026-01',
            'basic_salary'      => 20000,
            'gross_compensation' => 22000,
            'taxable_income'    => 20000,
            'withholding_tax'   => 1500,
        ]);

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/bir');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->component('Payroll/Government/BIR/Index')
                 ->has('summary')
        );
    }

    // =========================================================================
    // GET /payroll/government/sss
    // =========================================================================

    public function test_sss_page_returns_200(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/sss');

        $response->assertStatus(200);
    }

    public function test_sss_page_returns_real_sss_contribution_data(): void
    {
        $period   = $this->createPayrollPeriod();
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'               => $employee->id,
            'payroll_period_id'         => $period->id,
            'period_start'              => '2026-01-01',
            'period_end'                => '2026-01-31',
            'period_month'              => '2026-01',
            'basic_salary'              => 20000,
            'gross_compensation'        => 22000,
            'taxable_income'            => 20000,
            'sss_employee_contribution' => 900,
            'sss_employer_contribution' => 1800,
            'sss_total_contribution'    => 2700,
        ]);

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/sss');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->component('Payroll/Government/SSS/Index')
                 ->has('contributions')
                 ->has('periods')
        );
    }

    // =========================================================================
    // GET /payroll/government/philhealth
    // =========================================================================

    public function test_philhealth_page_returns_200(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/philhealth');

        $response->assertStatus(200);
    }

    public function test_philhealth_page_returns_real_philhealth_data(): void
    {
        $period   = $this->createPayrollPeriod();
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'                       => $employee->id,
            'payroll_period_id'                 => $period->id,
            'period_start'                      => '2026-01-01',
            'period_end'                        => '2026-01-31',
            'period_month'                      => '2026-01',
            'basic_salary'                      => 20000,
            'gross_compensation'                => 22000,
            'taxable_income'                    => 20000,
            'philhealth_employee_contribution'  => 550,
            'philhealth_employer_contribution'  => 550,
            'philhealth_total_contribution'     => 1100,
        ]);

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/philhealth');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->component('Payroll/Government/PhilHealth/Index')
                 ->has('contributions')
                 ->has('periods')
        );
    }

    // =========================================================================
    // GET /payroll/government/pagibig
    // =========================================================================

    public function test_pagibig_page_returns_200(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/pagibig');

        $response->assertStatus(200);
    }

    public function test_pagibig_page_returns_real_pagibig_data(): void
    {
        $period   = $this->createPayrollPeriod();
        $employee = Employee::factory()->create();

        EmployeeGovernmentContribution::create([
            'employee_id'                    => $employee->id,
            'payroll_period_id'              => $period->id,
            'period_start'                   => '2026-01-01',
            'period_end'                     => '2026-01-31',
            'period_month'                   => '2026-01',
            'basic_salary'                   => 20000,
            'gross_compensation'             => 22000,
            'taxable_income'                 => 20000,
            'pagibig_employee_contribution'  => 100,
            'pagibig_employer_contribution'  => 100,
            'pagibig_total_contribution'     => 200,
        ]);

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/pagibig');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->component('Payroll/Government/PagIbig/Index')
                 ->has('contributions')
                 ->has('periods')
        );
    }

    // =========================================================================
    // Access control
    // =========================================================================

    public function test_unauthenticated_user_cannot_access_government_agency_pages(): void
    {
        $this->get('/payroll/government/bir')->assertRedirect();
        $this->get('/payroll/government/sss')->assertRedirect();
        $this->get('/payroll/government/philhealth')->assertRedirect();
        $this->get('/payroll/government/pagibig')->assertRedirect();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createPayrollPeriod(): PayrollPeriod
    {
        return PayrollPeriod::create([
            'period_number'           => '2026-01-A',
            'period_name'             => 'January 2026',
            'period_start'            => '2026-01-01',
            'period_end'              => '2026-01-31',
            'payment_date'            => '2026-02-05',
            'period_month'            => '2026-01',
            'period_year'             => 2026,
            'timekeeping_cutoff_date' => '2026-01-31',
            'leave_cutoff_date'       => '2026-01-31',
            'adjustment_deadline'     => '2026-02-02',
        ]);
    }
}
