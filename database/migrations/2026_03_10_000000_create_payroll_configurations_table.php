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
        Schema::create('payroll_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique()->comment('Unique configuration key (e.g., deduction_timing)');
            $table->json('config_value')->comment('Flexible JSON structure for configuration values');
            $table->string('description')->nullable()->comment('Human-readable description of this configuration');
            $table->date('effective_from')->nullable()->comment('Date when this configuration becomes effective');
            $table->date('effective_to')->nullable()->comment('Date when this configuration expires');
            $table->boolean('is_active')->default(true)->comment('Whether this configuration is currently active');
            $table->foreignId('created_by')->nullable()->constrained('users')->comment('User who created this configuration');
            $table->foreignId('updated_by')->nullable()->constrained('users')->comment('User who last updated this configuration');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('config_key');
            $table->index(['is_active', 'effective_from', 'effective_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_configurations');
    }
};
