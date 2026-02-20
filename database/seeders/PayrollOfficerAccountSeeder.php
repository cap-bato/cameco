<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use App\Models\Profile;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class PayrollOfficerAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Creating Payroll Officer account...');

        // Create Payroll Officer role if it doesn't exist
        $payrollOfficerRole = Role::firstOrCreate(
            ['name' => 'Payroll Officer'],
            ['guard_name' => 'web']
        );

        $this->command->info('✓ Payroll Officer role created/verified');

        // Get Finance department (find or create)
        $financeDepartment = DB::table('departments')
            ->where('name', 'Finance')
            ->orWhere('code', 'FIN')
            ->first();
            
        if (!$financeDepartment) {
            $financeDepartmentId = DB::table('departments')->insertGetId([
                'name' => 'Finance',
                'code' => 'FIN',
                'description' => 'Finance and Accounting Department',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $financeDepartmentId = $financeDepartment->id;
        }

        // Get Payroll Officer position (find or create)
        $payrollPosition = DB::table('positions')
            ->where('title', 'Payroll Officer')
            ->first();
            
        if (!$payrollPosition) {
            $payrollPositionId = DB::table('positions')->insertGetId([
                'title' => 'Payroll Officer',
                'description' => 'Handles payroll processing, calculations, and government compliance',
                'department_id' => $financeDepartmentId,
                'level' => 'staff',
                'min_salary' => 25000.00,
                'max_salary' => 45000.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $payrollPositionId = $payrollPosition->id;
        }

        // Get the superadmin user for created_by field
        $superadmin = User::where('email', 'superadmin@cameco.com')->first();
        if (!$superadmin) {
            $this->command->error('Superadmin user not found. Please run RolesAndPermissionsSeeder first.');
            return;
        }

        // Create user first (required for profile)
        $user = User::firstOrCreate(
            ['email' => 'payroll@cathay.com'],
            [
                'name' => 'Maria Santos Reyes',
                'username' => 'payroll_officer',
                'password' => Hash::make('payroll123'),
                'email' => 'payroll@cathay.com',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ User account created: ' . $user->email);

        // Create profile
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'user_id' => $user->id,
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
                'last_name' => 'Reyes',
                'suffix' => null,
                'gender' => 'female',  // lowercase as per migration
                'civil_status' => 'married',  // lowercase as per migration
                'date_of_birth' => '1985-06-15',
                'place_of_birth' => 'Manila, Philippines',
                'phone' => '(02) 8456-7890',
                'mobile' => '+63 917 123 4567',
                'current_address' => '456 Finance Street, Barangay San Antonio, Makati City, Metro Manila',
                'permanent_address' => '456 Finance Street, Barangay San Antonio, Makati City, Metro Manila',
                'emergency_contact_name' => 'Roberto Reyes',
                'emergency_contact_relationship' => 'Spouse',
                'emergency_contact_phone' => '+63 917 987 6543',
                'emergency_contact_address' => '456 Finance Street, Makati City, Metro Manila',
                'sss_number' => '34-1234567-8',
                'tin_number' => '123-456-789-000',
                'philhealth_number' => '12-345678901-2',
                'pagibig_number' => '1234-5678-9012',
                'spouse_name' => 'Roberto Reyes',
                'spouse_date_of_birth' => '1983-08-20',
                'spouse_contact_number' => '+63 917 987 6543',
            ]
        );

        $this->command->info('✓ Profile created: ' . $profile->first_name . ' ' . $profile->last_name);

        // Create employee record
        $employee = Employee::firstOrCreate(
            ['employee_number' => 'PAY-2025-001'],
            [
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'employee_number' => 'PAY-2025-001',
                'department_id' => $financeDepartmentId,
                'position_id' => $payrollPositionId,
                'employment_type' => 'regular',  // lowercase as per migration
                'date_hired' => '2020-03-01',
                'regularization_date' => '2020-09-01',
                'status' => 'active',
                'created_by' => $superadmin->id,
            ]
        );

        $this->command->info('✓ Employee record created: ' . $employee->employee_number);

        // Assign Payroll Officer role
        if (!$user->hasRole('Payroll Officer')) {
            $user->assignRole('Payroll Officer');
            $this->command->info('✓ Payroll Officer role assigned to user');
        } else {
            $this->command->info('✓ User already has Payroll Officer role');
        }

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Payroll Officer Account Created Successfully!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Email:    payroll@cathay.com');
        $this->command->info('  Password: payroll123');
        $this->command->info('  Name:     Maria Santos Reyes');
        $this->command->info('  Role:     Payroll Officer');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}
