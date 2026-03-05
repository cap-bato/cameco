# HR Document Requests System - Implementation Plan

**Purpose:** Replace mock data in HR Document Requests page with real backend implementation, including database integration, document generation, approval workflow, and employee notifications.

**Status:** 🔄 IN PROGRESS  
**Created:** 2026-03-05  
**Last Updated:** 2026-03-05

---

## 📋 Executive Summary

The HR Document Requests page (`/hr/documents/requests`) currently displays mock data. This implementation will:
1. Connect to the `document_requests` table with real queries
2. Implement approve/reject workflow with document generation
3. Add PDF generation for COE, payslip retrieval, BIR forms
4. Implement notification system for employees and HR staff
5. Add audit logging and security controls

**Current State:**
- ✅ Frontend UI complete with filters, sorting, actions
- ✅ Routes defined in `routes/hr.php`
- ✅ DocumentRequest model exists with relationships
- ✅ Employee request submission working
- ❌ HR controller uses mock data
- ❌ Process method not implemented
- ❌ No document generation logic
- ❌ No notifications

---

## 🎯 Goals & Acceptance Criteria

### Primary Goals
1. **Real Data Integration:** Fetch document requests from database with proper relationships
2. **Approval Workflow:** Implement approve/reject with document generation
3. **Document Generation:** Create PDFs for COE, fetch payslips, generate compliance docs
4. **Notifications:** Email/in-app notifications for request status changes
5. **Audit Trail:** Log all actions with security auditing

### Acceptance Criteria
- [ ] HR can view all pending document requests with employee details
- [ ] Filters work for status, document type, date range, priority
- [ ] Approve action generates requested document and notifies employee
- [ ] Reject action saves reason and notifies employee
- [ ] Generated documents stored in `storage/app/documents/generated/`
- [ ] Email sent to employee when request is processed
- [ ] Statistics (pending, processing, completed, rejected) are accurate
- [ ] Bulk approve/reject for multiple requests
- [ ] Search by employee name/number works
- [ ] Download generated documents from requests list
- [ ] Audit log captures all request processing actions

---

## 📊 Current Code Analysis

### Existing Files
```
app/
  Http/
    Controllers/
      HR/
        Documents/
          DocumentRequestController.php         # Uses mock data, needs real implementation
    Requests/
      HR/
        Documents/
          ApproveDocumentRequest.php            # Exists but needs completion
          RejectDocumentRequest.php             # Exists but needs completion
  Models/
    DocumentRequest.php                         # Complete with relationships & methods
resources/
  js/
    pages/
      HR/
        Documents/
          Requests/
            Index.tsx                            # Frontend complete, expects real data
```

### Database Schema
**Table: `document_requests`**
```sql
- id
- employee_id (FK → employees)
- document_type (enum: certificate_of_employment, payslip, bir_form_2316, government_compliance)
- purpose (text, nullable)
- period (string, nullable, for payslips)
- request_source (enum: employee_portal, manual, email)
- requested_at (timestamp)
- status (enum: pending, processing, completed, rejected)
- processed_by (FK → users, nullable)
- processed_at (timestamp, nullable)
- file_path (string, nullable, path to generated document)
- notes (text, nullable, HR notes)
- rejection_reason (text, nullable)
- employee_notified_at (timestamp, nullable)
- created_at, updated_at
```

### Model Relationships (DocumentRequest.php)
```php
- employee() → belongsTo(Employee)
- processedBy() → belongsTo(User)
- Methods: process(), reject(), markEmployeeNotified()
- Scopes: pending(), processed(), rejected(), forEmployee(), byType()
```

---

## 📝 Implementation Plan

### Phase 1: Database Integration & Query Optimization
**Objective:** Replace mock data with real database queries

#### Task 1.1: Update DocumentRequestController@index
**File:** `app/Http/Controllers/HR/Documents/DocumentRequestController.php`

**Changes:**
1. Replace mock data array with eloquent query:
```php
$query = DocumentRequest::with(['employee.profile', 'processedBy'])
    ->select('document_requests.*')
    ->join('employees', 'employees.id', '=', 'document_requests.employee_id')
    ->join('employee_profiles', 'employee_profiles.employee_id', '=', 'employees.id')
    ->leftJoin('users', 'users.id', '=', 'document_requests.processed_by')
    ->orderByRaw("FIELD(status, 'pending', 'processing', 'completed', 'rejected')")
    ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal')")  // Add priority column
    ->orderBy('requested_at', 'desc');
```

