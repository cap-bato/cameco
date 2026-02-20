# Payments Module — Frontend/Backend Integration Gap Report

**Generated:** February 19, 2026  
**Scope:** `Payroll/Payments/*` — BankFiles, Cash, Payslips, Tracking  
**Backend Phase:** 1 & 2 Complete (Migrations + Models)  
**Frontend Phase:** Pages, components, and TypeScript types all exist with mock data  

---

## 🔴 Executive Summary

**All 4 controllers are 100% mock data.** The models created in Phase 2 are not wired to any controller or frontend page yet. Beyond that, there are **significant shape mismatches** between the TypeScript interfaces in `payroll-pages.ts` and the actual database schema — field names, status enums, and data structures differ in several places. These must be reconciled before any controller can return real data without breaking the frontend.

---

## 📊 Alignment Matrix

| Area | Backend Model | Controller | Frontend Page | TS Interface | Status |
|---|---|---|---|---|---|
| Bank Files | ✅ `BankFileBatch` | ❌ Mock only | ✅ Exists | ⚠️ Shape mismatch | **Not wired** |
| Cash Distribution | ✅ `CashDistributionBatch` + `PayrollPayment` | ❌ Mock only | ✅ Exists | ⚠️ Shape mismatch | **Not wired** |
| Payslips | ✅ `Payslip` | ❌ Mock only | ✅ Exists | ⚠️ Shape mismatch | **Not wired** |
| Payment Tracking | ✅ `PayrollPayment` | ❌ Mock only | ✅ Exists | ⚠️ Shape mismatch | **Not wired** |

---

## 🔍 Detailed Gap Analysis Per Module

---

### 1. Bank Files (`BankFileBatch` ↔ `BankFilesController` ↔ `BankFilesPageProps`)

#### Controller issues
- Returns hardcoded `getMockBankFiles()` array — model never queried
- `generateFile()` returns an in-memory array with a fake `status: 'generated'` — no `BankFileBatch::create()` called
- `validateFile()` returns hardcoded errors — no `is_validated` or `validation_errors` DB update
- Supported bank list is `BPI, PNB, RCBC, Unionbank` but the DB only seeds **Metrobank** and **BDO** (Phase 1 decision)

#### TypeScript ↔ Model field mismatches

| TS field (`BankPayrollFile`) | DB column (`bank_file_batches`) | Issue |
|---|---|---|
| `status: 'generated' \| 'uploaded' \| 'processed' \| 'confirmed' \| 'failed'` | `status: 'draft' \| 'ready' \| 'submitted' \| 'processing' \| 'completed' \| 'partially_completed' \| 'failed'` | **Completely different enum values** |
| `generated_at` | No such column (use `created_at`) | **Missing column** |
| `uploaded_at`, `uploaded_by` | No such columns (use `submitted_at`, `submitted_by`) | **Different field names** |
| `confirmation_number` | `bank_confirmation_number` | **Different name** |
| `file_format: 'csv' \| 'txt' \| 'excel' \| 'fixed_width'` | `file_format: string` (open string) | Minor — acceptable |
| `bank_name` (flat string) | `bank_name` + `bank_code` (two fields) | Needs both passed |
| `total_amount` | `total_amount` | ✅ Match |
| `total_employees` | `total_employees` | ✅ Match |
| No `transfer_type` | `transfer_type: 'instapay' \| 'pesonet' \| 'internal'` | **Frontend missing this key field** |
| No `is_validated`, `validation_errors` | Present in DB | **Frontend missing validation state** |
| No `successful_count`, `failed_count` | Present in DB | **Frontend missing transaction result fields** |

#### What needs to change
- Update `BankPayrollFile` status enum to match DB: `draft | ready | submitted | processing | completed | partially_completed | failed`
- Rename `uploaded_at → submitted_at`, `uploaded_by → submitted_by_name`, `confirmation_number → bank_confirmation_number`
- Add `transfer_type`, `is_validated`, `validation_errors`, `successful_count`, `failed_count` to `BankPayrollFile`
- Remove unsupported banks (BPI, PNB, RCBC, Unionbank) from the controller's validation and frontend bank list
- Wire `BankFilesController@index()` to query `BankFileBatch::with(['payrollPeriod', 'generatedBy', 'submittedBy'])`

---

