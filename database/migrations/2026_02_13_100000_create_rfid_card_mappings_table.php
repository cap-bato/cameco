<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Task 2.2.1: Create rfid_card_mappings table (HR-managed badge assignments)
     *
     * This table maps RFID card UIDs to employees and tracks:
     * - Badge issuance and activation
     * - Badge expiration and renewal
     * - Usage tracking (last_used_at, usage_count)
     * - Deactivation (lost, stolen, inactive)
     * - SoftDeletes for audit trail
     * - Activity logging via LogsActivity trait
     */
    public function up(): void
    {
        Schema::create('rfid_card_mappings', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Badge Identification
            $table->string('card_uid', 255)->unique()->comment('RFID card unique identifier (e.g., 04:3A:B2:C5:D8)');
            $table->enum('card_type', ['mifare', 'desfire', 'em4100'])->default('mifare')->comment('Card technology type');

            // Employee Assignment
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade')
                ->comment('Foreign key to employees table');

            // Issuance Information
            $table->timestamp('issued_at')->comment('Badge issuance timestamp');
            $table->foreignId('issued_by')
                ->constrained('users')
                ->comment('HR Staff/Manager who issued the badge');

            // Expiration
            $table->timestamp('expires_at')->nullable()->comment('Optional badge expiration date');

            // Status & Activity
            $table->boolean('is_active')->default(true)->comment('Badge active status');
            $table->timestamp('last_used_at')->nullable()->comment('Last scan timestamp (auto-updated by FastAPI)');
            $table->unsignedInteger('usage_count')->default(0)->comment('Total number of scans (auto-updated by FastAPI)');

            // Deactivation Information
            $table->timestamp('deactivated_at')->nullable()->comment('Deactivation timestamp');
            $table->foreignId('deactivated_by')
                ->nullable()
                ->constrained('users')
                ->comment('User who deactivated the badge');
            $table->text('deactivation_reason')->nullable()->comment('Reason for deactivation (lost, stolen, expired, etc.)');

            // Additional Notes
            $table->text('notes')->nullable()->comment('Additional notes about the badge');

            // Soft Deletes for Audit Trail
            $table->softDeletes();

            // Timestamps
            $table->timestamps();

            // Indexes for Performance
            $table->unique('card_uid', 'uk_card_uid');
            $table->index('employee_id', 'idx_rfid_card_mappings_employee');
            $table->index('is_active', 'idx_rfid_card_mappings_active');
            $table->index('expires_at', 'idx_rfid_card_mappings_expires');
            $table->index('last_used_at', 'idx_rfid_card_mappings_last_used');
            $table->index('card_type', 'idx_rfid_card_mappings_type');

            // Unique constraint: Only one active badge per employee
            $table->unique(['employee_id', 'is_active'], 'uk_employee_active_badge');

            // Table Comment
            $table->comment('RFID badge mappings: Associates card UIDs to employees for timekeeping and access control');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfid_card_mappings');
    }
};
