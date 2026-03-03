<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('government_remittance_id')->nullable()->constrained()->nullOnDelete();

            // Report Information
            $table->enum('agency', ['sss', 'philhealth', 'pagibig', 'bir'])->index();
            $table->string('report_type', 50); // 'r3', 'rf1', 'mcrf', '1601c', '2316', 'alphalist'
            $table->string('report_name', 100);
            $table->string('report_period', 50); // e.g., "January 2026"

            // File Information
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 10); // 'csv', 'dat', 'pdf', 'excel'
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->string('file_hash')->nullable(); // SHA256 hash

            // Report Data Summary
            $table->integer('total_employees')->default(0);
            $table->decimal('total_compensation', 12, 2)->default(0);
            $table->decimal('total_employee_share', 12, 2)->default(0);
            $table->decimal('total_employer_share', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            // BIR-specific
            $table->string('rdo_code')->nullable();
            $table->decimal('total_tax_withheld', 12, 2)->nullable();

            // Submission Information
            $table->enum('status', ['draft', 'ready', 'submitted', 'accepted', 'rejected'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->string('submission_reference')->nullable();
            $table->text('rejection_reason')->nullable();

            // Validation
            $table->boolean('is_validated')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_errors')->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['agency', 'report_type']);
            $table->index(['payroll_period_id', 'agency']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_reports');
    }
};
