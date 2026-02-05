<?php

namespace App\Http\Controllers\HR\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Documents\UploadDocumentRequest;
use App\Http\Requests\HR\Documents\ApproveDocumentRequest;
use App\Http\Requests\HR\Documents\RejectDocumentRequest;
use App\Models\Employee;
use App\Traits\LogsSecurityAudits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EmployeeDocumentController
 *
 * Handles employee-specific document operations via API endpoints.
 * This controller is scoped to a single employee context for use in the
 * Employee Profile → Documents Tab.
 *
 * CONTEXT:
 * ========
 * This controller powers the Documents tab within an employee's profile page
 * (/hr/employees/{id}). When HR staff is reviewing a specific employee's profile,
 * this API provides document CRUD operations for that ONE employee only.
 *
 * DIFFERENCE FROM Documents/EmployeeDocumentController:
 * - Documents/EmployeeDocumentController: Returns Inertia pages for centralized hub
 * - Employee/EmployeeDocumentController: Returns JSON for employee profile tab
 *
 * All methods are filtered by employee_id from route parameter.
 *
 * @author HR Development Team
 * @version 1.0
 */
class EmployeeDocumentController extends Controller
{
    use LogsSecurityAudits;

    /**
     * Get all documents for a specific employee.
     *
     * Used by: Employee Profile → Documents Tab
     * Returns: JSON array of documents with metadata
     *
     * @param int $employeeId Employee ID from route parameter
     * @return JsonResponse
     */
    public function index(int $employeeId): JsonResponse
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR permission
        $this->authorize('view', $employee);

