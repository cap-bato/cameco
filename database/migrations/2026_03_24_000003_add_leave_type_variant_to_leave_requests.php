<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds support for half-day leave variants to the leave_requests table.
     * This allows employees to request half-day AM or half-day PM leave, 
     * instead of full-day leaves.
     * 
     * The leave_type_variant column stores:
     * - null: Full day leave (default)
     * - 'half_am': Half day AM leave (0.5 days)
     * - 'half_pm': Half day PM leave (0.5 days)
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Add leave_type_variant column after leave_policy_id
            $table->string('leave_type_variant', 20)->nullable()->after('leave_policy_id')
                ->comment('Variant of leave type: null (full day), half_am, half_pm');

            // Add composite index for efficient filtering by policy and variant
            $table->index(['leave_policy_id', 'leave_type_variant'], 'idx_leave_requests_policy_variant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex('idx_leave_requests_policy_variant');

            // Then drop the column
            $table->dropColumn('leave_type_variant');
        });
    }
};
