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
        Schema::create('employee_payment_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            
            // Priority
            $table->boolean('is_primary')->default(false);
            $table->integer('priority')->default(1); // 1 = highest
            
            // Bank Account Details
            $table->string('bank_code', 10)->nullable(); // e.g., 'MBTC', 'BDO', 'BPI'
            $table->string('bank_name', 100)->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->string('account_number')->nullable(); // Encrypted
            $table->string('account_name', 200)->nullable();
            $table->enum('account_type', ['savings', 'checking', 'payroll'])->nullable();
            
            // E-wallet Details
            $table->string('ewallet_provider')->nullable(); // 'gcash', 'maya', 'paymongo'
            $table->string('ewallet_account_number')->nullable(); // Mobile number
            $table->string('ewallet_account_name', 200)->nullable();
            
            // Verification
            $table->enum('verification_status', ['pending', 'verified', 'failed', 'rejected'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            
            // Supporting Documents
            $table->string('document_type')->nullable(); // 'bank_statement', 'passbook', 'screenshot'
            $table->string('document_path')->nullable();
            $table->timestamp('document_uploaded_at')->nullable();
            
            // Usage Tracking
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->integer('successful_payments')->default(0);
            $table->integer('failed_payments')->default(0);
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'is_primary']);
            $table->index(['employee_id', 'payment_method_id']);
            $table->index('verification_status');
            $table->index('bank_code');
            $table->unique(['employee_id', 'payment_method_id', 'account_number'], 'unique_employee_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payment_preferences');
    }
};
