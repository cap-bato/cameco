<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_payroll_calculation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            
            // Period Information
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date'); // Expected payment date
            
            // Payment Amounts
            $table->decimal('gross_pay', 10, 2);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2);
            
            // Deduction Breakdown
            $table->decimal('sss_deduction', 8, 2)->default(0);
            $table->decimal('philhealth_deduction', 8, 2)->default(0);
            $table->decimal('pagibig_deduction', 8, 2)->default(0);
            $table->decimal('tax_deduction', 8, 2)->default(0);
            $table->decimal('loan_deduction', 8, 2)->default(0);
            $table->decimal('advance_deduction', 8, 2)->default(0);
            $table->decimal('leave_deduction', 8, 2)->default(0); // Unpaid leave
            $table->decimal('attendance_deduction', 8, 2)->default(0); // Absences/tardiness
            $table->decimal('other_deductions', 8, 2)->default(0);
            
            // Payment Details
            $table->string('payment_reference')->nullable(); // Bank ref, transaction ID, envelope #
            $table->string('batch_number')->nullable(); // Links to bank_file_batches or cash_distribution_batches
            
            // Bank Transfer Details
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_transaction_id')->nullable();
            
            // E-wallet Details
            $table->string('ewallet_account')->nullable();
            $table->string('ewallet_transaction_id')->nullable();
            
            // Cash Distribution Details
            $table->string('envelope_number')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('claimed_by_signature')->nullable(); // Path to signature image
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Payment Status
            $table->enum('status', [
                'pending',          // Awaiting processing
                'processing',       // Payment initiated
                'paid',            // Successfully paid
                'partially_paid',  // Partial payment made
                'failed',          // Payment failed
                'cancelled',       // Payment cancelled
                'unclaimed'        // Cash not claimed (after 30 days)
            ])->default('pending')->index();
            
            // Timestamps
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Retry Logic
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->text('failure_reason')->nullable();
            
            // Webhook/Confirmation
            $table->json('provider_response')->nullable(); // PayMongo/bank response
            $table->string('confirmation_code')->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index(['payment_date', 'status']);
            $table->index('payment_reference');
            $table->index('batch_number');
            $table->unique(['employee_id', 'payroll_period_id'], 'unique_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
    }
};