2. Apply filters:
```php
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
        $q->where('employees.employee_number', 'like', "%{$search}%")
          ->orWhereRaw("CONCAT(employee_profiles.first_name, ' ', employee_profiles.last_name) LIKE ?", ["%{$search}%"]);
    });
}
```

3. Calculate statistics:
```php
$statistics = [
    'pending' => DocumentRequest::pending()->count(),
    'processing' => DocumentRequest::where('status', 'processing')->count(),
    'completed' => DocumentRequest::processed()->count(),
    'rejected' => DocumentRequest::rejected()->count(),
];
```

4. Map results to frontend format:
```php
$requests = $query->get()->map(function ($request) {
    return [
        'id' => $request->id,
        'employee_id' => $request->employee_id,
        'employee_name' => $request->employee->profile->full_name ?? 'N/A',
        'employee_number' => $request->employee->employee_number,
        'department' => $request->employee->department_name ?? 'N/A',
        'document_type' => $this->formatDocumentType($request->document_type),
        'purpose' => $request->purpose ?? 'Not specified',
        'priority' => $this->calculatePriority($request),
        'status' => $request->status,
        'requested_at' => $request->requested_at->format('Y-m-d H:i:s'),
        'processed_by' => $request->processedBy?->name,
        'processed_at' => $request->processed_at?->format('Y-m-d H:i:s'),
        'generated_document_path' => $request->file_path,
        'rejection_reason' => $request->rejection_reason,
    ];
});
```

5. Add helper methods:
```php
private function formatDocumentType(string $type): string
{
    return match($type) {
        'certificate_of_employment' => 'Certificate of Employment',
        'payslip' => 'Payslip',
        'bir_form_2316' => 'BIR Form 2316',
        'government_compliance' => 'SSS/PhilHealth/Pag-IBIG Contribution',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}

private function calculatePriority(DocumentRequest $request): string
{
    // Urgent: Government compliance or pending > 3 days
    if ($request->document_type === 'government_compliance') {
        return 'urgent';
    }
    
    $daysPending = $request->requested_at->diffInDays(now());
    if ($daysPending >= 3) {
        return 'urgent';
    }
    
    // High: BIR forms or pending > 1 day
    if ($request->document_type === 'bir_form_2316' || $daysPending >= 1) {
        return 'high';
    }
    
    return 'normal';
}
```

**Testing:**
- [ ] Page loads without errors
- [ ] All pending requests visible
- [ ] Filters work correctly
- [ ] Search finds employees by name/number
- [ ] Statistics match database counts
- [ ] Priority calculated correctly

---

### Phase 2: Form Request Validators
**Objective:** Create validation classes for approve/reject actions

#### Task 2.1: Complete ApproveDocumentRequest
**File:** `app/Http/Requests/HR/Documents/ApproveDocumentRequest.php`

```php
<?php

namespace App\Http\Requests\HR\Documents;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.documents.requests.approve');
    }

    public function rules(): array
    {
        return [
            'template_id' => 'nullable|integer|exists:document_templates,id',
            'notes' => 'nullable|string|max:1000',
            'send_email' => 'boolean',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:effective_date',
        ];
    }

    public function messages(): array
    {
        return [
            'template_id.exists' => 'The selected template does not exist.',
            'expiry_date.after' => 'Expiry date must be after effective date.',
        ];
    }
}
```

#### Task 2.2: Complete RejectDocumentRequest
**File:** `app/Http/Requests/HR/Documents/RejectDocumentRequest.php`

```php
<?php

namespace App\Http\Requests\HR\Documents;

use Illuminate\Foundation\Http\FormRequest;

class RejectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.documents.requests.reject');
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|max:500|min:10',
            'notes' => 'nullable|string|max:1000',
            'send_email' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Please provide a reason for rejection.',
            'rejection_reason.min' => 'Rejection reason must be at least 10 characters.',
        ];
    }
}
```

**Testing:**
- [ ] Validation fails with missing data
- [ ] Authorization checked for permissions
- [ ] Custom error messages display correctly

