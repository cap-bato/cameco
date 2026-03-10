<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates User accounts for employees with usernames based on their names.
     */
    public function run(): void
    {
        $employeeCount = 0;
        $skippedCount = 0;

        // Get all employees
        $employees = Employee::with('profile')->get();

        foreach ($employees as $employee) {
            // Get profile - ensure it's loaded
            $profile = $employee->profile;
            if (!$profile || !$profile->email) {
                $this->command->warn("⚠️ Skipped employee {$employee->employee_number} - no profile or email");
                continue;
            }

            // Check if user already exists for this employee
            $existingUser = User::where('email', $profile->email)->first();
            
            if ($existingUser) {
                $skippedCount++;
                continue;
            }

            // Generate username from first_name and last_name
            // Format: firstname.lastname (lowercase)
            $firstName = strtolower(str_replace(' ', '', $profile->first_name));
            $lastName = strtolower(str_replace(' ', '', $profile->last_name));
            $baseUsername = "{$firstName}.{$lastName}";
            $username = $baseUsername;

            // Ensure username is unique
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = "{$baseUsername}{$counter}";
                $counter++;
            }

            // Create user account
            $user = User::create([
                'name' => "{$profile->first_name} {$profile->last_name}",
                'username' => $username,
                'email' => $profile->email,
                'password' => Hash::make('password'), // Default temporary password
                'email_verified_at' => now(),
            ]);

            // Link user to employee profile
            $profile->update(['user_id' => $user->id]);

            // Link user to employee record
            $employee->update(['user_id' => $user->id]);

            // Assign default 'Employee' role if not already assigned
            if (!$user->hasRole('Employee')) {
                $user->assignRole('Employee');
            }

            $employeeCount++;

            $this->command->line("✓ Created account for {$user->name} with username: {$username}");
        }

        $this->command->info("\n✅ Employee accounts seeded successfully!");
        $this->command->line("Created: {$employeeCount} accounts");
        if ($skippedCount > 0) {
            $this->command->line("Skipped: {$skippedCount} (already have accounts)");
        }
    }
}
