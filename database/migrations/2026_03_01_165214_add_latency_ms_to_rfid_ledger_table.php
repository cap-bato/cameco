<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Phase 4, Task 4.1: Add latency_ms column for real metric calculations
     * This column stores the processing latency in milliseconds for each ledger entry.
     */
    public function up(): void
    {
        Schema::table('rfid_ledger', function (Blueprint $table) {
            // Add latency_ms column for performance metric tracking
            $table->integer('latency_ms')->nullable()->after('device_signature')
                ->comment('Processing latency in milliseconds (time from scan to ledger entry)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfid_ledger', function (Blueprint $table) {
            $table->dropColumn('latency_ms');
        });
    }
};
