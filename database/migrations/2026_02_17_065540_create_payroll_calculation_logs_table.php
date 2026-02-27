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
        if (Schema::hasTable('payroll_calculation_logs')) {
            return;
        }
        
        Schema::create('payroll_calculation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Log Entry
            $table->enum('log_type', [
                'calculation_started',
                'calculation_completed',
                'calculation_failed',
                'data_fetched',
                'exception_detected',
                'adjustment_applied',
                'recalculation',
                'approval',
                'rejection',
                'lock',
                'unlock'
            ])->index();
            
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            
            // Message
            $table->string('message');
            $table->text('details')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            
            // Processing Stats
            $table->integer('employees_processed')->nullable();
            $table->integer('employees_success')->nullable();
            $table->integer('employees_failed')->nullable();
            $table->integer('exceptions_generated')->nullable();
            $table->decimal('processing_time_seconds', 8, 2)->nullable();
            
            // Actor
            $table->string('actor_type')->nullable(); // 'user', 'system', 'cron'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            
            // Request Context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_calculation_logs');
    }
};