---

### Phase 3: Document Generation Service
**Objective:** Create service for generating PDF documents

#### Task 3.1: Create DocumentGeneratorService
**File:** `app/Services/DocumentGeneratorService.php`

```php
<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\DocumentRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DocumentGeneratorService
{
    /**
     * Generate document based on request type
     */
    public function generate(DocumentRequest $request): string
    {
        return match($request->document_type) {
            'certificate_of_employment' => $this->generateCOE($request),
            'payslip' => $this->generatePayslip($request),
            'bir_form_2316' => $this->generateBIR2316($request),
            'government_compliance' => $this->generateGovernmentCompliance($request),
            default => throw new \Exception("Unknown document type: {$request->document_type}"),
        };
    }

    /**
     * Generate Certificate of Employment
     */
    private function generateCOE(DocumentRequest $request): string
    {
        $employee = $request->employee;
        
        $data = [
            'employee' => $employee,
            'profile' => $employee->profile,
            'employment' => $employee->employment,
            'position' => $employee->employment?->position ?? 'N/A',
            'department' => $employee->department_name ?? 'N/A',
            'hire_date' => $employee->employment?->hire_date?->format('F d, Y') ?? 'N/A',
            'generated_date' => Carbon::now()->format('F d, Y'),
            'purpose' => $request->purpose ?? 'Whom it may concern',
        ];

        $pdf = Pdf::loadView('documents.certificate-of-employment', $data);
        
        $filename = "COE_{$employee->employee_number}_" . Carbon::now()->format('Ymd_His') . ".pdf";
        $path = "documents/generated/coe/{$filename}";
        
        Storage::put($path, $pdf->output());
        
        return $path;
    }

    /**
     * Generate or fetch Payslip
     */
    private function generatePayslip(DocumentRequest $request): string
    {
        $employee = $request->employee;
        $period = $request->period; // Format: "01-2024" (month-year)
        
        // TODO: Fetch actual payslip from payroll system
        // For now, generate a basic payslip
        
        [$month, $year] = explode('-', $period);
        
        $data = [
            'employee' => $employee,
            'profile' => $employee->profile,
            'period' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
            'generated_date' => Carbon::now()->format('F d, Y'),
            // TODO: Add salary data from payroll
        ];

        $pdf = Pdf::loadView('documents.payslip', $data);
        
        $filename = "Payslip_{$employee->employee_number}_{$period}.pdf";
        $path = "documents/generated/payslips/{$filename}";
        
        Storage::put($path, $pdf->output());
        
        return $path;
    }

    /**
     * Generate BIR Form 2316
     */
    private function generateBIR2316(DocumentRequest $request): string
    {
        $employee = $request->employee;
        $year = Carbon::now()->year - 1; // Previous year for tax filing
        
        // TODO: Fetch tax data from payroll system
        
        $data = [
            'employee' => $employee,
            'profile' => $employee->profile,
            'year' => $year,
            'generated_date' => Carbon::now()->format('F d, Y'),
            // TODO: Add tax computation data
        ];

        $pdf = Pdf::loadView('documents.bir-form-2316', $data);
        
        $filename = "BIR2316_{$employee->employee_number}_{$year}.pdf";
        $path = "documents/generated/bir/{$filename}";
        
        Storage::put($path, $pdf->output());
        
        return $path;
    }

    /**
     * Generate Government Compliance Document
     */
    private function generateGovernmentCompliance(DocumentRequest $request): string
    {
        $employee = $request->employee;
        
        // TODO: Fetch contribution data from system
        
        $data = [
            'employee' => $employee,
            'profile' => $employee->profile,
            'generated_date' => Carbon::now()->format('F d, Y'),
            'purpose' => $request->purpose ?? 'Loan application',
            // TODO: Add SSS, PhilHealth, Pag-IBIG data
        ];

        $pdf = Pdf::loadView('documents.government-compliance', $data);
        
        $filename = "GovCompliance_{$employee->employee_number}_" . Carbon::now()->format('Ymd') . ".pdf";
        $path = "documents/generated/government/{$filename}";
        
        Storage::put($path, $pdf->output());
        
        return $path;
    }
}
```

