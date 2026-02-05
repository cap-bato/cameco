# Leave Management Backend Implementation - Backend API Only

**Issue Type:** Backend Feature Implementation  
**Priority:** HIGH  
**Estimated Duration:** 3-4 weeks  
**Current Progress:** 🟢 Phase 1 Complete (6/6 tasks complete)  
**Dependencies:** ✅ All Frontend Pages Complete, ✅ Database Models Complete  
**Related Modules:** HR Module, Admin Module, Employee Portal

---

## 📊 Latest Progress Update (December 8, 2025)

### Phase 1: Office Admin Backend - Policy Management ✅ 100% Complete

**Completed Tasks:**
1. ✅ **Task 1.1** - Leave Policy CRUD Controller
   - File: `app/Http/Controllers/Admin/LeavePolicyController.php`
   - Status: Already implemented with full CRUD operations
   - Methods: index(), store(), update(), destroy(), configureApprovalRules(), updateApprovalRules()
   - Routes: All configured with proper permissions in `routes/admin.php`
   - Frontend Integration: Connected to `Admin/LeavePolicies/Index.tsx`

2. ✅ **Task 1.2** - Leave Policy Authorization Policy
   - File: `app/Policies/LeavePolicyPolicy.php` (NEW - Created)
   - Status: Implemented and registered in AuthServiceProvider
   - Methods: viewAny(), view(), create(), update(), delete(), restore(), forceDelete()
   - Business Rules: Enforces Office Admin permissions, validates no active dependencies before deletion
   - Registration: Added to `AuthServiceProvider::$policies` array

3. ✅ **Task 1.3** - Leave Management Service (NEW - Created)
   - File: `app/Services/HR/LeaveManagementService.php`
   - Status: Fully implemented with balance initialization and synchronization
   - Methods:
     - `initializeBalancesForNewPolicy()` - Creates balances for all active employees when policy created
     - `recalculateBalancesForUpdatedPolicy()` - Updates balances when policy entitlement changes
     - `syncEmployeeBalances()` - Syncs balances for all employees (handles new hires)
     - `calculateProratedEntitlement()` - Prorates leave for mid-year hires
     - `calculateMonthlyAccrual()` - Calculates monthly accrual amount
     - `adjustBalance()` - Manual balance adjustments with audit trail
   - Business Logic: Monthly accrual (annual_entitlement / 12), prorated calculations, transaction handling
   - Integration: Uses WorkforceCoverageService for coverage analysis
   - Logging: Comprehensive activity logs for all operations

4. ✅ **Task 1.4** - Approval Workflow Configuration (Already Implemented)
   - File: `app/Http/Controllers/Admin/LeavePolicyController.php`
   - Status: Complete - integrated into LeavePolicyController (not separate controller)
   - Methods:
     - `configureApprovalRules()` - Display approval rules configuration page
     - `updateApprovalRules()` - Store approval workflow rules in system_settings
     - `getApprovalRulesConfiguration()` - Retrieve current rules with defaults
     - `getApprovalRuleDescription()` - Human-readable rule descriptions
   - Rules Stored: 13 configurable approval rules across 7 categories:
     - Duration thresholds (tier1: 5 days, tier2: 15 days)
     - Balance thresholds (minimum balance: 5 days)
     - Advance notice requirements (default: 3 days)
     - Coverage percentage (threshold: 75%)
     - Leave type restrictions
     - Blackout date enforcement
     - Frequency limits
   - Storage: SystemSetting model with keys like `leave_approval.duration.threshold_days`
   - Frontend Integration: `Admin/LeavePolicies/ApprovalRules.tsx` with workflow tester
   - Routes: GET/PUT `/admin/leave-policies/approval-rules`

**All Phase 1 Tasks Complete! ✅**

**Next Steps (Phase 2):**
1. Wire LeaveManagementService to LeavePolicyController store/update methods
2. Begin Phase 2 - Employee Backend (Leave Request Submission)
3. Register scheduled commands in Kernel.php for monthly accrual and year-end carryover

---

## Executive Summary

Implement **BACKEND ONLY** - Connect existing frontend pages to backend controllers, services, and business logic. All React/TypeScript frontend components are already built and functional.

**Existing Frontends (Already Built):**
- ✅ **Office Admin:** `/admin/leave-policies` - Policy CRUD and approval rules configuration
- ✅ **HR Manager/Staff:** `/hr/leave/requests` - Approval interface with filters
- ✅ **HR Manager/Staff:** `/hr/leave/balances` - Balance viewing
- ✅ **HR Manager/Staff:** `/hr/leave/policies` - Policy viewing
- ✅ **HR Manager/Staff:** `/hr/leave/create-request` - Submit on behalf of employee
- ✅ **Employee:** `/employee/leave/create-request` - Leave request submission with coverage check
- ✅ **Employee:** `/employee/leave/balances` - View leave balances
- ✅ **Employee:** `/employee/leave/history` - View request history

**Backend Deliverables Needed:**
1. **Office Admin Backend:** Controllers for policy CRUD, approval rules, blackout periods
2. **HR Backend:** Controllers for request approval/rejection, balance adjustments
3. **Employee Backend:** Controllers for request submission, balance viewing
4. **Services:** LeaveBalanceCalculator, LeaveApprovalWorkflow, WorkforceCoverageService
5. **Scheduled Jobs:** Monthly accrual, year-end carryover
6. **Notifications:** Email + in-app for all leave actions
7. **API Endpoints:** Coverage calculation, balance checking

---

## Database Infrastructure Status

✅ **COMPLETE** - All required database tables and relationships exist:

### Existing Migrations
1. **`2025_11_29_000001_create_leave_policies_table.php`**
   - Schema: id, code, name, description, annual_entitlement, max_carryover, can_carry_forward, is_paid, is_active, effective_date
   - Indexes: code, is_active
   - Status: ✅ Complete - no modifications needed

2. **`2025_11_29_000002_create_leave_requests_table.php`**
   - Schema: id, employee_id, leave_policy_id, start_date, end_date, days_requested, reason, status
   - Approval workflow: supervisor_id, supervisor_approved_at, manager_id, manager_approved_at
   - HR processing: hr_processed_by, hr_processed_at, hr_notes
   - Metadata: submitted_at, submitted_by, cancelled_at, cancellation_reason
   - Foreign Keys: leave_policy_id → leave_policies (restrict)
   - Indexes: employee_id, status, leave_policy_id, dates
   - Status: ✅ Complete - no modifications needed

3. **`2025_11_30_000001_create_leave_balances_table.php`**
   - Schema: id, employee_id, leave_policy_id, year, earned, used, remaining, carried_forward
   - Foreign Keys: employee_id → employees (cascade), leave_policy_id → leave_policies (set null)
   - Unique Constraint: employee_id + leave_policy_id + year
   - Status: ✅ Complete - no modifications needed

### Existing Models
- ✅ **LeavePolicy** model with relationships and active scope
- ✅ **LeaveRequest** model with relationships and date casts
- ✅ **LeaveBalance** model with relationships and decimal casts

### Existing Seeders
- ✅ **LeavePolicySeeder** with 7 Philippine labor law compliant leave types:
  - VL (Vacation Leave): 15 days, 5 carryover, paid
  - SL (Sick Leave): 10 days, no carryover, paid
  - EL (Emergency Leave): 5 days, no carryover, paid
  - ML (Maternity/Paternity): 90 days, no carryover, paid
  - PL (Privilege Leave): 8 days, 2 carryover, paid
  - BL (Bereavement Leave): 3 days, no carryover, paid
  - SP (Special Leave): 0 days, no carryover, unpaid

**Conclusion:** Database foundation is solid. Focus implementation on service layer, controllers, policies, and frontend.

---

## 📋 Clarifications & Decisions

### 1. Leave Policy Configuration
- ✅ **Company-wide policies** - Small company, no need for department-specific
- ⚠️ **Different employee types** - Consider implementing different policies for regular/contractual/probationary
- ✅ **Monthly accrual** - Standard for Philippine companies
- ✅ **Carry-over caps per leave type** - Configurable by Office Admin
- ✅ **Carry forward** - Unused credits carry forward (not expire)

### 2. Approval Workflow
- ✅ **Configurable by Office Admin** - Approval thresholds should be configurable (not hardcoded)
- ⚠️ **Department heads/supervisors** - Feature needed but not yet implemented (future phase)
- ❌ **Emergency bypass** - All leaves must be approved (no bypass)
- ✅ **HR Manager absence** - Escalate to Office Admin
- ✅ **Email notifications** - Required using Laravel Mail

### 3. Workforce Coverage Rules
- ✅ **75% default threshold** - Correct for all departments
- ✅ **Department-specific customization** - Coverage rules customizable per department
- ✅ **Alternative date suggestions** - System should suggest alternative dates
- ✅ **Critical positions** - Require replacement coverage
- ✅ **Block overlapping requests** - If coverage goes below threshold

### 4. Leave Request Rules
- ⚠️ **Advance notice** - Needs clarification (suggest 3 days default, configurable)
- ❌ **No backdated requests** - Not allowed for employees (HR Staff may file backdated with justification)
- ✅ **Max 30 consecutive days** - Or configurable by Office Admin
- ✅ **Cancel approved leave** - Up to 1 day before start date
- ✅ **Blackout periods** - System blocks filing during blackout

### 5. Leave Types
- ✅ **Configurable by Office Admin** - Can create any leave policy type
- ✅ **Document requirements** - Configurable per leave type by Office Admin

### 6. Integration Points
- ✅ **Payroll integration** - Unpaid leave affects payroll
- ✅ **Attendance integration** - Leave affects timekeeping records
- ✅ **Laravel Mail** - For email notifications
- ❌ **No calendar sync** - No Outlook/Google Calendar integration

### 💡 Implementation Recommendations

#### Architecture Recommendations

1. **Service Layer Approach** ✅
   - Create `LeaveManagementService.php` for business logic
   - Keep controllers thin - delegate to services
   - Already have `WorkforceCoverageService` - integrate with it

2. **Policy-Based Authorization** ✅
   - Expand `LeaveRequestPolicy.php` with granular permissions
   - Use policies for approve/reject/cancel actions
   - Already have good RBAC foundation

3. **Event-Driven Notifications** 📧
   - Implement Laravel Notifications (currently missing)
   - Create notification classes:
     - `LeaveRequestSubmitted`
     - `LeaveRequestApproved`
     - `LeaveRequestRejected`
     - `LeaveRequestCancelled`
     - `LeavePolicyUpdated`
     - `LeaveBalanceUpdated`
     - `LeaveCoverageWarning`

4. **Queue-Based Processing** ⚡
   - Use queues for coverage calculations (can be slow)
   - Use queues for email notifications
   - Prevent blocking during request submission

5. **Audit Trail** 📝
   - Log all leave policy changes (Office Admin)
   - Log all approval/rejection actions (HR Manager/Staff)
   - Use existing `LogsSecurityAudits` trait

#### Database Recommendations

1. **Additional Tables Needed:**
   - `leave_approval_workflows` - Track approval chain
   - `leave_policy_history` - Audit policy changes
   - `leave_blackout_periods` - Company-wide block dates
   - `leave_balance_adjustments` - Manual credit adjustments
   - `leave_request_attachments` - Document uploads

2. **Existing Tables to Enhance:**
   - `leave_requests` - Add approval chain tracking fields
   - `leave_policies` - Add department-specific rules support
   - `leave_balances` - Add balance history tracking

#### Business Logic Recommendations

