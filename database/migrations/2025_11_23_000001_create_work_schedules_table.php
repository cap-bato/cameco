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
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name', 191);
            $table->text('description')->nullable();

            // Schedule Validity Period
            $table->date('effective_date');
            $table->date('expires_at')->nullable();
            $table->enum('status', ['active', 'expired', 'draft', 'archived'])->default('draft');

            // Weekly Schedule (each day can have different times)
            // Monday
            $table->time('monday_start')->nullable();
            $table->time('monday_end')->nullable();
            // Tuesday
            $table->time('tuesday_start')->nullable();
            $table->time('tuesday_end')->nullable();
            // Wednesday
            $table->time('wednesday_start')->nullable();
            $table->time('wednesday_end')->nullable();
            // Thursday
            $table->time('thursday_start')->nullable();
            $table->time('thursday_end')->nullable();
            // Friday
            $table->time('friday_start')->nullable();
            $table->time('friday_end')->nullable();
            // Saturday
            $table->time('saturday_start')->nullable();
            $table->time('saturday_end')->nullable();
            // Sunday
            $table->time('sunday_start')->nullable();
            $table->time('sunday_end')->nullable();

            // Break Durations (in minutes)
            $table->integer('lunch_break_duration')->nullable();
            $table->integer('morning_break_duration')->nullable();
            $table->integer('afternoon_break_duration')->nullable();

            // Overtime Configuration
            $table->integer('overtime_threshold')->nullable(); // Hours before overtime kicks in
            $table->decimal('overtime_rate_multiplier', 3, 2)->default(1.25);

            // Department Assignment (optional)
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');

            // Template Flag
            $table->boolean('is_template')->default(false);

            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('effective_date');
            $table->index('department_id');
            $table->index('is_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