**Testing:**
- [ ] COE generates with employee data
- [ ] Payslip generates for specified period
- [ ] BIR form generates with tax data
- [ ] Government compliance doc generates
- [ ] Files stored in correct directories
- [ ] Filenames are unique and descriptive

---

### Phase 4: Process Request Implementation
**Objective:** Implement approve/reject workflow in controller

#### Task 4.1: Update DocumentRequestController@process
**File:** `app/Http/Controllers/HR/Documents/DocumentRequestController.php`

```php
use App\Http\Requests\HR\Documents\ApproveDocumentRequest;
use App\Http\Requests\HR\Documents\RejectDocumentRequest;
use App\Services\DocumentGeneratorService;
use App\Notifications\DocumentRequestProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

public function process(Request $request, $id)
{
    $action = $request->input('action');
    
    if ($action === 'approve') {
        return $this->approve($request, $id);
    } else if ($action === 'reject') {
        return $this->reject($request, $id);
    }
    
    return redirect()->route('hr.documents.requests.index')
        ->with('error', 'Invalid action');
}

/**
 * Approve document request
 */
private function approve(Request $request, $id)
{
    $validated = $request->validate([
        'template_id' => 'nullable|integer',
        'notes' => 'nullable|string|max:1000',
        'send_email' => 'boolean',
    ]);
    
    DB::beginTransaction();
    
    try {
        $documentRequest = DocumentRequest::with('employee.profile')->findOrFail($id);
        
        if ($documentRequest->status !== 'pending') {
            return back()->with('error', 'This request has already been processed');
        }
        
        // Generate document
        $generator = new DocumentGeneratorService();
        $filePath = $generator->generate($documentRequest);
        
        // Update request status
        $documentRequest->process(
            auth()->user(),
            $filePath,
            $validated['notes'] ?? null
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
            ]
        );
        
        DB::commit();
        
        return redirect()->route('hr.documents.requests.index')
            ->with('success', "Document request approved and {$documentRequest->document_type} generated successfully");
            
    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('Document request approval failed', [
            'request_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return back()->with('error', 'Failed to approve document request: ' . $e->getMessage());
    }
}

/**
 * Reject document request
 */
private function reject(Request $request, $id)
{
    $validated = $request->validate([
        'rejection_reason' => 'required|string|max:500|min:10',
        'notes' => 'nullable|string|max:1000',
        'send_email' => 'boolean',
    ]);
    
    DB::beginTransaction();
    
    try {
        $documentRequest = DocumentRequest::with('employee.profile')->findOrFail($id);
        
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
            'request_id' => $id,
            'error' => $e->getMessage(),
        ]);
        
        return back()->with('error', 'Failed to reject document request: ' . $e->getMessage());
    }
}

/**
 * Get document category for storage
 */
private function getDocumentCategory(string $type): string
{
    return match($type) {
        'certificate_of_employment' => 'employment',
        'payslip' => 'employment',
        'bir_form_2316' => 'government',
        'government_compliance' => 'government',
        default => 'special',
    };
}
```

**Testing:**
- [ ] Approve generates document and updates status
- [ ] Reject saves reason and notifies employee
- [ ] Already processed requests cannot be re-processed
- [ ] Transactions rollback on error
- [ ] Audit logs created for both actions

---

### Phase 5: Notification System
**Objective:** Create notification for employees when requests are processed

#### Task 5.1: Create DocumentRequestProcessed Notification
**File:** `app/Notifications/DocumentRequestProcessed.php`

```php
<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentRequestProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private DocumentRequest $documentRequest,
        private string $status,
        private ?string $filePath = null
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $documentType = $this->formatDocumentType($this->documentRequest->document_type);
        
        if ($this->status === 'approved') {
            return (new MailMessage)
                ->subject("Document Request Approved - {$documentType}")
                ->greeting("Hello {$notifiable->name},")
                ->line("Your document request for **{$documentType}** has been approved.")
                ->line("You can now download the document from your employee portal.")
                ->action('View My Documents', url('/employee/documents'))
                ->line('Thank you for using our system!');
        } else {
            return (new MailMessage)
                ->subject("Document Request Update - {$documentType}")
                ->greeting("Hello {$notifiable->name},")
                ->line("Your document request for **{$documentType}** could not be processed.")
                ->line("**Reason:** {$this->documentRequest->rejection_reason}")
                ->line('Please contact HR if you have questions or need to submit a new request.')
                ->action('Contact HR', url('/employee/support'));
        }
    }

    public function toArray($notifiable): array
    {
        return [
            'document_request_id' => $this->documentRequest->id,
            'document_type' => $this->documentRequest->document_type,
            'status' => $this->status,
            'file_path' => $this->filePath,
            'processed_by' => auth()->user()?->name,
            'processed_at' => now()->toDateTimeString(),
        ];
    }

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
}
```

