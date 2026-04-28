<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add department_id to leave_requests and backfill from employees table.
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('employee_id')->index();
        });

        // Backfill department_id for all leave_requests
        DB::statement('
            UPDATE leave_requests lr
            SET department_id = (
                SELECT e.department_id FROM employees e WHERE e.id = lr.employee_id
            )
        ');
    }

    /**
     * Remove department_id from leave_requests.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('department_id');
        });
    }
};
