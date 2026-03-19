<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Update interviews table enums to match controller expectations and TypeScript types.
     * - status: add 'no_show' and change 'canceled' to 'cancelled'
     * - location_type: change from ['office', 'virtual'] to ['office', 'video_call', 'phone']
     */
    public function up(): void
    {
        // Use VARCHAR for status and location_type for maximum compatibility
        Schema::table('interviews', function (Blueprint $table) {
            $table->string('status', 32)->default('scheduled')->change();
            $table->string('location_type', 32)->nullable()->change();
        });
        // Optionally, update existing rows to use new values if needed:
        // DB::statement("UPDATE interviews SET status = 'cancelled' WHERE status = 'canceled'");
        // DB::statement("UPDATE interviews SET location_type = 'video_call' WHERE location_type = 'virtual'");
    }

    public function down(): void
    {
        // Optionally, revert to previous type if needed (not implemented)
    }
};
