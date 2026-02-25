<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_distribution_batches')) {
            return;
        }
        
        Schema::create('cash_distribution_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();

            // Batch Information
            $table->string('batch_number')->unique();
            $table->date('distribution_date');
            $table->string('distribution_location')->nullable();

            // Cash Preparation
            $table->decimal('total_cash_amount', 12, 2);
            $table->integer('total_employees');
            $table->json('denomination_breakdown')->nullable(); // {1000: 10, 500: 5, 100: 20, etc}

            // Withdrawal Details
            $table->string('withdrawal_source')->nullable(); // 'vault', 'bank_branch'
            $table->string('withdrawal_reference')->nullable();
            $table->date('withdrawal_date')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();

            // Verification (Dual verification: Payroll Officer + Office Admin both required)
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete(); // Payroll Officer
            $table->foreignId('witnessed_by')->nullable()->constrained('users')->nullOnDelete(); // HR Manager/Office Admin
            $table->timestamp('verification_at')->nullable();
            $table->text('verification_notes')->nullable();

            // Distribution Tracking
            $table->integer('envelopes_prepared')->default(0);
            $table->integer('envelopes_distributed')->default(0);
            $table->integer('envelopes_unclaimed')->default(0);
            $table->decimal('amount_distributed', 12, 2)->default(0);
            $table->decimal('amount_unclaimed', 12, 2)->default(0);

            // Disbursement Log
            $table->string('log_sheet_path')->nullable(); // Scanned signature log
            $table->timestamp('distribution_started_at')->nullable();
            $table->timestamp('distribution_completed_at')->nullable();

            // Unclaimed Handling
            // Decision #9: Manual disposition — 're-deposited', 'held', 'added_to_next_period'
            $table->date('unclaimed_deadline')->nullable(); // 30 days after distribution
            $table->string('unclaimed_disposition')->nullable(); // 're-deposited', 'held', 'next_period'
            $table->date('redeposit_date')->nullable();
            $table->string('redeposit_reference')->nullable();

            // Status
            $table->enum('status', ['preparing', 'ready', 'distributing', 'completed', 'partially_completed', 'reconciled'])->default('preparing');

            // Accountability Report
            $table->string('accountability_report_path')->nullable();
            $table->timestamp('report_generated_at')->nullable();
            $table->foreignId('report_approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Notes
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['payroll_period_id', 'distribution_date'], 'cdb_period_date_idx');
            $table->index(['status', 'distribution_date'], 'cdb_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_distribution_batches');
    }
};
