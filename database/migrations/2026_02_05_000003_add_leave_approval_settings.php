<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'key' => 'leave_auto_approval_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable auto-approval for 1-2 day leave requests',
                'category' => 'leave_management',
                'is_encrypted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'leave_min_advance_notice_days',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Minimum advance notice days for leave requests',
                'category' => 'leave_management',
                'is_encrypted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'leave_approval_routing',
                'value' => json_encode([
                    '1-2' => ['auto'], // Auto-approval if conditions met
                    '3-5' => ['manager'], // HR Manager only
                    '6+' => ['manager', 'admin'], // Both required
                ]),
                'type' => 'json',
                'description' => 'Leave approval routing based on duration',
                'category' => 'leave_management',
                'is_encrypted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('system_settings')->insert($settings);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('key', [
                'leave_auto_approval_enabled',
                'leave_min_advance_notice_days',
                'leave_approval_routing',
            ])
            ->delete();
    }
};
