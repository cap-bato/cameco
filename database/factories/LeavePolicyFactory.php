<?php

namespace Database\Factories;

use App\Models\LeavePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeavePolicyFactory extends Factory
{
    protected $model = LeavePolicy::class;

    public function definition(): array
    {
        $codes = ['VL','SL','EL','ML','PL','BL','SP','HAM','HPM'];
        $code = $this->faker->unique()->randomElement($codes);
        $names = [
            'VL' => 'Vacation Leave',
            'SL' => 'Sick Leave',
            'EL' => 'Emergency Leave',
            'ML' => 'Maternity/Paternity Leave',
            'PL' => 'Privilege Leave',
            'BL' => 'Bereavement Leave',
            'SP' => 'Special Leave',
            'HAM' => 'Half Day AM Leave',
            'HPM' => 'Half Day PM Leave'
        ];

        return [
            'code' => $code,
            'name' => $names[$code] ?? 'Other',
            'description' => $this->faker->sentence(),
            'annual_entitlement' => $this->faker->randomFloat(1, 0, 90),
            'max_carryover' => $this->faker->randomFloat(1, 0, 10),
            'can_carry_forward' => $this->faker->boolean(30),
            'is_paid' => $this->faker->boolean(90),
            'is_active' => true,
        ];
    }
}
