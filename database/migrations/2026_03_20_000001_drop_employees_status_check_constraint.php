<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the employees_status_check constraint if it exists (PostgreSQL only).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_status_check');
        }
    }

    /**
     * No down migration (cannot restore dropped constraint automatically).
     */
    public function down(): void
    {
        // No action
    }
};
