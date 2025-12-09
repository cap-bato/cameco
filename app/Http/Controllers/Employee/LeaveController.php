<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\LeaveRequestRequest;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Services\HR\Workforce\WorkforceCoverageService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee Leave Controller
 * 
 * Handles employee self-service leave management:
 * - View leave balances by type
 * - View leave request history
 * - Submit new leave requests with workforce coverage validation
 * - Cancel pending/approved leave requests
 * - Calculate real-time workforce coverage impact
 */
class LeaveController extends Controller
{
    protected WorkforceCoverageService $coverageService;

    public function __construct(WorkforceCoverageService $coverageService)
    {
        $this->coverageService = $coverageService;
    }

    /**
     * Display leave balances for authenticated employee
     * Shows balances for all leave types (Vacation, Sick, Emergency, etc.)
     */
    public function balances(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        try {
            // Get current year leave balances with leave policy details
            $currentYear = Carbon::now()->year;
            $balances = LeaveBalance::where('employee_id', $employee->id)
                ->where('year', $currentYear)
                ->with('leavePolicy:id,name,code,color,accrual_method,max_days')
                ->get()
                ->map(function ($balance) {
                    return [
                        'id' => $balance->id,
                        'leave_type' => $balance->leavePolicy->name ?? 'Unknown',
                        'leave_code' => $balance->leavePolicy->code ?? 'N/A',
                        'color' => $balance->leavePolicy->color ?? '#64748b',
                        'total_entitled' => $balance->earned,
                        'used' => $balance->used,
                        'pending' => $balance->pending ?? 0,
                        'available' => $balance->remaining,
                        'carried_forward' => $balance->carried_forward ?? 0,
                        'accrual_method' => $balance->leavePolicy->accrual_method ?? 'annual',
                        'max_days' => $balance->leavePolicy->max_days ?? 0,
                    ];
                });

            // Get leave policies to show all available types (even with 0 balance)
            $allPolicies = LeavePolicy::where('is_active', true)
                ->select('id', 'name', 'code', 'color', 'accrual_method', 'max_days')
                ->get()
                ->map(function ($policy) use ($balances) {
                    $existingBalance = $balances->firstWhere('leave_code', $policy->code);
                    
                    if ($existingBalance) {
                        return $existingBalance;
                    }

                    // Return policy with zero balance if no balance record exists
                    return [
                        'id' => null,
                        'leave_type' => $policy->name,
                        'leave_code' => $policy->code,
                        'color' => $policy->color ?? '#64748b',
                        'total_entitled' => 0,
                        'used' => 0,
                        'pending' => 0,
                        'available' => 0,
                        'carried_forward' => 0,
                        'accrual_method' => $policy->accrual_method,
                        'max_days' => $policy->max_days,
                    ];
                });

            Log::info('Employee leave balances viewed', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'year' => $currentYear,
            ]);

            return Inertia::render('Employee/Leave/Balances', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? 'N/A',
                    'department' => $employee->department->name ?? 'N/A',
                    'position' => $employee->position->title ?? 'N/A',
                ],
                'balances' => $allPolicies,
                'year' => $currentYear,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch employee leave balances', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Employee/Leave/Balances', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? 'N/A',
                    'department' => $employee->department->name ?? 'N/A',
                    'position' => $employee->position->title ?? 'N/A',
                ],
                'balances' => [],
                'year' => Carbon::now()->year,
                'error' => 'Failed to load leave balances. Please try again or contact HR Staff if the issue persists.',
            ]);
        }
    }

    /**
     * Display leave request history for authenticated employee
     * Shows all past and current leave requests (approved, rejected, pending, cancelled)
     */
    public function history(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        try {
            // Get filter parameters
            $status = $request->input('status'); // 'all', 'pending', 'approved', 'rejected', 'cancelled'
            $leaveType = $request->input('leave_type');
            $year = $request->input('year', Carbon::now()->year);

            // Build query
            $query = LeaveRequest::where('employee_id', $employee->id)
                ->with(['leavePolicy:id,name,code,color', 'supervisor.profile'])
                ->whereYear('start_date', $year);

            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            if ($leaveType) {
                $query->where('leave_policy_id', $leaveType);
            }

            $requests = $query->orderBy('start_date', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'leave_type' => $request->leavePolicy->name ?? 'Unknown',
                        'leave_code' => $request->leavePolicy->code ?? 'N/A',
                        'color' => $request->leavePolicy->color ?? '#64748b',
                        'start_date' => $request->start_date->format('Y-m-d'),
                        'end_date' => $request->end_date->format('Y-m-d'),
                        'days_requested' => $request->days_requested,
                        'reason' => $request->reason,
                        'status' => $request->status,
                        'submitted_at' => $request->submitted_at?->format('Y-m-d H:i:s'),
                        'approved_at' => $request->supervisor_approved_at?->format('Y-m-d H:i:s') ?? $request->hr_processed_at?->format('Y-m-d H:i:s'),
                        'approver_name' => $request->supervisor?->profile?->full_name ?? 'HR Staff',
                        'approver_comments' => $request->supervisor_comments ?? $request->hr_notes,
                        'cancelled_at' => $request->cancelled_at?->format('Y-m-d H:i:s'),
                        'cancellation_reason' => $request->cancellation_reason,
                    ];
                });

            // Get available leave types for filter
            $leaveTypes = LeavePolicy::where('is_active', true)
                ->select('id', 'name', 'code')
                ->get();

            // Get available years (last 2 years + current year)
            $availableYears = collect([
                Carbon::now()->year,
                Carbon::now()->subYear()->year,
                Carbon::now()->subYears(2)->year,
            ]);

            Log::info('Employee leave history viewed', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'filters' => compact('status', 'leaveType', 'year'),
            ]);

            return Inertia::render('Employee/Leave/History', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? 'N/A',
                ],
                'requests' => $requests,
                'leaveTypes' => $leaveTypes,
                'availableYears' => $availableYears,
                'filters' => [
                    'status' => $status ?? 'all',
                    'leave_type' => $leaveType,
                    'year' => $year,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch employee leave history', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Employee/Leave/History', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? 'N/A',
                ],
                'requests' => [],
                'leaveTypes' => [],
                'availableYears' => [Carbon::now()->year],
                'filters' => [],
                'error' => 'Failed to load leave history. Please try again or contact HR Staff if the issue persists.',
            ]);
        }
    }

    /**
     * Show leave request form
     * Displays available leave types and current balances for submission
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        try {
            // Get active leave policies
            $leavePolicies = LeavePolicy::where('is_active', true)
                ->select('id', 'name', 'code', 'color', 'requires_document', 'min_advance_notice_days', 'max_consecutive_days')
                ->get();

            // Get current year balances
            $currentYear = Carbon::now()->year;
            $balances = LeaveBalance::where('employee_id', $employee->id)
                ->where('year', $currentYear)
                ->with('leavePolicy:id,name,code')
                ->get()
                ->keyBy('leave_policy_id')
                ->map(function ($balance) {
                    return [
                        'available' => $balance->remaining,
                        'pending' => $balance->pending ?? 0,
                    ];
                });

            // Merge policies with balances
            $leavePoliciesWithBalances = $leavePolicies->map(function ($policy) use ($balances) {
                $balance = $balances->get($policy->id);
                
                return [
                    'id' => $policy->id,
                    'name' => $policy->name,
                    'code' => $policy->code,
                    'color' => $policy->color ?? '#64748b',
                    'available' => $balance['available'] ?? 0,
                    'pending' => $balance['pending'] ?? 0,
                    'requires_document' => $policy->requires_document ?? false,
                    'min_advance_notice_days' => $policy->min_advance_notice_days ?? 3,
                    'max_consecutive_days' => $policy->max_consecutive_days ?? 30,
                ];
            });

            Log::info('Employee leave request form viewed', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
            ]);

            return Inertia::render('Employee/Leave/CreateRequest', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? 'N/A',
                    'department_id' => $employee->department_id,
                    'department_name' => $employee->department->name ?? 'N/A',
                ],
                'leavePolicies' => $leavePoliciesWithBalances,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load leave request form', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Employee/Leave/CreateRequest', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? 'N/A',
                ],
                'leavePolicies' => [],
                'error' => 'Failed to load leave request form. Please try again or contact HR Staff if the issue persists.',
            ]);
        }
    }

    /**
     * Submit new leave request
     * Validates balance, advance notice, blackout periods, and calculates workforce coverage
     */
    public function store(LeaveRequestRequest $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            // Calculate days requested
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $daysRequested = $startDate->diffInDays($endDate) + 1;

            // Get leave policy
            $policy = LeavePolicy::findOrFail($validated['leave_policy_id']);

            // Validate leave balance (except for emergency leaves)
            $year = $startDate->year;
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_policy_id', $policy->id)
                ->where('year', $year)
                ->first();

            $isEmergency = str_contains(strtolower($policy->name), 'emergency') || strtolower($policy->code) === 'el';

            if (!$isEmergency && $balance && $balance->remaining < $daysRequested) {
                DB::rollBack();
                return back()->withInput()->withErrors([
                    'days_requested' => "Insufficient leave balance. You have {$balance->remaining} days available but requested {$daysRequested} days.",
                ]);
            }

            // Handle file upload (supporting documents)
            $documentPath = null;
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $fileName = time() . '_' . $employee->employee_number . '_' . $file->getClientOriginalName();
                $documentPath = $file->storeAs(
                    "leave-documents/{$employee->id}",
                    $fileName,
                    'private'
                );
            }

            // Calculate workforce coverage
            $coverage = $this->coverageService->getCoverageForDate(
                $startDate,
                $employee->department_id
            );
            $coveragePercentage = $coverage['coverage_percentage'] ?? 100;

            // Create leave request
            $leaveRequest = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_policy_id' => $policy->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_requested' => $daysRequested,
                'reason' => $validated['reason'],
                'status' => 'pending',
                'submitted_at' => now(),
                'submitted_by' => $user->id,
            ]);

            // Update leave balance pending count
            if ($balance) {
                $balance->increment('pending', $daysRequested);
            }

            DB::commit();

            Log::info('Employee leave request submitted', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'leave_request_id' => $leaveRequest->id,
                'leave_type' => $policy->name,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days_requested' => $daysRequested,
                'coverage_percentage' => $coveragePercentage,
                'document_uploaded' => $documentPath !== null,
            ]);

            // TODO: Send LeaveRequestSubmitted notification to appropriate approver
            // Determine approver using ApprovalRuleEngine based on:
            // - Leave type (standard vs extended)
            // - Duration (days_requested)
            // - Coverage impact (coveragePercentage)
            // - Department settings

            $message = 'Leave request submitted successfully. ';
            
            if ($coveragePercentage < 75) {
                $message .= 'Your request will be carefully reviewed due to low department coverage (' . round($coveragePercentage, 1) . '%). ';
            }
            
            $message .= 'You will be notified once your request is processed. Tracking number: LR-' . str_pad($leaveRequest->id, 6, '0', STR_PAD_LEFT);

            return redirect()->route('employee.leave.history')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to submit leave request', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'error' => 'Failed to submit leave request. Please try again or contact HR Staff if the issue persists.',
            ]);
        }
    }

    /**
     * Cancel pending or approved leave request
     * Employees can cancel before leave start date
     */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        // Validate cancellation reason
        $request->validate([
            'cancellation_reason' => 'required|string|min:10|max:500',
        ], [
            'cancellation_reason.required' => 'Please provide a reason for cancelling this leave request.',
            'cancellation_reason.min' => 'Cancellation reason must be at least 10 characters.',
            'cancellation_reason.max' => 'Cancellation reason must not exceed 500 characters.',
        ]);

        DB::beginTransaction();
        try {
            // Get leave request (self-only access check)
            $leaveRequest = LeaveRequest::where('id', $id)
                ->where('employee_id', $employee->id)
                ->firstOrFail();

            // Validate cancellation is allowed
            if (!in_array($leaveRequest->status, ['pending', 'approved'])) {
                DB::rollBack();
                return back()->withErrors([
                    'error' => 'Cannot cancel this leave request. Only pending or approved requests can be cancelled.',
                ]);
            }

            // Validate leave hasn't started yet
            if ($leaveRequest->start_date->isPast()) {
                DB::rollBack();
                return back()->withErrors([
                    'error' => 'Cannot cancel this leave request. The leave period has already started.',
                ]);
            }

            // Update leave request status
            $leaveRequest->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->input('cancellation_reason'),
            ]);

            // Restore leave balance pending count
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_policy_id', $leaveRequest->leave_policy_id)
                ->where('year', $leaveRequest->start_date->year)
                ->first();

            if ($balance) {
                $balance->decrement('pending', $leaveRequest->days_requested);
            }

            DB::commit();

            Log::info('Employee cancelled leave request', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'leave_request_id' => $leaveRequest->id,
                'cancellation_reason' => $request->input('cancellation_reason'),
            ]);

            // TODO: Send LeaveRequestCancelled notification to approver/HR Staff

            return back()->with('success', 'Leave request cancelled successfully. Your leave balance has been restored.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Leave request not found or you do not have permission to cancel it.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to cancel leave request', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'leave_request_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to cancel leave request. Please try again or contact HR Staff if the issue persists.',
            ]);
        }
    }

    /**
     * Calculate workforce coverage impact for selected dates (AJAX endpoint)
     * Returns real-time coverage percentage and status for leave request form
     */
    public function calculateCoverage(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json([
                'error' => 'No employee record found for your account.',
            ], 403);
        }

        // Validate input
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));

            // Get coverage for the requested date range
            $coverage = $this->coverageService->getCoverageForDate(
                $startDate,
                $employee->department_id
            );

            $coveragePercentage = $coverage['coverage_percentage'] ?? 100;
            $status = $this->determineCoverageStatus($coveragePercentage);

            // Get alternative dates with better coverage (if coverage is low)
            $alternativeDates = [];
            if ($coveragePercentage < 80) {
                // Check next 2 weeks for better dates
                $alternativeDates = $this->findAlternativeDates(
                    $startDate,
                    $endDate,
                    $employee->department_id
                );
            }

            return response()->json([
                'coverage_percentage' => round($coveragePercentage, 1),
                'status' => $status,
                'message' => $this->getCoverageMessage($status, $coveragePercentage),
                'alternative_dates' => $alternativeDates,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to calculate workforce coverage', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to calculate coverage. Please try again.',
            ], 500);
        }
    }

    /**
     * Determine coverage status based on percentage
     */
    private function determineCoverageStatus(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'optimal'; // 🟢
        } elseif ($percentage >= 75) {
            return 'acceptable'; // 🟡
        } elseif ($percentage >= 60) {
            return 'warning'; // 🟠
        } else {
            return 'critical'; // 🔴
        }
    }

    /**
     * Get user-friendly coverage message
     */
    private function getCoverageMessage(string $status, float $percentage): string
    {
        switch ($status) {
            case 'optimal':
                return "Your dates have minimal impact on department coverage ({$percentage}%). Your request will likely be approved quickly.";
            case 'acceptable':
                return "Your dates have slight impact on department coverage ({$percentage}%), but it's manageable. Your request should be approved.";
            case 'warning':
                return "Your dates have significant impact on department coverage ({$percentage}%). Your request may require additional justification or date adjustment.";
            case 'critical':
                return "Your dates have severe impact on department coverage ({$percentage}%). Your request will likely be rejected. Please consider alternative dates.";
            default:
                return "Department coverage: {$percentage}%";
        }
    }

    /**
     * Find alternative dates with better coverage
     */
    private function findAlternativeDates(Carbon $startDate, Carbon $endDate, int $departmentId): array
    {
        $alternatives = [];
        $duration = $startDate->diffInDays($endDate) + 1;
        $checkDate = Carbon::now()->addWeek();

        // Check next 4 weeks for better dates
        for ($i = 0; $i < 28; $i++) {
            $testStart = $checkDate->copy();
            $testEnd = $checkDate->copy()->addDays($duration - 1);

            $coverage = $this->coverageService->getCoverageForDate(
                $testStart,
                $departmentId
            );

            $percentage = $coverage['coverage_percentage'] ?? 100;

            if ($percentage >= 85 && count($alternatives) < 3) {
                $alternatives[] = [
                    'start_date' => $testStart->toDateString(),
                    'end_date' => $testEnd->toDateString(),
                    'coverage_percentage' => round($percentage, 1),
                    'status' => $this->determineCoverageStatus($percentage),
                ];
            }

            $checkDate->addDay();
        }

        return $alternatives;
    }
}
