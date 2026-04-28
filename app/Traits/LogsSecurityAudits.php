<?php

namespace App\Traits;

use App\Models\SecurityAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsSecurityAudits
{
    /**
     * Log a security audit event.
     *
     * @param string $eventType
     * @param string $severity
     * @param array $details
     * @param int|null $userId
     * @return void
     */
    protected function logAudit(
        string $eventType,
        string $severity = 'info',
        array $details = [],
        ?int $userId = null
    ): void {
        try {
            SecurityAuditLog::create([
                'user_id' => $userId ?? Auth::id(),
                'event_type' => $eventType,
                'severity' => $severity,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'details' => $details,
                'description' => ucwords(str_replace('_', ' ', $eventType)),
            ]);
        } catch (\Exception $e) {
            // Fail silently to not interrupt the main flow
            \Log::error('Failed to create security audit log', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log cron job created event.
     *
     * @param array $jobData
     * @return void
     */
    protected function logCronJobCreated(array $jobData): void
    {
        $this->logAudit(
            'cron_job_created',
            'info',
            [
                'job_name' => $jobData['name'] ?? null,
                'command' => $jobData['command'] ?? null,
                'cron_expression' => $jobData['cron_expression'] ?? null,
                'is_enabled' => $jobData['is_enabled'] ?? null,
            ]
        );
    }

    /**
     * Log cron job updated event.
     *
     * @param int $jobId
     * @param array $oldData
     * @param array $newData
     * @return void
     */
    protected function logCronJobUpdated(int $jobId, array $oldData, array $newData): void
    {
        $changes = [];
        foreach ($newData as $key => $value) {
            if (isset($oldData[$key]) && $oldData[$key] !== $value) {
                $changes[$key] = [
                    'old' => $oldData[$key],
                    'new' => $value,
                ];
            }
        }

        $this->logAudit(
            'cron_job_updated',
            'info',
            [
                'job_id' => $jobId,
                'job_name' => $newData['name'] ?? $oldData['name'] ?? null,
                'changes' => $changes,
            ]
        );
    }

    /**
     * Log cron job executed event.
     *
     * @param int $jobId
     * @param string $jobName
     * @param int $exitCode
     * @param bool $success
     * @return void
     */
    protected function logCronJobExecuted(int $jobId, string $jobName, int $exitCode, bool $success): void
    {
        $this->logAudit(
            'cron_job_executed',
            $success ? 'info' : 'warning',
            [
                'job_id' => $jobId,
                'job_name' => $jobName,
                'exit_code' => $exitCode,
                'success' => $success,
                'executed_manually' => true,
            ]
        );
    }

    /**
     * Log cron job deleted event.
     *
     * @param int $jobId
     * @param string $jobName
     * @return void
     */
    protected function logCronJobDeleted(int $jobId, string $jobName): void
    {
        $this->logAudit(
            'cron_job_deleted',
            'warning',
            [
                'job_id' => $jobId,
                'job_name' => $jobName,
            ]
        );
    }

    /**
     * Log cron job toggled event.
     *
     * @param int $jobId
     * @param string $jobName
     * @param bool $newStatus
     * @return void
     */
    protected function logCronJobToggled(int $jobId, string $jobName, bool $newStatus): void
    {
        $this->logAudit(
            'cron_job_toggled',
            'info',
            [
                'job_id' => $jobId,
                'job_name' => $jobName,
                'enabled' => $newStatus,
                'action' => $newStatus ? 'enabled' : 'disabled',
            ]
        );
    }

    /**
     * Log cron jobs synced event.
     *
     * @param array $syncResult
     * @return void
     */
    protected function logCronJobsSynced(array $syncResult): void
    {
        $this->logAudit(
            'cron_jobs_synced',
            'info',
            [
                'added' => $syncResult['added'] ?? 0,
                'updated' => $syncResult['updated'] ?? 0,
                'skipped' => $syncResult['skipped'] ?? 0,
                'total_discovered' => $syncResult['total'] ?? 0,
            ]
        );
    }

    /**
     * Log a security audit event with additional context.
     * 
     * This is a more flexible version that accepts description and module.
     *
     * @param string $eventType
     * @param string $description
     * @param string $severity
     * @param string|null $module
     * @param array $metadata
     * @param int|null $userId
     * @return void
     */
    protected function auditLog(
        string $eventType,
        string $description,
        string $severity = 'info',
        ?string $module = null,
        array $metadata = [],
        ?int $userId = null
    ): void {
        $details = array_merge($metadata, [
            'description' => $description,
        ]);

        if ($module) {
            $details['module'] = $module;
        }

        $this->logAudit($eventType, $severity, $details, $userId);
    }
}
