<?php

namespace App\Console\Commands;

use App\Models\ClearanceItem;
use App\Models\OffboardingCase;
use App\Models\CompanyAsset;
use App\Models\ExitInterview;
use App\Models\User;
use App\Notifications\ClearanceItemPending;
use App\Notifications\ClearanceOverdue;
use App\Notifications\ExitInterviewPending;
use App\Notifications\AssetReturnOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OffboardingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'offboarding:reminders
                           {--job= : Execute a specific job (overdue-clearance, pending-interviews, approaching-lwd, weekly-report, asset-return)}
                           {--detailed : Display detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders and generate reports for offboarding process';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $job = $this->option('job');
        $verbose = (bool) $this->option('detailed');

        if ($job) {
            // Execute specific job
            return $this->executeJob($job, $verbose);
        }

        // Execute all jobs
        $this->info('🔄 Starting Offboarding Reminders...');
        $this->newLine();

        $jobs = [
            'overdue-clearance' => 'Checking for overdue clearance items...',
            'pending-interviews' => 'Checking for pending exit interviews...',
            'approaching-lwd' => 'Checking for cases approaching last working day...',
            'asset-return' => 'Checking for overdue asset returns...',
            'weekly-report' => 'Checking if weekly report should be sent...',
        ];

        $succeeded = 0;
        $failed = 0;

        foreach ($jobs as $jobName => $message) {
            try {
                $this->info("  ↳ $message");
                $result = $this->executeJob($jobName, $verbose);
                if ($result === 0) {
                    $this->line('    ✓ Completed');
                    $succeeded++;
                } else {
                    $this->line('    ✗ Failed');
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("    ✗ Error: {$e->getMessage()}");
                $failed++;
            }
            $this->newLine();
        }

        $this->info("✅ Offboarding Reminders Complete: $succeeded succeeded, $failed failed");

        return $failed === 0 ? 0 : 1;
    }

    /**
     * Execute a specific job.
     */
    private function executeJob(string $job, bool $verbose = false): int
    {
        match ($job) {
            'overdue-clearance' => $this->sendOverdueClearanceReminders($verbose),
            'pending-interviews' => $this->sendPendingInterviewReminders($verbose),
            'approaching-lwd' => $this->sendApproachingLWDAlerts($verbose),
            'asset-return' => $this->sendAssetReturnReminders($verbose),
            'weekly-report' => $this->sendWeeklyReport($verbose),
            default => $this->error("Unknown job: $job"),
        };

        return match ($job) {
            'overdue-clearance', 'pending-interviews', 'approaching-lwd', 'asset-return', 'weekly-report' => 0,
            default => 1,
        };
    }

    /**
     * Send reminders for overdue clearance items.
     * Logic: Items with due_date < today and status != 'approved'
     */
    private function sendOverdueClearanceReminders(bool $verbose = false): int
    {
        $today = Carbon::today();

        // Get overdue clearance items
        $overdueItems = ClearanceItem::with([
            'offboardingCase.employee.profile',
            'offboardingCase.hrCoordinator',
            'assignedTo',
        ])
            ->where('status', '!=', 'approved')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->get();

        if ($overdueItems->isEmpty()) {
            $verbose && $this->line('    No overdue clearance items found.');
            return 0;
        }

        $count = 0;
        /** @var ClearanceItem $item */
        foreach ($overdueItems as $item) {
            try {
                // Calculate days overdue
                $daysOverdue = $today->diffInDays($item->due_date);

                // Send to approver
                if ($item->assignedTo) {
                    $item->assignedTo->notify(new ClearanceOverdue($item, $daysOverdue));
                    $count++;
                }

                // Send to HR coordinator
                if ($item->offboardingCase->hrCoordinator) {
                    $item->offboardingCase->hrCoordinator->notify(new ClearanceOverdue($item, $daysOverdue));
                }

                $verbose && $this->line("    Notified: {$item->item_name} (Case: {$item->offboardingCase->case_number})");
            } catch (\Exception $e) {
                $verbose && $this->error("    Error notifying for item {$item->id}: {$e->getMessage()}");
            }
        }

        $verbose && $this->line("    Total overdue clearance reminders sent: $count");

        return 0;
    }

    /**
     * Send reminders for pending exit interviews.
     * Logic: Cases with status = 'in_progress' and no completed exit interview
     */
    private function sendPendingInterviewReminders(bool $verbose = false): int
    {
        // Get offboarding cases with pending exit interviews
        $cases = OffboardingCase::with([
            'employee.profile',
            'exitInterview',
            'hrCoordinator',
        ])
            ->where('status', 'in_progress')
            ->get()
            ->filter(function ($case) {
                // Filter for pending interviews (not completed)
                $interview = $case->exitInterview;
                return !$interview || $interview->status !== 'completed';
            });

        if ($cases->isEmpty()) {
            $verbose && $this->line('    No pending exit interviews found.');
            return 0;
        }

        $count = 0;
        foreach ($cases as $case) {
            try {
                $employee = $case->employee;
                $employeeUser = $employee->user;

                if ($employeeUser) {
                    $employeeUser->notify(new ExitInterviewPending($case));
                    $count++;
                    $verbose && $this->line("    Notified employee: {$employee->profile?->first_name} {$employee->profile?->last_name} (Case: {$case->case_number})");
                }
            } catch (\Exception $e) {
                $verbose && $this->error("    Error notifying for case {$case->id}: {$e->getMessage()}");
            }
        }

        $verbose && $this->line("    Total pending interview reminders sent: $count");

        return 0;
    }

    /**
     * Alert HR of cases approaching last working day.
     * Logic: Cases with last_working_day between today and 3 days from today
     */
    private function sendApproachingLWDAlerts(bool $verbose = false): int
    {
        $today = Carbon::today();
        $threeDaysFromNow = Carbon::today()->addDays(3);

        // Get cases approaching last working day
        $cases = OffboardingCase::with([
            'employee.profile',
            'hrCoordinator',
        ])
            ->where('status', 'in_progress')
            ->whereBetween('last_working_day', [$today, $threeDaysFromNow])
            ->get();

        if ($cases->isEmpty()) {
            $verbose && $this->line('    No cases approaching last working day.');
            return 0;
        }

        $count = 0;
        foreach ($cases as $case) {
            try {
                if ($case->hrCoordinator) {
                    // Calculate days until LWD
                    $daysUntilLWD = $today->diffInDays($case->last_working_day);

                    // Record directly to notifications table
                    $case->hrCoordinator->notifications()->create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'type' => 'App\Notifications\ApproachingLWDAlert',
                        'notifiable_type' => 'App\Models\User',
                        'notifiable_id' => $case->hrCoordinator->id,
                        'data' => json_encode([
                            'title' => 'Last Working Day Approaching',
                            'message' => "Employee: {$case->employee->profile?->first_name} {$case->employee->profile?->last_name}",
                            'case_id' => $case->id,
                            'case_number' => $case->case_number,
                            'last_working_day' => $case->last_working_day->format('F d, Y'),
                            'days_remaining' => $daysUntilLWD,
                        ]),
                    ]);

                    $count++;
                    $verbose && $this->line("    Alert sent for case {$case->case_number} (LWD: {$case->last_working_day->format('M d')})");
                }
            } catch (\Exception $e) {
                $verbose && $this->error("    Error notifying for case {$case->id}: {$e->getMessage()}");
            }
        }

        $verbose && $this->line("    Total approaching LWD alerts sent: $count");

        return 0;
    }

    /**
     * Check for overdue asset returns and send reminders.
     * Logic: Assets not yet returned (status != 'returned') and return_date < today
     */
    private function sendAssetReturnReminders(bool $verbose = false): int
    {
        $today = Carbon::today();

        // Get overdue assets
        $overdueAssets = CompanyAsset::with([
            'employee.profile',
            'employee.user',
            'offboardingCase.hrCoordinator',
        ])
            ->where('status', '!=', 'returned')
            ->whereNotNull('return_date')
            ->where('return_date', '<', $today)
            ->get();

        if ($overdueAssets->isEmpty()) {
            $verbose && $this->line('    No overdue asset returns found.');
            return 0;
        }

        $count = 0;
        /** @var CompanyAsset $asset */
        foreach ($overdueAssets as $asset) {
            try {
                // Calculate days overdue
                $daysOverdue = $today->diffInDays($asset->return_date);

                $employee = $asset->employee;
                if ($employee && $employee->user) {
                    $employee->user->notify(new AssetReturnOverdue($asset, $daysOverdue));
                    $count++;
                    $verbose && $this->line("    Notified employee: {$employee->profile?->first_name} {$employee->profile?->last_name} ({$asset->asset_name})");
                }
            } catch (\Exception $e) {
                $verbose && $this->error("    Error notifying for asset {$asset->id}: {$e->getMessage()}");
            }
        }

        $verbose && $this->line("    Total asset return reminders sent: $count");

        return 0;
    }

    /**
     * Send weekly summary report to HR head.
     * Logic: Every Monday (or if last run was > 7 days ago)
     */
    private function sendWeeklyReport(bool $verbose = false): int
    {
        // Check if today is Monday (1 = Monday in ISO format)
        $isMonday = Carbon::today()->isMonday();

        if (!$isMonday) {
            $verbose && $this->line('    Not Monday - weekly report scheduled for Mondays only.');
            return 0;
        }

        try {
            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();

            // Collect weekly statistics
            $statistics = [
                'new_cases' => OffboardingCase::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count(),
                'completed_this_week' => OffboardingCase::where('status', 'completed')
                    ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                    ->count(),
                'in_progress' => OffboardingCase::where('status', 'in_progress')->count(),
                'pending_clearances' => ClearanceItem::where('status', '!=', 'approved')->count(),
                'pending_interviews' => ExitInterview::where('status', '!=', 'completed')->count(),
                'overdue_assets' => CompanyAsset::where('status', '!=', 'returned')
                    ->where('return_date', '<', Carbon::today())
                    ->count(),
            ];

            // Get HR head email
            $hrHeadRole = \App\Models\Role::where('name', 'hr_head')->first();
            if (!$hrHeadRole) {
                $verbose && $this->line('    HR Head role not found - skipping report.');
                return 0;
            }

            $hrHeads = User::role('hr_head')->get();

            if ($hrHeads->isEmpty()) {
                $verbose && $this->line('    No HR Head users found - skipping report.');
                return 0;
            }

            foreach ($hrHeads as $hrHead) {
                try {
                    // Send via email
                    Mail::send('emails.hr.offboarding-weekly-report', [
                        'hrHead' => $hrHead,
                        'statistics' => $statistics,
                        'weekStart' => $startOfWeek->format('F d, Y'),
                        'weekEnd' => $endOfWeek->format('F d, Y'),
                    ], function ($message) use ($hrHead) {
                        $message->to($hrHead->email)
                            ->subject('Weekly Offboarding Report - ' . Carbon::now()->format('M d, Y'));
                    });

                    // Also store as database notification
                    $hrHead->notifications()->create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'type' => 'App\Notifications\WeeklyOffboardingReport',
                        'notifiable_type' => 'App\Models\User',
                        'notifiable_id' => $hrHead->id,
                        'data' => json_encode([
                            'title' => 'Weekly Offboarding Report',
                            'message' => 'Your weekly offboarding summary report is ready',
                            'type' => 'weekly_report',
                            'statistics' => $statistics,
                            'week_start' => $startOfWeek->format('Y-m-d'),
                            'week_end' => $endOfWeek->format('Y-m-d'),
                        ]),
                    ]);

                    $verbose && $this->line("    Report sent to: {$hrHead->email}");
                } catch (\Exception $e) {
                    $verbose && $this->error("    Error sending report to {$hrHead->email}: {$e->getMessage()}");
                }
            }

            $verbose && $this->info('    Weekly report generated and sent.');

            return 0;
        } catch (\Exception $e) {
            $this->error("Error generating weekly report: {$e->getMessage()}");
            return 1;
        }
    }
}