1. **Approval Workflow Engine:**
   ```php
   // Recommended structure
   class LeaveApprovalWorkflow {
       public function determineApprovers(LeaveRequest $request): array
       public function processApproval(LeaveRequest $request, User $approver): void
       public function canApprove(LeaveRequest $request, User $user): bool
       public function escalate(LeaveRequest $request): void
   }
   ```

2. **Leave Balance Calculator:**
   ```php
   class LeaveBalanceCalculator {
       public function calculateAccrual(Employee $employee, LeavePolicy $policy): float
       public function deductLeave(LeaveRequest $request): void
       public function refundLeave(LeaveRequest $request): void
       public function getAvailableBalance(Employee $employee, string $leaveType): float
   }
   ```

3. **Coverage Validator:**
   ```php
   class LeaveCoverageValidator {
       public function validate(LeaveRequest $request): CoverageResult
       public function suggestAlternativeDates(LeaveRequest $request): array
       public function getOverlappingRequests(LeaveRequest $request): Collection
   }
   ```

#### Testing Recommendations

1. **Unit Tests Priority:**
   - Leave balance calculations
   - Approval workflow logic
   - Coverage validation algorithms

2. **Integration Tests Priority:**
   - End-to-end leave request submission → approval → notification
   - Multi-role approval chain
   - Balance deduction and refund

3. **Feature Tests Priority:**
   - Employee submits leave via portal
   - HR Staff inputs leave on behalf of employee
   - HR Manager approves/rejects leave
   - Office Admin updates policies

---

## 🎯 Implementation Phases

---

## Phase 1: Office Admin Backend - Leave Policy Management (Week 1)

**Goal:** Connect Office Admin frontend `/admin/leave-policies` to backend controllers

**Duration:** 1 week

**Status:** 🟢 Complete (6/6 tasks complete)

**Frontend Status:** ✅ Complete (`resources/js/pages/Admin/LeavePolicies/Index.tsx`, `ApprovalRules.tsx`)

**Progress Summary:**
- ✅ Task 1.1: Leave Policy CRUD Controller (COMPLETE)
- ✅ Task 1.2: Leave Policy Authorization Policy (COMPLETE)
- ✅ Task 1.3: Leave Management Service (COMPLETE)
- ✅ Task 1.4: Approval Workflow Configuration (COMPLETE)
- ✅ Task 1.5: Blackout Period Management (COMPLETE)
- ✅ Task 1.6: Leave Accrual Configuration (COMPLETE)

#### Task 1.1: Leave Policy CRUD Controller ✅ **COMPLETE**
**File:** `app/Http/Controllers/Admin/LeavePolicyController.php` (EXISTS)

**Status:** Controller already implemented with all required methods.

**Implemented Methods:**
- ✅ `index()` - Returns policies collection with approval rules
  - Returns: policies array with id, code, name, description, annual_entitlement, max_carryover, etc.
  - Includes: approvalRules configuration from system settings
  - Inertia render: `Admin/LeavePolicies/Index`
  
- ✅ `store()` - Create new leave policy
  - Validates: code (unique), name, annual_entitlement, max_carryover, can_carry_forward, is_paid, is_active, effective_date
  - Creates policy record using `LeavePolicy::create($validated)`
  - Logs activity: 'Created leave policy: {name}'
  - Returns: redirect with success message
  - **Note:** Balance initialization for existing employees should be triggered (add to Task 1.3)
  
- ✅ `update()` - Update existing policy
  - Validates policy exists and data
  - Stores old values for audit trail
  - Updates policy record
  - Logs activity with old/new values comparison
  - Returns: redirect with success message
  - **Note:** Balance recalculation logic needed (add to Task 1.3)
  
- ✅ `destroy()` - Soft delete policy
  - Checks for active balances (employees with is_active=true)
  - Prevents deletion if active balances exist
  - Soft deletes policy using Eloquent SoftDeletes trait
  - Logs activity: 'Archived leave policy: {name}'
  - Returns: redirect with success/error message

**Additional Methods Present:**
- ✅ `configureApprovalRules()` - Display approval rules page
- ✅ `updateApprovalRules()` - Update approval workflow configuration
- ✅ `testApprovalWorkflow()` - Test workflow with sample data

**Routes:** Already configured in `routes/admin.php` with proper permissions:
- `GET /admin/leave-policies` → index (permission: admin.leave-policies.view)
- `POST /admin/leave-policies` → store (permission: admin.leave-policies.create)
- `PUT /admin/leave-policies/{id}` → update (permission: admin.leave-policies.edit)
- `DELETE /admin/leave-policies/{id}` → destroy (permission: admin.leave-policies.delete)
- `GET /admin/leave-policies/approval-rules` → configureApprovalRules
- `POST /admin/leave-policies/approval-rules` → updateApprovalRules

**Frontend Integration:** ✅ Complete
- `resources/js/pages/Admin/LeavePolicies/Index.tsx` - Policy CRUD interface
- `resources/js/pages/Admin/LeavePolicies/ApprovalRules.tsx` - Approval rules configuration
- `resources/js/components/admin/leave-type-form-modal.tsx` - Policy form modal

#### Task 1.2: Create Leave Policy Authorization ✅ **COMPLETE**
**File:** `app/Policies/LeavePolicyPolicy.php` (NEW - CREATED)

**Status:** Policy created and registered successfully.

**Implemented Methods:**
- ✅ `viewAny(User $user)` - Office Admin only
  - Checks: `$user->can('admin.leave-policies.view')`
  
- ✅ `view(User $user, LeavePolicy $leavePolicy)` - Office Admin only
  - Checks: `$user->can('admin.leave-policies.view')`
  
- ✅ `create(User $user)` - Office Admin only
  - Checks: `$user->can('admin.leave-policies.create')`
  
- ✅ `update(User $user, LeavePolicy $leavePolicy)` - Office Admin only
  - Checks: `$user->can('admin.leave-policies.edit')`
  
- ✅ `delete(User $user, LeavePolicy $leavePolicy)` - Office Admin with validation
  - Checks: `$user->can('admin.leave-policies.delete')`
  - Validates: No active employee balances (where employee is_active=true)
  - Validates: No pending/approved leave requests
  - Returns false if dependencies exist
  
- ✅ `restore(User $user, LeavePolicy $leavePolicy)` - Office Admin only
  - Checks: `$user->can('admin.leave-policies.edit')`
  
- ✅ `forceDelete(User $user, LeavePolicy $leavePolicy)` - Superadmin only
  - Checks: `$user->hasRole('Superadmin')`

**Registration:** ✅ Registered in `AuthServiceProvider`
- Added `LeavePolicy::class => LeavePolicyPolicy::class` mapping

**Permissions Required:**
- `admin.leave-policies.view` - View leave policies
- `admin.leave-policies.create` - Create new policies
- `admin.leave-policies.edit` - Update existing policies
- `admin.leave-policies.delete` - Delete/archive policies

**Business Rules Enforced:**
1. Only Office Admin role can manage leave policies
2. Cannot delete policy with active employee balances
3. Cannot delete policy with pending/approved leave requests
4. Soft deletes are used for archiving
5. Force delete restricted to Superadmin only

#### Task 1.3: Leave Management Service ✅ **COMPLETE**
**File:** `app/Services/HR/LeaveManagementService.php` (NEW - CREATED)

**Status:** Fully implemented with comprehensive balance management logic.

**Implemented Methods:**
- ✅ `initializeBalancesForNewPolicy(LeavePolicy $policy)` - Create balances for all active employees
  - Fetches all employees with status='active'
  - Calculates prorated entitlement based on hire date
  - Creates LeaveBalance records for current year
  - Uses database transactions for data integrity
  - Logs activity with balance creation summary
  - Returns: array with balances_created, balances_skipped, total_employees
  
- ✅ `recalculateBalancesForUpdatedPolicy(LeavePolicy $policy, float $oldEntitlement)` - Update balances when policy changes
  - Fetches all current year balances for the policy
  - Calculates entitlement difference
  - Applies prorated difference to each employee
  - Updates earned and remaining balances
  - Uses database transactions
  - Logs activity with update summary
  - Returns: array with balances_updated, entitlement_difference
  
- ✅ `syncEmployeeBalances(?int $policyId = null)` - Sync balances for all employees
  - Handles new employees hired after policy creation
  - Can sync all policies or specific policy
  - Checks for existing balances to avoid duplicates
  - Creates missing balance records with prorated entitlement
  - Uses database transactions
  - Logs activity with sync summary
  - Returns: array with balances_created, balances_skipped, totals
  
- ✅ `calculateProratedEntitlement(float $annualEntitlement, Carbon $hireDate, int $year)` - Protected helper
  - Calculates monthly entitlement: annual_entitlement / 12
  - Prorates for mid-year hires based on remaining months
  - Handles edge cases (hired before/after target year)
  - Returns: float rounded to 2 decimals
  
- ✅ `calculateMonthlyAccrual(Employee $employee, LeavePolicy $policy)` - Monthly accrual calculation
  - Calculates standard monthly accrual: annual_entitlement / 12
  - Prorates for employees hired mid-month
  - Returns: float rounded to 2 decimals
  
- ✅ `adjustBalance(int $balanceId, float $adjustmentAmount, string $reason, int $adjustedBy)` - Manual adjustments
  - Updates earned and remaining balances
  - Logs adjustment using activity log
  - Records: adjustment_amount, reason, old/new values
  - Uses database transactions
  - Returns: updated LeaveBalance model

**Dependencies:**
- ✅ Injects `WorkforceCoverageService` (for future coverage integration)
- ✅ Uses models directly: Employee, LeaveBalance, LeavePolicy (no repositories needed)

**Business Logic:**
- ✅ Monthly accrual formula: `annual_entitlement / 12`
- ✅ Prorated calculation: `(annual_entitlement / 12) * months_remaining`
- ✅ Mid-month hire proration: `(monthly_accrual / days_in_month) * days_remaining`

**Integration Points:**
- 🔄 **TODO:** Wire to LeavePolicyController::store() to trigger initializeBalancesForNewPolicy()
- 🔄 **TODO:** Wire to LeavePolicyController::update() to trigger recalculateBalancesForUpdatedPolicy()
- 🔄 **TODO:** Create scheduled job to run syncEmployeeBalances() monthly

**Testing Checklist:**
- [ ] Test initializeBalancesForNewPolicy creates balances for all active employees
- [ ] Test prorated calculation for mid-year hires
- [ ] Test recalculateBalancesForUpdatedPolicy updates balances correctly
- [ ] Test syncEmployeeBalances creates missing records only
- [ ] Test adjustBalance records audit trail

---

#### Task 1.4: Approval Workflow Configuration ✅ **COMPLETE**
**File:** `app/Http/Controllers/Admin/LeavePolicyController.php` (INTEGRATED)

**Status:** Fully implemented - approval workflow configuration integrated into LeavePolicyController instead of separate controller. This is a better design as it keeps related functionality together.

**Implemented Methods:**
- ✅ `configureApprovalRules()` - Display approval rules configuration page
  - Fetches current approval rules configuration
  - Returns Inertia response: `Admin/LeavePolicies/ApprovalRules`
  - Includes: all 13 approval rule values
  
