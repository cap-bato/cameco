# Phase 6 Task 6.3: Manual Testing & Validation Guide

## Overview

This guide provides step-by-step manual testing scenarios for the EmployeePayroll Module (Phase 6 Tasks 6.2.1 and 6.2.2). Testing covers:
- UI functionality and user workflows
- Data consistency and validation
- Integration between services
- Error handling and edge cases
- End-to-end business scenarios

**Duration:** 2-3 hours for complete manual testing
**Prerequisites:** Database seeded with test data, application running locally
**Test Environment:** Development environment with RefreshDatabase capability

---

## Test Scenarios

### Scenario 1: Create Employee Payroll Info with Salary History

**Objective:** Verify payroll info creation, derived rate calculations, and history tracking

**Steps:**
1. Navigate to **Payroll → Employee Payroll → Info**
2. Click **Create Payroll Info** button
3. Select an employee from the dropdown
4. Fill in payroll information:
   - Salary Type: `Monthly`
   - Basic Salary: `₱30,000`
   - Payment Method: `Bank Transfer`
   - Tax Status: `Single (S)`
   - Bank Name: `BPI`
   - Bank Account: `1234567890`
   - Government Numbers:
     - SSS: `01-2345678-9`
     - PhilHealth: `001000000001`
     - Pag-IBIG: `1204-5678-9012`
5. Click **Save**

**Expected Results:**
- ✅ Payroll info created successfully
- ✅ Daily Rate = ₱1,363.64 (30,000 ÷ 22)
- ✅ Hourly Rate = ₱170.45 (1,363.64 ÷ 8)
- ✅ SSS Bracket = `E5` (auto-detected from salary range)
- ✅ Record appears in "Active Payroll Info" list
- ✅ Created by: Current user
- ✅ Effective Date: Today

**Validation:**
- Verify daily_rate = basic_salary / 22
- Verify hourly_rate = daily_rate / 8
- Verify SSS bracket matches salary range (E1-E6)
- Check database: `employee_payroll_info` record exists

---

### Scenario 2: Update Salary (Creates History)

**Objective:** Verify salary history tracking and old record deactivation

**Steps:**
1. From **Payroll → Employee Payroll → Info**, select the employee from Scenario 1
2. Click **Edit** on the active payroll info record
3. Change Basic Salary: `₱30,000` → `₱35,000`
4. Click **Save**

**Expected Results:**
- ✅ New payroll info record created
- ✅ New record has:
  - Basic Salary: ₱35,000
  - Daily Rate: ₱1,590.91 (35,000 ÷ 22)
  - Hourly Rate: ₱198.86
  - is_active: true
  - end_date: NULL
- ✅ Old record (₱30,000) marked as inactive
- ✅ Old record has:
  - is_active: false
  - end_date: Today
- ✅ History shows both records

**Validation:**
- Check "Payroll History" tab shows 2 records
- Verify new rates calculated correctly
- Verify old record has end_date set

---

### Scenario 3: Assign Multiple Allowances

**Objective:** Verify allowance assignment and management

**Steps:**
1. Navigate to **Payroll → Employee Payroll → Allowances & Deductions**
2. Select the employee from Scenario 1
3. Click **Add Allowance** 
4. Add Rice Allowance:
   - Type: `Rice`
   - Amount: `₱2,000`
   - Click **Save**
5. Click **Add Allowance** again
6. Add COLA:
   - Type: `Cola`
   - Amount: `₱1,000`
   - Click **Save**
7. Click **Add Allowance** again
8. Add Transportation:
   - Type: `Transportation`
   - Amount: `₱1,500`
   - Click **Save**

**Expected Results:**
- ✅ All 3 allowances appear in list
- ✅ Total Allowances: ₱4,500
- ✅ Each allowance shows:
  - Type (Rice, COLA, Transportation)
  - Amount
  - Effective Date: Today
  - Status: Active
- ✅ Table shows all allowances with columns: Type, Amount, Effective Date, End Date, Status, Actions

**Validation:**
- Verify sum of allowances = ₱4,500
- Check database: 3 `employee_allowance` records exist
- Verify each has `is_active = true`

---

### Scenario 4: Update Allowance Amount

**Objective:** Verify allowance replacement (deactivate old, activate new)

**Steps:**
1. From allowances list, find Rice allowance (₱2,000)
2. Click **Edit** or **Update Amount**
3. Change amount: `₱2,000` → `₱2,500`
4. Click **Save**

