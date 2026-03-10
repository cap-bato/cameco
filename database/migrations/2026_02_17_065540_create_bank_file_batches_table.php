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
        Schema::create('bank_file_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            
            // Batch Information
            $table->string('batch_number')->unique();
            $table->string('batch_name');
            $table->date('payment_date');
            
            // Bank Details
            $table->string('bank_code', 10); // 'MBTC', 'BDO', 'BPI'
            $table->string('bank_name', 100);
            $table->enum('transfer_type', ['instapay', 'pesonet', 'internal'])->default('pesonet');
            
            // File Details
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_format', 10); // 'csv', 'xlsx', 'txt'
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->string('file_hash')->nullable(); // SHA256
            
            // Amounts
            $table->integer('total_employees');
            $table->decimal('total_amount', 12, 2);
            $table->integer('successful_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->decimal('total_fees', 8, 2)->default(0);
            
            // Settlement
            $table->date('settlement_date')->nullable();
            $table->string('settlement_reference')->nullable();
            
            // Status
            $table->enum('status', ['draft', 'ready', 'submitted', 'processing', 'completed', 'partially_completed', 'failed'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Validation
            $table->boolean('is_validated')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->json('validation_errors')->nullable();
            
            // Bank Response
            $table->text('bank_response')->nullable();
            $table->string('bank_confirmation_number')->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['payroll_period_id', 'bank_code']);
            $table->index(['payment_date', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_file_batches');
    }
};
