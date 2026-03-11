<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PayrollPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $payrollOfficer;
    private User $regularEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset cached roles/permissions so Spatie picks up DB changes
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $officerRole  = Role::create(['name' => 'Payroll Officer', 'guard_name' => 'web']);
        $employeeRole = Role::create(['name' => 'Employee',        'guard_name' => 'web']);

        // Create permissions required by the routes
        $loansPermission        = Permission::create(['name' => 'payroll.loans.view',               'guard_name' => 'web']);
        $allowancesPermission   = Permission::create(['name' => 'payroll.allowances-deductions.view', 'guard_name' => 'web']);

        // Payroll Officer gets both permissions via the role
        $officerRole->givePermissionTo([$loansPermission, $allowancesPermission]);

        // Create a user with the Payroll Officer role
        $this->payrollOfficer = User::factory()->create();
        $this->payrollOfficer->assignRole($officerRole);

        // Create a regular employee user (role only — no payroll permissions)
        $this->regularEmployee = User::factory()->create();
        $this->regularEmployee->assignRole($employeeRole);
    }

    // =========================================================================
    // /payroll/loans
    // =========================================================================

    public function test_payroll_officer_can_access_loans_page(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/loans');
        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_loans_page(): void
    {
        $response = $this->actingAs($this->regularEmployee)->get('/payroll/loans');
        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_is_redirected_from_loans(): void
    {
        $response = $this->get('/payroll/loans');
        $response->assertRedirect();
    }

    // =========================================================================
    // /payroll/allowances-deductions
    // =========================================================================

    public function test_payroll_officer_can_access_allowances_deductions_page(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/allowances-deductions');
        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_allowances_deductions_page(): void
    {
        $response = $this->actingAs($this->regularEmployee)->get('/payroll/allowances-deductions');
        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_is_redirected_from_allowances_deductions(): void
    {
        $response = $this->get('/payroll/allowances-deductions');
        $response->assertRedirect();
    }

    // =========================================================================
    // Superadmin fallback
    // =========================================================================

    public function test_superadmin_can_access_loans_page(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superadminRole = Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        $superadminRole->givePermissionTo(Permission::findByName('payroll.loans.view', 'web'));

        $superadmin = User::factory()->create();
        $superadmin->assignRole($superadminRole);

        $response = $this->actingAs($superadmin)->get('/payroll/loans');
        $response->assertStatus(200);
    }
}