### 2. Cash Payments (`CashDistributionBatch` + `PayrollPayment` ↔ `CashPaymentController` ↔ `CashPaymentPageProps`)

#### Controller issues
- Returns `getMockCashEmployees()` — no query against `payroll_payments` or `cash_distribution_batches`
- `generateEnvelopes()` renders `EnvelopePreview` with fake data — no DB write
- `recordDistribution()` validates but logs nothing to DB
- `getAccountabilityReport()` / `getEnvelopePreview()` return fake Inertia renders

#### TypeScript ↔ Model field mismatches

| TS interface | Model/DB reality | Issue |
|---|---|---|
| `CashEmployee` — flat per-employee record | Backend has two separate tables: `payroll_payments` (one row per employee) + `cash_distribution_batches` (one batch for all) | **Frontend "cash employee" must be assembled from `payroll_payments` joined with employee data** |
| `CashEmployee.envelope_status: 'pending' \| 'printed' \| 'prepared' \| 'distributed' \| 'unclaimed'` | `payroll_payments.status: 'pending' \| 'processing' \| 'paid' \| 'failed' \| 'unclaimed'` | **No `printed` or `prepared` status in DB** — these are not tracked at payment level |
| `CashEmployee.distribution_status` | No such column in `payroll_payments` | **Frontend invents a second status not in DB** |
| `CashDistribution` (per-employee distribution record) | `cash_distribution_batches` is a **batch-level** table (one row for the entire distribution run) | **Architectural mismatch** — frontend models individual distributions, DB models a batch |
| `UnclaimedCash.days_until_returned`, `contact_attempts` | Not in DB | **Computed/missing fields** |
| `CashPaymentSummary.envelopes_printed`, `envelopes_pending` | `cash_distribution_batches.envelopes_prepared`, `envelopes_distributed`, `envelopes_unclaimed` | **Different field names** |
| `distributions` prop (array of `CashDistribution`) | Should be array of `CashDistributionBatch` records | **Type name and shape need update** |

#### What needs to change
- `CashEmployee` should be sourced from `payroll_payments WHERE payment_method_id = (cash method id)` joined with `employees`
- `envelope_status` and `distribution_status` need to be collapsed into `payroll_payments.status` (or derived from it)
- Rename `CashDistribution` → `CashDistributionBatch` in the TS types, update all fields to match the `cash_distribution_batches` table
- Add `batch_number`, `distribution_location`, `denomination_breakdown`, `counted_by`, `witnessed_by`, `verification_at` to the frontend batch type
- `CashPaymentSummary` fields: rename `envelopes_printed → envelopes_prepared`, remove unsupported computed fields

---

### 3. Payslips (`Payslip` ↔ `PayslipsController` ↔ `PayslipsPageProps`)

#### Controller issues
- Returns `getMockPayslips()` — `Payslip` model never queried
- `generate()` returns a back-redirect with a success flash but creates no `Payslip` record
- `distribute()` returns success flash but updates nothing in DB
- `preview()` renders `Payslips/Index` (wrong — should render a preview component or return JSON)
- Validation allows `distribution_method: 'email|portal|printed'` but DB enum is `email|portal|print|sms`

#### TypeScript ↔ Model field mismatches

| TS field (`Payslip` interface) | DB column (`payslips`) | Issue |
|---|---|---|
| `payroll_calculation_id` | `payroll_payment_id` | **Wrong FK name** |
| `basic_salary`, `overtime_pay`, `night_differential`, `holiday_pay`, `allowances`, `other_earnings` (flat columns) | `earnings_data` JSON + `total_earnings` decimal | **Frontend expects flat columns, DB stores JSON** |
| `sss_contribution`, `philhealth_contribution`, etc. (flat columns) | `deductions_data` JSON + `total_deductions` decimal | **Frontend expects flat columns, DB stores JSON** |
| `ytd_deductions` | No such column — DB has `ytd_sss`, `ytd_philhealth`, `ytd_pagibig`, `ytd_tax` separately | **Frontend aggregates, DB is granular** |
| `pdf_file_path`, `pdf_file_size`, `pdf_hash` | `file_path`, `file_size`, `file_hash` | **Different field names (pdf_ prefix)** |
| `email_sent`, `email_sent_at`, `email_address`, `downloaded_by_employee`, `downloaded_at`, `printed`, `printed_at`, `printed_by`, `acknowledged_by_employee`, `acknowledged_at` | Not in DB — DB only has `distributed_at`, `is_viewed`, `viewed_at`, `distribution_method` | **Frontend has much richer distribution tracking than DB supports** |
| `status: 'pending' \| 'generated' \| 'sent' \| 'acknowledged' \| 'failed'` | `status: 'draft' \| 'generated' \| 'distributed' \| 'acknowledged'` | **Different enum: no `pending`, no `failed`, `sent → distributed`** |
| No `payslip_number` | `payslip_number` (unique) | **Frontend missing the unique identifier** |
| No `signature_hash`, `qr_code_data` | Present in DB | **Frontend missing QR/signature fields** |
| `period_name` | Not stored — must be joined from `payroll_periods.name` | Needs JOIN |