**Expected Results:**
- ✅ Old Rice allowance (₱2,000) marked as inactive
- ✅ New Rice allowance (₱2,500) created and marked as active
- ✅ List shows only 1 active Rice allowance (₱2,500)
- ✅ Total allowances now: ₱5,000 (2,500 + 1,000 + 1,500)
- ✅ History shows old and new records

**Validation:**
- Verify old allowance has `is_active = false`
- Verify new allowance has `is_active = true`
- Verify only 1 Rice allowance is active

---

### Scenario 5: Assign Deductions

**Objective:** Verify deduction creation and effective date handling

**Steps:**
1. From **Allowances & Deductions** page, go to **Deductions** tab
2. Click **Add Deduction**
3. Add Insurance Deduction:
   - Type: `Insurance`
   - Amount: `₱500`
   - Effective Date: `Today`
   - Click **Save**
4. Click **Add Deduction** again
5. Add Union Dues:
   - Type: `Union Dues`
   - Amount: `₱250`
   - Effective Date: `Today`
   - Click **Save**

**Expected Results:**
- ✅ Both deductions appear in list
- ✅ Total Deductions: ₱750
- ✅ Each shows:
  - Type
  - Amount
  - Effective Date: Today
  - Is Active: Yes
- ✅ Deductions table displays all columns

**Validation:**
- Sum of deductions = ₱750
- Check database: 2 `employee_deduction` records with `is_active = true`
- Verify `effective_date` is set (NOT NULL)

---

### Scenario 6: Create Loans

**Objective:** Verify loan creation and deduction scheduling

**Steps:**
1. Navigate to **Payroll → Employee Payroll → Loans**
2. Select the same employee
3. Click **Create Loan**
4. Create SSS Loan:
   - Loan Type: `SSS Loan`
   - Amount: `₱20,000`
   - Number of Months: `24`
   - Start Date: `Today`
   - Click **Save**

**Expected Results:**
- ✅ SSS Loan created with:
  - Loan Type: SSS Loan
  - Amount: ₱20,000
  - Monthly Payment: ₱833.33 (20,000 ÷ 24)
  - Status: Active
  - Expected End Date: 24 months from today
- ✅ Loan deduction auto-created in Deductions:
  - Type: `Loan`
  - Amount: ₱833.33
  - Is Active: Yes

**Expected Results (Alternative):**
- If loan creation fails due to eligibility: ✅ Error message displayed
  - "Employee does not meet SSS loan eligibility requirements"
  - This is acceptable (depends on service implementation)

**Validation:**
- Check database: `employee_loan` record exists
- Verify monthly payment = amount ÷ months
- Verify `status = 'active'`
- Check loan deduction created in `employee_deduction`

---

### Scenario 7: Complete Payroll Setup (End-to-End)

**Objective:** Verify all components work together

**Setup:** Use employee from previous scenarios

**Components Verification:**

✅ **Payroll Info:**
- Navigate to Employee Payroll Info
- Verify:
  - Basic Salary: ₱35,000 (latest)
  - Daily Rate: ₱1,590.91
  - Hourly Rate: ₱198.86
  - Tax Status: Single
  - Government Numbers: All present and valid

✅ **Allowances:**
- Rice: ₱2,500 (active)
- COLA: ₱1,000 (active)
- Transportation: ₱1,500 (active)
- **Total: ₱5,000**

✅ **Deductions:**
- Insurance: ₱500 (active)
- Union Dues: ₱250 (active)
- Loan (SSS): ₱833.33 (active, if created)
- **Total: ₱1,583.33**

✅ **Summary:**
- Gross Pay (Salary + Allowances): ₱35,000 + ₱5,000 = **₱40,000**
- Total Deductions: **₱1,583.33**
- Est. Net Pay: **₱38,416.67** (before tax and other govt. deductions)

**Validation:**
- All components display correctly
- Calculations are accurate
- Data persists in database
- No errors in application logs

---

### Scenario 8: Data Validation Testing

**Objective:** Verify input validation and error handling

**Test Case 1: Invalid Government Numbers**

1. Attempt to create payroll info with invalid SSS number
2. SSS Number: `123456789` (wrong format)
3. Expected: ❌ Error message: "Invalid SSS number format. Expected: 01-1234567-8"

**Test Case 2: Invalid Government Numbers (PhilHealth)**

