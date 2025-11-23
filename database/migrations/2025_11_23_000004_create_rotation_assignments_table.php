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
        Schema::create('rotation_assignments', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('rotation_id')->constrained('employee_rotations')->onDelete('cascade');

            // Date Range
            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            // Indexes
            $table->index('employee_id');
            $table->index('rotation_id');
            $table->index('start_date');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotation_assignments');
    }
};