- ✅ `updateApprovalRules(Request $request)` - Store/update approval workflow rules
  - Validates 13 approval rule fields:
    - `duration_threshold_days` (integer, min: 1, max: 30)
    - `duration_tier2_days` (integer, min: 1, max: 90)
    - `balance_threshold_days` (integer, min: 1, max: 30)
    - `advance_notice_days` (integer, min: 1, max: 90)
    - `coverage_threshold_percentage` (integer, min: 50, max: 100)
    - `require_manager_approval` (boolean)
    - `require_hr_approval` (boolean)
    - `require_office_admin_approval` (boolean)
    - `allow_negative_balance` (boolean)
    - `restrict_leave_types` (boolean)
    - `enforce_blackout_dates` (boolean)
    - `blackout_dates` (array of dates)
    - `frequency_limit_days` (integer, min: 0, max: 365)
  - Stores each rule in `system_settings` table with keys like:
    - `leave_approval.duration.threshold_days`
    - `leave_approval.duration.tier2_days`
    - `leave_approval.balance.threshold_days`
    - etc.
  - Handles blackout_dates as JSON array
  - Logs activity: 'Updated leave approval workflow rules'
  - Returns: redirect with success message
  
- ✅ `getApprovalRulesConfiguration()` - Protected helper method
  - Retrieves all 13 approval rules from SystemSetting
  - Returns array with default values if settings don't exist:
    - duration_threshold_days: 5
    - duration_tier2_days: 15
    - balance_threshold_days: 5
    - advance_notice_days: 3
    - coverage_threshold_percentage: 75
    - require_manager_approval: true
    - require_hr_approval: true
    - require_office_admin_approval: false
    - allow_negative_balance: false
    - restrict_leave_types: false
    - enforce_blackout_dates: true
    - blackout_dates: []
    - frequency_limit_days: 0
  
- ✅ `getApprovalRuleDescription(string $key)` - Protected helper method
  - Returns human-readable descriptions for each rule
  - Used for audit trail and user notifications

**Approval Rule Categories (7 total):**
1. **Duration Rules** - Tier-based approval based on leave length
2. **Balance Rules** - Approval required if balance below threshold
3. **Advance Notice Rules** - Minimum days before leave starts
4. **Coverage Rules** - Department workforce coverage percentage
5. **Leave Type Rules** - Restrictions on specific leave types
6. **Blackout Rules** - Block leave during specified dates
7. **Frequency Rules** - Limit number of leave requests per period

**Storage Implementation:**
- Uses `SystemSetting` model with key-value pairs
- Keys follow pattern: `leave_approval.{category}.{field}`
- Values stored as strings, casted appropriately when retrieved
- Blackout dates stored as JSON array

**Frontend Integration:** ✅ Complete
- `resources/js/pages/Admin/LeavePolicies/ApprovalRules.tsx` - Full UI with all 13 fields
- Includes workflow tester component for testing approval chains
- Form organized by rule categories with clear descriptions

**Routes:** ✅ Configured
- `GET /admin/leave-policies/approval-rules` → configureApprovalRules()
- `PUT /admin/leave-policies/approval-rules` → updateApprovalRules()

**Implementation Note:**
Original specification called for separate `ApprovalWorkflowController`, but implementation was integrated into `LeavePolicyController` for better code organization. This follows single responsibility principle while avoiding controller proliferation.

**Testing Checklist:**
- [ ] Test updating approval rules with valid data
- [ ] Test default values are returned when rules not configured
- [ ] Test blackout dates are stored/retrieved as JSON array
- [ ] Test workflow tester UI simulates approval chain correctly
- [ ] Test rule changes are logged in activity log

---

#### Task 1.5: Blackout Period Management ✅ **COMPLETE**
**Files:** 
- `database/migrations/2025_12_08_000001_create_leave_blackout_periods_table.php` (NEW - CREATED)
- `app/Models/LeaveBlackoutPeriod.php` (NEW - CREATED)
- `app/Http/Controllers/Admin/LeaveBlackoutController.php` (NEW - CREATED)

**Status:** Fully implemented with migration, model, controller, and routes.

**Migration Created:**
- ✅ `leave_blackout_periods` table with fields:
  - `id`, `name`, `start_date`, `end_date`, `reason`
  - `department_id` (nullable - null = company-wide blackout)
  - `created_by` (foreign key to users)
  - `timestamps`, `soft_deletes`
  - Indexes: start_date/end_date, department_id, deleted_at
  - Foreign keys: department_id → departments (cascade), created_by → users (restrict)

**Model Created:**
- ✅ `LeaveBlackoutPeriod` model with features:
  - Uses `SoftDeletes` trait
  - Fillable: name, start_date, end_date, reason, department_id, created_by
  - Casts: start_date, end_date as dates
  - Relationships:
    - `department()` - BelongsTo Department
    - `createdBy()` - BelongsTo User
  - Query Scopes:
    - `active()` - Non-deleted blackouts
    - `companyWide()` - Null department_id
    - `forDepartment($departmentId)` - Department-specific
    - `overlapping($startDate, $endDate)` - Date range overlap detection
    - `current()` - Today falls within period
    - `upcoming()` - Future blackouts
  - Helper Methods:
    - `isActive()` - Check if currently active
    - `isCompanyWide()` - Check if applies to all departments
    - `getDurationInDays()` - Calculate period length

**Controller Implemented:**
- ✅ `index()` - List blackout periods with filters
  - Paginates 20 per page
  - Filters: department_id, status (current/upcoming/company_wide)
  - Eager loads: department, createdBy relationships
  - Returns departments for filter dropdown
  - Inertia render: `Admin/LeavePolicies/BlackoutPeriods`
  
- ✅ `store()` - Create blackout period
  - Validates: name (required, max 255), dates (start >= today, end >= start), reason (optional, max 500), department_id (nullable, exists)
  - Checks overlapping blackouts (same department or company-wide)
  - Returns validation error if overlap detected
  - Creates blackout period with created_by = Auth::id()
  - Logs activity with full details
  - Returns: redirect with success message
  
- ✅ `update()` - Update blackout period
  - Validates: same as store
  - Checks overlapping blackouts (excluding current record)
  - Stores old values for audit trail
  - Updates blackout period
  - Logs activity with old/new comparison
  - Returns: redirect with success message
  
- ✅ `destroy()` - Delete blackout period
  - Soft deletes blackout period
  - Logs activity with deleted details
  - Returns: redirect with success message
  
- ✅ `checkBlackout()` - API endpoint for validation
  - Accepts: start_date, end_date, department_id
  - Finds overlapping blackouts (company-wide + department-specific)
  - Returns JSON: has_blackout (boolean), blackouts array with details
  - Used by leave request forms for real-time validation

**Routes Configured:** ✅
- `GET /admin/leave-blackouts` → index()
- `POST /admin/leave-blackouts` → store()
- `PUT /admin/leave-blackouts/{blackout}` → update()
- `DELETE /admin/leave-blackouts/{blackout}` → destroy()
- `POST /admin/leave-blackouts/check` → checkBlackout() (API)
- Permissions: Uses admin.leave-policies permissions

**Business Logic:**
- ✅ Overlapping detection prevents double-booking of blackout periods
- ✅ Company-wide blackouts affect all departments
- ✅ Department-specific blackouts only affect that department
- ✅ Start date must be today or future (cannot create past blackouts)
- ✅ Soft deletes allow recovery if deleted by mistake
- ✅ Activity logging for full audit trail

**Frontend Integration:** 🔄 TODO
- Need to create `resources/js/pages/Admin/LeavePolicies/BlackoutPeriods.tsx`
- Need to integrate checkBlackout API into leave request forms

**Testing Checklist:**
- [ ] Test creating company-wide blackout period
- [ ] Test creating department-specific blackout
- [ ] Test overlapping blackout detection prevents conflicts
- [ ] Test leave requests blocked during blackout (API integration)
- [ ] Test soft delete and activity logging

---

#### Task 1.6: Leave Accrual Configuration ✅ **COMPLETE**
**Files:**
- `app/Services/HR/LeaveManagementService.php` (ENHANCED)
- `app/Console/Commands/ProcessMonthlyLeaveAccrual.php` (NEW - CREATED)
- `app/Console/Commands/ProcessYearEndCarryover.php` (NEW - CREATED)

**Status:** Accrual methods added to existing LeaveManagementService, scheduled commands created.

**Service Methods Added:**
- ✅ `processMonthlyAccrual()` - Process monthly leave accrual for all employees
  - Runs on first of each month (scheduled)
  - Gets all active employees and active policies
  - Creates or updates balance records for current year
  - Calculates monthly accrual using `calculateMonthlyAccrual()` method
  - Updates earned and remaining balances
  - Uses database transactions for data integrity
  - Logs each accrual with activity log
  - Returns: summary with employees_processed, balances_updated, balances_created, errors array
  - Error handling: Continues processing if individual employee fails, logs errors
  
- ✅ `processYearEndCarryover(int $year)` - Process year-end carryover
  - Runs on December 31st (scheduled)
  - Gets all balances for specified year
  - Only processes policies with can_carry_forward = true
  - Calculates carryover: min(remaining, max_carryover)
  - Creates or updates next year balance records
  - Adds carryover to earned, remaining, carried_forward fields
  - Uses database transactions
  - Logs each carryover with activity log
  - Returns: summary with balances_processed, balances_created, total_carried_forward
  
- ✅ `calculateMonthlyAccrual(Employee $employee, LeavePolicy $policy)` - Already existed
  - Monthly accrual = annual_entitlement / 12
  - Prorates for employees hired mid-month
  - Returns: float rounded to 2 decimals

- ✅ `adjustBalance(int $balanceId, float $adjustmentAmount, string $reason, int $adjustedBy)` - Already existed
  - Manual balance adjustment with audit trail
  - Updates earned and remaining
  - Logs to activity log with reason
  - Returns: updated LeaveBalance

**Console Commands Created:**
- ✅ `ProcessMonthlyLeaveAccrual` command
  - Signature: `leave:process-monthly-accrual`
  - Description: Process monthly leave accrual for all active employees
  - Calls: `$leaveService->processMonthlyAccrual()`
  - Output: Table with metrics (year, month, employees processed, balances updated/created, errors)
  - Returns: Command::SUCCESS or Command::FAILURE
  
- ✅ `ProcessYearEndCarryover` command
  - Signature: `leave:process-year-end-carryover {--year=}`
  - Description: Process year-end leave carryover for all active employees
  - Option: --year (defaults to current year)
  - Confirmation: Asks user to confirm before processing
  - Calls: `$leaveService->processYearEndCarryover($year)`
  - Output: Table with metrics (from_year, to_year, balances processed/created, total carried forward)
  - Returns: Command::SUCCESS or Command::FAILURE

**Scheduling Required:** 🔄 TODO
- Register in `app/Console/Kernel.php`:
  ```php
  // Run on 1st of every month at 12:01 AM
  $schedule->command('leave:process-monthly-accrual')
      ->monthlyOn(1, '00:01');
  
  // Run on December 31st at 11:59 PM
  $schedule->command('leave:process-year-end-carryover')
      ->yearlyOn(12, 31, '23:59');
  ```

**Business Logic:**
- ✅ Monthly accrual: annual_entitlement / 12
- ✅ Mid-month hire proration: (monthly_accrual / days_in_month) * days_remaining
- ✅ Carryover limited by max_carryover per policy
- ✅ Only policies with can_carry_forward = true are processed
- ✅ Balances automatically created if missing (handles new hires)
- ✅ Transaction-based processing ensures data integrity
- ✅ Comprehensive error handling and logging

**Activity Logging:**
- ✅ Each accrual logged: leave_accrual_processed
- ✅ Each carryover logged: leave_carryover_processed
- ✅ Logs include: employee_id, policy_id, amounts, year, month

