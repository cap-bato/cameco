<?php

namespace App\Console\Commands;

use App\Services\LeaveAccrualService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ProcessMonthlyLeaveAccrual extends Command
{
    protected $signature = 'leave:process-monthly-accrual {--date=}';
    
    protected $description = 'Process monthly leave accrual for all active employees';

    /**
     * Execute the console command.
     */
    public function handle(LeaveAccrualService $service): int
    {
        $date = $this->option('date') 
            ? Carbon::parse($this->option('date'))
            : now();

        $this->info("Processing monthly accrual for {$date->format('Y-m-d')}...");

        $results = $service->processMonthlyAccrualForAllEmployees($date);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Date Processed', $date->format('Y-m-d')],
                ['Successfully Processed', $results['processed']],
                ['Skipped', $results['skipped']],
                ['Errors', count($results['errors'])],
            ]
        );

        if (count($results['errors']) > 0) {
            $this->warn("\n⚠ Errors occurred during processing:");
            foreach ($results['errors'] as $error) {
                $this->error("  - Employee {$error['employee_id']}, Policy {$error['policy_id']}: {$error['error']}");
            }
            return Command::FAILURE;
        }

        $this->info("✅ Monthly accrual processing completed successfully!");
        return Command::SUCCESS;
    }
}
