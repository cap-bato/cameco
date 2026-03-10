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
        Schema::table('departments', function (Blueprint $table) {
            // Configurable coverage threshold per department
            $table->decimal('min_coverage_percentage', 5, 2)
                ->default(75.00)
                ->after('is_active')
                ->comment('Minimum workforce coverage required for auto-approval');
            
            // Future: approval chain configuration
            $table->json('approval_chain_config')
                ->nullable()
                ->after('min_coverage_percentage')
                ->comment('Custom approval chain: supervisors, managers, etc.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['min_coverage_percentage', 'approval_chain_config']);
        });
    }
};
