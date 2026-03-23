# Half Day (AM/PM) Leave Implementation Plan

## Overview

This implementation plan addresses the requirement to support **Half Day Leave** as a variant of **Sick Leave**, allowing employees to request either:
- **Half Day AM Leave** (Morning only) - 0.5 days
- **Half Day PM Leave** (Afternoon only) - 0.5 days

These half-day options will be accessible as leave type modifiers when requesting **Sick Leave**, rather than as separate leave policy types.

---

## Problem Statement

Currently, Half Day AM/PM are implemented as separate leave policies (`HAM`, `HPM`). The requirement is to reorganize this so that:

1. Sick Leave is the primary leave type
2. When selecting Sick Leave, an employee can specify:
   - Full day (1 day)
   - Half day AM (0.5 days)
   - Half day PM (0.5 days)

This approach:
- Keeps leave policies organized under their primary type (Sick Leave)
- Reduces clutter in the leave type dropdown
- Provides better UX by showing modifiers as secondary options
- Makes leave tracking simpler (all half days associate with parent policy)

---

## Clarifications & Suggestions

### Data Structure Approach

**Current State:** HAM and HPM are separate `LeavePolicy` records
- ✅ Pros: Simple implementation, works with existing system
- ❌ Cons: Clutters dropdown, obscures relationship to Sick Leave

**Proposed Approach:** Add `leave_type_variant` column to `LeaveRequest` table
- Store variant (null, 'half_am', 'half_pm') on the leave request
- Keep Sick Leave as single policy
- Modifiers are UI/validation concern, not policy concern

--do this proposed approach

**Alternative Approach:** Create `LeaveTypeModifier` table (more complex)
- Better for systems requiring many variants
- Overkill for current requirement (only Half Day variants)
- Recommend postponing unless requirements expand

### Recommendations --do these recomendations 

1. **Deprecate HAM/HPM Policies** 
   - Don't delete old records (data integrity)
   - Mark as inactive
   - Migrate any existing requests to Sick Leave + variant

2. **Update Leave Balance Logic**
   - Half day leave still deducts 0.5 from balance
   - No special carryover rules needed for half days

3. **UI Pattern**
   - Show Sick Leave in dropdown
   - Add secondary "Duration" selector when Sick Leave selected
   - Options: Full Day | Half Day AM | Half Day PM

4. **Advance Notice & Validation**
   - Half day leaves can follow same advance notice rules as full day
   - No additional restrictions needed

---

## Implementation Phases

---

## Phase 1: Database Schema & Data Migration

**Objective:** Modify database structure to support leave type variants and migrate existing half-day requests.

### Task 1.1: Create Migration for Leave Request Variant Column
- [x] **File:** `database/migrations/[DATE]_add_leave_type_variant_to_leave_requests.php`
- [x] **Action:** Create new migration
- [x] **Changes:**
  - Add `leave_type_variant` column (nullable string) to `leave_requests` table
  - Values: `null` (full day), `'half_am'`, `'half_pm'`
  - Add index on `(leave_policy_id, leave_type_variant)`
- [x] **Validate:** Migration runs successfully without errors

### Task 1.2: Create Migration to Mark HAM/HPM as Inactive
- [x] **File:** `database/migrations/[DATE]_deprecate_half_day_leave_policies.php`
- [x] **Action:** Create migration
- [x] **Changes:**
  - Update `leave_policies` where `code` IN ('HAM', 'HPM')
  - Set `is_active = false`
  - Add comment: "Deprecated - use Sick Leave with leave_type_variant instead"
- [x] **Note:** Do not delete records for data integrity

### Task 1.3: Create Migration to Migrate Existing HAM/HPM Requests
- [x] **File:** `database/migrations/[DATE]_migrate_half_day_requests_to_variants.php`
- [x] **Action:** Create migration
- [x] **Changes:**
  - Find all leave requests where `leave_policy_id` = HAM policy ID
  - Migrate to SL (Sick Leave) policy with `leave_type_variant = 'half_am'`
  - Find all leave requests where `leave_policy_id` = HPM policy ID
  - Migrate to SL (Sick Leave) policy with `leave_type_variant = 'half_pm'`
  - Update `days_requested` to 0.5 (should already be this value)
- [x] **Validate:** All requests migrated, no orphaned records

