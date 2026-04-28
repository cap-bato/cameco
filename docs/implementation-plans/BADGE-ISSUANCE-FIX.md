# Badge Issuance Fix — Implementation Plan

**Page:** `/hr/timekeeping/badges`
**Date:** 2026-03-19

---

## Root Cause

Both badge issuance entry points use `setTimeout()` mock functions that **never make a network request**. The backend controller `RfidBadgeController::store()` is fully implemented and ready to receive data — the frontend simply never calls it.

Additionally, a secondary bug causes a validation failure even if the request were sent: the frontend field is named `issue_notes` but the backend validates a field named `notes`.

There are **3 bugs total**.

---

## Bug Map

| # | File | Line(s) | Description |
|---|------|---------|-------------|
| 1 | `Index.tsx` | 157–167 | `handleIssuanceSubmit` — mock `setTimeout`, no HTTP request |
| 2 | `Create.tsx` | 61–98 | `handleSubmit` — mock `setTimeout`, no HTTP request |
| 3 | `badge-issuance-modal.tsx` | 61 | `issue_notes` field → backend expects `notes` |

---

## Bug 1 — `Index.tsx:157` — `handleIssuanceSubmit` is a mock

This is the handler that fires when a user clicks "Issue Badge" next to an employee in the **Employees Without Badges** widget on the main `/hr/timekeeping/badges` page.

### Current code (broken)

```ts
// Index.tsx lines 157–167
const handleIssuanceSubmit = useCallback((formData: BadgeFormData) => {
    console.log('Badge issuance submitted:', formData);
    // Simulate issuance submission
    const employeeName = selectedEmployeeForIssuance?.name || 'Unknown Employee';
    setIsIssuanceModalOpen(false);
    setReplacementResult({
        success: true,
        message: `Badge ${formData.card_uid} has been successfully issued to ${employeeName}.`,
    });
    setTimeout(() => setReplacementResult(null), 5000);
}, [selectedEmployeeForIssuance]);
```

No network request is made. The success message is fabricated.

### Fix

Replace the mock with `router.post()`. Add `router` to the existing import on line 1 (it is already imported: `import { Head, Link, router } from '@inertiajs/react'`).

```ts
// Index.tsx — replace lines 157–167 with:
const handleIssuanceSubmit = useCallback((formData: BadgeFormData) => {
    router.post(
        route('hr.timekeeping.badges.store'),
        {
            employee_id:               formData.employee_id,
            card_uid:                  formData.card_uid,
            card_type:                 formData.card_type,
            expires_at:                formData.expires_at ?? null,
            notes:                     formData.issue_notes ?? null,     // ← map issue_notes → notes
            acknowledgement_signature: formData.acknowledgement_signature ?? null,
            replace_existing:          selectedEmployeeForIssuance?.badge?.is_active ? true : false,
        },
        {
            onSuccess: () => {
                setIsIssuanceModalOpen(false);
                setSelectedEmployeeForIssuance(null);
                // Success flash is handled by Laravel's session flash → Inertia shared props
            },
            onError: (errors) => {
                // Keep modal open so the user can see validation errors
                console.error('Badge issuance failed:', errors);
            },
        }
    );
}, [selectedEmployeeForIssuance]);
```

**Why `replace_existing`:** The backend checks for an existing active badge and returns a 422 if `replace_existing` is not `true`. The modal already shows a warning and requires the user to click "Replace Badge" before the form is enabled — so if `selectedEmployeeForIssuance` has an active badge and the user is proceeding, they have already acknowledged the replacement. Pass `true` in that case.

---

## Bug 2 — `Create.tsx:61` — `handleSubmit` is a mock

This is the handler on the `/hr/timekeeping/badges/create` page.

### Current code (broken)

```ts
// Create.tsx lines 61–98
const handleSubmit = async (formData: BadgeFormData) => {
    setIsSubmitting(true);

    // Simulate API call (Phase 1 - mock data)
    setTimeout(() => {
        try {
            // Mock success response
            const selectedEmployee = employees.find((emp) => emp.id === formData.employee_id);
            setSubmitResult({
                success: true,
                message: `Badge successfully issued to ${selectedEmployee?.name}`,
                badgeData: { ... },
            });
            setIsSubmitting(false);
            setIsModalOpen(false);
            setTimeout(() => { setSubmitResult(null); }, 5000);
        } catch { ... }
    }, 1000);
};
```

No network request is made. `async` is declared but no `await` is ever used.

### Fix

Add `router` to the import on line 2 (currently only `Head` and `Link` are imported):

```ts
// Create.tsx line 2 — update import:
import { Head, Link, router } from '@inertiajs/react';
```

Replace the entire `handleSubmit` function:

```ts
// Create.tsx — replace lines 61–98 with:
const handleSubmit = (formData: BadgeFormData) => {
    setIsSubmitting(true);

    const selectedEmployee = employees.find((emp) => emp.id === formData.employee_id);

    router.post(
        route('hr.timekeeping.badges.store'),
        {
            employee_id:               formData.employee_id,
            card_uid:                  formData.card_uid,
            card_type:                 formData.card_type,
            expires_at:                formData.expires_at ?? null,
            notes:                     formData.issue_notes ?? null,     // ← map issue_notes → notes
            acknowledgement_signature: formData.acknowledgement_signature ?? null,
            replace_existing:          selectedEmployee?.badge?.is_active ? true : false,
        },
        {
            onSuccess: () => {
                setIsSubmitting(false);
                setIsModalOpen(false);
                // Backend redirects to badges.index with a flash success message
            },
            onError: (errors) => {
                setIsSubmitting(false);
                setSubmitResult({
                    success: false,
                    message: errors.error
                          ?? errors.employee_id
                          ?? errors.card_uid
                          ?? 'Failed to issue badge. Please try again.',
                });
            },
        }
    );
};
```

