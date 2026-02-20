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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            
            // Method Type
            $table->enum('method_type', ['cash', 'bank', 'ewallet', 'check'])->unique();
            $table->string('display_name', 50);
            $table->text('description')->nullable();
            
            // Configuration
            $table->boolean('is_enabled')->default(false);
            $table->boolean('requires_employee_setup')->default(false);
            $table->boolean('supports_bulk_payment')->default(false);
            $table->decimal('transaction_fee', 8, 2)->default(0);
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_amount', 10, 2)->nullable();
            
            // Processing Settings
            $table->enum('settlement_speed', ['instant', 'same_day', 'next_day', 'manual'])->default('manual');
            $table->integer('processing_days')->default(0); // Days to settlement
            $table->time('cutoff_time')->nullable(); // Daily cutoff for same-day
            
            // Bank-specific
            $table->string('bank_code')->nullable(); // e.g., 'MBTC' for Metrobank
            $table->string('bank_name')->nullable();
            $table->string('file_format')->nullable(); // 'csv', 'xlsx', 'txt', 'dat'
            $table->json('file_template')->nullable(); // Column mapping
            
            // E-wallet-specific
            $table->string('provider_name')->nullable(); // 'GCash', 'Maya', 'PayMongo'
            $table->string('api_endpoint')->nullable();
            $table->text('api_credentials')->nullable(); // Encrypted
            $table->string('webhook_url')->nullable();
            
            // Priority & Display
            $table->integer('sort_order')->default(999);
            $table->string('icon')->nullable();
            $table->string('color_hex', 7)->nullable();
            
            // Audit
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['method_type', 'is_enabled']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
