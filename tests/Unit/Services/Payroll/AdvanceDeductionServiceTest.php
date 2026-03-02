<?php

namespace Tests\Unit\Services\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Payroll\AdvanceDeductionService;
use App\Models\CashAdvance;
use App\Models\AdvanceDeduction;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\EmployeePayrollCalculation;
use App\Models\User;
use Carbon\Carbon;

class AdvanceDeductionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdvanceDeductionService $service;
    protected Employee $employee;
    protected PayrollPeriod $payrollPeriod;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdvanceDeductionService::class);
        $this->user = User::factory()->create();
        
        $this->employee = Employee::factory()->create([
            'employee_type' => 'regular',
        ]);

        $this->payrollPeriod = PayrollPeriod::factory()->create([
            'pay_date' => now(),
        ]);
    }

    /**
     * Test getting pending deductions for employee in payroll period
     * 
     * @test
     */
    public function test_gets_pending_deductions_for_employee()
    {
        // Create active advance
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
        ]);

        // Create pending deduction
        AdvanceDeduction::factory()->create([
            'cash_advance_id' => $advance->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'is_deducted' => false,
            'deduction_amount' => 2000,
        ]);

        $deductions = $this->service->getPendingDeductionsForEmployee(
            $this->employee->id,
            $this->payrollPeriod->id
        );

        $this->assertCount(1, $deductions);
        $this->assertEquals(2000, $deductions[0]['deduction_amount']);
    }

    /**
     * Test getting total pending deductions for employee
     * 
     * @test
     */
    public function test_gets_total_pending_deductions_for_employee()
    {
        // Create advance with multiple deductions
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
        ]);

        AdvanceDeduction::factory()->create([
            'cash_advance_id' => $advance->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'is_deducted' => false,
            'deduction_amount' => 2000,
        ]);

        $total = $this->service->getTotalPendingDeductionsForEmployee(
            $this->employee->id,
            $this->payrollPeriod->id
        );

        $this->assertEquals(2000, $total);
    }

    /**
     * Test processing deductions successfully
     * 
     * @test
     */
    public function test_processes_deductions_successfully()
    {
        // Create active advance with pending deduction
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
            'amount_approved' => 2000,
            'total_deducted' => 0,
            'remaining_balance' => 2000,
        ]);

        AdvanceDeduction::factory()->create([
            'cash_advance_id' => $advance->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'is_deducted' => false,
            'deduction_amount' => 2000,
        ]);

        $result = $this->service->processDeductions(
            $this->employee->id,
            $this->payrollPeriod->id,
            5000, // Available net pay
            null
        );

        $this->assertEquals(2000, $result['total_deduction']);
        $this->assertEquals(1, $result['deductions_applied']);
        $this->assertFalse($result['insufficient_pay']);

        // Verify deduction is marked as deducted
        $deduction = AdvanceDeduction::first();
        $this->assertTrue($deduction->is_deducted);
    }

    /**
     * Test processing deductions with insufficient net pay (partial deduction)
     * 
     * @test
     */
    public function test_processes_deductions_with_insufficient_net_pay()
    {
        // Create active advance requiring 5000 deduction
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
            'amount_approved' => 5000,
            'total_deducted' => 0,
            'remaining_balance' => 5000,
        ]);

        AdvanceDeduction::factory()->create([
            'cash_advance_id' => $advance->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'is_deducted' => false,
            'deduction_amount' => 5000,
        ]);

        // Process with only 3000 available
        $result = $this->service->processDeductions(
            $this->employee->id,
            $this->payrollPeriod->id,
            3000, // Only 3000 available
            null
        );

        $this->assertLessThanOrEqual(3000, $result['total_deduction']);
        $this->assertTrue($result['insufficient_pay']);
    }

    /**
     * Test deduction marks advance as completed when fully paid
     * 
     * @test
     */
    public function test_marks_advance_as_completed_when_fully_paid()
    {
        // Create advance with small amount
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
            'amount_approved' => 1000,
            'total_deducted' => 0,
            'remaining_balance' => 1000,
        ]);

        AdvanceDeduction::factory()->create([
            'cash_advance_id' => $advance->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'is_deducted' => false,
            'deduction_amount' => 1000,
        ]);

        $this->service->processDeductions(
            $this->employee->id,
            $this->payrollPeriod->id,
            5000,
            null
        );

        $advance->refresh();
        $this->assertEquals('completed', $advance->deduction_status);
        $this->assertEquals(0, $advance->remaining_balance);
    }

    /**
     * Test early repayment of advance
     * 
     * @test
     */
    public function test_allows_early_repayment()
    {
        // Create advance with multiple pending deductions
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
            'amount_approved' => 6000,
            'total_deducted' => 2000,
            'remaining_balance' => 4000,
            'installments_completed' => 1,
        ]);

        // Create 3 pending deductions
        for ($i = 0; $i < 3; $i++) {
            AdvanceDeduction::factory()->create([
                'cash_advance_id' => $advance->id,
                'is_deducted' => false,
            ]);
        }

        // Repay 4000 (full remaining balance)
        $repaid = $this->service->allowEarlyRepayment($advance, 4000);

        $this->assertEquals('completed', $repaid->deduction_status);
        $this->assertEquals(4000, $repaid->total_deducted);
        $this->assertEquals(0, $repaid->remaining_balance);
        // Verify pending deductions are deleted
        $this->assertCount(0, $repaid->advanceDeductions()->where('is_deducted', false)->get());
    }

    /**
     * Test early repayment fails if amount exceeds remaining balance
     * 
     * @test
     */
    public function test_early_repayment_fails_if_exceeds_balance()
    {
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
            'remaining_balance' => 2000,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exceeds remaining balance');
        $this->service->allowEarlyRepayment($advance, 3000);
    }

    /**
     * Test early repayment fails if advance is not active
     * 
     * @test
     */
    public function test_early_repayment_fails_if_not_active()
    {
        $advance = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'completed',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only active advances');
        $this->service->allowEarlyRepayment($advance, 1000);
    }

    /**
     * Test no deductions to process returns zero
     * 
     * @test
     */
    public function test_returns_zero_when_no_deductions_exist()
    {
        $result = $this->service->processDeductions(
            $this->employee->id,
            $this->payrollPeriod->id,
            5000,
            null
        );

        $this->assertEquals(0, $result['total_deduction']);
        $this->assertEquals(0, $result['deductions_applied']);
        $this->assertFalse($result['insufficient_pay']);
    }

    /**
     * Test multiple deductions are processed correctly
     * 
     * @test
     */
    public function test_processes_multiple_deductions()
    {
        // Create two active advances
        $advance1 = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
        ]);

        $advance2 = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'deduction_status' => 'active',
        ]);

        // Create deductions for each
        AdvanceDeduction::factory()->create([
            'cash_advance_id' => $advance1->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'is_deducted' => false,
            'deduction_amount' => 1500,
        ]);

        AdvanceDeduction::factory()->create([
            'cash_advance_id' => $advance2->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'is_deducted' => false,
            'deduction_amount' => 2000,
        ]);

        $result = $this->service->processDeductions(
            $this->employee->id,
            $this->payrollPeriod->id,
            5000,
            null
        );

        $this->assertEquals(3500, $result['total_deduction']);
        $this->assertEquals(2, $result['deductions_applied']);
    }
}
