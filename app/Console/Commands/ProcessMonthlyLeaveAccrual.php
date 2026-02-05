<?php

namespace App\Console\Commands;

use App\Services\HR\LeaveManagementService;
use Illuminate\Console\Command;

/**
 * Process Monthly Leave Accrual Command
 * 
 * This command should be scheduled to run on the first day of each month.
 * It adds monthly leave credits to all active employees.
 */
class ProcessMonthlyLeaveAccrual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:process-monthly-accrual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process monthly leave accrual for all active employees';

    /**
     * Execute the console command.
     */
    public function handle(LeaveManagementService $leaveService): int
    {
        $this->info('Starting monthly leave accrual processing...');

        try {
            $result = $leaveService->processMonthlyAccrual();

            if ($result['success']) {
                $this->info('✓ Monthly leave accrual processed successfully');
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Year', $result['year']],
                        ['Month', $result['month']],
                        ['Employees Processed', $result['employees_processed']],
                        ['Policies Processed', $result['policies_processed']],
                        ['Balances Updated', $result['balances_updated']],
                        ['Balances Created', $result['balances_created']],
                        ['Errors', count($result['errors'])],
                    ]
                );

                if (!empty($result['errors'])) {
                    $this->warn('Some errors occurred during processing:');
                    foreach ($result['errors'] as $error) {
                        $this->error("Employee ID {$error['employee_id']}, Policy ID {$error['policy_id']}: {$error['error']}");
                    }
                }

                return Command::SUCCESS;
            }

            $this->error('Failed to process monthly leave accrual');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Error processing monthly leave accrual: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
