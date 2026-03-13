<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Employee Document Controller
 *
 * Handles employee self-service document operations:
 * - View own documents
 * - Request new documents (COE, Payslip, 2316 Form)
 * - Download documents
 *
 * @package App\Http\Controllers\Employee
 */
class DocumentController extends Controller
{
    /**
     * Preview own document inline (PDF/images only)
     *
     * @param  int  $documentId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function preview($documentId)
    {
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return back()->with('error', 'Employee record not found');
        }

        // Get document
        $document = \DB::table('employee_documents')
            ->where('id', $documentId)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $filePath = storage_path("app/{$document->file_path}");
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $mime = $document->mime_type ?? 'application/octet-stream';
        $previewable = str_starts_with($mime, 'image/') || $mime === 'application/pdf';

        if (!$previewable) {
            return response()->json([
                'previewable' => false,
                'download_url' => route('employee.documents.download', ['documentId' => $documentId]),
            ]);
        }

        // Stream file inline
            // Optional audit logging for preview action
            \Log::info('Employee document previewed', [
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'document_id' => $documentId,
                'file_path' => $document->file_path,
                'previewed_at' => now(),
            ]);
        return response()->stream(function () use ($filePath) {
            $stream = fopen($filePath, 'rb');
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . ($document->file_name ?? 'document') . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /**
     * List employee's own documents
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        // Get authenticated user's employee record
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return redirect()->back()->with('error', 'Employee record not found');
        }

        // Get filter from request
        $category = $request->query('category', null);

        // Build query for documents
        $query = \DB::table('employee_documents')
            ->where('employee_id', $employee->id)
            ->where('status', '!=', 'rejected')
            ->orderBy('expires_at', 'asc')
            ->orderBy('created_at', 'desc');

        // Apply category filter if provided
        if ($category) {
            $query->where('document_category', $category);
        }

        // Get documents with calculated fields
        $documents = $query->get()->map(function ($doc) use ($employee) {
            $expiryDate = $doc->expires_at ? \Carbon\Carbon::parse($doc->expires_at) : null;
            $daysRemaining = $expiryDate ? $expiryDate->diffInDays(\Carbon\Carbon::now(), false) : null;

            return [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'document_category' => $doc->document_category,
                'uploaded_date' => $doc->created_at ? \Carbon\Carbon::parse($doc->created_at)->format('M d, Y') : 'N/A',
                'expiry_date' => $expiryDate ? $expiryDate->format('M d, Y') : 'N/A',
                'status' => $doc->status,
                'status_display' => $this->getStatusDisplay($doc->status, $daysRemaining),
                'days_remaining' => $daysRemaining,
                'file_size' => $this->formatFileSize($doc->file_size),
                'mime_type' => $doc->mime_type,
                'notes' => $doc->notes,
            ];
        })->toArray();

        // Get available categories
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

        // Get document request statistics
        $pendingRequests = \DB::table('document_requests')
            ->where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->count();

        return Inertia::render('Employee/Documents/Index', [
            'employee' => [
                'id' => $employee->id,
                'name' => "{$employee->profile?->first_name} {$employee->profile?->last_name}",
                'employee_number' => $employee->employee_number,
            ],
            'documents' => $documents,
            'categories' => $categories,
            'selectedCategory' => $category,
            'pendingRequests' => $pendingRequests,
            'totalDocuments' => count($documents),
        ]);
    }

    /**
     * Show document request form
     *
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function createRequest()
    {
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return redirect()->back()->with('error', 'Employee record not found');
        }

        // Available document types for employee request
        $documentTypes = [
            [
                'value' => 'certificate_of_employment',
                'label' => 'Certificate of Employment',
                'description' => 'Official document confirming your employment',
            ],
            [
                'value' => 'payslip',
                'label' => 'Payslip',
                'description' => 'Salary statement for a specific period',
            ],
            [
                'value' => 'bir_form_2316',
                'label' => 'BIR Form 2316',
                'description' => 'Tax form for government filing',
            ],
            [
                'value' => 'government_compliance',
                'label' => 'Government Compliance Document',
                'description' => 'SSS, PhilHealth, Pag-IBIG related documents',
            ],
        ];

        return Inertia::render('Employee/Documents/RequestForm', [
            'employee' => [
                'id' => $employee->id,
                'name' => "{$employee->profile?->first_name} {$employee->profile?->last_name}",
                'employee_number' => $employee->employee_number,
            ],
            'documentTypes' => $documentTypes,
        ]);
    }

    /**
     * Submit document request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeRequest(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'document_type' => 'required|string|in:certificate_of_employment,payslip,bir_form_2316,government_compliance',
            'purpose' => 'nullable|string|max:500',
            'period' => 'nullable|string|required_if:document_type,payslip|max:7', // For payslip: YYYY-MM
        ]);

        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return back()->with('error', 'Employee record not found');
        }

        try {
            $notes = null;
            if (!empty($validated['period'])) {
                $notes = 'Requested period: ' . $validated['period'];
            }

            // Create document request
            \DB::table('document_requests')->insert([
                'employee_id' => $employee->id,
                'document_type' => $validated['document_type'],
                'purpose' => $validated['purpose'] ?? null,
                'status' => 'pending',
                'request_source' => 'employee_portal',
                'requested_at' => now(),
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // TODO: Send notification to HR Staff
            // Notification::send(
            //     User::role('HR Staff')->get(),
            //     new DocumentRequestSubmitted($employee, $validated['document_type'])
            // );

            return redirect()->route('employee.documents.index')
                ->with('success', 'Document request submitted successfully. HR Staff will process your request.');
        } catch (\Exception $e) {
            \Log::error('Document request creation failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to submit document request. Please try again.');
        }
    }

    /**
     * Download own document
     *
     * @param  int  $documentId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
     */
    public function download($documentId)
    {
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return back()->with('error', 'Employee record not found');
        }

