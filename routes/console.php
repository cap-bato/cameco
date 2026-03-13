<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Stringable;
use App\Jobs\Timekeeping\ProcessRfidLedgerJob;
use App\Services\System\SystemCronService;

function recordScheduledCommandResult(string $command, int $exitCode, string $output = ''): void
{
    app(SystemCronService::class)->recordScheduledExecutionByCommand($command, $exitCode, $output);
}

/**
 * Phase 6, Task 6.1.2: Configure scheduled jobs
 * 
 * RFID Ledger Polling: Runs every 1 minute to process new attendance events
 */
Schedule::job(new ProcessRfidLedgerJob())
    ->everyMinute()
    ->name('process-rfid-ledger')
    ->withoutOverlapping() // Prevent concurrent runs
    ->onOneServer() // Run on only one server in a multi-server setup
    ->onSuccess(function () {
        recordScheduledCommandResult('app:process-rfid-ledger', 0);
    })
    ->onFailure(function () {
        recordScheduledCommandResult('app:process-rfid-ledger', 1);
    });

/**
 * Cleanup expired deduplication cache entries (every 5 minutes)
 */
Schedule::command('timekeeping:cleanup-deduplication-cache')
    ->everyFiveMinutes()
    ->name('cleanup-deduplication-cache')
    ->withoutOverlapping()
    ->onSuccess(function (Stringable $output) {
        recordScheduledCommandResult('timekeeping:cleanup-deduplication-cache', 0, (string) $output);
    })
    ->onFailure(function (Stringable $output) {
        recordScheduledCommandResult('timekeeping:cleanup-deduplication-cache', 1, (string) $output);
    });

/**
 * Generate daily attendance summaries (runs at 11:59 PM)
 */
Schedule::command('timekeeping:generate-daily-summaries')
    ->dailyAt('23:59')
    ->name('generate-daily-summaries')
    ->timezone('Asia/Manila')
    ->onSuccess(function (Stringable $output) {
        recordScheduledCommandResult('timekeeping:generate-daily-summaries', 0, (string) $output);
    })
    ->onFailure(function (Stringable $output) {
        recordScheduledCommandResult('timekeeping:generate-daily-summaries', 1, (string) $output);
    });

/**
 * Health check for offline devices (every 2 minutes)
 */
Schedule::command('timekeeping:check-device-health')
    ->everyTwoMinutes()
    ->name('check-device-health')
    ->withoutOverlapping()
    ->onSuccess(function (Stringable $output) {
        recordScheduledCommandResult('timekeeping:check-device-health', 0, (string) $output);
    })
    ->onFailure(function (Stringable $output) {
        recordScheduledCommandResult('timekeeping:check-device-health', 1, (string) $output);
    });

/**
 * Phase 1, Task 1.6: Configure leave management scheduled tasks
 * 
 * Process Monthly Leave Accrual: Runs on the 1st of each month at 00:01
 * - Accrues leave credits for all Regular employees
 * - Prorates for new hires based on hire_date
 * - Logs transactions for audit trail
 */
Schedule::command('leave:process-monthly-accrual')
    ->monthlyOn(1, '00:01')
    ->name('process-monthly-leave-accrual')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->onSuccess(function (Stringable $output) {
        recordScheduledCommandResult('leave:process-monthly-accrual', 0, (string) $output);
    })
    ->onFailure(function (Stringable $output) {
        recordScheduledCommandResult('leave:process-monthly-accrual', 1, (string) $output);
    });

/**
 * Process Year-End Leave Carryover: Runs on December 31st at 23:00
 * - Carries forward unused leave based on policy rules
 * - Handles cash conversion (marks excess for payroll)
 * - Handles forfeit conversion (removes excess days)
 * - Handles none conversion (carries all forward)
 */
Schedule::command('leave:process-year-end-carryover')
    ->cron('0 23 31 12 *')
    ->name('process-year-end-leave-carryover')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->onSuccess(function (Stringable $output) {
        recordScheduledCommandResult('leave:process-year-end-carryover', 0, (string) $output);
    })
    ->onFailure(function (Stringable $output) {
        recordScheduledCommandResult('leave:process-year-end-carryover', 1, (string) $output);
    });

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