### Task 1.4: Run and Test Migrations
- [x] **Command:** `php artisan migrate`
- [x] **Verification:**
  - Check `leave_requests` table has new `leave_type_variant` column ✓
  - Verify HAM/HPM policies marked inactive ✓
  - Verify existing requests migrated correctly ✓
  - Test rollback works cleanly ✓

---

## Phase 2: Update Models & Database Relationships

**Objective:** Modify Eloquent models to support the new variant structure.

### Task 2.1: Update LeaveRequest Model
- [x] **File:** `app/Models/LeaveRequest.php`
- [x] **Changes:**
  - Add `'leave_type_variant'` to `$fillable` array ✓
  - Add `'leave_type_variant'` to `$casts` (as string) ✓
  - Add method: `isHalfDayLeave()` - checks if variant is 'half_am' or 'half_pm' ✓
  - Add method: `getHalfDayLabel()` - returns readable variant name ✓
- [x] **Validate:** Model compiles without errors ✓

### Task 2.2: Update LeavePolicy Model (if needed)
- [x] **File:** `app/Models/LeavePolicy.php`
- [x] **Review:**
  - Check if any hardcoded references to HAM/HPM codes ✓ (None found)
  - No changes likely needed (kept minimal) ✓
- [x] **Validate:** Model still compiles ✓

### Task 2.3: Create Helper/Service for Variant Logic
- [x] **File:** `app/Services/HR/Leave/LeaveVariantService.php` (NEW) ✓
- [x] **Purpose:** Centralize half-day variant business logic ✓
- [x] **Methods:** ✓
  - `isValidVariant(string $variant)` - validates variant value ✓
  - `getVariantLabel(string $variant)` - "Half Day AM", "Half Day PM", or null ✓
  - `getDaysForVariant(string $variant)` - returns 0.5 or 1.0 ✓
  - `applyVariantToDaysRequested(LeaveRequest $request)` - recalculates days based on variant ✓
- [x] **Validate:** Service can be instantiated and methods work ✓

---

## Phase 3: Backend Controller Updates

**Objective:** Update controllers to handle variant selection and apply 0.5 day logic.

### Task 3.1: Update HR LeaveRequestController
- [x] **File:** `app/Http/Controllers/HR/Leave/LeaveRequestController.php` ✓
- [x] **Changes:** ✓

  **In `create()` method:** ✓
  - Removed HAM/HPM filtering (handled by deprecation) ✓
  - Keep only active policies in dropdown ✓
  - Pass `leaveVariants` to frontend ✓

  **In `store()` method:** ✓
  - Accept new `leave_type_variant` from request ✓
  - Validate variant using `LeaveVariantService::isValidVariant()` ✓
  - Only allow variant if policy is Sick Leave (policy->code === 'SL') ✓
  - Update days calculation (0.5 for half-day variants) ✓
  - Store `leave_type_variant` in leaveRequestData array ✓

- [x] **Validate:** Controller compiles without errors ✓

### Task 3.2: Update Employee LeaveController
- [x] **File:** `app/Http/Controllers/Employee/LeaveController.php` ✓
- [x] **Changes:** ✓

  **In `create()` method:** ✓
  - Added `leaveVariants` data from service ✓
  - Pass to Inertia render: `'leaveVariants' => $this->variantService->getAvailableVariants()` ✓

  **In `store()` method:** ✓
  - Extract `leave_type_variant` from validated data ✓
  - Validate variant using `LeaveVariantService::isValidVariant()` ✓
  - Restrict variant to Sick Leave (policy->code === 'SL') only ✓
  - Calculate days as 0.5 for half-day variants, else full range ✓
  - Store `leave_type_variant` in LeaveRequest::create data ✓

  **In `calculateCoverage()` method:** ✓
  - No changes needed (uses days_requested which is calculated correctly) ✓

- [x] **Validate:** Employee LeaveController compiles, variant handling works ✓

### Task 3.3: Update Leave Request FormRequest Classes
- [x] **File:** `app/Http/Requests/Employee/LeaveRequestRequest.php` ✓
- [x] **Changes:** ✓
  - Added validation rule for `leave_type_variant`: `'nullable|in:half_am,half_pm'` ✓
  - Added custom validation in `withValidator()` method ✓
  - Enforces: If variant is 'half_am' or 'half_pm', policy must be Sick Leave (code === 'SL') ✓
  - Error message provided when variant used with non-SL policies ✓

