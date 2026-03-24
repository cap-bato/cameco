# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SyncingSteel — an on-premise Enterprise HRIS for manufacturing/industrial use. Built with Laravel 12 + Inertia.js (React 19 + TypeScript). Covers HR lifecycle, payroll (Philippine government contributions), RFID timekeeping, ATS, and workforce scheduling.

## Commands

### Development
```bash
composer run dev       # Starts all 4 processes concurrently: Laravel server, queue listener, Pail logger, Vite dev server
npm run dev            # Vite dev server only (port 5173)
php artisan serve      # Laravel server only (port 8000)
```

### Setup
```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
```

### Build
```bash
npm run build          # Production Vite build (runs wayfinder:generate first)
npm run types          # TypeScript type check (no emit)
```

### Lint & Format
```bash
npm run lint           # ESLint with auto-fix
npm run format         # Prettier formatting
npm run format:check   # Check formatting without changes
vendor/bin/pint        # PHP code formatting (PSR-12)
```

### Testing
```bash
php artisan test                    # Run all PHPUnit tests
php vendor/bin/phpunit              # Alternative runner
php artisan test --filter TestName  # Run single test
```

Tests use an in-memory SQLite database (configured in `phpunit.xml`). No frontend test suite is enforced.

## Architecture

### Backend (`app/`)
Thin controllers delegating to service classes. Domain services live in `app/Services/`:

- **`HR/`** — Employee lifecycle, document management, onboarding
- **`Payroll/`** — Core payroll calculation engine, government contributions (SSS, PhilHealth, Pag-IBIG, BIR), salary components, loans, and payslip PDF generation
- **`Timekeeping/`** — RFID ledger polling, attendance summaries, event replay with hash-verified immutable ledger
- **`LeaveManagementService.php`** — Accrual, balances, multi-level approvals

`app/Models/` has 60+ Eloquent models. `app/Http/Controllers/` is organized by domain matching `app/Services/`.

Routes are split by domain: `routes/web.php`, `routes/payroll.php`, etc.

### Frontend (`resources/js/`)
Inertia.js pages — no separate API layer; data flows through Inertia props.

- **`pages/`** — One directory per domain (`HR/`, `Payroll/`, `Admin/`, `Employee/`)
- **`components/`** — Shared Radix UI + Tailwind components
- **`types/`** — TypeScript definitions; import from here, not inline
- **`wayfinder/`** — Auto-generated route helpers (`php artisan wayfinder:generate`)
- Path alias `@/` maps to `resources/js/`

UI stack: Radix UI (headless), Tailwind CSS 4, Recharts, lucide-react, sonner (toasts), date-fns.

### Database
- Development: SQLite (`database/database.sqlite`)
- Production: MySQL/PostgreSQL via `.env`
- Permissions: Spatie Laravel Permission (roles: Superadmin, Office Admin, HR Manager, HR Staff, Payroll Officer)
- Audit trail: Spatie Activity Log on all significant models

### Payroll PDF Generation
Server-side PDFs use DomPDF via Blade templates. Pattern: controller in `app/Http/Controllers/Payroll/Payments/`, route in `routes/payroll.php`, Blade template in `resources/views/`. Add docblocks to public PHP methods and header comments to PDF Blade templates.

## Key Conventions

- **PHP**: PSR-12 style, type-hinted methods and return types. Keep controllers thin; business logic in services.
- **TypeScript/React**: Functional components and hooks. Use types from `resources/js/types/`. Use `@/` path aliases.
- **Commits**: Conventional commits tied to issue numbers — `feat(#42): description`, `fix(#42): description`
- **Stray debug statements**: Remove all `console.log`, `dd()`, `dump()` before committing.

## Issue Planning Workflow

When working on a GitHub issue:
1. Check for `.aiplans/ISSUE-<number>.md` (local only, gitignored). Initialize with `pwsh ./scripts/init-plan.ps1 -IssueNumber <n>` if missing.
2. Resolve any open questions in the "Clarifications" section before implementing.
3. Work phase by phase; update the plan file with ✅ markers after each phase.
4. Archive after merge: `mv .aiplans/ISSUE-<n>.md .aiplans/archive/`

## CI/CD

Two GitHub Actions workflows run on push/PR to `main`/`develop`:
- **lint.yml** — PHP Pint, Prettier, ESLint
- **tests.yml** — Full PHPUnit suite (PHP 8.4, Node 22, xdebug coverage)

