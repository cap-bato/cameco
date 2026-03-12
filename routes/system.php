<?php

/*
|--------------------------------------------------------------------------
| System Administration Routes
|--------------------------------------------------------------------------
|
| These routes are protected by the 'superadmin' middleware and are only
| accessible to users with the Superadmin role. All routes here require
| authentication and superadmin privileges.
|
*/

use Illuminate\Support\Facades\Route;

use \App\Http\Controllers\System\DashboardController;
use \App\Http\Controllers\System\SystemAdministration\HealthController;
use \App\Http\Controllers\System\SystemAdministration\BackupController;
use \App\Http\Controllers\System\SystemAdministration\PatchController;
use \App\Http\Controllers\System\SystemAdministration\SecurityAuditController;
use \App\Http\Controllers\System\Logs\ErrorLogController;
use \App\Http\Controllers\System\SystemAdministration\StorageController;
use \App\Http\Controllers\System\Security\RoleController;   
use \App\Http\Controllers\System\Security\UserLifecycleController;
use \App\Http\Controllers\System\Security\PolicyController;
use \App\Http\Controllers\System\Security\IPRuleController;
use \App\Http\Controllers\System\Organization\OverviewController;
use \App\Http\Controllers\System\Organization\DepartmentController;
use \App\Http\Controllers\System\Organization\PositionController;
use \App\Http\Controllers\System\Reports\UsageController;
use \App\Http\Controllers\System\Reports\SecurityController;
use \App\Http\Controllers\System\Reports\PayrollController;
use \App\Http\Controllers\System\Reports\ComplianceController;
use \App\Http\Controllers\System\UpdateController;
use \App\Http\Controllers\System\SystemAdministration\CronController;
use \App\Http\Controllers\System\SLAController;
use \App\Http\Controllers\System\SystemAdministration\VendorContractController;
use \App\Http\Controllers\System\Timekeeping\DeviceManagementController;


