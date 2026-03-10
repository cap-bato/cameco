<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained('appraisals')->cascadeOnDelete();
            $table->foreignId('appraisal_criteria_id')->constrained('appraisal_criteria')->cascadeOnDelete();
            $table->decimal('score', 4, 2);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['appraisal_id', 'appraisal_criteria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_scores');
    }
};
