<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->string('location_type', 32)->nullable()->change();
        });
        // Update any legacy/invalid values to valid ones before adding the CHECK constraint
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Map 'virtual' to 'video_call', and NULL/other invalids to 'office' (or NULL if you prefer)
            \DB::statement("UPDATE interviews SET location_type = 'video_call' WHERE location_type = 'virtual'");
            \DB::statement("UPDATE interviews SET location_type = 'office' WHERE location_type IS NULL OR location_type NOT IN ('office', 'video_call', 'phone')");
            \DB::statement(<<<SQL
                ALTER TABLE interviews
                DROP CONSTRAINT IF EXISTS interviews_location_type_check,
                ADD CONSTRAINT interviews_location_type_check
                CHECK (location_type IN ('office', 'video_call', 'phone'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->string('location_type', 32)->nullable()->change();
        });
        // Restore old CHECK constraint (PostgreSQL only)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            \DB::statement(<<<SQL
                ALTER TABLE interviews
                DROP CONSTRAINT IF EXISTS interviews_location_type_check,
                ADD CONSTRAINT interviews_location_type_check
                CHECK (location_type IN ('office', 'virtual'))
            SQL);
        }
    }
};
