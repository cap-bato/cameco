# Employee Document Request System - Implementation Plan

**Purpose:** Enhance employee document request functionality with request history, status tracking, notifications, better UI/UX, and integration with HR approval workflow.

**Status:** 🔄 IN PROGRESS  
**Created:** 2026-03-05  
**Last Updated:** 2026-03-05

---

## 📋 Executive Summary

The employee document request system has basic functionality (request form, submission), but lacks:
1. Request history/status tracking view
2. Real-time notifications when requests are processed
3. Ability to view/download approved documents
4. Better UI integration in employee portal
5. Request cancellation feature
6. Document preview capability

**Current State:**
- ✅ Request form exists (`Employee/Documents/RequestForm.tsx`)
- ✅ Request submission working (`DocumentController@storeRequest`)
- ✅ "Request Document" button in Documents Index
- ✅ Routes configured in `routes/employee.php`
- ❌ No request history view
- ❌ No status tracking UI
- ❌ No notifications to employees
- ❌ Limited document type options
- ❌ No request cancellation
- ❌ No document preview

---

## 🎯 Goals & Acceptance Criteria

### Primary Goals
1. **Request History:** Employees can view all their document requests with status
2. **Status Tracking:** Real-time updates on request progress (pending, processing, approved, rejected)
3. **Notifications:** In-app and email notifications when requests are processed
4. **Document Access:** Quick download/preview of approved documents from request history
5. **Request Management:** Cancel pending requests, re-submit rejected ones
6. **Better UX:** Intuitive UI with clear guidance and feedback

### Acceptance Criteria
- [ ] Employee dashboard shows pending requests count
- [ ] Request history page lists all requests with status badges
- [ ] Status badges color-coded (pending=yellow, approved=green, rejected=red)
- [ ] Click on approved request opens document for download
- [ ] Rejected requests show rejection reason
- [ ] Cancel button for pending requests
- [ ] Re-request button for rejected requests
- [ ] Email notification when request is approved/rejected
- [ ] In-app notification badge in navigation
- [ ] Request form pre-fills data for re-submission
- [ ] Document preview modal for PDFs
- [ ] Mobile-responsive design

---

## 📊 Current Code Analysis

### Existing Files
```
app/
  Http/
    Controllers/
      Employee/
        DocumentController.php                   # Basic CRUD, needs enhancement
resources/
  js/
    pages/
      Employee/
        Documents/
          Index.tsx                              # Main documents page, has request button
          RequestForm.tsx                        # Request submission form
          (NEW) RequestHistory.tsx               # Need to create
routes/
  employee.php                                   # Routes configured
```

### Current Flow
1. Employee clicks "Request Document" button in `/employee/documents`
2. Redirected to `/employee/documents/request` (RequestForm.tsx)
3. Fills form (document type, purpose, period for payslip)
4. Submits → `DocumentController@storeRequest`
5. Record inserted in `document_requests` table
6. Redirected back to documents page with success message
7. ❌ **No way to track request status after submission**

---

## 📝 Implementation Plan

### Phase 1: Request History Backend
**Objective:** Create controller methods to fetch employee's document requests

#### Task 1.1: Add requestHistory Method
**File:** `app/Http/Controllers/Employee/DocumentController.php`

**Add method:**
```php
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
```

#### Task 1.2: Add Cancel Request Method
**File:** `app/Http/Controllers/Employee/DocumentController.php`

```php
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
```

#### Task 1.3: Add Download from Request Method
**File:** `app/Http/Controllers/Employee/DocumentController.php`

```php
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
```

#### Task 1.4: Update Routes
**File:** `routes/employee.php`

Add inside `documents` group:
```php
// Document request history
Route::get('/requests/history', [DocumentController::class, 'requestHistory'])
    ->middleware('permission:employee.documents.view')
    ->name('requests.history');

// Cancel pending request
Route::post('/requests/{requestId}/cancel', [DocumentController::class, 'cancelRequest'])
    ->middleware('permission:employee.documents.request')
    ->name('requests.cancel');

// Download document from approved request
Route::get('/requests/{requestId}/download', [DocumentController::class, 'downloadFromRequest'])
    ->middleware('permission:employee.documents.download')
    ->name('requests.download');
```

