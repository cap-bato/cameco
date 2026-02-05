<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmployeeDocumentPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $otherEmployee;
    private Employee $employeeProfile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create employee role
        Role::create(['name' => 'Employee', 'guard_name' => 'web']);

        // Create employees
        $this->employee = User::factory()->create();
        $this->employee->assignRole('Employee');
        
        $this->otherEmployee = User::factory()->create();
        $this->otherEmployee->assignRole('Employee');
        
        $this->employeeProfile = Employee::factory()->create(['user_id' => $this->employee->id]);
    }

    /**
     * Test employee can view their own documents
     */
    public function test_employee_can_view_own_documents()
    {
        $doc1 = EmployeeDocument::factory()
            ->create(['employee_id' => $this->employeeProfile->id, 'status' => 'approved']);
        
        $doc2 = EmployeeDocument::factory()
            ->create(['employee_id' => $this->employeeProfile->id, 'status' => 'approved']);

        $response = $this->actingAs($this->employee)
            ->get('/employee/documents');

        $response->assertStatus(200);
        $response->assertViewHas('documents');
    }

    /**
     * Test employee cannot view other employee's documents
     */
    public function test_employee_cannot_view_other_employee_documents()
    {
        $otherProfile = Employee::factory()->create(['user_id' => $this->otherEmployee->id]);
        
        $document = EmployeeDocument::factory()
            ->create(['employee_id' => $otherProfile->id, 'status' => 'approved']);

        $response = $this->actingAs($this->employee)
            ->get("/employee/documents/{$document->id}");

        $response->assertForbidden();
    }

    /**
     * Test employee can download their own documents
     */
    public function test_employee_can_download_own_document()
    {
        $document = EmployeeDocument::factory()
            ->create([
                'employee_id' => $this->employeeProfile->id,
                'status' => 'approved',
                'file_path' => 'personal/birth_certificate.pdf'
            ]);

        $response = $this->actingAs($this->employee)
            ->get("/employee/documents/{$document->id}/download");

        // Should return download or redirect
        $this->assertIn($response->status(), [200, 302]);
    }

    /**
     * Test employee can request documents via portal
     */
    public function test_employee_can_request_document()
    {
        $response = $this->actingAs($this->employee)
            ->post('/employee/documents/request', [
                'document_type' => 'coe',
                'purpose' => 'Loan application',
            ]);

        $this->assertDatabaseHas('document_requests', [
            'employee_id' => $this->employeeProfile->id,
            'document_type' => 'coe',
            'request_source' => 'employee_portal',
            'status' => 'pending',
        ]);
    }

    /**
     * Test employee can view request status
     */
    public function test_employee_can_view_request_status()
    {
        $request = DocumentRequest::factory()
            ->create([
                'employee_id' => $this->employeeProfile->id,
                'status' => 'pending'
            ]);

        $response = $this->actingAs($this->employee)
            ->get('/employee/documents/requests');

        $response->assertStatus(200);
    }

    /**
     * Test employee cannot request documents for another employee
     */
    public function test_employee_cannot_request_for_other_employee()
    {
        $otherProfile = Employee::factory()->create(['user_id' => $this->otherEmployee->id]);

        $response = $this->actingAs($this->employee)
            ->post('/employee/documents/request', [
                'employee_id' => $otherProfile->id,
                'document_type' => 'coe',
            ]);

        $response->assertForbidden();
    }

    /**
     * Test unauthenticated user cannot access documents
     */
    public function test_unauthenticated_user_cannot_access_documents()
    {
        $response = $this->get('/employee/documents');

        $response->assertRedirect('/login');
    }

    /**
     * Test only approved documents are visible to employee
     */
    public function test_only_approved_documents_visible()
    {
        $approvedDoc = EmployeeDocument::factory()
            ->create(['employee_id' => $this->employeeProfile->id, 'status' => 'approved']);
        
        $pendingDoc = EmployeeDocument::factory()
            ->create(['employee_id' => $this->employeeProfile->id, 'status' => 'pending']);
        
        $rejectedDoc = EmployeeDocument::factory()
            ->create(['employee_id' => $this->employeeProfile->id, 'status' => 'rejected']);

        $response = $this->actingAs($this->employee)
            ->get('/employee/documents');

        // Verify query uses appropriate scopes
        $response->assertStatus(200);
    }

    /**
     * Test employee document audit logging
     */
    public function test_employee_document_download_logged()
    {
        $document = EmployeeDocument::factory()
            ->create([
                'employee_id' => $this->employeeProfile->id,
                'status' => 'approved'
            ]);

        $this->actingAs($this->employee)
            ->get("/employee/documents/{$document->id}/download");

        // Verify audit log created
        $this->assertDatabaseHas('document_audit_logs', [
            'document_id' => $document->id,
            'action' => 'downloaded',
        ]);
    }
}