#### What needs to change
- Fix FK: `payroll_calculation_id → payroll_payment_id`
- Change flat earnings/deductions fields to parse from `earnings_data` / `deductions_data` JSON (frontend can flatten on client)
- Update status enum: remove `pending | failed`, add `draft`, rename `sent → distributed`
- Rename `pdf_file_path → file_path`, `pdf_file_size → file_size`, `pdf_hash → file_hash`
- Add `payslip_number`, `signature_hash`, `qr_code_data` to the TS interface
- Remove per-channel distribution tracking fields (`email_sent`, `printed_at`, etc.) — DB only stores `distribution_method` + `distributed_at`
- Add `ytd_sss`, `ytd_philhealth`, `ytd_pagibig`, `ytd_tax` — replace `ytd_deductions` with individual YTD fields
- Fix controller validation: `printed → print` (matches DB enum)

---

### 4. Payment Tracking (`PayrollPayment` ↔ `PaymentTrackingController` ↔ `PaymentTrackingPageProps`)

#### Controller issues
- Returns `getMockPayments()` — `PayrollPayment` model never queried
- `confirm()` validates `unique:payment_confirmations` — that table **does not exist** (audit logs replaced it)
- `markPaid()` / `retry()` / `changeMethod()` all return back() with no DB update
- `payment_methods` prop is hardcoded as `['bank_transfer', 'cash', 'check']` — should come from `PaymentMethod::enabled()`
- No `check` method exists in the payment methods table

#### TypeScript ↔ Model field mismatches

