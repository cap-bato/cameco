<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();

            // Related Entity (polymorphic: PayrollPayment, BankFileBatch, CashDistributionBatch, etc.)
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');

            // Action Details
            // Decision #21: Capture: created, processed, paid, failed, retried, cancelled, approved, rejected
            $table->string('action', 50); // 'created', 'processed', 'paid', 'failed', 'retried', 'cancelled'
            $table->string('actor_type')->nullable(); // 'user', 'system', 'webhook'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();

            // Changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable(); // Additional context (e.g., PayMongo response snippet)

            // Request Information
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();

            // Notes (Decision #23: Retention 7 years, archived via artisan command)
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('action');
            $table->index('created_at');
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
    }
};
