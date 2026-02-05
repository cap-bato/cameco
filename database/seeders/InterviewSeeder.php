<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Candidate;
use App\Models\Application;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InterviewSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------
        // 1. Create dummy department
        // -------------------------------
        $department = Department::firstOrCreate(
            ['id' => 1],
            ['name' => 'Engineering']
        );
        $this->command->info("Department created: {$department->name}");

        // -------------------------------
        // 2. Create dummy user (creator)
        // -------------------------------
        $user = User::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password')
            ]
        );
        $this->command->info("User created: {$user->name}");

        // -------------------------------
        // 3. Create job posting
        // -------------------------------
        $jobPosting = JobPosting::firstOrCreate(
            ['id' => 1],
            [
                'title' => 'Software Engineer',
                'department_id' => $department->id,
                'description' => 'Dummy job posting for seeder',
                'requirements' => 'PHP, Laravel, JS',
                'status' => 'open',
                'created_by' => $user->id,
                'posted_at' => now(),
            ]
        );
        $this->command->info("Job posting created: {$jobPosting->title}");

        // -------------------------------
        // 4. Create 5 dummy candidates
        // -------------------------------
        $candidates = collect();
for ($i = 1; $i <= 5; $i++) {
    $candidates->push(
        Candidate::firstOrCreate(
            ['email' => "candidate{$i}@example.com"],
            [
                'first_name' => "Candidate{$i}",
                'last_name' => "Test{$i}",
                'phone' => '0912' . rand(1000000, 9999999),
            ]
        )
    );
}
$this->command->info("Created {$candidates->count()} candidates.");

        // -------------------------------
        // 5. Create applications for candidates
        // -------------------------------
        $applications = collect();
        foreach ($candidates as $candidate) {
            $applications->push(Application::create([
                'candidate_id' => $candidate->id,
                'job_posting_id' => $jobPosting->id,
                'status' => 'applied',
            ]));
        }
        $this->command->info("Created {$applications->count()} applications.");

        // -------------------------------
        // 6. Create 10 dummy interviews
        // -------------------------------
        for ($i = 1; $i <= 10; $i++) {
            $app = $applications->random();
            Interview::create([
                'application_id' => $app->id,
                'candidate_id' => $app->candidate_id,
                'job_title' => $jobPosting->title,
                'scheduled_date' => Carbon::now()->addDays(rand(1, 10))->format('Y-m-d'),
                'scheduled_time' => Carbon::now()->addHours(rand(8, 17))->format('H:i'),
                'duration_minutes' => 60,
                'location_type' => 'virtual',
                'status' => 'scheduled',
                'interviewer_name' => 'HR Manager',
            ]);
        }
        $this->command->info("Created 10 mock interviews successfully!");
    }
}
