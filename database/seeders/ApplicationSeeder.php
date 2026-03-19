<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Application;
use App\Models\Candidate;
use Illuminate\Support\Facades\DB;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Optionally clear table first
        // DB::table('applications')->truncate();

        // Get some candidate IDs
        $candidates = Candidate::take(3)->get();

        $applications = [
            [
                'candidate_id' => $candidates[0]->id ?? 1,
                'job_posting_id' => 1,
                'status' => 'submitted',
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'candidate_id' => $candidates[1]->id ?? 2,
                'job_posting_id' => 2,
                'status' => 'in_review',
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'candidate_id' => $candidates[2]->id ?? 3,
                'job_posting_id' => 3,
                'status' => 'shortlisted',
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($applications as $data) {
            Application::firstOrCreate([
                'candidate_id' => $data['candidate_id'],
                'job_posting_id' => $data['job_posting_id'],
            ], $data);
        }
    }
}
