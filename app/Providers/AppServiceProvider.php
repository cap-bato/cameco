<?php

namespace App\Providers;

use App\Repositories\Contracts\HR\EmployeeRepositoryInterface;
use App\Repositories\Contracts\HR\ProfileRepositoryInterface;
use App\Repositories\Contracts\System\CronRepositoryInterface;
use App\Repositories\Contracts\System\SystemHealthRepositoryInterface;
use App\Repositories\Contracts\System\SuperadminSLARepositoryInterface;
use App\Repositories\Eloquent\HR\EmployeeRepository;
use App\Repositories\Eloquent\HR\ProfileRepository;
use App\Repositories\Eloquent\System\CronRepository;
use App\Repositories\Eloquent\System\SystemHealthRepository;
use App\Repositories\Eloquent\System\SuperadminSLARepository;
use App\Services\Social\FacebookService;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\URL;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind System Health Repository
        $this->app->bind(
            SystemHealthRepositoryInterface::class,
            SystemHealthRepository::class
        );

        // Bind Superadmin SLA Repository
        $this->app->bind(
            SuperadminSLARepositoryInterface::class,
            SuperadminSLARepository::class
        );

        // Bind Cron Repository
        $this->app->bind(
            CronRepositoryInterface::class,
            CronRepository::class
        );

        // Bind HR Module Repositories
        $this->app->bind(
            EmployeeRepositoryInterface::class,
            EmployeeRepository::class
        );

        $this->app->bind(
            ProfileRepositoryInterface::class,
            ProfileRepository::class
        );

        $this->app->bind(
        \App\Repositories\Contracts\System\Organization\SystemOnboardingRepositoryInterface::class,
        \App\Repositories\Eloquent\System\Organization\SystemOnboardingRepository::class
         );

         $this->app->bind(
            \App\Repositories\Contracts\System\User\UserOnboardingRepositoryInterface::class,
            \App\Repositories\Eloquent\System\User\UserOnboardingRepository::class
         );

         // Register FacebookService as singleton
         $this->app->singleton(FacebookService::class, function ($app) {
             return new FacebookService();
         });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    if (app()->environment('production') && request()->header('x-forwarded-proto') === 'https') {
        URL::forceScheme('https');
    }        
    }
}

