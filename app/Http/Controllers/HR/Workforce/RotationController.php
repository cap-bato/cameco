<?php

namespace App\Http\Controllers\HR\Workforce;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Workforce\StoreEmployeeRotationRequest;
use App\Http\Requests\HR\Workforce\UpdateEmployeeRotationRequest;
use App\Models\EmployeeRotation;
use App\Services\HR\Workforce\EmployeeRotationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RotationController extends Controller
{
    protected EmployeeRotationService $employeeRotationService;

    public function __construct(EmployeeRotationService $employeeRotationService)
    {
        $this->employeeRotationService = $employeeRotationService;
    }

    /**
     * Display a listing of employee rotations.
     */
    public function index(Request $request): Response
    {
        $rotations = $this->employeeRotationService->getRotations();
        
        // Calculate summary statistics
        $totalRotations = $rotations->count();
        $activeRotations = $rotations->where('is_active', true)->count();
        
        // Count total employees assigned across all rotations
        $employeesInRotation = \App\Models\RotationAssignment::distinct('employee_id')
            ->where('is_active', true)
            ->count();
        
        // Calculate coverage percentage
        $totalEmployees = \App\Models\Employee::count();
        $coveragePercentage = $totalEmployees > 0 
            ? round(($employeesInRotation / $totalEmployees) * 100, 2)
            : 0;
        
        $summary = [
            'total_rotations' => $totalRotations,
            'active_patterns' => $activeRotations,
            'employees_in_rotation' => $employeesInRotation,
            'coverage_percentage' => $coveragePercentage,
        ];

        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();

        $patternTemplates = [
            ['pattern_type' => '4x2', 'name' => '4 Days Work / 2 Days Rest'],
            ['pattern_type' => '5x2', 'name' => '5 Days Work / 2 Days Rest'],
            ['pattern_type' => '6x1', 'name' => '6 Days Work / 1 Day Rest'],
            ['pattern_type' => '3x3', 'name' => '3 Days Work / 3 Days Rest'],
            ['pattern_type' => '2x2', 'name' => '2 Days Work / 2 Days Rest'],
            ['pattern_type' => 'custom', 'name' => 'Custom Pattern'],
        ];

        $filters = [
            'search' => $request->input('search', ''),
            'department_id' => $request->input('department_id'),
            'pattern_type' => $request->input('pattern_type'),
            'is_active' => $request->input('is_active'),
        ];

        return Inertia::render('HR/Workforce/Rotations/Index', [
            'rotations' => $rotations,
            'summary' => $summary,
            'departments' => $departments,
            'pattern_templates' => $patternTemplates,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new rotation.
     */
    public function create(): Response
    {
        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();
        $patternTypes = ['4x2', '6x1', '5x2', '3x3', '2x2', 'custom'];

        return Inertia::render('HR/Workforce/Rotations/Create', [
            'departments' => $departments,
            'patternTypes' => $patternTypes,
        ]);
    }

    /**
     * Store a newly created rotation in storage.
     */
    public function store(StoreEmployeeRotationRequest $request)
    {
        $this->employeeRotationService->createRotation(
            $request->validated(),
            auth()->user()
        );

        return redirect()->route('hr.workforce.rotations.index')
            ->with('success', 'Rotation created successfully.');
    }

    /**
     * Display the specified rotation.
     */
    public function show(string $id): Response
    {
        $rotation = EmployeeRotation::with(['department', 'createdBy', 'rotationAssignments'])->findOrFail($id);

        return Inertia::render('HR/Workforce/Rotations/Show', [
            'rotation' => $rotation,
        ]);
    }

    /**
     * Show the form for editing the specified rotation.
     */
    public function edit(string $id): Response
    {
        $rotation = EmployeeRotation::findOrFail($id);
        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();
        $patternTypes = ['4x2', '6x1', '5x2', '3x3', '2x2', 'custom'];

        return Inertia::render('HR/Workforce/Rotations/Edit', [
            'rotation' => $rotation,
            'departments' => $departments,
            'patternTypes' => $patternTypes,
        ]);
    }

    /**
     * Update the specified rotation in storage.
     */
    public function update(UpdateEmployeeRotationRequest $request, string $id)
    {
        $rotation = EmployeeRotation::findOrFail($id);
        $this->employeeRotationService->updateRotation($rotation, $request->validated());

        return redirect()->route('hr.workforce.rotations.index')
            ->with('success', 'Rotation updated successfully.');
    }

    /**
     * Remove the specified rotation from storage.
     */
    public function destroy(string $id)
    {
        $rotation = EmployeeRotation::findOrFail($id);
        $this->employeeRotationService->deleteRotation($rotation);

        return redirect()->route('hr.workforce.rotations.index')
            ->with('success', 'Rotation deleted successfully.');
    }

    /**
     * Assign employees to a rotation.
     */
    public function assignEmployees(Request $request, string $id)
    {
        $rotation = EmployeeRotation::findOrFail($id);
        
        // Validate input
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'effective_date' => 'nullable|date',
        ]);
        
        $effectiveDate = $validated['effective_date'] 
            ? Carbon::parse($validated['effective_date'])
            : now();

        try {
            $this->employeeRotationService->assignToMultipleEmployees(
                $rotation, 
                $validated['employee_ids'], 
                $effectiveDate,
                null,
                auth()->user()
            );
        } catch (\Exception $e) {
            return redirect()->route('hr.workforce.rotations.index')
                ->with('error', 'Failed to assign employees: ' . $e->getMessage());
        }

        return redirect()->route('hr.workforce.rotations.index')
            ->with('success', count($validated['employee_ids']) . ' employee(s) assigned to rotation successfully.');
    }

    /**
     * Remove employees from a rotation.
     */
    public function unassignEmployees(Request $request, string $id)
    {
        $rotation = EmployeeRotation::findOrFail($id);
        $employeeIds = $request->input('employee_ids', []);

        foreach ($employeeIds as $employeeId) {
            $employee = \App\Models\Employee::find($employeeId);
            if ($employee) {
                $rotationAssignment = $rotation->employeeRotationAssignments()
                    ->where('employee_id', $employeeId)
                    ->first();
                if ($rotationAssignment) {
                    $rotationAssignment->delete();
                }
            }
        }

        return redirect()->route('hr.workforce.rotations.show', $id)
            ->with('success', count($employeeIds) . ' employee(s) removed from rotation successfully.');
    }

    /**
     * Duplicate an existing rotation.
     */
    public function duplicate(string $id)
    {
        $rotation = EmployeeRotation::findOrFail($id);
        $newName = request('name', $rotation->name . ' (Copy)');
        $newRotation = $this->employeeRotationService->duplicateRotation($rotation, $newName);

        return redirect()->route('hr.workforce.rotations.index')
            ->with('success', 'Rotation duplicated successfully.');
    }

    /**
     * Generate shift assignments from a rotation pattern.
     */
    public function generateShifts(Request $request, string $id)
    {
        $rotation = EmployeeRotation::findOrFail($id);
        $startDate = $request->input('start_date', now());
        $endDate = $request->input('end_date', now()->addMonths(3));

        $generatedCount = $this->employeeRotationService->generateShiftAssignments(
            $rotation,
            $startDate,
            $endDate
        );

        return redirect()->route('hr.workforce.rotations.show', $id)
            ->with('success', "{$generatedCount} shift assignment(s) generated successfully.");
    }

    /**
     * Validate a rotation pattern.
     */
    public function validatePattern(Request $request)
    {
        $patternJson = $request->input('pattern_json');
        $isValid = $this->employeeRotationService->validatePattern($patternJson);

        return response()->json([
            'valid' => $isValid,
            'cycle_length' => $isValid ? $this->employeeRotationService->calculateCycleLength($patternJson) : null,
        ]);
    }

    /**
     * Get rotation statistics and analytics.
     */
    public function getStatistics()
    {
        $rotations = $this->employeeRotationService->getRotations();

        $statistics = [
            'total_rotations' => $rotations->count(),
            'active_rotations' => $rotations->where('is_active', true)->count(),
            'inactive_rotations' => $rotations->where('is_active', false)->count(),
        ];

        return response()->json($statistics);
    }

    /**
     * Get available employees for assignment to a rotation.
     */
    public function getAvailableEmployees(Request $request)
    {
        $departmentId = $request->input('department_id');
        
        $query = \App\Models\Employee::with(['profile', 'department'])
            ->where('status', 'active');

        if ($departmentId) {
            $query = $query->where('department_id', $departmentId);
        }

        $employees = $query->get();

        // Format response with department names
        $employees = $employees->map(function ($emp) {
            return [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'first_name' => $emp->profile?->first_name ?? '',
                'last_name' => $emp->profile?->last_name ?? '',
                'department_id' => $emp->department_id,
                'department_name' => $emp->department?->name ?? 'Unknown',
            ];
        });

        return response()->json($employees->values());
    }

    /**
     * Get employees assigned to a specific rotation.
     */
    public function getAssignedEmployees(string $id)
    {
        $rotation = EmployeeRotation::findOrFail($id);
        $assignments = $rotation->rotationAssignments()
            ->with(['employee.profile', 'employee.department'])
            ->get();

        // Format response with employee details
        $assignedEmployees = $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->employee->id,
                'employee_number' => $assignment->employee->employee_number,
                'first_name' => $assignment->employee->profile?->first_name ?? '',
                'last_name' => $assignment->employee->profile?->last_name ?? '',
                'department_name' => $assignment->employee->department?->name ?? 'Unknown',
                'effective_date' => $assignment->start_date,
            ];
        });

        return response()->json($assignedEmployees->values());
    }

    /**
     * Check for schedule-rotation conflicts before assigning shifts.
     *
     * Phase 3: Conflict Detection
     * Validates that a work schedule is compatible with employee rotation patterns.
     *
     * Route: POST /hr/workforce/rotations/{id}/check-conflicts
     * Request body:
     *   - employee_ids: array of employee IDs to check
     *   - work_schedule_id: ID of the proposed work schedule
     *   - effective_date: Date when schedule takes effect (YYYY-MM-DD)
     *   - duration_days: Number of days to analyze (recommended: 60)
     *
     * Response:
     *   {
     *       "has_errors": true,
     *       "has_warnings": true,
     *       "total_conflicts": 5,
     *       "error_count": 2,
     *       "warning_count": 3,
     *       "employees": [
     *           {
     *               "employee_id": 1,
     *               "name": "John Doe",
     *               "rotation_name": "4x2 Manufacturing",
     *               "conflict_count": 3,
     *               "error_count": 1,
     *               "warning_count": 2,
     *               "conflicts": [...]
     *           }
     *       ]
     *   }
     */
    public function checkScheduleConflicts(Request $request, string $id)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:employees,id',
            'work_schedule_id' => 'required|exists:work_schedules,id',
            'effective_date' => 'required|date_format:Y-m-d',
            'duration_days' => 'required|integer|min:1|max:365',
        ]);

        try {
            $workSchedule = \App\Models\WorkSchedule::findOrFail($validated['work_schedule_id']);
            $fromDate = Carbon::parse($validated['effective_date']);
            $toDate = $fromDate->clone()->addDays($validated['duration_days'] - 1);

            $allConflicts = [];
            $totalConflicts = 0;
            $totalErrors = 0;
            $totalWarnings = 0;
            $employeeResults = [];

            // Check conflicts for each employee
            foreach ($validated['employee_ids'] as $employeeId) {
                $employee = \App\Models\Employee::findOrFail($employeeId);
                
                // Get the employee's current rotation assignment
                $rotationAssignment = \App\Models\RotationAssignment::where('employee_id', $employeeId)
                    ->where('is_active', true)
                    ->with('rotation')
                    ->first();

                if (!$rotationAssignment) {
                    // Employee has no active rotation, no conflicts
                    $employeeResults[] = [
                        'employee_id' => $employeeId,
                        'name' => $employee->profile?->full_name ?? $employee->employee_number,
                        'rotation_name' => null,
                        'has_rotation' => false,
                        'conflict_count' => 0,
                        'error_count' => 0,
                        'warning_count' => 0,
                        'conflicts' => [],
                    ];
                    continue;
                }

                // Detect conflicts
                $conflicts = $this->employeeRotationService->detectScheduleRotationConflicts(
                    $rotationAssignment,
                    $workSchedule,
                    $fromDate,
                    $toDate
                );

                // Count error vs warning conflicts
                $errorConflicts = collect($conflicts)->where('severity', 'error')->count();
                $warningConflicts = collect($conflicts)->where('severity', 'warning')->count();

                $totalConflicts += count($conflicts);
                $totalErrors += $errorConflicts;
                $totalWarnings += $warningConflicts;

                $employeeResults[] = [
                    'employee_id' => $employeeId,
                    'name' => $employee->profile?->full_name ?? $employee->employee_number,
                    'rotation_name' => $rotationAssignment->rotation->name,
                    'has_rotation' => true,
                    'conflict_count' => count($conflicts),
                    'error_count' => $errorConflicts,
                    'warning_count' => $warningConflicts,
                    'conflicts' => $conflicts,
                ];
            }

            return response()->json([
                'success' => true,
                'has_errors' => $totalErrors > 0,
                'has_warnings' => $totalWarnings > 0,
                'total_conflicts' => $totalConflicts,
                'error_count' => $totalErrors,
                'warning_count' => $totalWarnings,
                'employees' => $employeeResults,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking conflicts: ' . $e->getMessage(),
            ], 500);
        }
    }
}
