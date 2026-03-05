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
        Schema::create('company_assets', function (Blueprint $table) {
            $table->id();

            // Asset Details
            $table->enum('asset_type', [
                'laptop',
                'desktop',
                'phone',
                'tablet',
                'id_card',
                'access_card',
                'keys',
                'uniform',
                'tools',
                'documents',
                'other'
            ]);
            $table->string('asset_name', 200)->nullable();
            $table->string('serial_number', 200)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();

            // Assignment
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('assigned_date');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            // Condition
            $table->enum('condition_at_issuance', [
                'new',
                'excellent',
                'good',
                'fair'
            ])->default('good');
            $table->decimal('value_at_issuance', 10, 2)->nullable();
            $table->string('photo_at_issuance', 500)->nullable();

            // Return Tracking
            $table->enum('status', [
                'issued',
                'returned',
                'lost',
                'damaged',
                'written_off'
            ])->default('issued');
            $table->date('return_date')->nullable();
            $table->enum('condition_at_return', [
                'excellent',
                'good',
                'fair',
                'poor',
                'damaged',
                'lost'
            ])->nullable();
            $table->text('return_notes')->nullable();
            $table->string('photo_at_return', 500)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            // Financial
            $table->decimal('liability_amount', 10, 2)->default(0.00);
            $table->boolean('deducted_from_final_pay')->default(false);

            // Association with Offboarding
            $table->foreignId('offboarding_case_id')->nullable()->constrained('offboarding_cases')->nullOnDelete();
            $table->foreignId('clearance_item_id')->nullable()->constrained('clearance_items')->nullOnDelete();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('employee_id');
            $table->index('status');
            $table->index('asset_type');
            $table->index('offboarding_case_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_assets');
    }
};
