<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('candidate_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('job_posting_id')
                ->constrained()
                ->cascadeOnDelete();

            // Application fields
            $table->string('status')->default('submitted');   // applied, reviewed, interviewed, hired, rejected
            $table->float('score')->nullable();            // interview score
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('applications');
    }
};
