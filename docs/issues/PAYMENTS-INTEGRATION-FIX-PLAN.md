# Payments Module — Frontend/Backend Integration Fix Plan

**Purpose:** Actionable implementation guide to resolve all gaps identified in `PAYMENTS-FRONTEND-BACKEND-INTEGRATION-REPORT.md`  
**Created:** February 19, 2026  
**Author:** AI — based on gap analysis  
**Prerequisite:** Phase 1 (migrations) ✅ + Phase 2 (models) ✅ complete  

---

## 📦 Work Breakdown

| Fix Set | Category | Effort | Blocks |
|---|---|---|---|
| **Set A** | TypeScript type fixes (`payroll-pages.ts`) | ~2–3 hrs | Everything else |
| **Set B** | Controller stub fixes (remove broken validation, wire `payment_methods`) | ~1 hr | Phase 3 services |
| **Set C** | Frontend page route / filter fixes | ~30 min | Payslips page |
| **Set D** | Controller wiring (replace mock with real queries) | ~4–6 hrs | Phase 3 services |

**Do in order:** A → B → C → D

---

## SET A — TypeScript Interface Fixes

**File:** `resources/js/types/payroll-pages.ts`

---

### A-1. `BankPayrollFile` interface (line 1268)

**Status enum** — replace current enum with DB values:

```ts
// BEFORE (wrong)
status: 'generated' | 'uploaded' | 'processed' | 'confirmed' | 'failed';

// AFTER (matches bank_file_batches.status)
status: 'draft' | 'ready' | 'submitted' | 'processing' | 'completed' | 'partially_completed' | 'failed';
```

**Field renames** — update these fields:

```ts
// BEFORE (wrong)
generated_at: string;
uploaded_at: string | null;
uploaded_by: string | null;
confirmation_number: string | null;

// AFTER (matches DB columns)
// generated_at → REMOVE (use created_at which already exists)
submitted_at: string | null;           // was uploaded_at
submitted_by: string | null;           // was uploaded_by (joined user name)
bank_confirmation_number: string | null; // was confirmation_number
```

**New fields to add** (present in `bank_file_batches` but missing from TS):

```ts
// ADD these fields after bank_code:
transfer_type: 'instapay' | 'pesonet' | 'internal' | null;

// ADD these fields after file_hash:
is_validated: boolean;
validated_at: string | null;
validation_errors: Array<{ employee_id: number; message: string }> | null;

// ADD these fields after total_amount:
successful_count: number | null;
failed_count: number | null;
total_fees: number;
settlement_date: string | null;
settlement_reference: string | null;
```

**Status color** — update to cover all new statuses:

```ts
// BEFORE
status_color: 'gray' | 'blue' | 'green' | 'orange' | 'red';

// AFTER
status_color: 'gray' | 'blue' | 'yellow' | 'green' | 'orange' | 'red';
// draft=gray, ready=blue, submitted=yellow, processing=blue,
// completed=green, partially_completed=orange, failed=red
```

---

### A-2. `BankOption` interface (line 1304)

Restrict `id` and `name` to only the two seeded banks. The controller validation must match.

```ts
// BEFORE
export interface BankOption {
    id: string;       // was any string like "BPI", "PNB", "RCBC", "Unionbank"
    name: string;
    code: string;
    icon?: string;
    supported_formats: Array<'csv' | 'txt' | 'excel' | 'fixed_width'>;
}

// AFTER — no interface change needed, but document supported banks:
// Only 'BDO' and 'Metrobank' are seeded in payment_methods.
// The BankOption[] data returned by controller must only contain these two.
// Do NOT add a union type restriction here — keep it flexible for future banks.
// The restriction lives in controller validation (see Set B-1).
```

---

### A-3. `Payslip` interface (line 1360)

#### FK rename

```ts
// BEFORE
payroll_calculation_id: string | number;

// AFTER
payroll_payment_id: string | number;  // FK to payroll_payments.id
```

#### Earnings — replace flat columns with JSON structure

```ts
// REMOVE these flat columns:
basic_salary: number;
overtime_pay: number;
night_differential: number;
holiday_pay: number;
allowances: number;
other_earnings: number;
// gross_pay: keep — it is stored as a flat decimal in DB

// ADD in their place:
earnings_data: Record<string, number>; // {"basic_salary": 15000, "overtime": 1200, ...}
// gross_pay stays as-is
```

#### Deductions — replace flat columns with JSON structure

```ts
// REMOVE these flat columns:
sss_contribution: number;
philhealth_contribution: number;
pagibig_contribution: number;
withholding_tax: number;
loans: number;
other_deductions: number;
// total_deductions: keep — stored as flat decimal in DB

// ADD in their place:
deductions_data: Record<string, number>; // {"sss": 1125, "philhealth": 450, ...}
// total_deductions stays as-is
```

