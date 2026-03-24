# Payroll — Single Officer Workflow Simplification

**Date:** 2026-03-07  
**Scope:** Remove multi-person approval workflow from the payroll module.  
The company has one payroll officer who calculates, reviews, and approves payroll in-office.  
The existing 3-step state machine (`calculated → under_review → pending_approval → approved`) is replaced with a single step (`calculated → approved`).

---

## Current State (What to Remove)

### Status State Machine (❌ Before)
```
calculated
  ↓  "Approve"  (click 1)
under_review        ← Payroll Officer reviewing
  ↓  "Approve"  (click 2)
pending_approval    ← Submitted to HR Manager
  ↓  "Approve"  (click 3)
approved            ← Finance Director final auth
  ↓  "Lock"
finalized
```

### Review Page UI
- `<ApprovalWorkflow>` component with 3 step tiles:
  - Step 1 — Payroll Officer
  - Step 2 — Payroll Manager
  - Step 3 — Finance Director
- Approve button advances through each step individually

---

## Target State (What to Build)

### Status State Machine (✅ After)
```
calculated
  ↓  "Approve Payroll"  (single click)
approved
  ↓  "Lock & Finalize"
finalized
```

### Review Page UI
- Remove the multi-step `<ApprovalWorkflow>` component
- Replace with a single **"Approved by Payroll Officer"** status banner (visible when `status === 'approved'`)
- Single "Approve Payroll" button visible when `status === 'calculated'`
- "Lock & Finalize" button visible when `status === 'approved'`
- Keep "Generate Payslips" and "Send Back for Recalculation" (useful for error recovery)
- Keep audit trail via `PayrollApprovalHistory` (1 record per approve/lock action)

---

## What Does NOT Change

| Component | Reason |
|---|---|
| `PayrollCalculationService` | Not involved in approval flow |
| `CalculatePayrollJob` / `CalculateEmployeePayrollJob` / `FinalizePayrollJob` | Not involved |
| `DailyAttendanceSummary` / `is_finalized` logic | Upstream of payroll, unrelated |
| `PayrollApprovalHistory` model & table | Keep for audit trail |
| `PayrollPeriod` DB enum values | `under_review` / `pending_approval` stay in DB (unused) — no migration needed |
| `payment` / `completed` / `finalized` statuses | All downstream statuses untouched |
| `ApprovalWorkflows` in Admin panel | That's the Leave/Overtime approval system — separate |

---

## Phase 1 — Backend: Simplify `PayrollReviewController`

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollReviewController.php`

### Task 1.1 — Simplify `REVIEWABLE_STATUSES`

```php
// BEFORE
private const REVIEWABLE_STATUSES = ['calculated', 'under_review', 'pending_approval', 'approved'];

// AFTER
private const REVIEWABLE_STATUSES = ['calculated', 'approved'];
```

### Task 1.2 — Simplify `approve()` method

```php
// BEFORE — 3-step state machine
$nextStatus = match ($prevStatus) {
    'calculated'       => 'under_review',
    'under_review'     => 'pending_approval',
    'pending_approval' => 'approved',
    default            => 'approved',
};

// AFTER — single step
$nextStatus = 'approved'; // Always goes directly to approved
```

Also update the WHERE clauses inside `index()` that reference these statuses:

```php
// Period listing for available_periods dropdown — AFTER
$availablePeriods = PayrollPeriod::whereIn('status', ['calculated', 'approved', 'finalized', 'completed'])
    ...

