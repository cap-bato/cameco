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
        Schema::create('exit_interviews', function (Blueprint $table) {
            $table->id();

            // Case Association
            $table->foreignId('offboarding_case_id')->unique()->constrained('offboarding_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Interview Details
            $table->date('interview_date')->nullable();
            $table->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('interview_method', [
                'in_person',
                'video_call',
                'phone',
                'written_form'
            ])->default('written_form');

            // Core Questions
            $table->longText('reason_for_leaving')->nullable();
            $table->unsignedTinyInteger('overall_satisfaction')->nullable();
            $table->unsignedTinyInteger('work_environment_rating')->nullable();
            $table->unsignedTinyInteger('management_rating')->nullable();
            $table->unsignedTinyInteger('compensation_rating')->nullable();
            $table->unsignedTinyInteger('career_growth_rating')->nullable();
            $table->unsignedTinyInteger('work_life_balance_rating')->nullable();

            // Open-ended Feedback
            $table->longText('liked_most')->nullable();
            $table->longText('liked_least')->nullable();
            $table->longText('suggestions_for_improvement')->nullable();
            $table->boolean('would_recommend_company')->nullable();
            $table->boolean('would_consider_returning')->nullable();

            // Additional Questions
            $table->json('questions_responses')->nullable();

            // Analysis
            $table->decimal('sentiment_score', 3, 2)->nullable();
            $table->json('key_themes')->nullable();

            // Status
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'declined'
            ])->default('pending');
            $table->timestamp('completed_at')->nullable();

            // Privacy
            $table->boolean('confidential')->default(true);
            $table->boolean('shared_with_manager')->default(false);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('offboarding_case_id');
            $table->index('status');
            $table->index('interview_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exit_interviews');
    }
};
