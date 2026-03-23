<?php

namespace Database\Seeders;

use App\Models\LeavePolicy;
use Illuminate\Database\Seeder;

class LeavePolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            ['code' => 'VL', 'name' => 'Vacation Leave', 'description' => 'Annual vacation or holiday leave for personal rest and relaxation', 'annual_entitlement' => 15.0, 'max_carryover' => 5.0, 'can_carry_forward' => true, 'is_paid' => true],
            ['code' => 'SL', 'name' => 'Sick Leave', 'description' => 'Leave for illness or medical treatment', 'annual_entitlement' => 10.0, 'max_carryover' => 0.0, 'can_carry_forward' => false, 'is_paid' => true],
            ['code' => 'EL', 'name' => 'Emergency Leave', 'description' => 'Leave for urgent personal or family emergencies', 'annual_entitlement' => 5.0, 'max_carryover' => 0.0, 'can_carry_forward' => false, 'is_paid' => true],
            ['code' => 'ML', 'name' => 'Maternity/Paternity Leave', 'description' => 'Leave for new parents', 'annual_entitlement' => 90.0, 'max_carryover' => 0.0, 'can_carry_forward' => false, 'is_paid' => true],
            ['code' => 'PL', 'name' => 'Privilege Leave', 'description' => 'General personal leave', 'annual_entitlement' => 8.0, 'max_carryover' => 2.0, 'can_carry_forward' => true, 'is_paid' => true],
            ['code' => 'BL', 'name' => 'Bereavement Leave', 'description' => 'Leave for death of a family member', 'annual_entitlement' => 3.0, 'max_carryover' => 0.0, 'can_carry_forward' => false, 'is_paid' => true],
            ['code' => 'SP', 'name' => 'Special Leave', 'description' => 'Leave for special circumstances', 'annual_entitlement' => 0.0, 'max_carryover' => 0.0, 'can_carry_forward' => false, 'is_paid' => false],
            ['code' => 'HAM', 'name' => 'Half Day AM Leave', 'description' => 'Half-day leave for morning (AM) only', 'annual_entitlement' => 0.0, 'max_carryover' => 0.0, 'can_carry_forward' => false, 'is_paid' => true],
            ['code' => 'HPM', 'name' => 'Half Day PM Leave', 'description' => 'Half-day leave for afternoon (PM) only', 'annual_entitlement' => 0.0, 'max_carryover' => 0.0, 'can_carry_forward' => false, 'is_paid' => true],
        ];

        foreach ($policies as $p) {
            LeavePolicy::firstOrCreate(['code' => $p['code']], $p);
        }
    }
}
