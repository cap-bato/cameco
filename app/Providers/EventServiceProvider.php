<?php

namespace App\Providers;

use App\Events\Payroll\EmployeePayrollCalculated;
use App\Events\Payroll\PayrollCalculationCompleted;
use App\Events\Payroll\PayrollCalculationFailed;
use App\Events\Payroll\PayrollCalculationStarted;
use App\Events\Payroll\PayrollPeriodCreated;
use App\Listeners\Payroll\LogPayrollCalculation;
use App\Listeners\Payroll\NotifyPayrollOfficer;
use App\Listeners\Payroll\UpdatePayrollProgress;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        PayrollPeriodCreated::class => [
            LogPayrollCalculation::class,
        ],
        PayrollCalculationStarted::class => [
            LogPayrollCalculation::class,
        ],
        EmployeePayrollCalculated::class => [
            UpdatePayrollProgress::class,
            LogPayrollCalculation::class,
        ],
        PayrollCalculationCompleted::class => [
            NotifyPayrollOfficer::class,
            LogPayrollCalculation::class,
        ],
        PayrollCalculationFailed::class => [
            LogPayrollCalculation::class,
        ],
    ];

    /**
     * Discover and register events and listeners using attribute routing.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