1. PhilHealth: `00-100000000-0` (wrong format)
2. Expected: ❌ Error message: "Invalid PhilHealth number format. Expected: 12 digits"

**Test Case 3: Invalid Government Numbers (Pag-IBIG)**

1. Pag-IBIG: `120456789012` (missing dashes)
2. Expected: ❌ Error message: "Invalid Pag-IBIG number format. Expected: 1234-5678-9012"

**Test Case 4: Zero Salary**

1. Basic Salary: `0`
2. Expected: ❌ Error message: "Basic salary must be greater than 0"

**Test Case 5: Negative Allowance**

1. Allowance Amount: `-1000`
2. Expected: ❌ Error message: "Amount must be positive"

**Test Case 6: Missing Required Fields**

1. Attempt to save without Tax Status
2. Expected: ❌ Error message: "Tax status is required"

**Validation:**
- All validation messages display correctly
- Fields are highlighted with errors
- Form prevents submission with invalid data

---

### Scenario 9: Effective Date & Deactivation Workflows

**Objective:** Verify date handling and deactivation logic

**Test Case 1: Future-Dated Allowance**

1. Add allowance with Effective Date = 30 days in future
2. Expected:
   - ✅ Allowance saved
   - ✅ is_active = true (but not yet effective)
   - Note: Payroll calculation should ignore until effective date

**Test Case 2: Allowance with End Date**

1. Add temporary allowance:
   - Amount: ₱5,000
   - Effective Date: Today
   - End Date: 3 months from today
2. Expected:
   - ✅ Saved successfully
   - ✅ Shows in active list
   - ✅ End date visible in details
   - Note: After end date, should be auto-deactivated in payroll calculations

**Test Case 3: Deactivate Deduction**

1. From deductions list, find Insurance deduction
2. Click **Deactivate** or toggle is_active switch
3. Expected:
   - ✅ Deduction marked as inactive
   - ✅ Removed from active list
   - ✅ Still visible in history/audit trail

**Validation:**
- Check database for effective_date and end_date values
- Verify is_active flag updates correctly

---

### Scenario 10: Payment Method Variations

**Objective:** Verify support for different payment methods

**Test Cases:**

1. **Bank Transfer:**
   - Payment Method: Bank Transfer
   - Bank Name: BPI
   - Account Number: 1234567890
   - Expected: ✅ Saved, bank details visible

2. **Cash Payment:**
   - Payment Method: Cash
   - Expected: ✅ Saved, no bank details required

3. **Check Payment:**
   - Payment Method: Check
   - Expected: ✅ Saved, relevant fields populated

**Validation:**
- All methods save successfully
- Conditional fields display based on payment method
- Data persists correctly

---

### Scenario 11: Tax Status Variations

**Objective:** Verify all tax status options

**Test Cases:**

1. Single (S)
2. Married with 1 exemption (S1)
3. Married with 2 exemptions (ME2)
4. Married with 4 exemptions (ME4)
5. Zero/Exempt (Z)

**Steps for each:**
1. Create/update payroll info with tax status
2. Verify saved correctly
3. Check display in payroll info

**Validation:**
- All statuses save and display correctly
- Tax status used in payroll calculations

---

### Scenario 12: Salary Type Conversions

**Objective:** Verify rate calculations for different salary types

**Test Cases:**

**1. Monthly → Daily:**
- Create Monthly: Basic Salary = ₱22,000
- Daily Rate = 22,000 ÷ 22 = **₱1,000**
- Hourly Rate = 1,000 ÷ 8 = **₱125**

**2. Convert to Daily:**
- Update to Daily Salary Type
- Enter Daily Rate = ₱1,000
- Expected Basic Salary = 1,000 × 22 = **₱22,000**
- Hourly Rate = **₱125**

**3. Verify Rates:**
- Check all rates calculated correctly
- Verify consistency across conversions

**Validation:**
- Daily Rate = Monthly / 22
- Hourly Rate = Daily / 8
- Conversions maintain rate consistency

---

### Scenario 13: Multi-Employee Workflow

**Objective:** Verify system handles multiple employees independently

**Steps:**
1. Create payroll info for Employee A
   - Salary: ₱30,000
   - Allowances: ₱3,000
2. Create payroll info for Employee B
   - Salary: ₱25,000
   - Allowances: ₱2,000
3. Verify independent data

