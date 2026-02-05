<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use App\Models\EmployeeDocument;
use App\Models\Employee;
use App\Models\User;
use App\Services\HR\DocumentExpiryReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendDocumentExpiryRemindersTest extends TestCase
{
    use RefreshDatabase;

    private DocumentExpiryReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DocumentExpiryReminderService::class);
        Mail::fake();
    }

    /**
     * Test document expiry reminder command
     */
    public function test_send_document_expiry_reminders_command()
    {
        // Create documents expiring within threshold
        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'approved',
                'reminder_sent_at' => null
            ]);

        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(25),
                'status' => 'approved',
                'reminder_sent_at' => null
            ]);

        $this->artisan('documents:send-expiry-reminders')
            ->expectsOutput('Expiry reminders sent successfully')
            ->assertExitCode(0);
    }

    /**
     * Test command with dry-run option
     */
    public function test_send_expiry_reminders_dry_run()
    {
        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'approved'
            ]);

        $this->artisan('documents:send-expiry-reminders --dry-run')
            ->expectsOutput('DRY RUN')
            ->assertExitCode(0);
    }

    /**
     * Test command with custom days threshold
     */
    public function test_send_expiry_reminders_custom_days()
    {
        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(15),
                'status' => 'approved'
            ]);

        $this->artisan('documents:send-expiry-reminders --days=10')
            ->assertExitCode(0);
    }

    /**
     * Test expiry reminder service finds expiring documents
     */
    public function test_check_expiring_documents()
    {
        // Expiring within threshold
        $expiring = EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'approved'
            ]);

        // Not expiring within threshold
        $notExpiring = EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(35),
                'status' => 'approved'
            ]);

        $documents = $this->service->checkExpiringDocuments(30);

        $this->assertTrue($documents->contains($expiring));
        $this->assertFalse($documents->contains($notExpiring));
    }

    /**
     * Test expiry reminder service respects cooldown
     */
    public function test_expiry_reminder_cooldown()
    {
        $document = EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'approved',
                'reminder_sent_at' => Carbon::now()->subDays(2)
            ]);

        $documents = $this->service->checkExpiringDocuments();

        // Should be excluded due to 7-day cooldown
        $this->assertFalse($documents->contains($document));
    }

    /**
     * Test expiry reminder sends notifications
     */
    public function test_send_reminder_notifications()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $document = EmployeeDocument::factory()
            ->create([
                'employee_id' => $employee->id,
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'approved',
                'reminder_sent_at' => null
            ]);

        $documents = collect([$document]);
        $count = $this->service->sendReminderNotifications($documents);

        $this->assertGreaterThan(0, $count);

        // Verify reminder_sent_at was updated
        $this->refresh($document);
        $this->assertNotNull($document->reminder_sent_at);
    }

    /**
     * Test expiry report generation
     */
    public function test_generate_expiry_report()
    {
        // Create documents in different expiry windows
        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->subDay(),
                'status' => 'approved'
            ]);

        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'approved'
            ]);

        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(20),
                'status' => 'approved'
            ]);

        $report = $this->service->generateExpiryReport();

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('by_category', $report);
        $this->assertArrayHasKey('critical_stats', $report);
    }

    /**
     * Test critical statistics calculation
     */
    public function test_critical_stats_calculation()
    {
        // Create expired document
        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->subDay(),
                'status' => 'approved'
            ]);

        // Create document expiring within 7 days
        EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(3),
                'status' => 'approved'
            ]);

        $report = $this->service->generateExpiryReport();

        $this->assertGreaterThan(0, $report['critical_stats']['expired_count']);
        $this->assertGreaterThan(0, $report['critical_stats']['severe_count']);
    }

    /**
     * Test audit logging of reminders
     */
    public function test_audit_logging_on_reminder_send()
    {
        $document = EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'approved'
            ]);

        $documents = collect([$document]);
        $this->service->sendReminderNotifications($documents);

        // Verify audit log entry
        $this->assertDatabaseHas('document_audit_logs', [
            'document_id' => $document->id,
            'action' => 'reminder_sent',
        ]);
    }

    /**
     * Test only active and approved documents are included
     */
    public function test_only_active_approved_documents()
    {
        $approved = EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'approved'
            ]);

        $pending = EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'pending'
            ]);

        $rejected = EmployeeDocument::factory()
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'rejected'
            ]);

        $documents = $this->service->checkExpiringDocuments();

        $this->assertTrue($documents->contains($approved));
        $this->assertFalse($documents->contains($pending));
        $this->assertFalse($documents->contains($rejected));
    }
}