        // Mock data for frontend development
        // In Phase 4, this will query actual database
        $documents = collect([
            [
                'id' => 1,
                'employee_id' => $employeeId,
                'name' => 'Birth Certificate',
                'document_type' => 'Birth Certificate (PSA)',
                'file_name' => 'birth_certificate_psa.pdf',
                'file_path' => '/storage/employee-documents/' . $employeeId . '/personal/2024/birth_certificate_psa.pdf',
                'file_size' => 245000,
                'mime_type' => 'application/pdf',
                'category' => 'personal',
                'status' => 'approved',
                'uploaded_at' => '2024-01-15T10:30:00Z',
                'uploaded_by' => 'HR Staff',
                'uploaded_by_id' => 2,
                'approved_at' => '2024-01-15T14:20:00Z',
                'approved_by' => 'HR Manager',
                'approved_by_id' => 1,
                'expires_at' => null,
                'notes' => 'PSA-authenticated original copy',
            ],
            [
                'id' => 2,
                'employee_id' => $employeeId,
                'name' => 'NBI Clearance',
                'document_type' => 'NBI Clearance',
                'file_name' => 'nbi_clearance_2024.pdf',
                'file_path' => '/storage/employee-documents/' . $employeeId . '/government/2024/nbi_clearance_2024.pdf',
                'file_size' => 180000,
                'mime_type' => 'application/pdf',
                'category' => 'government',
                'status' => 'approved',
                'uploaded_at' => '2024-03-20T09:15:00Z',
                'uploaded_by' => 'HR Staff',
                'uploaded_by_id' => 2,
                'approved_at' => '2024-03-20T11:30:00Z',
                'approved_by' => 'HR Manager',
                'approved_by_id' => 1,
                'expires_at' => '2025-03-20',
                'notes' => 'Valid for employment purposes',
                'days_until_expiry' => 90,
                'is_expiring_soon' => true,
            ],
            [
                'id' => 3,
                'employee_id' => $employeeId,
                'name' => 'Medical Certificate',
                'document_type' => 'Pre-Employment Medical',
                'file_name' => 'medical_cert_2024.pdf',
                'file_path' => '/storage/employee-documents/' . $employeeId . '/medical/2024/medical_cert_2024.pdf',
                'file_size' => 320000,
                'mime_type' => 'application/pdf',
                'category' => 'medical',
                'status' => 'approved',
                'uploaded_at' => '2024-02-10T13:45:00Z',
                'uploaded_by' => 'HR Manager',
                'uploaded_by_id' => 1,
                'approved_at' => '2024-02-10T13:45:00Z',
                'approved_by' => 'HR Manager',
                'approved_by_id' => 1,
                'expires_at' => '2025-02-10',
                'notes' => 'Annual medical checkup',
                'days_until_expiry' => 52,
                'is_expiring_soon' => false,
            ],
            [
                'id' => 4,
                'employee_id' => $employeeId,
                'name' => 'Employment Contract',
                'document_type' => 'Regular Employment Contract',
                'file_name' => 'employment_contract_signed.pdf',
                'file_path' => '/storage/employee-documents/' . $employeeId . '/contracts/2024/employment_contract_signed.pdf',
                'file_size' => 450000,
                'mime_type' => 'application/pdf',
                'category' => 'contracts',
                'status' => 'approved',
                'uploaded_at' => '2024-01-20T16:00:00Z',
                'uploaded_by' => 'HR Manager',
                'uploaded_by_id' => 1,
                'approved_at' => '2024-01-20T16:00:00Z',
                'approved_by' => 'HR Manager',
                'approved_by_id' => 1,
                'expires_at' => null,
                'notes' => 'Signed contract - regular employment',
            ],
            [
                'id' => 5,
                'employee_id' => $employeeId,
                'name' => 'SSS E-1 Form',
                'document_type' => 'SSS Registration',
                'file_name' => 'sss_e1_form.pdf',
                'file_path' => '/storage/employee-documents/' . $employeeId . '/government/2024/sss_e1_form.pdf',
                'file_size' => 150000,
                'mime_type' => 'application/pdf',
                'category' => 'government',
                'status' => 'approved',
                'uploaded_at' => '2024-01-25T11:20:00Z',
                'uploaded_by' => 'HR Staff',
                'uploaded_by_id' => 2,
                'approved_at' => '2024-01-25T14:00:00Z',
                'approved_by' => 'HR Manager',
                'approved_by_id' => 1,
                'expires_at' => null,
                'notes' => 'SSS employment registration',
            ],
            [
                'id' => 6,
                'employee_id' => $employeeId,
                'name' => 'College Diploma',
                'document_type' => 'Bachelor\'s Degree Diploma',
                'file_name' => 'diploma_bscs.pdf',
                'file_path' => '/storage/employee-documents/' . $employeeId . '/educational/2024/diploma_bscs.pdf',
                'file_size' => 280000,
                'mime_type' => 'application/pdf',
                'category' => 'educational',
                'status' => 'approved',
                'uploaded_at' => '2024-01-18T10:00:00Z',
                'uploaded_by' => 'HR Staff',
                'uploaded_by_id' => 2,
                'approved_at' => '2024-01-18T15:30:00Z',
                'approved_by' => 'HR Manager',
                'approved_by_id' => 1,
                'expires_at' => null,
                'notes' => 'Bachelor of Science in Computer Science',
            ],
        ]);

        // Log security audit
        $this->logSecurityAudit(
            'employee_documents.view',
            "Viewed documents for employee ID: {$employeeId}",
            [
                'employee_id' => $employeeId,
                'employee_name' => $employee->profile->first_name . ' ' . $employee->profile->last_name,
                'document_count' => $documents->count(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $documents->values(),
            'meta' => [
                'total' => $documents->count(),
                'pending' => $documents->where('status', 'pending')->count(),
                'approved' => $documents->where('status', 'approved')->count(),
                'rejected' => $documents->where('status', 'rejected')->count(),
                'expiring_soon' => $documents->where('is_expiring_soon', true)->count(),
            ],
        ]);
    }

    /**
     * Upload a new document for a specific employee.
     *
     * Used by: Employee Profile → Documents Tab → Upload button
     * Accepts: Multipart form data with file
     *
     * @param UploadDocumentRequest $request
     * @param int $employeeId
     * @return JsonResponse
     */
    public function store(UploadDocumentRequest $request, int $employeeId): JsonResponse
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR permission
        $this->authorize('update', $employee);