**Testing Checklist:**
- [ ] Test processMonthlyAccrual creates/updates balances correctly
- [ ] Test mid-month hire proration in calculateMonthlyAccrual
- [ ] Test processYearEndCarryover respects max_carryover limits
- [ ] Test carryover only processes can_carry_forward policies
- [ ] Test commands can be run manually via Artisan
- [ ] Test scheduled commands run automatically (after kernel registration)
- [ ] Test error handling continues processing if one employee fails

---

#### Task 1.7: Create Frontend Pages (Office Admin)
- [ ] Create `resources/js/pages/Admin/LeavePolicy/Index.tsx` - List view
- [ ] Create `resources/js/pages/Admin/LeavePolicy/Create.tsx` - Create form
- [ ] Create `resources/js/pages/Admin/LeavePolicy/Edit.tsx` - Edit form
- [ ] Add DataTable with sorting, filtering, pagination
- [ ] Add search by code/name
- [ ] Add status filter (Active/Inactive)
- [ ] Add "Soft Deleted" view toggle
- [ ] Add bulk actions (Activate, Deactivate)
- [ ] Add confirmation dialogs for destructive actions

#### Task 1.8: Add Routes
- [ ] Add routes to `routes/admin.php`:
  - `GET /admin/leave-policies` - index
  - `GET /admin/leave-policies/create` - create
  - `POST /admin/leave-policies` - store
  - `GET /admin/leave-policies/{id}/edit` - edit
  - `PUT /admin/leave-policies/{id}` - update
  - `DELETE /admin/leave-policies/{id}` - destroy
  - `POST /admin/leave-policies/{id}/restore` - restore
- [ ] Apply `auth` and `role:office-admin` middleware
- [ ] Add route names for easy reference

#### Task 1.9: Validation & Business Rules
- [ ] Code must be unique and uppercase (2-10 chars)
- [ ] Name required (max 100 chars)
- [ ] Annual entitlement >= 0, max 365 days
- [ ] Max carryover >= 0, <= annual_entitlement
- [ ] Cannot delete policy with active balances or pending requests
- [ ] Cannot deactivate policy mid-year if employees have balances
- [ ] Effective date validation (cannot be in past for new policies)

#### Task 1.10: Testing Phase 1
- [ ] Unit test: LeavePolicy model relationships
- [ ] Feature test: Office Admin can create leave policy
- [ ] Feature test: Office Admin can update leave policy
- [ ] Feature test: Office Admin can soft delete policy
- [ ] Feature test: Office Admin can restore policy
- [ ] Feature test: Cannot delete policy with dependencies
- [ ] Feature test: Validation rules enforced
- [ ] Feature test: Authorization - Only Office Admin can access

---

## Phase 2: Employee Backend - Leave Request Submission (Week 2)

**Goal:** Connect Employee frontend to backend for leave request submission and balance viewing

**Duration:** 1 week

**Frontend Status:** 
- ✅ Complete: `CreateRequest.tsx` (with coverage warning UI)
- ✅ Complete: `Balances.tsx` (balance display)
- ✅ Complete: `History.tsx` (request history table)
- ✅ Complete: `LeaveRequestRequest.php` (validation request)

#### Task 2.1: Employee Leave Controller (Backend Only)
**File:** `app/Http/Controllers/Employee/LeaveController.php` (EXTEND EXISTING)

- [ ] Implement `balances()` - Return leave balances for frontend
  - [ ] Fetch employee's leave balances for current year
  - [ ] Calculate: earned, used, remaining, carried_forward per policy
  - [ ] Include: leave type details, policy information
  - [ ] Inertia render: `Employee/Leave/Balances`
  
- [ ] Implement `history()` - Return leave request history
  - [ ] Fetch employee's leave requests (all statuses)
  - [ ] Include: leave type, dates, status, approver info
  - [ ] Support filtering: status, date range, leave type
  - [ ] Paginate: 20 per page
  - [ ] Inertia render: `Employee/Leave/History`
  
- [ ] Implement `create()` - Show create form
  - [ ] Fetch active leave policies
  - [ ] Calculate available balance per policy
  - [ ] Return employee info
  - [ ] Inertia render: `Employee/Leave/CreateRequest`
  
- [ ] Implement `store()` - Submit leave request (connects to existing frontend form)
  - [ ] Validate using `LeaveRequestRequest.php` (already exists)
  - [ ] Check sufficient balance
  - [ ] Check no overlapping requests
  - [ ] Check blackout periods
  - [ ] Calculate days (exclude weekends/holidays)
  - [ ] Create leave_request record (status: 'pending')
  - [ ] Deduct balance provisionally
  - [ ] Upload document if provided
  - [ ] Determine approver based on rules
  - [ ] Send notification to approver
  - [ ] Return: success message + redirect to history
  
- [ ] Implement `cancel()` - Cancel pending/approved request
  - [ ] Validate request belongs to employee
  - [ ] Validate status is 'pending' or 'approved'
  - [ ] Validate within cancellation deadline (1 day before start)
  - [ ] Update status to 'cancelled'
  - [ ] Refund balance
  - [ ] Log cancellation
  - [ ] Send notification
  - [ ] Return: success message

#### Task 2.2: Coverage Calculation API (Backend Only)
**File:** `app/Http/Controllers/Employee/LeaveController.php`

- [ ] Implement `calculateCoverage()` - API endpoint for real-time coverage check
  - [ ] Accept: start_date, end_date (from frontend AJAX call)
  - [ ] Calculate: department coverage percentage
  - [ ] Find: overlapping leave requests from same team
  - [ ] Determine: coverage status (optimal/acceptable/warning/critical)
  - [ ] Suggest: alternative dates if coverage < 80%
  - [ ] Return JSON: coverage_percentage, status, message, alternative_dates, team_members_on_leave
  - [ ] Frontend already calls this: `axios.post('/employee/leave/request/calculate-coverage')`

#### Task 2.3: Leave Request Service (Backend Only)
**File:** `app/Services/LeaveRequestService.php`

Business logic layer supporting Employee and HR operations:

- [ ] **createRequest($data)** - Create new leave request
  - [ ] Calculate working days (exclude weekends/holidays)
  - [ ] Validate sufficient balance
  - [ ] Check overlapping requests
  - [ ] Check blackout periods
  - [ ] Calculate prorated balances for mid-year hires
  - [ ] Create leave_request record
  - [ ] Deduct balance provisionally
  - [ ] Return: LeaveRequest instance

- [ ] **cancelRequest($requestId, $userId, $reason)** - Cancel logic
  - [ ] Validate ownership
  - [ ] Validate cancellation deadline
  - [ ] Update status to 'cancelled'
  - [ ] Refund balance
  - [ ] Log cancellation with reason

- [ ] **calculateWorkingDays($startDate, $endDate)** - Working days calculation
  - [ ] Exclude weekends (Saturday/Sunday)
  - [ ] Exclude Philippine holidays
  - [ ] Handle half-day leaves
  - [ ] Return: integer days

- [ ] **validateBalanceSufficiency($employeeId, $policyId, $requestedDays)** - Balance check
  - [ ] Fetch current balance
  - [ ] Compare with requested days
  - [ ] Return: boolean + error message if insufficient

**Example Logic:**
```php
public function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
{
    $workingDays = 0;
    $current = $startDate->copy();
    
    while ($current->lte($endDate)) {
        if (!$current->isWeekend() && !$this->isHoliday($current)) {
            $workingDays++;
        }
        $current->addDay();
    }
    
    return $workingDays;
}
```

#### Task 2.4: Workforce Coverage Service (Backend Only)
**File:** `app/Services/WorkforceCoverageService.php`

Calculates team coverage for leave approval decisions:

- [ ] **calculateCoverage($departmentId, $startDate, $endDate)** - Main calculation
  - [ ] Count total employees in department
  - [ ] Count employees on leave during period
  - [ ] Calculate coverage percentage
  - [ ] Determine status: optimal (>90%), acceptable (80-90%), warning (70-80%), critical (<70%)
  - [ ] Return: CoverageData object

- [ ] **getAlternativeDates($departmentId, $startDate, $endDate, $daysNeeded)** - Suggest better dates
  - [ ] Find next available dates with >80% coverage
  - [ ] Return: array of alternative date ranges

- [ ] **getTeamMembersOnLeave($departmentId, $startDate, $endDate)** - Who's out
  - [ ] Fetch overlapping leave requests
  - [ ] Include: employee name, leave type, dates
  - [ ] Return: array of LeaveRequest objects

**Return Structure (matches frontend AJAX expectation):**
```php
return [
    'coverage_percentage' => 85.5,
    'status' => 'acceptable', // optimal|acceptable|warning|critical
    'message' => '6 out of 40 team members will be on leave',
    'alternative_dates' => [
        ['start_date' => '2025-02-10', 'end_date' => '2025-02-14', 'coverage' => 92.5]
    ],
    'team_members_on_leave' => [
        ['name' => 'John Doe', 'leave_type' => 'Vacation', 'dates' => '2025-02-05 to 2025-02-07']
    ]
];
```

#### Task 2.5: Document Upload API (Backend Only)
**File:** `app/Http/Controllers/Employee/LeaveController.php`

- [ ] Add `uploadDocument()` method for medical certificates
  - [ ] Validate file: PDF/JPG/PNG, max 5MB
  - [ ] Store in: `storage/app/leave_documents/{user_id}/{year}/`
  - [ ] Save path to leave_requests.document_path
  - [ ] Return: file URL

- [ ] Add `downloadDocument($requestId)` method
  - [ ] Authorize: owner or HR staff
  - [ ] Return: file download response
---

## Phase 3: HR Backend - Leave Request Processing (Week 3)

**Goal:** Connect HR frontend to backend for leave approval and management

**Duration:** 1 week

**Frontend Status:**
- ✅ Complete: `HR/Leave/Requests.tsx` (approval interface with filters)
- ✅ Complete: `HR/Leave/Balances.tsx` (balance viewing)
- ✅ Complete: `HR/Leave/Policies.tsx` (policy viewing)
- ✅ Complete: `HR/Leave/CreateRequest.tsx` (submit on behalf)
- ✅ Complete: `StoreLeaveRequestRequest.php`, `UpdateLeaveRequestRequest.php` (validation)

#### Task 3.1: HR Leave Request Controller (Backend Only)
**File:** `app/Http/Controllers/HR/LeaveRequestController.php`

Connect to existing HR frontend for leave processing:

- [ ] **index()** - List leave requests with filters
  - [ ] Accept filters: status, department_id, employee_id, date_range
  - [ ] Authorize: HR Staff (own department), HR Manager (all)
  - [ ] Calculate meta statistics: total_pending, total_approved, total_rejected
  - [ ] Include: employee, leave_type, approvers
  - [ ] Paginate: 20 per page
  - [ ] Inertia render: `HR/Leave/Requests`
  
- [ ] **show($id)** - View request details with coverage analysis
  - [ ] Fetch full request + employee + policy + approvals
  - [ ] Calculate workforce coverage for request period
  - [ ] Include approval history
  - [ ] Check authorization (HR Staff/Manager)
  - [ ] Return: request object + coverage data
  
- [ ] **approve(UpdateLeaveRequestRequest $request, $id)** - Approve request
  - [ ] Validate using existing `UpdateLeaveRequestRequest.php`
  - [ ] Check authorization (HR Staff for 1-5 days, HR Manager for all)
  - [ ] Update status to 'approved'
  - [ ] Confirm balance deduction
  - [ ] Create approval record
  - [ ] Send notification to employee
  - [ ] Return: success message + redirect to index
  
