<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DocumentManagementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create all Document Management permissions
        $permissions = [
            // HR Staff Permissions - Base document operations
            'hr.documents.view',           // View all employee documents
            'hr.documents.upload',         // Upload documents
            'hr.documents.download',       // Download documents
            'hr.documents.delete',         // Delete documents (soft delete)

            // HR Manager Additional Permissions
            'hr.documents.approve',        // Approve pending documents
            'hr.documents.reject',         // Reject pending documents
            'hr.documents.templates.manage', // Manage document templates
            'hr.documents.audit',          // View audit logs
            'hr.documents.bulk-upload',    // Bulk upload via CSV
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
        }

        // Get HR Staff role
        $hrStaffRole = Role::firstOrCreate(
            ['name' => 'HR Staff'],
            ['guard_name' => 'web']
        );

        // Assign base permissions to HR Staff
        $hrStaffPermissions = [
            'hr.documents.view',
            'hr.documents.upload',
            'hr.documents.download',
            'hr.documents.delete',
        ];
        $hrStaffRole->givePermissionTo($hrStaffPermissions);

        // Get HR Manager role
        $hrManagerRole = Role::firstOrCreate(
            ['name' => 'HR Manager'],
            ['guard_name' => 'web']
        );

        // Assign all permissions to HR Manager (includes HR Staff permissions + additional)
        $hrManagerRole->givePermissionTo($permissions);

        $this->command->info('✓ Document Management permissions created and assigned successfully');
        $this->command->info('✓ HR Staff: 4 permissions assigned');
        $this->command->info('✓ HR Manager: 9 permissions assigned');
    }
}