**Testing:**
- [ ] Email sent to employee after approval
- [ ] Email sent to employee after rejection
- [ ] Database notification created
- [ ] Queue processes notification
- [ ] Notification includes download link

---

### Phase 6: PDF Templates
**Objective:** Create Blade templates for PDF generation

#### Task 6.1: Create COE Template
**File:** `resources/views/documents/certificate-of-employment.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate of Employment</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 40px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .company-address {
            font-size: 12px;
            color: #666;
        }
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 40px 0 30px 0;
            text-decoration: underline;
        }
        .content {
            text-align: justify;
            margin: 20px 0;
        }
        .signature-section {
            margin-top: 60px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ config('app.company_name', 'Company Name') }}</div>
        <div class="company-address">{{ config('app.company_address', 'Company Address') }}</div>
    </div>

    <div class="title">CERTIFICATE OF EMPLOYMENT</div>

    <p><strong>Date Issued:</strong> {{ $generated_date }}</p>

    <div class="content">
        <p>TO WHOM IT MAY CONCERN:</p>

        <p>This is to certify that <strong>{{ $profile->full_name ?? 'N/A' }}</strong> with employee number <strong>{{ $employee->employee_number }}</strong> has been employed with this company since <strong>{{ $hire_date }}</strong>.</p>

        <p>During the period of employment, {{ $profile->first_name ?? 'the employee' }} held the position of <strong>{{ $position }}</strong> in the <strong>{{ $department }}</strong> department.</p>

        <p>This certification is issued upon the request of the above-named employee for <strong>{{ $purpose }}</strong>.</p>

        <p>Issued this {{ $generated_date }}.</p>
    </div>

    <div class="signature-section">
        <div class="signature-line"></div>
        <p><strong>HR Manager</strong><br>Human Resources Department</p>
    </div>
</body>
</html>
```

#### Task 6.2: Create Additional Templates
**Files:**
- `resources/views/documents/payslip.blade.php`
- `resources/views/documents/bir-form-2316.blade.php`
- `resources/views/documents/government-compliance.blade.php`

(Similar structure with document-specific data)

**Testing:**
- [ ] PDF renders correctly with company branding
- [ ] Employee data populated correctly
- [ ] Formatting looks professional
- [ ] No missing data warnings

---

### Phase 7: Bulk Actions
**Objective:** Add ability to approve/reject multiple requests at once

#### Task 7.1: Add Bulk Approve/Reject Methods
**File:** `app/Http/Controllers/HR/Documents/DocumentRequestController.php`

```php
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
            $this->approve(new Request([
                'notes' => $validated['notes'] ?? null,
                'send_email' => $validated['send_email'] ?? true,
            ]), $requestId);
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
    
    foreach ($validated['request_ids'] as $requestId) {
        try {
            $this->reject(new Request([
                'rejection_reason' => $validated['rejection_reason'],
                'notes' => $validated['notes'] ?? null,
                'send_email' => $validated['send_email'] ?? true,
            ]), $requestId);
            $successCount++;
        } catch (\Exception $e) {
            $failCount++;
        }
    }
    
    return redirect()->route('hr.documents.requests.index')
        ->with('success', "Bulk reject completed: {$successCount} rejected, {$failCount} failed");
}
```

#### Task 7.2: Add Routes for Bulk Actions
**File:** `routes/hr.php`

```php
Route::prefix('requests')->name('requests.')->group(function () {
    // ... existing routes ...
    
    Route::post('/bulk-approve', [DocumentRequestController::class, 'bulkApprove'])
        ->middleware('permission:hr.documents.requests.approve')
        ->name('bulk-approve');
    
    Route::post('/bulk-reject', [DocumentRequestController::class, 'bulkReject'])
        ->middleware('permission:hr.documents.requests.reject')
        ->name('bulk-reject');
});
```

