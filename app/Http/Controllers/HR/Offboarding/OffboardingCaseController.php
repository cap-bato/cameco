<?php

namespace App\Http\Controllers\HR\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\OffboardingCase;
use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Services\HR\OffboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OffboardingCaseController extends Controller
{
    protected OffboardingService $offboardingService;

    public function __construct(OffboardingService $offboardingService)
    {
        $this->offboardingService = $offboardingService;
    }

    /**
     * Display a listing of all offboarding cases with filters and statistics.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'all');
        $separationType = $request->input('separation_type', 'all');
        $searchTerm = $request->input('search', '');
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        // Validate per_page value
        $perPage = in_array($perPage, [10, 15, 25, 50]) ? $perPage : 15;

        // Build base query
        $query = OffboardingCase::with([
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'initiatedBy:id,name',
            'hrCoordinator:id,name',
            'clearanceItems',
            'exitInterview',
        ]);

        // Apply status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Apply separation type filter
        if ($separationType !== 'all') {
            $query->where('separation_type', $separationType);
        }

        // Apply search filter
        if ($searchTerm) {
            $query->whereHas('employee.profile', function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%{$searchTerm}%")
                  ->orWhere('last_name', 'like', "%{$searchTerm}%");
            })
            ->orWhere('case_number', 'like', "%{$searchTerm}%");
        }

        // Get all cases for statistics calculation
        $allCases = $query->get();

        // Calculate statistics
        $statistics = $this->calculateStatistics($allCases);

        // Apply pagination
        $cases = $query->orderByDesc('created_at')
                      ->paginate($perPage, ['*'], 'page', $page);

        // Transform cases for frontend
        $transformedCases = $cases->map(function ($case) {
            return $this->transformCaseForResponse($case);
        });

        return Inertia::render('HR/Offboarding/Cases/Index', [
            'cases' => $transformedCases,
            'pagination' => [
                'current_page' => $cases->currentPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
                'last_page' => $cases->lastPage(),
            ],
            'statistics' => $statistics,
            'filters' => [
                'status' => $status,
                'separation_type' => $separationType,
                'search' => $searchTerm,
                'per_page' => $perPage,
            ],
            'statusOptions' => [
                'all' => 'All Statuses',
                'pending' => 'Pending',
                'in_progress' => 'In Progress',
                'clearance_pending' => 'Clearance Pending',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
            'separationTypeOptions' => [
                'all' => 'All Types',
                'resignation' => 'Resignation',
                'termination' => 'Termination',
                'retirement' => 'Retirement',
                'end_of_contract' => 'End of Contract',
                'death' => 'Death',
                'abscondment' => 'Abscondment',
            ],
        ]);
    }

    /**
     * Show the form for creating a new offboarding case.
     */
    public function create(): Response
    {
        // Get active employees only
        $employees = Employee::with('profile:id,first_name,last_name', 'department:id,name')
            ->where('status', 'active')
            ->orderBy('employee_number')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'employee_number' => $emp->employee_number,
                    'name' => $emp->profile?->first_name . ' ' . $emp->profile?->last_name,
                    'department' => $emp->department?->name,
                ];
            });

        return Inertia::render('HR/Offboarding/Cases/Create', [
            'employees' => $employees,
            'separationTypes' => [
                'resignation' => 'Resignation',
                'termination' => 'Termination',
                'retirement' => 'Retirement',
                'end_of_contract' => 'End of Contract',
                'death' => 'Death',
                'abscondment' => 'Abscondment',
            ],
        ]);
    }

    /**
     * Store a newly created offboarding case.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'separation_type' => 'required|in:resignation,termination,retirement,end_of_contract,death,abscondment',
            'last_working_day' => 'required|date|after_or_equal:today',
            'separation_reason' => 'required|string|max:500',
            'notice_period_days' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($validated['employee_id']);

            // Create the offboarding case
            $caseNumber = $this->offboardingService->generateCaseNumber();
            
            $case = OffboardingCase::create([
                'employee_id' => $validated['employee_id'],
                'initiated_by' => $request->user()->id,
                'hr_coordinator_id' => $request->user()->id,
                'case_number' => $caseNumber,
                'separation_type' => $validated['separation_type'],
                'separation_reason' => $validated['separation_reason'],
                'last_working_day' => $validated['last_working_day'],
                'notice_period_days' => $validated['notice_period_days'],
                'status' => 'pending',
                'resignation_submitted_at' => now(),
            ]);

            // Auto-create clearance items based on employee role
            $this->offboardingService->createDefaultClearanceItems($case);

            // Auto-create access revocation list
            $this->offboardingService->createDefaultAccessRevocations($case);

            // Update employee status to offboarding
            $employee->update(['status' => 'offboarding']);

            // Send notifications
            $this->offboardingService->notifyOffboardingInitiated($case);

            DB::commit();

            Log::info('Offboarding case created', [
                'case_number' => $caseNumber,
                'employee_id' => $validated['employee_id'],
                'created_by' => $request->user()->id,
            ]);

            return redirect()->route('hr.offboarding.cases.show', $case->id)
                           ->with('success', "Offboarding case {$caseNumber} created successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create offboarding case', [
                'error' => $e->getMessage(),
                'employee_id' => $validated['employee_id'] ?? null,
            ]);

            return back()->withErrors(['error' => 'Failed to create offboarding case. ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified offboarding case with all related data.
     */
    public function show($caseId): Response
    {
        $case = OffboardingCase::with([
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'employee.position:id,name',
            'initiatedBy:id,name',
            'hrCoordinator:id,name',
            'clearanceItems.assignedTo:id,name',
            'clearanceItems.approvedBy:id,name',
            'exitInterview',
            'companyAssets',
            'knowledgeTransferItems.transferredTo',
            'accessRevocations',
            'documents',
        ])->findOrFail($caseId);

        // Transform data for frontend
        $caseData = $this->transformCaseDetail($case);

        // Group clearance items by category
        $clearancesByCategory = $case->clearanceItems->groupBy('category');

        // Get progress summary
        $progressSummary = $case->getProgressSummary();

        // Get next actions
        $nextActions = $case->getNextActions();

        return Inertia::render('HR/Offboarding/Cases/Show', [
            'case' => $caseData,
            'clearancesByCategory' => $this->transformClearanceItems($clearancesByCategory),
            'exitInterview' => $case->exitInterview ? $this->transformExitInterview($case->exitInterview) : null,
            'companyAssets' => $case->companyAssets->map(fn($asset) => $this->transformAsset($asset)),
            'knowledgeTransfers' => $case->knowledgeTransferItems->map(fn($item) => $this->transformKnowledgeTransfer($item)),
            'accessRevocations' => $case->accessRevocations->map(fn($rev) => $this->transformAccessRevocation($rev)),
            'documents' => $case->documents->map(fn($doc) => $this->transformDocument($doc)),
            'progressSummary' => $progressSummary,
            'nextActions' => $nextActions,
            'canComplete' => $case->canBeCompleted(),
            'canCancel' => in_array($case->status, ['pending', 'in_progress', 'clearance_pending']),
        ]);
    }

    /**
     * Show the form for editing the offboarding case.
     */
    public function edit($caseId): Response
    {
        $case = OffboardingCase::findOrFail($caseId);

        return Inertia::render('HR/Offboarding/Cases/Edit', [
            'case' => $this->transformCaseForResponse($case),
            'separationTypes' => [
                'resignation' => 'Resignation',
                'termination' => 'Termination',
                'retirement' => 'Retirement',
                'end_of_contract' => 'End of Contract',
                'death' => 'Death',
                'abscondment' => 'Abscondment',
            ],
        ]);
    }

    /**
     * Update the specified offboarding case.
     */
    public function update(Request $request, $caseId): RedirectResponse
    {
        $case = OffboardingCase::findOrFail($caseId);

        $validated = $request->validate([
            'separation_reason' => 'required|string|max:500',
            'last_working_day' => 'required|date',
            'rehire_eligible' => 'nullable|boolean',
            'rehire_eligibility_reason' => 'nullable|string|max:500',
            'internal_notes' => 'nullable|string',
        ]);

        try {
            $case->update($validated);

            Log::info('Offboarding case updated', [
                'case_number' => $case->case_number,
                'updated_by' => $request->user()->id,
            ]);

            return back()->with('success', 'Offboarding case updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update offboarding case', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to update offboarding case.']);
        }
    }

    /**
     * Cancel an offboarding case and restore employee status.
     */
    public function cancel($caseId): RedirectResponse
    {
        $case = OffboardingCase::findOrFail($caseId);

        if (!in_array($case->status, ['pending', 'in_progress', 'clearance_pending'])) {
            return back()->withErrors(['error' => 'Only pending or in-progress cases can be cancelled.']);
        }

        try {
            DB::beginTransaction();

            // Restore employee status to active
            $case->employee->update(['status' => 'active']);

            // Update case status
            $case->update(['status' => 'cancelled']);

            // Send cancellation notifications
            $this->offboardingService->notifyOffboardingCancelled($case);

            DB::commit();

            Log::info('Offboarding case cancelled', [
                'case_number' => $case->case_number,
                'cancelled_by' => request()->user()->id,
            ]);

            return redirect()->route('hr.offboarding.cases.index')
                           ->with('success', 'Offboarding case cancelled and employee status restored.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel offboarding case', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to cancel offboarding case.']);
        }
    }

    /**
     * Mark offboarding case as completed.
     */
    public function complete($caseId): RedirectResponse
    {
        $case = OffboardingCase::findOrFail($caseId);

        if (!$case->canBeCompleted()) {
            return back()->withErrors([
                'error' => 'All clearances must be approved or waived before completing the case.',
            ]);
        }

        try {
            DB::beginTransaction();

            // Determine final status based on separation type
            $finalStatus = match($case->separation_type) {
                'termination', 'end_of_contract' => 'terminated',
                'resignation', 'retirement' => 'resigned',
                'death' => 'deceased',
                'abscondment' => 'absconded',
                default => 'terminated',
            };

            // Update employee
            $case->employee->update([
                'status' => $finalStatus,
                'termination_date' => $case->last_working_day,
                'termination_reason' => $case->separation_reason,
            ]);

            // Generate final documents
            $this->offboardingService->generateFinalDocuments($case);

            // Mark case as completed
            $case->markAsCompleted();

            // Deactivate user account if exists
            if ($case->employee->user) {
                $case->employee->user->update(['is_active' => false]);
            }

            // Send completion notifications
            $this->offboardingService->notifyOffboardingCompleted($case);

            DB::commit();

            Log::info('Offboarding case completed', [
                'case_number' => $case->case_number,
                'employee_id' => $case->employee_id,
                'completed_by' => request()->user()->id,
            ]);

            return redirect()->route('hr.offboarding.cases.show', $case->id)
                           ->with('success', 'Offboarding case completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete offboarding case', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to complete offboarding case.']);
        }
    }

    /**
     * Export offboarding case report as PDF.
     */
    public function exportReport($caseId)
    {
        $case = OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'clearanceItems',
            'exitInterview',
            'companyAssets',
        ])->findOrFail($caseId);

        try {
            $pdf = $this->offboardingService->generateCaseReportPDF($case);

            Log::info('Offboarding case report exported', [
                'case_number' => $case->case_number,
                'exported_by' => request()->user()->id,
            ]);

            return $pdf->download("Offboarding-{$case->case_number}.pdf");
        } catch (\Exception $e) {
            Log::error('Failed to export offboarding case report', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to generate report.']);
        }
    }

    /**
     * Get statistics for all offboarding cases.
     */
    private function calculateStatistics($cases)
    {
        $now = now();

        return [
            'total' => $cases->count(),
            'pending' => $cases->where('status', 'pending')->count(),
            'in_progress' => $cases->where('status', 'in_progress')->count(),
            'clearance_pending' => $cases->where('status', 'clearance_pending')->count(),
            'completed' => $cases->where('status', 'completed')->count(),
            'cancelled' => $cases->where('status', 'cancelled')->count(),
            'due_this_week' => $cases
                ->whereBetween('last_working_day', [$now->startOfWeek(), $now->endOfWeek()])
                ->whereIn('status', ['pending', 'in_progress'])
                ->count(),
            'overdue' => $cases
                ->where('last_working_day', '<', $now->toDateString())
                ->whereIn('status', ['pending', 'in_progress'])
                ->count(),
        ];
    }

    /**
     * Transform case for API response.
     */
    private function transformCaseForResponse(OffboardingCase $case): array
    {
        return [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'employee' => [
                'id' => $case->employee->id,
                'name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                'employee_number' => $case->employee->employee_number,
                'department' => $case->employee->department?->name,
            ],
            'separation_type' => $case->separation_type,
            'separation_reason' => $case->separation_reason,
            'last_working_day' => $case->last_working_day?->format('Y-m-d'),
            'status' => $case->status,
            'status_label' => ucfirst(str_replace('_', ' ', $case->status)),
            'initiated_by' => $case->initiatedBy?->name,
            'hr_coordinator' => $case->hrCoordinator?->name,
            'rehire_eligible' => $case->rehire_eligible,
            'created_at' => $case->created_at?->format('Y-m-d H:i'),
            'completion_percentage' => $case->calculateCompletionPercentage(),
        ];
    }

    /**
     * Transform case detail for full view.
     */
    private function transformCaseDetail(OffboardingCase $case): array
    {
        return array_merge($this->transformCaseForResponse($case), [
            'position' => $case->employee->position?->name,
            'notice_period_days' => $case->notice_period_days,
            'internal_notes' => $case->internal_notes,
            'rehire_eligibility_reason' => $case->rehire_eligibility_reason,
            'final_pay_computed' => $case->final_pay_computed,
            'final_documents_issued' => $case->final_documents_issued,
            'resignation_submitted_at' => $case->resignation_submitted_at?->format('Y-m-d H:i'),
            'clearance_started_at' => $case->clearance_started_at?->format('Y-m-d H:i'),
            'exit_interview_completed_at' => $case->exit_interview_completed_at?->format('Y-m-d H:i'),
            'all_clearances_approved_at' => $case->all_clearances_approved_at?->format('Y-m-d H:i'),
            'final_documents_generated_at' => $case->final_documents_generated_at?->format('Y-m-d H:i'),
            'account_deactivated_at' => $case->account_deactivated_at?->format('Y-m-d H:i'),
            'completed_at' => $case->completed_at?->format('Y-m-d H:i'),
        ]);
    }

    /**
     * Transform clearance items by category.
     */
    private function transformClearanceItems($itemsByCategory): array
    {
        return $itemsByCategory->map(function ($items) {
            return $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'category' => $item->category,
                    'item_name' => $item->item_name,
                    'description' => $item->description,
                    'priority' => $item->priority,
                    'priority_label' => $item->getPriorityLabel(),
                    'status' => $item->status,
                    'status_label' => $item->getStatusLabel(),
                    'assigned_to' => $item->assignedTo?->name,
                    'approved_by' => $item->approvedBy?->name,
                    'approved_at' => $item->approved_at?->format('Y-m-d H:i'),
                    'due_date' => $item->due_date?->format('Y-m-d'),
                    'has_issues' => $item->has_issues,
                    'issue_description' => $item->issue_description,
                    'is_overdue' => $item->isOverdue(),
                ];
            })->toArray();
        })->toArray();
    }

    /**
     * Transform exit interview data.
     */
    private function transformExitInterview($interview): array
    {
        return [
            'id' => $interview->id,
            'status' => $interview->status,
            'status_label' => ucfirst(str_replace('_', ' ', $interview->status)),
            'interview_date' => $interview->interview_date?->format('Y-m-d'),
            'conducted_by' => $interview->conductedBy?->name,
            'reason_for_leaving' => $interview->reason_for_leaving,
            'overall_satisfaction' => $interview->overall_satisfaction,
            'average_rating' => round($interview->getAverageRating(), 2),
            'would_recommend_company' => $interview->would_recommend_company,
            'would_consider_returning' => $interview->would_consider_returning,
            'sentiment_score' => $interview->sentiment_score,
            'sentiment_classification' => $interview->getSentimentClassification(),
            'key_themes' => $interview->key_themes,
            'completed_at' => $interview->completed_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Transform company asset data.
     */
    private function transformAsset($asset): array
    {
        return [
            'id' => $asset->id,
            'asset_name' => $asset->getDisplayName(),
            'asset_type' => $asset->asset_type,
            'serial_number' => $asset->serial_number,
            'status' => $asset->status,
            'status_label' => $asset->getStatusLabel(),
            'assigned_date' => $asset->assigned_date?->format('Y-m-d'),
            'return_date' => $asset->return_date?->format('Y-m-d'),
            'condition_at_return' => $asset->condition_at_return,
            'liability_amount' => $asset->liability_amount,
            'has_liability' => $asset->hasLiability(),
        ];
    }

    /**
     * Transform knowledge transfer item data.
     */
    private function transformKnowledgeTransfer($item): array
    {
        return [
            'id' => $item->id,
            'item_type' => $item->item_type,
            'item_type_label' => $item->getItemTypeLabel(),
            'title' => $item->title,
            'description' => $item->description,
            'status' => $item->status,
            'status_label' => $item->getStatusLabel(),
            'priority' => $item->priority,
            'priority_label' => $item->getPriorityLabel(),
            'transferred_to' => $item->transferredTo?->profile?->first_name . ' ' . $item->transferredTo?->profile?->last_name,
            'due_date' => $item->due_date?->format('Y-m-d'),
            'is_overdue' => $item->isOverdue(),
            'completed_at' => $item->completed_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Transform access revocation data.
     */
    private function transformAccessRevocation($revocation): array
    {
        return [
            'id' => $revocation->id,
            'system_name' => $revocation->system_name,
            'system_category' => $revocation->system_category,
            'system_category_label' => $revocation->getSystemCategoryLabel(),
            'account_identifier' => $revocation->account_identifier,
            'status' => $revocation->status,
            'status_label' => $revocation->getStatusLabel(),
            'data_backed_up' => $revocation->data_backed_up,
            'needs_backup' => $revocation->needsBackup(),
            'revoked_at' => $revocation->revoked_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Transform offboarding document data.
     */
    private function transformDocument($document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'document_type_label' => $document->getDocumentTypeLabel(),
            'document_name' => $document->document_name,
            'status' => $document->status,
            'status_label' => $document->getStatusLabel(),
            'generated_by_system' => $document->generated_by_system,
            'issued_to_employee' => $document->issued_to_employee,
            'file_path' => $document->file_path,
            'file_size' => $document->getFormattedFileSize(),
            'created_at' => $document->created_at?->format('Y-m-d H:i'),
            'issued_at' => $document->issued_at?->format('Y-m-d H:i'),
        ];
    }
}
