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
        Schema::table('leave_policies', function (Blueprint $table) {
            // Carry-over configuration (note: max_carryover already exists, adding max_carryover_days for clarity)
            $table->integer('max_carryover_days')
                ->nullable()
                ->after('max_carryover')
                ->comment('Maximum days that can be carried over to next year');
            
            $table->enum('carryover_conversion', ['cash', 'forfeit', 'none'])
                ->default('none')
                ->after('max_carryover_days')
                ->comment('What happens to excess days: cash=convert to pay, forfeit=lose, none=no limit');
            
            // Employee type eligibility (future use)
            $table->json('employee_type_config')
                ->nullable()
                ->after('carryover_conversion')
                ->comment('Employee type-specific rules: {"regular": {...}, "contractual": {...}}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropColumn([
                'max_carryover_days',
                'carryover_conversion',
                'employee_type_config',
            ]);
        });
    }
};
