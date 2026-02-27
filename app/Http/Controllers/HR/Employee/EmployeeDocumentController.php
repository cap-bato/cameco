<?php

namespace App\Http\Controllers\HR\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Documents\UploadDocumentRequest;
use App\Http\Requests\HR\Documents\ApproveDocumentRequest;
use App\Http\Requests\HR\Documents\RejectDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Traits\LogsSecurityAudits;
use App\Traits\StreamsEmployeeDocumentPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    use StreamsEmployeeDocumentPreview;

    /**
     * Safely get employee name from profile or return default string.
     *
     * @param Employee|null $employee
     * @return string
     */
    private function getEmployeeName(?Employee $employee): string
    {
        if (!$employee || !$employee->profile) {
            return 'Unknown';
        }
        
        return trim(
            ($employee->profile->first_name ?? '') . ' ' . 
            ($employee->profile->last_name ?? '')
        ) ?: 'Unknown';
    }

    /**
     * Safely get authenticated user's name from profile.
     *
     * @return string
     */
    private function getAuthUserName(): string
    {
        $user = auth()->user();
        if (!$user || !$user->profile) {
            return 'System User';
        }
        
        return trim(
            ($user->profile->first_name ?? '') . ' ' . 
            ($user->profile->last_name ?? '')
        ) ?: 'System User';
    }

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

        // Query actual documents from database
        $dbDocuments = EmployeeDocument::where('employee_id', $employeeId)
            ->with(['uploadedBy:id,email', 'approvedBy:id,email'])
            ->orderByDesc('created_at')
            ->get();

        // Format documents for frontend
        $documents = $dbDocuments->map(function ($doc) {
            $expiresAt = $doc->expires_at ? \Carbon\Carbon::parse($doc->expires_at) : null;
            $daysUntilExpiry = $expiresAt ? $expiresAt->diffInDays(now(), false) : null;
            
            return [
                'id' => $doc->id,
                'employee_id' => $doc->employee_id,
                'name' => $doc->document_type,
                'document_type' => $doc->document_type,
                'file_name' => $doc->file_name,
                'file_path' => $doc->file_path,
                'file_size' => $doc->file_size,
                'mime_type' => $doc->mime_type,
                'category' => $doc->document_category,
                'status' => $doc->status,
                'uploaded_at' => $doc->uploaded_at?->toIso8601String(),
                'uploaded_by' => $doc->uploadedBy?->email ?? 'Unknown',
                'uploaded_by_id' => $doc->uploaded_by,
                'approved_at' => $doc->approved_at?->toIso8601String(),
                'approved_by' => $doc->approvedBy?->email ?? null,
                'approved_by_id' => $doc->approved_by,
                'expires_at' => $doc->expires_at?->toDateString(),
                'notes' => $doc->notes,
                'rejection_reason' => $doc->rejection_reason,
                'days_until_expiry' => $daysUntilExpiry,
                'is_expiring_soon' => $daysUntilExpiry !== null && $daysUntilExpiry <= 30 && $daysUntilExpiry >= 0,
            ];
        });

        // Log security audit
        $this->logAudit(
            'employee_documents.view',
            'info',
            [
                'employee_id' => $employeeId,
                'employee_name' => $this->getEmployeeName($employee),
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

        try {
            // Store file to storage
            $file = $request->file('file');
            $year = now()->year;
            $category = $validated['document_category'];
            $path = "{$employeeId}/{$category}/{$year}";
            
            $filePath = $file->store($path, 'employee_documents');

            // Create database record
            $document = EmployeeDocument::create([
                'employee_id' => $employeeId,
                'document_category' => $category,
                'document_type' => $validated['document_type'],
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
                'expires_at' => $validated['expires_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => in_array($category, ['personal', 'educational']) ? 'approved' : 'pending',
                'requires_approval' => !in_array($category, ['personal', 'educational']),
                'source' => 'manual',
            ]);

            // Format response
            $responseData = [
                'id' => $document->id,
                'employee_id' => $document->employee_id,
                'name' => $document->document_type,
                'document_type' => $document->document_type,
                'file_name' => $document->file_name,
                'file_size' => $document->file_size,
                'mime_type' => $document->mime_type,
                'category' => $document->document_category,
                'status' => $document->status,
                'uploaded_at' => $document->uploaded_at->toIso8601String(),
                'uploaded_by' => $document->uploadedBy?->email ?? 'Unknown',
                'uploaded_by_id' => $document->uploaded_by,
                'expires_at' => $document->expires_at?->toDateString(),
                'notes' => $document->notes,
            ];

            // Log security audit
            $this->logAudit(
                'employee_documents.upload',
                'info',
                [
                    'employee_id' => $employeeId,
                    'employee_name' => $this->getEmployeeName($employee),
                    'document_type' => $validated['document_type'],
                    'category' => $category,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'document_id' => $document->id,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $responseData,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to upload employee document', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document. ' . $e->getMessage(),
            ], 500);
        }
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

        // Get document from database
        $doc = EmployeeDocument::where('employee_id', $employeeId)
            ->where('id', $documentId)
            ->with(['uploadedBy:id,email', 'approvedBy:id,email'])
            ->firstOrFail();

        $expiresAt = $doc->expires_at ? \Carbon\Carbon::parse($doc->expires_at) : null;
        $daysUntilExpiry = $expiresAt ? $expiresAt->diffInDays(now(), false) : null;

        $document = [
            'id' => $doc->id,
            'employee_id' => $doc->employee_id,
            'name' => $doc->document_type,
            'document_type' => $doc->document_type,
            'file_name' => $doc->file_name,
            'file_path' => $doc->file_path,
            'file_size' => $doc->file_size,
            'mime_type' => $doc->mime_type,
            'category' => $doc->document_category,
            'status' => $doc->status,
            'uploaded_at' => $doc->uploaded_at?->toIso8601String(),
            'uploaded_by' => $doc->uploadedBy?->email ?? 'Unknown',
            'uploaded_by_id' => $doc->uploaded_by,
            'approved_at' => $doc->approved_at?->toIso8601String(),
            'approved_by' => $doc->approvedBy?->email,
            'approved_by_id' => $doc->approved_by,
            'expires_at' => $doc->expires_at?->toDateString(),
            'notes' => $doc->notes,
            'rejection_reason' => $doc->rejection_reason,
            'days_until_expiry' => $daysUntilExpiry,
            'is_expiring_soon' => $daysUntilExpiry !== null && $daysUntilExpiry <= 30 && $daysUntilExpiry >= 0,
            'audit_log' => [
                [
                    'action' => 'uploaded',
                    'user' => $doc->uploadedBy?->email ?? 'Unknown',
                    'timestamp' => $doc->uploaded_at?->toIso8601String(),
                    'details' => 'Document uploaded',
                ],
                $doc->approved_at ? [
                    'action' => 'approved',
                    'user' => $doc->approvedBy?->email ?? 'Unknown',
                    'timestamp' => $doc->approved_at->toIso8601String(),
                    'details' => 'Document approved',
                ] : null,
                $doc->rejection_reason ? [
                    'action' => 'rejected',
                    'user' => $doc->approvedBy?->email ?? 'Unknown',
                    'timestamp' => $doc->updated_at->toIso8601String(),
                    'details' => "Rejected: {$doc->rejection_reason}",
                ] : null,
            ],
        ];

        // Remove null entries from audit_log
        $document['audit_log'] = array_filter($document['audit_log']);

        // Log security audit
        $this->logAudit(
            'employee_documents.view_detail',
            'info',
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
     * Preview a document file inline (PDF or image).
     *
     * Used by: Employee Profile → Documents Tab → Document Preview Modal
     * Returns: Streamed file for inline viewing (PDF in iframe, images in img tag)
     * Returns: JSON if file type is not previewable (e.g., DOCX, XLSX)
     *
     * @param int $employeeId
     * @param int $documentId
     * @return StreamedResponse|JsonResponse
     */
    public function preview(int $employeeId, int $documentId): StreamedResponse|JsonResponse
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR permission
        $this->authorize('view', $employee);

        // Get document from database
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('id', $documentId)
            ->firstOrFail();
        return $this->streamDocumentPreview(
            $document,
            route('hr.api.employees.documents.download-file', [
                'employeeId' => $employeeId,
                'documentId' => $documentId,
            ]),
            true,
            function (string $mime) use ($employeeId, $employee, $documentId, $document) {
                $this->logAudit(
                    'employee_documents.preview',
                    'info',
                    [
                        'employee_id' => $employeeId,
                        'employee_name' => $this->getEmployeeName($employee),
                        'document_id' => $documentId,
                        'document_type' => $document->document_type,
                        'mime_type' => $mime,
                        'previewed_by' => $this->getAuthUserName(),
                    ]
                );
            }
        );
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

        // Get document and update status
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('id', $documentId)
            ->firstOrFail();

        $document->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Log security audit
        $this->logAudit(
            'employee_documents.approve',
            'info',
            [
                'employee_id' => $employeeId,
                'employee_name' => $this->getEmployeeName($employee),
                'document_id' => $documentId,
                'document_type' => $document->document_type,
                'approved_by' => $this->getAuthUserName(),
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

        // Get document and update status
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('id', $documentId)
            ->firstOrFail();

        $document->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Log security audit
        $this->logAudit(
            'employee_documents.reject',
            'warning',
            [
                'employee_id' => $employeeId,
                'employee_name' => $this->getEmployeeName($employee),
                'document_id' => $documentId,
                'document_type' => $document->document_type,
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_by' => $this->getAuthUserName(),
            ]
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

        // Get document and soft delete
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('id', $documentId)
            ->firstOrFail();

        $documentType = $document->document_type;
        $document->delete(); // Soft delete

        // Log security audit
        $this->logAudit(
            'employee_documents.delete',
            'warning',
            [
                'employee_id' => $employeeId,
                'employee_name' => $this->getEmployeeName($employee),
                'document_id' => $documentId,
                'document_type' => $documentType,
                'deleted_by' => $this->getAuthUserName(),
            ]
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

        // Get document from database
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('id', $documentId)
            ->firstOrFail();

        // Check if file exists in storage
        if (!\Storage::disk('employee_documents')->exists($document->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Document file not found on server',
            ], 404);
        }

        // Generate download URL - use a route that will stream the file
        $downloadUrl = route('hr.api.employees.documents.download-file', [
            'employeeId' => $employeeId,
            'documentId' => $documentId,
        ]);

        // Log security audit
        $this->logAudit(
            'employee_documents.download',
            'info',
            [
                'employee_id' => $employeeId,
                'employee_name' => $this->getEmployeeName($employee),
                'document_id' => $documentId,
                'document_type' => $document->document_type,
                'downloaded_by' => $this->getAuthUserName(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Document ready for download',
            'data' => [
                'download_url' => $downloadUrl,
                'file_name' => $document->file_name,
                'expires_in' => '24 hours',
            ],
        ]);
    }

    /**
     * Download document file (actual file streaming).
     *
     * @param int $employeeId
     * @param int $documentId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadFile(int $employeeId, int $documentId)
    {
        // Verify employee exists
        $employee = Employee::findOrFail($employeeId);

        // Check HR permission
        $this->authorize('view', $employee);

        // Get document from database
        $document = EmployeeDocument::where('employee_id', $employeeId)
            ->where('id', $documentId)
            ->firstOrFail();

        // Check if file exists in storage
        if (!\Storage::disk('employee_documents')->exists($document->file_path)) {
            abort(404, 'Document file not found');
        }

        // Log the download
        $this->logAudit(
            'employee_documents.file_download',
            'info',
            [
                'employee_id' => $employeeId,
                'document_id' => $documentId,
                'document_type' => $document->document_type,
                'downloaded_by' => $this->getAuthUserName(),
            ]
        );

        // Stream file download
        return \Storage::disk('employee_documents')->download(
            $document->file_path,
            $document->file_name
        );
    }
}