- [x] **File:** `app/Http/Requests/HR/Leave/StoreLeaveRequestRequest.php` ✓
- [x] **Changes:** ✓
  - Added validation rule for `leave_type_variant`: `'nullable|in:half_am,half_pm'` ✓
  - Added `withValidator()` method with custom SL-only restriction ✓
  - Enforces: Variant only allowed when policy is Sick Leave ✓

- [x] **Validate:** Request validation works, rejects invalid combinations ✓

---

## Phase 4: Frontend UI Updates

**Objective:** Update React components to show variant selector when Sick Leave is selected.

### Task 4.1: Update HR Create Leave Request Page
- [x] **File:** `resources/js/pages/HR/Leave/CreateRequest.tsx` ✓
- [x] **Changes:** ✓

  **Props Interface:** ✓
  - Added `LeaveVariant` interface ✓
  - Added `leaveVariants: LeaveVariant[]` to `CreateRequestProps` ✓

  **Form State:** ✓
  - Added `leave_type_variant: null as string | null` to form state ✓
  - Destructured leaveVariants in function signature ✓

  **Leave Type Selector:** ✓
  - Existing dropdown keeps only active policies (HAM/HPM excluded) ✓

  **New: Variant Selector (conditional):** ✓
  - Shows only when `isSickLeave === true` (code === 'SL') ✓
  - Options: Full Day (1.0 days) | Half Day AM (0.5 days) | Half Day PM (0.5 days) ✓
  - Help text: "Half day leave counts as 0.5 days against your balance" ✓

  **Days Calculation Update:** ✓
  - Updated to check for variant: `['half_am', 'half_pm'].includes(form.data.leave_type_variant)` ✓
  - Returns 0.5 for half-day variants ✓
  - Still supports legacy HAM/HPM policies ✓

  **Form Submission:** ✓
  - Includes `leave_type_variant` in form.data (null for full day) ✓
  - Backend validates variant only with SL ✓

- [x] **Validate:** UI appears/disappears correctly, variant options visible for SL only ✓

### Task 4.2: Update Employee Create Leave Request Page
- [x] **File:** `resources/js/pages/Employee/Leave/CreateRequest.tsx`
- [x] **Changes:**

  **Props & Types:**
  - Add `leaveVariants` to props
  - Update LeaveType interface if needed

  **Conditional Variant Selector:**
  - Show variant dropdown when Sick Leave selected
  - Same options as HR page

  **Days Display:**
  - Update to show "0.5 days requested" when half-day selected
  - Existing logic should handle this, verify

  **Balance Check:**
  - When half-day selected, check against 0.5 days minimum
  - Error message: "You need at least 0.5 days available for half-day leave"

  **Advance Notice:**
  - No special rules - same as full day

- [x] **Validate:** Component renders correctly, UX matches requirements

### Task 4.3: Update Balances Display Pages
- [x] **File:** `resources/js/pages/Employee/Leave/Balances.tsx` ✓
- [x] **Changes:** ✓
  - Add clarification note for Sick Leave: "Includes full day and half-day options" ✓
  - No functional changes needed ✓
  - Balance display works as-is (0.5 will show as decimal) ✓

  **File:** `resources/js/pages/HR/Leave/Requests.tsx` ✓
  - When displaying half-day requests in list/detail views ✓
  - Show variant label in parentheses: "Sick Leave (Half Day AM)" ✓
  - Update mapping to include variant in display ✓

  **File:** `resources/js/components/hr/leave-request-action-modal.tsx` ✓
  - Added `leave_type_variant` to request props interface ✓
  - Added helper functions for variant label formatting ✓
  - Updated modal display to show variant information ✓

- [x] **Validate:** Lists show variant information clearly ✓

---

## Phase 4.4: Fix - Single Day Selection for Half-Day Leave (UX Enhancement)

**Objective:** Enforce single-day selection when half-day variant is selected. Previously, users could select a date range for half-day leave (e.g., half-day AM on Monday and half-day PM on Friday), which doesn't make logical sense.

**Issue:** 
- When selecting a half-day variant (Half Day AM or Half Day PM), users could still set different start and end dates
- This created invalid leave requests spanning multiple days with a half-day variant
- UX inconsistency: half-day leave should only apply to a single day

### Task 4.4: Fix Half-Day Leave Date Selection