> **Frontend display note:** Components that render payslip detail can `Object.entries(earnings_data)` to show the breakdown table — this is more flexible than hard-coded columns.

#### YTD — split `ytd_deductions` into individual fields

```ts
// REMOVE:
ytd_deductions: number;

// ADD:
ytd_sss: number;
ytd_philhealth: number;
ytd_pagibig: number;
ytd_tax: number;
// ytd_gross and ytd_net stay as-is
```

#### File fields — strip `pdf_` prefix

```ts
// BEFORE
pdf_file_path: string | null;
pdf_file_size: number | null;
pdf_hash: string | null;

// AFTER (matches DB columns)
file_path: string | null;
file_size: number | null;
file_hash: string | null;
```

#### Distribution tracking — remove unsupported fields, keep only what DB stores

```ts
// REMOVE these (not in payslips table):
email_sent: boolean;
email_sent_at: string | null;
email_address: string | null;
downloaded_by_employee: boolean;
downloaded_at: string | null;
printed: boolean;
printed_at: string | null;
printed_by: string | null;
acknowledged_by_employee: boolean;
acknowledged_at: string | null;

// KEEP / ADD these (actually in DB):
distribution_method: 'email' | 'portal' | 'print' | 'sms'; // fix 'printed' → 'print', add 'sms'
distributed_at: string | null;        // when distributed
is_viewed: boolean;                   // employee viewed via portal
viewed_at: string | null;
```

#### Status enum — align with DB

```ts
// BEFORE
status: 'pending' | 'generated' | 'sent' | 'acknowledged' | 'failed';

// AFTER (matches payslips.status enum in DB)
status: 'draft' | 'generated' | 'distributed' | 'acknowledged';
```

#### Add missing fields that ARE in DB

```ts
// ADD (these exist in payslips table but were missing from TS):
payslip_number: string;          // unique, e.g. "PS-2025-10-00123"
signature_hash: string | null;  // digital signature
qr_code_data: string | null;    // QR code payload

// ADD employee identification fields (used for DOLE-compliant display):
sss_number: string | null;
philhealth_number: string | null;
pagibig_number: string | null;
tin: string | null;
```

---

### A-4. `PayslipsSummary` interface (line ~1535)

Update counts to match corrected status enum:

```ts
// BEFORE
export interface PayslipsSummary {
    total_payslips: number;
    generated: number;
    pending: number;     // remove — 'pending' not in DB enum
    sent: number;        // remove — 'sent' not in DB enum
    acknowledged: number;
    failed: number;      // remove — 'failed' not in DB enum
    total_distribution_email: number;
    total_distribution_portal: number;
    total_distribution_printed: number; // rename to total_distribution_print
}

// AFTER
export interface PayslipsSummary {
    total_payslips: number;
    draft: number;
    generated: number;
    distributed: number;   // was sent
    acknowledged: number;
    total_distribution_email: number;
    total_distribution_portal: number;
    total_distribution_print: number;   // was total_distribution_printed
    total_distribution_sms: number;     // new channel
}
```

---

### A-5. `PayslipsFilters` interface (line ~1551)

```ts
// BEFORE
status: string; // 'all' | 'pending' | 'generated' | 'sent' | 'acknowledged' | 'failed'
distribution_method: string; // 'all' | 'email' | 'portal' | 'printed'

// AFTER
status: string; // 'all' | 'draft' | 'generated' | 'distributed' | 'acknowledged'
distribution_method: string; // 'all' | 'email' | 'portal' | 'print' | 'sms'
```

---

### A-6. Request/Response interfaces for Payslips

```ts
// PayslipGenerationRequest — fix distribution_method
distribution_method: 'email' | 'portal' | 'print' | 'sms'; // was 'printed'

// PayslipDistributionRequest — same fix
distribution_method: 'email' | 'portal' | 'print' | 'sms'; // was 'printed'
```

---

### A-7. `PaymentTrackingPageProps` (line 1599)

```ts
// BEFORE
payment_methods: string[];   // hardcoded ['bank_transfer', 'cash', 'check']

// AFTER — change to typed array of method objects from PaymentMethod model
payment_methods: Array<{
    id: number;
    method_type: 'bank' | 'cash' | 'ewallet';
    display_name: string;
    is_enabled: boolean;
}>;
```

---

### A-8. `PaymentTracking` interface (line 1633)

#### Payment method type

```ts
// BEFORE
payment_method: 'bank_transfer' | 'cash' | 'check';
payment_method_icon: string;  // 'bank', 'cash', 'check'

// AFTER (matches payment_methods.method_type)
payment_method: 'bank' | 'cash' | 'ewallet';
payment_method_icon: 'bank' | 'cash' | 'ewallet'; // no more 'check'
```

#### Payment status

