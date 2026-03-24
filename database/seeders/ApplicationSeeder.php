<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\JobPosting;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * How each candidate maps to a role — telling their story:
         *
         *  Raphael Villanueva      → Software Engineer   (referred, fast-tracked to shortlist)
         *  Angelica Delos Reyes    → Software Engineer   (fresh grad, still under review)
         *  Paolo Resurreccion      → Software Engineer   (mid-level, already interviewed)
         *  Kristine Joy Manalo     → HR Specialist       (career shifter, shortlisted)
         *  Danielle Buenaventura   → HR Specialist       (experienced, moved to interview)
         *  Maribel Ignacio         → HR Specialist       (walk-in, application submitted)
         *  Bernard Ocampo          → Accountant          (CPA, interviewed — top candidate)
         *  Jerome Castillo         → Accountant          (referred by Bernard, just submitted)
         */

        $map = [
            // [candidate email, job posting id, status, applied_at offset in days]
            ['raphael.villanueva@gmail.com',    1, 'shortlisted', 30],
            ['angelica.delosreyes@gmail.com',   1, 'submitted',   28],
            ['paolo.resurreccion@gmail.com',    1, 'interviewed', 20],
            ['kjoy.manalo@yahoo.com',           2, 'shortlisted', 25],
            ['danielle.buenaventura@gmail.com', 2, 'interviewed', 15],
            ['maribel.ignacio@gmail.com',       2, 'submitted',   18],
            ['bernard.ocampo@outlook.com',      3, 'interviewed', 22],
            ['jerome.castillo@gmail.com',       3, 'submitted',   12],
        ];

        foreach ($map as [$email, $jobId, $status, $daysAgo]) {
            $candidate  = Candidate::where('email', $email)->first();
            $jobPosting = JobPosting::find($jobId);

            if (! $candidate || ! $jobPosting) {
                $this->command->warn("⚠️  Skipping: {$email} or job #{$jobId} not found.");
                continue;
            }

            Application::firstOrCreate(
                [
                    'candidate_id'   => $candidate->id,
                    'job_posting_id' => $jobPosting->id,
                ],
                [
                    'status'     => $status,
                    'applied_at' => now()->subDays($daysAgo),
                    'created_at' => now()->subDays($daysAgo),
                    'updated_at' => now()->subDays($daysAgo),
                ]
            );
        }

        $this->command->info('✅ Seeded ' . count($map) . ' applications.');
    }
}