<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_payment_id')->constrained()->cascadeOnDelete();

            // Payslip Details
            $table->string('payslip_number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');

            // Employee Information (snapshot at time of payslip generation)
            $table->string('employee_number', 20);
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->string('position')->nullable();

            // Government Numbers (snapshot — Decision #16: YTD included on all payslips)
            $table->string('sss_number')->nullable();
            $table->string('philhealth_number')->nullable();
            $table->string('pagibig_number')->nullable();
            $table->string('tin')->nullable();

            // Earnings Breakdown
            $table->json('earnings_data'); // {basic_salary, overtime, allowances, etc}
            $table->decimal('total_earnings', 10, 2);

            // Deductions Breakdown
            $table->json('deductions_data'); // {sss, philhealth, pagibig, tax, loans, etc}
            $table->decimal('total_deductions', 10, 2);

            // Net Pay
            $table->decimal('net_pay', 10, 2);

            // Leave Information
            $table->json('leave_summary')->nullable(); // {used_days, unpaid_days, deduction_amount}

            // Attendance Information
            $table->json('attendance_summary')->nullable(); // {present_days, absences, tardiness}

            // Year-to-Date Summaries (Decision #16: Required on all payslips)
            $table->decimal('ytd_gross', 12, 2)->nullable();
            $table->decimal('ytd_tax', 10, 2)->nullable();
            $table->decimal('ytd_sss', 10, 2)->nullable();
            $table->decimal('ytd_philhealth', 10, 2)->nullable();
            $table->decimal('ytd_pagibig', 10, 2)->nullable();
            $table->decimal('ytd_net', 12, 2)->nullable();

            // File Details (Decision #14: PDF format with QR code)
            $table->string('file_path');
            $table->string('file_format', 10)->default('pdf');
            $table->bigInteger('file_size')->nullable();
            $table->string('file_hash')->nullable();

            // Distribution (Decision #15: Hybrid email + print)
            $table->enum('distribution_method', ['email', 'portal', 'print', 'sms'])->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->boolean('is_viewed')->default(false);
            $table->timestamp('viewed_at')->nullable();

            // Digital Signature & QR Verification (Decision #14)
            $table->string('signature_hash')->nullable(); // For authenticity verification
            $table->string('qr_code_data')->nullable(); // QR code for quick verification

            // Status
            $table->enum('status', ['draft', 'generated', 'distributed', 'acknowledged'])->default('draft');

            // Notes
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index('payslip_number');
            $table->index(['payment_date', 'status']);
            $table->index('distributed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