**Testing:**
- [ ] Request history page loads with real data
- [ ] Status badges display correctly
- [ ] Cancel button only shows for pending requests
- [ ] Download button only shows for approved requests
- [ ] Filters work (status, document type)
- [ ] Statistics are accurate

---

### Phase 2: Request History Frontend
**Objective:** Create React component to display request history

#### Task 2.1: Create RequestHistory Component
**File:** `resources/js/pages/Employee/Documents/RequestHistory.tsx`

```typescript
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    FileText,
    Download,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    ArrowLeft,
    RefreshCw,
    X,
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

// ============================================================================
// Type Definitions
// ============================================================================

interface DocumentRequest {
    id: number;
    document_type: string;
    document_type_key: string;
    purpose: string;
    period: string | null;
    status: string;
    status_display: {
        label: string;
        color: string;
        icon: string;
        description: string;
    };
    requested_at: string;
    processed_at: string | null;
    processed_by: string | null;
    file_path: string | null;
    rejection_reason: string | null;
    notes: string | null;
    can_cancel: boolean;
    can_resubmit: boolean;
    days_pending: number | null;
}

interface RequestHistoryProps {
    employee: {
        id: number;
        name: string;
        employee_number: string;
    };
    requests: DocumentRequest[];
    statistics: {
        total: number;
        pending: number;
        approved: number;
        rejected: number;
    };
    filters: {
        status: string;
        document_type: string;
    };
}

// ============================================================================
// Helper Functions
// ============================================================================

function getStatusIcon(iconName: string) {
    const icons: Record<string, JSX.Element> = {
        Clock: <Clock className="h-4 w-4" />,
        FileText: <FileText className="h-4 w-4" />,
        CheckCircle: <CheckCircle className="h-4 w-4" />,
        XCircle: <XCircle className="h-4 w-4" />,
    };
    return icons[iconName] || <FileText className="h-4 w-4" />;
}

function getStatusBadgeClass(color: string): string {
    const colorMap: Record<string, string> = {
        yellow: 'bg-yellow-100 text-yellow-800 border-yellow-300',
        blue: 'bg-blue-100 text-blue-800 border-blue-300',
        green: 'bg-green-100 text-green-800 border-green-300',
        red: 'bg-red-100 text-red-800 border-red-300',
        gray: 'bg-gray-100 text-gray-800 border-gray-300',
    };
    return colorMap[color] || colorMap.gray;
}

// ============================================================================
// Main Component
// ============================================================================

export default function RequestHistory({
    employee,
    requests,
    statistics,
    filters,
}: RequestHistoryProps) {
    const { toast } = useToast();
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<DocumentRequest | null>(null);

    // Handle filter changes
    const handleStatusFilter = (status: string) => {
        router.get(
            '/employee/documents/requests/history',
            { status, document_type: filters.document_type },
            { preserveState: true }
        );
    };

    const handleDocumentTypeFilter = (documentType: string) => {
        router.get(
            '/employee/documents/requests/history',
            { status: filters.status, document_type: documentType },
            { preserveState: true }
        );
    };

    // Handle cancel request
    const handleCancelClick = (request: DocumentRequest) => {
        setSelectedRequest(request);
        setCancelDialogOpen(true);
    };

    const confirmCancel = () => {
        if (!selectedRequest) return;

        router.post(
            `/employee/documents/requests/${selectedRequest.id}/cancel`,
            {},
            {
                onSuccess: () => {
                    toast({
                        title: 'Request Cancelled',
                        description: 'Your document request has been cancelled successfully.',
                    });
                    setCancelDialogOpen(false);
                    setSelectedRequest(null);
                },
                onError: () => {
                    toast({
                        title: 'Error',
                        description: 'Failed to cancel request. Please try again.',
                        variant: 'destructive',
                    });
                },
            }
        );
    };

    // Handle download
    const handleDownload = (request: DocumentRequest) => {
        window.location.href = `/employee/documents/requests/${request.id}/download`;
        toast({
            title: 'Download Started',
            description: `Downloading ${request.document_type}...`,
        });
    };

    // Handle resubmit
    const handleResubmit = (request: DocumentRequest) => {
        router.get('/employee/documents/request', {
            document_type: request.document_type_key,
            purpose: request.purpose,
            period: request.period,
        });
    };

    return (
        <AppLayout>
            <Head title="Document Request History" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            onClick={() => router.visit('/employee/documents')}
                            variant="outline"
                            size="icon"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Request History</h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Track the status of your document requests
                            </p>
                        </div>
                    </div>
                    <Button
                        onClick={() => router.visit('/employee/documents/request')}
                        className="gap-2"
                    >
                        <FileText className="h-4 w-4" />
                        New Request
                    </Button>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Total Requests</p>
                                    <p className="text-2xl font-bold mt-1">{statistics.total}</p>
                                </div>
                                <FileText className="h-8 w-8 text-blue-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Pending</p>
                                    <p className="text-2xl font-bold mt-1">{statistics.pending}</p>
                                </div>
                                <Clock className="h-8 w-8 text-yellow-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Approved</p>
                                    <p className="text-2xl font-bold mt-1">{statistics.approved}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-green-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Declined</p>
                                    <p className="text-2xl font-bold mt-1">{statistics.rejected}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-red-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm font-medium mb-2 block">Status</label>
                                <Select value={filters.status} onValueChange={handleStatusFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="pending">Pending</SelectItem>
                                        <SelectItem value="processing">Processing</SelectItem>
                                        <SelectItem value="completed">Approved</SelectItem>
                                        <SelectItem value="rejected">Declined</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <label className="text-sm font-medium mb-2 block">Document Type</label>
                                <Select
                                    value={filters.document_type}
                                    onValueChange={handleDocumentTypeFilter}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Types</SelectItem>
                                        <SelectItem value="certificate_of_employment">
                                            Certificate of Employment
                                        </SelectItem>
                                        <SelectItem value="payslip">Payslip</SelectItem>
                                        <SelectItem value="bir_form_2316">BIR Form 2316</SelectItem>
                                        <SelectItem value="government_compliance">
                                            Government Compliance
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Pending Requests Alert */}
                {statistics.pending > 0 && (
                    <Alert>
                        <Clock className="h-4 w-4" />
                        <AlertDescription>
                            You have {statistics.pending} pending request{statistics.pending > 1 ? 's' : ''}.
                            HR Staff will review and process your request shortly.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Requests List */}
                {requests.length === 0 ? (
                    <Card>
                        <CardContent className="pt-12 pb-12 text-center">
                            <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No requests found</h3>
                            <p className="text-gray-600 mb-4">
                                You haven't submitted any document requests yet.
                            </p>
                            <Button onClick={() => router.visit('/employee/documents/request')}>
                                Submit Your First Request
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {requests.map((request) => (
                            <Card key={request.id}>
                                <CardContent className="pt-6">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                <h3 className="text-lg font-semibold">
                                                    {request.document_type}
                                                </h3>
                                                <Badge
                                                    variant="outline"
                                                    className={`flex items-center gap-1 ${getStatusBadgeClass(
                                                        request.status_display.color
                                                    )}`}
                                                >
                                                    {getStatusIcon(request.status_display.icon)}
                                                    {request.status_display.label}
                                                </Badge>
                                                {request.days_pending !== null &&
                                                    request.days_pending >= 3 && (
                                                        <Badge variant="outline" className="bg-orange-100 text-orange-800">
                                                            {request.days_pending} days pending
                                                        </Badge>
                                                    )}
                                            </div>

                                            <p className="text-sm text-gray-600 mb-3">
                                                {request.status_display.description}
                                            </p>

                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <span className="font-medium">Purpose:</span>{' '}
                                                    {request.purpose}
                                                </div>
                                                {request.period && (
                                                    <div>
                                                        <span className="font-medium">Period:</span>{' '}
                                                        {request.period}
                                                    </div>
                                                )}
                                                <div>
                                                    <span className="font-medium">Requested:</span>{' '}
                                                    {request.requested_at}
                                                </div>
                                                {request.processed_at && (
                                                    <div>
                                                        <span className="font-medium">Processed:</span>{' '}
                                                        {request.processed_at}
                                                    </div>
                                                )}
                                                {request.processed_by && (
                                                    <div>
                                                        <span className="font-medium">Processed By:</span>{' '}
                                                        {request.processed_by}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Rejection Reason */}
                                            {request.rejection_reason && (
                                                <Alert variant="destructive" className="mt-4">
                                                    <AlertCircle className="h-4 w-4" />
                                                    <AlertDescription>
                                                        <strong>Reason:</strong> {request.rejection_reason}
                                                    </AlertDescription>
                                                </Alert>
                                            )}

                                            {/* HR Notes */}
                                            {request.notes && request.status !== 'rejected' && (
                                                <Alert className="mt-4">
                                                    <AlertDescription>
                                                        <strong>Note:</strong> {request.notes}
                                                    </AlertDescription>
                                                </Alert>
                                            )}
                                        </div>

                                        {/* Actions */}
                                        <div className="flex flex-col gap-2 ml-4">
                                            {request.status === 'completed' && request.file_path && (
                                                <Button
                                                    size="sm"
                                                    onClick={() => handleDownload(request)}
                                                    className="gap-2"
                                                >
                                                    <Download className="h-4 w-4" />
                                                    Download
                                                </Button>
                                            )}

                                            {request.can_cancel && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleCancelClick(request)}
                                                    className="gap-2"
                                                >
                                                    <X className="h-4 w-4" />
                                                    Cancel
                                                </Button>
                                            )}

                                            {request.can_resubmit && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleResubmit(request)}
                                                    className="gap-2"
                                                >
                                                    <RefreshCw className="h-4 w-4" />
                                                    Re-submit
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Cancel Confirmation Dialog */}
            <AlertDialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Cancel Document Request?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to cancel this request for{' '}
                            <strong>{selectedRequest?.document_type}</strong>? This action cannot be
                            undone. You can submit a new request if needed.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Keep Request</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmCancel} className="bg-red-600 hover:bg-red-700">
                            Yes, Cancel Request
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
```