- [ ] **reject(UpdateLeaveRequestRequest $request, $id)** - Reject request
  - [ ] Validate using `UpdateLeaveRequestRequest.php`
  - [ ] Require rejection reason
  - [ ] Update status to 'rejected'
  - [ ] Refund balance
  - [ ] Create rejection record
  - [ ] Send notification to employee
  - [ ] Return: success message

- [ ] **create()** - Show create form (submit on behalf of employee)
  - [ ] Fetch active employees in department (HR Staff) or all (HR Manager)
  - [ ] Fetch active leave policies
  - [ ] Return: employees list, policies, current user info
  - [ ] Inertia render: `HR/Leave/CreateRequest`
  
- [ ] **store(StoreLeaveRequestRequest $request)** - Submit on behalf of employee
  - [ ] Validate using existing `StoreLeaveRequestRequest.php`
  - [ ] Call LeaveRequestService to create request
  - [ ] Set created_by_hr = true
  - [ ] Set submitter_id = current user
  - [ ] Apply auto-approval if within HR Staff authority (1-5 days)
  - [ ] Send notification to employee
  - [ ] Return: success message + redirect to index

**Inertia Response Example:**
```php
return Inertia::render('HR/Leave/Requests', [
    'requests' => LeaveRequestResource::collection($requests),
    'meta' => [
        'total_pending' => LeaveRequest::where('status', 'pending')->count(),
        'total_approved' => LeaveRequest::where('status', 'approved')->count(),
        'total_rejected' => LeaveRequest::where('status', 'rejected')->count(),
    ],
    'filters' => $request->only(['status', 'department_id', 'employee_id', 'date_range']),
    'departments' => Department::pluck('name', 'id'),
]);
```

#### Task 3.2: Leave Approval Workflow Service (Backend Only)
**File:** `app/Services/LeaveApprovalWorkflowService.php`

Implements multi-level approval logic:

- [ ] **determineApprover($leaveRequest)** - Route to correct approver
  - [ ] If 1-5 days: HR Staff of employee's department
  - [ ] If 6+ days: HR Manager
  - [ ] If 6+ days approved by HR Manager: escalate to Office Admin for final approval
  - [ ] Return: approver User instance

- [ ] **approveRequest($requestId, $approverId, $comments)** - Approval logic
  - [ ] Validate approver has authority
  - [ ] Check if escalation needed (6+ days)
  - [ ] If escalation needed: update status to 'pending_final_approval', notify Office Admin
  - [ ] If no escalation: update status to 'approved', deduct balance, notify employee
  - [ ] Create leave_request_approvals record
  - [ ] Log approval in activity log

- [ ] **rejectRequest($requestId, $approverId, $reason)** - Rejection logic
  - [ ] Validate approver has authority
  - [ ] Update status to 'rejected'
  - [ ] Refund balance
  - [ ] Create leave_request_approvals record
  - [ ] Send notification with reason
  - [ ] Log rejection

- [ ] **escalateToFinalApproval($requestId)** - Escalation to Office Admin
  - [ ] Update status to 'pending_final_approval'
  - [ ] Find Office Admin users
  - [ ] Send escalation notification
  - [ ] Log escalation

**Business Rules:**
- 1-5 days: HR Staff → Approved (single approval)
- 6+ days: HR Manager → Office Admin → Approved (two-level approval)
- Rejections can happen at any level

#### Task 3.3: Leave Request Policy (Authorization) (Backend Only)
**File:** `app/Policies/LeaveRequestPolicy.php`

Define authorization rules:

- [ ] **viewAny(User $user)** - Who can list leave requests
  - [ ] Return true if: HR Staff, HR Manager, Office Admin
  
- [ ] **view(User $user, LeaveRequest $request)** - Who can view specific request
  - [ ] Return true if: Owner (employee), HR Staff (same department), HR Manager, Office Admin
  
- [ ] **approve(User $user, LeaveRequest $request)** - Who can approve
  - [ ] HR Staff: only 1-5 days, same department, status = 'pending'
  - [ ] HR Manager: all durations, all departments, status = 'pending'
  - [ ] Office Admin: only if status = 'pending_final_approval' (6+ days escalation)
  
- [ ] **reject(User $user, LeaveRequest $request)** - Who can reject
  - [ ] Same as approve authorization
  
- [ ] **cancel(User $user, LeaveRequest $request)** - Who can cancel
  - [ ] Owner (employee): if status = 'pending' or 'approved', within cancellation deadline
  - [ ] HR Staff/Manager: any status except 'cancelled'

- [ ] Register policy in `AuthServiceProvider`

**Example:**
```php
public function approve(User $user, LeaveRequest $request): bool
{
    if ($user->hasRole('office-admin') && $request->status === 'pending_final_approval') {
        return true;
    }
    
    if ($user->hasRole('hr-manager') && $request->status === 'pending') {
        return true;
    }
    
    if ($user->hasRole('hr-staff') && 
        $request->status === 'pending' && 
        $request->user->department_id === $user->department_id &&
        $request->days_requested <= 5) {
        return true;
    }
    
    return false;
}
```

#### Task 3.4: Balance Adjustment API (Backend Only)
**File:** `app/Http/Controllers/HR/LeaveBalanceController.php`

Manual balance adjustments by HR:

- [ ] **index()** - List employee balances
  - [ ] Filter by: department, employee, leave type, year
  - [ ] Include: earned, used, remaining, carried_forward
  - [ ] Inertia render: `HR/Leave/Balances`
  
- [ ] **adjustBalance(Request $request)** - Manual adjustment
  - [ ] Validate: user_id, leave_policy_id, adjustment_amount, adjustment_reason
  - [ ] Authorize: HR Manager or Office Admin only
  - [ ] Call LeaveBalanceService->adjustBalance()
  - [ ] Log adjustment in leave_balance_adjustments table
  - [ ] Send notification to employee
  - [ ] Return: success message

## Phase 4: Office Admin Backend - Final Approval (Week 4)

**Goal:** Connect Office Admin frontend to backend for final approval of long leaves (6+ days)

**Duration:** 3 days

**Frontend Status:**
- ✅ Complete: `Admin/LeavePolicies/Index.tsx` (policy management UI)
- ✅ Complete: `Admin/LeavePolicies/ApprovalRules.tsx` (approval rules UI)
- ✅ Note: Office Admin uses same HR Leave pages for final approvals

#### Task 4.1: Office Admin Leave Controller (Backend Only)
**File:** `app/Http/Controllers/Admin/LeaveFinalApprovalController.php`

Handle final approvals for 6+ day requests:

- [ ] **pendingFinalApprovals()** - List requests needing final approval
  - [ ] Filter: status = 'pending_final_approval'
  - [ ] Include: employee, leave type, HR Manager who pre-approved, coverage data
  - [ ] Sort by: submission date (oldest first)
  - [ ] Paginate: 20 per page
  - [ ] Inertia render: `Admin/Leave/FinalApprovals`
  
- [ ] **approveFinal($id)** - Final approval
  - [ ] Authorize: Office Admin only
  - [ ] Validate: status = 'pending_final_approval', days >= 6
  - [ ] Update status to 'approved'
  - [ ] Confirm balance deduction
  - [ ] Create final approval record
  - [ ] Send notification to employee + HR Manager
  - [ ] Log final approval
  - [ ] Return: success message
  
- [ ] **rejectFinal($id)** - Final rejection
  - [ ] Authorize: Office Admin only
  - [ ] Require rejection reason
  - [ ] Update status to 'rejected'
  - [ ] Refund balance
  - [ ] Send notification to employee + HR Manager
  - [ ] Log rejection
  - [ ] Return: success message

#### Task 4.2: Update Leave Approval Workflow Service
**File:** `app/Services/LeaveApprovalWorkflowService.php`

Add Office Admin final approval logic:

- [ ] **approveFinalByAdmin($requestId, $adminId, $comments)** - Final approval
  - [ ] Validate: request status = 'pending_final_approval'
  - [ ] Update status to 'approved'
  - [ ] Confirm balance deduction
  - [ ] Create leave_request_approvals record (level: 'final')
  - [ ] Send notifications
  - [ ] Log activity

---

## Phase 5: Scheduled Jobs & Notifications (Week 4)

**Goal:** Automate balance accruals, carryovers, and expiration notifications

**Duration:** 2 days

#### Task 5.1: Monthly Leave Accrual Job
**File:** `app/Console/Commands/ProcessMonthlyLeaveAccrual.php`

- [ ] Create Artisan command: `leave:process-monthly-accrual`
- [ ] Schedule: 1st day of each month at 1:00 AM
- [ ] Logic:
  - [ ] Fetch all active employees
  - [ ] Fetch active leave policies with accrual_frequency = 'monthly'
  - [ ] Calculate monthly accrual per policy (annual_entitlement / 12)
  - [ ] Calculate pro-rated accrual for mid-year hires
  - [ ] Update leave_balances: earned_balance += monthly_accrual
  - [ ] Log accrual in leave_balance_adjustments table
  - [ ] Send notification to employees: "Your leave balance has been updated"
  
- [ ] Register in `app/Console/Kernel.php`:
```php
$schedule->command('leave:process-monthly-accrual')->monthlyOn(1, '01:00');
```

#### Task 5.2: Year-End Carryover Job
**File:** `app/Console/Commands/ProcessYearEndCarryover.php`

- [ ] Create Artisan command: `leave:process-year-end-carryover`
- [ ] Schedule: December 31 at 11:59 PM
- [ ] Logic:
  - [ ] Fetch all employees with leave balances for current year
  - [ ] For each balance:
    - [ ] Calculate carryover: min(remaining_balance, policy.max_carryover)
    - [ ] Create next year balance record: carried_forward = carryover
    - [ ] Expire unused non-carryover balance
  - [ ] Log carryover in leave_balance_adjustments
  - [ ] Send notification: "Your [X] days have been carried forward to 2025"

#### Task 5.3: Notification System
**File:** `app/Notifications/Leave/`

Create notification classes:

- [ ] **LeaveRequestSubmittedNotification** (to approver)
  - [ ] Mail + Database
  - [ ] Include: employee name, leave type, dates, days, reason, link to request
  
- [ ] **LeaveRequestApprovedNotification** (to employee)
  - [ ] Mail + Database
  - [ ] Include: leave type, dates, approved by, comments
  
- [ ] **LeaveRequestRejectedNotification** (to employee)
  - [ ] Mail + Database
  - [ ] Include: rejection reason, rejected by, suggest alternative
  
- [ ] **LeaveRequestCancelledNotification** (to approver)
  - [ ] Mail + Database
  - [ ] Include: employee name, leave type, dates, cancellation reason
  
- [ ] **LeaveBalanceAccruedNotification** (to employee)
  - [ ] Mail + Database
  - [ ] Include: accrued amount, leave type, new balance
  
- [ ] **LeaveExpirationReminderNotification** (to employee)
  - [ ] Mail + Database
  - [ ] Sent: 30 days before expiration
  - [ ] Include: expiring balance, leave type, expiration date

#### Task 5.4: Notification Scheduling
- [ ] Create `leave:send-expiration-reminders` command
- [ ] Schedule: Daily at 9:00 AM
- [ ] Logic: Find balances expiring in 30 days, send reminders

---

**Duration:** 1 week

