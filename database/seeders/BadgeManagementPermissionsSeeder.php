<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BadgeManagementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Permissions for RFID Badge Management module.
     * Uses dotted naming convention: hr.timekeeping.badges.{action}
     */
    public function run(): void
    {
        // Define permissions
        $permissions = [
            'hr.timekeeping.badges.view' => 'View RFID Badges',
            'hr.timekeeping.badges.manage' => 'Manage RFID Badges (Issue, Replace, Deactivate)',
            'hr.timekeeping.badges.bulk-import' => 'Bulk Import RFID Badges',
            'hr.timekeeping.badges.reports' => 'View Badge Reports',
        ];

        // Create or retrieve permissions
        $createdPermissions = [];
        foreach ($permissions as $name => $description) {
            $createdPermissions[$name] = Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web', 'description' => $description]
            );
        }

        // Get roles (check both naming conventions)
        $hrManager = Role::where('name', 'HR Manager')
            ->orWhere('name', 'hr-manager')
            ->first();
        
        if (!$hrManager) {
            $hrManager = Role::firstOrCreate(
                ['name' => 'HR Manager'],
                ['guard_name' => 'web']
            );
        }

        $hrStaff = Role::where('name', 'HR Staff')
            ->orWhere('name', 'hr-staff')
            ->first();
        
        if (!$hrStaff) {
            $hrStaff = Role::firstOrCreate(
                ['name' => 'HR Staff'],
                ['guard_name' => 'web']
            );
        }

        // Assign permissions to roles
        // HR Manager gets all badge management permissions
        $hrManager->givePermissionTo(array_keys($createdPermissions));

        // HR Staff gets view and basic manage permissions only
        $hrStaff->givePermissionTo([
            'hr.timekeeping.badges.view',
            'hr.timekeeping.badges.manage'
        ]);

        $this->command->info('✅ Badge management permissions seeded successfully.');
        $this->command->line("   - Created 4 permissions");
        $this->command->line("   - Assigned to {$hrManager->name}: All permissions");
        $this->command->line("   - Assigned to {$hrStaff->name}: View & Manage only");
    }
}
