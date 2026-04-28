<?php

/**
 * NOTE: Refactor / Observability Integration Plan
 *
 * TODO: Implement retry and error recovery for cron executions.
 * Current limitation: failed jobs are only logged — no retry, backoff, or visibility.
 *
 * Purpose: interim solution for on-prem HRIS deployments lacking centralized observability.
 * Once a platform like SigNoz, Prometheus+Grafana, or Netdata is active,
 * most of this logic should be deprecated.
 *
 * Refactor plan:
 * 1. Replace manual metrics (run_count, success_count, failure_count, etc.) with OTEL-based instrumentation. 
 *  --Signoz would be my bet. DX friendly 
 * and not a headache inducer with a frankenstein of an observability. 
 *   --yeah, Grafafa, Jaeger and Prometheus 
 * 2. Stream execution traces, logs, and runtime metrics to the observability backend.
 * 3. **Retain** local job history tables for UI management requires persistence.
 * 4. **Retain** configuration and enable/disable controls.
 *
 * End state:
 * - Retry and recovery handled by queue/worker orchestration.
 * - Metrics and visibility provided by OTEL → SigNoz/Grafana.
 * - This service reduced to thin orchestration glue.
 */


namespace App\Services\System;

use App\Models\ScheduledJob;
use App\Repositories\Contracts\System\CronRepositoryInterface;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SystemCronService
{
    public function __construct(
        protected CronRepositoryInterface $repository
    ) {}

    /**
     * Discover all registered scheduled commands from Laravel's Schedule.
     *
     * @return array
     */
    public function discoverScheduledCommands(): array
    {
        try {
            $schedule = app(Schedule::class);
            $events = $schedule->events();
            
            $discoveredCommands = [];

            foreach ($events as $event) {
                // Extract command from event
                $command = $this->extractCommand($event);
                
                if ($command) {
                    $discoveredCommands[] = [
                        'command' => $command,
                        'expression' => $event->expression,
                        'description' => $event->description ?? "Scheduled: {$command}",
                        'timezone' => $event->timezone ?? config('app.timezone'),
                    ];
                }
            }

            return $discoveredCommands;
        } catch (\Exception $e) {
            Log::error('Failed to discover scheduled commands', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Extract command signature from schedule event.
     *
     * @param mixed $event
     * @return string|null
     */
    protected function extractCommand($event): ?string
    {
        // Try to get command from event
        if (property_exists($event, 'command')) {
            $command = $event->command;
            
            // Remove PHP binary and artisan path, keep just the command
            if (is_string($command)) {
                // Match pattern like: "'path/to/php' 'artisan' command:name"
                if (preg_match("/artisan'\s+([^\s']+)/", $command, $matches)) {
                    return $matches[1];
                }
                
                // Match pattern like: "php artisan command:name"
                if (preg_match("/php\s+artisan\s+([^\s]+)/", $command, $matches)) {
                    return $matches[1];
                }
                
                // If it's already a clean command name
                if (strpos($command, 'artisan') === false && strpos($command, 'php') === false) {
                    return $command;
                }
            }
        }

        return null;
    }

    /**
     * Sync discovered commands with database.
     * Compares Laravel Schedule with DB, adds missing jobs.
     *
     * @param int|null $userId User ID for created_by field
     * @return array Summary of sync operation
     */
    public function syncJobs(?int $userId = null): array
    {
        $discovered = $this->discoverScheduledCommands();
        $added = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($discovered as $commandData) {
            $existing = $this->repository->findByCommand($commandData['command']);

            if (!$existing) {
                // Create new job
                $this->repository->createJob([
                    'name' => $this->generateJobName($commandData['command']),
                    'description' => $commandData['description'],
                    'command' => $commandData['command'],
                    'cron_expression' => $commandData['expression'],
                    'is_enabled' => true,
                    'created_by' => $userId,
                ]);
                $added++;
            } else {
                // Optionally update cron expression if changed
                if ($existing->cron_expression !== $commandData['expression']) {
                    $this->repository->updateJob($existing->id, [
                        'cron_expression' => $commandData['expression'],
                        'updated_by' => $userId,
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        }

        return [
            'discovered' => count($discovered),
            'added' => $added,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Generate a human-readable job name from command signature.
     *
     * @param string $command
     * @return string
     */
    protected function generateJobName(string $command): string
    {
        // Convert "command:name" to "Command Name"
        $name = str_replace([':', '-', '_'], ' ', $command);
        return ucwords($name);
    }

    /**
     * Validate a cron expression syntax.
     *
     * @param string $expression
     * @return bool
     */
    public function validateCronExpression(string $expression): bool
    {
        try {
            return CronExpression::isValidExpression($expression);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Parse and calculate next run time from cron expression.
     *
     * @param string $expression
     * @param \DateTime|null $currentTime
     * @return Carbon|null
     */
    public function parseNextRunTime(string $expression, ?\DateTime $currentTime = null): ?Carbon
    {
        try {
            $cron = new CronExpression($expression);
            $nextRun = $cron->getNextRunDate($currentTime ?? new \DateTime());
            
            return Carbon::instance($nextRun);
        } catch (\Exception $e) {
            Log::error('Failed to parse cron expression', [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get multiple next run times for a cron expression.
     *
     * @param string $expression
     * @param int $count Number of future runs to calculate
     * @return array
     */
    public function getNextRunTimes(string $expression, int $count = 5): array
    {
        try {
            $cron = new CronExpression($expression);
            $runs = [];
            $currentTime = new \DateTime();

            for ($i = 0; $i < $count; $i++) {
                $nextRun = $cron->getNextRunDate($currentTime);
                $runs[] = Carbon::instance($nextRun);
                $currentTime = $nextRun;
            }

            return $runs;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Manually execute a scheduled job.
     *
     * @param int $id Job ID
     * @param int|null $userId User triggering the execution
     * @return array Execution result
     */
    public function executeJob(int $id, ?int $userId = null): array
    {
        $job = $this->repository->findById($id);

        if (!$job) {
            return [
                'success' => false,
                'message' => 'Job not found',
                'exit_code' => -1,
                'output' => '',
            ];
        }

        try {
            // Log execution attempt
            Log::info('Manual cron job execution started', [
                'job_id' => $job->id,
                'job_name' => $job->name,
                'command' => $job->command,
                'user_id' => $userId,
            ]);

            // Execute the Artisan command
            $exitCode = Artisan::call($job->command);
            $output = Artisan::output();

            // Record execution in database
            $this->repository->recordExecution($job->id, $exitCode, $output);

            // Log execution result
            Log::info('Manual cron job execution completed', [
                'job_id' => $job->id,
                'exit_code' => $exitCode,
                'user_id' => $userId,
            ]);

            return [
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Job executed successfully' : 'Job failed',
                'exit_code' => $exitCode,
                'output' => $output,
                'job' => $job->fresh(),
            ];
        } catch (\Exception $e) {
            Log::error('Manual cron job execution failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            // Record failure
            $this->repository->recordExecution($job->id, 1, "Error: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Execution error: ' . $e->getMessage(),
                'exit_code' => 1,
                'output' => $e->getMessage(),
            ];
        }
    }

    /**
     * Record a scheduler-triggered execution by command signature.
     *
     * This keeps the Superadmin Cron UI in sync with jobs that run via
     * Laravel's scheduler, not only jobs manually triggered from the UI.
     */
    public function recordScheduledExecutionByCommand(string $command, int $exitCode, string $output = ''): void
    {
        $job = $this->repository->findByCommand($command);

        if (! $job) {
            Log::warning('Skipped scheduled job execution recording because command was not found in scheduled_jobs', [
                'command' => $command,
                'exit_code' => $exitCode,
            ]);

            return;
        }

        $this->repository->recordExecution($job->id, $exitCode, $output);
    }

    /**
     * Get aggregated job metrics.
     *
     * @return array
     */
    public function getJobMetrics(): array
    {
        return $this->repository->getJobMetrics();
    }

    /**
     * Get detailed metrics for a specific job.
     *
     * @param int $id
     * @return array|null
     */
    public function getJobDetailMetrics(int $id): ?array
    {
        $job = $this->repository->findById($id);

        if (!$job) {
            return null;
        }

        $successRate = $job->success_rate;
        $failureRate = $job->run_count > 0 
            ? round(($job->failure_count / $job->run_count) * 100, 2) 
            : 0;

        // Calculate average time between runs (if we had execution times)
        // For now, estimate based on cron expression
        $estimatedInterval = $this->estimateRunInterval($job->cron_expression);

        return [
            'job_id' => $job->id,
            'name' => $job->name,
            'command' => $job->command,
            'status' => $job->status,
            'is_enabled' => $job->is_enabled,
            'total_runs' => $job->run_count,
            'successful_runs' => $job->success_count,
            'failed_runs' => $job->failure_count,
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
            'last_run_at' => $job->last_run_at?->toIso8601String(),
            'last_exit_code' => $job->last_exit_code,
            'next_run_at' => $job->next_run_at?->toIso8601String(),
            'formatted_next_run' => $job->formatted_next_run,
            'formatted_last_run' => $job->formatted_last_run,
            'cron_expression' => $job->cron_expression,
            'cron_description' => $job->cron_description,
            'estimated_interval' => $estimatedInterval,
            'is_overdue' => $job->isOverdue(),
        ];
    }

    /**
     * Estimate run interval in minutes from cron expression.
     *
     * @param string $expression
     * @return string
     */
    protected function estimateRunInterval(string $expression): string
    {
        // Common patterns
        $patterns = [
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/10 * * * *' => 'Every 10 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '*/30 * * * *' => 'Every 30 minutes',
            '0 * * * *' => 'Every hour',
            '0 */2 * * *' => 'Every 2 hours',
            '0 */4 * * *' => 'Every 4 hours',
            '0 */6 * * *' => 'Every 6 hours',
            '0 */12 * * *' => 'Every 12 hours',
            '0 0 * * *' => 'Daily',
            '0 0 * * 0' => 'Weekly',
            '0 0 1 * *' => 'Monthly',
        ];

        return $patterns[$expression] ?? 'Custom interval';
    }

    /**
     * Get human-readable description for cron expression.
     *
     * @param string $expression
     * @return string
     */
    public function describeCronExpression(string $expression): string
    {
        return $this->estimateRunInterval($expression);
    }

    /**
     * Enable a job.
     *
     * @param int $id
     * @param int|null $userId
     * @return ScheduledJob|null
     */
    public function enableJob(int $id, ?int $userId = null): ?ScheduledJob
    {
        $job = $this->repository->findById($id);

        if (!$job || $job->is_enabled) {
            return $job;
        }

        return $this->repository->updateJob($id, [
            'is_enabled' => true,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Disable a job.
     *
     * @param int $id
     * @param int|null $userId
     * @return ScheduledJob|null
     */
    public function disableJob(int $id, ?int $userId = null): ?ScheduledJob
    {
        $job = $this->repository->findById($id);

        if (!$job || !$job->is_enabled) {
            return $job;
        }

        return $this->repository->updateJob($id, [
            'is_enabled' => false,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Get list of available Artisan commands.
     *
     * @return array
     */
    public function getAvailableCommands(): array
    {
        try {
            $commands = Artisan::all();
            $commandList = [];

            foreach ($commands as $name => $command) {
                // Skip hidden commands
                if ($command->isHidden()) {
                    continue;
                }

                $commandList[] = [
                    'name' => $name,
                    'description' => $command->getDescription(),
                ];
            }

            // Sort by name
            usort($commandList, fn($a, $b) => strcmp($a['name'], $b['name']));

            return $commandList;
        } catch (\Exception $e) {
            Log::error('Failed to get available commands', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get all scheduled jobs with optional filters and pagination.
     */
    public function getAllJobs(int $perPage = 15, array $filters = [])
    {
        return $this->repository->getPaginatedJobs($perPage, $filters);
    }

    /**
     * Find a scheduled job by ID.
     */
    public function findJobById(int $id): ?ScheduledJob
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new scheduled job.
     */
    public function createJob(array $data, ?int $userId = null): ScheduledJob
    {
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        
        return $this->repository->createJob($data);
    }

    /**
     * Update an existing scheduled job.
     */
    public function updateJob(int $id, array $data, ?int $userId = null): ScheduledJob
    {
        $data['updated_by'] = $userId;
        
        return $this->repository->updateJob($id, $data);
    }

    /**
     * Delete a scheduled job (soft delete).
     */
    public function deleteJob(int $id): bool
    {
        return $this->repository->deleteJob($id);
    }

    /**
     * Get execution history for a specific job.
     */
    public function getExecutionHistory(int $id, int $limit = 50): array
    {
        return $this->repository->getExecutionHistory($id, $limit);
    }
}

