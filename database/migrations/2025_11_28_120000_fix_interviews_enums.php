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
        Schema::table('interviews', function (Blueprint $table) {
            // Update status enum: add 'no_show', standardize to 'cancelled'
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])
                ->default('scheduled')
                ->change();

            // Update location_type enum: change 'virtual' to 'video_call' and 'phone'
            $table->enum('location_type', ['office', 'video_call', 'phone'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            // Revert status enum
            $table->enum('status', ['scheduled', 'completed', 'canceled'])->default('scheduled')->change();

            // Revert location_type enum
            $table->enum('location_type', ['office', 'virtual'])->change();
        });
    }
};
