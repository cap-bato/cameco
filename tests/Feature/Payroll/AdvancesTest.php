<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

class AdvancesTest extends TestCase
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
    // GET /payroll/advances
    // =========================================================================

    public function test_payroll_officer_can_access_advances_page(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/advances');

        $response->assertStatus(200);
    }

    public function test_advances_page_returns_data_from_employee_loans(): void
    {
        $employee = Employee::factory()->create();

        EmployeeLoan::create([
            'employee_id'      => $employee->id,
            'loan_type'        => 'cash_advance',
            'loan_number'      => 'ADV-20260101-0001',
            'principal_amount' => 10000,
            'total_loan_amount' => 10000,
            'remaining_balance' => 10000,
            'loan_date'        => '2026-01-01',
            'status'           => 'pending',
        ]);

        $response = $this->actingAs($this->payrollOfficer)->get('/payroll/advances');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) =>
            $page->has('advances')
                 ->has('advances.data', 1)
        );
    }

    // =========================================================================
    // POST /payroll/advances
    // =========================================================================

    public function test_post_advances_creates_employee_loan_with_cash_advance_type(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->payrollOfficer)->postJson('/payroll/advances', [
            'employee_id'      => $employee->id,
            'advance_type'     => 'cash_advance',
            'amount_requested' => 5000,
            'purpose'          => 'Emergency medical expense',
            'requested_date'   => '2026-01-15',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('employee_loans', [
            'employee_id' => $employee->id,
            'loan_type'   => 'cash_advance',
            'status'      => 'pending',
        ]);
    }

    public function test_post_advances_validates_required_fields(): void
    {
        $response = $this->actingAs($this->payrollOfficer)->postJson('/payroll/advances', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id', 'advance_type', 'amount_requested', 'purpose', 'requested_date']);
    }

    public function test_unauthenticated_user_cannot_access_advances_page(): void
    {
        $response = $this->get('/payroll/advances');

        $response->assertRedirect();
    }
}