**Testing:**
- [ ] Page renders with request history
- [ ] Status badges show correct colors
- [ ] Cancel dialog opens and works
- [ ] Download triggers file download
- [ ] Re-submit navigates to form with pre-filled data
- [ ] Filters update URL and data
- [ ] Empty state shows when no requests
- [ ] Responsive design works on mobile

---

### Phase 3: Navigation Integration
**Objective:** Add links to request history in employee navigation

#### Task 3.1: Update Documents Index
**File:** `resources/js/pages/Employee/Documents/Index.tsx`

Add button in header:
```typescript
<div className="flex items-center justify-between">
    <div>
        <h1 className="text-3xl font-bold tracking-tight">My Documents</h1>
        <p className="text-sm text-gray-600 mt-1">
            View and manage your employee documents
        </p>
    </div>
    <div className="flex gap-2">
        <Button
            onClick={() => router.visit('/employee/documents/requests/history')}
            variant="outline"
            className="gap-2"
        >
            <Clock className="h-4 w-4" />
            Request History
            {pendingRequests > 0 && (
                <Badge variant="destructive" className="ml-1">
                    {pendingRequests}
                </Badge>
            )}
        </Button>
        <Button onClick={handleRequestDocument} className="gap-2">
            <Plus className="h-4 w-4" />
            Request Document
        </Button>
    </div>
</div>
```

