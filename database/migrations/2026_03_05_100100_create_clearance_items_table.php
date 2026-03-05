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
        Schema::create('clearance_items', function (Blueprint $table) {
            $table->id();

            // Case Association
            $table->foreignId('offboarding_case_id')->constrained('offboarding_cases')->cascadeOnDelete();

            // Item Details
            $table->enum('category', [
                'hr',
                'it',
                'finance',
                'admin',
                'operations',
                'security',
                'facilities'
            ]);
            $table->string('item_name', 200);
            $table->text('description')->nullable();
            $table->enum('priority', [
                'critical',
                'high',
                'normal',
                'low'
            ])->default('normal');

            // Approval
            $table->enum('status', [
                'pending',
                'in_progress',
                'approved',
                'waived',
                'issues'
            ])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Issue Tracking
            $table->boolean('has_issues')->default(false);
            $table->text('issue_description')->nullable();
            $table->text('resolution_notes')->nullable();

            // Attachments
            $table->string('proof_of_return_file_path', 500)->nullable();

            // Timestamps
            $table->date('due_date')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('offboarding_case_id');
            $table->index('category');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clearance_items');
    }
};
