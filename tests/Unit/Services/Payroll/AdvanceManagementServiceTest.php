<?php

namespace Tests\Unit\Services\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Payroll\AdvanceManagementService;
use App\Models\CashAdvance;
use App\Models\AdvanceDeduction;
use App\Models\Employee;
use App\Models\Department;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AdvanceManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdvanceManagementService $service;
    protected User $user;
    protected Employee $employee;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdvanceManagementService::class);
        $this->user = User::factory()->create();
        
        $this->department = Department::factory()->create();
        $this->employee = Employee::factory()->create([
            'employee_type' => 'regular',
            'employment_status' => 'active',
            'hire_date' => now()->subMonths(4), // 4 months ago (meets 3-month requirement)
            'department_id' => $this->department->id,
        ]);
    }

    /**
     * Test advance creation with valid data
     * 
     * @test
     */
    public function test_creates_advance_request_successfully()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'advance_type' => 'cash_advance',
            'amount_requested' => 10000,
            'purpose' => 'Emergency medical expenses',
            'requested_date' => now()->toDateString(),
            'priority_level' => 'urgent',
        ];

        $advance = $this->service->createAdvanceRequest($data, $this->user);

        $this->assertInstanceOf(CashAdvance::class, $advance);
        $this->assertEquals('pending', $advance->approval_status);
        $this->assertEquals($this->employee->id, $advance->employee_id);
        $this->assertEquals('cash_advance', $advance->advance_type);
        $this->assertEquals(10000, $advance->amount_requested);
        $this->assertEquals('Emergency medical expenses', $advance->purpose);
        $this->assertEquals('urgent', $advance->priority_level);
        $this->assertTrue(str_starts_with($advance->advance_number, 'ADV-'));
        $this->assertEquals($this->user->id, $advance->created_by);
    }

    /**
     * Test advance creation exceeds maximum amount
     * 
     * @test
     */
    public function test_advance_creation_exceeds_maximum_amount()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'advance_type' => 'cash_advance',
            'amount_requested' => 500000, // Way above 50% of salary
            'purpose' => 'Emergency expenses',
            'requested_date' => now()->toDateString(),
            'priority_level' => 'normal',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exceeds maximum allowed');
        $this->service->createAdvanceRequest($data, $this->user);
    }

    /**
     * Test advance eligibility check for probationary employee
     * 
     * @test
     */
    public function test_rejects_advance_for_probationary_employee()
    {
        $probationaryEmployee = Employee::factory()->create([
            'employee_type' => 'probationary',
            'employment_status' => 'active',
            'hire_date' => now()->subMonths(4),
        ]);

        $eligibility = $this->service->checkEmployeeEligibility($probationaryEmployee);

        $this->assertFalse($eligibility['eligible']);
        $this->assertStringContainsString('Probationary', $eligibility['reason']);
    }

    /**
     * Test advance eligibility check for inactive employee
     * 
     * @test
     */
    public function test_rejects_advance_for_inactive_employee()
    {
        $inactiveEmployee = Employee::factory()->create([
            'employee_type' => 'regular',
            'employment_status' => 'resigned',
            'hire_date' => now()->subMonths(4),
        ]);

        $eligibility = $this->service->checkEmployeeEligibility($inactiveEmployee);

        $this->assertFalse($eligibility['eligible']);
        $this->assertStringContainsString('not actively employed', $eligibility['reason']);
    }

    /**
     * Test advance eligibility check for insufficient tenure
     * 
     * @test
     */
    public function test_rejects_advance_for_insufficient_tenure()
    {
        $newEmployee = Employee::factory()->create([
            'employee_type' => 'regular',
            'employment_status' => 'active',
            'hire_date' => now()->subMonth(), // Only 1 month employed
        ]);

        $eligibility = $this->service->checkEmployeeEligibility($newEmployee);

        $this->assertFalse($eligibility['eligible']);
        $this->assertStringContainsString('3 months', $eligibility['reason']);
    }

    /**
     * Test advance eligibility check for existing active advance
     * 
     * @test
     */
    public function test_rejects_advance_if_active_advance_exists()
    {
        // Create an active advance for the employee
        CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
        ]);

        $eligibility = $this->service->checkEmployeeEligibility($this->employee);

        $this->assertFalse($eligibility['eligible']);
        $this->assertStringContainsString('active advance', $eligibility['reason']);
    }

    /**
     * Test advance approval with deduction scheduling
     * 
     * @test
     */
    public function test_approves_advance_and_schedules_deductions()
    {
        // Create payroll periods for scheduling
        for ($i = 0; $i < 5; $i++) {
            PayrollPeriod::factory()->create([
                'pay_date' => now()->addMonths($i),
            ]);
        }

        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'pending',
            'amount_requested' => 10000,
        ]);

        $approvalData = [
            'amount_approved' => 10000,
            'deduction_schedule' => 'installments',
            'number_of_installments' => 5,
            'approval_notes' => 'Approved',
        ];

        $approved = $this->service->approveAdvance($advance, $approvalData, $this->user);

        $this->assertEquals('approved', $approved->approval_status);
        $this->assertEquals('active', $approved->deduction_status);
        $this->assertEquals(10000, $approved->amount_approved);
        $this->assertEquals(5, $approved->number_of_installments);
        $this->assertCount(5, $approved->advanceDeductions);
        $this->assertEquals($this->user->id, $approved->approved_by);
    }

    /**
     * Test advance rejection
     * 
     * @test
     */
    public function test_rejects_advance()
    {
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'pending',
        ]);

        $rejected = $this->service->rejectAdvance($advance, 'Insufficient funds available', $this->user);

        $this->assertEquals('rejected', $rejected->approval_status);
        $this->assertEquals('Insufficient funds available', $rejected->rejection_reason);
        $this->assertEquals($this->user->id, $rejected->rejected_by);
    }

    /**
     * Test advance cancellation
     * 
     * @test
     */
    public function test_cancels_advance()
    {
        // Create advance with pending deductions
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'approval_status' => 'approved',
            'deduction_status' => 'active',
        ]);

        AdvanceDeduction::factory()->create([
            'cash_advance_id' => $advance->id,
            'is_deducted' => false,
        ]);

        $cancelled = $this->service->cancelAdvance($advance, 'Employee request', $this->user);

        $this->assertEquals('cancelled', $cancelled->deduction_status);
        $this->assertEquals('Employee request', $cancelled->cancellation_reason);
        $this->assertEquals($this->user->id, $cancelled->cancelled_by);
        // Verify pending deductions are deleted
        $this->assertCount(0, $cancelled->advanceDeductions()->where('is_deducted', false)->get());
    }

    /**
     * Test maximum advance amount calculation
     * 
     * @test
     */
    public function test_calculates_maximum_advance_amount()
    {
        $maxAmount = $this->service->calculateMaxAdvanceAmount($this->employee);

        // Assuming employee has basic salary of 25000 from factory
        $expectedMax = $this->employee->payrollInfo->basic_salary * 0.50;
        $this->assertEquals($expectedMax, $maxAmount);
    }

    /**
     * Test advance number generation is unique
     * 
     * @test
     */
    public function test_generates_unique_advance_numbers()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'advance_type' => 'cash_advance',
            'amount_requested' => 5000,
            'purpose' => 'Test 1',
            'requested_date' => now()->toDateString(),
            'priority_level' => 'normal',
        ];

        $advance1 = $this->service->createAdvanceRequest($data, $this->user);
        
        $data['purpose'] = 'Test 2';
        $advance2 = $this->service->createAdvanceRequest($data, $this->user);

        $this->assertNotEquals($advance1->advance_number, $advance2->advance_number);
        $this->assertTrue(str_starts_with($advance1->advance_number, 'ADV-'));
        $this->assertTrue(str_starts_with($advance2->advance_number, 'ADV-'));
    }

    /**
     * Test eligible employee can create advance
     * 
     * @test
     */
    public function test_eligible_employee_can_create_advance()
    {
        $eligibility = $this->service->checkEmployeeEligibility($this->employee);

        $this->assertTrue($eligibility['eligible']);
        $this->assertNull($eligibility['reason']);
    }
}
