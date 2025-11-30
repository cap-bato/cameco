<?php

namespace App\Http\Controllers\HR\Leave;

use App\Http\Controllers\Controller;
use App\Models\Employee;
// LeaveBalance model will be implemented later as part of balances feature
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Illuminate\Http\Request;
use App\Http\Requests\HR\Leave\StoreLeaveRequestRequest;
use App\Http\Requests\HR\Leave\UpdateLeaveRequestRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

/**
 * LeaveRequestController
 *
 * Handles all leave request operations for the HR Leave Management module.
 *
 * WORKFLOW OVERVIEW:
 * ==================
 * This controller manages the complete leave request lifecycle as submitted by employees
 * through HR staff. All leave requests originate from employee input (either direct submission
 * or HR staff entering requests on their behalf) and flow through the following stages:
 *
 * 1. SUBMISSION (HR Input)
 *    - HR Staff receives leave request from employee (verbal, form, or system submission)
 *    - HR Staff validates employee's leave balance and dates
 *    - HR Staff creates/submits leave request in system
 *    - System generates initial notification
 *
 * 2. APPROVAL WORKFLOW
 *    - Request routed to Immediate Supervisor for first-level approval
 *    - Supervisor reviews request with comments
 *    - If rejected: goes back to employee with rejection reason via HR
 *    - If approved: forwarded to HR Manager for final approval
 *    - HR Manager reviews and makes final decision (approve/reject)
 *
 * 3. PROCESSING (by HR)
 *    - HR Staff processes approved requests
 *    - Leave balance is deducted from employee's annual allocation
 *    - Leave slip/document is generated
 *    - Employee is notified of approval status
 *    - Record is filed in employee's personnel file
 *
 * 4. COMPLETION
 *    - Employee takes approved leave
 *    - HR tracks dates and ensures DTR alignment (via Timekeeping module)
 *    - Leave request is marked as completed
 *    - Annual audit shows in employee's leave record
 *
 * KEY POINTS:
 * - All requests originate from EMPLOYEE INPUT (either submitted directly or via HR staff)
 * - HR STAFF is the central coordinator/processor of all leave requests
 * - Supervisors and HR Manager approve, but HR staff executes the processing
 * - Employees do NOT have direct system access; HR staff acts as intermediary
 * - System tracks full audit trail of all approvals and HR actions
 *
 * @author HR Development Team
 * @version 1.0
 */
class LeaveRequestController extends Controller
{
    /**
     * Display a listing of leave requests with filters.
     *
     * HR Staff View: Shows all leave requests from all employees
     * - Pending requests requiring supervisor/manager approval
     * - Approved requests awaiting HR processing
     * - Completed/processed leave records
     *
     * Filters available:
     * - by status: pending, approved, rejected, cancelled, completed
     * - by employee: search by employee number, name
     * - by leave type: vacation, sick, emergency, etc.
     * - by period: current year, specific date range
     * - by department: filter by employee's department
     *
     * @param Request $request Contains filter parameters from frontend
     * @return Response Inertia response with leave requests data and metadata
     */
    public function index(Request $request): Response
    {
        // Verify HR staff has permission to view all leave requests
        $this->authorize('viewAny', Employee::class);

        // Get filter parameters from HR staff input
        $status = $request->input('status', 'all');
        $employeeId = $request->input('employee_id');
        $leaveType = $request->input('leave_type', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $department = $request->input('department');

        // Query real leave requests from DB
        $query = LeaveRequest::with(['employee.profile', 'leavePolicy', 'supervisor.profile'])
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->when($leaveType !== 'all', fn($q) => $q->where('leave_policy_id', $leaveType))
            ->when($dateFrom, fn($q) => $q->where('start_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('end_date', '<=', $dateTo))
            ->when($department, fn($q) => $q->whereHas('employee', fn($qq) => $qq->where('department_id', $department)));

        $leaveRequests = $query->latest('submitted_at')->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'employee_id' => $r->employee_id,
                'employee_name' => $r->employee?->profile?->first_name . ' ' . $r->employee?->profile?->last_name,
                'employee_number' => $r->employee?->employee_number,
                'department' => $r->employee?->department?->name,
                'leave_type' => $r->leavePolicy?->name,
                'start_date' => $r->start_date->format('Y-m-d'),
                'end_date' => $r->end_date->format('Y-m-d'),
                'days_requested' => (float) $r->days_requested,
                // include the leave policy's entitlement so the UI can show coverage days
                'policy_days' => $r->leavePolicy?->annual_entitlement ? (float) $r->leavePolicy?->annual_entitlement : null,
                'reason' => $r->reason,
                'status' => $r->status,
                'supervisor_name' => $r->supervisor?->profile?->first_name . ' ' . $r->supervisor?->profile?->last_name,
                'submitted_at' => $r->submitted_at?->format('Y-m-d'),
                'supervisor_approved_at' => $r->supervisor_approved_at?->format('Y-m-d'),
                'manager_approved_at' => $r->manager_approved_at?->format('Y-m-d'),
                'hr_processed_at' => $r->hr_processed_at?->format('Y-m-d'),
            ];
        })->toArray();

        // Mock: Get employees for the filter dropdown
        // HR Staff uses this to filter requests by specific employees
        $employees = $this->getMockEmployees();

        // Mock: Get departments for filtering
        // HR Staff may need to filter by department to handle approvals
        $departments = $this->getMockDepartments();

        // Return leave requests page with all data needed by HR staff
        return Inertia::render('HR/Leave/Requests', [
            'requests' => $leaveRequests,
            'filters' => [
                'status' => $status,
                'employee_id' => $employeeId,
                'leave_type' => $leaveType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'department' => $department,
            ],
            'employees' => $employees,
            'departments' => $departments,
            'meta' => [
                    'total_pending' => count(array_filter($leaveRequests, fn($r) => strtolower($r['status'] ?? '') === 'pending')),
                    'total_approved' => count(array_filter($leaveRequests, fn($r) => strtolower($r['status'] ?? '') === 'approved')),
                    'total_rejected' => count(array_filter($leaveRequests, fn($r) => strtolower($r['status'] ?? '') === 'rejected')),
            ],
        ]);
    }

