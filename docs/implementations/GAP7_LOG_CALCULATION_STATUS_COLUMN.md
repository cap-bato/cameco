# Gap 7 — `LogPayrollCalculation` Uses Wrong Column Name (`status` vs `calculation_status`)

**Status:** ⚠️ Bug — `$event->calculation->status` silently returns `null`; wrong value logged  
**Priority:** 🟡 Low — log entries show null status, but calculation still proceeds  

---

## The Problem

`app/Listeners/Payroll/LogPayrollCalculation.php` in `handleEmployeeCalculated()`:

```php
$this->payrollLogger->log(
    $event->payrollPeriod,
    'employee_calculated',
    "Employee #{$event->employee->id} calculated: {$event->calculation->status}",  // ← WRONG
    $event->calculation->toArray()
);
```

The column on `EmployeePayrollCalculation` is **`calculation_status`**, not `status`.

`$event->calculation->status` → `null` (Eloquent returns null for undefined attributes)

**Result:** Log entries read:  
`"Employee #42 calculated: "` (empty status)  
instead of:  
`"Employee #42 calculated: completed"`

---

## Phase 1 — Apply the Fix

### Task 1.1 — Fix Column Reference ✅ COMPLETE (2026-03-06)

**File:** `app/Listeners/Payroll/LogPayrollCalculation.php`

**What was done:**
- Line 44: Changed `$event->calculation->status` → `$event->calculation->calculation_status`
- Verified: PHP syntax check passed, no parse errors
- Search: Confirmed no other instances of `calculation->status` in the codebase

**Result:** `handleEmployeeCalculated()` now logs the correct `calculation_status` column value instead of silently returning `null`

---

### Task 1.2 — Scan for Other Incorrect Column References

Run a project-wide search for any other places using `.status` on a  
`EmployeePayrollCalculation` model/variable:

```bash
grep -rn "calculation->status\b" app/
grep -rn "->status" app/Listeners/Payroll/
```

Fix any additional occurrences found.

---

## Phase 2 — Verify `EmployeePayrollCalculation` Has No `status` Accessor

### Task 2.1 — Check Model for `getStatusAttribute()` ✅ COMPLETE (2026-03-06)

**File:** `app/Models/EmployeePayrollCalculation.php`

**Verification Results:**

**1. No `$appends` Array**
- Searched entire model file — no `protected $appends` declaration
- No attribute aliasing configured

**2. No `getStatusAttribute()` Accessor**
- No accessor method for `status`
- No manual attribute mapping in place

**3. No `setStatusAttribute()` Mutator**
- No custom mutator for `status`
- All writes use `calculation_status` directly in `markAsCalculated()`, `markAsException()`, `lock()`, etc.

**4. Consistent Use of `calculation_status` Throughout**
- ✅ Scopes (lines 233, 238): `where('calculation_status', ...)`
- ✅ Helper methods (line 278): `$this->calculation_status === 'locked'`
- ✅ Helper methods (line 283): `in_array($this->calculation_status, [...])`
- ✅ Update methods (lines 331-366): All use `'calculation_status' => '...'`

**Conclusion:**
The model intentionally and exclusively uses `calculation_status` as the canonical column name. There is no conflicting `status` accessor or alias. The Phase 1 Task 1.1 fix (changing `.status` → `.calculation_status` in LogPayrollCalculation listener) is correct and aligns with the model's design.

---

## Summary of Changes

| File | Action |
|---|---|
| `app/Listeners/Payroll/LogPayrollCalculation.php` | **MODIFY** — change `.status` → `.calculation_status` |
| `app/Models/EmployeePayrollCalculation.php` | **VERIFY** — ensure no conflicting `status` accessor |
