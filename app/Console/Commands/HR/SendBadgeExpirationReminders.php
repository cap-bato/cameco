<?php

namespace App\Console\Commands\HR;

use App\Mail\BadgeExpirationReminder;
use App\Models\BadgeIssueLog;
use App\Models\RfidCardMapping;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendBadgeExpirationReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'badges:send-expiration-reminders
                            {--dry-run : Run without sending actual emails}
                            {--verbose : Show detailed output}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Send email reminders for RFID badges expiring soon (scheduled: Daily at 8:00 AM)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔔 Starting badge expiration reminder task...');
        $isDryRun = $this->option('dry-run');
        $verbose = $this->option('verbose');

        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN MODE - No emails will be sent');
        }

        try {
            // Get badges expiring in next 30 days
            $expiringBadges = RfidCardMapping::with(['employee.user', 'employee.department'])
                ->active()
                ->whereNotNull('expires_at')
                ->where('expires_at', '>=', now())
                ->where('expires_at', '<=', now()->addDays(30))
                ->get()
                ->groupBy(function ($badge) {
                    return $badge->expires_at->diffInDays(now());
                });

            $sentCount = 0;
            $skippedCount = 0;

            foreach ($expiringBadges as $daysUntilExpiry => $badges) {
                // Send reminders at specific intervals: 30, 14, 7, 3, 1 days
                if (!in_array($daysUntilExpiry, [30, 14, 7, 3, 1])) {
                    $skippedCount += count($badges);
                    continue;
                }

                foreach ($badges as $badge) {
                    try {
                        // Email to employee
                        if ($badge->employee && $badge->employee->user && $badge->employee->user->email) {
                            if (!$isDryRun) {
                                Mail::to($badge->employee->user->email)
                                    ->send(new BadgeExpirationReminder($badge, $daysUntilExpiry));
                            }

                            $this->info(
                                sprintf(
                                    '✅ Reminder sent to %s for badge %s - %d days until expiry',
                                    $badge->employee->user->email,
                                    $badge->card_uid,
                                    $daysUntilExpiry
                                )
                            );
                            $sentCount++;
                        } else {
                            if ($verbose) {
                                $this->warn(
                                    sprintf(
                                        '⚠️  No email found for employee %s (badge: %s)',
                                        $badge->employee->full_name ?? 'Unknown',
                                        $badge->card_uid
                                    )
                                );
                            }
                            $skippedCount++;
                        }

                        // Optional: Notify HR Managers with badge summary
                        if ($daysUntilExpiry <= 7) {
                            $this->notifyHRManagers($badge, $daysUntilExpiry, $isDryRun);
                        }
                    } catch (\Exception $e) {
                        $this->error(
                            sprintf(
                                '❌ Failed to send reminder for badge %s: %s',
                                $badge->card_uid,
                                $e->getMessage()
                            )
                        );
                    }
                }
            }

            // Process and deactivate expired badges
            $this->processExpiredBadges($isDryRun);

            // Summary
            $this->info('');
            $this->info('═══════════════════════════════════════════════════');
            $this->info(sprintf('✅ Reminders sent: %d', $sentCount));
            $this->info(sprintf('⏭️  Skipped: %d', $skippedCount));
            if ($isDryRun) {
                $this->info('(DRY RUN - No emails actually sent)');
            }
            $this->info('═══════════════════════════════════════════════════');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Badge expiration reminder command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Process and deactivate badges that have expired today.
     *
     * @param bool $isDryRun
     * @return void
     */
    protected function processExpiredBadges(bool $isDryRun): void
    {
        $this->info('');
        $this->info('🔍 Checking for expired badges...');

        $expiredToday = RfidCardMapping::query()
            ->active()
            ->whereDate('expires_at', now()->toDateString())
            ->get();

        if ($expiredToday->isEmpty()) {
            $this->info('✅ No badges expired today');

            return;
        }

        /** @var RfidCardMapping $badge */
        foreach ($expiredToday as $badge) {
            try {
                if (!$isDryRun) {
                    DB::beginTransaction();

                    // Deactivate badge
                    $badge->update([
                        'is_active' => false,
                        'deactivated_at' => now(),
                        'deactivation_reason' => 'Badge expired automatically',
                    ]);

                    // Log expiration in BadgeIssueLog
                    BadgeIssueLog::create([
                        'card_uid' => $badge->card_uid,
                        'employee_id' => $badge->employee_id,
                        'issued_by' => $this->getSystemUserId(),
                        'issued_at' => now(),
                        'action_type' => 'expired',
                        'reason' => 'Automatic expiration',
                    ]);

                    DB::commit();
                }

                $this->warn(
                    sprintf(
                        '⏱️  Badge %s (Employee: %s) expired and deactivated automatically',
                        $badge->card_uid,
                        $badge->employee->full_name ?? 'Unknown'
                    )
                );
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error(
                    sprintf(
                        '❌ Failed to deactivate badge %s: %s',
                        $badge->card_uid,
                        $e->getMessage()
                    )
                );
            }
        }
    }

    /**
     * Notify HR Managers about upcoming critical badge expirations.
     *
     * @param RfidCardMapping $badge
     * @param int $daysUntilExpiry
     * @param bool $isDryRun
     * @return void
     */
    protected function notifyHRManagers(RfidCardMapping $badge, int $daysUntilExpiry, bool $isDryRun): void
    {
        try {
            $hrManagers = User::role('hr-manager')->get();

            foreach ($hrManagers as $manager) {
                // This could be extended with an HR notification system
                // For now, we log the notification intent
                if (!$isDryRun && $manager->email) {
                    // Optional: Send to HR managers as well
                    // Mail::to($manager->email)->send(new BadgeExpirationAlert($badge, $daysUntilExpiry));
                }
            }
        } catch (\Exception $e) {
            // Log but don't fail on HR manager notification
            \Illuminate\Support\Facades\Log::warning('Failed to notify HR managers', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get system user ID for automated actions.
     * Falls back to user ID 1 if system user doesn't exist.
     *
     * @return int
     */
    protected function getSystemUserId(): int
    {
        try {
            $systemUser = User::where('email', 'system@' . config('app.name'))
                ->first();

            return $systemUser?->id ?? 1;
        } catch (\Exception $e) {
            return 1;
        }
    }
}
