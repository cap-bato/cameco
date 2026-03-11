<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_policy_id')->constrained()->onDelete('restrict');
            $table->integer('year');
            $table->decimal('opening_balance', 5, 1)->default(0.0);
            $table->decimal('earned', 5, 1);
            $table->decimal('used', 5, 1)->default(0.0);
            $table->decimal('pending', 5, 1)->default(0.0);
            $table->decimal('remaining', 5, 1);
            $table->decimal('carried_forward', 5, 1)->default(0.0);
            $table->timestamps();
            
            $table->index('employee_id');
            $table->index('year');
            $table->index('leave_policy_id');
            $table->unique(['employee_id', 'leave_policy_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};