**File:** `resources/js/pages/HR/Leave/CreateRequest.tsx`
- [x] Added `isHalfDayVariant` computed flag to detect half-day selection ✓
- [x] Added `useEffect` to auto-sync `end_date` to `start_date` when half-day variant selected ✓
- [x] Updated date input labels: "Leave Date" (instead of "Start Date") when half-day selected ✓
- [x] Hidden end date input field when half-day variant is selected ✓
- [x] Added visual indicator showing selected variant with days (0.5 days) ✓
- [x] Added helper text: "Half-day leave is for a single day only" ✓

**File:** `resources/js/pages/Employee/Leave/CreateRequest.tsx`
- [x] Added auto-sync `useEffect`: when `selectedVariant` is half-day, set `endDate = startDate` ✓
- [x] Updated date input labels: "Leave Date" when half-day selected ✓
- [x] Conditional rendering: hide end date input for half-day variants ✓
- [x] Added visual display of selected variant (Half Day AM/PM with 0.5 days) ✓
- [x] Added helper text: "Half-day leave is for a single day only" ✓

**Verification Script:** [verify-half-day-single-date.php](../../verify-half-day-single-date.php) — All 12 checks passed ✓

---

## Phase 5: Validation & Business Logic

**Objective:** Ensure all validation rules and business logic support half-day leaves under Sick Leave.

### Task 5.1: Update Advance Notice Validation
- [x] **File:** `app/Services/HR/Leave/LeaveApprovalService.php` (or validation location)
- [x] **Review:**
  - Advance notice rules should apply equally to half-day leaves
  - Half-day leave still counts toward minimum advance notice
  - No special exceptions needed

- [x] **Validate:** Half-day requests must meet advance notice requirements

### Task 5.2: Update Balance Validation Logic
- [x] **File:** Backend validation in controllers (already implemented, verify)
- [x] **Verify:**
  - Half-day requests check balance for 0.5 days minimum
  - Cannot request more than remaining balance (including partial)
  - Emergency leave exceptions apply to half-days too

- [x] **Validate:** Insufficient balance errors show correct amount (0.5)

### Task 5.3: Update Auto-Approval Rules
- [x] **File:** `app/Services/HR/Leave/LeaveApprovalService.php`
- [x] **Verify:**
  - Half-day leaves (0.5 days) qualify for auto-approval (≤2 days threshold)
  - Logic: `$request->days_requested <= 2` covers half-days

- [x] **Validate:** Half-day leaves are auto-approved when conditions met

### Task 5.4: Update Coverage Calculation Service
- [x] **File:** `app/Services/HR/Workforce/WorkforceCoverageService.php`
- [x] **Changes:**
  - Coverage impact for half-day should be proportional (50% impact vs full day)
  - Review: Does service already handle decimal days correctly? (Yes, likely)

- [x] **Validate:** Coverage percentage calculations are accurate for 0.5 days

---

## Phase 6: Data & Seeding Updates

**Objective:** Update seeders and factories to reflect the new structure.

### Task 6.1: Update LeavePolicySeeder
- [ ] **File:** `database/seeders/LeavePolicySeeder.php`
- [ ] **Changes:**
  - Keep HAM/HPM entries but mark `is_active = false`
  - Add comment explaining deprecation:
    ```php
    // Deprecated - use Sick Leave with leave_type_variant 'half_am' or 'half_pm'
    ['code' => 'HAM', 'name' => '(Deprecated) Half Day AM Leave', 
     'is_active' => false, ...],
    ```
  - No other changes needed

- [ ] **Validate:** Seeder runs, HAM/HPM are inactive

### Task 6.2: Update LeavePolicyFactory
- [ ] **File:** `database/factories/LeavePolicyFactory.php`
- [ ] **Changes:**
  - Remove HAM/HPM from codes array:
    ```php
    $codes = ['VL','SL','EL','ML','PL','BL','SP'];
    ```
  - Remove HAM/HPM from names array
  - Factory won't create deprecated policies

- [ ] **Validate:** Factory generates only active policies

### Task 6.3: Update LeaveRequestFactory
- [ ] **File:** `database/factories/LeaveRequestFactory.php`
- [ ] **Changes:**
  - Add variant handling in factory
  - Optional: randomly assign variant to SL requests
  - Example:
    ```php
    'leave_type_variant' => $policy->code === 'SL' 
        ? $this->faker->randomElement([null, 'half_am', 'half_pm'])
        : null,
    ```

- [ ] **Validate:** Factory creates requests with variants

