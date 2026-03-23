<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Marks HAM (Half Day AM Leave) and HPM (Half Day PM Leave) policies as inactive.
     * These policies are being deprecated in favor of using a leave_type_variant approach
     * where Half Day variants are stored on the leave_request record associated with Sick Leave.
     * 
     * Note: Records are not deleted to maintain data integrity for historical leave requests.
     */
    public function up(): void
    {
        DB::table('leave_policies')
            ->whereIn('code', ['HAM', 'HPM'])
            ->update([
                'is_active' => false,
                'description' => DB::raw("CONCAT(description, ' [DEPRECATED - use Sick Leave with half-day variant instead]')")
            ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Reactivates HAM and HPM policies by resetting is_active to true
     * and removing the deprecation message from descriptions.
     */
    public function down(): void
    {
        DB::table('leave_policies')
            ->whereIn('code', ['HAM', 'HPM'])
            ->update([
                'is_active' => true,
                'description' => DB::raw("REPLACE(description, ' [DEPRECATED - use Sick Leave with half-day variant instead]', '')")
            ]);
    }
};
