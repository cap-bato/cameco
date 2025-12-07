<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureOfficeAdmin;

// Office Admin Routes
// NOTE: Controllers will be created in Phase 3 - these routes are prepared in advance

Route::middleware(['auth', 'verified', EnsureOfficeAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        
        // ============================================================
        // DASHBOARD
        // ============================================================
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])
            ->middleware('permission:admin.dashboard.view')
            ->name('dashboard');

        // ============================================================
        // COMPANY SETUP (1. Company Onboarding & Setup)
        // ============================================================
        Route::prefix('company')->name('company.')->group(function () {
            // View company configuration
            Route::get('/', [\App\Http\Controllers\Admin\CompanyController::class, 'index'])
                ->middleware('permission:admin.company.view')
                ->name('index');
            
            // Update company configuration (basic info, tax, government numbers)
            Route::put('/', [\App\Http\Controllers\Admin\CompanyController::class, 'update'])
                ->middleware('permission:admin.company.edit')
                ->name('update');
            
            // Upload company logo
            Route::post('/logo', [\App\Http\Controllers\Admin\CompanyController::class, 'uploadLogo'])
                ->middleware('permission:admin.company.edit')
                ->name('logo.upload');
            
            // Delete company logo
            Route::delete('/logo', [\App\Http\Controllers\Admin\CompanyController::class, 'deleteLogo'])
                ->middleware('permission:admin.company.edit')
                ->name('logo.delete');
        });

        // ============================================================
        // BUSINESS RULES (2. Business Rules Configuration)
        // ============================================================
        Route::prefix('business-rules')->name('business-rules.')->group(function () {
            // View all business rules
            Route::get('/', [\App\Http\Controllers\Admin\BusinessRulesController::class, 'index'])
                ->middleware('permission:admin.business-rules.view')
                ->name('index');
            
            // Update working hours configuration
            Route::put('/working-hours', [\App\Http\Controllers\Admin\BusinessRulesController::class, 'updateWorkingHours'])
                ->middleware('permission:admin.business-rules.edit')
                ->name('working-hours.update');

            // Holiday Calendar Management
            Route::post('/holidays', [\App\Http\Controllers\Admin\BusinessRulesController::class, 'storeHoliday'])
                ->middleware('permission:admin.business-rules.edit')
                ->name('holidays.store');
            Route::put('/holidays/{holidayId}', [\App\Http\Controllers\Admin\BusinessRulesController::class, 'updateHoliday'])
                ->middleware('permission:admin.business-rules.edit')
                ->name('holidays.update');
            Route::delete('/holidays/{holidayId}', [\App\Http\Controllers\Admin\BusinessRulesController::class, 'deleteHoliday'])
                ->middleware('permission:admin.business-rules.edit')
                ->name('holidays.delete');

            // Update overtime rules configuration
            Route::put('/overtime', [\App\Http\Controllers\Admin\BusinessRulesController::class, 'updateOvertimeRules'])
                ->middleware('permission:admin.business-rules.edit')
                ->name('overtime.update');

            // Update attendance policies configuration
            Route::put('/attendance', [\App\Http\Controllers\Admin\BusinessRulesController::class, 'updateAttendanceRules'])
                ->middleware('permission:admin.business-rules.edit')
                ->name('attendance.update');

            // Update holiday pay multipliers
            Route::put('/holiday-multipliers', [\App\Http\Controllers\Admin\BusinessRulesController::class, 'updateHolidayMultipliers'])
                ->middleware('permission:admin.business-rules.edit')
                ->name('holiday-multipliers.update');
        });

        // ============================================================
        // DEPARTMENTS & POSITIONS (3. Department & Position Management)
        // ============================================================
        // NOTE: Reusing HR controllers with proper admin permissions
        // Office Admin has admin.departments.* and admin.positions.* permissions
        // HR controllers use policies which will validate against these permissions
        
        Route::prefix('departments')->name('departments.')->group(function () {
            // List all departments
            Route::get('/', [\App\Http\Controllers\HR\Employee\DepartmentController::class, 'index'])
                ->middleware('permission:admin.departments.view')
                ->name('index');
            
            // Create new department form
            Route::get('/create', [\App\Http\Controllers\HR\Employee\DepartmentController::class, 'create'])
                ->middleware('permission:admin.departments.create')
                ->name('create');
            
            // Store new department
            Route::post('/', [\App\Http\Controllers\HR\Employee\DepartmentController::class, 'store'])
                ->middleware('permission:admin.departments.create')
                ->name('store');
            
            // Edit department form
            Route::get('/{department}/edit', [\App\Http\Controllers\HR\Employee\DepartmentController::class, 'edit'])
                ->middleware('permission:admin.departments.edit')
                ->name('edit');
            
            // Update department
            Route::put('/{department}', [\App\Http\Controllers\HR\Employee\DepartmentController::class, 'update'])
                ->middleware('permission:admin.departments.edit')
                ->name('update');
            
            // Delete/archive department
            Route::delete('/{department}', [\App\Http\Controllers\HR\Employee\DepartmentController::class, 'destroy'])
                ->middleware('permission:admin.departments.delete')
                ->name('destroy');
        });

        Route::prefix('positions')->name('positions.')->group(function () {
            // List all positions
            Route::get('/', [\App\Http\Controllers\HR\Employee\PositionController::class, 'index'])
                ->middleware('permission:admin.positions.view')
                ->name('index');
            
            // Create new position form
            Route::get('/create', [\App\Http\Controllers\HR\Employee\PositionController::class, 'create'])
                ->middleware('permission:admin.positions.create')
                ->name('create');
            
            // Store new position
            Route::post('/', [\App\Http\Controllers\HR\Employee\PositionController::class, 'store'])
                ->middleware('permission:admin.positions.create')
                ->name('store');
            
            // Edit position form
            Route::get('/{position}/edit', [\App\Http\Controllers\HR\Employee\PositionController::class, 'edit'])
                ->middleware('permission:admin.positions.edit')
                ->name('edit');
            
            // Update position
            Route::put('/{position}', [\App\Http\Controllers\HR\Employee\PositionController::class, 'update'])
                ->middleware('permission:admin.positions.edit')
                ->name('update');
            
            // Delete/archive position
            Route::delete('/{position}', [\App\Http\Controllers\HR\Employee\PositionController::class, 'destroy'])
                ->middleware('permission:admin.positions.delete')
                ->name('destroy');
        });

        // ============================================================
        // LEAVE POLICIES (4. Leave Policies Configuration)
        // ============================================================
        Route::prefix('leave-policies')->name('leave-policies.')->group(function () {
            // List all leave policies
            Route::get('/', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'index'])
                ->middleware('permission:admin.leave-policies.view')
                ->name('index');
            
            // Create leave policy
            Route::post('/', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'store'])
                ->middleware('permission:admin.leave-policies.create')
                ->name('store');
            
            // Update leave policy
            Route::put('/{leavePolicy}', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'update'])
                ->middleware('permission:admin.leave-policies.edit')
                ->name('update');
            
            // Delete/archive leave policy
            Route::delete('/{leavePolicy}', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'destroy'])
                ->middleware('permission:admin.leave-policies.delete')
                ->name('destroy');
            
            // Approval rules configuration
            Route::get('/approval-rules', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'configureApprovalRules'])
                ->middleware('permission:admin.leave-policies.view')
                ->name('approval-rules');
            
            Route::put('/approval-rules', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'updateApprovalRules'])
                ->middleware('permission:admin.leave-policies.edit')
                ->name('approval-rules.update');
        });

        // ============================================================
        // PAYROLL RULES (5. Payroll Rules Configuration)
        // ============================================================
        Route::prefix('payroll-rules')->name('payroll-rules.')->group(function () {
            // View payroll rules
            Route::get('/', [\App\Http\Controllers\Admin\PayrollRulesController::class, 'index'])
                ->middleware('permission:admin.payroll-rules.view')
                ->name('index');
            
            // Update salary structure
            Route::put('/salary-structure', [\App\Http\Controllers\Admin\PayrollRulesController::class, 'updateSalaryStructure'])
                ->middleware('permission:admin.payroll-rules.edit')
                ->name('salary-structure.update');
            
            // Update allowances
            Route::put('/allowances', [\App\Http\Controllers\Admin\PayrollRulesController::class, 'updateAllowances'])
                ->middleware('permission:admin.payroll-rules.edit')
                ->name('allowances.update');
            
            // Update government rates (SSS, PhilHealth, Pag-IBIG)
            Route::put('/government-rates', [\App\Http\Controllers\Admin\PayrollRulesController::class, 'updateGovernmentRates'])
                ->middleware('permission:admin.payroll-rules.edit')
                ->name('government-rates.update');
            
            // Update withholding tax (BIR)
            Route::put('/withholding-tax', [\App\Http\Controllers\Admin\PayrollRulesController::class, 'updateWithholdingTax'])
                ->middleware('permission:admin.payroll-rules.edit')
                ->name('withholding-tax.update');
            
            // Update payment methods
            Route::put('/payment-methods', [\App\Http\Controllers\Admin\PayrollRulesController::class, 'updatePaymentMethods'])
                ->middleware('permission:admin.payroll-rules.edit')
                ->name('payment-methods.update');
        });

        // ============================================================
        // SYSTEM CONFIGURATION (6. System-wide Configuration)
        // ============================================================
        Route::prefix('system-config')->name('system-config.')->group(function () {
            // View system configuration
            Route::get('/', [\App\Http\Controllers\Admin\SystemConfigController::class, 'index'])
                ->middleware('permission:admin.system-config.view')
                ->name('index');
            
            // Update notification settings
            Route::put('/notifications', [\App\Http\Controllers\Admin\SystemConfigController::class, 'updateNotifications'])
                ->middleware('permission:admin.system-config.edit')
                ->name('notifications.update');
            
            // Update report settings
            Route::put('/reports', [\App\Http\Controllers\Admin\SystemConfigController::class, 'updateReports'])
                ->middleware('permission:admin.system-config.edit')
                ->name('reports.update');
            
            // Update integration settings
            Route::put('/integrations', [\App\Http\Controllers\Admin\SystemConfigController::class, 'updateIntegrations'])
                ->middleware('permission:admin.system-config.edit')
                ->name('integrations.update');
            
            // Audit logs
            Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])
                ->middleware('permission:admin.system-config.view')
                ->name('audit-logs.index');
            
            // Export audit logs
            Route::get('/audit-logs/export', [\App\Http\Controllers\Admin\AuditLogController::class, 'export'])
                ->middleware('permission:admin.system-config.view')
                ->name('audit-logs.export');
            
            // Test RFID connection
            Route::post('/integrations/rfid/test', [\App\Http\Controllers\Admin\SystemConfigController::class, 'testRFIDConnection'])
                ->middleware('permission:admin.system-config.edit')
                ->name('integrations.rfid.test');
        });

        // ============================================================
        // APPROVAL WORKFLOWS (7. Approval Workflow Setup)
        // ============================================================
        Route::prefix('approval-workflows')->name('approval-workflows.')->group(function () {
            // View all workflows
            Route::get('/', [\App\Http\Controllers\Admin\ApprovalWorkflowController::class, 'index'])
                ->middleware('permission:admin.approval-workflows.view')
                ->name('index');
            
            // Leave approval workflow
            Route::get('/leave/configure', [\App\Http\Controllers\Admin\ApprovalWorkflowController::class, 'configureLeaveWorkflow'])
                ->middleware('permission:admin.approval-workflows.view')
                ->name('leave.configure');
            
            Route::put('/leave', [\App\Http\Controllers\Admin\ApprovalWorkflowController::class, 'updateLeaveWorkflow'])
                ->middleware('permission:admin.approval-workflows.edit')
                ->name('leave.update');
            
            // Overtime approval workflow
            Route::get('/overtime/configure', [\App\Http\Controllers\Admin\ApprovalWorkflowController::class, 'configureOvertimeWorkflow'])
                ->middleware('permission:admin.approval-workflows.view')
                ->name('overtime.configure');
            
            Route::put('/overtime', [\App\Http\Controllers\Admin\ApprovalWorkflowController::class, 'updateOvertimeWorkflow'])
                ->middleware('permission:admin.approval-workflows.edit')
                ->name('overtime.update');
            
            // Test leave approval workflow
            Route::post('/leave/test', [\App\Http\Controllers\Admin\ApprovalWorkflowController::class, 'testLeaveWorkflow'])
                ->middleware('permission:admin.approval-workflows.view')
                ->name('leave.test');
        });

        // ============================================================
        // AUDIT LOGS & ACTIVITY TRACKING
        // ============================================================
        Route::prefix('audit')->name('audit.')->group(function () {
            // View configuration change logs (using Spatie Activity Log)
            // Route::get('/logs', [AuditController::class, 'index'])
            //     ->middleware('permission:admin.dashboard.view')
            //     ->name('logs.index');
            
            // View specific log entry
            // Route::get('/logs/{id}', [AuditController::class, 'show'])
            //     ->middleware('permission:admin.dashboard.view')
            //     ->name('logs.show');
        });
    });
