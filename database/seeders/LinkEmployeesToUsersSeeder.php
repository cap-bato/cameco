<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class LinkEmployeesToUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Links existing employees to their user accounts via the profile.
     */
    public function run(): void
    {
        $count = 0;
        
        // Get all employees with profiles that have user_id
        $employees = Employee::with('profile')
            ->whereHas('profile', fn($q) => $q->whereNotNull('user_id'))
            ->get();

        foreach ($employees as $employee) {
            if ($employee->profile->user_id && !$employee->user_id) {
                $employee->update(['user_id' => $employee->profile->user_id]);
                $count++;
                $this->command->line("✓ Linked {$employee->employee_number} to user_id {$employee->profile->user_id}");
            } elseif ($employee->profile->user_id && $employee->user_id && $employee->user_id !== $employee->profile->user_id) {
                // Sync if they mismatch
                $employee->update(['user_id' => $employee->profile->user_id]);
                $count++;
                $this->command->line("↻ Synced {$employee->employee_number} to user_id {$employee->profile->user_id}");
            }
        }

        $this->command->info("\n✅ Linked/synced {$count} employees to user accounts!");
    }
}