```ts
// BEFORE
payment_status: 'pending' | 'processing' | 'paid' | 'failed';

// AFTER (adds all DB enum values)
payment_status: 'pending' | 'processing' | 'paid' | 'partially_paid' | 'failed' | 'cancelled' | 'unclaimed';
```

---

### A-9. `FailedPayment` interface (line ~1680)

```ts
// BEFORE
current_payment_method: 'bank_transfer' | 'cash' | 'check';
failure_code: string;          // REMOVE — no such column in DB
failure_timestamp: string;     // RENAME to failed_at
max_retries: number;           // CHANGE to constant comment
next_retry_date?: string;      // REMOVE — no such column, computed if needed

// AFTER
current_payment_method: 'bank' | 'cash' | 'ewallet';
// failure_code: REMOVED
failed_at: string;             // was failure_timestamp
retry_count: number;           // keep
// max_retries: REMOVE from interface — it's a system constant (3), not per-record
// next_retry_date: REMOVE — not stored, can be computed in controller if needed

// Also fix alternative_methods type:
alternative_methods: Array<{
    method: 'bank' | 'cash' | 'ewallet';  // was 'bank_transfer' | 'cash' | 'check'
    label: string;
    available: boolean;
}>;
```

---

### A-10. `CashPaymentPageProps` (line 1719)

```ts
// BEFORE
export interface CashPaymentPageProps {
    cash_employees: CashEmployee[];
    summary: CashPaymentSummary;
    payroll_periods: PayrollPeriod[];
    distributions: CashDistribution[];       // WRONG — this is per-employee, but DB is batch-level
    unclaimed_cash: UnclaimedCash[];
}

// AFTER
export interface CashPaymentPageProps {
    cash_employees: CashEmployee[];
    summary: CashPaymentSummary;
    payroll_periods: PayrollPeriod[];
    batches: CashDistributionBatch[];         // RENAMED from distributions, new type
    unclaimed_cash: UnclaimedCash[];
    departments: Array<{ id: number; name: string }>; // ADD — needed for filters
    query_params: {
        period_id: string | number;
        department_id: string | number;
        status: string;
        search: string;
    };
}
```

---

### A-11. `CashPaymentSummary` interface (line ~1730)

```ts
// BEFORE
envelopes_printed: number;    // RENAME
envelopes_pending: number;    // RENAME

// AFTER (matches cash_distribution_batches columns)
envelopes_prepared: number;       // was envelopes_printed
envelopes_distributed: number;    // was envelopes_pending (renamed conceptually)
envelopes_unclaimed: number;      // keep
```

---

### A-12. `CashEmployee` interface (line 1745)

```ts
// BEFORE
envelope_status: 'pending' | 'printed' | 'prepared' | 'distributed' | 'unclaimed';
distribution_status: 'pending' | 'distributed' | 'unclaimed' | 'claimed';  // REMOVE

// AFTER — collapse to single status derived from payroll_payments.status
payment_status: 'pending' | 'processing' | 'paid' | 'failed' | 'unclaimed';
// NOTE: 'printed' and 'prepared' are not tracked at payment level in DB.
// Envelope printing is tracked at the batch level (cash_distribution_batches.envelopes_prepared).
// envelope_status_* labels: drive from batch status, not per-employee status.

// REMOVE these fields (not in payroll_payments):
// distribution_status
// distribution_status_label
// envelope_printed_at (not tracked per-employee in DB)
// envelope_printed_by (not tracked per-employee in DB)

// KEEP (from payroll_payments or joined data):
distributed_at?: string;          // payroll_payments.paid_at (when cash was handed over)
distributed_by?: string;          // joined from user name
claimed_at?: string;              // keep — can be tracked via payment_audit_logs
claimed_by?: string;              // keep — can be tracked via payment_audit_logs
signature_capture_url?: string;   // keep — claimed_by_signature column in payroll_payments
```

---

### A-13. Rename `CashDistribution` → `CashDistributionBatch` (line ~1810)

The old `CashDistribution` interface modelled a per-employee distribution record. The DB stores a **batch-level** record. Rename and reshape:

```ts
// REMOVE the old CashDistribution interface entirely.
// REPLACE with CashDistributionBatch matching cash_distribution_batches table:

export interface CashDistributionBatch {
    id: number;
    payroll_period_id: number;
    period_name: string;                     // joined from payroll_periods

    batch_number: string;                    // e.g. "CDB-2025-10-001"
    distribution_date: string;               // date only
    distribution_time?: string;              // time of distribution
    distribution_location: string | null;

    total_cash_amount: number;
    total_employees: number;
    formatted_total_cash: string;

    denomination_breakdown: Record<string, number> | null; // {"1000": 50, "500": 30, ...}

    envelopes_prepared: number;
    envelopes_distributed: number;
    envelopes_unclaimed: number;
    amount_distributed: number;
    amount_unclaimed: number;

    prepared_by: string | null;              // joined user name
    counted_by: string | null;               // joined user name
    witnessed_by: string | null;             // joined user name
    distributed_by: string | null;           // joined user name
    verification_at: string | null;

    status: 'pending' | 'counting' | 'verified' | 'distributing' | 'completed' | 'partially_completed';
    status_label: string;
    status_color: string;

    notes: string | null;
    created_at: string;
    updated_at: string;
}
```

