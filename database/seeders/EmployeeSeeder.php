<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Profile;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get departments and positions
        $hr = Department::where('code', 'HR')->first();
        $it = Department::where('code', 'IT')->first();
        $finance = Department::where('code', 'FIN')->first();
        $operations = Department::where('code', 'OPS')->first();
        $sales = Department::where('code', 'SALES')->first();
        $production = Department::where('code', 'PROD')->first();

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
                    'first_name' => 'Maria',
                    'middle_name' => 'Santos',
                    'last_name' => 'Cruz',
                    'suffix' => null,
                    'date_of_birth' => '1985-03-15',
                    'gender' => 'female',
                    'civil_status' => 'married',
                    'phone' => '(02) 8123-4567',
                    'mobile' => '+63 917 123 4567',
                    'current_address' => '123 Mabini Street, Makati City, Metro Manila',
                    'permanent_address' => '456 Rizal Avenue, Quezon City, Metro Manila',
                    'emergency_contact_name' => 'Juan Cruz',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 917 234 5678',
                    'emergency_contact_address' => '123 Mabini Street, Makati City, Metro Manila',
                    'sss_number' => '33-1234567-8',
                    'tin_number' => '123-456-789-000',
                    'philhealth_number' => '12-345678901-2',
                    'pagibig_number' => '1234-5678-9012',
                ],
                'employee' => [
                    'email' => 'maria.cruz@cameco.com',
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
                    'first_name' => 'Ana',
                    'middle_name' => 'Lopez',
                    'last_name' => 'Reyes',
                    'suffix' => null,
                    'date_of_birth' => '1992-07-22',
                    'gender' => 'female',
                    'civil_status' => 'single',
                    'phone' => '(02) 8234-5678',
                    'mobile' => '+63 918 234 5678',
                    'current_address' => '789 Del Pilar Street, Manila',
                    'permanent_address' => '789 Del Pilar Street, Manila',
                    'emergency_contact_name' => 'Rosa Reyes',
                    'emergency_contact_relationship' => 'Mother',
                    'emergency_contact_phone' => '+63 918 345 6789',
                    'sss_number' => '33-2345678-9',
                    'tin_number' => '234-567-890-000',
                    'philhealth_number' => '23-456789012-3',
                    'pagibig_number' => '2345-6789-0123',
                ],
                'employee' => [
                    'email' => 'ana.reyes@cameco.com',
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
                    'first_name' => 'Roberto',
                    'middle_name' => 'Garcia',
                    'last_name' => 'Fernandez',
                    'suffix' => 'Jr.',
                    'date_of_birth' => '1987-11-10',
                    'gender' => 'male',
                    'civil_status' => 'married',
                    'phone' => '(02) 8345-6789',
                    'mobile' => '+63 919 345 6789',
                    'current_address' => '321 Shaw Boulevard, Pasig City',
                    'permanent_address' => '321 Shaw Boulevard, Pasig City',
                    'emergency_contact_name' => 'Elena Fernandez',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 919 456 7890',
                    'sss_number' => '33-3456789-0',
                    'tin_number' => '345-678-901-000',
                    'philhealth_number' => '34-567890123-4',
                    'pagibig_number' => '3456-7890-1234',
                ],
                'employee' => [
                    'email' => 'roberto.fernandez@cameco.com',
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
                    'first_name' => 'Carlos',
                    'middle_name' => 'Miguel',
                    'last_name' => 'Ramos',
                    'suffix' => null,
                    'date_of_birth' => '1995-05-18',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'phone' => null,
                    'mobile' => '+63 920 456 7890',
                    'current_address' => '654 Ortigas Avenue, Mandaluyong City',
                    'permanent_address' => '111 Provincial Road, Bulacan',
                    'emergency_contact_name' => 'Pedro Ramos',
                    'emergency_contact_relationship' => 'Father',
                    'emergency_contact_phone' => '+63 920 567 8901',
                    'sss_number' => '33-4567890-1',
                    'tin_number' => '456-789-012-000',
                    'philhealth_number' => '45-678901234-5',
                    'pagibig_number' => '4567-8901-2345',
                ],
                'employee' => [
                    'email' => 'carlos.ramos@cameco.com',
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
                    'first_name' => 'Patricia',
                    'middle_name' => 'De Leon',
                    'last_name' => 'Santos',
                    'suffix' => null,
                    'date_of_birth' => '1983-09-25',
                    'gender' => 'female',
                    'civil_status' => 'married',
                    'phone' => '(02) 8456-7890',
                    'mobile' => '+63 921 567 8901',
                    'current_address' => '987 Ayala Avenue, Makati City',
                    'permanent_address' => '987 Ayala Avenue, Makati City',
                    'emergency_contact_name' => 'Miguel Santos',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 921 678 9012',
                    'sss_number' => '33-5678901-2',
                    'tin_number' => '567-890-123-000',
                    'philhealth_number' => '56-789012345-6',
                    'pagibig_number' => '5678-9012-3456',
                ],
                'employee' => [
                    'email' => 'patricia.santos@cameco.com',
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
                    'first_name' => 'Jose',
                    'middle_name' => 'Antonio',
                    'last_name' => 'Mendoza',
                    'suffix' => null,
                    'date_of_birth' => '1990-12-03',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'phone' => null,
                    'mobile' => '+63 922 678 9012',
                    'current_address' => '222 BGC, Taguig City',
                    'permanent_address' => '333 Main Street, Cavite',
                    'emergency_contact_name' => 'Carmen Mendoza',
                    'emergency_contact_relationship' => 'Mother',
                    'emergency_contact_phone' => '+63 922 789 0123',
                    'sss_number' => '33-6789012-3',
                    'tin_number' => '678-901-234-000',
                    'philhealth_number' => '67-890123456-7',
                    'pagibig_number' => '6789-0123-4567',
                ],
                'employee' => [
                    'email' => 'jose.mendoza@cameco.com',
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
                    'first_name' => 'Luis',
                    'middle_name' => 'Eduardo',
                    'last_name' => 'Torres',
                    'suffix' => null,
                    'date_of_birth' => '1986-04-30',
                    'gender' => 'male',
                    'civil_status' => 'married',
                    'phone' => '(02) 8567-8901',
                    'mobile' => '+63 923 789 0123',
                    'current_address' => '444 Commonwealth Avenue, Quezon City',
                    'permanent_address' => '444 Commonwealth Avenue, Quezon City',
                    'emergency_contact_name' => 'Sofia Torres',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 923 890 1234',
                    'sss_number' => '33-7890123-4',
                    'tin_number' => '789-012-345-000',
                    'philhealth_number' => '78-901234567-8',
                    'pagibig_number' => '7890-1234-5678',
                ],
                'employee' => [
                    'email' => 'luis.torres@cameco.com',
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
                    'first_name' => 'Gabriela',
                    'middle_name' => 'Isabel',
                    'last_name' => 'Morales',
                    'suffix' => null,
                    'date_of_birth' => '1988-06-14',
                    'gender' => 'female',
                    'civil_status' => 'single',
                    'phone' => null,
                    'mobile' => '+63 924 890 1234',
                    'current_address' => '555 Quezon Avenue, Quezon City',
                    'permanent_address' => '666 Barangay Road, Laguna',
                    'emergency_contact_name' => 'Ricardo Morales',
                    'emergency_contact_relationship' => 'Father',
                    'emergency_contact_phone' => '+63 924 901 2345',
                    'sss_number' => '33-8901234-5',
                    'tin_number' => '890-123-456-000',
                    'philhealth_number' => '89-012345678-9',
                    'pagibig_number' => '8901-2345-6789',
                ],
                'employee' => [
                    'email' => 'gabriela.morales@cameco.com',
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
                    'first_name' => 'Diego',
                    'middle_name' => 'Francisco',
                    'last_name' => 'Villanueva',
                    'suffix' => null,
                    'date_of_birth' => '1993-08-08',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'phone' => null,
                    'mobile' => '+63 925 901 2345',
                    'current_address' => '777 EDSA, Mandaluyong City',
                    'permanent_address' => '888 Town Plaza, Pampanga',
                    'emergency_contact_name' => 'Teresa Villanueva',
                    'emergency_contact_relationship' => 'Mother',
                    'emergency_contact_phone' => '+63 925 012 3456',
                    'sss_number' => '33-9012345-6',
                    'tin_number' => '901-234-567-000',
                    'philhealth_number' => '90-123456789-0',
                    'pagibig_number' => '9012-3456-7890',
                ],
                'employee' => [
                    'email' => 'diego.villanueva@cameco.com',
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
                    'first_name' => 'Fernando',
                    'middle_name' => 'Raul',
                    'last_name' => 'Castillo',
                    'suffix' => 'Sr.',
                    'date_of_birth' => '1980-02-28',
                    'gender' => 'male',
                    'civil_status' => 'married',
                    'phone' => '(02) 8678-9012',
                    'mobile' => '+63 926 012 3456',
                    'current_address' => '999 Industrial Park, Caloocan City',
                    'permanent_address' => '999 Industrial Park, Caloocan City',
                    'emergency_contact_name' => 'Linda Castillo',
                    'emergency_contact_relationship' => 'Spouse',
                    'emergency_contact_phone' => '+63 926 123 4567',
                    'sss_number' => '33-0123456-7',
                    'tin_number' => '012-345-678-000',
                    'philhealth_number' => '01-234567890-1',
                    'pagibig_number' => '0123-4567-8901',
                ],
                'employee' => [
                    'email' => 'fernando.castillo@cameco.com',
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

        $createdEmployees = [];

        foreach ($employees as $data) {
            // Check if employee already exists
            $existingEmployee = Employee::where('employee_number', $data['employee']['employee_number'])->first();
            
            if ($existingEmployee) {
                $createdEmployees[$data['employee']['employee_number']] = $existingEmployee;
                continue;
            }

            // Create profile
            $profile = Profile::create($data['profile']);

            // Create employee
            $employeeData = array_merge($data['employee'], ['profile_id' => $profile->id]);
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