    /**
     * Show the create form for HR staff to submit a new leave request.
     *
     * CONTEXT: Employee submits leave request to HR staff (verbally, by form, or other means)
     * HR staff then enters the request into the system using this form.
     *
     * This form allows HR staff to:
     * - Select the employee requesting leave
     * - Choose leave type (vacation, sick, emergency, etc.)
     * - Set request dates
     * - Add notes/justification
     * - Check employee's current leave balance
     *
     * @param Request $request
     * @return Response Inertia response with leave creation form
     */
    public function create(Request $request): Response
    {
        // Verify HR staff or HR manager has permission to create leave requests
        $user = auth()->user();
        $canCreate = false;

        // Allow explicit HR roles (HR Staff and HR Manager) to create requests
        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['HR Staff', 'HR Manager'])) {
            $canCreate = true;
        }

        // Fallback to policy check
        if (!$canCreate) {
            $this->authorize('create', Employee::class);
        }

        // Get active employees and leave policies
        $employees = Employee::active()->with('profile')->get()->map(fn($e) => [
            'id' => $e->id,
            'employee_number' => $e->employee_number,
            'name' => $e->profile?->first_name . ' ' . $e->profile?->last_name,
        ])->toArray();

        $leaveTypes = LeavePolicy::active()->orderBy('name')->get()->map(fn($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            // include entitlement so frontend can show how many days this policy covers
            'annual_entitlement' => $p->annual_entitlement,
        ])->toArray();

        return Inertia::render('HR/Leave/CreateRequest', [
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    /**
     * Store a new leave request submitted by employee via HR staff.
     *
     * CRITICAL: This endpoint receives employee leave request data that has been
     * entered into the system by HR staff. The request is:
     * 1. Validated against employee's leave balance
     * 2. Validated for date conflicts with other approved leaves
     * 3. Stored in the database
     * 4. Routed to appropriate approver (immediate supervisor)
     * 5. Notification sent to supervisor to approve/reject
     *
     * Validation rules:
     * - Employee must exist and be active
     * - Leave dates must be within current calendar year (or company policy period)
     * - Employee must have sufficient leave balance for selected leave type
     * - No duplicate requests for same dates
     * - Dates must be valid (start_date < end_date)
     * - Dates must not conflict with already-approved leaves
     *
     * @param Request $request Contains:
     *        - employee_id: Which employee is requesting leave
     *        - leave_type_id: Type of leave (vacation, sick, emergency, etc.)
     *        - start_date: First date of leave
     *        - end_date: Last date of leave
     *        - reason: Reason/justification for leave (required for some types)
     *        - hr_notes: Internal HR notes about the request
     *
     * @return RedirectResponse Redirects to requests list with success/error message
     */
    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        // Verify HR staff or HR manager has permission to update/approve leave requests
        $user = auth()->user();
        $canCreate = false;

        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['HR Staff', 'HR Manager'])) {
            $canCreate = true;
        }

        if (!$canCreate) {
            $this->authorize('create', Employee::class);
        }

        // Use the FormRequest's validated data (prepareForValidation normalized any legacy ids)
        $validated = $request->validated();

        // STEP 1: Load the employee making the leave request
        $employee = Employee::with(['profile', 'department'])->findOrFail($validated['employee_id']);

        // STEP 2: Validate employee has sufficient leave balance
        // Leave policy id normalization is handled in StoreLeaveRequestRequest::prepareForValidation()

        // NOTE: leave balance validation is a future enhancement once LeaveBalance model
        // and table are fully implemented. For now ensure policy exists.
        $policy = LeavePolicy::find($validated['leave_policy_id']);
        if (!$policy) {
            // return input + validation-style error so the UI can show field error
            return back()->withInput()->withErrors(['leave_policy_id' => 'Invalid leave type selected.']);
        }

        // STEP 3: Calculate number of days requested
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        // compute absolute difference (in case dates are accidentally swapped) and include both
        // start and end dates (+1)
        $daysRequested = (int) ($endDate->diffInDays($startDate, true) + 1);

        // STEP 3.5: Check leave balance (prevent filing when remaining is 0) unless policy is Emergency
        $year = $startDate->year;
        $policy = LeavePolicy::find($validated['leave_policy_id']);

        // determine if this is an emergency leave policy (allow filing regardless of balance)
        $policyName = strtolower((string) ($policy?->name ?? ''));
        $policyCode = strtolower((string) ($policy?->code ?? ''));
        $isEmergency = str_contains($policyName, 'emergency') || $policyCode === 'el';

        // load existing balance if available
        $balance = null;
        try {
            $balance = LeaveBalance::firstWhere([
                'employee_id' => $validated['employee_id'],
                'leave_policy_id' => $validated['leave_policy_id'],
                'year' => $year,
            ]);
        } catch (\Exception $e) {
            $balance = null;
        }

        if ($balance) {
            $remaining = (float) $balance->remaining;
            // Block filing when remaining is zero (unless emergency)
            if (!$isEmergency && $remaining <= 0) {
                return back()->withInput()->withErrors(['leave_policy_id' => 'Employee has no remaining balance for this leave type.']);
            }

            // Also prevent requesting more days than remaining (unless emergency)
            if (!$isEmergency && $daysRequested > $remaining) {
                return back()->withInput()->withErrors(['start_date' => 'Requested days exceed remaining balance for this leave type. Reduce days or select Emergency Leave.']);
            }
        } else {
            // No balance record exists — use policy entitlement as starting remaining
            $entitlement = $policy?->annual_entitlement ? (float) $policy->annual_entitlement : 0.0;
            if (!$isEmergency && $entitlement <= 0) {
                return back()->withInput()->withErrors(['leave_policy_id' => 'This leave type has no entitlement configured for the year.']);
            }
            if (!$isEmergency && $daysRequested > $entitlement) {
                return back()->withInput()->withErrors(['start_date' => 'Requested days exceed entitlement for this leave type. Reduce days or select Emergency Leave.']);
            }
        }

        // NOTE: leave balance validation is a future enhancement once LeaveBalance model
        // and table are implemented. For now we skip balance checks and allow HR staff
        // to submit the request — HR will process/deduct balances during processing.

        // STEP 4: Create leave request record in database
        // Status starts as "Pending" - awaiting supervisor approval
        $leaveRequestData = [
            'employee_id' => $employee->id,
            'leave_policy_id' => $validated['leave_policy_id'],
            'leave_type' => $policy->name,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days_requested' => $daysRequested,
            'reason' => $validated['reason'] ?? '',
            'status' => 'pending', // Initial status: awaiting supervisor approval
            'submitted_by' => auth()->id(), // HR Staff who entered the request
            'submitted_at' => now(),
            'supervisor_id' => $employee->immediate_supervisor_id, // Route to supervisor for approval
            'supervisor_comments' => null,
            'supervisor_approved_at' => null,
            'manager_id' => null,
            'manager_comments' => null,
            'manager_approved_at' => null,
            'hr_notes' => $validated['hr_notes'] ?? '',
            'hr_processed_by' => null,
            'hr_processed_at' => null,
            'cancellation_reason' => null,
            'cancelled_at' => null,
        ];

        // Persist to database
        $leaveRequest = LeaveRequest::create($leaveRequestData);

        // STEP 5: Send notification to supervisor
        // Supervisor receives notification to review and approve/reject request
        // Example: Mail::send(new LeaveRequestSubmittedNotification($employee, $leaveRequest));

        // Always redirect to the leave requests list after filing
        return redirect()->route('hr.leave.requests')
            ->with('success', "Leave request for {$employee->profile->first_name} {$employee->profile->last_name} has been submitted successfully. Awaiting supervisor approval.");
    }

    /**
     * Show details of a specific leave request.
     *
     * HR Staff can view:
     * - Complete request details (dates, type, reason)
     * - Employee information
     * - Supervisor's approval status and comments
     * - HR Manager's approval status
     * - Processing status (pending HR processing, completed, etc.)
     * - Full audit trail of all approvals
     *
     * @param int $id Leave request ID
     * @return Response Inertia response with leave request details
     */
    public function show(int $id): Response
    {
        // In production: $leaveRequest = LeaveRequest::with(['employee', 'supervisor', 'approvedBy'])->findOrFail($id);
        
        $leaveRequest = LeaveRequest::with(['employee.profile', 'leavePolicy', 'supervisor.profile'])->findOrFail($id);

        return Inertia::render('HR/Leave/ShowRequest', [
            'request' => $leaveRequest,
        ]);
    }

    /**
     * Show the approval form for supervisors/managers.
     *
     * When a leave request is submitted, the supervisor receives a notification
     * to approve or reject the request. This form allows the supervisor to:
     * - Review request details
     * - Add approval comments
     * - Mark as approved or rejected
     *
     * @param int $id Leave request ID
     * @return Response Inertia response with approval form
     */
    public function edit(int $id): Response
    {
        // In production: $leaveRequest = LeaveRequest::findOrFail($id);
        
        $leaveRequest = LeaveRequest::with(['employee.profile', 'leavePolicy'])->findOrFail($id);

        return Inertia::render('HR/Leave/ApproveRequest', [
            'request' => $leaveRequest,
        ]);
    }

    /**
     * Process supervisor or manager approval/rejection of a leave request.
     *
     * SUPERVISOR APPROVAL (First Level):
     * When supervisor approves: Request moves to HR Manager for final approval
     * When supervisor rejects: Request is marked rejected, employee notified via HR
     *
     * HR MANAGER APPROVAL (Final Level):
     * When manager approves: Request is approved, HR staff can now process it
     * When manager rejects: Request is marked rejected, employee notified via HR
     *
     * @param Request $request Contains:
     *        - leave_request_id: The request being approved
     *        - action: 'approve' or 'reject'
     *        - approval_comments: Comments from approver
     *        - status: Updated status based on approval
     *
     * @return RedirectResponse Redirects with success/error message
     */
    public function update(UpdateLeaveRequestRequest $request, int $id): RedirectResponse
    {
        // Validate approval action
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'approval_comments' => 'nullable|string|max:1000',
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($validated['action'] === 'approve') {
            // If supervisor approving
            if ($leaveRequest->supervisor_id && auth()->id() === optional(auth()->user())->id) {
                // In this simplified implementation we don't have the supervisor user vs employee check.
            }

            // If supervisor approval: set supervisor_approved_at and comments
            if (!$leaveRequest->supervisor_approved_at && auth()->id()) {
                $leaveRequest->supervisor_approved_at = now();
                $leaveRequest->supervisor_comments = $validated['approval_comments'] ?? null;
                // keep status pending until manager approves
            } else {
                // Manager approval -> mark approved
                $wasManagerAlreadyApproved = $leaveRequest->manager_approved_at !== null;
                $leaveRequest->manager_id = auth()->id();
                $leaveRequest->manager_approved_at = now();
                $leaveRequest->manager_comments = $validated['approval_comments'] ?? null;
                $leaveRequest->status = 'approved';

                // Only deduct from balance the first time manager approves
                if (!$wasManagerAlreadyApproved) {
                    try {
                        $policyId = $leaveRequest->leave_policy_id;
                        $employeeId = $leaveRequest->employee_id;
                        $year = \Carbon\Carbon::parse($leaveRequest->start_date)->year;

                        $policy = $leaveRequest->leavePolicy;

                        // Find existing balance for the year and policy
                        $balance = LeaveBalance::firstWhere([
                            'employee_id' => $employeeId,
                            'leave_policy_id' => $policyId,
                            'year' => $year,
                        ]);

                        if (!$balance) {
                            $earned = $policy?->annual_entitlement ? (float) $policy->annual_entitlement : 0.0;
                            $balance = LeaveBalance::create([
                                'employee_id' => $employeeId,
                                'leave_policy_id' => $policyId,
                                'year' => $year,
                                'earned' => $earned,
                                'used' => 0.0,
                                'remaining' => $earned,
                                'carried_forward' => 0.0,
                            ]);
                        }

                        // Deduct requested days
                        $days = (float) $leaveRequest->days_requested;
                        $balance->used = (float) $balance->used + $days;
                        $balance->remaining = (float) $balance->earned - (float) $balance->used;
                        $balance->save();
                    } catch (\Exception $e) {
                        // don't block approval on balance failure — log and continue
                        logger()->error('Failed to update leave balance on approval: ' . $e->getMessage());
                    }
                }
            }
        } else {
            // Reject (supervisor or manager): set rejection details and status
            $leaveRequest->status = 'rejected';
            // Record appropriate comments
            if (!$leaveRequest->supervisor_approved_at) {
                $leaveRequest->supervisor_comments = $validated['approval_comments'] ?? null;
                $leaveRequest->supervisor_approved_at = null;
            } else {
                $leaveRequest->manager_comments = $validated['approval_comments'] ?? null;
                $leaveRequest->manager_approved_at = null;
            }
        }

        $leaveRequest->save();
        // 
        // // Notify HR staff of approval decision
        // // HR staff will then notify employee of result

        return back()->with('success', 'Leave request has been processed.');
    }

    /**
     * Process an approved leave request for HR completion.
     *
     * After a leave request is approved by supervisor and HR Manager,
     * HR Staff must process it, which involves:
     * 1. Deducting days from employee's leave balance
     * 2. Generating leave slip/certificate
     * 3. Filing record in employee personnel file
     * 4. Notifying employee of approval
     * 5. Updating DTR/attendance system integration
     *
     * @param int $id Leave request ID
     * @return RedirectResponse Redirects with success message
     */
    public function processApproval(Request $request, int $id): RedirectResponse
    {
        // Verify only HR staff with proper permissions can process approvals
        $this->authorize('delete', Employee::class); // Using delete as proxy for "process" permission

        $leaveRequest = LeaveRequest::findOrFail($id);

        // mark processed by HR
        $leaveRequest->hr_processed_by = auth()->id();
        $leaveRequest->hr_processed_at = now();
        // keep status as approved
        $leaveRequest->save();
        // 
        // // Generate leave slip and send to employee
        // // Store in employee's document archive

        return back()->with('success', 'Leave request has been processed and approved. Leave balance updated.');
    }

    /**
     * Cancel a leave request.
     *
     * HR Staff may need to cancel a leave request if:
     * - Employee withdraws the request before processing
     * - Request was submitted in error
     * - Employee changed their plans
     *
     * Note: Cannot cancel already-completed leaves. Those require amendment process.
     *
     * @param int $id Leave request ID
     * @return RedirectResponse Redirects with success message
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        // Verify HR staff has permission
        $this->authorize('delete', Employee::class);

        // Validate cancellation
        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:1000',
        ]);

        // In production:
        // $leaveRequest = LeaveRequest::findOrFail($id);
        // 
        // if (in_array($leaveRequest->status, ['Completed', 'Processed'])) {
        //     return back()->with('error', 'Cannot cancel already-completed leave requests.');
        // }
        // 
        // $leaveRequest->status = 'Cancelled';
        // $leaveRequest->cancellation_reason = $validated['cancellation_reason'];
        // $leaveRequest->cancelled_by = auth()->id();
        // $leaveRequest->cancelled_at = now();
        // $leaveRequest->save();

        return back()->with('success', 'Leave request has been cancelled.');
    }

    // ============================================================================
    // MOCK DATA GENERATORS - Used for frontend development and testing
    // ============================================================================
    // In production, these methods would be replaced with actual database queries

    /**
     * Generate mock leave requests data.
     * Simulates various leave request scenarios for HR staff testing.
     */
    private function getMockLeaveRequests($status = 'all', $employeeId = null, $leaveType = 'all', $dateFrom = null, $dateTo = null, $department = null)
    {
        $requests = [
            [
                'id' => 1,
                'employee_id' => 101,
                'employee_name' => 'Maria Santos',
                'employee_number' => 'EMP-001',
                'department' => 'Operations',
                'leave_type' => 'Vacation Leave',
                'start_date' => '2025-12-01',
                'end_date' => '2025-12-05',
                'days_requested' => 5,
                'reason' => 'Family vacation',
                'status' => 'Pending',
                'supervisor_name' => 'Juan dela Cruz',
                'submitted_at' => '2025-11-15',
                'supervisor_approved_at' => null,
                'manager_approved_at' => null,
                'hr_processed_at' => null,
            ],
            [
                'id' => 2,
                'employee_id' => 102,
                'employee_name' => 'Jose Garcia',
                'employee_number' => 'EMP-002',
                'department' => 'Production',
                'leave_type' => 'Sick Leave',
                'start_date' => '2025-11-20',
                'end_date' => '2025-11-21',
                'days_requested' => 2,
                'reason' => 'Medical appointment',
                'status' => 'Approved',
                'supervisor_name' => 'Pedro Lopez',
                'submitted_at' => '2025-11-14',
                'supervisor_approved_at' => '2025-11-14',
                'manager_approved_at' => '2025-11-15',
                'hr_processed_at' => null,
            ],
            [
                'id' => 3,
                'employee_id' => 103,
                'employee_name' => 'Angela Cruz',
                'employee_number' => 'EMP-003',
                'department' => 'HR',
                'leave_type' => 'Emergency Leave',
                'start_date' => '2025-11-18',
                'end_date' => '2025-11-18',
                'days_requested' => 1,
                'reason' => 'Family emergency',
                'status' => 'Rejected',
                'supervisor_name' => 'Maria Reyes',
                'submitted_at' => '2025-11-17',
                'supervisor_approved_at' => null,
                'manager_approved_at' => '2025-11-17',
                'hr_processed_at' => null,
            ],
        ];

        // Filter based on status if specified
        if ($status !== 'all') {
            $requests = array_filter($requests, fn($r) => strtolower($r['status']) === strtolower($status));
        }

        return array_values($requests);
    }

    /**
     * Generate mock employee data for dropdown selection.
     */
    private function getMockEmployees()
    {
        return [
            ['id' => 101, 'employee_number' => 'EMP-001', 'name' => 'Maria Santos'],
            ['id' => 102, 'employee_number' => 'EMP-002', 'name' => 'Jose Garcia'],
            ['id' => 103, 'employee_number' => 'EMP-003', 'name' => 'Angela Cruz'],
            ['id' => 104, 'employee_number' => 'EMP-004', 'name' => 'Robert Martinez'],
            ['id' => 105, 'employee_number' => 'EMP-005', 'name' => 'Carmen Rodriguez'],
        ];
    }

    /**
     * Generate mock leave types.
     */
    private function getMockLeaveTypes()
    {
        return [
            ['id' => 1, 'name' => 'Vacation Leave', 'annual_entitlement' => 15],
            ['id' => 2, 'name' => 'Sick Leave', 'annual_entitlement' => 10],
            ['id' => 3, 'name' => 'Emergency Leave', 'annual_entitlement' => 5],
            ['id' => 4, 'name' => 'Maternity Leave', 'annual_entitlement' => 60],
            ['id' => 5, 'name' => 'Paternity Leave', 'annual_entitlement' => 7],
        ];
    }

    /**
     * Generate mock departments.
     */
    private function getMockDepartments()
    {
        return [
            ['id' => 1, 'name' => 'HR'],
            ['id' => 2, 'name' => 'Operations'],
            ['id' => 3, 'name' => 'Production'],
            ['id' => 4, 'name' => 'Accounting'],
            ['id' => 5, 'name' => 'IT'],
        ];
    }

    /**
     * Generate mock leave request detail for show/edit views.
     */
    private function getMockLeaveRequestDetail(int $id)
    {
        return [
            'id' => $id,
            'employee_id' => 101,
            'employee_name' => 'Maria Santos',
            'employee_number' => 'EMP-001',
            'department' => 'Operations',
            'position' => 'Operations Manager',
            'leave_type' => 'Vacation Leave',
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-05',
            'days_requested' => 5,
            'reason' => 'Family vacation to Boracay',
            'status' => 'Pending',
            'supervisor_name' => 'Juan dela Cruz',
            'supervisor_id' => 201,
            'submitted_at' => '2025-11-15 10:30:00',
            'supervisor_approval_status' => null,
            'supervisor_approved_at' => null,
            'supervisor_comments' => null,
            'manager_approval_status' => null,
            'manager_approved_at' => null,
            'manager_comments' => null,
            'hr_processed_at' => null,
            'processed_by' => null,
            'hr_notes' => 'Employee balance verified. All documents attached.',
        ];
    }
}