        // Get document
        $document = \DB::table('employee_documents')
            ->where('id', $documentId)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$document) {
            return back()->with('error', 'Document not found');
        }

        // Check if document file exists
        $filePath = storage_path("app/{$document->file_path}");
        if (!file_exists($filePath)) {
            \Log::warning('Document file not found', [
                'document_id' => $documentId,
                'file_path' => $document->file_path,
            ]);

            return back()->with('error', 'Document file not found on server');
        }

        try {
            // Log download action
            \DB::table('activity_logs')->insert([
                'subject_type' => 'App\Models\EmployeeDocument',
                'subject_id' => $documentId,
                'causer_type' => 'App\Models\User',
                'causer_id' => $user->id,
                'event' => 'downloaded',
                'properties' => json_encode([
                    'document_type' => $document->document_type,
                    'employee_id' => $employee->id,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Download file
            return response()->download(
                $filePath,
                "{$document->document_type}_{$employee->employee_number}." . pathinfo($document->file_path, PATHINFO_EXTENSION),
                [
                    'Content-Type' => $document->mime_type,
                    'Content-Disposition' => 'attachment',
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Document download failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to download document. Please try again.');
        }
    }

    /**
     * Get status display with color coding
     *
     * @param  string  $status
     * @param  int|null  $daysRemaining
     * @return array
     */
    private function getStatusDisplay($status, $daysRemaining = null)
    {
        $statusMap = [
            'pending' => ['label' => 'Pending', 'color' => 'warning', 'icon' => 'Clock'],
            'approved' => ['label' => 'Approved', 'color' => 'success', 'icon' => 'CheckCircle'],
            'auto_approved' => ['label' => 'Approved', 'color' => 'success', 'icon' => 'CheckCircle'],
            'rejected' => ['label' => 'Rejected', 'color' => 'destructive', 'icon' => 'XCircle'],
        ];

        $display = $statusMap[$status] ?? ['label' => $status, 'color' => 'secondary', 'icon' => 'FileText'];

        // Add expiry warning for approved documents
        if (in_array($status, ['approved', 'auto_approved']) && $daysRemaining !== null) {
            if ($daysRemaining < 0) {
                $display['expiry_label'] = 'Expired';
                $display['expiry_color'] = 'destructive';
            } elseif ($daysRemaining <= 7) {
                $display['expiry_label'] = "Expires in {$daysRemaining} days";
                $display['expiry_color'] = 'warning';
            } elseif ($daysRemaining <= 30) {
                $display['expiry_label'] = "Expires in {$daysRemaining} days";
                $display['expiry_color'] = 'warning';
            }
        }

        return $display;
    }

    /**
     * Format file size for display
     *
     * @param  int|null  $bytes
     * @return string
     */
    private function formatFileSize($bytes)
    {
        if ($bytes === null) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    
/**
 * Show request history for logged-in employee
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
 */
public function requestHistory(Request $request)
{
    $user = Auth::user();
    $employee = Employee::where('user_id', $user->id)->first();

    if (!$employee) {
        return redirect()->back()->with('error', 'Employee record not found');
    }

    // Get filter parameters
    $status = $request->query('status', 'all');
    $documentType = $request->query('document_type', 'all');

    // Build query
    $query = \DB::table('document_requests')
        ->where('employee_id', $employee->id)
        ->orderBy('requested_at', 'desc');

    // Apply filters
    if ($status !== 'all') {
        $query->where('status', $status);
    }

    if ($documentType !== 'all') {
        $query->where('document_type', $documentType);
    }

    // Get requests with processing info
    $requests = $query->get()->map(function ($request) {
        return [
            'id' => $request->id,
            'document_type' => $this->formatDocumentType($request->document_type),
            'document_type_key' => $request->document_type,
            'purpose' => $request->purpose ?? 'Not specified',
            'period' => $request->period,
            'status' => $request->status,
            'status_display' => $this->getRequestStatusDisplay($request->status),
            'requested_at' => \Carbon\Carbon::parse($request->requested_at)->format('M d, Y h:i A'),
            'processed_at' => $request->processed_at ? \Carbon\Carbon::parse($request->processed_at)->format('M d, Y h:i A') : null,
            'processed_by' => $request->processed_by ? $this->getProcessorName($request->processed_by) : null,
            'file_path' => $request->file_path,
            'rejection_reason' => $request->rejection_reason,
            'notes' => $request->notes,
            'can_cancel' => $request->status === 'pending',
            'can_resubmit' => $request->status === 'rejected',
            'days_pending' => $request->status === 'pending' 
                ? \Carbon\Carbon::parse($request->requested_at)->diffInDays(\Carbon\Carbon::now()) 
                : null,
        ];
    })->toArray();

    // Get statistics
    $statistics = [
        'total' => count($requests),
        'pending' => collect($requests)->where('status', 'pending')->count(),
        'approved' => collect($requests)->where('status', 'completed')->count(),
        'rejected' => collect($requests)->where('status', 'rejected')->count(),
    ];

    return Inertia::render('Employee/Documents/RequestHistory', [
        'employee' => [
            'id' => $employee->id,
            'name' => "{$employee->profile?->first_name} {$employee->profile?->last_name}",
            'employee_number' => $employee->employee_number,
        ],
        'requests' => $requests,
        'statistics' => $statistics,
        'filters' => [
            'status' => $status,
            'document_type' => $documentType,
        ],
    ]);
}

/**
 * Format document type for display
 */
private function formatDocumentType(string $type): string
{
    return match($type) {
        'certificate_of_employment' => 'Certificate of Employment',
        'payslip' => 'Payslip',
        'bir_form_2316' => 'BIR Form 2316',
        'government_compliance' => 'Government Compliance Document',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}

/**
 * Get request status display configuration
 */
private function getRequestStatusDisplay(string $status): array
{
    return match($status) {
        'pending' => [
            'label' => 'Pending Review',
            'color' => 'yellow',
            'icon' => 'Clock',
            'description' => 'Your request is being reviewed by HR',
        ],
        'processing' => [
            'label' => 'Processing',
            'color' => 'blue',
            'icon' => 'FileText',
            'description' => 'HR is generating your document',
        ],
        'completed' => [
            'label' => 'Approved',
            'color' => 'green',
            'icon' => 'CheckCircle',
            'description' => 'Your document is ready for download',
        ],
        'rejected' => [
            'label' => 'Declined',
            'color' => 'red',
            'icon' => 'XCircle',
            'description' => 'Your request could not be processed',
        ],
        default => [
            'label' => ucfirst($status),
            'color' => 'gray',
            'icon' => 'FileText',
            'description' => '',
        ],
    };
}

/**
 * Get processor name from user ID
 */
private function getProcessorName(int $userId): string
{
    $user = \DB::table('users')->where('id', $userId)->first();
    return $user ? $user->name : 'HR Staff';
}

/**
 * Cancel pending document request
 *
 * @param  int  $requestId
 * @return \Illuminate\Http\RedirectResponse
 */
public function cancelRequest($requestId)
{
    $user = Auth::user();
    $employee = Employee::where('user_id', $user->id)->first();

    if (!$employee) {
        return back()->with('error', 'Employee record not found');
    }

    try {
        // Get request
        $request = \DB::table('document_requests')
            ->where('id', $requestId)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$request) {
            return back()->with('error', 'Request not found');
        }

        // Only pending requests can be cancelled
        if ($request->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be cancelled');
        }

        // Update status to cancelled
        \DB::table('document_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'cancelled',
                'notes' => 'Cancelled by employee',
                'updated_at' => now(),
            ]);

        // Log action
        \Log::info('Document request cancelled by employee', [
            'request_id' => $requestId,
            'employee_id' => $employee->id,
            'user_id' => $user->id,
        ]);

        return back()->with('success', 'Request cancelled successfully');
    } catch (\Exception $e) {
        \Log::error('Request cancellation failed', [
            'request_id' => $requestId,
            'error' => $e->getMessage(),
        ]);

        return back()->with('error', 'Failed to cancel request. Please try again.');
    }
}

/**
 * Download document from approved request
 *
 * @param  int  $requestId
 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
 */
public function downloadFromRequest($requestId)
{
    $user = Auth::user();
    $employee = Employee::where('user_id', $user->id)->first();

    if (!$employee) {
        return back()->with('error', 'Employee record not found');
    }

    try {
        // Get request
        $request = \DB::table('document_requests')
            ->where('id', $requestId)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$request) {
            return back()->with('error', 'Request not found');
        }

        // Only completed requests have files
        if ($request->status !== 'completed' || !$request->file_path) {
            return back()->with('error', 'Document not available for download');
        }

        // Check if file exists
        $filePath = storage_path("app/{$request->file_path}");
        if (!file_exists($filePath)) {
            \Log::warning('Request document file not found', [
                'request_id' => $requestId,
                'file_path' => $request->file_path,
            ]);

            return back()->with('error', 'Document file not found on server');
        }

        // Log download
        \DB::table('activity_logs')->insert([
            'subject_type' => 'App\Models\DocumentRequest',
            'subject_id' => $requestId,
            'causer_type' => 'App\Models\User',
            'causer_id' => $user->id,
            'event' => 'downloaded',
            'properties' => json_encode([
                'document_type' => $request->document_type,
                'employee_id' => $employee->id,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Download file
        $filename = $this->formatDocumentType($request->document_type);
        $filename = str_replace(' ', '_', $filename) . '_' . $employee->employee_number . '.pdf';

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    } catch (\Exception $e) {
        \Log::error('Request document download failed', [
            'request_id' => $requestId,
            'error' => $e->getMessage(),
        ]);

        return back()->with('error', 'Failed to download document. Please try again.');
    }
}
}
