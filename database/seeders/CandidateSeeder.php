<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Candidate;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Meet the applicants of Cameco's Q1 hiring drive.
         *
         * Eight candidates, each with their own story:
         * - Some were referred by existing employees
         * - Some found the postings on job boards
         * - One walked in off the street with a printed résumé
         *
         * Their journeys through the pipeline tell the full picture
         * of a busy recruitment month.
         */

        $candidates = [
            // --- Referred by a current Cameco engineer ---
            [
                'first_name' => 'Raphael',
                'last_name'  => 'Villanueva',
                'email'      => 'raphael.villanueva@gmail.com',
                'phone'      => '09171234001',
                'source'     => 'referral',
                'status'     => 'active',
                'applied_at' => now()->subDays(30),
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(30),
            ],
            // --- Fresh grad from UP Diliman, CS graduate ---
            [
                'first_name' => 'Angelica',
                'last_name'  => 'Delos Reyes',
                'email'      => 'angelica.delosreyes@gmail.com',
                'phone'      => '09181234002',
                'source'     => 'job_board',
                'status'     => 'active',
                'applied_at' => now()->subDays(28),
                'created_at' => now()->subDays(28),
                'updated_at' => now()->subDays(28),
            ],
            // --- Career shifter, former BPO team lead, now aiming for HR ---
            [
                'first_name' => 'Kristine Joy',
                'last_name'  => 'Manalo',
                'email'      => 'kjoy.manalo@yahoo.com',
                'phone'      => '09191234003',
                'source'     => 'referral',
                'status'     => 'active',
                'applied_at' => now()->subDays(25),
                'created_at' => now()->subDays(25),
                'updated_at' => now()->subDays(25),
            ],
            // --- CPA with 4 years at SGV & Co., seeking industry experience ---
            [
                'first_name' => 'Bernard',
                'last_name'  => 'Ocampo',
                'email'      => 'bernard.ocampo@outlook.com',
                'phone'      => '09171239004',
                'source'     => 'job_board',
                'status'     => 'active',
                'applied_at' => now()->subDays(22),
                'created_at' => now()->subDays(22),
                'updated_at' => now()->subDays(22),
            ],
            // --- Mid-level dev, currently at a startup, wants stability ---
            [
                'first_name' => 'Paolo',
                'last_name'  => 'Resurreccion',
                'email'      => 'paolo.resurreccion@gmail.com',
                'phone'      => '09181239005',
                'source'     => 'linkedin',
                'status'     => 'active',
                'applied_at' => now()->subDays(20),
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(20),
            ],
            // --- Walked in with a printed résumé, genuinely impressive ---
            [
                'first_name' => 'Maribel',
                'last_name'  => 'Ignacio',
                'email'      => 'maribel.ignacio@gmail.com',
                'phone'      => '09171239006',
                'source'     => 'walk_in',
                'status'     => 'active',
                'applied_at' => now()->subDays(18),
                'created_at' => now()->subDays(18),
                'updated_at' => now()->subDays(18),
            ],
            // --- HR practitioner, relocating from Cebu ---
            [
                'first_name' => 'Danielle',
                'last_name'  => 'Buenaventura',
                'email'      => 'danielle.buenaventura@gmail.com',
                'phone'      => '09191239007',
                'source'     => 'job_board',
                'status'     => 'active',
                'applied_at' => now()->subDays(15),
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(15),
            ],
            // --- Junior accountant, referred by Bernard Ocampo himself ---
            [
                'first_name' => 'Jerome',
                'last_name'  => 'Castillo',
                'email'      => 'jerome.castillo@gmail.com',
                'phone'      => '09181239008',
                'source'     => 'referral',
                'status'     => 'active',
                'applied_at' => now()->subDays(12),
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(12),
            ],
        ];

        foreach ($candidates as $data) {
            Candidate::firstOrCreate(['email' => $data['email']], $data);
        }

        $this->command->info('✅ Seeded ' . count($candidates) . ' candidates.');
    }
}