**Testing:**
- [ ] Multiple requests can be approved at once
- [ ] Multiple requests can be rejected at once
- [ ] Partial failures handled gracefully
- [ ] Error messages informative

---

## 🧪 Testing Checklist

### Unit Tests
- [ ] DocumentRequest model methods
- [ ] DocumentGeneratorService methods
- [ ] Helper methods (priority calculation, formatting)

### Integration Tests
- [ ] Approve workflow end-to-end
- [ ] Reject workflow end-to-end
- [ ] Notification delivery
- [ ] PDF generation and storage

### Manual Testing
- [ ] HR sees real pending requests
- [ ] Filters and search work
- [ ] Statistics are accurate
- [ ] Approve generates document
- [ ] Reject notifies employee
- [ ] Bulk actions work
- [ ] Audit logs created
- [ ] Employee receives email
- [ ] Employee can download generated doc

---

## 📦 Dependencies

### Composer Packages
```bash
composer require barryvdh/laravel-dompdf
```

### Configuration
**File:** `config/dompdf.php`
```php
return [
    'public_path' => null,
    'font_dir' => storage_path('fonts/'),
    'font_cache' => storage_path('fonts/'),
    'temp_dir' => sys_get_temp_dir(),
    'chroot' => realpath(base_path()),
    'enable_font_subsetting' => false,
    'pdf_backend' => 'CPDF',
    'default_media_type' => 'screen',
    'default_paper_size' => 'a4',
    'default_font' => 'serif',
    'dpi' => 96,
    'enable_php' => false,
    'enable_javascript' => true,
    'enable_remote' => true,
    'font_height_ratio' => 1.1,
    'enable_html5_parser' => false,
];
```

---

## 🔐 Security Considerations

1. **Authorization:** Check `hr.documents.requests.view`, `approve`, `reject` permissions
2. **Validation:** Use FormRequest classes for all inputs
3. **Audit Logging:** Log all approve/reject actions with user ID and timestamp
4. **File Storage:** Store generated documents in private storage (not publicly accessible)
5. **Download Protection:** Verify employee owns document before allowing download
6. **SQL Injection:** Use Eloquent/Query Builder, never raw queries with user input
7. **XSS Protection:** Sanitize all user input displayed in PDFs

---

## 📊 Performance Optimization

1. **Eager Loading:** Load `employee.profile`, `processedBy` relationships to avoid N+1
2. **Indexing:** Add indexes on `status`, `document_type`, `requested_at` columns
3. **Pagination:** Limit results to 50 per page for large datasets
4. **Caching:** Cache statistics for 5 minutes to reduce queries
5. **Queue Jobs:** Process document generation in background queue for large files
6. **PDF Optimization:** Use compressed images, optimize fonts

---

## 📝 Next Steps After Completion

1. **Phase 8:** Add document templates management (HR can customize COE format)
2. **Phase 9:** Add analytics dashboard (most requested docs, average processing time)
3. **Phase 10:** Integration with payroll system for accurate salary data
4. **Phase 11:** Mobile app support for document requests
5. **Phase 12:** E-signature integration for authorized documents

---

## ✅ Completion Checklist

### Backend
- [ ] DocumentRequestController@index uses real queries
- [ ] DocumentRequestController@process implemented
- [ ] ApproveDocumentRequest validator complete
- [ ] RejectDocumentRequest validator complete
- [ ] DocumentGeneratorService created
- [ ] All PDF templates created
- [ ] DocumentRequestProcessed notification created
- [ ] Bulk approve/reject implemented
- [ ] Routes updated
- [ ] Audit logging added

### Frontend
- [ ] Frontend receives real data (already complete)
- [ ] Process modal connects to backend
- [ ] Download generated documents works
- [ ] Bulk actions UI functional

### Testing
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Manual testing complete
- [ ] Security audit passed
- [ ] Performance benchmarks met

### Documentation
- [ ] API endpoints documented
- [ ] PDF templates documented
- [ ] Notification system documented
- [ ] Admin guide for managing requests

---

**Status:** 🔄 Ready for implementation  
**Estimated Effort:** 3-4 days  
**Priority:** High  
**Dependencies:** DomPDF library, Mail configuration
