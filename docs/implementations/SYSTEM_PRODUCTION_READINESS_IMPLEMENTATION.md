# System Production Readiness Implementation

**Date:** 2026-03-13  
**Status:** In Progress  
**Scope:** Full app readiness for realistic production behavior (data, scheduler, queue, seeders, and operational safety)

---

## Summary

This implementation file defines the concrete work needed to make the system behave like a complete, production-ready deployment while keeping local development fast and deterministic.

The goal is not only to make pages render, but to ensure:
- Seeded data matches real command/module behavior
- Scheduler and cron UI show true execution state
- Queue-backed flows work consistently
- Setup is repeatable and idempotent
- Health and audit signals are trustworthy

---

## Acceptance Criteria

- [ ] Fresh setup completes with one command sequence and no fatal errors
- [ ] Superadmin can log in and navigate all major modules without crash
- [ ] HR Staff can log in and access HR pages allowed by role
- [ ] HR Manager can log in and access approval and management flows
- [ ] Office Admin can log in and access office-admin scoped modules only
- [ ] Employee can log in and access employee self-service modules only
- [ ] Payroll Officer can log in and execute payroll workflows without permission errors
- [ ] `scheduled_jobs` rows map to valid Artisan commands only
- [ ] All scheduled commands in `routes/console.php` are visible in `schedule:list`
- [ ] Manual run from Superadmin Cron UI updates `last_run_at`, `last_exit_code`, run counters
- [ ] Scheduler-driven runs also update the same cron tracking fields
- [ ] Queue worker processes timekeeping/payroll/offboarding jobs without dead letters
- [ ] Seeder chain is deterministic, idempotent, and free of class-name mismatches
- [ ] No interactive command prompt blocks web-triggered or scheduler-triggered command execution
- [ ] Key maintenance commands run in `--no-interaction` mode without errors

---

## Role Coverage Matrix

The production-like seeded environment must include the following role personas and baseline access:

- Superadmin:
  - System dashboard, cron management, health, backups, patches, security audit, user lifecycle, role/policy/IP management
- HR Staff:
  - Employee records, attendance/timekeeping operations, leave operations, document workflows
- HR Manager:
  - HR Staff capabilities plus approval-centric and managerial views
- Office Admin:
  - Office-admin scoped operations and limited admin utilities
- Employee:
  - Self-service views (profile, personal documents/requests, personal attendance/payslip access where applicable)
- Payroll Officer:
  - Payroll period execution, payroll calculations, payroll approvals/review flows as configured

---

## Role Seeder Baseline

The following seeders should collectively produce a usable multi-role system state:

- `RolesAndPermissionsSeeder`
- `ATSPermissionsSeeder`
- `TimekeepingPermissionsSeeder`
- `BadgeManagementPermissionsSeeder`
- `WorkforceManagementPermissionsSeeder`
- `DocumentManagementPermissionsSeeder`
- `PayrollPermissionsSeeder`
- `OffboardingPermissionsSeeder`
- `OfficeAdminSeeder`
- `EmployeeRoleSeeder`
- `PayrollOfficerAccountSeeder`
- `HRStaffAccountSeeder`
- `EmployeeAccountSeeder`

Implementation notes:

- [ ] Confirm each role exists in DB and has expected permission set
- [ ] Confirm each seeded account is assigned the intended role(s)
- [ ] Confirm no role is left without a usable login account in non-production seed profile
- [ ] Confirm permission-gated frontend routes do not expose unauthorized modules

---

## Current Gaps To Close

- [ ] Seeder naming mismatch in `DatabaseSeeder`: references `ScheduledJobsSeeder` while actual class/file is `ScheduledJobSeeder`
- [ ] Potential duplicate responsibility between cron-related seeders (must define one source of truth)
- [ ] Some command outputs include non-ASCII symbols that can break DB write on Windows PG client encoding
- [ ] Some commands were historically written with interactive prompts, causing `STDIN` failures in web/scheduler contexts
- [ ] Historical cron data may still show old failure counts from pre-fix runs

---

## Phase 1 - Seeder Hardening (P0)

### Objective
Ensure seeded state is internally consistent and always bootable.

### Implementation
- [ ] Fix class reference mismatch in `database/seeders/DatabaseSeeder.php`:
  - Replace `ScheduledJobsSeeder` check/call with `ScheduledJobSeeder`
  - Or remove it if intentionally deprecated
- [ ] Keep `CronJobSeeder` as authoritative source for `scheduled_jobs`
- [ ] Ensure all critical seeders are idempotent (`firstOrCreate`, `updateOrCreate`, or guarded inserts)
- [ ] Verify seeded cron commands exactly match scheduled commands and real registered Artisan signatures
- [ ] Re-seed with `migrate:fresh --seed` and verify no class-not-found warnings
- [ ] Validate role/account seeders create all required personas (Superadmin, HR Staff, HR Manager, Office Admin, Employee, Payroll Officer)

### Validation
- [ ] `php artisan migrate:fresh --seed` exits `0`
- [ ] Superadmin + HR users exist with expected roles
- [ ] `scheduled_jobs` contains only valid commands
- [ ] Role/account matrix is complete and login-capable for all required personas

---

## Phase 1B - RBAC and Persona Readiness (P0)

### Objective
Ensure every key persona can actually use the system after seeding, with correct access boundaries.

### Implementation
- [ ] Build a role-to-module access checklist for:
  - Superadmin
  - HR Staff
  - HR Manager
  - Office Admin
  - Employee
  - Payroll Officer
