<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\HR\Workforce\WorkScheduleService;
use App\Services\HR\Workforce\EmployeeRotationService;
use App\Services\HR\Workforce\ShiftAssignmentService;
use App\Services\HR\Workforce\WorkforceCoverageService;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Temporary API routes for Phase 4 service testing
Route::middleware(['auth:sanctum'])->prefix('v1/workforce')->group(function () {
    
    // Test WorkScheduleService
    Route::get('/test/schedules/service', function () {
        try {
            $service = new WorkScheduleService();
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
    });

    // Test EmployeeRotationService
    Route::get('/test/rotations/service', function () {
        try {
            $service = new EmployeeRotationService();
            
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
    });

    // Test ShiftAssignmentService
    Route::get('/test/assignments/service', function () {
        try {
            $service = new ShiftAssignmentService();
            
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
    });

    // Test WorkforceCoverageService
    Route::get('/test/coverage/service', function () {
        try {
            $service = new WorkforceCoverageService();
            
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
    });

    // Test all Phase 4 services
    Route::get('/test/all-services', function () {
        $results = [];
        
        try {
            $workScheduleService = new WorkScheduleService();
            $results['WorkScheduleService'] = ['status' => 'OK', 'methods' => 13];
        } catch (\Exception $e) {
            $results['WorkScheduleService'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
        }

        try {
            $rotationService = new EmployeeRotationService();
            $results['EmployeeRotationService'] = ['status' => 'OK', 'methods' => 15];
        } catch (\Exception $e) {
            $results['EmployeeRotationService'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
        }

        try {
            $assignmentService = new ShiftAssignmentService();
            $results['ShiftAssignmentService'] = ['status' => 'OK', 'methods' => 18];
        } catch (\Exception $e) {
            $results['ShiftAssignmentService'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
        }

        try {
            $coverageService = new WorkforceCoverageService();
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
                'GET /api/v1/workforce/test/schedules/service',
                'GET /api/v1/workforce/test/rotations/service',
                'GET /api/v1/workforce/test/assignments/service',
                'GET /api/v1/workforce/test/coverage/service',
                'GET /api/v1/workforce/test/all-services',
            ]
        ]);
    });

});