// previousPeriod lookup — AFTER
->whereIn('status', ['calculated', 'approved', 'finalized', 'completed'])
```

### Task 1.3 — Simplify `buildApprovalWorkflow()`

```php
// AFTER
return [
    'id'                => $period->id,
    'payroll_period_id' => $period->id,
    'current_step'      => 1,
    'total_steps'       => 1,
    'status'            => in_array($status, ['approved', 'finalized', 'completed']) ? 'approved' : 'in_progress',
    'can_approve'       => $status === 'calculated',
    'can_reject'        => $status === 'approved',   // "Send back for recalculation" if error found after approving
    'approver_role'     => 'Payroll Officer',
    'steps'             => $this->buildWorkflowSteps($period, $history),
    'rejection_reason'  => $rejectionEntry?->rejection_reason ?? $period->rejection_reason,
    'rejection_date'    => $rejectionEntry?->created_at?->format('Y-m-d H:i:s'),
    'rejection_by'      => $rejectionEntry?->user_name,
];
```

### Task 1.4 — Simplify `buildWorkflowSteps()`

Return a single step instead of 3:

```php
private function buildWorkflowSteps(PayrollPeriod $period, $history): array
{
    $status    = $period->status;
    $isDone    = in_array($status, ['approved', 'finalized', 'completed']);
    $approvedByName = $period->approvedBy?->full_name ?? $period->approvedBy?->name;
    $approvalEntry  = $history->where('action', 'approved')->sortByDesc('created_at')->first();

    return [
        [
            'step_number'  => 1,
            'role'         => 'Payroll Officer',
            'status'       => $isDone ? 'approved' : 'pending',
            'status_label' => $isDone ? 'Approved' : 'Pending',
            'status_color' => $isDone ? 'green' : 'yellow',
            'description'  => 'Review calculations and approve payroll for processing',
            'approved_by'  => $isDone ? ($approvalEntry?->user_name ?? $approvedByName ?? 'Officer') : null,
            'approved_at'  => $isDone ? $period->approved_at?->format('Y-m-d H:i:s') : null,
            'comments'     => $approvalEntry?->comments ?? null,
        ],
    ];
}
```

Also update `emptyWorkflowSteps()` to return a single step:

```php
private function emptyWorkflowSteps(): array
{
    return [[
        'step_number'  => 1,
        'role'         => 'Payroll Officer',
        'status'       => 'pending',
        'status_label' => 'Pending',
        'status_color' => 'gray',
        'description'  => 'Review calculations and approve payroll for processing',
        'approved_by'  => null,
        'approved_at'  => null,
        'comments'     => null,
    ]];
}
```

### Task 1.5 — Simplify `mapStatusToFrontend()`

```php
// BEFORE
return match ($status) {
    'calculated'       => 'calculated',
    'under_review',
    'pending_approval' => 'reviewing',
    'approved'         => 'approved',
    default            => 'calculated',
};

// AFTER
return match ($status) {
    'calculated' => 'calculated',
    'approved'   => 'approved',
    default      => 'calculated',
};
```

### Task 1.6 — Handle existing `under_review`/`pending_approval` periods (data fix)

If there are any periods stuck in these states from previous usage, reset them:

```bash
php artisan tinker --execute="
\$fixed = DB::table('payroll_periods')
    ->whereIn('status', ['under_review', 'pending_approval'])
    ->update(['status' => 'calculated']);
echo \"Fixed \$fixed periods stuck in old workflow states\n\";
"
```

---

## Phase 2 — Frontend: Simplify Review Page

**File:** `resources/js/pages/Payroll/PayrollProcessing/Review/Index.tsx`

### Task 2.1 — Remove `ApprovalWorkflow` component import and usage

```tsx
// Remove this import:
import { ApprovalWorkflow } from '@/components/payroll/approval-workflow';

// Remove this block from JSX (line ~311):
{/* Approval Workflow */}
<ApprovalWorkflow workflow={approval_workflow} onReject={handleReject} />
```

### Task 2.2 — Replace with a simple "Approved" status banner (when approved)

Add this in place of `<ApprovalWorkflow>`:

```tsx
{/* Officer Approval Status */}
{payroll_period.status === 'approved' && (
    <Card className="border-green-200 bg-green-50 p-4">
        <div className="flex items-center gap-3">
            <CheckCircle2 className="h-5 w-5 text-green-600 flex-shrink-0" />
            <div>
                <p className="font-medium text-green-800">Payroll Approved</p>
                <p className="text-sm text-green-700">
                    Approved by Payroll Officer. Ready to lock and release for payment.
                </p>
            </div>
        </div>
    </Card>
)}
```

### Task 2.3 — Simplify header action buttons

Replace the multi-condition button area with a clean single-officer layout:

```tsx
<div className="flex gap-2">
    {/* Approve — only when calculated */}
    {approval_workflow.can_approve && (
        <Button
            onClick={handleApprove}
            disabled={isSubmittingApproval}
            className="gap-2 bg-green-600 hover:bg-green-700"
        >
            <CheckCircle2 className="h-4 w-4" />
            {isSubmittingApproval ? 'Approving...' : 'Approve Payroll'}
        </Button>
    )}

    {/* Lock — only when approved */}
    {payroll_period.status === 'approved' && (
        <Button onClick={() => setShowLockDialog(true)} variant="outline" className="gap-2">
            <Lock className="h-4 w-4" />
            Lock & Finalize
        </Button>
    )}

    {/* Send back for recalculation — payroll officer found error after approving */}
    {approval_workflow.can_reject && (
        <Button
            onClick={() => {
                const reason = prompt('Reason for sending back for recalculation?');
                if (reason) handleReject(reason);
            }}
            variant="outline"
            className="gap-2 text-orange-600 border-orange-300 hover:bg-orange-50"
        >
            Send Back for Recalculation
        </Button>
    )}

    <Button onClick={handleDownloadPayslips} variant="outline" className="gap-2">
        <Download className="h-4 w-4" />
        Generate Payslips
    </Button>
