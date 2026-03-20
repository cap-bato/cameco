<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have a valid created_by user id (prefer superadmin)
        $superadminUser = User::where('email', 'superadmin@cameco.com')->first();
        $createdBy = $superadminUser ? $superadminUser->id : 1;

        // Get departments and positions
        $hr = Department::where('code', 'HR')->first();
        $it = Department::where('code', 'IT')->first();
        $finance = Department::where('code', 'FIN')->first();
        $operations = Department::where('code', 'OPS')->first();
        $sales = Department::where('code', 'SALES')->first();
        $production = Department::where('code', 'PROD')->first();
        // Ensure Rolling Mill 1, 2, 3 sub-departments exist under Production
        $rollingMill1 = Department::firstOrCreate(
            ['code' => 'RM1'],
            [
                'name' => 'Rolling Mill 1',
                'description' => 'Rolling Mill 1 under Production',
                'is_active' => true,
                'parent_id' => $production?->id
            ]
        );
        $rollingMill2 = Department::firstOrCreate(
            ['code' => 'RM2'],
            [
                'name' => 'Rolling Mill 2',
                'description' => 'Rolling Mill 2 under Production',
                'is_active' => true,
                'parent_id' => $production?->id
            ]
        );
        $rollingMill3 = Department::firstOrCreate(
            ['code' => 'RM3'],
            [
                'name' => 'Rolling Mill 3',
                'description' => 'Rolling Mill 3 under Production',
                'is_active' => true,
                'parent_id' => $production?->id
            ]
        );
        // Get production/rolling mill positions
        $prodWorker = Position::where('title', 'Production Worker')->first();
        $prodManager = Position::where('title', 'Production Manager')->first();
        $prodSupervisor = Position::where('title', 'Production Supervisor')->first();
        $machineOperator = Position::where('title', 'Machine Operator')->first();
        $rmWorker = $prodWorker; // Use same as production worker for rolling mill
        $rmManager = $prodManager;
        $rmSupervisor = $prodSupervisor;
        $rmOperator = $machineOperator;

        // Bulk-generate Rolling Mill 1 employees (30) - prevent duplicate profiles/employees
        for ($i = 1; $i <= 30; $i++) {
            $empNum = sprintf('EMP-RM1-%04d', $i);
            $email = "rm1_{$i}@cameco.com";
            // Prevent logical duplicate: check for existing profile with same name and DOB
            $existingProfile = Profile::where([
                ['first_name', '=', 'Rolling'],
                ['middle_name', '=', 'Mill1'],
                ['last_name', '=', (string)$i],
                ['date_of_birth', '=', '1990-01-01'],
            ])->first();
            if ($existingProfile) {
                // Skip creating both profile and employee if logical duplicate found
                continue;
            }
            $profile = Profile::firstOrCreate([
                'email' => $email
            ], [
                'first_name' => 'Rolling',
                'middle_name' => 'Mill1',
                'last_name' => "{$i}",
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'civil_status' => 'single',
                'current_address' => 'Rolling Mill 1 Area',
                'permanent_address' => 'Rolling Mill 1 Area',
            ]);
            Employee::firstOrCreate([
                'employee_number' => $empNum
            ], [
                'profile_id' => $profile->id,
                'department_id' => $rollingMill1?->id,
                'position_id' => $rmWorker?->id,
                'employment_type' => 'regular',
                'date_hired' => '2022-01-01',
                'status' => 'active',
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]);
        }

        // Bulk-generate Rolling Mill 2 employees (25)
        for ($i = 1; $i <= 25; $i++) {
            $empNum = sprintf('EMP-RM2-%04d', $i);
            $email = "rm2_{$i}@cameco.com";
            $existingProfile = Profile::where([
                ['first_name', '=', 'Rolling'],
                ['middle_name', '=', 'Mill2'],
                ['last_name', '=', (string)$i],
                ['date_of_birth', '=', '1990-01-01'],
            ])->first();
            if ($existingProfile) {
                continue;
            }
            $profile = Profile::firstOrCreate([
                'email' => $email
            ], [
                'first_name' => 'Rolling',
                'middle_name' => 'Mill2',
                'last_name' => "{$i}",
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'civil_status' => 'single',
                'current_address' => 'Rolling Mill 2 Area',
                'permanent_address' => 'Rolling Mill 2 Area',
            ]);
            Employee::firstOrCreate([
                'employee_number' => $empNum
            ], [
                'profile_id' => $profile->id,
                'department_id' => $rollingMill2?->id,
                'position_id' => $rmWorker?->id,
                'employment_type' => 'regular',
                'date_hired' => '2022-01-01',
                'status' => 'active',
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]);
        }

        // Bulk-generate Rolling Mill 3 employees (20)
        for ($i = 1; $i <= 20; $i++) {
            $empNum = sprintf('EMP-RM3-%04d', $i);
            $email = "rm3_{$i}@cameco.com";
            $existingProfile = Profile::where([
                ['first_name', '=', 'Rolling'],
                ['middle_name', '=', 'Mill3'],
                ['last_name', '=', (string)$i],
                ['date_of_birth', '=', '1990-01-01'],
            ])->first();
            if ($existingProfile) {
                continue;
            }
            $profile = Profile::firstOrCreate([
                'email' => $email
            ], [
                'first_name' => 'Rolling',
                'middle_name' => 'Mill3',
                'last_name' => "{$i}",
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'civil_status' => 'single',
                'current_address' => 'Rolling Mill 3 Area',
                'permanent_address' => 'Rolling Mill 3 Area',
            ]);
            Employee::firstOrCreate([
                'employee_number' => $empNum
            ], [
                'profile_id' => $profile->id,
                'department_id' => $rollingMill3?->id,
                'position_id' => $rmWorker?->id,
                'employment_type' => 'regular',
                'date_hired' => '2022-01-01',
                'status' => 'active',
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]);
        }

        $hrManager = Position::where('title', 'HR Manager')->first();
        $hrSpecialist = Position::where('title', 'HR Specialist')->first();
        $itManager = Position::where('title', 'IT Manager')->first();
        $softwareDev = Position::where('title', 'Software Developer')->first();
        $financeManager = Position::where('title', 'Finance Manager')->first();
        $accountant = Position::where('title', 'Accountant')->first();
        $opsManager = Position::where('title', 'Operations Manager')->first();
        $salesManager = Position::where('title', 'Sales Manager')->first();
        $salesRep = Position::where('title', 'Sales Representative')->first();
        $prodManager = Position::where('title', 'Production Manager')->first();
        $prodWorker = Position::where('title', 'Production Worker')->first();

        $employees = [
            // HR Department
            [
                'profile' => [
                    'first_name' => 'Pedro',
                    'middle_name' => 'Reyes',
                    'last_name' => 'Bautista',
                    'suffix' => null,
                    'date_of_birth' => '1985-03-15',
                    'gender' => 'male',
                    'civil_status' => 'married',
                    'phone' => '(02) 8123-4567',
                    'mobile' => '+63 917 123 4567',
                    'email' => 'pedro.bautista@cameco.com',
                    'current_address' => '123 Mabini Street, Makati City, Metro Manila',
                    'permanent_address' => '456 Rizal Avenue, Quezon City, Metro Manila',
                    'emergency_contact_name' => 'Carlos Bautista',
                    'emergency_contact_relationship' => 'Brother',
                    'emergency_contact_phone' => '+63 917 234 5678',
                    'emergency_contact_address' => '123 Mabini Street, Makati City, Metro Manila',
                    'profile_picture_path' => 'employees/EMP-2024-0001/profile.jpg',
                    'sss_number' => '33-1234567-8',
                    'tin_number' => '123-456-789-000',
                    'philhealth_number' => '12-345678901-2',
                    'pagibig_number' => '1234-5678-9012',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0001',
                    'department_id' => $hr?->id,
                    'position_id' => $hrManager?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2020-01-15',
                    'regularization_date' => '2020-07-15',
                    'status' => 'active',
                ],
            ],
            [
                'profile' => [
                    'first_name' => 'Jose',
                    'middle_name' => 'Garcia',
                    'last_name' => 'Aquino',
                    'suffix' => null,
                    'date_of_birth' => '1992-07-22',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'phone' => '(02) 8234-5678',
                    'mobile' => '+63 918 234 5678',
                    'email' => 'jose.aquino@cameco.com',
                    'current_address' => '789 Del Pilar Street, Manila',
                    'permanent_address' => '789 Del Pilar Street, Manila',
                    'emergency_contact_name' => 'Ramon Aquino',
                    'emergency_contact_relationship' => 'Father',
                    'emergency_contact_phone' => '+63 918 345 6789',
                    'profile_picture_path' => 'employees/EMP-2024-0002/profile.jpg',
                    'sss_number' => '33-2345678-9',
                    'tin_number' => '234-567-890-000',
                    'philhealth_number' => '23-456789012-3',
                    'pagibig_number' => '2345-6789-0123',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0002',
                    'department_id' => $hr?->id,
                    'position_id' => $hrSpecialist?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2021-06-01',
                    'regularization_date' => '2021-12-01',
                    'status' => 'active',
                ],
            ],

            // IT Department
            [
                'profile' => [
                    'first_name' => 'Miguel',
                    'middle_name' => 'Lopez',
                    'last_name' => 'Santos',
                    'suffix' => 'Jr.',
                    'date_of_birth' => '1987-11-10',
                    'gender' => 'male',
                    'civil_status' => 'married',
                    'phone' => '(02) 8345-6789',
                    'mobile' => '+63 919 345 6789',
                    'email' => 'miguel.santos@cameco.com',
                    'current_address' => '321 Shaw Boulevard, Pasig City',
                    'permanent_address' => '321 Shaw Boulevard, Pasig City',
                    'emergency_contact_name' => 'Rosa Santos',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 919 456 7890',
                    'profile_picture_path' => 'employees/EMP-2024-0003/profile.jpg',
                    'sss_number' => '33-3456789-0',
                    'tin_number' => '345-678-901-000',
                    'philhealth_number' => '34-567890123-4',
                    'pagibig_number' => '3456-7890-1234',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0003',
                    'department_id' => $it?->id,
                    'position_id' => $itManager?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2019-03-20',
                    'regularization_date' => '2019-09-20',
                    'status' => 'active',
                ],
            ],
            [
                'profile' => [
                    'first_name' => 'Antonio',
                    'middle_name' => 'Cruz',
                    'last_name' => 'Villanueva',
                    'suffix' => null,
                    'date_of_birth' => '1995-05-18',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'phone' => null,
                    'mobile' => '+63 920 456 7890',
                    'email' => 'antonio.villanueva@cameco.com',
                    'current_address' => '654 Ortigas Avenue, Mandaluyong City',
                    'permanent_address' => '111 Provincial Road, Bulacan',
                    'emergency_contact_name' => 'Ramon Villanueva',
                    'emergency_contact_relationship' => 'Father',
                    'emergency_contact_phone' => '+63 920 567 8901',
                    'profile_picture_path' => 'employees/EMP-2024-0004/profile.jpg',
                    'sss_number' => '33-4567890-1',
                    'tin_number' => '456-789-012-000',
                    'philhealth_number' => '45-678901234-5',
                    'pagibig_number' => '4567-8901-2345',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0004',
                    'department_id' => $it?->id,
                    'position_id' => $softwareDev?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2022-02-14',
                    'regularization_date' => '2022-08-14',
                    'status' => 'active',
                    'immediate_supervisor_id' => null, // Will be set after manager is created
                ],
            ],

            // Finance Department
            [
                'profile' => [
                    'first_name' => 'Rafael',
                    'middle_name' => 'Bautista',
                    'last_name' => 'Mendoza',
                    'suffix' => null,
                    'date_of_birth' => '1983-09-25',
                    'gender' => 'male',
                    'civil_status' => 'married',
                    'phone' => '(02) 8456-7890',
                    'mobile' => '+63 921 567 8901',
                    'email' => 'rafael.mendoza@cameco.com',
                    'current_address' => '987 Ayala Avenue, Makati City',
                    'permanent_address' => '987 Ayala Avenue, Makati City',
                    'emergency_contact_name' => 'Carmen Mendoza',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 921 678 9012',
                    'profile_picture_path' => 'employees/EMP-2024-0005/profile.jpg',
                    'sss_number' => '33-5678901-2',
                    'tin_number' => '567-890-123-000',
                    'philhealth_number' => '56-789012345-6',
                    'pagibig_number' => '5678-9012-3456',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0005',
                    'department_id' => $finance?->id,
                    'position_id' => $financeManager?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2018-05-10',
                    'regularization_date' => '2018-11-10',
                    'status' => 'active',
                ],
            ],
            [
                'profile' => [
                    'first_name' => 'Gabriel',
                    'middle_name' => 'Torres',
                    'last_name' => 'Ramos',
                    'suffix' => null,
                    'date_of_birth' => '1990-12-03',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'phone' => null,
                    'mobile' => '+63 922 678 9012',
                    'email' => 'gabriel.ramos@cameco.com',
                    'current_address' => '222 BGC, Taguig City',
                    'permanent_address' => '333 Main Street, Cavite',
                    'emergency_contact_name' => 'Eduardo Ramos',
                    'emergency_contact_relationship' => 'Father',
                    'emergency_contact_phone' => '+63 922 789 0123',
                    'profile_picture_path' => 'employees/EMP-2024-0006/profile.jpg',
                    'sss_number' => '33-6789012-3',
                    'tin_number' => '678-901-234-000',
                    'philhealth_number' => '67-890123456-7',
                    'pagibig_number' => '6789-0123-4567',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0006',
                    'department_id' => $finance?->id,
                    'position_id' => $accountant?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2021-09-15',
                    'regularization_date' => '2022-03-15',
                    'status' => 'active',
                ],
            ],

            // Operations Department
            [
                'profile' => [
                    'first_name' => 'Carlos',
                    'middle_name' => 'Ramos',
                    'last_name' => 'Flores',
                    'suffix' => null,
                    'date_of_birth' => '1986-04-30',
                    'gender' => 'male',
                    'civil_status' => 'married',
                    'phone' => '(02) 8567-8901',
                    'mobile' => '+63 923 789 0123',
                    'email' => 'carlos.flores@cameco.com',
                    'current_address' => '444 Commonwealth Avenue, Quezon City',
                    'permanent_address' => '444 Commonwealth Avenue, Quezon City',
                    'emergency_contact_name' => 'Elena Flores',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 923 890 1234',
                    'profile_picture_path' => 'employees/EMP-2024-0007/profile.jpg',
                    'sss_number' => '33-7890123-4',
                    'tin_number' => '789-012-345-000',
                    'philhealth_number' => '78-901234567-8',
                    'pagibig_number' => '7890-1234-5678',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0007',
                    'department_id' => $operations?->id,
                    'position_id' => $opsManager?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2019-08-01',
                    'regularization_date' => '2020-02-01',
                    'status' => 'active',
                ],
            ],

            // Sales Department
            [
                'profile' => [
                    'first_name' => 'Luis',
                    'middle_name' => 'Mendoza',
                    'last_name' => 'Torres',
                    'suffix' => null,
                    'date_of_birth' => '1988-06-14',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'phone' => null,
                    'mobile' => '+63 924 890 1234',
                    'email' => 'luis.torres@cameco.com',
                    'current_address' => '555 Quezon Avenue, Quezon City',
                    'permanent_address' => '666 Barangay Road, Laguna',
                    'emergency_contact_name' => 'Ricardo Torres',
                    'emergency_contact_relationship' => 'Father',
                    'emergency_contact_phone' => '+63 924 901 2345',
                    'profile_picture_path' => 'employees/EMP-2024-0008/profile.jpg',
                    'sss_number' => '33-8901234-5',
                    'tin_number' => '890-123-456-000',
                    'philhealth_number' => '89-012345678-9',
                    'pagibig_number' => '8901-2345-6789',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0008',
                    'department_id' => $sales?->id,
                    'position_id' => $salesManager?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2020-11-20',
                    'regularization_date' => '2021-05-20',
                    'status' => 'active',
                ],
            ],
            [
                'profile' => [
                    'first_name' => 'Fernando',
                    'middle_name' => 'Flores',
                    'last_name' => 'Rivera',
                    'suffix' => null,
                    'date_of_birth' => '1993-08-08',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'phone' => null,
                    'mobile' => '+63 925 901 2345',
                    'email' => 'fernando.rivera@cameco.com',
                    'current_address' => '777 EDSA, Mandaluyong City',
                    'permanent_address' => '888 Town Plaza, Pampanga',
                    'emergency_contact_name' => 'Eduardo Rivera',
                    'emergency_contact_relationship' => 'Father',
                    'emergency_contact_phone' => '+63 925 012 3456',
                    'profile_picture_path' => 'employees/EMP-2024-0009/profile.jpg',
                    'sss_number' => '33-9012345-6',
                    'tin_number' => '901-234-567-000',
                    'philhealth_number' => '90-123456789-0',
                    'pagibig_number' => '9012-3456-7890',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0009',
                    'department_id' => $sales?->id,
                    'position_id' => $salesRep?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2023-01-10',
                    'regularization_date' => '2023-07-10',
                    'status' => 'active',
                ],
            ],

            // Production Department
            [
                'profile' => [
                    'first_name' => 'Ricardo',
                    'middle_name' => 'Rivera',
                    'last_name' => 'Gonzales',
                    'suffix' => 'Sr.',
                    'date_of_birth' => '1980-02-28',
                    'gender' => 'male',
                    'civil_status' => 'married',
                    'phone' => '(02) 8678-9012',
                    'mobile' => '+63 926 012 3456',
                    'email' => 'ricardo.gonzales@cameco.com',
                    'current_address' => '999 Industrial Park, Caloocan City',
                    'permanent_address' => '999 Industrial Park, Caloocan City',
                    'emergency_contact_name' => 'Elena Gonzales',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 926 123 4567',
                    'profile_picture_path' => 'employees/EMP-2024-0010/profile.jpg',
                    'sss_number' => '33-0123456-7',
                    'tin_number' => '012-345-678-000',
                    'philhealth_number' => '01-234567890-1',
                    'pagibig_number' => '0123-4567-8901',
                ],
                'employee' => [
                    'employee_number' => 'EMP-2024-0010',
                    'department_id' => $production?->id,
                    'position_id' => $prodManager?->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2017-10-05',
                    'regularization_date' => '2018-04-05',
                    'status' => 'active',
                ],
            ],
        ];


        // ...existing code...

        $createdEmployees = [];

        foreach ($employees as $data) {
            // Check if employee already exists
            $existingEmployee = Employee::where('employee_number', $data['employee']['employee_number'])->first();
            if ($existingEmployee) {
                $createdEmployees[$data['employee']['employee_number']] = $existingEmployee;
                continue;
            }
            // Prevent logical duplicate: check for existing profile with same name and DOB
            $profileData = $data['profile'];
            $existingProfile = Profile::where([
                ['first_name', '=', $profileData['first_name']],
                ['middle_name', '=', $profileData['middle_name']],
                ['last_name', '=', $profileData['last_name']],
                ['date_of_birth', '=', $profileData['date_of_birth']],
            ])->first();
            if ($existingProfile) {
                // Skip creating both profile and employee if logical duplicate found
                continue;
            }
            // Create profile
            $profile = Profile::create($data['profile']);
            // Create employee
            $employeeData = array_merge($data['employee'], ['profile_id' => $profile->id, 'created_by' => $createdBy, 'updated_by' => $createdBy]);
            $employee = Employee::create($employeeData);
            $createdEmployees[$data['employee']['employee_number']] = $employee;
        }

        // Update supervisor relationships
        if (isset($createdEmployees['EMP-2024-0004'])) {
            $createdEmployees['EMP-2024-0004']->update([
                'immediate_supervisor_id' => $createdEmployees['EMP-2024-0003']->id ?? null
            ]);
        }
        if (isset($createdEmployees['EMP-2024-0006'])) {
            $createdEmployees['EMP-2024-0006']->update([
                'immediate_supervisor_id' => $createdEmployees['EMP-2024-0005']->id ?? null
            ]);
        }
        if (isset($createdEmployees['EMP-2024-0009'])) {
            $createdEmployees['EMP-2024-0009']->update([
                'immediate_supervisor_id' => $createdEmployees['EMP-2024-0008']->id ?? null
            ]);
        }

        $this->command->info('Employees seeded successfully!');
    }
}



