<?php

namespace Database\Seeders\Cleanup;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class RemoveDuplicateLuisTorresSeeder extends Seeder
{
    /**
     * Remove duplicate Luis Torres profiles and users, keeping only the correct one.
     */
    public function run(): void
    {
        $this->command->info('Cleaning up duplicate Luis Torres profiles and users...');

        // Find all profiles for Luis Torres (by name)
        $profiles = Profile::where('first_name', 'Luis')
            ->where('last_name', 'Torres')
            ->get();

        // Keep the profile with email luis.torres@cameco.com, remove others
        $primaryProfile = $profiles->firstWhere('email', 'luis.torres@cameco.com');
        $toDelete = $profiles->filter(fn($p) => $p->email !== 'luis.torres@cameco.com');

        foreach ($toDelete as $profile) {
            // Update any employees referencing this profile to point to the primary profile
            $affected = \App\Models\Employee::where('profile_id', $profile->id)->update(['profile_id' => $primaryProfile->id]);
            if ($affected > 0) {
                $this->command->line("Updated {$affected} employees to use primary profile");
            }
            // Remove linked user if exists
            if ($profile->user_id) {
                $user = User::find($profile->user_id);
                if ($user) {
                    $user->delete();
                }
            }
            $profile->delete();
        }

        $this->command->info('Removed ' . $toDelete->count() . ' duplicate Luis Torres profiles.');
    }
}
