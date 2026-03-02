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
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('component_type', ['earning', 'deduction', 'benefit', 'tax', 'contribution', 'loan', 'allowance']);
            $table->enum('category', ['regular', 'overtime', 'holiday', 'leave', 'allowance', 'deduction', 'tax', 'contribution', 'loan', 'adjustment']);
            
            // Calculation Settings
            $table->enum('calculation_method', ['fixed_amount', 'percentage_of_basic', 'percentage_of_gross', 'per_hour', 'per_day', 'per_unit', 'percentage_of_component']);
            $table->decimal('default_amount', 10, 2)->nullable();
            $table->decimal('default_percentage', 5, 2)->nullable();
            $table->foreignId('reference_component_id')->nullable()->constrained('salary_components')->onDelete('set null');
            
            // Overtime and Premium Settings
            $table->decimal('ot_multiplier', 4, 2)->nullable();
            $table->boolean('is_premium_pay')->default(false);
            
            // Tax Treatment
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_deminimis')->default(false);
            $table->decimal('deminimis_limit_monthly', 10, 2)->nullable();
            $table->decimal('deminimis_limit_annual', 10, 2)->nullable();
            $table->boolean('is_13th_month')->default(false);
            $table->boolean('is_other_benefits')->default(false);
            
            // Government Contribution Settings
            $table->boolean('affects_sss')->default(false);
            $table->boolean('affects_philhealth')->default(false);
            $table->boolean('affects_pagibig')->default(false);
            $table->boolean('affects_gross_compensation')->default(true);
            
            // Display Settings
            $table->integer('display_order')->default(0);
            $table->boolean('is_displayed_on_payslip')->default(true);
            
            // System Fields
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_component')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('code');
            $table->index('component_type');
            $table->index('category');
            $table->index(['is_active', 'component_type']);
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
