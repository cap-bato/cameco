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
        Schema::create('workforce_coverage_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->date('date');
            $table->decimal('coverage_percentage', 5, 2)->comment('Department coverage percentage for the date');
            $table->integer('employees_available')->default(0)->comment('Number of employees available/scheduled');
            $table->integer('total_employees')->default(0)->comment('Total employees in department');
            $table->timestamps();
            
            // Unique index on department_id and date for fast lookups
            $table->unique(['department_id', 'date'], 'unique_dept_date');
            
            // Index for date queries
            $table->index('date');
            
            // Index for coverage percentage queries (finding low coverage days)
            $table->index('coverage_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workforce_coverage_cache');
    }
};
