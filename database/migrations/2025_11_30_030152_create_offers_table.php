<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable(); // Offer title or position
            $table->text('details')->nullable(); // Offer details
            $table->decimal('salary', 12, 2)->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by')->nullable(); // user who created
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
