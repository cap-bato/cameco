<?php

namespace Database\Seeders;

use App\Models\AppraisalCycle;
use App\Models\AppraisalCriteria;
use Illuminate\Database\Seeder;

class AppraisalCycleSeeder extends Seeder
{
    public function run(): void
    {
        // Create Annual Review 2025 cycle
        $cycle = AppraisalCycle::firstOrCreate(
            ['name' => 'Annual Review 2025'],
            [
                'start_date' => now()->startOfYear(),
                'end_date' => now()->endOfYear(),
                'status' => 'open',
                'description' => 'Annual performance review cycle for 2025',
                'created_by' => 1,
            ]
        );

        // Default appraisal criteria if this cycle doesn't have any
        if ($cycle->criteria->isEmpty()) {
            AppraisalCriteria::insert([
                [
                    'appraisal_cycle_id' => $cycle->id,
                    'name' => 'Technical Skills',
                    'description' => 'Proficiency in job-related technical competencies',
                    'weight' => 25,
                    'max_score' => 10,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'appraisal_cycle_id' => $cycle->id,
                    'name' => 'Communication',
                    'description' => 'Ability to communicate effectively with colleagues and clients',
                    'weight' => 20,
                    'max_score' => 10,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'appraisal_cycle_id' => $cycle->id,
                    'name' => 'Team Collaboration',
                    'description' => 'Ability to work effectively with team members',
                    'weight' => 20,
                    'max_score' => 10,
                    'sort_order' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'appraisal_cycle_id' => $cycle->id,
                    'name' => 'Productivity',
                    'description' => 'Ability to meet deadlines and deliver quality work',
                    'weight' => 20,
                    'max_score' => 10,
                    'sort_order' => 4,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'appraisal_cycle_id' => $cycle->id,
                    'name' => 'Leadership',
                    'description' => 'Ability to lead and mentor team members',
                    'weight' => 15,
                    'max_score' => 10,
                    'sort_order' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
