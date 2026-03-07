# Gap 8 — `NotifyPayrollOfficer` Sends No Actual Notifications

**Status:** ⚠️ Stub implementation — listener loops officers but only logs  
**Priority:** 🟡 Low — payroll officers not alerted when calculation completes  

---

## The Problem

`app/Listeners/Payroll/NotifyPayrollOfficer.php` loops through payroll officers but  
does nothing other than log:

```php
foreach ($payrollOfficers as $officer) {
    // TODO: Send actual notification
    Log::info("Payroll officer {$officer->name} should be notified");
}
```

No email, no in-app notification, no database notification is dispatched.

---

## Phase 1 — Create a Notification Class

### Task 1.1 — Create `PayrollCalculationCompletedNotification`

**File:** `app/Notifications/Payroll/PayrollCalculationCompletedNotification.php`

```php
<?php

namespace App\Notifications\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayrollCalculationCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PayrollPeriod $payrollPeriod,
        public readonly int $totalEmployees,
        public readonly int $completedCount,
        public readonly int $failedCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $periodLabel = $this->payrollPeriod->name ?? $this->payrollPeriod->period_name
            ?? "Period #{$this->payrollPeriod->id}";

        return (new MailMessage)
            ->subject("Payroll Calculation Completed — {$periodLabel}")
            ->greeting("Hello, {$notifiable->name}!")
            ->line("The payroll calculation for **{$periodLabel}** has finished.")
            ->line("**Results:**")
            ->line("- Total employees: {$this->totalEmployees}")
            ->line("- Completed: {$this->completedCount}")
            ->line("- Failed/Exception: {$this->failedCount}")
            ->action('Review Payroll', url('/payroll/periods/' . $this->payrollPeriod->id))
            ->line('Please review the results before approving the payroll run.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'               => 'payroll_calculation_completed',
            'payroll_period_id'  => $this->payrollPeriod->id,
            'period_name'        => $this->payrollPeriod->name ?? "Period #{$this->payrollPeriod->id}",
            'total_employees'    => $this->totalEmployees,
            'completed_count'    => $this->completedCount,
            'failed_count'       => $this->failedCount,
            'url'                => '/payroll/periods/' . $this->payrollPeriod->id,
        ];
    }
}
```

> **Requires:** `php artisan notifications:table && php artisan migrate` for the  
> `notifications` table (if not already present).

---

## Phase 2 — Wire the Notification in the Listener

### Task 2.1 — Update `NotifyPayrollOfficer::handle()`

**File:** `app/Listeners/Payroll/NotifyPayrollOfficer.php`

Replace the loop body:

```php
// Old (stub):
foreach ($payrollOfficers as $officer) {
    Log::info("Payroll officer {$officer->name} should be notified");
}
```

With:

```php
// New (real notifications)
$completedCount = EmployeePayrollCalculation::where('payroll_period_id', $event->payrollPeriod->id)
    ->where('calculation_status', 'completed')
    ->count();

$failedCount = EmployeePayrollCalculation::where('payroll_period_id', $event->payrollPeriod->id)
    ->whereIn('calculation_status', ['failed', 'exception'])
    ->count();

$totalEmployees = $completedCount + $failedCount;

$notification = new \App\Notifications\Payroll\PayrollCalculationCompletedNotification(
    $event->payrollPeriod,
    $totalEmployees,
    $completedCount,
    $failedCount,
);

foreach ($payrollOfficers as $officer) {
    $officer->notify($notification);
}

Log::info("PayrollOfficer notifications sent", [
    'payroll_period_id' => $event->payrollPeriod->id,
    'officer_count'     => $payrollOfficers->count(),
    'completed'         => $completedCount,
    'failed'            => $failedCount,
]);
```

Add required `use` statements:

```php
use App\Models\EmployeePayrollCalculation;
use App\Notifications\Payroll\PayrollCalculationCompletedNotification;
```

---

## Phase 3 — Ensure `notifications` Table Exists

### Task 3.1 — Run Notifications Migration If Needed

```bash
php artisan notifications:table
php artisan migrate
```

Or check if `notifications` table already exists:

```bash
php artisan tinker --execute="Schema::hasTable('notifications') ? 'exists' : 'missing'"
```

---

## Phase 4 — Verify `User` Model Uses `Notifiable` Trait

### Task 4.1 — Check `User` Model

**File:** `app/Models/User.php`

Confirm the `Notifiable` trait is used:

```php
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
    // ...
}
```

If the user who is the "payroll officer" is accessed via a relationship  
(e.g., `$officer` is an `Employee` not a `User`), add `Notifiable` to `Employee` as well:

```php
// app/Models/Employee.php
use Illuminate\Notifications\Notifiable;

class Employee extends Model
{
    use Notifiable;

    // Required for mail: routeNotificationForMail() or use email attribute
    public function routeNotificationForMail(): string
    {
        return $this->email ?? $this->user?->email ?? '';
    }
}
```

---

## Summary of Changes

| File | Action |
|---|---|
| `app/Notifications/Payroll/PayrollCalculationCompletedNotification.php` | **CREATE** |
| `app/Listeners/Payroll/NotifyPayrollOfficer.php` | **MODIFY** — dispatch real notification |
| `app/Models/User.php` or `Employee.php` | **VERIFY** — `Notifiable` trait present |
| `database/migrations/…notifications_table.php` | **CREATE** via `php artisan notifications:table` |