---

### A-14. `UnclaimedCash` interface (line ~1840)

```ts
// Remove fields not in DB:
// days_until_returned — REMOVE (not stored, can be computed in controller from company policy)
// contact_attempts — REMOVE (not stored in DB)
// last_contact_attempt — REMOVE (not stored in DB)

// Keep / add:
export interface UnclaimedCash {
    id: number;
    payroll_payment_id: number;             // FK to payroll_payments
    employee_id: number;
    employee_number: string;
    employee_name: string;
    period_name: string;
    amount: number;
    formatted_amount: string;
    days_unclaimed: number;                 // computed: today - envelope_prepared_at
    envelope_prepared_at: string;
    distribution_scheduled_for: string | null;
    status: 'pending_distribution' | 'pending_collection' | 'escalated' | 'returned';
    status_label: string;
    status_color: string;
    notes: string | null;
}
```

---

## SET B — Controller Bug Fixes

### B-1. `BankFilesController` — restrict banks to seeded values

**File:** `app/Http/Controllers/Payroll/Payments/BankFilesController.php`

```php
// BEFORE (line ~46)
'bank_name' => 'required|string|in:BPI,BDO,Metrobank,PNB,RCBC,Unionbank',

// AFTER
'bank_name' => 'required|string|in:BDO,Metrobank',
// Only Metrobank (MBTC) and BDO are seeded in payment_methods table.
// BPI, PNB, RCBC, Unionbank to be added when those banks go live (Phase 4+).
```

Also update the docblock comment at the top of the class:

```php
// BEFORE
 * Supported Banks:
 * - BPI (Bank of the Philippine Islands) - CSV, Fixed-width
 * - BDO (Banco de Oro) - CSV, Excel
 * - Metrobank - CSV, Fixed-width
 * - PNB (Philippine National Bank) - CSV, Excel
 * - RCBC (Rizal Commercial Banking Corporation) - CSV, Fixed-width
 * - Unionbank - CSV, Excel

// AFTER
 * Supported Banks (Phase 3):
 * - BDO (Banco de Oro) - CSV, Excel
 * - Metrobank - CSV, Fixed-width
 * 
 * Future banks (Phase 4+, requires adding to payment_methods seeder):
 * - BPI, PNB, RCBC, Unionbank
```

---

### B-2. `PaymentTrackingController@confirm()` — remove non-existent table reference

**File:** `app/Http/Controllers/Payroll/Payments/PaymentTrackingController.php`

```php
// BEFORE (line ~68)
'payment_reference' => 'required|string|unique:payment_confirmations',

// AFTER — validate uniqueness against the actual column in payroll_payments
'payment_reference' => 'required|string|unique:payroll_payments,payment_reference',
// The payment_confirmations table does not exist. Uniqueness is on payroll_payments.payment_reference.
```

---

### B-3. `PaymentTrackingController@index()` — wire `payment_methods` from DB

**File:** `app/Http/Controllers/Payroll/Payments/PaymentTrackingController.php`

Add the import at the top of the file:

```php
// ADD at top
use App\Models\PaymentMethod;
```

Update the index method:

```php
// BEFORE (line ~49)
$paymentMethods = ['bank_transfer', 'cash', 'check'];
$paymentStatuses = ['pending', 'processing', 'paid'];

// AFTER
$paymentMethods = PaymentMethod::enabled()->ordered()->get(['id', 'method_type', 'display_name', 'is_enabled']);
$paymentStatuses = ['pending', 'processing', 'paid', 'partially_paid', 'failed', 'cancelled', 'unclaimed'];
```

---

### B-4. `PayslipsController@generate()` and `distribute()` — fix `printed` → `print`

**File:** `app/Http/Controllers/Payroll/Payments/PayslipsController.php`

```php
// BEFORE (in generate() validation, line ~58)
'distribution_method' => 'required|in:email,portal,printed',

// AFTER
'distribution_method' => 'required|in:email,portal,print,sms',
```

```php
// BEFORE (in distribute() validation)
'distribution_method' => 'required|in:email,portal,printed',

// AFTER
'distribution_method' => 'required|in:email,portal,print,sms',
```

---

### B-5. `CashPaymentController@index()` — remove `envelope_status` and `distribution_status` filter params

**File:** `app/Http/Controllers/Payroll/Payments/CashPaymentController.php`

