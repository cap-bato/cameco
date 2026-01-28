<?php

namespace App\Http\Controllers\HR\Documents;

use App\Http\Controllers\Controller;
use App\Traits\LogsSecurityAudits;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentRequestController extends Controller
{
    use LogsSecurityAudits;

    /**
     * Display a listing of document requests from employees.
     */
    public function index(Request $request)
    {
        // Mock data for testing
        $requests = collect([
            [
                'id' => 1,
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP-2024-001',
                'department' => 'IT Department',
                'document_type' => 'Certificate of Employment',
                'purpose' => 'Bank loan application',
                'priority' => 'urgent',
                'status' => 'pending',
                'requested_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
                'processed_by' => null,
                'processed_at' => null,
            ],
            [
                'id' => 2,
                'employee_id' => 2,
                'employee_name' => 'Maria Santos',
                'employee_number' => 'EMP-2024-002',
                'department' => 'HR Department',
                'document_type' => 'Payslip',
                'purpose' => 'Visa application',
                'priority' => 'high',
                'status' => 'processing',
                'requested_at' => now()->subHours(5)->format('Y-m-d H:i:s'),
                'processed_by' => 'HR Admin',
                'processed_at' => now()->subHours(1)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 3,
                'employee_id' => 3,
                'employee_name' => 'Pedro Reyes',
                'employee_number' => 'EMP-2024-003',
                'department' => 'Finance',
                'document_type' => 'BIR Form 2316',
                'purpose' => 'Annual tax filing',
                'priority' => 'normal',
                'status' => 'completed',
                'requested_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'processed_by' => 'Payroll Manager',
                'processed_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'generated_document_path' => '/storage/documents/bir-2316-pedro-reyes-2024.pdf',
            ],
            [
                'id' => 4,
                'employee_id' => 4,
                'employee_name' => 'Ana Garcia',
                'employee_number' => 'EMP-2024-004',
                'department' => 'Operations',
                'document_type' => 'Employment Contract',
                'purpose' => 'Personal records',
                'priority' => 'normal',
                'status' => 'completed',
                'requested_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
                'processed_by' => 'HR Manager',
                'processed_at' => now()->subDays(4)->format('Y-m-d H:i:s'),
                'generated_document_path' => '/storage/documents/contract-ana-garcia.pdf',
            ],
            [
                'id' => 5,
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP-2024-001',
                'department' => 'IT Department',
                'document_type' => 'SSS/PhilHealth/Pag-IBIG Contribution',
                'purpose' => 'Loan application',
                'priority' => 'high',
                'status' => 'rejected',
                'requested_at' => now()->subDays(3)->format('Y-m-d H:i:s'),
                'processed_by' => 'HR Staff',
                'processed_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'rejection_reason' => 'Incomplete contribution records for Q1 2024. Please contact payroll for clarification.',
            ],
            [
                'id' => 6,
                'employee_id' => 5,
                'employee_name' => 'Carlos Mendoza',
                'employee_number' => 'EMP-2024-005',
                'department' => 'Marketing',
                'document_type' => 'Leave Credits Statement',
                'purpose' => 'Personal planning',
                'priority' => 'normal',
                'status' => 'pending',
                'requested_at' => now()->subDays(1)->format('Y-m-d H:i:s'),
                'processed_by' => null,
                'processed_at' => null,
            ],
        ]);

        // Apply filters
        if ($request->filled('status')) {
            $requests = $requests->where('status', $request->status);
        }

        if ($request->filled('document_type')) {
            $requests = $requests->where('document_type', $request->document_type);
        }

        if ($request->filled('date_from')) {
            $requests = $requests->filter(function ($req) use ($request) {
                return $req['requested_at'] >= $request->date_from;
            });
        }

        if ($request->filled('date_to')) {
            $requests = $requests->filter(function ($req) use ($request) {
                return $req['requested_at'] <= $request->date_to . ' 23:59:59';
            });
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $requests = $requests->filter(function ($req) use ($search) {
                return str_contains(strtolower($req['employee_name']), $search) ||
                       str_contains(strtolower($req['employee_number']), $search) ||
                       str_contains(strtolower($req['document_type']), $search);
            });
        }

        // Calculate statistics
        $statistics = [
            'pending' => collect($requests)->where('status', 'pending')->count(),
            'processing' => collect($requests)->where('status', 'processing')->count(),
            'completed' => collect($requests)->where('status', 'completed')->count(),
            'rejected' => collect($requests)->where('status', 'rejected')->count(),
        ];

        // Log security audit
        $this->logAudit(
            'document_requests.view',
            'info',
            ['filters' => $request->only(['status', 'document_type', 'date_from', 'date_to', 'search'])]
        );

        return Inertia::render('HR/Documents/Requests/Index', [
            'requests' => $requests->values(),
            'statistics' => $statistics,
            'filters' => $request->only(['status', 'document_type', 'date_from', 'date_to', 'search']),
        ]);
    }

    /**
     * Process a document request (approve or reject).
     */
    public function process(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'template_id' => 'required_if:action,approve|nullable|integer',
            'notes' => 'nullable|string|max:500',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
            'send_email' => 'boolean',
        ]);

        // In production:
        // 1. Fetch document request from database
        // 2. If approve: Generate document from template, upload, notify employee
        // 3. If reject: Update status, save reason, notify employee
        // 4. Update processed_by and processed_at

        $action = $validated['action'];

        if ($action === 'approve') {
            // Mock document generation
            $generatedDocument = [
                'filename' => 'COE_Juan_dela_Cruz_' . now()->format('Ymd') . '.pdf',
                'path' => '/storage/documents/coe-juan-dela-cruz-' . now()->format('Ymd') . '.pdf',
                'size' => '125 KB',
            ];

            $this->logAudit(
                'document_requests.approve',
                'info',
                [
                    'request_id' => $id,
                    'template_id' => $validated['template_id'] ?? null,
                    'send_email' => $validated['send_email'] ?? false,
                    'document' => $generatedDocument,
                ]
            );

            return redirect()->route('hr.documents.requests.index')
                ->with('success', 'Document request approved and generated successfully');
        }

        if ($action === 'reject') {
            $this->logAudit(
                'document_requests.reject',
                'info',
                [
                    'request_id' => $id,
                    'rejection_reason' => $validated['rejection_reason'],
                ]
            );

            return redirect()->route('hr.documents.requests.index')
                ->with('success', 'Document request rejected. Employee has been notified.');
        }

        return redirect()->route('hr.documents.requests.index')
            ->with('error', 'Invalid action');
    }
}
