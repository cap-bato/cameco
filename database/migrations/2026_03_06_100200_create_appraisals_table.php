<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_cycle_id')->constrained('appraisal_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('appraiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'acknowledged'])->default('draft');
            $table->decimal('overall_score', 4, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['appraisal_cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisals');
    }
};
