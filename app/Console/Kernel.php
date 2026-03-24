<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\Timekeeping\FinalizeAttendanceForPeriodCommand;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //add commands here if needed
        FinalizeAttendanceForPeriodCommand::class,
        \App\Console\Commands\SyncLeaveBalanceRemaining::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // ============================================================
        // PAYROLL & LEAVE PROCESSING
        // ============================================================

        // Monthly leave accrual processing (1st of every month at 1 AM)
        $schedule->command('leave:process-monthly-accrual')
            ->monthlyOn(1, '01:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('monthly-leave-accrual')
            ->describedAs('Process monthly leave accrual for all employees');

        // Year-end leave carry-over processing (December 30 at 2 AM)
        $schedule->command('leave:process-year-end-carryover')
            ->yearlyOn(12, 30, '02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('year-end-leave-carryover')
            ->describedAs('Process year-end leave carry-over balances');

        // ============================================================
        // DOCUMENT MANAGEMENT
        // ============================================================

        // Send document expiry reminders (Daily at 9 AM)
        $schedule->command('documents:send-expiry-reminders')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('document-expiry-reminders')
            ->describedAs('Send email reminders for documents expiring soon');

        // ============================================================
        // BADGE & ACCESS MANAGEMENT
        // ============================================================

        // Send badge expiration reminders (Daily at 8 AM)
        $schedule->command('badges:send-expiration-reminders')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('badge-expiration-reminders')
            ->describedAs('Send email reminders for RFID badges expiring soon and deactivate expired badges');

        // ============================================================
        // TIMEKEEPING & ATTENDANCE
        // ============================================================

        // Generate daily attendance summaries (Daily at 10 PM)
        $schedule->command('attendance:generate-daily-summaries')
            ->dailyAt('22:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('daily-attendance-summaries')
            ->describedAs('Generate daily attendance summaries from RFID events');

        // Check device health status (Every 15 minutes)
        $schedule->command('timekeeping:check-device-health')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->name('device-health-check')
            ->describedAs('Check RFID device health and connectivity');

        // Cleanup deduplication cache (Daily at 11 PM)
        $schedule->command('timekeeping:cleanup-deduplication-cache')
            ->dailyAt('23:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('cleanup-dedup-cache')
            ->describedAs('Cleanup RFID event deduplication cache');

        // ============================================================
        // OFFBOARDING & SEPARATIONS
        // ============================================================

        // Send overdue clearance item reminders (Daily at 10 AM)
        $schedule->command('offboarding:reminders', ['--job=overdue-clearance'])
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('offboarding-overdue-clearance')
            ->describedAs('Send reminders for overdue clearance items');

        // Remind employees of pending exit interviews (Daily at 11 AM)
        $schedule->command('offboarding:reminders', ['--job=pending-interviews'])
            ->dailyAt('11:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('offboarding-pending-interviews')
            ->describedAs('Send reminders to employees with pending exit interviews');

        // Alert HR of cases approaching last working day (Daily at 9 AM)
        $schedule->command('offboarding:reminders', ['--job=approaching-lwd'])
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('offboarding-approaching-lwd')
            ->describedAs('Alert HR of cases approaching last working day');

        // Check for overdue asset returns (Daily at 2 PM)
        $schedule->command('offboarding:reminders', ['--job=asset-return'])
            ->dailyAt('14:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('offboarding-asset-return')
            ->describedAs('Send reminders for overdue asset returns');

        // Send weekly offboarding summary report (Mondays at 8 AM)
        $schedule->command('offboarding:reminders', ['--job=weekly-report'])
            ->weeklyOn(1, '08:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('offboarding-weekly-report')
            ->describedAs('Send weekly offboarding summary report to HR head');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
