<?php

namespace Tests\Unit\Services\Payroll;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Payroll\EmployeePayrollInfoService;
use App\Models\Employee;
use App\Models\EmployeePayrollInfo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class EmployeePayrollInfoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmployeePayrollInfoService $service;
    protected User $user;
    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmployeePayrollInfoService::class);
        $this->user = User::factory()->create();
        $this->employee = Employee::factory()->create();
    }

    /**
     * Test payroll info creation with valid data
     * 
     * @test
     */
    public function test_payroll_info_creation_with_valid_data()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
            'sss_number' => '01-2345678-9',
            'philhealth_number' => '123456789012',
            'pagibig_number' => '1234-5678-9012',
            'tin_number' => '123-456-789-000',
            'bank_name' => 'BPI',
            'bank_account_number' => '1234567890',
        ];

        $payrollInfo = $this->service->createPayrollInfo($data, $this->user);

        $this->assertInstanceOf(EmployeePayrollInfo::class, $payrollInfo);
        $this->assertEquals($this->employee->id, $payrollInfo->employee_id);
        $this->assertEquals('monthly', $payrollInfo->salary_type);
        $this->assertEquals(30000, $payrollInfo->basic_salary);
        $this->assertEquals('S', $payrollInfo->tax_status);
        $this->assertEquals('01-2345678-9', $payrollInfo->sss_number);
        $this->assertTrue($payrollInfo->is_active);
        $this->assertEquals($this->user->id, $payrollInfo->created_by);
    }

    /**
     * Test derived rate calculations (daily_rate and hourly_rate)
     * 
     * @test
     */
    public function test_derived_rate_calculations()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 22000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
        ];

        $payrollInfo = $this->service->createPayrollInfo($data, $this->user);

        // Daily rate should be basic_salary / 22 working days
        $expectedDailyRate = 22000 / 22;
        $this->assertEquals($expectedDailyRate, $payrollInfo->daily_rate);

        // Hourly rate should be daily_rate / 8 hours
        $expectedHourlyRate = $expectedDailyRate / 8;
        $this->assertEquals($expectedHourlyRate, $payrollInfo->hourly_rate);
    }

    /**
     * Test SSS bracket auto-detection based on salary
     * 
     * @test
     */
    public function test_sss_bracket_auto_detection()
    {
        // Test different salary brackets
        $brackets = [
            3000 => 'E1',    // < 4250
            5000 => 'E2',    // < 8000
            12000 => 'E3',   // < 16000
            20000 => 'E4',   // < 30000
            35000 => 'E5',   // < 40000
            50000 => 'E6',   // >= 40000
        ];

        foreach ($brackets as $salary => $expectedBracket) {
            $data = [
                'employee_id' => Employee::factory()->create()->id,
                'salary_type' => 'monthly',
                'basic_salary' => $salary,
                'payment_method' => 'bank_transfer',
                'tax_status' => 'S',
            ];

            $payrollInfo = $this->service->createPayrollInfo($data, $this->user);
            $this->assertEquals($expectedBracket, $payrollInfo->sss_bracket, "Failed for salary: {$salary}");
        }
    }

    /**
     * Test government number validation
     * 
     * @test
     */
    public function test_government_number_validation_invalid_sss()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
            'sss_number' => 'invalid-sss-number',
        ];

        $this->expectException(ValidationException::class);
        $this->service->createPayrollInfo($data, $this->user);
    }

    /**
     * Test government number validation for PhilHealth
     * 
     * @test
     */
    public function test_government_number_validation_invalid_philhealth()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
            'philhealth_number' => '12345',  // Too short
        ];

        $this->expectException(ValidationException::class);
        $this->service->createPayrollInfo($data, $this->user);
    }

    /**
     * Test government number validation for Pag-IBIG
     * 
     * @test
     */
    public function test_government_number_validation_invalid_pagibig()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
            'pagibig_number' => 'invalid-format',
        ];

        $this->expectException(ValidationException::class);
        $this->service->createPayrollInfo($data, $this->user);
    }

    /**
     * Test salary history tracking - creating new record on salary change
     * 
     * @test
     */
    public function test_salary_history_tracking_on_update()
    {
        // Create initial payroll info
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
        ];

        $payrollInfo = $this->service->createPayrollInfo($data, $this->user);
        $firstId = $payrollInfo->id;
        $this->assertTrue($payrollInfo->is_active);
        $this->assertNull($payrollInfo->end_date);

        // Update with salary change - must include salary_type
        $updateData = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 35000,
            'payment_method' => 'cash',
            'tax_status' => 'S',
        ];

        $updatedPayrollInfo = $this->service->updatePayrollInfo($payrollInfo, $updateData, $this->user);

        // New record should be created
        $this->assertNotEquals($firstId, $updatedPayrollInfo->id);
        $this->assertTrue($updatedPayrollInfo->is_active);
        $this->assertEquals(35000, $updatedPayrollInfo->basic_salary);

        // Old record should be marked inactive
        $oldRecord = EmployeePayrollInfo::find($firstId);
        $this->assertFalse($oldRecord->is_active);
        $this->assertNotNull($oldRecord->end_date);
    }

    /**
     * Test non-salary field update without creating new record
     * 
     * @test
     */
    public function test_non_salary_update_without_creating_new_record()
    {
        // Create initial payroll info
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
            'bank_account_number' => '1234567890',
        ];

        $payrollInfo = $this->service->createPayrollInfo($data, $this->user);
        $firstId = $payrollInfo->id;

        // Update non-salary fields only - must still include salary_type and other required fields
        $updateData = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,  // Keep same salary
            'payment_method' => 'cash',
            'tax_status' => 'S',
            'bank_account_number' => '0987654321',
        ];

        $updatedPayrollInfo = $this->service->updatePayrollInfo($payrollInfo, $updateData, $this->user);

        // Same record should be updated
        $this->assertEquals($firstId, $updatedPayrollInfo->id);
        $this->assertEquals('cash', $updatedPayrollInfo->payment_method);
        $this->assertTrue($updatedPayrollInfo->is_active);
    }

    /**
     * Test getting active payroll info for employee
     * 
     * @test
     */
    public function test_get_active_payroll_info()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
        ];

        $payrollInfo = $this->service->createPayrollInfo($data, $this->user);

        $activePayrollInfo = $this->service->getActivePayrollInfo($this->employee);

        $this->assertNotNull($activePayrollInfo);
        $this->assertEquals($payrollInfo->id, $activePayrollInfo->id);
        $this->assertTrue($activePayrollInfo->is_active);
    }

    /**
     * Test payroll history tracking
     * 
     * @test
     */
    public function test_payroll_history_tracking()
    {
        // Create initial payroll info
        $data1 = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
        ];

        $payroll1 = $this->service->createPayrollInfo($data1, $this->user);

        // Update with salary change - include all required fields
        $data2 = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 35000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
        ];
        $payroll2 = $this->service->updatePayrollInfo($payroll1, $data2, $this->user);

        // Get history
        $history = $this->service->getPayrollHistory($this->employee);

        // Verify both records exist with correct data
        $this->assertCount(2, $history);
        $salaries = array_collect($history)->pluck('basic_salary')->toArray();
        $this->assertContains(30000, $salaries);
        $this->assertContains(35000, $salaries);
        $isActiveStatuses = array_collect($history)->pluck('is_active')->toArray();
        $this->assertContains(true, $isActiveStatuses); // Current should be active
        $this->assertContains(false, $isActiveStatuses); // Old should be inactive
    }

    /**
     * Test effective date handling
     * 
     * @test
     */
    public function test_effective_date_handling()
    {
        $effectiveDate = Carbon::now()->subDay()->toDateString();

        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
            'effective_date' => $effectiveDate,
        ];

        $payrollInfo = $this->service->createPayrollInfo($data, $this->user);

        $this->assertEquals($effectiveDate, $payrollInfo->effective_date->toDateString());
    }

    /**
     * Test default effective date when not provided
     * 
     * @test
     */
    public function test_default_effective_date_when_not_provided()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
        ];

        $payrollInfo = $this->service->createPayrollInfo($data, $this->user);

        $this->assertEquals(Carbon::now()->toDateString(), $payrollInfo->effective_date->toDateString());
    }

    /**
     * Test multiple payroll records for same employee (salary history)
     * 
     * @test
     */
    public function test_multiple_payroll_records_for_same_employee()
    {
        // Create first payroll info
        $data1 = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 25000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
        ];

        $payroll1 = $this->service->createPayrollInfo($data1, $this->user);

        // Create second payroll info (should deactivate first)
        $data2 = [
            'employee_id' => $this->employee->id,
            'salary_type' => 'monthly',
            'basic_salary' => 30000,
            'payment_method' => 'bank_transfer',
            'tax_status' => 'S',
        ];

        $payroll2 = $this->service->createPayrollInfo($data2, $this->user);

        // Check that first record is now inactive
        $oldRecord = EmployeePayrollInfo::find($payroll1->id);
        $this->assertFalse($oldRecord->is_active);
        $this->assertNotNull($oldRecord->end_date);

        // Check that second record is active
        $this->assertTrue($payroll2->is_active);
        $this->assertNull($payroll2->end_date);

        // Get active payroll info should return the latest
        $activePayroll = $this->service->getActivePayrollInfo($this->employee);
        $this->assertEquals($payroll2->id, $activePayroll->id);
    }
}
