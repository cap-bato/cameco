<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class HRStaffAccountSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating HR Staff account...');

        // ── Role ───────────────────────────────────────────────────────────
        $role = Role::firstOrCreate(
            ['name' => 'HR Staff'],
            ['guard_name' => 'web']
        );

        // ── Department & position ──────────────────────────────────────────
        $hrDept   = Department::where('code', 'HR')->first();
        $position = Position::where('title', 'HR Specialist')->first();

        $createdBy = User::where('email', 'superadmin@cameco.com')->value('id') ?? 1;

        // ── User account ───────────────────────────────────────────────────
        $email         = 'hrstaff@cameco.com';
        $passwordPlain = 'password';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => 'Maria Santos',
                'username'          => 'hrstaff',
                'password'          => Hash::make($passwordPlain),
                'email_verified_at' => now(),
            ]
        );

        // ── Profile ────────────────────────────────────────────────────────
        // EMP-HR-0001 is reserved for the HR Manager in EmployeeSeeder;
        // HR Staff gets EMP-HR-0002.
        $empNumber = 'EMP-HR-0002';

        if (!Employee::where('employee_number', $empNumber)->exists()
            && !Profile::where('email', $email)->exists()
        ) {
            $profile = Profile::create([
                'first_name'      => 'Maria',
                'middle_name'     => 'Cruz',
                'last_name'       => 'Santos',
                'suffix'          => null,
                'date_of_birth'   => '1992-08-22',
                'gender'          => 'female',
                'civil_status'    => 'single',
                'mobile'          => '+63 917 000 0003',
                'email'           => $email,
                'current_address' => 'Blk. 5 Lot 12, Sta. Monica Village, Novaliches, Q.C. 1117',
                'permanent_address' => 'Blk. 5 Lot 12, Sta. Monica Village, Novaliches, Q.C. 1117',
                'emergency_contact_name'         => 'Jose Santos',
                'emergency_contact_relationship' => 'Father',
                'emergency_contact_phone'        => '+63 917 000 0004',
                'emergency_contact_address'      => 'Blk. 5 Lot 12, Sta. Monica Village, Novaliches, Q.C. 1117',
                'sss_number'        => '33-0000003-3',
                'tin_number'        => '000-000-003-000',
                'philhealth_number' => '00-000000003-3',
                'pagibig_number'    => '0000-0000-0003',
            ]);

            // ── Employee record ────────────────────────────────────────────
            Employee::create([
                'employee_number'     => $empNumber,
                'profile_id'          => $profile->id,
                'department_id'       => $hrDept?->id,
                'position_id'         => $position?->id,
                'employment_type'     => 'regular',
                'date_hired'          => '2019-03-01',
                'regularization_date' => '2019-09-01',
                'status'              => 'active',
                'created_by'          => $createdBy,
                'updated_by'          => $createdBy,
            ]);

            $this->command->info('✓ Profile and Employee record created for ' . $email);
        } else {
            $this->command->info('✓ Profile/Employee for ' . $email . ' already exists — skipped');
        }

        // ── Assign role ────────────────────────────────────────────────────
        if (!$user->hasRole('HR Staff')) {
            $user->assignRole('HR Staff');
        }

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  HR Staff Setup Completed Successfully!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Name:        Maria Cruz Santos');
        $this->command->info('  Email:       ' . $email);
        $this->command->info('  Password:    ' . $passwordPlain);
        $this->command->info('  Employee No: ' . $empNumber);
        $this->command->info('  Department:  HR');
        $this->command->info('  Position:    HR Specialist');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}