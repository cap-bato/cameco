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
        // Disabled: employees.status is now VARCHAR, not enum. No action needed.
        // This migration is a no-op for compatibility.
    }

    /**
     * No down migration (removing enum values is not supported in PostgreSQL).
     */
    public function down(): void
    {
        // Not supported
    }
};