| TS field (`PaymentTracking`) | DB column (`payroll_payments`) | Issue |
|---|---|---|
| `payment_method: 'bank_transfer' \| 'cash' \| 'check'` | `payment_method_id` FK → `payment_methods.method_type: 'cash' \| 'bank' \| 'ewallet'` | **Completely different type values** — `bank_transfer → bank`, no `check` |
| `payment_status: 'pending' \| 'processing' \| 'paid' \| 'failed'` | `status: 'pending' \| 'processing' \| 'paid' \| 'partially_paid' \| 'failed' \| 'cancelled' \| 'unclaimed'` | **DB has more values (`partially_paid`, `cancelled`, `unclaimed`)** |
| `payment_method_icon: 'bank' \| 'cash' \| 'check'` | Not stored — derived from PaymentMethod | Computed field |
| `payment_method_label` | Not stored — derived from `payment_methods.display_name` | Computed field (fine via JOIN) |
| `payment_confirmation_file` | `claimed_by_signature` (cash only) / `provider_response` JSON (bank/ewallet) | **Different field names** |
| `failure_code` (on `FailedPayment`) | `failure_reason` only — no `failure_code` column in DB | **Column doesn't exist** |
| `max_retries` | Not in DB — hardcoded limit of 3 (Decision #12) | **Computed constant, not DB column** |
| `next_retry_date` | `last_retry_at` exists, `next_retry_at` does NOT exist in DB | **Column doesn't exist** |
| `alternative_methods` array | Not in DB | **Computed from enabled PaymentMethods** |
| `FailedPayment.failure_timestamp` | `failed_at` | **Different field name** |
| `PaymentStatusSummary.formatted_*` fields (pre-formatted strings) | Not in DB — formatting should happen in controller or frontend | Minor — acceptable pattern |

#### What needs to change
- Update `payment_method` enum: `bank_transfer → bank`, remove `check`, add `ewallet`
- Update `payment_status` enum to add `partially_paid | cancelled | unclaimed`
- Rename `failure_timestamp → failed_at` in `FailedPayment`
- Remove `failure_code` (not in DB) or add it as nullable computed from `provider_response`
- Remove `next_retry_date` or compute it from `last_retry_at + backoff` in the controller
- `max_retries` should be a constant (3) returned by the controller, not a per-record DB value
- Fix `confirm()` unique validation — remove `unique:payment_confirmations`, use `payment_reference` field on `payroll_payments`
- Wire `payment_methods` from `PaymentMethod::enabled()->ordered()->get()`

---

## 🗂️ Summary of All Field Renames Required

| Location | Current TS name | Correct DB name |
|---|---|---|
| `BankPayrollFile` | `uploaded_at` | `submitted_at` |
| `BankPayrollFile` | `uploaded_by` | `submitted_by` (join to user name) |
| `BankPayrollFile` | `confirmation_number` | `bank_confirmation_number` |
| `BankPayrollFile` | `generated_at` | `created_at` |
| `Payslip` | `payroll_calculation_id` | `payroll_payment_id` |
| `Payslip` | `pdf_file_path` | `file_path` |
| `Payslip` | `pdf_file_size` | `file_size` |
| `Payslip` | `pdf_hash` | `file_hash` |
| `FailedPayment` | `failure_timestamp` | `failed_at` |

---

## ⚠️ Enum Mismatches (Must Fix Before Wiring Controllers)

| Model field | Frontend enum | DB enum |
|---|---|---|
| `BankFileBatch.status` | `generated \| uploaded \| processed \| confirmed \| failed` | `draft \| ready \| submitted \| processing \| completed \| partially_completed \| failed` |
| `Payslip.status` | `pending \| generated \| sent \| acknowledged \| failed` | `draft \| generated \| distributed \| acknowledged` |
| `PayrollPayment.status` | `pending \| processing \| paid \| failed` | `pending \| processing \| paid \| partially_paid \| failed \| cancelled \| unclaimed` |
| `PayrollPayment` method type | `bank_transfer \| cash \| check` | `bank \| cash \| ewallet` |
| `Payslip` distribution_method | `email \| portal \| printed` | `email \| portal \| print \| sms` |

---

## 🏗️ Architectural Mismatch (Requires Design Decision)

### Cash: Batch vs Per-Employee

The frontend models cash distribution as **per-employee records** (`CashEmployee`, `CashDistribution`), but the database models it as a **batch operation** (`cash_distribution_batches` with one row per payroll run + individual `payroll_payments` per employee).

**Recommended approach:**
- Keep the `CashEmployee` frontend type as a view model assembled by the controller from `payroll_payments JOIN employees WHERE method = cash`
- Rename `CashDistribution[] distributions` prop to `CashDistributionBatch[] batches` — one batch per period/run
- The controller builds the `CashEmployee[]` list fresh from `payroll_payments` for the period

---

## ✅ Recommended Fix Order (Phase 3 Pre-work)

Before writing any service layer, fix these in order to avoid double-work:

1. **Fix all TS enum values** in `payroll-pages.ts` to match DB enums (30 min)
2. **Fix all field renames** in `payroll-pages.ts` (30 min)
3. **Add missing TS fields** to each interface (`transfer_type`, `payslip_number`, `qr_code_data`, etc.)
4. **Resolve the Cash batch vs per-employee architectural mismatch** — update `CashPaymentPageProps` to use `CashDistributionBatch` type for the `distributions` prop
5. **Update controller stubs** to remove references to non-existent tables (`payment_confirmations`) and invalid validation rules
6. **Wire `payment_methods` controller prop** from `PaymentMethod::enabled()->ordered()->get()` — this is low-risk and can be done immediately since `PaymentMethod` model exists

---

## 📋 Phase 3 Readiness

| Service to Build | Blocked By |
|---|---|
| `CashDistributionService` | TS type mismatch (batch vs per-employee architectural fix needed first) |
| `BankFileGeneratorService` | Status enum mismatch must be fixed in TS |
| `PayslipGeneratorService` | FK rename (`payroll_calculation_id → payroll_payment_id`) + JSON earnings structure |
| `PaymentTrackingService` | Method type enum fix (`bank_transfer → bank`) |
| `PaymentAuditLog::record()` | ✅ Ready — no blockers, can be used immediately |

---

**Last Updated:** February 19, 2026  
**Status:** 🟡 Phase 3 blocked pending TS type fixes and enum alignment