Route::middleware(['auth', 'superadmin'])->group(function () {

    // System Dashboard
    Route::get('/system/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:system.dashboard.view')
        ->name('system.dashboard');

    // System Health Monitoring Detail Pages
    Route::get('/system/health', [HealthController::class, 'index'])->name('system.health');
    Route::post('/system/health/refresh', [HealthController::class, 'refresh'])->name('system.health.refresh');

    // Backup Management
    Route::prefix('system/backups')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('system.backups');
        Route::get('/{backup}', [BackupController::class, 'show'])->name('system.backups.show');
        Route::post('/trigger', [BackupController::class, 'trigger'])->name('system.backups.trigger');
        Route::post('/schedule', [BackupController::class, 'updateSchedule'])->name('system.backups.schedule');
        Route::post('/retention', [BackupController::class, 'updateRetention'])->name('system.backups.retention');
        Route::post('/{backup}/restore', [BackupController::class, 'restore'])->name('system.backups.restore');
        Route::post('/cleanup', [BackupController::class, 'cleanup'])->name('system.backups.cleanup');
    });

    // Patch Management
    Route::prefix('system/patches')->group(function () {
        Route::get('/', [PatchController::class, 'index'])->name('system.patches');
        Route::get('/{patch}', [PatchController::class, 'show'])->name('system.patches.show');
        Route::post('/{patch}/approve', [PatchController::class, 'approve'])->name('system.patches.approve');
        Route::post('/{patch}/reject', [PatchController::class, 'reject'])->name('system.patches.reject');
        Route::post('/{patch}/deploy', [PatchController::class, 'markDeployed'])->name('system.patches.deploy');
    });

    // Security Audit Logs
    Route::prefix('system/security/audit')->group(function () {
        Route::get('/', [SecurityAuditController::class, 'index'])->name('system.security.audit');
        Route::get('/{log}', [SecurityAuditController::class, 'show'])->name('system.security.audit.show');
        Route::get('/export', [SecurityAuditController::class, 'export'])->name('system.security.audit.export');
    });

    // Error Logs
    Route::prefix('system/logs/errors')->group(function () {
        Route::get('/', [ErrorLogController::class, 'index'])->name('system.logs.errors');
        Route::get('/{errorLog}', [ErrorLogController::class, 'show'])->name('system.logs.errors.show');
        Route::post('/{errorLog}/resolve', [ErrorLogController::class, 'resolve'])->name('system.logs.errors.resolve');
        Route::post('/{errorLog}/bulk-resolve', [ErrorLogController::class, 'bulkResolve'])->name('system.logs.errors.bulk-resolve');
        Route::post('/cleanup', [ErrorLogController::class, 'cleanup'])->name('system.logs.errors.cleanup');
    });

    // Storage Management
    Route::prefix('system/storage')->group(function () {
        Route::get('/', [StorageController::class, 'index'])->name('system.storage');
        Route::post('/cleanup', [StorageController::class, 'cleanup'])->name('system.storage.cleanup');
    });

    // Security & Access - Roles and Permissions
    Route::prefix('system/security/roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('system.security.roles');
        Route::get('/create', [RoleController::class, 'create'])->name('system.security.roles.create');
        Route::post('/', [RoleController::class, 'store'])->name('system.security.roles.store');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('system.security.roles.edit');
        Route::put('/{role}', [RoleController::class, 'update'])->name('system.security.roles.update');
        Route::post('/{role}', [RoleController::class, 'update'])->name('system.security.roles.update.post');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('system.security.roles.destroy');
        Route::get('/{role}/permissions', [RoleController::class, 'getPermissionMatrix'])->name('system.security.roles.permissions');
    });

    // Security & Access - User Lifecycle
    Route::prefix('system/users')->group(function () {
        Route::get('/', [UserLifecycleController::class, 'index'])->name('system.users');
        Route::post('/', [UserLifecycleController::class, 'store'])->name('system.users.store');
        Route::get('/{user}', [UserLifecycleController::class, 'show'])->name('system.users.show');
        Route::put('/{user}', [UserLifecycleController::class, 'update'])->name('system.users.update');
        Route::post('/{user}/password-reset', [UserLifecycleController::class, 'sendPasswordReset'])->name('system.users.password-reset');
        Route::post('/{user}/deactivate', [UserLifecycleController::class, 'deactivate'])->name('system.users.deactivate');
        Route::post('/{user}/activate', [UserLifecycleController::class, 'activate'])->name('system.users.activate');
        Route::get('/{user}/audit-trail', [UserLifecycleController::class, 'auditTrail'])->name('system.users.audit-trail');
    });

    // Security & Access - Security Policies
    Route::prefix('system/security/policies')->group(function () {
        Route::get('/', [PolicyController::class, 'index'])->name('system.security.policies');
        Route::post('/', [PolicyController::class, 'update'])->name('system.security.policies.update');
        Route::get('/{key}', [PolicyController::class, 'show'])->name('system.security.policies.show');
        Route::post('/{key}/reset', [PolicyController::class, 'reset'])->name('system.security.policies.reset');
    });

    // Security & Access - IP Rules
    Route::prefix('system/security/ip-rules')->group(function () {
        Route::get('/', [IPRuleController::class, 'index'])->name('system.security.ip-rules');
        Route::post('/', [IPRuleController::class, 'store'])->name('system.security.ip-rules.store');
        Route::put('/{rule}', [IPRuleController::class, 'update'])->name('system.security.ip-rules.update');
        Route::delete('/{rule}', [IPRuleController::class, 'destroy'])->name('system.security.ip-rules.destroy');
        Route::post('/{rule}/toggle', [IPRuleController::class, 'toggle'])->name('system.security.ip-rules.toggle');
    });

    // Organization Control - Overview, Departments & Positions
    Route::prefix('system/organization')->group(function () {
        Route::get('/overview', [OverviewController::class, 'index'])->name('system.organization.overview');
        
        // Departments
        Route::prefix('departments')->group(function () {
            Route::get('/', [\App\Http\Controllers\System\Organization\DepartmentController::class, 'index'])->name('system.organization.departments');
            Route::post('/', [\App\Http\Controllers\System\Organization\DepartmentController::class, 'store'])->name('system.organization.departments.store');
            Route::post('/seed', [\App\Http\Controllers\System\Organization\DepartmentController::class, 'seedDefaults'])->name('system.organization.departments.seed');
            Route::get('/{department}', [\App\Http\Controllers\System\Organization\DepartmentController::class, 'show'])->name('system.organization.departments.show');
            Route::put('/{department}', [\App\Http\Controllers\System\Organization\DepartmentController::class, 'update'])->name('system.organization.departments.update');
            Route::delete('/{department}', [\App\Http\Controllers\System\Organization\DepartmentController::class, 'destroy'])->name('system.organization.departments.destroy');
        });

        // Positions
        Route::prefix('positions')->group(function () {
            Route::get('/', [PositionController::class, 'index'])->name('system.organization.positions');
            Route::post('/', [PositionController::class, 'store'])->name('system.organization.positions.store');
            Route::post('/seed', [PositionController::class, 'seedDefaults'])->name('system.organization.positions.seed');
            Route::get('/{position}', [PositionController::class, 'show'])->name('system.organization.positions.show');
            Route::put('/{position}', [PositionController::class, 'update'])->name('system.organization.positions.update');
            Route::delete('/{position}', [PositionController::class, 'destroy'])->name('system.organization.positions.destroy');
        });
    });

    // Monitoring & Reporting - Usage Analytics & Security Reports
    Route::prefix('system/reports')->group(function () {
        Route::get('/usage', [UsageController::class, 'index'])->name('system.reports.usage');
        Route::get('/security', [SecurityController::class, 'index'])->name('system.reports.security');
        Route::get('/payroll', [PayrollController::class, 'index'])->name('system.reports.payroll');
        Route::get('/compliance', [ComplianceController::class, 'index'])->name('system.reports.compliance');
    });

    // Update Management
    Route::prefix('system/updates')->group(function () {
        Route::get('/', [UpdateController::class, 'index'])->name('system.updates');
        Route::post('/check', [UpdateController::class, 'check'])->name('system.updates.check');
        Route::post('/download', [UpdateController::class, 'download'])->name('system.updates.download');
        Route::post('/deploy', [UpdateController::class, 'deploy'])->name('system.updates.deploy');
        Route::get('/progress/{deploymentId}', [UpdateController::class, 'progress'])->name('system.updates.progress');
        Route::post('/rollback', [UpdateController::class, 'rollback'])->name('system.updates.rollback');
    });

    // Cron Job Management
    Route::prefix('system/cron')->group(function () {
        Route::get('/', [CronController::class, 'index'])->name('system.cron');
        Route::post('/', [CronController::class, 'store'])->name('system.cron.store');
        Route::post('/sync', [CronController::class, 'sync'])->name('system.cron.sync');
        Route::post('/{id}/toggle', [CronController::class, 'toggle'])->name('system.cron.toggle');
        Route::post('/{id}/run', [CronController::class, 'run'])->name('system.cron.run');
        Route::get('/{id}/history', [CronController::class, 'history'])->name('system.cron.history');
        Route::put('/{id}', [CronController::class, 'update'])->name('system.cron.update');
        Route::delete('/{id}', [CronController::class, 'destroy'])->name('system.cron.destroy');
    });

    // SLA Monitoring & Metrics
    Route::prefix('system/sla')->group(function () {
        Route::get('/', [SLAController::class, 'index'])->name('system.sla');
        Route::get('/uptime', [SLAController::class, 'uptime'])->name('system.sla.uptime');
        Route::get('/incidents', [SLAController::class, 'incidents'])->name('system.sla.incidents');
        Route::get('/patches', [SLAController::class, 'patches'])->name('system.sla.patches');
        Route::post('/cache/clear', [SLAController::class, 'clearCache'])->name('system.sla.cache.clear');
    });

    // Vendor Contract Management
    Route::prefix('system/vendor-contract')->group(function () {
        Route::get('/', [VendorContractController::class, 'index'])->name('system.vendor-contract');
        Route::get('/show', [VendorContractController::class, 'show'])->name('system.vendor-contract.show');
        Route::post('/cache/clear', [VendorContractController::class, 'clearCache'])->name('system.vendor-contract.cache.clear');
    });

    // Timekeeping Device Management (RFID)
    Route::prefix('system/timekeeping/devices')->group(function () {
        Route::get('/', [DeviceManagementController::class, 'index'])->name('system.timekeeping.devices.index');
        Route::post('/', [DeviceManagementController::class, 'store'])->name('system.timekeeping.devices.store');
        Route::patch('/{device}', [DeviceManagementController::class, 'update'])->name('system.timekeeping.devices.update');
        Route::delete('/{device}', [DeviceManagementController::class, 'destroy'])->name('system.timekeeping.devices.destroy');
        Route::post('/{device}/generate-key', [DeviceManagementController::class, 'generateKey'])->name('system.timekeeping.devices.generate-key');
        Route::post('/{device}/revoke-key', [DeviceManagementController::class, 'revokeKey'])->name('system.timekeeping.devices.revoke-key');
    });
});
