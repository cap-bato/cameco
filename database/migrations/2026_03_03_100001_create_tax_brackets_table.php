<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();

            // Tax Status
            $table->string('tax_status', 10)->index(); // Z, S, ME, S1, ME1, etc.
            $table->string('status_description', 100);

            // Bracket Information
            $table->integer('bracket_level')->default(1); // 1, 2, 3, 4, 5, 6, 7
            $table->decimal('income_from', 12, 2); // Annualized taxable income
            $table->decimal('income_to', 12, 2)->nullable(); // NULL = no upper limit

            // Tax Calculation
            $table->decimal('base_tax', 10, 2)->default(0); // Fixed tax for bracket
            $table->decimal('tax_rate', 5, 2)->default(0); // Percentage (0-35)
            $table->decimal('excess_over', 12, 2)->default(0); // Amount to subtract from income

            // Exemptions (TRAIN Law)
            $table->decimal('personal_exemption', 10, 2)->default(50000); // ₱50k standard
            $table->decimal('additional_exemption', 10, 2)->default(25000); // ₱25k per dependent
            $table->integer('max_dependents')->default(4);

            // Effective Period
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tax_status', 'is_active', 'effective_from']);
            $table->index(['income_from', 'income_to']);
            $table->index('bracket_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
