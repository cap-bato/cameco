<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use App\Models\CashAdvance;
use App\Models\PayrollPeriod;
use App\Services\Payroll\AdvanceManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class AdvancesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Employee $employee;
    protected Department $department;
    protected AdvanceManagementService $advanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->advanceService = app(AdvanceManagementService::class);
        $this->user = User::factory()->create();
        
        $this->department = Department::factory()->create();
        $this->employee = Employee::factory()->create([
            'employee_type' => 'regular',
            'employment_status' => 'active',
            'hire_date' => now()->subMonths(4),
            'department_id' => $this->department->id,
        ]);
    }

    /**
     * Test advances index page displays correctly
     * 
     * @test
     */
    public function test_displays_advances_index_page()
    {
        $this->actingAs($this->user);

        // Create some test advances
        CashAdvance::factory()->count(3)->create();

        $response = $this->get(route('payroll.advances.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Advances/Index')
            ->has('advances')
            ->has('employees')
        );
    }

    /**
     * Test advances index page with filters
     * 
     * @test
     */
    public function test_advances_index_with_filters()
    {
        $this->actingAs($this->user);

        $advance = CashAdvance::factory()->create([
            'approval_status' => 'pending',
        ]);

        $response = $this->get(route('payroll.advances.index', ['status' => 'pending']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Advances/Index')
            ->has('advances')
        );
    }

    /**
     * Test creating advance request with valid data
     * 
     * @test
     */
    public function test_creates_advance_request_with_valid_data()
    {
        $this->actingAs($this->user);

        $data = [
            'employee_id' => $this->employee->id,
            'advance_type' => 'cash_advance',
            'amount_requested' => 10000,
            'purpose' => 'Emergency medical expenses',
            'requested_date' => now()->toDateString(),
            'priority_level' => 'urgent',
        ];

        $response = $this->post(route('payroll.advances.store'), $data);

        $response->assertRedirect(route('payroll.advances.index'));
        $this->assertDatabaseHas('employee_cash_advances', [
            'employee_id' => $this->employee->id,
            'amount_requested' => 10000,
            'approval_status' => 'pending',
        ]);
    }

    /**
     * Test creating advance with invalid amount
     * 
     * @test
     */
    public function test_fails_to_create_advance_with_invalid_amount()
    {
        $this->actingAs($this->user);

        $data = [
            'employee_id' => $this->employee->id,
            'advance_type' => 'cash_advance',
            'amount_requested' => 500, // Below minimum of 1000
            'purpose' => 'Emergency',
            'requested_date' => now()->toDateString(),
            'priority_level' => 'normal',
        ];

        $response = $this->post(route('payroll.advances.store'), $data);

        $response->assertSessionHasErrors(['amount_requested']);
        $this->assertDatabaseMissing('employee_cash_advances', [
            'amount_requested' => 500,
        ]);
    }

    /**
     * Test creating advance with invalid employee
     * 
     * @test
     */
    public function test_fails_to_create_advance_with_nonexistent_employee()
    {
        $this->actingAs($this->user);

        $data = [
            'employee_id' => 99999,
            'advance_type' => 'cash_advance',
            'amount_requested' => 10000,
            'purpose' => 'Emergency',
            'requested_date' => now()->toDateString(),
            'priority_level' => 'normal',
        ];

        $response = $this->post(route('payroll.advances.store'), $data);

        $response->assertSessionHasErrors(['employee_id']);
    }

    /**
     * Test approving advance
     * 
     * @test
     */
    public function test_approves_pending_advance()
    {
        $this->actingAs($this->user);

        // Create payroll periods for deduction scheduling
        PayrollPeriod::factory()->count(5)->create();

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'pending',
            'amount_requested' => 10000,
        ]);

        $data = [
            'amount_approved' => 10000,
            'deduction_schedule' => 'installments',
            'number_of_installments' => 5,
            'approval_notes' => 'Approved for employee',
        ];

        $response = $this->post(route('payroll.advances.approve', $advance->id), $data);

        $response->assertRedirect(route('payroll.advances.index'));
        $this->assertDatabaseHas('employee_cash_advances', [
            'id' => $advance->id,
            'approval_status' => 'approved',
            'deduction_status' => 'active',
        ]);
    }

    /**
     * Test approving advance with reduced amount
     * 
     * @test
     */
    public function test_approves_advance_with_reduced_amount()
    {
        $this->actingAs($this->user);

        PayrollPeriod::factory()->count(3)->create();

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'pending',
            'amount_requested' => 10000,
        ]);

        $data = [
            'amount_approved' => 8000, // Approved for less
            'deduction_schedule' => 'installments',
            'number_of_installments' => 2,
            'approval_notes' => 'Approved for lesser amount',
        ];

        $response = $this->post(route('payroll.advances.approve', $advance->id), $data);

        $response->assertRedirect(route('payroll.advances.index'));
        $this->assertDatabaseHas('employee_cash_advances', [
            'id' => $advance->id,
            'amount_approved' => 8000,
        ]);
    }

    /**
     * Test fails to approve with amount exceeding request
     * 
     * @test
     */
    public function test_fails_to_approve_with_excessive_amount()
    {
        $this->actingAs($this->user);

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'pending',
            'amount_requested' => 10000,
        ]);

        $data = [
            'amount_approved' => 15000, // More than requested
            'deduction_schedule' => 'installments',
            'number_of_installments' => 5,
            'approval_notes' => 'Approved',
        ];

        $response = $this->post(route('payroll.advances.approve', $advance->id), $data);

        $response->assertSessionHasErrors(['amount_approved']);
    }

    /**
     * Test rejecting advance
     * 
     * @test
     */
    public function test_rejects_pending_advance()
    {
        $this->actingAs($this->user);

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'pending',
        ]);

        $data = [
            'rejection_reason' => 'Insufficient company funds available at this time',
        ];

        $response = $this->post(route('payroll.advances.reject', $advance->id), $data);

        $response->assertRedirect(route('payroll.advances.index'));
        $this->assertDatabaseHas('employee_cash_advances', [
            'id' => $advance->id,
            'approval_status' => 'rejected',
        ]);
    }

    /**
     * Test fails to reject with short reason
     * 
     * @test
     */
    public function test_fails_to_reject_with_short_reason()
    {
        $this->actingAs($this->user);

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'pending',
        ]);

        $data = [
            'rejection_reason' => 'No', // Too short
        ];

        $response = $this->post(route('payroll.advances.reject', $advance->id), $data);

        $response->assertSessionHasErrors(['rejection_reason']);
    }

    /**
     * Test cancelling active advance
     * 
     * @test
     */
    public function test_cancels_active_advance()
    {
        $this->actingAs($this->user);

        PayrollPeriod::factory()->count(3)->create();

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'approved',
            'deduction_status' => 'active',
        ]);

        $data = [
            'cancellation_reason' => 'Employee requested cancellation due to change in circumstances',
        ];

        $response = $this->post(route('payroll.advances.cancel', $advance->id), $data);

        $response->assertRedirect(route('payroll.advances.index'));
        $this->assertDatabaseHas('employee_cash_advances', [
            'id' => $advance->id,
            'deduction_status' => 'cancelled',
        ]);
    }

    /**
     * Test fails to cancel with short reason
     * 
     * @test
     */
    public function test_fails_to_cancel_with_short_reason()
    {
        $this->actingAs($this->user);

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
        ]);

        $data = [
            'cancellation_reason' => 'Short', // Too short
        ];

        $response = $this->post(route('payroll.advances.cancel', $advance->id), $data);

        $response->assertSessionHasErrors(['cancellation_reason']);
    }

    /**
     * Test cannot approve non-pending advance
     * 
     * @test
     */
    public function test_fails_to_approve_non_pending_advance()
    {
        $this->actingAs($this->user);

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'approved', // Already approved
        ]);

        $data = [
            'amount_approved' => 10000,
            'deduction_schedule' => 'installments',
            'number_of_installments' => 5,
            'approval_notes' => 'Approved',
        ];

        $response = $this->post(route('payroll.advances.approve', $advance->id), $data);

        $response->assertSessionHasErrors();
    }

    /**
     * Test cannot reject non-pending advance
     * 
     * @test
     */
    public function test_fails_to_reject_non_pending_advance()
    {
        $this->actingAs($this->user);

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'rejected', // Already rejected
        ]);

        $data = [
            'rejection_reason' => 'This advance is already rejected, cannot reject again',
        ];

        $response = $this->post(route('payroll.advances.reject', $advance->id), $data);

        $response->assertSessionHasErrors();
    }

    /**
     * Test advance is created with generated number
     * 
     * @test
     */
    public function test_advance_created_with_unique_number()
    {
        $this->actingAs($this->user);

        $data = [
            'employee_id' => $this->employee->id,
            'advance_type' => 'cash_advance',
            'amount_requested' => 5000,
            'purpose' => 'Test advance 1',
            'requested_date' => now()->toDateString(),
            'priority_level' => 'normal',
        ];

        $response1 = $this->post(route('payroll.advances.store'), $data);
        $response1->assertRedirect();

        $data['purpose'] = 'Test advance 2';
        $response2 = $this->post(route('payroll.advances.store'), $data);
        $response2->assertRedirect();

        $advances = CashAdvance::latest()->take(2)->get();
        $this->assertNotEquals($advances[0]->advance_number, $advances[1]->advance_number);
    }
}
