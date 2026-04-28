# Module Feature Flags Implementation Guide

This guide provides a step-by-step implementation plan for adding feature flags to the application. The goal is to easily toggle different modules (ATS, Payroll, System, Admin, etc.) so that you can exclusively deploy the **Employee Management Module** first and incrementally enable the rest as they are finalized.

## Phase 1: Environment & Configuration Setup

**Goal:** Establish the source of truth for the feature flags using environment variables.

### Task 1.1: Define Variables in Environment Files
Add the following feature flags to both `.env` and `.env.example`. This allows different environments (local, staging, production) to have different modules enabled or disabled easily.

```dotenv
# Module Feature Flags
MODULE_EMPLOYEE_ENABLED=true
MODULE_LEAVE_ENABLED=false
MODULE_DOCUMENTS_ENABLED=false
MODULE_ATS_ENABLED=false
MODULE_WORKFORCE_ENABLED=false
MODULE_TIMEKEEPING_ENABLED=false
MODULE_APPRAISALS_ENABLED=false
MODULE_OFFBOARDING_ENABLED=false
MODULE_PAYROLL_ENABLED=false
MODULE_ADMIN_ENABLED=false
MODULE_SYSTEM_ENABLED=false
```

### Task 1.2: Create the Configuration File
Create a new configuration file at `config/modules.php` to securely access these environment variables throughout the application.

```php
// config/modules.php
return [
    'employee'    => env('MODULE_EMPLOYEE_ENABLED', true),
    'leave'       => env('MODULE_LEAVE_ENABLED', false),
    'documents'   => env('MODULE_DOCUMENTS_ENABLED', false),
    'ats'         => env('MODULE_ATS_ENABLED', false),
    'workforce'   => env('MODULE_WORKFORCE_ENABLED', false),
    'timekeeping' => env('MODULE_TIMEKEEPING_ENABLED', false),
    'appraisals'  => env('MODULE_APPRAISALS_ENABLED', false),
    'offboarding' => env('MODULE_OFFBOARDING_ENABLED', false),
    'payroll'     => env('MODULE_PAYROLL_ENABLED', false),
    'admin'       => env('MODULE_ADMIN_ENABLED', false),
    'system'      => env('MODULE_SYSTEM_ENABLED', false),
];
```

## Phase 2: Route Protection (Backend Security)

**Goal:** Prevent backend access to disabled module endpoints. Even if a user knows the URL, they should not be able to interact with disabled features.

### Task 2.1: Create a Feature Flag Middleware
Generate a middleware to guard routes.
```bash
php artisan make:middleware CheckModuleEnabled
```

```php
// app/Http/Middleware/CheckModuleEnabled.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckModuleEnabled
{
    public function handle(Request $request, Closure $next, $module)
    {
        if (!config("modules.{$module}")) {
            abort(404, "This module is currently disabled.");
        }
        
        return $next($request);
    }
}
```

### Task 2.2: Register the Middleware
If you are on Laravel 10 or below, register it in `app/Http/Kernel.php` under `$routeMiddleware` (or `$middlewareAliases`):
```php
'module' => \App\Http\Middleware\CheckModuleEnabled::class,
```
*(If using Laravel 11, register it inside `bootstrap/app.php` using `->aliasMiddleware('module', CheckModuleEnabled::class)`)*

### Task 2.3: Apply Middleware to Route Files
Wrap your module-specific routes using the new middleware. For example, in `routes/web.php` or `routes/hr.php` for the ATS module:

```php
// HR ATS MODULE
Route::middleware(['auth', 'module:ats'])
    ->prefix('hr/ats')
    ->name('hr.ats.')
    ->group(function () {
        // ... all ATS routes ...
    });
```
*Repeat this for the other route files (`admin.php`, `payroll.php`, `system.php`), applying `middleware('module:admin')` and so forth.*

## Phase 3: Frontend UI Toggling (Inertia)

**Goal:** Hide navigation links, buttons, and dashboard widgets for disabled modules so the user experience is clean and unbroken.

### Task 3.1: Share Flags with the Frontend
Open `app/Http/Middleware/HandleInertiaRequests.php` and share the configuration globally so the frontend knows which features are active.

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        // existing shared props...
        'features' => config('modules'),
    ]);
}
```

### Task 3.2: Conditionally Render Navigation Links
In your frontend layout (e.g., your Sidebar component in Vue/React/Svelte), check the shared prop before rendering links to other modules.

*Vue/Blade Example:*
```vue
<template>
  <nav>
    <!-- Employee Module (Always visible if true) -->
    <Link v-if="$page.props.features.employee" href="/employees">Employees</Link>
    
    <!-- ATS Module (Hidden for now) -->
    <Link v-if="$page.props.features.ats" href="/hr/ats/job-postings">Applicant Tracking System</Link>
    
    <!-- Payroll Module (Hidden for now) -->
    <Link v-if="$page.props.features.payroll" href="/payroll">Payroll</Link>
  </nav>
</template>
```

### Task 3.3: Hide Dashboard Widgets
If your `DashboardController` queries aggregate data (like open job postings, pending payroll), wrap those queries in backend conditionals (`if (config('modules.ats')) { ... }`) to prevent unnecessary database queries. Then, hide the corresponding UI cards on the frontend using your `$page.props.features` checks.

## Phase 4: Background Jobs & Schedulers

**Goal:** Prevent automated tasks (like daily payroll calculation or ATS reminder emails) from executing when the underlying module is disabled.

### Task 4.1: Update the Console Kernel
In `app/Console/Kernel.php` (or `routes/console.php`), wrap your scheduled tasks in a truth test:

```php
protected function schedule(Schedule $schedule)
{
    if (config('modules.payroll')) {
        $schedule->command('payroll:process')->monthly();
    }
    
    if (config('modules.ats')) {
        $schedule->command('ats:send-reminders')->daily();
    }
}
```

## Phase 5: Deployment Strategy

1. **Deploy Initial Code:** Push this feature flag architecture to your codebase.
2. **Configure Production Environment:** In your production `.env` file, ensure only the Employee Management Module is active:
   ```dotenv
   MODULE_EMPLOYEE_ENABLED=true
   MODULE_LEAVE_ENABLED=false
   MODULE_DOCUMENTS_ENABLED=false
   MODULE_ATS_ENABLED=false
   MODULE_WORKFORCE_ENABLED=false
   MODULE_TIMEKEEPING_ENABLED=false
   MODULE_APPRAISALS_ENABLED=false
   MODULE_OFFBOARDING_ENABLED=false
   MODULE_PAYROLL_ENABLED=false
   MODULE_ADMIN_ENABLED=false
   MODULE_SYSTEM_ENABLED=false
   ```
3. **Local Development:** When your team is ready to fix the ATS module or Payroll module, they simply set `MODULE_ATS_ENABLED=true` in their local `.env`.
4. **Staging/QA:** Once a module is fixed and ready for review, enable it on the Staging server's `.env` for testing.
5. **Production Release:** Finally, update the production `.env` to enable the module (e.g., `MODULE_ATS_ENABLED=true`). This instantly makes the feature available to live users without requiring a code push or triggering unexpected downtime.
