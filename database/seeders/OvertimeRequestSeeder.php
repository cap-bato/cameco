<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Models\User;

class OvertimeRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates overtime request records with various statuses for testing:
     * - Pending requests awaiting approval
     * - Approved requests
     * - Completed requests with actual hours
     * - Rejected requests with reasons
     */
    public function run(): void
    {
        $this->command->info('Seeding overtime requests...');

        // Get existing employees (limit 15)
        $employees = Employee::limit(15)->get();
        
        // Get HR users for approvals
        $hrUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['HR Staff', 'HR Manager', 'HR Officer', 'Super Admin']);
        })->limit(3)->get();

        // Validate we have required data
        if ($employees->isEmpty()) {
            $this->command->warn('⚠️  No employees found. Skipping OvertimeRequest seeding.');
            return;
        }

        if ($hrUsers->isEmpty()) {
            $this->command->warn('⚠️  No HR users found. Skipping OvertimeRequest seeding.');
            return;
        }

        $creator = $hrUsers->first();
        $totalCreated = 0;

        // Create progress bar
        $progressBar = $this->command->getOutput()->createProgressBar($employees->count());
        $progressBar->start();

        // For each employee, create overtime requests with various statuses
        foreach ($employees as $employee) {
            // Create 1-2 pending requests per employee
            $pendingCount = rand(1, 2);
            OvertimeRequest::factory()
                ->count($pendingCount)
                ->forEmployee($employee->id)
                ->create([
                    'created_by' => $creator->id,
                ]);
            $totalCreated += $pendingCount;

            // Create 1-2 approved requests
            $approvedCount = rand(1, 2);
            OvertimeRequest::factory()
                ->count($approvedCount)
                ->forEmployee($employee->id)
                ->approved()
                ->create([
                    'created_by' => $creator->id,
                    'approved_by' => $hrUsers->random()->id,
                ]);
            $totalCreated += $approvedCount;

            // Create 1-2 completed requests
            $completedCount = rand(1, 2);
            OvertimeRequest::factory()
                ->count($completedCount)
                ->forEmployee($employee->id)
                ->completed()
                ->create([
                    'created_by' => $creator->id,
                    'approved_by' => $hrUsers->random()->id,
                ]);
            $totalCreated += $completedCount;

            // Occasionally create a rejected request (30% chance)
            if (rand(0, 2) === 0) {
                OvertimeRequest::factory()
                    ->forEmployee($employee->id)
                    ->rejected()
                    ->create([
                        'created_by' => $creator->id,
                        'approved_by' => $hrUsers->random()->id,
                    ]);
                $totalCreated++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();

        // Display summary
        $this->command->info("✅ Overtime requests seeded successfully!");
        $this->command->info("   Total records created: {$totalCreated}");
        
        // Display status breakdown
        $byStatus = OvertimeRequest::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
        
        foreach ($byStatus as $status => $count) {
            $this->command->line("   • {$status}: {$count} records");
        }
    }
}