        $validated = $request->validated();

        // In Phase 4, this will:
        // 1. Store file to storage/app/employee-documents/{employee_id}/{category}/{year}/
        // 2. Create database record
        // 3. Auto-approve or set pending based on document category

        // Mock response for frontend development
        $mockDocument = [
            'id' => 99,
            'employee_id' => $employeeId,
            'name' => $validated['document_type'],
            'document_type' => $validated['document_type'],
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => '/storage/employee-documents/' . $employeeId . '/' . $validated['document_category'] . '/2024/' . $request->file('file')->getClientOriginalName(),
            'file_size' => $request->file('file')->getSize(),
            'mime_type' => $request->file('file')->getMimeType(),
            'category' => $validated['document_category'],
            'status' => in_array($validated['document_category'], ['personal', 'educational']) ? 'approved' : 'pending',
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => auth()->user()->profile->first_name . ' ' . auth()->user()->profile->last_name,
            'uploaded_by_id' => auth()->id(),
            'expires_at' => $validated['expires_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        // Log security audit
        $this->logSecurityAudit(
            'employee_documents.upload',
            "Uploaded document for employee ID: {$employeeId}",
            [
                'employee_id' => $employeeId,
                'employee_name' => $employee->profile->first_name . ' ' . $employee->profile->last_name,
                'document_type' => $validated['document_type'],
                'category' => $validated['document_category'],
                'file_name' => $request->file('file')->getClientOriginalName(),
                'file_size' => $request->file('file')->getSize(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => $mockDocument,
        ], 201);
    }

    /**
     * Get details of a specific document.
     *
     * Used by: Employee Profile → Documents Tab → View document
     * Returns: Full document details including audit log
     *
     * @param int $employeeId
     * @param int $documentId
     * @return JsonResponse
     */
    public function show(int $employeeId, int $documentId): JsonResponse
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR permission
        $this->authorize('view', $employee);

        // Mock document details for frontend development
        $document = [
            'id' => $documentId,
            'employee_id' => $employeeId,
            'name' => 'Birth Certificate',
            'document_type' => 'Birth Certificate (PSA)',
            'file_name' => 'birth_certificate_psa.pdf',
            'file_path' => '/storage/employee-documents/' . $employeeId . '/personal/2024/birth_certificate_psa.pdf',
            'file_size' => 245000,
            'mime_type' => 'application/pdf',
            'category' => 'personal',
            'status' => 'approved',
            'uploaded_at' => '2024-01-15T10:30:00Z',
            'uploaded_by' => 'HR Staff',
            'uploaded_by_id' => 2,
            'approved_at' => '2024-01-15T14:20:00Z',
            'approved_by' => 'HR Manager',
            'approved_by_id' => 1,
            'expires_at' => null,
            'notes' => 'PSA-authenticated original copy',
            'audit_log' => [
                [
                    'action' => 'uploaded',
                    'user' => 'HR Staff',
                    'timestamp' => '2024-01-15T10:30:00Z',
                    'details' => 'Document uploaded',
                ],
                [
                    'action' => 'approved',
                    'user' => 'HR Manager',
                    'timestamp' => '2024-01-15T14:20:00Z',
                    'details' => 'Document approved for 201 file',
                ],
            ],
        ];

        // Log security audit
        $this->logSecurityAudit(
            'employee_documents.view_detail',
            "Viewed document details for employee ID: {$employeeId}, document ID: {$documentId}",
            [
                'employee_id' => $employeeId,
                'document_id' => $documentId,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $document,
        ]);
    }

    /**
     * Approve a pending document.
     *
     * Used by: Employee Profile → Documents Tab → Approve button (HR Manager only)
     * Action: Changes status from pending to approved
     *
     * @param ApproveDocumentRequest $request
     * @param int $employeeId
     * @param int $documentId
     * @return JsonResponse
     */
    public function approve(ApproveDocumentRequest $request, int $employeeId, int $documentId): JsonResponse
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR Manager permission
        $this->authorize('update', $employee);

        $validated = $request->validated();

        // In Phase 4, this will update database record status to 'approved'

        // Log security audit
        $this->logSecurityAudit(
            'employee_documents.approve',
            "Approved document for employee ID: {$employeeId}, document ID: {$documentId}",
            [
                'employee_id' => $employeeId,
                'employee_name' => $employee->profile->first_name . ' ' . $employee->profile->last_name,
                'document_id' => $documentId,
                'approved_by' => auth()->user()->profile->first_name . ' ' . auth()->user()->profile->last_name,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Document approved successfully',
        ]);
    }

