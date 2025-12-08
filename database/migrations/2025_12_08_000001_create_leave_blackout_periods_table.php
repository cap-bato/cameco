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
        Schema::create('leave_blackout_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Year-End Inventory", "Holiday Rush"
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable(); // Explanation for blackout period
            
            // Department-specific or company-wide
            $table->foreignId('department_id')
                ->nullable() // null = company-wide blackout
                ->constrained('departments')
                ->onDelete('cascade');
            
            // Audit fields
            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete('restrict');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['start_date', 'end_date']);
            $table->index('department_id');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_blackout_periods');
    }
};