**Frontend Status:**
- ✅ Complete: `HR/Leave/Requests.tsx` (approval interface with filters)
- ✅ Complete: `HR/Leave/Balances.tsx` (balance viewing)
- ✅ Complete: `HR/Leave/Policies.tsx` (policy viewing)
- ✅ Complete: `HR/Leave/CreateRequest.tsx` (submit on behalf)
- ✅ Complete: `StoreLeaveRequestRequest.php`, `UpdateLeaveRequestRequest.php` (validation)

## Phase 6: Testing & Quality Assurance (Week 5)

**Goal:** Comprehensive testing of backend APIs and business logic

**Duration:** 3-4 days

#### Task 6.1: Unit Tests (Backend Logic)

**File:** `tests/Unit/Services/`

- [ ] **LeaveRequestServiceTest** - Test business logic
  - [ ] testCalculateWorkingDays - Weekend/holiday exclusion
  - [ ] testCreateRequest - Request creation with balance deduction
  - [ ] testCancelRequest - Cancellation with balance refund
  - [ ] testValidateBalanceSufficiency - Balance checking
  
- [ ] **WorkforceCoverageServiceTest** - Test coverage calculations
  - [ ] testCalculateCoverage - Department coverage percentage
  - [ ] testGetAlternativeDates - Date suggestions
  - [ ] testGetTeamMembersOnLeave - Overlapping leave detection
  
- [ ] **LeaveApprovalWorkflowServiceTest** - Test approval logic
  - [ ] testDetermineApprover - Correct approver routing
  - [ ] testApproveRequest - Approval with balance update
  - [ ] testRejectRequest - Rejection with balance refund
  - [ ] testEscalateToFinalApproval - Escalation for 6+ days
  
- [ ] **LeaveBalanceCalculatorTest** - Test balance calculations
  - [ ] testCalculateMonthlyAccrual - Monthly accrual calculation
  - [ ] testCalculateProration - Mid-year hire prorated balance
  - [ ] testCalculateCarryover - Year-end carryover logic

#### Task 6.2: Feature Tests (API Endpoints)

**File:** `tests/Feature/Leave/`

**Office Admin Tests:**
- [ ] testOfficeAdminCanCreateLeavePolicy
- [ ] testOfficeAdminCanUpdateLeavePolicy
- [ ] testOfficeAdminCanDeleteLeavePolicy
- [ ] testOfficeAdminCannotDeletePolicyWithActiveBalances
- [ ] testOfficeAdminCanApproveFinalApproval

**Employee Tests:**
- [ ] testEmployeeCanViewOwnBalances
- [ ] testEmployeeCanViewOwnHistory
- [ ] testEmployeeCanSubmitLeaveRequest
- [ ] testEmployeeCannotSubmitWithInsufficientBalance
- [ ] testEmployeeCanCancelPendingRequest
- [ ] testEmployeeCannotCancelApprovedRequestAfterDeadline
- [ ] testCoverageCalculationReturnsCorrectData

**HR Tests:**
- [ ] testHRStaffCanApprove1To5DayRequests
- [ ] testHRStaffCannotApprove6PlusDayRequests
- [ ] testHRManagerCanApproveAllRequests
- [ ] testHRCanSubmitRequestOnBehalfOfEmployee
- [ ] testHRCanAdjustBalanceManually
- [ ] testHRCanViewDepartmentRequests

**Authorization Tests:**
- [ ] testEmployeeCannotViewOtherEmployeeBalances
- [ ] testHRStaffCannotApproveDifferentDepartment
- [ ] testHRManagerCanApproveCrossDepartment
- [ ] testUnauthorizedUserCannotAccessLeaveEndpoints

#### Task 6.3: Integration Tests

- [ ] **testCompleteApprovalWorkflow1To5Days** - End-to-end single-level approval
  - [ ] Employee submits 3-day request
  - [ ] HR Staff approves
  - [ ] Balance deducted correctly
  - [ ] Notifications sent
  
- [ ] **testCompleteApprovalWorkflow6PlusDays** - End-to-end two-level approval
  - [ ] Employee submits 7-day request
  - [ ] HR Manager approves (escalates to Office Admin)
  - [ ] Office Admin final approval
  - [ ] Balance deducted correctly
  - [ ] Notifications sent at each level
  
- [ ] **testLeaveRequestRejection** - End-to-end rejection
  - [ ] Employee submits request
  - [ ] HR rejects with reason
  - [ ] Balance refunded
  - [ ] Notification sent
  
- [ ] **testMonthlyAccrualJob** - Scheduled job execution
  - [ ] Run artisan command
  - [ ] Verify all employees' balances updated
  - [ ] Verify pro-rated calculation for new hires
  
- [ ] **testYearEndCarryover** - Year-end carryover job
  - [ ] Run year-end command
  - [ ] Verify carryover calculated correctly
  - [ ] Verify next year balances created

#### Task 6.4: Manual Testing (QA Checklist)

- [ ] Test Office Admin policy management UI
- [ ] Test Employee request submission with coverage warning
- [ ] Test HR approval/rejection workflow
- [ ] Test Office Admin final approval
- [ ] Test balance accrual and carryover
- [ ] Test notifications (email + in-app)
- [ ] Test authorization for all roles
- [ ] Test edge cases: overlapping requests, blackout periods, insufficient balance
- [ ] Test mobile responsiveness
- [ ] Test performance with 100+ concurrent requests

## Phase 7: Documentation & Deployment (Week 5-6)

**Goal:** Complete API documentation and deployment preparation

**Duration:** 2 days

#### Task 7.1: API Documentation

**File:** `docs/API_LEAVE_MANAGEMENT.md`

Document all backend endpoints:

**Office Admin Endpoints:**
- `GET /admin/leave-policies` - List all policies
- `POST /admin/leave-policies` - Create policy
- `PUT /admin/leave-policies/{id}` - Update policy
- `DELETE /admin/leave-policies/{id}` - Delete policy
- `GET /admin/leave/final-approvals` - Pending final approvals
- `POST /admin/leave/final-approvals/{id}/approve` - Final approve
- `POST /admin/leave/final-approvals/{id}/reject` - Final reject

**Employee Endpoints:**
- `GET /employee/leave/balances` - View balances
- `GET /employee/leave/history` - View request history
- `GET /employee/leave/create` - Show create form
- `POST /employee/leave/request` - Submit request
- `POST /employee/leave/request/calculate-coverage` - Calculate coverage (AJAX)
- `POST /employee/leave/request/{id}/cancel` - Cancel request
- `POST /employee/leave/document/upload` - Upload document
- `GET /employee/leave/document/{requestId}` - Download document

**HR Endpoints:**
- `GET /hr/leave/requests` - List requests with filters
- `GET /hr/leave/requests/{id}` - View request details
- `POST /hr/leave/requests/{id}/approve` - Approve request
- `POST /hr/leave/requests/{id}/reject` - Reject request
- `GET /hr/leave/balances` - View employee balances
- `POST /hr/leave/balances/adjust` - Adjust balance manually
- `GET /hr/leave/create` - Show create form (on behalf)
- `POST /hr/leave/request` - Submit on behalf

For each endpoint document:
- HTTP method and path
- Authorization requirements
- Request parameters
- Request body schema
- Response schema
- Error responses
- Example requests/responses

#### Task 7.2: Database Seeding

**File:** `database/seeders/LeaveManagementSeeder.php`

- [ ] Seed 7 leave policies (already exists)
- [ ] Seed leave balances for all employees (current year)
- [ ] Seed sample leave requests (various statuses)
- [ ] Seed approval records
- [ ] Seed balance adjustments history

#### Task 7.3: Environment Configuration

**File:** `.env.example`

Add leave management configurations:
```env
# Leave Management
LEAVE_MIN_ADVANCE_DAYS=3
LEAVE_MAX_DAYS_PER_REQUEST=30
LEAVE_CANCELLATION_DEADLINE_HOURS=24
LEAVE_COVERAGE_WARNING_THRESHOLD=80
LEAVE_COVERAGE_CRITICAL_THRESHOLD=70
LEAVE_DOCUMENTS_PATH=leave_documents
LEAVE_MAX_FILE_SIZE_MB=5
```

#### Task 7.4: Deployment Checklist

- [ ] Run migrations: `php artisan migrate`
- [ ] Run seeders: `php artisan db:seed --class=LeavePolicySeeder`
- [ ] Create storage symlink: `php artisan storage:link`
- [ ] Schedule cron job for monthly accrual: `0 1 1 * * php artisan leave:process-monthly-accrual`
- [ ] Schedule cron job for year-end carryover: `59 23 31 12 * php artisan leave:process-year-end-carryover`
- [ ] Schedule cron job for expiration reminders: `0 9 * * * php artisan leave:send-expiration-reminders`
- [ ] Configure mail server for notifications
- [ ] Test all notifications
- [ ] Assign roles to users
- [ ] Verify permissions

#### Task 7.5: User Documentation

**File:** `docs/USER_GUIDE_LEAVE_MANAGEMENT.md`

Create user guides for:
- Office Admin: How to configure policies, approval rules, blackout periods
- HR Staff/Manager: How to approve/reject requests, submit on behalf, adjust balances
- Employees: How to view balances, submit requests, interpret coverage warnings, cancel requests

---
- [ ] Feature test: Employee can view own request history
- [ ] Feature test: Employee can cancel pending request
- [ ] Feature test: Employee cannot cancel approved request directly
- [ ] Feature test: Balance updated correctly on approval
- [ ] Feature test: Coverage warning shown correctly
- [ ] Feature test: Days calculation excludes weekends/holidays
- [ ] Feature test: Authorization - Employee can only view own requests

---

### Phase 4: Shared Services - Balance Management & Notifications

**Objective:** Implement balance accrual, carryover processing, and notification system.

**Duration:** 1 week (can run parallel to Phase 1-3)

#### Task 4.1: Create Leave Balance Service
- [ ] Create `app/Services/LeaveBalanceService.php`
- [ ] Implement `initializeBalance($employeeId, $leavePolicyId, $year)` - Create initial balance
- [ ] Implement `accrueMonthly($employeeId, $year, $month)` - Monthly accrual
- [ ] Implement `accrueAnnual($employeeId, $year)` - Annual accrual
- [ ] Implement `processCarryover($employeeId, $fromYear, $toYear)` - Year-end carryover
- [ ] Implement `adjustBalance($balanceId, $amount, $reason)` - Manual adjustment by HR
- [ ] Implement `recalculateBalance($balanceId)` - Recalculate from requests
- [ ] Implement `getBalance($employeeId, $leavePolicyId, $year)` - Get current balance
- [ ] Implement `getAllBalances($employeeId, $year)` - Get all balances for year
- [ ] Add audit logging for all balance changes

#### Task 4.2: Create Balance Initialization Command
- [ ] Create `app/Console/Commands/InitializeLeaveBalances.php`
- [ ] Command: `php artisan leave:initialize-balances {year?}`
- [ ] For all active employees:
  - Create leave_balances for each active leave policy
  - Set earned = annual_entitlement (or prorated if mid-year hire)
  - Set used = 0, remaining = earned, carried_forward = 0
- [ ] Skip if balance already exists for employee+policy+year
- [ ] Add `--force` flag to recreate balances
- [ ] Add `--employee-id` flag to initialize single employee
- [ ] Log all initializations

---

## Phase 8: Progress Tracking & Timeline

### Phase Summary

