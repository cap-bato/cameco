<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaxBracketsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $effectiveFrom = Carbon::parse('2018-01-01'); // TRAIN Law effective date
        
        // Tax statuses to populate
        $taxStatuses = [
            'Z' => 'Zero/Exempt',
            'S' => 'Single',
            'ME' => 'Married Employee',
            'S1' => 'Single with 1 Dependent',
            'ME1' => 'Married with 1 Dependent',
            'S2' => 'Single with 2 Dependents',
            'ME2' => 'Married with 2 Dependents',
            'S3' => 'Single with 3 Dependents',
            'ME3' => 'Married with 3 Dependents',
            'S4' => 'Single with 4 Dependents',
            'ME4' => 'Married with 4 Dependents',
        ];
        
        // ============================================================
        // TRAIN LAW TAX BRACKETS (2018 onwards)
        // Annualized Progressive Tax Table
        // ============================================================
        
        $brackets = [
            // Bracket 1: ₱0 - ₱250,000 (0%)
            [
                'level' => 1,
                'income_from' => 0,
                'income_to' => 250000,
                'base_tax' => 0,
                'tax_rate' => 0,
                'excess_over' => 0,
            ],
            // Bracket 2: ₱250,001 - ₱400,000 (15%)
            [
                'level' => 2,
                'income_from' => 250000.01,
                'income_to' => 400000,
                'base_tax' => 0,
                'tax_rate' => 15,
                'excess_over' => 250000,
            ],
            // Bracket 3: ₱400,001 - ₱800,000 (20%)
            [
                'level' => 3,
                'income_from' => 400000.01,
                'income_to' => 800000,
                'base_tax' => 22500, // ₱150k × 15%
                'tax_rate' => 20,
                'excess_over' => 400000,
            ],
            // Bracket 4: ₱800,001 - ₱2,000,000 (25%)
            [
                'level' => 4,
                'income_from' => 800000.01,
                'income_to' => 2000000,
                'base_tax' => 102500, // ₱22,500 + (₱400k × 20%)
                'tax_rate' => 25,
                'excess_over' => 800000,
            ],
            // Bracket 5: ₱2,000,001 - ₱8,000,000 (30%)
            [
                'level' => 5,
                'income_from' => 2000000.01,
                'income_to' => 8000000,
                'base_tax' => 402500, // ₱102,500 + (₱1.2M × 25%)
                'tax_rate' => 30,
                'excess_over' => 2000000,
            ],
            // Bracket 6: ₱8,000,001+ (35%)
            [
                'level' => 6,
                'income_from' => 8000000.01,
                'income_to' => null,
                'base_tax' => 2202500, // ₱402,500 + (₱6M × 30%)
                'tax_rate' => 35,
                'excess_over' => 8000000,
            ],
        ];
        
        $personalExemption = 50000;      // Standard personal exemption (TRAIN)
        $additionalExemptionPerDependent = 25000; // Per dependent (TRAIN)

        // Insert tax brackets for each status
        foreach ($taxStatuses as $status => $description) {
            // Extract number of dependents from status code
            $dependents = 0;
            if (preg_match('/\d+/', $status, $matches)) {
                $dependents = (int) $matches[0];
            }

            // Total additional exemption varies by number of dependents
            $additionalExemption = $additionalExemptionPerDependent * $dependents;

            foreach ($brackets as $bracket) {
<<<<<<< copilot/sub-pr-31
                DB::table('tax_brackets')->insert([
                    'tax_status' => $status,
                    'status_description' => $description,
                    'bracket_level' => $bracket['level'],
                    'income_from' => $bracket['income_from'],
                    'income_to' => $bracket['income_to'],
                    'base_tax' => $bracket['base_tax'],
                    'tax_rate' => $bracket['tax_rate'],
                    'excess_over' => $bracket['excess_over'],
                    'personal_exemption' => $personalExemption,
                    'additional_exemption' => $additionalExemption,
                    'max_dependents' => $dependents,
                    'effective_from' => $effectiveFrom,
                    'is_active' => true,
                    'notes' => sprintf(
                        'TRAIN Law tax bracket for %s. Exemptions: ₱%s personal + ₱%s additional (%d dependent(s) × ₱%s each)',
                        $description,
                        number_format($personalExemption),
                        number_format($additionalExemption),
                        $dependents,
                        number_format($additionalExemptionPerDependent)
                    ),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
=======
                DB::table('tax_brackets')->updateOrInsert(
                    [
                        'tax_status' => $status,
                        'bracket_level' => $bracket['level'],
                        'effective_from' => $effectiveFrom,
                    ],
                    [
                        'status_description' => $description,
                        'income_from' => $bracket['income_from'],
                        'income_to' => $bracket['income_to'],
                        'base_tax' => $bracket['base_tax'],
                        'tax_rate' => $bracket['tax_rate'],
                        'excess_over' => $bracket['excess_over'],
                        'personal_exemption' => $personalExemption,
                        'additional_exemption' => $additionalExemption,
                        'max_dependents' => 4,
                        'is_active' => true,
                        'notes' => sprintf(
                            'TRAIN Law tax bracket for %s. Exemptions: ₱%s personal + ₱%s × %d dependents',
                            $description,
                            number_format($personalExemption),
                            number_format($additionalExemption),
                            $dependents
                        ),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
>>>>>>> main
            }
        }
        
        $this->command->info('Tax brackets seeded successfully!');
        $this->command->info('- Tax statuses: 11 (Z, S, ME, S1-S4, ME1-ME4)');
        $this->command->info('- Brackets per status: 6');
        $this->command->info('- Total records: 66');
    }
}
