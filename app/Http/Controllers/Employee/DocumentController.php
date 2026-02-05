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
            'period' => 'nullable|string', // For payslip: month-year format (e.g., "01-2024")
        ]);

        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return back()->with('error', 'Employee record not found');
        }

        try {
            // Create document request
            $documentRequest = \DB::table('document_requests')->insert([
                'employee_id' => $employee->id,
                'document_type' => $validated['document_type'],
                'purpose' => $validated['purpose'] ?? null,
                'period' => $validated['period'] ?? null,
                'status' => 'pending',
                'requested_by' => $user->id,
                'requested_at' => now(),
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
}
