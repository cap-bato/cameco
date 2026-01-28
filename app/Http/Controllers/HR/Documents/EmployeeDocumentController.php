<?php

namespace App\Http\Controllers\HR\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Documents\ApproveDocumentRequest;
use App\Http\Requests\HR\Documents\BulkUploadRequest;
use App\Http\Requests\HR\Documents\RejectDocumentRequest;
use App\Http\Requests\HR\Documents\UploadDocumentRequest;
use App\Traits\LogsSecurityAudits;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeDocumentController extends Controller
{
    use LogsSecurityAudits;

    /**
     * Display a listing of employee documents with filters.
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->input('search'),
            'employee_id' => $request->input('employee_id'),
            'category' => $request->input('category'),
            'status' => $request->input('status'),
            'expiry_date' => $request->input('expiry_date'),
        ];

        // Query documents with filters
        $query = \App\Models\EmployeeDocument::with([
            'employee.profile:id,first_name,last_name',
            'uploadedBy:id,name'
        ]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('document_type', 'ilike', "%{$search}%")
                  ->orWhere('file_name', 'ilike', "%{$search}%")
                  ->orWhereHas('employee.profile', function ($q) use ($search) {
                      $q->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%");
                  });
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('category')) {
            $query->where('document_category', $request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Paginate results
        $documents = $query->paginate(20)->through(function ($doc) {
            // Handle missing relationships gracefully
            $employeeName = 'Unknown Employee';
            $employeeNumber = 'N/A';
            
            if ($doc->employee && $doc->employee->profile) {
                $employeeName = $doc->employee->profile->first_name . ' ' . $doc->employee->profile->last_name;
                $employeeNumber = $doc->employee->employee_number;
            }
            
            $uploadedBy = $doc->uploadedBy->name ?? 'Unknown';

            return [
                'id' => $doc->id,
                'employee_id' => $doc->employee_id,
                'employee_name' => $employeeName,
                'employee_number' => $employeeNumber,
                'document_category' => $doc->document_category,
                'document_type' => $doc->document_type,
                'file_name' => $doc->file_name,
                'file_size' => $doc->file_size,
                'file_path' => $doc->file_path,
                'status' => $doc->status,
                'uploaded_by' => $uploadedBy,
                'uploaded_at' => $doc->uploaded_at->toDateTimeString(),
                'expires_at' => $doc->expires_at?->toDateString(),
                'notes' => $doc->notes,
                'mime_type' => $doc->mime_type,
            ];
        });

        // Get employees for filter dropdown
        $employees = \App\Models\Employee::with('profile:id,first_name,last_name')
            ->select('id', 'employee_number', 'profile_id')
            ->orderBy('employee_number')
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->profile->first_name . ' ' . $emp->profile->last_name,
            ]);

        // Document categories
        $categories = [
            'personal' => 'Personal',
            'educational' => 'Educational',
            'employment' => 'Employment',
            'medical' => 'Medical',
            'contracts' => 'Contracts',
            'benefits' => 'Benefits',
            'performance' => 'Performance',
            'separation' => 'Separation',
            'government' => 'Government',
            'special' => 'Special',
        ];

        return Inertia::render('HR/Documents/Index', [
            'documents' => $documents,
            'filters' => $filters,
            'employees' => $employees,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for uploading a new document.
     * 
     * @return \Inertia\Response
     */
    public function create()
    {
        // Get employees for dropdown
        $employees = \App\Models\Employee::with('profile:id,first_name,last_name')
            ->select('id', 'employee_number', 'profile_id')
            ->where('status', 'active')
            ->orderBy('employee_number')
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->profile->first_name . ' ' . $emp->profile->last_name,
            ]);

        // Document categories
        $categories = [
            'personal' => 'Personal',
            'educational' => 'Educational',
            'employment' => 'Employment',
            'medical' => 'Medical',
            'contracts' => 'Contracts',
            'benefits' => 'Benefits',
            'performance' => 'Performance',
            'separation' => 'Separation',
            'government' => 'Government',
            'special' => 'Special',
        ];

        return Inertia::render('HR/Documents/Upload', [
            'employees' => $employees,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly uploaded document.
     * 
     * @param UploadDocumentRequest $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function store(UploadDocumentRequest $request)
    {
        $validated = $request->validated();

        try {
            // Store the file in a structured path
            $employee = \App\Models\Employee::findOrFail($validated['employee_id']);
            $year = now()->year;
            $category = $validated['document_category'];
            
            // Create storage path: storage/app/employee-documents/{employee_id}/{category}/{year}/
            $storagePath = "{$employee->id}/{$category}/{$year}";
            
            // Store the file and get the path using the 'employee_documents' disk
            $file = $validated['file'];
            $originalFileName = $file->getClientOriginalName();
            $fileName = time() . '_' . $originalFileName;
            $filePath = $file->storeAs($storagePath, $fileName, 'employee_documents');
            
            // Create EmployeeDocument record
            $document = \App\Models\EmployeeDocument::create([
                'employee_id' => $validated['employee_id'],
                'document_category' => $validated['document_category'],
                'document_type' => $validated['document_type'],
                'file_name' => $originalFileName,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'expires_at' => $validated['expires_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
                'source' => 'manual',
            ]);

            // Log security audit
            $this->logAudit(
                eventType: 'document_uploaded',
                severity: 'info',
                details: [
                    'document_id' => $document->id,
                    'employee_id' => $validated['employee_id'],
                    'document_category' => $validated['document_category'],
                    'document_type' => $validated['document_type'],
                    'file_name' => $originalFileName,
                    'file_path' => $filePath,
                ]
            );

            \Log::info('Document uploaded successfully', [
                'document_id' => $document->id,
                'employee_id' => $validated['employee_id'],
                'file_path' => $filePath,
                'uploaded_by' => auth()->id(),
            ]);

            // Return JSON response for AJAX requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Document uploaded successfully.',
                    'document_id' => $document->id,
                    'document' => $document,
                ], 201);
            }

            return redirect()
                ->route('hr.documents.index')
                ->with('success', 'Document uploaded successfully.');
        } catch (\Exception $e) {
            \Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'employee_id' => $validated['employee_id'] ?? null,
                'uploaded_by' => auth()->id(),
            ]);

            // Return JSON response for AJAX requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload document: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'Failed to upload document: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified document.
     * 
     * @param int $id
     * @return \Inertia\Response
     */
    public function show(int $id)
    {
        // TODO: Implement document retrieval when EmployeeDocument model is created (Phase 4)
        // For now, return mock data structure
        
        $document = [
            'id' => $id,
            'employee_id' => 1,
            'employee_name' => 'Mock Employee',
            'document_category' => 'personal',
            'document_type' => 'Birth Certificate',
            'file_name' => 'birth_certificate.pdf',
            'file_size' => 1024000,
            'file_path' => null,
            'status' => 'pending',
            'uploaded_by' => auth()->user()->name,
            'uploaded_at' => now()->toDateTimeString(),
            'expires_at' => null,
            'notes' => 'Mock document for frontend development',
        ];

        // Log security audit
        $this->logAudit(
            eventType: 'document_viewed',
            severity: 'info',
            details: [
                'document_id' => $id,
            ]
        );

        return Inertia::render('HR/Documents/Show', [
            'document' => $document,
        ]);
    }

    /**
     * Download the specified document.
     * 
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(int $id)
    {
        // TODO: Implement document download when EmployeeDocument model is created (Phase 4)
        // For now, log the action and return error
        
        \Log::info('Document download requested', [
            'document_id' => $id,
            'user_id' => auth()->id(),
        ]);

        // Log security audit
        $this->logAudit(
            eventType: 'document_downloaded',
            severity: 'info',
            details: [
                'document_id' => $id,
            ]
        );

        return response()->json([
            'error' => 'Document download not yet implemented.'
        ], 501);
    }

    /**
     * Approve a pending document.
     * 
     * @param ApproveDocumentRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(ApproveDocumentRequest $request, int $id)
    {
        $validated = $request->validated();

        // TODO: Implement document approval when EmployeeDocument model is created (Phase 4)
        // For now, log the action
        
        \Log::info('Document approval request received', [
            'document_id' => $id,
            'approved_by' => auth()->id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        // Log security audit
        $this->logAudit(
            eventType: 'document_approved',
            severity: 'info',
            details: [
                'document_id' => $id,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Document approved successfully. (Database implementation pending - Phase 4)');
    }

    /**
     * Reject a pending document.
     * 
     * @param RejectDocumentRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(RejectDocumentRequest $request, int $id)
    {
        $validated = $request->validated();

        // TODO: Implement document rejection when EmployeeDocument model is created (Phase 4)
        // For now, log the action
        
        \Log::info('Document rejection request received', [
            'document_id' => $id,
            'rejected_by' => auth()->id(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        // Log security audit
        $this->logAudit(
            eventType: 'document_rejected',
            severity: 'info',
            details: [
                'document_id' => $id,
                'rejection_reason' => $validated['rejection_reason'],
            ]
        );

        return back()->with('success', 'Document rejected. (Database implementation pending - Phase 4)');
    }

    /**
     * Soft delete a document.
     * 
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id)
    {
        // TODO: Implement soft delete when EmployeeDocument model is created (Phase 4)
        // For now, log the action
        
        \Log::info('Document deletion request received', [
            'document_id' => $id,
            'deleted_by' => auth()->id(),
        ]);

        // Log security audit
        $this->logAudit(
            eventType: 'document_deleted',
            severity: 'warning',
            details: [
                'document_id' => $id,
            ]
        );

        return redirect()
            ->route('hr.documents.index')
            ->with('success', 'Document deleted successfully. (Database implementation pending - Phase 4)');
    }

    /**
     * Show the bulk upload form.
     * 
     * @return \Inertia\Response
     */
    public function bulkUploadForm()
    {
        return Inertia::render('HR/Documents/BulkUpload', [
            'csvTemplate' => [
                'headers' => [
                    'employee_id',
                    'document_category',
                    'document_type',
                    'file_name',
                    'expires_at',
                    'notes'
                ],
                'example' => [
                    '1',
                    'personal',
                    'Birth Certificate',
                    'birth_cert_emp1.pdf',
                    '2030-12-31',
                    'PSA authenticated copy'
                ],
            ],
            'categories' => [
                'personal',
                'educational',
                'employment',
                'medical',
                'contracts',
                'benefits',
                'performance',
                'separation',
                'government',
                'special',
            ],
        ]);
    }

    /**
     * Process bulk document upload via CSV + ZIP.
     * 
     * @param BulkUploadRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Inertia\Response
     */
    public function bulkUpload(BulkUploadRequest $request)
    {
        $validated = $request->validated();

        // TODO: Implement bulk upload processing when EmployeeDocument model is created (Phase 4)
        // For now, log the action and return mock results
        
        \Log::info('Bulk upload request received', [
            'csv_file' => $validated['csv_file']->getClientOriginalName(),
            'zip_file' => $validated['zip_file']->getClientOriginalName(),
            'uploaded_by' => auth()->id(),
        ]);

        // Log security audit
        $this->logAudit(
            eventType: 'bulk_upload_initiated',
            severity: 'info',
            details: [
                'csv_file' => $validated['csv_file']->getClientOriginalName(),
                'zip_file' => $validated['zip_file']->getClientOriginalName(),
            ]
        );

        // Mock results for frontend development
        $results = [
            'total' => 0,
            'success' => 0,
            'errors' => [],
            'message' => 'Bulk upload not yet implemented. (Database implementation pending - Phase 4)',
        ];

        return Inertia::render('HR/Documents/BulkUploadResult', [
            'results' => $results,
        ]);
    }
}
