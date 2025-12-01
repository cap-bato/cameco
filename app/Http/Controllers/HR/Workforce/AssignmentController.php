<?php

namespace App\Http\Controllers\HR\Workforce;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Workforce\StoreShiftAssignmentRequest;
use App\Http\Requests\HR\Workforce\UpdateShiftAssignmentRequest;
use App\Http\Requests\HR\Workforce\BulkAssignShiftsRequest;
use App\Models\ShiftAssignment;
use App\Services\HR\Workforce\ShiftAssignmentService;
use App\Services\HR\Workforce\WorkforceCoverageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    protected ShiftAssignmentService $shiftAssignmentService;
    protected WorkforceCoverageService $workforceCoverageService;

    public function __construct(
        ShiftAssignmentService $shiftAssignmentService,
        WorkforceCoverageService $workforceCoverageService
    ) {
        $this->shiftAssignmentService = $shiftAssignmentService;
        $this->workforceCoverageService = $workforceCoverageService;
    }

    /**
     * Display a listing of shift assignments.
     */
    public function index(Request $request): Response
    {
        $assignments = $this->shiftAssignmentService->getAssignments();
        
        // Transform assignments to include formatted data
        $transformedAssignments = $assignments->map(function ($assignment) {
            // Find conflicting shifts if has_conflict is true
            $conflictingShift = null;
            if ($assignment->has_conflict) {
                $conflictingShift = ShiftAssignment::with('employee.profile')
                    ->where('employee_id', $assignment->employee_id)
                    ->where('date', $assignment->date)
                    ->where('id', '!=', $assignment->id)
                    ->where('deleted_at', null)
                    ->first();
            }
            
            return [
                'id' => $assignment->id,
                'employee_id' => $assignment->employee_id,
                'employee_name' => "{$assignment->employee?->profile?->first_name} {$assignment->employee?->profile?->last_name}",
                'employee_number' => $assignment->employee?->employee_number,
                'schedule_id' => $assignment->schedule_id,
                'schedule_name' => $assignment->schedule?->name,
                'date' => $assignment->date?->format('Y-m-d'),
                'shift_start' => $assignment->shift_start,
                'shift_end' => $assignment->shift_end,
                'shift_type' => $assignment->shift_type,
                'location' => $assignment->location,
                'department_id' => $assignment->department_id,
                'department_name' => $assignment->department?->name,
                'is_overtime' => $assignment->is_overtime,
                'overtime_hours' => $assignment->overtime_hours,
                'status' => $assignment->status,
                'has_conflict' => $assignment->has_conflict,
                'conflict_reason' => $assignment->conflict_reason,
                'conflicting_assignment_id' => $conflictingShift?->id,
                'conflicting_employee_name' => $conflictingShift ? "{$conflictingShift->employee?->profile?->first_name} {$conflictingShift->employee?->profile?->last_name}" : null,
                'conflicting_shift_date' => $conflictingShift ? \Carbon\Carbon::parse($conflictingShift->date)->format('Y-m-d') : null,
                'conflicting_shift_start' => $conflictingShift?->shift_start,
                'conflicting_shift_end' => $conflictingShift?->shift_end,
                'created_at' => $assignment->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        // Calculate summary stats
        $todayAssignments = $assignments->filter(fn ($a) => $a->date->isToday());
        $summary = [
            'total_assignments' => $assignments->count(),
            'todays_shifts' => $todayAssignments->count(),
            'coverage_percentage' => $assignments->count() > 0 ? round(($todayAssignments->count() / 5) * 100) : 0, // Assuming 5 is required staff
            'overtime_hours' => $assignments->sum('overtime_hours'),
            'conflicts_count' => $assignments->where('has_conflict', true)->count(),
            'understaffed_days' => 0, // Calculate if needed
        ];

        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();
        $employees = \App\Models\Employee::with('profile:id,first_name,last_name', 'department:id,name')
            ->select('id', 'employee_number', 'department_id', 'profile_id')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'full_name' => "{$emp->profile?->first_name} {$emp->profile?->last_name}",
                'department_id' => $emp->department_id,
                'department_name' => $emp->department?->name,
            ])
            ->toArray();
        $schedules = \App\Models\WorkSchedule::where('status', 'active')->get(['id', 'name', 'monday_start', 'monday_end'])->toArray();

        $filters = [
            'search' => $request->input('search', ''),
            'department_id' => $request->input('department_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'has_conflict' => $request->input('has_conflict'),
        ];

        return Inertia::render('HR/Workforce/Assignments/Index', [
            'assignments' => $transformedAssignments,
            'summary' => $summary,
            'departments' => $departments,
            'employees' => $employees,
            'schedules' => $schedules,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new shift assignment.
     */
    public function create(): Response
    {
        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();
        $employees = \App\Models\Employee::with('profile:id,first_name,last_name', 'department:id,name')
            ->select('id', 'employee_number', 'department_id', 'profile_id')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'full_name' => "{$emp->profile?->first_name} {$emp->profile?->last_name}",
                'department_id' => $emp->department_id,
                'department_name' => $emp->department?->name,
            ])
            ->toArray();
        $schedules = \App\Models\WorkSchedule::where('status', 'active')->get(['id', 'name'])->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ])->toArray();
        $shiftTypes = ['morning', 'afternoon', 'evening', 'night', 'custom'];

        return Inertia::render('HR/Workforce/Assignments/Create', [
            'departments' => $departments,
            'employees' => $employees,
            'schedules' => $schedules,
            'shiftTypes' => $shiftTypes,
        ]);
    }

    /**
     * Store a newly created shift assignment in storage.
     */
    public function store(StoreShiftAssignmentRequest $request)
    {
        $this->shiftAssignmentService->createAssignment(
            $request->validated(),
            auth()->user()
        );

        return redirect()->route('hr.workforce.assignments.index')
            ->with('success', 'Shift assignment created successfully.');
    }

    /**
     * Display the specified shift assignment.
     */
    public function show(string $id): Response
    {
        $assignment = ShiftAssignment::with(['employee', 'schedule', 'createdBy'])->findOrFail($id);

        return Inertia::render('HR/Workforce/Assignments/Show', [
            'assignment' => $assignment,
        ]);
    }

    /**
     * Show the form for editing the specified shift assignment.
     */
    public function edit(string $id): Response
    {
        $assignment = ShiftAssignment::findOrFail($id);
        $employees = \App\Models\Employee::with('profile:id,first_name,last_name', 'department:id,name')
            ->select('id', 'employee_number', 'department_id', 'profile_id')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'full_name' => "{$emp->profile?->first_name} {$emp->profile?->last_name}",
                'department_id' => $emp->department_id,
                'department_name' => $emp->department?->name,
            ])
            ->toArray();
        $schedules = \App\Models\WorkSchedule::where('status', 'active')->get(['id', 'name'])->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ])->toArray();
        $shiftTypes = ['morning', 'afternoon', 'evening', 'night', 'custom'];

        return Inertia::render('HR/Workforce/Assignments/Edit', [
            'assignment' => $assignment,
            'employees' => $employees,
            'schedules' => $schedules,
            'shiftTypes' => $shiftTypes,
        ]);
    }

    /**
     * Update the specified shift assignment in storage.
     */
    public function update(UpdateShiftAssignmentRequest $request, string $id)
    {
        $assignment = ShiftAssignment::findOrFail($id);
        $this->shiftAssignmentService->updateAssignment($assignment, $request->validated());

        return redirect()->route('hr.workforce.assignments.index')
            ->with('success', 'Shift assignment updated successfully.');
    }

    /**
     * Remove the specified shift assignment from storage.
     */
    public function destroy(string $id)
    {
        $assignment = ShiftAssignment::findOrFail($id);
        $this->shiftAssignmentService->deleteAssignment($assignment);

        return redirect()->route('hr.workforce.assignments.index')
            ->with('success', 'Shift assignment deleted successfully.');
    }

    /**
     * Show the bulk assignment page.
     */
    public function bulkAssign(): Response
    {
        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();
        $employees = \App\Models\Employee::with('profile:id,first_name,last_name', 'department:id,name')
            ->select('id', 'employee_number', 'department_id', 'profile_id')
            ->get()
            ->map(fn ($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'full_name' => "{$emp->profile?->first_name} {$emp->profile?->last_name}",
                'department_id' => $emp->department_id,
                'department_name' => $emp->department?->name,
            ])
            ->toArray();
        $schedules = \App\Models\WorkSchedule::where('status', 'active')->get(['id', 'name'])->toArray();

        return Inertia::render('HR/Workforce/Assignments/BulkAssign', [
            'departments' => $departments,
            'employees' => $employees,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Check for conflicts in bulk assignment before creation.
     */
    public function checkBulkConflicts(Request $request)
    {
        $employeeIds = $request->input('employee_ids', []);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $shiftStart = $request->input('shift_start');
        $shiftEnd = $request->input('shift_end');

        $conflicts = [];

        // Check each employee for conflicts across the date range
        foreach ($employeeIds as $employeeId) {
            $conflictingAssignments = ShiftAssignment::where('employee_id', $employeeId)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->where('deleted_at', null)
                ->get(['id', 'date', 'shift_start', 'shift_end']);

            if ($conflictingAssignments->isNotEmpty()) {
                $employee = \App\Models\Employee::with('profile:id,first_name,last_name')
                    ->find($employeeId);

                // Ensure dates are properly formatted as array
                $conflictingDates = $conflictingAssignments
                    ->map(function ($a) {
                        $date = is_string($a->date) ? \Carbon\Carbon::parse($a->date) : $a->date;
                        return $date->format('Y-m-d');
                    })
                    ->unique()
                    ->values()
                    ->toArray();

                $conflicts[] = [
                    'employee_id' => (int) $employeeId,
                    'employee_name' => "{$employee?->profile?->first_name} {$employee?->profile?->last_name}",
                    'conflicting_dates' => $conflictingDates,
                    'conflict_count' => (int) count($conflictingDates),
                ];
            }
        }

        return response()->json([
            'conflicts' => $conflicts,
            'has_conflicts' => count($conflicts) > 0,
            'total_conflicts' => count($conflicts),
        ]);
    }

    /**
     * Bulk create shift assignments.
     */
    public function bulkStore(BulkAssignShiftsRequest $request)
    {
        $employeeIds = $request->input('employee_ids', []);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $scheduleId = $request->input('schedule_id');
        $shiftStart = $request->input('shift_start');
        $shiftEnd = $request->input('shift_end');
        $allowConflicts = (bool) $request->input('allow_conflicts', false);

        // Build assignments array for each employee and date in range
        $assignmentsData = [];
        $currentDate = \Carbon\Carbon::parse($dateFrom);
        $endDate = \Carbon\Carbon::parse($dateTo);

        while ($currentDate <= $endDate) {
            foreach ($employeeIds as $employeeId) {
                $assignmentsData[] = [
                    'employee_id' => $employeeId,
                    'schedule_id' => $scheduleId,
                    'date' => $currentDate->format('Y-m-d'),
                    'shift_start' => $shiftStart,
                    'shift_end' => $shiftEnd,
                    'shift_type' => $request->input('shift_type', 'standard'),
                    'status' => 'scheduled',
                    'is_overtime' => $request->input('is_overtime', false),
                    'department_id' => $request->input('department_id'),
                    'location' => $request->input('location'),
                ];
            }
            $currentDate->addDay();
        }

        try {
            $created = $this->shiftAssignmentService->bulkCreateAssignments(
                $assignmentsData,
                auth()->user()
            );

            return redirect()->route('hr.workforce.assignments.index')
                ->with('success', "{$created->count()} shift assignment(s) created successfully.");
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Handle duplicate assignments
            return redirect()->back()
                ->with('error', 'Some assignments could not be created because they already exist. Please review and try again.')
                ->withInput();
        }
    }

    /**
     * Detect conflicts for a shift assignment.
     */
    public function detectConflicts(Request $request, string $id)
    {
        $assignment = ShiftAssignment::findOrFail($id);
        $conflicts = $this->shiftAssignmentService->detectConflicts($assignment);

        return response()->json([
            'has_conflicts' => count($conflicts) > 0,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Get all conflicts in the system.
     */
    public function getConflicts(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $conflictingAssignments = ShiftAssignment::where('has_conflict', true)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->with(['employee', 'schedule'])
            ->get();

        return response()->json($conflictingAssignments);
    }

    /**
     * Calculate overtime hours for an assignment.
     */
    public function calculateOvertime(Request $request, string $id)
    {
        $assignment = ShiftAssignment::findOrFail($id);
        $overtimeHours = $this->shiftAssignmentService->calculateOvertimeHours($assignment);

        return response()->json([
            'overtime_hours' => $overtimeHours,
            'is_overtime' => $overtimeHours > 0,
        ]);
    }

    /**
     * Get workforce coverage report.
     */
    public function getCoverageReport(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth());
        $dateTo = $request->input('date_to', now()->endOfMonth());
        $departmentId = $request->input('department_id');

        $report = $this->shiftAssignmentService->getCoverageReport(
            $dateFrom,
            $dateTo,
            $departmentId
        );

        return response()->json($report);
    }

    /**
     * Analyze coverage by department.
     */
    public function analyzeCoverage(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth());
        $dateTo = $request->input('date_to', now()->endOfMonth());

        $coverage = $this->workforceCoverageService->analyzeCoverage($dateFrom, $dateTo);

        return response()->json($coverage);
    }

    /**
     * Get coverage by department.
     */
    public function getCoverageByDepartment(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth());
        $dateTo = $request->input('date_to', now()->endOfMonth());

        $departmentCoverage = $this->workforceCoverageService->getCoverageByDepartment($dateFrom, $dateTo);

        return response()->json($departmentCoverage);
    }

    /**
     * Export coverage data.
     */
    public function exportCoverage(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth());
        $dateTo = $request->input('date_to', now()->endOfMonth());

        $filename = 'coverage_' . date('Y-m-d_H-i-s') . '.csv';
        $path = storage_path("app/exports/{$filename}");

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = fopen($path, 'w');
        fputcsv($file, ['Department', 'Date', 'Total Employees', 'Assigned', 'Coverage %', 'Gaps']);

        $coverage = $this->workforceCoverageService->getCoverageByDepartment($dateFrom, $dateTo);

        foreach ($coverage as $dept) {
            fputcsv($file, [
                $dept['department_name'],
                $dept['date'],
                $dept['total_employees'],
                $dept['assigned'],
                $dept['coverage_percentage'],
                $dept['gaps'],
            ]);
        }

        fclose($file);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    /**
     * Identify coverage gaps.
     */
    public function identifyGaps(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth());
        $dateTo = $request->input('date_to', now()->endOfMonth());

        $gaps = $this->workforceCoverageService->identifyCoverageGaps($dateFrom, $dateTo);

        return response()->json($gaps);
    }

    /**
     * Get assignment statistics and analytics.
     */
    public function getStatistics()
    {
        $assignments = $this->shiftAssignmentService->getAssignments();

        $statistics = [
            'total_assignments' => $assignments->count(),
            'scheduled_assignments' => $assignments->where('status', 'scheduled')->count(),
            'completed_assignments' => $assignments->where('status', 'completed')->count(),
            'cancelled_assignments' => $assignments->where('status', 'cancelled')->count(),
            'assignments_with_conflicts' => $assignments->where('has_conflict', true)->count(),
            'overtime_assignments' => $assignments->where('is_overtime', true)->count(),
        ];

        return response()->json($statistics);
    }

    /**
     * Get available employees for assignment.
     */
    public function getAvailableEmployees(Request $request)
    {
        $departmentId = $request->input('department_id');
        $date = $request->input('date', now()->format('Y-m-d'));

        $query = \App\Models\Employee::all(['id', 'employee_number', 'first_name', 'last_name', 'department_id']);

        if ($departmentId) {
            $query = $query->where('department_id', $departmentId);
        }

        return response()->json($query->values());
    }

    /**
     * Bulk update assignment status.
     */
    public function bulkUpdateStatus(Request $request)
    {
        $assignmentIds = $request->input('assignment_ids', []);
        $status = $request->input('status');

        foreach ($assignmentIds as $assignmentId) {
            $assignment = ShiftAssignment::find($assignmentId);
            if ($assignment) {
                $assignment->update(['status' => $status]);
            }
        }

        return redirect()->route('hr.workforce.assignments.index')
            ->with('success', count($assignmentIds) . " assignment(s) status updated to '{$status}' successfully.");
    }
}
