<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobPosting;

class JobPostingSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Cameco opens three roles in Q1 to support its growth roadmap:
         *
         *  1. Software Engineer — IT team needs hands for a new internal ERP module
         *  2. HR Specialist     — Growing headcount means HR needs reinforcement
         *  3. Accountant        — Finance is expanding after last year's audit findings
         *
         * All three postings are live and actively sourcing.
         */

        $it         = \App\Models\Department::where('code', 'IT')->first();
        $hr         = \App\Models\Department::where('code', 'HR')->first();
        $fin        = \App\Models\Department::where('code', 'FIN')->first();
        $superadmin = \App\Models\User::where('email', 'superadmin@cameco.com')->first();
        $createdBy  = $superadmin?->id ?? 1;

        $postings = [
            [
                'id'            => 1,
                'title'         => 'Software Engineer',
                'description'   => 'Join the IT team to design, build, and maintain Cameco\'s internal ERP modules. '
                                 . 'You\'ll work closely with operations and finance to automate manual workflows, '
                                 . 'reduce processing time, and ensure data integrity across departments.',
                'requirements'  => "• Bachelor's degree in Computer Science, Information Technology, or related field\n"
                                 . "• At least 2 years of professional software development experience\n"
                                 . "• Proficiency in PHP (Laravel) and JavaScript (Vue.js or React)\n"
                                 . "• Familiarity with REST APIs, relational databases (MySQL/PostgreSQL)\n"
                                 . "• Experience with Git and agile/scrum workflows\n"
                                 . "• Strong problem-solving skills and attention to detail",
                'department_id' => $it?->id ?? 1,
                'status'        => 'open',
                'created_by'    => $createdBy,
                'posted_at'     => now()->subDays(30),
                'created_at'    => now()->subDays(30),
                'updated_at'    => now()->subDays(30),
            ],
            [
                'id'            => 2,
                'title'         => 'HR Specialist',
                'description'   => 'Support end-to-end recruitment and employee relations for Cameco\'s growing team. '
                                 . 'You\'ll manage job postings, screen applicants, coordinate interviews, '
                                 . 'onboard new hires, and help maintain a healthy company culture.',
                'requirements'  => "• Bachelor's degree in Psychology, Human Resource Management, or related field\n"
                                 . "• At least 1 year of relevant HR or recruitment experience\n"
                                 . "• Excellent interpersonal and communication skills\n"
                                 . "• Familiarity with Philippine labor laws and DOLE regulations\n"
                                 . "• Proficiency in MS Office and HRIS tools\n"
                                 . "• Highly organized with the ability to juggle multiple requisitions",
                'department_id' => $hr?->id ?? 2,
                'status'        => 'open',
                'created_by'    => $createdBy,
                'posted_at'     => now()->subDays(28),
                'created_at'    => now()->subDays(28),
                'updated_at'    => now()->subDays(28),
            ],
            [
                'id'            => 3,
                'title'         => 'Accountant',
                'description'   => 'Handle financial reporting, tax compliance, and payroll for Cameco. '
                                 . 'Following last year\'s external audit recommendations, this role will also '
                                 . 'spearhead the overhaul of internal controls and cost-center monitoring.',
                'requirements'  => "• Bachelor's degree in Accountancy; CPA license is a strong advantage\n"
                                 . "• Minimum 2 years of accounting experience (public accounting experience a plus)\n"
                                 . "• Working knowledge of BIR compliance and PFRS\n"
                                 . "• Proficiency in accounting software (QuickBooks, Xero, or SAP)\n"
                                 . "• High integrity, detail-oriented, and deadline-driven\n"
                                 . "• Experience with internal audit or internal controls is desirable",
                'department_id' => $fin?->id ?? 3,
                'status'        => 'open',
                'created_by'    => $createdBy,
                'posted_at'     => now()->subDays(25),
                'created_at'    => now()->subDays(25),
                'updated_at'    => now()->subDays(25),
            ],
        ];

        foreach ($postings as $data) {
            JobPosting::updateOrCreate(['id' => $data['id']], $data);
        }

        $this->command->info('✅ Seeded ' . count($postings) . ' job postings.');

        // --- Reset PostgreSQL sequence to max(id) ---
        // This prevents duplicate key errors when inserting new job postings after seeding with explicit IDs.
        $connection = \DB::connection()->getDriverName();
        if ($connection === 'pgsql') {
            $maxId = \DB::table('job_postings')->max('id');
            if ($maxId) {
                $sequence = 'job_postings_id_seq';
                \DB::statement("SELECT setval('$sequence', $maxId)");
                $this->command->info("[PostgreSQL] Reset $sequence to $maxId");
            }
        }
    }
}