<?php

namespace App\Console\Commands\Timekeeping;

use App\Models\DailyAttendanceSummary;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalizeAttendanceForPeriodCommand extends Command
{
    protected $signature = 'timekeeping:finalize-attendance
                            {--from=      : Start date YYYY-MM-DD (required unless --period is given)}
                            {--to=        : End date YYYY-MM-DD (required unless --period is given)}
                            {--period=    : PayrollPeriod ID — uses period_start / timekeeping_cutoff_date automatically}
                            {--dry-run    : Show what would be finalized without making changes}
                            {--force      : Re-finalize rows that are already finalized}';

    protected $description = 'Mark daily_attendance_summary rows as is_finalized=true for a date range, locking them for payroll processing';

    public function handle(): int
    {
        // Resolve date range
        [$from, $to, $period] = $this->resolveDateRange();

        if (!$from || !$to) {
            $this->error('Provide --from and --to, or --period=<id>.');
            return Command::FAILURE;
        }

        $this->info("Finalizing attendance from {$from} to {$to}...");

        // Build query
        $query = DailyAttendanceSummary::whereBetween('attendance_date', [$from, $to]);
        if (!$this->option('force')) {
            $query->where('is_finalized', false);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->warn('No unfinalized rows found in range. Use --force to re-finalize.');
            return Command::SUCCESS;
        }

        $this->line("Found {$count} row(s) to finalize.");

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] No changes made.');
            return Command::SUCCESS;
        }

        // Confirm for large sets
        if ($count > 500 && !$this->confirm("Finalize {$count} rows?")) {
            $this->warn('Aborted.');
            return Command::FAILURE;
        }

        DB::beginTransaction();
        try {
            $updated = (clone $query)->update(['is_finalized' => true]);

            // If --period provided, lock timekeeping on the period
            if ($period) {
                $period->update(['timekeeping_data_locked' => true]);
                $this->info("🔒 Locked timekeeping for period [{$period->id}] {$period->period_name}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed: ' . $e->getMessage());
            Log::error('[FinalizeAttendanceForPeriodCommand] Failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        $this->info("✅ Finalized {$updated} attendance row(s).");

        Log::info('[FinalizeAttendanceForPeriodCommand] Attendance finalized', [
            'from'       => $from,
            'to'         => $to,
            'period_id'  => $period?->id,
            'rows'       => $updated,
        ]);

        return Command::SUCCESS;
    }

    /** Resolve --from/--to from options or from --period */
    private function resolveDateRange(): array
    {
        if ($periodId = $this->option('period')) {
            $period = PayrollPeriod::findOrFail($periodId);
            $from   = $period->period_start->toDateString();
            // Use timekeeping_cutoff_date if set, otherwise period_end
            $to     = $period->timekeeping_cutoff_date
                ? $period->timekeeping_cutoff_date->toDateString()
                : $period->period_end->toDateString();
            return [$from, $to, $period];
        }

        return [
            $this->option('from'),
            $this->option('to'),
            null,
        ];
    }
}