| Phase | Focus | Duration | Status |
|-------|-------|----------|--------|
| Phase 1 | Office Admin - Policy Management | 1 week | 🟡 In Progress (33% complete) |
| Phase 2 | Employee Backend - Request Submission | 1 week | ⏳ Pending |
| Phase 3 | HR Backend - Request Processing | 1 week | ⏳ Pending |
| Phase 4 | Office Admin - Final Approval | 3 days | ⏳ Pending |
| Phase 5 | Scheduled Jobs & Notifications | 2 days | ⏳ Pending |
| Phase 6 | Testing & QA | 3-4 days | ⏳ Pending |
| Phase 7 | Documentation & Deployment | 2 days | ⏳ Pending |

**Total Duration: 3-4 weeks (Backend Only)**

---

## Acceptance Criteria

### Office Admin Role
- ✅ Can create, update, delete leave policies
- ✅ Can configure approval rules (1-5 days: HR Staff, 6+ days: HR Manager → Office Admin)
- ✅ Can configure blackout periods
- ✅ Can perform final approval for 6+ day requests
- ✅ Can view all leave requests and balances system-wide

### HR Manager Role
- ✅ Can approve all leave requests (any duration, any department)
- ✅ Can submit leave requests on behalf of employees
- ✅ Can manually adjust leave balances
- ✅ Can view workforce coverage analysis
- ✅ Can view all requests and balances system-wide

### HR Staff Role
- ✅ Can approve leave requests (1-5 days, own department only)
- ✅ Can submit leave requests on behalf of employees in own department
- ✅ Can manually adjust leave balances in own department
- ✅ Can view coverage analysis for own department
- ✅ Cannot approve 6+ day requests (escalates to HR Manager)

### Employee Role
- ✅ Can view own leave balances (earned, used, remaining, carried forward)
- ✅ Can submit leave requests with real-time coverage check
- ✅ Receives coverage warnings if <80% workforce availability
- ✅ Can view alternative date suggestions
- ✅ Can view request history and status
- ✅ Can cancel pending requests
- ✅ Can upload supporting documents (medical certificates)

### System Requirements
- ✅ Automatic monthly leave accrual (1st of each month)
- ✅ Automatic year-end carryover (Dec 31)
- ✅ Email + in-app notifications for all actions
- ✅ Approval workflow: 1-5 days (single level), 6+ days (two level)
- ✅ Balance tracking: Provisional deduction on submission, confirmed on approval, refunded on rejection/cancellation
- ✅ Coverage analysis: Real-time calculation, color-coded warnings, alternative date suggestions
- ✅ Authorization: Role-based access control with policy-based authorization
- ✅ Activity logging: All actions logged with user, timestamp, and details
- ✅ API endpoints: RESTful design, Inertia rendering, proper validation

---

## Technical Debt & Future Enhancements

