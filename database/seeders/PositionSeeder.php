<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Rolling Mill Departments
        $rm1 = Department::where('code', 'RM1')->first();
        $rm2 = Department::where('code', 'RM2')->first();
        $rm3 = Department::where('code', 'RM3')->first();

        // Rolling Mill Positions (same as production, but for each RM department)
        $rollingMillPositions = [
            [
                'title' => 'Production Worker',
                'description' => 'Operates production equipment and machinery',
                'level' => 'staff',
                'min_salary' => 20000,
                'max_salary' => 30000,
                'is_active' => true,
            ],
            [
                'title' => 'Production Manager',
                'description' => 'Oversees manufacturing and production processes',
                'level' => 'manager',
                'min_salary' => 50000,
                'max_salary' => 75000,
                'is_active' => true,
            ],
            [
                'title' => 'Production Supervisor',
                'description' => 'Supervises production line workers',
                'level' => 'supervisor',
                'min_salary' => 32000,
                'max_salary' => 48000,
                'is_active' => true,
            ],
            [
                'title' => 'Machine Operator',
                'description' => 'Operates specific production machinery',
                'level' => 'staff',
                'min_salary' => 22000,
                'max_salary' => 32000,
                'is_active' => true,
            ],
        ];

        foreach ([$rm1, $rm2, $rm3] as $rmDept) {
            if ($rmDept) {
                foreach ($rollingMillPositions as $pos) {
                    $data = array_merge($pos, ['department_id' => $rmDept->id]);
                    Position::firstOrCreate(
                        ['title' => $pos['title'], 'department_id' => $rmDept->id],
                        $data
                    );
                }
            }
        }
        // Get department IDs
        $hr = Department::where('code', 'HR')->first();
        $it = Department::where('code', 'IT')->first();
        $finance = Department::where('code', 'FIN')->first();
        $operations = Department::where('code', 'OPS')->first();
        $sales = Department::where('code', 'SALES')->first();
        $production = Department::where('code', 'PROD')->first();
        $qa = Department::where('code', 'QA')->first();
        $logistics = Department::where('code', 'LOG')->first();
        $rnd = Department::where('code', 'RND')->first();
        $admin = Department::where('code', 'ADMIN')->first();

        $positions = [
            // HR Positions
            [
                'title' => 'HR Manager',
                'description' => 'Oversees all HR operations and employee relations',
                'department_id' => $hr?->id,
                'level' => 'manager',
                'min_salary' => 50000,
                'max_salary' => 80000,
                'is_active' => true,
            ],
            [
                'title' => 'HR Specialist',
                'description' => 'Handles recruitment, onboarding, and employee documentation',
                'department_id' => $hr?->id,
                'level' => 'staff',
                'min_salary' => 30000,
                'max_salary' => 45000,
                'is_active' => true,
            ],
            [
                'title' => 'Payroll Officer',
                'description' => 'Manages payroll processing and benefits administration',
                'department_id' => $hr?->id,
                'level' => 'staff',
                'min_salary' => 28000,
                'max_salary' => 40000,
                'is_active' => true,
            ],

            // IT Positions
            [
                'title' => 'IT Manager',
                'description' => 'Manages IT infrastructure and development teams',
                'department_id' => $it?->id,
                'level' => 'manager',
                'min_salary' => 60000,
                'max_salary' => 90000,
                'is_active' => true,
            ],
            [
                'title' => 'Software Developer',
                'description' => 'Develops and maintains software applications',
                'department_id' => $it?->id,
                'level' => 'staff',
                'min_salary' => 40000,
                'max_salary' => 65000,
                'is_active' => true,
            ],
            [
                'title' => 'System Administrator',
                'description' => 'Manages servers, networks, and IT infrastructure',
                'department_id' => $it?->id,
                'level' => 'staff',
                'min_salary' => 35000,
                'max_salary' => 55000,
                'is_active' => true,
            ],
            [
                'title' => 'IT Support Specialist',
                'description' => 'Provides technical support to end users',
                'department_id' => $it?->id,
                'level' => 'staff',
                'min_salary' => 25000,
                'max_salary' => 38000,
                'is_active' => true,
            ],

            // Finance Positions
            [
                'title' => 'Finance Manager',
                'description' => 'Oversees financial operations and reporting',
                'department_id' => $finance?->id,
                'level' => 'manager',
                'min_salary' => 55000,
                'max_salary' => 85000,
                'is_active' => true,
            ],
            [
                'title' => 'Accountant',
                'description' => 'Handles accounting transactions and financial records',
                'department_id' => $finance?->id,
                'level' => 'staff',
                'min_salary' => 32000,
                'max_salary' => 48000,
                'is_active' => true,
            ],
            [
                'title' => 'Accounts Payable Clerk',
                'description' => 'Processes vendor invoices and payments',
                'department_id' => $finance?->id,
                'level' => 'staff',
                'min_salary' => 22000,
                'max_salary' => 32000,
                'is_active' => true,
            ],

            // Operations Positions
            [
                'title' => 'Operations Manager',
                'description' => 'Manages daily operations and process optimization',
                'department_id' => $operations?->id,
                'level' => 'manager',
                'min_salary' => 50000,
                'max_salary' => 75000,
                'is_active' => true,
            ],
            [
                'title' => 'Operations Supervisor',
                'description' => 'Supervises operational staff and processes',
                'department_id' => $operations?->id,
                'level' => 'supervisor',
                'min_salary' => 35000,
                'max_salary' => 50000,
                'is_active' => true,
            ],
            [
                'title' => 'Operations Coordinator',
                'description' => 'Coordinates daily operational activities',
                'department_id' => $operations?->id,
                'level' => 'staff',
                'min_salary' => 25000,
                'max_salary' => 38000,
                'is_active' => true,
            ],

            // Sales & Marketing Positions
            [
                'title' => 'Sales Manager',
                'description' => 'Manages sales team and customer relationships',
                'department_id' => $sales?->id,
                'level' => 'manager',
                'min_salary' => 48000,
                'max_salary' => 75000,
                'is_active' => true,
            ],
            [
                'title' => 'Sales Representative',
                'description' => 'Handles customer sales and account management',
                'department_id' => $sales?->id,
                'level' => 'staff',
                'min_salary' => 28000,
                'max_salary' => 45000,
                'is_active' => true,
            ],
            [
                'title' => 'Marketing Specialist',
                'description' => 'Develops and executes marketing campaigns',
                'department_id' => $sales?->id,
                'level' => 'staff',
                'min_salary' => 30000,
                'max_salary' => 45000,
                'is_active' => true,
            ],

            // Production Positions
            [
                'title' => 'Production Manager',
                'description' => 'Oversees manufacturing and production processes',
                'department_id' => $production?->id,
                'level' => 'manager',
                'min_salary' => 50000,
                'max_salary' => 75000,
                'is_active' => true,
            ],
            [
                'title' => 'Production Supervisor',
                'description' => 'Supervises production line workers',
                'department_id' => $production?->id,
                'level' => 'supervisor',
                'min_salary' => 32000,
                'max_salary' => 48000,
                'is_active' => true,
            ],
            [
                'title' => 'Production Worker',
                'description' => 'Operates production equipment and machinery',
                'department_id' => $production?->id,
                'level' => 'staff',
                'min_salary' => 20000,
                'max_salary' => 30000,
                'is_active' => true,
            ],
            [
                'title' => 'Machine Operator',
                'description' => 'Operates specific production machinery',
                'department_id' => $production?->id,
                'level' => 'staff',
                'min_salary' => 22000,
                'max_salary' => 32000,
                'is_active' => true,
            ],

            // Quality Assurance Positions
            [
                'title' => 'QA Manager',
                'description' => 'Manages quality assurance processes and standards',
                'department_id' => $qa?->id,
                'level' => 'manager',
                'min_salary' => 48000,
                'max_salary' => 70000,
                'is_active' => true,
            ],
            [
                'title' => 'QA Inspector',
                'description' => 'Inspects products for quality compliance',
                'department_id' => $qa?->id,
                'level' => 'staff',
                'min_salary' => 25000,
                'max_salary' => 38000,
                'is_active' => true,
            ],

            // Logistics Positions
            [
                'title' => 'Logistics Manager',
                'description' => 'Manages supply chain and distribution operations',
                'department_id' => $logistics?->id,
                'level' => 'manager',
                'min_salary' => 45000,
                'max_salary' => 70000,
                'is_active' => true,
            ],
            [
                'title' => 'Warehouse Supervisor',
                'description' => 'Supervises warehouse operations and inventory',
                'department_id' => $logistics?->id,
                'level' => 'supervisor',
                'min_salary' => 30000,
                'max_salary' => 45000,
                'is_active' => true,
            ],
            [
                'title' => 'Warehouse Staff',
                'description' => 'Handles inventory and warehouse activities',
                'department_id' => $logistics?->id,
                'level' => 'staff',
                'min_salary' => 20000,
                'max_salary' => 28000,
                'is_active' => true,
            ],

            // R&D Positions
            [
                'title' => 'R&D Manager',
                'description' => 'Manages research and product development initiatives',
                'department_id' => $rnd?->id,
                'level' => 'manager',
                'min_salary' => 60000,
                'max_salary' => 90000,
                'is_active' => true,
            ],
            [
                'title' => 'Research Engineer',
                'description' => 'Conducts research and develops new products',
                'department_id' => $rnd?->id,
                'level' => 'staff',
                'min_salary' => 40000,
                'max_salary' => 60000,
                'is_active' => true,
            ],

            // Administration Positions
            [
                'title' => 'Administrative Manager',
                'description' => 'Manages administrative services and office operations',
                'department_id' => $admin?->id,
                'level' => 'manager',
                'min_salary' => 40000,
                'max_salary' => 60000,
                'is_active' => true,
            ],
            [
                'title' => 'Administrative Assistant',
                'description' => 'Provides administrative support and office services',
                'department_id' => $admin?->id,
                'level' => 'staff',
                'min_salary' => 22000,
                'max_salary' => 32000,
                'is_active' => true,
            ],
            [
                'title' => 'Receptionist',
                'description' => 'Handles reception and front desk duties',
                'department_id' => $admin?->id,
                'level' => 'staff',
                'min_salary' => 18000,
                'max_salary' => 26000,
                'is_active' => true,
            ],
        ];

        foreach ($positions as $position) {
            if ($position['department_id']) {
                Position::firstOrCreate(
                    ['title' => $position['title']], // Check if exists by title
                    $position // Create with all fields if not exists
                );
            }
        }

        $this->command->info('Positions seeded successfully!');
    }
}
