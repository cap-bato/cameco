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

        // Get all candidates and job postings
        $candidates = Candidate::all();
        $jobPostings = \App\Models\JobPosting::all();

        // Only create applications if both exist
        if ($candidates->count() > 0 && $jobPostings->count() > 0) {
            // Pair up candidates and job postings by index, or loop if fewer
            $max = min($candidates->count(), $jobPostings->count(), 10); // up to 10
            for ($i = 0; $i < $max; $i++) {
                $candidate = $candidates[$i % $candidates->count()];
                $jobPosting = $jobPostings[$i % $jobPostings->count()];
                $status = match($i % 3) {
                    0 => 'submitted',
                    1 => 'shortlisted',
                    default => 'interviewed',
                };
                Application::firstOrCreate([
                    'candidate_id' => $candidate->id,
                    'job_posting_id' => $jobPosting->id,
                ], [
                    'candidate_id' => $candidate->id,
                    'job_posting_id' => $jobPosting->id,
                    'status' => $status,
                    'applied_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