**Expected:**
- ✅ Each employee has separate records
- ✅ No data leakage between employees
- ✅ Updates to one don't affect other
- ✅ Reports show correct data per employee

**Validation:**
- Database shows separate records for each
- UI displays correct employee's data
- Calculations are per-employee

---

### Scenario 14: Error Recovery & Retry

**Objective:** Verify system handles errors gracefully

**Test Cases:**

1. **Network Timeout Simulation:**
   - Start saving payroll info
   - Disconnect network mid-save
   - Expected: Error message, no partial saves

2. **Duplicate Entry Prevention:**
   - Attempt to create same allowance twice
   - Expected: Either created as new (with old deactivated) or error

3. **Concurrent Edits:**
   - Open payroll info in 2 browser windows
   - Edit in both
   - Expected: Second edit either fails or overwrites (based on design)

**Validation:**
- Errors displayed clearly
- Data consistency maintained
- No orphaned records

---

### Scenario 15: Data Consistency Check

**Objective:** Verify referential integrity and consistency

**Database Checks:**

1. **Orphaned Records:**
   - ❌ No `employee_allowance` without matching `employee`
   - ❌ No `employee_deduction` without matching `employee`
   - ❌ No `employee_loan` without matching `employee`

2. **Active Record Count:**
   - Each employee should have 0 or 1 active `employee_payroll_info`
   - Multiple inactive records allowed (history)

3. **Required Fields:**
   - `employee_payroll_info.payment_method` NOT NULL
   - `employee_payroll_info.tax_status` NOT NULL
   - `employee_payroll_info.basic_salary` > 0
   - `employee_deduction.effective_date` NOT NULL

4. **Calculated Fields:**
   - `daily_rate` = `basic_salary` / 22
   - `hourly_rate` = `daily_rate` / 8
   - `sss_bracket` matches salary range

**Validation:**
- Run database integrity checks
- Verify no orphaned records
- Validate all calculated fields
- Check constraints enforced

---

## Checklist for Manual Testing Completion

### Functionality Testing
- [ ] Payroll info creation (Scenario 1)
- [ ] Salary history & updates (Scenario 2)
- [ ] Multiple allowances (Scenario 3)
- [ ] Allowance updates (Scenario 4)
- [ ] Deduction assignment (Scenario 5)
- [ ] Loan creation (Scenario 6)
- [ ] Complete payroll setup (Scenario 7)
- [ ] Data validation (Scenario 8)
- [ ] Effective dates & deactivation (Scenario 9)
- [ ] Payment methods (Scenario 10)
- [ ] Tax status variations (Scenario 11)
- [ ] Salary type conversions (Scenario 12)
- [ ] Multi-employee workflow (Scenario 13)

### Error & Recovery Testing
- [ ] Error messages display correctly (Scenario 14)
- [ ] Data consistency maintained (Scenario 15)
- [ ] No orphaned records
- [ ] Validation prevents invalid data

### Documentation
- [ ] All scenarios tested
- [ ] Issues logged (if any)
- [ ] Pass/Fail documented
- [ ] Screenshots captured (if issues)

---

## Known Limitations & Expected Failures

1. **PayrollCalculationService Integration:**
   - Not yet integrated (requires PayrollPeriod model)
   - Payroll calculation/finalization not available
   - Will be added in future phase

2. **Loan Eligibility:**
   - Loan creation may fail based on service implementation
   - This is expected behavior if not yet implemented

3. **Advanced Features:**
   - Batch operations
   - Import/Export
   - Advanced filtering
   - These are out of scope for Phase 6

---

## Testing Completion Criteria

✅ **Phase 6.3 Complete When:**
- All 15 scenarios executed
- No critical errors found
- Data consistency verified
- All validations working
- UI displays correctly
- Error messages clear and helpful
- Database integrity maintained

---

## Test Results Summary

**Date Tested:** [Date]
**Tester:** [Name]
**Duration:** [Hours:Minutes]
**Total Scenarios:** 15
**Passed:** [Count]
**Failed:** [Count]
**Issues Found:** [Count]

**Issues Log:**
1. [Issue 1]: [Description]
2. [Issue 2]: [Description]

**Overall Status:** ✅ PASSED / ❌ FAILED / ⚠️ PASSED WITH ISSUES

---

## Sign-Off

**Tester Name:** ________________
**Date:** ________________
**Signature:** ________________

**QA Manager Name:** ________________
**Date:** ________________
**Signature:** ________________
