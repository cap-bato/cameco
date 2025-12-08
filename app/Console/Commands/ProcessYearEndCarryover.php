<?php

namespace App\Console\Commands;

use App\Services\HR\LeaveManagementService;
use Illuminate\Console\Command;

/**
 * Process Year-End Leave Carryover Command
 * 
 * This command should be scheduled to run on December 31st of each year.
 * It carries forward unused leave balances to the next year based on policy rules.
 */
class ProcessYearEndCarryover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:process-year-end-carryover {--year= : The year to process carryover for (defaults to current year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process year-end leave carryover for all active employees';

    /**
     * Execute the console command.
     */
    public function handle(LeaveManagementService $leaveService): int
    {
        $year = $this->option('year') ?? now()->year;

        $this->info("Starting year-end leave carryover processing for year {$year}...");

        if (!$this->confirm("This will carry forward unused leave balances from {$year} to " . ($year + 1) . ". Continue?", true)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        try {
            $result = $leaveService->processYearEndCarryover($year);

            if ($result['success']) {
                $this->info('✓ Year-end leave carryover processed successfully');
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['From Year', $result['from_year']],
                        ['To Year', $result['to_year']],
                        ['Balances Processed', $result['balances_processed']],
                        ['Balances Created', $result['balances_created']],
                        ['Total Days Carried Forward', $result['total_carried_forward']],
                    ]
                );

                return Command::SUCCESS;
            }

            $this->error('Failed to process year-end carryover');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Error processing year-end carryover: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
