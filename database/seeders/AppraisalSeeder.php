<?php

namespace Database\Seeders;

use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\AppraisalScore;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class AppraisalSeeder extends Seeder
{
    public function run(): void
    {
        // Get or find the Annual Review 2025 cycle
        $cycle = AppraisalCycle::where('name', 'Annual Review 2025')->first();
        if (!$cycle) {
            $this->command->warn('AppraisalCycle "Annual Review 2025" not found. Run AppraisalCycleSeeder first.');
            return;
        }

        // Get up to 10 active employees
        $employees = Employee::where('status', 'active')->limit(10)->get();
        
        if ($employees->isEmpty()) {
            $this->command->warn('No active employees found.');
            return;
        }

        // Get all criteria for this cycle
        $criteria = $cycle->criteria;
        
        if ($criteria->isEmpty()) {
            $this->command->warn('No appraisal criteria found for cycle ' . $cycle->name);
            return;
        }

        $this->command->info('Creating appraisals for ' . $employees->count() . ' employees...');

        // Create appraisals for each employee
        foreach ($employees as $employee) {
            // Skip if appraisal already exists
            $existing = Appraisal::where('appraisal_cycle_id', $cycle->id)
                ->where('employee_id', $employee->id)
                ->exists();
            
            if ($existing) {
                $this->command->warn("Appraisal already exists for {$employee->employee_number}");
                continue;
            }

            // Randomly assign status
            $statuses = ['draft', 'in_progress', 'completed', 'acknowledged'];
            $randomStatus = $statuses[array_rand($statuses)];

            $appraisal = Appraisal::create([
                'appraisal_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'appraiser_id' => 1, // Assuming user ID 1 exists
                'status' => $randomStatus,
                'created_by' => 1,
            ]);

            // Add scores if status is completed or acknowledged
            if (in_array($appraisal->status, ['completed', 'acknowledged'])) {
                $totalScore = 0;
                $criteriaCount = $criteria->count();

                foreach ($criteria as $criterion) {
                    // Generate a random score between 6.0 and 9.5
                    $score = round(rand(60, 95) / 10, 1);
                    
                    AppraisalScore::create([
                        'appraisal_id' => $appraisal->id,
                        'appraisal_criteria_id' => $criterion->id,
                        'score' => $score,
                        'comments' => 'Sample comment for ' . $criterion->name,
                    ]);
                    
                    $totalScore += $score;
                }

                // Calculate weighted average
                $overallScore = round($totalScore / $criteriaCount, 2);

                // Update appraisal with overall score and submission date
                $appraisal->update([
                    'overall_score' => $overallScore,
                    'submitted_at' => now()->subDays(rand(1, 30)),
                    'feedback' => 'Overall performance was satisfactory during this review period.',
                ]);

                $this->command->line("✓ {$employee->employee_number} - {$appraisal->status} - Score: {$overallScore}");
            } else {
                $this->command->line("✓ {$employee->employee_number} - {$appraisal->status}");
            }
        }

        $this->command->info('Appraisal seeding completed successfully.');
    }
}
