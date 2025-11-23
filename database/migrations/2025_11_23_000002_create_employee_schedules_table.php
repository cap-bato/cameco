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
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('work_schedule_id')->constrained('work_schedules')->onDelete('restrict');

            // Date Range
            $table->date('effective_date');
            $table->date('end_date')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            // Indexes
            $table->index('employee_id');
            $table->index('work_schedule_id');
            $table->index('effective_date');
            $table->index('is_active');

            // Unique Constraint - prevent duplicate assignments for same employee on same date
            $table->unique(['employee_id', 'effective_date', 'work_schedule_id'], 'unique_employee_schedule_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
