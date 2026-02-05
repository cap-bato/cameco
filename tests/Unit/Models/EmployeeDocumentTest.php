<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\DocumentAuditLog;
use App\Models\BulkUploadBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmployeeDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;
    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->employee = Employee::factory()->create();
    }

    /**
     * Test EmployeeDocument model relationships
     */
    public function test_employee_document_belongs_to_employee()
    {
        $document = EmployeeDocument::factory()
            ->create(['employee_id' => $this->employee->id]);

        $this->assertTrue($document->employee->is($this->employee));
    }

    public function test_employee_document_belongs_to_uploaded_by_user()
    {
        $document = EmployeeDocument::factory()
            ->create(['uploaded_by' => $this->user->id]);

        $this->assertTrue($document->uploadedBy->is($this->user));
    }

    public function test_employee_document_belongs_to_approved_by_user()
    {
        $document = EmployeeDocument::factory()
            ->create(['approved_by' => $this->admin->id, 'status' => 'approved']);

        $this->assertTrue($document->approvedBy->is($this->admin));
    }

    public function test_employee_document_has_many_audit_logs()
    {
        $document = EmployeeDocument::factory()->create();
        
        DocumentAuditLog::factory()
            ->count(3)
            ->create(['document_id' => $document->id]);

        $this->assertCount(3, $document->auditLogs);
    }

    /**
     * Test EmployeeDocument scopes
     */
    public function test_active_scope_filters_approved_documents()
    {
        EmployeeDocument::factory()->create(['status' => 'approved']);
        EmployeeDocument::factory()->create(['status' => 'approved']);
        EmployeeDocument::factory()->create(['status' => 'pending']);

        $active = EmployeeDocument::active()->get();
        
        $this->assertCount(2, $active);
        $this->assertTrue($active->every(fn($doc) => $doc->status === 'approved'));
    }

    public function test_pending_scope_filters_pending_documents()
    {
        EmployeeDocument::factory()->create(['status' => 'pending']);
        EmployeeDocument::factory()->create(['status' => 'pending']);
        EmployeeDocument::factory()->create(['status' => 'approved']);

        $pending = EmployeeDocument::pending()->get();
        
        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(fn($doc) => $doc->status === 'pending'));
    }

    public function test_approved_scope()
    {
        EmployeeDocument::factory()->create(['status' => 'approved']);
        EmployeeDocument::factory()->create(['status' => 'rejected']);

        $approved = EmployeeDocument::approved()->get();
        
        $this->assertCount(1, $approved);
        $this->assertEquals('approved', $approved->first()->status);
    }

    public function test_requires_approval_scope()
    {
        EmployeeDocument::factory()->create(['requires_approval' => true]);
        EmployeeDocument::factory()->create(['requires_approval' => true]);
        EmployeeDocument::factory()->create(['requires_approval' => false]);

        $needsApproval = EmployeeDocument::requiresApproval()->get();
        
        $this->assertCount(2, $needsApproval);
    }

    public function test_critical_scope()
    {
        EmployeeDocument::factory()->create(['is_critical' => true]);
        EmployeeDocument::factory()->create(['is_critical' => true]);
        EmployeeDocument::factory()->create(['is_critical' => false]);

        $critical = EmployeeDocument::critical()->get();
        
        $this->assertCount(2, $critical);
    }

    public function test_expired_scope()
    {
        EmployeeDocument::factory()
            ->create(['expires_at' => Carbon::now()->subDay(), 'status' => 'approved']);
        EmployeeDocument::factory()
            ->create(['expires_at' => Carbon::now()->addDay(), 'status' => 'approved']);

        $expired = EmployeeDocument::expired()->get();
        
        $this->assertCount(1, $expired);
    }

    public function test_expiring_within_scope()
    {
        EmployeeDocument::factory()
            ->create(['expires_at' => Carbon::now()->addDays(5), 'status' => 'approved']);
        EmployeeDocument::factory()
            ->create(['expires_at' => Carbon::now()->addDays(15), 'status' => 'approved']);

        $expiring = EmployeeDocument::expiringWithin(7)->get();
        
        $this->assertCount(1, $expiring);
    }

    public function test_for_employee_scope()
    {
        $doc1 = EmployeeDocument::factory()->create(['employee_id' => $this->employee->id]);
        $doc2 = EmployeeDocument::factory()->create(['employee_id' => $this->employee->id]);
        EmployeeDocument::factory()->create();

        $documents = EmployeeDocument::forEmployee($this->employee->id)->get();
        
        $this->assertCount(2, $documents);
        $this->assertTrue($documents->contains($doc1));
        $this->assertTrue($documents->contains($doc2));
    }

    public function test_by_category_scope()
    {
        EmployeeDocument::factory()->create(['document_category' => 'personal']);
        EmployeeDocument::factory()->create(['document_category' => 'personal']);
        EmployeeDocument::factory()->create(['document_category' => 'educational']);

        $personal = EmployeeDocument::byCategory('personal')->get();
        
        $this->assertCount(2, $personal);
    }

    public function test_by_type_scope()
    {
        EmployeeDocument::factory()->create(['document_type' => 'birth_certificate']);
        EmployeeDocument::factory()->create(['document_type' => 'birth_certificate']);
        EmployeeDocument::factory()->create(['document_type' => 'passport']);

        $birthCerts = EmployeeDocument::byType('birth_certificate')->get();
        
        $this->assertCount(2, $birthCerts);
    }

    /**
     * Test EmployeeDocument accessors
     */
    public function test_is_expired_accessor()
    {
        $expiredDoc = EmployeeDocument::factory()
            ->create(['expires_at' => Carbon::now()->subDay()]);
        
        $activeDoc = EmployeeDocument::factory()
            ->create(['expires_at' => Carbon::now()->addDay()]);

        $this->assertTrue($expiredDoc->is_expired);
        $this->assertFalse($activeDoc->is_expired);
    }

    public function test_days_until_expiry_accessor()
    {
        $document = EmployeeDocument::factory()
            ->create(['expires_at' => Carbon::now()->addDays(5)]);

        $this->assertEquals(5, $document->days_until_expiry);
    }

    public function test_file_size_formatted_accessor()
    {
        $document = EmployeeDocument::factory()
            ->create(['file_size' => 1048576]); // 1MB

        $this->assertStringContainsString('MB', $document->file_size_formatted);
    }

    public function test_status_label_accessor()
    {
        $document = EmployeeDocument::factory()
            ->create(['status' => 'pending']);

        $this->assertIsString($document->status_label);
    }

    /**
     * Test EmployeeDocument methods
     */
    public function test_approve_method()
    {
        $document = EmployeeDocument::factory()
            ->create(['status' => 'pending', 'approved_by' => null]);

        $document->approve($this->admin, 'Looks good');

        $this->refresh($document);
        $this->assertEquals('approved', $document->status);
        $this->assertTrue($document->approved_by === $this->admin->id);
        $this->assertNotNull($document->approved_at);
    }

    public function test_reject_method()
    {
        $document = EmployeeDocument::factory()
            ->create(['status' => 'pending']);

        $document->reject($this->admin, 'Invalid format');

        $this->refresh($document);
        $this->assertEquals('rejected', $document->status);
        $this->assertEquals('Invalid format', $document->rejection_reason);
    }

    public function test_mark_reminder_sent_method()
    {
        $document = EmployeeDocument::factory()
            ->create(['reminder_sent_at' => null]);

        $document->markReminderSent($this->user);

        $this->refresh($document);
        $this->assertNotNull($document->reminder_sent_at);
    }

    public function test_auto_approve_method()
    {
        $document = EmployeeDocument::factory()
            ->create(['requires_approval' => false, 'status' => 'pending']);

        $document->autoApprove();

        $this->refresh($document);
        $this->assertEquals('auto_approved', $document->status);
    }
}
