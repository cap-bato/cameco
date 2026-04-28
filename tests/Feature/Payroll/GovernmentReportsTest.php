<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PayrollPeriod;
use App\Models\GovernmentReport;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

class GovernmentReportsTest extends TestCase
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
    // GET /payroll/reports/government
    // =========================================================================

    public function test_payroll_officer_can_access_government_reports_page(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/government');

        $response->assertStatus(200);
    }

    public function test_government_reports_aggregates_from_government_reports_table(): void
    {
        $period = $this->createPayrollPeriod();

        GovernmentReport::create([
            'payroll_period_id' => $period->id,
            'agency'            => 'sss',
            'report_type'       => 'r3',
            'report_name'       => 'SSS R3 Report',
            'report_period'     => '2026-01',
            'file_name'         => 'sss_r3.csv',
            'file_path'         => 'reports/sss_r3.csv',
            'file_type'         => 'csv',
            'status'            => 'draft',
        ]);

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/government');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->component('Payroll/Reports/Government/Index')
                 ->where('reports_summary.total_reports_generated', 1)
        );
    }

    public function test_government_reports_returns_expected_props(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/government');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->has('reports_summary')
                 ->has('sss_reports')
                 ->has('philhealth_reports')
                 ->has('pagibig_reports')
                 ->has('bir_reports')
        );
    }

    public function test_unauthenticated_user_cannot_access_government_reports_page(): void
    {
        $response = $this->get('/payroll/reports/government');

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
