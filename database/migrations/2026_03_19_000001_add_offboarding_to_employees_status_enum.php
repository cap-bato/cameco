<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'offboarding' to employees.status enum (PostgreSQL).
     */
    public function up(): void
    {
        DB::statement("ALTER TYPE employees_status_enum ADD VALUE IF NOT EXISTS 'offboarding'");
    }

    /**
     * No down migration (removing enum values is not supported in PostgreSQL).
     */
    public function down(): void
    {
        // Not supported
    }
};
