<?php

namespace App\Console\Commands;

use App\Services\LeaveAccrualService;
use App\Models\Employee;
use Illuminate\Console\Command;

class ProcessYearEndCarryover extends Command
{
    protected $signature = 'leave:process-year-end-carryover {--year= : The year to process carryover for (defaults to current year)}';

    protected $description = 'Process year-end leave carryover for all active employees';

    /**
     * Execute the console command.
     */
    public function handle(LeaveAccrualService $service): int
    {
        $year = (int)($this->option('year') ?? now()->year);
        $nextYear = $year + 1;

        $this->info("Starting year-end leave carryover processing for year {$year}...");

        // Prompt only in real CLI context. Web-triggered executions do not have STDIN.
        if (app()->runningInConsole() && $this->input->isInteractive()) {
            if (!$this->confirm("This will carry forward unused leave balances from {$year} to {$nextYear}. Continue?", true)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $employees = Employee::where('status', 'active')->get();
        $bar = $this->output->createProgressBar($employees->count());
        $bar->start();

        $processed = 0;
        $errors = [];

        foreach ($employees as $employee) {
            try {
                $service->carryForwardLeave($employee, $year, $nextYear);
                $processed++;
            } catch (\Exception $e) {
                $errors[] = [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['From Year', $year],
                ['To Year', $nextYear],
                ['Employees Processed', $processed],
                ['Errors', count($errors)],
            ]
        );

        if (count($errors) > 0) {
            $this->warn("\n⚠ Errors occurred during processing:");
            foreach ($errors as $error) {
                $this->error("  - Employee {$error['employee_id']}: {$error['error']}");
            }
            return Command::FAILURE;
        }

        $this->info("✅ Year-end carryover processing completed successfully!");
        return Command::SUCCESS;
    }
}
