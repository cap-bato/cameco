<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Agency Information
            $table->enum('agency', ['sss', 'philhealth', 'pagibig', 'bir'])->index();
            $table->string('remittance_type', 50); // 'monthly', 'quarterly', 'annual'
            
            // Period Information
            $table->string('remittance_month', 7); // YYYY-MM format
            $table->date('period_start');
            $table->date('period_end');
            
            // Amounts
            $table->decimal('employee_share', 12, 2)->default(0);
            $table->decimal('employer_share', 12, 2)->default(0);
            $table->decimal('ec_share', 8, 2)->default(0); // SSS EC
            $table->decimal('total_amount', 12, 2);
            
            // Employee Count
            $table->integer('total_employees')->default(0);
            $table->integer('active_employees')->default(0);
            $table->integer('exempted_employees')->default(0);
            
            // Deadlines
            $table->date('due_date');
            $table->date('submission_date')->nullable();
            $table->date('payment_date')->nullable();
            
            // Payment Information
            $table->string('payment_reference')->nullable();
            $table->string('payment_method')->nullable(); // 'bank', 'online', 'otc'
            $table->string('bank_name')->nullable();
            $table->decimal('amount_paid', 12, 2)->nullable();
            
            // Penalties
            $table->boolean('has_penalty')->default(false);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->text('penalty_reason')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'ready', 'submitted', 'paid', 'partially_paid', 'overdue'])->default('pending');
            $table->boolean('is_late')->default(false);
            $table->integer('days_overdue')->default(0);
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['agency', 'remittance_month']);
            $table->index(['agency', 'status']);
            $table->index('due_date');
            $table->index(['is_late', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_remittances');
    }
};
