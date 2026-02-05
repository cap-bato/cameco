<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureEmployee;

// Employee Portal Routes
// NOTE: Controllers will be created in Phase 3 - these routes are prepared in advance
// All routes require Employee role or Superadmin for access

Route::middleware(['auth', 'verified', EnsureEmployee::class])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {
        
        // ============================================================
        // DASHBOARD
        // ============================================================
        // Employee dashboard with quick stats (leave balance, attendance, pending requests, next payday)
        Route::get('/dashboard', [\App\Http\Controllers\Employee\DashboardController::class, 'index'])
            ->middleware('permission:employee.dashboard.view')
            ->name('dashboard');

        // ============================================================
        // PERSONAL INFORMATION (Self-Service)
        // ============================================================
        Route::prefix('profile')->name('profile.')->group(function () {
            // View own profile information (personal details, employment info, government IDs)
            Route::get('/', [\App\Http\Controllers\Employee\ProfileController::class, 'index'])
                ->middleware('permission:employee.profile.view')
                ->name('index');
            
            // Submit profile update request (contact info only - requires HR approval)
            Route::post('/update-request', [\App\Http\Controllers\Employee\ProfileController::class, 'requestUpdate'])
                ->middleware('permission:employee.profile.update')
                ->name('update-request');
        });

        // ============================================================
        // ATTENDANCE & TIME LOGS (Self-Service)
        // ============================================================
        Route::prefix('attendance')->name('attendance.')->group(function () {
            // View own attendance records (daily, weekly, monthly views with RFID punch history)
            Route::get('/', [\App\Http\Controllers\Employee\AttendanceController::class, 'index'])
                ->middleware('permission:employee.attendance.view')
                ->name('index');
            
            // Report attendance issue (missing punch, wrong time - requires HR verification)
            Route::post('/report-issue', [\App\Http\Controllers\Employee\AttendanceController::class, 'reportIssue'])
                ->middleware('permission:employee.attendance.report')
                ->name('report-issue');
        });

        // ============================================================
        // PAYSLIPS (Self-Service)
        // ============================================================
        Route::prefix('payslips')->name('payslips.')->group(function () {
            // List all own payslips
            Route::get('/', [\App\Http\Controllers\Employee\PayslipController::class, 'index'])
                ->middleware('permission:employee.payslips.view')
                ->name('index');
            
            // View specific payslip details (salary breakdown, deductions, net pay)
            Route::get('/{id}', [\App\Http\Controllers\Employee\PayslipController::class, 'show'])
                ->middleware('permission:employee.payslips.view')
                ->name('show');
            
            // Download payslip as PDF
            Route::get('/{id}/download', [\App\Http\Controllers\Employee\PayslipController::class, 'download'])
                ->middleware('permission:employee.payslips.download')
                ->name('download');
            
            // View annual summary (total gross, deductions, net pay by year)
            Route::get('/annual-summary/{year}', [\App\Http\Controllers\Employee\PayslipController::class, 'annualSummary'])
                ->middleware('permission:employee.payslips.view')
                ->name('annual-summary');
        });

        // ============================================================
        // LEAVE MANAGEMENT (Self-Service)
        // ============================================================
        Route::prefix('leave')->name('leave.')->group(function () {
            // View own leave balances by type (Vacation, Sick, Emergency, etc.)
            Route::get('/balances', [\App\Http\Controllers\Employee\LeaveController::class, 'balances'])
                ->middleware('permission:employee.leave.view-balance')
                ->name('balances');
            
            // View own leave request history (approved, rejected, pending, cancelled)
            Route::get('/history', [\App\Http\Controllers\Employee\LeaveController::class, 'history'])
                ->middleware('permission:employee.leave.view-history')
                ->name('history');
            
            // Show leave request form
            Route::get('/request', [\App\Http\Controllers\Employee\LeaveController::class, 'create'])
                ->middleware('permission:employee.leave.submit')
                ->name('request.create');
            
            // Submit leave request (validates balance, advance notice, blackout periods)
            Route::post('/request', [\App\Http\Controllers\Employee\LeaveController::class, 'store'])
                ->middleware('permission:employee.leave.submit')
                ->name('request.store');
            
            // Cancel pending leave request
            Route::post('/request/{id}/cancel', [\App\Http\Controllers\Employee\LeaveController::class, 'cancel'])
                ->middleware('permission:employee.leave.cancel')
                ->name('request.cancel');
            
            // Calculate workforce coverage impact for leave dates (AJAX endpoint)
            Route::post('/request/calculate-coverage', [\App\Http\Controllers\Employee\LeaveController::class, 'calculateCoverage'])
                ->middleware('permission:employee.leave.submit')
                ->name('request.calculate-coverage');
        });

        // ============================================================
        // NOTIFICATIONS (Self-Service)
        // ============================================================
        Route::prefix('notifications')->name('notifications.')->group(function () {
            // List all own notifications (leave approvals, payslips, attendance alerts)
            Route::get('/', [\App\Http\Controllers\Employee\NotificationController::class, 'index'])
                ->middleware('permission:employee.notifications.view')
                ->name('index');
            
            // Mark notification as read
            Route::post('/{id}/mark-read', [\App\Http\Controllers\Employee\NotificationController::class, 'markRead'])
                ->middleware('permission:employee.notifications.manage')
                ->name('mark-read');
            
            // Delete notification
            Route::delete('/{id}', [\App\Http\Controllers\Employee\NotificationController::class, 'destroy'])
                ->middleware('permission:employee.notifications.manage')
                ->name('destroy');
        });

        // ============================================================
        // DOCUMENTS (Self-Service)
        // ============================================================
        Route::prefix('documents')->name('documents.')->group(function () {
            // View own documents with filtering
            Route::get('/', [\App\Http\Controllers\Employee\DocumentController::class, 'index'])
                ->middleware('permission:employee.documents.view')
                ->name('index');
            
            // Show document request form (Certificate of Employment, Payslip, 2316 Form, etc.)
            Route::get('/request', [\App\Http\Controllers\Employee\DocumentController::class, 'createRequest'])
                ->middleware('permission:employee.documents.request')
                ->name('request.create');
            
            // Submit document request to HR Staff
            Route::post('/request', [\App\Http\Controllers\Employee\DocumentController::class, 'storeRequest'])
                ->middleware('permission:employee.documents.request')
                ->name('request.store');
            
            // Download own document with logging
            Route::get('/{document}/download', [\App\Http\Controllers\Employee\DocumentController::class, 'download'])
                ->middleware('permission:employee.documents.download')
                ->name('download');
        });
    });
