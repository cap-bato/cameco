<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_cycles');
    }
};
