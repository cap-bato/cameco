<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::create('job_postings', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->foreignId('department_id')->constrained()->cascadeOnDelete();
        $table->text('description');
        $table->text('requirements');
        $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
        $table->timestamp('posted_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('job_postings');
}
};
