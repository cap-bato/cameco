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
        Schema::create('knowledge_transfer_items', function (Blueprint $table) {
            $table->id();

            // Case Association
            $table->foreignId('offboarding_case_id')->constrained('offboarding_cases')->cascadeOnDelete();

            // Item Details
            $table->enum('item_type', [
                'project',
                'client',
                'process',
                'documentation',
                'credentials',
                'contacts',
                'other'
            ]);
            $table->string('title', 200);
            $table->text('description')->nullable();

            // Transfer Details
            $table->foreignId('transferred_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'not_applicable'
            ])->default('pending');
            $table->enum('priority', [
                'critical',
                'high',
                'normal',
                'low'
            ])->default('normal');

            // Documentation
            $table->string('documentation_location', 500)->nullable();
            $table->text('handover_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            // Timestamps
            $table->date('due_date')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('offboarding_case_id');
            $table->index('status');
            $table->index('transferred_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_transfer_items');
    }
};
