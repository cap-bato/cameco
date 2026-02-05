<?php

namespace Tests\Feature\HR;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\DocumentAuditLog;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Storage;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $hrStaff;
    private User $hrManager;
    private Employee $employee;
    private string $disk = 'employee_documents';

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake($this->disk);

        // Create roles
        Role::create(['name' => 'HR Staff', 'guard_name' => 'web']);
        Role::create(['name' => 'HR Manager', 'guard_name' => 'web']);

        // Create users
        $this->hrStaff = User::factory()->create();
        $this->hrStaff->assignRole('HR Staff');
        
        $this->hrManager = User::factory()->create();
        $this->hrManager->assignRole('HR Manager');
        
        $this->employee = Employee::factory()->create();
    }

    /**
     * Test HR Staff can upload documents
     */
    public function test_hr_staff_can_upload_document()
    {
        $file = UploadedFile::fake()->create('document.pdf', 512);

        $response = $this->actingAs($this->hrStaff)
            ->post('/hr/documents/upload', [
                'employee_id' => $this->employee->id,
                'document_category' => 'personal',
                'document_type' => 'birth_certificate',
                'file' => $file,
                'expires_at' => '2030-12-31',
            ]);

        $this->assertDatabaseHas('employee_documents', [
            'employee_id' => $this->employee->id,
            'document_category' => 'personal',
        ]);
    }

    /**
     * Test HR Manager can approve documents
     */
    public function test_hr_manager_can_approve_document()
    {
        $document = EmployeeDocument::factory()
            ->create(['status' => 'pending', 'requires_approval' => true]);

        $response = $this->actingAs($this->hrManager)
            ->post("/hr/documents/{$document->id}/approve", [
                'notes' => 'Document verified and approved',
            ]);

        $this->refresh($document);
        $this->assertEquals('approved', $document->status);
        $this->assertNotNull($document->approved_at);
    }

    /**
     * Test HR Manager can reject documents
     */
    public function test_hr_manager_can_reject_document()
    {
        $document = EmployeeDocument::factory()
            ->create(['status' => 'pending', 'requires_approval' => true]);

        $response = $this->actingAs($this->hrManager)
            ->post("/hr/documents/{$document->id}/reject", [
                'rejection_reason' => 'Poor image quality',
            ]);

        $this->refresh($document);
        $this->assertEquals('rejected', $document->status);
        $this->assertEquals('Poor image quality', $document->rejection_reason);
    }

    /**
     * Test document download authorization
     */
    public function test_hr_staff_can_download_document()
    {
        $file = UploadedFile::fake()->create('document.pdf', 512);
        
        $document = EmployeeDocument::factory()
            ->create([
                'file_path' => $file->hashName('personal'),
                'status' => 'approved'
            ]);

        $response = $this->actingAs($this->hrStaff)
            ->get("/hr/documents/{$document->id}/download");

        $this->assertNotNull($response);
    }

    /**
     * Test document audit logging
     */
    public function test_document_audit_logging_on_upload()
    {
        $file = UploadedFile::fake()->create('document.pdf', 512);

        $this->actingAs($this->hrStaff)
            ->post('/hr/documents/upload', [
                'employee_id' => $this->employee->id,
                'document_category' => 'personal',
                'document_type' => 'birth_certificate',
                'file' => $file,
            ]);

        $document = EmployeeDocument::latest()->first();

        $this->assertDatabaseHas('document_audit_logs', [
            'document_id' => $document->id,
            'action' => 'uploaded',
            'user_id' => $this->hrStaff->id,
        ]);
    }

    public function test_document_audit_logging_on_approve()
    {
        $document = EmployeeDocument::factory()->create();

        $this->actingAs($this->hrManager)
            ->post("/hr/documents/{$document->id}/approve");

        $this->assertDatabaseHas('document_audit_logs', [
            'document_id' => $document->id,
            'action' => 'approved',
            'user_id' => $this->hrManager->id,
        ]);
    }

    /**
     * Test file validation
     */
    public function test_file_size_validation()
    {
        $largeFile = UploadedFile::fake()->create('large.pdf', 11 * 1024); // 11MB

        $response = $this->actingAs($this->hrStaff)
            ->post('/hr/documents/upload', [
                'employee_id' => $this->employee->id,
                'document_category' => 'personal',
                'file' => $largeFile,
            ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_file_type_validation()
    {
        $invalidFile = UploadedFile::fake()->create('document.exe', 512);

        $response = $this->actingAs($this->hrStaff)
            ->post('/hr/documents/upload', [
                'employee_id' => $this->employee->id,
                'document_category' => 'personal',
                'file' => $invalidFile,
            ]);

        $response->assertSessionHasErrors('file');
    }

    /**
     * Test document expiry tracking
     */
    public function test_document_expiry_tracking()
    {
        $document = EmployeeDocument::factory()
            ->create(['expires_at' => now()->addDays(5)]);

        $this->assertTrue($document->days_until_expiry <= 7);
        $this->assertFalse($document->is_expired);
    }

    /**
     * Test document filtering
     */
    public function test_filter_documents_by_category()
    {
        EmployeeDocument::factory()
            ->count(3)
            ->create([
                'employee_id' => $this->employee->id,
                'document_category' => 'personal'
            ]);
        
        EmployeeDocument::factory()
            ->create([
                'employee_id' => $this->employee->id,
                'document_category' => 'educational'
            ]);

        $response = $this->actingAs($this->hrStaff)
            ->get('/hr/documents?employee_id=' . $this->employee->id . '&category=personal');

        // Verify response contains personal documents
        $this->assertResponseOk($response);
    }

    public function test_filter_documents_by_status()
    {
        EmployeeDocument::factory()
            ->count(3)
            ->create(['status' => 'approved']);
        
        EmployeeDocument::factory()
            ->count(2)
            ->create(['status' => 'pending']);

        $response = $this->actingAs($this->hrStaff)
            ->get('/hr/documents?status=approved');

        $this->assertResponseOk($response);
    }

    /**
     * Test bulk upload processing
     */
    public function test_bulk_upload_processing()
    {
        $csvFile = UploadedFile::fake()->create('documents.csv', 512);

        $response = $this->actingAs($this->hrStaff)
            ->post('/hr/documents/bulk-upload', [
                'csv_file' => $csvFile,
            ]);

        $this->assertDatabaseHas('bulk_upload_batches', [
            'uploaded_by' => $this->hrStaff->id,
        ]);
    }

    /**
     * Test employee request processing
     */
    public function test_employee_document_request_processing()
    {
        $document = EmployeeDocument::factory()->create();

        $response = $this->actingAs($this->hrStaff)
            ->post('/hr/documents/requests/process', [
                'request_id' => 1,
                'document_id' => $document->id,
                'notes' => 'Generated COE',
            ]);

        // Verify request was processed
        $this->assertDatabaseHas('document_requests', [
            'status' => 'processed',
        ]);
    }
}