## Work Log

### Phase 4, Task 4.2 — Employee Create Leave Request Page ✅ COMPLETE
**Date:** December 19, 2024  

**Objective:** Update the Employee Create Leave Request page to support leave request variants (full day, half-day AM, half-day PM) for Sick Leave.

**Implementation:**
1. Added `LeaveVariant` interface to type the variant data
2. Added `leaveVariants` prop to `CreateRequestProps` and destructured in component
3. Created `selectedVariant` state and integrated into days calculation logic
4. Added conditional rendering of Leave Duration selector (only shows for Sick Leave)
5. Implemented variant options: Full Day (1.0 days), Half Day AM (0.5 days), Half Day PM (0.5 days)
6. Added help text explaining half-day leave impact
7. Updated form submission to include variant data for half-day requests
8. Verified `leaveVariants` passed from Employee LeaveController

**Files Modified:**
- [resources/js/pages/Employee/Leave/CreateRequest.tsx](resources/js/pages/Employee/Leave/CreateRequest.tsx)

**Verification Script:** [verify-phase4-task4-2.php](verify-phase4-task4-2.php) — All 12 checks passed ✅

---

### Phase 4, Task 4.3 — Leave Balances Display Pages ✅ COMPLETE
**Date:** December 19, 2024  

**Objective:** Update Employee and HR leave balances display pages to show variant information for half-day leave requests.

**Implementation:**

**Employee Leave Balances Page:**
1. Added condition to detect Sick Leave (SL code)
2. Added clarification note: "Includes full day and half-day options"
3. Explains half-day AM/PM options with 0.5 day deduction
4. Displayed with visual indicator (💡) for better visibility

**HR Leave Requests Page:**
1. Updated `LeaveRequest` interface to include `leave_type_variant` property
2. Created `getVariantLabel()` helper function to map variant codes to readable labels
3. Created `formatLeaveTypeWithVariant()` function to display "Leave Type (Variant)"
4. Updated table display to show variant in parentheses

**HR Leave Request Detail Modal:**
1. Updated modal interface to include `leave_type_variant` property
2. Added variant formatting helper functions
3. Updated modal display to show variant information alongside leave type

**Files Modified:**
- [resources/js/pages/Employee/Leave/Balances.tsx](resources/js/pages/Employee/Leave/Balances.tsx)
- [resources/js/pages/HR/Leave/Requests.tsx](resources/js/pages/HR/Leave/Requests.tsx)
- [resources/js/components/hr/leave-request-action-modal.tsx](resources/js/components/hr/leave-request-action-modal.tsx)

**Verification Script:** [verify-phase4-task4-3.php](verify-phase4-task4-3.php) — All 10 checks passed ✅

---

### Half-Day Leave UX Fix — Single Day Selection Only ✅ COMPLETE
**Date:** December 19, 2024  

**Issue Identified:** 
When users selected a half-day variant (AM or PM), they could still set different start and end dates, allowing illogical requests like "half-day AM on Monday and half-day PM on Friday" spanning multiple days.

**Solution Implemented:**
Enforced single-day selection when half-day variant is active across all leave request pages.

**HR Leave CreateRequest Page:**
1. Added `isHalfDayVariant` computed flag to detect half-day selection
2. Added `useEffect` to automatically sync end_date to start_date when variant changes
3. Updated label from "Start Date" to "Leave Date" when half-day selected
4. Hidden the end date input field when half-day variant is active
5. Added visual indicator showing variant (Half Day AM or PM) with day count
6. Added helper text: "Half-day leave is for a single day only"

**Employee Leave CreateRequest Page:**
1. Added auto-sync `useEffect`: when selectedVariant is half-day, endDate = startDate
2. Dynamic labels: "Leave Date" instead of "Start Date" for half-day requests
3. Conditional rendering: end date input hidden for half-day variants
4. Visual display of variant with days (0.5 days)
5. Helper text explaining single-day nature of half-day leave

**Files Modified:**
- [resources/js/pages/HR/Leave/CreateRequest.tsx](resources/js/pages/HR/Leave/CreateRequest.tsx)
- [resources/js/pages/Employee/Leave/CreateRequest.tsx](resources/js/pages/Employee/Leave/CreateRequest.tsx)

**Verification Script:** [verify-half-day-single-date.php](verify-half-day-single-date.php) — All 12 checks passed ✅