```php
// BEFORE (line ~16)
$envelopeStatus = $request->input('envelope_status', 'all');
$distributionStatus = $request->input('distribution_status', 'all');

// AFTER — collapse to single 'status' filter that maps to payroll_payments.status
$paymentStatus = $request->input('status', 'all');
// 'envelope_status' and 'distribution_status' are not DB columns.
// Filtering will be done by payroll_payments.status.
```

---

## SET C — Frontend Page Fixes

### C-1. `Payslips/Index.tsx` — fix route

**File:** `resources/js/pages/Payroll/Payments/Payslips/Index.tsx`

The Payslips page uses the wrong base route for navigation. The correct route prefix is `payroll.payments.payslips.*` not `payroll.payslips.*`.

Search for all `route('payroll.payslips.*')` calls in the file and ensure they use `route('payroll.payments.payslips.*')`.

```tsx
// Audit all router.* / route('payroll.*') calls:
// CORRECT route names (from routes/payroll.php):
//   payroll.payments.payslips.index
//   payroll.payments.payslips.generate
//   payroll.payments.payslips.distribute
//   payroll.payments.payslips.preview
//   payroll.payments.payslips.download
```

---

### C-2. `BankFiles/Index.tsx` — update status filter options

**File:** `resources/js/pages/Payroll/Payments/BankFiles/Index.tsx`

```tsx
// BEFORE — status filter values in the filter dropdown
{ value: 'generated', label: 'Generated' }
{ value: 'uploaded', label: 'Uploaded' }
{ value: 'processed', label: 'Processed' }
{ value: 'confirmed', label: 'Confirmed' }
{ value: 'failed', label: 'Failed' }

// AFTER — match DB enum
{ value: 'draft', label: 'Draft' }
{ value: 'ready', label: 'Ready' }
{ value: 'submitted', label: 'Submitted' }
{ value: 'processing', label: 'Processing' }
{ value: 'completed', label: 'Completed' }
{ value: 'partially_completed', label: 'Partial' }
{ value: 'failed', label: 'Failed' }
```

---

### C-3. `Cash/Index.tsx` — update filter to use single `status` param

**File:** `resources/js/pages/Payroll/Payments/Cash/Index.tsx`

```tsx
// BEFORE — two separate filter params
envelope_status: 'pending' | 'printed' | 'prepared' | 'distributed' | 'unclaimed'
distribution_status: 'pending' | 'distributed' | 'unclaimed' | 'claimed'

// AFTER — single status filter mapped to payroll_payments.status
status: 'all' | 'pending' | 'processing' | 'paid' | 'failed' | 'unclaimed'
```

Also update the `distributions` prop reference:

```tsx
// BEFORE
const { cash_employees, summary, distributions, unclaimed_cash } = props;

// AFTER
const { cash_employees, summary, batches, unclaimed_cash } = props;
```

---

### C-4. `Tracking/Index.tsx` — update payment method labels

**File:** `resources/js/pages/Payroll/Payments/Tracking/Index.tsx`

```tsx
// BEFORE — filter dropdown options
{ value: 'bank_transfer', label: 'Bank Transfer' }
{ value: 'cash', label: 'Cash' }
{ value: 'check', label: 'Check' }

// AFTER — match DB method_type values
{ value: 'bank', label: 'Bank Transfer' }
{ value: 'cash', label: 'Cash' }
{ value: 'ewallet', label: 'E-Wallet' }
// Note: 'check' method does not exist in payment_methods table
```

Also update the `payment_methods` prop consumption:

```tsx
// BEFORE
payment_methods.map(method => ...)  // was string[]

// AFTER — payment_methods is now Array<{id, method_type, display_name, is_enabled}>
payment_methods.map(m => ({ value: m.method_type, label: m.display_name }))
```

---

## SET D — Controller Wiring (Replace Mock Data with Real Queries)

Do these **after** Set A, B, C are complete. One controller at a time.

---

### D-1. `BankFilesController@index()` — wire to `BankFileBatch` model

```php
// Replace getMockBankFiles() with:
use App\Models\BankFileBatch;
use App\Models\PayrollPeriod;
use App\Models\PaymentMethod;

public function index(Request $request)
{
    $bankFiles = BankFileBatch::with(['payrollPeriod', 'generatedBy', 'submittedBy'])
        ->when($request->period_id, fn($q, $id) => $q->where('payroll_period_id', $id))
        ->when($request->bank_name, fn($q, $bank) => $q->where('bank_name', $bank))
        ->when($request->status, fn($q, $s) => $q->where('status', $s))
        ->latest()
        ->paginate(20);

    $periods = PayrollPeriod::select('id', 'name', 'start_date', 'end_date', 'pay_date')
        ->orderByDesc('pay_date')
        ->get();

    // bankList comes from enabled bank-type PaymentMethods
    $bankList = PaymentMethod::enabled()
        ->where('method_type', 'bank')
        ->ordered()
        ->get()
        ->map(fn($m) => [
            'id' => $m->bank_code ?? $m->display_name,
            'name' => $m->display_name,
            'code' => $m->bank_code,
            'supported_formats' => ['csv', 'txt'],
        ]);

    $employeesCount = // TODO: count employees with bank payment preference for period

    return Inertia::render('Payroll/Payments/BankFiles/Index', compact(
        'bankFiles', 'periods', 'bankList', 'employeesCount'
    ));
}
```