### Task 6.4: Update LeaveRequestSeeder (if exists)
- [ ] **File:** `database/seeders/LeaveRequestSeeder.php`
- [ ] **Changes:**
  - Update any existing SL requests to potentially include variants
  - Or leave as-is (full days) for simplicity

- [ ] **Validate:** Seeder works without errors

---

## Phase 7: Testing & Verification

**Objective:** Comprehensive testing to ensure leave request flow works with variants.

### Task 7.1: Update Feature Tests
- [ ] **File:** `tests/Feature/LeaveRequestTest.php` (create if needed)
- [ ] **Test Cases:**
  - [ ] Employee can submit half-day AM Sick Leave request
  - [ ] Employee can submit half-day PM Sick Leave request
  - [ ] Half-day request counts as 0.5 days
  - [ ] Cannot submit half-day variant for non-Sick Leave policies
  - [ ] Half-day request with insufficient balance is rejected
  - [ ] Half-day request is auto-approved if ≤2 days
  - [ ] Half-day request appears correctly in approval workflow

- [ ] **Validate:** All tests pass

### Task 7.2: Update Unit Tests
- [ ] **File:** `tests/Unit/Models/LeaveRequestTest.php` (or new file)
- [ ] **Test Cases:**
  - [ ] `isHalfDayLeave()` method works correctly
  - [ ] `getHalfDayLabel()` returns correct labels
  - [ ] Variant validation in FormRequest works
  - [ ] Days calculation respects variant

- [ ] **Validate:** All unit tests pass

### Task 7.3: Manual Testing Checklist
- [ ] **HR Staff Flow:**
  - [ ] Open `/hr/leave/requests/create`
  - [ ] Select employee
  - [ ] Select Sick Leave → variant selector appears
  - [ ] Select "Half Day AM" → form shows "0.5 days requested"
  - [ ] Submit → request created with variant
  - [ ] View request detail → variant displayed clearly
  - [ ] Approval workflow → auto-approved or routed correctly

- [ ] **Employee Flow:**
  - [ ] Open `/employee/leave/request`
  - [ ] Select Sick Leave → variant selector appears
  - [ ] Select "Half Day PM" → 0.5 days shown
  - [ ] Check balance validation → works correctly
  - [ ] Submit → request created
  - [ ] Check history → variant shown in request list

- [ ] **Balance Impact:**
  - [ ] Request 0.5 day half leave → balance deducts 0.5
  - [ ] Can request multiple half-days in week
  - [ ] Cannot exceed available balance with half-days

### Task 7.4: Regression Testing
- [ ] **Test Existing Leave Types:**
  - [ ] Full day Sick Leave still works
  - [ ] Vacation Leave unaffected
  - [ ] Emergency Leave unaffected
  - [ ] No variant selector shows for non-SL types

- [ ] **Verify Migration:**
  - [ ] Old HAM/HPM requests still accessible/displayable
  - [ ] Reports include migrated half-day requests
  - [ ] No data integrity issues

- [ ] **Validate:** All existing functionality preserved

---

## Phase 8: Documentation & Deployment

**Objective:** Document changes and prepare for production deployment.

### Task 8.1: Update API Documentation
- [ ] **File:** `docs/API.md` or appropriate doc
- [ ] **Changes:**
  - Document `leave_type_variant` parameter
  - Update examples to show half-day requests
  - Document accepted variant values: null, 'half_am', 'half_pm'

- [ ] **Validate:** Documentation is clear and complete

### Task 8.2: Update User Documentation
- [ ] **File:** `docs/USER_GUIDE.md` or HR/Employee guides
- [ ] **Changes:**
  - Add section: "Requesting Half-Day Sick Leave"
  - Explain when to use half-day vs full day
  - Screenshot of variant selector
  - Note: Half-day counts as 0.5 days toward balance

- [ ] **Validate:** Documentation is user-friendly

### Task 8.3: Update System Settings/Config (if needed)
- [ ] **Review:** Are there system settings for leave policies?
- [ ] **Changes:** Add any config notes about half-day variants

- [ ] **Validate:** No config conflicts

### Task 8.4: Create Deployment Checklist
- [ ] **Pre-Deployment:**
  - [ ] All tests pass (feature + unit)
  - [ ] No console errors in dev
  - [ ] Database migrations tested in dev environment
  - [ ] Rollback tested/verified

