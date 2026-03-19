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
