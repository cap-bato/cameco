<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobPosting;
use Illuminate\Support\Facades\DB;

class JobPostingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Optionally clear table first
        // DB::table('job_postings')->truncate();

        // Get department IDs by code
        $hr = \App\Models\Department::where('code', 'HR')->first();
        $it = \App\Models\Department::where('code', 'IT')->first();
        $fin = \App\Models\Department::where('code', 'FIN')->first();

        $jobPostings = [
            [
                'id' => 1,
                'title' => 'Software Engineer',
                'description' => 'Develop and maintain software applications.',
                'requirements' => 'Bachelor’s degree in Computer Science or related field. 2+ years experience in software development.',
                'department_id' => $it ? $it->id : 1,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'title' => 'HR Specialist',
                'description' => 'Handle HR-related tasks and recruitment.',
                'requirements' => 'Bachelor’s degree in HR, Psychology, or related field. 1+ year HR experience preferred.',
                'department_id' => $hr ? $hr->id : 1,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'title' => 'Accountant',
                'description' => 'Manage company finances and payroll.',
                'requirements' => 'Bachelor’s degree in Accountancy. CPA preferred. 2+ years accounting experience.',
                'department_id' => $fin ? $fin->id : 1,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($jobPostings as $data) {
            JobPosting::updateOrCreate(['id' => $data['id']], $data);
        }
    }
}
