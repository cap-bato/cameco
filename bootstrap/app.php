<?php

use App\Http\Middleware\EnsureEmployee;
use App\Http\Middleware\EnsureHRAccess;
use App\Http\Middleware\EnsureHRManager;
use App\Http\Middleware\EnsureOfficeAdmin;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/system.php',
            __DIR__.'/../routes/hr.php',
            __DIR__.'/../routes/payroll.php',
            __DIR__.'/../routes/admin.php',
            __DIR__.'/../routes/employee.php',
            __DIR__.'/../routes/settings.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'superadmin' => EnsureSuperadmin::class,
            'hr.manager' => EnsureHRManager::class,
            'hr.access' => EnsureHRAccess::class,
            'office.admin' => EnsureOfficeAdmin::class,
            'employee' => EnsureEmployee::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
