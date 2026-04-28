<?php

namespace App\Http\Controllers\HR\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Documents\ApproveDocumentRequest;
use App\Http\Requests\HR\Documents\RejectDocumentRequest;
use App\Models\DocumentRequest;
use App\Models\DocumentTemplate;
use App\Notifications\DocumentRequestProcessed;
use App\Services\DocumentGeneratorService;
use App\Traits\LogsSecurityAudits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentRequestController extends Controller
{
    use LogsSecurityAudits;

    /**
     * Display a listing of document requests from employees.
     */
    public function index(Request $request)
    {
        // Build base query using model relationships to stay schema/database compatible.
        $query = DocumentRequest::query()
            ->with(['employee.profile', 'employee.department', 'processedBy'])
            ->orderByRaw("CASE
                WHEN document_requests.status = 'pending' THEN 1
                WHEN document_requests.status = 'processing' THEN 2
                WHEN document_requests.status = 'processed' THEN 3
                WHEN document_requests.status = 'completed' THEN 3
                WHEN document_requests.status = 'rejected' THEN 4
                ELSE 5
            END")
            ->orderBy('requested_at', 'desc');

        // Filters
        if ($request->filled('status')) {
            $query->where('document_requests.status', $request->status);
        }

        if ($request->filled('document_type')) {
            $query->where('document_requests.document_type', $request->document_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('document_requests.requested_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('document_requests.requested_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($employeeQuery) use ($search) {
                    $employeeQuery->where('employee_number', 'like', "%{$search}%");
                })->orWhereHas('employee.profile', function ($profileQuery) use ($search) {
                    $profileQuery->whereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"]);
                });
            });
        }

        // Fetch results and map to frontend format
        $requests = $query->get()->map(function (DocumentRequest $req) {
            return [
                'id' => $req->id,
                'employee_id' => $req->employee_id,
                'employee_name' => $req->employee->profile->full_name ?? 'N/A',
                'employee_number' => $req->employee->employee_number ?? null,
                'department' => $req->employee?->department?->name ?? 'N/A',
                'document_type' => $this->formatDocumentType($req->document_type),
                'purpose' => $req->purpose ?? 'Not specified',
                'priority' => $this->calculatePriority($req),
                'status' => $req->status,
                'requested_at' => $req->requested_at?->format('Y-m-d H:i:s'),
                'processed_by' => $req->processedBy?->name,
                'processed_at' => $req->processed_at?->format('Y-m-d H:i:s'),
                'generated_document_path' => $req->file_path,
                'rejection_reason' => $req->rejection_reason,
            ];
        });

        // Statistics (use model scopes where available)
        $statistics = [
            'pending' => DocumentRequest::pending()->count(),
            'processing' => DocumentRequest::where('status', 'processing')->count(),
            'completed' => DocumentRequest::processed()->count(),
            'rejected' => DocumentRequest::rejected()->count(),
        ];

        // Log security audit
        $this->logAudit(
            'document_requests.view',
            'info',
            ['filters' => $request->only(['status', 'document_type', 'date_from', 'date_to', 'search'])]
        );

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'data' => $requests->values(),
                'meta' => $statistics,
                'filters' => $request->only(['status', 'document_type', 'date_from', 'date_to', 'search']),
            ]);
        }

        return Inertia::render('HR/Documents/Requests/Index', [
            'requests' => $requests->values(),
            'statistics' => $statistics,
            'filters' => $request->only(['status', 'document_type', 'date_from', 'date_to', 'search']),
        ]);
    }

    /**
     * Convert stored document type to human friendly label
     */
    private function formatDocumentType(string $type): string
    {
        return match($type) {
            'certificate_of_employment' => 'Certificate of Employment',
            'bir_form_2316' => 'BIR Form 2316',
            'government_compliance' => 'SSS/PhilHealth/Pag-IBIG Contribution',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Determine priority based on document type and age
     */
    private function calculatePriority(DocumentRequest $request): string
    {
        // Urgent: government compliance or pending >= 3 days
        if ($request->document_type === 'government_compliance') {
            return 'urgent';
        }

        if ($request->requested_at instanceof \Illuminate\Support\Carbon) {
            $daysPending = $request->requested_at->diffInDays(now());
        } else {
            try {
                $daysPending = \Illuminate\Support\Carbon::parse($request->requested_at)->diffInDays(now());
            } catch (\Throwable $e) {
                $daysPending = 0;
            }
        }

        if ($daysPending >= 3) {
            return 'urgent';
        }

        // High: BIR forms or pending >= 1 day
        if ($request->document_type === 'bir_form_2316' || $daysPending >= 1) {
            return 'high';
        }

        return 'normal';
    }

    /**
     * Return detailed request data for the request details modal.
     */
    public function show(Request $request, int $id)
    {
        $documentRequest = DocumentRequest::with(['employee.profile', 'employee.department', 'processedBy'])
            ->findOrFail($id);

        $employeeId = $documentRequest->employee_id;

        $history = DocumentRequest::query()
            ->where('employee_id', $employeeId)
            ->orderBy('requested_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function (DocumentRequest $item) {
                return [
                    'id' => $item->id,
                    'document_type' => $this->formatDocumentType($item->document_type),
                    'status' => $item->status,
                    'requested_at' => $item->requested_at?->format('Y-m-d H:i:s'),
                    'processed_by' => $item->processedBy?->name,
                    'downloaded_at' => null,
                ];
            })
            ->values();

        $employeeRequests = DocumentRequest::query()->where('employee_id', $employeeId);
        $totalRequests = (clone $employeeRequests)->count();
        $processedCount = (clone $employeeRequests)->whereIn('status', ['processed', 'completed'])->count();
        $avgMinutes = (int) round(
            DocumentRequest::query()
                ->where('employee_id', $employeeId)
                ->whereNotNull('processed_at')
                ->whereNotNull('requested_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (processed_at - requested_at)) / 60) as avg_minutes')
                ->value('avg_minutes') ?? 0
        );

        $mostRequestedType = (string) (DocumentRequest::query()
            ->where('employee_id', $employeeId)
            ->selectRaw('document_type, COUNT(*) as aggregate')
            ->groupBy('document_type')
            ->orderByDesc('aggregate')
            ->value('document_type') ?? $documentRequest->document_type);

        $statistics = [
            'total_requests' => $totalRequests,
            'most_requested_type' => $this->formatDocumentType($mostRequestedType),
            'average_processing_time_minutes' => $avgMinutes,
            'success_rate_percentage' => $totalRequests > 0
                ? round(($processedCount / $totalRequests) * 100, 2)
                : 0,
        ];

        $auditTrail = collect();
        if (\Schema::hasTable('activity_logs')) {
            $auditTrail = \DB::table('activity_logs')
                ->where('subject_type', 'App\\Models\\DocumentRequest')
                ->where('subject_id', $documentRequest->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->event ?? 'updated',
                        'description' => $log->description ?? ucfirst((string) ($log->event ?? 'updated')),
                        'timestamp' => $log->created_at,
                        'user' => 'System',
                        'event_type' => $log->event ?? 'updated',
                    ];
                })
                ->values();
        }

        $data = [
            'id' => $documentRequest->id,
            'employee_id' => $documentRequest->employee_id,
            'employee_name' => $documentRequest->employee?->profile?->full_name ?? 'N/A',
            'employee_number' => $documentRequest->employee?->employee_number,
            'department' => $documentRequest->employee?->department?->name ?? 'N/A',
            'position' => $documentRequest->employee?->position?->title,
            'email' => $documentRequest->employee?->profile?->email,
            'phone' => $documentRequest->employee?->profile?->mobile,
            'date_hired' => $documentRequest->employee?->date_hired?->format('Y-m-d'),
            'employment_status' => $documentRequest->employee?->status,
            'document_type' => $this->formatDocumentType($documentRequest->document_type),
            'purpose' => $documentRequest->purpose ?? 'Not specified',
            'priority' => $this->calculatePriority($documentRequest),
            'status' => $documentRequest->status,
            'requested_at' => $documentRequest->requested_at?->format('Y-m-d H:i:s'),
            'processed_by' => $documentRequest->processedBy?->name,
            'processed_at' => $documentRequest->processed_at?->format('Y-m-d H:i:s'),
            'generated_document_path' => $documentRequest->file_path,
            'rejection_reason' => $documentRequest->rejection_reason,
        ];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'data' => $data,
                'history' => $history,
                'statistics' => $statistics,
                'audit_trail' => $auditTrail,
            ]);
        }

        return redirect()->route('hr.documents.requests.index');
    }

    /**
     * Process a document request (approve or reject). Keeps backward compatibility
     * for clients that post a single `action` field.
     */
    public function process(Request $request, $id)
    {
        $action = $request->input('action');

        if ($action === 'approve') {
            // Validate using the same rules as ApproveDocumentRequest
            $validated = $request->validate([
                'template_id' => 'nullable|integer|exists:document_templates,id',
                'notes' => 'nullable|string|max:1000',
                'send_email' => 'boolean',
                'effective_date' => 'nullable|date',
                'expiry_date' => 'nullable|date|after:effective_date',
            ]);

            $documentRequest = DocumentRequest::with('employee.profile')->findOrFail($id);
            return $this->performApprove($documentRequest, $validated);
        } elseif ($action === 'reject') {
            $validated = $request->validate([
                'rejection_reason' => 'required|string|min:10|max:500',
                'notes' => 'nullable|string|max:1000',
                'send_email' => 'boolean',
            ]);

            $documentRequest = DocumentRequest::with('employee.profile')->findOrFail($id);
            return $this->performReject($documentRequest, $validated);
        }

        return redirect()->route('hr.documents.requests.index')
            ->with('error', 'Invalid action');
    }

    /**
     * Approve document request (route entrypoint using FormRequest).
     */
    public function approve(ApproveDocumentRequest $request, $id)
    {
        $validated = $request->validated();

        $documentRequest = DocumentRequest::with('employee.profile')->findOrFail($id);
        return $this->performApprove($documentRequest, $validated);
    }

    /**
     * Perform the approve logic for a single DocumentRequest.
     */
    private function performApprove(DocumentRequest $documentRequest, array $validated)
    {
        DB::beginTransaction();

        try {
            if ($documentRequest->status !== 'pending') {
                return back()->with('error', 'This request has already been processed');
            }

            $selectedTemplate = null;
            if (!empty($validated['template_id'])) {
                $selectedTemplate = DocumentTemplate::query()
                    ->where('id', (int) $validated['template_id'])
                    ->where('status', 'approved')
                    ->first();

                if (!$selectedTemplate) {
                    return back()->with('error', 'Selected template is not available or not approved');
                }
            }

            $processingNotes = trim((string) ($validated['notes'] ?? ''));
            if ($selectedTemplate) {
                $templateNote = "Template: {$selectedTemplate->name} (#{$selectedTemplate->id})";
                $processingNotes = $processingNotes !== ''
                    ? "{$processingNotes}\n{$templateNote}"
                    : $templateNote;
            }

            // Generate document
            $generator = new DocumentGeneratorService();
            $filePath = $generator->generate($documentRequest);

            // Update request status
            $documentRequest->process(
                auth()->user(),
                $filePath,
                $processingNotes !== '' ? $processingNotes : null
            );

            // Store document in employee_documents table for future access
            DB::table('employee_documents')->insert([
                'employee_id' => $documentRequest->employee_id,
                'document_type' => $documentRequest->document_type,
                'document_category' => $this->getDocumentCategory($documentRequest->document_type),
                'file_path' => $filePath,
                'file_name' => basename($filePath),
                'mime_type' => 'application/pdf',
                'file_size' => Storage::size($filePath),
                'uploaded_by' => auth()->id(),
                'status' => 'approved',
                'notes' => "Generated from document request #{$documentRequest->id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Send notification
            if ($validated['send_email'] ?? true) {
                $documentRequest->employee->user->notify(
                    new DocumentRequestProcessed($documentRequest, 'approved', $filePath)
                );
                $documentRequest->markEmployeeNotified();
            }

            // Log audit
            $this->logAudit(
                'document_request.approved',
                'info',
                [
                    'request_id' => $documentRequest->id,
                    'employee_id' => $documentRequest->employee_id,
                    'document_type' => $documentRequest->document_type,
                    'file_path' => $filePath,
                    'template_id' => $selectedTemplate?->id,
                    'template_name' => $selectedTemplate?->name,
                ]
            );

            DB::commit();

            return redirect()->route('hr.documents.requests.index')
                ->with('success', "Document request approved and {$documentRequest->document_type} generated successfully");

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Document request approval failed', [
                'request_id' => $documentRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to approve document request: ' . $e->getMessage());
        }
    }

    /**
     * Reject document request (route entrypoint using FormRequest).
     */
    public function reject(RejectDocumentRequest $request, $id)
    {
        $validated = $request->validated();

        $documentRequest = DocumentRequest::with('employee.profile')->findOrFail($id);
        return $this->performReject($documentRequest, $validated);
    }

    /**
     * Perform the reject logic for a single DocumentRequest.
     */
    private function performReject(DocumentRequest $documentRequest, array $validated)
    {
        DB::beginTransaction();

        try {
            if ($documentRequest->status !== 'pending') {
                return back()->with('error', 'This request has already been processed');
            }

            // Reject request
            $documentRequest->reject(
                auth()->user(),
                $validated['rejection_reason'],
                $validated['notes'] ?? null
            );

            // Send notification
            if ($validated['send_email'] ?? true) {
                $documentRequest->employee->user->notify(
                    new DocumentRequestProcessed($documentRequest, 'rejected')
                );
                $documentRequest->markEmployeeNotified();
            }

            // Log audit
            $this->logAudit(
                'document_request.rejected',
                'warning',
                [
                    'request_id' => $documentRequest->id,
                    'employee_id' => $documentRequest->employee_id,
                    'rejection_reason' => $validated['rejection_reason'],
                ]
            );

            DB::commit();

            return redirect()->route('hr.documents.requests.index')
                ->with('success', 'Document request rejected and employee notified');

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Document request rejection failed', [
                'request_id' => $documentRequest->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to reject document request: ' . $e->getMessage());
        }
    }

    /**
     * Bulk approve document requests
     */
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'integer|exists:document_requests,id',
            'notes' => 'nullable|string|max:1000',
            'send_email' => 'boolean',
        ]);

        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($validated['request_ids'] as $requestId) {
            try {
                $documentRequest = DocumentRequest::with('employee.profile')->findOrFail($requestId);
                $this->performApprove($documentRequest, [
                    'notes' => $validated['notes'] ?? null,
                    'send_email' => $validated['send_email'] ?? true,
                ]);
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                $errors[] = "Request #{$requestId}: {$e->getMessage()}";
            }
        }

        $message = "Bulk approve completed: {$successCount} succeeded";
        if ($failCount > 0) {
            $message .= ", {$failCount} failed";
        }

        return redirect()->route('hr.documents.requests.index')
            ->with($failCount > 0 ? 'warning' : 'success', $message)
            ->with('bulk_errors', $errors);
    }

    /**
     * Bulk reject document requests
     */
    public function bulkReject(Request $request)
    {
        $validated = $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'integer|exists:document_requests,id',
            'rejection_reason' => 'required|string|max:500|min:10',
            'notes' => 'nullable|string|max:1000',
            'send_email' => 'boolean',
        ]);

        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($validated['request_ids'] as $requestId) {
            try {
                $documentRequest = DocumentRequest::with('employee.profile')->findOrFail($requestId);
                $this->performReject($documentRequest, [
                    'rejection_reason' => $validated['rejection_reason'],
                    'notes' => $validated['notes'] ?? null,
                    'send_email' => $validated['send_email'] ?? true,
                ]);
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                $errors[] = "Request #{$requestId}: {$e->getMessage()}";
            }
        }

        $message = "Bulk reject completed: {$successCount} rejected";
        if ($failCount > 0) {
            $message .= ", {$failCount} failed";
        }

        return redirect()->route('hr.documents.requests.index')
            ->with($failCount > 0 ? 'warning' : 'success', $message)
            ->with('bulk_errors', $errors);
    }

    /**
     * Get document category for storage
     */
    private function getDocumentCategory(string $type): string
    {
        return match($type) {
            'certificate_of_employment' => 'employment',
            'bir_form_2316' => 'government',
            'government_compliance' => 'government',
            default => 'special',
        };
    }
}