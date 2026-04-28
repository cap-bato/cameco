<?php

namespace App\Console\Commands\Timekeeping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * CleanupDeduplicationCacheCommand
 * 
 * Removes expired entries from the event deduplication cache.
 * Scheduled to run every 5 minutes.
 * 
 * Phase 6, Task 6.1.2: Supporting scheduled command
 * 
 * @package App\Console\Commands\Timekeeping
 */
class CleanupDeduplicationCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timekeeping:cleanup-deduplication-cache
                            {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired deduplication cache entries from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting deduplication cache cleanup...');

        // Require explicit confirmation unless --force is provided or not running in a real
        // CLI context (e.g. called via Artisan::call() from a web request or cron runner).
        if (! $this->option('force') && app()->runningInConsole() && $this->input->isInteractive()) {
            $this->warn('This command will permanently delete expired deduplication entries from the attendance_events table.');
            if (! $this->confirm('Do you wish to continue?', false)) {
                $this->comment('Cleanup cancelled by user.');
                return Command::SUCCESS;
            }
        }

        try {
            // Delete deduplicated entries older than 1 hour (well past the 15-second window)
            $expirationThreshold = Carbon::now()->subHour();

            $deletedCount = DB::table('attendance_events')
                ->where('is_deduplicated', true)
                ->where('created_at', '<', $expirationThreshold)
                ->delete();

            if ($deletedCount > 0) {
                $this->info("Deleted {$deletedCount} expired deduplication entries");
            } else {
                $this->comment('No expired entries found to clean up');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to clean up deduplication cache: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