Remove the `BadgeSubmitResult` interface (lines 26–33) and the `submitResult.badgeData` display block in the JSX (lines 139–154) — the backend redirects back to the index page on success, so there's no need to show a success card on this page. The flash message is shown on the index page instead via the existing `replacementResult` / shared flash mechanism.

---

## Bug 3 — `badge-issuance-modal.tsx:61` — `issue_notes` field name mismatch

### Current code

```ts
// badge-issuance-modal.tsx line 61
export interface BadgeFormData {
    employee_id: string;
    card_uid: string;
    card_type: 'mifare' | 'desfire' | 'em4100';
    expires_at?: string;
    issue_notes?: string;          // ← frontend name
    employee_acknowledged: boolean;
    badge_tested: boolean;
    acknowledgement_signature?: string;
}
```

### Backend expects

```php
// RfidBadgeController::store() line 275
'notes' => 'nullable|string|max:1000',   // ← backend name
```

The `issue_notes` value is never received by the backend because the key doesn't match. Bug 1 and Bug 2 fixes already map `issue_notes → notes` at the call site. No change is needed to the modal's interface or internal state — the mapping happens in the submit handlers.

---

## Secondary Bug — `handleReplacementSubmit` is also a mock

While the user reported the issuance flow, the badge **replacement** flow on the same page has the identical problem:

```ts
// Index.tsx lines 140–150
const handleReplacementSubmit = useCallback((data: { old_badge_id: string; new_card_uid: string; reason: string }) => {
    // Simulate replacement submission
    console.log('Badge replacement submitted:', data);
    setReplacementResult({ success: true, message: `...` });
    setIsReplacementModalOpen(false);
    // In Phase 2, this will submit to the backend
    setTimeout(() => setReplacementResult(null), 5000);
}, []);
```

Fix this at the same time to avoid a duplicate report:

```ts
// Index.tsx — replace lines 140–150 with:
const handleReplacementSubmit = useCallback((data: { old_badge_id: string; new_card_uid: string; reason: string }) => {
    router.post(
        route('hr.timekeeping.badges.replace', { badge: data.old_badge_id }),
        {
            new_card_uid: data.new_card_uid,
            reason:       data.reason,
        },
        {
            onSuccess: () => {
                setIsReplacementModalOpen(false);
                setSelectedBadgeForReplacement(null);
            },
            onError: (errors) => {
                console.error('Badge replacement failed:', errors);
            },
        }
    );
}, []);
```

> **Note:** Verify the replacement route name (`hr.timekeeping.badges.replace`) exists in `routes/hr.php`. If the route doesn't exist, it needs to be added pointing to `RfidBadgeController::replace()`.

---

## Validation Alignment Check

Before sending, confirm the frontend form data maps correctly to backend validation rules:

| Backend field | Backend rule | Frontend source | Notes |
|---|---|---|---|
| `employee_id` | `required\|integer\|exists:employees,id` | `formData.employee_id` (string) | PHP coerces string `"42"` to int `42` — OK |
| `card_uid` | `required\|string\|regex:/^[0-9A-Fa-f:-]+$/\|unique` | `formData.card_uid` (uppercased) | Uppercase is within `[A-Fa-f]` — OK |
| `card_type` | `required\|in:mifare,desfire,em4100` | `formData.card_type` | Values match — OK |
| `expires_at` | `nullable\|date\|after:today` | `formData.expires_at` (yyyy-MM-dd) | Format matches — OK |
| `notes` | `nullable\|string\|max:1000` | `formData.issue_notes` mapped → `notes` | Requires Bug 3 mapping in handlers |
| `acknowledgement_signature` | `nullable\|string\|max:1000` | `formData.acknowledgement_signature` | OK |
| `replace_existing` | `nullable\|boolean` | Derived from employee's active badge state | Set to `true` when replacing |

---

## Files to Edit

| File | Change |
|---|---|
| `resources/js/pages/HR/Timekeeping/Badges/Index.tsx` | Replace `handleIssuanceSubmit` (lines 157–167) and `handleReplacementSubmit` (lines 140–150) |
| `resources/js/pages/HR/Timekeeping/Badges/Create.tsx` | Add `router` to import; replace `handleSubmit` (lines 61–98); clean up unused `BadgeSubmitResult` type and success card JSX |

The modal component (`badge-issuance-modal.tsx`) and the backend controller need **no changes**.

---

## Acceptance Criteria

- [ ] Clicking "Issue Badge" next to Ricardo on `/hr/timekeeping/badges` opens the modal
- [ ] On submit, a POST request to `/hr/timekeeping/badges` appears in the DevTools Network tab
- [ ] The response is a 302 redirect to `/hr/timekeeping/badges` with a flash success message
- [ ] A new row appears in the `rfid_card_mappings` table with `employee_id = Ricardo's ID`
- [ ] A new row appears in the `badge_issue_logs` table with `action_type = 'issued'`
- [ ] Submitting via the `/hr/timekeeping/badges/create` page behaves identically
- [ ] If the card UID is already taken, a 422 error is returned and the modal stays open showing the error
- [ ] `php artisan test` passes
