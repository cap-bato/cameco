<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Candidate;
use Illuminate\Support\Facades\DB;

class CandidateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Optionally clear table first
        // DB::table('candidates')->truncate();

        $candidates = [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan.delacruz@example.com',
                'phone' => '09171234567',
                'source' => 'referral',
                'status' => 'new',
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'maria.santos@example.com',
                'phone' => '09181234567',
                'source' => 'job_board',
                'status' => 'new',
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'first_name' => 'Pedro',
                'last_name' => 'Bautista',
                'email' => 'pedro.bautista@example.com',
                'phone' => '09191234567',
                'source' => 'walk_in',
                'status' => 'new',
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($candidates as $data) {
            Candidate::firstOrCreate([
                'email' => $data['email'],
            ], $data);
        }
    }
}
