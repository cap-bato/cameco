<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollPaymentsSeeder extends Seeder
{
    /**
     * Seed payroll payments for approved/closed periods.
     * Creates payment records for all employees in each period.
     */
    public function run(): void
    {
        // Avoid duplicate seeding
        if (PayrollPayment::count() > 0) {
            $this->command->warn('payroll_payments already seeded — skipping.');
            return;
        }

        // Get approved or completed periods
        $periods = PayrollPeriod::whereIn('status', ['approved', 'completed'])->get();
        
        if ($periods->isEmpty()) {
            $this->command->warn('No approved/completed payroll periods found. Run PayrollPeriodsSeeder first.');
            return;
        }

        // Get all active employees
        $employees = Employee::where('status', 'active')->get();
        
        if ($employees->isEmpty()) {
            $this->command->warn('No active employees found. Run EmployeeSeeder first.');
            return;
        }

        // Get payment methods
        $cashMethod = PaymentMethod::where('method_type', 'cash')->first();
        $bankMethod = PaymentMethod::where('method_type', 'bank')->first();

        if (!$cashMethod) {
            $this->command->warn('No cash payment method found. Run PaymentMethodsSeeder first.');
            return;
        }

        $payrollOfficer = User::where('email', 'payroll@cameco.com')->first();
        $hrManager = User::where('email', 'hrmanager@cameco.com')->first();
        $preparedById = $payrollOfficer?->id ?? 1;
        $approvedById = $hrManager?->id ?? 1;

        $payments = [];
        $now = Carbon::now();

        foreach ($periods as $period) {
            // Randomly select 70-90% of employees to have payments in this period
            $selectedEmployees = $employees->random(min($employees->count(), rand(
                (int)($employees->count() * 0.7),
                (int)($employees->count() * 0.9)
            )));

            foreach ($selectedEmployees as $employee) {
                // 80% cash, 20% bank (if available)
                $useBank = $bankMethod && rand(1, 100) <= 20;
                $paymentMethod = $useBank ? $bankMethod : $cashMethod;

                // Generate realistic salary amounts
                $grossPay = rand(15000, 50000);
                $sssDeduction = $grossPay * 0.045; // 4.5% employee share
                $philhealthDeduction = $grossPay * 0.02; // 2% employee share
                $pagibigDeduction = 200; // Fixed ₱200
                $taxDeduction = $this->calculateWithholdingTax($grossPay);
                $loanDeduction = rand(0, 5000);
                $advanceDeduction = rand(0, 2000);
                $leaveDeduction = rand(0, 1000);
                $attendanceDeduction = rand(0, 500);
                $otherDeductions = rand(0, 500);

                $totalDeductions = $sssDeduction + $philhealthDeduction + $pagibigDeduction 
                    + $taxDeduction + $loanDeduction + $advanceDeduction 
                    + $leaveDeduction + $attendanceDeduction + $otherDeductions;

                $netPay = $grossPay - $totalDeductions;

                // Determine payment status based on period status and payment method
                if ($period->status === 'completed') {
                    if ($paymentMethod->method_type === 'cash') {
                        // 90% claimed, 10% unclaimed for cash
                        $status = rand(1, 100) <= 90 ? 'paid' : 'unclaimed';
                        $paidAt = $status === 'paid' ? Carbon::parse($period->payment_date)->addHours(rand(0, 48)) : null;
                        $claimedAt = $paidAt;
                    } else {
                        // All bank payments completed for completed periods
                        $status = 'paid';
                        $paidAt = Carbon::parse($period->payment_date)->addHours(rand(1, 6));
                        $claimedAt = null;
                    }
                } else {
                    // Approved but not yet paid
                    $status = 'pending';
                    $paidAt = null;
                    $claimedAt = null;
                }

                $payments[] = [
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'payment_method_id' => $paymentMethod->id,
                    'period_start' => $period->period_start,
                    'period_end' => $period->period_end,
                    'payment_date' => $period->payment_date,
                    'gross_pay' => $grossPay,
                    'total_deductions' => $totalDeductions,
                    'net_pay' => $netPay,
                    'sss_deduction' => $sssDeduction,
                    'philhealth_deduction' => $philhealthDeduction,
                    'pagibig_deduction' => $pagibigDeduction,
                    'tax_deduction' => $taxDeduction,
                    'loan_deduction' => $loanDeduction,
                    'advance_deduction' => $advanceDeduction,
                    'leave_deduction' => $leaveDeduction,
                    'attendance_deduction' => $attendanceDeduction,
                    'other_deductions' => $otherDeductions,
                    'payment_reference' => 'PAY-' . $period->period_number . '-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT),
                    'batch_number' => $period->period_number,
                    'bank_account_number' => $useBank ? fake()->numerify('##########') : null,
                    'bank_name' => $useBank ? $paymentMethod->bank_name : null,
                    'bank_transaction_id' => $useBank && $status === 'paid' ? 'TXN' . fake()->numerify('############') : null,
                    'envelope_number' => !$useBank ? 'ENV-' . $period->period_number . '-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT) : null,
                    'claimed_at' => $claimedAt,
                    'released_by' => $status === 'paid' && !$useBank ? $preparedById : null,
                    'status' => $status,
                    'processed_at' => Carbon::parse($period->approved_at ?? $period->payment_date)->subHours(2),
                    'paid_at' => $paidAt,
                    'confirmation_code' => $status === 'paid' ? strtoupper(fake()->bothify('??##??##')) : null,
                    'prepared_by' => $preparedById,
                    'approved_by' => $period->status === 'completed' ? $approvedById : null,
                    'created_at' => Carbon::parse($period->approved_at ?? $period->payment_date)->subDays(1),
                    'updated_at' => $paidAt ?? $now,
                ];

                // Insert in batches of 100 to avoid memory issues
                if (count($payments) >= 100) {
                    PayrollPayment::insert($payments);
                    $payments = [];
                }
            }
        }

        // Insert remaining payments
        if (count($payments) > 0) {
            PayrollPayment::insert($payments);
        }

        $totalPayments = PayrollPayment::count();
        $this->command->info("✅ Seeded $totalPayments payroll payments for " . $periods->count() . " periods");
    }

    /**
     * Simple withholding tax calculation (2025 tax tables)
     */
    private function calculateWithholdingTax(float $grossPay): float
    {
        $annualGross = $grossPay * 24; // Semi-monthly

        if ($annualGross <= 250000) {
            return 0;
        } elseif ($annualGross <= 400000) {
            $tax = ($annualGross - 250000) * 0.15;
        } elseif ($annualGross <= 800000) {
            $tax = 22500 + (($annualGross - 400000) * 0.20);
        } elseif ($annualGross <= 2000000) {
            $tax = 102500 + (($annualGross - 800000) * 0.25);
        } elseif ($annualGross <= 8000000) {
            $tax = 402500 + (($annualGross - 2000000) * 0.30);
        } else {
            $tax = 2202500 + (($annualGross - 8000000) * 0.35);
        }

        return round($tax / 24, 2); // Convert back to semi-monthly
    }
}
