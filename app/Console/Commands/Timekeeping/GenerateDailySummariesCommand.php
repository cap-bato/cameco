<?php

namespace App\Console\Commands\Timekeeping;

use Illuminate\Console\Command;
use App\Services\Timekeeping\AttendanceSummaryService;
use App\Models\Employee;
use Carbon\Carbon;

/**
 * GenerateDailySummariesCommand
 * 
 * Generates daily attendance summaries for all employees.
 * Scheduled to run at 11:59 PM daily.
 * 
 * Phase 6, Task 6.1.2: Supporting scheduled command
 * 
 * @package App\Console\Commands\Timekeeping
 */
class GenerateDailySummariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timekeeping:generate-daily-summaries
                            {--date= : Specific date to generate summaries for (YYYY-MM-DD)}
                            {--force : Force regeneration even if summaries exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily attendance summaries for all employees';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetDate = $this->option('date') 
            ? Carbon::parse($this->option('date')) 
            : Carbon::today();

        $this->info("Generating daily attendance summaries for {$targetDate->toDateString()}...");

        try {
            $summaryService = new AttendanceSummaryService();
            
            // Get all active employees
            $employees = Employee::where('status', 'active')->get();
            $this->info("Processing {$employees->count()} active employees...");

            $successCount = 0;
            $errorCount = 0;

            $progressBar = $this->output->createProgressBar($employees->count());
            $progressBar->start();

            foreach ($employees as $employee) {
                try {
                    // Compute and store daily summary
                    $summary = $summaryService->computeDailySummary(
                        $employee->id, 
                        $targetDate
                    );

                    $summaryService->storeDailySummary($summary);
                    $successCount++;

                } catch (\Exception $e) {
                    $this->error("\nFailed for employee {$employee->id}: {$e->getMessage()}");
                    $errorCount++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("✓ Summary generation completed:");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Successful', $successCount],
                    ['Failed', $errorCount],
                    ['Total Processed', $employees->count()],
                ]
            );

            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to generate summaries: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
