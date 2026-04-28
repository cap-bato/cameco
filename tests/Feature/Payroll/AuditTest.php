<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PayrollPeriod;
use App\Models\PayrollApprovalHistory;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

class AuditTest extends TestCase
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
    // GET /payroll/reports/audit
    // =========================================================================

    public function test_payroll_officer_can_access_audit_page(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/audit');

        $response->assertStatus(200);
    }

    public function test_audit_page_returns_paginated_activity_log_data(): void
    {
        $period = PayrollPeriod::create([
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

        PayrollApprovalHistory::create([
            'payroll_period_id' => $period->id,
            'approval_step'     => 'payroll_officer_submit',
            'action'            => 'submit',
            'status_from'       => 'draft',
            'status_to'         => 'pending_approval',
            'user_id'           => $this->payrollOfficer->id,
            'user_name'         => $this->payrollOfficer->name,
            'user_role'         => 'Payroll Officer',
            'created_at'        => now(),
        ]);

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/audit');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->component('Payroll/Reports/Audit')
                 ->has('auditLogs')
                 ->has('changeHistory')
        );
    }

    public function test_audit_page_returns_expected_props(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/reports/audit');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->has('auditLogs')
                 ->has('changeHistory')
                 ->has('filters')
        );
    }

    public function test_unauthenticated_user_cannot_access_audit_page(): void
    {
        $response = $this->get('/payroll/reports/audit');

        $response->assertRedirect();
    }
}
