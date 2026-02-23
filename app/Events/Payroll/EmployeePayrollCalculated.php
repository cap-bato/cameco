<?php

namespace App\Events\Payroll;

use App\Models\Employee;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeePayrollCalculated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Employee $employee,
        public PayrollPeriod $payrollPeriod,
        public EmployeePayrollCalculation $calculation
    ) {}
}
