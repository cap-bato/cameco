<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The positions table has a unique constraint on (title) alone, so every
     * title must be globally unique. Rolling Mill positions are prefixed with
     * their mill code (e.g. "RM1 Production Worker") so they don't collide
     * with the base Production department titles.
     *
     * The EmployeeSeeder uses these exact prefixed titles when looking up
     * Rolling Mill positions.
     */
    public function run(): void
    {
        // ── Resolve departments ───────────────────────────────────────────
        $hr         = Department::where('code', 'HR')->first();
        $it         = Department::where('code', 'IT')->first();
        $finance    = Department::where('code', 'FIN')->first();
        $operations = Department::where('code', 'OPS')->first();
        $sales      = Department::where('code', 'SALES')->first();
        $production = Department::where('code', 'PROD')->first();
        $qa         = Department::where('code', 'QA')->first();
        $logistics  = Department::where('code', 'LOG')->first();
        $rnd        = Department::where('code', 'RND')->first();
        $admin      = Department::where('code', 'ADMIN')->first();

        // ── Ensure Rolling Mill sub-departments exist ─────────────────────
        $rm1 = Department::firstOrCreate(
            ['code' => 'RM1'],
            ['name' => 'Rolling Mill 1', 'description' => 'Rolling Mill 1 under Production', 'is_active' => true, 'parent_id' => $production?->id]
        );
        $rm2 = Department::firstOrCreate(
            ['code' => 'RM2'],
            ['name' => 'Rolling Mill 2', 'description' => 'Rolling Mill 2 under Production', 'is_active' => true, 'parent_id' => $production?->id]
        );
        $rm3 = Department::firstOrCreate(
            ['code' => 'RM3'],
            ['name' => 'Rolling Mill 3', 'description' => 'Rolling Mill 3 under Production', 'is_active' => true, 'parent_id' => $production?->id]
        );

        // ── Helper: seed positions for one department ─────────────────────
        // Matches on title alone to honour the positions_title_unique constraint.
        $seed = function (array $positions, ?object $dept) {
            if (! $dept) {
                return;
            }
            foreach ($positions as $pos) {
                Position::firstOrCreate(
                    ['title' => $pos['title']],
                    array_merge($pos, ['department_id' => $dept->id, 'is_active' => true])
                );
            }
        };

        // ── HR ────────────────────────────────────────────────────────────
        $seed([
            ['title' => 'HR Manager',     'description' => 'Oversees all HR operations and employee relations',           'level' => 'manager', 'min_salary' => 50000, 'max_salary' => 80000],
            ['title' => 'HR Specialist',   'description' => 'Handles recruitment, onboarding, and employee documentation', 'level' => 'staff',   'min_salary' => 30000, 'max_salary' => 45000],
            ['title' => 'HR Generalist',   'description' => 'Handles general HR tasks',                                    'level' => 'staff',   'min_salary' => 25000, 'max_salary' => 40000],
            ['title' => 'Payroll Officer', 'description' => 'Manages payroll processing and benefits administration',      'level' => 'staff',   'min_salary' => 28000, 'max_salary' => 40000],
        ], $hr);

        // ── IT ────────────────────────────────────────────────────────────
        $seed([
            ['title' => 'IT Manager',           'description' => 'Manages IT infrastructure and development teams',    'level' => 'manager', 'min_salary' => 60000, 'max_salary' => 90000],
            ['title' => 'Software Developer',    'description' => 'Develops and maintains software applications',      'level' => 'staff',   'min_salary' => 40000, 'max_salary' => 65000],
            ['title' => 'System Administrator',  'description' => 'Manages servers, networks, and IT infrastructure', 'level' => 'staff',   'min_salary' => 35000, 'max_salary' => 55000],
            ['title' => 'IT Support Specialist', 'description' => 'Provides technical support to end users',          'level' => 'staff',   'min_salary' => 25000, 'max_salary' => 38000],
            ['title' => 'IT Staff',              'description' => 'Handles IT support and maintenance',               'level' => 'staff',   'min_salary' => 25000, 'max_salary' => 40000],
        ], $it);

        // ── Finance ───────────────────────────────────────────────────────
        $seed([
            ['title' => 'Finance Manager',       'description' => 'Oversees financial operations and reporting',  'level' => 'manager', 'min_salary' => 55000, 'max_salary' => 85000],
            ['title' => 'Accountant',             'description' => 'Handles accounting transactions and records', 'level' => 'staff',   'min_salary' => 32000, 'max_salary' => 48000],
            ['title' => 'Accounts Payable Clerk', 'description' => 'Processes vendor invoices and payments',     'level' => 'staff',   'min_salary' => 22000, 'max_salary' => 32000],
        ], $finance);

        // ── Operations ────────────────────────────────────────────────────
        $seed([
            ['title' => 'Operations Manager',     'description' => 'Manages daily operations and process optimisation', 'level' => 'manager',    'min_salary' => 50000, 'max_salary' => 75000],
            ['title' => 'Operations Supervisor',  'description' => 'Supervises operational staff and processes',        'level' => 'supervisor', 'min_salary' => 35000, 'max_salary' => 50000],
            ['title' => 'Operations Coordinator', 'description' => 'Coordinates daily operational activities',          'level' => 'staff',      'min_salary' => 25000, 'max_salary' => 38000],
            ['title' => 'Operations Staff',       'description' => 'Handles daily operations',                          'level' => 'staff',      'min_salary' => 22000, 'max_salary' => 35000],
        ], $operations);

        // ── Sales & Marketing ─────────────────────────────────────────────
        $seed([
            ['title' => 'Sales Manager',        'description' => 'Manages sales team and customer relationships',  'level' => 'manager', 'min_salary' => 48000, 'max_salary' => 75000],
            ['title' => 'Sales Representative', 'description' => 'Handles customer sales and account management', 'level' => 'staff',   'min_salary' => 28000, 'max_salary' => 45000],
            ['title' => 'Marketing Specialist', 'description' => 'Develops and executes marketing campaigns',     'level' => 'staff',   'min_salary' => 30000, 'max_salary' => 45000],
            ['title' => 'Sales Staff',          'description' => 'Handles sales and customer relations',          'level' => 'staff',   'min_salary' => 22000, 'max_salary' => 35000],
        ], $sales);

        // ── Production ────────────────────────────────────────────────────
        $seed([
            ['title' => 'Production Manager',    'description' => 'Oversees manufacturing and production processes', 'level' => 'manager',    'min_salary' => 50000, 'max_salary' => 75000],
            ['title' => 'Production Supervisor', 'description' => 'Supervises production line workers',              'level' => 'supervisor', 'min_salary' => 32000, 'max_salary' => 48000],
            ['title' => 'Production Worker',     'description' => 'Operates production equipment and machinery',     'level' => 'staff',      'min_salary' => 20000, 'max_salary' => 30000],
            ['title' => 'Machine Operator',      'description' => 'Operates specific production machinery',          'level' => 'staff',      'min_salary' => 22000, 'max_salary' => 32000],
            ['title' => 'Production Staff',      'description' => 'Handles production tasks',                        'level' => 'staff',      'min_salary' => 22000, 'max_salary' => 35000],
        ], $production);

        // ── Rolling Mills ─────────────────────────────────────────────────
        // Titles are prefixed with the mill code (RM1 / RM2 / RM3) to keep
        // them globally unique while still conveying the role.
        // EmployeeSeeder looks these up using the same prefixed titles.
        foreach ([
            'RM1' => $rm1,
            'RM2' => $rm2,
            'RM3' => $rm3,
        ] as $prefix => $dept) {
            $seed([
                ['title' => "{$prefix} Production Worker",     'description' => 'Operates production equipment and machinery',    'level' => 'staff',      'min_salary' => 20000, 'max_salary' => 30000],
                ['title' => "{$prefix} Production Manager",    'description' => 'Oversees manufacturing and production processes', 'level' => 'manager',    'min_salary' => 50000, 'max_salary' => 75000],
                ['title' => "{$prefix} Production Supervisor", 'description' => 'Supervises production line workers',             'level' => 'supervisor', 'min_salary' => 32000, 'max_salary' => 48000],
                ['title' => "{$prefix} Machine Operator",      'description' => 'Operates specific production machinery',         'level' => 'staff',      'min_salary' => 22000, 'max_salary' => 32000],
            ], $dept);
        }

        // ── Quality Assurance ─────────────────────────────────────────────
        $seed([
            ['title' => 'QA Manager',   'description' => 'Manages quality assurance processes and standards', 'level' => 'manager', 'min_salary' => 48000, 'max_salary' => 70000],
            ['title' => 'QA Inspector', 'description' => 'Inspects products for quality compliance',          'level' => 'staff',   'min_salary' => 25000, 'max_salary' => 38000],
            ['title' => 'QA Staff',     'description' => 'Handles quality assurance',                         'level' => 'staff',   'min_salary' => 22000, 'max_salary' => 35000],
        ], $qa);

        // ── Logistics ─────────────────────────────────────────────────────
        $seed([
            ['title' => 'Logistics Manager',   'description' => 'Manages supply chain and distribution operations', 'level' => 'manager',    'min_salary' => 45000, 'max_salary' => 70000],
            ['title' => 'Warehouse Supervisor', 'description' => 'Supervises warehouse operations and inventory',   'level' => 'supervisor', 'min_salary' => 30000, 'max_salary' => 45000],
            ['title' => 'Warehouse Staff',      'description' => 'Handles inventory and warehouse activities',      'level' => 'staff',      'min_salary' => 20000, 'max_salary' => 28000],
            ['title' => 'Logistics Staff',      'description' => 'Handles logistics and inventory',                 'level' => 'staff',      'min_salary' => 22000, 'max_salary' => 35000],
        ], $logistics);

        // ── R&D ───────────────────────────────────────────────────────────
        $seed([
            ['title' => 'R&D Manager',       'description' => 'Manages research and product development initiatives', 'level' => 'manager', 'min_salary' => 60000, 'max_salary' => 90000],
            ['title' => 'Research Engineer', 'description' => 'Conducts research and develops new products',          'level' => 'staff',   'min_salary' => 40000, 'max_salary' => 60000],
            ['title' => 'R&D Staff',         'description' => 'Handles research and development',                     'level' => 'staff',   'min_salary' => 22000, 'max_salary' => 35000],
        ], $rnd);

        // ── Administration ────────────────────────────────────────────────
        $seed([
            ['title' => 'Administrative Manager',  'description' => 'Manages administrative services and office operations', 'level' => 'manager', 'min_salary' => 40000, 'max_salary' => 60000],
            ['title' => 'Administrative Assistant', 'description' => 'Provides administrative support and office services',  'level' => 'staff',   'min_salary' => 22000, 'max_salary' => 32000],
            ['title' => 'Receptionist',             'description' => 'Handles reception and front desk duties',              'level' => 'staff',   'min_salary' => 18000, 'max_salary' => 26000],
            ['title' => 'Admin Staff',              'description' => 'Handles administrative tasks',                         'level' => 'staff',   'min_salary' => 22000, 'max_salary' => 35000],
        ], $admin);

        $this->command->info('Positions seeded successfully!');
    }
}