<?php

namespace App\Http\Controllers\HR\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\ClearanceItem;
use App\Models\OffboardingCase;
use App\Services\HR\OffboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class ClearanceController extends Controller
{
    protected OffboardingService $offboardingService;

    public function __construct(OffboardingService $offboardingService)
    {
        $this->offboardingService = $offboardingService;
    }

    /**
     * Display an overview of clearance status for all active offboarding cases.
     */
    public function indexAll(Request $request): Response
    {
        $cases = OffboardingCase::with([
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'clearanceItems',
        ])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($case) {
                $stats = $this->calculateClearanceStatistics($case->clearanceItems);
                return [
                    'id'              => $case->id,
                    'case_number'     => $case->case_number,
                    'status'          => $case->status,
                    'separation_type' => $case->separation_type,
                    'employee' => [
                        'name'            => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                        'employee_number' => $case->employee->employee_number,
                        'department'      => $case->employee->department?->name,
                    ],
                    'clearance_stats' => $stats,
                    'clearance_url'   => '/hr/offboarding/clearance/' . $case->id,
                ];
            });

        $totals = [
            'total_cases'   => $cases->count(),
            'total_pending' => $cases->sum(fn($c) => $c['clearance_stats']['pending']),
            'total_issues'  => $cases->sum(fn($c) => $c['clearance_stats']['issues']),
            'total_overdue' => $cases->sum(fn($c) => $c['clearance_stats']['overdue']),
        ];

        return Inertia::render('HR/Offboarding/Clearance/Overview', [
            'cases'  => $cases->values(),
            'totals' => $totals,
        ]);
    }

    /**
     * Display clearance checklist for an offboarding case, grouped by category.
     */
    public function index($caseId): Response
    {
        $case = OffboardingCase::with([
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'clearanceItems.assignedTo:id,name',
            'clearanceItems.approvedBy:id,name',
        ])->findOrFail($caseId);

        // Group clearance items by category
        $itemsByCategory = $case->clearanceItems
            ->groupBy('category')
            ->map(function ($items) {
                return $items->map(fn($item) => $this->transformClearanceItem($item))->values();
            });

        // Calculate statistics
        $statistics = $this->calculateClearanceStatistics($case->clearanceItems);

        // Check if user can waive items (HR role)
        $canWaive = $this->offboardingService->userCanWaiveClearances($this->getCurrentUser());

        return Inertia::render('HR/Offboarding/Clearance/Index', [
            'case' => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'employee' => [
                    'name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                    'employee_number' => $case->employee->employee_number,
                    'department' => $case->employee->department?->name,
                ],
                'status' => $case->status,
                'separation_type' => $case->separation_type,
            ],
            'itemsByCategory' => $itemsByCategory,
            'statistics' => $statistics,
            'categoryLabels' => [
                'hr' => 'Human Resources',
                'it' => 'Information Technology',
                'finance' => 'Finance',
                'admin' => 'Administration',
                'operations' => 'Operations',
                'security' => 'Security',
                'facilities' => 'Facilities',
            ],
            'priorities' => [
                'critical' => 'Critical',
                'high' => 'High',
                'normal' => 'Normal',
                'low' => 'Low',
            ],
            'canWaive' => $canWaive,
            'currentUserId' => $this->getCurrentUser()->id,
        ]);
    }

    /**
     * Approve a clearance item.
     */
    public function approve(Request $request, $itemId): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'proof_file' => 'nullable|file|max:10240', // 10MB max
        ]);

        try {
            DB::beginTransaction();

            $item = ClearanceItem::with('offboardingCase.employee')
                ->findOrFail($itemId);

            $user = $this->getCurrentUser();

            // Check authorization - verify user is approved to clear this category
            if (!$this->canApproveClearanceItem($item, $user)) {
                return back()->withErrors([
                    'error' => 'You do not have permission to approve clearances in the ' . $item->category . ' category.',
                ]);
            }

            // Handle proof file upload if provided
            $proofPath = null;
            if ($request->hasFile('proof_file')) {
                $proofPath = $this->uploadProofFile($request->file('proof_file'), $item);
            }

            // Approve the item
            $item->approve($user, $validated['notes'] ?? null);

            // Update proof file path if uploaded
            if ($proofPath) {
                $item->update(['proof_of_return_file_path' => $proofPath]);
            }

            // Send notification to HR coordinator
            $this->offboardingService->notifyClearanceApproved($item, $user);

            // Check if all clearances are now complete
            if ($this->offboardingService->allClearancesComplete($item->offboardingCase)) {
                $item->offboardingCase->update([
                    'status' => 'clearance_pending',
                    'all_clearances_approved_at' => now(),
                ]);

                $this->offboardingService->notifyAllClearancesComplete($item->offboardingCase);
            }

            DB::commit();

            Log::info('Clearance item approved', [
                'item_id' => $itemId,
                'case_number' => $item->offboardingCase->case_number,
                'approved_by' => $user->id,
                'category' => $item->category,
            ]);

            return back()->with('success', "Clearance item '{$item->item_name}' has been approved.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve clearance item', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to approve clearance item.']);
        }
    }

    /**
     * Report issues with a clearance item.
     */
    public function reportIssue(Request $request, $itemId): RedirectResponse
    {
        $validated = $request->validate([
            'issue_description' => 'required|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $item = ClearanceItem::with('offboardingCase')
                ->findOrFail($itemId);

            $user = $this->getCurrentUser();

            // Mark item as having issues

            $item->markAsHavingIssues($validated['issue_description']);

            // Send notification to HR and employee
            $this->offboardingService->notifyClearanceIssueReported($item, $validated['issue_description']);

            DB::commit();

            Log::info('Clearance issue reported', [
                'item_id' => $itemId,
                'case_number' => $item->offboardingCase->case_number,
                'reported_by' => $user->id,
            ]);

            return back()->with('success', "Issue reported for '{$item->item_name}'. HR has been notified.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to report clearance issue', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to report issue.']);
        }
    }

    /**
     * Waive a clearance item (HR only).
     */
    public function waive(Request $request, $itemId): RedirectResponse
    {
        // Authorize: only HR staff can waive clearances
        if (!$this->offboardingService->userCanWaiveClearances($this->getCurrentUser())) {
            return back()->withErrors([
                'error' => 'You do not have permission to waive clearance items.',
            ]);
        }

        $validated = $request->validate([
            'waiver_reason' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $item = ClearanceItem::with('offboardingCase')
                ->findOrFail($itemId);

            $user = $this->getCurrentUser();

            // Waive the item
            $item->waive($user, $validated['waiver_reason']);

            // Send notification
            $this->offboardingService->notifyClearanceWaived($item, $user);

            // Check if all clearances are now complete
            if ($this->offboardingService->allClearancesComplete($item->offboardingCase)) {
                $item->offboardingCase->update([
                    'status' => 'clearance_pending',
                    'all_clearances_approved_at' => now(),
                ]);

                $this->offboardingService->notifyAllClearancesComplete($item->offboardingCase);
            }

            DB::commit();

            Log::info('Clearance item waived', [
                'item_id' => $itemId,
                'case_number' => $item->offboardingCase->case_number,
                'waived_by' => $user->id,
                'reason' => $validated['waiver_reason'],
            ]);

            return back()->with('success', "Clearance item '{$item->item_name}' has been waived.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to waive clearance item', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to waive clearance item.']);
        }
    }

    /**
     * Bulk approve multiple clearance items at once.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:clearance_items,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $user = $this->getCurrentUser();
            $approvedCount = 0;
            $failedCount = 0;
            $caseIds = [];

            foreach ($validated['item_ids'] as $itemId) {
                try {
                    $item = ClearanceItem::with('offboardingCase')
                        ->findOrFail($itemId);

                    // Check authorization
                    if (!$this->canApproveClearanceItem($item, $user)) {
                        $failedCount++;
                        continue;
                    }

                    // Approve the item
                    $item->approve($user, $validated['notes'] ?? null);
                    $approvedCount++;

                    // Track case IDs for completion check
                    if (!in_array($item->offboardingCase->id, $caseIds)) {
                        $caseIds[] = $item->offboardingCase->id;
                    }

                    Log::info('Clearance item approved (bulk)', [
                        'item_id' => $itemId,
                        'case_number' => $item->offboardingCase->case_number,
                        'approved_by' => $user->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to approve clearance item in bulk operation', [
                        'item_id' => $itemId,
                        'error' => $e->getMessage(),
                    ]);
                    $failedCount++;
                }
            }

            // Check if any cases are now complete
            foreach ($caseIds as $caseId) {
                $case = OffboardingCase::find($caseId);
                if ($case && $this->offboardingService->allClearancesComplete($case)) {
                    $case->update([
                        'status' => 'clearance_pending',
                        'all_clearances_approved_at' => now(),
                    ]);
                    $this->offboardingService->notifyAllClearancesComplete($case);
                }
            }

            DB::commit();

            $message = "Successfully approved $approvedCount clearance item" . ($approvedCount !== 1 ? 's' : '');
            if ($failedCount > 0) {
                $message .= " ($failedCount failed due to authorization).";
            } else {
                $message .= '.';
            }

            Log::info('Bulk approval completed', [
                'approved_count' => $approvedCount,
                'failed_count' => $failedCount,
                'approved_by' => $user->id,
            ]);

            return back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to perform bulk approval', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to approve clearance items.']);
        }
    }

    /**
     * Download proof file for a clearance item.
     */
    public function downloadProof($itemId)
    {
        $item = ClearanceItem::findOrFail($itemId);

        if (!$item->proof_of_return_file_path) {
            return back()->withErrors(['error' => 'No proof file available for this item.']);
        }

        if (!Storage::disk('public')->exists($item->proof_of_return_file_path)) {
            return back()->withErrors(['error' => 'Proof file not found.']);
        }

        return Storage::disk('public')->download($item->proof_of_return_file_path);
    }

    /**
     * Check if user can approve a clearance item in a specific category.
     */
    private function canApproveClearanceItem(ClearanceItem $item, $user): bool
    {
        // HR can approve anything
        if ($user->hasRole('HR Manager') || $user->hasRole('Superadmin')) {
            return true;
        }

        // Department heads can approve in their category
        $categoryPermissions = [
            'it' => 'can_approve_it_clearances',
            'hr' => 'can_approve_hr_clearances',
            'finance' => 'can_approve_finance_clearances',
            'admin' => 'can_approve_admin_clearances',
            'operations' => 'can_approve_operations_clearances',
            'security' => 'can_approve_security_clearances',
            'facilities' => 'can_approve_facilities_clearances',
        ];

        $permission = $categoryPermissions[$item->category] ?? null;

        return $permission && $user->hasPermissionTo($permission);
    }

    /**
     * Upload proof file and return storage path.
     */
    private function uploadProofFile($file, ClearanceItem $item): ?string
    {
        try {
            $fileName = sprintf(
                'clearance-proofs/%s/%s/%s.%s',
                $item->offboardingCase->case_number,
                $item->category,
                time(),
                $file->getClientOriginalExtension()
            );

            $path = Storage::disk('public')->putFileAs('', $file, $fileName);

            Log::info('Clearance proof file uploaded', [
                'item_id' => $item->id,
                'path' => $path,
                'uploaded_by' => $this->getCurrentUser()->id,
            ]);

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to upload clearance proof file', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Transform clearance item for response.
     */
    private function transformClearanceItem(ClearanceItem $item): array
    {
        return [
            'id' => $item->id,
            'item_name' => $item->item_name,
            'description' => $item->description,
            'category' => $item->category,
            'priority' => $item->priority,
            'priority_label' => $item->getPriorityLabel(),
            'status' => $item->status,
            'status_label' => $item->getStatusLabel(),
            'assigned_to' => $item->assignedTo?->name,
            'assigned_to_id' => $item->assigned_to,
            'approved_by' => $item->approvedBy?->name,
            'approved_at' => $item->approved_at?->format('Y-m-d H:i'),
            'due_date' => $item->due_date?->format('Y-m-d'),
            'has_issues' => $item->has_issues,
            'issue_description' => $item->issue_description,
            'resolution_notes' => $item->resolution_notes,
            'proof_file_path' => $item->proof_of_return_file_path,
            'is_overdue' => $item->isOverdue(),
        ];
    }

    /**
     * Calculate statistics for clearance items.
     */
    private function calculateClearanceStatistics($items): array
    {
        return [
            'total' => $items->count(),
            'pending' => $items->where('status', 'pending')->count(),
            'in_progress' => $items->where('status', 'in_progress')->count(),
            'approved' => $items->where('status', 'approved')->count(),
            'waived' => $items->where('status', 'waived')->count(),
            'issues' => $items->where('has_issues', true)->count(),
            'overdue' => $items->filter(fn($i) => $i->isOverdue())->count(),
            'critical_pending' => $items->where('priority', 'critical')
                ->whereIn('status', ['pending', 'in_progress'])
                ->count(),
        ];
    }

    /**
     * Get current user.
     */
    private function getCurrentUser()
    {
        return auth()->user();
    }
}
