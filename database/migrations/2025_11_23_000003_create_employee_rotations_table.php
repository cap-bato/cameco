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
        Schema::create('employee_rotations', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name', 191);
            $table->text('description')->nullable();

            // Pattern Configuration
            $table->enum('pattern_type', ['4x2', '6x1', '5x2', 'custom']);

            // Rotation pattern stored as JSON
            // Example: {"work_days": 4, "rest_days": 2, "pattern": [1,1,1,1,0,0]}
            // Pattern array: 1 = work day, 0 = rest day
            $table->json('pattern_json');

            // Department Assignment (optional)
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');

            // Status
            $table->boolean('is_active')->default(true);

            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('pattern_type');
            $table->index('department_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_rotations');
    }
};
