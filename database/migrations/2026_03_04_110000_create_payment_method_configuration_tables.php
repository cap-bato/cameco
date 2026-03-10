<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->string('code')->unique(); // bdo, bpi, gcash, maya, etc.
            $table->string('name');
            $table->string('category'); // local_bank, international_bank, ewallet
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_available')->default(true);
            $table->json('configuration')->nullable();
            $table->decimal('transaction_fee', 10, 2)->default(0);
            $table->string('fee_type')->default('fixed'); // fixed, percentage
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_amount', 10, 2)->nullable();
            $table->integer('daily_limit')->nullable();
            $table->integer('monthly_limit')->nullable();
            $table->integer('processing_time_hours')->default(24);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['payment_method_id', 'is_enabled']);
            $table->index('category');
            $table->index('sort_order');
        });

        Schema::create('payment_method_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_level')->nullable();
            $table->foreignId('default_payment_method_provider_id')->constrained('payment_method_providers')->cascadeOnDelete();
            $table->json('allowed_payment_method_providers');
            $table->boolean('allow_employee_change')->default(true);
            $table->string('approval_required_for_change')->default('none'); // none, supervisor, office_admin
            $table->timestamps();

            $table->index(['department_id', 'employee_level']);
        });

        Schema::create('employee_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_provider_id')->constrained('payment_method_providers')->cascadeOnDelete();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'payment_method_provider_id']);
            $table->index('employee_id');
            $table->index('is_default');
        });

        Schema::create('payment_method_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_provider_id')->constrained('payment_method_providers')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('status'); // pending, processing, completed, failed
            $table->string('transaction_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_method_provider_id', 'created_at']);
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index('status');
        });

        $this->seedDefaultPaymentMethodProviders();
    }

    protected function seedDefaultPaymentMethodProviders(): void
    {
        $bankMethodId = DB::table('payment_methods')->where('method_type', 'bank')->value('id');
        $ewalletMethodId = DB::table('payment_methods')->where('method_type', 'ewallet')->value('id');

        $now = now();
        $providers = [];

        if ($bankMethodId) {
            $providers = array_merge($providers, [
                ['payment_method_id' => $bankMethodId, 'code' => 'bdo', 'name' => 'BDO Unibank', 'category' => 'local_bank', 'is_available' => true, 'sort_order' => 1],
                ['payment_method_id' => $bankMethodId, 'code' => 'bpi', 'name' => 'Bank of the Philippine Islands (BPI)', 'category' => 'local_bank', 'is_available' => true, 'sort_order' => 2],
                ['payment_method_id' => $bankMethodId, 'code' => 'metrobank', 'name' => 'Metrobank', 'category' => 'local_bank', 'is_available' => true, 'sort_order' => 3],
                ['payment_method_id' => $bankMethodId, 'code' => 'unionbank', 'name' => 'UnionBank', 'category' => 'local_bank', 'is_available' => true, 'sort_order' => 4],
                ['payment_method_id' => $bankMethodId, 'code' => 'landbank', 'name' => 'Land Bank of the Philippines', 'category' => 'local_bank', 'is_available' => true, 'sort_order' => 5],
                ['payment_method_id' => $bankMethodId, 'code' => 'pnb', 'name' => 'Philippine National Bank (PNB)', 'category' => 'local_bank', 'is_available' => true, 'sort_order' => 6],
                ['payment_method_id' => $bankMethodId, 'code' => 'security_bank', 'name' => 'Security Bank', 'category' => 'local_bank', 'is_available' => true, 'sort_order' => 7],
                ['payment_method_id' => $bankMethodId, 'code' => 'rcbc', 'name' => 'Rizal Commercial Banking Corporation (RCBC)', 'category' => 'local_bank', 'is_available' => true, 'sort_order' => 8],
            ]);
        }

        if ($ewalletMethodId) {
            $providers = array_merge($providers, [
                ['payment_method_id' => $ewalletMethodId, 'code' => 'gcash', 'name' => 'GCash', 'category' => 'ewallet', 'is_available' => true, 'processing_time_hours' => 1, 'sort_order' => 101],
                ['payment_method_id' => $ewalletMethodId, 'code' => 'maya', 'name' => 'Maya (PayMaya)', 'category' => 'ewallet', 'is_available' => true, 'processing_time_hours' => 1, 'sort_order' => 102],
                ['payment_method_id' => $ewalletMethodId, 'code' => 'grabpay', 'name' => 'GrabPay', 'category' => 'ewallet', 'is_available' => true, 'processing_time_hours' => 1, 'sort_order' => 103],
            ]);
        }

        foreach ($providers as $provider) {
            DB::table('payment_method_providers')->insert(array_merge($provider, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_usage_logs');
        Schema::dropIfExists('employee_payment_methods');
        Schema::dropIfExists('payment_method_policies');
        Schema::dropIfExists('payment_method_providers');
    }
};