---

### D-2. `CashPaymentController@index()` — wire to DB

```php
use App\Models\PayrollPayment;
use App\Models\CashDistributionBatch;
use App\Models\PaymentMethod;
use App\Models\Department;

public function index(Request $request)
{
    $cashMethodId = PaymentMethod::where('method_type', 'cash')->value('id');

    $cashEmployees = PayrollPayment::with(['employee.profile', 'employee.department', 'employee.position'])
        ->where('payment_method_id', $cashMethodId)
        ->when($request->period_id !== 'all', fn($q) => $q->where('payroll_period_id', $request->period_id))
        ->when($request->status !== 'all', fn($q) => $q->where('status', $request->status))
        ->when($request->department_id !== 'all', fn($q) => $q->whereHas('employee', fn($eq) =>
            $eq->where('department_id', $request->department_id)
        ))
        ->when($request->search, fn($q, $s) => $q->whereHas('employee', fn($eq) =>
            $eq->where('full_name', 'ilike', "%{$s}%")
                ->orWhere('employee_number', 'ilike', "%{$s}%")
        ))
        ->get()
        ->map(fn($p) => $this->formatCashEmployee($p));

    $batches = CashDistributionBatch::with(['payrollPeriod', 'preparedBy', 'distributedBy'])
        ->when($request->period_id !== 'all', fn($q) => $q->where('payroll_period_id', $request->period_id))
        ->latest()
        ->get();

    $summary = $this->buildCashSummary($cashEmployees, $batches);

    $unclaimedCash = PayrollPayment::with('employee.profile')
        ->where('payment_method_id', $cashMethodId)
        ->where('status', 'unclaimed')
        ->get()
        ->map(fn($p) => $this->formatUnclaimedCash($p));

    return Inertia::render('Payroll/Payments/Cash/Index', [
        'cash_employees' => $cashEmployees,
        'summary' => $summary,
        'batches' => $batches,
        'unclaimed_cash' => $unclaimedCash,
        'payroll_periods' => $this->getPayrollPeriods(),
        'departments' => Department::select('id', 'name')->orderBy('name')->get(),
        'query_params' => $request->only(['period_id', 'department_id', 'status', 'search']),
    ]);
}
```

---

### D-3. `PayslipsController@index()` — wire to `Payslip` model

```php
use App\Models\Payslip;
use App\Models\PayrollPeriod;
use App\Models\Department;

public function index(Request $request)
{
    $payslips = Payslip::with(['employee.profile', 'employee.department', 'payrollPayment.payrollPeriod', 'generatedBy'])
        ->when($request->search, fn($q, $s) => $q->whereHas('employee', fn($eq) =>
            $eq->where('full_name', 'ilike', "%{$s}%")
                ->orWhere('employee_number', 'ilike', "%{$s}%")
        ))
        ->when($request->period_id, fn($q, $id) => $q->whereHas('payrollPayment', fn($pq) =>
            $pq->where('payroll_period_id', $id)
        ))
        ->when($request->department_id, fn($q, $id) => $q->whereHas('employee', fn($eq) =>
            $eq->where('department_id', $id)
        ))
        ->when($request->status && $request->status !== 'all', fn($q, $s) => $q->where('status', $s))
        ->when($request->distribution_method && $request->distribution_method !== 'all', fn($q, $m) =>
            $q->where('distribution_method', $m)
        )
        ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
        ->when($request->date_to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
        ->latest()
        ->paginate(50)
        ->through(fn($p) => $this->formatPayslip($p));

    $summary = [
        'total_payslips' => Payslip::count(),
        'draft' => Payslip::where('status', 'draft')->count(),
        'generated' => Payslip::where('status', 'generated')->count(),
        'distributed' => Payslip::where('status', 'distributed')->count(),
        'acknowledged' => Payslip::where('status', 'acknowledged')->count(),
        'total_distribution_email' => Payslip::where('distribution_method', 'email')->count(),
        'total_distribution_portal' => Payslip::where('distribution_method', 'portal')->count(),
        'total_distribution_print' => Payslip::where('distribution_method', 'print')->count(),
        'total_distribution_sms' => Payslip::where('distribution_method', 'sms')->count(),
    ];

    return Inertia::render('Payroll/Payments/Payslips/Index', [
        'payslips' => $payslips,
        'summary' => $summary,
        'filters' => $request->only(['search','period_id','department_id','status','distribution_method','date_from','date_to']),
        'periods' => PayrollPeriod::select('id','name','start_date','end_date','pay_date')->orderByDesc('pay_date')->get(),
        'departments' => Department::select('id','name')->orderBy('name')->get(),
        'distributionMethods' => [
            ['id' => 'email',  'name' => 'Email'],
            ['id' => 'portal', 'name' => 'Self-Service Portal'],
            ['id' => 'print',  'name' => 'Printed'],
            ['id' => 'sms',    'name' => 'SMS'],
        ],
    ]);
}
```

