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
        Schema::create('offboarding_documents', function (Blueprint $table) {
            $table->id();

            // Case Association
            $table->foreignId('offboarding_case_id')->constrained('offboarding_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Document Details
            $table->enum('document_type', [
                'clearance_certificate',
                'certificate_of_employment',
                'final_pay_computation',
                'bir_form_2316',
                'resignation_letter',
                'termination_letter',
                'exit_interview',
                'other'
            ]);
            $table->string('document_name', 200);
            $table->string('file_path', 500);

            // Generation/Upload
            $table->boolean('generated_by_system')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Status
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'issued'
            ])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('issued_to_employee')->default(false);
            $table->timestamp('issued_at')->nullable();

            // Metadata
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('offboarding_case_id');
            $table->index('employee_id');
            $table->index('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offboarding_documents');
    }
};
