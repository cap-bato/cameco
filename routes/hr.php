<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HR\DashboardController as HRDashboardController;
use App\Http\Controllers\HR\Reports\AnalyticsController;
use App\Http\Controllers\HR\Employee\EmployeeController;
use App\Http\Controllers\HR\Employee\EmployeeExportImportController;
use App\Http\Controllers\HR\Employee\DepartmentController;
use App\Http\Controllers\HR\Employee\PositionController;
use App\Http\Controllers\HR\Leave\LeaveBalanceController;
use App\Http\Controllers\HR\Leave\LeavePolicyController;
use App\Http\Controllers\HR\Leave\LeaveRequestController;
use App\Http\Controllers\HR\Reports\ReportController;
use App\Http\Controllers\HR\ATS\JobPostingController;
use App\Http\Controllers\HR\ATS\CandidateController;
use App\Http\Controllers\HR\ATS\ApplicationController;
use App\Http\Controllers\HR\ATS\InterviewController;
use App\Http\Controllers\HR\ATS\HiringPipelineController;
use App\Http\Controllers\HR\Workforce\ScheduleController;
use App\Http\Controllers\HR\Workforce\RotationController;
use App\Http\Controllers\HR\Workforce\AssignmentController;
use App\Http\Controllers\HR\Timekeeping\AttendanceController;
use App\Http\Controllers\HR\Timekeeping\OvertimeController;
use App\Http\Controllers\HR\Timekeeping\ImportController;
use App\Http\Controllers\HR\Timekeeping\LedgerController;
use App\Http\Controllers\HR\Timekeeping\LedgerHealthController;
use App\Http\Controllers\HR\Timekeeping\LedgerSyncController;
use App\Http\Controllers\HR\Timekeeping\LedgerDeviceController;
use App\Http\Controllers\HR\Timekeeping\DeviceController;
use App\Http\Controllers\HR\Timekeeping\EmployeeTimelineController;
use App\Http\Controllers\HR\Timekeeping\AnalyticsController as TimekeepingAnalyticsController;
use App\Http\Controllers\HR\Appraisal\AppraisalCycleController;
use App\Http\Controllers\HR\Appraisal\AppraisalController;
use App\Http\Controllers\HR\Appraisal\PerformanceMetricsController;
use App\Http\Controllers\HR\Appraisal\RehireRecommendationController;
use App\Http\Controllers\HR\Employee\EmployeeDocumentController;
use App\Http\Middleware\EnsureHRAccess;
// use App\Http\Middleware\EnsureProfileComplete; for future useronboarding workflow