    /**
     * Reject a pending document.
     *
     * Used by: Employee Profile → Documents Tab → Reject button (HR Manager only)
     * Action: Changes status from pending to rejected with reason
     *
     * @param RejectDocumentRequest $request
     * @param int $employeeId
     * @param int $documentId
     * @return JsonResponse
     */
    public function reject(RejectDocumentRequest $request, int $employeeId, int $documentId): JsonResponse
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR Manager permission
        $this->authorize('update', $employee);

        $validated = $request->validated();

        // In Phase 4, this will update database record status to 'rejected'

        // Log security audit
        $this->logSecurityAudit(
            'employee_documents.reject',
            "Rejected document for employee ID: {$employeeId}, document ID: {$documentId}",
            [
                'employee_id' => $employeeId,
                'employee_name' => $employee->profile->first_name . ' ' . $employee->profile->last_name,
                'document_id' => $documentId,
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_by' => auth()->user()->profile->first_name . ' ' . auth()->user()->profile->last_name,
            ],
            'warning'
        );

        return response()->json([
            'success' => true,
            'message' => 'Document rejected',
        ]);
    }

    /**
     * Delete a document (soft delete).
     *
     * Used by: Employee Profile → Documents Tab → Delete button
     * Action: Soft delete (mark as deleted, don't remove from storage)
     *
     * @param int $employeeId
     * @param int $documentId
     * @return JsonResponse
     */
    public function destroy(int $employeeId, int $documentId): JsonResponse
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR permission
        $this->authorize('delete', $employee);

        // In Phase 4, this will soft delete database record

        // Log security audit
        $this->logSecurityAudit(
            'employee_documents.delete',
            "Deleted document for employee ID: {$employeeId}, document ID: {$documentId}",
            [
                'employee_id' => $employeeId,
                'employee_name' => $employee->profile->first_name . ' ' . $employee->profile->last_name,
                'document_id' => $documentId,
                'deleted_by' => auth()->user()->profile->first_name . ' ' . auth()->user()->profile->last_name,
            ],
            'warning'
        );

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * Download a document file.
     *
     * Used by: Employee Profile → Documents Tab → Download button
     * Returns: File download response
     *
     * @param int $employeeId
     * @param int $documentId
     * @return JsonResponse
     */
    public function download(int $employeeId, int $documentId): JsonResponse
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR permission
        $this->authorize('view', $employee);

        // In Phase 4, this will:
        // 1. Fetch document from database
        // 2. Generate signed URL for file download (24-hour expiry)
        // 3. Return download response or redirect to signed URL

        // Mock download URL for frontend development
        $downloadUrl = url('/storage/employee-documents/' . $employeeId . '/personal/2024/birth_certificate_psa.pdf');

        // Log security audit
        $this->logSecurityAudit(
            'employee_documents.download',
            "Downloaded document for employee ID: {$employeeId}, document ID: {$documentId}",
            [
                'employee_id' => $employeeId,
                'employee_name' => $employee->profile->first_name . ' ' . $employee->profile->last_name,
                'document_id' => $documentId,
                'downloaded_by' => auth()->user()->profile->first_name . ' ' . auth()->user()->profile->last_name,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Document ready for download',
            'data' => [
                'download_url' => $downloadUrl,
                'file_name' => 'birth_certificate_psa.pdf',
                'expires_in' => '24 hours',
            ],
        ]);
    }
}