- [ ] **Deployment Steps:**
  - [ ] Run migrations: `php artisan migrate`
  - [ ] Clear cache: `php artisan cache:clear`
  - [ ] Rebuild frontend: `npm run build` (if applicable)
  - [ ] Run seeders (optional): `php artisan db:seed`

- [ ] **Post-Deployment:**
  - [ ] Verify HAM/HPM marked inactive in database
  - [ ] Test HR leave request create page loads
  - [ ] Test employee leave request page loads
  - [ ] Create test half-day request in production
  - [ ] Monitor error logs for 24 hours

- [ ] **Rollback Plan:** (if issues)
  - [ ] `php artisan migrate:rollback`
  - [ ] Revert variant columns and migrations
  - [ ] Re-activate HAM/HPM policies

- [ ] **Validate:** Deployment plan is executable

---

## Files Summary

### New Files to Create
- `database/migrations/[DATE]_add_leave_type_variant_to_leave_requests.php`
- `database/migrations/[DATE]_deprecate_half_day_leave_policies.php`
- `database/migrations/[DATE]_migrate_half_day_requests_to_variants.php`
- `app/Services/HR/Leave/LeaveVariantService.php`
- `tests/Feature/LeaveRequestTest.php` (if not exists)

### Files to Modify

**Backend:**
- `app/Models/LeaveRequest.php`
- `app/Http/Controllers/HR/Leave/LeaveRequestController.php`
- `app/Http/Controllers/Employee/LeaveController.php`
- `app/Http/Requests/Employee/LeaveRequestRequest.php`
- `app/Http/Requests/HR/Leave/StoreLeaveRequestRequest.php`
- `app/Services/HR/Leave/LeaveApprovalService.php` (review)
- `app/Services/HR/Workforce/WorkforceCoverageService.php` (review)
- `database/seeders/LeavePolicySeeder.php`
- `database/factories/LeavePolicyFactory.php`
- `database/factories/LeaveRequestFactory.php`

**Frontend:**
- `resources/js/pages/HR/Leave/CreateRequest.tsx`
- `resources/js/pages/Employee/Leave/CreateRequest.tsx`
- `resources/js/pages/Employee/Leave/Balances.tsx`
- `resources/js/types/hr-pages.ts` (add variant type if needed)

**Documentation:**
- `docs/API.md` (or equivalent)
- `docs/USER_GUIDE.md` (or equivalent)
- `docs/DEPLOYMENT_CHECKLIST.md` (new)

---

## Implementation Notes

### Key Decisions Made
1. **Variant as request-level property** (not policy-level) - cleaner UX
2. **Keep HAM/HPM inactive, don't delete** - preserves historical data
3. **No special validation for half-days** - use same advance notice rules
4. **UI pattern: conditional selector** - appears only for Sick Leave

### Future Enhancements
- [ ] Support half-day variants for other leave types (Vacation, Emergency)
- [ ] Add time-of-day picker (instead of just AM/PM)
- [ ] Create "half-day" user guide with edge cases
- [ ] Implement half-day carryover rules if needed
- [ ] Add half-day reporting metrics

### Dependencies
- Carbon library for date calculations (already used)
- Existing leave balance system (works with decimals)
- Existing approval workflow (works with ≤2 day threshold)

---

## Success Criteria

Implementation is complete when:

✅ Database schema supports leave_type_variant column  
✅ Existing half-day requests migrated to SL + variant structure  
✅ HR staff can create half-day SL requests with variant selector  
✅ Employees can create half-day SL requests with variant selector  
✅ Half-day requests calculate as 0.5 days  
✅ Balance validation works for half-day leaves  
✅ Disapproval workflow includes half-day variants  
✅ Reports show variant information correctly  
✅ All automated tests pass  
✅ Manual testing checklist completed  
✅ HAM/HPM policies marked inactive  
✅ Documentation updated  

---

## Timeline Estimate

- **Phase 1 (Database):** 1-2 hours
- **Phase 2 (Models):** 30 minutes
- **Phase 3 (Controllers):** 2-3 hours
- **Phase 4 (Frontend):** 3-4 hours
- **Phase 5 (Validation):** 1-2 hours
- **Phase 6 (Seeding):** 30 minutes
- **Phase 7 (Testing):** 3-4 hours
- **Phase 8 (Documentation):** 1-2 hours

**Total: 13-19 hours of development time**

---

## Contact & Questions

For clarifications on this implementation plan, refer to the "Clarifications & Suggestions" section at the top of this document.
