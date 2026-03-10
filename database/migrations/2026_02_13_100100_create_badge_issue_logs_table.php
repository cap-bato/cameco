<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Task 2.2.2: Create badge_issue_logs table (HR audit trail)
     *
     * This table maintains an immutable audit trail of all badge-related actions:
     * - Badge issuance (initial assignment)
     * - Replacements (due to loss, damage, or upgrade)
     * - Deactivation (lost, stolen, or intentional removal)
     * - Reactivation (badge re-enabled)
     * - Expiration (automatic or manual)
     *
     * Used for compliance, auditing, and tracking badge lifecycle.
     */
    public function up(): void
    {
        Schema::create('badge_issue_logs', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Badge & Employee Information
            $table->string('card_uid', 255)->comment('RFID card unique identifier');
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->comment('Foreign key to employees table');

            // Action Information
            $table->enum('action_type', ['issued', 'replaced', 'deactivated', 'reactivated', 'expired'])
                ->comment('Type of action performed');
            $table->timestamp('issued_at')->comment('Action timestamp');
            $table->foreignId('issued_by')
                ->constrained('users')
                ->comment('HR Staff/Manager who performed the action');

            // Action Details
            $table->text('reason')->nullable()->comment('Reason for the action (e.g., lost, upgrade, expiration)');
            $table->string('previous_card_uid', 255)->nullable()->comment('Previous card UID for replacement actions');
            $table->decimal('replacement_fee', 10, 2)->nullable()->comment('Replacement fee for lost/damaged badges');
            $table->text('acknowledgement_signature')->nullable()->comment('Optional digital acknowledgment/signature from employee');

            // Timestamps
            $table->timestamp('created_at')->useCurrent()->comment('Log entry creation timestamp');

            // Indexes for Performance
            $table->index('employee_id', 'idx_badge_issue_logs_employee');
            $table->index('card_uid', 'idx_badge_issue_logs_card_uid');
            $table->index('action_type', 'idx_badge_issue_logs_action');
            $table->index('issued_at', 'idx_badge_issue_logs_issued_at');
            $table->index(['employee_id', 'issued_at'], 'idx_badge_issue_logs_employee_date');
            $table->index(['action_type', 'issued_at'], 'idx_badge_issue_logs_action_date');

            // Full-text search index for reason field (optional, useful for searching reason text)
            // $table->fullText('reason', 'ft_badge_issue_logs_reason');

            // Table Comment
            $table->comment('Badge issue logs: Immutable audit trail of all badge lifecycle actions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_issue_logs');
    }
};