- [ ] Verify backend middleware and policy checks align with seeded permissions
- [ ] Verify sidebar/menu rendering matches effective permissions for each persona
- [ ] Add/adjust missing seed data for role-dependent pages (dashboard cards, counts, workflow rows)

### Validation
- [ ] Each persona can sign in and load dashboard without runtime errors
- [ ] Each persona can access expected pages and is blocked from unauthorized pages
- [ ] Payroll Officer can run payroll actions without missing permission exceptions
- [ ] Employee role cannot access admin/system-only routes

---

## Phase 2 - Scheduler and Cron UI Consistency (P0)

### Objective
Make Cron UI metrics and status reflect actual runtime behavior.

### Implementation
- [ ] Keep scheduler callbacks in `routes/console.php` for success/failure recording
- [ ] Ensure each scheduled command calls runtime recorder with command signature + output + exit code
- [ ] Confirm all seeded jobs are actively scheduled:
  - `app:process-rfid-ledger`
  - `timekeeping:check-device-health`
  - `timekeeping:cleanup-deduplication-cache`
  - `timekeeping:generate-daily-summaries`
  - `leave:process-monthly-accrual`
  - `leave:process-year-end-carryover`
  - `documents:send-expiry-reminders`
  - `offboarding:reminders`
- [ ] Keep output sanitization before storing `last_output` to avoid encoding failures

### Validation
- [ ] `php artisan schedule:list` shows all expected jobs and valid cron expressions
- [ ] `php artisan schedule:run --no-interaction` runs due jobs successfully
- [ ] Cron UI no longer shows "Never" after actual run

---

## Phase 3 - Non-Interactive Command Safety (P0)

### Objective
Prevent command execution failures when triggered from web requests or scheduler.

### Implementation
- [ ] Require interactive confirmation only when both conditions are true:
  - running in console
  - input is interactive
- [ ] Replace custom `--verbose` command options that collide with Symfony built-in option
- [ ] Ensure maintenance commands support `--no-interaction`

### Validation
- [ ] Commands below run without `STDIN` errors:
  - `php artisan timekeeping:cleanup-deduplication-cache --no-interaction`
  - `php artisan leave:process-year-end-carryover --year=2025 --no-interaction`
  - `php artisan documents:send-expiry-reminders --dry-run --no-interaction`

---

## Phase 4 - Queue and Worker Reliability (P1)

### Objective
Guarantee background processing in dev and production.

### Implementation
- [ ] Confirm queue driver and migration state (`jobs`, `failed_jobs` tables)
- [ ] Run dedicated worker process (Supervisor in production)
- [ ] Add worker restart policy and memory/time limits
- [ ] Add dead-letter monitoring and alert threshold for `failed_jobs`

### Validation
- [ ] Queue job lifecycle observable (queued -> processing -> completed)
- [ ] Failed jobs retriable via `queue:retry`

---

## Phase 5 - Production Runtime Profile (P1)

### Objective
Ship realistic production behavior and observability.

### Implementation
- [ ] Confirm Caddy/Nginx + PHP-FPM + queue supervisor + scheduler cron are active
- [ ] Enforce environment-specific seed strategy:
  - dev/staging can include demo/mock-heavy seeders
  - production excludes non-essential mock records
- [ ] Enable log rotation and retention policy
- [ ] Add daily health report for cron, queue, DB, storage, and backup status

### Validation
- [ ] 24-hour burn-in run with no fatal scheduler/queue failures
- [ ] Dashboard health widgets remain green under nominal load

---

## Seeder Execution Profile

### Fresh Local Bootstrap (recommended)

```powershell
php artisan migrate:fresh --seed
php artisan storage:link
php artisan schedule:list
php artisan schedule:run --no-interaction
php artisan queue:work --tries=3 --timeout=120
```

### Production-Like Bootstrap (no destructive reset)

```powershell
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan schedule:list
```

---

## Data Quality Checks

- [ ] `scheduled_jobs.command` values all exist in `php artisan list --raw`
- [ ] `scheduled_jobs.next_run_at` populated for enabled jobs
- [ ] `scheduled_jobs.last_run_at` updates after scheduler/manual execution
- [ ] `rfid_card_mappings` has active UID mapping for target employees
- [ ] `daily_attendance_summary` generation works for active employees
- [ ] Each required role has at least one active login-capable user
- [ ] Role-permission mapping matches route middleware expectations

---

## Known Risks

- Historical `run_count/success_count/failure_count` may include old failures from before fixes
- DB client encoding differences (especially Windows + PostgreSQL) can break unfiltered command output storage
- Duplicated or outdated seeders can silently diverge from scheduler reality if not consolidated

---

## Recommended Immediate Fix Order

1. Resolve `ScheduledJobsSeeder` vs `ScheduledJobSeeder` mismatch in `DatabaseSeeder`
2. Keep one cron seeder strategy (`CronJobSeeder`) and remove/retire conflicting alternatives
3. Re-run seed and scheduler validation commands
4. Execute manual runs for all 8 cron jobs from UI and verify status updates
5. Run 24-hour scheduler/queue observation window and review logs/failures

---

## Definition of Done

The system is considered production-ready when:
- Seed/bootstrap is deterministic
- Scheduler is command-accurate
- Cron UI status is trustworthy
- Queue is stable
- No interactive command breaks automated execution
- No critical runtime errors appear in logs during the burn-in period
