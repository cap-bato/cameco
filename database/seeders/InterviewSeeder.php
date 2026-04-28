<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Interview;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\JobPosting;
use Carbon\Carbon;

class InterviewSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Three candidates reached the interview stage:
         *
         *  Paolo Resurreccion   — Software Engineer
         *    Round 1: Phone screen with HR (done)
         *    Round 2: Technical panel with IT lead (upcoming)
         *
         *  Danielle Buenaventura — HR Specialist
         *    Round 1: Video call with HR Manager (done)
         *    Round 2: In-person with Department Head (upcoming)
         *
         *  Bernard Ocampo       — Accountant
         *    Round 1: Phone screen with HR (done)
         *    Round 2: In-person with Finance Director — final round (upcoming)
         *
         * Paolo and Bernard were both referred, which explains the faster cadence.
         */

        $interviews = [
            // --- Paolo: Round 1 (completed phone screen) ---
            [
                'candidate_email'   => 'paolo.resurreccion@gmail.com',
                'job_posting_id'    => 1,
                'scheduled_date'    => now()->subDays(10)->format('Y-m-d'),
                'scheduled_time'    => '10:00',
                'duration_minutes'  => 30,
                'location_type'     => 'phone',
                'status'            => 'completed',
                'interviewer_name'  => 'Maria Lourdes Fajardo',
                'notes'             => 'Strong communication, good problem-solving instincts. Recommended for technical panel.',
            ],
            // --- Paolo: Round 2 (upcoming technical panel) ---
            [
                'candidate_email'   => 'paolo.resurreccion@gmail.com',
                'job_posting_id'    => 1,
                'scheduled_date'    => now()->addDays(3)->format('Y-m-d'),
                'scheduled_time'    => '14:00',
                'duration_minutes'  => 90,
                'location_type'     => 'office',
                'status'            => 'scheduled',
                'interviewer_name'  => 'Engr. Ronald Dela Torre',
                'notes'             => 'Technical deep-dive: system design, Laravel architecture, live coding exercise.',
            ],

            // --- Danielle: Round 1 (completed video call) ---
            [
                'candidate_email'   => 'danielle.buenaventura@gmail.com',
                'job_posting_id'    => 2,
                'scheduled_date'    => now()->subDays(8)->format('Y-m-d'),
                'scheduled_time'    => '11:00',
                'duration_minutes'  => 45,
                'location_type'     => 'video_call',
                'status'            => 'completed',
                'interviewer_name'  => 'Maria Lourdes Fajardo',
                'notes'             => 'Impressive grasp of PH labor law. Relocation timeline is manageable. Move to in-person.',
            ],
            // --- Danielle: Round 2 (upcoming in-person with dept head) ---
            [
                'candidate_email'   => 'danielle.buenaventura@gmail.com',
                'job_posting_id'    => 2,
                'scheduled_date'    => now()->addDays(5)->format('Y-m-d'),
                'scheduled_time'    => '13:00',
                'duration_minutes'  => 60,
                'location_type'     => 'office',
                'status'            => 'scheduled',
                'interviewer_name'  => 'Atty. Corazon Mendez',
                'notes'             => 'Culture fit and leadership potential assessment with Department Head.',
            ],

            // --- Bernard: Round 1 (completed phone screen) ---
            [
                'candidate_email'   => 'bernard.ocampo@outlook.com',
                'job_posting_id'    => 3,
                'scheduled_date'    => now()->subDays(14)->format('Y-m-d'),
                'scheduled_time'    => '09:30',
                'duration_minutes'  => 30,
                'location_type'     => 'phone',
                'status'            => 'completed',
                'interviewer_name'  => 'Maria Lourdes Fajardo',
                'notes'             => 'CPA with Big 4 background. Salary expectations within range. Fast-tracked to final round.',
            ],
            // --- Bernard: Round 2 (final round, in-person with Finance Director) ---
            [
                'candidate_email'   => 'bernard.ocampo@outlook.com',
                'job_posting_id'    => 3,
                'scheduled_date'    => now()->addDays(2)->format('Y-m-d'),
                'scheduled_time'    => '10:00',
                'duration_minutes'  => 60,
                'location_type'     => 'office',
                'status'            => 'scheduled',
                'interviewer_name'  => 'CFO Jose Antonio Reyes',
                'notes'             => 'Final round. Discuss internal controls overhaul roadmap and compensation package.',
            ],
        ];

        foreach ($interviews as $data) {
            $candidate = Candidate::where('email', $data['candidate_email'])->first();

            if (! $candidate) {
                $this->command->warn("⚠️  Candidate not found: {$data['candidate_email']}");
                continue;
            }

            $application = Application::where('candidate_id', $candidate->id)
                ->where('job_posting_id', $data['job_posting_id'])
                ->first();

            if (! $application) {
                $this->command->warn("⚠️  Application not found for {$data['candidate_email']} → job #{$data['job_posting_id']}");
                continue;
            }

            Interview::create([
                'application_id'   => $application->id,
                'candidate_id'     => $candidate->id,
                'job_title'        => JobPosting::find($data['job_posting_id'])?->title ?? 'Unknown',
                'scheduled_date'   => $data['scheduled_date'],
                'scheduled_time'   => $data['scheduled_time'],
                'duration_minutes' => $data['duration_minutes'],
                'location_type'    => $data['location_type'],
                'status'           => $data['status'],
                'interviewer_name' => $data['interviewer_name'],
            ]);
        }

        $this->command->info('✅ Seeded ' . count($interviews) . ' interviews.');
    }
}