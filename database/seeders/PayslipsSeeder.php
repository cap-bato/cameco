<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Payslip;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayslipsSeeder extends Seeder
{
    /**
     * Seed payslips for all payroll payments.
     * Creates digital payslip records for each payment.
     */
    public function run(): void
    {
        // Avoid duplicate seeding
        if (Payslip::count() > 0) {
            $this->command->warn('payslips already seeded — skipping.');
            return;
        }

        // Get all payments
        $payments = PayrollPayment::with(['employee', 'payrollPeriod'])->get();
        
        if ($payments->isEmpty()) {
            $this->command->warn('No payroll payments found. Run PayrollPaymentsSeeder first.');
            return;
        }

        $payslips = [];
        $now = Carbon::now();

        foreach ($payments as $payment) {
            if (!$payment->employee || !$payment->payrollPeriod) {
                continue; // Skip if relationships are missing
            }

            $period = $payment->payrollPeriod;
            $employee = $payment->employee;

            // Calculate earning breakdown
            $earnings = $this->generateEarningsBreakdown($payment->gross_pay);
            
            // Calculate deduction breakdown (use actual values from payment)
            $deductions = [
                'sss' => (float)$payment->sss_deduction,
                'philhealth' => (float)$payment->philhealth_deduction,
                'pagibig' => (float)$payment->pagibig_deduction,
                'tax' => (float)$payment->tax_deduction,
                'loan' => (float)$payment->loan_deduction,
                'advance' => (float)$payment->advance_deduction,
                'leave' => (float)$payment->leave_deduction,
                'attendance' => (float)$payment->attendance_deduction,
                'other' => (float)$payment->other_deductions,
            ];

            // Determine payslip status and distribution
            if ($payment->status === 'paid') {
                $status = rand(1, 100) <= 70 ? 'distributed' : 'generated'; // 70% distributed
                $distributedAt = Carbon::parse($period->approved_at)->addHours(2)->addMinutes(30);
                $isViewed = $status === 'distributed' && rand(1, 100) <= 80; // 80% of distributed payslips viewed
                $viewedAt = $isViewed ? $distributedAt->copy()->addHours(rand(1, 24)) : null;
            } else {
                $status = 'generated';
                $distributedAt = null;
                $isViewed = false;
                $viewedAt = null;
            }

            $ytdMultiplier = rand(8, 12);
            $sssYtd = (float)$payment->sss_deduction * $ytdMultiplier;
            $philhealthYtd = (float)$payment->philhealth_deduction * $ytdMultiplier;
            $pagibigYtd = (float)$payment->pagibig_deduction * $ytdMultiplier;

            $payslips[] = [
                'payroll_payment_id' => $payment->id,
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'period_start' => $period->period_start,
                'period_end' => $period->period_end,
                'payment_date' => $period->payment_date,
                'payslip_number' => 'PS-' . $period->period_number . '-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT),
                
                // Employee snapshot
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->full_name,
                'department' => $employee->department->name ?? null,
                'position' => $employee->position->title ?? null,
                'sss_number' => $employee->sss_number ?? null,
                'philhealth_number' => $employee->philhealth_number ?? null,
                'pagibig_number' => $employee->pagibig_number ?? null,
                'tin' => $employee->tin ?? null,
                
                // Amounts
                'total_earnings' => $payment->gross_pay,
                'total_deductions' => $payment->total_deductions,
                'net_pay' => $payment->net_pay,
                
                // JSON Data
                'earnings_data' => json_encode($earnings),
                'deductions_data' => json_encode($deductions),
                'attendance_summary' => json_encode([
                    'days_worked' => rand(10, 15),
                    'days_absent' => rand(0, 2),
                    'days_late' => rand(0, 3),
                    'overtime_hours' => rand(0, 20),
                    'undertime_hours' => rand(0, 5),
                ]),
                'leave_summary' => json_encode([
                    'vacation_taken' => rand(0, 2),
                    'sick_taken' => rand(0, 1),
                    'unpaid_taken' => rand(0, 1),
                ]),
                
                // YTD values
                'ytd_gross' => $payment->gross_pay * $ytdMultiplier,
                'ytd_tax' => $payment->tax_deduction * $ytdMultiplier,
                'ytd_sss' => $sssYtd,
                'ytd_philhealth' => $philhealthYtd,
                'ytd_pagibig' => $pagibigYtd,
                'ytd_net' => $payment->net_pay * $ytdMultiplier,
                
                // File details
                'file_path' => 'payslips/' . $period->period_number . '/' . $employee->employee_number . '.pdf',
                'file_format' => 'pdf',
                'file_size' => rand(50000, 150000),
                'file_hash' => md5($payment->id . $employee->id . $now),
                
                // Distribution
                'distribution_method' => $distributedAt ? 'email' : null,
                'distributed_at' => $distributedAt,
                'is_viewed' => $isViewed,
                'viewed_at' => $viewedAt,
                
                // Security
                'signature_hash' => $distributedAt ? hash('sha256', $payment->id . $employee->id) : null,
                'qr_code_data' => $distributedAt ? base64_encode($payment->id . '|' . $employee->id) : null,
                
                // Status & audit
                'status' => $status,
                'generated_by' => 1, // Admin user
                'notes' => null,
                'created_at' => $distributedAt ?? $now,
                'updated_at' => $viewedAt ?? $distributedAt ?? $now,
            ];

            // Insert in batches of 100 to avoid memory issues
            if (count($payslips) >= 100) {
                Payslip::insert($payslips);
                $payslips = [];
            }
        }

        // Insert remaining payslips
        if (count($payslips) > 0) {
            Payslip::insert($payslips);
        }

        $totalPayslips = Payslip::count();
        $this->command->info("✅ Seeded $totalPayslips payslips");
    }

    /**
     * Generate realistic earnings breakdown.
     */
    private function generateEarningsBreakdown(float $grossPay): array
    {
        $basicPay = $grossPay * 0.75; // 75% basic
        $overtime = $grossPay * 0.10; // 10% overtime
        $allowances = $grossPay * 0.15; // 15% allowances

        return [
            'basic_pay' => round($basicPay, 2),
            'overtime_pay' => round($overtime, 2),
            'night_differential' => round($overtime * 0.1, 2),
            'holiday_pay' => 0,
            'allowances' => [
                'meal' => round($allowances * 0.4, 2),
                'transportation' => round($allowances * 0.4, 2),
                'communication' => round($allowances * 0.2, 2),
            ],
            'bonuses' => 0,
            'other_earnings' => 0,
        ];
    }
}
