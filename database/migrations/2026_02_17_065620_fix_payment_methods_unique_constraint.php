<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix payment_methods unique constraint.
 *
 * The original migration added a unique constraint on `method_type` alone,
 * which only allows one record per type (cash, bank, ewallet).
 * We need multiple bank/ewallet records (Metrobank + BDO, GCash + Maya).
 *
 * New constraint: unique on (method_type, bank_code, provider_name) to allow
 * multiple records per type distinguished by bank_code or provider_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Drop the single-column unique index on method_type
            $table->dropUnique(['method_type']);

            // Add composite unique: one record per (type + bank_code + provider_name)
            // NULLs are treated as distinct in PostgreSQL, so cash records
            // (both bank_code and provider_name = NULL) would still conflict.
            // Use display_name to disambiguate instead.
            $table->unique(['method_type', 'display_name'], 'payment_methods_type_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropUnique('payment_methods_type_name_unique');
            $table->unique('method_type');
        });
    }
};
