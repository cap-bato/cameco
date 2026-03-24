<?php

namespace Database\Seeders;

use App\Models\ScheduledJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CronJobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find user without role check to avoid errors if roles not seeded yet
        $superadmin = User::where('email', 'superadmin@cameco.com')->first()
            ?? User::first();

        if (!$superadmin) {
            $this->command->warn('No user found. Skipping CronJobSeeder.');
            return;
        }

        $jobs = [
            // --- Timekeeping / RFID ---
            [
                'name' => 'Process RFID Ledger',
                'description' => 'Process unprocessed RFID ledger entries and convert them into attendance events.',
                'command' => 'app:process-rfid-ledger',
                'cron_expression' => '* * * * *', // Every minute
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            [
                'name' => 'Database and Files Backup',
                'description' => 'Database backup automated run.',
                'command' => 'backup:run',
                'cron_expression' => '1 0 1 * *', // 1st of month at 00:01
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            [
                'name' => 'DAtabase Only Backup',
                'description' => 'Database backup automated run.',
                'command' => 'backup:run --only-db',
                'cron_expression' => '1 0 1 * *', // 1st of month at 00:01
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            [
                'name' => 'Check RFID Device Health',
                'description' => 'Mark RFID devices as offline when their heartbeat has not been received within the expected window.',
                'command' => 'timekeeping:check-device-health',
                'cron_expression' => '*/2 * * * *', // Every 2 minutes
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            [
                'name' => 'Cleanup Deduplication Cache',
                'description' => 'Prune expired entries from the RFID scan deduplication cache to keep the table lean.',
                'command' => 'timekeeping:cleanup-deduplication-cache',
                'cron_expression' => '*/5 * * * *', // Every 5 minutes
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            [
                'name' => 'Generate Daily Attendance Summaries',
                'description' => 'Aggregate raw attendance events into daily summaries for all employees at the end of each day.',
                'command' => 'timekeeping:generate-daily-summaries',
                'cron_expression' => '59 23 * * *', // Daily at 23:59
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            // --- Leave Management ---
            [
                'name' => 'Process Monthly Leave Accrual',
                'description' => 'Credit monthly leave entitlements to employee balances on the first day of each month.',
                'command' => 'leave:process-monthly-accrual',
                'cron_expression' => '1 0 1 * *', // 1st of month at 00:01
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            [
                'name' => 'Process Year-End Leave Carryover',
                'description' => 'Roll over unused leave balances to the next year on December 31st per company policy.',
                'command' => 'leave:process-year-end-carryover',
                'cron_expression' => '0 23 31 12 *', // Dec 31 at 23:00
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            // --- Document Management ---
            [
                'name' => 'Send Document Expiry Reminders',
                'description' => 'Email HR and employees about documents expiring within 30, 7, and 1 day(s).',
                'command' => 'documents:send-expiry-reminders',
                'cron_expression' => '0 8 * * *', // Daily at 8 AM
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
            // --- Offboarding ---
            [
                'name' => 'Offboarding Reminders',
                'description' => 'Send weekday reminders to HR officers for employees with pending offboarding tasks.',
                'command' => 'offboarding:reminders',
                'cron_expression' => '0 9 * * 1-5', // Weekdays at 9 AM
                'is_enabled' => true,
                'run_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'last_run_at' => null,
                'last_exit_code' => null,
            ],
        ];

        foreach ($jobs as $jobData) {
            $jobData['created_by'] = $superadmin->id;
            $jobData['updated_by'] = $superadmin->id;
            
            // Calculate next run time based on cron expression
            try {
                $cron = new \Cron\CronExpression($jobData['cron_expression']);
                $jobData['next_run_at'] = Carbon::instance($cron->getNextRunDate());
            } catch (\Exception $e) {
                $jobData['next_run_at'] = Carbon::now()->addDay();
            }

            // Use firstOrCreate to avoid duplicate entry errors
            ScheduledJob::firstOrCreate(
                ['name' => $jobData['name']], // Search criteria
                $jobData // Full data to create if not found
            );
        }

        $this->command->info('Seeded cron jobs (created new or skipped existing).');
    }
}
