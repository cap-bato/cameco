<?php

/**
 * ⚠️ Refactor Notes for CronRepository
 *
 * This repository currently contains both persistence and business logic,
 * which violates the repository pattern and pollutes the domain boundary.
 *
 * Cleanup Plan:
 * 1. Extract common filter logic into a reusable scope or filter builder
 *    (remove duplicated filters in getAllJobs and getPaginatedJobs)
 * 2. Fix operator precedence issues (wrap enabled + exit_code checks)
 * 3. Move execution-related logic (increment counters, failure handling,
 *    timestamps) into a CronExecutionService
 * 4. Replace fake getExecutionHistory with a real executions table
 * 5. Add tenant/org scoping once multi-tenancy is enabled
 * 6. Remove unbounded getAllJobs in favor of pagination everywhere
 *
 * Do not introduce new logic here until the separation of responsibilities
 * is complete.
 * This is a temporary but functional implementation for the UI.
 */

namespace App\Repositories\Eloquent\System;

use App\Models\ScheduledJob;
use App\Repositories\Contracts\System\CronRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CronRepository implements CronRepositoryInterface
{
    /**
     * Get all scheduled jobs with optional filters.
     *
     * @param array $filters Optional filters (is_enabled, status, search)
     * @return Collection
     */
    public function getAllJobs(array $filters = []): Collection
    {
        $query = ScheduledJob::with(['creator', 'updater']);

        // Filter by enabled status
        if (isset($filters['is_enabled'])) {
            $query->where('is_enabled', $filters['is_enabled']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            switch ($filters['status']) {
                case 'overdue':
                    $query->overdue();
                    break;
                case 'active':
                    $query->enabled()->whereNull('last_exit_code')->orWhere('last_exit_code', 0);
                    break;
                case 'failed':
                    $query->where('last_exit_code', '!=', 0)->whereNotNull('last_exit_code');
                    break;
            }
        }

        // Search by name or command
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('command', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get all enabled scheduled jobs.
     *
     * @return Collection
     */
    public function getEnabledJobs(): Collection
    {
        return ScheduledJob::enabled()
            ->with(['creator', 'updater'])
            ->orderBy('next_run_at')
            ->get();
    }

    /**
     * Get all disabled scheduled jobs.
     *
     * @return Collection
     */
    public function getDisabledJobs(): Collection
    {
        return ScheduledJob::disabled()
            ->with(['creator', 'updater'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get overdue jobs (past their next_run_at time).
     *
     * @return Collection
     */
    public function getOverdueJobs(): Collection
    {
        return ScheduledJob::overdue()
            ->with(['creator', 'updater'])
            ->orderBy('next_run_at')
            ->get();
    }

    /**
     * Get upcoming jobs (running within next X minutes).
     *
     * @param int $minutes
     * @return Collection
     */
    public function getUpcomingJobs(int $minutes = 60): Collection
    {
        return ScheduledJob::upcoming($minutes)
            ->with(['creator', 'updater'])
            ->get();
    }

    /**
     * Find a scheduled job by ID.
     *
     * @param int $id
     * @return ScheduledJob|null
     */
    public function findById(int $id): ?ScheduledJob
    {
        return ScheduledJob::with(['creator', 'updater'])->find($id);
    }

    /**
     * Find a scheduled job by command signature.
     *
     * @param string $command
     * @return ScheduledJob|null
     */
    public function findByCommand(string $command): ?ScheduledJob
    {
        return ScheduledJob::where('command', $command)->first();
    }

    /**
     * Find a scheduled job by name.
     *
     * @param string $name
     * @return ScheduledJob|null
     */
    public function findByName(string $name): ?ScheduledJob
    {
        return ScheduledJob::where('name', $name)->first();
    }

    /**
     * Create a new scheduled job.
     *
     * @param array $data
     * @return ScheduledJob
     */
    public function createJob(array $data): ScheduledJob
    {
        $job = ScheduledJob::create($data);

        // Calculate initial next run time
        if ($job->is_enabled && $job->cron_expression) {
            $job->next_run_at = $job->calculateNextRun();
            $job->save();
        }

        return $job->load(['creator', 'updater']);
    }

    /**
     * Update an existing scheduled job.
     *
     * @param int $id
     * @param array $data
     * @return ScheduledJob
     */
    public function updateJob(int $id, array $data): ScheduledJob
    {
        $job = $this->findById($id);

        if (!$job) {
            throw new \Exception("Scheduled job with ID {$id} not found.");
        }

        $job->update($data);

        // Recalculate next run time if cron expression changed or job was enabled
        if (isset($data['cron_expression']) || (isset($data['is_enabled']) && $data['is_enabled'])) {
            if ($job->is_enabled && $job->cron_expression) {
                $job->next_run_at = $job->calculateNextRun();
                $job->save();
            }
        }

        return $job->fresh(['creator', 'updater']);
    }

    /**
     * Toggle job enabled/disabled status.
     *
     * @param int $id
     * @return ScheduledJob
     */
    public function toggleJobStatus(int $id): ScheduledJob
    {
        $job = $this->findById($id);

        if (!$job) {
            throw new \Exception("Scheduled job with ID {$id} not found.");
        }

        $job->is_enabled = !$job->is_enabled;

        // Calculate next run time if enabling
        if ($job->is_enabled && $job->cron_expression) {
            $job->next_run_at = $job->calculateNextRun();
        } else {
            $job->next_run_at = null;
        }

        $job->save();

        return $job->fresh(['creator', 'updater']);
    }

    /**
     * Soft delete a scheduled job.
     *
     * @param int $id
     * @return bool
     */
    public function deleteJob(int $id): bool
    {
        $job = $this->findById($id);

        if (!$job) {
            throw new \Exception("Scheduled job with ID {$id} not found.");
        }

        return $job->delete();
    }

    /**
     * Record a job execution (update last_run_at, exit_code, output, counts).
     *
     * @param int $id
     * @param int $exitCode
     * @param string $output
     * @return ScheduledJob
     */
    public function recordExecution(int $id, int $exitCode, string $output = ''): ScheduledJob
    {
        $job = $this->findById($id);

        if (!$job) {
            throw new \Exception("Scheduled job with ID {$id} not found.");
        }

        // Strip ANSI escape codes and characters outside Latin-1 (WIN1252) before
        // storing, so the output can be saved regardless of the PostgreSQL client encoding.
        $output = preg_replace('/\x1B\[[0-9;]*[mGKHFJSTsulABCDEF]/', '', $output ?? '');
        $output = preg_replace('/[^\x00-\xFF]/', '', $output);

        // Increment run count
        $job->incrementRunCount();

        // Record success or failure
        if ($exitCode === 0) {
            $job->recordSuccess($output);
        } else {
            $job->recordFailure($exitCode, $output);
        }

        return $job->fresh(['creator', 'updater']);
    }

    /**
     * Get execution history for a job.
     * Note: This is a simplified version. For full history, consider creating
     * a separate cron_job_executions table with individual execution records.
     *
     * @param int $id
     * @param int $limit
     * @return array Historical data
     */
    public function getExecutionHistory(int $id, int $limit = 10): array
    {
        $job = $this->findById($id);

        if (!$job) {
            return [];
        }

        // For now, return the most recent execution details
        // In a full implementation, you'd query a separate executions table
        return [
            [
                'run_at' => $job->last_run_at,
                'exit_code' => $job->last_exit_code,
                'output' => $job->last_output,
                'status' => $job->last_exit_code === 0 ? 'success' : 'failure',
            ]
        ];
    }

    /**
     * Get aggregate metrics for all jobs.
     *
     * @return array
     */
    public function getJobMetrics(): array
    {
        $totalJobs = ScheduledJob::count();
        $enabledJobs = ScheduledJob::enabled()->count();
        $disabledJobs = ScheduledJob::disabled()->count();
        $overdueJobs = ScheduledJob::overdue()->count();

        // Get jobs with failures
        $failedJobs = ScheduledJob::where('last_exit_code', '!=', 0)
            ->whereNotNull('last_exit_code')
            ->count();

        // Calculate overall success rate
        $jobs = ScheduledJob::all();
        $totalRuns = $jobs->sum('run_count');
        $totalSuccesses = $jobs->sum('success_count');
        $overallSuccessRate = $totalRuns > 0 ? round(($totalSuccesses / $totalRuns) * 100, 2) : 0;

        // Get next upcoming job
        $nextJob = ScheduledJob::upcoming(1440) // Next 24 hours
            ->orderBy('next_run_at')
            ->first();

        // Get recent failures (last 24 hours)
        $recentFailures = ScheduledJob::where('last_exit_code', '!=', 0)
            ->whereNotNull('last_exit_code')
            ->where('last_run_at', '>=', now()->subDay())
            ->count();

        return [
            'total_jobs' => $totalJobs,
            'enabled_jobs' => $enabledJobs,
            'disabled_jobs' => $disabledJobs,
            'overdue_jobs' => $overdueJobs,
            'failed_jobs' => $failedJobs,
            'recent_failures' => $recentFailures,
            'overall_success_rate' => $overallSuccessRate,
            'total_runs' => $totalRuns,
            'next_job' => $nextJob ? [
                'name' => $nextJob->name,
                'next_run_at' => $nextJob->next_run_at,
                'formatted_next_run' => $nextJob->formatted_next_run,
            ] : null,
        ];
    }

    /**
     * Get jobs with pagination.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedJobs(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = ScheduledJob::with(['creator', 'updater']);

        // Apply filters (same as getAllJobs)
        if (isset($filters['is_enabled'])) {
            $query->where('is_enabled', $filters['is_enabled']);
        }

        if (isset($filters['status'])) {
            switch ($filters['status']) {
                case 'overdue':
                    $query->overdue();
                    break;
                case 'active':
                    $query->enabled()->where(function ($q) {
                        $q->whereNull('last_exit_code')->orWhere('last_exit_code', 0);
                    });
                    break;
                case 'failed':
                    $query->where('last_exit_code', '!=', 0)->whereNotNull('last_exit_code');
                    break;
            }
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('command', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort order
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Calculate next run time for all enabled jobs.
     *
     * @return void
     */
    public function recalculateNextRuns(): void
    {
        $jobs = ScheduledJob::enabled()->get();

        foreach ($jobs as $job) {
            if ($job->cron_expression) {
                $job->next_run_at = $job->calculateNextRun();
                $job->save();
            }
        }
    }
}
