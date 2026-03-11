Backend Implementation: HR Leave Management Module
📋 Overview
Implement the backend database schema, models, services, and API endpoints for the HR Leave Management feature. The frontend is already complete and expects specific data structures as outlined in this document.

🎯 Business Context
Current State: HR staff acts as intermediary between employees and the system. Employees do NOT have direct system access.

Workflow:

Employee submits leave request to HR staff (verbally/form)
HR staff creates request in system
Request routes to Supervisor → HR Manager for approval
HR staff processes approved requests (deducts balance, generates slip)
HR staff notifies employee
Future Enhancement: Employee self-service portal (Phase 2)

📚 Reference Documentation
docs/HR_MODULE_ARCHITECTURE.md - Complete architecture and workflows
docs/HR_CORE_SCHEMA.md - Database schema definitions
app/Http/Controllers/HR/Leave/*.php - Controller stubs with mock data
resources/js/pages/HR/Leave/*.tsx - Frontend implementation (data structure reference)
Laravel Migration Quick Tips
Create a New Migration
php artisan make:migration create_whatever_table
Create a Migration That Creates a Specific Table
php artisan make:migration create_users_table --create=users
Create a Migration That Modifies an Existing Table
php artisan make:migration add_status_to_orders_table --table=orders
Migration File Location
All generated migrations are stored in:

database/migrations/
Running Migrations
php artisan migrate
Rolling Back Migrations
php artisan migrate:rollback
Rolling Back and Re-Running Migrations
php artisan migrate:refresh
Fresh Database Migration (Drops all tables)
php artisan migrate:fresh
🗄️ Phase 1: Database Schema & Migrations ✅
- [x] Task 1.1 Create leave_policies Migration
- [x] Task 1.2 Create leave_requests Migration  
- [x] Task 1.3 Create leave_balances Migration
- [x] Task 1.4 Create leave_audit_logs Migration

🏗️ Phase 2: Eloquent Models ✅
- [x] Task 2.1 LeavePolicy Model
- [x] Task 2.2 LeaveRequest Model
- [x] Task 2.3 LeaveBalance Model
- [x] Task 2.4 LeaveAuditLog Model

🔧 Phase 3: Service Layer ✅
- [x] Task 3.1 LeaveService
- [x] Task 3.2 LeaveBalanceService

🎮 Phase 4: Controller Implementation ✅
- [x] Task 4.1 Update LeaveRequestController
- [x] Task 4.2 Update LeavePolicyController
- [x] Task 4.3 Update LeaveBalanceController

🔐 Phase 5: Form Request Validation ✅
- [x] Task 5.1 StoreLeaveRequestRequest
- [x] Task 5.2 UpdateLeaveRequestRequest
- [x] Task 5.3 StoreLeavePolicyRequest

🧪 Phase 6: Testing Requirements ✅
- [x] Task 6.1 Unit Tests
- [x] Task 6.2 Feature Tests
- [x] Task 6.3 Integration Tests

🚀 Phase 7: Additional Routes ✅
- [x] All required routes implemented

📊 Phase 8: Database Seeders ✅
- [x] 8.1 LeavePolicySeeder
- [x] 8.2 LeaveBalanceSeeder
File: database/migrations/YYYY_MM_DD_create_leave_policies_table.php

Schema:

CREATE TABLE leave_policies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,              -- VL, SL, EL, ML, PL, BL, SP
    name VARCHAR(100) NOT NULL,                     -- Vacation Leave, Sick Leave, etc.
    description TEXT NULL,                          -- Policy description
    annual_entitlement DECIMAL(5,1) NOT NULL,       -- Days per year (e.g., 15.0)
    max_carryover DECIMAL(5,1) DEFAULT 0.0,         -- Max days to carry forward
    can_carry_forward BOOLEAN DEFAULT false,        -- Can unused days carry forward?
    is_paid BOOLEAN DEFAULT true,                   -- Is this paid leave?
    is_active BOOLEAN DEFAULT true,                 -- Policy is active
    effective_date DATE NULL,                       -- When policy takes effect
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_leave_policies_code (code),
    INDEX idx_leave_policies_is_active (is_active)
);
Required Seeders:

// 7 Standard Leave Types (matches LeavePolicyController mock data)
1. VL - Vacation Leave (15 days, 5 carryover, paid)
2. SL - Sick Leave (10 days, 0 carryover, paid)
3. EL - Emergency Leave (5 days, 0 carryover, paid)
4. ML - Maternity/Paternity Leave (90 days, 0 carryover, paid)
5. PL - Privilege Leave (8 days, 2 carryover, paid)
6. BL - Bereavement Leave (3 days, 0 carryover, paid)
7. SP - Special Leave (0 days, 0 carryover, unpaid)
Task 1.2 Create leave_requests Migration
File: database/migrations/YYYY_MM_DD_create_leave_requests_table.php

Schema:

CREATE TABLE leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_policy_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested DECIMAL(5,1) NOT NULL,           -- Total days (e.g., 5.0)
    reason TEXT NULL,                                -- Employee's reason
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    
    -- Approval Workflow Fields
    supervisor_id BIGINT UNSIGNED NULL,              -- Immediate supervisor
    supervisor_approved_at TIMESTAMP NULL,
    supervisor_comments TEXT NULL,
    
    manager_id BIGINT UNSIGNED NULL,                 -- HR Manager
    manager_approved_at TIMESTAMP NULL,
    manager_comments TEXT NULL,
    
    -- HR Processing Fields
    hr_processed_by BIGINT UNSIGNED NULL,            -- HR staff who processed
    hr_processed_at TIMESTAMP NULL,
    hr_notes TEXT NULL,                              -- Internal HR notes
    
    -- System Fields
    submitted_at TIMESTAMP NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,           -- HR staff who entered request
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_leave_requests_employee_id (employee_id),
    INDEX idx_leave_requests_status (status),
    INDEX idx_leave_requests_leave_policy_id (leave_policy_id),
    INDEX idx_leave_requests_dates (start_date, end_date),
    INDEX idx_leave_requests_supervisor_id (supervisor_id),
    INDEX idx_leave_requests_manager_id (manager_id),
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_policy_id) REFERENCES leave_policies(id) ON DELETE RESTRICT,
    FOREIGN KEY (supervisor_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (hr_processed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
);
Task 1.3 Update/Create leave_balances Migration
File: database/migrations/YYYY_MM_DD_create_leave_balances_table.php

Schema (per HR_MODULE_ARCHITECTURE.md):

CREATE TABLE leave_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_policy_id BIGINT UNSIGNED NOT NULL,
    year INT NOT NULL,                               -- Calendar year (e.g., 2025)
    opening_balance DECIMAL(5,1) DEFAULT 0.0,        -- Carried forward from previous year
    earned DECIMAL(5,1) NOT NULL,                    -- Annual entitlement
    used DECIMAL(5,1) DEFAULT 0.0,                   -- Days used
    pending DECIMAL(5,1) DEFAULT 0.0,                -- Days in pending requests
    remaining DECIMAL(5,1) NOT NULL,                 -- Calculated: earned + opening - used - pending
    carried_forward DECIMAL(5,1) DEFAULT 0.0,        -- To next year (if applicable)
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_leave_balances_employee_id (employee_id),
    INDEX idx_leave_balances_year (year),
    INDEX idx_leave_balances_leave_policy_id (leave_policy_id),
    UNIQUE KEY unique_employee_policy_year (employee_id, leave_policy_id, year),
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_policy_id) REFERENCES leave_policies(id) ON DELETE RESTRICT
);
Task 1.4 Create leave_audit_logs Migration (Optional but Recommended)
File: database/migrations/YYYY_MM_DD_create_leave_audit_logs_table.php

Schema:

CREATE TABLE leave_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    leave_request_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,                     -- submitted, approved, rejected, processed, cancelled
    performed_by BIGINT UNSIGNED NOT NULL,           -- User who performed action
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NULL,
    comments TEXT NULL,
    metadata JSON NULL,                              -- Additional data
    created_at TIMESTAMP NULL,
    
    INDEX idx_leave_audit_logs_request_id (leave_request_id),
    INDEX idx_leave_audit_logs_performed_by (performed_by),
    
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
);
Laravel Model Quick Tips
Create a New Model
php artisan make:model User
Create a Model With a Migration
php artisan make:model User -m
Create a Model With a Controller
php artisan make:model User -c
Create a Model With a Factory
php artisan make:model User -f
Create a Model With a Seeder
php artisan make:model User -s
Create a Model With Everything (Migration, Controller, Factory, Seeder)
php artisan make:model User -mcsf
Model Location
All generated models are stored in:

app/Models/
Controller Location
Controllers generated with models are stored in:

app/Http/Controllers/
🏗️ Phase 2: Eloquent Models
Task 2.1 LeavePolicy Model
File: app/Models/LeavePolicy.php

Requirements:

Fillable: code, name, description, annual_entitlement, max_carryover, can_carry_forward, is_paid, is_active, effective_date
Casts: annual_entitlement → decimal:1, max_carryover → decimal:1, can_carry_forward → boolean, is_paid → boolean, is_active → boolean
SoftDeletes trait
Relationships:
hasMany(LeaveBalance::class)
hasMany(LeaveRequest::class)
Scopes: active(), inactive()
Task 2.2 LeaveRequest Model
File: app/Models/LeaveRequest.php

Requirements:

Fillable: employee_id, leave_policy_id, start_date, end_date, days_requested, reason, status, supervisor_id, manager_id, hr_notes, submitted_by
Casts:
days_requested → decimal:1
start_date, end_date → date
submitted_at, supervisor_approved_at, manager_approved_at, hr_processed_at, cancelled_at → datetime
SoftDeletes trait
Relationships:
belongsTo(Employee::class, 'employee_id')
belongsTo(LeavePolicy::class)
belongsTo(Employee::class, 'supervisor_id')
belongsTo(User::class, 'manager_id')
belongsTo(User::class, 'hr_processed_by')
belongsTo(User::class, 'submitted_by')
hasMany(LeaveAuditLog::class) (if implemented)
Scopes: pending(), approved(), rejected(), cancelled(), forEmployee($employeeId), inDateRange($from, $to)
Accessors: getStatusLabelAttribute(), getEmployeeNameAttribute() (via relationship)
Taks 2.3 LeaveBalance Model
File: app/Models/LeaveBalance.php

Requirements:

Fillable: employee_id, leave_policy_id, year, opening_balance, earned, used, pending, remaining, carried_forward
Casts: year → integer, all balance fields → decimal:1
Relationships:
belongsTo(Employee::class)
belongsTo(LeavePolicy::class)
Scopes: forYear($year), forEmployee($employeeId)
Methods:
calculateRemaining() - Auto-calculate remaining balance
updateUsed($days) - Update used and recalculate
updatePending($days) - Update pending and recalculate
Task 2.4 LeaveAuditLog Model (Optional)
File: app/Models/LeaveAuditLog.php

Requirements:

Fillable: leave_request_id, action, performed_by, old_status, new_status, comments, metadata
Casts: metadata → array
Relationships:
belongsTo(LeaveRequest::class)
belongsTo(User::class, 'performed_by')
🔧 Phase 3: Service Layer
Task 3.1 LeaveService
File: app/Services/HR/LeaveService.php

Required Methods:

// Leave Request Operations
public function createLeaveRequest(Employee $employee, array $data): LeaveRequest
public function approveRequest(LeaveRequest $request, User $approver, ?string $comments = null): LeaveRequest
public function rejectRequest(LeaveRequest $request, User $approver, string $reason): LeaveRequest
public function cancelRequest(LeaveRequest $request, string $reason): LeaveRequest
public function processApprovedRequest(LeaveRequest $request, User $hrUser): LeaveRequest

// Balance Operations
public function getEmployeeBalance(Employee $employee, int $year, LeavePolicy $policy): ?LeaveBalance
public function initializeBalancesForEmployee(Employee $employee, int $year): Collection
public function deductLeaveBalance(LeaveBalance $balance, float $days): LeaveBalance
public function calculateRemainingDays(LeaveBalance $balance): float

// Validation Methods
public function validateLeaveRequest(Employee $employee, LeavePolicy $policy, Carbon $startDate, Carbon $endDate): array
public function hasSufficientBalance(Employee $employee, LeavePolicy $policy, float $daysRequested): bool
public function hasConflictingRequest(Employee $employee, Carbon $startDate, Carbon $endDate): bool

// Query Methods
public function getLeaveRequestsForApproval(User $approver, ?string $status = null): Collection
public function getEmployeeLeaveHistory(Employee $employee, ?int $year = null): Collection
Business Logic:

Validate sufficient leave balance before creating request
Check for overlapping leave requests (same employee, conflicting dates)
Calculate working days (exclude weekends/holidays - future enhancement)
Update leave balance pending field when request is created
Deduct from balance only when request is fully approved and processed
Generate leave slip PDF (future enhancement)
Send email notifications (future enhancement)
Log all actions to audit trail
Task 3.2 LeaveBalanceService
File: app/Services/HR/LeaveBalanceService.php

Required Methods:

public function getBalancesForEmployee(Employee $employee, int $year): Collection
public function getBalanceSummary(int $year, ?int $departmentId = null): array
public function carryForwardBalances(int $fromYear, int $toYear): int
public function recalculateAllBalances(int $year): int
public function exportBalances(int $year, string $format = 'csv'): string
🎮 Phase 4: Controller Implementation
Task 4.1 Update LeaveRequestController
File: app/Http/Controllers/HR/Leave/LeaveRequestController.php

Current State: Contains mock data generators
Action: Replace mock methods with real database queries

Required Updates:

index() - Replace getMockLeaveRequests():

$query = LeaveRequest::with(['employee.profile', 'leavePolicy', 'supervisor.profile'])
    ->when($status !== 'all', fn($q) => $q->where('status', $status))
    ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
    ->when($leaveType !== 'all', fn($q) => $q->whereHas('leavePolicy', fn($q) => $q->where('code', $leaveType)))
    ->when($dateFrom, fn($q) => $q->where('start_date', '>=', $dateFrom))
    ->when($dateTo, fn($q) => $q->where('end_date', '<=', $dateTo))
    ->when($department, fn($q) => $q->whereHas('employee.department', fn($q) => $q->where('id', $department)))
    ->latest('submitted_at')
    ->get();
store() - Implement real validation and storage:

// Validate using LeaveService
$validation = $this->leaveService->validateLeaveRequest($employee, $policy, $startDate, $endDate);
if (!$validation['valid']) {
    return back()->withErrors(['error' => $validation['message']]);
}

// Create request
$leaveRequest = $this->leaveService->createLeaveRequest($employee, $validated);
update() - Implement approval/rejection logic:

$action = $request->input('action'); // 'approve' or 'reject'
$comments = $request->input('approval_comments');

if ($action === 'approve') {
    $this->leaveService->approveRequest($leaveRequest, auth()->user(), $comments);
} else {
    $this->leaveService->rejectRequest($leaveRequest, auth()->user(), $comments);
}
processApproval() - Implement HR processing:

$this->leaveService->processApprovedRequest($leaveRequest, auth()->user());
destroy() - Implement cancellation:

$this->leaveService->cancelRequest($leaveRequest, $request->input('reason'));
Response Format (must match frontend expectations):

return Inertia::render('HR/Leave/Requests', [
    'requests' => $requests->map(fn($r) => [
        'id' => $r->id,
        'employee_id' => $r->employee_id,
        'employee_name' => $r->employee->profile->full_name,
        'employee_number' => $r->employee->employee_number,
        'department' => $r->employee->department?->name,
        'leave_type' => $r->leavePolicy->name,
        'start_date' => $r->start_date->format('Y-m-d'),
        'end_date' => $r->end_date->format('Y-m-d'),
        'days_requested' => $r->days_requested,
        'reason' => $r->reason,
        'status' => $r->status,
        'supervisor_name' => $r->supervisor?->profile?->full_name,
        'submitted_at' => $r->submitted_at->format('Y-m-d'),
        'supervisor_approved_at' => $r->supervisor_approved_at?->format('Y-m-d'),
        'manager_approved_at' => $r->manager_approved_at?->format('Y-m-d'),
        'hr_processed_at' => $r->hr_processed_at?->format('Y-m-d'),
    ]),
    'filters' => [...],
    'employees' => [...],
    'departments' => [...],
    'meta' => [
        'total_pending' => $requests->where('status', 'pending')->count(),
        'total_approved' => $requests->where('status', 'approved')->count(),
        'total_rejected' => $requests->where('status', 'rejected')->count(),
    ],
]);
Task 4.2 Update LeavePolicyController
File: app/Http/Controllers/HR/Leave/LeavePolicyController.php

Required Updates:

index() - Query from database:

$policies = LeavePolicy::active()->orderBy('name')->get();

return Inertia::render('HR/Leave/Policies', [
    'policies' => $policies->map(fn($p) => [
        'id' => $p->id,
        'code' => $p->code,
        'name' => $p->name,
        'description' => $p->description,
        'annual_entitlement' => $p->annual_entitlement,
        'max_carryover' => $p->max_carryover,
        'can_carry_forward' => $p->can_carry_forward,
        'is_paid' => $p->is_paid,
    ]),
    'canEdit' => auth()->user()->can('hr.leave.policies.update'),
]);
store() - Create new policy (Admin only)

update() - Update existing policy

destroy() - Soft delete policy (check for active balances first)

Task 4.3 Update LeaveBalanceController
File: app/Http/Controllers/HR/Leave/LeaveBalanceController.php

Required Updates:

index() - Query real balances:
$year = $request->input('year', now()->year);
$employeeId = $request->input('employee_id');

$employeesQuery = Employee::with(['profile', 'department'])
    ->where('status', 'active')
    ->when($employeeId, fn($q) => $q->where('id', $employeeId));

$employees = $employeesQuery->get();

$balances = $employees->map(function($emp) use ($year) {
    $empBalances = LeaveBalance::where('employee_id', $emp->id)
        ->where('year', $year)
        ->with('leavePolicy')
        ->get();
    
    return [
        'id' => $emp->id,
        'employee_number' => $emp->employee_number,
        'name' => $emp->profile->full_name,
        'department' => $emp->department?->name,
        'balances' => $empBalances->map(fn($b) => [
            'type' => $b->leavePolicy->code,
            'name' => $b->leavePolicy->name,
            'earned' => $b->earned,
            'used' => $b->used,
            'remaining' => $b->remaining,
            'carried_forward' => $b->carried_forward,
        ])->toArray(),
    ];
});
🔐 Phase 5: Form Request Validation
Task 5.1 StoreLeaveRequestRequest
File: app/Http/Requests/HR/Leave/StoreLeaveRequestRequest.php

public function rules(): array
{
    return [
        'employee_id' => 'required|exists:employees,id',
        'leave_policy_id' => 'required|exists:leave_policies,id',
        'start_date' => 'required|date|after_or_equal:today',
        'end_date' => 'required|date|after_or_equal:start_date',
        'reason' => 'required|string|max:1000',
        'hr_notes' => 'nullable|string|max:1000',
    ];
}
Task 5.2 UpdateLeaveRequestRequest
File: app/Http/Requests/HR/Leave/UpdateLeaveRequestRequest.php

public function rules(): array
{
    return [
        'action' => 'required|in:approve,reject',
        'approval_comments' => 'required_if:action,reject|nullable|string|max:500',
    ];
}
Task 5.3 StoreLeavePolicyRequest
File: app/Http/Requests/HR/Leave/StoreLeavePolicyRequest.php

public function rules(): array
{
    return [
        'code' => 'required|string|max:10|unique:leave_policies,code',
        'name' => 'required|string|max:100',
        'description' => 'nullable|string',
        'annual_entitlement' => 'required|numeric|min:0|max:365',
        'max_carryover' => 'nullable|numeric|min:0|max:365',
        'can_carry_forward' => 'boolean',
        'is_paid' => 'boolean',
    ];
}
🧪 Phase 6: Testing Requirements
Task 6.1 Unit Tests
Files: tests/Unit/Services/HR/LeaveServiceTest.php

Test Cases:

✅ Create leave request with sufficient balance
✅ Reject leave request with insufficient balance
✅ Detect conflicting leave requests
✅ Approve request updates status correctly
✅ Reject request updates status and logs reason
✅ Process approved request deducts balance
✅ Calculate remaining days correctly
✅ Carry forward balances to next year
Task 6.2 Feature Tests
Files: tests/Feature/HR/Leave/LeaveRequestTest.php

Test Cases:

✅ HR can view all leave requests
✅ HR can create leave request for employee
✅ Supervisor can approve/reject requests
✅ Manager can approve/reject after supervisor
✅ HR can process approved requests
✅ HR can cancel requests
✅ Employee cannot access leave endpoints (no direct access)
✅ Unauthorized users cannot access leave management
Task 6.3 Integration Tests
Files: tests/Integration/HR/Leave/LeaveWorkflowTest.php

Test Cases:

✅ Complete workflow: Create → Supervisor Approve → Manager Approve → HR Process
✅ Rejection workflow: Create → Supervisor Reject → Status updated
✅ Balance deduction workflow: Approve → Process → Balance updated
✅ Year-end carry forward workflow
🚀 Phase 7: Additional Routes
File: routes/hr.php

Already Exists:

✅ GET /hr/leave/requests - List requests
✅ GET /hr/leave/requests/create - Show create form
✅ POST /hr/leave/requests - Store request
✅ GET /hr/leave/requests/{id} - Show request
✅ GET /hr/leave/requests/{id}/edit - Show edit form
✅ PUT /hr/leave/requests/{id} - Update request (approve/reject)
✅ POST /hr/leave/requests/{id}/process - HR process
✅ DELETE /hr/leave/requests/{id} - Cancel request
✅ GET /hr/leave/balances - List balances
✅ GET /hr/leave/policies - List policies
To Add:

// Leave Policies CRUD (Admin only)
Route::post('/leave/policies', [LeavePolicyController::class, 'store'])
    ->name('policies.store');
Route::put('/leave/policies/{id}', [LeavePolicyController::class, 'update'])
    ->name('policies.update');
Route::delete('/leave/policies/{id}', [LeavePolicyController::class, 'destroy'])
    ->name('policies.destroy');

// Leave Balance Operations
Route::post('/leave/balances/initialize', [LeaveBalanceController::class, 'initialize'])
    ->name('balances.initialize'); // Initialize balances for new year
Route::post('/leave/balances/carry-forward', [LeaveBalanceController::class, 'carryForward'])
    ->name('balances.carry-forward'); // Year-end carry forward
📊 Phase 8: Database Seeders
8.1 LeavePolicySeeder
File: database/seeders/LeavePolicySeeder.php

Seed 7 standard leave types (see Phase 1.1)

8.2 LeaveBalanceSeeder (Optional - for testing)
File: database/seeders/LeaveBalanceSeeder.php

Initialize balances for all active employees for current year

✅ Acceptance Criteria
Backend Completeness

All 4 database tables created with correct schema

All 4 models created with relationships and scopes

LeaveService and LeaveBalanceService fully implemented

All 3 controllers updated with real database queries

All form request validators created

Leave policy seeder created and runs successfully

All routes defined and protected by appropriate middleware
Data Contract (Frontend Expectations)

/hr/leave/requests returns array of requests with exact structure defined in Phase 4.1

/hr/leave/policies returns array of policies with exact structure defined in Phase 4.2

/hr/leave/balances returns nested employee-balances structure defined in Phase 4.3
Business Logic

Cannot create leave request with insufficient balance

Cannot create overlapping leave requests for same employee

Approval workflow: Pending → Supervisor Approve → Manager Approve → HR Process

Balance only deducted after HR processes approved request

Audit trail logs all status changes
Testing

Minimum 80% code coverage on service layer

All feature tests passing

Integration test for complete workflow passing
Authorization

Only HR staff with hr.leave.requests.view can access leave management

Only supervisors can approve requests for their direct reports

Only HR managers can give final approval

Only HR staff can process approved requests
🎯 Success Metrics
Frontend Integration: All 3 leave pages load real data without errors
Workflow Completion: Complete leave request cycle works end-to-end
Performance: Leave requests page loads in < 500ms with 100+ records
Data Integrity: All balances recalculate correctly after requests
Audit Compliance: Full audit trail for all leave actions
📝 Notes for Developers
Important Considerations
Authorization Check: Controllers currently have $this->authorize('viewAny', Employee::class) - this is temporarily disabled for testing. Re-enable once proper permissions are seeded.

Employee-Profile Relationship: Remember employees link to profiles table for personal data. When displaying employee names:

$employee->profile->full_name // NOT $employee->name
Date Calculations: For Phase 1, use simple date diff. Future enhancement: exclude weekends and holidays.

Notifications: Stub notification methods for now. Implement in Phase 2.

PDF Generation: Leave slip generation to be implemented in Phase 2.

Frontend Routes: Frontend is already wired up and expects these exact routes:

POST /hr/leave/requests (create)
PUT /hr/leave/requests/{id} (approve/reject)
POST /hr/leave/requests/{id}/process (HR process)
DELETE /hr/leave/requests/{id} (cancel)
Mock Data Reference
Current mock data in controllers serves as data structure reference. Your database queries must return data in the exact same format.

Testing Strategy
Start with unit tests for LeaveService methods
Add feature tests for controller endpoints
End with integration test for complete workflow
Use database factories for test data