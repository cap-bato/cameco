<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Migrates existing leave requests from HAM (Half Day AM Leave) and HPM (Half Day PM Leave)
     * policies to Sick Leave (SL) policy with leave_type_variant set to 'half_am' or 'half_pm'.
     * 
     * This consolidates half-day leave under the Sick Leave policy rather than as separate policies.
     */
    public function up(): void
    {
        // Get policy IDs
        $hamPolicyId = DB::table('leave_policies')->where('code', 'HAM')->value('id');
        $hpmPolicyId = DB::table('leave_policies')->where('code', 'HPM')->value('id');
        $slPolicyId = DB::table('leave_policies')->where('code', 'SL')->value('id');

        // Skip if HAM/HPM policies don't exist (deprecated policies)
        // Only proceed if SL policy exists for migration to target
        if (!$hamPolicyId || !$hpmPolicyId || !$slPolicyId) {
            return;
        }

        // Migrate HAM requests to SL with half_am variant
        DB::table('leave_requests')
            ->where('leave_policy_id', $hamPolicyId)
            ->update([
                'leave_policy_id' => $slPolicyId,
                'leave_type_variant' => 'half_am',
                'days_requested' => 0.5
            ]);

        // Migrate HPM requests to SL with half_pm variant
        DB::table('leave_requests')
            ->where('leave_policy_id', $hpmPolicyId)
            ->update([
                'leave_policy_id' => $slPolicyId,
                'leave_type_variant' => 'half_pm',
                'days_requested' => 0.5
            ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Reverts the migration by moving Sick Leave requests with half-day variants
     * back to their respective HAM/HPM policies and clearing the variant.
     */
    public function down(): void
    {
        // Get policy IDs
        $hamPolicyId = DB::table('leave_policies')->where('code', 'HAM')->value('id');
        $hpmPolicyId = DB::table('leave_policies')->where('code', 'HPM')->value('id');
        $slPolicyId = DB::table('leave_policies')->where('code', 'SL')->value('id');

        // Only proceed if all policies exist
        if (!$hamPolicyId || !$hpmPolicyId || !$slPolicyId) {
            return;
        }

        // Revert half_am variant requests back to HAM policy
        DB::table('leave_requests')
            ->where('leave_policy_id', $slPolicyId)
            ->where('leave_type_variant', 'half_am')
            ->update([
                'leave_policy_id' => $hamPolicyId,
                'leave_type_variant' => null,
                'days_requested' => 0.5
            ]);

        // Revert half_pm variant requests back to HPM policy
        DB::table('leave_requests')
            ->where('leave_policy_id', $slPolicyId)
            ->where('leave_type_variant', 'half_pm')
            ->update([
                'leave_policy_id' => $hpmPolicyId,
                'leave_type_variant' => null,
                'days_requested' => 0.5
            ]);
    }
};