---

### D-4. `PaymentTrackingController@index()` — wire to `PayrollPayment` model

```php
use App\Models\PayrollPayment;
use App\Models\PaymentMethod;
use App\Models\PayrollPeriod;
use App\Models\Department;

public function index(Request $request)
{
    $query = PayrollPayment::with([
        'employee.profile',
        'employee.department',
        'employee.position',
        'paymentMethod',
        'payrollPeriod',
    ]);

    // Apply filters
    if ($request->search) {
        $query->whereHas('employee', fn($q) =>
            $q->where('full_name', 'ilike', "%{$request->search}%")
              ->orWhere('employee_number', 'ilike', "%{$request->search}%")
        );
    }
    if ($request->period_id && $request->period_id !== 'all') {
        $query->where('payroll_period_id', $request->period_id);
    }
    if ($request->payment_method && $request->payment_method !== 'all') {
        $query->whereHas('paymentMethod', fn($q) =>
            $q->where('method_type', $request->payment_method)
        );
    }
    if ($request->payment_status && $request->payment_status !== 'all') {
        $query->where('status', $request->payment_status);
    }

    $allPayments = $query->get();

    $failedPayments = $allPayments->where('status', 'failed')
        ->map(fn($p) => $this->formatFailedPayment($p))
        ->values();

    $payments = $allPayments->where('status', '!=', 'failed')
        ->map(fn($p) => $this->formatPaymentTracking($p))
        ->values();

    $summary = $this->buildPaymentSummary($allPayments);

    return Inertia::render('Payroll/Payments/Tracking/Index', [
        'payments' => $payments,
        'summary' => $summary,
        'payroll_periods' => PayrollPeriod::select('id','name','start_date','end_date','pay_date')->orderByDesc('pay_date')->get(),
        'departments' => Department::select('id','name')->orderBy('name')->get(),
        'payment_methods' => PaymentMethod::enabled()->ordered()->get(['id','method_type','display_name','is_enabled']),
        'payment_statuses' => ['pending','processing','paid','partially_paid','cancelled','unclaimed'],
        'failed_payments' => $failedPayments,
    ]);
}
```

---

## 📋 Checklist Summary

### Set A — TypeScript Fixes (`payroll-pages.ts`)

- [ ] **A-1** `BankPayrollFile.status` enum → `draft|ready|submitted|processing|completed|partially_completed|failed`
- [ ] **A-1** `BankPayrollFile` field renames: `uploaded_at→submitted_at`, `uploaded_by→submitted_by`, `confirmation_number→bank_confirmation_number`, remove `generated_at`
- [ ] **A-1** `BankPayrollFile` add: `transfer_type`, `is_validated`, `validated_at`, `validation_errors`, `successful_count`, `failed_count`, `total_fees`, `settlement_date`, `settlement_reference`
- [ ] **A-3** `Payslip.payroll_calculation_id` → `payroll_payment_id`
- [ ] **A-3** `Payslip` earnings: remove flat fields, add `earnings_data: Record<string, number>`
- [ ] **A-3** `Payslip` deductions: remove flat fields, add `deductions_data: Record<string, number>`
- [ ] **A-3** `Payslip.ytd_deductions` → `ytd_sss`, `ytd_philhealth`, `ytd_pagibig`, `ytd_tax`
- [ ] **A-3** `Payslip` file fields: `pdf_file_path→file_path`, `pdf_file_size→file_size`, `pdf_hash→file_hash`
- [ ] **A-3** `Payslip` remove distribution fields: `email_sent`, `email_sent_at`, `email_address`, `downloaded_by_employee`, `downloaded_at`, `printed`, `printed_at`, `printed_by`, `acknowledged_by_employee`, `acknowledged_at`
- [ ] **A-3** `Payslip.distribution_method`: fix `'printed'→'print'`, add `'sms'`
- [ ] **A-3** `Payslip.status` enum → `draft|generated|distributed|acknowledged`
- [ ] **A-3** `Payslip` add: `payslip_number`, `signature_hash`, `qr_code_data`, `distributed_at`, `is_viewed`, `viewed_at`, `sss_number`, `philhealth_number`, `pagibig_number`, `tin`
- [ ] **A-4** `PayslipsSummary` counts: replace `pending`, `sent`, `failed` → `draft`, `distributed`; rename `total_distribution_printed→total_distribution_print`, add `sms`
- [ ] **A-5** `PayslipsFilters.status` → `all|draft|generated|distributed|acknowledged`
- [ ] **A-5** `PayslipsFilters.distribution_method` → `all|email|portal|print|sms`
- [ ] **A-6** `PayslipGenerationRequest` + `PayslipDistributionRequest`: `printed→print`
- [ ] **A-7** `PaymentTrackingPageProps.payment_methods` → typed object array
- [ ] **A-8** `PaymentTracking.payment_method` → `'bank'|'cash'|'ewallet'`
- [ ] **A-8** `PaymentTracking.payment_status` add `partially_paid|cancelled|unclaimed`
- [ ] **A-9** `FailedPayment`: remove `failure_code`, rename `failure_timestamp→failed_at`, remove `max_retries`, remove `next_retry_date`, fix method enum
- [ ] **A-10** `CashPaymentPageProps.distributions` → `batches: CashDistributionBatch[]`, add `departments`, `query_params`
- [ ] **A-11** `CashPaymentSummary`: `envelopes_printed→envelopes_prepared`, `envelopes_pending→envelopes_distributed`
- [ ] **A-12** `CashEmployee`: replace `envelope_status` + `distribution_status` with `payment_status`, remove tracking fields not in DB
- [ ] **A-13** Remove old `CashDistribution` interface, create `CashDistributionBatch` interface
- [ ] **A-14** `UnclaimedCash`: remove `days_until_returned`, `contact_attempts`, `last_contact_attempt`

