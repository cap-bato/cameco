<?php

namespace App\Console\Commands;

use App\Services\HR\DocumentExpiryReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDocumentExpiryReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:send-expiry-reminders
                            {--dry-run : Run without sending actual emails}
                            {--days=30 : Number of days to check for expiring documents}
                            {--verbose : Show detailed output}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Send email reminders for documents expiring soon (scheduled: Daily at 8:00 AM)';

    /**
     * The document expiry reminder service
     *
     * @var DocumentExpiryReminderService
     */
    protected DocumentExpiryReminderService $reminderService;

    /**
     * Create a new command instance.
     *
     * @param DocumentExpiryReminderService $reminderService
     */
    public function __construct(DocumentExpiryReminderService $reminderService)
    {
        parent::__construct();
        $this->reminderService = $reminderService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $daysThreshold = (int) $this->option('days');
        $isVerbose = $this->option('verbose');

        $this->info('🔍 Starting Document Expiry Reminder Command');
        if ($isDryRun) {
            $this->warn('⚠️  Running in DRY-RUN mode (no emails will be sent)');
        }

        try {
            // Get expiring documents
            $this->line('Checking for documents expiring within ' . $daysThreshold . ' days...');
            $expiringDocuments = $this->reminderService->checkExpiringDocuments($daysThreshold);

            if ($expiringDocuments->isEmpty()) {
                $this->info('✓ No documents expiring soon. All documents are up to date.');
                Log::info('Document expiry reminder check completed: No expiring documents found');
                return self::SUCCESS;
            }

            $this->line("Found {$expiringDocuments->count()} expiring documents");

            if ($isVerbose) {
                $this->displayDocumentDetails($expiringDocuments);
            }

            // Send reminders (unless dry-run)
            if ($isDryRun) {
                $this->displayDryRunSummary($expiringDocuments);
                $this->info('✓ Dry-run completed. No emails were sent.');
                return self::SUCCESS;
            }

            // Send actual reminders
            $this->line('Sending reminder emails...');
            $remindersSent = $this->reminderService->sendReminderNotifications($expiringDocuments, auth()->user() ?? null);

            // Display results
            $this->displayResults($expiringDocuments, $remindersSent);

            Log::info("Document expiry reminders sent successfully: {$remindersSent} documents");
            $this->info('✓ Command completed successfully');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error sending expiry reminders: ' . $e->getMessage());
            Log::error('Error in SendDocumentExpiryReminders command: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Display detailed information about expiring documents
     *
     * @param \Illuminate\Support\Collection $documents
     * @return void
     */
    protected function displayDocumentDetails($documents): void
    {
        $this->newLine();
        $this->line('📋 Expiring Documents Detail:');
        $this->line(str_repeat('-', 100));

        $headers = ['Employee', 'Doc Type', 'Category', 'Expires', 'Days Left', 'Status'];
        $rows = [];

        foreach ($documents as $doc) {
            $employee = $doc->employee;
            $employeeName = $employee->profile?->full_name ?? $employee->user?->name ?? 'Unknown';
            $daysLeft = $doc->days_until_expiry;

            $statusBadge = match (true) {
                $daysLeft <= 0 => '❌ EXPIRED',
                $daysLeft <= 7 => '🔴 SEVERE',
                $daysLeft <= 14 => '🟠 MODERATE',
                default => '🟡 WARNING',
            };

            $rows[] = [
                substr($employeeName, 0, 20),
                substr($doc->document_type, 0, 25),
                substr($doc->document_category, 0, 15),
                $doc->expires_at?->format('M d, Y') ?? 'N/A',
                $daysLeft,
                $statusBadge,
            ];
        }

        $this->table($headers, $rows);
        $this->line(str_repeat('-', 100));
    }

    /**
     * Display summary for dry-run mode
     *
     * @param \Illuminate\Support\Collection $documents
     * @return void
     */
    protected function displayDryRunSummary($documents): void
    {
        $this->newLine();
        $this->line('📊 Dry-Run Summary (No Emails Sent):');
        $this->line(str_repeat('-', 60));

        // Categorize by severity
        $severe = $documents->filter(fn($doc) => $doc->days_until_expiry <= 7);
        $moderate = $documents->filter(fn($doc) => 
            $doc->days_until_expiry > 7 && $doc->days_until_expiry <= 14
        );
        $warning = $documents->filter(fn($doc) => 
            $doc->days_until_expiry > 14 && $doc->days_until_expiry <= 30
        );

        $this->line("🔴 <comment>Severe (≤7 days)</comment>: {$severe->count()} documents");
        $this->line("🟠 <comment>Moderate (8-14 days)</comment>: {$moderate->count()} documents");
        $this->line("🟡 <comment>Warning (15-30 days)</comment>: {$warning->count()} documents");
        $this->line("─────────────────────────────────");
        $this->line("Total would notify: {$documents->count()} documents");

        // Unique employees affected
        $uniqueEmployees = $documents->pluck('employee_id')->unique()->count();
        $this->line("Employees affected: {$uniqueEmployees}");

        $this->line(str_repeat('-', 60));
    }

    /**
     * Display final results
     *
     * @param \Illuminate\Support\Collection $documents
     * @param int $remindersSent
     * @return void
     */
    protected function displayResults($documents, int $remindersSent): void
    {
        $this->newLine();
        $this->info('✅ Results:');
        $this->line(str_repeat('-', 60));

        // Categorize by severity
        $severe = $documents->filter(fn($doc) => $doc->days_until_expiry <= 7);
        $moderate = $documents->filter(fn($doc) => 
            $doc->days_until_expiry > 7 && $doc->days_until_expiry <= 14
        );
        $warning = $documents->filter(fn($doc) => 
            $doc->days_until_expiry > 14 && $doc->days_until_expiry <= 30
        );

        $this->line("🔴 <error>Severe (≤7 days)</error>: <comment>{$severe->count()}</comment> documents");
        $this->line("🟠 <fg=yellow>Moderate (8-14 days)</>: <comment>{$moderate->count()}</comment> documents");
        $this->line("🟡 <fg=yellow>Warning (15-30 days)</>: <comment>{$warning->count()}</comment> documents");
        $this->line("─────────────────────────────────");
        $this->line("✉️  Reminders sent: <fg=green>{$remindersSent}</>");

        // Unique employees affected
        $uniqueEmployees = $documents->pluck('employee_id')->unique()->count();
        $this->line("👥 Employees notified: <comment>{$uniqueEmployees}</comment>");

        $this->line(str_repeat('-', 60));

        // Display command to generate report
        $this->info('💡 Tip: Run <fg=cyan>php artisan documents:expiry-report</> to see dashboard statistics');
    }
}
