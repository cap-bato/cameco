<?php

namespace App\Jobs\Timekeeping;

use App\Services\Timekeeping\LedgerPollingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\LedgerProcessingFailedNotification;
use Exception;

/**
 * ProcessRfidLedgerJob
 * 
 * Scheduled job that polls the RFID ledger for new events and processes them
 * into attendance events. This job runs every 1 minute via Laravel Scheduler.
 * 
 * Phase 6, Task 6.1: Scheduled Jobs & Real-Time Updates
 * 
 * Responsibilities:
 * - Task 6.1.1: Call LedgerPollingService to process new events
 * - Task 6.1.2: Run every 1 minute via scheduler (configured in routes/console.php)
 * - Task 6.1.3: Implement retry logic and failure notifications
 * 
 * @package App\Jobs\Timekeeping
 */
class ProcessRfidLedgerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * Task 6.1.3: Retry logic
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Task 6.1.3: Exponential backoff
     *
     * @var array
     */
    public $backoff = [60, 120, 300]; // 1 min, 2 min, 5 min

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Job is dispatched without parameters (runs globally)
    }

    /**
     * Execute the job.
     * 
     * Task 6.1.1: Implement handle() method calling LedgerPollingService
     * 
     * This method:
     * 1. Polls new events from rfid_ledger
     * 2. Validates hash chains
     * 3. Deduplicates events
     * 4. Creates AttendanceEvent records
     * 5. Marks ledger entries as processed
     *
     * @return void
     * @throws Exception When processing fails
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        Log::info('[ProcessRfidLedgerJob] Starting ledger polling cycle');

        try {
            // Initialize polling service
            $pollingService = new LedgerPollingService();

            // Task 6.1.1: Call LedgerPollingService to process complete pipeline
            $result = $pollingService->processLedgerEventsComplete();

            // Calculate processing time
            $processingTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

            // Log success with metrics
            Log::info('[ProcessRfidLedgerJob] Ledger polling completed successfully', [
                'events_polled' => $result['polled_count'],
                'events_deduplicated' => $result['deduplication_stats']['duplicate_count'],
                'events_created' => $result['processing_stats']['events_created'],
                'processing_time_ms' => $processingTime,
                'hash_chain_valid' => $result['hash_chain_validation']['is_valid'],
                'sequence_gaps' => count($result['hash_chain_validation']['gaps'])
            ]);

            // Alert on hash chain validation failures (Task 6.1.3: Failure notifications)
            if (!$result['hash_chain_validation']['is_valid']) {
                $this->handleHashChainFailure($result['hash_chain_validation']);
            }

            // Alert on sequence gaps (Task 6.1.3: Failure notifications)
            if (count($result['hash_chain_validation']['gaps']) > 0) {
                $this->handleSequenceGaps($result['hash_chain_validation']['gaps']);
            }

        } catch (Exception $e) {
            // Task 6.1.3: Failure handling and notifications
            Log::error('[ProcessRfidLedgerJob] Ledger polling failed', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries
            ]);

            // If this is the final attempt, send notification
            if ($this->attempts() >= $this->tries) {
                $this->notifyAdministrators($e);
            }

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle hash chain validation failures.
     * Task 6.1.3: Critical alert for data integrity issues
     *
     * @param array $validationResult
     * @return void
     */
    private function handleHashChainFailure(array $validationResult): void
    {
        Log::critical('[ProcessRfidLedgerJob] Hash chain validation FAILED - Possible tampering detected', [
            'failed_sequences' => $validationResult['failed_sequences'],
            'total_failures' => count($validationResult['failed_sequences'])
        ]);

        // Send critical notification to HR Manager and System Admin
        $this->notifyAdministrators(
            new Exception('Hash chain validation failed - Possible ledger tampering'),
            'critical'
        );
    }

    /**
     * Handle sequence gaps in the ledger.
     * Task 6.1.3: Warning alert for offline device catch-up needed
     *
     * @param array $gaps
     * @return void
     */
    private function handleSequenceGaps(array $gaps): void
    {
        Log::warning('[ProcessRfidLedgerJob] Sequence gaps detected in ledger', [
            'gaps' => $gaps,
            'gap_count' => count($gaps)
        ]);

        // If gaps are significant (more than 10), notify administrators
        if (count($gaps) > 10) {
            $this->notifyAdministrators(
                new Exception('Large sequence gaps detected (' . count($gaps) . ' gaps) - Possible offline device backlog'),
                'warning'
            );
        }
    }

    /**
     * Notify system administrators about job failures.
     * Task 6.1.3: Send failure notifications to HR Manager role
     *
     * @param Exception $exception
     * @param string $severity
     * @return void
     */
    private function notifyAdministrators(Exception $exception, string $severity = 'error'): void
    {
        try {
            // Get HR Manager users for notification
            $hrManagers = \App\Models\User::role('HR Manager')->get();

            if ($hrManagers->isEmpty()) {
                Log::warning('[ProcessRfidLedgerJob] No HR Managers found to notify');
                return;
            }

            // Send notification to all HR Managers
            Notification::send($hrManagers, new LedgerProcessingFailedNotification(
                $exception->getMessage(),
                $severity,
                [
                    'job' => self::class,
                    'attempts' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'timestamp' => now()->toDateTimeString()
                ]
            ));

            Log::info('[ProcessRfidLedgerJob] Failure notification sent to HR Managers', [
                'recipient_count' => $hrManagers->count(),
                'severity' => $severity
            ]);

        } catch (Exception $e) {
            // Fail silently on notification errors (don't break the retry chain)
            Log::error('[ProcessRfidLedgerJob] Failed to send administrator notification', [
                'notification_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     * Task 6.1.3: Final failure handler after all retries exhausted
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        Log::critical('[ProcessRfidLedgerJob] Job failed after all retry attempts', [
            'error_message' => $exception->getMessage(),
            'total_attempts' => $this->tries,
            'job_id' => $this->job?->getJobId()
        ]);

        // Send final failure notification
        $this->notifyAdministrators($exception, 'critical');
    }
}