### Set B — Controller Bug Fixes

- [ ] **B-1** `BankFilesController`: restrict bank validation to `BDO,Metrobank`
- [ ] **B-2** `PaymentTrackingController@confirm()`: fix `unique:payment_confirmations` → `unique:payroll_payments,payment_reference`
- [ ] **B-3** `PaymentTrackingController@index()`: wire `payment_methods` from `PaymentMethod::enabled()`
- [ ] **B-4** `PayslipsController@generate()` + `distribute()`: `printed→print`, add `sms`
- [ ] **B-5** `CashPaymentController@index()`: replace `envelope_status`/`distribution_status` params with `status`

### Set C — Frontend Page Fixes

- [ ] **C-1** `Payslips/Index.tsx`: audit and fix all route names to `payroll.payments.payslips.*`
- [ ] **C-2** `BankFiles/Index.tsx`: update status filter to new enum values
- [ ] **C-3** `Cash/Index.tsx`: replace two-filter (envelope/distribution) with single `status` filter; rename `distributions→batches`
- [ ] **C-4** `Tracking/Index.tsx`: update payment method filter values; update `payment_methods` prop consumption

### Set D — Controller Wiring

- [ ] **D-1** `BankFilesController@index()`: replace `getMockBankFiles()` with `BankFileBatch::with(...)->paginate()`
- [ ] **D-2** `CashPaymentController@index()`: replace mock with real `PayrollPayment` + `CashDistributionBatch` queries
- [ ] **D-3** `PayslipsController@index()`: replace mock with `Payslip::with(...)->paginate()`
- [ ] **D-4** `PaymentTrackingController@index()`: replace mock with `PayrollPayment::with(...)->get()`

---

## ⚠️ Notes & Decisions

1. **`ytd_deductions` → 4 separate fields:** The DB stores YTD government deductions per agency. Frontend components that show a single YTD deductions total should sum `ytd_sss + ytd_philhealth + ytd_pagibig + ytd_tax`.

2. **Flat earnings → `earnings_data` JSON:** When implementing payslip PDF rendering, the component should use `Object.entries(earnings_data)` to display a dynamic earnings table. This is more future-proof than hard-coded columns.

3. **Cash batch vs per-employee:** The `CashDistributionBatch` represents one cash run per period. The `CashEmployee` represents one employee's cash payment in that period. The controller must assemble the `CashEmployee[]` list from `payroll_payments WHERE method=cash`, not from a dedicated per-employee table.

4. **`check` payment method does not exist** in the `payment_methods` table. Any frontend reference to `'check'` as a payment method must be removed.

5. **`max_retries` is a constant (3):** Per Decision #12 from `PAYROLL-PAYMENTS-IMPLEMENTATION-PLAN.md`, maximum retries is 3. This should be a PHP constant or config value, not a per-record DB column. Remove from `FailedPayment` TS interface.

6. **`payment_confirmations` table does not exist:** This table was never created. The `confirm()` controller validation rule must reference `payroll_payments.payment_reference` for uniqueness.

7. **`BankFileBatch` + `BankFilesController`** will need a `generateFileName()` helper that persists to `bank_file_batches` via `BankFileBatch::create()`. This will be implemented as `BankFileGeneratorService` in Phase 3.

---

**Status:** 🟡 Ready for implementation — start with Set A  
**Last Updated:** February 19, 2026
