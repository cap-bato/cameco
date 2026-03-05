<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GovernmentContributionRatesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $effectiveFrom = Carbon::parse('2024-01-01');
        
        // ============================================================
        // SSS CONTRIBUTION BRACKETS (2024 Rates - RA 11199)
        // Total: 13% (4.5% EE + 8.5% ER + EC)
        // ============================================================
        
        $sssRates = [
            // Format: [bracket_code, min, max, MSC, EE_amount, ER_amount, EC_amount]
            ['A', 0, 4249.99, 4000, 180, 340, 10],
            ['B', 4250, 4749.99, 4500, 202.50, 382.50, 10],
            ['C', 4750, 5249.99, 5000, 225, 425, 10],
            ['D', 5250, 5749.99, 5500, 247.50, 467.50, 10],
            ['E', 5750, 6249.99, 6000, 270, 510, 10],
            ['F', 6250, 6749.99, 6500, 292.50, 552.50, 10],
            ['G', 6750, 7249.99, 7000, 315, 595, 10],
            ['H', 7250, 7749.99, 7500, 337.50, 637.50, 10],
            ['I', 7750, 8249.99, 8000, 360, 680, 10],
            ['J', 8250, 8749.99, 8500, 382.50, 722.50, 10],
            ['K', 8750, 9249.99, 9000, 405, 765, 10],
            ['L', 9250, 9749.99, 9500, 427.50, 807.50, 10],
            ['M', 9750, 10249.99, 10000, 450, 850, 10],
            ['N', 10250, 10749.99, 10500, 472.50, 892.50, 10],
            ['O', 10750, 11249.99, 11000, 495, 935, 10],
            ['P', 11250, 11749.99, 11500, 517.50, 977.50, 10],
            ['Q', 11750, 12249.99, 12000, 540, 1020, 10],
            ['R', 12250, 12749.99, 12500, 562.50, 1062.50, 20],
            ['S', 12750, 13249.99, 13000, 585, 1105, 20],
            ['T', 13250, 13749.99, 13500, 607.50, 1147.50, 20],
            ['U', 13750, 14249.99, 14000, 630, 1190, 20],
            ['V', 14250, 14749.99, 14500, 652.50, 1232.50, 20],
            ['W', 14750, 15249.99, 15000, 675, 1275, 20],
            ['X', 15250, 15749.99, 15500, 697.50, 1317.50, 20],
            ['Y', 15750, 16249.99, 16000, 720, 1360, 20],
            ['Z', 16250, 16749.99, 16500, 742.50, 1402.50, 30],
            ['AA', 16750, 17249.99, 17000, 765, 1445, 30],
            ['AB', 17250, 17749.99, 17500, 787.50, 1487.50, 30],
            ['AC', 17750, 18249.99, 18000, 810, 1530, 30],
            ['AD', 18250, 18749.99, 18500, 832.50, 1572.50, 30],
            ['AE', 18750, 19249.99, 19000, 855, 1615, 30],
            ['AF', 19250, 19749.99, 19500, 877.50, 1657.50, 30],
            ['AG', 19750, 20249.99, 20000, 900, 1700, 30],
            ['AH', 20250, 20749.99, 20500, 922.50, 1742.50, 30],
            ['AI', 20750, 21249.99, 21000, 945, 1785, 30],
            ['AJ', 21250, 21749.99, 21500, 967.50, 1827.50, 30],
            ['AK', 21750, 22249.99, 22000, 990, 1870, 30],
            ['AL', 22250, 22749.99, 22500, 1012.50, 1912.50, 30],
            ['AM', 22750, 23249.99, 23000, 1035, 1955, 30],
            ['AN', 23250, 23749.99, 23500, 1057.50, 1997.50, 30],
            ['AO', 23750, 24249.99, 24000, 1080, 2040, 30],
            ['AP', 24250, 24749.99, 24500, 1102.50, 2082.50, 30],
            ['AQ', 24750, 29999.99, 27500, 1237.50, 2337.50, 30],
            ['AR', 30000, null, 30000, 1350, 2550, 30], // ₱30,000+ (no upper limit)
        ];
        
        foreach ($sssRates as $rate) {
            DB::table('government_contribution_rates')->insert([
                'agency' => 'sss',
                'rate_type' => 'bracket',
                'bracket_code' => $rate[0],
                'compensation_min' => $rate[1],
                'compensation_max' => $rate[2],
                'monthly_salary_credit' => $rate[3],
                'employee_rate' => 4.5,
                'employer_rate' => 8.5,
                'total_rate' => 13.0,
                'employee_amount' => $rate[4],
                'employer_amount' => $rate[5],
                'ec_amount' => $rate[6],
                'total_amount' => $rate[4] + $rate[5] + $rate[6],
                'effective_from' => $effectiveFrom,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        
        // ============================================================
        // PHILHEALTH PREMIUM RATES (2024 - RA 11223)
        // Premium: 5% of basic salary (2.5% EE + 2.5% ER)
        // Minimum: ₱500/month (₱10,000 basic salary)
        // Maximum: ₱5,000/month (₱100,000 basic salary)
        // ============================================================
        
        DB::table('government_contribution_rates')->insert([
            'agency' => 'philhealth',
            'rate_type' => 'premium_rate',
            'bracket_code' => null,
            'compensation_min' => 10000,
            'compensation_max' => 100000,
            'employee_rate' => 2.5,
            'employer_rate' => 2.5,
            'total_rate' => 5.0,
            'minimum_contribution' => 500, // ₱500 total (₱250 EE + ₱250 ER)
            'maximum_contribution' => 5000, // ₱5,000 total (₱2,500 EE + ₱2,500 ER)
            'premium_ceiling' => 100000,
            'effective_from' => $effectiveFrom,
            'is_active' => true,
            'notes' => 'PhilHealth premium is 5% of basic salary with ₱10k-₱100k range. Minimum ₱500/month, Maximum ₱5,000/month.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        // ============================================================
        // PAG-IBIG CONTRIBUTION RATES (2024 - RA 9679)
        // ≤ ₱1,500: 1% EE + 2% ER
        // > ₱1,500: 2% EE + 2% ER
        // Employee ceiling: ₱100/month
        // ============================================================
        
        // Low earners (≤ ₱1,500)
        DB::table('government_contribution_rates')->insert([
            'agency' => 'pagibig',
            'rate_type' => 'contribution_rate',
            'bracket_code' => 'LOW',
            'compensation_min' => 0,
            'compensation_max' => 1500,
            'employee_rate' => 1.0,
            'employer_rate' => 2.0,
            'total_rate' => 3.0,
            'contribution_ceiling' => 100, // Employee max ₱100
            'effective_from' => $effectiveFrom,
            'is_active' => true,
            'notes' => 'For salary ≤ ₱1,500: 1% employee + 2% employer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        // Regular earners (> ₱1,500)
        DB::table('government_contribution_rates')->insert([
            'agency' => 'pagibig',
            'rate_type' => 'contribution_rate',
            'bracket_code' => 'REGULAR',
            'compensation_min' => 1500.01,
            'compensation_max' => null,
            'employee_rate' => 2.0,
            'employer_rate' => 2.0,
            'total_rate' => 4.0,
            'contribution_ceiling' => 100, // Employee max ₱100
            'effective_from' => $effectiveFrom,
            'is_active' => true,
            'notes' => 'For salary > ₱1,500: 2% employee + 2% employer. Employee contribution capped at ₱100.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        $this->command->info('Government contribution rates seeded successfully!');
        $this->command->info('- SSS: 44 brackets');
        $this->command->info('- PhilHealth: 1 rate');
        $this->command->info('- Pag-IBIG: 2 rates');
    }
}