</div>
```

> **Note:** The `prompt()` is a quick inline trigger. If you want a proper modal for the reason field, the existing reject modal (if any) can be reused.

---

## Phase 3 — TypeScript Types

**File:** `resources/js/types/payroll-review-types.ts`

### Task 3.1 — Remove `'reviewing'` from status union

```ts
// BEFORE
status: 'calculated' | 'reviewing' | 'approved';

// AFTER
status: 'calculated' | 'approved';
```

### Task 3.2 — Update `ApprovalWorkflow` type (optional cleanup)

The TypeScript interface already supports `total_steps: number` generically, so no structural change is needed — backend just returns `total_steps: 1` instead of `3`. No type error.

---

## Phase 4 — PayrollPeriod Model (Annotation Update Only)

**File:** `app/Models/PayrollPeriod.php`

The `scopeUnderReview()` and `isUnderReview()` methods check for the now-unused states. No code will call these for new periods, but leave them as-is for backward compatibility with any reporting code that might inspect old data.

Simply **add a comment** to the relevant scope:

```php
/**
 * @deprecated Workflow simplified to single officer. These states are no longer
 * used for new periods as of 2026-03-07. Kept for historic period queries only.
 */
public function scopeUnderReview($query)
{
    return $query->whereIn('status', ['under_review', 'pending_approval']);
}
```

---

## Phase 5 — Page Title / Breadcrumb (Polish)

**File:** `resources/js/pages/Payroll/PayrollProcessing/Review/Index.tsx`

```tsx
// BEFORE
{ title: 'Review & Approval', href: '/payroll/review' }

// AFTER
{ title: 'Payroll Review', href: '/payroll/review' }
```

Also update `<Head title="Payroll Review & Approval" />`:
```tsx
<Head title="Payroll Review" />
```

---

## Summary of Files Changed

| File | Change | Phase |
|---|---|---|
| `app/Http/Controllers/Payroll/PayrollProcessing/PayrollReviewController.php` | Simplify status machine, workflow steps, can_approve/can_reject | 1 |
| `resources/js/pages/Payroll/PayrollProcessing/Review/Index.tsx` | Remove ApprovalWorkflow component, simplify buttons | 2 |
| `resources/js/types/payroll-review-types.ts` | Remove `'reviewing'` from status union | 3 |
| `app/Models/PayrollPeriod.php` | Add deprecation comment on `scopeUnderReview` | 4 |

**No DB migrations required** — the `under_review` / `pending_approval` enum values stay in the DB (they just won't be set for new periods).

---

## Progress Tracker

- [x] Phase 1.1 — Simplify `REVIEWABLE_STATUSES`
- [x] Phase 1.2 — Simplify `approve()` state machine to single step
- [x] Phase 1.3 — Simplify `buildApprovalWorkflow()` return
- [ ] Phase 1.4 — Simplify `buildWorkflowSteps()` and `emptyWorkflowSteps()` to 1 step
- [ ] Phase 1.5 — Simplify `mapStatusToFrontend()`
- [ ] Phase 1.6 — Data fix: reset any stuck periods
- [x] Phase 2.1 — Remove `<ApprovalWorkflow>` import and component
- [x] Phase 2.2 — Add "Approved" status banner
- [x] Phase 2.3 — Simplify header action buttons
- [x] Phase 3.1 — Remove `'reviewing'` from TypeScript status union
- [x] Phase 3.2 — Update `ApprovalWorkflow` type (optional cleanup)
- [x] Phase 4 — Add deprecation comment on `PayrollPeriod::scopeUnderReview()`
- [x] Phase 5 — Update page/breadcrumb titles
