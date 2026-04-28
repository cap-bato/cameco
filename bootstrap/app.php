<?php

use App\Http\Middleware\EnsureEmployee;
use App\Http\Middleware\EnsureHRAccess;
use App\Http\Middleware\EnsureHRManager;
use App\Http\Middleware\EnsureOfficeAdmin;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ValidateTimekeepingApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/api.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // RFID gate PC endpoints are called by the Python client with no browser session
        $middleware->validateCsrfTokens(except: [
            'rfid/*',
        ]);

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
            'timekeeping.api' => ValidateTimekeepingApiKey::class,
            'module' => \App\Http\Middleware\CheckModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            // Skip expected validation errors and client-side HTTP errors.
            if ($e instanceof ValidationException) {
                return;
            }

            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return;
            }

            try {
                if (!Schema::hasTable('system_error_logs')) {
                    return;
                }

                $request = request();

                $level = 'error';
                if ($e instanceof \Error) {
                    $level = 'critical';
                } elseif ($e instanceof HttpExceptionInterface && $e->getStatusCode() >= 500) {
                    $level = 'critical';
                }

                \App\Models\SystemErrorLog::create([
                    'level' => $level,
                    'message' => Str::limit($e->getMessage() ?: class_basename($e), 250),
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString(),
                    'file' => $e->getFile() ? Str::limit($e->getFile(), 250, '') : null,
                    'line' => $e->getLine(),
                    'url' => $request?->fullUrl(),
                    'method' => $request?->method(),
                    'ip_address' => $request?->ip(),
                    'user_id' => Auth::id(),
                    'context' => [
                        'environment' => app()->environment(),
                        'status_code' => $e instanceof HttpExceptionInterface ? $e->getStatusCode() : null,
                    ],
                    'is_resolved' => false,
                ]);
            } catch (Throwable $loggingException) {
                // Avoid recursive failures in the exception handler.
            }
        });
    })->create();