#### Task 3.2: Add Notification Badge
**File:** `resources/js/layouts/app-layout.tsx` (or navigation component)

Add pending requests count badge in employee navigation:
```typescript
{
    name: 'Documents',
    href: '/employee/documents',
    icon: FileText,
    badge: pendingDocumentRequests > 0 ? pendingDocumentRequests : null,
}
```

**Testing:**
- [ ] Request History button visible
- [ ] Pending count badge shows
- [ ] Navigation to history page works
- [ ] Badge updates when requests processed

---

### Phase 4: Notification System (Employee Side)
**Objective:** Display notifications when requests are processed

#### Task 4.1: Create Notification Component
**File:** `resources/js/components/NotificationBell.tsx`

```typescript
import { useState, useEffect } from 'react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

interface Notification {
    id: string;
    type: string;
    data: {
        document_request_id: number;
        document_type: string;
        status: string;
    };
    created_at: string;
    read_at: string | null;
}

export default function NotificationBell() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);

    useEffect(() => {
        fetchNotifications();
        
        // Poll for new notifications every 30 seconds
        const interval = setInterval(fetchNotifications, 30000);
        return () => clearInterval(interval);
    }, []);

    const fetchNotifications = async () => {
        try {
            const response = await fetch('/employee/notifications');
            const data = await response.json();
            setNotifications(data.notifications);
            setUnreadCount(data.unread_count);
        } catch (error) {
            console.error('Failed to fetch notifications', error);
        }
    };

    const markAsRead = async (notificationId: string) => {
        try {
            await fetch(`/employee/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            fetchNotifications();
        } catch (error) {
            console.error('Failed to mark notification as read', error);
        }
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs"
                        >
                            {unreadCount}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-80">
                <div className="space-y-2">
                    <h3 className="font-semibold mb-4">Notifications</h3>
                    {notifications.length === 0 ? (
                        <p className="text-sm text-gray-600 text-center py-4">
                            No notifications
                        </p>
                    ) : (
                        notifications.map((notification) => (
                            <div
                                key={notification.id}
                                className={`p-3 rounded-lg border cursor-pointer ${
                                    notification.read_at ? 'bg-white' : 'bg-blue-50'
                                }`}
                                onClick={() => {
                                    markAsRead(notification.id);
                                    window.location.href = '/employee/documents/requests/history';
                                }}
                            >
                                <p className="text-sm font-medium">
                                    Document Request {notification.data.status}
                                </p>
                                <p className="text-xs text-gray-600 mt-1">
                                    Your {notification.data.document_type} request has been {notification.data.status}
                                </p>
                                <p className="text-xs text-gray-400 mt-1">
                                    {new Date(notification.created_at).toLocaleDateString()}
                                </p>
                            </div>
                        ))
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
```

#### Task 4.2: Add Notification Endpoints
**File:** `routes/employee.php`

```php
// Notifications
Route::get('/notifications', [NotificationController::class, 'index'])
    ->name('notifications.index');

Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
    ->name('notifications.read');
```

**File:** `app/Http/Controllers/Employee/NotificationController.php` (create new)

```php
<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Auth::user()->notifications()
            ->take(10)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => Auth::user()->unreadNotifications->count(),
        ]);
    }

    public function markAsRead($notificationId)
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }
}
```

**Testing:**
- [ ] Notification bell shows in navigation
- [ ] Unread count displays correctly
- [ ] Clicking notification navigates to history
- [ ] Marking as read works
- [ ] Real-time updates work (polling)

---

## 🧪 Testing Checklist

### Backend Tests
- [ ] Request history fetches correct employee requests
- [ ] Status filters work correctly
- [ ] Document type filters work correctly
- [ ] Cancel only works for pending requests
- [ ] Download only works for completed requests
- [ ] Authorization checks pass

### Frontend Tests
- [ ] Request history page renders
- [ ] Empty state shows when no requests
- [ ] Status badges display correctly
- [ ] Actions (cancel, download, resubmit) work
- [ ] Filters update data
- [ ] Navigation breadcrumbs work
- [ ] Mobile responsive

### Integration Tests
- [ ] Employee submits request → appears in history
- [ ] HR approves request → employee sees in history
- [ ] HR rejects request → rejection reason shows
- [ ] Employee cancels request → status updates
- [ ] Employee downloads document → file downloads
- [ ] Notification sent → employee receives

---

## 📦 Dependencies

No new dependencies required. Uses existing:
- Inertia.js
- React
- shadcn/ui components
- Laravel notifications system

---

## 🔐 Security Considerations

1. **Authorization:** Employees can only view/cancel their own requests
2. **File Access:** Verify employee owns request before allowing download
3. **Status Validation:** Only pending requests can be cancelled
4. **CSRF Protection:** All POST requests include CSRF token
5. **SQL Injection:** Use Eloquent/Query Builder with parameter binding

---

## ✅ Completion Checklist

### Backend
- [ ] requestHistory method implemented
- [ ] cancelRequest method implemented
- [ ] downloadFromRequest method implemented
- [ ] Routes added
- [ ] NotificationController created
- [ ] Authorization checks added

### Frontend
- [ ] RequestHistory component created
- [ ] Documents Index updated with history button
- [ ] NotificationBell component created
- [ ] Navigation integration complete
- [ ] Mobile responsive design

### Testing
- [ ] All backend tests pass
- [ ] All frontend components render
- [ ] Integration tests pass
- [ ] Manual testing complete

### Documentation
- [ ] Employee user guide updated
- [ ] Screenshots added
- [ ] FAQ section added

---

**Status:** 🔄 Ready for implementation  
**Estimated Effort:** 2-3 days  
**Priority:** Medium  
**Dependencies:** HR document request implementation (for full workflow)
