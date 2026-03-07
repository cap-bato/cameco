# Timekeeping → Payroll: Gap Implementation Index

**Updated:** auto-generated from codebase analysis  
**Phase completion tracker for the full timekeeping → payroll pipeline**

---

## Pipeline Overview

```
AttendanceEvent (RFID/manual)
    ↓
[GenerateDailySummariesCommand] (daily cron, 23:59)
    ↓
DailyAttendanceSummary (is_finalized = false)
    ↓
[FinalizeAttendanceForPeriodCommand] ← GAP 1
    ↓
DailyAttendanceSummary (is_finalized = true)
    ↓
[CalculatePayrollJob] → [CalculateEmployeePayrollJob × N]
    ↓                           ↓ (fixed via GAP 5: Bus::batch)
    ↓               EmployeePayrollCalculation rows
    ↓
[FinalizePayrollJob] (runs after batch completes — GAP 5)
    ↓
PayrollPeriod.status = 'finalized'
    ↓
[NotifyPayrollOfficer] ← GAP 8
    ↓
Payroll officers notified → review → approve
```

---

## Gap Summary Table

| # | Gap File | Priority | Status | Affects |
|---|---|---|---|---|
| 1 | [GAP1_FINALIZE_ATTENDANCE_COMMAND.md](GAP1_FINALIZE_ATTENDANCE_COMMAND.md) | 🔴 Critical | ✅ Done 2026-03-06 | Pipeline start |
| 2 | [GAP2_TIMEKEEPING_SEEDER_FIX.md](GAP2_TIMEKEEPING_SEEDER_FIX.md) | 🟠 Medium | ⬜ Not started | Testing/seeding |
| 3 | [GAP3_ATTENDANCE_CORRECTION_SUMMARY_UPDATE.md](GAP3_ATTENDANCE_CORRECTION_SUMMARY_UPDATE.md) | 🟠 Medium | ⬜ Not started | Data accuracy |
| 4 | [GAP4_PROGRESS_PERCENTAGE_MIGRATION.md](GAP4_PROGRESS_PERCENTAGE_MIGRATION.md) | 🟠 Medium | ⬜ Not started | Progress tracking |
| 5 | [GAP5_BUS_BATCH_FINALIZE.md](GAP5_BUS_BATCH_FINALIZE.md) | 🟠 Medium | ⬜ Not started | Reliability (50+ employees) |
| 6 | [GAP6_DUPLICATE_EXCEPTION_RECORDS.md](GAP6_DUPLICATE_EXCEPTION_RECORDS.md) | 🟡 Low | ⬜ Not started | Data integrity |
| 7 | [GAP7_LOG_CALCULATION_STATUS_COLUMN.md](GAP7_LOG_CALCULATION_STATUS_COLUMN.md) | 🟡 Low | ⬜ Not started | Observability |
| 8 | [GAP8_NOTIFY_PAYROLL_OFFICER.md](GAP8_NOTIFY_PAYROLL_OFFICER.md) | 🟡 Low | ⬜ Not started | Notifications |

---

## Recommended Implementation Order

### Sprint 1 — Pipeline Correctness (Critical + Data Accuracy)

1. **GAP 4** first — add `progress_percentage` migration + model update  
   *(No code dependencies; DB migration is safe to run anytime)*

2. **GAP 7** next — fix `calculation_status` column name typo in listener  
   *(One-line fix; zero risk)*

3. **GAP 6** — fix duplicate exception records  
   *(Refactor `CalculateEmployeePayrollJob` catch/failed pattern)*

4. **GAP 1** — implement `FinalizeAttendanceForPeriodCommand`  
   *(Critical path: without this, `is_finalized` is never set → payroll finds no rows)*

### Sprint 2 — Data Quality + Testing

5. **GAP 3** — wire `AttendanceCorrectionController::approve()` summary update  
   *(Needs `correction_applied` migration from step)*

6. **GAP 2** — fix `TimekeepingTestDataSeeder` + create standalone seeders  
   *(Improves test data quality; safe to run in dev)*

### Sprint 3 — Scalability + Reliability

7. **GAP 5** — replace 30s delay with `Bus::batch()`  
   *(Requires `job_batches` table migration; larger refactor)*

8. **GAP 8** — implement `NotifyPayrollOfficer` real notifications  
   *(Requires `notifications` table + new Notification class)*

---

## Cross-Cutting Notes

### Migrations Required (run in this order)

```bash
# GAP 4
php artisan migrate  # progress_percentage on payroll_periods

# GAP 3
php artisan migrate  # correction_applied on daily_attendance_summary

# GAP 5
php artisan queue:batches-table
php artisan migrate  # job_batches + calculation_batch_id on payroll_periods

# GAP 8
php artisan notifications:table
php artisan migrate  # notifications table
```

### Files Modified / Created (consolidated)

| File | Gap(s) | Action |
|---|---|---|
| `app/Console/Commands/Timekeeping/FinalizeAttendanceForPeriodCommand.php` | 1 | CREATE |
| `app/Http/Controllers/HR/Timekeeping/AttendanceFinalizeController.php` | 1 | CREATE |
| `app/Http/Controllers/HR/Timekeeping/AttendanceCorrectionController.php` | 3 | MODIFY |
| `app/Jobs/Payroll/CalculatePayrollJob.php` | 4, 5 | MODIFY |
| `app/Jobs/Payroll/CalculateEmployeePayrollJob.php` | 6 | MODIFY |
| `app/Listeners/Payroll/UpdatePayrollProgress.php` | 4 | VERIFY (no change if column added) |
| `app/Listeners/Payroll/LogPayrollCalculation.php` | 7 | MODIFY (1 line) |
| `app/Listeners/Payroll/NotifyPayrollOfficer.php` | 8 | MODIFY |
| `app/Models/PayrollPeriod.php` | 4, 5 | MODIFY |
| `app/Models/DailyAttendanceSummary.php` | 3 | MODIFY |
| `app/Notifications/Payroll/PayrollCalculationCompletedNotification.php` | 8 | CREATE |
| `database/seeders/DailyAttendanceSummarySeeder.php` | 2 | CREATE |
| `database/seeders/EmployeePayrollInfoSeeder.php` | 2 | CREATE |
| `database/seeders/TimekeepingTestDataSeeder.php` | 2 | MODIFY |
| `database/seeders/PayrollCalculationTestSeeder.php` | 2 | MODIFY |
| `database/seeders/DatabaseSeeder.php` | 2 | MODIFY |
| `routes/hr.php` | 1 | MODIFY (finalize-attendance routes) |
