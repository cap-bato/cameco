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
        Schema::create('access_revocations', function (Blueprint $table) {
            $table->id();

            // Case Association
            $table->foreignId('offboarding_case_id')->constrained('offboarding_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // System Details
            $table->string('system_name', 200);
            $table->enum('system_category', [
                'email',
                'network',
                'application',
                'physical_access',
                'cloud_service',
                'other'
            ]);
            $table->string('account_identifier', 200)->nullable();

            // Access Details
            $table->string('access_level', 100)->nullable();
            $table->date('granted_date')->nullable();

            // Revocation
            $table->enum('status', [
                'active',
                'disabled',
                'revoked',
                'archived',
                'pending'
            ])->default('active');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();

            // Backup
            $table->boolean('data_backed_up')->default(false);
            $table->string('backup_location', 500)->nullable();
            $table->foreignId('backup_completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('backup_completed_at')->nullable();

            // Notes
            $table->text('revocation_notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('offboarding_case_id');
            $table->index('employee_id');
            $table->index('status');
            $table->index('system_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_revocations');
    }
};