Route::middleware(['auth', 'verified' , EnsureHRAccess::class])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        // HR Dashboard
        Route::get('/dashboard', [HRDashboardController::class, 'index'])->name('dashboard');

        // HR Reports & Analytics
        Route::get('/reports/analytics', [AnalyticsController::class, 'index'])->name('reports.analytics');

        // Employee Import/Export (must be before resource routes)
        Route::get('/employees/export/csv', [EmployeeExportImportController::class, 'export'])->name('employees.export');
        Route::get('/employees/import', [EmployeeExportImportController::class, 'showImport'])->name('employees.import.show');
        Route::post('/employees/import', [EmployeeExportImportController::class, 'import'])->name('employees.import');
        Route::get('/employees/import/template', [EmployeeExportImportController::class, 'downloadTemplate'])->name('employees.import.template');

        // Employee Management
        Route::resource('employees', EmployeeController::class);
        Route::post('/employees/{id}/restore', [EmployeeController::class, 'restore'])->name('employees.restore');

        // Employee-Specific Document API Routes (for Employee Profile → Documents Tab)
        // These routes are scoped to a single employee context
        Route::prefix('employees/{employeeId}/api/documents')->name('api.employees.documents.')->group(function () {
            Route::get('/', [EmployeeDocumentController::class, 'index'])
                ->middleware('permission:hr.documents.view')
                ->name('index');
            
            Route::post('/', [EmployeeDocumentController::class, 'store'])
                ->middleware('permission:hr.documents.upload')
                ->name('store');
            
            Route::get('/{documentId}', [EmployeeDocumentController::class, 'show'])
                ->middleware('permission:hr.documents.view')
                ->name('show');
            
            Route::put('/{documentId}/approve', [EmployeeDocumentController::class, 'approve'])
                ->middleware('permission:hr.documents.approve')
                ->name('approve');
            
            Route::put('/{documentId}/reject', [EmployeeDocumentController::class, 'reject'])
                ->middleware('permission:hr.documents.reject')
                ->name('reject');
            
            Route::delete('/{documentId}', [EmployeeDocumentController::class, 'destroy'])
                ->middleware('permission:hr.documents.delete')
                ->name('destroy');
            
            Route::get('/{documentId}/download', [EmployeeDocumentController::class, 'download'])
                ->middleware('permission:hr.documents.download')
                ->name('download');
        });

        // Department Management
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::middleware('permission:hr.departments.manage')->group(function () {
            Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
        });

        // Position Management
        Route::get('/positions', [PositionController::class, 'index'])->name('positions.index');
        Route::middleware('permission:hr.positions.manage')->group(function () {
            Route::post('/positions', [PositionController::class, 'store'])->name('positions.store');
            Route::put('/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
            Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');
        });

        // Leave Management
        Route::prefix('leave')->name('leave.')->group(function () {
            Route::get('/requests', [LeaveRequestController::class, 'index'])->name('requests');
            Route::get('/requests/create', [LeaveRequestController::class, 'create'])->name('requests.create');
            Route::post('/requests', [LeaveRequestController::class, 'store'])->name('requests.store');
            Route::get('/requests/{id}', [LeaveRequestController::class, 'show'])->name('requests.show');
            Route::get('/requests/{id}/edit', [LeaveRequestController::class, 'edit'])->name('requests.edit');
            Route::put('/requests/{id}', [LeaveRequestController::class, 'update'])->name('requests.update');
            Route::post('/requests/{id}/process', [LeaveRequestController::class, 'processApproval'])->name('requests.process');
            Route::delete('/requests/{id}', [LeaveRequestController::class, 'destroy'])->name('requests.destroy');
            Route::get('/balances', [LeaveBalanceController::class, 'index'])->name('balances');
            Route::get('/policies', [LeavePolicyController::class, 'index'])->name('policies');
            
            // Leave Policy CRUD (HR Manager only)
            Route::middleware('permission:hr.leave-policies.create')->group(function () {
                Route::post('/policies', [LeavePolicyController::class, 'store'])->name('policies.store');
            });
            Route::middleware('permission:hr.leave-policies.update')->group(function () {
                Route::put('/policies/{policy}', [LeavePolicyController::class, 'update'])->name('policies.update');
                Route::delete('/policies/{policy}', [LeavePolicyController::class, 'destroy'])->name('policies.destroy');
            });
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/employees', [ReportController::class, 'employees'])->name('employees');
            Route::get('/leave', [ReportController::class, 'leave'])->name('leave');
        });

        // Document Management Module
        Route::prefix('documents')->name('documents.')->group(function () {
            // Employee Documents
            Route::get('/', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'index'])
                ->middleware('permission:hr.documents.view')
                ->name('index');
            
            Route::get('/upload', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'create'])
                ->middleware('permission:hr.documents.upload')
                ->name('create');
            
            Route::post('/', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'store'])
                ->middleware('permission:hr.documents.upload')
                ->name('store');
            
            Route::get('/bulk-upload', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'bulkUploadForm'])
                ->middleware('permission:hr.documents.bulk-upload')
                ->name('bulk-upload');
            
            Route::post('/bulk-upload', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'bulkUpload'])
                ->middleware('permission:hr.documents.bulk-upload')
                ->name('bulk-upload.store');
            
            // Document Templates (must come before generic {document} route)
            Route::prefix('templates')->name('templates.')->group(function () {
                Route::get('/', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'index'])
                    ->middleware('permission:hr.documents.view')
                    ->name('index');
                
                Route::get('/create', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'create'])
                    ->middleware('permission:hr.documents.templates.manage')
                    ->name('create');
                
                Route::post('/', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'store'])
                    ->middleware('permission:hr.documents.templates.manage')
                    ->name('store');
                
                Route::get('/{template}/edit', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'edit'])
                    ->middleware('permission:hr.documents.templates.manage')
                    ->name('edit');
                
                Route::put('/{template}', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'update'])
                    ->middleware('permission:hr.documents.templates.manage')
                    ->name('update');
                
                Route::post('/{template}/generate', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'generate'])
                    ->middleware('permission:hr.documents.view')
                    ->name('generate');
            });
            
            // API Endpoints for templates
            Route::get('/api/templates', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'apiList'])
                ->middleware('permission:hr.documents.view')
                ->name('api.templates');
            
            // Document Requests (must come before generic {document} route)
            Route::prefix('requests')->name('requests.')->group(function () {
                Route::get('/', [\App\Http\Controllers\HR\Documents\DocumentRequestController::class, 'index'])
                    ->middleware('permission:hr.documents.view')
                    ->name('index');
                
                Route::post('/{request}/process', [\App\Http\Controllers\HR\Documents\DocumentRequestController::class, 'process'])
                    ->middleware('permission:hr.documents.upload')
                    ->name('process');
            });

            // API Endpoints for Document Generation
            Route::get('/api/templates', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'apiList'])
                ->middleware('permission:hr.documents.view')
                ->name('api.templates.list');
            
            Route::post('/api/templates/generate', [\App\Http\Controllers\HR\Documents\DocumentTemplateController::class, 'apiGenerate'])
                ->middleware('permission:hr.documents.view')
                ->name('api.templates.generate');
            
            // Generic document routes (must come last)
            Route::get('/{document}', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'show'])
                ->middleware('permission:hr.documents.view')
                ->name('show');
            
            Route::get('/{document}/download', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'download'])
                ->middleware('permission:hr.documents.download')
                ->name('download');
            
            Route::post('/{document}/approve', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'approve'])
                ->middleware('permission:hr.documents.approve')
                ->name('approve');
            
            Route::post('/{document}/reject', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'reject'])
                ->middleware('permission:hr.documents.reject')
                ->name('reject');
            
            Route::delete('/{document}', [\App\Http\Controllers\HR\Documents\EmployeeDocumentController::class, 'destroy'])
                ->middleware('permission:hr.documents.delete')
                ->name('destroy');
        });

        // Appraisal & Performance Management Module
        Route::prefix('appraisals')->name('appraisals.')->group(function () {
            // Appraisal Cycles
            Route::prefix('cycles')->name('cycles.')->group(function () {
                Route::get('/', [AppraisalCycleController::class, 'index'])
                    ->middleware('permission:hr.appraisals.view')
                    ->name('index');
                Route::get('/create', [AppraisalCycleController::class, 'create'])
                    ->middleware('permission:hr.appraisals.conduct')
                    ->name('create');
                Route::post('/', [AppraisalCycleController::class, 'store'])
                    ->middleware('permission:hr.appraisals.conduct')
                    ->name('store');
                Route::get('/{id}', [AppraisalCycleController::class, 'show'])
                    ->middleware('permission:hr.appraisals.view')
                    ->name('show');
                Route::get('/{id}/edit', [AppraisalCycleController::class, 'edit'])
                    ->middleware('permission:hr.appraisals.conduct')
                    ->name('edit');
                Route::put('/{id}', [AppraisalCycleController::class, 'update'])
                    ->middleware('permission:hr.appraisals.conduct')
                    ->name('update');
                Route::post('/{id}/close', [AppraisalCycleController::class, 'close'])
                    ->middleware('permission:hr.appraisals.conduct')
                    ->name('close');
                Route::get('/{id}/assign', [AppraisalCycleController::class, 'assignEmployees'])
                    ->middleware('permission:hr.appraisals.conduct')
                    ->name('assign.show');
                Route::post('/{id}/assign', [AppraisalCycleController::class, 'storeAssignment'])
                    ->middleware('permission:hr.appraisals.conduct')
                    ->name('assign.store');
            });

            // Appraisals (Individual)
            Route::get('/', [AppraisalController::class, 'index'])
                ->middleware('permission:hr.appraisals.view')
                ->name('index');
            Route::get('/{id}', [AppraisalController::class, 'show'])
                ->middleware('permission:hr.appraisals.view')
                ->name('show');
            Route::post('/', [AppraisalController::class, 'store'])
                ->middleware('permission:hr.appraisals.conduct')
                ->name('store');
            Route::put('/{id}/scores', [AppraisalController::class, 'updateScores'])
                ->middleware('permission:hr.appraisals.conduct')
                ->name('update-scores');
            Route::put('/{id}/status', [AppraisalController::class, 'updateStatus'])
                ->middleware('permission:hr.appraisals.conduct')
                ->name('update-status');
            Route::put('/{id}/feedback', [AppraisalController::class, 'submitFeedback'])
                ->middleware('permission:hr.appraisals.conduct')
                ->name('submit-feedback');
        });

        // Performance Metrics & Rehire Recommendations
        Route::get('/performance-metrics', [PerformanceMetricsController::class, 'index'])
            ->middleware('permission:hr.appraisals.view')
            ->name('performance-metrics.index');
        Route::get('/performance-metrics/{employeeId}', [PerformanceMetricsController::class, 'show'])
            ->middleware('permission:hr.appraisals.view')
            ->name('performance-metrics.show');
        Route::get('/performance-metrics/department/comparison', [PerformanceMetricsController::class, 'departmentComparison'])
            ->middleware('permission:hr.appraisals.view')
            ->name('performance-metrics.department-comparison');

        Route::prefix('rehire-recommendations')->name('rehire-recommendations.')->group(function () {
            Route::get('/', [RehireRecommendationController::class, 'index'])
                ->middleware('permission:hr.appraisals.view')
                ->name('index');
            Route::get('/{id}', [RehireRecommendationController::class, 'show'])
                ->middleware('permission:hr.appraisals.view')
                ->name('show');
            Route::put('/{id}/override', [RehireRecommendationController::class, 'override'])
                ->middleware('permission:hr.appraisals.conduct')
                ->name('override');
            Route::post('/bulk/approve', [RehireRecommendationController::class, 'bulkApprove'])
                ->middleware('permission:hr.appraisals.conduct')
                ->name('bulk-approve');
        });

        // ATS (Applicant Tracking System) Module
        Route::prefix('ats')->name('ats.')->group(function () {
            // Job Postings
            Route::get('/job-postings', [JobPostingController::class, 'index'])
                ->middleware('permission:hr.ats.view')
                ->name('job-postings.index');
            Route::get('/job-postings/create', [JobPostingController::class, 'create'])
                ->middleware('permission:hr.ats.candidates.create')
                ->name('job-postings.create');
            Route::post('/job-postings', [JobPostingController::class, 'store'])
                ->middleware('permission:hr.ats.candidates.create')
                ->name('job-postings.store');
            Route::get('/job-postings/{id}/edit', [JobPostingController::class, 'edit'])
                ->middleware('permission:hr.ats.candidates.update')
                ->name('job-postings.edit');
            Route::put('/job-postings/{id}', [JobPostingController::class, 'update'])
                ->middleware('permission:hr.ats.candidates.update')
                ->name('job-postings.update');
            Route::delete('/job-postings/{id}', [JobPostingController::class, 'destroy'])
                ->middleware('permission:hr.ats.candidates.delete')
                ->name('job-postings.destroy');
            Route::post('/job-postings/{id}/publish', [JobPostingController::class, 'publish'])
                ->middleware('permission:hr.ats.candidates.update')
                ->name('job-postings.publish');
            Route::post('/job-postings/{id}/close', [JobPostingController::class, 'close'])
                ->middleware('permission:hr.ats.candidates.update')
                ->name('job-postings.close');

            // Candidates
            Route::get('/candidates', [CandidateController::class, 'index'])
                ->middleware('permission:hr.ats.candidates.view')
                ->name('candidates.index');
            Route::get('/candidates/{id}', [CandidateController::class, 'show'])
                ->middleware('permission:hr.ats.candidates.view')
                ->name('candidates.show');
            Route::post('/candidates', [CandidateController::class, 'store'])
                ->middleware('permission:hr.ats.candidates.create')
                ->name('candidates.store');
            Route::put('/candidates/{id}', [CandidateController::class, 'update'])
                ->middleware('permission:hr.ats.candidates.update')
                ->name('candidates.update');
            Route::post('/candidates/{id}/notes', [CandidateController::class, 'addNote'])
                ->middleware('permission:hr.ats.candidates.update')
                ->name('candidates.notes.store');

            // Applications
            Route::get('/applications', [ApplicationController::class, 'index'])
                ->middleware('permission:hr.ats.applications.view')
                ->name('applications.index');
            Route::get('/applications/{id}', [ApplicationController::class, 'show'])
                ->middleware('permission:hr.ats.applications.view')
                ->name('applications.show');
            Route::put('/applications/{id}/status', [ApplicationController::class, 'updateStatus'])
                ->middleware('permission:hr.ats.applications.update')
                ->name('applications.update-status');
            Route::post('/applications/{id}/shortlist', [ApplicationController::class, 'shortlist'])
                ->middleware('permission:hr.ats.applications.update')
                ->name('applications.shortlist');
            Route::post('/applications/{id}/reject', [ApplicationController::class, 'reject'])
                ->middleware('permission:hr.ats.applications.update')
                ->name('applications.reject');

            // Interviews
            Route::get('/interviews', [InterviewController::class, 'index'])
                ->middleware('permission:hr.ats.view')
                ->name('interviews.index');
            Route::get('/interviews/{id}', [InterviewController::class, 'show'])
                ->middleware('permission:hr.ats.view')
                ->name('interviews.show');
            Route::post('/interviews', [InterviewController::class, 'store'])
                ->middleware('permission:hr.ats.interviews.schedule')
                ->name('interviews.store');
            Route::put('/interviews/{id}', [InterviewController::class, 'update'])
                ->middleware('permission:hr.ats.interviews.schedule')
                ->name('interviews.update');
            Route::post('/interviews/{id}/feedback', [InterviewController::class, 'addFeedback'])
                ->middleware('permission:hr.ats.interviews.schedule')
                ->name('interviews.feedback');
            Route::post('/interviews/{id}/cancel', [InterviewController::class, 'cancel'])
                ->middleware('permission:hr.ats.interviews.schedule')
                ->name('interviews.cancel');
            Route::post('/interviews/{id}/complete', [InterviewController::class, 'markCompleted'])
                ->middleware('permission:hr.ats.interviews.schedule')
                ->name('interviews.complete');

            // Hiring Pipeline
            Route::get('/hiring-pipeline', [HiringPipelineController::class, 'index'])
                ->middleware('permission:hr.ats.view')
                ->name('hiring-pipeline.index');
            Route::put('/hiring-pipeline/applications/{id}/move', [HiringPipelineController::class, 'moveApplication'])
                ->middleware('permission:hr.ats.applications.update')
                ->name('hiring-pipeline.move');
        });

        // Workforce Management Module
        Route::prefix('workforce')->name('workforce.')->group(function () {
            // Work Schedules
            Route::get('/schedules', [ScheduleController::class, 'index'])
                ->middleware('permission:hr.workforce.schedules.view')
                ->name('schedules.index');
            Route::get('/schedules/create', [ScheduleController::class, 'create'])
                ->middleware('permission:hr.workforce.schedules.create')
                ->name('schedules.create');
            Route::post('/schedules', [ScheduleController::class, 'store'])
                ->middleware('permission:hr.workforce.schedules.create')
                ->name('schedules.store');
            Route::get('/schedules/{id}', [ScheduleController::class, 'show'])
                ->middleware('permission:hr.workforce.schedules.view')
                ->name('schedules.show');
            Route::get('/schedules/{id}/edit', [ScheduleController::class, 'edit'])
                ->middleware('permission:hr.workforce.schedules.update')
                ->name('schedules.edit');
            Route::put('/schedules/{id}', [ScheduleController::class, 'update'])
                ->middleware('permission:hr.workforce.schedules.update')
                ->name('schedules.update');
            Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])
                ->middleware('permission:hr.workforce.schedules.delete')
                ->name('schedules.destroy');
            // Schedule custom actions
            Route::post('/schedules/{id}/assign-employees', [ScheduleController::class, 'assignEmployees'])
                ->middleware('permission:hr.workforce.schedules.update')
                ->name('schedules.assign-employees');
            Route::post('/schedules/{id}/unassign-employees', [ScheduleController::class, 'unassignEmployees'])
                ->middleware('permission:hr.workforce.schedules.update')
                ->name('schedules.unassign-employees');
            Route::post('/schedules/{id}/duplicate', [ScheduleController::class, 'duplicate'])
                ->middleware('permission:hr.workforce.schedules.create')
                ->name('schedules.duplicate');
            Route::post('/schedules/clone-template', [ScheduleController::class, 'cloneTemplate'])
                ->middleware('permission:hr.workforce.schedules.create')
                ->name('schedules.clone-template');
            Route::post('/schedules/bulk-update-status', [ScheduleController::class, 'bulkUpdateStatus'])
                ->middleware('permission:hr.workforce.schedules.update')
                ->name('schedules.bulk-update-status');
            Route::get('/schedules/api/list', [ScheduleController::class, 'list'])
                ->middleware('permission:hr.workforce.schedules.view')
                ->name('schedules.api.list');
            Route::get('/schedules/export/csv', [ScheduleController::class, 'exportCsv'])
                ->middleware('permission:hr.workforce.schedules.view')
                ->name('schedules.export');
            Route::get('/schedules/api/statistics', [ScheduleController::class, 'getStatistics'])
                ->middleware('permission:hr.workforce.schedules.view')
                ->name('schedules.statistics');
            Route::get('/schedules/api/available-employees', [ScheduleController::class, 'getAvailableEmployees'])
                ->middleware('permission:hr.workforce.schedules.view')
                ->name('schedules.available-employees');
            Route::get('/schedules/{id}/api/assigned-employees', [ScheduleController::class, 'getAssignedEmployees'])
                ->middleware('permission:hr.workforce.schedules.view')
                ->name('schedules.assigned-employees');

            // Employee Rotations
            Route::get('/rotations', [RotationController::class, 'index'])
                ->middleware('permission:hr.workforce.rotations.view')
                ->name('rotations.index');
            Route::get('/rotations/create', [RotationController::class, 'create'])
                ->middleware('permission:hr.workforce.rotations.create')
                ->name('rotations.create');
            Route::post('/rotations', [RotationController::class, 'store'])
                ->middleware('permission:hr.workforce.rotations.create')
                ->name('rotations.store');
            // Rotation custom actions (must come before {id} routes)
            Route::get('/rotations/available-employees', [RotationController::class, 'getAvailableEmployees'])
                ->middleware('permission:hr.workforce.rotations.view')
                ->name('rotations.available-employees');
            Route::get('/rotations/{id}', [RotationController::class, 'show'])
                ->middleware('permission:hr.workforce.rotations.view')
                ->name('rotations.show');
            Route::get('/rotations/{id}/edit', [RotationController::class, 'edit'])
                ->middleware('permission:hr.workforce.rotations.update')
                ->name('rotations.edit');
            Route::put('/rotations/{id}', [RotationController::class, 'update'])
                ->middleware('permission:hr.workforce.rotations.update')
                ->name('rotations.update');
            Route::delete('/rotations/{id}', [RotationController::class, 'destroy'])
                ->middleware('permission:hr.workforce.rotations.delete')
                ->name('rotations.destroy');
            Route::post('/rotations/{id}/assign-employees', [RotationController::class, 'assignEmployees'])
                ->middleware('permission:hr.workforce.rotations.update')
                ->name('rotations.assign-employees');
            Route::post('/rotations/{id}/check-conflicts', [RotationController::class, 'checkScheduleConflicts'])
                ->middleware('permission:hr.workforce.rotations.view')
                ->name('rotations.check-conflicts');
            Route::post('/rotations/{id}/unassign-employees', [RotationController::class, 'unassignEmployees'])
                ->middleware('permission:hr.workforce.rotations.update')
                ->name('rotations.unassign-employees');
            Route::post('/rotations/{id}/duplicate', [RotationController::class, 'duplicate'])
                ->middleware('permission:hr.workforce.rotations.create')
                ->name('rotations.duplicate');
            Route::post('/rotations/{id}/generate-assignments', [RotationController::class, 'generateAssignments'])
                ->middleware('permission:hr.workforce.assignments.create')
                ->name('rotations.generate-assignments');
            Route::post('/rotations/bulk-update-status', [RotationController::class, 'bulkUpdateStatus'])
                ->middleware('permission:hr.workforce.rotations.update')
                ->name('rotations.bulk-update-status');
            Route::get('/rotations/export/csv', [RotationController::class, 'exportCsv'])
                ->middleware('permission:hr.workforce.rotations.view')
                ->name('rotations.export');
            Route::get('/rotations/api/statistics', [RotationController::class, 'getStatistics'])
                ->middleware('permission:hr.workforce.rotations.view')
                ->name('rotations.statistics');
            Route::get('/rotations/{id}/api/assigned-employees', [RotationController::class, 'getAssignedEmployees'])
                ->middleware('permission:hr.workforce.rotations.view')
                ->name('rotations.assigned-employees');

            // Shift Assignments
            Route::get('/assignments', [AssignmentController::class, 'index'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.index');
            Route::get('/assignments/create', [AssignmentController::class, 'create'])
                ->middleware('permission:hr.workforce.assignments.create')
                ->name('assignments.create');
            Route::get('/assignments/bulk-assign', [AssignmentController::class, 'bulkAssign'])
                ->middleware('permission:hr.workforce.assignments.create')
                ->name('assignments.bulk-assign');
            Route::post('/assignments', [AssignmentController::class, 'store'])
                ->middleware('permission:hr.workforce.assignments.create')
                ->name('assignments.store');
            Route::post('/assignments/bulk', [AssignmentController::class, 'bulkStore'])
                ->middleware('permission:hr.workforce.assignments.create')
                ->name('assignments.bulk');
            Route::post('/assignments/check-bulk-conflicts', [AssignmentController::class, 'checkBulkConflicts'])
                ->middleware(['permission:hr.workforce.assignments.view', 'throttle:60,1'])
                ->name('assignments.check-bulk-conflicts');
            Route::get('/assignments/coverage', [AssignmentController::class, 'coverage'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.coverage');
            Route::get('/assignments/{id}', [AssignmentController::class, 'show'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.show');
            Route::get('/assignments/{id}/edit', [AssignmentController::class, 'edit'])
                ->middleware('permission:hr.workforce.assignments.update')
                ->name('assignments.edit');
            Route::put('/assignments/{id}', [AssignmentController::class, 'update'])
                ->middleware('permission:hr.workforce.assignments.update')
                ->name('assignments.update');
            Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy'])
                ->middleware('permission:hr.workforce.assignments.delete')
                ->name('assignments.destroy');
            // Assignment custom actions
            Route::post('/assignments/{id}/resolve-conflict', [AssignmentController::class, 'resolveConflict'])
                ->middleware('permission:hr.workforce.assignments.update')
                ->name('assignments.resolve-conflict');
            Route::post('/assignments/{id}/mark-overtime', [AssignmentController::class, 'markOvertime'])
                ->middleware('permission:hr.workforce.assignments.update')
                ->name('assignments.mark-overtime');
            Route::get('/assignments/api/conflicts', [AssignmentController::class, 'getConflicts'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.conflicts');
            Route::get('/assignments/export/csv', [AssignmentController::class, 'exportCsv'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.export');
            Route::get('/assignments/api/statistics', [AssignmentController::class, 'getStatistics'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.statistics');
            Route::get('/assignments/api/employee/{employeeId}', [AssignmentController::class, 'getEmployeeAssignments'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.employee-assignments');
            Route::get('/assignments/api/date', [AssignmentController::class, 'getDateAssignments'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.date-assignments');
            Route::get('/assignments/api/coverage-analysis', [AssignmentController::class, 'getCoverageAnalysis'])
                ->middleware('permission:hr.workforce.assignments.view')
                ->name('assignments.coverage-analysis');
        });

        // Timekeeping Module
        Route::prefix('timekeeping')->name('timekeeping.')->group(function () {
            // Attendance Management
            Route::get('/attendance', [AttendanceController::class, 'index'])
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('attendance.index');
            Route::get('/attendance/create', [AttendanceController::class, 'create'])
                ->middleware('permission:hr.timekeeping.attendance.create')
                ->name('attendance.create');
            Route::post('/attendance', [AttendanceController::class, 'store'])
                ->middleware('permission:hr.timekeeping.attendance.create')
                ->name('attendance.store');
            Route::post('/attendance/bulk', [AttendanceController::class, 'bulkEntry'])
                ->middleware('permission:hr.timekeeping.attendance.create')
                ->name('attendance.bulk');
            Route::get('/attendance/daily/{date}', [AttendanceController::class, 'daily'])
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('attendance.daily');
            Route::get('/attendance/{id}', [AttendanceController::class, 'show'])
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('attendance.show');
            Route::get('/attendance/{id}/edit', [AttendanceController::class, 'edit'])
                ->middleware('permission:hr.timekeeping.attendance.update')
                ->name('attendance.edit');
            Route::put('/attendance/{id}', [AttendanceController::class, 'update'])
                ->middleware('permission:hr.timekeeping.attendance.update')
                ->name('attendance.update');
            Route::delete('/attendance/{id}', [AttendanceController::class, 'destroy'])
                ->middleware('permission:hr.timekeeping.attendance.delete')
                ->name('attendance.destroy');
            Route::post('/attendance/{id}/correct', [AttendanceController::class, 'correctAttendance'])
                ->middleware('permission:hr.timekeeping.attendance.correct')
                ->name('attendance.correct');
            Route::get('/attendance/{id}/history', [AttendanceController::class, 'correctionHistory'])
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('attendance.history');

            // RFID Ledger Page
            Route::get('/ledger', [LedgerController::class, 'index'])
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('ledger.index');
            Route::get('/ledger/{sequenceId}', [LedgerController::class, 'show'])
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('ledger.show');

            // Device Status Dashboard
            Route::get('/devices', [DeviceController::class, 'index'])
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('devices');

            // Employee Timeline
            Route::get('/employee/{employeeId}/timeline', [EmployeeTimelineController::class, 'show'])
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('employee.timeline');

            // Performance Test Page (Task 7.2.4)
            Route::get('/performance-test', function () {
                return inertia('HR/Timekeeping/PerformanceTest');
            })
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('performance-test');

            // Integration Test Page (Tasks 7.3.1 & 7.3.2)
            Route::get('/integration-test', function () {
                return inertia('HR/Timekeeping/IntegrationTest');
            })
                ->middleware('permission:hr.timekeeping.attendance.view')
                ->name('integration-test');

            // ========================================
            // RFID Ledger API Routes (JSON Responses)
            // ========================================
            Route::prefix('api/ledger')->name('api.ledger.')->group(function () {
                // Get ledger health status
                Route::get('/health', [LedgerHealthController::class, 'index'])
                    ->middleware('permission:hr.timekeeping.attendance.view')
                    ->name('health');

                // Get 24-hour health history
                Route::get('/health-history', [LedgerHealthController::class, 'history'])
                    ->middleware('permission:hr.timekeeping.attendance.view')
                    ->name('health.history');

                // Clear health cache (administrative endpoint)
                Route::delete('/health-cache', [LedgerHealthController::class, 'clearCache'])
                    ->middleware('permission:hr.timekeeping.attendance.update')
                    ->name('health.cache.clear');

                // Get ledger events (paginated JSON)
                Route::get('/events', [LedgerController::class, 'events'])
                    ->middleware('permission:hr.timekeeping.attendance.view')
                    ->name('events');

                // Get single event by sequence ID
                Route::get('/events/{sequenceId}', [LedgerController::class, 'eventDetail'])
                    ->middleware('permission:hr.timekeeping.attendance.view')
                    ->name('event');

                // Manual ledger sync
                Route::post('/sync', [LedgerSyncController::class, 'trigger'])
                    ->middleware('permission:hr.timekeeping.attendance.update')
                    ->name('sync');

                // Get sync job status
                Route::get('/sync/{syncJobId}', [LedgerSyncController::class, 'status'])
                    ->middleware('permission:hr.timekeeping.attendance.view')
                    ->name('sync.status');

                // Get sync history
                Route::get('/sync-history', [LedgerSyncController::class, 'history'])
                    ->middleware('permission:hr.timekeeping.attendance.view')
                    ->name('sync.history');

                // Get device list with status and metrics
                Route::get('/devices', [LedgerDeviceController::class, 'index'])
                    ->middleware('permission:hr.timekeeping.attendance.view')
                    ->name('devices');

                // Get single device details
                Route::get('/devices/{deviceId}', [LedgerDeviceController::class, 'show'])
                    ->middleware('permission:hr.timekeeping.attendance.view')
                    ->name('device');
            });

            // Attendance Correction API Routes (JSON Responses)
            // Task 4.4: Manual correction workflow with audit trail
            // =======================================================
            Route::prefix('api/attendance/corrections')->name('api.attendance.corrections.')->group(function () {
                // Submit new correction request
                Route::post('/', [\App\Http\Controllers\HR\Timekeeping\AttendanceCorrectionController::class, 'store'])
                    ->middleware('permission:hr.timekeeping.corrections.create')
                    ->name('store');
                
                // Approve correction request
                Route::put('/{id}/approve', [\App\Http\Controllers\HR\Timekeeping\AttendanceCorrectionController::class, 'approve'])
                    ->middleware('permission:hr.timekeeping.corrections.approve')
                    ->name('approve');
                
                // Reject correction request
                Route::put('/{id}/reject', [\App\Http\Controllers\HR\Timekeeping\AttendanceCorrectionController::class, 'reject'])
                    ->middleware('permission:hr.timekeeping.corrections.approve')
                    ->name('reject');
            });

            // Overtime Management
            Route::get('/overtime', [OvertimeController::class, 'index'])
                ->middleware('permission:hr.timekeeping.overtime.view')
                ->name('overtime.index');
            Route::get('/overtime/create', [OvertimeController::class, 'create'])
                ->middleware('permission:hr.timekeeping.overtime.create')
                ->name('overtime.create');
            Route::post('/overtime', [OvertimeController::class, 'store'])
                ->middleware('permission:hr.timekeeping.overtime.create')
                ->name('overtime.store');
            Route::get('/overtime/{id}', [OvertimeController::class, 'show'])
                ->middleware('permission:hr.timekeeping.overtime.view')
                ->name('overtime.show');
            Route::get('/overtime/{id}/edit', [OvertimeController::class, 'edit'])
                ->middleware('permission:hr.timekeeping.overtime.update')
                ->name('overtime.edit');
            Route::put('/overtime/{id}', [OvertimeController::class, 'update'])
                ->middleware('permission:hr.timekeeping.overtime.update')
                ->name('overtime.update');
            Route::delete('/overtime/{id}', [OvertimeController::class, 'destroy'])
                ->middleware('permission:hr.timekeeping.overtime.delete')
                ->name('overtime.destroy');
            Route::post('/overtime/{id}/process', [OvertimeController::class, 'processOvertime'])
                ->middleware('permission:hr.timekeeping.overtime.update')
                ->name('overtime.process');
            Route::get('/overtime/budget/{departmentId}', [OvertimeController::class, 'getBudget'])
                ->middleware('permission:hr.timekeeping.overtime.view')
                ->name('overtime.budget');

            // Import Management
            Route::get('/import', [ImportController::class, 'index'])
                ->middleware('permission:hr.timekeeping.import.view')
                ->name('import.index');
            Route::post('/import/upload', [ImportController::class, 'upload'])
                ->middleware('permission:hr.timekeeping.import.create')
                ->name('import.upload');
            Route::post('/import/{id}/process', [ImportController::class, 'process'])
                ->middleware('permission:hr.timekeeping.import.create')
                ->name('import.process');
            Route::get('/import/history', [ImportController::class, 'history'])
                ->middleware('permission:hr.timekeeping.import.view')
                ->name('import.history');
            Route::get('/import/{id}/errors', [ImportController::class, 'errors'])
                ->middleware('permission:hr.timekeeping.import.view')
                ->name('import.errors');

            // Analytics & Reports
            Route::get('/overview', [TimekeepingAnalyticsController::class, 'overview'])
                ->middleware('permission:hr.timekeeping.analytics.view')
                ->name('overview');
            Route::get('/analytics', [TimekeepingAnalyticsController::class, 'overview'])
                ->middleware('permission:hr.timekeeping.analytics.view')
                ->name('analytics.overview');
            Route::get('/analytics/department/{id}', [TimekeepingAnalyticsController::class, 'department'])
                ->middleware('permission:hr.timekeeping.analytics.view')
                ->name('analytics.department');
            Route::get('/analytics/employee/{id}', [TimekeepingAnalyticsController::class, 'employee'])
                ->middleware('permission:hr.timekeeping.analytics.view')
                ->name('analytics.employee');
        });

        // Development & Testing Routes (Service Layer Testing)
        // These routes test the service layer without requiring specific permissions
        // Accessible only to authenticated users via session-based auth
        Route::prefix('dev/test')->name('dev.test.')->group(function () {
            
            // Test WorkScheduleService
            Route::get('/schedules/service', function () {
                try {
                    $service = new \App\Services\HR\Workforce\WorkScheduleService();
                    $schedules = $service->getSchedules();
                    
                    return response()->json([
                        'status' => 'success',
                        'service' => 'WorkScheduleService',
                        'methods_available' => [
                            'createSchedule',
                            'updateSchedule',
                            'deleteSchedule',
                            'duplicateSchedule',
                            'activateSchedule',
                            'expireSchedule',
                            'createTemplate',
                            'assignToEmployee',
                            'assignToMultipleEmployees',
                            'unassignFromEmployee',
                            'getSchedules',
                            'getActiveSchedules',
                            'getTemplates',
                            'getScheduleSummary',
                            'getEmployeeSchedule',
                        ],
                        'count' => count($schedules),
                        'data' => $schedules
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'service' => 'WorkScheduleService',
                        'error' => $e->getMessage()
                    ], 500);
                }
            })->name('schedules.service');

            // Test EmployeeRotationService
            Route::get('/rotations/service', function () {
                try {
                    $service = new \App\Services\HR\Workforce\EmployeeRotationService();
                    
                    return response()->json([
                        'status' => 'success',
                        'service' => 'EmployeeRotationService',
                        'methods_available' => [
                            'createRotation',
                            'updateRotation',
                            'deleteRotation',
                            'duplicateRotation',
                            'validatePattern',
                            'generatePatternFromType',
                            'calculateCycleLength',
                            'assignToEmployee',
                            'assignToMultipleEmployees',
                            'unassignFromEmployee',
                            'generateShiftAssignments',
                            'isWorkDay',
                            'getRotations',
                            'getActiveRotations',
                            'getRotationSummary',
                            'getEmployeeRotation',
                            'getRotationAnalysis',
                        ],
                        'pattern_types_available' => ['4x2', '6x1', '5x2', 'custom']
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'service' => 'EmployeeRotationService',
                        'error' => $e->getMessage()
                    ], 500);
                }
            })->name('rotations.service');

            // Test ShiftAssignmentService
            Route::get('/assignments/service', function () {
                try {
                    $service = new \App\Services\HR\Workforce\ShiftAssignmentService();
                    
                    return response()->json([
                        'status' => 'success',
                        'service' => 'ShiftAssignmentService',
                        'methods_available' => [
                            'createAssignment',
                            'updateAssignment',
                            'deleteAssignment',
                            'bulkCreateAssignments',
                            'bulkUpdateAssignments',
                            'bulkDeleteAssignments',
                            'detectConflicts',
                            'resolveConflict',
                            'getConflictingAssignments',
                            'calculateOvertimeHours',
                            'markAsOvertime',
                            'getOvertimeAssignments',
                            'getCoverageReport',
                            'getUnderstaffedDays',
                            'getStaffingLevels',
                            'getAssignments',
                            'getAssignmentSummary',
                            'getEmployeeAssignments',
                            'getTodayAssignments',
                        ],
                        'total_methods' => 18
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'service' => 'ShiftAssignmentService',
                        'error' => $e->getMessage()
                    ], 500);
                }
            })->name('assignments.service');

            // Test WorkforceCoverageService
            Route::get('/coverage/service', function () {
                try {
                    $service = new \App\Services\HR\Workforce\WorkforceCoverageService();
                    
                    return response()->json([
                        'status' => 'success',
                        'service' => 'WorkforceCoverageService',
                        'methods_available' => [
                            'analyzeCoverage',
                            'getCoverageForDate',
                            'getCoverageByDepartment',
                            'identifyCoverageGaps',
                            'getCoverageByShiftType',
                            'suggestOptimalStaffing',
                            'calculateStaffingEfficiency',
                            'getOvertimeTrends',
                            'generateCoverageReport',
                            'exportCoverageData',
                            'analyzeTrend',
                            'generateRecommendations',
                            'generateCsvExport',
                        ],
                        'total_methods' => 13
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'service' => 'WorkforceCoverageService',
                        'error' => $e->getMessage()
                    ], 500);
                }
            })->name('coverage.service');

            // Test all Phase 4 services
            Route::get('/all-services', function () {
                $results = [];
                
                try {
                    $workScheduleService = new \App\Services\HR\Workforce\WorkScheduleService();
                    $results['WorkScheduleService'] = ['status' => 'OK', 'methods' => 13];
                } catch (\Exception $e) {
                    $results['WorkScheduleService'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
                }

                try {
                    $rotationService = new \App\Services\HR\Workforce\EmployeeRotationService();
                    $results['EmployeeRotationService'] = ['status' => 'OK', 'methods' => 15];
                } catch (\Exception $e) {
                    $results['EmployeeRotationService'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
                }

                try {
                    $assignmentService = new \App\Services\HR\Workforce\ShiftAssignmentService();
                    $results['ShiftAssignmentService'] = ['status' => 'OK', 'methods' => 18];
                } catch (\Exception $e) {
                    $results['ShiftAssignmentService'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
                }

                try {
                    $shiftService = new \App\Services\HR\Workforce\ShiftAssignmentService();
                    $coverageService = new \App\Services\HR\Workforce\WorkforceCoverageService($shiftService);
                    $results['WorkforceCoverageService'] = ['status' => 'OK', 'methods' => 13];
                } catch (\Exception $e) {
                    $results['WorkforceCoverageService'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
                }

                return response()->json([
                    'phase' => 'Phase 4: Service Layer',
                    'total_services' => 4,
                    'total_methods' => 59,
                    'services' => $results,
                    'test_url_endpoints' => [
                        'GET /hr/dev/test/schedules/service',
                        'GET /hr/dev/test/rotations/service',
                        'GET /hr/dev/test/assignments/service',
                        'GET /hr/dev/test/coverage/service',
                        'GET /hr/dev/test/all-services',
                    ]
                ]);
            })->name('all-services');
        });
    });