### Known Limitations
- Coverage calculation assumes equal workforce distribution (doesn't account for skill gaps)
- No support for half-day or hourly leaves yet
- No support for unpaid leave tracking
- No support for leave type dependencies (e.g., must use sick leave before vacation)

### Planned Enhancements (Post-Launch)
- **Leave Analytics Dashboard**: Trends, peak periods, department comparison
- **Leave Calendar View**: Visual calendar showing team absences
- **Mobile App Support**: Native mobile app for request submission
- **Integration with Payroll**: Automatic leave deduction from payroll
- **Integration with Timekeeping**: Link approved leaves to timekeeping system
- **Leave Forecasting**: Predict future leave patterns based on historical data
- **Leave Delegation**: Assign backup/delegate when on leave
- **Emergency Leave Fast-Track**: Expedited approval for emergencies

---

## Related Documentation

- [HR Module Architecture](./HR_MODULE_ARCHITECTURE.md) - Overall HR module design
- [RBAC Guide](./RBAC_GUIDE.md) - Role-based access control
- [Database Schema](./HR_CORE_SCHEMA.md) - Database structure
- [API Documentation](./API_LEAVE_MANAGEMENT.md) - API endpoints (to be created)
- [User Guide](./USER_GUIDE_LEAVE_MANAGEMENT.md) - End-user documentation (to be created)

---

## Questions & Clarifications

### Resolved (User Answered)

**Q1: Should leave requests be auto-approved for certain roles or conditions?**
**A:** No. All requests require approval. Minimum approval levels:
- 1-5 days: HR Staff (department)
- 6+ days: HR Manager → Office Admin

**Q2: How should we handle leave requests during probationary periods?**
**A:** Employees on probation can submit requests, but they will not have earned balance. HR can manually adjust balance for compassionate cases.

**Q3: Should we support different leave calendars (fiscal vs calendar year)?**
**A:** Use calendar year (Jan 1 - Dec 31) for now. Fiscal year support can be added later.

**Q4: How should we handle employees who transfer between departments mid-year?**
**A:** Leave balances are tied to employee, not department. Balances transfer with employee. Approval routing updates to new department.

**Q5: What happens to pending requests when an employee resigns?**
**A:** Pending requests are auto-rejected. Approved future leaves are cancelled. Final leave balance calculated in exit clearance.

**Q6: Should we enforce a maximum consecutive leave days limit?**
**A:** No hard limit, but coverage warnings will trigger for extended absences. HR can override.

**Q7: How should we handle public holidays that fall within leave period?**
**A:** Public holidays are NOT counted as leave days. Only working days are deducted from balance.

**Q8: Should we support backdated leave requests?**
**A:** Only HR Manager and Office Admin can submit backdated requests. Employees must submit 3+ days in advance (except emergencies requiring medical proof).

**Q9: What's the cancellation policy for approved leaves?**
**A:** Employees can cancel approved leave up to 24 hours before start date. After that, requires HR approval.

**Q10: Should we calculate leave balance in days or hours?**
**A:** Use days for now. Hourly leave support can be added in Phase 2.
- [ ] In `LeaveRequestService::approveRequest()` - Send LeaveRequestApproved
- [ ] In `LeaveRequestService::rejectRequest()` - Send LeaveRequestRejected
- [ ] In `LeaveRequestService::cancelRequest()` - Send LeaveRequestCancelled
- [ ] In `LeaveController::storeRequest()` - Send LeaveRequestSubmitted
- [ ] In `AccrueMonthlyLeave` command - Send LeaveBalanceUpdated (monthly)
- [ ] In `ProcessLeaveCarryover` command - Send LeaveBalanceUpdated (carryover)
- [ ] In `LeaveBalanceService::adjustBalance()` - Send LeaveBalanceAdjusted
- [ ] In `WorkforceCoverageService` - Send LeaveCoverageWarning if critical
- [ ] Queue all notifications for background processing

#### Task 4.8: Testing
- [ ] Unit test: LeaveBalanceService initialization logic
- [ ] Unit test: LeaveBalanceService monthly accrual calculations
- [ ] Unit test: LeaveBalanceService carryover calculations
- [ ] Unit test: LeaveBalanceService adjustment logic
- [ ] Feature test: Initialize balances command works
- [ ] Feature test: Monthly accrual command works
- [ ] Feature test: Carryover command works correctly
- [ ] Feature test: HR can adjust balance manually
---

## Appendix: Code Examples

### Example 1: Leave Request Controller Store Method

```php
// app/Http/Controllers/Employee/LeaveController.php

public function store(LeaveRequestRequest $request)
{
    DB::beginTransaction();
    
    try {
        // Create leave request using service
        $leaveRequest = $this->leaveRequestService->createRequest([
            'user_id' => auth()->id(),
            'leave_policy_id' => $request->leave_policy_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'days_requested' => $this->leaveRequestService->calculateWorkingDays(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date)
            ),
        ]);
        
        // Handle document upload
        if ($request->hasFile('document')) {
            $path = $request->file('document')->store('leave_documents/' . auth()->id() . '/' . date('Y'));
            $leaveRequest->update(['document_path' => $path]);
        }
        
        // Determine approver and send notification
        $approver = $this->approvalWorkflowService->determineApprover($leaveRequest);
        $approver->notify(new LeaveRequestSubmittedNotification($leaveRequest));
        
        DB::commit();
        
        return redirect()->route('employee.leave.history')
            ->with('success', 'Leave request submitted successfully.');
            
    } catch (\Exception $e) {
        DB::rollBack();
        return back()->withErrors(['error' => 'Failed to submit leave request: ' . $e->getMessage()]);
    }
}
```

### Example 2: Coverage Calculation API

```php
// app/Http/Controllers/Employee/LeaveController.php

public function calculateCoverage(Request $request)
{
    $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
    ]);
    
    $employee = auth()->user();
    $coverageData = $this->workforceCoverageService->calculateCoverage(
        $employee->department_id,
        Carbon::parse($request->start_date),
        Carbon::parse($request->end_date)
    );
    
    return response()->json($coverageData);
}
```

### Example 3: Approval Workflow Service

```php
// app/Services/LeaveApprovalWorkflowService.php

public function approveRequest(int $requestId, int $approverId, ?string $comments = null): LeaveRequest
{
    $request = LeaveRequest::findOrFail($requestId);
    
    // Validate approver authority
    $this->validateApproverAuthority($request, $approverId);
    
    DB::beginTransaction();
    
    try {
        // Check if escalation needed (6+ days)
        if ($request->days_requested >= 6 && !$request->needsFinalApproval()) {
            $request->update(['status' => 'pending_final_approval']);
            $this->escalateToFinalApproval($requestId);
        } else {
            $request->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approverId,
            ]);
            
            // Confirm balance deduction
            $this->leaveBalanceService->confirmDeduction($request);
            
            // Notify employee
            $request->user->notify(new LeaveRequestApprovedNotification($request));
        }
        
        // Create approval record
        LeaveRequestApproval::create([
            'leave_request_id' => $requestId,
            'approver_id' => $approverId,
            'action' => 'approved',
            'comments' => $comments,
            'approved_at' => now(),
        ]);
        
        // Log activity
        activity()
            ->performedOn($request)
            ->causedBy($approverId)
            ->withProperties(['action' => 'approved', 'comments' => $comments])
            ->log('Leave request approved');
        
        DB::commit();
        
        return $request->fresh();
        
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

### Example 4: Working Days Calculation

```php
// app/Services/LeaveRequestService.php

public function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
{
    $workingDays = 0;
    $current = $startDate->copy();
    
    // Get Philippine holidays for the period
    $holidays = $this->getPhilippineHolidays($startDate->year);
    
    while ($current->lte($endDate)) {
        // Skip weekends
        if (!$current->isWeekend()) {
            // Skip public holidays
            if (!in_array($current->format('Y-m-d'), $holidays)) {
                $workingDays++;
            }
        }
        $current->addDay();
    }
    
    return $workingDays;
}

private function getPhilippineHolidays(int $year): array
{
    // Fetch from database or cache
    return Cache::remember("holidays_{$year}", 86400, function () use ($year) {
        return Holiday::where('year', $year)->pluck('date')->toArray();
    });
}
```

---

## Database Migrations Reference

### Migration: `add_leave_request_approvals_table`

```php
Schema::create('leave_request_approvals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('leave_request_id')->constrained()->onDelete('cascade');
    $table->foreignId('approver_id')->constrained('users')->onDelete('cascade');
    $table->enum('action', ['approved', 'rejected']);
    $table->enum('level', ['hr_staff', 'hr_manager', 'final'])->nullable();
    $table->text('comments')->nullable();
    $table->timestamp('approved_at');
    $table->timestamps();
});
```

### Migration: `add_leave_balance_adjustments_table`

```php
Schema::create('leave_balance_adjustments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('leave_balance_id')->constrained()->onDelete('cascade');
    $table->foreignId('adjusted_by')->constrained('users')->onDelete('cascade');
    $table->decimal('adjustment_amount', 5, 2); // Can be positive or negative
    $table->text('reason');
    $table->timestamps();
});
```

---

## Conclusion

This implementation focuses **exclusively on backend API development** to connect the existing React/TypeScript frontends with Laravel backend services. With all frontend components already built, the timeline is reduced to **3-4 weeks** covering:

1. **Office Admin Backend** - Policy management APIs
2. **Employee Backend** - Request submission and balance APIs
3. **HR Backend** - Approval workflow and management APIs
4. **Office Admin Final Approval** - Escalation logic for 6+ days
5. **Scheduled Jobs & Notifications** - Automation and alerts
6. **Testing** - Comprehensive backend and API testing
7. **Documentation** - API docs and deployment guides

All tasks focus on creating controllers, services, policies, and API endpoints that match the data structures and routes expected by the existing frontend pages.
- [ ] Document all endpoints:
  - Office Admin: Leave policy CRUD
  - HR Staff/Manager: Leave request processing
  - Employee: Leave request submission
  - Shared: Balance checking, coverage analysis
- [ ] Include: Route, Method, Auth, Parameters, Response format, Examples
- [ ] Add Postman collection or OpenAPI spec
- [ ] Add code examples for frontend integration

#### Task 5.5: User Documentation
- [ ] Office Admin Guide: How to configure leave policies
- [ ] HR Staff Guide: How to approve/reject leave requests
- [ ] HR Manager Guide: How to handle escalated requests
- [ ] Employee Guide: How to submit leave requests
- [ ] Troubleshooting: Common issues and solutions
- [ ] FAQ: Coverage warnings, balance calculations, carryover rules

#### Task 5.6: Database Seeding for Testing
- [ ] Create test employees (10-20) across different departments
- [ ] Create leave balances for all test employees
- [ ] Create sample leave requests (pending, approved, rejected, cancelled)
- [ ] Create historical data (previous year balances and requests)
- [ ] Add `DatabaseSeeder` call for leave management test data
- [ ] Add `--seed` option to refresh test data

#### Task 5.7: Production Deployment Checklist
- [ ] Run migrations: `php artisan migrate`
- [ ] Run seeders: `php artisan db:seed --class=LeavePolicySeeder`
- [ ] Initialize balances: `php artisan leave:initialize-balances 2025`
- [ ] Schedule commands in `app/Console/Kernel.php`:
  - Monthly accrual: 1st of month
  - Carryover: Dec 31
- [ ] Configure notification channels (mail, database)
- [ ] Set up queue worker for notification processing
- [ ] Verify RBAC permissions for all roles
- [ ] Test in staging environment
- [ ] Monitor logs for errors
- [ ] Backup database before deployment

#### Task 5.8: Monitoring & Logging
- [ ] Add logging for all critical operations:
  - Leave request submissions
  - Approval/rejection actions
  - Balance adjustments
  - Accrual processing
  - Carryover processing
- [ ] Add metrics: Total requests, approval rate, average response time
- [ ] Add alerts: Failed accrual, failed carryover, critical coverage
- [ ] Create admin dashboard: Leave management stats

---

## Success Criteria

### Functional Requirements Met
- ✅ Office Admin can create, edit, delete leave policies
- ✅ HR Staff can approve/reject leave requests (1-5 days)
- ✅ HR Manager can approve/reject all leave requests
- ✅ Office Admin can provide final approval (6+ days)
- ✅ Employees can view balances and submit requests
- ✅ Employees receive coverage warnings when submitting
- ✅ HR views workforce coverage analysis when approving
- ✅ Balances automatically accrue monthly (scheduled job)
- ✅ Balances automatically carry over at year end
- ✅ Notifications sent for all major actions
- ✅ HR can manually adjust balances with audit log

### Technical Requirements Met
- ✅ Authorization enforced via Laravel Policies
- ✅ Business logic in Service layer (not controllers)
- ✅ All database operations use transactions
- ✅ All balance updates logged with activity log
- ✅ Notifications queued for background processing
- ✅ Scheduled commands for accrual and carryover
- ✅ Frontend uses Inertia.js and React TypeScript
- ✅ API responses include proper error handling
- ✅ All features covered by automated tests

### Non-Functional Requirements Met
- ✅ Response time < 500ms for leave request submission
- ✅ Coverage analysis completes in < 2 seconds
- ✅ Monthly accrual completes in < 5 minutes for 5000 employees
- ✅ Carryover completes in < 10 minutes for 5000 employees
- ✅ 95%+ uptime for leave management features
- ✅ Zero data loss for balance calculations
- ✅ Audit trail for all financial/balance changes

---

## Risk Assessment

### High Risk
1. **Balance Calculation Errors**
   - Risk: Incorrect accrual or carryover calculations
   - Impact: Financial liability, employee trust issues
   - Mitigation: Comprehensive unit tests, manual verification before first year-end

2. **Race Conditions**
   - Risk: Concurrent approvals or balance updates
   - Impact: Duplicate balance deductions, data inconsistency
   - Mitigation: Database transactions, row-level locking, unique constraints

### Medium Risk
1. **Coverage Analysis Performance**
   - Risk: Slow queries for large departments
   - Impact: Poor user experience, timeout errors
   - Mitigation: Query optimization, caching, async processing

2. **Notification Overload**
   - Risk: Too many notifications for HR Staff
   - Impact: Important notifications missed
   - Mitigation: Notification preferences, batching, summary emails

### Low Risk
1. **Historical Data Import**
   - Risk: Complex one-time data migration
   - Impact: Initial setup time
   - Mitigation: Optional feature, manual entry alternative

2. **Document Attachments**
   - Risk: File storage and security
   - Impact: Additional complexity
   - Mitigation: Use existing file upload infrastructure if available

---

## Dependencies

### Internal Dependencies
- ✅ Employee model and table (exists)
- ✅ Department model and table (exists)
- ✅ User authentication and RBAC system (exists)
- ✅ Activity logging system (Spatie Activity Log - exists)
- ✅ Notification system (Laravel Notifications - exists)
- ⚠️ Queue system (Laravel Queues - verify configured)
- ⚠️ Scheduler (Laravel Task Scheduling - verify configured)
- ⚠️ File upload system (for document attachments - optional)

### External Dependencies
- None (all features can be implemented with existing Laravel stack)

### Technical Stack
- Laravel 11.x
- Inertia.js + React TypeScript
- PostgreSQL
- Laravel Policies (Authorization)
- Laravel Notifications
- Laravel Queues
- Laravel Task Scheduler
- Spatie Activity Log

---

## Timeline Estimate

| Phase | Duration | Start | End |
|-------|----------|-------|-----|
| Phase 1: Office Admin | 1-2 weeks | Week 1 | Week 2 |
| Phase 2: Employee Portal | 1-1.5 weeks | Week 2 | Week 3 |
| Phase 3: HR Staff | 1 week | Week 3 | Week 4 |
| Phase 4: HR Manager | 1-1.5 weeks | Week 4 | Week 5 |
| Phase 5: Office Admin Final | 1 week | Week 5 | Week 6 |
| Phase 6: Notifications | 1 week | Week 6 | Week 6 |
| Phase 7: Integration & Testing | 1 week | Week 6 | Week 6 |
| Phase 8: Documentation | 1 week | Week 6 | Week 6 |

**Total Duration: 4-6 weeks**

*Phases 6-8 can run partially in parallel during Week 6

---

## ✅ Acceptance Criteria

**Phase 1 Complete When:**
- [ ] Office Admin can create, update, and delete leave policies
- [ ] Office Admin can configure approval workflows
- [ ] Office Admin can set blackout periods
- [ ] Leave accrual service implemented

**Phase 2 Complete When:**
- [ ] Employees can submit leave requests via portal
- [ ] Employees can view leave balances and history
- [ ] Employees can cancel pending requests
- [ ] Coverage calculation displays in real-time

**Phase 3 Complete When:**
- [ ] HR Staff can submit leave on behalf of employees
- [ ] HR Staff can adjust leave balances
- [ ] HR Staff can view and manage all leave requests
- [ ] HR Staff can cancel leave requests

**Phase 4 Complete When:**
- [ ] HR Manager can approve/reject leave requests
- [ ] HR Manager can conditionally approve long leaves (6+ days)
- [ ] Coverage warnings display during approval
- [ ] Approval workflow enforces correct authority

**Phase 5 Complete When:**
- [ ] Office Admin can provide final approval (6+ days)
- [ ] Office Admin can view leave policy analytics
- [ ] Policy enforcement rules are active
- [ ] Leave reports can be exported

**Phase 6 Complete When:**
- [ ] All notifications are sent correctly
- [ ] Notification center displays leave updates
- [ ] Email notifications work
- [ ] Audit trail logs all leave actions

**Phase 7 Complete When:**
- [ ] All integration tests pass
- [ ] All unit tests pass
- [ ] Performance benchmarks met
- [ ] Frontend integration complete

**Phase 8 Complete When:**
- [ ] API documentation complete
- [ ] User guide complete
- [ ] Deployment checklist ready
- [ ] Rollback plan tested

---

## 📊 Progress Tracking

**Overall Progress:** 0 / 184+ subtasks completed (0%)

**Phase Progress:**
- Phase 1 (Office Admin): 0 / 40+ subtasks (0%)
- Phase 2 (Employee Portal): 0 / 30+ subtasks (0%)
- Phase 3 (HR Staff): 0 / 20+ subtasks (0%)
- Phase 4 (HR Manager): 0 / 30+ subtasks (0%)
- Phase 5 (Office Admin Final): 0 / 20+ subtasks (0%)
- Phase 6 (Notifications): 0 / 20+ subtasks (0%)
- Phase 7 (Testing): 0 / 15+ subtasks (0%)
- Phase 8 (Documentation): 0 / 10+ subtasks (0%)

---

## 🔗 Related Documentation

- [Office Admin Workflow](../../docs/workflows/02-office-admin-workflow.md)
- [HR Manager Workflow](../../docs/workflows/03-hr-manager-workflow.md)
- [HR Staff Workflow](../../docs/workflows/04-hr-staff-workflow.md)
- [Employee Portal Guide](../../docs/workflows/06-employee-portal.md)
- [System Overview](../../docs/workflows/00-system-overview.md)
- [Database Schema](../../docs/DATABASE_SCHEMA.md)

---

## Next Steps

1. ✅ **Review Clarifications** - All questions answered and documented
2. **Confirm Additional Tables** - Verify if we need to create: `leave_approval_workflows`, `leave_policy_history`, `leave_blackout_periods`, `leave_balance_adjustments`, `leave_request_attachments`
3. **Prioritize Phases** - Confirm 8-phase approach or adjust
4. **Begin Phase 1** - Start with Office Admin leave policy management
5. **Set Up Environment** - Verify queue and scheduler configuration

---

## 📝 Notes

- **Priority:** HIGH - Employee Portal frontend already complete
- **Database:** All core migrations exist - may need additional tables for advanced features
- **Models:** All core models exist with proper relationships
- **Seeder:** Production-ready leave types already configured
- **Focus:** Service layer, controllers, policies, frontend, and notifications
- **Notifications:** Email + in-app required
- **Testing:** Comprehensive testing required due to cross-role complexity
- **Performance:** Coverage calculation may need caching for large departments
- **Integration:** Payroll and attendance/timekeeping integration required
- **Deployment:** Verify queue worker and task scheduler before production

---

**Issue Created:** December 8, 2025  
**Last Updated:** December 8, 2025  
**Status:** Ready for Implementation
