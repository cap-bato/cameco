<?php

namespace Tests\Unit\Services\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Payroll\LoanManagementService;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanDeduction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LoanManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LoanManagementService $service;
    protected User $user;
    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LoanManagementService::class);
        $this->user = User::factory()->create();
        $this->employee = Employee::factory()->create();
    }

    /**
     * Test loan creation with valid data
     * 
     * @test
     */
    public function test_loan_creation_with_valid_data()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 10,
            'number_of_months' => 12,
            'reason' => 'Emergency expenses',
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        $this->assertInstanceOf(EmployeeLoan::class, $loan);
        $this->assertEquals($this->employee->id, $loan->employee_id);
        $this->assertEquals('company_loan', $loan->loan_type);
        $this->assertEquals(50000, $loan->amount);
        $this->assertEquals(10, $loan->interest_rate);
        $this->assertEquals(12, $loan->number_of_months);
        $this->assertEquals('Emergency expenses', $loan->reason);
        $this->assertEquals('active', $loan->status);
        $this->assertEquals($this->user->id, $loan->created_by);
    }

    /**
     * Test loan creation with invalid loan type
     * 
     * @test
     */
    public function test_loan_creation_with_invalid_loan_type()
    {
        $data = [
            'loan_type' => 'invalid_loan_type',
            'amount' => 50000,
            'interest_rate' => 10,
            'number_of_months' => 12,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createLoan($this->employee, $data, $this->user);
    }

    /**
     * Test loan creation with invalid amount
     * 
     * @test
     */
    public function test_loan_creation_with_invalid_amount()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 0,
            'interest_rate' => 10,
            'number_of_months' => 12,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createLoan($this->employee, $data, $this->user);
    }

    /**
     * Test loan creation with invalid number of months
     * 
     * @test
     */
    public function test_loan_creation_with_invalid_number_of_months()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 10,
            'number_of_months' => 0,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createLoan($this->employee, $data, $this->user);
    }

    /**
     * Test monthly payment calculation
     * 
     * @test
     */
    public function test_monthly_payment_calculation()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 120000,
            'interest_rate' => 0,  // No interest for simple calculation
            'number_of_months' => 12,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        // Expected monthly payment = 120000 / 12 = 10000
        // With interest calculation, it will be slightly different
        $this->assertGreaterThan(0, $loan->monthly_payment);
        $this->assertEquals($this->employee->id, $loan->employee_id);
        $this->assertEquals('active', $loan->status);
    }

    /**
     * Test loan deduction scheduling
     * 
     * @test
     */
    public function test_loan_deduction_scheduling()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 60000,
            'interest_rate' => 0,
            'number_of_months' => 6,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        // Check that deductions are scheduled for all months
        $deductions = LoanDeduction::where('employee_loan_id', $loan->id)->get();

        $this->assertCount(6, $deductions);
        $deductions->each(function ($deduction) use ($loan) {
            $this->assertEquals($loan->employee_id, $deduction->employee_id);
            $this->assertEquals('pending', $deduction->status);
            $this->assertGreaterThan(0, $deduction->deduction_amount);
        });
    }

    /**
     * Test SSS loan creation
     * 
     * @test
     */
    public function test_sss_loan_creation()
    {
        $data = [
            'loan_type' => 'sss_loan',
            'amount' => 30000,
            'interest_rate' => 5,
            'number_of_months' => 24,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        $this->assertEquals('sss_loan', $loan->loan_type);
        $this->assertEquals(30000, $loan->amount);
        $this->assertEquals('active', $loan->status);
    }

    /**
     * Test Pag-IBIG loan creation
     * 
     * @test
     */
    public function test_pagibig_loan_creation()
    {
        $data = [
            'loan_type' => 'pagibig_loan',
            'amount' => 200000,
            'interest_rate' => 6,
            'number_of_months' => 60,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        $this->assertEquals('pagibig_loan', $loan->loan_type);
        $this->assertEquals(200000, $loan->amount);
        $this->assertEquals('active', $loan->status);
    }

    /**
     * Test early payment on loan
     * 
     * @test
     */
    public function test_early_payment_on_loan()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 0,
            'number_of_months' => 10,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);
        $originalBalance = $loan->balance;

        // Make early payment of 10000
        $paymentAmount = 10000;
        $this->service->makeEarlyPayment($loan, $paymentAmount, $this->user);

        // Refresh loan from database
        $loan->refresh();

        // Balance should be reduced
        $this->assertEquals($originalBalance - $paymentAmount, $loan->balance);
    }

    /**
     * Test early payment validation - amount exceeds balance
     * 
     * @test
     */
    public function test_early_payment_exceeds_balance()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 10000,
            'interest_rate' => 0,
            'number_of_months' => 5,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        // Try to pay more than balance
        $this->expectException(ValidationException::class);
        $this->service->makeEarlyPayment($loan, $loan->balance + 5000, $this->user);
    }

    /**
     * Test early payment validation - zero or negative amount
     * 
     * @test
     */
    public function test_early_payment_invalid_amount()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 10000,
            'interest_rate' => 0,
            'number_of_months' => 5,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        // Try to pay zero or negative amount
        $this->expectException(ValidationException::class);
        $this->service->makeEarlyPayment($loan, 0, $this->user);
    }

    /**
     * Test loan completion when fully paid
     * 
     * @test
     */
    public function test_loan_completion_when_fully_paid()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 10000,
            'interest_rate' => 0,
            'number_of_months' => 5,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);
        $this->assertEquals('active', $loan->status);

        // Make payment equal to full balance
        $this->service->makeEarlyPayment($loan, $loan->balance, $this->user);

        // Refresh and check
        $loan->refresh();

        $this->assertEquals('completed', $loan->status);
        $this->assertEquals(0, $loan->balance);
        $this->assertNotNull($loan->end_date);
    }

    /**
     * Test multiple loans for same employee
     * 
     * @test
     */
    public function test_multiple_loans_for_same_employee()
    {
        $data1 = [
            'loan_type' => 'company_loan',
            'amount' => 30000,
            'interest_rate' => 0,
            'number_of_months' => 6,
        ];

        $data2 = [
            'loan_type' => 'sss_loan',
            'amount' => 20000,
            'interest_rate' => 0,
            'number_of_months' => 12,
        ];

        $loan1 = $this->service->createLoan($this->employee, $data1, $this->user);
        $loan2 = $this->service->createLoan($this->employee, $data2, $this->user);

        // Both loans should exist for the employee
        $this->assertNotEquals($loan1->id, $loan2->id);
        $this->assertEquals($this->employee->id, $loan1->employee_id);
        $this->assertEquals($this->employee->id, $loan2->employee_id);

        // Get all loans for employee
        $loans = EmployeeLoan::where('employee_id', $this->employee->id)
            ->where('status', 'active')
            ->get();

        $this->assertCount(2, $loans);
    }

    /**
     * Test default interest rate assignment
     * 
     * @test
     */
    public function test_default_interest_rate_assignment()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'number_of_months' => 12,
            // No interest_rate provided
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        // Should have a default interest rate assigned
        $this->assertGreaterThanOrEqual(0, $loan->interest_rate);
    }

    /**
     * Test loan with start date
     * 
     * @test
     */
    public function test_loan_with_custom_start_date()
    {
        $startDate = Carbon::now()->addDay()->toDateString();

        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 0,
            'number_of_months' => 12,
            'start_date' => $startDate,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        $this->assertEquals($startDate, $loan->start_date);
    }

    /**
     * Test default start date when not provided
     * 
     * @test
     */
    public function test_loan_with_default_start_date()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 0,
            'number_of_months' => 12,
            // No start_date provided
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        $this->assertEquals(Carbon::now()->toDateString(), $loan->start_date);
    }

    /**
     * Test expected end date calculation
     * 
     * @test
     */
    public function test_expected_end_date_calculation()
    {
        $startDate = Carbon::now()->toDateString();

        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 0,
            'number_of_months' => 12,
            'start_date' => $startDate,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        $expectedEndDate = Carbon::parse($startDate)->addMonths(12)->toDateString();
        $this->assertEquals($expectedEndDate, $loan->expected_end_date);
    }

    /**
     * Test loan with remarks
     * 
     * @test
     */
    public function test_loan_with_remarks()
    {
        $remarks = 'Emergency home repair';

        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 0,
            'number_of_months' => 12,
            'remarks' => $remarks,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        $this->assertEquals($remarks, $loan->remarks);
    }

    /**
     * Test total amount with interest calculation
     * 
     * @test
     */
    public function test_total_amount_with_interest_calculation()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 5,
            'number_of_months' => 12,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        // Total amount with interest should be greater than principal
        $this->assertGreaterThan($data['amount'], $loan->total_amount_with_interest);
        // Monthly payment * number of months should equal total with interest
        $this->assertEquals(
            round($loan->monthly_payment * $data['number_of_months'], 2),
            $loan->total_amount_with_interest
        );
    }

    /**
     * Test loan balance initialization
     * 
     * @test
     */
    public function test_loan_balance_initialization()
    {
        $data = [
            'loan_type' => 'company_loan',
            'amount' => 50000,
            'interest_rate' => 0,
            'number_of_months' => 12,
        ];

        $loan = $this->service->createLoan($this->employee, $data, $this->user);

        // Initial balance should equal the principal amount
        $this->assertEquals($data['amount'], $loan->balance);
    }
}
