<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PayrollPeriod;
use App\Models\GovernmentRemittance;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

class GovernmentRemittancesTest extends TestCase
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
    // GET /payroll/government/remittances
    // =========================================================================

    public function test_payroll_officer_can_access_remittances_page(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/remittances');

        $response->assertStatus(200);
    }

    public function test_remittances_page_returns_data_from_government_remittances_table(): void
    {
        $period = $this->createPayrollPeriod();

        GovernmentRemittance::create([
            'payroll_period_id' => $period->id,
            'agency'            => 'sss',
            'status'            => 'pending',
            'remittance_type'   => 'contribution',
            'remittance_month'  => '2026-01',
            'period_start'      => '2026-01-01',
            'period_end'        => '2026-01-31',
            'due_date'          => '2026-02-15',
            'total_amount'      => 5000.00,
            'employee_share'    => 2000.00,
            'employer_share'    => 3000.00,
            'is_late'           => false,
        ]);

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/remittances');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->component('Payroll/Government/Remittances/Index')
                 ->has('remittances', 1)
                 ->has('summary')
        );
    }

    public function test_remittances_page_returns_expected_props(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/government/remittances');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->has('remittances')
                 ->has('periods')
                 ->has('summary')
                 ->has('calendarEvents')
        );
    }

    public function test_unauthenticated_user_cannot_access_remittances_page(): void
    {
        $response = $this->get('/payroll/government/remittances');

        $response->assertRedirect();
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
