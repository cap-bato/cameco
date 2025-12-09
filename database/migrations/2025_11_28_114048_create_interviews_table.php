<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            $table->string('job_title');
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->integer('duration_minutes')->default(30);
            $table->enum('location_type', ['office', 'virtual']);
            $table->enum('status', ['scheduled', 'completed', 'canceled'])->default('scheduled');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('interviewer_name');
            $table->enum('recommendation', ['hire', 'no_hire', 'hold'])->nullable();
            $table->text('feedback')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
