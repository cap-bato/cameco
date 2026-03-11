<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PayrollPeriod;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

class RegisterReportTest extends TestCase
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
    // GET /payroll/reports/register
    // =========================================================================

    public function test_payroll_officer_can_access_register_report(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/register');

        $response->assertStatus(200);
    }

    public function test_register_report_returns_real_payroll_period_data(): void
    {
        PayrollPeriod::create([
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

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/register');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->component('Payroll/Reports/Register/Index')
                 ->has('periods', 1)
        );
    }

    public function test_register_report_returns_expected_props(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/register');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->has('register_data')
                 ->has('summary')
                 ->has('periods')
                 ->has('departments')
                 ->has('filters')
        );
    }

    public function test_unauthenticated_user_cannot_access_register_report(): void
    {
        $response = $this->get('/payroll/reports/register');

        $response->assertRedirect();
    }
}
